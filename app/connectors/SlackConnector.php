<?php
/**
 * Slack Connector
 *
 * Config: { "bot_token": "xoxb-xxx", "channel_id": "C0123456789" }
 *
 * Uses Slack Web API. Stores contextkeeper state updates as messages
 * in a dedicated channel. Reads back pinned/recent messages.
 */

require_once __DIR__ . '/ConnectorInterface.php';

class SlackConnector implements ConnectorInterface
{
    private string $botToken  = '';
    private string $channelId = '';
    private string $api       = 'https://slack.com/api';

    public function connect(array $config): bool
    {
        if (empty($config['bot_token']) || empty($config['channel_id'])) {
            return false;
        }
        $this->botToken  = $config['bot_token'];
        $this->channelId = $config['channel_id'];
        return true;
    }

    public function test(): bool
    {
        $resp = $this->slackApi('auth.test');
        return $resp !== null && ($resp['ok'] ?? false) === true;
    }

    public function list(string $path = '/'): array
    {
        // List recent messages in channel as "files"
        $resp = $this->slackApi('conversations.history', [
            'channel' => $this->channelId,
            'limit'   => 100,
        ]);

        if (!$resp || ($resp['ok'] ?? false) !== true || !isset($resp['messages'])) {
            return [];
        }

        $items = [];
        foreach ($resp['messages'] as $i => $msg) {
            $text = $msg['text'] ?? '';
            // Only include contextkeeper-tagged messages
            if (strpos($text, '[contextkeeper]') !== false || strpos($text, 'contextkeeper sync') !== false) {
                $items[] = [
                    'name' => 'message_' . ($msg['ts'] ?? $i),
                    'path' => $msg['ts'] ?? (string)$i,
                    'type' => 'file',
                    'size' => strlen($text),
                ];
            }
        }
        return $items;
    }

    public function read(string $path): string
    {
        $ts = trim($path, '/');

        // Fetch specific message by timestamp
        $resp = $this->slackApi('conversations.history', [
            'channel'   => $this->channelId,
            'oldest'    => $ts,
            'latest'    => $ts,
            'inclusive' => true,
            'limit'    => 1,
        ]);

        if ($resp && ($resp['ok'] ?? false) && !empty($resp['messages'][0]['text'])) {
            return $resp['messages'][0]['text'];
        }
        return '';
    }

    public function write(string $path, string $content): bool
    {
        $key = trim($path, '/');

        $resp = $this->slackApi('chat.postMessage', [
            'channel' => $this->channelId,
            'text'    => "[contextkeeper] {$key}\n```\n" . substr($content, 0, 3000) . "\n```",
            'unfurl_links' => false,
            'unfurl_media' => false,
        ]);

        return $resp !== null && ($resp['ok'] ?? false) === true;
    }

    public function sync(int $projectId): array
    {
        $files = $this->list('/');
        return [
            'files_synced' => count($files),
            'errors'       => [],
            'source'       => 'slack:channel/' . $this->channelId,
        ];
    }

    public function getType(): string { return 'slack'; }
    public function getName(): string { return 'Slack'; }

    private function slackApi(string $method, array $params = []): ?array
    {
        $url = $this->api . '/' . $method;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($params),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->botToken,
                'Content-Type: application/json; charset=utf-8',
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
