<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function fail(int $code,string $message): never { http_response_code($code); echo json_encode(['ok'=>false,'error'=>$message]);exit; }
if(empty($_SESSION['bh_admin_authenticated']))fail(401,'Admin login required.');
if($_SERVER['REQUEST_METHOD']!=='POST')fail(405,'POST required.');
$input=json_decode(file_get_contents('php://input'),true);
$url=trim((string)($input['url']??''));
$p=parse_url($url);
if(!$p||strtolower($p['scheme']??'')!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass'])||isset($p['fragment'])||isset($p['port']))fail(422,'Use a public HTTPS .ics feed URL.');
$host=$p['host'];
if(!preg_match('/^[a-z0-9.-]+$/i',$host)||!str_contains($host,'.'))fail(422,'Invalid feed host.');
$ips=gethostbynamel($host);
if(!$ips)fail(422,'Feed host could not be resolved.');
foreach($ips as $ip){if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))fail(422,'Feed host must be publicly accessible.');}
if(!function_exists('curl_init'))fail(500,'Calendar import is not available on this server.');
$ch=curl_init($url);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]],CURLOPT_USERAGENT=>'BubbaHubCalendarImporter/1.0',CURLOPT_MAXFILESIZE=>1048576]);
$body=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
if(!is_string($body)||strlen($body)>1048576||$code!==200)fail(422,'Could not retrieve the public calendar feed (HTTP '.$code.').');
if(!str_contains($body,'BEGIN:VCALENDAR'))fail(422,'The URL did not return an iCalendar feed.');
$lines=preg_split('/\r\n|\n|\r/',$body);$unfold=[];
foreach($lines as $line){if(preg_match('/^[ \t]/',$line)&&$unfold){$unfold[count($unfold)-1].=substr($line,1);}else{$unfold[]=$line;}}
$events=[];$event=null;$skippedRecurring=0;
foreach($unfold as $line){
 if($line==='BEGIN:VEVENT'){$event=[];continue;}
 if($line==='END:VEVENT'&&$event!==null){
  if(isset($event['RRULE'])){$skippedRecurring++;$event=null;continue;}
  if(($event['STATUS']??'')==='CANCELLED'){$event=null;continue;}
  $start=$event['DTSTART']??'';$end=$event['DTEND']??'';
  $parse=static function(string $v): ?DateTimeImmutable {if(!preg_match('/^\d{8}T\d{6}Z?$/',$v))return null;$tz=str_ends_with($v,'Z')?new DateTimeZone('UTC'):new DateTimeZone('Europe/London');$d=DateTimeImmutable::createFromFormat('!Ymd\\THis'.(str_ends_with($v,'Z')?'\\Z':''),$v,$tz);return $d?$d->setTimezone(new DateTimeZone('Europe/London')):null;};
  $dt=$parse($start);$de=$parse($end);
  if($dt&&count($events)<150){$events[]=['title'=>$event['SUMMARY']??'Class session','day_of_week'=>(int)$dt->format('N'),'start_time'=>$dt->format('H:i'),'end_time'=>$de?$de->format('H:i'):'','start_date'=>$dt->format('Y-m-d'),'end_date'=>$dt->format('Y-m-d'),'frequency'=>'once','url'=>$event['URL']??'','uid'=>$event['UID']??''];}
  $event=null;continue;
 }
 if($event!==null&&preg_match('/^([A-Z-]+)(?:;[^:]*)?:(.*)$/',$line,$m)){$event[$m[1]]=$m[2];}
}
echo json_encode(['ok'=>true,'data'=>$events,'skipped_recurring'=>$skippedRecurring,'note'=>'Recurring rules require separate handling; no recurring events were silently imported.']);
