<?php
declare(strict_types=1);
/**
 * Read-only duplicate candidates. Never changes, merges or deletes listings.
 * Admin-only endpoint: GET /api/admin-listing-duplicates.php?title=...&town=...&organiser_id=...&exclude_id=...
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
session_name('BUBBAHUB_ADMINSESSID');
session_start();
function duplicate_reply(int $code,array $payload): never {
    http_response_code($code);
    echo json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (empty($_SESSION['bh_admin_authenticated'])) duplicate_reply(401,['ok'=>false,'error'=>'admin_login_required']);
if (($_SERVER['REQUEST_METHOD']??'')!=='GET') duplicate_reply(405,['ok'=>false,'error'=>'method_not_allowed']);
$title=trim((string)($_GET['title']??''));
$town=trim((string)($_GET['town']??''));
$organiserId=max(0,(int)($_GET['organiser_id']??0));
$excludeId=max(0,(int)($_GET['exclude_id']??0));
if (mb_strlen($title)<3 || mb_strlen($title)>190 || mb_strlen($town)>190) duplicate_reply(422,['ok'=>false,'error'=>'invalid_search']);
function duplicate_normalize(string $value): string {
    $value=mb_strtolower(trim($value),'UTF-8');
    $value=preg_replace('/[^\\p{L}\\p{N}]+/u',' ',$value);
    return trim((string)preg_replace('/\\s+/u',' ',$value));
}
try {
    require_once __DIR__.'/db.php';
    $db=bh_mysql();
    // Bound queries and capped results prevent full-directory downloads.
    $words=preg_split('/\\s+/u',duplicate_normalize($title));
    $term=mb_substr((string)($words[0]??''),0,60);
    if (mb_strlen($term)<2) duplicate_reply(200,['ok'=>true,'candidates'=>[]]);
    $sql="SELECT a.id,a.title,a.slug,a.organiser_id,o.organisation_name,MIN(v.town) AS town
          FROM bh_activities a
          LEFT JOIN bh_organisers o ON o.id=a.organiser_id
          LEFT JOIN bh_venues v ON v.activity_id=a.id
          WHERE a.id<>:exclude_id AND a.title LIKE :title
          GROUP BY a.id,a.title,a.slug,a.organiser_id,o.organisation_name
          ORDER BY a.id DESC LIMIT 100";
    $stmt=$db->prepare($sql);
    $stmt->execute(['exclude_id'=>$excludeId,'title'=>'%'.str_replace(['%','_'],['\\%','\\_'],$term).'%']);
    $target=duplicate_normalize($title);$targetTown=duplicate_normalize($town);$out=[];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name=duplicate_normalize((string)$row['title']);
        $sameTitle=$name===$target;
        similar_text($target,$name,$similarity);
        if (!$sameTitle && $similarity<72) continue;
        $sameOrg=$organiserId>0 && $organiserId===(int)$row['organiser_id'];
        $sameTown=$targetTown!=='' && $targetTown===duplicate_normalize((string)$row['town']);
        $score=(int)round(min(100,($sameTitle?70:$similarity*.55)+($sameOrg?20:0)+($sameTown?10:0)));
        $out[]=['id'=>(int)$row['id'],'title'=>$row['title'],'slug'=>$row['slug'],'organiser_name'=>$row['organisation_name'],'town'=>$row['town'],'score'=>$score,'reasons'=>array_values(array_filter([$sameTitle?'matching_title':'similar_title',$sameOrg?'same_organiser':null,$sameTown?'same_town':null]))];
    }
    usort($out,static fn($a,$b)=>$b['score']<=>$a['score']);
    duplicate_reply(200,['ok'=>true,'candidates'=>array_slice($out,0,10),'warning_only'=>true]);
} catch (Throwable $e) {
    error_log('Listing duplicate check failed: '.$e->getMessage());
    duplicate_reply(500,['ok'=>false,'error'=>'duplicate_check_unavailable']);
}
