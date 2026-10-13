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
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_MAXREDIRS=>0,CURLOPT_USERAGENT=>'BubbaHubListingPreview/1.0',CURLOPT_HTTPHEADER=>['Accept: text/html'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_MAXFILESIZE=>1048576,CURLOPT_RESOLVE=>[$host.':'.(strtolower((string)parse_url($url,PHP_URL_SCHEME))==='https'?'443':'80').':'.$ips[0]]]);
$html=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);
if(!is_string($html)||$status>=300||$status<200||stripos($type,'text/html')===false)fail('This website could not be read. Try a direct class page or enter details manually.');
$html=substr($html,0,1048576);
libxml_use_internal_errors(true);$doc=new DOMDocument();$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);libxml_clear_errors();
$xp=new DOMXPath($doc);
$meta=function($key)use($xp){$nodes=$xp->query('//meta[@name="'.$key.'" or @property="'.$key.'"]/@content');return $nodes&&$nodes->length?trim($nodes->item(0)->nodeValue):'';};
$title=$meta('og:title');if(!$title){$nodes=$doc->getElementsByTagName('title');$title=$nodes->length?trim($nodes->item(0)->textContent):'';}
$summary=$meta('og:description')?:$meta('description');
$description='';
$bestScore=-999;
$bestBlocks=[];
$scopes=['//article','//*[@itemprop="articleBody"]','//*[contains(concat(" ",normalize-space(@class)," ")," entry-content ")]','//*[contains(concat(" ",normalize-space(@class)," ")," page-content ")]','//main'];
foreach($scopes as $scope){
 $containers=$xp->query($scope);
 if(!$containers)continue;
 foreach($containers as $container){
  $paragraphs=$xp->query('.//p',$container);
  if(!$paragraphs)continue;
  $candidate=[];$score=0;
  foreach($paragraphs as $node){
   $skip=false;$parent=$node;
   while($parent&&$parent!==$container){
    if($parent instanceof DOMElement){
     $tag=strtolower($parent->tagName);
     $identity=strtolower($parent->getAttribute('class').' '.$parent->getAttribute('id'));
     if(in_array($tag,['nav','footer','header','aside','form'],true)||preg_match('/\\b(location|venue|address|postcode|directory|footer|sidebar|map|booking-widget|find-a-class)\\b/i',$identity)){$skip=true;break;}
    }
    $parent=$parent->parentNode;
   }
   if($skip)continue;
   $v=trim((string)preg_replace('/\\s+/u',' ',$node->textContent));
   $len=mb_strlen($v);
   if($len<75||$len>1600)continue;
   if(preg_match('/^(cookie|privacy policy|terms and conditions|subscribe|sign up|book now|follow us)/iu',$v))continue;
   if(preg_match('/\\b(?:road|street|avenue|postcode|post code|church|community centre|village hall|sports hall|leisure centre)\\b/iu',$v)&&$len<210)continue;
   if(preg_match('/\\b[A-Z]{1,2}[0-9][A-Z0-9]?\\s*[0-9][A-Z]{2}\\b/u',$v))continue;
   $relevance=preg_match_all('/\\b(class(?:es)?|bab(?:y|ies)|toddler(?:s)?|sensory|storytell(?:ing)?|play|music|movement|parent(?:s)?|development|session(?:s)?|pregnan(?:t|cy)|massage|yoga|learn|children)\\b/iu',$v);
   if($relevance<2)continue;
   if(!in_array($v,$candidate,true)){$candidate[]=$v;$score+=min(6,$relevance)*10+min(300,$len)/50;}
   if(count($candidate)>=8)break;
  }
  if($score>$bestScore&&count($candidate)>=2){$bestScore=$score;$bestBlocks=$candidate;}
 }
}
$description=$bestBlocks?implode("\n\n",$bestBlocks):$summary;
$title=mb_substr(strip_tags($title),0,160);
$description=mb_substr(strip_tags($description),0,4000);
// Enrich the preview with a same-host homepage and About page (no cross-site crawling).
$origin=strtolower((string)parse_url($url,PHP_URL_SCHEME)).'://'.$host;
$aboutUrls=[];
foreach($xp->query('//a[@href]')?:[] as $link){
 $label=mb_strtolower(trim((string)$link->textContent));
 $href=trim((string)$link->getAttribute('href'));
 if(!preg_match('/\\b(about us|about me|our story|meet the team|who we are|meet your leader|about)\\b/u',$label.' '.$href))continue;
 if(str_starts_with($href,'/'))$candidate=$origin.$href;
 elseif(str_starts_with($href,'https://'))$candidate=$href;
 else continue;
 $parts=parse_url($candidate);
 if(!$parts||strtolower((string)($parts['host']??''))!==strtolower($host)||isset($parts['port'])||isset($parts['user'])||isset($parts['pass']))continue;
 $aboutUrls[]=$candidate;
 if(count($aboutUrls)>=2)break;
}
$pages=array_values(array_unique(array_merge([$origin.'/'],$aboutUrls)));
$extra=[];
foreach(array_slice($pages,0,3) as $pageUrl){
 if($pageUrl===$url)continue;
 $pageCh=curl_init($pageUrl);
 curl_setopt_array($pageCh,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>4,CURLOPT_TIMEOUT=>8,CURLOPT_USERAGENT=>'BubbaHubListingPreview/1.0',CURLOPT_HTTPHEADER=>['Accept: text/html'],CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_MAXFILESIZE=>1048576,CURLOPT_RESOLVE=>[$host.':443:'.$ips[0]]]);
 $pageHtml=curl_exec($pageCh);$pageStatus=(int)curl_getinfo($pageCh,CURLINFO_RESPONSE_CODE);$pageType=(string)curl_getinfo($pageCh,CURLINFO_CONTENT_TYPE);curl_close($pageCh);
 if(!is_string($pageHtml)||strlen($pageHtml)>1048576||$pageStatus!==200||stripos($pageType,'text/html')===false)continue;
 $pageDoc=new DOMDocument();$old=libxml_use_internal_errors(true);$pageDoc->loadHTML($pageHtml,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($old);
 $pageXp=new DOMXPath($pageDoc);$pageBlocks=[];
 foreach($pageXp->query('//main//p|//article//p')?:[] as $paragraph){
  $value=trim((string)preg_replace('/\\s+/u',' ',$paragraph->textContent));
  if(mb_strlen($value)<90||mb_strlen($value)>1200)continue;
  if(preg_match('/\\b[A-Z]{1,2}[0-9][A-Z0-9]?\\s*[0-9][A-Z]{2}\\b/u',$value))continue;
  if(preg_match('/\\b(?:road|street|postcode|venues|locations)\\b/iu',$value)&&mb_strlen($value)<220)continue;
  if(!preg_match('/\\b(baby|babies|children|toddler|class|sensory|play|parent|story|family|families|development|pregnancy|experience|qualified|founder|leader)\\b/iu',$value))continue;
  if(!in_array($value,$pageBlocks,true))$pageBlocks[]=$value;
  if(count($pageBlocks)>=4)break;
 }
 if($pageBlocks)$extra[]=implode("\n\n",$pageBlocks);
}
$aboutDescription=mb_substr(implode("\n\n",$extra),0,3000);
// Resolve only public HTTPS images on the same website; never fetch image bytes server-side.
$imageCandidates=[];
foreach(['logo','og:image','twitter:image'] as $property){
 $value=$meta($property);
 if($value!=='')$imageCandidates[]=$value;
}
foreach($xp->query('//link[contains(translate(@rel,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"icon")]/@href')?:[] as $node)$imageCandidates[]=$node->nodeValue;
$imageUrl='';
foreach($imageCandidates as $candidate){
 $candidate=trim((string)$candidate);
 if(str_starts_with($candidate,'//'))$candidate='https:'.$candidate;
 elseif(str_starts_with($candidate,'/'))$candidate=$origin.$candidate;
 elseif(!str_starts_with($candidate,'https://'))continue;
 $parts=parse_url($candidate);
 if(!$parts||strtolower((string)($parts['host']??''))!==strtolower($host)||isset($parts['user'])||isset($parts['pass'])||isset($parts['port']))continue;
 $imageUrl=$candidate;break;
}
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
echo json_encode(['ok'=>true,'title'=>$title,'description'=>$description,'about_description'=>$aboutDescription,'image_url'=>$imageUrl,'categories'=>$categories,'tags'=>$tags,'source'=>$url,'message'=>'Review and edit all suggested information before saving.'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
