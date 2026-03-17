<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/StripeHelper.php';

if (empty($_SESSION['user_id'])) { header('Location: /app/auth/login.php'); exit; }

$db = Database::getInstance();
$auth = new Auth();
$user = $auth->getUserById((int)$_SESSION['user_id']);
if (!$user) { session_destroy(); header('Location: /app/auth/login.php'); exit; }

$limits = $auth->getPlanLimits($user['plan']);
$message = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'checkout') {
        $plan = $_POST['plan'] ?? '';
        if (in_array($plan, ['pro', 'team'])) {
            try {
                $stripe = new StripeHelper();
                $session = $stripe->createCheckoutSession($user, $plan);
                header('Location: ' . $session->url);
                exit;
            } catch (\Exception $e) {
                $error = 'Unable to start checkout. Please try again.';
            }
        } else {
            $error = 'Invalid plan selected.';
        }
    }

    if ($action === 'portal') {
        if (!empty($user['stripe_customer_id'])) {
            try {
                $stripe = new StripeHelper();
                $portalSession = $stripe->createPortalSession($user['stripe_customer_id']);
                header('Location: ' . $portalSession->url);
                exit;
            } catch (\Exception $e) {
                $error = 'Unable to open billing portal. Please try again.';
            }
        } else {
            $error = 'No billing account found. Subscribe to a plan first.';
        }
    }
}

// Check for success/cancel return from Stripe
if (isset($_GET['success'])) {
    $message = 'Subscription activated! Your plan will update shortly.';
    $user = $auth->getUserById((int)$_SESSION['user_id']);
    $_SESSION['user_plan'] = $user['plan'];
}
if (isset($_GET['canceled'])) {
    $message = 'Checkout canceled. No charges were made.';
}

// Fetch invoices
$invoices = [];
if (!empty($user['stripe_customer_id'])) {
    try {
        $stripe = $stripe ?? new StripeHelper();
        $invoices = $stripe->getInvoices($user['stripe_customer_id'], 5);
    } catch (\Exception $e) {}
}

// Sessions this month
$monthStart = date('Y-m-01 00:00:00');
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM sessions_log WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?) AND created_at >= ?"
);
$stmt->execute([$user['id'], $monthStart]);
$sessionsThisMonth = (int)$stmt->fetchColumn();

$plans = [
    'free' => ['name' => 'Free', 'price' => '$0', 'color' => 'var(--text-dim)'],
    'pro' => ['name' => 'Pro', 'price' => '$12/mo', 'color' => 'var(--cyan)'],
    'team' => ['name' => 'Team', 'price' => '$29/user/mo', 'color' => 'var(--purple)'],
    'enterprise' => ['name' => 'Enterprise', 'price' => 'Custom', 'color' => 'var(--amber)'],
];
$currentPlan = $plans[$user['plan']] ?? $plans['free'];
$subscriptionStatus = $user['subscription_status'] ?? null;

$pageTitle = 'Billing - contextkeeper';
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
    :root {
      --bg: #06080d; --bg-alt: #0c1018; --surface: #111722;
      --surface-hover: #182030; --border: #1e2a3a;
      --cyan: #00c8ff; --cyan-dim: #00c8ff22;
      --purple: #8b5cf6; --green: #34d399;
      --amber: #f59e0b; --red: #ef4444;
      --text: #e2e8f0; --text-dim: #94a3b8; --text-bright: #f8fafc;
    }
    body { font-family: 'Outfit', sans-serif; background: var(--bg); color: var(--text); }
    code, .mono { font-family: 'JetBrains Mono', monospace; }

    /* Dashboard Nav */
    .dash-nav {
      background: var(--surface); border-bottom: 1px solid var(--border);
      padding: 0.75rem 0;
    }
    .dash-nav a { text-decoration: none; }
    .nav-brand { font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 1.1rem; color: var(--text-bright); }
    .nav-brand span { color: var(--cyan); }
    .nav-link-dash { color: var(--text-dim); font-size: 0.88rem; padding: 0.4rem 0.75rem; }
    .nav-link-dash:hover { color: var(--text-bright); }
    .nav-link-dash.active { color: var(--cyan); font-weight: 500; }

    /* Cards */
    .billing-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem;
    }
    .billing-card h3 {
      font-size: 1.05rem; font-weight: 600; color: var(--text-bright);
      margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;
    }
    .billing-card h3 i { color: var(--cyan); font-size: 1.1rem; }

    /* Current plan display */
    .plan-badge-lg {
      font-size: 1.5rem; font-weight: 700; text-transform: uppercase;
      letter-spacing: 0.02em;
    }
    .status-badge {
      display: inline-block; padding: 0.15rem 0.6rem; border-radius: 999px;
      font-size: 0.72rem; font-weight: 600; text-transform: uppercase;
    }
    .status-active { background: rgba(52,211,153,0.15); color: var(--green); }
    .status-canceling { background: rgba(245,158,11,0.15); color: var(--amber); }
    .status-past_due { background: rgba(239,68,68,0.15); color: var(--red); }
    .status-canceled { background: rgba(148,163,184,0.15); color: var(--text-dim); }

    /* Usage bar */
    .usage-bar { background: var(--bg); border-radius: 8px; height: 8px; overflow: hidden; }
    .usage-fill { height: 100%; border-radius: 8px; transition: width 0.3s; }

    /* Plan comparison grid */
    .plan-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; }
    .plan-card {
      background: var(--bg); border: 1px solid var(--border); border-radius: 12px;
      padding: 1.25rem 1rem; text-align: center; transition: all 0.2s;
      display: flex; flex-direction: column; min-width: 0;
    }
    .plan-card:hover { border-color: var(--cyan); }
    .plan-card.current { border-color: var(--cyan); background: rgba(0,200,255,0.04); }
    .plan-name { font-weight: 600; font-size: 1rem; color: var(--text-bright); margin-bottom: 0.35rem; }
    .plan-price {
      font-family: 'JetBrains Mono', monospace; font-weight: 700;
      color: var(--text-bright); white-space: nowrap;
      font-size: clamp(1.1rem, 2vw, 1.6rem);
      line-height: 1.2; margin-bottom: 0.75rem;
    }
    .plan-features { flex: 1; }
    .plan-feature {
      font-size: 0.82rem; color: var(--text-dim); padding: 0.2rem 0;
      display: flex; align-items: baseline; gap: 0.35rem; justify-content: center;
    }
    .plan-feature i { color: var(--green); font-size: 0.75rem; flex-shrink: 0; }
    .plan-action { margin-top: 1rem; }

    /* Responsive: stack to 2-col on tablet, 1-col on mobile */
    @media (max-width: 992px) {
      .plan-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 576px) {
      .plan-grid { grid-template-columns: 1fr; }
      .plan-card { padding: 1.25rem 1.5rem; }
      .plan-price { font-size: 1.5rem; }
    }

    /* Buttons */
    .btn-cyan {
      background: var(--cyan); color: var(--bg); font-weight: 600;
      border: none; border-radius: 8px; padding: 0.5rem 1.25rem;
      font-size: 0.88rem; cursor: pointer; transition: all 0.2s;
      text-decoration: none; display: inline-block;
    }
    .btn-cyan:hover { background: #33d4ff; color: var(--bg); transform: translateY(-1px); }
    .btn-outline-dim {
      background: transparent; color: var(--text-dim); font-weight: 500;
      border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1.25rem;
      font-size: 0.88rem; cursor: pointer; transition: all 0.2s;
    }
    .btn-outline-dim:hover { border-color: var(--cyan); color: var(--cyan); }

    /* Invoices */
    .invoice-table { width: 100%; font-size: 0.85rem; }
    .invoice-table td { padding: 0.6rem 0; border-bottom: 1px solid var(--border); color: var(--text-dim); }
    .invoice-table tr:last-child td { border-bottom: none; }

    /* Alerts */
    .alert-success { background: rgba(52,211,153,0.1); border: 1px solid rgba(52,211,153,0.3); color: var(--green); border-radius: 8px; }
    .alert-danger { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--red); border-radius: 8px; }
  </style>
</head>
<body>

  <!-- Dashboard Nav -->
  <nav class="dash-nav">
    <div class="container-fluid px-4 d-flex align-items-center justify-content-between" style="max-width:1400px; margin:0 auto;">
      <div class="d-flex align-items-center gap-4">
        <a class="nav-brand" href="/app/dashboard/">context<span>keeper</span></a>
        <div class="d-none d-md-flex gap-1">
          <a class="nav-link-dash" href="/app/dashboard/">Dashboard</a>
          <a class="nav-link-dash" href="/app/dashboard/connectors.php">Connectors</a>
          <a class="nav-link-dash" href="/app/dashboard/settings.php">Settings</a>
          <a class="nav-link-dash active" href="/app/dashboard/billing.php">Billing</a>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span style="color:var(--text-dim); font-size:0.85rem;">
          <?= htmlspecialchars($user['name'] ?? $user['email']) ?>
        </span>
        <a href="/app/auth/logout.php" style="color:var(--text-dim); font-size:0.85rem;">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
      </div>
    </div>
  </nav>

  <div class="container-fluid px-4 py-4" style="max-width:960px; margin:0 auto;">
    <h1 style="font-size:1.5rem; font-weight:600; color:var(--text-bright); margin-bottom:1.5rem;">Billing</h1>

    <?php if ($message): ?><div class="alert alert-success mb-3"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger mb-3"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- Current Plan -->
    <div class="billing-card">
      <h3><i class="bi bi-credit-card"></i> Current Plan</h3>
      <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
        <span class="plan-badge-lg" style="color:<?= $currentPlan['color'] ?>;"><?= htmlspecialchars($currentPlan['name']) ?></span>
        <span style="color:var(--text-dim); font-size:1.1rem;"><?= htmlspecialchars($currentPlan['price']) ?></span>
        <?php if ($subscriptionStatus): ?>
          <span class="status-badge status-<?= htmlspecialchars($subscriptionStatus) ?>"><?= htmlspecialchars($subscriptionStatus) ?></span>
        <?php endif; ?>
      </div>
      <?php if (!empty($user['current_period_end']) && $user['plan'] !== 'free'): ?>
        <p style="color:var(--text-dim); font-size:0.85rem; margin-bottom:1rem;">
          <?= $subscriptionStatus === 'canceling' ? 'Access until' : 'Renews' ?>: <?= date('F j, Y', strtotime($user['current_period_end'])) ?>
        </p>
      <?php endif; ?>
      <?php if ($user['plan'] === 'free'): ?>
        <p style="color:var(--text-dim); font-size:0.88rem;">Upgrade to Pro for unlimited projects, API access, and cloud sync.</p>
      <?php elseif (!empty($user['stripe_customer_id'])): ?>
        <form method="POST" style="display:inline;">
          <input type="hidden" name="action" value="portal">
          <button type="submit" class="btn-cyan"><i class="bi bi-box-arrow-up-right"></i> Manage Billing</button>
        </form>
      <?php endif; ?>
    </div>

    <!-- Usage This Period -->
    <div class="billing-card">
      <h3><i class="bi bi-bar-chart"></i> Usage This Period</h3>
      <p style="color:var(--text-dim); font-size:0.85rem; margin-bottom:1rem;"><?= date('F 1') ?> - <?= date('F t') ?>, <?= date('Y') ?></p>
      <div class="mb-2">
        <div class="d-flex justify-content-between mb-1">
          <span style="font-size:0.88rem;">Sessions</span>
          <span class="mono" style="font-size:0.85rem; color:var(--text-bright);">
            <?= $sessionsThisMonth ?> / <?= $limits['sessions_per_month'] === -1 ? 'Unlimited' : $limits['sessions_per_month'] ?>
          </span>
        </div>
        <?php if ($limits['sessions_per_month'] !== -1): ?>
          <div class="usage-bar">
            <div class="usage-fill" style="width:<?= min(100, ($sessionsThisMonth / max(1, $limits['sessions_per_month'])) * 100) ?>%; background:var(--cyan);"></div>
          </div>
        <?php else: ?>
          <div class="usage-bar"><div class="usage-fill" style="width:100%; background:var(--green); opacity:0.3;"></div></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($invoices)): ?>
    <!-- Invoice History -->
    <div class="billing-card">
      <h3><i class="bi bi-receipt"></i> Recent Invoices</h3>
      <table class="invoice-table">
        <?php foreach ($invoices as $inv): ?>
        <tr>
          <td><?= date('M j, Y', $inv->created) ?></td>
          <td style="color:var(--text-bright);"><?= htmlspecialchars($inv->lines->data[0]->description ?? 'Subscription') ?></td>
          <td style="text-align:right;">
            <?php if ($inv->status === 'paid'): ?>
              <span style="color:var(--green);">$<?= number_format($inv->amount_paid / 100, 2) ?></span>
            <?php elseif ($inv->status === 'open'): ?>
              <span style="color:var(--amber);">$<?= number_format($inv->amount_due / 100, 2) ?> (pending)</span>
            <?php else: ?>
              <span><?= htmlspecialchars($inv->status) ?></span>
            <?php endif; ?>
          </td>
          <td style="text-align:right;">
            <?php if ($inv->hosted_invoice_url): ?>
              <a href="<?= htmlspecialchars($inv->hosted_invoice_url) ?>" target="_blank" style="color:var(--cyan); font-size:0.82rem;">View</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
    <?php endif; ?>

    <!-- Plan Comparison -->
    <div class="billing-card">
      <h3><i class="bi bi-grid"></i> Plans</h3>
      <div class="plan-grid">
        <?php
        $planDetails = [
          'free' => ['price'=>'$0','features'=>['1 project','3 connectors','50 sessions/mo','Community support']],
          'pro' => ['price'=>'$12/mo','features'=>['Unlimited projects','10 connectors','Unlimited sessions','API access','Cloud sync','Email support']],
          'team' => ['price'=>'$29/user/mo','features'=>['Everything in Pro','All connectors','Shared state','Role management','Priority support']],
          'enterprise' => ['price'=>'Custom','features'=>['Everything in Team','Custom connectors','Audit exports','Dedicated support','SLA']],
        ];
        foreach ($planDetails as $key => $plan): ?>
        <div class="plan-card <?= $key === $user['plan'] ? 'current' : '' ?>">
          <div class="plan-name"><?= ucfirst($key) ?></div>
          <div class="plan-price"><?= $plan['price'] ?></div>
          <div class="plan-features">
            <?php foreach ($plan['features'] as $f): ?>
              <div class="plan-feature"><i class="bi bi-check2"></i> <?= htmlspecialchars($f) ?></div>
            <?php endforeach; ?>
          </div>
          <div class="plan-action">
            <?php if ($key === $user['plan']): ?>
              <span style="font-size:0.8rem; color:var(--cyan); font-weight:600;">CURRENT PLAN</span>
            <?php elseif (in_array($key, ['pro', 'team'])): ?>
              <form method="POST">
                <input type="hidden" name="action" value="checkout">
                <input type="hidden" name="plan" value="<?= $key ?>">
                <button type="submit" class="btn-cyan" style="width:100%; padding:0.45rem; font-size:0.82rem;">
                  <?= $user['plan'] === 'free' ? 'Upgrade' : 'Switch' ?>
                </button>
              </form>
            <?php elseif ($key === 'enterprise'): ?>
              <a href="mailto:masterboss@contextkeeper.org" style="font-size:0.82rem; color:var(--cyan); text-decoration:none;">Contact Us</a>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
