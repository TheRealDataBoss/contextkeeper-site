<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Csrf.php';

Csrf::init();

if (empty($_SESSION['user_id'])) { header('Location: /app/auth/login.php'); exit; }

$db = Database::getInstance();
$auth = new Auth();
$user = $auth->getUserById((int)$_SESSION['user_id']);
if (!$user) { session_destroy(); header('Location: /app/auth/login.php'); exit; }

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please reload the page and try again.';
    } else {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        if ($name && $email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?")->execute([$name, $email, $user['id']]);
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $message = 'Profile updated.';
            $user['name'] = $name;
            $user['email'] = $email;
        } else { $error = 'Valid name and email required.'; }
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        if (password_verify($current, $user['password_hash'])) {
            if (strlen($new) >= 8 && $new === $confirm) {
                $hash = password_hash($new, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, $user['id']]);
                $message = 'Password changed.';
            } else { $error = 'New password must be 8+ chars and match confirmation.'; }
        } else { $error = 'Current password is incorrect.'; }
    }

    if ($action === 'regenerate_key') {
        $newKey = $auth->regenerateApiKey($user['id']);
        $user['api_key'] = $newKey;
        $message = 'API key regenerated.';
    }
    } // end CSRF validation
}

$pageTitle = 'Settings - contextkeeper';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $pageTitle ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root { --bg:#06080d; --surface:#111722; --surface-hover:#182030; --border:#1e2a3a; --cyan:#00c8ff; --cyan-dim:#00c8ff22; --green:#34d399; --red:#ef4444; --text:#e2e8f0; --text-dim:#94a3b8; --text-bright:#f8fafc; }
    body { font-family:'Outfit',sans-serif; background:var(--bg); color:var(--text); }
    .dash-nav { background:var(--surface); border-bottom:1px solid var(--border); padding:0.75rem 0; }
    .dash-nav a { text-decoration:none; }
    .settings-card { background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-bottom:1.5rem; }
    .settings-card h3 { font-size:1.1rem; font-weight:600; color:var(--text-bright); margin-bottom:1rem; }
    .form-control { background:var(--bg); border:1px solid var(--border); color:var(--text-bright); border-radius:8px; }
    .form-control:focus { background:var(--bg); border-color:var(--cyan); box-shadow:0 0 0 3px var(--cyan-dim); color:var(--text-bright); }
    .form-label { color:var(--text-dim); font-size:0.85rem; }
    .btn-cyan { background:var(--cyan); color:var(--bg); font-weight:600; border:none; border-radius:8px; padding:0.5rem 1.25rem; font-size:0.88rem; }
    .btn-cyan:hover { background:#33d4ff; color:var(--bg); }
    .api-key-display { font-family:'JetBrains Mono',monospace; font-size:0.8rem; background:var(--bg); border:1px solid var(--border); border-radius:8px; padding:0.75rem 1rem; word-break:break-all; color:var(--cyan); }
    .alert-success { background:rgba(52,211,153,0.1); border:1px solid rgba(52,211,153,0.3); color:var(--green); border-radius:8px; }
    .alert-danger { background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3); color:var(--red); border-radius:8px; }
  </style>
</head>
<body>
  <nav class="dash-nav">
    <div class="container-fluid px-4 d-flex align-items-center justify-content-between" style="max-width:1400px;margin:0 auto;">
      <div class="d-flex align-items-center gap-4">
        <a style="font-family:'JetBrains Mono',monospace;font-weight:700;font-size:1.1rem;color:var(--text-bright);" href="/app/dashboard/">context<span style="color:var(--cyan);">keeper</span></a>
        <div class="d-none d-md-flex gap-1">
          <a href="/app/dashboard/" style="color:var(--text-dim);font-size:0.88rem;padding:0.4rem 0.75rem;">Dashboard</a>
          <a href="/app/dashboard/connectors.php" style="color:var(--text-dim);font-size:0.88rem;padding:0.4rem 0.75rem;">Connectors</a>
          <a href="/app/dashboard/settings.php" style="color:var(--cyan);font-size:0.88rem;padding:0.4rem 0.75rem;font-weight:500;">Settings</a>
          <a href="/app/dashboard/billing.php" style="color:var(--text-dim);font-size:0.88rem;padding:0.4rem 0.75rem;">Billing</a>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span style="color:var(--text-dim);font-size:0.85rem;"><?= htmlspecialchars($user['name'] ?? $user['email']) ?></span>
        <a href="/app/auth/logout.php" style="color:var(--text-dim);font-size:0.85rem;"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </div>
    </div>
  </nav>

  <div class="container-fluid px-4 py-4" style="max-width:800px;margin:0 auto;">
    <h1 style="font-size:1.5rem;font-weight:600;color:var(--text-bright);margin-bottom:1.5rem;">Settings</h1>

    <?php if ($message): ?><div class="alert alert-success mb-3"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Profile -->
    <div class="settings-card">
      <h3><i class="bi bi-person"></i> Profile</h3>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($user['email']) ?>" required>
        </div>
        <button type="submit" class="btn btn-cyan">Save Changes</button>
      </form>
    </div>

    <!-- API Key -->
    <div class="settings-card">
      <h3><i class="bi bi-key"></i> API Key</h3>
      <p style="color:var(--text-dim);font-size:0.88rem;">Use this key in the <code>X-API-Key</code> header for CLI and SDK authentication.</p>
      <div class="api-key-display mb-3" id="apiKeyDisplay"><?= htmlspecialchars($user['api_key'] ?? 'Not generated') ?></div>
      <div class="d-flex gap-2">
        <button class="btn btn-cyan" onclick="navigator.clipboard.writeText(document.getElementById('apiKeyDisplay').textContent).then(()=>{this.textContent='Copied!';setTimeout(()=>{this.textContent='Copy Key'},2000)})">Copy Key</button>
        <form method="POST" style="display:inline;" onsubmit="return confirm('Regenerate API key? Your current key will stop working immediately.')">
          <?= Csrf::field() ?>
          <input type="hidden" name="action" value="regenerate_key">
          <button type="submit" style="background:transparent;color:var(--text-dim);border:1px solid var(--border);border-radius:8px;padding:0.5rem 1.25rem;font-size:0.88rem;">Regenerate</button>
        </form>
      </div>
    </div>

    <!-- Change Password -->
    <div class="settings-card">
      <h3><i class="bi bi-lock"></i> Change Password</h3>
      <form method="POST">
        <?= Csrf::field() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="mb-3">
          <label class="form-label">Current Password</label>
          <input type="password" class="form-control" name="current_password" required>
        </div>
        <div class="mb-3">
          <label class="form-label">New Password</label>
          <input type="password" class="form-control" name="new_password" required minlength="8">
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm New Password</label>
          <input type="password" class="form-control" name="confirm_password" required minlength="8">
        </div>
        <button type="submit" class="btn btn-cyan">Change Password</button>
      </form>
    </div>

    <!-- Account Info -->
    <div class="settings-card">
      <h3><i class="bi bi-info-circle"></i> Account</h3>
      <div class="d-flex flex-column gap-2" style="font-size:0.88rem;">
        <div class="d-flex justify-content-between"><span style="color:var(--text-dim);">Plan</span><span style="color:var(--text-bright);text-transform:uppercase;"><?= htmlspecialchars($user['plan']) ?></span></div>
        <div class="d-flex justify-content-between"><span style="color:var(--text-dim);">Member since</span><span style="color:var(--text-bright);"><?= date('F j, Y', strtotime($user['created_at'])) ?></span></div>
        <div class="d-flex justify-content-between"><span style="color:var(--text-dim);">User ID</span><span style="color:var(--text-bright);"><?= (int)$user['id'] ?></span></div>
      </div>
    </div>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
