<?php
/**
 * Jira Connector
 *
 * Config: { "domain": "yourteam.atlassian.net", "email": "user@example.com", "api_token": "xxx", "project_key": "CK" }
 *
 * Uses Jira Cloud REST API v3 with HTTP Basic auth (email + API token).
 * Stores contextkeeper state as issue comments or reads project issues.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class JiraConnector implements ConnectorInterface
{
    private string $domain     = '';
    private string $email      = '';
    private string $apiToken   = '';
    private string $projectKey = '';

    public function connect(array $config): bool
    {
        if (empty($config['domain']) || empty($config['email']) || empty($config['api_token'])) {
            return false;
        }
        $this->domain     = rtrim($config['domain'], '/');
        $this->email      = $config['email'];
        $this->apiToken   = $config['api_token'];
        $this->projectKey = $config['project_key'] ?? '';
        return true;
    }

    public function test(): bool
    {
        $resp = $this->request('GET', '/rest/api/3/myself');
        return $resp !== null && isset($resp['accountId']);
    }

    public function list(string $path = '/'): array
    {
        if (empty($this->projectKey)) {
            // List accessible projects
            $resp = $this->request('GET', '/rest/api/3/project?maxResults=50');
            if (!is_array($resp)) return [];

            $items = [];
            foreach ($resp as $proj) {
                $items[] = [
                    'name' => $proj['key'] . ' - ' . $proj['name'],
                    'path' => $proj['key'],
                    'type' => 'dir',
                    'size' => 0,
                ];
            }
            return $items;
        }

        // List issues in project
        $jql = urlencode("project = {$this->projectKey} ORDER BY created DESC");
        $resp = $this->request('GET', "/rest/api/3/search?jql={$jql}&maxResults=50&fields=summary,status");

        if (!$resp || !isset($resp['issues'])) {
            return [];
        }

        $items = [];
        foreach ($resp['issues'] as $issue) {
            $items[] = [
                'name' => $issue['key'] . ': ' . ($issue['fields']['summary'] ?? ''),
                'path' => $issue['key'],
                'type' => 'file',
                'size' => 0,
                'id'   => $issue['id'],
            ];
        }
        return $items;
    }

    public function read(string $path): string
    {
        $issueKey = trim($path, '/');
        $resp = $this->request('GET', "/rest/api/3/issue/{$issueKey}?fields=summary,description,status,assignee,priority");

        if (!$resp || !isset($resp['key'])) {
            return '';
        }

        // Extract description text from ADF (Atlassian Document Format)
        $description = '';
        if (isset($resp['fields']['description']['content'])) {
            $description = $this->extractAdfText($resp['fields']['description']['content']);
        }

        $data = [
            'key'         => $resp['key'],
            'summary'     => $resp['fields']['summary'] ?? '',
            'status'      => $resp['fields']['status']['name'] ?? '',
            'assignee'    => $resp['fields']['assignee']['displayName'] ?? 'Unassigned',
            'priority'    => $resp['fields']['priority']['name'] ?? '',
            'description' => $description,
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function write(string $path, string $content): bool
    {
        $issueKey = trim($path, '/');

        // Add comment to existing issue
        $resp = $this->request('POST', "/rest/api/3/issue/{$issueKey}/comment", [
            'body' => [
                'type'    => 'doc',
                'version' => 1,
                'content' => [[
                    'type'    => 'paragraph',
                    'content' => [[
                        'type' => 'text',
                        'text' => '[contextkeeper] ' . substr($content, 0, 30000),
                    ]],
                ]],
            ],
        ]);

        return $resp !== null && isset($resp['id']);
    }

    public function sync(int $projectId): array
    {
        $files = $this->list('/');
        return [
            'files_synced' => count($files),
            'errors'       => [],
            'source'       => "jira://{$this->domain}/project/{$this->projectKey}",
        ];
    }

    public function getType(): string { return 'jira'; }
    public function getName(): string { return 'Jira'; }

    private function extractAdfText(array $content): string
    {
        $text = '';
        foreach ($content as $node) {
            if (isset($node['text'])) {
                $text .= $node['text'];
            }
            if (isset($node['content'])) {
                $text .= $this->extractAdfText($node['content']);
            }
            if ($node['type'] === 'paragraph') {
                $text .= "\n";
            }
        }
        return trim($text);
    }

    private function request(string $method, string $endpoint, ?array $body = null): ?array
    {
        $url = "https://{$this->domain}{$endpoint}";
        $ch = curl_init($url);

        $headers = [
            'Authorization: Basic ' . base64_encode($this->email . ':' . $this->apiToken),
            'Accept: application/json',
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
