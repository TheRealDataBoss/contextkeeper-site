<?php
/**
 * /api/v1/connectors
 * 
 * GET    /connectors          - List user connectors
 * POST   /connectors          - Add a new connector
 * DELETE /connectors/:id      - Remove a connector
 * POST   /connectors/:id/test - Test a connector
 */

require_once __DIR__ . '/../../lib/Encryption.php';

$connectorId = $segments[1] ?? null;
$subAction = $segments[2] ?? null;

// ---- POST /connectors/:id/test ----
if ($method === 'POST' && $connectorId && $subAction === 'test') {
    $connectorId = Validator::positiveInt($connectorId, 'connector_id');

    $stmt = $db->prepare(
        "SELECT * FROM connectors WHERE id = ? AND user_id = ?"
    );
    $stmt->execute([$connectorId, $user['id']]);
    $connector = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$connector) {
        jsonError('Connector not found.', 404);
    }

    // Decrypt config
    $encryption = new Encryption();
    $config = json_decode($encryption->decrypt($connector['config_encrypted'], $user['id']), true);

    if (!$config) {
        // Update status to error
        $db->prepare("UPDATE connectors SET status = 'error' WHERE id = ?")->execute([$connectorId]);
        jsonError('Failed to decrypt connector configuration. Re-add the connector.', 500);
    }

    // Load the appropriate connector class
    $connectorType = $connector['type'];
    $connectorClass = ucfirst($connectorType) . 'Connector';
    $connectorFile = __DIR__ . '/../../connectors/' . $connectorClass . '.php';

    if (!file_exists($connectorFile)) {
        jsonError("Connector type '{$connectorType}' is not yet implemented.", 501);
    }

    require_once __DIR__ . '/../../connectors/ConnectorInterface.php';
    require_once $connectorFile;

    try {
        $instance = new $connectorClass();
        $instance->connect($config);
        $testResult = $instance->test();

        $newStatus = $testResult ? 'active' : 'error';
        $db->prepare(
            "UPDATE connectors SET status = ?, last_sync = NOW() WHERE id = ?"
        )->execute([$newStatus, $connectorId]);

        jsonResponse([
            'test' => $testResult ? 'passed' : 'failed',
            'connector_id' => $connectorId,
            'type' => $connectorType,
            'status' => $newStatus,
        ]);
    } catch (Exception $e) {
        $db->prepare("UPDATE connectors SET status = 'error' WHERE id = ?")->execute([$connectorId]);
        jsonResponse([
            'test' => 'failed',
            'connector_id' => $connectorId,
            'type' => $connectorType,
            'status' => 'error',
            'error' => $e->getMessage(),
        ]);
    }
}

// ---- DELETE /connectors/:id ----
if ($method === 'DELETE' && $connectorId) {
    $connectorId = Validator::positiveInt($connectorId, 'connector_id');

    $stmt = $db->prepare(
        "SELECT id FROM connectors WHERE id = ? AND user_id = ?"
    );
    $stmt->execute([$connectorId, $user['id']]);

    if (!$stmt->fetch()) {
        jsonError('Connector not found.', 404);
    }

    $db->prepare("DELETE FROM connectors WHERE id = ? AND user_id = ?")->execute([
        $connectorId, $user['id'],
    ]);

    // Log usage
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, metadata) VALUES (?, 'connector_delete', ?)"
    )->execute([$user['id'], json_encode(['connector_id' => $connectorId])]);

    jsonResponse(['deleted' => true, 'connector_id' => $connectorId]);
}

// ---- GET /connectors ----
if ($method === 'GET' && !$connectorId) {
    $stmt = $db->prepare(
        "SELECT id, type, name, last_sync, status, created_at
         FROM connectors WHERE user_id = ?
         ORDER BY created_at DESC"
    );
    $stmt->execute([$user['id']]);
    $connectors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($connectors as &$c) {
        $c['id'] = (int)$c['id'];
    }
    unset($c);

    $auth = new Auth();
    $limits = $auth->getPlanLimits($user['plan']);

    jsonResponse([
        'connectors' => $connectors,
        'total' => count($connectors),
        'limit' => $limits['connectors'],
    ]);
}

// ---- POST /connectors ----
if ($method === 'POST' && !$connectorId) {
    // Check connector limit
    $auth = new Auth();
    $limits = $auth->getPlanLimits($user['plan']);

    if ($limits['connectors'] !== -1) {
        $stmt = $db->prepare("SELECT COUNT(*) FROM connectors WHERE user_id = ?");
        $stmt->execute([$user['id']]);
        $currentCount = (int)$stmt->fetchColumn();

        if ($currentCount >= $limits['connectors']) {
            jsonError(
                "Connector limit reached ({$limits['connectors']}). Upgrade your plan for more connectors.",
                403
            );
        }
    }

    $body = Validator::jsonBody();
    $required = Validator::requireFields($body, ['type', 'name', 'config']);

    $validTypes = [
        'github', 'google_drive', 's3', 'postgresql', 'local_file',
        'gitlab', 'bitbucket', 'dropbox', 'onedrive', 'azure_blob',
        'bigquery', 'snowflake', 'mongodb', 'redis', 'notion',
        'slack', 'jira', 'supabase', 'cloudflare_r2', 'hugging_face',
    ];

    $type = Validator::enum($required['type'], $validTypes, 'type');
    $name = Validator::maxLength($required['name'], 255, 'name');

    // Encrypt the config
    $encryption = new Encryption();
    $configJson = is_string($required['config'])
        ? $required['config']
        : json_encode($required['config']);
    $encryptedConfig = $encryption->encrypt($configJson, $user['id']);

    $stmt = $db->prepare(
        "INSERT INTO connectors (user_id, type, name, config_encrypted, status)
         VALUES (?, ?, ?, ?, 'active')"
    );
    $stmt->execute([$user['id'], $type, $name, $encryptedConfig]);
    $newId = (int)$db->lastInsertId();

    // Log usage
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, metadata) VALUES (?, 'connector_add', ?)"
    )->execute([$user['id'], json_encode(['connector_id' => $newId, 'type' => $type, 'name' => $name])]);

    jsonResponse([
        'connector' => [
            'id' => $newId,
            'type' => $type,
            'name' => $name,
            'status' => 'active',
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], 201);
}

jsonError('Method not allowed on /connectors.', 405);
