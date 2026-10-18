<?php
declare(strict_types=1);
/** Staging-only authenticated read-only organiser/venue matching for five Sheets rows. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function matchReply(int $code,array $data): void {http_response_code($code);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if (($_SERVER['REQUEST_METHOD']??'')!=='POST')matchReply(405,['ok'=>false,'error'=>'post_required']);
if (strtolower((string)($_SERVER['HTTP_HOST']??''))!=='staging.bubbahub.co.uk')matchReply(403,['ok'=>false,'error'=>'staging_only']);
$config=is_file(__DIR__.'/config.php')?require __DIR__.'/config.php':[];
$secret=(string)(getenv('BH_SYNC_TOKEN')?:(is_array($config)?($config['sheets_sync_token']??''):''));
if($secret===''){
 $wpConfig=dirname(__DIR__).'/wp-config.php';
 if(is_readable($wpConfig)){
  $source=file_get_contents($wpConfig);
  if(is_string($source)&&preg_match('/putenv\\s*\\(\\s*([\\x27\\x22])BH_SYNC_TOKEN=([^\\x27\\x22\\r\\n]*)\\1\\s*\\)\\s*;/',$source,$m))$secret=$m[2];
 }
}
$auth=(string)($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
if($secret===''||!preg_match('/^Bearer (.+)$/i',$auth,$m)||!hash_equals($secret,trim($m[1])))matchReply(401,['ok'=>false,'error'=>'unauthorized']);
$raw=file_get_contents('php://input',false,null,0,8193);
if(!is_string($raw)||strlen($raw)>8192)matchReply(413,['ok'=>false,'error'=>'payload_too_large']);
$input=json_decode($raw,true);
if(!is_array($input)||($input['action']??'')!=='match_preview'||!isset($input['records'])||!is_array($input['records'])||count($input['records'])<1||count($input['records'])>5)matchReply(400,['ok'=>false,'error'=>'invalid_payload']);
foreach($input['records'] as $r){
 if(!is_array($r)||!isset($r['id'],$r['organisation_name'],$r['venue_name'])||!is_string($r['id'])||!preg_match('/^[1-9][0-9]*$/',$r['id'])||!is_string($r['organisation_name'])||!is_string($r['venue_name'])||strlen($r['organisation_name'])>255||strlen($r['venue_name'])>255)matchReply(400,['ok'=>false,'error'=>'invalid_record']);
}
try{
 require_once __DIR__.'/db.php';$pdo=bh_mysql();
 $org=$pdo->query('SELECT id,organisation_name FROM bh_organisers ORDER BY id LIMIT 5001')->fetchAll(PDO::FETCH_ASSOC);
 $venues=$pdo->query('SELECT id,name,venue_name,postcode,town,address FROM bh_venues ORDER BY id LIMIT 5001')->fetchAll(PDO::FETCH_ASSOC);
 if(count($org)>5000||count($venues)>5000)matchReply(409,['ok'=>false,'error'=>'matching_dataset_too_large']);
 $norm=static function(string $v):string{return strtolower(trim(preg_replace('/\\s+/u',' ',$v)??$v));};
 $results=[];
 foreach($input['records'] as $r){
  $on=$norm($r['organisation_name']);$vn=$norm($r['venue_name']);
  $om=$on===''?[]:array_values(array_filter($org,static fn($o)=>$norm((string)$o['organisation_name'])===$on));
  $vm=$vn===''?[]:array_values(array_filter($venues,static fn($v)=>$norm((string)($v['name']?:$v['venue_name']))===$vn||$norm((string)$v['venue_name'])===$vn));
  $results[]=[
   'id'=>$r['id'],
   'organiser'=>['status'=>$on===''?'MISSING_SOURCE':(stripos($on,'unconfirmed')!==false?'UNCONFIRMED':(count($om)===1?'NAME_MATCH_REVIEW':(count($om)>1?'AMBIGUOUS':'NO_NAME_MATCH'))),'matches'=>array_map(static fn($o)=>['id'=>(string)$o['id'],'name'=>$o['organisation_name']],array_slice($om,0,10))],
   'venue'=>['status'=>$vn===''?'MISSING_SOURCE':(count($vm)===1?'NAME_MATCH_REVIEW':(count($vm)>1?'AMBIGUOUS':'NO_NAME_MATCH')),'matches'=>array_map(static fn($v)=>['id'=>(string)$v['id'],'name'=>$v['name'],'postcode'=>$v['postcode'],'town'=>$v['town']],array_slice($vm,0,10))]
  ];
 }
 matchReply(200,['ok'=>true,'mode'=>'ORGANISER_VENUE_MATCH_PREVIEW_READ_ONLY','records'=>$results,'organisersScanned'=>count($org),'venuesScanned'=>count($venues),'autoLinkAllowed'=>false,'writesPerformed'=>false]);
}catch(Throwable $e){error_log('Sheets match preview failed: '.$e->getMessage());matchReply(500,['ok'=>false,'error'=>'match_preview_failed']);}
