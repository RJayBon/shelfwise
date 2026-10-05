<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_role(['staff', 'admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'checkin') {
        $res = checkin_loan($pdo, (int)($_POST['loan_id'] ?? 0), (int)$me['user_id']);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Checked in.' : $res['error']);
    }
    redirect('checkin.php');
}

$q  = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT l.loan_id, l.due_at, UNIX_TIMESTAMP(l.due_at)*1000 AS due_ts,
               b.title, u.name AS user_name,
               (l.due_at < NOW()) AS overdue
        FROM loans l
        JOIN books b ON b.book_id = l.book_id
        JOIN users u ON u.user_id = l.user_id
        WHERE l.status = 'active'";
$args = [];
if ($q !== '') { $sql .= ' AND (b.title LIKE ? OR u.name LIKE ?)'; $args = ["%$q%", "%$q%"]; }
$sql .= ' ORDER BY l.due_at ASC LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

render_head('Book Check-In');
?>
<div class="panel">
  <form class="toolbar" method="get">
    <input type="search" name="q" placeholder="Search by book or member…" value="<?= h($q) ?>" maxlength="100">
    <button class="btn btn-sm" type="submit">Search</button>
  </form>
  <?php if ($rows): ?>
    <div class="table-scroll"><table>
      <thead><tr><th>Book</th><th>Member</th><th>Due</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td data-label="Book"><?= h($r['title']) ?></td>
          <td data-label="Member"><?= h($r['user_name']) ?></td>
          <td data-label="Due"><?= h(date('M j, Y', (int)($r['due_ts'] / 1000))) ?></td>
          <td data-label="Status"><span class="badge <?= $r['overdue'] ? 'bad' : 'ok' ?>"><?= $r['overdue'] ? 'Overdue' : 'On time' ?></span></td>
          <td data-label="">
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="checkin">
              <input type="hidden" name="loan_id" value="<?= (int)$r['loan_id'] ?>">
              <button class="btn btn-primary btn-sm" type="submit">Check in</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty">Nothing to check in.</div>
  <?php endif; ?>
  <div class="ds-note">On check-in the copy count goes back up and the first member in the waitlist is notified immediately.</div>
</div>
<?php render_foot(); ?>