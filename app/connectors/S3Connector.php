<?php
/**
 * AWS S3 Connector
 *
 * Config: { "access_key": "AKIA...", "secret_key": "xxx", "bucket": "my-bucket", "region": "us-east-1", "prefix": "" }
 *
 * Uses raw REST API with AWS Signature V4 - no SDK needed.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class S3Connector implements ConnectorInterface
{
    private string $accessKey = '';
    private string $secretKey = '';
    private string $bucket    = '';
    private string $region    = 'us-east-1';
    private string $prefix    = '';

    public function connect(array $config): bool
    {
        if (empty($config['access_key']) || empty($config['secret_key']) || empty($config['bucket'])) {
            return false;
        }
        $this->accessKey = $config['access_key'];
        $this->secretKey = $config['secret_key'];
        $this->bucket    = $config['bucket'];
        $this->region    = $config['region'] ?? 'us-east-1';
        $this->prefix    = rtrim($config['prefix'] ?? '', '/');
        return true;
    }

    public function test(): bool
    {
        // HEAD bucket to check access
        $resp = $this->s3Request('HEAD', '/', null, ['max-keys' => '0']);
        return $resp['http_code'] >= 200 && $resp['http_code'] < 300;
    }

    public function list(string $path = '/'): array
    {
        $prefix = $this->prefix ? $this->prefix . '/' : '';
        $path = trim($path, '/');
        if ($path && $path !== '/') {
            $prefix .= $path . '/';
        }

        $resp = $this->s3Request('GET', '/', null, [
            'list-type' => '2',
            'prefix' => $prefix,
            'delimiter' => '/',
            'max-keys' => '1000',
        ]);

        if ($resp['http_code'] !== 200) {
            return [];
        }

        $items = [];
        $xml = @simplexml_load_string($resp['body']);
        if (!$xml) return [];

        // Files
        foreach ($xml->Contents as $obj) {
            $key = (string)$obj->Key;
            if ($key === $prefix) continue; // skip the prefix itself
            $items[] = [
                'name' => basename($key),
                'path' => $key,
                'type' => 'file',
                'size' => (int)$obj->Size,
            ];
        }

        // Directories (common prefixes)
        foreach ($xml->CommonPrefixes as $cp) {
            $p = rtrim((string)$cp->Prefix, '/');
            $items[] = [
                'name' => basename($p),
                'path' => (string)$cp->Prefix,
                'type' => 'dir',
                'size' => 0,
            ];
        }

        return $items;
    }

    public function read(string $path): string
    {
        $key = ltrim($path, '/');
        $resp = $this->s3Request('GET', '/' . $key);
        return $resp['http_code'] === 200 ? $resp['body'] : '';
    }

    public function write(string $path, string $content): bool
    {
        $key = ltrim($path, '/');
        $resp = $this->s3Request('PUT', '/' . $key, $content);
        return $resp['http_code'] >= 200 && $resp['http_code'] < 300;
    }

    public function sync(int $projectId): array
    {
        $prefix = $this->prefix ? $this->prefix . '/.contextkeeper/' : '.contextkeeper/';
        $files = $this->list(rtrim($prefix, '/'));
        $synced = 0;
        $errors = [];

        foreach ($files as $file) {
            if ($file['type'] === 'file') {
                try {
                    $this->read($file['path']);
                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = $file['path'] . ': ' . $e->getMessage();
                }
            }
        }

        return [
            'files_synced' => $synced,
            'errors' => $errors,
            'source' => "s3://{$this->bucket}/{$prefix}",
        ];
    }

    public function getType(): string { return 's3'; }
    public function getName(): string { return 'AWS S3'; }

    /**
     * AWS Signature V4 signed S3 request.
     */
    private function s3Request(string $method, string $path, ?string $body = null, array $queryParams = []): array
    {
        $host = "{$this->bucket}.s3.{$this->region}.amazonaws.com";
        $path = '/' . ltrim($path, '/');
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');
        $service = 's3';

        // Build query string
        ksort($queryParams);
        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $payloadHash = hash('sha256', $body ?? '');

        // Canonical headers
        $headers = [
            'host' => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date' => $datetime,
        ];
        if ($body !== null && $method === 'PUT') {
            $headers['content-length'] = (string)strlen($body);
        }

        ksort($headers);
        $canonicalHeaders = '';
        $signedHeadersList = [];
        foreach ($headers as $k => $v) {
            $canonicalHeaders .= strtolower($k) . ':' . trim($v) . "\n";
            $signedHeadersList[] = strtolower($k);
        }
        $signedHeaders = implode(';', $signedHeadersList);

        // Canonical request
        $canonicalRequest = implode("\n", [
            $method,
            $path,
            $queryString,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        // String to sign
        $scope = "$date/{$this->region}/$service/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $datetime,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        // Signing key
        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/$scope, "
                    . "SignedHeaders=$signedHeaders, Signature=$signature";

        // Build URL
        $url = "https://$host$path";
        if ($queryString) $url .= '?' . $queryString;

        // cURL
        $ch = curl_init($url);
        $curlHeaders = [
            "Authorization: $authHeader",
            "x-amz-content-sha256: $payloadHash",
            "x-amz-date: $datetime",
        ];
        if ($body !== null && $method === 'PUT') {
            $curlHeaders[] = 'Content-Length: ' . strlen($body);
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $curlHeaders,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['http_code' => $httpCode, 'body' => $responseBody ?: ''];
    }
}
