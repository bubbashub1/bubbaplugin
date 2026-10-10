<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('BUBBAHUB_ADMINSESSID');
session_start();

function bh_region_response(int $status,array $data): never{
    http_response_code($status);
    echo json_encode($data,JSON_UNESCAPED_SLASHES);
    exit;
}
if(empty($_SESSION['bh_admin_authenticated'])) bh_region_response(401,['ok'=>false,'error'=>'Admin login required.']);

try{
    require __DIR__.'/db.php';
    $db=bh_mysql();

    if($_SERVER['REQUEST_METHOD']==='GET'){
        $regions=$db->query("SELECT id,county,region,active,sort_order FROM bh_regions WHERE active=1 ORDER BY sort_order,id")->fetchAll();
        $towns=$db->query("SELECT rt.id,rt.region_id,rt.town,rt.active,r.county,r.region FROM bh_region_towns rt INNER JOIN bh_regions r ON r.id=rt.region_id WHERE rt.active=1 AND r.active=1 ORDER BY rt.town")->fetchAll();
        bh_region_response(200,['ok'=>true,'regions'=>$regions,'towns'=>$towns]);
    }

    if($_SERVER['REQUEST_METHOD']!=='POST') bh_region_response(405,['ok'=>false,'error'=>'GET or POST required.']);

    $input=json_decode((string)file_get_contents('php://input'),true);
    if(!is_array($input)) bh_region_response(400,['ok'=>false,'error'=>'Invalid JSON.']);

    $town=trim((string)($input['town']??''));
    $regionId=(int)($input['region_id']??0);
    if($town==='') bh_region_response(422,['ok'=>false,'error'=>'Enter a town name.']);
    if($regionId<1) bh_region_response(422,['ok'=>false,'error'=>'Select a region for the new town.']);

    $q=$db->prepare("SELECT id,county,region FROM bh_regions WHERE id=? AND active=1 LIMIT 1");
    $q->execute([$regionId]);
    $region=$q->fetch();
    if(!$region) bh_region_response(422,['ok'=>false,'error'=>'The selected region is not available.']);

    $q=$db->prepare("SELECT rt.id,rt.town,r.county,r.region FROM bh_region_towns rt INNER JOIN bh_regions r ON r.id=rt.region_id WHERE rt.active=1 AND LOWER(TRIM(rt.town))=LOWER(TRIM(?)) LIMIT 1");
    $q->execute([$town]);
    $existing=$q->fetch();
    if($existing) bh_region_response(409,['ok'=>false,'error'=>'That town already exists as '.$existing['town'].' in '.$existing['region'].', '.$existing['county'].'.','existing'=>$existing]);

    $columns=[];
    try{
        $stmt=$db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_region_towns'");
        foreach($stmt->fetchAll(PDO::FETCH_COLUMN) as $col) $columns[(string)$col]=true;
    }catch(Throwable $ignored){}

    if(!isset($columns['region_id'])||!isset($columns['town'])) bh_region_response(500,['ok'=>false,'error'=>'bh_region_towns must contain region_id and town columns.']);

    $fields=['region_id','town'];
    $values=['?','?'];
    $params=[$regionId,$town];
    if(isset($columns['active'])){ $fields[]='active'; $values[]='1'; }
    if(isset($columns['created_at'])){ $fields[]='created_at'; $values[]='NOW()'; }

    $sql="INSERT INTO bh_region_towns (".implode(',',$fields).") VALUES (".implode(',',$values).")";
    $q=$db->prepare($sql);
    $q->execute($params);
    $newId=(int)$db->lastInsertId();

    bh_region_response(201,['ok'=>true,'town'=>['id'=>$newId,'region_id'=>$regionId,'town'=>$town,'county'=>$region['county'],'region'=>$region['region']]]);
}catch(Throwable $e){
    bh_region_response(500,['ok'=>false,'error_type'=>get_class($e),'error'=>$e->getMessage()]);
}
?>