<?php
declare(strict_types=1);
/** Bubba & Me Expo exhibitor applications — staging feature. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function expo_reply(int $code,array $data): never { http_response_code($code);echo json_encode($data,JSON_UNESCAPED_SLASHES);exit; }
if (($_SERVER['REQUEST_METHOD']??'')!=='POST') expo_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
if ((int)($_SERVER['CONTENT_LENGTH']??0)>20000) expo_reply(413,['ok'=>false,'error'=>'request_too_large']);
$body=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($body))expo_reply(400,['ok'=>false,'error'=>'invalid_json']);
$val=static fn(string $key,int $max=2000):string=>mb_substr(trim((string)($body[$key]??'')),0,$max);
if($val('contact_consent')!=='yes'||$val('conditions_accepted')!=='yes'||$val('insurance_confirm')!=='yes')expo_reply(422,['ok'=>false,'error'=>'consent_required']);
$name=$val('business_name',150);$contact=$val('contact_name',120);$email=strtolower($val('email',190));$phone=$val('phone',30);$description=$val('description',2000);
if(!$name||!$contact||!filter_var($email,FILTER_VALIDATE_EMAIL)||!$phone)expo_reply(422,['ok'=>false,'error'=>'required_fields']);
$table=filter_var($body['preferred_table']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>26]]);
if(!$table)expo_reply(422,['ok'=>false,'error'=>'table_required']);
$policy=$val('insurance_policy',120);$expiry=$val('insurance_expiry',20);
if(!$policy||!preg_match('/^20\\d{2}-\\d{2}-\\d{2}$/',$expiry)||!strtotime($expiry))expo_reply(422,['ok'=>false,'error'=>'insurance_required']);
$keywords='/\\b(pregnan\\w*|matern\\w*|antenatal|postnatal|birth|bab(?:y|ies)|newborn\\w*|toddler\\w*|parent\\w*|famil(?:y|ies)|child\\w*|infant\\w*|breastfeed\\w*|midwi\\w*)\\b/iu';
$relevant=mb_strlen($description)>=35&&preg_match($keywords,$description)===1;
$status=$relevant?'approved':'pending_review';
try {
 require __DIR__.'/db.php';$db=bh_mysql();
 // Installation/migrations are separate: do not create or mutate schema from public requests.
 $db->beginTransaction();
 $lock=$db->prepare('SELECT table_number FROM bh_expo_tables WHERE table_number=? AND active=1 FOR UPDATE');$lock->execute([$table]);
 if(!$lock->fetchColumn()){ $db->rollBack();expo_reply(409,['ok'=>false,'error'=>'table_unavailable']);}
 $taken=$db->prepare("SELECT id FROM bh_expo_exhibitors WHERE table_number=? AND status IN ('approved','pending_review') LIMIT 1 FOR UPDATE");$taken->execute([$table]);
 if($taken->fetchColumn()){ $db->rollBack();expo_reply(409,['ok'=>false,'error'=>'table_unavailable']);}
 $details=[];
 foreach(['website','town','service_category','oversize','oversize_details','electricity','chair','goody_bag','donation_interest','payment_preference','invoice_requested','insurance_policy','insurance_expiry','logo_url','requirements','notes','participation'] as $key){
  $v=$body[$key]??'';$details[$key]=is_array($v)?array_slice(array_map('strval',$v),0,8):mb_substr((string)$v,0,1500);
 }
 $stmt=$db->prepare('INSERT INTO bh_expo_exhibitors (business_name,contact_name,email,phone,description,table_number,status,details_json,created_at) VALUES (?,?,?,?,?,?,?,?,NOW())');
 $stmt->execute([$name,$contact,$email,$phone,$description,$table,$status,json_encode($details,JSON_UNESCAPED_UNICODE)]);
 $id=(int)$db->lastInsertId();$db->commit();
 // Never expose applicant contact details in notification subject.
 $subject='Bubba & Me Expo exhibitor application #'.$id;
 $message="New exhibitor application #".$id."\\nStatus: ".$status."\\nPreferred table: ".$table."\\nReview securely in the admin area.";
 $notified=@mail('contact@bubbahub.co.uk',$subject,$message,"From: noreply@bubbahub.co.uk\\r\\nContent-Type: text/plain; charset=UTF-8");
 error_log('Expo application '.$id.' admin_mail='.($notified?'sent':'failed'));
 expo_reply(201,['ok'=>true,'application_id'=>$id,'status'=>$status,'table_number'=>$table,'admin_notification_queued'=>$notified]);
} catch(Throwable $e){if(isset($db)&&$db->inTransaction())$db->rollBack();error_log('Expo application failure: '.$e->getMessage());expo_reply(503,['ok'=>false,'error'=>'booking_temporarily_unavailable']);}
