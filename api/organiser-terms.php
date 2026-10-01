<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require_once __DIR__ . '/db.php';
    $db=bh_mysql();
    $slug=strtolower(trim((string)($_GET['slug']??'')));
    $slug=preg_replace('/[^a-z0-9]+/','-',str_replace('&','and',$slug));
    $slug=trim((string)$slug,'-');
    if($slug===''){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'slug_required']);exit;}
    $q=$db->prepare("SELECT id,organisation_name,terms_content,about_content,logo_url,facebook_url,instagram_url,tiktok_url FROM bh_organisers WHERE LOWER(TRIM(REPLACE(REPLACE(organisation_name,'&','and'),' ','-'))) = ? LIMIT 1");
    $q->execute([$slug]);
    $row=$q->fetch();
    if(!$row){
        $all=$db->query("SELECT organisation_name,terms_content,about_content,logo_url,facebook_url,instagram_url,tiktok_url FROM bh_organisers")->fetchAll();
        foreach($all as $candidate){
            $normal=strtolower(trim((string)$candidate['organisation_name']));
            $normal=str_replace('&','and',$normal);
            $normal=preg_replace('/[^a-z0-9]+/','-',$normal);
            $normal=trim((string)$normal,'-');
            if($normal===$slug){$row=$candidate;break;}
        }
    }
    if(!$row){http_response_code(404);echo json_encode(['ok'=>false,'error'=>'organiser_not_found']);exit;}
    $faqs=[];
    try{
        $f=$db->prepare("SELECT id,question,answer,activity_id,sort_order FROM bh_leader_faqs WHERE organiser_id=? AND status='published' ORDER BY sort_order,id");
        $f->execute([(int)$row['id']]);
        $faqs=$f->fetchAll();
    }catch(Throwable $ignored){}
    echo json_encode(['ok'=>true,'organisation_name'=>$row['organisation_name'],'terms_content'=>$row['terms_content']??'','about_content'=>$row['about_content']??'','logo_url'=>$row['logo_url']??'','facebook_url'=>$row['facebook_url']??'','instagram_url'=>$row['instagram_url']??'','tiktok_url'=>$row['tiktok_url']??'','faqs'=>$faqs],JSON_UNESCAPED_SLASHES);
} catch(Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'terms_load_failed']);
}