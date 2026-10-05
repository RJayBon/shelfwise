    <?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
render_head('About');
?>
<div class="panel">
  <div class="panel-head"><div><h2>Purpose</h2></div></div>
  <p style="color:var(--ink-dim);">Manual library circulation has no single source of truth: copy counts are guessed, due dates pass unnoticed and popular titles are handed out unfairly. This system centralises borrowing, renewal, check-in and availability, and raises due-date alerts so nothing slips.</p>
</div>
<div class="panel">
  <div class="panel-head"><div><h2>Main functionalities</h2></div></div>
  <ol style="color:var(--ink-dim);padding-left:20px;">
    <li>Books availability monitoring with live copy meters and search</li>
    <li>Book borrowing with loan limits and automatic due dates</li>
    <li>Book renewal (<?= RENEW_DAYS ?> days, max <?= MAX_RENEWALS ?>, blocked when overdue or reserved)</li>
    <li>Book check-in that restocks the copy and releases the waitlist</li>
    <li>Borrowing history with an audit trail of who performed each action</li>
  </ol>
</div>
<div class="panel">
  <div class="panel-head"><div><h2>Roles</h2></div></div>
  <div class="grid grid-3">
    <div class="card"><span class="tag">Role</span><h3>Member</h3><p>Browse, borrow, renew, reserve, return their own books, read their history.</p></div>
    <div class="card"><span class="tag">Role</span><h3>Staff</h3><p>Member powers plus the check-in desk: return any copy, see all active loans.</p></div>
    <div class="card"><span class="tag">Role</span><h3>Administrator</h3><p>Staff powers plus catalog management and user accounts.</p></div>
  </div>
</div>
<?php render_foot(); ?>