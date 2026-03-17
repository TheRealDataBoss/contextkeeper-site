<?php
/**
 * /api/v1/decisions/:project_slug
 * 
 * GET  /decisions/:slug   - List decisions for a project
 * POST /decisions/:slug   - Record a new decision
 */

$projectSlug = $segments[1] ?? null;

if (!$projectSlug) {
    jsonError('Project slug required: /api/v1/decisions/{project_slug}', 400);
}

$slug = Validator::slug($projectSlug);
$auth = new Auth();
$project = $auth->requireProjectOwnership($user, $slug);

// ---- GET /decisions/:slug ----
if ($method === 'GET') {
    list($limit, $offset) = Validator::pagination($_GET);

    // Optional filters
    $sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : null;
    $agent = isset($_GET['agent']) ? trim($_GET['agent']) : null;
    $search = isset($_GET['q']) ? trim($_GET['q']) : null;

    $where = "WHERE d.project_id = ?";
    $params = [$project['id']];

    if ($sessionId) {
        $where .= " AND d.session_id = ?";
        $params[] = $sessionId;
    }

    if ($agent) {
        $where .= " AND d.established_by = ?";
        $params[] = $agent;
    }

    if ($search) {
        $where .= " AND (d.title LIKE ? OR d.rationale LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM decisions d {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Fetch
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare(
        "SELECT d.id, d.session_id, d.title, d.rationale, d.alternatives_rejected,
                d.established_by, d.created_at,
                s.agent as session_agent, s.action as session_action
         FROM decisions d
         LEFT JOIN sessions_log s ON d.session_id = s.id
         {$where}
         ORDER BY d.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute($params);
    $decisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse JSON fields and cast types
    foreach ($decisions as &$d) {
        $d['id'] = (int)$d['id'];
        $d['session_id'] = $d['session_id'] ? (int)$d['session_id'] : null;
        $d['alternatives_rejected'] = $d['alternatives_rejected']
            ? json_decode($d['alternatives_rejected'], true)
            : null;
    }
    unset($d);

    jsonResponse([
        'decisions' => $decisions,
        'total' => $total,
        'project_slug' => $slug,
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

// ---- POST /decisions/:slug ----
if ($method === 'POST') {
    $body = Validator::jsonBody();
    $required = Validator::requireFields($body, ['title']);
    $optional = Validator::optionalFields($body, [
        'rationale' => null,
        'alternatives_rejected' => null,
        'established_by' => null,
        'session_id' => null,
    ]);

    $title = Validator::maxLength($required['title'], 500, 'title');
    $rationale = $optional['rationale'];
    $establishedBy = $optional['established_by']
        ? Validator::maxLength($optional['established_by'], 100, 'established_by')
        : null;

    // Validate session_id belongs to this project if provided
    $sessionId = null;
    if ($optional['session_id'] !== null) {
        $sessionId = (int)$optional['session_id'];
        $stmt = $db->prepare(
            "SELECT id FROM sessions_log WHERE id = ? AND project_id = ?"
        );
        $stmt->execute([$sessionId, $project['id']]);
        if (!$stmt->fetch()) {
            jsonError('Session ID does not belong to this project.', 400);
        }
    }

    // Validate alternatives_rejected JSON
    $alternativesJson = null;
    if ($optional['alternatives_rejected'] !== null) {
        $alternativesJson = Validator::jsonData($optional['alternatives_rejected'], 'alternatives_rejected');
    }

    $stmt = $db->prepare(
        "INSERT INTO decisions 
         (project_id, session_id, title, rationale, alternatives_rejected, established_by)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $project['id'],
        $sessionId,
        $title,
        $rationale,
        $alternativesJson,
        $establishedBy,
    ]);
    $decisionId = (int)$db->lastInsertId();

    // Update project decisions count
    $db->prepare(
        "UPDATE projects SET decisions_count = decisions_count + 1, updated_at = NOW() WHERE id = ?"
    )->execute([$project['id']]);

    // Log usage
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'decision_create', ?, ?)"
    )->execute([
        $user['id'],
        $project['id'],
        json_encode(['decision_id' => $decisionId, 'title' => $title]),
    ]);

    jsonResponse([
        'decision' => [
            'id' => $decisionId,
            'project_slug' => $slug,
            'session_id' => $sessionId,
            'title' => $title,
            'rationale' => $rationale,
            'alternatives_rejected' => $optional['alternatives_rejected'],
            'established_by' => $establishedBy,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], 201);
}

jsonError('Method not allowed on /decisions. Use GET or POST.', 405);
