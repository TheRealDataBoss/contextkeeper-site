<?php
/**
 * GET /api/v1/usage
 * 
 * Returns usage statistics for the current billing period.
 * Includes breakdown by action type, daily activity, and top projects.
 */

if ($method !== 'GET') {
    jsonError('Method not allowed. Use GET.', 405);
}

$monthStart = date('Y-m-01 00:00:00');
$monthEnd = date('Y-m-t 23:59:59');

// Sessions this month (across all projects)
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM sessions_log 
     WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)
     AND created_at >= ? AND created_at <= ?"
);
$stmt->execute([$user['id'], $monthStart, $monthEnd]);
$sessionsThisMonth = (int)$stmt->fetchColumn();

// Sessions by action type
$stmt = $db->prepare(
    "SELECT action, COUNT(*) as count 
     FROM sessions_log 
     WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)
     AND created_at >= ? AND created_at <= ?
     GROUP BY action"
);
$stmt->execute([$user['id'], $monthStart, $monthEnd]);
$sessionsByAction = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Decisions this month
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM decisions 
     WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)
     AND created_at >= ? AND created_at <= ?"
);
$stmt->execute([$user['id'], $monthStart, $monthEnd]);
$decisionsThisMonth = (int)$stmt->fetchColumn();

// API calls this month
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM usage_log 
     WHERE user_id = ? AND action = 'api_call'
     AND created_at >= ? AND created_at <= ?"
);
$stmt->execute([$user['id'], $monthStart, $monthEnd]);
$apiCallsThisMonth = (int)$stmt->fetchColumn();

// Daily activity (last 30 days)
$stmt = $db->prepare(
    "SELECT DATE(created_at) as date, COUNT(*) as sessions
     FROM sessions_log 
     WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)
     AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     GROUP BY DATE(created_at)
     ORDER BY date ASC"
);
$stmt->execute([$user['id']]);
$dailyActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top projects by session count this month
$stmt = $db->prepare(
    "SELECT p.name, p.slug, COUNT(s.id) as session_count
     FROM projects p
     LEFT JOIN sessions_log s ON p.id = s.project_id 
         AND s.created_at >= ? AND s.created_at <= ?
     WHERE p.user_id = ?
     GROUP BY p.id
     ORDER BY session_count DESC
     LIMIT 10"
);
$stmt->execute([$monthStart, $monthEnd, $user['id']]);
$topProjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($topProjects as &$tp) {
    $tp['session_count'] = (int)$tp['session_count'];
}
unset($tp);

// Plan limits for context
$auth = new Auth();
$limits = $auth->getPlanLimits($user['plan']);

jsonResponse([
    'period' => [
        'start' => $monthStart,
        'end' => $monthEnd,
    ],
    'usage' => [
        'sessions' => $sessionsThisMonth,
        'sessions_limit' => $limits['sessions_per_month'],
        'sessions_remaining' => $limits['sessions_per_month'] === -1
            ? -1
            : max(0, $limits['sessions_per_month'] - $sessionsThisMonth),
        'decisions_recorded' => $decisionsThisMonth,
        'api_calls' => $apiCallsThisMonth,
    ],
    'sessions_by_action' => $sessionsByAction,
    'daily_activity' => $dailyActivity,
    'top_projects' => $topProjects,
    'plan' => $user['plan'],
]);
