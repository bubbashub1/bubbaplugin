<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function finish(int $code,array $data): never {http_response_code($code);echo json_encode($data);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')finish(405,['ok'=>false,'error'=>'method_not_allowed']);
$host=strtolower((string)($_SERVER['HTTP_HOST']??''));
if(!in_array($host,['bubbahub.co.uk','staging.bubbahub.co.uk'],true))finish(404,['ok'=>false]);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$referer=(string)($_SERVER['HTTP_REFERER']??'');
if($origin!==''?$origin!=='https://'.$host:($referer===''||parse_url($referer,PHP_URL_SCHEME)!=='https'||parse_url($referer,PHP_URL_HOST)!==$host))finish(403,['ok'=>false,'error'=>'invalid_origin']);
ini_set('session.use_strict_mode','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated'])||time()-(int)($_SESSION['bh_admin_last_activity']??0)>1800)finish(401,['ok'=>false,'error'=>'admin_login_required']);
$csrf=(string)($_SERVER['HTTP_X_BH_ADMIN_CSRF']??'');
if($csrf===''||!hash_equals((string)($_SESSION['bh_admin_csrf']??''),$csrf))finish(403,['ok'=>false,'error'=>'invalid_csrf']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>256)finish(413,['ok'=>false]);
$input=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($input)||($input['confirmation']??'')!=='CANCEL_FOUR_EXPO_TESTS')finish(422,['ok'=>false,'error'=>'confirmation_required']);
$refs=['BEDBB93515D33BB49DE6CBB8','C2611A49F6852EEE9AAE4DAB','7ADC41B0DE448CF11918D095','A2862D6048F5E43E27454C19'];
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();$db->beginTransaction();
 $in=implode(',',array_fill(0,count($refs),'?'));
 $q=$db->prepare("SELECT public_reference,booking_status,adults,children FROM bh_expo_reservations WHERE event_key='bubba-me-2027' AND public_reference IN ($in) FOR UPDATE");
 $q->execute($refs);$rows=$q->fetchAll(PDO::FETCH_ASSOC);
 if(count($rows)!==4){$db->rollBack();finish(409,['ok'=>false,'error'=>'expected_bookings_not_found']);}
 $released=0;$changed=0;
 foreach($rows as $row){if(in_array($row['booking_status'],['pending','confirmed'],true)){$released+=(int)$row['adults']+(int)$row['children'];$changed++;}elseif($row['booking_status']!=='cancelled'){$db->rollBack();finish(409,['ok'=>false,'error'=>'unexpected_booking_status']);}}
 $update=$db->prepare("UPDATE bh_expo_reservations SET booking_status='cancelled' WHERE event_key='bubba-me-2027' AND public_reference IN ($in) AND booking_status IN ('pending','confirmed')");
 $update->execute($refs);$db->commit();
 finish(200,['ok'=>true,'cancelled'=>$changed,'released_spaces'=>$released]);
}catch(Throwable $e){if(isset($db)&&$db instanceof PDO&&$db->inTransaction())$db->rollBack();error_log('Expo test cancellation failed: '.get_class($e));finish(503,['ok'=>false,'error'=>'cancellation_unavailable']);}
