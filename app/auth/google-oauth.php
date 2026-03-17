<?php
/**
 * Google OAuth 2.0 Callback Handler
 * 
 * Flow:
 *   1. User clicks "Sign in with Google" on login page
 *   2. Redirected to Google consent screen
 *   3. Google redirects back here with ?code=...
 *   4. We exchange code for tokens, get user profile
 *   5. Create or log in the user
 * 
 * Requires: GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in config.php
 */

session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Database.php';

// Bail if Google OAuth not configured
if (empty(GOOGLE_CLIENT_ID) || empty(GOOGLE_CLIENT_SECRET)) {
    header('Location: /app/auth/login.php?error=google_not_configured');
    exit;
}

// Step 1: If no code parameter, redirect to Google
if (empty($_GET['code'])) {
    // Generate state token for CSRF protection
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;

    $params = http_build_query([
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account',
    ]);

    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    exit;
}

// Step 2: Validate state
if (empty($_GET['state']) || empty($_SESSION['google_oauth_state'])
    || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    unset($_SESSION['google_oauth_state']);
    header('Location: /app/auth/login.php?error=invalid_state');
    exit;
}
unset($_SESSION['google_oauth_state']);

// Step 3: Exchange code for tokens
$code = $_GET['code'];
$tokenResponse = curlPost('https://oauth2.googleapis.com/token', [
    'code' => $code,
    'client_id' => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'grant_type' => 'authorization_code',
]);

if (empty($tokenResponse['access_token'])) {
    error_log('contextkeeper Google OAuth: Token exchange failed. ' . json_encode($tokenResponse));
    header('Location: /app/auth/login.php?error=token_failed');
    exit;
}

// Step 4: Get user profile
$accessToken = $tokenResponse['access_token'];
$profile = curlGet('https://www.googleapis.com/oauth2/v2/userinfo', $accessToken);

if (empty($profile['id']) || empty($profile['email'])) {
    error_log('contextkeeper Google OAuth: Profile fetch failed. ' . json_encode($profile));
    header('Location: /app/auth/login.php?error=profile_failed');
    exit;
}

$googleId = $profile['id'];
$email = strtolower($profile['email']);
$name = $profile['name'] ?? '';
$avatar = $profile['picture'] ?? '';

// Step 5: Find or create user
$db = Database::getInstance();

// Check if user exists by google_id
$stmt = $db->prepare("SELECT * FROM users WHERE google_id = ? LIMIT 1");
$stmt->execute([$googleId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    // Check if email exists (local account) - link it
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Link Google to existing account
        $db->prepare("UPDATE users SET google_id = ?, google_avatar = ?, auth_provider = 'google' WHERE id = ?")
           ->execute([$googleId, $avatar, $user['id']]);
        $user['google_id'] = $googleId;
    } else {
        // Create new account
        $apiKey = bin2hex(random_bytes(32));
        $db->prepare(
            "INSERT INTO users (email, name, google_id, google_avatar, auth_provider, api_key, plan)
             VALUES (?, ?, ?, ?, 'google', ?, 'free')"
        )->execute([$email, $name, $googleId, $avatar, $apiKey]);

        $userId = (int)$db->lastInsertId();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Step 6: Log in
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['name'];
$_SESSION['user_plan'] = $user['plan'];

// Update avatar and last login
$db->prepare("UPDATE users SET google_avatar = ?, updated_at = NOW() WHERE id = ?")
   ->execute([$avatar, $user['id']]);

header('Location: /app/dashboard/');
exit;

// ---- Helper functions ----

function curlPost(string $url, array $data): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?: [];
}

function curlGet(string $url, string $accessToken): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $accessToken"],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true) ?: [];
}
