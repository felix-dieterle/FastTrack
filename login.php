<?php
require_once __DIR__ . '/includes/auth.php';

// Start session if needed (auth.php already calls session_start)
$error   = '';
$success = '';

// If already logged in, redirect
check_remember_me();
if (get_current_user_id()) {
    header('Location: /index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username    = trim($_POST['username'] ?? '');
    $password    = $_POST['password'] ?? '';
    $remember_me = !empty($_POST['remember_me']);

    if ($username === '' || $password === '') {
        $error = 'Bitte Benutzername und Passwort eingeben.';
    } else {
        $db   = get_db();
        $stmt = $db->prepare('SELECT id, password_hash FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            login_user((int)$user['id']);
            if ($remember_me) {
                set_remember_me((int)$user['id']);
            }
            $redirect = $_SESSION['redirect_after_login'] ?? '/index.php';
            unset($_SESSION['redirect_after_login']);
            // Whitelist allowed post-login redirects to prevent open-redirect attacks
            $allowed_redirects = ['/clock_in.php', '/clock_out.php'];
            if (!in_array($redirect, $allowed_redirects, true)) {
                $redirect = '/index.php';
            }
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = 'Ungültiger Benutzername oder Passwort.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FastTrack – Anmelden</title>
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
          <h2 class="h6 text-center text-muted mb-4">Anmelden</h2>

          <?php if ($error): ?>
            <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>

          <form method="post" novalidate>
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div class="mb-3">
              <label for="username" class="form-label">Benutzername</label>
              <input type="text" id="username" name="username" class="form-control"
                     value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                     autofocus required>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Passwort</label>
              <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" class="form-check-input" id="remember_me" name="remember_me"
                     <?= !empty($_POST['remember_me']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="remember_me">Angemeldet bleiben (30 Tage)</label>
            </div>
            <button type="submit" class="btn btn-primary w-100">Anmelden</button>
          </form>

          <hr>
          <p class="text-center small mb-0">
            Noch kein Konto? <a href="/register.php">Registrieren</a>
          </p>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
</body>
</html>
