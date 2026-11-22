<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');
header('X-Content-Type-Options: nosniff');
$tables=['business'=>'bh_directory_businesses','childcare'=>'bh_directory_childcare','place'=>'bh_directory_places','school_club'=>'bh_directory_school_clubs'];
$type=(string)($_GET['type']??'');
if(!isset($tables[$type])){http_response_code(400);echo json_encode(['ok'=>false,'error'=>'invalid_section']);exit;}
try{
 require_once __DIR__.'/db.php';$db=bh_mysql();$table=$tables[$type];
 $q=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?");$q->execute([$table]);
 if(!(int)$q->fetchColumn()){echo json_encode(['ok'=>true,'schema_ready'=>false,'records'=>[]]);exit;}
 $where=["status='published'"];$params=[];$search=trim((string)($_GET['q']??''));$town=trim((string)($_GET['town']??''));$category=trim((string)($_GET['category']??''));
 if($search!==''){$where[]="(title LIKE ? OR summary LIKE ? OR description LIKE ?)";$like='%'.$search.'%';array_push($params,$like,$like,$like);}
 if($town!==''){$where[]="(town LIKE ? OR postcode LIKE ?)";$like='%'.$town.'%';array_push($params,$like,$like);}
 if($category!==''){$where[]="category=?";$params[]=$category;}
 $columns=$db->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='".$table."'")->fetchAll(PDO::FETCH_COLUMN);
 $extraFields=$type==='business'?['website_url']:($type==='childcare'?['website_url','age_min','age_max','registration_body','registration_reference','verification_url','verified_on']:($type==='place'?['website_url','facilities']:['website_url','school_name','age_min','age_max','days_times','collection_arrangements','holiday_provision','price_details','registration_details','verification_url','verified_on']));
 $extraFields=array_values(array_filter($extraFields,static fn($field)=>in_array($field,$columns,true)));
 $extra=$extraFields?','.implode(',',$extraFields):'';
 $sql="SELECT id,title,category,summary,description,town,region,postcode".$extra." FROM ".$table." WHERE ".implode(' AND ',$where)." ORDER BY title LIMIT 100";
 $q=$db->prepare($sql);$q->execute($params);
 echo json_encode(['ok'=>true,'schema_ready'=>true,'records'=>$q->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $e){error_log('Directory sector API: '.$e->getMessage());http_response_code(500);echo json_encode(['ok'=>false,'error'=>'directory_unavailable']);}
