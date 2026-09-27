<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$county=trim((string)($_GET['county']??''));
$allowed=['Devon'=>'devon','Cornwall'=>'cornwall','Plymouth'=>'plymouth','Torbay'=>'torbay'];
if(!isset($allowed[$county])){http_response_code(422);echo json_encode(['ok'=>false,'error'=>'Invalid county.']);exit;}

$url='https://schoolholidays.org.uk/area/'.$allowed[$county];
$context=stream_context_create(['http'=>['timeout'=>8,'user_agent'=>'BubbaHub/1.0']]);
$html=@file_get_contents($url,false,$context);
if($html===false){http_response_code(502);echo json_encode(['ok'=>false,'error'=>'School holiday dates could not be loaded.']);exit;}

$holiday=false;
$today=new DateTimeImmutable('today',new DateTimeZone('Europe/London'));
if(preg_match_all('/<tr[^>]*>\s*<td[^>]*>.*?<\/td>\s*<td[^>]*>([^<]+)<\/td>\s*<td[^>]*>([^<]+)<\/td>\s*<\/tr>/is',$html,$matches,PREG_SET_ORDER)){
 foreach($matches as $m){
   $start=DateTimeImmutable::createFromFormat('!j F Y',trim(html_entity_decode(strip_tags($m[1]))),new DateTimeZone('Europe/London'));
   $end=DateTimeImmutable::createFromFormat('!j F Y',trim(html_entity_decode(strip_tags($m[2]))),new DateTimeZone('Europe/London'));
   if(!$start||!$end)continue;
   if($today >= $start && $today <= $end->setTime(23,59,59)){$holiday=true;break;}
 }
}
echo json_encode(['ok'=>true,'county'=>$county,'date'=>$today->format('Y-m-d'),'is_school_holiday'=>$holiday,'source'=>$url],JSON_UNESCAPED_SLASHES);
