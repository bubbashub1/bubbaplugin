<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require __DIR__.'/admin-session-guard.php';
require __DIR__.'/db.php';
function respond(int $status,array $data):never{http_response_code($status);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;}
try{
 if($_SERVER['REQUEST_METHOD']!=='GET')respond(405,['ok'=>false,'error'=>'Method not allowed']);
 $db=bh_mysql();bh_ensure_newsletter_subscribers($db);
 $counts=$db->query("SELECT status,COUNT(*) AS total FROM bh_newsletter_subscribers GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);
 $recent=$db->query("SELECT id,email,status,source,consented_at,unsubscribed_at FROM bh_newsletter_subscribers ORDER BY id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
 $accounts=['enabled'=>0,'daily'=>0,'weekly'=>0,'monthly'=>0];
 try{$q=$db->query("SELECT newsletter_frequency,COUNT(*) AS total FROM bh_user_preferences WHERE newsletter_enabled=1 AND email_enabled=1 GROUP BY newsletter_frequency");foreach($q as $row){$key=(string)$row['newsletter_frequency'];if(isset($accounts[$key]))$accounts[$key]=(int)$row['total'];$accounts['enabled']+=(int)$row['total'];}}catch(Throwable $ignored){}
 $configFile=__DIR__.'/config.php';$config=is_file($configFile)?require $configFile:[];
 $cronConfigured=trim((string)($config['newsletter']['cron_secret']??getenv('BUBBAHUB_NEWSLETTER_SECRET')?:''))!=='';
 respond(200,['ok'=>true,'counts'=>['subscribed'=>(int)($counts['subscribed']??0),'unsubscribed'=>(int)($counts['unsubscribed']??0)],'account_preferences'=>$accounts,'cron_configured'=>$cronConfigured,'recent'=>$recent,'note'=>'Delivery history and SMTP health are not recorded by this endpoint.']);
}catch(Throwable $e){error_log('[BH email centre] '.$e->getMessage());respond(500,['ok'=>false,'error'=>'Email centre data temporarily unavailable']);}
