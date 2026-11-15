<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode','1');ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');session_start();
function create_org_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);exit;}
if(empty($_SESSION['bh_admin_authenticated']))create_org_reply(401,['ok'=>false,'error'=>'admin_login_required']);
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')create_org_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$origin=parse_url((string)($_SERVER['HTTP_ORIGIN']??''));$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
if(!is_array($origin)||strtolower((string)($origin['host']??''))!==$host||strtolower((string)($origin['scheme']??''))!=='https'||isset($origin['port']))create_org_reply(403,['ok'=>false,'error'=>'origin_forbidden']);
if(!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json'))create_org_reply(415,['ok'=>false,'error'=>'json_required']);
$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input))create_org_reply(422,['ok'=>false,'error'=>'invalid_body']);
$leaderId=filter_var($input['leader_id']??null,FILTER_VALIDATE_INT);
$name=trim((string)($input['organisation_name']??''));
if(!$leaderId||$leaderId<1)create_org_reply(422,['ok'=>false,'error'=>'invalid_leader_id']);
if($name===''||mb_strlen($name)>190)create_org_reply(422,['ok'=>false,'message'=>'Enter an organiser profile name (maximum 190 characters).']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $q=$db->prepare("SELECT id,email FROM bh_users WHERE id=? AND role='leader' LIMIT 1");$q->execute([$leaderId]);$leader=$q->fetch(PDO::FETCH_ASSOC);
 if(!$leader)create_org_reply(404,['ok'=>false,'error'=>'leader_not_found']);
 $columns=$db->query("SHOW COLUMNS FROM bh_organisers")->fetchAll(PDO::FETCH_ASSOC);$meta=[];
 foreach($columns as $column){$field=(string)($column['Field']??'');if($field!=='')$meta[$field]=$column;}
 $nameField=isset($meta['organisation_name'])?'organisation_name':(isset($meta['name'])?'name':(isset($meta['business_name'])?'business_name':''));
 if($nameField==='')throw new RuntimeException('Organiser table has no supported name field.');
 $fields=[$nameField];$values=[$name];
 if(isset($meta['user_id'])){$fields[]='user_id';$values[]=$leaderId;}
 if(isset($meta['email'])){$fields[]='email';$values[]=$leader['email'];}
 if(isset($meta['slug'])){
  $base=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$name),'-'));if($base==='')$base='organiser';
  $slug=$base;$n=2;$check=$db->prepare('SELECT id FROM bh_organisers WHERE slug=? LIMIT 1');
  while(true){$check->execute([$slug]);if(!$check->fetchColumn())break;$slug=$base.'-'.$n++;}
  $fields[]='slug';$values[]=$slug;
 }
 foreach($meta as $field=>$column){
  $required=(($column['Null']??'YES')==='NO')&&($column['Default']??null)===null&&stripos((string)($column['Extra']??''),'auto_increment')===false;
  if(!$required||in_array($field,$fields,true))continue;
  $lower=strtolower($field);$value='';
  if(in_array($lower,['created_by','owner_user_id','leader_user_id','account_user_id'],true))$value=$leaderId;
  elseif(in_array($lower,['terms_accepted','terms_agreed','organiser_terms_accepted','active','is_active','enabled'],true))$value=1;
  elseif(in_array($lower,['terms_accepted_at','terms_agreed_at'],true))$value=date('Y-m-d H:i:s');
  elseif(str_contains($lower,'email'))$value=$leader['email'];
  elseif(str_contains($lower,'name')||str_contains($lower,'title')||str_contains($lower,'address')||str_contains($lower,'town')||str_contains($lower,'city')||str_contains($lower,'region')||str_contains($lower,'county')||str_contains($lower,'description')||str_contains($lower,'about')||str_contains($lower,'notes')||str_contains($lower,'phone')||str_contains($lower,'website'))$value='';
  else throw new RuntimeException('Unsupported required organiser field: '.$field);
  $fields[]=$field;$values[]=$value;
 }
 $quoted=implode(',',array_map(static fn($field)=>'\x60'.$field.'\x60',$fields));
 $marks=implode(',',array_fill(0,count($fields),'?'));
 $db->beginTransaction();
 $insert=$db->prepare("INSERT INTO bh_organisers ($quoted) VALUES ($marks)");$insert->execute($values);
 $organiserId=(int)$db->lastInsertId();$db->commit();
 create_org_reply(201,['ok'=>true,'organiser_id'=>$organiserId,'message'=>'New organiser profile created. Existing organiser profiles were left unchanged.']);
}catch(Throwable $e){
 if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();
 error_log('Admin create organiser profile: '.$e->getMessage());
 create_org_reply(500,['ok'=>false,'error'=>'organiser_profile_create_failed','message'=>'The new organiser profile could not be created.']);
}
