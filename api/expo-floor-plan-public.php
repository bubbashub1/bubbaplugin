<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);exit;}
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $q=$db->prepare('SELECT published FROM bh_expo_floor_plan_publication WHERE event_key=?');$q->execute(['bubba-me-2027']);
 if(!(bool)$q->fetchColumn()){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'floor_plan_not_published']);exit;}
 $q=$db->prepare('SELECT layout_json FROM bh_expo_floor_plans WHERE event_key=?');$q->execute(['bubba-me-2027']);
 $items=json_decode((string)($q->fetchColumn()?:'[]'),true);
 if(!is_array($items))throw new RuntimeException('invalid_layout');
 $tables=[];foreach($items as $item){if(($item['type']??'')!=='table')continue;$tables[]=['label'=>mb_substr((string)($item['label']??''),0,35),'x'=>(float)($item['x']??0),'y'=>(float)($item['y']??0),'angle'=>(int)($item['angle']??0)];}
 echo json_encode(['ok'=>true,'tables'=>$tables],JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){error_log('expo public floor plan: '.get_class($e));http_response_code(503);echo json_encode(['ok'=>false,'error'=>'floor_plan_unavailable']);}
