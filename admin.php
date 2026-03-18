<?php
/**
 * admin.php — Eid Salami Admin Panel
 * Password-protected dashboard to approve/reject/delete submissions
 */

// ── Config ──
define('ADMIN_PASSWORD', 'eid2025admin'); // ← Change this!
define('DATA_FILE',      __DIR__ . '/data.json');
define('UPLOAD_DIR',     __DIR__ . '/uploads/');
define('SESSION_KEY',    'eid_admin_auth');
define('GOAL',           10000);

session_start();

// ── Handle AJAX actions ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    if (!($_SESSION[SESSION_KEY] ?? false)) {
        echo json_encode(['success' => false, 'error' => 'Unauthorized']);
        exit;
    }

    $entries = readData();
    $id      = trim($_POST['id'] ?? '');
    $action  = $_POST['action'];

    if ($action === 'login') {
        // handled below, shouldn't reach here
        exit;
    }

    if (in_array($action, ['approve', 'reject', 'delete'], true) && $id) {
        foreach ($entries as $i => $e) {
            if (($e['id'] ?? '') === $id) {
                if ($action === 'delete') {
                    // Optionally delete file
                    if (!empty($e['screenshot'])) {
                        @unlink(UPLOAD_DIR . basename($e['screenshot']));
                    }
                    array_splice($entries, $i, 1);
                } elseif ($action === 'approve') {
                    $entries[$i]['status'] = 'approved';
                } elseif ($action === 'reject') {
                    $entries[$i]['status'] = 'rejected';
                }
                break;
            }
        }
        writeData($entries);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'toggle_closed') {
        $meta_i = null;
        foreach ($entries as $i => $e) {
            if (isset($e['_meta'])) { $meta_i = $i; break; }
        }
        $current = false;
        if ($meta_i !== null) {
            $current = $entries[$meta_i]['_meta']['closed'] ?? false;
            $entries[$meta_i]['_meta']['closed'] = !$current;
        } else {
            $entries[] = ['_meta' => ['closed' => true]];
        }
        writeData($entries);
        echo json_encode(['success' => true, 'closed' => !$current]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);
    exit;
}

// ── Handle login ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION[SESSION_KEY] = true;
    } else {
        $loginError = 'Incorrect password.';
    }
}

// ── Handle logout ──
if (isset($_GET['logout'])) {
    $_SESSION[SESSION_KEY] = false;
    session_destroy();
    header('Location: admin.php');
    exit;
}

// ── Helpers ──
function readData(): array {
    if (!file_exists(DATA_FILE)) return [];
    $raw = file_get_contents(DATA_FILE);
    $d   = json_decode($raw, true);
    return is_array($d) ? $d : [];
}

function writeData(array $data): void {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function esc(mixed $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

// ── Gather stats ──
$allEntries  = readData();
$realEntries = array_filter($allEntries, fn($e) => !isset($e['_meta']));
$pending     = array_filter($realEntries, fn($e) => ($e['status'] ?? '') === 'pending');
$approved    = array_filter($realEntries, fn($e) => ($e['status'] ?? '') === 'approved');
$rejected    = array_filter($realEntries, fn($e) => ($e['status'] ?? '') === 'rejected');
$totalApproved = array_sum(array_column(iterator_to_array($approved), 'amount'));
$isClosed    = false;
foreach ($allEntries as $e) {
    if (isset($e['_meta']['closed'])) { $isClosed = $e['_meta']['closed']; break; }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin — Eid Salami</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Cinzel+Decorative:wght@700&family=Cairo:wght@300;400;600&display=swap" rel="stylesheet"/>
  <style>
    :root{
      --bg: #07050f; --bg2: #0f0b1a; --bg3: #13101f;
      --gold: #f5c842; --gold2: #d4a520; --gold3: #8a6a10;
      --text: #fff8ea; --text2: #c9b99a; --text3: #7a6b58;
      --green: #4dd9a0; --red: #e87c9e; --orange: #f5a742;
      --border: rgba(212,165,32,0.22); --radius: 12px;
    }
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{background:var(--bg);color:var(--text);font-family:'Cairo',sans-serif;min-height:100vh}
    a{color:var(--gold2);text-decoration:none}
    a:hover{color:var(--gold)}

    /* Login */
    .login-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1rem}
    .login-box{background:var(--bg3);border:1px solid var(--border);border-radius:20px;padding:2.5rem 2rem;width:100%;max-width:380px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,0.7)}
    .login-box h1{font-family:'Cinzel Decorative',cursive;font-size:1.3rem;color:var(--gold);margin-bottom:0.4rem}
    .login-box p{color:var(--text3);font-size:0.9rem;margin-bottom:1.5rem}
    .login-input{width:100%;background:rgba(255,255,255,0.05);border:1px solid var(--border);border-radius:var(--radius);padding:.8rem 1rem;color:var(--text);font-size:1rem;font-family:'Cairo',sans-serif;outline:none;margin-bottom:1rem}
    .login-input:focus{border-color:var(--gold2);box-shadow:0 0 0 3px rgba(212,165,32,0.15)}
    .login-btn{width:100%;background:linear-gradient(135deg,#c88e0a,#f5c842,#d4a520);color:#07050f;border:none;border-radius:50px;padding:.85rem;font-family:'Cinzel Decorative',cursive;font-size:1rem;font-weight:700;cursor:pointer;transition:all .3s}
    .login-btn:hover{transform:translateY(-2px);box-shadow:0 6px 25px rgba(245,200,66,0.4)}
    .login-error{color:var(--red);font-size:.85rem;margin-bottom:.8rem}

    /* Admin Layout */
    .admin-header{background:var(--bg2);border-bottom:1px solid var(--border);padding:1rem 1.5rem;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:.5rem}
    .admin-header h1{font-family:'Cinzel Decorative',cursive;font-size:1.1rem;color:var(--gold)}
    .admin-header nav{display:flex;gap:1rem;align-items:center;font-size:.85rem}
    .logout-btn{background:rgba(232,124,158,.12);border:1px solid rgba(232,124,158,.3);color:var(--red);padding:.35rem .9rem;border-radius:20px;cursor:pointer;font-size:.82rem;font-family:'Cairo',sans-serif;transition:all .2s}
    .logout-btn:hover{background:rgba(232,124,158,.25)}

    .admin-content{max-width:1100px;margin:0 auto;padding:1.5rem}

    /* Stats */
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:2rem}
    .stat-card{background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem;text-align:center}
    .stat-card .stat-val{font-family:'Cinzel Decorative',cursive;font-size:1.6rem;font-weight:700;margin-bottom:.2rem}
    .stat-card .stat-label{font-size:.75rem;color:var(--text3);text-transform:uppercase;letter-spacing:.1em}
    .stat-total .stat-val{color:var(--gold)}
    .stat-pending .stat-val{color:var(--orange)}
    .stat-approved .stat-val{color:var(--green)}
    .stat-rejected .stat-val{color:var(--red)}

    /* Toggle Closed */
    .toggle-section{margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem;flex-wrap:wrap}
    .toggle-closed-btn{padding:.5rem 1.2rem;border-radius:20px;border:1px solid var(--border);font-family:'Cairo',sans-serif;font-size:.85rem;cursor:pointer;transition:all .2s}
    .toggle-closed-btn.open{background:rgba(232,124,158,.12);color:var(--red);border-color:rgba(232,124,158,.4)}
    .toggle-closed-btn.closed{background:rgba(77,217,160,.12);color:var(--green);border-color:rgba(77,217,160,.4)}
    .status-badge{font-size:.8rem;color:var(--text3)}

    /* Filter Tabs */
    .filter-tabs{display:flex;gap:.5rem;margin-bottom:1.2rem;flex-wrap:wrap}
    .tab-btn{background:rgba(255,255,255,.04);border:1px solid var(--border);color:var(--text2);padding:.4rem 1rem;border-radius:20px;cursor:pointer;font-size:.82rem;font-family:'Cairo',sans-serif;transition:all .2s}
    .tab-btn:hover,.tab-btn.active{background:var(--gold3);border-color:var(--gold2);color:var(--gold)}

    /* Table */
    .table-wrap{overflow-x:auto;border-radius:var(--radius);border:1px solid var(--border)}
    table{width:100%;border-collapse:collapse;font-size:.875rem}
    th{background:var(--bg2);color:var(--text3);text-transform:uppercase;letter-spacing:.08em;font-size:.72rem;padding:.8rem 1rem;text-align:left;border-bottom:1px solid var(--border)}
    td{padding:.8rem 1rem;border-bottom:1px solid rgba(212,165,32,.1);vertical-align:middle}
    tr:last-child td{border-bottom:none}
    tr:hover td{background:rgba(245,200,66,.03)}
    .thumb{width:44px;height:44px;object-fit:cover;border-radius:6px;border:1px solid var(--border);cursor:pointer;transition:opacity .2s}
    .thumb:hover{opacity:.8}

    .badge{display:inline-block;padding:.2rem .65rem;border-radius:20px;font-size:.72rem;font-weight:600;letter-spacing:.05em}
    .badge-pending {background:rgba(245,167,66,.15);color:var(--orange);border:1px solid rgba(245,167,66,.3)}
    .badge-approved{background:rgba(77,217,160,.12);color:var(--green); border:1px solid rgba(77,217,160,.3)}
    .badge-rejected{background:rgba(232,124,158,.12);color:var(--red);   border:1px solid rgba(232,124,158,.3)}

    .action-btns{display:flex;gap:.4rem;flex-wrap:wrap}
    .act-btn{padding:.28rem .7rem;border-radius:20px;border:none;font-family:'Cairo',sans-serif;font-size:.75rem;cursor:pointer;transition:all .2s;font-weight:600}
    .act-approve{background:rgba(77,217,160,.15);color:var(--green); border:1px solid rgba(77,217,160,.3)}
    .act-reject {background:rgba(245,167,66,.12);color:var(--orange);border:1px solid rgba(245,167,66,.3)}
    .act-delete {background:rgba(232,124,158,.12);color:var(--red);  border:1px solid rgba(232,124,158,.3)}
    .act-btn:hover{filter:brightness(1.3);transform:translateY(-1px)}

    .empty-state{text-align:center;padding:2.5rem;color:var(--text3);font-style:italic}

    /* Image Lightbox */
    .lightbox{position:fixed;inset:0;z-index:999;background:rgba(7,5,15,.95);display:flex;align-items:center;justify-content:center;display:none}
    .lightbox.open{display:flex}
    .lightbox img{max-width:90vw;max-height:85vh;border-radius:12px;border:1px solid var(--border);box-shadow:0 20px 60px rgba(0,0,0,.8)}
    .lightbox-close{position:fixed;top:1.2rem;right:1.5rem;background:rgba(255,255,255,.1);border:1px solid var(--border);color:var(--text);width:36px;height:36px;border-radius:50%;cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center}
    .lightbox-close:hover{background:rgba(255,255,255,.2)}

    .back-link{font-size:.85rem;color:var(--text3)}
    .back-link:hover{color:var(--gold)}
    @media(max-width:600px){
      .admin-content{padding:1rem}
      .stats{grid-template-columns:1fr 1fr}
    }
  </style>
</head>
<body>

<?php if (!($_SESSION[SESSION_KEY] ?? false)): ?>
<!-- ── LOGIN ── -->
<div class="login-wrap">
  <div class="login-box">
    <div style="font-size:2.5rem;margin-bottom:.8rem">🔐</div>
    <h1>Admin Panel</h1>
    <p>Eid Salami Collection</p>
    <?php if (!empty($loginError)): ?>
      <div class="login-error"><?= esc($loginError) ?></div>
    <?php endif; ?>
    <form method="POST">
      <input class="login-input" type="password" name="password" placeholder="Enter admin password" autofocus required/>
      <button class="login-btn" type="submit">Login 🔑</button>
    </form>
    <p style="margin-top:1.2rem;font-size:.8rem"><a href="index.html">← Back to site</a></p>
  </div>
</div>

<?php else: ?>
<!-- ── ADMIN DASHBOARD ── -->
<header class="admin-header">
  <h1>🎉 Eid Salami Admin</h1>
  <nav>
    <a class="back-link" href="index.html">← View Site</a>
    <form method="GET" style="display:inline"><button name="logout" class="logout-btn" type="submit">Logout</button></form>
  </nav>
</header>

<div class="admin-content">

  <!-- Stats -->
  <div class="stats">
    <div class="stat-card stat-total">
      <div class="stat-val">৳<?= number_format($totalApproved) ?></div>
      <div class="stat-label">Total Collected</div>
    </div>
    <div class="stat-card stat-pending">
      <div class="stat-val"><?= count($pending) ?></div>
      <div class="stat-label">Pending</div>
    </div>
    <div class="stat-card stat-approved">
      <div class="stat-val"><?= count($approved) ?></div>
      <div class="stat-label">Approved</div>
    </div>
    <div class="stat-card stat-rejected">
      <div class="stat-val"><?= count($rejected) ?></div>
      <div class="stat-label">Rejected</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:var(--text2)"><?= count($realEntries) ?></div>
      <div class="stat-label">Total Submissions</div>
    </div>
    <div class="stat-card">
      <div class="stat-val" style="color:var(--gold3)"><?= min(100, round(($totalApproved / GOAL) * 100)) ?>%</div>
      <div class="stat-label">Goal (৳<?= number_format(GOAL) ?>)</div>
    </div>
  </div>

  <!-- Toggle Collection -->
  <div class="toggle-section">
    <button class="toggle-closed-btn <?= $isClosed ? 'closed' : 'open' ?>" onclick="toggleClosed()">
      <?= $isClosed ? '✅ Re-open Collection' : '🔒 Close Collection' ?>
    </button>
    <span class="status-badge">Collection is currently: <strong style="color:<?= $isClosed ? 'var(--red)' : 'var(--green)' ?>"><?= $isClosed ? 'CLOSED' : 'OPEN' ?></strong></span>
  </div>

  <!-- Filter Tabs -->
  <div class="filter-tabs">
    <button class="tab-btn active" onclick="filterTab('all',this)">All (<?= count($realEntries) ?>)</button>
    <button class="tab-btn" onclick="filterTab('pending',this)">Pending (<?= count($pending) ?>)</button>
    <button class="tab-btn" onclick="filterTab('approved',this)">Approved (<?= count($approved) ?>)</button>
    <button class="tab-btn" onclick="filterTab('rejected',this)">Rejected (<?= count($rejected) ?>)</button>
  </div>

  <!-- Table -->
  <div class="table-wrap">
    <table id="submissionsTable">
      <thead>
        <tr>
          <th>#</th>
          <th>Screenshot</th>
          <th>Name</th>
          <th>Amount</th>
          <th>Via</th>
          <th>Message</th>
          <th>Status</th>
          <th>Date</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rows = array_values(array_filter($realEntries));
        usort($rows, fn($a,$b) => strcmp($b['timestamp']??'', $a['timestamp']??''));
        if (empty($rows)):
        ?>
        <tr><td colspan="9" class="empty-state">No submissions yet 🌙</td></tr>
        <?php else: foreach ($rows as $idx => $e): ?>
        <tr data-status="<?= esc($e['status'] ?? 'pending') ?>">
          <td style="color:var(--text3);font-size:.8rem"><?= $idx+1 ?></td>
          <td>
            <?php if (!empty($e['screenshot']) && file_exists(UPLOAD_DIR . $e['screenshot'])): ?>
              <img class="thumb" src="uploads/<?= esc($e['screenshot']) ?>" alt="screenshot" onclick="openLightbox(this.src)" loading="lazy"/>
            <?php else: ?>
              <span style="color:var(--text3);font-size:.78rem">No file</span>
            <?php endif; ?>
          </td>
          <td style="font-weight:600"><?= esc($e['name'] ?? '—') ?></td>
          <td style="color:var(--gold);font-weight:600">৳<?= number_format($e['amount'] ?? 0) ?></td>
          <td><?= esc($e['method'] ?? '—') ?></td>
          <td style="color:var(--text2);font-style:italic;max-width:160px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= esc($e['message'] ?? '') ?>">
            <?= esc($e['message'] ?? '—') ?>
          </td>
          <td>
            <span class="badge badge-<?= esc($e['status'] ?? 'pending') ?>">
              <?= esc($e['status'] ?? 'pending') ?>
            </span>
          </td>
          <td style="color:var(--text3);font-size:.78rem;white-space:nowrap">
            <?= esc($e['timestamp'] ? date('d M, H:i', strtotime($e['timestamp'])) : '—') ?>
          </td>
          <td>
            <div class="action-btns">
              <?php if (($e['status'] ?? '') !== 'approved'): ?>
                <button class="act-btn act-approve" onclick="doAction('approve','<?= esc($e['id'] ?? '') ?>',this.closest('tr'))">✓ Approve</button>
              <?php endif; ?>
              <?php if (($e['status'] ?? '') !== 'rejected'): ?>
                <button class="act-btn act-reject" onclick="doAction('reject','<?= esc($e['id'] ?? '') ?>',this.closest('tr'))">✗ Reject</button>
              <?php endif; ?>
              <button class="act-btn act-delete" onclick="doDelete('<?= esc($e['id'] ?? '') ?>',this.closest('tr'))">🗑</button>
            </div>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

</div>

<!-- Lightbox -->
<div class="lightbox" id="lightbox" onclick="closeLightbox()">
  <button class="lightbox-close" onclick="closeLightbox()">✕</button>
  <img id="lightboxImg" src="" alt="Screenshot" onclick="event.stopPropagation()"/>
</div>

<script>
function doAction(action, id, row) {
  if (!id) return;
  fetch('admin.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=${action}&id=${encodeURIComponent(id)}`
  }).then(r => r.json()).then(d => {
    if (d.success) {
      const badge = row.querySelector('.badge');
      badge.className = 'badge badge-' + action + (action === 'approve' ? 'd' : 'ed');
      badge.textContent = action === 'approve' ? 'approved' : 'rejected';
      row.dataset.status = action === 'approve' ? 'approved' : 'rejected';
    }
  });
}

function doDelete(id, row) {
  if (!id || !confirm('Delete this submission?')) return;
  fetch('admin.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: `action=delete&id=${encodeURIComponent(id)}`
  }).then(r => r.json()).then(d => {
    if (d.success) row.remove();
  });
}

function toggleClosed() {
  fetch('admin.php', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'action=toggle_closed'
  }).then(r => r.json()).then(d => {
    if (d.success) location.reload();
  });
}

function filterTab(status, btn) {
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('#submissionsTable tbody tr[data-status]').forEach(row => {
    row.style.display = (status === 'all' || row.dataset.status === status) ? '' : 'none';
  });
}

function openLightbox(src) {
  document.getElementById('lightboxImg').src = src;
  document.getElementById('lightbox').classList.add('open');
}
function closeLightbox() {
  document.getElementById('lightbox').classList.remove('open');
}
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
</script>

<?php endif; ?>
</body>
</html>
