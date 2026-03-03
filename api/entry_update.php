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

$entry_id  = (int)($_POST['entry_id'] ?? 0);
$clock_in  = trim($_POST['clock_in']  ?? '');
$clock_out = trim($_POST['clock_out'] ?? '');
$note      = trim($_POST['note']      ?? '');

if (!$entry_id || $clock_in === '') {
    json_response(['success' => false, 'message' => 'Ungültige Eingabedaten.']);
}

// Validate datetime formats (HTML datetime-local: YYYY-MM-DDTHH:MM)
$cin_ts  = strtotime($clock_in);
$cout_ts = ($clock_out !== '') ? strtotime($clock_out) : null;

if (!$cin_ts) {
    json_response(['success' => false, 'message' => 'Ungültiges Einstempelzeit-Format.']);
}
if ($clock_out !== '' && !$cout_ts) {
    json_response(['success' => false, 'message' => 'Ungültiges Ausstempelzeit-Format.']);
}
if ($cout_ts && $cout_ts <= $cin_ts) {
    json_response(['success' => false, 'message' => 'Ausstempelzeit muss nach der Einstempelzeit liegen.']);
}

$db = get_db();

// Verify ownership
$chk = $db->prepare('SELECT * FROM time_entries WHERE id = ? AND user_id = ?');
$chk->execute([$entry_id, $user_id]);
$old = $chk->fetch();
if (!$old) {
    json_response(['success' => false, 'message' => 'Eintrag nicht gefunden.'], 404);
}

// Store old values for undo
$_SESSION['last_action'] = [
    'type'     => 'entry_update',
    'entry_id' => $entry_id,
    'old'      => [
        'clock_in'  => $old['clock_in'],
        'clock_out' => $old['clock_out'],
        'note'      => $old['note'],
    ],
];

$cin_db  = date('Y-m-d H:i:s', $cin_ts);
$cout_db = $cout_ts ? date('Y-m-d H:i:s', $cout_ts) : null;

$upd = $db->prepare(
    'UPDATE time_entries SET clock_in = ?, clock_out = ?, note = ? WHERE id = ? AND user_id = ?'
);
$upd->execute([$cin_db, $cout_db, $note !== '' ? $note : null, $entry_id, $user_id]);

json_response(['success' => true]);
