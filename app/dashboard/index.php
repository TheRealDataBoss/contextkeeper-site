<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Database.php';
require_once __DIR__ . '/../lib/Auth.php';

if (empty($_SESSION['user_id'])) {
    header('Location: /app/auth/login.php');
    exit;
}

$db = Database::getInstance();
$auth = new Auth();
$user = $auth->getUserById((int)$_SESSION['user_id']);

if (!$user) {
    session_destroy();
    header('Location: /app/auth/login.php');
    exit;
}

$limits = $auth->getPlanLimits($user['plan']);

// Fetch projects
$stmt = $db->prepare(
    "SELECT id, name, slug, current_state, sessions_count, decisions_count, updated_at
     FROM projects WHERE user_id = ? ORDER BY updated_at DESC"
);
$stmt->execute([$user['id']]);
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sessions this month
$monthStart = date('Y-m-01 00:00:00');
$stmt = $db->prepare(
    "SELECT COUNT(*) FROM sessions_log
     WHERE project_id IN (SELECT id FROM projects WHERE user_id = ?)
     AND created_at >= ?"
);
$stmt->execute([$user['id'], $monthStart]);
$sessionsThisMonth = (int)$stmt->fetchColumn();

// Connectors count
$stmt = $db->prepare("SELECT COUNT(*) FROM connectors WHERE user_id = ?");
$stmt->execute([$user['id']]);
$connectorsCount = (int)$stmt->fetchColumn();

$pageTitle = 'Dashboard - contextkeeper';
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

    .dash-nav {
      background: var(--surface); border-bottom: 1px solid var(--border);
      padding: 0.75rem 0;
    }
    .dash-nav .nav-brand { font-family: 'JetBrains Mono', monospace; font-weight: 700; font-size: 1.1rem; color: var(--text-bright); text-decoration: none; }
    .dash-nav .nav-brand span { color: var(--cyan); }
    .dash-nav .nav-link { color: var(--text-dim); font-size: 0.88rem; padding: 0.4rem 0.75rem; }
    .dash-nav .nav-link:hover { color: var(--text-bright); }
    .dash-nav .nav-link.active { color: var(--cyan); }
    .user-badge { color: var(--text-dim); font-size: 0.85rem; }
    .plan-pill {
      font-size: 0.7rem; padding: 0.15rem 0.6rem; border-radius: 999px;
      font-weight: 600; text-transform: uppercase;
    }
    .plan-free { background: var(--border); color: var(--text-dim); }
    .plan-pro { background: rgba(0,200,255,0.15); color: var(--cyan); }
    .plan-team { background: rgba(139,92,246,0.15); color: var(--purple); }
    .plan-enterprise { background: rgba(245,158,11,0.15); color: var(--amber); }

    .stat-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 12px; padding: 1.25rem;
    }
    .stat-label { color: var(--text-dim); font-size: 0.8rem; font-weight: 500; margin-bottom: 0.25rem; }
    .stat-value { font-family: 'JetBrains Mono', monospace; font-size: 1.8rem; font-weight: 700; color: var(--text-bright); }
    .stat-sub { font-size: 0.78rem; color: var(--text-dim); }

    .project-card {
      background: var(--surface); border: 1px solid var(--border);
      border-radius: 12px; padding: 1.25rem; transition: all 0.2s;
      text-decoration: none; display: block; color: inherit;
    }
    .project-card:hover { border-color: var(--cyan); background: var(--surface-hover); color: inherit; }
    .project-name { font-weight: 600; color: var(--text-bright); font-size: 1.05rem; }
    .project-slug { font-family: 'JetBrains Mono', monospace; color: var(--cyan); font-size: 0.8rem; }
    .state-badge {
      font-size: 0.7rem; padding: 0.15rem 0.6rem; border-radius: 999px; font-weight: 600;
    }
    .state-active { background: rgba(52,211,153,0.15); color: var(--green); }
    .state-uninitiated { background: rgba(245,158,11,0.15); color: var(--amber); }

    .empty-state {
      text-align: center; padding: 3rem; color: var(--text-dim);
      background: var(--surface); border: 1px dashed var(--border);
      border-radius: 12px;
    }
    .empty-state i { font-size: 2.5rem; color: var(--border); margin-bottom: 1rem; display: block; }

    .btn-cyan {
      background: var(--cyan); color: var(--bg); font-weight: 600;
      border: none; border-radius: 8px; padding: 0.5rem 1.25rem;
      font-size: 0.88rem; transition: all 0.2s;
    }
    .btn-cyan:hover { background: #33d4ff; color: var(--bg); }
    .btn-outline-surface {
      background: transparent; color: var(--text-dim); font-weight: 500;
      border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem 1.25rem;
      font-size: 0.88rem; transition: all 0.2s;
    }
    .btn-outline-surface:hover { border-color: var(--text-dim); color: var(--text-bright); }
  </style>
</head>
<body>

  <!-- Dashboard Nav -->
  <nav class="dash-nav">
    <div class="container-fluid px-4 d-flex align-items-center justify-content-between" style="max-width:1400px; margin:0 auto;">
      <div class="d-flex align-items-center gap-4">
        <a class="nav-brand" href="/app/dashboard/">context<span>keeper</span></a>
        <div class="d-none d-md-flex gap-1">
          <a class="nav-link active" href="/app/dashboard/">Dashboard</a>
          <a class="nav-link" href="/app/dashboard/connectors.php">Connectors</a>
          <a class="nav-link" href="/app/dashboard/settings.php">Settings</a>
          <a class="nav-link" href="/app/dashboard/billing.php">Billing</a>
        </div>
      </div>
      <div class="d-flex align-items-center gap-3">
        <span class="user-badge">
          <?= htmlspecialchars($user['name'] ?? $user['email']) ?>
          <span class="plan-pill plan-<?= $user['plan'] ?>"><?= strtoupper($user['plan']) ?></span>
        </span>
        <a href="/app/auth/logout.php" class="nav-link" style="color:var(--text-dim); font-size:0.85rem;">
          <i class="bi bi-box-arrow-right"></i> Logout
        </a>
      </div>
    </div>
  </nav>

  <div class="container-fluid px-4 py-4" style="max-width:1400px; margin:0 auto;">

    <!-- Welcome -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 style="font-size:1.5rem; font-weight:600; color:var(--text-bright); margin:0;">
        Welcome back<?= $user['name'] ? ', ' . htmlspecialchars(explode(' ', $user['name'])[0]) : '' ?>
      </h1>
      <a href="/app/dashboard/" class="btn btn-cyan" onclick="event.preventDefault(); promptNewProject();">
        <i class="bi bi-plus-lg"></i> New Project
      </a>
    </div>

    <!-- Stats Row -->
    <div class="row g-3 mb-4">
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-label">Projects</div>
          <div class="stat-value"><?= count($projects) ?></div>
          <div class="stat-sub"><?= $limits['projects'] === -1 ? 'Unlimited' : "of {$limits['projects']}" ?></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-label">Sessions This Month</div>
          <div class="stat-value"><?= $sessionsThisMonth ?></div>
          <div class="stat-sub"><?= $limits['sessions_per_month'] === -1 ? 'Unlimited' : "of {$limits['sessions_per_month']}" ?></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-label">Connectors</div>
          <div class="stat-value"><?= $connectorsCount ?></div>
          <div class="stat-sub"><?= $limits['connectors'] === -1 ? 'Unlimited' : "of {$limits['connectors']}" ?></div>
        </div>
      </div>
      <div class="col-md-3 col-6">
        <div class="stat-card">
          <div class="stat-label">API Key</div>
          <div class="stat-value" style="font-size:0.85rem; word-break:break-all; line-height:1.4;">
            <?= $user['api_key'] ? substr($user['api_key'], 0, 12) . '...' : 'Not generated' ?>
          </div>
          <div class="stat-sub"><a href="/app/dashboard/settings.php" style="color:var(--cyan);">View full key</a></div>
        </div>
      </div>
    </div>

    <!-- Projects -->
    <h2 style="font-size:1.15rem; font-weight:600; color:var(--text-bright); margin-bottom:1rem;">Your Projects</h2>

    <?php if (empty($projects)): ?>
      <div class="empty-state">
        <i class="bi bi-folder2-open"></i>
        <p style="font-size:1rem; margin-bottom:0.5rem; color:var(--text);">No projects yet</p>
        <p style="font-size:0.88rem;">Create your first project or use the API to sync from your CLI.</p>
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($projects as $p): ?>
        <div class="col-md-6 col-lg-4">
          <a href="/app/dashboard/project.php?slug=<?= htmlspecialchars($p['slug']) ?>" class="project-card">
            <div class="d-flex justify-content-between align-items-start mb-2">
              <div>
                <div class="project-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="project-slug"><?= htmlspecialchars($p['slug']) ?></div>
              </div>
              <span class="state-badge state-<?= strtolower($p['current_state']) ?>">
                <?= htmlspecialchars($p['current_state']) ?>
              </span>
            </div>
            <div class="d-flex gap-3" style="font-size:0.8rem; color:var(--text-dim);">
              <span><i class="bi bi-clock-history"></i> <?= (int)$p['sessions_count'] ?> sessions</span>
              <span><i class="bi bi-signpost-split"></i> <?= (int)$p['decisions_count'] ?> decisions</span>
            </div>
            <div style="font-size:0.75rem; color:var(--text-dim); margin-top:0.5rem;">
              Updated <?= date('M j, g:ia', strtotime($p['updated_at'])) ?>
            </div>
          </a>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($user['plan'] === 'free'): ?>
    <div style="background:var(--surface); border:1px solid var(--border); border-radius:12px; padding:1.5rem; margin-top:2rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:1rem;">
      <div>
        <strong style="color:var(--text-bright);">Upgrade to Pro</strong>
        <p style="color:var(--text-dim); font-size:0.88rem; margin:0.25rem 0 0 0;">Unlimited projects, API access, cloud sync, and more. $12/month.</p>
      </div>
      <a href="/pricing.html" class="btn btn-cyan">View Plans</a>
    </div>
    <?php endif; ?>

  </div>

  <script>
  function promptNewProject() {
    const name = prompt('Project name:');
    if (!name || !name.trim()) return;

    fetch('/app/api/v1/projects', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ name: name.trim() })
    })
    .then(r => r.json())
    .then(data => {
      if (data.project) {
        window.location.reload();
      } else {
        alert(data.message || 'Failed to create project.');
      }
    })
    .catch(() => alert('Network error. Try again.'));
  }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
