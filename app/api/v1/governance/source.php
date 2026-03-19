<?php
/**
 * /api/v1/governance/source
 *
 * POST /governance/source/attach     - Attach source file (content-hashed)
 * GET  /governance/source/manifest   - List all attachments for a task
 * POST /governance/source/verify     - Verify file content against stored hash
 *
 * G1.3 scope. Spec source: governance-mapping.md mechanism 3, whitepaper Section 3.3
 *
 * Variables in scope from parent router chain (index.php -> governance.php):
 *   $user, $db, $method, $segments
 * Functions in scope: jsonResponse(), jsonError()
 * Classes loaded: Auth, Database, Validator, UUID
 */

$action = $segments[2] ?? null;

// ---- POST /governance/source/attach ----
if ($method === 'POST' && $action === 'attach') {
    $body = Validator::jsonBody();

    $required = Validator::requireFields($body, ['task_id', 'file_path', 'file_content']);

    $taskId = $required['task_id'];
    $filePath = Validator::maxLength(trim($required['file_path']), 500, 'file_path');
    $fileContent = $required['file_content'];

    if (!is_string($fileContent)) {
        jsonError('file_content must be a string.', 400);
    }

    if ($filePath === '') {
        jsonError('file_path must not be empty.', 400);
    }

    // Verify task ownership via project
    $task = fetchTaskForSource($db, $user, $taskId);

    // Compute SHA-256 hash of the content
    $contentHash = hash('sha256', $fileContent);

    $attachedBy = 'user:' . $user['id'];

    // UPSERT: check for existing task_id + file_path
    $existStmt = $db->prepare(
        "SELECT id FROM source_attachments WHERE task_id = ? AND file_path = ? LIMIT 1"
    );
    $existStmt->execute([$task['id'], $filePath]);
    $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing attachment with new hash
        $db->prepare(
            "UPDATE source_attachments
             SET content_hash = ?, attached_by = ?, created_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        )->execute([$contentHash, $attachedBy, $existing['id']]);

        $resultId = $existing['id'];
        $resultAction = 'updated';
    } else {
        // Insert new attachment
        $resultId = UUID::v4();
        $db->prepare(
            "INSERT INTO source_attachments (id, task_id, file_path, content_hash, attached_by)
             VALUES (?, ?, ?, ?, ?)"
        )->execute([$resultId, $task['id'], $filePath, $contentHash, $attachedBy]);

        $resultAction = 'created';
    }

    // Log usage (matches decisions.php pattern: user_id, action, project_id, metadata)
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'governance_source_attach', ?, ?)"
    )->execute([
        $user['id'],
        $task['project_id'],
        json_encode([
            'attachment_id' => $resultId,
            'task_id' => $task['id'],
            'file_path' => $filePath,
            'action' => $resultAction,
        ]),
    ]);

    jsonResponse([
        'attachment' => [
            'id' => $resultId,
            'task_id' => $task['id'],
            'file_path' => $filePath,
            'content_hash' => $contentHash,
            'action' => $resultAction,
        ],
    ], 201);
}

// ---- GET /governance/source/manifest?task_id=... ----
if ($method === 'GET' && $action === 'manifest') {
    $taskId = $_GET['task_id'] ?? null;
    if (!$taskId) {
        jsonError('Query parameter task_id is required.', 400);
    }

    $task = fetchTaskForSource($db, $user, $taskId);

    $stmt = $db->prepare(
        "SELECT file_path, content_hash, attached_by, created_at
         FROM source_attachments
         WHERE task_id = ?
         ORDER BY file_path ASC
         LIMIT 500"
    );
    $stmt->execute([$task['id']]);
    $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonResponse([
        'manifest' => [
            'task_id' => $task['id'],
            'attachment_count' => count($attachments),
            'attachments' => $attachments,
        ],
    ]);
}

// ---- POST /governance/source/verify ----
if ($method === 'POST' && $action === 'verify') {
    $body = Validator::jsonBody();

    $required = Validator::requireFields($body, ['task_id', 'file_path', 'file_content']);

    $taskId = $required['task_id'];
    $filePath = trim($required['file_path']);
    $fileContent = $required['file_content'];

    if (!is_string($fileContent)) {
        jsonError('file_content must be a string.', 400);
    }

    $task = fetchTaskForSource($db, $user, $taskId);

    // Look up stored attachment
    $stmt = $db->prepare(
        "SELECT content_hash FROM source_attachments
         WHERE task_id = ? AND file_path = ?
         LIMIT 1"
    );
    $stmt->execute([$task['id'], $filePath]);
    $stored = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$stored) {
        jsonError('No source attachment found for this task and file path.', 404);
    }

    // Compute hash of provided content and compare
    $computedHash = hash('sha256', $fileContent);
    $verified = ($computedHash === $stored['content_hash']);

    // Log verification attempt
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'governance_source_verify', ?, ?)"
    )->execute([
        $user['id'],
        $task['project_id'],
        json_encode([
            'task_id' => $task['id'],
            'file_path' => $filePath,
            'verified' => $verified,
        ]),
    ]);

    jsonResponse([
        'verification' => [
            'task_id' => $task['id'],
            'file_path' => $filePath,
            'verified' => $verified,
            'stored_hash' => $stored['content_hash'],
            'computed_hash' => $computedHash,
        ],
    ]);
}

// Fallback
jsonError('Unknown source action. Use POST /attach, GET /manifest, or POST /verify.', 404);


// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Fetch a governed task and verify the authenticated user owns the parent project.
 * Returns task row or calls jsonError(404).
 */
function fetchTaskForSource(PDO $db, array $user, string $taskId): array {
    if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $taskId)) {
        jsonError('Invalid task ID format. Expected UUID v4.', 400);
    }

    $stmt = $db->prepare(
        "SELECT gt.* FROM governed_tasks gt
         JOIN projects p ON gt.project_id = p.id
         WHERE gt.id = ? AND p.user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$taskId, $user['id']]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$task) {
        jsonError('Governed task not found.', 404);
    }

    return $task;
}
