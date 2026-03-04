<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

require_login();

$db      = get_db();
$user_id = get_current_user_id();

$per_page = 20;
$page     = max(1, (int)($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

// Count total entries
$count_stmt = $db->prepare('SELECT COUNT(*) FROM time_entries WHERE user_id = ?');
$count_stmt->execute([$user_id]);
$total      = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total / $per_page);

// Fetch page
$stmt = $db->prepare(
    'SELECT * FROM time_entries WHERE user_id = ? ORDER BY clock_in DESC LIMIT ? OFFSET ?'
);
$stmt->execute([$user_id, $per_page, $offset]);
$entries = $stmt->fetchAll();

// Last action for undo
$last_action = $_SESSION['last_action'] ?? null;
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>FastTrack – Einträge</title>
  <link rel="stylesheet" href="assets/vendor/bootstrap.min.css">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-light">

<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h4 mb-0">Einträge</h1>
    <a href="export.php" class="btn btn-outline-secondary btn-sm">CSV exportieren</a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body p-0">
      <?php if (empty($entries)): ?>
        <p class="text-muted text-center py-4 mb-0">Noch keine Einträge vorhanden.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover mb-0" id="entriesTable">
            <thead class="table-light">
              <tr>
                <th>Datum</th>
                <th>Einstempeln</th>
                <th>Ausstempeln</th>
                <th>Dauer</th>
                <th>Einsatzart</th>
                <th>Notiz</th>
                <th class="text-end">Aktionen</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($entries as $entry): ?>
                <?php
                  $dur = ($entry['clock_out'])
                    ? (strtotime($entry['clock_out']) - strtotime($entry['clock_in']))
                    : null;
                  $cin_val  = date('Y-m-d\TH:i', strtotime($entry['clock_in']));
                  $cout_val = $entry['clock_out'] ? date('Y-m-d\TH:i', strtotime($entry['clock_out'])) : '';
                ?>
                <!-- View row -->
                <tr id="row-<?= $entry['id'] ?>" class="entry-row">
                  <td><?= date('d.m.Y', strtotime($entry['clock_in'])) ?></td>
                  <td><?= date('H:i', strtotime($entry['clock_in'])) ?></td>
                  <td><?= $entry['clock_out'] ? date('H:i', strtotime($entry['clock_out'])) : '<span class="text-success fw-semibold">Offen</span>' ?></td>
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
                  <td class="text-end">
                    <button class="btn btn-sm btn-outline-primary me-1" onclick="editEntry(<?= $entry['id'] ?>)">Bearbeiten</button>
                    <button class="btn btn-sm btn-outline-danger" onclick="confirmDelete(<?= $entry['id'] ?>)">Löschen</button>
                  </td>
                </tr>
                <!-- Edit row (hidden by default) -->
                <tr id="edit-row-<?= $entry['id'] ?>" class="edit-row d-none table-warning">
                  <td colspan="7">
                    <form class="row g-2 align-items-end py-1" onsubmit="saveEntry(event, <?= $entry['id'] ?>)">
                      <div class="col-12 col-md-3">
                        <label class="form-label small mb-1">Einstempeln</label>
                        <input type="datetime-local" class="form-control form-control-sm" name="clock_in"
                               value="<?= $cin_val ?>" required>
                      </div>
                      <div class="col-12 col-md-3">
                        <label class="form-label small mb-1">Ausstempeln</label>
                        <input type="datetime-local" class="form-control form-control-sm" name="clock_out"
                               value="<?= $cout_val ?>">
                      </div>
                      <div class="col-12 col-md-2">
                        <label class="form-label small mb-1">Einsatzart</label>
                        <select class="form-select form-select-sm" name="service_type">
                          <option value="" <?= empty($entry['service_type']) ? 'selected' : '' ?>>– Allgemein –</option>
                          <option value="haushaltshilfe" <?= ($entry['service_type'] ?? '') === 'haushaltshilfe' ? 'selected' : '' ?>>Haushaltshilfe</option>
                          <option value="dorfhelferin" <?= ($entry['service_type'] ?? '') === 'dorfhelferin' ? 'selected' : '' ?>>Dorfhelferin</option>
                        </select>
                      </div>
                      <div class="col-12 col-md-2">
                        <label class="form-label small mb-1">Notiz</label>
                        <input type="text" class="form-control form-control-sm" name="note"
                               value="<?= htmlspecialchars((string)($entry['note'] ?? '')) ?>"
                               maxlength="500">
                      </div>
                      <div class="col-12 col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-sm btn-primary">Speichern</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="cancelEdit(<?= $entry['id'] ?>)">Abbrechen</button>
                      </div>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
          <nav class="p-3">
            <ul class="pagination pagination-sm mb-0 justify-content-center">
              <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                  <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                </li>
              <?php endfor; ?>
            </ul>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

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
  const lastAction = <?= json_encode($last_action) ?>;
</script>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<script src="assets/js/app.js"></script>
<?php if ($last_action): ?>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    const labels = {
      entry_update: 'Eintrag aktualisiert',
      entry_delete: 'Eintrag gelöscht',
    };
    const msg = labels[lastAction?.type] ?? 'Aktion durchgeführt';
    showUndo(msg);
  });
</script>
<?php endif; ?>
</body>
</html>
