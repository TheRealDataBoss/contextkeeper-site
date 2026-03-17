<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Csrf.php';

Csrf::init();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $email = trim($_POST['email'] ?? '');
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $db = Database::getInstance();

            // Always show success to prevent email enumeration
            $success = 'If an account exists with that email, a password reset link has been sent. Check your inbox.';

            $stmt = $db->prepare("SELECT id, name FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                // Invalidate old tokens
                $db->prepare("UPDATE password_resets SET used = 1 WHERE user_id = ? AND used = 0")
                   ->execute([$user['id']]);

                $db->prepare(
                    "INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)"
                )->execute([$user['id'], $tokenHash, $expiresAt]);

                $resetUrl = APP_URL . '/app/auth/reset-password.php?token=' . $token;

                // Send via Resend API
                if (defined('RESEND_API_KEY') && RESEND_API_KEY !== '') {
                    $name = htmlspecialchars($user['name'] ?? 'there');
                    $html = <<<HTML
<div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;max-width:480px;margin:0 auto;padding:2rem;">
  <h2 style="color:#111;margin-bottom:0.5rem;">Reset your password</h2>
  <p style="color:#555;line-height:1.6;">Hi {$name},</p>
  <p style="color:#555;line-height:1.6;">Click below to reset your contextkeeper password. This link expires in 1 hour.</p>
  <p style="text-align:center;margin:2rem 0;">
    <a href="{$resetUrl}" style="background:#00c8ff;color:#000;font-weight:600;text-decoration:none;padding:12px 32px;border-radius:8px;display:inline-block;">Reset Password</a>
  </p>
  <p style="color:#999;font-size:0.85rem;">If you did not request this, ignore this email.</p>
  <hr style="border:none;border-top:1px solid #eee;margin:2rem 0;">
  <p style="color:#999;font-size:0.8rem;">contextkeeper - Zero model drift between AI agents</p>
</div>
HTML;
                    $ch = curl_init('https://api.resend.com/emails');
                    curl_setopt_array($ch, [
                        CURLOPT_POST => true,
                        CURLOPT_POSTFIELDS => json_encode([
                            'from' => FROM_EMAIL,
                            'to' => [$email],
                            'subject' => 'Reset your contextkeeper password',
                            'html' => $html,
                        ]),
                        CURLOPT_HTTPHEADER => [
                            'Authorization: Bearer ' . RESEND_API_KEY,
                            'Content-Type: application/json',
                        ],
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_TIMEOUT => 10,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                } else {
                    // Dev fallback: log the link
                    error_log("contextkeeper password reset for $email: $resetUrl");
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
  <title>Forgot Password - contextkeeper</title>
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
    .form-control::placeholder { color: var(--text-dim); opacity: 0.6; }
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
    .back-link { display: block; text-align: center; margin-bottom: 1.5rem; color: var(--text-dim); font-size: 0.85rem; text-decoration: none; }
    .back-link:hover { color: var(--cyan); }
  </style>
</head>
<body>
  <div class="auth-card">
    <a href="/app/auth/login.php" class="back-link"><i class="bi bi-arrow-left"></i> Back to login</a>
    <a href="/" class="auth-brand">context<span>keeper</span></a>
    <p class="auth-subtitle">Reset your password</p>

    <?php if ($success): ?>
      <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <?= Csrf::field() ?>
      <div class="mb-3">
        <label for="email" class="form-label">Email address</label>
        <input type="email" class="form-control" id="email" name="email"
               placeholder="you@example.com" required autofocus>
      </div>
      <button type="submit" class="btn btn-login">Send Reset Link</button>
    </form>
    <?php endif; ?>

    <div class="auth-footer">
      Remember your password? <a href="/app/auth/login.php">Sign in</a>
    </div>
  </div>
</body>
</html>
