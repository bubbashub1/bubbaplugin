<?php
declare(strict_types=1);

/**
 * Read-only Leader Hub relationship diagnostic.
 * Admin-session only. Returns schema metadata for the Leader Hub data model.
 * Does not read listing values, user data, or personal information.
 * No DDL, migration, inserts, updates, or deletes.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params(['lifetime'=>0, 'path'=>'/', 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();

function leader_schema_reply(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (empty($_SESSION['bh_admin_authenticated'])) {
    leader_schema_reply(401, ['ok'=>false, 'error'=>'Admin login required.']);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    leader_schema_reply(405, ['ok'=>false, 'error'=>'GET only. Read-only diagnostic.']);
}

try {
    require_once __DIR__ . '/db.php';
    $db = bh_mysql();
    $database = (string)$db->query('SELECT DATABASE()')->fetchColumn();

    $wanted = [
        'bh_users', 'bh_organisers', 'bh_activities',
        'bh_venues', 'bh_activity_venues', 'bh_activity_schedules', 'bh_sessions',
        'bh_booking_slots', 'bh_booking_reservations',
        'bh_directory_businesses', 'bh_directory_business_listings',
        'bh_directory_childcare', 'bh_directory_places', 'bh_directory_school_clubs',
        'bh_directory_events', 'bh_directory_event_occurrences', 'bh_directory_event_listings',
        'bh_directory_record_revisions', 'bh_leader_faqs'
    ];

    $marks = implode(',', array_fill(0, count($wanted), '?'));
    $tableQuery = $db->prepare(
        "SELECT TABLE_NAME, ENGINE
         FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($marks)
         ORDER BY TABLE_NAME"
    );
    $tableQuery->execute($wanted);
    $engines = [];
    foreach ($tableQuery->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $engines[$row['TABLE_NAME']] = $row['ENGINE'];
    }

    $columnQuery = $db->prepare(
        "SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ($marks)
         ORDER BY TABLE_NAME, ORDINAL_POSITION"
    );
    $columnQuery->execute($wanted);
    $tables = [];
    foreach ($wanted as $table) {
        $tables[$table] = [
            'exists' => isset($engines[$table]),
            'engine' => $engines[$table] ?? null,
            'columns' => []
        ];
    }
    foreach ($columnQuery->fetchAll(PDO::FETCH_ASSOC) as $column) {
        $tables[$column['TABLE_NAME']]['columns'][] = [
            'name' => $column['COLUMN_NAME'],
            'type' => $column['COLUMN_TYPE'],
            'nullable' => $column['IS_NULLABLE'] === 'YES',
            'key' => $column['COLUMN_KEY'],
            'extra' => $column['EXTRA']
        ];
    }

    // Exact row totals only: this reveals whether legacy and relationship tables
    // are populated without returning any listing values or personal data.
    $countWanted = [
        'bh_activities', 'bh_venues', 'bh_activity_venues', 'bh_activity_schedules', 'bh_sessions',
        'bh_directory_businesses', 'bh_directory_business_listings',
        'bh_directory_childcare', 'bh_directory_places', 'bh_directory_school_clubs',
        'bh_directory_events', 'bh_directory_event_occurrences', 'bh_directory_event_listings'
    ];
    $rowCounts = [];
    foreach ($countWanted as $table) {
        if (!isset($engines[$table])) {
            $rowCounts[$table] = null;
            continue;
        }
        // Table names come exclusively from the fixed allowlist above.
        $rowCounts[$table] = (int)$db->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
    }

    $fkQuery = $db->prepare(
        "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
         FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME IN ($marks)
           AND REFERENCED_TABLE_NAME IS NOT NULL
         ORDER BY TABLE_NAME, COLUMN_NAME"
    );
    $fkQuery->execute($wanted);
    $foreignKeys = $fkQuery->fetchAll(PDO::FETCH_ASSOC);

    $has = static function (string $table, string $column) use (&$tables): bool {
        foreach ($tables[$table]['columns'] ?? [] as $item) {
            if ($item['name'] === $column) return true;
        }
        return false;
    };

    $relationships = [
        'legacy_venue_belongs_to_activity' => $has('bh_venues', 'activity_id'),
        'activity_venue_join_table' => $has('bh_activity_venues', 'activity_id') && $has('bh_activity_venues', 'venue_id'),
        'activity_schedule_join_table' => $has('bh_activity_schedules', 'activity_id') && $has('bh_activity_schedules', 'venue_id'),
        'sessions_have_activity_id' => $has('bh_sessions', 'activity_id'),
        'sessions_have_venue_id' => $has('bh_sessions', 'venue_id'),
        'sessions_have_schedule_id' => $has('bh_sessions', 'schedule_id'),
        'booking_slots_have_activity_id' => $has('bh_booking_slots', 'activity_id'),
        'booking_slots_have_venue_id' => $has('bh_booking_slots', 'venue_id'),
        'events_have_shared_venue_id' => $has('bh_directory_events', 'venue_id'),
        'event_links_support_activity_id' => $has('bh_directory_event_listings', 'activity_id'),
        'business_links_support_activity_id' => $has('bh_directory_business_listings', 'activity_id'),
    ];

    leader_schema_reply(200, [
        'ok' => true,
        'read_only' => true,
        'database' => $database,
        'checked_at' => date(DATE_ATOM),
        'tables' => $tables,
        'row_counts' => $rowCounts,
        'foreign_keys' => $foreignKeys,
        'relationship_hints' => $relationships,
        'note' => 'Schema metadata and exact table row counts only. No listing values, contact details, user records or other personal data were read. No database changes were made.'
    ]);
} catch (Throwable $e) {
    error_log('Leader Hub schema diagnostic failed: ' . $e->getMessage());
    leader_schema_reply(500, ['ok'=>false, 'error'=>'Leader Hub schema diagnostic failed. Check the PHP error log.']);
}
