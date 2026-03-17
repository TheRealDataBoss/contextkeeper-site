<?php
/**
 * /api/v1/sessions
 * 
 * GET /sessions?project_slug=xxx   - List sessions for a project
 * GET /sessions/:id                - Get session details
 * 
 * Sessions are created implicitly by sync/bootstrap/bundle actions.
 * This endpoint is read-only.
 */

$sessionId = $segments[1] ?? null;

// ---- GET /sessions/:id ----
if ($method === 'GET' && $sessionId) {
    $sessionId = Validator::positiveInt($sessionId, 'session_id');

    $stmt = $db->prepare(
        "SELECT s.*, p.slug as project_slug, p.name as project_name
         FROM sessions_log s
         JOIN projects p ON s.project_id = p.id
         WHERE s.id = ? AND p.user_id = ?"
    );
    $stmt->execute([$sessionId, $user['id']]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        jsonError('Session not found.', 404);
    }

    // Get decisions from this session
    $stmt = $db->prepare(
        "SELECT id, title, rationale, alternatives_rejected, established_by, created_at
         FROM decisions WHERE session_id = ? ORDER BY created_at ASC"
    );
    $stmt->execute([$sessionId]);
    $decisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($decisions as &$d) {
        $d['id'] = (int)$d['id'];
        $d['alternatives_rejected'] = $d['alternatives_rejected']
            ? json_decode($d['alternatives_rejected'], true)
            : null;
    }
    unset($d);

    jsonResponse([
        'session' => [
            'id' => (int)$session['id'],
            'project_slug' => $session['project_slug'],
            'project_name' => $session['project_name'],
            'agent' => $session['agent'],
            'action' => $session['action'],
            'decisions_captured' => (int)$session['decisions_captured'],
            'invariants_captured' => (int)$session['invariants_captured'],
            'files_captured' => (int)$session['files_captured'],
            'authority_sha' => $session['authority_sha'],
            'repo_sha' => $session['repo_sha'],
            'created_at' => $session['created_at'],
        ],
        'decisions' => $decisions,
    ]);
}

// ---- GET /sessions ----
if ($method === 'GET' && !$sessionId) {
    $projectSlug = isset($_GET['project_slug']) ? trim($_GET['project_slug']) : null;

    if (!$projectSlug) {
        jsonError('Query parameter project_slug is required.', 400);
    }

    $slug = Validator::slug($projectSlug);
    $auth = new Auth();
    $project = $auth->requireProjectOwnership($user, $slug);

    list($limit, $offset) = Validator::pagination($_GET);

    // Optional filters
    $agent = isset($_GET['agent']) ? trim($_GET['agent']) : null;
    $action = isset($_GET['action']) ? trim($_GET['action']) : null;

    $where = "WHERE s.project_id = ?";
    $params = [$project['id']];

    if ($agent) {
        $where .= " AND s.agent = ?";
        $params[] = $agent;
    }

    if ($action) {
        $validActions = ['sync', 'bootstrap', 'init', 'doctor', 'bundle'];
        if (in_array($action, $validActions, true)) {
            $where .= " AND s.action = ?";
            $params[] = $action;
        }
    }

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM sessions_log s {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Fetch
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare(
        "SELECT s.id, s.agent, s.action, s.decisions_captured, s.invariants_captured,
                s.files_captured, s.authority_sha, s.repo_sha, s.created_at
         FROM sessions_log s
         {$where}
         ORDER BY s.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute($params);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($sessions as &$s) {
        $s['id'] = (int)$s['id'];
        $s['decisions_captured'] = (int)$s['decisions_captured'];
        $s['invariants_captured'] = (int)$s['invariants_captured'];
        $s['files_captured'] = (int)$s['files_captured'];
    }
    unset($s);

    // Get distinct agents for filtering
    $agentStmt = $db->prepare(
        "SELECT DISTINCT agent FROM sessions_log WHERE project_id = ? ORDER BY agent"
    );
    $agentStmt->execute([$project['id']]);
    $agents = $agentStmt->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse([
        'sessions' => $sessions,
        'total' => $total,
        'project_slug' => $slug,
        'available_agents' => $agents,
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

jsonError('Method not allowed on /sessions. Use GET.', 405);
