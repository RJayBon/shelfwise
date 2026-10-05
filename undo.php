<?php
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Use POST.'); }
csrf_check();

$last = pop_undoable();
if (!$last) {
    flash('info', 'Nothing to undo.');
    redirect('dashboard.php');
}

$type = $last['type'] ?? '';

try {
    if ($type === 'borrow') {
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT book_id, user_id FROM loans WHERE loan_id = ? AND status = 'active' FOR UPDATE");
        $s->execute([(int)$last['loan_id']]);
        $l = $s->fetch();
        if ($l) {
            $pdo->prepare("UPDATE loans SET status = 'cancelled' WHERE loan_id = ?")->execute([(int)$last['loan_id']]);
            $pdo->prepare('UPDATE books SET available_copies = LEAST(total_copies, available_copies + 1) WHERE book_id = ?')
                ->execute([(int)$l['book_id']]);
            log_history($pdo, 'undo', (int)$l['book_id'], (int)$l['user_id'], (int)$me['user_id'], 'Undid: Borrowed');
        }
        $pdo->commit();
        flash('info', 'Borrow undone.');

    } elseif ($type === 'renew') {
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT book_id, user_id FROM loans WHERE loan_id = ? AND status = 'active' FOR UPDATE");
        $s->execute([(int)$last['loan_id']]);
        $l = $s->fetch();
        if ($l) {
            $upd = $pdo->prepare('UPDATE loans SET due_at = FROM_UNIXTIME(?), renewals = GREATEST(0, renewals - 1) WHERE loan_id = ?');
            $upd->execute([(int)floor($last['prev_due_ts'] / 1000), (int)$last['loan_id']]);
            log_history($pdo, 'undo', (int)$l['book_id'], (int)$l['user_id'], (int)$me['user_id'], 'Undid: Renewed');
        }
        $pdo->commit();
        flash('info', 'Renewal undone.');

    } elseif ($type === 'checkin') {
        $pdo->beginTransaction();
        $s = $pdo->prepare("SELECT book_id FROM loans WHERE loan_id = ? AND status = 'returned' FOR UPDATE");
        $s->execute([(int)$last['loan_id']]);
        $l = $s->fetch();
        if ($l) {
            $bid = (int)$l['book_id'];
            $b = $pdo->prepare('SELECT available_copies FROM books WHERE book_id = ? FOR UPDATE');
            $b->execute([$bid]);
            $avail = (int)$b->fetchColumn();
            if ($avail > 0) {
                $pdo->prepare("UPDATE loans SET status = 'active', returned_at = NULL WHERE loan_id = ?")->execute([(int)$last['loan_id']]);
                $pdo->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?')->execute([$bid]);
                if (!empty($last['notified'])) {
                    $notified = (int)$last['notified'];
                    $has = $pdo->prepare("SELECT 1 FROM loans WHERE book_id = ? AND user_id = ? AND status = 'active'");
                    $has->execute([$bid, $notified]);
                    if (!$has->fetchColumn()) {
                        $pdo->prepare('DELETE FROM reservations WHERE book_id = ? AND user_id = ?')->execute([$bid, $notified]);
                        $pdo->prepare('UPDATE reservations SET position = position + 1 WHERE book_id = ?')->execute([$bid]);
                        $pdo->prepare('INSERT INTO reservations (book_id, user_id, position) VALUES (?, ?, 1)')->execute([$bid, $notified]);
                    }
                }
                log_history($pdo, 'undo', $bid, (int)$last['user_id'], (int)$me['user_id'], 'Undid: Checked in');
                flash('info', 'Check-in undone.');
            } else {
                flash('error', 'That copy has already been borrowed again.');
            }
        } else {
            flash('error', 'That loan is not in the returned state.');
        }
        $pdo->commit();

    } elseif ($type === 'reserve') {
        leave_reservation($pdo, (int)$last['book_id'], (int)$last['user_id']);
        flash('info', 'Reservation undone.');

    } else {
        flash('warning', 'That action can no longer be undone.');
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    flash('error', 'Undo failed: ' . $e->getMessage());
}

redirect('dashboard.php');