<?php
declare(strict_types=1);

/* ---------- business rules (mirror js/data.js RULES) ---------- */
const LOAN_DAYS     = 14;
const RENEW_DAYS    = 7;
const MAX_RENEWALS  = 2;
const MAX_ACTIVE    = 5;
const DUE_SOON_DAYS = 3;

/* ---------- URL + escaping ---------- */
function base_url(string $path = ''): string
{
    $base = config()['app']['base_path'] ?? '';
    return $base . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}

function h($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function role_label(string $r): string
{
    return ['member' => 'Member', 'staff' => 'Staff', 'admin' => 'Administrator'][$r] ?? $r;
}

/* ---------- flash messages ---------- */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- history log ---------- */
function log_history(PDO $pdo, string $type, ?int $book_id, ?int $user_id, ?int $actor_id, ?string $note): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO history (type, book_id, user_id, actor_id, note) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$type, $book_id, $user_id, $actor_id, $note]);
}

/* ---------- undo stack (one entry per session) ---------- */
function push_undoable(array $action): void
{
    $_SESSION['undoable'] = $action;
}

function pop_undoable(): ?array
{
    $a = $_SESSION['undoable'] ?? null;
    unset($_SESSION['undoable']);
    return $a;
}

/* ---------- loan helpers ---------- */
function active_loans_of(PDO $pdo, int $uid): array
{
    $s = $pdo->prepare(
        "SELECT l.loan_id, l.book_id, l.borrowed_at, l.due_at, l.renewals, b.title, b.author,
                UNIX_TIMESTAMP(l.borrowed_at)*1000 AS borrowed_ts,
                UNIX_TIMESTAMP(l.due_at)*1000      AS due_ts
         FROM loans l JOIN books b ON b.book_id = l.book_id
         WHERE l.user_id = ? AND l.status = 'active'
         ORDER BY l.due_at ASC"
    );
    $s->execute([$uid]);
    return $s->fetchAll();
}

function loan_status(array $loan): string
{
    $left = ((int)$loan['due_ts'] - (int)(microtime(true) * 1000)) / 1000;
    if ($left < 0)                       return 'overdue';
    if ($left <= DUE_SOON_DAYS * 86400)  return 'due-soon';
    return 'on-time';
}

/* ---------- borrow ---------- */
function borrow_book(PDO $pdo, int $bid, int $actorId, int $targetId): array
{
    $pdo->beginTransaction();
    try {
        // Lock the user row so concurrent borrows for the same member serialize.
        $lock = $pdo->prepare('SELECT user_id FROM users WHERE user_id = ? FOR UPDATE');
        $lock->execute([$targetId]);

        $chk = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE user_id = ? AND status = 'active'");
        $chk->execute([$targetId]);
        if ((int)$chk->fetchColumn() >= MAX_ACTIVE) {
            throw new RuntimeException('Borrow limit reached (max ' . MAX_ACTIVE . ').');
        }

        $s = $pdo->prepare('SELECT title, available_copies FROM books WHERE book_id = ? FOR UPDATE');
        $s->execute([$bid]);
        $book = $s->fetch();
        if (!$book) throw new RuntimeException('Book not found.');
        if ((int)$book['available_copies'] <= 0) throw new RuntimeException('No copies available — join the waitlist.');

        $dup = $pdo->prepare("SELECT 1 FROM loans WHERE book_id = ? AND user_id = ? AND status = 'active'");
        $dup->execute([$bid, $targetId]);
        if ($dup->fetchColumn()) throw new RuntimeException('That member already has this book.');

        $pdo->prepare('UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?')->execute([$bid]);
        $pdo->prepare(
            'INSERT INTO loans (book_id, user_id, borrowed_at, due_at)
             VALUES (?, ?, NOW(), NOW() + INTERVAL ' . LOAN_DAYS . ' DAY)'
        )->execute([$bid, $targetId]);
        $loanId = (int)$pdo->lastInsertId();

        log_history($pdo, 'borrow', $bid, $targetId, $actorId, 'Due in ' . LOAN_DAYS . ' days');
        $pdo->commit();

        push_undoable(['type' => 'borrow', 'loan_id' => $loanId, 'book_id' => $bid, 'user_id' => $targetId, 'title' => $book['title']]);
        return ['ok' => true, 'loan_id' => $loanId, 'title' => $book['title']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/* ---------- renew (blocked when overdue or reserved) ---------- */
function renew_loan(PDO $pdo, int $lid, int $actorId, bool $asStaff): array
{
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare(
            "SELECT l.loan_id, l.book_id, l.user_id, l.renewals, l.due_at, b.title,
                    (l.due_at < NOW()) AS overdue,
                    (SELECT COUNT(*) FROM reservations r WHERE r.book_id = l.book_id) AS queue_len
             FROM loans l JOIN books b ON b.book_id = l.book_id
             WHERE l.loan_id = ? AND l.status = 'active' FOR UPDATE"
        );
        $s->execute([$lid]);
        $loan = $s->fetch();
        if (!$loan)                                     throw new RuntimeException('Loan not found.');
        if (!$asStaff && (int)$loan['user_id'] !== $actorId) throw new RuntimeException('That is not your loan.');
        if ((bool)$loan['overdue'])                     throw new RuntimeException('Overdue loans cannot be renewed — return the book first.');
        if ((int)$loan['renewals'] >= MAX_RENEWALS)     throw new RuntimeException('Renewal limit reached.');
        if ((int)$loan['queue_len'] > 0)                throw new RuntimeException('Reserved by another member — renewal blocked.');

        $prevDueMs = strtotime((string)$loan['due_at']) * 1000;

        $pdo->prepare(
            'UPDATE loans
             SET due_at = due_at + INTERVAL ' . RENEW_DAYS . ' DAY,
                 renewals = renewals + 1
             WHERE loan_id = ?'
        )->execute([$lid]);

        $new = $pdo->prepare('SELECT UNIX_TIMESTAMP(due_at)*1000 FROM loans WHERE loan_id = ?');
        $new->execute([$lid]);
        $newTs = (int)$new->fetchColumn();

        log_history($pdo, 'renew', (int)$loan['book_id'], (int)$loan['user_id'], $actorId, 'Extended by ' . RENEW_DAYS . ' days');
        $pdo->commit();

        push_undoable([
            'type' => 'renew',
            'loan_id' => $lid,
            'book_id' => (int)$loan['book_id'],
            'user_id' => (int)$loan['user_id'],
            'prev_due_ts' => $prevDueMs,
            'title' => $loan['title'],
        ]);
        return ['ok' => true, 'title' => $loan['title'], 'due_ts' => $newTs];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/* ---------- check-in ----------
 * $restrictToUserId: when non-null, the loan must belong to that user
 * (used for member self-returns so a member cannot return another's loan).
 */
function checkin_loan(PDO $pdo, int $lid, int $actorId, ?int $restrictToUserId = null): array
{
    $pdo->beginTransaction();
    try {
        $s = $pdo->prepare(
            "SELECT book_id, user_id, (due_at < NOW()) AS late
             FROM loans WHERE loan_id = ? AND status = 'active' FOR UPDATE"
        );
        $s->execute([$lid]);
        $loan = $s->fetch();
        if (!$loan) throw new RuntimeException('Loan not found.');

        if ($restrictToUserId !== null && (int)$loan['user_id'] !== $restrictToUserId) {
            throw new RuntimeException('You can only return your own loans.');
        }

        $bid  = (int)$loan['book_id'];
        $late = (bool)$loan['late'];

        $pdo->prepare("UPDATE loans SET status = 'returned', returned_at = NOW() WHERE loan_id = ?")->execute([$lid]);
        $pdo->prepare('UPDATE books SET available_copies = LEAST(total_copies, available_copies + 1) WHERE book_id = ?')->execute([$bid]);

        $first = $pdo->prepare(
            'SELECT reservation_id, user_id FROM reservations
             WHERE book_id = ? ORDER BY position ASC, queued_at ASC LIMIT 1 FOR UPDATE'
        );
        $first->execute([$bid]);
        $notified = null;
        if ($row = $first->fetch()) {
            $notified = (int)$row['user_id'];
            $pdo->prepare('DELETE FROM reservations WHERE reservation_id = ?')->execute([(int)$row['reservation_id']]);
            $pdo->prepare('UPDATE reservations SET position = position - 1 WHERE book_id = ?')->execute([$bid]);
            log_history($pdo, 'notify', $bid, $notified, $actorId, 'Reserved copy is ready for pickup');
        }
        log_history($pdo, 'checkin', $bid, (int)$loan['user_id'], $actorId, $late ? 'Returned late' : 'Returned on time');
        $pdo->commit();

        push_undoable([
            'type' => 'checkin',
            'loan_id' => $lid,
            'book_id' => $bid,
            'user_id' => (int)$loan['user_id'],
            'notified' => $notified,
        ]);
        return ['ok' => true, 'notified_user_id' => $notified, 'late' => $late];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/* ---------- reservations ---------- */
function reserve_book(PDO $pdo, int $bid, int $uid, int $actorId): array
{
    $pdo->beginTransaction();
    try {
        $chk = $pdo->prepare('SELECT 1 FROM reservations WHERE book_id = ? AND user_id = ? FOR UPDATE');
        $chk->execute([$bid, $uid]);
        if ($chk->fetchColumn()) throw new RuntimeException('Already on the waitlist.');

        $hasLoan = $pdo->prepare("SELECT 1 FROM loans WHERE book_id = ? AND user_id = ? AND status = 'active'");
        $hasLoan->execute([$bid, $uid]);
        if ($hasLoan->fetchColumn()) throw new RuntimeException('You already have this book on loan.');

        $s = $pdo->prepare('SELECT COALESCE(MAX(position),0)+1 FROM reservations WHERE book_id = ? FOR UPDATE');
        $s->execute([$bid]);
        $pos = (int)$s->fetchColumn();

        $pdo->prepare('INSERT INTO reservations (book_id, user_id, position) VALUES (?, ?, ?)')->execute([$bid, $uid, $pos]);
        log_history($pdo, 'reserve', $bid, $uid, $actorId, "Queued at position $pos");
        $pdo->commit();

        push_undoable(['type' => 'reserve', 'book_id' => $bid, 'user_id' => $uid]);
        return ['ok' => true, 'position' => $pos];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

function leave_reservation(PDO $pdo, int $bid, int $uid): array
{
    $pdo->beginTransaction();
    try {
        $del = $pdo->prepare('DELETE FROM reservations WHERE book_id = ? AND user_id = ?');
        $del->execute([$bid, $uid]);
        $removed = $del->rowCount();

        $ids = $pdo->prepare('SELECT reservation_id FROM reservations WHERE book_id = ? ORDER BY position, queued_at');
        $ids->execute([$bid]);
        $upd = $pdo->prepare('UPDATE reservations SET position = ? WHERE reservation_id = ?');
        $i = 1;
        foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $rid) {
            $upd->execute([$i++, (int)$rid]);
        }
        $pdo->commit();
        return ['ok' => true, 'removed' => $removed > 0];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/* ---------- catalog ---------- */
function add_book(PDO $pdo, string $title, string $author, string $cat, int $total, ?string $isbn): array
{
    $title  = trim($title);
    $author = trim($author);
    $cat    = trim($cat) ?: 'General';
    $isbn   = $isbn !== null ? trim($isbn) : '';

    if ($title === '' || $author === '')                      return ['ok' => false, 'error' => 'Title and author are required.'];
    if (mb_strlen($title) > 255 || mb_strlen($author) > 100)  return ['ok' => false, 'error' => 'Title or author is too long.'];
    if (mb_strlen($cat) > 50)                                 return ['ok' => false, 'error' => 'Category is too long.'];
    if ($isbn !== '' && mb_strlen($isbn) > 20)                return ['ok' => false, 'error' => 'ISBN is too long.'];
    $total = max(1, min(1000, $total));

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO books (title, author, category, isbn, total_copies, available_copies)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$title, $author, $cat, $isbn !== '' ? $isbn : null, $total, $total]);
        return ['ok' => true, 'book_id' => (int)$pdo->lastInsertId(), 'title' => $title];
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') return ['ok' => false, 'error' => 'That ISBN is already in use.'];
        throw $e;
    }
}

function remove_book(PDO $pdo, int $bid): array
{
    $chk = $pdo->prepare("SELECT COUNT(*) FROM loans WHERE book_id = ? AND status = 'active'");
    $chk->execute([$bid]);
    if ((int)$chk->fetchColumn() > 0) return ['ok' => false, 'error' => 'This title still has active loans.'];

    $pdo->prepare('DELETE FROM books WHERE book_id = ?')->execute([$bid]);
    return ['ok' => true];
}

/* ---------- users ---------- */
function set_user_role(PDO $pdo, int $uid, string $role): array
{
    if (!in_array($role, ['member', 'staff', 'admin'], true)) return ['ok' => false, 'error' => 'Invalid role.'];

    $chk = $pdo->prepare('SELECT 1 FROM users WHERE user_id = ?');
    $chk->execute([$uid]);
    if (!$chk->fetchColumn()) return ['ok' => false, 'error' => 'User not found.'];

    $pdo->prepare('UPDATE users SET role = ? WHERE user_id = ?')->execute([$role, $uid]);
    return ['ok' => true];
}

/* ---------- reset demo (admin only, called from dashboard) ---------- */
function reset_demo(PDO $pdo): array
{
    $file = __DIR__ . '/../database/bookborrow.sql';
    if (!is_readable($file)) return ['ok' => false, 'error' => 'Seed file not found.'];

    $sql = file_get_contents($file);
    // Strip line comments so split logic does not swallow statements that
    // follow a comment block (e.g. the demo INSERTs).
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);

    $stmts = preg_split('/;\s*(?:\r?\n|$)/', $sql);
    foreach ($stmts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') continue;
        // PDO is already connected to the target DB.
        if (stripos($stmt, 'CREATE DATABASE') === 0) continue;
        if (stripos($stmt, 'USE ') === 0) continue;
        $pdo->exec($stmt);
    }
    return ['ok' => true];
}