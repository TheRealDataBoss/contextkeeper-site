<?php
/**
 * Notion Connector
 *
 * Config: { "token": "secret_xxx", "database_id": "abc123...", "root_page_id": "optional" }
 *
 * Uses Notion API (2022-06-28). Stores contextkeeper state as pages
 * in a Notion database, or reads/writes page content from a root page.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class NotionConnector implements ConnectorInterface
{
    private string $token      = '';
    private string $databaseId = '';
    private string $rootPageId = '';
    private string $api        = 'https://api.notion.com/v1';
    private string $version    = '2022-06-28';

    public function connect(array $config): bool
    {
        if (empty($config['token'])) {
            return false;
        }
        $this->token      = $config['token'];
        $this->databaseId = $config['database_id'] ?? '';
        $this->rootPageId = $config['root_page_id'] ?? '';
        return true;
    }

    public function test(): bool
    {
        // Verify token by fetching current bot user
        $resp = $this->request('GET', '/users/me');
        return $resp !== null && isset($resp['id']);
    }

    public function list(string $path = '/'): array
    {
        if (empty($this->databaseId)) {
            return [];
        }

        $resp = $this->request('POST', "/databases/{$this->databaseId}/query", [
            'page_size' => 100,
            'sorts'     => [['property' => 'Name', 'direction' => 'ascending']],
        ]);

        if (!$resp || !isset($resp['results'])) {
            return [];
        }

        $items = [];
        foreach ($resp['results'] as $page) {
            $title = '';
            // Extract title from properties
            foreach ($page['properties'] as $prop) {
                if ($prop['type'] === 'title' && !empty($prop['title'])) {
                    $title = $prop['title'][0]['plain_text'] ?? '';
                    break;
                }
            }

            $items[] = [
                'name' => $title ?: $page['id'],
                'path' => $page['id'],
                'type' => 'file',
                'size' => 0,
                'id'   => $page['id'],
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        $pageId = trim($path, '/');

        // Get page blocks (content)
        $resp = $this->request('GET', "/blocks/{$pageId}/children?page_size=100");
        if (!$resp || !isset($resp['results'])) {
            return '';
        }

        // Extract text content from blocks
        $content = [];
        foreach ($resp['results'] as $block) {
            $type = $block['type'] ?? '';
            if (isset($block[$type]['rich_text'])) {
                $text = '';
                foreach ($block[$type]['rich_text'] as $rt) {
                    $text .= $rt['plain_text'] ?? '';
                }
                $content[] = $text;
            }
        }

        return implode("\n", $content);
    }

    public function write(string $path, string $content): bool
    {
        if (empty($this->databaseId)) {
            return false;
        }

        $key = trim($path, '/');

        // Create a new page in the database
        $resp = $this->request('POST', '/pages', [
            'parent'     => ['database_id' => $this->databaseId],
            'properties' => [
                'Name' => [
                    'title' => [[
                        'text' => ['content' => $key],
                    ]],
                ],
            ],
            'children' => [[
                'object' => 'block',
                'type'   => 'paragraph',
                'paragraph' => [
                    'rich_text' => [[
                        'type' => 'text',
                        'text' => ['content' => substr($content, 0, 2000)],
                    ]],
                ],
            ]],
        ]);

        return $resp !== null && isset($resp['id']);
    }

    public function sync(int $projectId): array
    {
        $files = $this->list('/');
        return [
            'files_synced' => count($files),
            'errors'       => [],
            'source'       => 'notion:database/' . $this->databaseId,
        ];
    }

    public function getType(): string { return 'notion'; }
    public function getName(): string { return 'Notion'; }

    private function request(string $method, string $endpoint, ?array $body = null): ?array
    {
        $url = $this->api . $endpoint;
        $ch = curl_init($url);

        $headers = [
            'Authorization: Bearer ' . $this->token,
            'Notion-Version: ' . $this->version,
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
