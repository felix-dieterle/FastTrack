<?php
require_once __DIR__ . '/includes/auth.php';

$error   = '';
$success = '';

check_remember_me();
if (get_current_user_id()) {
    header('Location: /index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Bitte alle Felder ausfüllen.';
    } elseif (strlen($username) < 3 || strlen($username) > 50) {
        $error = 'Benutzername muss zwischen 3 und 50 Zeichen lang sein.';
    } elseif (strlen($password) < 6) {
        $error = 'Passwort muss mindestens 6 Zeichen lang sein.';
    } elseif ($password !== $password2) {
        $error = 'Die Passwörter stimmen nicht überein.';
    } else {
        $db   = get_db();
        $chk  = $db->prepare('SELECT id FROM users WHERE username = ?');
        $chk->execute([$username]);
        if ($chk->fetch()) {
            $error = 'Dieser Benutzername ist bereits vergeben.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins  = $db->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)');
            $ins->execute([$username, $hash]);
            $success = 'Konto erstellt! Du kannst dich jetzt <a href="/login.php">anmelden</a>.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FastTrack – Registrieren</title>
  <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light d-flex align-items-center min-vh-100">
<div class="container">
  <div class="row justify-content-center">
    <div class="col-12 col-sm-8 col-md-5 col-lg-4">
      <div class="card shadow-sm">
        <div class="card-body p-4">
          <h1 class="h4 mb-4 text-center fw-bold">⏱ FastTrack</h1>
          <h2 class="h6 text-center text-muted mb-4">Registrieren</h2>

          <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <?php if ($success): ?>
            <div class="alert alert-success py-2"><?= $success ?></div>
          <?php endif; ?>

          <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-3">
              <label for="username" class="form-label">Benutzername</label>
              <input type="text" id="username" name="username" class="form-control"
                     value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                     minlength="3" maxlength="50" autofocus required>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Passwort</label>
              <input type="password" id="password" name="password" class="form-control"
                     minlength="6" required>
            </div>
            <div class="mb-3">
              <label for="password2" class="form-label">Passwort bestätigen</label>
              <input type="password" id="password2" name="password2" class="form-control"
                     minlength="6" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Konto erstellen</button>
          </form>

          <hr>
          <p class="text-center small mb-0">
            Bereits ein Konto? <a href="/login.php">Anmelden</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
</body>
</html>
