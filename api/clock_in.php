<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'message' => 'Methode nicht erlaubt.'], 405);
}

$user_id = get_current_user_id();
if (!$user_id) {
    json_response(['success' => false, 'message' => 'Nicht angemeldet.'], 401);
}

$db = get_db();

// Check for already open entry
$open = get_open_entry($user_id, $db);
if ($open) {
    json_response(['success' => false, 'message' => 'Du bist bereits eingestempelt.']);
}

$service_type = sanitize_service_type($_POST['service_type'] ?? null);

$result = perform_clock_in($user_id, $db, $service_type);

json_response([
    'success'      => true,
    'entry_id'     => $result['entry_id'],
    'clock_in'     => date('H:i', strtotime($result['clock_in'])),
    'service_type' => $service_type,
]);
