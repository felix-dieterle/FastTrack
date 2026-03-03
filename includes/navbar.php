<?php
/**
 * Shared navigation bar – included by every page.
 */
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navbar navbar-expand-md navbar-dark bg-primary">
  <div class="container">
    <a class="navbar-brand fw-bold" href="/index.php">⏱ FastTrack</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'index.php' ? 'active' : '' ?>" href="/index.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'entries.php' ? 'active' : '' ?>" href="/entries.php">Einträge</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'export.php' ? 'active' : '' ?>" href="/export.php">Exportieren</a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $current_page === 'settings.php' ? 'active' : '' ?>" href="/settings.php">Einstellungen</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="/logout.php">Abmelden</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
