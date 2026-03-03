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

$entry_id = (int)($_POST['entry_id'] ?? 0);
if (!$entry_id) {
    json_response(['success' => false, 'message' => 'Ungültige Eintrag-ID.']);
}

$db = get_db();

// Verify ownership and fetch for undo
$chk = $db->prepare('SELECT * FROM time_entries WHERE id = ? AND user_id = ?');
$chk->execute([$entry_id, $user_id]);
$entry = $chk->fetch();
if (!$entry) {
    json_response(['success' => false, 'message' => 'Eintrag nicht gefunden.'], 404);
}

// Store for undo
$_SESSION['last_action'] = [
    'type'  => 'entry_delete',
    'entry' => $entry,
];

$del = $db->prepare('DELETE FROM time_entries WHERE id = ? AND user_id = ?');
$del->execute([$entry_id, $user_id]);

json_response(['success' => true]);
