<?php
declare(strict_types=1);
/**
 * Staging-only schema compatibility report for Google Sheets write integration.
 * No database mutations and no secret values in output.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function auditReply(int $code,array $data): void {
    http_response_code($code);
    echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') auditReply(405,['ok'=>false,'error'=>'post_required']);
if (strtolower((string)($_SERVER['HTTP_HOST'] ?? '')) !== 'staging.bubbahub.co.uk') auditReply(403,['ok'=>false,'error'=>'staging_only']);
$config = is_file(__DIR__.'/config.php') ? require __DIR__.'/config.php' : [];
$secret = (string)(getenv('BH_SYNC_TOKEN') ?: (is_array($config) ? ($config['sheets_sync_token'] ?? '') : ''));
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if ($secret === '' || !preg_match('/^Bearer (.+)$/i',$auth,$matches) || !hash_equals($secret,trim($matches[1]))) auditReply(401,['ok'=>false,'error'=>'unauthorized']);
try {
    require_once __DIR__.'/db.php';
    $db=bh_mysql();
    $tables=['bh_activities','bh_organisers','bh_venues','bh_sessions','bh_activity_venues','bh_activity_schedules','bh_sheet_sync_records','bh_sheet_sync_runs','bh_sheet_sync_conflicts'];
    $report=[];
    foreach ($tables as $table) {
        $stmt=$db->prepare('SELECT COLUMN_NAME,DATA_TYPE,IS_NULLABLE,COLUMN_DEFAULT,EXTRA,COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? ORDER BY ORDINAL_POSITION');
        $stmt->execute([$table]);
        $cols=$stmt->fetchAll(PDO::FETCH_ASSOC);
        $required=[];
        foreach ($cols as $c) {
            if ($c['IS_NULLABLE']==='NO' && $c['COLUMN_DEFAULT']===null && stripos((string)$c['EXTRA'],'auto_increment')===false) $required[]=$c['COLUMN_NAME'];
        }
        $report[$table]=['exists'=>count($cols)>0,'columns'=>array_map(static fn($c)=>['name'=>$c['COLUMN_NAME'],'type'=>$c['DATA_TYPE'],'nullable'=>$c['IS_NULLABLE']==='YES','auto_increment'=>stripos((string)$c['EXTRA'],'auto_increment')!==false,'key'=>$c['COLUMN_KEY']],$cols),'required_without_default'=>$required];
    }
    $activities=array_column($report['bh_activities']['columns'],'name');
    $needed=['id','title','slug','description','category','status','organiser_id','price_from','booking_url'];
    $missing=array_values(array_diff($needed,$activities));
    auditReply(200,['ok'=>true,'mode'=>'schema_audit_read_only','ready_for_automatic_writes'=>false,'missing_expected_activity_columns'=>$missing,'tables'=>$report]);
} catch (Throwable $e) {
    error_log('Sheet schema audit: '.$e->getMessage());
    auditReply(500,['ok'=>false,'error'=>'schema_audit_failed']);
}
