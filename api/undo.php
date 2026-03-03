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

$last_action = $_SESSION['last_action'] ?? null;
if (!$last_action) {
    json_response(['success' => false, 'message' => 'Keine rückgängig machbare Aktion vorhanden.']);
}

$db   = get_db();
$type = $last_action['type'];

switch ($type) {
    case 'clock_in':
        // Delete the entry (only if no clock_out set, to be safe)
        $del = $db->prepare(
            'DELETE FROM time_entries WHERE id = ? AND user_id = ? AND clock_out IS NULL'
        );
        $del->execute([$last_action['entry_id'], $user_id]);
        $msg = 'Einstempeln rückgängig gemacht.';
        break;

    case 'clock_out':
        $upd = $db->prepare(
            'UPDATE time_entries SET clock_out = NULL WHERE id = ? AND user_id = ?'
        );
        $upd->execute([$last_action['entry_id'], $user_id]);
        $msg = 'Ausstempeln rückgängig gemacht.';
        break;

    case 'entry_update':
        $upd = $db->prepare(
            'UPDATE time_entries SET clock_in = ?, clock_out = ?, note = ? WHERE id = ? AND user_id = ?'
        );
        $upd->execute([
            $last_action['old']['clock_in'],
            $last_action['old']['clock_out'],
            $last_action['old']['note'],
            $last_action['entry_id'],
            $user_id,
        ]);
        $msg = 'Eintrag auf vorherigen Stand zurückgesetzt.';
        break;

    case 'entry_delete':
        $ins = $db->prepare(
            'INSERT INTO time_entries (id, user_id, clock_in, clock_out, note, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $last_action['entry']['id'],
            $user_id,
            $last_action['entry']['clock_in'],
            $last_action['entry']['clock_out'],
            $last_action['entry']['note'],
            $last_action['entry']['created_at'],
        ]);
        $msg = 'Gelöschter Eintrag wiederhergestellt.';
        break;

    default:
        json_response(['success' => false, 'message' => 'Unbekannte Aktion.']);
}

unset($_SESSION['last_action']);

json_response(['success' => true, 'message' => $msg]);
