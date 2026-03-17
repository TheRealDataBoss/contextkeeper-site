<?php
/**
 * GET /api/v1/status
 * 
 * Returns authenticated user's account status, plan info, and current usage.
 * Available to all plans (including free).
 */

if ($method !== 'GET') {
    jsonError('Method not allowed. Use GET.', 405);
}

$auth = new Auth();
$limits = $auth->getPlanLimits($user['plan']);

// Count current projects
$stmt = $db->prepare("SELECT COUNT(*) FROM projects WHERE user_id = ?");
$stmt->execute([$user['id']]);
$projectCount = (int)$stmt->fetchColumn();

// Count sessions this month
$monthStart = date('Y-m-01 00:00:00');
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM sessions_log 
     WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?) 
     AND created_at >= ?"
);
$stmt->execute([$user['id'], $monthStart]);
$sessionCount = (int)$stmt->fetchColumn();

// Count connectors
$stmt = $db->prepare("SELECT COUNT(*) FROM connectors WHERE user_id = ?");
$stmt->execute([$user['id']]);
$connectorCount = (int)$stmt->fetchColumn();

jsonResponse([
    'status' => 'ok',
    'user' => [
        'id' => (int)$user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'plan' => $user['plan'],
        'created_at' => $user['created_at'],
    ],
    'usage' => [
        'projects' => $projectCount,
        'projects_limit' => $limits['projects'],
        'sessions_this_month' => $sessionCount,
        'sessions_limit' => $limits['sessions_per_month'],
        'connectors' => $connectorCount,
        'connectors_limit' => $limits['connectors'],
    ],
    'features' => [
        'api_access' => $limits['api_access'],
        'cloud_sync' => $limits['cloud_sync'],
        'shared_state' => $limits['shared_state'],
    ],
]);
