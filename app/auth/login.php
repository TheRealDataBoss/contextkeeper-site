<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Csrf.php';
require_once __DIR__ . '/../lib/LoginLimiter.php';

Csrf::init();

$error = '';
$email = '';

// Already logged in? Go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /app/dashboard/');
    exit;
}

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !empty($_POST['remember_me']);
    $ip = LoginLimiter::getClientIp();
    $limiter = new LoginLimiter();

    // Rate limit check
    if ($limiter->isBlocked($ip)) {
        $error = 'Too many login attempts. Please try again in 15 minutes.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Email and password are required.';
    } else {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Clear failed attempts on success
            $limiter->clearAttempts($ip);

            // Session fixation protection
            session_regenerate_id(true);

            // Re-init CSRF token for new session
            Csrf::regenerate();

            // Remember me: extend session lifetime to 30 days
            if ($remember) {
                $lifetime = 60 * 60 * 24 * 30; // 30 days
                ini_set('session.cookie_lifetime', $lifetime);
                ini_set('session.gc_maxlifetime', $lifetime);
                $params = session_get_cookie_params();
                setcookie(session_name(), session_id(), [
                    'expires' => time() + $lifetime,
                    'path' => $params['path'],
                    'domain' => $params['domain'],
                    'secure' => $params['secure'],
                    'httponly' => $params['httponly'],
                    'samesite' => $params['samesite'] ?? 'Lax'
                ]);
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_plan'] = $user['plan'];

            // Update last login
            $db->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?")->execute([$user['id']]);

            header('Location: /app/dashboard/');
            exit;
        } else {
            // Record failed attempt
            $limiter->recordFailure($ip, $email);
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login - contextkeeper</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #06080d; --bg-alt: #0c1018; --surface: #111722;
      --surface-hover: #182030; --border: #1e2a3a;
      --cyan: #00c8ff; --cyan-dim: #00c8ff22;
      --purple: #8b5cf6; --green: #34d399;
      --amber: #f59e0b; --red: #ef4444;
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
    .auth-subtitle {
      text-align: center; color: var(--text-dim); font-size: 0.9rem;
      margin-bottom: 2rem;
    }
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
    .form-control::placeholder { color: var(--text-dim); opacity: 0.6; }
    .btn-login {
      background: var(--cyan); color: var(--bg); font-weight: 600;
      border: none; border-radius: 8px; padding: 0.7rem; width: 100%;
      font-size: 0.95rem; transition: all 0.2s; margin-top: 0.5rem;
    }
    .btn-login:hover { background: #33d4ff; color: var(--bg); transform: translateY(-1px); }
    .auth-footer {
      text-align: center; margin-top: 1.5rem; font-size: 0.85rem; color: var(--text-dim);
    }
    .auth-footer a { color: var(--cyan); text-decoration: none; }
    .auth-footer a:hover { text-decoration: underline; }
    .alert-danger {
      background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.3);
      color: var(--red); border-radius: 8px; font-size: 0.88rem;
    }
    .back-link {
      display: block; text-align: center; margin-bottom: 1.5rem;
      color: var(--text-dim); font-size: 0.85rem; text-decoration: none;
    }
    .back-link:hover { color: var(--cyan); }
    .pw-wrapper {
      position: relative;
    }
    .pw-wrapper .form-control {
      padding-right: 2.8rem;
    }
    .pw-toggle {
      position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; color: var(--text-dim); cursor: pointer;
      padding: 0; font-size: 1.1rem; line-height: 1;
    }
    .pw-toggle:hover { color: var(--cyan); }
    .remember-row {
      display: flex; align-items: center; justify-content: space-between;
      margin-bottom: 0.25rem;
    }
    .form-check-input {
      background-color: var(--bg); border-color: var(--border);
    }
    .form-check-input:checked {
      background-color: var(--cyan); border-color: var(--cyan);
    }
    .form-check-input:focus {
      box-shadow: 0 0 0 3px var(--cyan-dim); border-color: var(--cyan);
    }
    .form-check-label {
      color: var(--text-dim); font-size: 0.85rem; user-select: none;
    }
  </style>
</head>
<body>
  <div class="auth-card">
    <a href="/" class="back-link"><i class="bi bi-arrow-left"></i> Back to contextkeeper.org</a>
    <a href="/" class="auth-brand">context<span>keeper</span></a>
    <p class="auth-subtitle">Sign in to your account</p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= Csrf::field() ?>
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email"
               value="<?= htmlspecialchars($email) ?>" placeholder="you@example.com" required autofocus>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="pw-wrapper">
          <input type="password" class="form-control" id="password" name="password"
                 placeholder="Your password" required>
          <button type="button" class="pw-toggle" onclick="togglePw('password', this)" title="Show password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
      <div class="remember-row">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me" value="1">
          <label class="form-check-label" for="remember_me">Keep me logged in</label>
        </div>
        <a href="/app/auth/forgot-password.php" style="color:var(--cyan); font-size:0.82rem; text-decoration:none;">Forgot password?</a>
      </div>
      <button type="submit" class="btn btn-login">Sign In</button>
    </form>

    <div class="auth-footer">
      Don't have an account? <a href="/app/auth/register.php">Create one</a>
    </div>

    <?php if (defined('GOOGLE_CLIENT_ID') && GOOGLE_CLIENT_ID !== ''): ?>
    <div style="position:relative; text-align:center; margin:1.5rem 0 1rem;">
      <hr style="border:none; border-top:1px solid var(--border);">
      <span style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); background:var(--surface); padding:0 0.75rem; font-size:0.8rem; color:var(--text-dim);">or</span>
    </div>
    <a href="/app/auth/google-oauth.php"
       style="display:flex; align-items:center; justify-content:center; gap:0.6rem;
              background:var(--bg); border:1px solid var(--border); border-radius:8px;
              padding:0.65rem; color:var(--text-bright); text-decoration:none;
              font-size:0.92rem; font-weight:500; transition:all 0.2s;">
      <svg width="18" height="18" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>
      Continue with Google
    </a>
    <?php endif; ?>
  </div>
  <script>
  function togglePw(fieldId, btn) {
    var f = document.getElementById(fieldId);
    var icon = btn.querySelector('i');
    if (f.type === 'password') {
      f.type = 'text';
      icon.className = 'bi bi-eye-slash';
      btn.title = 'Hide password';
    } else {
      f.type = 'password';
      icon.className = 'bi bi-eye';
      btn.title = 'Show password';
    }
  }
  </script>
</body>
</html>
