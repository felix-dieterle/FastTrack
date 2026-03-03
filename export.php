<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$db      = get_db();
$user_id = get_current_user_id();

$from = $_GET['from'] ?? '';
$to   = $_GET['to']   ?? '';

// Build query
$params = [$user_id];
$sql    = 'SELECT clock_in, clock_out, note FROM time_entries WHERE user_id = ?';

if ($from !== '') {
    $sql      .= ' AND clock_in >= ?';
    $params[]  = $from . ' 00:00:00';
}
if ($to !== '') {
    $sql      .= ' AND clock_in <= ?';
    $params[]  = $to . ' 23:59:59';
}
$sql .= ' ORDER BY clock_in ASC';

if (isset($_GET['download'])) {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $entries = $stmt->fetchAll();

    $filename = 'fasttrack_export_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache');

    $out = fopen('php://output', 'w');
    // BOM for Excel UTF-8
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, ['Datum', 'Einstempeln', 'Ausstempeln', 'Dauer (Stunden)', 'Notiz'], ';');

    foreach ($entries as $entry) {
        $date  = date('d.m.Y', strtotime($entry['clock_in']));
        $cin   = date('H:i', strtotime($entry['clock_in']));
        $cout  = $entry['clock_out'] ? date('H:i', strtotime($entry['clock_out'])) : '';
        $hours = '';
        if ($entry['clock_out']) {
            $secs  = strtotime($entry['clock_out']) - strtotime($entry['clock_in']);
            $hours = number_format($secs / 3600, 2, ',', '');
        }
        fputcsv($out, [$date, $cin, $cout, $hours, $entry['note'] ?? ''], ';');
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FastTrack – Exportieren</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4">
  <h1 class="h4 mb-4">CSV Exportieren</h1>

  <div class="card shadow-sm" style="max-width:500px;">
    <div class="card-body">
      <p class="text-muted small mb-3">
        Optionalen Zeitraum angeben oder leer lassen für alle Einträge.
      </p>
      <form method="get" novalidate>
        <div class="mb-3 row align-items-center">
          <label for="from" class="col-sm-3 col-form-label">Von</label>
          <div class="col-sm-9">
            <input type="date" id="from" name="from" class="form-control"
                   value="<?= htmlspecialchars($from) ?>">
          </div>
        </div>
        <div class="mb-3 row align-items-center">
          <label for="to" class="col-sm-3 col-form-label">Bis</label>
          <div class="col-sm-9">
            <input type="date" id="to" name="to" class="form-control"
                   value="<?= htmlspecialchars($to) ?>">
          </div>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-outline-secondary">Vorschau</button>
          <button type="submit" name="download" value="1" class="btn btn-primary">
            CSV herunterladen
          </button>
        </div>
      </form>
    </div>
  </div>

  <?php if (!isset($_GET['download']) && (isset($_GET['from']) || isset($_GET['to']))): ?>
    <?php
      $stmt = $db->prepare($sql);
      $stmt->execute($params);
      $preview = $stmt->fetchAll();
    ?>
    <div class="card shadow-sm mt-4">
      <div class="card-header">Vorschau (<?= count($preview) ?> Einträge)</div>
      <div class="card-body p-0">
        <?php if (empty($preview)): ?>
          <p class="text-muted text-center py-3 mb-0">Keine Einträge im gewählten Zeitraum.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
              <thead class="table-light">
                <tr>
                  <th>Datum</th>
                  <th>Einstempeln</th>
                  <th>Ausstempeln</th>
                  <th>Dauer (h)</th>
                  <th>Notiz</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($preview as $entry): ?>
                  <?php
                    $secs  = $entry['clock_out'] ? (strtotime($entry['clock_out']) - strtotime($entry['clock_in'])) : null;
                    $hours = $secs !== null ? number_format($secs / 3600, 2, ',', '') : '–';
                  ?>
                  <tr>
                    <td><?= date('d.m.Y', strtotime($entry['clock_in'])) ?></td>
                    <td><?= date('H:i', strtotime($entry['clock_in'])) ?></td>
                    <td><?= $entry['clock_out'] ? date('H:i', strtotime($entry['clock_out'])) : '–' ?></td>
                    <td><?= $hours ?></td>
                    <td class="text-muted small"><?= htmlspecialchars((string)($entry['note'] ?? '')) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
