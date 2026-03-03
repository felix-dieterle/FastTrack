<?php
/**
 * Clock-in endpoint.
 *
 * POST (AJAX, CSRF-protected) → JSON response  (used by the dashboard button)
 * GET  (direct link / bookmark) → performs clock-in and redirects to dashboard
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
        $_SESSION['redirect_after_login'] = '/clock_in.php';
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

    if ($open) {
        json_response(['success' => false, 'message' => 'Du bist bereits eingestempelt.']);
    }

    $result = perform_clock_in($user_id, $db);

    json_response([
        'success'  => true,
        'entry_id' => $result['entry_id'],
        'clock_in' => date('H:i', strtotime($result['clock_in'])),
    ]);
}

// GET: direct link / home-screen shortcut
if ($open) {
    $_SESSION['flash'] = ['type' => 'warning', 'msg' => 'Du bist bereits eingestempelt.'];
} else {
    $result = perform_clock_in($user_id, $db);
    $_SESSION['flash'] = [
        'type' => 'success',
        'msg'  => '🟢 Eingestempelt um ' . date('H:i', strtotime($result['clock_in'])) . ' Uhr',
    ];
}

header('Location: /index.php');
exit;
