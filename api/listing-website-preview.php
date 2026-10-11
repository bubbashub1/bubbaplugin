<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function fail($message,$code=422){http_response_code($code);echo json_encode(['ok'=>false,'message'=>$message]);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST') fail('POST required',405);
$body=json_decode(file_get_contents('php://input'),true);
$url=trim((string)($body['url']??''));
if(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['https','http'],true))fail('Enter a valid public website URL.');
$host=(string)parse_url($url,PHP_URL_HOST);
if(!$host||strlen($url)>1500||preg_match('/(^localhost$|\\.local$)/i',$host)||parse_url($url,PHP_URL_USER)||parse_url($url,PHP_URL_PASS))fail('Public website URLs only.');
$ips=gethostbynamel($host);
if(!$ips)fail('Website could not be resolved.');
foreach($ips as $ip){if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))fail('Private addresses are not supported.');}
$ch=curl_init($url);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_MAXREDIRS=>0,CURLOPT_USERAGENT=>'BubbaHubListingPreview/1.0',CURLOPT_HTTPHEADER=>['Accept: text/html'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_MAXFILESIZE=>1048576]);
$html=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);
if(!is_string($html)||$status>=300||$status<200||stripos($type,'text/html')===false)fail('This website could not be read. Try a direct class page or enter details manually.');
$html=substr($html,0,1048576);
libxml_use_internal_errors(true);$doc=new DOMDocument();$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);libxml_clear_errors();
$xp=new DOMXPath($doc);
$meta=function($key)use($xp){$nodes=$xp->query('//meta[@name="'.$key.'" or @property="'.$key.'"]/@content');return $nodes&&$nodes->length?trim($nodes->item(0)->nodeValue):'';};
$title=$meta('og:title');if(!$title){$nodes=$doc->getElementsByTagName('title');$title=$nodes->length?trim($nodes->item(0)->textContent):'';}
$description=$meta('og:description')?:$meta('description');
if(!$description){$nodes=$xp->query('//main//p|//article//p');foreach($nodes?:[] as $node){$v=trim(preg_replace('/\\s+/u',' ',$node->textContent));if(mb_strlen($v)>70){$description=$v;break;}}}
$title=mb_substr(strip_tags($title),0,160);$description=mb_substr(strip_tags($description),0,1200);
$keywords=$meta('keywords');
$search=mb_strtolower($title.' '.$description.' '.$keywords);
$taxonomy=[
 'Baby classes'=>['baby class','baby massage','baby yoga','newborn','baby sensory'],
 'Toddler groups'=>['toddler','stay and play','playgroup','pre-school','preschool'],
 'Music & movement'=>['music','singing','dance','movement','rhythm'],
 'Swimming'=>['swim','aquatic','pool','water confidence'],
 'Soft play'=>['soft play','indoor play','play centre'],
 'Family activities'=>['family','children','kids','parent and child'],
];
$categories=[];
foreach($taxonomy as $category=>$phrases){foreach($phrases as $phrase){if(mb_strpos($search,$phrase)!==false){$categories[]=$category;break;}}}
$tagVocabulary=['baby massage','baby yoga','baby sensory','newborn','bonding','toddler','playgroup','stay and play','music','singing','dance','swimming','water confidence','soft play','outdoor','nature','arts and crafts','sensory','pregnancy','antenatal','postnatal','breastfeeding','parent support','siblings','storytime','library','free'];
$detected=[];foreach($tagVocabulary as $term){if(mb_strpos($search,$term)!==false)$detected[]=$term;}
$tags=array_slice(array_values(array_unique(array_filter(array_map('trim',explode(',',$keywords))))),0,12);
$tags=array_slice(array_values(array_unique(array_merge($detected,$tags))),0,12);
echo json_encode(['ok'=>true,'title'=>$title,'description'=>$description,'categories'=>$categories,'tags'=>$tags,'source'=>$url,'message'=>'Review and edit all suggested information before saving.'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
