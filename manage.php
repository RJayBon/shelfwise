<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_role(['admin']);
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $res = add_book(
            $pdo,
            (string)($_POST['title'] ?? ''),
            (string)($_POST['author'] ?? ''),
            (string)($_POST['category'] ?? ''),
            (int)($_POST['total'] ?? 1),
            isset($_POST['isbn']) ? (string)$_POST['isbn'] : null
        );
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Added "' . $res['title'] . '".' : $res['error']);
    } elseif ($action === 'remove') {
        $res = remove_book($pdo, (int)($_POST['book_id'] ?? 0));
        flash($res['ok'] ? 'success' : 'error', $res['ok'] ? 'Book removed.' : $res['error']);
    }
    redirect('manage.php');
}

$books = $pdo->query('SELECT * FROM books ORDER BY title')->fetchAll();

render_head('Manage Catalog');
?>
<div class="panel">
  <div class="panel-head"><div><h2>Add a title</h2></div></div>
  <form method="post">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <div class="grid grid-2">
      <div class="field"><label>Title</label><input name="title" required maxlength="255"></div>
      <div class="field"><label>Author</label><input name="author" required maxlength="100"></div>
      <div class="field"><label>Category</label><input name="category" maxlength="50" placeholder="General"></div>
      <div class="field"><label>ISBN (optional)</label><input name="isbn" maxlength="20"></div>
      <div class="field"><label>Copies</label><input name="total" type="number" min="1" max="1000" value="2" required></div>
    </div>
    <button class="btn btn-primary" type="submit">Add to catalog</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><div><h2>Catalog</h2><p><?= count($books) ?> titles</p></div></div>
  <div class="table-scroll"><table>
    <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Copies</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($books as $b): ?>
      <tr>
        <td data-label="Title"><?= h($b['title']) ?></td>
        <td data-label="Author"><?= h($b['author']) ?></td>
        <td data-label="Category"><?= h($b['category']) ?></td>
        <td data-label="Copies"><?= (int)$b['available_copies'] ?>/<?= (int)$b['total_copies'] ?></td>
        <td data-label="">
          <form method="post" onsubmit="return confirm('Remove this title?');" style="display:inline;">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="remove">
            <input type="hidden" name="book_id" value="<?= (int)$b['book_id'] ?>">
            <button class="btn btn-sm" type="submit">Remove</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<?php render_foot(); ?>