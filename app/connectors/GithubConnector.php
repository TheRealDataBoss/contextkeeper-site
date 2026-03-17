<?php
/**
 * GitHub Connector
 *
 * Config: { "token": "ghp_xxx", "owner": "user", "repo": "name", "branch": "main" }
 */

require_once __DIR__ . '/ConnectorInterface.php';

class GithubConnector implements ConnectorInterface
{
    private string $token = '';
    private string $owner = '';
    private string $repo  = '';
    private string $branch = 'main';
    private string $api = 'https://api.github.com';

    public function connect(array $config): bool
    {
        if (empty($config['token']) || empty($config['owner']) || empty($config['repo'])) {
            return false;
        }
        $this->token  = $config['token'];
        $this->owner  = $config['owner'];
        $this->repo   = $config['repo'];
        $this->branch = $config['branch'] ?? 'main';
        return true;
    }

    public function test(): bool
    {
        $resp = $this->request('GET', "/repos/{$this->owner}/{$this->repo}");
        return $resp !== null && isset($resp['id']);
    }

    public function list(string $path = '/'): array
    {
        $path = ltrim($path, '/');
        $endpoint = "/repos/{$this->owner}/{$this->repo}/contents/{$path}?ref={$this->branch}";
        $resp = $this->request('GET', $endpoint);

        if (!is_array($resp)) {
            return [];
        }

        // Single file returns an object, directory returns an array
        if (isset($resp['name'])) {
            $resp = [$resp];
        }

        $items = [];
        foreach ($resp as $item) {
            $items[] = [
                'name' => $item['name'],
                'path' => $item['path'],
                'type' => $item['type'], // 'file' or 'dir'
                'size' => $item['size'] ?? 0,
                'sha'  => $item['sha'] ?? '',
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        $path = ltrim($path, '/');
        $endpoint = "/repos/{$this->owner}/{$this->repo}/contents/{$path}?ref={$this->branch}";
        $resp = $this->request('GET', $endpoint);

        if (!$resp || empty($resp['content'])) {
            return '';
        }

        return base64_decode($resp['content']);
    }

    public function write(string $path, string $content): bool
    {
        $path = ltrim($path, '/');
        $endpoint = "/repos/{$this->owner}/{$this->repo}/contents/{$path}";

        // Check if file exists to get SHA for updates
        $existing = $this->request('GET', $endpoint . "?ref={$this->branch}");
        $sha = $existing['sha'] ?? null;

        $body = [
            'message' => 'contextkeeper sync: update ' . basename($path),
            'content' => base64_encode($content),
            'branch'  => $this->branch,
        ];
        if ($sha) {
            $body['sha'] = $sha;
        }

        $resp = $this->request('PUT', $endpoint, $body);
        return $resp !== null && isset($resp['content']);
    }

    public function sync(int $projectId): array
    {
        // Read .contextkeeper/ directory from repo
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
            'errors' => $errors,
            'source' => "github:{$this->owner}/{$this->repo}@{$this->branch}",
        ];
    }

    public function getType(): string { return 'github'; }
    public function getName(): string { return 'GitHub'; }

    private function request(string $method, string $endpoint, ?array $body = null): ?array
    {
        $url = $this->api . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Accept: application/vnd.github+json',
            'User-Agent: contextkeeper/1.0',
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($method === 'PUT' || $method === 'POST') {
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
