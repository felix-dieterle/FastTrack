<?php
/**
 * Clock-out endpoint.
 *
 * POST (AJAX, CSRF-protected) → JSON response  (used by the dashboard button)
 * GET  (direct link / bookmark) → performs clock-out and redirects to dashboard
 *
 * Both methods require an active session.
 * Unauthenticated GET requests are sent to login; the intended URL is stored in
 * the session so the user is redirected here automatically after signing in.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

// Ensure the user is logged in
if (!get_current_user_id()) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['redirect_after_login'] = '/clock_out.php';
        header('Location: /login.php');
        exit;
    }
    json_response(['success' => false, 'message' => 'Nicht angemeldet.'], 401);
}

$user_id = get_current_user_id();
$db      = get_db();
$open    = get_open_entry($user_id, $db);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // AJAX call from button – validate CSRF token
    $submitted = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        json_response(['success' => false, 'message' => 'Ungültiges CSRF-Token.'], 403);
    }

    if (!$open) {
        json_response(['success' => false, 'message' => 'Du bist nicht eingestempelt.']);
    }

    $result = perform_clock_out($open['id'], $user_id, $db);

    json_response([
        'success'  => true,
        'duration' => format_duration($result['seconds']),
        'hours'    => round($result['seconds'] / 3600, 2),
    ]);
}

// GET: direct link / home-screen shortcut
if (!$open) {
    $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Du bist nicht eingestempelt.'];
} else {
    $result = perform_clock_out($open['id'], $user_id, $db);
    $_SESSION['flash'] = [
        'type' => 'success',
        'msg'  => '🔴 Ausgestempelt – ' . format_duration($result['seconds']) . ' gearbeitet',
    ];
}

header('Location: /index.php');
exit;
