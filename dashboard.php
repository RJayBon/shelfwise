<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$pdo = db();

/* POST: admin "Reset demo" */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_demo') {
    csrf_check();
    require_role(['admin']);
    $res = reset_demo($pdo);
    flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Demo data reset.' : $res['error']);
    redirect('dashboard.php');
}

$books       = $pdo->query('SELECT * FROM books ORDER BY title')->fetchAll();
$allActive   = $pdo->query(
    "SELECT l.loan_id, l.book_id, l.user_id, l.due_at,
            UNIX_TIMESTAMP(l.due_at)*1000 AS due_ts, b.title, u.name AS user_name
     FROM loans l
     JOIN books b ON b.book_id = l.book_id
     JOIN users u ON u.user_id = l.user_id
     WHERE l.status = 'active'
     ORDER BY l.due_at ASC"
)->fetchAll();

$mine        = active_loans_of($pdo, (int)$me['user_id']);
$queued      = (int)$pdo->query('SELECT COUNT(*) FROM reservations')->fetchColumn();
$overdue     = array_filter($allActive, fn($l) => loan_status($l) === 'overdue');
$availCopies = array_sum(array_column($books, 'available_copies'));
$totalCopies = array_sum(array_column($books, 'total_copies'));

$recent = $pdo->prepare(
    "SELECT h.type, h.note, h.created_at, b.title
     FROM history h LEFT JOIN books b ON b.book_id = h.book_id
     WHERE h.user_id = ?
     ORDER BY h.created_at DESC LIMIT 6"
);
$recent->execute([(int)$me['user_id']]);
$recent = $recent->fetchAll();

render_head('Overview');
?>
<div class="stats">
  <div class="stat"><div class="n"><?= count($books) ?></div><div class="l">Titles</div></div>
  <div class="stat"><div class="n"><?= (int)$availCopies ?>/<?= (int)$totalCopies ?></div><div class="l">Copies on shelf</div></div>
  <div class="stat"><div class="n"><?= count($allActive) ?></div><div class="l">Active loans</div></div>
  <div class="stat"><div class="n"><?= count($overdue) ?></div><div class="l">Overdue</div></div>
  <div class="stat"><div class="n"><?= $queued ?></div><div class="l">In waitlists</div></div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Your loans</h2><p><?= count($mine) ?> active of <?= MAX_ACTIVE ?> allowed</p></div></div>
  <?php if ($mine): ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Book</th><th>Due</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($mine as $l): $st = loan_status($l); ?>
        <tr>
          <td data-label="Book"><?= h($l['title']) ?></td>
          <td data-label="Due"><?= h(date('M j, Y', (int)($l['due_ts'] / 1000))) ?></td>
          <td data-label="Status"><span class="badge <?= $st === 'overdue' ? 'bad' : ($st === 'due-soon' ? 'warn' : 'ok') ?>">
            <?= $st === 'overdue' ? 'Overdue' : 'Due in ' . max(0, (int)ceil(((int)$l['due_ts'] - time() * 1000) / 86400000)) . 'd' ?>
          </span></td>
          <td data-label="">
            <form method="post" action="<?= h(base_url('loans.php')) ?>" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="renew">
              <input type="hidden" name="loan_id" value="<?= (int)$l['loan_id'] ?>">
              <button class="btn btn-sm" type="submit" <?= $st === 'overdue' ? 'disabled' : '' ?>>Renew</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty">You have no active loans. Head to <a href="<?= h(base_url('catalog.php')) ?>">Books Availability</a> to borrow one.</div>
  <?php endif; ?>
</div>

<div class="grid grid-2">
  <div class="panel">
    <div class="panel-head"><div><h2>Next due date</h2><p>The soonest loan across the library</p></div></div>
    <?php if ($allActive): $n = $allActive[0]; ?>
      <p style="font-size:1.05rem;"><?= h($n['title']) ?></p>
      <p style="color:var(--ink-dim);font-size:0.9rem;">
        <?= h($n['user_name']) ?> &middot; due <?= h(date('M j, Y', (int)($n['due_ts'] / 1000))) ?>
      </p>
    <?php else: ?>
      <div class="empty">No active loans.</div>
    <?php endif; ?>
  </div>
  <div class="panel">
    <div class="panel-head"><div><h2>Your recent activity</h2></div></div>
    <?php if ($recent): ?>
      <ul class="timeline">
        <?php foreach ($recent as $r): ?>
          <li><span class="dot"></span><span><?= h(ucfirst($r['type'])) ?> — <?= h($r['title'] ?? '') ?>
            <div class="when"><?= h($r['created_at']) ?></div></span></li>
        <?php endforeach; ?>
      </ul>
    <?php else: ?>
      <div class="empty">No activity yet.</div>
    <?php endif; ?>
  </div>
</div>

<?php if ($me['role'] === 'admin'): ?>
<div class="panel">
  <div class="panel-head"><div><h2>Admin tools</h2><p>Re-seed the demo database</p></div></div>
  <form method="post" onsubmit="return confirm('Reset the entire database to demo data?');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="reset_demo">
    <button class="btn" type="submit">Reset demo data</button>
  </form>
</div>
<?php endif; ?>

<?php render_foot(); ?>