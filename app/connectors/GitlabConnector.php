<?php
/**
 * GitLab Connector
 *
 * Config: { "token": "glpat-xxx", "project_id": "12345", "branch": "main", "base_url": "https://gitlab.com" }
 *
 * Uses GitLab REST API v4. Supports self-hosted instances via base_url.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class GitlabConnector implements ConnectorInterface
{
    private string $token     = '';
    private string $projectId = '';
    private string $branch    = 'main';
    private string $baseUrl   = 'https://gitlab.com';

    public function connect(array $config): bool
    {
        if (empty($config['token']) || empty($config['project_id'])) {
            return false;
        }
        $this->token     = $config['token'];
        $this->projectId = $config['project_id'];
        $this->branch    = $config['branch'] ?? 'main';
        $this->baseUrl   = rtrim($config['base_url'] ?? 'https://gitlab.com', '/');
        return true;
    }

    public function test(): bool
    {
        $resp = $this->request('GET', "/projects/{$this->projectId}");
        return $resp !== null && isset($resp['id']);
    }

    public function list(string $path = '/'): array
    {
        $path = ltrim($path, '/');
        $params = [
            'ref'      => $this->branch,
            'per_page' => 100,
        ];
        if ($path !== '' && $path !== '/') {
            $params['path'] = $path;
        }

        $endpoint = "/projects/{$this->projectId}/repository/tree?" . http_build_query($params);
        $resp = $this->request('GET', $endpoint);

        if (!is_array($resp)) {
            return [];
        }

        $items = [];
        foreach ($resp as $item) {
            $items[] = [
                'name' => $item['name'],
                'path' => $item['path'],
                'type' => $item['type'] === 'tree' ? 'dir' : 'file',
                'size' => 0,
                'id'   => $item['id'] ?? '',
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        $path = ltrim($path, '/');
        $encodedPath = rawurlencode($path);
        $endpoint = "/projects/{$this->projectId}/repository/files/{$encodedPath}/raw?ref={$this->branch}";

        $url = $this->baseUrl . '/api/v4' . $endpoint;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'PRIVATE-TOKEN: ' . $this->token,
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
        $encodedPath = rawurlencode($path);
        $endpoint = "/projects/{$this->projectId}/repository/files/{$encodedPath}";

        // Check if file exists
        $existing = $this->request('GET', "/projects/{$this->projectId}/repository/files/{$encodedPath}?ref={$this->branch}");
        $method = ($existing !== null && isset($existing['file_name'])) ? 'PUT' : 'POST';

        $body = [
            'branch'         => $this->branch,
            'content'        => $content,
            'commit_message' => 'contextkeeper sync: update ' . basename($path),
        ];

        $resp = $this->request($method, $endpoint, $body);
        return $resp !== null && isset($resp['file_path']);
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
            'source'       => "gitlab:{$this->baseUrl}/project/{$this->projectId}@{$this->branch}",
        ];
    }

    public function getType(): string { return 'gitlab'; }
    public function getName(): string { return 'GitLab'; }

    private function request(string $method, string $endpoint, ?array $body = null): ?array
    {
        $url = $this->baseUrl . '/api/v4' . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'PRIVATE-TOKEN: ' . $this->token,
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
