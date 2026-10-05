<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    $bid    = (int)($_POST['book_id'] ?? 0);

    if ($action === 'borrow') {
        $res = borrow_book($pdo, $bid, (int)$me['user_id'], (int)$me['user_id']);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Borrowed "' . $res['title'] . '".' : $res['error']);
    } elseif ($action === 'reserve') {
        $res = reserve_book($pdo, $bid, (int)$me['user_id'], (int)$me['user_id']);
        flash($res['ok'] ? 'info' : 'error', $res['ok'] ? "Added to waitlist — position {$res['position']}." : $res['error']);
    } elseif ($action === 'leave') {
        leave_reservation($pdo, $bid, (int)$me['user_id']);
        flash('info', 'Reservation cancelled.');
    } elseif ($action === 'return') {
        // Members can return their own copy directly.
        // The 4th argument enforces ownership inside checkin_loan().
        $res = checkin_loan($pdo, (int)($_POST['loan_id'] ?? 0), (int)$me['user_id'], (int)$me['user_id']);
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Book returned.' : $res['error']);
    }
    redirect('catalog.php');
}

$search = trim((string)($_GET['q'] ?? ''));
$cat    = (string)($_GET['cat'] ?? 'all');
$avail  = (string)($_GET['avail'] ?? 'all');

$sql  = 'SELECT * FROM books WHERE 1=1';
$args = [];
if ($search !== '') {
    $sql .= ' AND (title LIKE ? OR author LIKE ?)';
    $args[] = '%' . $search . '%';
    $args[] = '%' . $search . '%';
}
if ($cat !== 'all')          { $sql .= ' AND category = ?';        $args[] = $cat; }
if ($avail === 'available')  $sql .= ' AND available_copies > 0';
if ($avail === 'out')        $sql .= ' AND available_copies = 0';
$sql .= ' ORDER BY title LIMIT 200';

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$books = $stmt->fetchAll();

$cats = $pdo->query('SELECT DISTINCT category FROM books ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

$myLoans = [];
foreach (active_loans_of($pdo, (int)$me['user_id']) as $l) {
    $myLoans[(int)$l['book_id']] = $l;
}

$queues = [];
foreach ($pdo->query('SELECT book_id, user_id FROM reservations ORDER BY book_id, position')->fetchAll() as $r) {
    $queues[(int)$r['book_id']][] = (int)$r['user_id'];
}

render_head('Books Availability');
?>
<div class="panel">
  <form class="toolbar" method="get">
    <input type="search" name="q" placeholder="Search by title or author…" value="<?= h($search) ?>" maxlength="100">
    <select name="cat">
      <option value="all">All categories</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= h($c) ?>" <?= $c === $cat ? 'selected' : '' ?>><?= h($c) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="avail">
      <option value="all"       <?= $avail === 'all' ? 'selected' : '' ?>>All copies</option>
      <option value="available" <?= $avail === 'available' ? 'selected' : '' ?>>Available now</option>
      <option value="out"       <?= $avail === 'out' ? 'selected' : '' ?>>Out of stock</option>
    </select>
    <button class="btn btn-sm" type="submit">Apply</button>
  </form>

  <?php if ($books): ?>
    <div class="book-grid">
    <?php foreach ($books as $b):
      $bid   = (int)$b['book_id'];
      $pct   = $b['total_copies'] ? round($b['available_copies'] / $b['total_copies'] * 100) : 0;
      $cls   = $b['available_copies'] === 0 ? 'none' : ($b['available_copies'] <= 1 ? 'low' : '');
      $queue = $queues[$bid] ?? [];
      $mine  = $myLoans[$bid] ?? null;
      $queued = in_array((int)$me['user_id'], $queue, true);
    ?>
      <article class="book">
        <div>
          <div class="t"><?= h($b['title']) ?></div>
          <div class="a"><?= h($b['author']) ?></div>
        </div>
        <div class="meta">
          <span class="badge"><?= h($b['category']) ?></span>
          <span class="badge <?= $b['available_copies'] ? 'ok' : 'bad' ?>">
            <?= (int)$b['available_copies'] ?>/<?= (int)$b['total_copies'] ?> available
          </span>
          <?php if ($queue): ?><span class="badge info"><?= count($queue) ?> waiting</span><?php endif; ?>
        </div>
        <div class="bar <?= $cls ?>"><i style="width:<?= $pct ?>%"></i></div>
        <div class="acts">
          <?php if ($mine): ?>
            <span class="badge warn">You have this</span>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="return">
              <input type="hidden" name="loan_id" value="<?= (int)$mine['loan_id'] ?>">
              <button class="btn btn-sm" type="submit">Return</button>
            </form>
          <?php elseif ($b['available_copies'] > 0): ?>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="borrow">
              <input type="hidden" name="book_id" value="<?= $bid ?>">
              <button class="btn btn-primary btn-sm" type="submit">Borrow</button>
            </form>
          <?php elseif ($queued): ?>
            <span class="badge info">Queued #<?= array_search((int)$me['user_id'], $queue, true) + 1 ?></span>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="leave">
              <input type="hidden" name="book_id" value="<?= $bid ?>">
              <button class="btn btn-sm" type="submit">Leave queue</button>
            </form>
          <?php else: ?>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="reserve">
              <input type="hidden" name="book_id" value="<?= $bid ?>">
              <button class="btn btn-sm" type="submit">Join waitlist</button>
            </form>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="empty">No titles match your search.</div>
  <?php endif; ?>
</div>
<?php render_foot(); ?>