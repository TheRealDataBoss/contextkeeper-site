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
