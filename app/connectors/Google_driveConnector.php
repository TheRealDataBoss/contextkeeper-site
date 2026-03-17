<?php
/**
 * Google Drive Connector
 *
 * Config (API Key mode):
 *   { "api_key": "AIza...", "folder_id": "1abc..." }
 *
 * Config (Service Account mode):
 *   { "service_account_json": "{...}", "folder_id": "1abc..." }
 *
 * API Key mode: read-only access to shared files.
 * Service Account mode: full read/write to files shared with the service account email.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class Google_driveConnector implements ConnectorInterface
{
    private string $accessToken = '';
    private string $apiKey = '';
    private string $folderId = '';
    private string $api = 'https://www.googleapis.com';
    private bool $serviceAccountMode = false;

    public function connect(array $config): bool
    {
        $this->folderId = $config['folder_id'] ?? '';

        if (!empty($config['service_account_json'])) {
            $sa = is_string($config['service_account_json'])
                ? json_decode($config['service_account_json'], true)
                : $config['service_account_json'];

            if (!$sa || empty($sa['client_email']) || empty($sa['private_key'])) {
                return false;
            }

            $this->accessToken = $this->getServiceAccountToken($sa);
            $this->serviceAccountMode = true;
            return !empty($this->accessToken);
        }

        if (!empty($config['api_key'])) {
            $this->apiKey = $config['api_key'];
            return true;
        }

        return false;
    }

    public function test(): bool
    {
        if ($this->folderId) {
            $resp = $this->driveRequest('GET', "/drive/v3/files/{$this->folderId}?fields=id,name,mimeType");
            return $resp !== null && isset($resp['id']);
        }

        // No folder specified, just check we can call the API
        $resp = $this->driveRequest('GET', '/drive/v3/about?fields=user');
        return $resp !== null && isset($resp['user']);
    }

    public function list(string $path = '/'): array
    {
        $parentId = $this->folderId ?: 'root';
        $q = urlencode("'{$parentId}' in parents and trashed = false");
        $resp = $this->driveRequest('GET', "/drive/v3/files?q={$q}&fields=files(id,name,mimeType,size)&pageSize=100");

        if (!$resp || empty($resp['files'])) {
            return [];
        }

        $items = [];
        foreach ($resp['files'] as $file) {
            $items[] = [
                'name' => $file['name'],
                'path' => $file['id'],
                'type' => ($file['mimeType'] === 'application/vnd.google-apps.folder') ? 'dir' : 'file',
                'size' => (int)($file['size'] ?? 0),
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        // $path is a Google Drive file ID
        $resp = $this->driveRequest('GET', "/drive/v3/files/{$path}?alt=media", true);
        return $resp ?? '';
    }

    public function write(string $path, string $content): bool
    {
        if (!$this->serviceAccountMode) {
            return false; // API key mode is read-only
        }

        // Check if file exists by name in folder
        $parentId = $this->folderId ?: 'root';
        $fileName = basename($path);
        $q = urlencode("name = '{$fileName}' and '{$parentId}' in parents and trashed = false");
        $search = $this->driveRequest('GET', "/drive/v3/files?q={$q}&fields=files(id)");

        if (!empty($search['files'])) {
            // Update existing file
            $fileId = $search['files'][0]['id'];
            $url = "https://www.googleapis.com/upload/drive/v3/files/{$fileId}?uploadType=media";
        } else {
            // Create new file (multipart upload)
            $metadata = json_encode(['name' => $fileName, 'parents' => [$parentId]]);
            $boundary = 'contextkeeper_boundary_' . bin2hex(random_bytes(8));

            $body = "--{$boundary}\r\n"
                  . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
                  . $metadata . "\r\n"
                  . "--{$boundary}\r\n"
                  . "Content-Type: application/octet-stream\r\n\r\n"
                  . $content . "\r\n"
                  . "--{$boundary}--";

            $ch = curl_init('https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart');
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $this->accessToken,
                    "Content-Type: multipart/related; boundary={$boundary}",
                ],
            ]);
            $resp = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code >= 200 && $code < 300;
        }

        // Simple media upload for update
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PATCH',
            CURLOPT_POSTFIELDS     => $content,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/octet-stream',
            ],
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $code >= 200 && $code < 300;
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
                    $errors[] = $file['name'] . ': ' . $e->getMessage();
                }
            }
        }

        return [
            'files_synced' => $synced,
            'errors' => $errors,
            'source' => "gdrive:" . ($this->folderId ?: 'root'),
        ];
    }

    public function getType(): string { return 'google_drive'; }
    public function getName(): string { return 'Google Drive'; }

    private function driveRequest(string $method, string $endpoint, bool $raw = false)
    {
        $url = $this->api . $endpoint;

        // Add auth
        if ($this->apiKey && !$this->serviceAccountMode) {
            $sep = str_contains($url, '?') ? '&' : '?';
            $url .= $sep . 'key=' . urlencode($this->apiKey);
        }

        $ch = curl_init($url);
        $headers = ['Accept: application/json'];

        if ($this->accessToken) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400 || $response === false) {
            return null;
        }

        return $raw ? $response : json_decode($response, true);
    }

    /**
     * Generate a short-lived OAuth2 token from a service account JSON key.
     */
    private function getServiceAccountToken(array $sa): string
    {
        $now = time();
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = base64_encode(json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/drive',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]));

        $toSign = "$header.$claim";
        $privateKey = openssl_pkey_get_private($sa['private_key']);
        if (!$privateKey) return '';

        openssl_sign($toSign, $signature, $privateKey, 'SHA256');
        $jwt = $toSign . '.' . rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
        ]);
        $resp = json_decode(curl_exec($ch), true);
        curl_close($ch);

        return $resp['access_token'] ?? '';
    }
}
