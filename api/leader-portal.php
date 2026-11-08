<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
// Log startup failures even when PHP cannot reach the API exception handler.
register_shutdown_function(static function (): void {
    $error=error_get_last();
    if (!$error || !in_array($error['type'],[E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR],true)) return;
    error_log('BH leader portal fatal: '.$error['message'].' in '.$error['file'].':'.$error['line']);
    if (headers_sent()) return;
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'error'=>'leader_portal_error','message'=>'Leader portal encountered a server error. Please try again.']);
});
try {
require_once __DIR__.'/db.php';
require_once __DIR__.'/mailer.php';
if(session_status()!==PHP_SESSION_ACTIVE){session_name('BUBBAHUBSESSID');session_start();}
function lp(int $s,array $d): void{http_response_code($s);echo json_encode($d,JSON_UNESCAPED_SLASHES);exit;}
function lpEnsureExpertise(PDO $db): void{
 try{$db->exec("CREATE TABLE IF NOT EXISTS bh_leader_expertise (organiser_id BIGINT UNSIGNED NOT NULL, topic_key VARCHAR(80) NOT NULL, enabled TINYINT(1) NOT NULL DEFAULT 1, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, PRIMARY KEY (organiser_id,topic_key), INDEX idx_leader_expertise_topic (topic_key)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}catch(Throwable $ignored){}
}
$expertiseTopics=[
 'baby-child-health'=>'Baby & child health',
 'feeding-weaning'=>'Feeding & weaning',
 'sleep'=>'Sleep',
 'pregnancy-new-parents'=>'Pregnancy & new parents',
 'family-wellbeing'=>'Family wellbeing',
 'activities-classes'=>'Activities & classes',
 'specialist-send'=>'SEND & additional needs'
];
