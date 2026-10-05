<?php
require __DIR__ . '/includes/bootstrap.php';

if (current_user()) redirect('dashboard.php');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Shelfwise — Library Circulation System</title>
<link rel="stylesheet" href="<?= h(base_url('css/styles.css')) ?>">
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <div class="brand"><span class="brand-mark">&#9776;</span> Shelfwise</div>
    <nav class="nav-links">
      <a class="btn btn-primary btn-sm" href="<?= h(base_url('login.php')) ?>">Sign in</a>
      <a class="btn btn-sm" href="<?= h(base_url('register.php')) ?>">Register</a>
    </nav>
  </div>
</header>

<main>
  <section class="wrap hero">
    <span class="eyebrow">Shelfwise Circulation System</span>
    <h1>Keep every book, loan and due date under control.</h1>
    <p class="lead">
      School and community libraries still track loans on paper or in spreadsheets, so copies go missing,
      due dates slip past unnoticed and members queue at the desk just to ask "is it available?".
      Shelfwise replaces that with a live circulation desk: availability updates the moment a book moves,
      renewals follow clear rules, returns are one click, and every member gets a warning before a book goes overdue.
    </p>
    <div class="hero-actions">
      <a class="btn btn-primary" href="<?= h(base_url('login.php')) ?>">Open Shelfwise</a>
      <a class="btn" href="#features">See the features</a>
    </div>
  </section>

  <section class="wrap section" id="features">
    <h2 class="section-title">Main functionalities</h2>
    <div class="grid grid-3">
      <div class="card"><span class="tag">Feature 01</span><h3>Books availability monitoring</h3><p>A live catalog with copy meters, search and category filters. Every borrow or return updates the counts instantly.</p></div>
      <div class="card"><span class="tag">Feature 02</span><h3>Book renewal</h3><p>Extend a loan by 7 days, up to two times, and only when the loan is on time and nobody is waiting for that title.</p></div>
      <div class="card"><span class="tag">Feature 03</span><h3>Book check-in</h3><p>Staff return any copy from the desk; members return their own. The copy goes back in stock and the first person in the waitlist is notified.</p></div>
      <div class="card"><span class="tag">Feature 04</span><h3>Borrowing history</h3><p>A complete, time-ordered log of borrows, renewals, returns and reservations, filterable by action and member.</p></div>
      <div class="card"><span class="tag">Feature 05</span><h3>Due-date alerts</h3><p>Overdue and due-soon warnings appear at the top of every page, and every action is confirmed with a flash message.</p></div>
      <div class="card"><span class="tag">Bonus</span><h3>Undo last action</h3><p>The side panel can reverse the most recent borrow, renewal, return or reservation.</p></div>
    </div>
  </section>
</main>

<footer class="footer">
  <div class="wrap">Shelfwise — server-rendered PHP with MySQL.</div>
</footer>
</body>
</html>