<?php
/**
 * Shared helper functions for FastTrack.
 */

require_once __DIR__ . '/db.php';

/**
 * Format a duration in seconds to a human-readable string like "2h 30m".
 */
function format_duration(int $seconds): string {
    if ($seconds < 0) {
        $seconds = 0;
    }
    $hours   = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    if ($hours > 0 && $minutes > 0) {
        return "{$hours}h {$minutes}m";
    }
    if ($hours > 0) {
        return "{$hours}h";
    }
    return "{$minutes}m";
}

/**
 * Return the currently open (clocked-in) entry for a user, or null.
 */
function get_open_entry(int $user_id, PDO $db): ?array {
    $stmt = $db->prepare(
        'SELECT * FROM time_entries WHERE user_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1'
    );
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Return total worked seconds for a given ISO week start date (Monday, 'Y-m-d').
 */
function get_week_seconds(int $user_id, PDO $db, string $week_start): int {
    $week_end = date('Y-m-d', strtotime($week_start . ' +7 days'));
    $stmt = $db->prepare(
        'SELECT SUM(TIMESTAMPDIFF(SECOND, clock_in, clock_out)) AS total
         FROM time_entries
         WHERE user_id = ? AND clock_out IS NOT NULL
           AND clock_in >= ? AND clock_in < ?'
    );
    $stmt->execute([$user_id, $week_start . ' 00:00:00', $week_end . ' 00:00:00']);
    $row = $stmt->fetch();
    return (int)($row['total'] ?? 0);
}

/**
 * Calculate total overtime in seconds (positive = overtime, negative = undertime).
 * Counts every completed calendar week since the user's first entry.
 */
function calculate_overtime(int $user_id, PDO $db): int {
    // Fetch user weekly_hours target
    $stmt = $db->prepare('SELECT weekly_hours FROM users WHERE id = ?');
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    if (!$user) {
        return 0;
    }
    $target_seconds_per_week = (int)($user['weekly_hours'] * 3600);

    // Find the Monday of the first entry week
    $stmt = $db->prepare(
        'SELECT MIN(clock_in) AS first_in FROM time_entries WHERE user_id = ? AND clock_out IS NOT NULL'
    );
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if (empty($row['first_in'])) {
        return 0;
    }

    // Start from the Monday of that week
    $monday = date('Y-m-d', strtotime('monday this week', strtotime($row['first_in'])));
    // Current week's Monday
    $this_monday = date('Y-m-d', strtotime('monday this week'));

    $overtime = 0;
    $cursor   = $monday;
    while ($cursor < $this_monday) {
        $worked   = get_week_seconds($user_id, $db, $cursor);
        $overtime += $worked - $target_seconds_per_week;
        $cursor = date('Y-m-d', strtotime($cursor . ' +7 days'));
    }
    return $overtime;
}

/**
 * Return total worked seconds for today.
 */
function get_today_seconds(int $user_id, PDO $db): int {
    $today = date('Y-m-d');
    $stmt  = $db->prepare(
        'SELECT SUM(TIMESTAMPDIFF(SECOND, clock_in, IFNULL(clock_out, NOW()))) AS total
         FROM time_entries
         WHERE user_id = ? AND DATE(clock_in) = ?'
    );
    $stmt->execute([$user_id, $today]);
    $row = $stmt->fetch();
    return (int)($row['total'] ?? 0);
}

/**
 * Send a JSON response and exit.
 */
function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
