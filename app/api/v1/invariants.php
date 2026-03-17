<?php
/**
 * /api/v1/invariants/:project_slug
 * 
 * GET  /invariants/:slug   - List invariants for a project
 * POST /invariants/:slug   - Register a new invariant
 */

$projectSlug = $segments[1] ?? null;

if (!$projectSlug) {
    jsonError('Project slug required: /api/v1/invariants/{project_slug}', 400);
}

$slug = Validator::slug($projectSlug);
$auth = new Auth();
$project = $auth->requireProjectOwnership($user, $slug);

// ---- GET /invariants/:slug ----
if ($method === 'GET') {
    list($limit, $offset) = Validator::pagination($_GET);

    // Optional filters
    $activeOnly = !isset($_GET['include_inactive']) || $_GET['include_inactive'] !== 'true';
    $scope = isset($_GET['scope']) ? trim($_GET['scope']) : null;
    $search = isset($_GET['q']) ? trim($_GET['q']) : null;

    $where = "WHERE project_id = ?";
    $params = [$project['id']];

    if ($activeOnly) {
        $where .= " AND active = 1";
    }

    if ($scope) {
        $where .= " AND scope = ?";
        $params[] = $scope;
    }

    if ($search) {
        $where .= " AND (name LIKE ? OR assertion LIKE ?)";
        $searchTerm = '%' . $search . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }

    // Count total
    $countStmt = $db->prepare("SELECT COUNT(*) FROM invariants {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Fetch
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare(
        "SELECT id, name, assertion, scope, established_by, active, created_at
         FROM invariants {$where}
         ORDER BY created_at ASC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute($params);
    $invariants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invariants as &$inv) {
        $inv['id'] = (int)$inv['id'];
        $inv['active'] = (bool)$inv['active'];
    }
    unset($inv);

    // Get distinct scopes for filtering
    $scopeStmt = $db->prepare(
        "SELECT DISTINCT scope FROM invariants WHERE project_id = ? AND scope IS NOT NULL ORDER BY scope"
    );
    $scopeStmt->execute([$project['id']]);
    $scopes = $scopeStmt->fetchAll(PDO::FETCH_COLUMN);

    jsonResponse([
        'invariants' => $invariants,
        'total' => $total,
        'project_slug' => $slug,
        'available_scopes' => $scopes,
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

// ---- POST /invariants/:slug ----
if ($method === 'POST') {
    $body = Validator::jsonBody();
    $required = Validator::requireFields($body, ['name']);
    $optional = Validator::optionalFields($body, [
        'assertion' => null,
        'scope' => null,
        'established_by' => null,
    ]);

    $name = Validator::maxLength($required['name'], 255, 'name');
    $assertion = $optional['assertion'];
    $scope = $optional['scope']
        ? Validator::maxLength($optional['scope'], 255, 'scope')
        : null;
    $establishedBy = $optional['established_by']
        ? Validator::maxLength($optional['established_by'], 100, 'established_by')
        : null;

    // Check for duplicate name in this project (active invariants only)
    $stmt = $db->prepare(
        "SELECT id FROM invariants WHERE project_id = ? AND name = ? AND active = 1"
    );
    $stmt->execute([$project['id'], $name]);
    if ($stmt->fetch()) {
        jsonError("An active invariant named '{$name}' already exists in this project.", 409);
    }

    $stmt = $db->prepare(
        "INSERT INTO invariants (project_id, name, assertion, scope, established_by)
         VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $project['id'],
        $name,
        $assertion,
        $scope,
        $establishedBy,
    ]);
    $invariantId = (int)$db->lastInsertId();

    // Log usage
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'invariant_create', ?, ?)"
    )->execute([
        $user['id'],
        $project['id'],
        json_encode(['invariant_id' => $invariantId, 'name' => $name]),
    ]);

    jsonResponse([
        'invariant' => [
            'id' => $invariantId,
            'project_slug' => $slug,
            'name' => $name,
            'assertion' => $assertion,
            'scope' => $scope,
            'established_by' => $establishedBy,
            'active' => true,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], 201);
}

jsonError('Method not allowed on /invariants. Use GET or POST.', 405);
