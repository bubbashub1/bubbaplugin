<?php
declare(strict_types=1);
/**
 * Read-only Bubba Hub schema diagnostic.
 * Admin-session only. Reports table and column names, never row data.
 * No DDL, migrations, writes or listing-content reads.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X--Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function bh_schema_reply(int $status,array $data): never {
    http_response_code($status);
    echo json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if(empty($_SESSION['bh_admin_authenticated'])) bh_schema_reply(401,['ok'=>false,'error'=>'Admin login required.']);
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET') bh_schema_reply(405,['ok'=>false,'error'=>'GET only. Read-only diagnostic.']);
try {
    require_once __DIR__.'/db.php';
    $db=bh_mysql();
    $database=(string)$db->query('SELECT DATABASE()')->fetchColumn();
    $tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $wanted=[
        'bh_activities','bh_activity_venues','bh_venues','bh_organisers',
        'bh_listing_checker_settings','bh_listing_discovery_terms',
        'bh_listing_check_runs','bh_listing_check_queue','bh_listing_check_findings'
    ];
    $report=[];
    foreach($wanted as $table) {
        if(!in_array($table,$tables,true)) {
            $report[$table]=['exists'=>false,'columns'=>[]];
            continue;
        }
        $stmt=$db->query('SHOW COLUMNS FROM `'.$table.'`');
        $columns=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $column) {
            $columns[]=[
                'name'=>(string)$column['Field'],
                'type'=>(string)$column['Type'],
                'nullable'=>(string)$column['Null']==='YES',
                'key'=>(string)$column['Key']
            ];
        }
        $report[$table]=['exists'=>true,'columns'=>$columns];
    }
    $has=static function(string $table,string $column) use($report): bool {
        if(empty($report[$table]['exists'])) return false;
        foreach($report[$table]['columns'] as $item) if($item['name']===$column) return true;
        return false;
    };
    $relations=[
        'activity_to_venue_link_table'=>$has('bh_activity_venues','activity_id')&&$has('bh_activity_venues','venue_id'),
        'venue_table_has_id'=>$has('bh_venues','id'),
        'venue_table_has_address'=>$has('bh_venues','address'),
        'venue_table_has_town'=>$has('bh_venues','town'),
        'venue_table_has_postcode'=>$has('bh_venues','postcode'),
        'organiser_table_has_id'=>$has('bh_organisers','id')
    ];
    bh_schema_reply(200,[
        'ok'=>true,
        'read_only'=>true,
        'database'=>$database,
        'checked_at'=>date(DATE_ATOM),
        'tables'=>$report,
        'relationship_hints'=>$relations,
        'note'=>'Only schema metadata was read. No listing rows or personal data were accessed. No database changes were made.'
    ]);
} catch(Throwable $e) {
    error_log('Listing Checker schema diagnostic: '.$e->getMessage());
    bh_schema_reply(500,['ok'=>false,'error'=>'Schema diagnostic failed. Check the PHP error log.']);
}
