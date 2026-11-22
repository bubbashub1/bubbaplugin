<?php
declare(strict_types=1);

/** Create a private draft storefront for a leader organiser; never auto-publish. */
function bh_ensure_business_storefront(PDO $db, int $organiserId, int $userId, string $organisationName): ?int {
 if ($organiserId < 1) return null;
 $tables=$db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_directory_businesses'");
 $tables->execute(); if ((int)$tables->fetchColumn() !== 1) return null;
 $lock=$db->prepare("SELECT id FROM bh_organisers WHERE id=? FOR UPDATE"); $lock->execute([$organiserId]); if (!$lock->fetchColumn()) return null;
 $existing=$db->prepare("SELECT id FROM bh_directory_businesses WHERE organiser_id=? ORDER BY id LIMIT 1"); $existing->execute([$organiserId]); $found=$existing->fetchColumn(); if ($found) return (int)$found;
 $columns=[]; foreach ($db->query("SHOW COLUMNS FROM bh_directory_businesses")->fetchAll(PDO::FETCH_ASSOC) as $column) { $name=(string)($column['Field']??''); if ($name!=='') $columns[$name]=$column; }
 foreach (['organiser_id','title','category','town','status'] as $required) if (!isset($columns[$required])) throw new RuntimeException('Business storefront schema is missing '.$required.'.');
 $name=trim($organisationName); if ($name==='') $name='New class leader';
 $values=['organiser_id'=>$organiserId,'created_by_user_id'=>$userId?:null,'title'=>mb_substr($name,0,190),'category'=>'Classes & Activities','summary'=>'','description'=>'','town'=>'To be confirmed','region'=>null,'address'=>null,'postcode'=>null,'website_url'=>null,'contact_email'=>null,'contact_phone'=>null,'status'=>'draft','reviewed_at'=>null,'reviewed_by_user_id'=>null];
 $fields=[]; $params=[]; foreach ($values as $field=>$value) if (isset($columns[$field])) { $fields[]=$field; $params[]=$value; }
 foreach ($columns as $field=>$meta) {
  if (in_array($field,$fields,true) || ($meta['Null']??'YES')!=='NO' || ($meta['Default']??null)!==null || stripos((string)($meta['Extra']??''),'auto_increment')!==false) continue;
  if (in_array($field,['created_at','updated_at'],true)) continue;
  throw new RuntimeException('Business storefront schema has an unsupported required field: '.$field.'.');
 }
 $quote=static fn($field)=>chr(96).$field.chr(96);
 $sql='INSERT INTO bh_directory_businesses ('.implode(',',array_map($quote,$fields)).') VALUES ('.implode(',',array_fill(0,count($fields),'?')).')';
 $insert=$db->prepare($sql); $insert->execute($params); return (int)$db->lastInsertId();
}
