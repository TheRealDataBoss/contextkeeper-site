<?php
/**
 * /api/v1/projects
 * 
 * GET  /projects          - List all user projects
 * POST /projects          - Create a new project
 * GET  /projects/:slug    - Get project details by slug
 */

$slug = $segments[1] ?? null;

// ---- GET /projects/:slug ----
if ($method === 'GET' && $slug) {
    $auth = new Auth();
    $project = $auth->requireProjectOwnership($user, $slug);

    // Enrich with counts
    $stmt = $db->prepare("SELECT COUNT(*) FROM decisions WHERE project_id = ?");
    $stmt->execute([$project['id']]);
    $decisionsCount = (int)$stmt->fetchColumn();

    $stmt = $db->prepare("SELECT COUNT(*) FROM invariants WHERE project_id = ? AND active = 1");
    $stmt->execute([$project['id']]);
    $invariantsCount = (int)$stmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM sessions_log WHERE project_id = ?"
    );
    $stmt->execute([$project['id']]);
    $sessionsCount = (int)$stmt->fetchColumn();

    // Last 10 sessions
    $stmt = $db->prepare(
        "SELECT id, agent, action, decisions_captured, invariants_captured, 
                files_captured, authority_sha, repo_sha, created_at
         FROM sessions_log WHERE project_id = ? ORDER BY created_at DESC LIMIT 10"
    );
    $stmt->execute([$project['id']]);
    $recentSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Parse state_vector JSON
    $stateVector = $project['state_vector'] ? json_decode($project['state_vector'], true) : null;

    jsonResponse([
        'project' => [
            'id' => (int)$project['id'],
            'name' => $project['name'],
            'slug' => $project['slug'],
            'current_state' => $project['current_state'],
            'state_vector' => $stateVector,
            'sessions_count' => $sessionsCount,
            'decisions_count' => $decisionsCount,
            'invariants_count' => $invariantsCount,
            'created_at' => $project['created_at'],
            'updated_at' => $project['updated_at'],
        ],
        'recent_sessions' => $recentSessions,
    ]);
}

// ---- GET /projects ----
if ($method === 'GET' && !$slug) {
    list($limit, $offset) = Validator::pagination($_GET);

    $stmt = $db->prepare(
        "SELECT id, name, slug, current_state, sessions_count, decisions_count, 
                created_at, updated_at
         FROM projects WHERE user_id = ? 
         ORDER BY updated_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute([$user['id'], $limit, $offset]);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast integer fields
    foreach ($projects as &$p) {
        $p['id'] = (int)$p['id'];
        $p['sessions_count'] = (int)$p['sessions_count'];
        $p['decisions_count'] = (int)$p['decisions_count'];
    }
    unset($p);

    $stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $total = (int)$stmt->fetchColumn();

    jsonResponse([
        'projects' => $projects,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

// ---- POST /projects ----
if ($method === 'POST' && !$slug) {
    // Check project limit
    $auth = new Auth();
    if (!$auth->checkProjectLimit($user)) {
        $limits = $auth->getPlanLimits($user['plan']);
        jsonError(
            "Project limit reached ({$limits['projects']}). Upgrade your plan for more projects.",
            403
        );
    }

    $body = Validator::jsonBody();
    $required = Validator::requireFields($body, ['name']);
    $optional = Validator::optionalFields($body, [
        'slug' => null,
        'state_vector' => null,
    ]);

    $name = Validator::maxLength($required['name'], 255, 'name');

    // Auto-generate slug from name if not provided
    $slug = $optional['slug']
        ? Validator::slug($optional['slug'])
        : Validator::slug($name);

    // Validate state_vector JSON if provided
    $stateVectorJson = null;
    if ($optional['state_vector'] !== null) {
        $stateVectorJson = Validator::jsonData($optional['state_vector'], 'state_vector');
    }

    // Check uniqueness
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM projects WHERE user_id = ? AND slug = ?"
    );
    $stmt->execute([$user['id'], $slug]);
    if ((int)$stmt->fetchColumn() > 0) {
        jsonError("A project with slug '{$slug}' already exists.", 409);
    }

    // Insert
    $stmt = $db->prepare(
        "INSERT INTO projects (user_id, name, slug, state_vector, current_state)
         VALUES (?, ?, ?, ?, 'UNINITIATED')"
    );
    $stmt->execute([$user['id'], $name, $slug, $stateVectorJson]);
    $projectId = (int)$db->lastInsertId();

    // Log usage
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'project_create', ?, ?)"
    )->execute([$user['id'], $projectId, json_encode(['name' => $name, 'slug' => $slug])]);

    jsonResponse([
        'project' => [
            'id' => $projectId,
            'name' => $name,
            'slug' => $slug,
            'current_state' => 'UNINITIATED',
            'state_vector' => $optional['state_vector'],
            'sessions_count' => 0,
            'decisions_count' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], 201);
}

// If we got here, method is not supported
jsonError('Method not allowed on /projects. Use GET or POST.', 405);
