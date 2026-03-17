<?php
/**
 * Dashboard: Single Project View
 * /app/dashboard/project.php?slug=my-project
 * 
 * Displays: state vector, session timeline, decision log, invariant registry.
 */

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

$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
if (empty($slug)) {
    header('Location: /app/dashboard/');
    exit;
}

// Fetch project
$stmt = $db->prepare("SELECT * FROM projects WHERE user_id = ? AND slug = ?");
$stmt->execute([$user['id'], $slug]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$project) {
    header('Location: /app/dashboard/');
    exit;
}

// Fetch recent sessions
$stmt = $db->prepare(
    "SELECT id, agent, action, decisions_captured, invariants_captured, 
            files_captured, authority_sha, repo_sha, created_at
     FROM sessions_log WHERE project_id = ? ORDER BY created_at DESC LIMIT 20"
);
$stmt->execute([$project['id']]);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch decisions
$stmt = $db->prepare(
    "SELECT d.id, d.title, d.rationale, d.established_by, d.created_at,
            s.agent as session_agent
     FROM decisions d
     LEFT JOIN sessions_log s ON d.session_id = s.id
     WHERE d.project_id = ? ORDER BY d.created_at DESC LIMIT 50"
);
$stmt->execute([$project['id']]);
$decisions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch invariants
$stmt = $db->prepare(
    "SELECT id, name, assertion, scope, established_by, active, created_at
     FROM invariants WHERE project_id = ? ORDER BY active DESC, created_at ASC"
);
$stmt->execute([$project['id']]);
$invariants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Parse state vector
$stateVector = $project['state_vector'] ? json_decode($project['state_vector'], true) : null;

$pageTitle = htmlspecialchars($project['name']) . ' - contextkeeper';

require_once __DIR__ . '/../templates/header.php';
require_once __DIR__ . '/../templates/nav.php';
?>

<div class="container-fluid px-4 py-4" style="max-width: 1400px; margin: 0 auto;">

  <!-- Project Header -->
  <div class="d-flex justify-content-between align-items-start mb-4">
    <div>
      <h1 class="mb-1" style="color: var(--text-bright); font-family: 'Outfit', sans-serif; font-weight: 600;">
        <?= htmlspecialchars($project['name']) ?>
      </h1>
      <div class="d-flex align-items-center gap-3">
        <code style="color: var(--cyan); font-family: 'JetBrains Mono', monospace; font-size: 0.85rem;">
          <?= htmlspecialchars($project['slug']) ?>
        </code>
        <span class="badge" style="background: <?= $project['current_state'] === 'ACTIVE' ? 'var(--green)' : ($project['current_state'] === 'UNINITIATED' ? 'var(--amber)' : 'var(--text-dim)') ?>; color: var(--bg); font-weight: 600; font-size: 0.75rem;">
          <?= htmlspecialchars($project['current_state']) ?>
        </span>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-sm" style="background: var(--surface); border: 1px solid var(--border); color: var(--text);" onclick="copyApiExample()">
        <i class="bi bi-clipboard"></i> Copy API Example
      </button>
    </div>
  </div>

  <div class="row g-4">

    <!-- State Vector Panel -->
    <div class="col-lg-4">
      <div class="card h-100" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px;">
        <div class="card-body">
          <h5 style="color: var(--cyan); font-family: 'Outfit', sans-serif; font-weight: 500; margin-bottom: 1rem;">
            <i class="bi bi-diagram-3"></i> State Vector
          </h5>
          <?php if ($stateVector): ?>
            <pre style="background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; color: var(--text); font-family: 'JetBrains Mono', monospace; font-size: 0.8rem; max-height: 400px; overflow: auto; white-space: pre-wrap;"><?= htmlspecialchars(json_encode($stateVector, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) ?></pre>
          <?php else: ?>
            <p style="color: var(--text-dim);">No state vector yet. Run a <code>sync</code> to capture state.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="card mt-4" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px;">
        <div class="card-body">
          <h5 style="color: var(--cyan); font-family: 'Outfit', sans-serif; font-weight: 500; margin-bottom: 1rem;">
            <i class="bi bi-bar-chart"></i> Stats
          </h5>
          <div class="d-flex flex-column gap-2">
            <div class="d-flex justify-content-between">
              <span style="color: var(--text-dim);">Sessions</span>
              <span style="color: var(--text-bright); font-family: 'JetBrains Mono', monospace;"><?= (int)$project['sessions_count'] ?></span>
            </div>
            <div class="d-flex justify-content-between">
              <span style="color: var(--text-dim);">Decisions</span>
              <span style="color: var(--text-bright); font-family: 'JetBrains Mono', monospace;"><?= (int)$project['decisions_count'] ?></span>
            </div>
            <div class="d-flex justify-content-between">
              <span style="color: var(--text-dim);">Active Invariants</span>
              <span style="color: var(--text-bright); font-family: 'JetBrains Mono', monospace;"><?= count(array_filter($invariants, fn($i) => $i['active'])) ?></span>
            </div>
            <div class="d-flex justify-content-between">
              <span style="color: var(--text-dim);">Created</span>
              <span style="color: var(--text); font-size: 0.85rem;"><?= date('M j, Y', strtotime($project['created_at'])) ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div class="col-lg-8">

      <!-- Session Timeline -->
      <div class="card mb-4" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px;">
        <div class="card-body">
          <h5 style="color: var(--cyan); font-family: 'Outfit', sans-serif; font-weight: 500; margin-bottom: 1rem;">
            <i class="bi bi-clock-history"></i> Session History
          </h5>
          <?php if (empty($sessions)): ?>
            <p style="color: var(--text-dim);">No sessions yet.</p>
          <?php else: ?>
            <div class="table-responsive">
              <table class="table table-sm mb-0" style="color: var(--text);">
                <thead>
                  <tr style="border-bottom: 1px solid var(--border);">
                    <th style="color: var(--text-dim); font-weight: 500; border: none;">Time</th>
                    <th style="color: var(--text-dim); font-weight: 500; border: none;">Agent</th>
                    <th style="color: var(--text-dim); font-weight: 500; border: none;">Action</th>
                    <th style="color: var(--text-dim); font-weight: 500; border: none;">Decisions</th>
                    <th style="color: var(--text-dim); font-weight: 500; border: none;">Files</th>
                    <th style="color: var(--text-dim); font-weight: 500; border: none;">SHA</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($sessions as $s): ?>
                  <tr style="border-bottom: 1px solid var(--border);">
                    <td style="border: none; font-size: 0.85rem; white-space: nowrap;"><?= date('M j, g:ia', strtotime($s['created_at'])) ?></td>
                    <td style="border: none;">
                      <code style="color: var(--purple); font-size: 0.8rem;"><?= htmlspecialchars($s['agent']) ?></code>
                    </td>
                    <td style="border: none;">
                      <?php
                        $actionColors = ['sync' => 'var(--green)', 'bootstrap' => 'var(--cyan)', 'init' => 'var(--amber)', 'bundle' => 'var(--purple)', 'doctor' => 'var(--red)'];
                        $color = $actionColors[$s['action']] ?? 'var(--text-dim)';
                      ?>
                      <span style="color: <?= $color ?>; font-weight: 500; font-size: 0.85rem;"><?= htmlspecialchars($s['action']) ?></span>
                    </td>
                    <td style="border: none; font-family: 'JetBrains Mono', monospace; font-size: 0.8rem;"><?= (int)$s['decisions_captured'] ?></td>
                    <td style="border: none; font-family: 'JetBrains Mono', monospace; font-size: 0.8rem;"><?= (int)$s['files_captured'] ?></td>
                    <td style="border: none;">
                      <?php if ($s['authority_sha']): ?>
                        <code style="color: var(--text-dim); font-size: 0.75rem;"><?= substr($s['authority_sha'], 0, 8) ?></code>
                      <?php else: ?>
                        <span style="color: var(--text-dim);">-</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Decision Log -->
      <div class="card mb-4" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px;">
        <div class="card-body">
          <h5 style="color: var(--cyan); font-family: 'Outfit', sans-serif; font-weight: 500; margin-bottom: 1rem;">
            <i class="bi bi-signpost-split"></i> Decision Log
          </h5>
          <?php if (empty($decisions)): ?>
            <p style="color: var(--text-dim);">No decisions recorded yet.</p>
          <?php else: ?>
            <div class="d-flex flex-column gap-3">
              <?php foreach ($decisions as $d): ?>
              <div style="background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 1rem;">
                <div class="d-flex justify-content-between align-items-start mb-1">
                  <strong style="color: var(--text-bright); font-size: 0.95rem;"><?= htmlspecialchars($d['title']) ?></strong>
                  <small style="color: var(--text-dim); white-space: nowrap; margin-left: 1rem;"><?= date('M j', strtotime($d['created_at'])) ?></small>
                </div>
                <?php if ($d['rationale']): ?>
                  <p style="color: var(--text); font-size: 0.85rem; margin-bottom: 0.5rem;"><?= htmlspecialchars(substr($d['rationale'], 0, 300)) ?><?= strlen($d['rationale']) > 300 ? '...' : '' ?></p>
                <?php endif; ?>
                <div class="d-flex gap-2">
                  <?php if ($d['established_by']): ?>
                    <small style="color: var(--purple);">by <?= htmlspecialchars($d['established_by']) ?></small>
                  <?php endif; ?>
                  <?php if ($d['session_agent']): ?>
                    <small style="color: var(--text-dim);">via <?= htmlspecialchars($d['session_agent']) ?></small>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Invariant Registry -->
      <div class="card mb-4" style="background: var(--surface); border: 1px solid var(--border); border-radius: 12px;">
        <div class="card-body">
          <h5 style="color: var(--cyan); font-family: 'Outfit', sans-serif; font-weight: 500; margin-bottom: 1rem;">
            <i class="bi bi-shield-check"></i> Invariant Registry
          </h5>
          <?php if (empty($invariants)): ?>
            <p style="color: var(--text-dim);">No invariants registered yet.</p>
          <?php else: ?>
            <div class="d-flex flex-column gap-2">
              <?php foreach ($invariants as $inv): ?>
              <div class="d-flex align-items-start gap-3" style="background: var(--bg); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem 1rem; opacity: <?= $inv['active'] ? '1' : '0.5' ?>;">
                <span style="color: <?= $inv['active'] ? 'var(--green)' : 'var(--red)' ?>; font-size: 1.1rem; margin-top: 2px;">
                  <i class="bi bi-<?= $inv['active'] ? 'check-circle-fill' : 'x-circle-fill' ?>"></i>
                </span>
                <div class="flex-grow-1">
                  <strong style="color: var(--text-bright); font-size: 0.9rem;"><?= htmlspecialchars($inv['name']) ?></strong>
                  <?php if ($inv['assertion']): ?>
                    <p style="color: var(--text); font-size: 0.8rem; margin: 0.25rem 0 0 0;"><?= htmlspecialchars($inv['assertion']) ?></p>
                  <?php endif; ?>
                </div>
                <?php if ($inv['scope']): ?>
                  <code style="color: var(--text-dim); font-size: 0.75rem; white-space: nowrap;"><?= htmlspecialchars($inv['scope']) ?></code>
                <?php endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
function copyApiExample() {
  const slug = '<?= htmlspecialchars($slug, ENT_QUOTES) ?>';
  const example = `curl -X POST https://contextkeeper.org/app/api/v1/sync \\
  -H "Content-Type: application/json" \\
  -H "X-API-Key: YOUR_API_KEY" \\
  -d '{
    "project_slug": "${slug}",
    "agent": "claude-4",
    "state_vector": { "phase": "active" },
    "decisions": [{ "title": "Example decision", "rationale": "Because..." }]
  }'`;

  navigator.clipboard.writeText(example).then(() => {
    const btn = event.target.closest('button');
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
    setTimeout(() => { btn.innerHTML = original; }, 2000);
  });
}
</script>

<?php require_once __DIR__ . '/../templates/footer.php'; ?>
