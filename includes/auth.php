<?php
declare(strict_types=1);

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    $cfg = config()['app'];
    session_name($cfg['session_name']);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();

    // idle timeout
    $timeout = $cfg['session_idle_timeout'];
    if (!empty($_SESSION['last_seen']) && (time() - (int)$_SESSION['last_seen']) > $timeout) {
        session_unset();
        session_destroy();
        session_start();
        flash('info', 'You were signed out after a period of inactivity.');
    }
    $_SESSION['last_seen'] = time();
}

function current_user(): ?array
{
    static $cache = null;
    if ($cache !== null) return $cache ?: null;
    if (empty($_SESSION['user_id'])) return null;

    $stmt = db()->prepare('SELECT user_id, name, email, role FROM users WHERE user_id = ?');
    $stmt->execute([(int)$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u) {
        logout();
        return null;
    }
    $cache = $u;
    return $u;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) redirect('login.php');
    return $u;
}

function require_role(array $roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        render_403();
        exit;
    }
    return $u;
}

/* ---------- login / register / logout ---------- */

function login(string $email, string $password): array
{
    $email = strtolower(trim($email));

    // Rate limit by IP + email window
    $cfg = config()['app'];
    $pdo = db();
    $cut = date('Y-m-d H:i:s', time() - $cfg['login_window_secs']);
    $rl  = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE successful = 0 AND attempted_at >= ?
           AND (ip = ? OR email = ?)'
    );
    $rl->execute([$cut, client_ip(), $email]);
    if ((int)$rl->fetchColumn() >= $cfg['login_max_attempts']) {
        return ['ok' => false, 'error' => 'Too many failed attempts. Try again in a few minutes.'];
    }

    $stmt = $pdo->prepare('SELECT user_id, name, email, password_hash, role FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $u = $stmt->fetch();

    $success = $u && password_verify($password, $u['password_hash']);
    $pdo->prepare('INSERT INTO login_attempts (ip, email, successful) VALUES (?, ?, ?)')
        ->execute([client_ip(), $email, $success ? 1 : 0]);

    if (!$success) {
        return ['ok' => false, 'error' => 'Email or password is incorrect.'];
    }

    session_regenerate_id(true);
    csrf_rotate();
    $_SESSION['user_id']   = (int)$u['user_id'];
    $_SESSION['last_seen'] = time();
    unset($u['password_hash']);
    return ['ok' => true, 'user' => $u];
}

function register(string $name, string $email, string $password): array
{
    $name  = trim($name);
    $email = strtolower(trim($email));

    if (mb_strlen($name) < 2)                       return ['ok' => false, 'error' => 'Please enter your full name.'];
    if (mb_strlen($name) > 100)                     return ['ok' => false, 'error' => 'Name is too long.'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'error' => 'Please enter a valid email address.'];
    if (strlen($email) > 100)                       return ['ok' => false, 'error' => 'Email is too long.'];
    if (strlen($password) < 6)                      return ['ok' => false, 'error' => 'Password must be at least 6 characters.'];
    if (strlen($password) > 72)                     return ['ok' => false, 'error' => 'Password is too long (max 72).'];

    $pdo = db();
    $chk = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
    $chk->execute([$email]);
    if ($chk->fetchColumn()) return ['ok' => false, 'error' => 'That email is already registered.'];

    // Registration is always member. An admin promotes later.
    $ins = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, "member")');
    $ins->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
    $id = (int)$pdo->lastInsertId();

    session_regenerate_id(true);
    csrf_rotate();
    $_SESSION['user_id']   = $id;
    $_SESSION['last_seen'] = time();
    return ['ok' => true, 'user_id' => $id];
}

/**
 * Sign the current user out.
 *
 * We do NOT call session_destroy() here because that would also destroy the
 * data written by the flash() call that follows on logout.php, and the
 * setcookie deletion the old version performed made the flash unreachable.
 * Instead we clear $_SESSION and rotate the session ID — the old session
 * data is deleted server-side and the browser receives a fresh cookie,
 * which is what actually invalidates the old session.
 */
function logout(): void
{
    csrf_rotate();
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

function client_ip(): string
{
    // Behind a trusted proxy, replace this with the forwarded header.
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}