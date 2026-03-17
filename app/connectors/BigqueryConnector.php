<?php
/**
 * BigQuery Connector
 *
 * Config: { "service_account_json": "{...}", "project_id": "my-gcp-project", "dataset": "contextkeeper", "table": "state" }
 *
 * Uses BigQuery REST API v2 with service account JWT authentication.
 * Stores/retrieves contextkeeper state as rows in a BigQuery table.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class BigqueryConnector implements ConnectorInterface
{
    private array  $serviceAccount = [];
    private string $projectId      = '';
    private string $dataset        = 'contextkeeper';
    private string $table          = 'state';
    private string $accessToken    = '';
    private string $api            = 'https://bigquery.googleapis.com/bigquery/v2';

    public function connect(array $config): bool
    {
        if (empty($config['service_account_json']) || empty($config['project_id'])) {
            return false;
        }

        $sa = is_string($config['service_account_json'])
            ? json_decode($config['service_account_json'], true)
            : $config['service_account_json'];

        if (!$sa || empty($sa['client_email']) || empty($sa['private_key'])) {
            return false;
        }

        $this->serviceAccount = $sa;
        $this->projectId      = $config['project_id'];
        $this->dataset        = $config['dataset'] ?? 'contextkeeper';
        $this->table          = $config['table'] ?? 'state';
        return true;
    }

    public function test(): bool
    {
        try {
            $this->authenticate();
            $url = "{$this->api}/projects/{$this->projectId}/datasets/{$this->dataset}";
            $resp = $this->request('GET', $url);
            return $resp !== null && isset($resp['datasetReference']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function list(string $path = '/'): array
    {
        try {
            $this->authenticate();
            $url = "{$this->api}/projects/{$this->projectId}/datasets/{$this->dataset}/tables/{$this->table}/data?maxResults=1000";
            $resp = $this->request('GET', $url);

            if (!$resp || !isset($resp['rows'])) {
                return [];
            }

            $items = [];
            foreach ($resp['rows'] as $row) {
                $key = $row['f'][0]['v'] ?? '';
                $size = strlen($row['f'][1]['v'] ?? '');
                $items[] = [
                    'name' => $key,
                    'path' => $key,
                    'type' => 'file',
                    'size' => $size,
                ];
            }
            return $items;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function read(string $path): string
    {
        try {
            $this->authenticate();
            $key = trim($path, '/');
            $query = "SELECT value FROM `{$this->projectId}.{$this->dataset}.{$this->table}` WHERE key = @key LIMIT 1";

            $url = "{$this->api}/projects/{$this->projectId}/queries";
            $resp = $this->request('POST', $url, [
                'query'        => $query,
                'useLegacySql' => false,
                'queryParameters' => [[
                    'name'           => 'key',
                    'parameterType'  => ['type' => 'STRING'],
                    'parameterValue' => ['value' => $key],
                ]],
            ]);

            if ($resp && isset($resp['rows'][0]['f'][0]['v'])) {
                return $resp['rows'][0]['f'][0]['v'];
            }
            return '';
        } catch (\Exception $e) {
            return '';
        }
    }

    public function write(string $path, string $content): bool
    {
        try {
            $this->authenticate();
            $key = trim($path, '/');

            // Use streaming insert
            $url = "{$this->api}/projects/{$this->projectId}/datasets/{$this->dataset}/tables/{$this->table}/insertAll";
            $resp = $this->request('POST', $url, [
                'rows' => [[
                    'json' => [
                        'key'        => $key,
                        'value'      => $content,
                        'updated_at' => gmdate('Y-m-d H:i:s'),
                    ],
                ]],
            ]);

            return $resp !== null && empty($resp['insertErrors']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sync(int $projectId): array
    {
        $files = $this->list('/');
        return [
            'files_synced' => count($files),
            'errors'       => [],
            'source'       => "bigquery://{$this->projectId}.{$this->dataset}.{$this->table}",
        ];
    }

    public function getType(): string { return 'bigquery'; }
    public function getName(): string { return 'BigQuery'; }

    private function authenticate(): void
    {
        if ($this->accessToken !== '') {
            return;
        }

        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = base64_encode(json_encode([
            'iss'   => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/bigquery',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $signInput = $header . '.' . $claim;
        $privateKey = openssl_pkey_get_private($this->serviceAccount['private_key']);
        if (!$privateKey) {
            throw new \Exception('Invalid service account private key.');
        }

        openssl_sign($signInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        $jwt = $signInput . '.' . $this->base64UrlEncode($signature);

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resp, true);
        if (empty($data['access_token'])) {
            throw new \Exception('Failed to obtain BigQuery access token.');
        }

        $this->accessToken = $data['access_token'];
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function request(string $method, string $url, ?array $body = null): ?array
    {
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->accessToken,
            'User-Agent: contextkeeper/1.0',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($method !== 'GET') {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
            if ($body) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($body);
                $headers[] = 'Content-Type: application/json';
                $opts[CURLOPT_HTTPHEADER] = $headers;
            }
        }

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 || $response === false) {
            return null;
        }

        return json_decode($response, true);
    }
}
