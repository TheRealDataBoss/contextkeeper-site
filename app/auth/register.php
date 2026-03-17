<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Csrf.php';

Csrf::init();

$error = '';
$name = '';
$email = '';

// Already logged in? Go to dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: /app/dashboard/');
    exit;
}

// Handle registration POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please reload the page and try again.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $passwordConfirm = $_POST['password_confirm'] ?? '';

        if (empty($name) || empty($email) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $passwordConfirm) {
            $error = 'Passwords do not match.';
        } else {
            $db = Database::getInstance();

            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error = 'An account with this email already exists.';
            } else {
                $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $apiKey = Auth::generateApiKey();

                $stmt = $db->prepare(
                    "INSERT INTO users (email, password_hash, name, plan, api_key)
                     VALUES (?, ?, ?, 'free', ?)"
                );
                $stmt->execute([$email, $passwordHash, $name, $apiKey]);
                $userId = (int)$db->lastInsertId();

                // Session fixation protection
                session_regenerate_id(true);

                $_SESSION['user_id'] = $userId;
                $_SESSION['user_email'] = $email;
                $_SESSION['user_name'] = $name;
                $_SESSION['user_plan'] = 'free';

                header('Location: /app/dashboard/');
                exit;
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
  <title>Create Account - contextkeeper</title>
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
    .btn-register {
      background: var(--cyan); color: var(--bg); font-weight: 600;
      border: none; border-radius: 8px; padding: 0.7rem; width: 100%;
      font-size: 0.95rem; transition: all 0.2s; margin-top: 0.5rem;
    }
    .btn-register:hover { background: #33d4ff; color: var(--bg); transform: translateY(-1px); }
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
    .plan-badge {
      display: inline-flex; align-items: center; gap: 0.4rem;
      background: var(--cyan-dim); border: 1px solid rgba(0,200,255,0.2);
      border-radius: 999px; padding: 0.2rem 0.75rem; font-size: 0.78rem;
      color: var(--cyan); font-weight: 500;
    }
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
  </style>
</head>
<body>
  <div class="auth-card">
    <a href="/" class="back-link"><i class="bi bi-arrow-left"></i> Back to contextkeeper.org</a>
    <a href="/" class="auth-brand">context<span>keeper</span></a>
    <p class="auth-subtitle">Create your free account <span class="plan-badge"><i class="bi bi-gift"></i> Free tier</span></p>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <?= Csrf::field() ?>
      <div class="mb-3">
        <label for="name" class="form-label">Full Name</label>
        <input type="text" class="form-control" id="name" name="name"
               value="<?= htmlspecialchars($name) ?>" placeholder="Steven Wazlavek" required autofocus>
      </div>
      <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email"
               value="<?= htmlspecialchars($email) ?>" placeholder="you@example.com" required>
      </div>
      <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="pw-wrapper">
          <input type="password" class="form-control" id="password" name="password"
                 placeholder="At least 8 characters" required minlength="8">
          <button type="button" class="pw-toggle" onclick="togglePw('password', this)" title="Show password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
      <div class="mb-3">
        <label for="password_confirm" class="form-label">Confirm Password</label>
        <div class="pw-wrapper">
          <input type="password" class="form-control" id="password_confirm" name="password_confirm"
                 placeholder="Confirm your password" required minlength="8">
          <button type="button" class="pw-toggle" onclick="togglePw('password_confirm', this)" title="Show password">
            <i class="bi bi-eye"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-register">Create Account</button>
    </form>

    <div class="auth-footer">
      Already have an account? <a href="/app/auth/login.php">Sign in</a>
    </div>
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
