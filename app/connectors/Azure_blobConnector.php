<?php
/**
 * Azure Blob Storage Connector
 *
 * Config: { "account_name": "myaccount", "account_key": "base64key==", "container": "contextkeeper" }
 *
 * Uses Azure Blob REST API with SharedKey authorization. No SDK required.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class Azure_blobConnector implements ConnectorInterface
{
    private string $accountName = '';
    private string $accountKey  = '';
    private string $container   = '';

    public function connect(array $config): bool
    {
        if (empty($config['account_name']) || empty($config['account_key']) || empty($config['container'])) {
            return false;
        }
        $this->accountName = $config['account_name'];
        $this->accountKey  = $config['account_key'];
        $this->container   = $config['container'];
        return true;
    }

    public function test(): bool
    {
        $resp = $this->azureRequest('GET', '', ['restype' => 'container']);
        return $resp['http_code'] >= 200 && $resp['http_code'] < 300;
    }

    public function list(string $path = '/'): array
    {
        $prefix = trim($path, '/');
        $params = [
            'restype' => 'container',
            'comp'    => 'list',
        ];
        if ($prefix !== '' && $prefix !== '/') {
            $params['prefix'] = $prefix . '/';
        }

        $resp = $this->azureRequest('GET', '', $params);
        if ($resp['http_code'] !== 200) {
            return [];
        }

        $items = [];
        $xml = @simplexml_load_string($resp['body']);
        if (!$xml || !isset($xml->Blobs)) {
            return [];
        }

        foreach ($xml->Blobs->Blob as $blob) {
            $name = (string)$blob->Name;
            $items[] = [
                'name' => basename($name),
                'path' => $name,
                'type' => 'file',
                'size' => (int)($blob->Properties->{'Content-Length'} ?? 0),
            ];
        }

        foreach ($xml->Blobs->BlobPrefix as $bp) {
            $p = rtrim((string)$bp->Name, '/');
            $items[] = [
                'name' => basename($p),
                'path' => (string)$bp->Name,
                'type' => 'dir',
                'size' => 0,
            ];
        }

        return $items;
    }

    public function read(string $path): string
    {
        $blobName = ltrim($path, '/');
        $resp = $this->azureRequest('GET', $blobName);
        return ($resp['http_code'] >= 200 && $resp['http_code'] < 300) ? $resp['body'] : '';
    }

    public function write(string $path, string $content): bool
    {
        $blobName = ltrim($path, '/');
        $resp = $this->azureRequest('PUT', $blobName, [], $content, [
            'x-ms-blob-type' => 'BlockBlob',
        ]);
        return $resp['http_code'] >= 200 && $resp['http_code'] < 300;
    }

    public function sync(int $projectId): array
    {
        $files = $this->list('.contextkeeper');
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
            'source'       => "azure://{$this->accountName}/{$this->container}",
        ];
    }

    public function getType(): string { return 'azure_blob'; }
    public function getName(): string { return 'Azure Blob Storage'; }

    private function azureRequest(
        string $method,
        string $blobName,
        array $queryParams = [],
        ?string $body = null,
        array $extraHeaders = []
    ): array {
        $host = "{$this->accountName}.blob.core.windows.net";
        $path = "/{$this->container}";
        if ($blobName !== '') {
            $path .= '/' . ltrim($blobName, '/');
        }

        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $version = '2020-10-02';
        $contentLength = $body !== null ? strlen($body) : 0;

        ksort($queryParams);
        $queryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        // Canonicalized headers
        $msHeaders = array_merge([
            'x-ms-date'    => $date,
            'x-ms-version' => $version,
        ], $extraHeaders);
        ksort($msHeaders);

        $canonicalizedHeaders = '';
        foreach ($msHeaders as $k => $v) {
            $canonicalizedHeaders .= strtolower($k) . ':' . trim($v) . "\n";
        }

        // Canonicalized resource
        $canonicalizedResource = "/{$this->accountName}{$path}";
        foreach ($queryParams as $k => $v) {
            $canonicalizedResource .= "\n" . strtolower($k) . ':' . $v;
        }

        // String to sign
        $contentType = ($method === 'PUT' && $body !== null) ? 'application/octet-stream' : '';
        $stringToSign = implode("\n", [
            $method,                    // verb
            '',                         // Content-Encoding
            '',                         // Content-Language
            $contentLength > 0 ? (string)$contentLength : '', // Content-Length
            '',                         // Content-MD5
            $contentType,               // Content-Type
            '',                         // Date
            '',                         // If-Modified-Since
            '',                         // If-Match
            '',                         // If-None-Match
            '',                         // If-Unmodified-Since
            '',                         // Range
            rtrim($canonicalizedHeaders, "\n"),
            $canonicalizedResource,
        ]);

        $signature = base64_encode(
            hash_hmac('sha256', $stringToSign, base64_decode($this->accountKey), true)
        );

        $authHeader = "SharedKey {$this->accountName}:{$signature}";

        // Build URL
        $url = "https://{$host}{$path}";
        if ($queryString) {
            $url .= '?' . $queryString;
        }

        $curlHeaders = [
            "Authorization: {$authHeader}",
            "x-ms-date: {$date}",
            "x-ms-version: {$version}",
        ];
        foreach ($extraHeaders as $k => $v) {
            if ($k !== 'x-ms-date' && $k !== 'x-ms-version') {
                $curlHeaders[] = "{$k}: {$v}";
            }
        }
        if ($contentType) {
            $curlHeaders[] = "Content-Type: {$contentType}";
        }

        $ch = curl_init($url);
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $curlHeaders,
        ];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);
        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['http_code' => $httpCode, 'body' => $responseBody ?: ''];
    }
}
