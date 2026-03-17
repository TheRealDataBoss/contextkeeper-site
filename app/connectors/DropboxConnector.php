<?php
/**
 * Dropbox Connector
 *
 * Config: { "token": "sl.xxx", "root_path": "/contextkeeper" }
 *
 * Uses Dropbox HTTP API v2 with short-lived or long-lived access tokens.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class DropboxConnector implements ConnectorInterface
{
    private string $token    = '';
    private string $rootPath = '/contextkeeper';
    private string $api      = 'https://api.dropboxapi.com/2';
    private string $content  = 'https://content.dropboxapi.com/2';

    public function connect(array $config): bool
    {
        if (empty($config['token'])) {
            return false;
        }
        $this->token    = $config['token'];
        $this->rootPath = rtrim($config['root_path'] ?? '/contextkeeper', '/');
        return true;
    }

    public function test(): bool
    {
        $resp = $this->apiCall($this->api . '/users/get_current_account', null);
        return $resp !== null && isset($resp['account_id']);
    }

    public function list(string $path = '/'): array
    {
        $fullPath = $this->rootPath;
        $path = trim($path, '/');
        if ($path !== '' && $path !== '/') {
            $fullPath .= '/' . $path;
        }

        $resp = $this->apiCall($this->api . '/files/list_folder', [
            'path'                                => $fullPath,
            'recursive'                           => false,
            'include_media_info'                   => false,
            'include_deleted'                      => false,
            'include_has_explicit_shared_members'  => false,
        ]);

        if (!$resp || !isset($resp['entries'])) {
            return [];
        }

        $items = [];
        foreach ($resp['entries'] as $entry) {
            $items[] = [
                'name' => $entry['name'],
                'path' => $entry['path_display'],
                'type' => $entry['.tag'] === 'folder' ? 'dir' : 'file',
                'size' => $entry['size'] ?? 0,
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        $fullPath = $this->rootPath . '/' . ltrim($path, '/');

        $ch = curl_init($this->content . '/files/download');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Dropbox-API-Arg: ' . json_encode(['path' => $fullPath]),
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
        $fullPath = $this->rootPath . '/' . ltrim($path, '/');

        $ch = curl_init($this->content . '/files/upload');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
                'Dropbox-API-Arg: ' . json_encode([
                    'path'            => $fullPath,
                    'mode'            => 'overwrite',
                    'autorename'      => false,
                    'mute'            => true,
                ]),
                'Content-Type: application/octet-stream',
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
        $files = $this->list('/');
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
            'source'       => 'dropbox:' . $this->rootPath,
        ];
    }

    public function getType(): string { return 'dropbox'; }
    public function getName(): string { return 'Dropbox'; }

    private function apiCall(string $url, ?array $body): ?array
    {
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'User-Agent: contextkeeper/1.0',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
        ];

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
            $headers[] = 'Content-Type: application/json';
        } else {
            $opts[CURLOPT_POSTFIELDS] = 'null';
            $headers[] = 'Content-Type: application/json';
        }

        $opts[CURLOPT_HTTPHEADER] = $headers;
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
