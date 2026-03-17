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
