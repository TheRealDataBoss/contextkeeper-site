<?php
/**
 * Supabase Connector
 *
 * Config: { "url": "https://xxx.supabase.co", "anon_key": "eyJhbGci...", "service_role_key": "optional", "table": "contextkeeper_state" }
 *
 * Uses Supabase REST API (PostgREST) with anon or service_role key.
 * Stores contextkeeper state as rows in a Supabase table.
 * Auto-creates table via RPC if service_role_key is provided.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class SupabaseConnector implements ConnectorInterface
{
    private string $url            = '';
    private string $apiKey         = '';
    private string $serviceRoleKey = '';
    private string $table          = 'contextkeeper_state';

    public function connect(array $config): bool
    {
        if (empty($config['url']) || (empty($config['anon_key']) && empty($config['service_role_key']))) {
            return false;
        }
        $this->url            = rtrim($config['url'], '/');
        $this->apiKey         = $config['service_role_key'] ?? $config['anon_key'] ?? '';
        $this->serviceRoleKey = $config['service_role_key'] ?? '';
        $this->table          = $config['table'] ?? 'contextkeeper_state';
        return true;
    }

    public function test(): bool
    {
        // Query the health endpoint or try to read from the table
        $resp = $this->supabaseRequest('GET', '/rest/v1/', ['limit' => '0']);
        // Supabase returns 200 even for empty results if auth is valid
        return $resp['http_code'] >= 200 && $resp['http_code'] < 300;
    }

    public function list(string $path = '/'): array
    {
        $resp = $this->supabaseRequest('GET', "/rest/v1/{$this->table}", [
            'select'   => 'key,value',
            'order'    => 'key.asc',
            'limit'    => '1000',
        ]);

        if ($resp['http_code'] < 200 || $resp['http_code'] >= 300) {
            return [];
        }

        $rows = json_decode($resp['body'], true);
        if (!is_array($rows)) {
            return [];
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'name' => $row['key'],
                'path' => $row['key'],
                'type' => 'file',
                'size' => strlen($row['value'] ?? ''),
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        $key = trim($path, '/');
        $resp = $this->supabaseRequest('GET', "/rest/v1/{$this->table}", [
            'select' => 'value',
            'key'    => 'eq.' . $key,
            'limit'  => '1',
        ]);

        if ($resp['http_code'] < 200 || $resp['http_code'] >= 300) {
            return '';
        }

        $rows = json_decode($resp['body'], true);
        if (is_array($rows) && !empty($rows[0]['value'])) {
            return $rows[0]['value'];
        }
        return '';
    }

    public function write(string $path, string $content): bool
    {
        $key = trim($path, '/');

        // Upsert (requires the table to have key as a unique/primary column)
        $body = json_encode([
            'key'        => $key,
            'value'      => $content,
            'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ]);

        $resp = $this->supabaseRequest('POST', "/rest/v1/{$this->table}", [], $body, [
            'Prefer'       => 'resolution=merge-duplicates',
            'Content-Type' => 'application/json',
        ]);

        return $resp['http_code'] >= 200 && $resp['http_code'] < 300;
    }

    public function sync(int $projectId): array
    {
        $files = $this->list('/');
        return [
            'files_synced' => count($files),
            'errors'       => [],
            'source'       => 'supabase:' . $this->url . '/' . $this->table,
        ];
    }

    public function getType(): string { return 'supabase'; }
    public function getName(): string { return 'Supabase'; }

    private function supabaseRequest(
        string $method,
        string $path,
        array $queryParams = [],
        ?string $body = null,
        array $extraHeaders = []
    ): array {
        $url = $this->url . $path;
        if (!empty($queryParams)) {
            $url .= '?' . http_build_query($queryParams);
        }

        $headers = array_merge([
            'apikey'        => $this->apiKey,
            'Authorization' => 'Bearer ' . $this->apiKey,
            'User-Agent'    => 'contextkeeper/1.0',
        ], $extraHeaders);

        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
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
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['http_code' => $httpCode, 'body' => $response ?: ''];
    }
}
