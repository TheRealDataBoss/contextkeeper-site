<?php
/**
 * Redis Connector
 *
 * Config: { "rest_url": "https://xxx.upstash.io", "rest_token": "AXxxxx...", "prefix": "ck:" }
 *
 * Uses HTTP REST API (Upstash-compatible) since cPanel shared hosting
 * typically lacks the phpredis extension. Works with any Redis provider
 * that exposes an HTTP/REST interface.
 *
 * For self-hosted Redis with REST proxy, set rest_url to your proxy endpoint.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class RedisConnector implements ConnectorInterface
{
    private string $restUrl   = '';
    private string $restToken = '';
    private string $prefix    = 'ck:';

    public function connect(array $config): bool
    {
        if (empty($config['rest_url']) || empty($config['rest_token'])) {
            return false;
        }
        $this->restUrl   = rtrim($config['rest_url'], '/');
        $this->restToken = $config['rest_token'];
        $this->prefix    = $config['prefix'] ?? 'ck:';
        return true;
    }

    public function test(): bool
    {
        $resp = $this->redisCommand(['PING']);
        return $resp !== null && (($resp['result'] ?? '') === 'PONG');
    }

    public function list(string $path = '/'): array
    {
        $pattern = $this->prefix . '*';
        $resp = $this->redisCommand(['KEYS', $pattern]);

        if (!$resp || !isset($resp['result']) || !is_array($resp['result'])) {
            return [];
        }

        $items = [];
        foreach ($resp['result'] as $key) {
            $name = substr($key, strlen($this->prefix));
            $items[] = [
                'name' => $name,
                'path' => $name,
                'type' => 'file',
                'size' => 0,
            ];
        }

        sort($items);
        return $items;
    }

    public function read(string $path): string
    {
        $key = $this->prefix . trim($path, '/');
        $resp = $this->redisCommand(['GET', $key]);

        if ($resp && isset($resp['result']) && is_string($resp['result'])) {
            return $resp['result'];
        }
        return '';
    }

    public function write(string $path, string $content): bool
    {
        $key = $this->prefix . trim($path, '/');
        $resp = $this->redisCommand(['SET', $key, $content]);
        return $resp !== null && ($resp['result'] ?? '') === 'OK';
    }

    public function sync(int $projectId): array
    {
        $files = $this->list('/');
        return [
            'files_synced' => count($files),
            'errors'       => [],
            'source'       => 'redis:' . $this->restUrl . '/' . $this->prefix . '*',
        ];
    }

    public function getType(): string { return 'redis'; }
    public function getName(): string { return 'Redis'; }

    private function redisCommand(array $command): ?array
    {
        $ch = curl_init($this->restUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($command),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->restToken,
                'Content-Type: application/json',
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
