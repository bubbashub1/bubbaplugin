<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function respond(int $status, array $data): void { http_response_code($status); echo json_encode($data); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, ['ok'=>false,'error'=>'method_not_allowed']);
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
if (parse_url($origin, PHP_URL_SCHEME) !== 'https' || strtolower((string)parse_url($origin, PHP_URL_HOST)) !== explode(':',$host)[0]) respond(403,['ok'=>false,'error'=>'invalid_origin']);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 2048) respond(413,['ok'=>false,'error'=>'request_too_large']);
$input=json_decode((string)file_get_contents('php://input'),true);
$url=trim((string)($input['url'] ?? ''));
$p=parse_url($url);
if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['port']) || isset($p['user']) || isset($p['pass']) || strlen($url)>500) respond(422,['ok'=>false,'error'=>'public_https_url_required']);
$domain=strtolower((string)$p['host']);
if (!preg_match('/^[a-z0-9.-]+$/',$domain) || !str_contains($domain,'.')) respond(422,['ok'=>false,'error'=>'invalid_domain']);
$ips=gethostbynamel($domain);
if (!$ips) respond(422,['ok'=>false,'error'=>'host_not_found']);
foreach ($ips as $ip) if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) respond(422,['ok'=>false,'error'=>'public_host_required']);
if (!function_exists('curl_init') || !class_exists('DOMDocument')) respond(503,['ok'=>false,'error'=>'lookup_unavailable']);
$ch=curl_init($url);
curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$domain.':443:'.$ips[0]],CURLOPT_MAXFILESIZE=>1048576,CURLOPT_USERAGENT=>'BubbaHubWebsiteImporter/1.0',CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
$html=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
if (!is_string($html) || strlen($html)>1048576 || $status!==200) respond(422,['ok'=>false,'error'=>'website_unavailable']);
$dom=new DOMDocument();$old=libxml_use_internal_errors(true);$dom->loadHTML($html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($old);
$xp=new DOMXPath($dom);
$resolve=static function(string $v) use($domain):string {
 $v=trim($v);
 if (str_starts_with($v,'//')) $v='https:'.$v;
 elseif (str_starts_with($v,'/')) $v='https://'.$domain.$v;
 elseif (!preg_match('~^https?://~i',$v)) $v='https://'.$domain.'/'.ltrim($v,'/');
 $p=parse_url($v);
 return $p && strtolower((string)($p['scheme']??''))==='https' && !empty($p['host']) && !isset($p['user']) && !isset($p['pass']) ? substr($v,0,500) : '';
};
$logo='';
foreach (["//meta[@property='og:image']/@content","//meta[@name='twitter:image']/@content","//link[contains(@rel,'icon')]/@href"] as $query) {
 $nodes=$xp->query($query);
 if ($nodes && $nodes->length) { $logo=$resolve((string)$nodes->item(0)->nodeValue); if ($logo!=='') break; }
}
$social=['facebook'=>'','instagram'=>'','tiktok'=>''];
foreach ($xp->query('//a[@href]') as $a) {
 $link=$resolve((string)$a->getAttribute('href'));$linkHost=strtolower((string)parse_url($link,PHP_URL_HOST));
 foreach ($social as $network=>$existing) if ($existing==='' && in_array($linkHost,[$network.'.com','www.'.$network.'.com'],true)) $social[$network]=$link;
}
respond(200,['ok'=>true,'logo_url'=>$logo,'social_links'=>$social]);
