<?php
/**
 * Cloudflare R2 Connector
 *
 * Config: { "account_id": "xxx", "access_key": "xxx", "secret_key": "xxx", "bucket": "contextkeeper", "prefix": "" }
 *
 * Cloudflare R2 is S3-compatible. Uses AWS Signature V4 against the R2 endpoint.
 * Endpoint: https://{account_id}.r2.cloudflarestorage.com
 */

require_once __DIR__ . '/ConnectorInterface.php';

class Cloudflare_r2Connector implements ConnectorInterface
{
    private string $accountId = '';
    private string $accessKey = '';
    private string $secretKey = '';
    private string $bucket    = '';
    private string $prefix    = '';

    public function connect(array $config): bool
    {
        if (empty($config['account_id']) || empty($config['access_key']) ||
            empty($config['secret_key']) || empty($config['bucket'])) {
            return false;
        }
        $this->accountId = $config['account_id'];
        $this->accessKey = $config['access_key'];
        $this->secretKey = $config['secret_key'];
        $this->bucket    = $config['bucket'];
        $this->prefix    = rtrim($config['prefix'] ?? '', '/');
        return true;
    }

    public function test(): bool
    {
        $resp = $this->r2Request('HEAD', '/', null, ['max-keys' => '0']);
        return $resp['http_code'] >= 200 && $resp['http_code'] < 300;
    }

    public function list(string $path = '/'): array
    {
        $prefix = $this->prefix ? $this->prefix . '/' : '';
        $path = trim($path, '/');
        if ($path !== '' && $path !== '/') {
            $prefix .= $path . '/';
        }

        $resp = $this->r2Request('GET', '/', null, [
            'list-type' => '2',
            'prefix'    => $prefix,
            'delimiter' => '/',
            'max-keys'  => '1000',
        ]);

        if ($resp['http_code'] !== 200) {
            return [];
        }

        $items = [];
        $xml = @simplexml_load_string($resp['body']);
        if (!$xml) return [];

        foreach ($xml->Contents as $obj) {
            $key = (string)$obj->Key;
            if ($key === $prefix) continue;
            $items[] = [
                'name' => basename($key),
                'path' => $key,
                'type' => 'file',
                'size' => (int)$obj->Size,
            ];
        }

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
        $resp = $this->r2Request('GET', '/' . $key);
        return ($resp['http_code'] >= 200 && $resp['http_code'] < 300) ? $resp['body'] : '';
    }

    public function write(string $path, string $content): bool
    {
        $key = ltrim($path, '/');
        $resp = $this->r2Request('PUT', '/' . $key, $content);
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
            'errors'       => $errors,
            'source'       => "r2://{$this->bucket}/{$prefix}",
        ];
    }

    public function getType(): string { return 'cloudflare_r2'; }
    public function getName(): string { return 'Cloudflare R2'; }

    /**
     * AWS Signature V4 request against the R2 endpoint.
     * R2 uses 'auto' as the region for signing.
     */
    private function r2Request(string $method, string $path, ?string $body = null, array $queryParams = []): array
    {
        $host = "{$this->bucket}.{$this->accountId}.r2.cloudflarestorage.com";
        $region = 'auto';
        $service = 's3';
        $path = '/' . ltrim($path, '/');
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        ksort($queryParams);
        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        $payloadHash = hash('sha256', $body ?? '');

        // Canonical headers
        $headers = [
            'host'                 => $host,
            'x-amz-content-sha256' => $payloadHash,
            'x-amz-date'          => $datetime,
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

        $canonicalRequest = implode("\n", [
            $method,
            $path,
            $queryString,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $scope = "{$date}/{$region}/{$service}/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $datetime,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $authHeader = "AWS4-HMAC-SHA256 Credential={$this->accessKey}/{$scope}, "
                    . "SignedHeaders={$signedHeaders}, Signature={$signature}";

        $url = "https://{$host}{$path}";
        if ($queryString) $url .= '?' . $queryString;

        $ch = curl_init($url);
        $curlHeaders = [
            "Authorization: {$authHeader}",
            "x-amz-content-sha256: {$payloadHash}",
            "x-amz-date: {$datetime}",
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
