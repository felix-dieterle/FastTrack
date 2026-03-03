<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$stmt = $db->prepare('INSERT INTO time_entries (user_id, clock_in) VALUES (?, NOW())');
$stmt->execute([$user_id]);
$entry_id = (int)$db->lastInsertId();

// Fetch the inserted clock_in time
$row = $db->prepare('SELECT clock_in FROM time_entries WHERE id = ?');
$row->execute([$entry_id]);
$entry = $row->fetch();

$_SESSION['last_action'] = [
    'type'     => 'clock_in',
    'entry_id' => $entry_id,
];

json_response([
    'success'  => true,
    'entry_id' => $entry_id,
    'clock_in' => date('H:i', strtotime($entry['clock_in'])),
]);
