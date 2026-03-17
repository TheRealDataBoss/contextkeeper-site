<?php
/**
 * Hugging Face Connector
 *
 * Config: { "token": "hf_xxx", "repo_id": "username/repo-name", "repo_type": "model" }
 *
 * Uses Hugging Face Hub API. Supports model, dataset, and space repos.
 * Reads/writes files in the repo via the API.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class Hugging_faceConnector implements ConnectorInterface
{
    private string $token    = '';
    private string $repoId   = '';
    private string $repoType = 'model';
    private string $revision = 'main';
    private string $api      = 'https://huggingface.co/api';

    public function connect(array $config): bool
    {
        if (empty($config['token']) || empty($config['repo_id'])) {
            return false;
        }
        $this->token    = $config['token'];
        $this->repoId   = $config['repo_id'];
        $this->repoType = $config['repo_type'] ?? 'model';
        $this->revision = $config['revision'] ?? 'main';
        return true;
    }

    public function test(): bool
    {
        // Verify token by checking whoami
        $resp = $this->request('GET', 'https://huggingface.co/api/whoami-v2');
        return $resp !== null && isset($resp['name']);
    }

    public function list(string $path = '/'): array
    {
        $typePath = $this->repoTypePath();
        $url = "{$this->api}/{$typePath}/{$this->repoId}/tree/{$this->revision}";

        $path = trim($path, '/');
        if ($path !== '' && $path !== '/') {
            $url .= '/' . $path;
        }

        $resp = $this->request('GET', $url);
        if (!is_array($resp)) {
            return [];
        }

        $items = [];
        foreach ($resp as $item) {
            $items[] = [
                'name' => basename($item['path'] ?? ''),
                'path' => $item['path'] ?? '',
                'type' => ($item['type'] ?? '') === 'directory' ? 'dir' : 'file',
                'size' => $item['size'] ?? 0,
                'oid'  => $item['oid'] ?? '',
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        $path = ltrim($path, '/');
        $typePath = $this->repoTypePath();
        $url = "https://huggingface.co/{$typePath}/{$this->repoId}/resolve/{$this->revision}/{$path}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'User-Agent: contextkeeper/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 300 && $response !== false) ? $response : '';
    }

    public function write(string $path, string $content): bool
    {
        $path = ltrim($path, '/');
        $typePath = $this->repoTypePath();
        // Use the commit API to create/update files
        $url = "{$this->api}/{$typePath}/{$this->repoId}/commit/{$this->revision}";

        $payload = [
            'summary'    => 'contextkeeper sync: update ' . basename($path),
            'operations' => [[
                'key'     => 'file',
                'path'    => $path,
                'content' => base64_encode($content),
                'encoding' => 'base64',
            ]],
        ];

        // HF uses multipart for commits, but the JSON API works for small files
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Content-Type: application/json',
                'User-Agent: contextkeeper/1.0',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return $httpCode >= 200 && $httpCode < 300;
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
            'source'       => "huggingface:{$this->repoType}/{$this->repoId}@{$this->revision}",
        ];
    }

    public function getType(): string { return 'hugging_face'; }
    public function getName(): string { return 'Hugging Face'; }

    private function repoTypePath(): string
    {
        switch ($this->repoType) {
            case 'dataset':  return 'datasets';
            case 'space':    return 'spaces';
            default:         return 'models';
        }
    }

    private function request(string $method, string $url, ?array $body = null): ?array
    {
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'User-Agent: contextkeeper/1.0',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
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
