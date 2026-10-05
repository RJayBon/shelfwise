<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $res = login((string)($_POST['email'] ?? ''), (string)($_POST['password'] ?? ''));
    if ($res['ok']) {
        flash('success', 'Welcome back, ' . $res['user']['name'] . '.');
        redirect('dashboard.php');
    }
    $error = $res['error'];
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in — Shelfwise</title>
<link rel="stylesheet" href="<?= h(base_url('css/styles.css')) ?>">
</head>
<body>
<div class="auth-shell">
  <aside class="auth-aside">
    <div>
      <div class="brand"><span class="brand-mark">&#9776;</span> Shelfwise</div>
      <h1 style="margin-top:42px;font-size:2.3rem;max-width:14ch;">One desk for every loan, renewal and return.</h1>
      <p style="color:var(--ink-dim);margin-top:18px;max-width:44ch;">
        Live availability, fair reservation queues, automatic due-date alerts and a full borrowing history —
        all in one place.
      </p>
    </div>
    <p style="color:var(--ink-faint);font-size:0.85rem;"><a href="<?= h(base_url('index.php')) ?>">&larr; Back to the overview</a></p>
  </aside>

  <main class="auth-main">
    <div class="auth-box">
      <h2 style="font-size:1.5rem;margin-bottom:6px;">Welcome back</h2>
      <p style="color:var(--ink-faint);font-size:0.9rem;margin-bottom:20px;">Sign in to Shelfwise to reach your loans and the circulation desk.</p>

      <?php foreach (take_flashes() as $f): ?>
        <div class="flash flash-<?= h($f['type']) ?>"><?= h($f['message']) ?></div>
      <?php endforeach; ?>
      <?php if ($error): ?>
        <div class="flash flash-error"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="email">Email</label>
          <input id="email" name="email" type="email" autocomplete="email" required value="<?= h($_POST['email'] ?? '') ?>"></div>
        <div class="field"><label for="password">Password</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required></div>
        <button class="btn btn-primary" type="submit" style="width:100%;">Sign in</button>
      </form>

      <p style="margin-top:16px;color:var(--ink-dim);font-size:0.9rem;">No account yet?
        <a href="<?= h(base_url('register.php')) ?>">Register</a></p>
    </div>
  </main>
</div>
</body>
</html>