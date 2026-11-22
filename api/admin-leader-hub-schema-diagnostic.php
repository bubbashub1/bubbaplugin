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


    // Aggregate-only relationship integrity counts (schema guarded).
    $integrity = [];
    $safeCount = static function (string $sql) use ($db): int { return (int)$db->query($sql)->fetchColumn(); };
    $col = static function (string $table, string $column) use (&$tables): bool { foreach ($tables[$table]['columns'] ?? [] as $item) if ($item['name'] === $column) return true; return false; };
    if ($col('bh_venues','activity_id') && $col('bh_activities','id')) $integrity['legacy_venues'] = ['linked_to_activity'=>$safeCount('SELECT COUNT(*) FROM bh_venues WHERE activity_id IS NOT NULL'),'valid_activity_reference'=>$safeCount('SELECT COUNT(*) FROM bh_venues v INNER JOIN bh_activities a ON a.id=v.activity_id'),'orphan_activity_reference'=>$safeCount('SELECT COUNT(*) FROM bh_venues v LEFT JOIN bh_activities a ON a.id=v.activity_id WHERE v.activity_id IS NOT NULL AND a.id IS NULL')];
    if ($col('bh_activity_venues','activity_id') && $col('bh_activity_venues','venue_id')) $integrity['activity_venue_links'] = ['total'=>$safeCount('SELECT COUNT(*) FROM bh_activity_venues'),'valid_pairs'=>$safeCount('SELECT COUNT(*) FROM bh_activity_venues l INNER JOIN bh_activities a ON a.id=l.activity_id INNER JOIN bh_venues v ON v.id=l.venue_id'),'orphan_activity_refs'=>$safeCount('SELECT COUNT(*) FROM bh_activity_venues l LEFT JOIN bh_activities a ON a.id=l.activity_id WHERE a.id IS NULL'),'orphan_venue_refs'=>$safeCount('SELECT COUNT(*) FROM bh_activity_venues l LEFT JOIN bh_venues v ON v.id=l.venue_id WHERE v.id IS NULL'),'duplicate_pairs'=>$safeCount('SELECT COUNT(*) FROM (SELECT activity_id,venue_id FROM bh_activity_venues GROUP BY activity_id,venue_id HAVING COUNT(*)>1) d'];
    if ($col('bh_activity_schedules','activity_id')) { $integrity['activity_schedules']=['total'=>$safeCount('SELECT COUNT(*) FROM bh_activity_schedules'),'valid_activity_refs'=>$safeCount('SELECT COUNT(*) FROM bh_activity_schedules s INNER JOIN bh_activities a ON a.id=s.activity_id'),'orphan_activity_refs'=>$safeCount('SELECT COUNT(*) FROM bh_activity_schedules s LEFT JOIN bh_activities a ON a.id=s.activity_id WHERE a.id IS NULL')]; if ($col('bh_activity_schedules','venue_id')) { $integrity['activity_schedules']['valid_venue_refs']=$safeCount('SELECT COUNT(*) FROM bh_activity_schedules s INNER JOIN bh_venues v ON v.id=s.venue_id WHERE s.venue_id IS NOT NULL'); $integrity['activity_schedules']['orphan_venue_refs']=$safeCount('SELECT COUNT(*) FROM bh_activity_schedules s LEFT JOIN bh_venues v ON v.id=s.venue_id WHERE s.venue_id IS NOT NULL AND v.id IS NULL'); } }
    if ($col('bh_activities','weekly_schedule')) $integrity['activities_with_weekly_schedule']=$safeCount("SELECT COUNT(*) FROM bh_activities WHERE weekly_schedule IS NOT NULL AND TRIM(weekly_schedule)<>'' AND TRIM(weekly_schedule)<>'[]' AND TRIM(weekly_schedule)<>'{}'");
    if ($col('bh_sessions','venue_id')) $integrity['sessions']=['total'=>$safeCount('SELECT COUNT(*) FROM bh_sessions'),'valid_venue_refs'=>$safeCount('SELECT COUNT(*) FROM bh_sessions s INNER JOIN bh_venues v ON v.id=s.venue_id'),'orphan_venue_refs'=>$safeCount('SELECT COUNT(*) FROM bh_sessions s LEFT JOIN bh_venues v ON v.id=s.venue_id WHERE s.venue_id IS NOT NULL AND v.id IS NULL'];
    if ($col('bh_directory_business_listings','business_id') && $col('bh_directory_business_listings','activity_id')) $integrity['business_activity_links']=['total'=>$safeCount('SELECT COUNT(*) FROM bh_directory_business_listings'),'valid_pairs'=>$safeCount('SELECT COUNT(*) FROM bh_directory_business_listings l INNER JOIN bh_directory_businesses b ON b.id=l.business_id INNER JOIN bh_activities a ON a.id=l.activity_id'),'orphan_business_refs'=>$safeCount('SELECT COUNT(*) FROM bh_directory_business_listings l LEFT JOIN bh_directory_businesses b ON b.id=l.business_id WHERE b.id IS NULL'),'orphan_activity_refs'=>$safeCount('SELECT COUNT(*) FROM bh_directory_business_listings l LEFT JOIN bh_activities a ON a.id=l.activity_id WHERE a.id IS NULL'),'duplicate_pairs'=>$safeCount('SELECT COUNT(*) FROM (SELECT business_id,activity_id FROM bh_directory_business_listings GROUP BY business_id,activity_id HAVING COUNT(*)>1) d'];

    leader_schema_reply(200, [
        'ok' => true,
        'read_only' => true,
        'database' => $database,
        'checked_at' => date(DATE_ATOM),
        'tables' => $tables,
        'row_counts' => $rowCounts,
        'relationship_integrity_counts' => $integrity,
        'foreign_keys' => $foreignKeys,
        'relationship_hints' => $relationships,
        'note' => 'Schema metadata and exact table row counts only. No listing values, contact details, user records or other personal data were read. No database changes were made.'
    ]);
} catch (Throwable $e) {
    error_log('Leader Hub schema diagnostic failed: ' . $e->getMessage());
    leader_schema_reply(500, ['ok'=>false, 'error'=>'Leader Hub schema diagnostic failed. Check the PHP error log.']);
}
