<?php
/**
 * /api/v1/bundles/:project_slug
 *
 * POST   /bundles/:slug              - Generate a new bundle (ZIP snapshot)
 * GET    /bundles/:slug              - List bundles for a project
 * GET    /bundles/:slug/download/:fn - Download a generated bundle ZIP
 * GET    /bundles/:slug/:id          - Get bundle session details (JSON)
 *
 * Sprint 6 - Bundle Generation Engine
 */

require_once __DIR__ . '/../../lib/BundleService.php';

$projectSlug = $segments[1] ?? null;
$segment2 = $segments[2] ?? null;
$segment3 = $segments[3] ?? null;

if (!$projectSlug) {
    jsonError('Project slug required: /api/v1/bundles/{project_slug}', 400);
}

$slug = Validator::slug($projectSlug);
$auth = new Auth();
$project = $auth->requireProjectOwnership($user, $slug);

$bundleService = new BundleService($db, (int)$user['id']);

// ---- GET /bundles/:slug/download/:filename ----
if ($method === 'GET' && $segment2 === 'download' && $segment3) {
    $filename = basename($segment3);

    // Verify the filename belongs to this project slug
    $expectedPrefix = 'bundle_' . $slug . '_';
    if (strpos($filename, $expectedPrefix) !== 0) {
        jsonError('Bundle does not belong to this project.', 403);
    }

    if (!$bundleService->streamDownload($filename)) {
        jsonError('Bundle file not found.', 404);
    }

    // streamDownload sends headers and body, then we exit
    exit;
}

// ---- GET /bundles/:slug/:id ----
if ($method === 'GET' && $segment2 && $segment2 !== 'download') {
    $bundleId = Validator::positiveInt($segment2, 'bundle_id');

    $stmt = $db->prepare(
        "SELECT * FROM sessions_log
         WHERE id = ? AND project_id = ? AND action = 'bundle'"
    );
    $stmt->execute([$bundleId, $project['id']]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        jsonError('Bundle not found.', 404);
    }

    // Get decisions that existed at bundle creation time
    $stmt = $db->prepare(
        "SELECT id, title, rationale, alternatives_rejected, established_by, created_at
         FROM decisions WHERE project_id = ? AND created_at <= ?
         ORDER BY id ASC"
    );
    $stmt->execute([$project['id'], $session['created_at']]);
    $decisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($decisions as &$d) {
        $d['id'] = (int)$d['id'];
        $d['alternatives_rejected'] = $d['alternatives_rejected']
            ? json_decode($d['alternatives_rejected'], true)
            : null;
    }
    unset($d);

    // Get active invariants at that time
    $stmt = $db->prepare(
        "SELECT id, name, assertion, scope, established_by, created_at
         FROM invariants WHERE project_id = ? AND active = 1 AND created_at <= ?
         ORDER BY id ASC"
    );
    $stmt->execute([$project['id'], $session['created_at']]);
    $invariants = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($invariants as &$inv) {
        $inv['id'] = (int)$inv['id'];
    }
    unset($inv);

    $stateVector = $project['state_vector']
        ? json_decode($project['state_vector'], true)
        : null;

    $bundle = [
        'bundle_id' => (int)$bundleId,
        'project' => [
            'name' => $project['name'],
            'slug' => $project['slug'],
            'state_vector' => $stateVector,
        ],
        'decisions' => $decisions,
        'invariants' => $invariants,
        'generated_at' => $session['created_at'],
        'authority_sha' => $session['authority_sha'],
    ];

    jsonResponse(['bundle' => $bundle]);
}

// ---- GET /bundles/:slug ----
if ($method === 'GET' && !$segment2) {
    // DB records
    $stmt = $db->prepare(
        "SELECT id, agent, decisions_captured, invariants_captured,
                files_captured, authority_sha, created_at
         FROM sessions_log
         WHERE project_id = ? AND action = 'bundle'
         ORDER BY created_at DESC LIMIT 50"
    );
    $stmt->execute([$project['id']]);
    $bundles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($bundles as &$b) {
        $b['id'] = (int)$b['id'];
        $b['decisions_captured'] = (int)$b['decisions_captured'];
        $b['invariants_captured'] = (int)$b['invariants_captured'];
        $b['files_captured'] = (int)$b['files_captured'];
    }
    unset($b);

    // Also list actual ZIP files on disk
    $files = $bundleService->listBundleFiles($slug);

    jsonResponse([
        'bundles' => $bundles,
        'files' => $files,
        'project_slug' => $slug,
    ]);
}

// ---- POST /bundles/:slug ----
if ($method === 'POST') {
    // Check session limit
    if (!$auth->checkSessionLimit($user, $project['id'])) {
        jsonError('Monthly session limit reached. Upgrade your plan.', 429);
    }

    $body = Validator::jsonBody();
    $optional = Validator::optionalFields($body, [
        'agent' => 'api',
    ]);
    $agent = Validator::maxLength($optional['agent'], 50, 'agent');

    try {
        $result = $bundleService->generate($project, $agent);
    } catch (Exception $e) {
        jsonError('Bundle generation failed: ' . $e->getMessage(), 500);
    }

    $downloadUrl = "/app/api/v1/bundles/{$slug}/download/{$result['filename']}";

    jsonResponse([
        'bundle' => [
            'session_id' => $result['session_id'],
            'project_slug' => $slug,
            'filename' => $result['filename'],
            'authority_sha' => $result['authority_sha'],
            'download_url' => $downloadUrl,
            'metadata' => $result['metadata'],
            'created_at' => $result['metadata']['generated_at'],
        ],
    ], 201);
}

jsonError('Method not allowed on /bundles. Use GET or POST.', 405);
