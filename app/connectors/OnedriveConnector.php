<?php
/**
 * OneDrive Connector
 *
 * Config: { "token": "EwBxxx...", "drive_id": "optional", "root_path": "/contextkeeper" }
 *
 * Uses Microsoft Graph API v1.0 with OAuth Bearer token.
 * If drive_id is omitted, uses the authenticated user's default drive.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class OnedriveConnector implements ConnectorInterface
{
    private string $token    = '';
    private string $driveId  = '';
    private string $rootPath = '/contextkeeper';
    private string $api      = 'https://graph.microsoft.com/v1.0';

    public function connect(array $config): bool
    {
        if (empty($config['token'])) {
            return false;
        }
        $this->token    = $config['token'];
        $this->driveId  = $config['drive_id'] ?? '';
        $this->rootPath = rtrim($config['root_path'] ?? '/contextkeeper', '/');
        return true;
    }

    public function test(): bool
    {
        $endpoint = $this->driveEndpoint();
        $resp = $this->request('GET', $endpoint);
        return $resp !== null && isset($resp['id']);
    }

    public function list(string $path = '/'): array
    {
        $path = trim($path, '/');
        $folderPath = $this->rootPath;
        if ($path !== '' && $path !== '/') {
            $folderPath .= '/' . $path;
        }

        $endpoint = $this->driveEndpoint() . "/root:{$folderPath}:/children?\$top=200";
        $resp = $this->request('GET', $endpoint);

        if (!$resp || !isset($resp['value'])) {
            return [];
        }

        $items = [];
        foreach ($resp['value'] as $item) {
            $items[] = [
                'name' => $item['name'],
                'path' => $item['parentReference']['path'] . '/' . $item['name'],
                'type' => isset($item['folder']) ? 'dir' : 'file',
                'size' => $item['size'] ?? 0,
                'id'   => $item['id'],
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        $fullPath = $this->rootPath . '/' . ltrim($path, '/');
        $endpoint = $this->driveEndpoint() . "/root:{$fullPath}:/content";

        $url = $this->api . $endpoint;
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
        $fullPath = $this->rootPath . '/' . ltrim($path, '/');
        $endpoint = $this->driveEndpoint() . "/root:{$fullPath}:/content";

        $url = $this->api . $endpoint;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->token,
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
                    $this->read(basename($file['path']));
                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = $file['name'] . ': ' . $e->getMessage();
                }
            }
        }

        return [
            'files_synced' => $synced,
            'errors'       => $errors,
            'source'       => 'onedrive:' . $this->rootPath,
        ];
    }

    public function getType(): string { return 'onedrive'; }
    public function getName(): string { return 'OneDrive'; }

    private function driveEndpoint(): string
    {
        return $this->driveId
            ? "/drives/{$this->driveId}"
            : '/me/drive';
    }

    private function request(string $method, string $endpoint, ?array $body = null): ?array
    {
        $url = $this->api . $endpoint;
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
