<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$db      = get_db();
$user_id = get_current_user_id();

// Fetch user info
$stmt = $db->prepare('SELECT username, weekly_hours FROM users WHERE id = ?');
$stmt->execute([$user_id]);
$user = $stmt->fetch();

$open_entry = get_open_entry($user_id, $db);

// Today's worked time
$today_seconds = get_today_seconds($user_id, $db);

// This week
$this_monday    = date('Y-m-d', strtotime('monday this week'));
$week_seconds   = get_week_seconds($user_id, $db, $this_monday);
$target_seconds = (int)($user['weekly_hours'] * 3600);

// Overtime (completed weeks)
$overtime_seconds = calculate_overtime($user_id, $db);

// Last 5 entries
$stmt = $db->prepare(
    'SELECT * FROM time_entries WHERE user_id = ? ORDER BY clock_in DESC LIMIT 5'
);
$stmt->execute([$user_id]);
$recent_entries = $stmt->fetchAll();

// Last action for undo
$last_action = $_SESSION['last_action'] ?? null;

// Flash message from direct clock-in/out link
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FastTrack – Dashboard</title>
  <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4">

  <!-- Flash message from direct clock-in/out link -->
  <?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
      <?= htmlspecialchars($flash['msg']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
    </div>
  <?php endif; ?>

  <!-- Status Card -->
  <div class="card shadow-sm mb-4 status-card <?= $open_entry ? 'border-success' : 'border-secondary' ?>">
    <div class="card-body text-center">
      <?php if ($open_entry): ?>
        <p class="mb-1 text-success fw-semibold fs-5">
          <span class="me-1">🟢</span> Eingestempelt seit
          <strong><?= date('H:i', strtotime($open_entry['clock_in'])) ?></strong>
          Uhr
          (<?= date('d.m.Y', strtotime($open_entry['clock_in'])) ?>)
        </p>
        <?php if ($open_entry['note']): ?>
          <p class="text-muted small mb-2"><?= htmlspecialchars($open_entry['note']) ?></p>
        <?php endif; ?>
        <?php if (!empty($open_entry['service_type'])): ?>
          <p class="mb-2">
            <span class="badge bg-info text-dark">
              <?= htmlspecialchars(service_type_label($open_entry['service_type'])) ?>
            </span>
          </p>
        <?php endif; ?>
        <button id="clockBtn" class="btn btn-clock btn-danger mt-2" onclick="handleClockOut()">
          Ausstempeln
        </button>
        <div class="mt-3 text-muted small">
          💡 <strong>Direkt-Link:</strong>
          <a href="/clock_out.php" class="font-monospace">/clock_out.php</a>
          – als Lesezeichen oder Startbildschirm-Shortcut speicherbar
        </div>
      <?php else: ?>
        <p class="mb-1 text-secondary fw-semibold fs-5">
          <span class="me-1">⚪</span> Nicht eingestempelt
        </p>
        <div class="mt-2 mb-2" style="max-width:280px; margin:auto;">
          <label for="serviceTypeSelect" class="form-label small mb-1">Einsatzart (optional)</label>
          <select id="serviceTypeSelect" class="form-select form-select-sm">
            <option value="">– Allgemein –</option>
            <option value="haushaltshilfe">Haushaltshilfe</option>
            <option value="dorfhelferin">Dorfhelferin</option>
          </select>
        </div>
        <button id="clockBtn" class="btn btn-clock btn-success mt-2" onclick="handleClockIn()">
          Einstempeln
        </button>
        <div class="mt-3 text-muted small">
          💡 <strong>Direkt-Link:</strong>
          <a href="/clock_in.php" class="font-monospace">/clock_in.php</a>
          – als Lesezeichen oder Startbildschirm-Shortcut speicherbar
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="card text-center h-100 shadow-sm">
        <div class="card-body">
          <div class="stat-label">Heute gearbeitet</div>
          <div class="stat-value"><?= format_duration($today_seconds) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center h-100 shadow-sm">
        <div class="card-body">
          <div class="stat-label">Diese Woche</div>
          <div class="stat-value"><?= format_duration($week_seconds) ?></div>
          <div class="text-muted small">Ziel: <?= format_duration($target_seconds) ?></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center h-100 shadow-sm">
        <div class="card-body">
          <div class="stat-label">
            <?= $overtime_seconds >= 0 ? 'Überstunden' : 'Fehlstunden' ?>
          </div>
          <div class="stat-value <?= $overtime_seconds >= 0 ? 'text-success' : 'text-danger' ?>">
            <?= ($overtime_seconds < 0 ? '−' : '') . format_duration(abs($overtime_seconds)) ?>
          </div>
          <div class="text-muted small">Abgeschl. Wochen</div>
        </div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="card text-center h-100 shadow-sm">
        <div class="card-body">
          <div class="stat-label">Wochenziel</div>
          <div class="stat-value"><?= number_format($user['weekly_hours'], 1) ?>h</div>
          <div class="text-muted small">konfigurierbar</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Recent Entries -->
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <strong>Letzte Einträge</strong>
      <a href="entries.php" class="btn btn-sm btn-outline-primary">Alle anzeigen</a>
    </div>
    <div class="card-body p-0">
      <?php if (empty($recent_entries)): ?>
        <p class="text-muted text-center py-3 mb-0">Noch keine Einträge vorhanden.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th>Datum</th>
                <th>Von</th>
                <th>Bis</th>
                <th>Dauer</th>
                <th>Einsatzart</th>
                <th>Notiz</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recent_entries as $entry): ?>
                <?php
                  $dur = ($entry['clock_out'])
                    ? (strtotime($entry['clock_out']) - strtotime($entry['clock_in']))
                    : null;
                ?>
                <tr>
                  <td><?= date('d.m.Y', strtotime($entry['clock_in'])) ?></td>
                  <td><?= date('H:i', strtotime($entry['clock_in'])) ?></td>
                  <td><?= $entry['clock_out'] ? date('H:i', strtotime($entry['clock_out'])) : '<span class="text-success">Offen</span>' ?></td>
                  <td><?= $dur !== null ? format_duration($dur) : '–' ?></td>
                  <td>
                    <?php if (!empty($entry['service_type'])): ?>
                      <span class="badge bg-info text-dark">
                        <?= htmlspecialchars(service_type_label($entry['service_type'])) ?>
                      </span>
                    <?php else: ?>
                      <span class="text-muted small">–</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-muted small"><?= htmlspecialchars((string)($entry['note'] ?? '')) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div><!-- /container -->

<!-- Undo Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
  <div id="undoToast" class="toast align-items-center border-0 undo-toast" role="alert" aria-live="assertive" data-bs-autohide="false">
    <div class="d-flex">
      <div class="toast-body" id="undoToastBody">
        Aktion rückgängig machen?
      </div>
      <div class="d-flex align-items-center me-2">
        <button type="button" class="btn btn-sm btn-light me-2" onclick="handleUndo()">
          Rückgängig (<span id="undoCountdown">10</span>s)
        </button>
        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Schließen"></button>
      </div>
    </div>
    <div class="progress undo-progress" style="height:4px;">
      <div id="undoProgressBar" class="progress-bar bg-warning" role="progressbar" style="width:100%"></div>
    </div>
  </div>
</div>

<script>
  // Pass PHP last_action to JS (safely encoded)
  const lastAction = <?= json_encode($last_action) ?>;
</script>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<?php if ($last_action): ?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const labels = {
      clock_in:      'Eingestempelt',
      clock_out:     'Ausgestempelt',
      entry_update:  'Eintrag aktualisiert',
      entry_delete:  'Eintrag gelöscht',
    };
    const msg = labels[lastAction?.type] ?? 'Aktion durchgeführt';
    showUndo(msg);
  });
</script>
<?php endif; ?>
</body>
</html>
