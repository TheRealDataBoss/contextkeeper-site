================================================================================
FILE: index.php
PATH: C:\Users\Steven\contextkeeper-site\app\api\v1\index.php
================================================================================
<?php
/**
 * contextkeeper API v1 Router
 * 
 * All API requests hit this file via .htaccess rewrite:
 *   RewriteRule ^api/v1/(.*)$ api/v1/index.php?route=$1 [QSA,L]
 * 
 * Handles routing, CORS, JSON response formatting, and error handling.
 */

// Strict error reporting in dev, silent in production
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Load config + libraries
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Validator.php';

// ---- CORS Headers ----
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://contextkeeper.org');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ---- Helper Functions ----

function jsonResponse(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function jsonError(string $message, int $status = 400, ?array $details = null): void {
    $payload = ['error' => true, 'message' => $message];
    if ($details !== null) {
        $payload['details'] = $details;
    }
    jsonResponse($payload, $status);
}

// ---- Parse Route ----
$route = trim($_GET['route'] ?? '', '/');
$method = $_SERVER['REQUEST_METHOD'];
$segments = $route ? explode('/', $route) : [];

// ---- Public Routes (no auth) ----
if ($route === 'webhooks/stripe' && $method === 'POST') {
    require __DIR__ . '/webhooks/stripe.php';
    exit;
}

// ---- Authenticate ----
$auth = new Auth();
$user = $auth->authenticateApiRequest();

if (!$user) {
    jsonError('Authentication required. Provide X-API-Key header or valid session.', 401);
}

// ---- Rate Limiting (simple, per-user, per-minute) ----
$db = Database::getInstance();
$rateLimitKey = 'api_rate_' . $user['id'];
$minuteAgo = date('Y-m-d H:i:s', time() - 60);

$stmt = $db->prepare(
    "SELECT COUNT(*) as cnt FROM usage_log 
     WHERE user_id = ? AND action = 'api_call' AND created_at > ?"
);
$stmt->execute([$user['id'], $minuteAgo]);
$callCount = (int)$stmt->fetchColumn();

$rateLimit = ($user['plan'] === 'free') ? 30 : 120;
if ($callCount >= $rateLimit) {
    header('Retry-After: 60');
    jsonError('Rate limit exceeded. Try again in 60 seconds.', 429);
}

// Log this API call for rate limiting
$db->prepare(
    "INSERT INTO usage_log (user_id, action, metadata) VALUES (?, 'api_call', ?)"
)->execute([$user['id'], json_encode(['route' => $route, 'method' => $method])]);

// ---- Plan-based API access check ----
if ($user['plan'] === 'free') {
    // Free plan: no API access except status endpoint
    $allowedFreeRoutes = ['status'];
    $baseRoute = $segments[0] ?? '';
    if (!in_array($baseRoute, $allowedFreeRoutes, true)) {
        jsonError('API access requires a Pro plan or higher. Upgrade at contextkeeper.org/pricing.', 403);
    }
}

// ---- Route Dispatch ----
$resource = $segments[0] ?? '';

try {
    switch ($resource) {
        case 'status':
            require __DIR__ . '/status.php';
            break;

        case 'projects':
            require __DIR__ . '/projects.php';
            break;

        case 'sync':
            if ($method !== 'POST') jsonError('Method not allowed. Use POST.', 405);
            require __DIR__ . '/sync.php';
            break;

        case 'bootstrap':
            if ($method !== 'POST') jsonError('Method not allowed. Use POST.', 405);
            require __DIR__ . '/bootstrap.php';
            break;

        case 'decisions':
            require __DIR__ . '/decisions.php';
            break;

        case 'invariants':
            require __DIR__ . '/invariants.php';
            break;

        case 'bundles':
            require __DIR__ . '/bundles.php';
            break;

        case 'sessions':
            require __DIR__ . '/sessions.php';
            break;

        case 'connectors':
            require __DIR__ . '/connectors.php';
            break;

        case 'usage':
            require __DIR__ . '/usage.php';
            break;

        default:
            jsonError('Unknown endpoint: /api/v1/' . htmlspecialchars($route), 404);
    }
} catch (PDOException $e) {
    error_log('contextkeeper API DB error: ' . $e->getMessage());
    jsonError('Database error. Please try again later.', 500);
} catch (Exception $e) {
    error_log('contextkeeper API error: ' . $e->getMessage());
    jsonError('Internal server error.', 500);
}


================================================================================
FILE: governance.php
PATH: C:\Users\Steven\contextkeeper-site\app\api\v1\governance.php
================================================================================
<?php
/**
 * contextkeeper Governance API Router
 *
 * Dispatches /api/v1/governance/* to governance endpoint handlers.
 * Called from api/v1/index.php after authentication and rate limiting.
 *
 * Variables in scope from parent:
 *   $user     - authenticated user array
 *   $db       - PDO instance (Database::getInstance())
 *   $method   - HTTP request method
 *   $segments - URL path segments (0='governance', 1+=sub-resource)
 *
 * Functions in scope from parent:
 *   jsonResponse(), jsonError()
 *
 * Classes loaded by parent:
 *   Auth, Database, Validator
 */

$subResource = $segments[1] ?? '';

switch ($subResource) {

    case 'tasks':
        require __DIR__ . '/governance/tasks.php';
        break;

    case 'contracts':
        require __DIR__ . '/governance/contracts.php';
        break;

    case 'source':
        require __DIR__ . '/governance/source.php';
        break;

    case 'gates':
        require __DIR__ . '/governance/gates.php';
        break;

    default:
        jsonError('Unknown governance endpoint: /api/v1/governance/' . htmlspecialchars($subResource), 404);
}


================================================================================
FILE: tasks.php
PATH: C:\Users\Steven\contextkeeper-site\app\api\v1\governance\tasks.php
================================================================================
<?php
/**
 * /api/v1/governance/tasks
 *
 * POST /governance/tasks              - Create a governed task
 * GET  /governance/tasks/:id          - Get task by UUID
 * GET  /governance/tasks?project_id=N - List tasks for a project
 * PUT  /governance/tasks/:id          - Update task status
 * POST /governance/tasks/:id/close    - Close task (governance-enforced)
 *
 * Spec source: task-governance-v2.md
 *
 * Variables in scope from parent router chain (index.php -> governance.php):
 *   $user, $db, $method, $segments
 * Functions in scope: jsonResponse(), jsonError()
 * Classes loaded: Auth, Database, Validator
 */

require_once __DIR__ . '/../../../lib/UUID.php';

$taskId = $segments[2] ?? null;

// ---- GET /governance/tasks/:id ----
if ($method === 'GET' && $taskId) {
    // Validate UUID format (36 chars, hex+hyphens)
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

    // Cast types to match existing response conventions
    $task['project_id'] = (int)$task['project_id'];
    $task['must_complete_before_parent_close'] = (bool)$task['must_complete_before_parent_close'];
    $task['consensus_required'] = $task['consensus_required'] !== null ? (bool)$task['consensus_required'] : null;
    $task['rollback_required'] = (bool)$task['rollback_required'];
    $task['observability_required'] = (bool)$task['observability_required'];
    $task['security_review_required'] = (bool)$task['security_review_required'];
    $task['correction_cycle_count'] = (int)$task['correction_cycle_count'];

    jsonResponse([
        'task' => $task,
    ]);
}

// ---- GET /governance/tasks?project_id=N ----
if ($method === 'GET' && !$taskId) {
    $projectId = $_GET['project_id'] ?? null;
    if (!$projectId) {
        jsonError('Query parameter project_id is required.', 400);
    }
    $projectId = (int)$projectId;

    // Verify project ownership (matches projects.php pattern)
    $auth = new Auth();
    $auth->requireProjectOwnershipById($user, $projectId);

    list($limit, $offset) = Validator::pagination($_GET);

    // Optional filters
    $statusFilter = isset($_GET['status']) ? trim($_GET['status']) : null;
    $classFilter = isset($_GET['task_class']) ? trim($_GET['task_class']) : null;

    $where = "WHERE gt.project_id = ?";
    $params = [$projectId];

    if ($statusFilter !== null) {
        Validator::enum($statusFilter, [
            'open', 'in_progress', 'review', 'blocked', 'complete', 'rejected'
        ], 'status');
        $where .= " AND gt.status = ?";
        $params[] = $statusFilter;
    }

    if ($classFilter !== null) {
        Validator::enum($classFilter, [
            'core', 'child', 'blocker', 'cleanup',
            'offshoot', 'research', 'architecture', 'governance'
        ], 'task_class');
        $where .= " AND gt.task_class = ?";
        $params[] = $classFilter;
    }

    // Count total (matches decisions.php pattern)
    $countStmt = $db->prepare("SELECT COUNT(*) FROM governed_tasks gt {$where}");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    // Fetch with LIMIT (matches decisions.php pattern)
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $db->prepare(
        "SELECT gt.id, gt.project_id, gt.title, gt.task_class, gt.status,
                gt.enterprise_criticality, gt.quality_tier,
                gt.parent_task_id, gt.must_complete_before_parent_close,
                gt.consensus_required, gt.future_rebuild_risk,
                gt.assigned_model, gt.correction_cycle_count,
                gt.created_at, gt.updated_at
         FROM governed_tasks gt
         {$where}
         ORDER BY gt.created_at DESC
         LIMIT ? OFFSET ?"
    );
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Cast types (matches projects.php pattern)
    foreach ($tasks as &$t) {
        $t['project_id'] = (int)$t['project_id'];
        $t['must_complete_before_parent_close'] = (bool)$t['must_complete_before_parent_close'];
        $t['consensus_required'] = $t['consensus_required'] !== null ? (bool)$t['consensus_required'] : null;
        $t['correction_cycle_count'] = (int)$t['correction_cycle_count'];
    }
    unset($t);

    jsonResponse([
        'tasks' => $tasks,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset,
    ]);
}

// ---- POST /governance/tasks ----
if ($method === 'POST' && !$taskId) {
    $body = Validator::jsonBody();

    // Required fields per task-governance-v2.md Section 1
    $required = Validator::requireFields($body, [
        'project_id',
        'title',
        'description',
        'task_class',
        'discovery_reason',
        'enterprise_criticality',
        'quality_tier',
        'acceptance_evidence_required',
        'future_rebuild_risk',
    ]);

    // Validate enums
    Validator::enum($required['task_class'], [
        'core', 'child', 'blocker', 'cleanup',
        'offshoot', 'research', 'architecture', 'governance'
    ], 'task_class');

    Validator::enum($required['enterprise_criticality'], [
        'low', 'medium', 'high', 'platform-critical'
    ], 'enterprise_criticality');

    Validator::enum($required['quality_tier'], [
        'prototype', 'production', 'enterprise', 'regulated-enterprise'
    ], 'quality_tier');

    Validator::enum($required['future_rebuild_risk'], [
        'none', 'low', 'medium', 'high'
    ], 'future_rebuild_risk');

    // Validate lengths
    $title = Validator::maxLength($required['title'], 500, 'title');

    // Verify project ownership (matches projects.php pattern)
    $projectId = (int)$required['project_id'];
    $auth = new Auth();
    $auth->requireProjectOwnershipById($user, $projectId);

    // Optional fields with defaults per task-governance-v2.md Section 6
    $optional = Validator::optionalFields($body, [
        'parent_task_id'                    => null,
        'origin_session_id'                 => null,
        'must_complete_before_parent_close' => false,
        'rollback_required'                 => true,
        'observability_required'            => true,
        'security_review_required'          => false,
        'technical_debt_if_deferred'        => null,
        'assigned_model'                    => null,
    ]);

    // Validate parent task exists in same project if provided
    if ($optional['parent_task_id'] !== null) {
        $parentId = $optional['parent_task_id'];
        if (!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $parentId)) {
            jsonError('Invalid parent_task_id format. Expected UUID v4.', 400);
        }
        $stmt = $db->prepare(
            "SELECT id FROM governed_tasks WHERE id = ? AND project_id = ? LIMIT 1"
        );
        $stmt->execute([$parentId, $projectId]);
        if (!$stmt->fetch()) {
            jsonError('Parent task not found in this project.', 404);
        }
    }

    // Validate optional string lengths
    if ($optional['origin_session_id'] !== null) {
        Validator::maxLength($optional['origin_session_id'], 100, 'origin_session_id');
    }
    if ($optional['assigned_model'] !== null) {
        Validator::maxLength($optional['assigned_model'], 100, 'assigned_model');
    }

    // Compute consensus_required per task-governance-v2.md Section 4
    $consensusRequired = computeConsensusRequired(
        $required['enterprise_criticality'],
        $required['quality_tier']
    );

    $id = UUID::v4();

    $stmt = $db->prepare(
        "INSERT INTO governed_tasks (
            id, project_id, title, description,
            task_class, parent_task_id, origin_session_id, discovery_reason,
            must_complete_before_parent_close,
            enterprise_criticality, quality_tier,
            consensus_required, acceptance_evidence_required,
            rollback_required, observability_required, security_review_required,
            future_rebuild_risk, technical_debt_if_deferred,
            assigned_model, status
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?,
            ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, 'open'
        )"
    );

    $stmt->execute([
        $id,
        $projectId,
        $title,
        $required['description'],
        $required['task_class'],
        $optional['parent_task_id'],
        $optional['origin_session_id'],
        $required['discovery_reason'],
        (int)(bool)$optional['must_complete_before_parent_close'],
        $required['enterprise_criticality'],
        $required['quality_tier'],
        $consensusRequired ? 1 : 0,
        $required['acceptance_evidence_required'],
        (int)(bool)$optional['rollback_required'],
        (int)(bool)$optional['observability_required'],
        (int)(bool)$optional['security_review_required'],
        $required['future_rebuild_risk'],
        $optional['technical_debt_if_deferred'],
        $optional['assigned_model'],
    ]);

    // Log usage (matches decisions.php pattern: user_id, action, project_id, metadata)
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'governance_task_create', ?, ?)"
    )->execute([
        $user['id'],
        $projectId,
        json_encode(['task_id' => $id, 'title' => $title]),
    ]);

    jsonResponse([
        'task' => [
            'id' => $id,
            'project_id' => $projectId,
            'title' => $title,
            'task_class' => $required['task_class'],
            'status' => 'open',
            'enterprise_criticality' => $required['enterprise_criticality'],
            'quality_tier' => $required['quality_tier'],
            'consensus_required' => $consensusRequired,
            'future_rebuild_risk' => $required['future_rebuild_risk'],
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], 201);
}

// ---- POST /governance/tasks/:id/close ----
if ($method === 'POST' && $taskId && isset($segments[3]) && $segments[3] === 'close') {
    $task = fetchTaskForEnforcement($db, $user, $taskId);

    $errors = [];

    // 1. Validate contract exists
    $contractStmt = $db->prepare(
        "SELECT id FROM delivery_contracts WHERE task_id = ? LIMIT 1"
    );
    $contractStmt->execute([$task['id']]);
    if (!$contractStmt->fetch()) {
        $errors[] = 'missing_contract';
    }

    // 2. Validate all 4 gates present and passing
    $gateStmt = $db->prepare(
        "SELECT gate_name, status FROM quality_gate_evaluations
         WHERE task_id = ?
         LIMIT 4"
    );
    $gateStmt->execute([$task['id']]);
    $gateRows = $gateStmt->fetchAll(PDO::FETCH_ASSOC);

    $evaluatedGates = [];
    foreach ($gateRows as $g) {
        $evaluatedGates[$g['gate_name']] = $g['status'];
    }

    $requiredGates = ['build', 'proof', 'operations', 'architecture'];
    $missingGates = [];
    $failedGates = [];

    foreach ($requiredGates as $gate) {
        if (!isset($evaluatedGates[$gate])) {
            $missingGates[] = $gate;
        } elseif ($evaluatedGates[$gate] === 'fail') {
            $failedGates[] = $gate;
        }
    }

    if (!empty($missingGates)) {
        $errors[] = 'missing_gates';
    }
    if (!empty($failedGates)) {
        $errors[] = 'failed_gates';
    }

    // 3. Validate all child tasks with must_complete_before_parent_close are complete
    $childStmt = $db->prepare(
        "SELECT COUNT(*) FROM governed_tasks
         WHERE parent_task_id = ?
         AND must_complete_before_parent_close = 1
         AND status != 'complete'
         LIMIT 1"
    );
    $childStmt->execute([$task['id']]);
    $incompleteChildren = (int)$childStmt->fetchColumn();

    if ($incompleteChildren > 0) {
        $errors[] = 'incomplete_children';
    }

    // If any check failed, return failure with details
    if (!empty($errors)) {
        jsonResponse([
            'success' => false,
            'errors' => $errors,
            'details' => [
                'missing_gates' => $missingGates,
                'failed_gates' => $failedGates,
                'incomplete_children' => $incompleteChildren,
            ],
        ], 409);
    }

    // All checks passed: close the task
    $db->prepare(
        "UPDATE governed_tasks SET status = 'complete', updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    )->execute([$task['id']]);

    // Log usage
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'governance_task_close', ?, ?)"
    )->execute([
        $user['id'],
        $task['project_id'],
        json_encode(['task_id' => $task['id']]),
    ]);

    jsonResponse([
        'success' => true,
        'task_id' => $task['id'],
        'status' => 'complete',
    ]);
}

// ---- PUT /governance/tasks/:id (status transition) ----
if ($method === 'PUT' && $taskId && !isset($segments[3])) {
    $task = fetchTaskForEnforcement($db, $user, $taskId);
    $body = Validator::jsonBody();

    $required = Validator::requireFields($body, ['status']);
    $newStatus = Validator::enum($required['status'], [
        'open', 'in_progress', 'review', 'blocked', 'rejected'
    ], 'status');

    // 'complete' is NOT allowed via PUT; must use POST .../close
    if ($newStatus === 'complete') {
        jsonError('Cannot set status to complete directly. Use POST /governance/tasks/:id/close.', 409);
    }

    $currentStatus = $task['status'];

    // Validate allowed transitions per task-governance-v2.md Section 3.1
    $allowedTransitions = [
        'open'        => ['in_progress', 'blocked'],
        'in_progress' => ['review', 'blocked'],
        'review'      => ['rejected', 'blocked'],
        'rejected'    => ['in_progress', 'blocked'],
        'blocked'     => ['open', 'in_progress'],
        'complete'    => [],
    ];

    $allowed = $allowedTransitions[$currentStatus] ?? [];
    if (!in_array($newStatus, $allowed, true)) {
        jsonError(
            "Invalid status transition: '{$currentStatus}' to '{$newStatus}'. "
            . "Allowed from '{$currentStatus}': " . (empty($allowed) ? 'none' : implode(', ', $allowed)) . '.',
            409
        );
    }

    $db->prepare(
        "UPDATE governed_tasks SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?"
    )->execute([$newStatus, $task['id']]);

    // Log usage
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'governance_task_transition', ?, ?)"
    )->execute([
        $user['id'],
        $task['project_id'],
        json_encode([
            'task_id' => $task['id'],
            'from' => $currentStatus,
            'to' => $newStatus,
        ]),
    ]);

    jsonResponse([
        'task_id' => $task['id'],
        'previous_status' => $currentStatus,
        'status' => $newStatus,
    ]);
}

// If we got here, method is not supported
jsonError('Method not allowed on /governance/tasks. Use GET, POST, or PUT.', 405);


// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Fetch a governed task with ownership verification.
 * Used by close and status transition endpoints.
 */
function fetchTaskForEnforcement(PDO $db, array $user, string $taskId): array {
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

/**
 * Compute whether consensus is required based on criticality and quality tier.
 * Per task-governance-v2.md Section 4.
 *
 * Always required: regulated-enterprise, platform-critical
 * Default required: high criticality
 * Not required: low, medium criticality with non-regulated tier
 */
function computeConsensusRequired(string $criticality, string $qualityTier): bool {
    if ($qualityTier === 'regulated-enterprise') {
        return true;
    }
    if ($criticality === 'platform-critical') {
        return true;
    }
    if ($criticality === 'high') {
        return true;
    }
    return false;
}


================================================================================
FILE: contracts.php
PATH: C:\Users\Steven\contextkeeper-site\app\api\v1\governance\contracts.php
================================================================================
<?php
/**
 * /api/v1/governance/contracts
 *
 * POST /governance/contracts              - Create delivery contract for a task
 * GET  /governance/contracts/:task_id     - Retrieve contract for a task
 * PUT  /governance/contracts/:id          - Update contract (versioned, append-only history)
 *
 * G1.2 scope. Spec source: governance-mapping.md mechanism 1, whitepaper Section 3.1
 *
 * Variables in scope from parent router chain (index.php -> governance.php):
 *   $user, $db, $method, $segments
 * Functions in scope: jsonResponse(), jsonError()
 * Classes loaded: Auth, Database, Validator, UUID
 */

$contractParam = $segments[2] ?? null;

// ---- POST /governance/contracts ----
if ($method === 'POST' && !$contractParam) {
    $body = Validator::jsonBody();

    $required = Validator::requireFields($body, ['task_id']);
    $taskId = $required['task_id'];

    // Verify task ownership via project
    $task = fetchTaskForContracts($db, $user, $taskId);

    // Check for existing contract (one contract per task)
    $existStmt = $db->prepare(
        "SELECT id FROM delivery_contracts WHERE task_id = ? LIMIT 1"
    );
    $existStmt->execute([$task['id']]);
    if ($existStmt->fetch()) {
        jsonError('Delivery contract already exists for this task. Use PUT to update.', 409);
    }

    // All 10 contract fields are required per whitepaper Section 3.1
    $contractFields = requireAllContractFields($body);

    $id = UUID::v4();

    $stmt = $db->prepare(
        "INSERT INTO delivery_contracts (
            id, task_id,
            system_invariants, operational_assumptions, security_requirements,
            failure_mode_requirements, observability_requirements, performance_constraints,
            idempotency_requirements, migration_rollback_plan,
            verification_evidence_required, review_failure_criteria,
            version
        ) VALUES (
            ?, ?,
            ?, ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, ?,
            1
        )"
    );

    // Build params in column order to match the INSERT
    $orderedParams = [
        $id,
        $task['id'],
        Validator::jsonData($contractFields['system_invariants'], 'system_invariants'),
        Validator::jsonData($contractFields['operational_assumptions'], 'operational_assumptions'),
        Validator::jsonData($contractFields['security_requirements'], 'security_requirements'),
        Validator::jsonData($contractFields['failure_mode_requirements'], 'failure_mode_requirements'),
        Validator::jsonData($contractFields['observability_requirements'], 'observability_requirements'),
        Validator::jsonData($contractFields['performance_constraints'], 'performance_constraints'),
        Validator::jsonData($contractFields['idempotency_requirements'], 'idempotency_requirements'),
        $contractFields['migration_rollback_plan'],
        Validator::jsonData($contractFields['verification_evidence_required'], 'verification_evidence_required'),
        Validator::jsonData($contractFields['review_failure_criteria'], 'review_failure_criteria'),
    ];

    $stmt->execute($orderedParams);


    // Record version 1 in append-only history
    $snapshot = buildContractSnapshot($contractFields);
    recordContractVersion($db, $id, 1, $snapshot, $user);

    // Log usage (matches decisions.php pattern)
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'governance_contract_create', ?, ?)"
    )->execute([
        $user['id'],
        $task['project_id'],
        json_encode(['contract_id' => $id, 'task_id' => $task['id']]),
    ]);

    jsonResponse([
        'contract' => [
            'id' => $id,
            'task_id' => $task['id'],
            'version' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ],
    ], 201);
}

// ---- GET /governance/contracts/:task_id ----
if ($method === 'GET' && $contractParam) {
    $task = fetchTaskForContracts($db, $user, $contractParam);

    $stmt = $db->prepare(
        "SELECT * FROM delivery_contracts WHERE task_id = ? LIMIT 1"
    );
    $stmt->execute([$task['id']]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        jsonError('No delivery contract found for this task.', 404);
    }

    // Decode JSON fields for response
    $jsonFields = [
        'system_invariants', 'operational_assumptions', 'security_requirements',
        'failure_mode_requirements', 'observability_requirements', 'performance_constraints',
        'idempotency_requirements', 'verification_evidence_required', 'review_failure_criteria',
    ];
    foreach ($jsonFields as $f) {
        if (is_string($contract[$f])) {
            $contract[$f] = json_decode($contract[$f], true);
        }
    }

    $contract['version'] = (int)$contract['version'];

    jsonResponse([
        'contract' => $contract,
    ]);
}

// ---- PUT /governance/contracts/:id ----
if ($method === 'PUT' && $contractParam) {
    // Fetch existing contract with ownership check
    $stmt = $db->prepare(
        "SELECT dc.*, gt.project_id FROM delivery_contracts dc
         JOIN governed_tasks gt ON dc.task_id = gt.id
         JOIN projects p ON gt.project_id = p.id
         WHERE dc.id = ? AND p.user_id = ?
         LIMIT 1"
    );
    $stmt->execute([$contractParam, $user['id']]);
    $contract = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$contract) {
        // Maybe the param is a task_id, not a contract_id.
        // Try looking up by task_id for better error messaging.
        jsonError('Delivery contract not found.', 404);
    }

    $body = Validator::jsonBody();

    // Require change_reason for audit trail
    if (!isset($body['change_reason']) || (is_string($body['change_reason']) && trim($body['change_reason']) === '')) {
        jsonError('Field change_reason is required for contract updates.', 400);
    }
    $changeReason = trim($body['change_reason']);

    // Collect updatable fields
    $allContractFieldNames = [
        'system_invariants', 'operational_assumptions', 'security_requirements',
        'failure_mode_requirements', 'observability_requirements', 'performance_constraints',
        'idempotency_requirements', 'migration_rollback_plan',
        'verification_evidence_required', 'review_failure_criteria',
    ];

    $jsonFieldNames = [
        'system_invariants', 'operational_assumptions', 'security_requirements',
        'failure_mode_requirements', 'observability_requirements', 'performance_constraints',
        'idempotency_requirements', 'verification_evidence_required', 'review_failure_criteria',
    ];

    $sets = [];
    $params = [];
    $hasUpdates = false;

    foreach ($allContractFieldNames as $f) {
        if (array_key_exists($f, $body)) {
            $hasUpdates = true;
            if (in_array($f, $jsonFieldNames, true)) {
                $sets[] = "`{$f}` = ?";
                $params[] = Validator::jsonData($body[$f], $f);
            } else {
                // migration_rollback_plan is TEXT, not JSON
                $val = is_string($body[$f]) ? trim($body[$f]) : $body[$f];
                $sets[] = "`{$f}` = ?";
                $params[] = $val;
            }
        }
    }

    if (!$hasUpdates) {
        jsonError('No contract fields provided for update.', 400);
    }

    $newVersion = (int)$contract['version'] + 1;
    $sets[] = "`version` = ?";
    $params[] = $newVersion;

    $params[] = $contract['id'];
    $sql = "UPDATE delivery_contracts SET " . implode(', ', $sets) . " WHERE id = ?";
    $db->prepare($sql)->execute($params);

    // Re-fetch updated contract for history snapshot
    $refetchStmt = $db->prepare("SELECT * FROM delivery_contracts WHERE id = ? LIMIT 1");
    $refetchStmt->execute([$contract['id']]);
    $updated = $refetchStmt->fetch(PDO::FETCH_ASSOC);

    // Build snapshot from the updated state
    $snapshot = [];
    foreach ($allContractFieldNames as $f) {
        $snapshot[$f] = $updated[$f];
    }

    recordContractVersion($db, $contract['id'], $newVersion, $snapshot, $user, $changeReason);

    // Log usage
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'governance_contract_update', ?, ?)"
    )->execute([
        $user['id'],
        $contract['project_id'],
        json_encode([
            'contract_id' => $contract['id'],
            'version' => $newVersion,
            'change_reason' => $changeReason,
        ]),
    ]);

    jsonResponse([
        'contract' => [
            'id' => $contract['id'],
            'task_id' => $contract['task_id'],
            'version' => $newVersion,
            'updated_at' => date('Y-m-d H:i:s'),
        ],
    ]);
}

// Fallback
jsonError('Method not allowed on /governance/contracts. Use GET, POST, or PUT.', 405);


// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Fetch a governed task and verify the authenticated user owns the parent project.
 * Accepts UUID task_id. Returns task row or calls jsonError(404).
 */
function fetchTaskForContracts(PDO $db, array $user, string $taskId): array {
    // Validate UUID format
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

/**
 * Validate that all 10 delivery contract fields are present.
 * Returns cleaned array. Calls jsonError if any are missing.
 */
function requireAllContractFields(array $data): array {
    $fields = [
        'system_invariants', 'operational_assumptions', 'security_requirements',
        'failure_mode_requirements', 'observability_requirements', 'performance_constraints',
        'idempotency_requirements', 'migration_rollback_plan',
        'verification_evidence_required', 'review_failure_criteria',
    ];

    $missing = [];
    $clean = [];

    foreach ($fields as $f) {
        if (!isset($data[$f]) || (is_string($data[$f]) && trim($data[$f]) === '')) {
            $missing[] = $f;
        } else {
            $clean[$f] = $data[$f];
        }
    }

    if (!empty($missing)) {
        jsonError(
            'All 10 delivery contract fields are required. Missing: ' . implode(', ', $missing),
            400,
            ['missing_fields' => $missing]
        );
    }

    return $clean;
}

/**
 * Build a snapshot array from contract fields for history storage.
 */
function buildContractSnapshot(array $contractFields): array {
    return $contractFields;
}

/**
 * Record a contract version in the append-only history table.
 */
function recordContractVersion(
    PDO $db,
    string $contractId,
    int $version,
    array $snapshot,
    array $user,
    ?string $changeReason = null
): void {
    $historyId = UUID::v4();
    $stmt = $db->prepare(
        "INSERT INTO delivery_contract_history
         (id, contract_id, version, snapshot, changed_by, change_reason)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $historyId,
        $contractId,
        $version,
        json_encode($snapshot),
        'user:' . $user['id'],
        $changeReason,
    ]);
}



================================================================================
FILE: source.php
PATH: C:\Users\Steven\contextkeeper-site\app\api\v1\governance\source.php
================================================================================
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


================================================================================
FILE: gates.php
PATH: C:\Users\Steven\contextkeeper-site\app\api\v1\governance\gates.php
================================================================================
<?php
/**
 * /api/v1/governance/gates
 *
 * POST /governance/gates/evaluate         - Record or update a gate evaluation
 * GET  /governance/gates?task_id=...      - List all 4 gates with status for a task
 * GET  /governance/gates/status?task_id=... - Aggregate pass/fail/missing summary
 *
 * G1.4 scope. Spec source: governance-mapping.md mechanism 2, whitepaper Section 3.2
 *
 * Gate evaluation is recording only. Gates are not auto-computed.
 * 'pending' is implicit: if no row exists for a gate, it is pending.
 *
 * Variables in scope from parent router chain (index.php -> governance.php):
 *   $user, $db, $method, $segments
 * Functions in scope: jsonResponse(), jsonError()
 * Classes loaded: Auth, Database, Validator, UUID
 */

$action = $segments[2] ?? null;

$validGates = ['build', 'proof', 'operations', 'architecture'];

// ---- POST /governance/gates/evaluate ----
if ($method === 'POST' && $action === 'evaluate') {
    $body = Validator::jsonBody();

    $required = Validator::requireFields($body, [
        'task_id', 'gate_name', 'status', 'evaluation_notes',
    ]);

    $taskId = $required['task_id'];
    $gateName = Validator::enum($required['gate_name'], $validGates, 'gate_name');
    $status = Validator::enum($required['status'], ['pass', 'fail'], 'status');
    $notes = $required['evaluation_notes'];

    if (is_string($notes) && trim($notes) === '') {
        jsonError('evaluation_notes must not be empty.', 400);
    }

    // Verify task ownership via project
    $task = fetchTaskForGates($db, $user, $taskId);

    // Enforce sequential gate order: build -> proof -> operations -> architecture
    // A gate can only be evaluated if all prior gates in the sequence have been evaluated.
    $gateSequence = ['build', 'proof', 'operations', 'architecture'];
    $currentIndex = array_search($gateName, $gateSequence);

    if ($currentIndex > 0) {
        $prerequisiteGates = array_slice($gateSequence, 0, $currentIndex);
        $placeholders = implode(',', array_fill(0, count($prerequisiteGates), '?'));

        $prereqStmt = $db->prepare(
            "SELECT gate_name FROM quality_gate_evaluations
             WHERE task_id = ? AND gate_name IN ({$placeholders})
             LIMIT " . count($prerequisiteGates)
        );
        $prereqParams = array_merge([$task['id']], $prerequisiteGates);
        $prereqStmt->execute($prereqParams);
        $evaluatedPrereqs = $prereqStmt->fetchAll(PDO::FETCH_COLUMN);

        $missingPrereqs = array_diff($prerequisiteGates, $evaluatedPrereqs);
        if (!empty($missingPrereqs)) {
            jsonError(
                "Cannot evaluate '{$gateName}' gate: prerequisite gate(s) not yet evaluated: "
                . implode(', ', $missingPrereqs) . '. '
                . 'Gates must be evaluated in order: build, proof, operations, architecture.',
                409
            );
        }
    }

    $evaluatedBy = 'user:' . $user['id'];

    // Upsert: check for existing evaluation for this task+gate
    $existStmt = $db->prepare(
        "SELECT id FROM quality_gate_evaluations
         WHERE task_id = ? AND gate_name = ?
         LIMIT 1"
    );
    $existStmt->execute([$task['id'], $gateName]);
    $existing = $existStmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing evaluation
        $db->prepare(
            "UPDATE quality_gate_evaluations
             SET status = ?, evaluated_by = ?, evaluation_notes = ?, created_at = CURRENT_TIMESTAMP
             WHERE id = ?"
        )->execute([$status, $evaluatedBy, $notes, $existing['id']]);

        $resultId = $existing['id'];
        $resultAction = 'updated';
    } else {
        // Insert new evaluation
        $resultId = UUID::v4();
        $db->prepare(
            "INSERT INTO quality_gate_evaluations
             (id, task_id, gate_name, status, evaluated_by, evaluation_notes)
             VALUES (?, ?, ?, ?, ?, ?)"
        )->execute([$resultId, $task['id'], $gateName, $status, $evaluatedBy, $notes]);

        $resultAction = 'created';
    }

    // Log usage (matches decisions.php pattern: user_id, action, project_id, metadata)
    $db->prepare(
        "INSERT INTO usage_log (user_id, action, project_id, metadata) VALUES (?, 'governance_gate_evaluate', ?, ?)"
    )->execute([
        $user['id'],
        $task['project_id'],
        json_encode([
            'evaluation_id' => $resultId,
            'task_id' => $task['id'],
            'gate_name' => $gateName,
            'status' => $status,
            'action' => $resultAction,
        ]),
    ]);

    jsonResponse([
        'evaluation' => [
            'id' => $resultId,
            'task_id' => $task['id'],
            'gate_name' => $gateName,
            'status' => $status,
            'action' => $resultAction,
        ],
    ], 201);
}

// ---- GET /governance/gates/status?task_id=... ----
if ($method === 'GET' && $action === 'status') {
    $taskId = $_GET['task_id'] ?? null;
    if (!$taskId) {
        jsonError('Query parameter task_id is required.', 400);
    }

    $task = fetchTaskForGates($db, $user, $taskId);

    // Fetch all evaluations for this task
    $stmt = $db->prepare(
        "SELECT gate_name, status FROM quality_gate_evaluations
         WHERE task_id = ?
         LIMIT 4"
    );
    $stmt->execute([$task['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build gate map from stored evaluations
    $evaluated = [];
    foreach ($rows as $r) {
        $evaluated[$r['gate_name']] = $r['status'];
    }

    $missing = [];
    $failed = [];
    $allPassed = true;

    foreach ($validGates as $gate) {
        if (!isset($evaluated[$gate])) {
            $missing[] = $gate;
            $allPassed = false;
        } elseif ($evaluated[$gate] === 'fail') {
            $failed[] = $gate;
            $allPassed = false;
        }
    }

    jsonResponse([
        'status' => [
            'task_id' => $task['id'],
            'all_passed' => $allPassed,
            'missing' => $missing,
            'failed' => $failed,
        ],
    ]);
}

// ---- GET /governance/gates?task_id=... ----
if ($method === 'GET' && !$action) {
    $taskId = $_GET['task_id'] ?? null;
    if (!$taskId) {
        jsonError('Query parameter task_id is required.', 400);
    }

    $task = fetchTaskForGates($db, $user, $taskId);

    // Fetch all evaluations for this task
    $stmt = $db->prepare(
        "SELECT gate_name, status, evaluated_by, evaluation_notes, created_at
         FROM quality_gate_evaluations
         WHERE task_id = ?
         LIMIT 4"
    );
    $stmt->execute([$task['id']]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build gate map from stored evaluations
    $evaluated = [];
    foreach ($rows as $r) {
        $evaluated[$r['gate_name']] = $r;
    }

    // Synthesize all 4 gates, pending if not evaluated
    $gates = [];
    foreach ($validGates as $gate) {
        if (isset($evaluated[$gate])) {
            $gates[] = [
                'gate_name' => $gate,
                'status' => $evaluated[$gate]['status'],
                'evaluated_by' => $evaluated[$gate]['evaluated_by'],
                'evaluation_notes' => $evaluated[$gate]['evaluation_notes'],
                'created_at' => $evaluated[$gate]['created_at'],
            ];
        } else {
            $gates[] = [
                'gate_name' => $gate,
                'status' => 'pending',
                'evaluated_by' => null,
                'evaluation_notes' => null,
                'created_at' => null,
            ];
        }
    }

    jsonResponse([
        'gates' => [
            'task_id' => $task['id'],
            'evaluations' => $gates,
        ],
    ]);
}

// Fallback
jsonError('Unknown gates action. Use POST /evaluate, GET /, or GET /status.', 404);


// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Fetch a governed task and verify the authenticated user owns the parent project.
 * Returns task row or calls jsonError(404).
 */
function fetchTaskForGates(PDO $db, array $user, string $taskId): array {
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


================================================================================
FILE: UUID.php
PATH: C:\Users\Steven\contextkeeper-site\app\lib\UUID.php
================================================================================
<?php
/**
 * contextkeeper UUID Helper
 *
 * Generates RFC 4122 version 4 UUIDs for governance table primary keys.
 * Uses cryptographically secure random bytes via random_bytes().
 */

class UUID {

    /**
     * Generate a version 4 UUID.
     *
     * @return string 36-character UUID (lowercase hex with hyphens)
     */
    public static function v4(): string {
        $bytes = random_bytes(16);

        // Set version to 0100 (UUID v4)
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        // Set variant to 10xx (RFC 4122)
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return sprintf(
            '%s-%s-%s-%s-%s',
            bin2hex(substr($bytes, 0, 4)),
            bin2hex(substr($bytes, 4, 2)),
            bin2hex(substr($bytes, 6, 2)),
            bin2hex(substr($bytes, 8, 2)),
            bin2hex(substr($bytes, 10, 6))
        );
    }
}


