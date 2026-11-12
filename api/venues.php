<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require __DIR__ . '/db.php';
    $db = bh_mysql();

    // Older Bubba Hub databases link activities to venues in different ways.
    // Detect the installed schema instead of assuming bh_venues.activity_id exists.
    $hasColumn = static function (string $table, string $column) use ($db): bool {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
        );
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    };

    $hasTable = static function (string $table) use ($db): bool {
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    };

    if ($hasColumn('bh_venues', 'activity_id')) {
        $activityCount = 'COUNT(DISTINCT v.activity_id)';
    } elseif ($hasTable('bh_activity_venues')) {
        $activityCount = '(
            SELECT COUNT(DISTINCT av.activity_id)
            FROM bh_activity_venues av
            WHERE av.venue_id = v.id
        )';
    } elseif ($hasTable('bh_activity_schedules')) {
        $activityCount = '(
            SELECT COUNT(DISTINCT s.activity_id)
            FROM bh_activity_schedules s
            WHERE s.venue_id = v.id
        )';
    } elseif ($hasTable('bh_sessions')) {
        $activityCount = '(
            SELECT COUNT(DISTINCT s.activity_id)
            FROM bh_sessions s
            WHERE s.venue_id = v.id
        )';
    } else {
        $activityCount = '0';
    }

    $sql = "
        SELECT
            v.id,
            v.venue_name AS name,
            v.address,
            v.town,
            v.region,
            v.postcode,
            v.latitude,
            v.longitude,
            {$activityCount} AS activity_count
        FROM bh_venues v
        WHERE TRIM(COALESCE(v.venue_name, '')) <> ''
        ORDER BY v.venue_name ASC, v.id ASC
    ";

    $rows = $db->query($sql)->fetchAll();

    echo json_encode(
        ['ok' => true, 'data' => $rows],
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
    );
} catch (Throwable $e) {
    error_log('Bubba Hub venues API failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(
        ['ok' => false, 'error' => 'Unable to load venues. Please try again.'],
        JSON_UNESCAPED_SLASHES
    );
}
