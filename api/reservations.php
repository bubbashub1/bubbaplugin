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
$leader=trim((string)($_POST['organiser_email']??''));
$activity=trim((string)($_POST['activity_title']??''));
$slotDate=trim((string)($_POST['slot_date']??''));
$slotTime=trim((string)($_POST['slot_time']??''));
$consentJson=trim((string)($_POST['consent_json']??''));

if($name===''||!filter_var($email,FILTER_VALIDATE_EMAIL)||$leader===''||!filter_var($leader,FILTER_VALIDATE_EMAIL)||!$consent||$consentJson===''){
 http_response_code(422);
 echo json_encode(['ok'=>false,'error'=>'Please complete the required fields.']);
 exit;
}

$consentData=json_decode($consentJson,true);
if(!is_array($consentData)||empty($consentData['consent_to_share_with_organiser'])){
 http_response_code(422);
 echo json_encode(['ok'=>false,'error'=>'Please complete the booking consent form first.']);
 exit;
}

$consentFields=[
 'Child name'=>$consentData['child_name']??'',
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
$body="New Bubba Hub reservation request\n\nActivity: {$activity}\nDate: {$slotDate}\nTime: {$slotTime}\nPlaces: {$qty}\n\nParent/carer: {$name}\nEmail: {$email}\nPhone: {$phone}\nChild: {$child}\nChild age: {$age}\n\nMessage:\n{$message}\n\nThe family has consented to their details being sent to you to action this reservation request.\n\nBooking consent information shared by the family:\n".($consentText!==''?$consentText:'No additional child/medical information supplied.')."\nPhoto permissions: ".($photos?implode(', ',$photos):'None selected')."\nConsent version: ".trim((string)($consentData['consent_version']??'1.0'))."\n";

$headers="From: Bubba Hub <no-reply@bubbahub.co.uk>\r\nReply-To: {$email}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$sent=mail($leader,$subject,$body,$headers);

if(!$sent){
 http_response_code(500);
 echo json_encode(['ok'=>false,'error'=>'We could not send the request right now. Please try again later.']);
 exit;
}
echo json_encode(['ok'=>true]);
