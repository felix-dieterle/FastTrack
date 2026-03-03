<?php
require_once __DIR__ . '/includes/auth.php';

require_login();

$db      = get_db();
$user_id = get_current_user_id();

$stmt = $db->prepare('SELECT username, weekly_hours FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$errors   = [];
$success  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // --- Update weekly hours ---
    if (isset($_POST['update_hours'])) {
        $weekly_hours = (float)str_replace(',', '.', $_POST['weekly_hours'] ?? '');
        if ($weekly_hours < 0.1 || $weekly_hours > 168) {
            $errors[] = 'Wochenstunden müssen zwischen 0.1 und 168 liegen.';
        } else {
            $upd = $db->prepare('UPDATE users SET weekly_hours = ? WHERE id = ?');
            $upd->execute([$weekly_hours, $user_id]);
            $user['weekly_hours'] = $weekly_hours;
            $success = 'Wochenstunden-Ziel gespeichert.';
        }
    }

    // --- Change password ---
    if (isset($_POST['change_password'])) {
        $current  = $_POST['current_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if ($current === '' || $new_pass === '' || $confirm === '') {
            $errors[] = 'Bitte alle Passwortfelder ausfüllen.';
        } elseif (strlen($new_pass) < 6) {
            $errors[] = 'Neues Passwort muss mindestens 6 Zeichen lang sein.';
        } elseif ($new_pass !== $confirm) {
            $errors[] = 'Die neuen Passwörter stimmen nicht überein.';
        } else {
            $chk = $db->prepare('SELECT password_hash FROM users WHERE id = ?');
            $chk->execute([$user_id]);
            $row = $chk->fetch();
            if (!password_verify($current, $row['password_hash'])) {
                $errors[] = 'Das aktuelle Passwort ist falsch.';
            } else {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $upd  = $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $upd->execute([$hash, $user_id]);
                $success = 'Passwort erfolgreich geändert.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FastTrack – Einstellungen</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4">
  <h1 class="h4 mb-4">Einstellungen</h1>

  <?php if ($errors): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- Weekly hours -->
  <div class="card shadow-sm mb-4">
    <div class="card-header"><strong>Wochenstunden-Ziel</strong></div>
    <div class="card-body">
      <form method="post" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="update_hours" value="1">
        <div class="mb-3">
          <label for="weekly_hours" class="form-label">Stunden pro Woche</label>
          <div class="input-group" style="max-width:200px;">
            <input type="number" id="weekly_hours" name="weekly_hours" class="form-control"
                   value="<?= htmlspecialchars((string)$user['weekly_hours']) ?>"
                   min="0.1" max="168" step="0.5" required>
            <span class="input-group-text">h</span>
          </div>
          <div class="form-text">Standard: 40h (Vollzeit)</div>
        </div>
        <button type="submit" class="btn btn-primary">Speichern</button>
      </form>
    </div>
  </div>

  <!-- Change password -->
  <div class="card shadow-sm mb-4">
    <div class="card-header"><strong>Passwort ändern</strong></div>
    <div class="card-body">
      <form method="post" novalidate style="max-width:400px;">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="change_password" value="1">
        <div class="mb-3">
          <label for="current_password" class="form-label">Aktuelles Passwort</label>
          <input type="password" id="current_password" name="current_password" class="form-control" required>
        </div>
        <div class="mb-3">
          <label for="new_password" class="form-label">Neues Passwort</label>
          <input type="password" id="new_password" name="new_password" class="form-control" minlength="6" required>
        </div>
        <div class="mb-3">
          <label for="confirm_password" class="form-label">Neues Passwort bestätigen</label>
          <input type="password" id="confirm_password" name="confirm_password" class="form-control" minlength="6" required>
        </div>
        <button type="submit" class="btn btn-primary">Passwort ändern</button>
      </form>
    </div>
  </div>

  <!-- Account info -->
  <div class="card shadow-sm">
    <div class="card-header"><strong>Konto-Informationen</strong></div>
    <div class="card-body">
      <p class="mb-1"><strong>Benutzername:</strong> <?= htmlspecialchars($user['username']) ?></p>
    </div>
  </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
