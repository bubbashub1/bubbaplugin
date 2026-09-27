<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function outj($s,$d){http_response_code($s);echo json_encode($d,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
$name=trim((string)($_GET['name']??'')); $town=trim((string)($_GET['town']??''));
if($name==='') outj(422,['ok'=>false,'error'=>'school_name_required']);
$cfile=__DIR__.'/config.php'; $cfg=is_file($cfile)?require $cfile:[]; $g=is_array($cfg['google']??null)?$cfg['google']:[];
$key=(string)($g['search_api_key']??$g['custom_search_api_key']??''); $cx=(string)($g['search_engine_id']??$g['cx']??'422b14c12fba84ec6');
if($key===''||$cx==='') outj(503,['ok'=>false,'error'=>'google_search_not_configured']);
$q=$name.($town!==''?' '.$town:'').' school official website';
$url='https://www.googleapis.com/customsearch/v1?key='.rawurlencode($key).'&cx='.rawurlencode($cx).'&q='.rawurlencode($q).'&num=5&safe=active';
$ch=curl_init($url); curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CONNECTTIMEOUT=>6]); $raw=curl_exec($ch); $code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
if($raw===false||$code<200||$code>=300) outj(502,['ok'=>false,'error'=>'google_search_unavailable']);
$data=json_decode($raw,true); if(!is_array($data)) outj(502,['ok'=>false,'error'=>'invalid_google_response']);
$r=[]; foreach(($data['items']??[]) as $i){if(!empty($i['link']))$r[]=['title'=>(string)($i['title']??''),'url'=>(string)$i['link'],'snippet'=>(string)($i['snippet']??''),'display_link'=>(string)($i['displayLink']??'')];}
outj(200,['ok'=>true,'results'=>$r]);
?>