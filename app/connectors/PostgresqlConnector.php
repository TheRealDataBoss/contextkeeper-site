<?php
/**
 * PostgreSQL Connector
 *
 * Config: { "host": "db.example.com", "port": 5432, "dbname": "mydb", "user": "ck_user", "password": "xxx", "schema": "public", "table": "contextkeeper_state" }
 *
 * Stores and retrieves contextkeeper state in a PostgreSQL table.
 * Auto-creates the state table on first sync if it doesn't exist.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class PostgresqlConnector implements ConnectorInterface
{
    private ?PDO $pdo = null;
    private string $host     = '';
    private int    $port     = 5432;
    private string $dbname   = '';
    private string $user     = '';
    private string $password = '';
    private string $schema   = 'public';
    private string $table    = 'contextkeeper_state';

    public function connect(array $config): bool
    {
        if (empty($config['host']) || empty($config['dbname']) || empty($config['user'])) {
            return false;
        }

        $this->host     = $config['host'];
        $this->port     = (int)($config['port'] ?? 5432);
        $this->dbname   = $config['dbname'];
        $this->user     = $config['user'];
        $this->password  = $config['password'] ?? '';
        $this->schema   = $config['schema'] ?? 'public';
        $this->table    = $config['table'] ?? 'contextkeeper_state';

        return true;
    }

    public function test(): bool
    {
        try {
            $pdo = $this->getPdo();
            $stmt = $pdo->query('SELECT 1 AS ok');
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return isset($row['ok']) && $row['ok'] == 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function list(string $path = '/'): array
    {
        try {
            $pdo = $this->getPdo();
            $this->ensureTable($pdo);

            $fqTable = $this->fqTable();
            $stmt = $pdo->query("SELECT key, LENGTH(value) AS size, updated_at FROM {$fqTable} ORDER BY key");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $items = [];
            foreach ($rows as $row) {
                $items[] = [
                    'name' => $row['key'],
                    'path' => $row['key'],
                    'type' => 'file',
                    'size' => (int)$row['size'],
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
            $pdo = $this->getPdo();
            $fqTable = $this->fqTable();
            $stmt = $pdo->prepare("SELECT value FROM {$fqTable} WHERE key = ?");
            $stmt->execute([trim($path, '/')]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $row['value'] : '';
        } catch (\Exception $e) {
            return '';
        }
    }

    public function write(string $path, string $content): bool
    {
        try {
            $pdo = $this->getPdo();
            $this->ensureTable($pdo);
            $fqTable = $this->fqTable();
            $key = trim($path, '/');

            // Upsert
            $stmt = $pdo->prepare(
                "INSERT INTO {$fqTable} (key, value, updated_at) VALUES (?, ?, NOW())
                 ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = NOW()"
            );
            $stmt->execute([$key, $content]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function sync(int $projectId): array
    {
        $files = $this->list('/');
        return [
            'files_synced' => count($files),
            'errors' => [],
            'source' => "postgresql://{$this->host}:{$this->port}/{$this->dbname}",
        ];
    }

    public function getType(): string { return 'postgresql'; }
    public function getName(): string { return 'PostgreSQL'; }

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $dsn = "pgsql:host={$this->host};port={$this->port};dbname={$this->dbname}";
            $this->pdo = new PDO($dsn, $this->user, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);
        }
        return $this->pdo;
    }

    private function fqTable(): string
    {
        // Whitelist characters to prevent SQL injection in table/schema names
        $schema = preg_replace('/[^a-zA-Z0-9_]/', '', $this->schema);
        $table  = preg_replace('/[^a-zA-Z0-9_]/', '', $this->table);
        return "\"{$schema}\".\"{$table}\"";
    }

    private function ensureTable(PDO $pdo): void
    {
        $fqTable = $this->fqTable();
        $pdo->exec("CREATE TABLE IF NOT EXISTS {$fqTable} (
            key VARCHAR(500) PRIMARY KEY,
            value TEXT NOT NULL,
            updated_at TIMESTAMP DEFAULT NOW()
        )");
    }
}
