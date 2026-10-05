<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $lid    = (int)($_POST['loan_id'] ?? 0);

    if ($action === 'renew') {
        $res = renew_loan($pdo, $lid, (int)$me['user_id'], in_array($me['role'], ['staff', 'admin'], true));
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Renewed "' . $res['title'] . '".' : $res['error']);
    } elseif ($action === 'return') {
        $res = checkin_loan($pdo, $lid, (int)$me['user_id'], (int)$me['user_id']);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Book checked in.' : $res['error']);
    } elseif ($action === 'cancel_reservation') {
        leave_reservation($pdo, (int)($_POST['book_id'] ?? 0), (int)$me['user_id']);
        flash('info', 'Reservation cancelled.');
    }
    redirect('loans.php');
}

$mine = active_loans_of($pdo, (int)$me['user_id']);

$q = $pdo->prepare(
    'SELECT r.book_id, r.position, b.title
     FROM reservations r JOIN books b ON b.book_id = r.book_id
     WHERE r.user_id = ? ORDER BY r.book_id'
);
$q->execute([(int)$me['user_id']]);
$myQueues = $q->fetchAll();

$queueLens = [];
foreach ($pdo->query('SELECT book_id, COUNT(*) c FROM reservations GROUP BY book_id')->fetchAll() as $r) {
    $queueLens[(int)$r['book_id']] = (int)$r['c'];
}

render_head('My Loans & Renewal');
?>
<div class="panel">
  <div class="panel-head"><div><h2>Active loans</h2><p>Sorted by due date</p></div></div>
  <?php if ($mine): ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Book</th><th>Borrowed</th><th>Due</th><th>Renewals</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($mine as $l):
        $st      = loan_status($l);
        $blocked = $st === 'overdue' || (int)$l['renewals'] >= MAX_RENEWALS || (($queueLens[(int)$l['book_id']] ?? 0) > 0);
        $title   = $st === 'overdue' ? 'Overdue — return first'
                 : ((int)$l['renewals'] >= MAX_RENEWALS ? 'Renewal limit reached'
                 : (($queueLens[(int)$l['book_id']] ?? 0) > 0 ? 'Reserved by another member' : 'Extend by ' . RENEW_DAYS . ' days'));
      ?>
        <tr>
          <td data-label="Book"><?= h($l['title']) ?><div style="color:var(--ink-faint);font-size:0.8rem;"><?= h($l['author']) ?></div></td>
          <td data-label="Borrowed"><?= h(date('M j, Y', (int)($l['borrowed_ts'] / 1000))) ?></td>
          <td data-label="Due"><?= h(date('M j, Y', (int)($l['due_ts'] / 1000))) ?></td>
          <td data-label="Renewals"><?= (int)$l['renewals'] ?>/<?= MAX_RENEWALS ?></td>
          <td data-label="Status"><span class="badge <?= $st === 'overdue' ? 'bad' : ($st === 'due-soon' ? 'warn' : 'ok') ?>">
            <?= $st === 'overdue' ? 'Overdue' : ucfirst($st) ?></span></td>
          <td data-label="">
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="renew">
              <input type="hidden" name="loan_id" value="<?= (int)$l['loan_id'] ?>">
              <button class="btn btn-sm" type="submit" <?= $blocked ? 'disabled' : '' ?> title="<?= h($title) ?>">Renew</button>
            </form>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="return">
              <input type="hidden" name="loan_id" value="<?= (int)$l['loan_id'] ?>">
              <button class="btn btn-sm" type="submit">Return</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty">No active loans.</div>
  <?php endif; ?>
  <div class="ds-note">Overdue loans cannot be renewed. A renewal is also refused when another member is waiting for the title.</div>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Your reservations</h2></div></div>
  <?php if ($myQueues): ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Book</th><th>Position</th><th>Waitlist length</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($myQueues as $q): ?>
        <tr>
          <td data-label="Book"><?= h($q['title']) ?></td>
          <td data-label="Position">#<?= (int)$q['position'] ?></td>
          <td data-label="Waitlist"><?= (int)($queueLens[(int)$q['book_id']] ?? 0) ?></td>
          <td data-label="">
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cancel_reservation">
              <input type="hidden" name="book_id" value="<?= (int)$q['book_id'] ?>">
              <button class="btn btn-sm" type="submit">Cancel</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty">You are not waiting for any title.</div>
  <?php endif; ?>
</div>
<?php render_foot(); ?>