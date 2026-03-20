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


