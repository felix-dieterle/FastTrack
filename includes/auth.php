<?php
/**
 * Authentication helpers: login, logout, remember-me, CSRF.
 */

require_once __DIR__ . '/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Redirect to login if not authenticated. */
function require_login(): void {
    check_remember_me();
    if (empty($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
}

/** Set session variables for a logged-in user. */
function login_user(int $user_id): void {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
}

/** Destroy session and remove remember-me token. */
function logout_user(): void {
    if (!empty($_COOKIE['remember_token'])) {
        $db = get_db();
        $stmt = $db->prepare('DELETE FROM remember_tokens WHERE token = ?');
        $stmt->execute([$_COOKIE['remember_token']]);
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
    }
    $_SESSION = [];
    session_destroy();
}

/**
 * Check remember-me cookie and restore session if valid token found.
 * Rotates token on each use to prevent replay attacks.
 */
function check_remember_me(): void {
    if (!empty($_SESSION['user_id']) || empty($_COOKIE['remember_token'])) {
        return;
    }
    $db  = get_db();
    $raw = $_COOKIE['remember_token'];
    $stmt = $db->prepare(
        'SELECT id, user_id FROM remember_tokens WHERE token = ? AND expires_at > NOW()'
    );
    $stmt->execute([$raw]);
    $row = $stmt->fetch();
    if (!$row) {
        setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        return;
    }
    // Rotate token
    $new_token = bin2hex(random_bytes(32));
    $expires   = date('Y-m-d H:i:s', strtotime('+30 days'));
    $upd = $db->prepare('UPDATE remember_tokens SET token = ?, expires_at = ? WHERE id = ?');
    $upd->execute([$new_token, $expires, $row['id']]);
    setcookie('remember_token', $new_token, strtotime('+30 days'), '/', '', false, true);

    login_user((int)$row['user_id']);
}

/** Return the current user ID or null. */
function get_current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** Generate (or return existing) CSRF token for the session. */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** Verify the submitted CSRF token matches the session token. */
function verify_csrf(): void {
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        http_response_code(403);
        die('Ungültiges CSRF-Token.');
    }
}

/**
 * Set a remember-me cookie and store the token in DB.
 */
function set_remember_me(int $user_id): void {
    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
    $db      = get_db();
    $stmt    = $db->prepare(
        'INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)'
    );
    $stmt->execute([$user_id, $token, $expires]);
    setcookie('remember_token', $token, strtotime('+30 days'), '/', '', false, true);
}
