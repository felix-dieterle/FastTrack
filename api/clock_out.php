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

$db   = get_db();
$open = get_open_entry($user_id, $db);
if (!$open) {
    json_response(['success' => false, 'message' => 'Du bist nicht eingestempelt.']);
}

$upd = $db->prepare('UPDATE time_entries SET clock_out = NOW() WHERE id = ? AND user_id = ?');
$upd->execute([$open['id'], $user_id]);

// Fetch updated entry
$row = $db->prepare('SELECT clock_in, clock_out FROM time_entries WHERE id = ?');
$row->execute([$open['id']]);
$entry = $row->fetch();

$seconds = strtotime($entry['clock_out']) - strtotime($entry['clock_in']);
$hours   = round($seconds / 3600, 2);

$_SESSION['last_action'] = [
    'type'     => 'clock_out',
    'entry_id' => $open['id'],
];

json_response([
    'success'  => true,
    'duration' => format_duration($seconds),
    'hours'    => $hours,
]);
