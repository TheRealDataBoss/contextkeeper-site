<?php
/**
 * POST /api/v1/sync
 * 
 * Sync session state from an AI agent into a project.
 * This is the primary write path: the agent reports what happened during a session.
 * 
 * Request body:
 * {
 *   "project_slug": "my-project",
 *   "agent": "claude-4",
 *   "state_vector": { ... },
 *   "decisions": [ { "title": "...", "rationale": "...", ... } ],
 *   "invariants": [ { "name": "...", "assertion": "...", ... } ],
 *   "files_captured": 12,
 *   "authority_sha": "abc123...",
 *   "repo_sha": "def456..."
 * }
 */

$body = Validator::jsonBody();
$required = Validator::requireFields($body, ['project_slug', 'agent']);
$optional = Validator::optionalFields($body, [
    'state_vector' => null,
    'decisions' => [],
    'invariants' => [],
    'files_captured' => 0,
    'authority_sha' => null,
    'repo_sha' => null,
]);

$slug = Validator::slug($required['project_slug']);
$agent = Validator::maxLength($required['agent'], 50, 'agent');

// Verify project ownership
$auth = new Auth();
$project = $auth->requireProjectOwnership($user, $slug);

// Check session limit
if (!$auth->checkSessionLimit($user, $project['id'])) {
    jsonError('Monthly session limit reached. Upgrade your plan for unlimited sessions.', 403);
}

// Begin transaction
$db->beginTransaction();

try {
    // 1. Create session log entry
    $decisionsCount = is_array($optional['decisions']) ? count($optional['decisions']) : 0;
    $invariantsCount = is_array($optional['invariants']) ? count($optional['invariants']) : 0;
    $filesCaptured = (int)$optional['files_captured'];

    $stmt = $db->prepare(
        "INSERT INTO sessions_log 
         (project_id, agent, action, decisions_captured, invariants_captured, 
          files_captured, authority_sha, repo_sha)
         VALUES (?, ?, 'sync', ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $project['id'],
        $agent,
        $decisionsCount,
        $invariantsCount,
        $filesCaptured,
        $optional['authority_sha'],
        $optional['repo_sha'],
    ]);
    $sessionId = (int)$db->lastInsertId();

    // 2. Update project state vector
    if ($optional['state_vector'] !== null) {
        $stateJson = Validator::jsonData($optional['state_vector'], 'state_vector');
        $stmt = $db->prepare(
            "UPDATE projects SET state_vector = ?, current_state = 'ACTIVE', 
             sessions_count = sessions_count + 1, updated_at = NOW() 
             WHERE id = ?"
        );
        $stmt->execute([$stateJson, $project['id']]);
    } else {
        $stmt = $db->prepare(
            "UPDATE projects SET sessions_count = sessions_count + 1, 
             updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$project['id']]);
    }

    // 3. Record decisions
    $decisionIds = [];
    if (!empty($optional['decisions']) && is_array($optional['decisions'])) {
        $insertDecision = $db->prepare(
            "INSERT INTO decisions 
             (project_id, session_id, title, rationale, alternatives_rejected, established_by)
             VALUES (?, ?, ?, ?, ?, ?)"
        );

        foreach ($optional['decisions'] as $dec) {
            if (empty($dec['title'])) continue;

            $title = substr(trim($dec['title']), 0, 500);
            $rationale = isset($dec['rationale']) ? trim($dec['rationale']) : null;
            $alternatives = isset($dec['alternatives_rejected'])
                ? (is_string($dec['alternatives_rejected'])
                    ? $dec['alternatives_rejected']
                    : json_encode($dec['alternatives_rejected']))
                : null;
            $establishedBy = isset($dec['established_by'])
                ? substr(trim($dec['established_by']), 0, 100)
                : $agent;

            $insertDecision->execute([
                $project['id'],
                $sessionId,
                $title,
                $rationale,
                $alternatives,
                $establishedBy,
            ]);
            $decisionIds[] = (int)$db->lastInsertId();
        }

        // Update project decisions count
        $db->prepare(
            "UPDATE projects SET decisions_count = decisions_count + ? WHERE id = ?"
        )->execute([count($decisionIds), $project['id']]);
    }

    // 4. Record invariants
    $invariantIds = [];
    if (!empty($optional['invariants']) && is_array($optional['invariants'])) {
        $insertInvariant = $db->prepare(
            "INSERT INTO invariants 
             (project_id, name, assertion, scope, established_by)
             VALUES (?, ?, ?, ?, ?)"
        );

        foreach ($optional['invariants'] as $inv) {
            if (empty($inv['name'])) continue;

            $name = substr(trim($inv['name']), 0, 255);
            $assertion = isset($inv['assertion']) ? trim($inv['assertion']) : null;
            $scope = isset($inv['scope']) ? substr(trim($inv['scope']), 0, 255) : null;
            $establishedBy = isset($inv['established_by'])
                ? substr(trim($inv['established_by']), 0, 100)
                : $agent;

            $insertInvariant->execute([
                $project['id'],
                $name,
                $assertion,
                $scope,
                $establishedBy,
            ]);
            $invariantIds[] = (int)$db->lastInsertId();
        }
    }

    // 5. Log usage
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'sync', ?, ?)"
    )->execute([
        $user['id'],
        $project['id'],
        json_encode([
            'session_id' => $sessionId,
            'agent' => $agent,
            'decisions' => $decisionsCount,
            'invariants' => $invariantsCount,
            'files' => $filesCaptured,
        ]),
    ]);

    $db->commit();

    jsonResponse([
        'sync' => 'ok',
        'session_id' => $sessionId,
        'project_slug' => $slug,
        'decisions_recorded' => count($decisionIds),
        'invariants_recorded' => count($invariantIds),
        'files_captured' => $filesCaptured,
        'state' => $optional['state_vector'] !== null ? 'updated' : 'unchanged',
    ], 201);

} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
