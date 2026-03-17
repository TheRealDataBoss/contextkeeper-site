<?php
// Dashboard navigation - included by dashboard pages
// Expects $user to be set
?>
<nav style="background: var(--surface); border-bottom: 1px solid var(--border); padding: 0.75rem 0;">
  <div class="container-fluid px-4 d-flex align-items-center justify-content-between" style="max-width:1400px; margin:0 auto;">
    <div class="d-flex align-items-center gap-4">
      <a style="font-family:'JetBrains Mono',monospace; font-weight:700; font-size:1.1rem; color:var(--text-bright); text-decoration:none;" href="/app/dashboard/">context<span style="color:var(--cyan);">keeper</span></a>
      <div class="d-none d-md-flex gap-1">
        <a class="nav-link" href="/app/dashboard/" style="color:var(--text-dim); font-size:0.88rem; padding:0.4rem 0.75rem;">Dashboard</a>
        <a class="nav-link" href="/app/dashboard/connectors.php" style="color:var(--text-dim); font-size:0.88rem; padding:0.4rem 0.75rem;">Connectors</a>
        <a class="nav-link" href="/app/dashboard/settings.php" style="color:var(--text-dim); font-size:0.88rem; padding:0.4rem 0.75rem;">Settings</a>
        <a class="nav-link" href="/app/dashboard/billing.php" style="color:var(--text-dim); font-size:0.88rem; padding:0.4rem 0.75rem;">Billing</a>
      </div>
    </div>
    <div class="d-flex align-items-center gap-3">
      <span style="color:var(--text-dim); font-size:0.85rem;">
        <?= htmlspecialchars($user['name'] ?? $user['email']) ?>
      </span>
      <a href="/app/auth/logout.php" style="color:var(--text-dim); font-size:0.85rem; text-decoration:none;">
        <i class="bi bi-box-arrow-right"></i> Logout
      </a>
    </div>
  </div>
</nav>
