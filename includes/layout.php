<?php
declare(strict_types=1);

function render_head(string $title): void
{
    $user    = current_user();
    $current = basename($_SERVER['SCRIPT_NAME']);

    $items = [
        ['dashboard.php', 'Overview',           null],
        ['catalog.php',   'Books Availability', null],
        ['loans.php',     'My Loans & Renewal', null],
        ['checkin.php',   'Check-In Desk',      ['staff', 'admin']],
        ['history.php',   'Borrowing History',  null],
        ['manage.php',    'Manage Catalog',     ['admin']],
        ['users.php',     'Users',              ['admin']],
        ['about.php',     'About',              null],
    ];
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($title) ?> — Shelfwise</title>
<link rel="stylesheet" href="<?= h(base_url('css/styles.css')) ?>">
</head>
<body>
<div class="dash">
  <aside class="side">
    <div class="brand"><span class="brand-mark">&#9776;</span> Shelfwise</div>
    <nav class="side-nav">
      <?php foreach ($items as [$href, $label, $roles]): ?>
        <?php if ($roles && (!$user || !in_array($user['role'], $roles, true))) continue; ?>
        <a href="<?= h(base_url($href)) ?>" class="<?= $current === $href ? 'active' : '' ?>"><?= h($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="side-foot">
      <p class="side-email"><?= h($user['email'] ?? '') ?></p>
      <div class="side-actions">
        <form method="post" action="<?= h(base_url('undo.php')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-sm" type="submit" <?= empty($_SESSION['undoable']) ? 'disabled' : '' ?>>Undo last</button>
        </form>
        <form method="post" action="<?= h(base_url('logout.php')) ?>">
          <?= csrf_field() ?>
          <button class="btn btn-sm" type="submit">Sign out</button>
        </form>
      </div>
    </div>
  </aside>
  <main class="main">
    <?php
    foreach (take_flashes() as $f):
        ?><div class="flash flash-<?= h($f['type']) ?>"><?= h($f['message']) ?></div><?php
    endforeach;

    if ($user) {
        foreach (compute_alerts(db(), (int)$user['user_id']) as $a):
            ?><div class="flash flash-<?= h($a['type']) ?>"><strong><?= h($a['title']) ?>:</strong> <?= h($a['message']) ?></div><?php
        endforeach;
    }
    ?>
    <div class="main-head">
      <div><h1><?= h($title) ?></h1></div>
      <?php if ($user): ?>
      <div class="who">
        <div class="avatar"><?= h(strtoupper(mb_substr($user['name'], 0, 1))) ?></div>
        <div>
          <div style="font-size:0.92rem;"><?= h($user['name']) ?></div>
          <span class="badge <?= $user['role'] === 'admin' ? 'warn' : ($user['role'] === 'staff' ? 'info' : 'ok') ?>">
            <?= h(role_label($user['role'])) ?>
          </span>
        </div>
      </div>
      <?php endif; ?>
    </div>
<?php
}

function render_foot(): void
{
    ?>
  </main>
</div>
</body>
</html><?php
}

function render_403(): void
{
    if (!headers_sent()) http_response_code(403);
    ?><!doctype html><meta charset="utf-8"><title>Forbidden</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?= h(base_url('css/styles.css')) ?>">
    <div style="max-width:520px;margin:80px auto;text-align:center;padding:0 16px;">
      <h1 style="font-size:2rem;margin-bottom:12px;">403 — Forbidden</h1>
      <p style="color:var(--ink-dim);margin-bottom:20px;">You don't have permission to view that page.</p>
      <a class="btn btn-primary" href="<?= h(base_url('dashboard.php')) ?>">Return to the dashboard</a>
    </div>
<?php
}