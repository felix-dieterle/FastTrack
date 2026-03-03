<?php
/**
 * Database connection singleton.
 * Loads config.php and returns a PDO instance via get_db().
 */

function get_db(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = __DIR__ . '/../config.php';
    if (!file_exists($config)) {
        die(
            '<h2>Konfigurationsdatei fehlt</h2>' .
            '<p>Bitte <code>config.example.php</code> nach <code>config.php</code> kopieren ' .
            'und die Datenbankzugangsdaten eintragen.</p>'
        );
    }
    require_once $config;

    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        DB_HOST,
        DB_NAME,
        DB_CHARSET
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        die(
            '<h2>Datenbankverbindung fehlgeschlagen</h2>' .
            '<p>' . htmlspecialchars($e->getMessage()) . '</p>'
        );
    }

    return $pdo;
}
