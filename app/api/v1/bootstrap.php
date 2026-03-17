<?php
/**
 * POST /api/v1/bootstrap
 * 
 * Bootstrap a new AI agent into an existing project.
 * Returns the full project state: state_vector, recent decisions, active invariants,
 * and session history so the new agent can resume without drift.
 * 
 * Request body:
 * {
 *   "project_slug": "my-project",
 *   "agent": "gpt-4",
 *   "include_decisions": true,      // optional, default true
 *   "include_invariants": true,     // optional, default true
 *   "decisions_limit": 50,          // optional, default 50
 *   "sessions_limit": 20            // optional, default 20
 * }
 */

$body = Validator::jsonBody();
$required = Validator::requireFields($body, ['project_slug', 'agent']);
$optional = Validator::optionalFields($body, [
    'include_decisions' => true,
    'include_invariants' => true,
    'decisions_limit' => 50,
    'sessions_limit' => 20,
]);

$slug = Validator::slug($required['project_slug']);
$agent = Validator::maxLength($required['agent'], 50, 'agent');
$decisionsLimit = max(1, min(200, (int)$optional['decisions_limit']));
$sessionsLimit = max(1, min(100, (int)$optional['sessions_limit']));

// Verify project ownership
$auth = new Auth();
$project = $auth->requireProjectOwnership($user, $slug);

// Check session limit
if (!$auth->checkSessionLimit($user, $project['id'])) {
    jsonError('Monthly session limit reached. Upgrade your plan for unlimited sessions.', 403);
}

// 1. Log the bootstrap session
$stmt = $db->prepare(
    "INSERT INTO sessions_log 
     (project_id, agent, action, authority_sha)
     VALUES (?, ?, 'bootstrap', ?)"
);
$stmt->execute([
    $project['id'],
    $agent,
    $project['state_vector'] ? hash('sha256', $project['state_vector']) : null,
]);
$sessionId = (int)$db->lastInsertId();

// Update session count
$db->prepare(
    "UPDATE projects SET sessions_count = sessions_count + 1, updated_at = NOW() WHERE id = ?"
)->execute([$project['id']]);

// 2. Build the bootstrap payload
$payload = [
    'bootstrap' => 'ok',
    'session_id' => $sessionId,
    'project' => [
        'id' => (int)$project['id'],
        'name' => $project['name'],
        'slug' => $project['slug'],
        'current_state' => $project['current_state'],
        'state_vector' => $project['state_vector']
            ? json_decode($project['state_vector'], true)
            : null,
        'created_at' => $project['created_at'],
        'updated_at' => $project['updated_at'],
    ],
];

// 3. Include decisions
if ($optional['include_decisions']) {
    $stmt = $db->prepare(
        "SELECT id, session_id, title, rationale, alternatives_rejected, 
                established_by, created_at
         FROM decisions WHERE project_id = ? 
         ORDER BY created_at DESC LIMIT ?"
    );
    $stmt->execute([$project['id'], $decisionsLimit]);
    $decisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse JSON fields
    foreach ($decisions as &$d) {
        $d['id'] = (int)$d['id'];
        $d['session_id'] = $d['session_id'] ? (int)$d['session_id'] : null;
        $d['alternatives_rejected'] = $d['alternatives_rejected']
            ? json_decode($d['alternatives_rejected'], true)
            : null;
    }
    unset($d);

    $payload['decisions'] = $decisions;
    $payload['decisions_total'] = count($decisions);
}

// 4. Include invariants
if ($optional['include_invariants']) {
    $stmt = $db->prepare(
        "SELECT id, name, assertion, scope, established_by, active, created_at
         FROM invariants WHERE project_id = ? AND active = 1
         ORDER BY created_at ASC"
    );
    $stmt->execute([$project['id']]);
    $invariants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invariants as &$inv) {
        $inv['id'] = (int)$inv['id'];
        $inv['active'] = (bool)$inv['active'];
    }
    unset($inv);

    $payload['invariants'] = $invariants;
    $payload['invariants_total'] = count($invariants);
}

// 5. Include recent session history
$stmt = $db->prepare(
    "SELECT id, agent, action, decisions_captured, invariants_captured,
            files_captured, authority_sha, repo_sha, created_at
     FROM sessions_log WHERE project_id = ?
     ORDER BY created_at DESC LIMIT ?"
);
$stmt->execute([$project['id'], $sessionsLimit]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($sessions as &$s) {
    $s['id'] = (int)$s['id'];
    $s['decisions_captured'] = (int)$s['decisions_captured'];
    $s['invariants_captured'] = (int)$s['invariants_captured'];
    $s['files_captured'] = (int)$s['files_captured'];
}
unset($s);

$payload['sessions'] = $sessions;

// 6. Authority SHA for integrity verification
$payload['authority_sha'] = $project['state_vector']
    ? hash('sha256', $project['state_vector'])
    : null;

// 7. Log usage
$db->prepare(
    "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'bootstrap', ?, ?)"
)->execute([
    $user['id'],
    $project['id'],
    json_encode([
        'session_id' => $sessionId,
        'agent' => $agent,
        'decisions_included' => $optional['include_decisions'],
        'invariants_included' => $optional['include_invariants'],
    ]),
]);

jsonResponse($payload);
