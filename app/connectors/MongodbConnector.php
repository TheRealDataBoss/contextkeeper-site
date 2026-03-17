<?php
/**
 * MongoDB Connector
 *
 * Config: { "connection_string": "mongodb+srv://user:pass@cluster.mongodb.net/db", "database": "contextkeeper", "collection": "state" }
 *
 * For MongoDB Atlas: uses the Atlas Data API (REST).
 * Config alt: { "data_api_url": "https://data.mongodb-api.com/app/.../endpoint/data/v1", "api_key": "xxx", "database": "contextkeeper", "collection": "state" }
 *
 * Falls back to Data API if connection_string is not directly usable from cPanel
 * (which lacks the mongodb PHP extension). This is the production-safe path.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class MongodbConnector implements ConnectorInterface
{
    private string $dataApiUrl  = '';
    private string $apiKey      = '';
    private string $database    = 'contextkeeper';
    private string $collection  = 'state';
    private string $dataSource  = '';

    public function connect(array $config): bool
    {
        // Prefer Data API config (works from any hosting without extensions)
        if (!empty($config['data_api_url']) && !empty($config['api_key'])) {
            $this->dataApiUrl = rtrim($config['data_api_url'], '/');
            $this->apiKey     = $config['api_key'];
            $this->dataSource = $config['data_source'] ?? $config['cluster_name'] ?? 'Cluster0';
        } elseif (!empty($config['connection_string'])) {
            // Parse Atlas cluster name from connection string for Data API
            // mongodb+srv://user:pass@cluster0.xxxxx.mongodb.net/db
            if (preg_match('/@([^\/]+)/', $config['connection_string'], $m)) {
                $clusterHost = $m[1];
                // Extract cluster name (first segment before dots)
                $parts = explode('.', $clusterHost);
                $this->dataSource = $parts[0] ?? 'Cluster0';
            }
            $this->dataApiUrl = $config['data_api_url'] ?? '';
            $this->apiKey     = $config['api_key'] ?? '';

            if (empty($this->dataApiUrl) || empty($this->apiKey)) {
                return false;
            }
        } else {
            return false;
        }

        $this->database   = $config['database'] ?? 'contextkeeper';
        $this->collection = $config['collection'] ?? 'state';
        return true;
    }

    public function test(): bool
    {
        $resp = $this->dataApiRequest('findOne', [
            'filter' => ['_test' => true],
            'limit'  => 1,
        ]);
        // A successful request returns a document key even if no match
        return $resp !== null && array_key_exists('document', $resp);
    }

    public function list(string $path = '/'): array
    {
        $resp = $this->dataApiRequest('find', [
            'filter'     => [],
            'projection' => ['key' => 1, '_id' => 0],
            'sort'       => ['key' => 1],
            'limit'      => 1000,
        ]);

        if (!$resp || !isset($resp['documents'])) {
            return [];
        }

        $items = [];
        foreach ($resp['documents'] as $doc) {
            $key = $doc['key'] ?? '';
            $items[] = [
                'name' => $key,
                'path' => $key,
                'type' => 'file',
                'size' => 0,
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        $key = trim($path, '/');
        $resp = $this->dataApiRequest('findOne', [
            'filter' => ['key' => $key],
        ]);

        if ($resp && isset($resp['document']['value'])) {
            return $resp['document']['value'];
        }
        return '';
    }

    public function write(string $path, string $content): bool
    {
        $key = trim($path, '/');
        $resp = $this->dataApiRequest('updateOne', [
            'filter' => ['key' => $key],
            'update' => [
                '$set' => [
                    'key'        => $key,
                    'value'      => $content,
                    'updated_at' => gmdate('Y-m-d\TH:i:s\Z'),
                ],
            ],
            'upsert' => true,
        ]);

        return $resp !== null && (
            ($resp['matchedCount'] ?? 0) > 0 ||
            ($resp['upsertedId'] ?? null) !== null
        );
    }

    public function sync(int $projectId): array
    {
        $files = $this->list('/');
        return [
            'files_synced' => count($files),
            'errors'       => [],
            'source'       => "mongodb://{$this->dataSource}/{$this->database}.{$this->collection}",
        ];
    }

    public function getType(): string { return 'mongodb'; }
    public function getName(): string { return 'MongoDB'; }

    private function dataApiRequest(string $action, array $body): ?array
    {
        $url = $this->dataApiUrl . '/action/' . $action;

        $payload = array_merge([
            'dataSource' => $this->dataSource,
            'database'   => $this->database,
            'collection' => $this->collection,
        ], $body);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'api-key: ' . $this->apiKey,
                'User-Agent: contextkeeper/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 || $response === false) {
            return null;
        }

        return json_decode($response, true);
    }
}
