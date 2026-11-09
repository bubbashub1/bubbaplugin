<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/admin-session-guard.php';
function org_reply(int $code,array $data):never{http_response_code($code);echo json_encode($data,JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES);exit;}
if(empty($_SESSION['bh_admin_authenticated']))org_reply(401,['ok'=>false,'error'=>'admin_login_required']);
$_SESSION['bh_org_profile_csrf']??=bin2hex(random_bytes(32));
try{
 require __DIR__.'/db.php';$db=bh_mysql();$contactExists=(int)$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_organisers' AND COLUMN_NAME='contact_name'")->fetchColumn();if(!$contactExists)$db->exec("ALTER TABLE bh_organisers ADD COLUMN contact_name VARCHAR(160) NULL AFTER organisation_name");$cols=$db->query("SHOW COLUMNS FROM bh_organisers")->fetchAll(PDO::FETCH_COLUMN);
 $editable=['organisation_name','contact_name','email','phone','website','description','about_content','logo_url','facebook_url','instagram_url','tiktok_url'];
 if(($_SERVER['REQUEST_METHOD']??'GET')==='GET'){
  $action=(string)($_GET['action']??'list');
  if($action==='list'){
   $q=trim((string)($_GET['q']??''));$sql="SELECT id,organisation_name,email,phone".(in_array('status',$cols,true)?",status":"")." FROM bh_organisers";$args=[];
   if($q!==''){$like='%'.$q.'%';$sql.=" WHERE organisation_name LIKE ? OR email LIKE ?";$args=[$like,$like];if(in_array('contact_name',$cols,true)){$sql.=" OR contact_name LIKE ?";$args[]=$like;}}
   $sql.=" ORDER BY organisation_name,id LIMIT 500";$s=$db->prepare($sql);$s->execute($args);org_reply(200,['ok'=>true,'organisers'=>$s->fetchAll(PDO::FETCH_ASSOC),'csrf'=>$_SESSION['bh_org_profile_csrf']]);
  }
  if($action==='get'){$id=(int)($_GET['id']??0);if($id<1)org_reply(422,['ok'=>false,'error'=>'invalid_id']);$fields=['id'];foreach($editable as $c)if(in_array($c,$cols,true))$fields[]='`'.$c.'`';if(in_array('user_id',$cols,true))$fields[]='user_id';$s=$db->prepare('SELECT '.implode(',',$fields).' FROM bh_organisers WHERE id=?');$s->execute([$id]);$o=$s->fetch(PDO::FETCH_ASSOC);if(!$o)org_reply(404,['ok'=>false,'error'=>'organiser_not_found']);$uid=(int)($o['user_id']??0);if($uid){$u=$db->prepare('SELECT email FROM bh_users WHERE id=?');$u->execute([$uid]);$o['login_email']=$u->fetchColumn()?:'';}else $o['login_email']=$o['email']??'';org_reply(200,['ok'=>true,'organiser'=>$o,'csrf'=>$_SESSION['bh_org_profile_csrf']]);}
  org_reply(422,['ok'=>false,'error'=>'invalid_action']);
 }
 if(($_SERVER['REQUEST_METHOD']??'')!=='POST')org_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
 $origin=parse_url((string)($_SERVER['HTTP_ORIGIN']??''));$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);if(!is_array($origin)||strtolower((string)($origin['host']??''))!==$host||strtolower((string)($origin['scheme']??''))!=='https'||isset($origin['port']))org_reply(403,['ok'=>false,'error'=>'origin_forbidden']);
 if(!str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE']??'')),'application/json'))org_reply(415,['ok'=>false,'error'=>'json_required']);
 $b=json_decode((string)file_get_contents('php://input'),true);if(!is_array($b))org_reply(422,['ok'=>false,'error'=>'invalid_body']);if(!hash_equals((string)$_SESSION['bh_org_profile_csrf'],(string)($b['csrf']??'')))org_reply(403,['ok'=>false,'error'=>'csrf_invalid']);
 $id=(int)($b['id']??0);if($id<1)org_reply(422,['ok'=>false,'error'=>'invalid_id']);$name=trim((string)($b['organisation_name']??''));if($name===''||mb_strlen($name)>190)org_reply(422,['ok'=>false,'error'=>'Enter an organisation name (maximum 190 characters).']);
 $data=[];foreach($editable as $c){if(array_key_exists($c,$b))$data[$c]=trim((string)$b[$c]);}
 foreach(['contact_name'=>160,'phone'=>80,'website'=>255,'description'=>200000,'about_content'=>200000,'logo_url'=>2048,'facebook_url'=>2048,'instagram_url'=>2048,'tiktok_url'=>2048,'email'=>190] as $c=>$max)if(isset($data[$c])&&mb_strlen($data[$c])>$max)org_reply(422,['ok'=>false,'error'=>'The '.$c.' field is too long.']);
 foreach(['email'] as $c)if(($data[$c]??'')!==''&&!filter_var($data[$c],FILTER_VALIDATE_EMAIL))org_reply(422,['ok'=>false,'error'=>'Enter a valid public contact email.']);
 foreach(['website','logo_url','facebook_url','instagram_url','tiktok_url'] as $c)if(($data[$c]??'')!==''&&(!filter_var($data[$c],FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($data[$c],PHP_URL_SCHEME)),['http','https'],true)))org_reply(422,['ok'=>false,'error'=>'Enter a valid web URL for '.$c.'.']);
 $login=strtolower(trim((string)($b['login_email']??'')));if($login!==''&&(!filter_var($login,FILTER_VALIDATE_EMAIL)||strlen($login)>190))org_reply(422,['ok'=>false,'error'=>'Enter a valid leader login email.']);
 $s=$db->prepare('SELECT * FROM bh_organisers WHERE id=?');$s->execute([$id]);$old=$s->fetch(PDO::FETCH_ASSOC);if(!$old)org_reply(404,['ok'=>false,'error'=>'organiser_not_found']);
 $uid=(int)($old['user_id']??0);if(!$uid&&!empty($old['email'])){$find=$db->prepare("SELECT id FROM bh_users WHERE LOWER(email)=LOWER(?) AND role IN ('leader','organiser') LIMIT 1");$find->execute([$old['email']]);$uid=(int)($find->fetchColumn()?:0);}if($login!==''){$d=$db->prepare('SELECT id FROM bh_users WHERE LOWER(email)=LOWER(?) AND id<>? LIMIT 1');$d->execute([$login,$uid]);if($d->fetchColumn())org_reply(409,['ok'=>false,'error'=>'login_email_in_use']);}
 $data['organisation_name']=$name;$db->beginTransaction();try{$sets=[];$vals=[];foreach($data as $c=>$v)if(in_array($c,$cols,true)){$sets[]='`'.$c.'`=?';$vals[]=$v;}$vals[]=$id;$db->prepare('UPDATE bh_organisers SET '.implode(',',$sets).' WHERE id=?')->execute($vals);if($login!==''){if($uid)$db->prepare('UPDATE bh_users SET email=? WHERE id=?')->execute([$login,$uid]);elseif(!empty($old['email']))$db->prepare("UPDATE bh_users SET email=? WHERE LOWER(email)=LOWER(?) AND role IN ('leader','organiser')")->execute([$login,$old['email']]);if(in_array('email',$cols,true)&&empty($data['email']))$db->prepare('UPDATE bh_organisers SET email=? WHERE id=?')->execute([$login,$id]);}$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 org_reply(200,['ok'=>true,'message'=>'Organiser profile updated.']);
}catch(Throwable $e){error_log('Admin organiser profile editor: '.$e->getMessage());org_reply(500,['ok'=>false,'error'=>'organiser_profile_save_failed']);}
