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
