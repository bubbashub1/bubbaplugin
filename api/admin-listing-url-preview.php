<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function reply(int $code,array $data): never {http_response_code($code);echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
if(empty($_SESSION['bh_admin_authenticated']))reply(401,['ok'=>false,'error'=>'Admin login required']);
if($_SERVER['REQUEST_METHOD']!=='POST')reply(405,['ok'=>false,'error'=>'POST required']);
$input=json_decode((string)file_get_contents('php://input'),true);
$url=trim((string)($input['url']??''));
$p=parse_url($url);
if(!$p||strtolower((string)($p['scheme']??''))!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass'])||isset($p['port'])||strlen($url)>1000)reply(422,['ok'=>false,'error'=>'Enter a public HTTPS page URL']);
$host=strtolower((string)$p['host']);
if(!preg_match('/^[a-z0-9.-]+$/',$host)||!str_contains($host,'.'))reply(422,['ok'=>false,'error'=>'Invalid host']);
$ips=gethostbynamel($host);
if(!$ips)reply(422,['ok'=>false,'error'=>'Host could not be resolved']);
foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))reply(422,['ok'=>false,'error'=>'Public websites only']);
if(!function_exists('curl_init')||!class_exists('DOMDocument'))reply(503,['ok'=>false,'error'=>'Page import is not available on this server']);
$ch=curl_init($url);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]],CURLOPT_MAXFILESIZE=>1048576,CURLOPT_USERAGENT=>'BubbaHubListingImporter/1.0']);
$html=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);
if(!is_string($html)||strlen($html)>1048576||$code!==200||!preg_match('~text/html|application/xhtml\\+xml~i',$type))reply(422,['ok'=>false,'error'=>'This page cannot be read automatically; enter details manually']);
$dom=new DOMDocument();$previous=libxml_use_internal_errors(true);$dom->loadHTML($html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($previous);
$xp=new DOMXPath($dom);
$first=static function(string $query)use($xp): string {$nodes=$xp->query($query);return $nodes&&$nodes->length?trim((string)$nodes->item(0)->nodeValue):'';};
$meta=static function(string $key)use($xp): string {$q="//meta[translate(@property,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='$key' or translate(@name,'ABCDEFGHIJKLMNOPQRSTUVWXYZ','abcdefghijklmnopqrstuvwxyz')='$key']/@content";$nodes=$xp->query($q);return $nodes&&$nodes->length?trim((string)$nodes->item(0)->nodeValue):'';};
$event=[];
foreach($xp->query('//script[@type="application/ld+json"]') as $node){$data=json_decode((string)$node->textContent,true);if(!is_array($data))continue;$stack=[$data];while($stack){$item=array_pop($stack);if(!is_array($item))continue;$types=(array)($item['@type']??[]);if(array_intersect($types,['Event','Course','EducationEvent'])){$event=$item;break 2;}foreach($item as $v)if(is_array($v))$stack[]=$v;}}
$clean=static fn($v,int $limit): string => mb_substr(trim(preg_replace('/\\s+/u',' ',strip_tags(is_string($v)?$v:''))),0,$limit);
$title=$clean($event['name']??$meta('og:title')?:$first('//h1[1]')?:$first('//title[1]'),180);
$description=$clean($event['description']??$meta('og:description')?:$meta('description'),4000);
$location='';if(isset($event['location'])){$loc=$event['location'];$location=$clean(is_array($loc)?($loc['name']??''):$loc,255);}
$category='';$keywords=$meta('keywords');if($keywords!=='')$category=$clean(explode(',',$keywords)[0],100);
reply(200,['ok'=>true,'data'=>['title'=>$title,'description'=>$description,'venue_name'=>$location,'category'=>$category,'website'=>$url,'booking_url'=>$url],'note'=>'Metadata only. Review all imported details before saving. No AI used.']);
