<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_schools_json(int $status,array $data): never{
  http_response_code($status);
  echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
  exit;
}

$lat=filter_input(INPUT_GET,'lat',FILTER_VALIDATE_FLOAT);
$lon=filter_input(INPUT_GET,'lon',FILTER_VALIDATE_FLOAT);
if($lat===false||$lon===false||$lat===null||$lon===null||$lat < 49 || $lat > 59 || $lon < -9 || $lon > 3){
  bh_schools_json(422,['ok'=>false,'error'=>'invalid_location']);
}

$query='[out:json][timeout:20];(nwr["amenity"="school"](around:30000,'.((float)$lat).','.((float)$lon).'););out center tags;';
$ch=curl_init('https://overpass-api.de/api/interpreter');
curl_setopt_array($ch,[
  CURLOPT_POST=>true,
  CURLOPT_POSTFIELDS=>$query,
  CURLOPT_RETURNTRANSFER=>true,
  CURLOPT_TIMEOUT=>25,
  CURLOPT_CONNECTTIMEOUT=>8,
  CURLOPT_HTTPHEADER=>['Content-Type: text/plain; charset=utf-8','User-Agent: BubbaHub/1.0 local-schools'],
]);
$raw=curl_exec($ch);$err=curl_error($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);curl_close($ch);
if($raw===false||$code<200||$code>=300)bh_schools_json(502,['ok'=>false,'error'=>'school_source_unavailable','message'=>'The school directory is temporarily unavailable.']);

$payload=json_decode($raw,true);
if(!is_array($payload)||!isset($payload['elements']))bh_schools_json(502,['ok'=>false,'error'=>'invalid_school_source']);

$distance=function(float $a,float $b)use($lat,$lon):float{
  $r=3958.7613;$p1=deg2rad((float)$lat);$p2=deg2rad($a);$dp=deg2rad($a-(float)$lat);$dl=deg2rad($b-(float)$lon);
  $x=sin($dp/2)**2+cos($p1)*cos($p2)*sin($dl/2)**2;
  return $r*2*asin(min(1,sqrt($x)));
};

$out=[];
foreach($payload['elements'] as $el){
  $t=is_array($el['tags']??null)?$el['tags']:[];
  $name=trim((string)($t['name']??''));
  $pos=$el['center']??$el;
  $slat=isset($pos['lat'])?(float)$pos['lat']:null;$slon=isset($pos['lon'])?(float)$pos['lon']:null;
  if($name===''||$slat===null||$slon===null)continue;
  $address=trim(implode(', ',array_filter([
    $t['addr:housenumber']??'',$t['addr:street']??'',$t['addr:place']??'',
    $t['addr:city']??$t['addr:town']??$t['addr:village']??'',$t['addr:postcode']??''
  ])));
  $website=trim((string)($t['website']??$t['contact:website']??''));
  $phone=trim((string)($t['phone']??$t['contact:phone']??''));
  $urn=trim((string)($t['ref:GB:school']??$t['ref:ofsted']??$t['ref:URN']??''));
  $ofstedUrl=$urn!==''?'https://reports.ofsted.gov.uk/provider/21/'.$urn:'';
  $rating=trim((string)($t['ofsted:rating']??$t['ofsted_rating']??''));
  $openDays=trim((string)($t['open_days']??$t['opening_hours']??''));
  $openDaysUrl=$openDays!==''?$openDays:'';
  $out[]=[
    'name'=>$name,'latitude'=>$slat,'longitude'=>$slon,
    'distance_miles'=>round($distance($slat,$slon),1),
    'address'=>$address,'postcode'=>(string)($t['addr:postcode']??''),
    'phone'=>$phone,'website'=>$website,'open_days_url'=>$openDaysUrl,
    'ofsted_url'=>$ofstedUrl,'ofsted_rating'=>$rating
  ];
}
usort($out,fn($a,$b)=>$a['distance_miles']<=>$b['distance_miles']);
$out=array_slice($out,0,10);

bh_schools_json(200,[
  'ok'=>true,
  'data'=>$out,
  'count'=>count($out),
  'source'=>'OpenStreetMap',
  'note'=>'Ofsted information is shown when it is present in the school source; the Ofsted link opens the official inspection service when a URN is available.'
]);
?>