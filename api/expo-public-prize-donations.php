<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function reply(int $code,array $data):never{http_response_code($code);echo json_encode($data);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='POST')reply(405,['ok'=>false,'error'=>'method_not_allowed']);
if((int)($_SERVER['CONTENT_LENGTH']??0)>12000)reply(413,['ok'=>false,'error'=>'too_large']);
$origin=(string)($_SERVER['HTTP_ORIGIN']??'');
$host=strtolower(explode(':',(string)($_SERVER['HTTP_HOST']??''))[0]);
if(!$origin||parse_url($origin,PHP_URL_SCHEME)!=='https'||strtolower((string)parse_url($origin,PHP_URL_HOST))!==$host)reply(403,['ok'=>false,'error'=>'invalid_origin']);
$data=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($data))reply(400,['ok'=>false,'error'=>'invalid_json']);
if(!empty($data['website']))reply(200,['ok'=>true]);
function field(array $d,string $key,int $max):string{return mb_substr(trim((string)($d[$key]??'')),0,$max);}
$name=field($data,'donor_name',160);$email=field($data,'email',190);$title=field($data,'title',160);
$description=field($data,'description',2000);$value=field($data,'prize_value',60);
$contact=field($data,'collection_details',500);$url=field($data,'donor_url',500);
if(!$name||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$title||!$description||empty($data['consent']))reply(422,['ok'=>false,'error'=>'missing_fields']);
if($url&&(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['https','http'],true)))reply(422,['ok'=>false,'error'=>'invalid_url']);
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();
 $recent=$db->prepare("SELECT COUNT(*) FROM bh_expo_public_prize_donations WHERE email=? AND created_at > (NOW() - INTERVAL 1 HOUR)");
 $recent->execute([$email]);
 if((int)$recent->fetchColumn()>=3)reply(429,['ok'=>false,'error'=>'too_many_submissions']);
 $q=$db->prepare("INSERT INTO bh_expo_public_prize_donations(event_key,donor_name,email,title,description,prize_value,donor_url,collection_details,public_credit) VALUES(?,?,?,?,?,?,?,?,?)");
 $q->execute(['bubba-me-2027',$name,$email,$title,$description,$value,$url,$contact,!empty($data['public_credit'])?1:0]);
 // Mail is best-effort: never tell the donor submission failed after a successful write.
 try {
  require_once __DIR__.'/mailer.php';
  $adminEmail=getenv('BH_EXPO_PRIZE_NOTIFY_EMAIL') ?: 'contact@bubbahub.co.uk';
  if(filter_var($adminEmail,FILTER_VALIDATE_EMAIL)){
   $safeName=htmlspecialchars($name,ENT_QUOTES,'UTF-8');
   $safeTitle=htmlspecialchars($title,ENT_QUOTES,'UTF-8');
   $html='<p>New Expo prize donation awaiting review.</p><p><strong>Donor:</strong> '.$safeName.'</p><p><strong>Prize:</strong> '.$safeTitle.'</p><p><a href="https://bubbahub.co.uk/admin/expo-prize-donations.html">Open admin approvals</a></p>';
   $plain="New Expo prize donation awaiting review.\\nDonor: $name\\nPrize: $title\\nOpen: https://bubbahub.co.uk/admin/expo-prize-donations.html";
   if(!bh_send_smtp_mail($adminEmail,'Expo prize donation awaiting approval',$html,$plain,''))error_log('[Expo donation] Admin notification send returned false');
  }
 }catch(Throwable $mailError){error_log('[Expo donation] Notification failed: '.$mailError->getMessage());}
 reply(200,['ok'=>true,'message'=>'submitted_for_review']);
}catch(Throwable $e){reply(503,['ok'=>false,'error'=>'submission_unavailable']);}
