<?php
/**
 * Bitbucket Connector
 *
 * Config: { "username": "user", "app_password": "xxx", "workspace": "ws", "repo_slug": "repo", "branch": "main" }
 *
 * Uses Bitbucket Cloud REST API 2.0 with HTTP Basic auth (app passwords).
 */

require_once __DIR__ . '/ConnectorInterface.php';

class BitbucketConnector implements ConnectorInterface
{
    private string $username    = '';
    private string $appPassword = '';
    private string $workspace   = '';
    private string $repoSlug   = '';
    private string $branch     = 'main';
    private string $api        = 'https://api.bitbucket.org/2.0';

    public function connect(array $config): bool
    {
        if (empty($config['username']) || empty($config['app_password']) ||
            empty($config['workspace']) || empty($config['repo_slug'])) {
            return false;
        }
        $this->username    = $config['username'];
        $this->appPassword = $config['app_password'];
        $this->workspace   = $config['workspace'];
        $this->repoSlug    = $config['repo_slug'];
        $this->branch      = $config['branch'] ?? 'main';
        return true;
    }

    public function test(): bool
    {
        $resp = $this->request('GET', "/repositories/{$this->workspace}/{$this->repoSlug}");
        return $resp !== null && isset($resp['uuid']);
    }

    public function list(string $path = '/'): array
    {
        $path = ltrim($path, '/');
        $endpoint = "/repositories/{$this->workspace}/{$this->repoSlug}/src/{$this->branch}/{$path}";
        $resp = $this->request('GET', $endpoint);

        if (!$resp || !isset($resp['values'])) {
            return [];
        }

        $items = [];
        foreach ($resp['values'] as $item) {
            $items[] = [
                'name' => basename($item['path']),
                'path' => $item['path'],
                'type' => $item['type'] === 'commit_directory' ? 'dir' : 'file',
                'size' => $item['size'] ?? 0,
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        $path = ltrim($path, '/');
        $url = $this->api . "/repositories/{$this->workspace}/{$this->repoSlug}/src/{$this->branch}/{$path}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERPWD        => $this->username . ':' . $this->appPassword,
            CURLOPT_HTTPHEADER     => ['User-Agent: contextkeeper/1.0'],
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ($httpCode >= 200 && $httpCode < 300 && $response !== false) ? $response : '';
    }

    public function write(string $path, string $content): bool
    {
        $path = ltrim($path, '/');
        $url = $this->api . "/repositories/{$this->workspace}/{$this->repoSlug}/src";

        $ch = curl_init($url);
        $postFields = [
            $path    => $content,
            'message' => 'contextkeeper sync: update ' . basename($path),
            'branch'  => $this->branch,
        ];

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_USERPWD        => $this->username . ':' . $this->appPassword,
            CURLOPT_HTTPHEADER     => ['User-Agent: contextkeeper/1.0'],
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
            'source'       => "bitbucket:{$this->workspace}/{$this->repoSlug}@{$this->branch}",
        ];
    }

    public function getType(): string { return 'bitbucket'; }
    public function getName(): string { return 'Bitbucket'; }

    private function request(string $method, string $endpoint, ?array $body = null): ?array
    {
        $url = $this->api . $endpoint;
        $ch = curl_init($url);

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERPWD        => $this->username . ':' . $this->appPassword,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: contextkeeper/1.0',
                'Accept: application/json',
            ],
        ];

        if ($method !== 'GET') {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
            if ($body) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($body);
                $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
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
