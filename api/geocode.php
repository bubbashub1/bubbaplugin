<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q=trim((string)($_GET['q']??''));
if($q===''||mb_strlen($q)<3){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'Enter at least 3 characters.']);exit;}
$q=mb_substr($q,0,180);
$url='https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=5&countrycodes=gb&q='.rawurlencode($q);
$headers=['User-Agent: Bubba Hub Address Lookup/1.1 (+https://bubbahub.co.uk)','Accept-Language: en-GB,en;q=0.9'];
$body=false;$status=0;$error='';
if(function_exists('curl_init')){
 $ch=curl_init($url);
 curl_setopt_array($ch,[
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_CONNECTTIMEOUT=>5,
  CURLOPT_TIMEOUT=>12,
  CURLOPT_HTTPHEADER=>$headers
 ]);
 $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
}elseif(ini_get('allow_url_fopen')){
 $context=stream_context_create(['http'=>['method'=>'GET','timeout'=>12,'header'=>"User-Agent: Bubba Hub Address Lookup/1.1 (+https://bubbahub.co.uk)\r\nAccept-Language: en-GB,en;q=0.9\r\n"]]);
 $body=@file_get_contents($url,false,$context);
 $status=$body!==false?200:0;
}else{
 http_response_code(503);echo json_encode(['ok'=>false,'error'=>'Address lookup is not available on this server. Please ask your host to enable PHP cURL.']);exit;
}
if($body===false||$status<200||$status>=300){
 http_response_code(502);echo json_encode(['ok'=>false,'error'=>'Address lookup is temporarily unavailable. Please check the address and try again.']);exit;
}
$data=json_decode($body,true);
if(!is_array($data)){http_response_code(502);echo json_encode(['ok'=>false,'error'=>'Invalid address response.']);exit;}
echo json_encode(['ok'=>true,'results'=>$data],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
