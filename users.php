<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_role(['admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'set_role') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $role = (string)($_POST['role'] ?? '');

        // Safety: an admin cannot demote themselves and lock the system out.
        if ($uid === (int)$me['user_id'] && $role !== 'admin') {
            flash('error', 'You cannot change your own admin role.');
        } else {
            $res = set_user_role($pdo, $uid, $role);
            flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Role updated.' : $res['error']);
        }
    }
    redirect('users.php');
}

$users = $pdo->query('SELECT user_id, name, email, role FROM users ORDER BY user_id')->fetchAll();

$counts = [];
foreach ($pdo->query("SELECT user_id, COUNT(*) c FROM loans WHERE status = 'active' GROUP BY user_id")->fetchAll() as $r) {
    $counts[(int)$r['user_id']] = (int)$r['c'];
}

render_head('Users');
?>
<div class="panel">
  <div class="table-scroll"><table>
    <thead><tr><th>Name</th><th>Email</th><th>Active loans</th><th>Role</th></tr></thead>
    <tbody>
    <?php foreach ($users as $u):
      $isMe = (int)$u['user_id'] === (int)$me['user_id'];
    ?>
      <tr>
        <td data-label="Name"><?= h($u['name']) ?><?= $isMe ? ' <span class="badge info">You</span>' : '' ?></td>
        <td data-label="Email"><?= h($u['email']) ?></td>
        <td data-label="Active loans"><?= (int)($counts[(int)$u['user_id']] ?? 0) ?></td>
        <td data-label="Role">
          <form method="post" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_role">
            <input type="hidden" name="user_id" value="<?= (int)$u['user_id'] ?>">
            <select name="role" onchange="this.form.submit()" <?= $isMe ? 'disabled' : '' ?>>
              <?php foreach (['member', 'staff', 'admin'] as $r): ?>
                <option value="<?= h($r) ?>" <?= $u['role'] === $r ? 'selected' : '' ?>><?= h(role_label($r)) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php render_foot(); ?>