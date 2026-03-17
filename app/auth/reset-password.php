<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Csrf.php';

Csrf::init();

$error = '';
$success = '';
$validToken = false;

$token = $_GET['token'] ?? $_POST['token'] ?? '';

if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
    $error = 'Invalid or missing reset token.';
} else {
    $db = Database::getInstance();
    $tokenHash = hash('sha256', $token);

    $stmt = $db->prepare(
        "SELECT pr.*, u.email FROM password_resets pr
         JOIN users u ON u.id = pr.user_id
         WHERE pr.token_hash = ? AND pr.used = 0 AND pr.expires_at > NOW()
         LIMIT 1"
    );
    $stmt->execute([$tokenHash]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reset) {
        $error = 'This reset link has expired or already been used. Please request a new one.';
    } else {
        $validToken = true;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $error = 'Invalid request. Please try again.';
                $validToken = true;
            } else {
                $password = $_POST['password'] ?? '';
                $confirm = $_POST['confirm_password'] ?? '';

                if (strlen($password) < 8) {
                    $error = 'Password must be at least 8 characters.';
                } elseif ($password !== $confirm) {
                    $error = 'Passwords do not match.';
                } else {
                    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                    // Update password
                    $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                       ->execute([$hash, $reset['user_id']]);

                    // Mark token as used
                    $db->prepare("UPDATE password_resets SET used = 1 WHERE id = ?")
                       ->execute([$reset['id']]);

                    $success = 'Password has been reset. You can now sign in with your new password.';
                    $validToken = false;
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password - contextkeeper</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #06080d; --surface: #111722; --border: #1e2a3a;
      --cyan: #00c8ff; --cyan-dim: #00c8ff22;
      --green: #34d399; --red: #ef4444;
      --text: #e2e8f0; --text-dim: #94a3b8; --text-bright: #f8fafc;
    }
    body {
      font-family: 'Outfit', sans-serif; background: var(--bg);
      color: var(--text); min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
    }
    .auth-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 16px; padding: 2.5rem; width: 100%; max-width: 420px;
    }
    .auth-brand {
      font-family: 'JetBrains Mono', monospace; font-weight: 700;
      font-size: 1.4rem; color: var(--text-bright); text-align: center;
      margin-bottom: 0.5rem; text-decoration: none; display: block;
    }
    .auth-brand span { color: var(--cyan); }
    .auth-subtitle { text-align: center; color: var(--text-dim); font-size: 0.9rem; margin-bottom: 2rem; }
    .form-label { color: var(--text-dim); font-size: 0.85rem; font-weight: 500; }
    .form-control {
      background: var(--bg); border: 1px solid var(--border);
      color: var(--text-bright); border-radius: 8px; padding: 0.65rem 1rem;
      font-family: 'Outfit', sans-serif; font-size: 0.95rem;
    }
    .form-control:focus {
      background: var(--bg); border-color: var(--cyan);
      box-shadow: 0 0 0 3px var(--cyan-dim); color: var(--text-bright);
    }
    .btn-login {
      background: var(--cyan); color: var(--bg); font-weight: 600;
      border: none; border-radius: 8px; padding: 0.7rem; width: 100%;
      font-size: 0.95rem; transition: all 0.2s; margin-top: 0.5rem;
    }
    .btn-login:hover { background: #33d4ff; color: var(--bg); transform: translateY(-1px); }
    .auth-footer { text-align: center; margin-top: 1.5rem; font-size: 0.85rem; color: var(--text-dim); }
    .auth-footer a { color: var(--cyan); text-decoration: none; }
    .auth-footer a:hover { text-decoration: underline; }
    .alert-success { background: rgba(52,211,153,0.1); border: 1px solid rgba(52,211,153,0.3); color: var(--green); border-radius: 8px; font-size: 0.88rem; }
    .alert-danger { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--red); border-radius: 8px; font-size: 0.88rem; }
    .pw-wrapper { position: relative; }
    .pw-wrapper .form-control { padding-right: 2.8rem; }
    .pw-toggle {
      position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; color: var(--text-dim); cursor: pointer;
      padding: 0; font-size: 1.1rem; line-height: 1;
    }
    .pw-toggle:hover { color: var(--cyan); }
  </style>
</head>
<body>
  <div class="auth-card">
    <a href="/" class="auth-brand">context<span>keeper</span></a>
    <p class="auth-subtitle">Choose a new password</p>

    <?php if ($success): ?>
      <div class="alert alert-success mb-3"><?= htmlspecialchars($success) ?></div>
      <div class="auth-footer"><a href="/app/auth/login.php">Sign in now</a></div>
    <?php elseif ($error && !$validToken): ?>
      <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
      <div class="auth-footer"><a href="/app/auth/forgot-password.php">Request a new reset link</a></div>
    <?php else: ?>
      <?php if ($error): ?>
        <div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="mb-3">
          <label for="password" class="form-label">New Password</label>
          <div class="pw-wrapper">
            <input type="password" class="form-control" id="password" name="password"
                   placeholder="Minimum 8 characters" required minlength="8">
            <button type="button" class="pw-toggle" onclick="togglePw('password', this)" title="Show password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <div class="mb-3">
          <label for="confirm_password" class="form-label">Confirm Password</label>
          <div class="pw-wrapper">
            <input type="password" class="form-control" id="confirm_password" name="confirm_password"
                   placeholder="Re-enter your password" required minlength="8">
            <button type="button" class="pw-toggle" onclick="togglePw('confirm_password', this)" title="Show password">
              <i class="bi bi-eye"></i>
            </button>
          </div>
        </div>
        <button type="submit" class="btn btn-login">Reset Password</button>
      </form>
    <?php endif; ?>
  </div>
  <script>
  function togglePw(fieldId, btn) {
    var f = document.getElementById(fieldId);
    var icon = btn.querySelector('i');
    if (f.type === 'password') { f.type = 'text'; icon.className = 'bi bi-eye-slash'; btn.title = 'Hide'; }
    else { f.type = 'password'; icon.className = 'bi bi-eye'; btn.title = 'Show'; }
  }
  </script>
</body>
</html>
