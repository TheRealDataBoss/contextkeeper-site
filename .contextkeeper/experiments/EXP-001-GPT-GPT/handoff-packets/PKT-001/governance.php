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
