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
 $isTurtleTots=(bool)preg_match('/(^|\.)turtletots\.com$/i',$host);
 if($isTurtleTots){
  if(!class_exists('DOMDocument'))fail(422,'Turtle Tots timetable parsing requires the PHP DOM extension.');
  $dom=new DOMDocument();$prev=libxml_use_internal_errors(true);$dom->loadHTML('<?xml encoding="utf-8" ?>'.$body);libxml_clear_errors();libxml_use_internal_errors($prev);
  $xp=new DOMXPath($dom);$iframeUrl='';
  foreach($xp->query('//iframe[@src]') as $frame){
   $src=html_entity_decode(trim($frame->getAttribute('src')),ENT_QUOTES|ENT_HTML5,'UTF-8');
   $parsed=parse_url($src);
   if($parsed&&strtolower($parsed['scheme']??'')==='https'&&strtolower($parsed['host']??'')==='www.thinksmartsoftwareuk.com'&&($parsed['path']??'')==='/ocr/single_class_listings_view.php'){$iframeUrl=$src;break;}
  }
  if(!$iframeUrl)fail(422,'Turtle Tots booking timetable iframe was not found on this page.');
  $iframeHost='www.thinksmartsoftwareuk.com';$iframeIps=gethostbynamel($iframeHost);
  if(!$iframeIps)fail(422,'Booking timetable host could not be resolved.');
  foreach($iframeIps as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))fail(422,'Booking timetable host is not public.');
  $iframeCurl=curl_init($iframeUrl);
  curl_setopt_array($iframeCurl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_MAXREDIRS=>0,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$iframeHost.':443:'.$iframeIps[0]],CURLOPT_USERAGENT=>'BubbaHubCalendarImporter/1.0',CURLOPT_MAXFILESIZE=>1048576]);
  $iframeBody=curl_exec($iframeCurl);$iframeCode=(int)curl_getinfo($iframeCurl,CURLINFO_HTTP_CODE);curl_close($iframeCurl);
  if(!is_string($iframeBody)||strlen($iframeBody)>1048576||$iframeCode!==200)fail(422,'Embedded booking timetable could not be retrieved (HTTP '.$iframeCode.').');
  $inner=new DOMDocument();$prev=libxml_use_internal_errors(true);$inner->loadHTML('<?xml encoding="utf-8" ?>'.$iframeBody);libxml_clear_errors();libxml_use_internal_errors($prev);
  $ix=new DOMXPath($inner);$found=[];$day=0;$days=['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7];
  // ThinkSmart themes use accordion buttons/divs rather than consistent heading tags.
  // Traverse text in document order so a day label applies to its following rows.
  foreach($ix->query('//body//*[self::h1 or self::h2 or self::h3 or self::h4 or self::button or self::summary or self::a or self::div or self::span or self::tr]') as $node){
   if($node->nodeName!=='tr'){
    $heading=strtolower(trim(preg_replace('/\s+/u',' ',$node->textContent)));
    if(strlen($heading)>40)continue;
    foreach($days as $word=>$number)if(preg_match('/^'.preg_quote($word,'/').'$/i',$heading)){$day=$number;break;}
    continue;
   }
   $cells=$ix->query('./td|./th',$node);if($cells->length<4)continue;
   $values=[];foreach($cells as $cell)$values[]=trim(preg_replace('/\s+/u',' ',html_entity_decode($cell->textContent,ENT_QUOTES|ENT_HTML5,'UTF-8')));
   $rowText=implode(' | ',$values);
   foreach($days as $word=>$number)if(preg_match('/^\s*'.preg_quote($word,'/').'\s*$/i',$rowText)){$day=$number;continue 2;}
   if(!$day)continue;
   $timeIndex=-1;$tm=[];
   foreach($values as $i=>$value)if(preg_match('/\b(\d{1,2}):(\d{2})\s*(AM|PM)\b/i',$value,$tm)){$timeIndex=$i;break;}
   if($timeIndex<0)continue;
   $hour=(int)$tm[1];if($hour<1||$hour>12)continue;$hour=$hour%12+(strtoupper($tm[3])==='PM'?12:0);
   $start=sprintf('%02d:%02d',$hour,(int)$tm[2]);$duration=0;
   foreach($values as $value)if(preg_match('/\b(\d{1,3})\s*(?:minutes?|mins?)\b/i',$value,$dm)){$duration=(int)$dm[1];break;}
   if($duration<1||$duration>240)continue;
   $end=(new DateTimeImmutable('2000-01-01 '.$start.':00'))->modify('+'.$duration.' minutes')->format('H:i');
   $dates=[];
   foreach($values as $value)if(preg_match_all('/\b(\d{1,2})\/(\d{1,2})\/(\d{4})\b/',$value,$matches,PREG_SET_ORDER))foreach($matches as $m){
    if(checkdate((int)$m[2],(int)$m[1],(int)$m[3]))$dates[]=sprintf('%04d-%02d-%02d',(int)$m[3],(int)$m[2],(int)$m[1]);
   }
   if(count($dates)<2)continue;
   $from=$dates[0];$to=$dates[count($dates)-1];if($to<$from)continue;
   $title=trim($values[$timeIndex+1]??'');if($title===''||preg_match('/^\d+\s*minutes?$/i',$title))continue;
   $price='';$prices=[];foreach($values as $value)if(preg_match('/£\s*(\d+(?:\.\d{1,2})?)/u',$value,$pm))$prices[]=$pm[1];
   if(count($prices)>1)$price=$prices[1];elseif(count($prices)===1)$price=$prices[0];
   $booking=$iframeUrl;foreach($ix->query('./td//a[@href]',$node) as $anchor){$href=trim($anchor->getAttribute('href'));if(str_starts_with($href,'https://')){$booking=$href;break;}}
   $waitlist=(bool)preg_match('/waitlist/i',$node->textContent);
   $key=implode('|',[$day,$start,$title,$from,$to]);
   $found[$key]=['title'=>$title,'class_name'=>$title,'day_of_week'=>$day,'start_time'=>$start,'end_time'=>$end,'start_date'=>$from,'end_date'=>$to,'frequency'=>'weekly','price'=>$price,'booking_url'=>$booking,'url'=>$url,'uid'=>$key,'availability'=>$waitlist?'waitlist':'bookable'];
   if(count($found)>=150)break;
  }
  // ThinkSmart also renders responsive layouts as labelled DIVs, not table rows.
  // Parse only complete, bounded class records with their own start, duration and two dates.
  if(!$found){
   $copy=new DOMDocument();$prev=libxml_use_internal_errors(true);$copy->loadHTML('<?xml encoding="utf-8" ?>'.$iframeBody);libxml_clear_errors();libxml_use_internal_errors($prev);
   $cx=new DOMXPath($copy);
   foreach($cx->query('//script|//style|//nav|//footer|//noscript') as $remove)$remove->parentNode?->removeChild($remove);
   foreach($cx->query('//tr|//td|//th|//div|//p|//h1|//h2|//h3|//h4|//button|//li|//section|//article') as $element){
    if($element->parentNode)$element->parentNode->insertBefore($copy->createTextNode("\n"),$element);
   }
   $flat=html_entity_decode($copy->textContent,ENT_QUOTES|ENT_HTML5,'UTF-8');
   $flat=preg_replace('/[ \t]+/u',' ',$flat);
   $chunks=preg_split('/(?=\bTime\s+\d{1,2}:\d{2}\s*(?:AM|PM)\b)/iu',$flat);
   $currentDay=0;
   foreach($chunks as $chunk){
    $prefix=trim($chunk);
    if(!preg_match('/^Time\s+(\d{1,2}):(\d{2})\s*(AM|PM)\b/iu',$prefix,$tm)){
     foreach($days as $word=>$number)if(preg_match('/\b'.preg_quote($word,'/').'\b/i',$prefix))$currentDay=$number;
     continue;
    }
    // The previous record can end with the next accordion day heading.
    $record=$prefix;
    $recordDay=$currentDay;
    foreach($days as $word=>$number){
     if(preg_match('/\b'.preg_quote($word,'/').'\b/i',$record,$dayMatch,PREG_OFFSET_CAPTURE)){
      $position=$dayMatch[0][1];
      if($position>25){$record=substr($record,0,$position);$currentDay=$number;}
     }
    }
    if(!$recordDay)continue;
    $hour=(int)$tm[1];if($hour<1||$hour>12)continue;
    $hour=$hour%12+(strtoupper($tm[3])==='PM'?12:0);
    $start=sprintf('%02d:%02d',$hour,(int)$tm[2]);
    if(!preg_match('/\bLesson\s+Format\s+(.+?)\s+Duration\s+(\d{1,3})\s*minutes?\b/isu',$record,$details))continue;
    $title=trim(preg_replace('/\s+/u',' ',$details[1]));$duration=(int)$details[2];
    if($title===''||mb_strlen($title)>150||$duration<1||$duration>240)continue;
    if(!preg_match('/\bNext\s+session\s+(\d{1,2})\/(\d{1,2})\/(\d{4})\b/iu',$record,$first))continue;
    if(!preg_match('/\bLast\s+session\s+(\d{1,2})\/(\d{1,2})\/(\d{4})\b/iu',$record,$last))continue;
    if(!checkdate((int)$first[2],(int)$first[1],(int)$first[3])||!checkdate((int)$last[2],(int)$last[1],(int)$last[3]))continue;
    $from=sprintf('%04d-%02d-%02d',(int)$first[3],(int)$first[2],(int)$first[1]);
    $to=sprintf('%04d-%02d-%02d',(int)$last[3],(int)$last[2],(int)$last[1]);
    if($to<$from)continue;
    $end=(new DateTimeImmutable('2000-01-01 '.$start.':00'))->modify('+'.$duration.' minutes')->format('H:i');
    $price='';if(preg_match('/\bPrice\s+Per\s+Class\s+£\s*(\d+(?:\.\d{1,2})?)/iu',$record,$pm))$price=$pm[1];
    $waitlist=(bool)preg_match('/Register\s+for\s+Waitlist/i',$record);
    $key=implode('|',[$recordDay,$start,$title,$from,$to]);
    $found[$key]=['title'=>$title,'class_name'=>$title,'day_of_week'=>$recordDay,'start_time'=>$start,'end_time'=>$end,'start_date'=>$from,'end_date'=>$to,'frequency'=>'weekly','price'=>$price,'booking_url'=>$iframeUrl,'url'=>$url,'uid'=>$key,'availability'=>$waitlist?'waitlist':'bookable'];
    if(count($found)>=150)break;
   }
  }
  if(!$found)fail(422,'Embedded Turtle Tots timetable retrieved but no complete dated class rows were recognised.');
  echo json_encode(['ok'=>true,'data'=>array_values($found),'skipped_recurring'=>0,'source'=>'turtle_tots_embedded','note'=>'Imported from embedded ThinkSmart timetable. Per-class prices used; waitlist classes flagged. Check venue and dates before adding.']);exit;
 }
 $isBabySensory=(bool)preg_match('/(^|\.)babysensory\.com$/i',$host);
 if($isBabySensory){
  if(!class_exists('DOMDocument'))fail(422,'Baby Sensory timetable parsing is unavailable on this server.');
  $dom=new DOMDocument();$prev=libxml_use_internal_errors(true);
  $dom->loadHTML('<?xml encoding="utf-8" ?>'.$body);libxml_clear_errors();libxml_use_internal_errors($prev);
  $xpath=new DOMXPath($dom);$found=[];
  $days=['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7];
  foreach($xpath->query('//tr') as $tr){
   $cells=$xpath->query('./td',$tr);
   if($cells->length<4)continue;
   $columns=[];
   foreach($cells as $cell)$columns[]=trim(preg_replace('/\s+/u',' ',html_entity_decode($cell->textContent,ENT_QUOTES|ENT_HTML5,'UTF-8')));
   $dayText=$columns[1]??'';$timeText=$columns[2]??'';$ageText=$columns[3]??'';
   if(!preg_match('/\b(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b/i',$dayText,$dm))continue;
   if(!preg_match('/\b([01]?\d|2[0-3]):([0-5]\d)\s*[-–]\s*([01]?\d|2[0-3]):([0-5]\d)\b/u',$timeText,$tm))continue;
   $start=sprintf('%02d:%02d',(int)$tm[1],(int)$tm[2]);
   $end=sprintf('%02d:%02d',(int)$tm[3],(int)$tm[4]);
   if($end<=$start)continue;
   $dates=[];
   if(preg_match_all('/\b(\d{1,2})\s+([A-Za-z]{3,9})\s+(\d{2,4})\b/u',$dayText,$dateMatches,PREG_SET_ORDER)){
    foreach($dateMatches as $dateMatch){
     try{$year=(int)$dateMatch[3];if($year<100)$year+=2000;
      $dt=DateTimeImmutable::createFromFormat('!j M Y',$dateMatch[1].' '.substr($dateMatch[2],0,3).' '.$year);
      if($dt)$dates[]=$dt->format('Y-m-d');
     }catch(Throwable $ignored){}
    }
   }
   if(count($dates)!==2)continue;
   $day=$days[strtolower($dm[1])];$venue=$columns[0]??'';
   $venueName=trim(preg_split('/\b\d{1,4}\s+[A-Za-z]/u',$venue,2)[0]);
   if($venueName==='')$venueName=$venue;
   $title=trim($ageText)!==''?trim($ageText):'Baby Sensory class';
   $frequency=$dates[0]===$dates[1]?'once':'weekly';
   $key=implode('|',[$venue,$day,$start,$end,$dates[0],$dates[1],$title]);
   $found[$key]=['title'=>$title,'day_of_week'=>$day,'start_time'=>$start,'end_time'=>$end,
    'start_date'=>$dates[0],'end_date'=>$dates[1],'frequency'=>$frequency,
    'venue_name'=>$venueName,'venue_address'=>$venue,'url'=>$url,'uid'=>$key];
   if(count($found)>=150)break;
  }
  if(!$found)fail(422,'Baby Sensory timetable loads class rows dynamically. No verified sessions were available in the public HTML. Open the timetable in your browser and paste the class rows, or provide a calendar feed. No times or venues were guessed.');
  echo json_encode(['ok'=>true,'data'=>array_values($found),'skipped_recurring'=>0,'source'=>'baby_sensory_table','note'=>'Times, dates and venues extracted per timetable row. Check term breaks and preview before saving.']);exit;
 }
 $isBookwhen=(bool)preg_match('/(^|\\.)bookwhen\\.com$/i',$host);
 $isBloom=(bool)preg_match('/(^|\\.)bloombabyclasses\\.com$/i',$host);
 if(!$isBookwhen&&!$isBloom){
  // Generic public HTML timetable: parse weekday sections and start times.
  // Do not invent end times or dates. Keep the user-facing preview mandatory.
  if(!class_exists('DOMDocument'))fail(422,'HTML timetable parsing is unavailable on this server.');
  $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);
  $dom->loadHTML('<?xml encoding="utf-8" ?>'.$body);libxml_clear_errors();libxml_use_internal_errors($previous);
  $xpath=new DOMXPath($dom);
  foreach($xpath->query('//script|//style|//nav|//footer|//header|//noscript') as $node){$node->parentNode?->removeChild($node);}
  foreach($xpath->query('//br|//p|//div|//h1|//h2|//h3|//h4|//li|//tr|//td|//section') as $node){
   if($node->parentNode)$node->parentNode->insertBefore($dom->createTextNode("\n"),$node);
  }
  $text=html_entity_decode($dom->textContent,ENT_QUOTES|ENT_HTML5,'UTF-8');
  $lines=preg_split('/\n+/u',$text);$found=[];$day=0;$context='';
  $days=['monday'=>1,'tuesday'=>2,'wednesday'=>3,'thursday'=>4,'friday'=>5,'saturday'=>6,'sunday'=>7];
  foreach($lines as $line){
   $line=trim(preg_replace('/\s+/u',' ',$line));if($line===''||mb_strlen($line)>250)continue;
   if(preg_match('/^(?:every\s+)?(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)s?\b[\s:,-]*(.*)$/iu',$line,$dm)){
    $day=$days[strtolower($dm[1])];$line=trim($dm[2]);$context='';
    if($line==='')continue;
   }
   if(!$day)continue;
   if(!preg_match_all('/\b(\d{1,2})(?::(\d{2}))?\s*(am|pm)\b/iu',$line,$matches,PREG_SET_ORDER)){
    if(preg_match('/\b(pilates|somatics|yoga|class|online|zoom|studio|with\s+\w+)\b/iu',$line))$context=mb_substr($line,0,120);
    continue;
   }
   foreach($matches as $m){
    $hour=(int)$m[1];$minute=(int)($m[2]??0);
    if($hour<1||$hour>12||$minute>59)continue;
    $hour=$hour%12+(strtolower($m[3])==='pm'?12:0);
    $start=sprintf('%02d:%02d',$hour,$minute);
    $title=trim(preg_replace('/\b\d{1,2}(?::\d{2})?\s*(?:am|pm)\b/iu','',$line));
    $title=trim($title," \t\n\r\0\x0B,|:-–—");
    if($title===''||mb_strlen($title)<4)$title=$context;
    if($title==='')$title='Class name to confirm';
    $key=$day.'|'.$start.'|'.$title;
    $found[$key]=['title'=>mb_substr($title,0,180),'day_of_week'=>$day,'start_time'=>$start,'end_time'=>'','start_date'=>'','end_date'=>'','frequency'=>'weekly','url'=>$url,'uid'=>$key];
    if(count($found)>=150)break 2;
   }
  }
  if(!$found)fail(422,'Website retrieved (HTTP 200), but no weekly timetable was recognised. Try Paste Timetable or check whether the schedule loads only in JavaScript.');
  echo json_encode(['ok'=>true,'data'=>array_values($found),'skipped_recurring'=>0,'source'=>'generic_html_timetable','note'=>'Weekly times found on the public webpage. Confirm class names, venues and missing end times before saving.']);exit;
 }
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
   $chunks=preg_split('/(?=(?:PAYG\\s+)?(?:BUSY\\s+BEES|CATERPILLAR\\s+CLUB)|WAITING\\s+LIST\\s+CATERPILLAR\\s+CLUB)/iu',$text,-1,PREG_SPLIT_NO_EMPTY);
   foreach($chunks as $chunk){
    if(count($found)>=150)break;
    $chunk=trim($chunk);
    if(!preg_match('/^(?:(?:PAYG\\s+)?(?:BUSY\\s+BEES|CATERPILLAR\\s+CLUB)|WAITING\\s+LIST\\s+CATERPILLAR\\s+CLUB)/iu',$chunk))continue;
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
