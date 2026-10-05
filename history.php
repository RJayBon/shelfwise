<?php
require __DIR__ . '/includes/bootstrap.php';
$me  = require_login();
$pdo = db();

$type = (string)($_GET['type'] ?? 'all');
$where = [];
$args  = [];

if ($me['role'] === 'member') {
    $where[] = 'h.user_id = ?';
    $args[]  = (int)$me['user_id'];
}
if (in_array($type, ['borrow', 'renew', 'checkin', 'reserve', 'notify', 'undo'], true)) {
    $where[] = 'h.type = ?';
    $args[]  = $type;
}

$sql = "SELECT h.history_id, h.type, h.book_id, h.user_id, h.actor_id, h.note,
               h.created_at, UNIX_TIMESTAMP(h.created_at)*1000 AS at,
               b.title, u.name AS user_name, a.name AS actor_name
        FROM history h
        LEFT JOIN books b ON b.book_id = h.book_id
        LEFT JOIN users u ON u.user_id = h.user_id
        LEFT JOIN users a ON a.user_id = h.actor_id";
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY h.created_at DESC, h.history_id DESC LIMIT 500';

$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$rows = $stmt->fetchAll();

$labels = ['borrow' => 'Borrowed', 'renew' => 'Renewed', 'checkin' => 'Checked in', 'reserve' => 'Reserved', 'notify' => 'Notified', 'undo' => 'Undo'];

render_head('Borrowing History');
?>
<div class="panel">
  <form class="toolbar" method="get">
    <select name="type" onchange="this.form.submit()">
      <option value="all">All actions</option>
      <?php foreach ($labels as $k => $v): ?>
        <option value="<?= h($k) ?>" <?= $type === $k ? 'selected' : '' ?>><?= h($v) ?></option>
      <?php endforeach; ?>
    </select>
    <span class="badge"><?= count($rows) ?> record(s)</span>
  </form>
  <?php if ($rows): ?>
    <div class="table-scroll"><table>
      <thead><tr><th>When</th><th>Action</th><th>Book</th>
        <?php if ($me['role'] !== 'member'): ?><th>Member</th><th>By</th><?php endif; ?>
        <th>Note</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td data-label="When"><?= h($r['created_at']) ?></td>
          <td data-label="Action"><span class="badge"><?= h($labels[$r['type']] ?? $r['type']) ?></span></td>
          <td data-label="Book"><?= h($r['title'] ?? '(removed title)') ?></td>
          <?php if ($me['role'] !== 'member'): ?>
            <td data-label="Member"><?= h($r['user_name'] ?? '—') ?></td>
            <td data-label="By"><?= h($r['actor_name'] ?? '—') ?></td>
          <?php endif; ?>
          <td data-label="Note" style="color:var(--ink-dim);"><?= h($r['note'] ?? '') ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  <?php else: ?>
    <div class="empty">No history yet.</div>
  <?php endif; ?>
</div>
<?php render_foot(); ?>