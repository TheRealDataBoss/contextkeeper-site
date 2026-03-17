<?php
/**
 * Snowflake Connector
 *
 * Config: { "account": "xy12345.us-east-1", "user": "CK_USER", "password": "xxx", "warehouse": "COMPUTE_WH", "database": "CONTEXTKEEPER", "schema": "PUBLIC", "table": "STATE" }
 *
 * Uses Snowflake SQL REST API with username/password authentication.
 * Stores contextkeeper state as rows in a Snowflake table.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class SnowflakeConnector implements ConnectorInterface
{
    private string $account   = '';
    private string $user      = '';
    private string $password  = '';
    private string $warehouse = '';
    private string $database  = '';
    private string $schema    = 'PUBLIC';
    private string $table     = 'CONTEXTKEEPER_STATE';
    private string $token     = '';

    public function connect(array $config): bool
    {
        if (empty($config['account']) || empty($config['user']) || empty($config['password'])) {
            return false;
        }
        $this->account   = $config['account'];
        $this->user      = $config['user'];
        $this->password  = $config['password'];
        $this->warehouse = $config['warehouse'] ?? 'COMPUTE_WH';
        $this->database  = $config['database'] ?? 'CONTEXTKEEPER';
        $this->schema    = $config['schema'] ?? 'PUBLIC';
        $this->table     = $config['table'] ?? 'CONTEXTKEEPER_STATE';
        return true;
    }

    public function test(): bool
    {
        try {
            $this->authenticate();
            $resp = $this->executeQuery('SELECT CURRENT_VERSION() AS v');
            return $resp !== null && isset($resp['data']);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function list(string $path = '/'): array
    {
        try {
            $this->authenticate();
            $this->ensureTable();
            $fqTable = $this->fqTable();
            $resp = $this->executeQuery("SELECT key, LENGTH(value) AS size FROM {$fqTable} ORDER BY key");

            if (!$resp || !isset($resp['data'])) {
                return [];
            }

            $items = [];
            foreach ($resp['data'] as $row) {
                $items[] = [
                    'name' => $row[0],
                    'path' => $row[0],
                    'type' => 'file',
                    'size' => (int)$row[1],
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
            $fqTable = $this->fqTable();
            $escaped = str_replace("'", "''", $key);
            $resp = $this->executeQuery("SELECT value FROM {$fqTable} WHERE key = '{$escaped}' LIMIT 1");

            if ($resp && isset($resp['data'][0][0])) {
                return $resp['data'][0][0];
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
            $this->ensureTable();
            $key = trim($path, '/');
            $fqTable = $this->fqTable();
            $escapedKey = str_replace("'", "''", $key);
            $escapedVal = str_replace("'", "''", $content);

            $sql = "MERGE INTO {$fqTable} t USING (SELECT '{$escapedKey}' AS key) s "
                 . "ON t.key = s.key "
                 . "WHEN MATCHED THEN UPDATE SET value = '{$escapedVal}', updated_at = CURRENT_TIMESTAMP() "
                 . "WHEN NOT MATCHED THEN INSERT (key, value) VALUES ('{$escapedKey}', '{$escapedVal}')";

            $resp = $this->executeQuery($sql);
            return $resp !== null;
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
            'source'       => "snowflake://{$this->account}/{$this->database}.{$this->schema}.{$this->table}",
        ];
    }

    public function getType(): string { return 'snowflake'; }
    public function getName(): string { return 'Snowflake'; }

    private function authenticate(): void
    {
        if ($this->token !== '') {
            return;
        }

        $url = "https://{$this->account}.snowflakecomputing.com/session/v1/login-request";
        $body = [
            'data' => [
                'CLIENT_APP_ID'      => 'contextkeeper',
                'CLIENT_APP_VERSION' => '1.0',
                'ACCOUNT_NAME'       => $this->account,
                'LOGIN_NAME'         => $this->user,
                'PASSWORD'           => $this->password,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resp, true);
        if (empty($data['data']['token'])) {
            throw new \Exception('Snowflake authentication failed.');
        }

        $this->token = $data['data']['token'];
    }

    private function executeQuery(string $sql): ?array
    {
        $url = "https://{$this->account}.snowflakecomputing.com/queries/v1/query-request";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'sqlText'   => $sql,
                'warehouse' => $this->warehouse,
                'database'  => $this->database,
                'schema'    => $this->schema,
            ]),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Snowflake Token="' . $this->token . '"',
                'User-Agent: contextkeeper/1.0',
            ],
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 || $resp === false) {
            return null;
        }

        return json_decode($resp, true);
    }

    private function fqTable(): string
    {
        $db     = preg_replace('/[^A-Za-z0-9_]/', '', $this->database);
        $schema = preg_replace('/[^A-Za-z0-9_]/', '', $this->schema);
        $table  = preg_replace('/[^A-Za-z0-9_]/', '', $this->table);
        return "{$db}.{$schema}.{$table}";
    }

    private function ensureTable(): void
    {
        $fqTable = $this->fqTable();
        $this->executeQuery(
            "CREATE TABLE IF NOT EXISTS {$fqTable} ("
            . "key VARCHAR(500) PRIMARY KEY, "
            . "value VARCHAR(16777216), "
            . "updated_at TIMESTAMP_NTZ DEFAULT CURRENT_TIMESTAMP()"
            . ")"
        );
    }
}
