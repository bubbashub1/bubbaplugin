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
if(!$p||strtolower($p['scheme']??'')!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass'])||isset($p['port']))fail(422,'Enter a public HTTPS booking page or calendar feed URL.');
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
if(!str_contains($body,'BEGIN:VCALENDAR')){
 $isBookwhen=(bool)preg_match('/(^|\\.)bookwhen\\.com$/i',$host);
 $isBloom=(bool)preg_match('/(^|\\.)bloombabyclasses\\.com$/i',$host);
 if(!$isBookwhen&&!$isBloom)fail(422,'This page does not contain an iCalendar feed. Try the public timetable text importer.');
 if($isBloom){
  $found=[];
  if(class_exists('DOMDocument')){
   $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($body);libxml_clear_errors();
   $xpath=new DOMXPath($dom);
   foreach($xpath->query('//script[@type="application/ld+json"]') as $script){
    $json=json_decode($script->textContent,true);if(!is_array($json))continue;
    $queue=[$json];
    while($queue){
     $item=array_pop($queue);if(!is_array($item))continue;
     if(in_array('Event',(array)($item['@type']??[]),true)&&!empty($item['startDate'])){
      try{
       $start=(new DateTimeImmutable($item['startDate']))->setTimezone(new DateTimeZone('Europe/London'));
       $end=!empty($item['endDate'])?(new DateTimeImmutable($item['endDate']))->setTimezone(new DateTimeZone('Europe/London')):null;
       $key=($item['@id']??$item['url']??'').$start->format('c');
       $found[$key]=['title'=>(string)($item['name']??'Class session'),'day_of_week'=>(int)$start->format('N'),'start_time'=>$start->format('H:i'),'end_time'=>$end?$end->format('H:i'):'','start_date'=>$start->format('Y-m-d'),'end_date'=>$start->format('Y-m-d'),'frequency'=>'once','url'=>(string)($item['url']??$url),'uid'=>$key];
      }catch(Throwable $ignored){}
     }
     foreach($item as $value)if(is_array($value))$queue[]=$value;
    }
   }
  }
  // Parse self-contained timetable cards, not adjacent dates in a flattened page.
  if(!$found&&isset($dom)){
   $xpath=new DOMXPath($dom);
   $days=['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7];
   $text=trim(preg_replace('/[ \\t]+/u',' ',preg_replace('/\\r\\n?/','\\n',$dom->textContent)));
   // Course headings include the programme and are followed by dates, venue and price.
   $chunks=preg_split('/(?=PAYG\\s+CATERPILLAR\\s+CLUB|WAITING\\s+LIST\\s+CATERPILLAR\\s+CLUB|CATERPILLAR\\s+CLUB\\s+NOV\\/DEC\\s+TERM|BUSY\\s+BEES\\s+CLASS)/iu',$text,-1,PREG_SPLIT_NO_EMPTY);
   foreach($chunks as $chunk){
    if(count($found)>=150)break;
    $chunk=trim($chunk);
    if(!preg_match('/^(PAYG\\s+CATERPILLAR\\s+CLUB|WAITING\\s+LIST\\s+CATERPILLAR\\s+CLUB|CATERPILLAR\\s+CLUB\\s+NOV\\/DEC\\s+TERM|BUSY\\s+BEES\\s+CLASS)/iu',$chunk))continue;
    $isPayg=(bool)preg_match('/^PAYG/iu',$chunk);
    $isWaiting=(bool)preg_match('/^WAITING\\s+LIST/iu',$chunk);
    $title=preg_match('/CATERPILLAR/iu',$chunk)?'Caterpillar Club':'Busy Bees';
    $frequency='weekly';$from=null;$to=null;$day=0;$time='';
    try{
     if(preg_match('/([0-9]{1,2})\\s+(January|February|March|April|May|June|July|August|September|October|November|December)\\s+(20[0-9]{2})\\s*[-–]\\s*([0-9]{1,2})\\s+(January|February|March|April|May|June|July|August|September|October|November|December)\\s+(20[0-9]{2})/iu',$chunk,$m)){
      $from=new DateTimeImmutable("$m[1] $m[2] $m[3]");$to=new DateTimeImmutable("$m[4] $m[5] $m[6]");
     }elseif(preg_match('/([0-9]{1,2})\\s+(January|February|March|April|May|June|July|August|September|October|November|December)\\s+(20[0-9]{2})\\s+([0-9]{1,2})(?::([0-9]{2}))?\\s*(am|pm)/iu',$chunk,$m)){
      $from=new DateTimeImmutable("$m[1] $m[2] $m[3]");$to=$from;$frequency='once';
     }
     if(!$from||!$to||$to<$from)continue;
     if(preg_match('/Every\\s+(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\\s+at\\s+([0-9]{1,2})(?::([0-9]{2}))?\\s*(am|pm)/iu',$chunk,$m)){
      $day=$days[strtolower($m[1])];$hour=(int)$m[2]%12+(strtolower($m[4])==='pm'?12:0);$time=sprintf('%02d:%02d',$hour,(int)($m[3]??0));
     }elseif(preg_match('/\\b([0-9]{1,2})(?::([0-9]{2}))?\\s*(am|pm)/iu',$chunk,$m)){
      $day=(int)$from->format('N');$hour=(int)$m[1]%12+(strtolower($m[3])==='pm'?12:0);$time=sprintf('%02d:%02d',$hour,(int)($m[2]??0));
     }
     if(!$day||!$time)continue;
     $venue='';$postcode='';
     if(preg_match('/(Abbotsham Village Hall|Newport Methodist Church|Tomlin Hall)[^\\n]*?(EX[0-9]{2}\\s*[0-9][A-Z]{2})/iu',$chunk,$vm)){$venue=$vm[1];$postcode=$vm[2];}
     $price='';
     if(preg_match('/Cost:\\s*£\\s*([0-9]+(?:\\.[0-9]{1,2})?)/iu',$chunk,$pm))$price=$pm[1];
     $key=$from->format('Y-m-d').'|'.$day.'|'.$time.'|'.$title.'|'.$venue;
     $found[$key]=['title'=>$title,'day_of_week'=>$day,'start_time'=>$time,'end_time'=>'','start_date'=>$from->format('Y-m-d'),'end_date'=>$to->format('Y-m-d'),'frequency'=>$frequency,'venue_name'=>$venue,'postcode'=>$postcode,'price'=>$frequency==='once'?$price:'','block_price'=>$frequency==='weekly'?$price:'','booking_type'=>$isWaiting?'waiting_list':($isPayg?'payg':'block'),'url'=>$url,'uid'=>$key];
    }catch(Throwable $ignored){}
   }
  }
  if(!$found)fail(422,'Bloom timetable retrieved, but no dated class events were exposed in the public page HTML. Expand Paste your schedule and preview the weekly blocks instead.');
  echo json_encode(['ok'=>true,'data'=>array_slice(array_values($found),0,150),'skipped_recurring'=>0,'source'=>'bloom_public_page','note'=>'Weekly course blocks: verify class names, venue, end times and whether dates are current before adding.']);exit;
 }

 $found=[];
 // Public Bookwhen pages may expose structured Event data without an ICS link.
 if(class_exists('DOMDocument')){
  $dom=new DOMDocument();libxml_use_internal_errors(true);$dom->loadHTML($body);libxml_clear_errors();
  $xpath=new DOMXPath($dom);
  foreach($xpath->query('//script[@type="application/ld+json"]') as $script){
   $json=json_decode($script->textContent,true);if(!is_array($json))continue;
   $queue=[$json];
   while($queue){$item=array_pop($queue);if(!is_array($item))continue;
    $types=(array)($item['@type']??[]);if(in_array('Event',$types,true)&&!empty($item['startDate'])){
     try{$dt=(new DateTimeImmutable($item['startDate']))->setTimezone(new DateTimeZone('Europe/London'));$end=!empty($item['endDate'])?(new DateTimeImmutable($item['endDate']))->setTimezone(new DateTimeZone('Europe/London')):null;
      $key=($item['@id']??$item['url']??'').$dt->format('c');
      $found[$key]=['title'=>(string)($item['name']??'Class session'),'day_of_week'=>(int)$dt->format('N'),'start_time'=>$dt->format('H:i'),'end_time'=>$end?$end->format('H:i'):'','start_date'=>$dt->format('Y-m-d'),'end_date'=>$dt->format('Y-m-d'),'frequency'=>'once','url'=>(string)($item['url']??$url),'uid'=>(string)($item['@id']??$key)];
     }catch(Throwable $ignored){}
    }
    foreach($item as $v){if(is_array($v))$queue[]=$v;}
   }
  }
 }

 // Fallback for server-rendered Bookwhen timetable rows (no JSON-LD).
 // Never infer dates from course names; require a real date heading/attribute.
 if(!$found&&isset($dom)){
  $xpath=new DOMXPath($dom);
  $rows=$xpath->query('//tr | //li | //*[@data-date or @data-start or @data-start-time or @datetime]');
  foreach($rows as $node){
   if(count($found)>=150)break;
   $date='';
   foreach(['data-date','data-start','data-start-time','datetime'] as $attr){if($node->hasAttribute($attr)){$date=trim($node->getAttribute($attr));break;}}
   if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}/',$date))continue;
   $text=trim(preg_replace('/\\s+/u',' ',$node->textContent));
   if(!preg_match('/\\b(\\d{1,2})(?::(\\d{2}))?\\s*(am|pm)\\b/i',$text,$tm))continue;
   $hour=(int)$tm[1]%12+(strtolower($tm[3])==='pm'?12:0);
   $start=sprintf('%02d:%02d',$hour,(int)($tm[2]??0));
   $title=preg_replace('/\\b\\d{1,2}(?::\\d{2})?\\s*(?:am|pm)\\b/i','',$text,1);
   $title=trim($title);
   if($title==='')continue;
   try{$dt=new DateTimeImmutable(substr($date,0,10),new DateTimeZone('Europe/London'));}catch(Throwable $ignored){continue;}
   $key=$dt->format('Y-m-d').'|'.$start.'|'.$title;
   $found[$key]=['title'=>mb_substr($title,0,180),'day_of_week'=>(int)$dt->format('N'),'start_time'=>$start,'end_time'=>'','start_date'=>$dt->format('Y-m-d'),'end_date'=>$dt->format('Y-m-d'),'frequency'=>'once','url'=>$url,'uid'=>$key];
  }
 }
 if(!$found)fail(422,'Bookwhen loads this schedule dynamically. Automatic extraction from this public URL is not supported yet. Use Paste timetable below, or a public iCalendar feed.');
 echo json_encode(['ok'=>true,'data'=>array_slice(array_values($found),0,150),'skipped_recurring'=>0,'source'=>'bookwhen_public_page']);exit;
}
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
