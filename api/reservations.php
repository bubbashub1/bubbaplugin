<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
 http_response_code(405);
 echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
 exit;
}

$name=trim((string)($_POST['name']??''));
$email=trim((string)($_POST['email']??''));
$phone=trim((string)($_POST['phone']??''));
$child=trim((string)($_POST['child_name']??''));
$age=trim((string)($_POST['child_age']??''));
$qty=max(1,(int)($_POST['quantity']??1));
$message=trim((string)($_POST['message']??''));
$consent=isset($_POST['consent']);
$activityId=(int)($_POST['activity_id']??0);
$childId=(int)($_POST['child_id']??0);

$activity=trim((string)($_POST['activity_title']??''));
$slotDate=trim((string)($_POST['slot_date']??''));
$slotTime=trim((string)($_POST['slot_time']??''));
$consentJson=trim((string)($_POST['consent_json']??''));

if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$activityId<1||!$consent||$consentJson===''){
 http_response_code(422);
 echo json_encode(['ok'=>false,'error'=>'Please complete the required fields.']);
 exit;
}

require __DIR__ . '/db.php';
$db=bh_mysql();
// Always resolve the organiser email from the saved activity. Never trust a
// browser-supplied organiser email for reservation delivery.
$stmt=$db->prepare("SELECT o.email AS organiser_email, a.title FROM bh_activities a INNER JOIN bh_organisers o ON o.id=a.organiser_id WHERE a.id=? AND a.status='published' LIMIT 1");
$stmt->execute([$activityId]);
$activityRow=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$activityRow || !filter_var((string)$activityRow['organiser_email'],FILTER_VALIDATE_EMAIL)){
 http_response_code(422);
 echo json_encode(['ok'=>false,'error'=>'We could not find a registered organiser email for this activity.']);
 exit;
}
$leader=(string)$activityRow['organiser_email'];
$activity=$activity!==''?$activity:(string)$activityRow['title'];

// If a signed-in family selected a child profile, verify ownership before
// attaching the child to the reservation snapshot.
$sessionUserId=0;
if(session_status()!==PHP_SESSION_ACTIVE){session_start();}
$sessionUserId=(int)($_SESSION['bh_user_id']??0);
if($childId>0){
 if($sessionUserId<1){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'Please sign in to use a saved child profile.']);exit;}
 $stmt=$db->prepare("SELECT id,name,date_of_birth FROM bh_children WHERE id=? AND user_id=? LIMIT 1");
 $stmt->execute([$childId,$sessionUserId]);
 $childRow=$stmt->fetch(PDO::FETCH_ASSOC);
 if(!$childRow){http_response_code(403);echo json_encode(['ok'=>false,'error'=>'The selected child profile could not be verified.']);exit;}
 $child=(string)$childRow['name'];
 if($child===''||($age===''&&$childRow['date_of_birth'])){
   $dob=new DateTime((string)$childRow['date_of_birth']);$now=new DateTime('today');$age=(string)$now->diff($dob)->y;
 }
}
$consentData=json_decode($consentJson,true);
if(!is_array($consentData)||empty($consentData['consent_to_share_with_organiser'])||empty($consentData['consent_given_at'])||empty($consentData['consent_version'])){
 http_response_code(422);
 echo json_encode(['ok'=>false,'error'=>'Please complete the booking consent form first.']);
 exit;
}

$consentFields=[
 'Selected child profile ID'=>$consentData['selected_child_id']??'',
 'Child name'=>$consentData['selected_child_name']??($consentData['child_name']??''),
 'Date of birth'=>$consentData['date_of_birth']??'',
 'Gender'=>$consentData['gender']??'',
 'Additional needs'=>$consentData['additional_needs']??'',
 'Allergies'=>$consentData['allergies']??'',
 'Medical conditions'=>$consentData['medical_conditions']??'',
 'Medication'=>$consentData['medications']??'',
 'Dietary requirements'=>$consentData['dietary_requirements']??'',
 'Emergency information'=>$consentData['emergency_information']??'',
 'Emergency contact'=>$consentData['emergency_contact_name']??'',
 'Emergency contact phone'=>$consentData['emergency_contact_phone']??''
];
$consentText='';
foreach($consentFields as $label=>$value){
 $value=trim((string)$value);
 if($value!=='') $consentText.=$label.': '.$value."\n";
}
$photos=[];
if(!empty($consentData['photo_activity'])) $photos[]='Activity/event photos';
if(!empty($consentData['photo_bubbahub'])) $photos[]='Bubba Hub photos';
if(!empty($consentData['photo_organiser_marketing'])) $photos[]='Organiser marketing/social photos';

$subject='Bubba Hub reservation request: '.$activity;
$body="New Bubba Hub reservation request\n\nActivity: {$activity}\nDate: {$slotDate}\nTime: {$slotTime}\nPlaces: {$qty}\n\nParent/carer: {$name}\nEmail: {$email}\nPhone: {$phone}\nChild: {$child}\nChild age: {$age}\n\nMessage:\n{$message}\n\nThe family has consented to their details being sent to you to action this reservation request.\n\nBooking consent information shared by the family:\n".($consentText!==''?$consentText:'No additional child/medical information supplied.')."\nPhoto permissions: ".($photos?implode(', ',$photos):'None selected')."\nConsent version: ".trim((string)($consentData['consent_version']??''))."\nConsent given at: ".trim((string)($consentData['consent_given_at']??''))."\nReservation snapshot created at: ".trim((string)($consentData['reservation_created_at']??date('c')))."\n";

$headers="From: Bubba Hub <no-reply@bubbahub.co.uk>\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$sent=mail($leader,$subject,$body,$headers);

if(!$sent){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>'We could not send the request right now. Please try again later.']);
 exit;
}
echo json_encode(['ok'=>true]);
