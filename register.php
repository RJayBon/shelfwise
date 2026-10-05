<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect('dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $res = register(
        (string)($_POST['name'] ?? ''),
        (string)($_POST['email'] ?? ''),
        (string)($_POST['password'] ?? '')
    );
    if ($res['ok']) {
        flash('success', 'Welcome to Shelfwise. You are signed in as a Member.');
        redirect('dashboard.php');
    }
    $error = $res['error'];
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register — Shelfwise</title>
<link rel="stylesheet" href="<?= h(base_url('css/styles.css')) ?>">
</head>
<body>
<div class="auth-shell">
  <aside class="auth-aside">
    <div>
      <div class="brand"><span class="brand-mark">&#9776;</span> Shelfwise</div>
      <h1 style="margin-top:42px;font-size:2.3rem;max-width:14ch;">Create an account and start borrowing.</h1>
    </div>
    <p style="color:var(--ink-faint);font-size:0.85rem;"><a href="<?= h(base_url('index.php')) ?>">&larr; Back to the overview</a></p>
  </aside>

  <main class="auth-main">
    <div class="auth-box">
      <h2 style="font-size:1.5rem;margin-bottom:6px;">Create an account</h2>
      <p style="color:var(--ink-faint);font-size:0.9rem;margin-bottom:20px;">
        New Shelfwise accounts start as a <strong>Member</strong>. Staff and administrator roles are assigned by an admin.
      </p>

      <?php if ($error): ?>
        <div class="flash flash-error"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="field"><label for="name">Full name</label>
          <input id="name" name="name" type="text" maxlength="100" required value="<?= h($_POST['name'] ?? '') ?>"></div>
        <div class="field"><label for="email">Email</label>
          <input id="email" name="email" type="email" maxlength="100" required value="<?= h($_POST['email'] ?? '') ?>"></div>
        <div class="field"><label for="password">Password (6–72 characters)</label>
          <input id="password" name="password" type="password" minlength="6" maxlength="72" required></div>
        <button class="btn btn-primary" type="submit" style="width:100%;">Create account</button>
      </form>

      <p style="margin-top:16px;color:var(--ink-dim);font-size:0.9rem;">Already registered?
        <a href="<?= h(base_url('login.php')) ?>">Sign in</a></p>
    </div>
  </main>
</div>
</body>
</html>