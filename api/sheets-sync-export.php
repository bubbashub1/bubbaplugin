<?php
declare(strict_types=1);
/** Authenticated, paginated, read-only activity export for Sheets reconciliation. */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
function bhSheetsExportReply(int $status, array $data): void {
    http_response_code($status);
    echo json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bhSheetsExportReply(405,['ok'=>false,'error'=>'post_required']);
$secretFile = dirname(__DIR__, 2) . '/bh-sheets-sync-secret.php';
if (!is_readable($secretFile)) bhSheetsExportReply(503,['ok'=>false,'error'=>'sync_not_configured']);
require_once $secretFile;
$secret = (string)(getenv('BH_SHEETS_SYNC_TOKEN') ?: '');
if (strlen($secret) < 32) bhSheetsExportReply(503,['ok'=>false,'error'=>'sync_not_configured']);
$auth = (string)($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $matches) || !hash_equals($secret,trim($matches[1]))) {
    bhSheetsExportReply(401,['ok'=>false,'error'=>'unauthorized']);
}
$raw = file_get_contents('php://input',false,null,0,4097);
if (!is_string($raw) || strlen($raw)>4096) bhSheetsExportReply(413,['ok'=>false,'error'=>'payload_too_large']);
$body = json_decode($raw,true);
if (!is_array($body) || ($body['action'] ?? '') !== 'export') bhSheetsExportReply(400,['ok'=>false,'error'=>'invalid_action']);
$after = $body['after_id'] ?? 0;
$limit = $body['limit'] ?? 100;
if (!is_int($after) || $after < 0 || !is_int($limit) || $limit < 1 || $limit > 100) bhSheetsExportReply(400,['ok'=>false,'error'=>'invalid_pagination']);
try {
    require_once __DIR__ . '/db.php';
    $db = bh_mysql();
    $fields = ['id','title','category','featured','description','age_range','age_min_months','age_max_months','county','price_from','price_per_family','price_per_session','price_free','booking_url','booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome','what_to_bring','good_to_know','image_path','image_1','image_2','image_3','status','organisation_name','facebook_url','instagram_url','email','phone','website','venue_name','address','venue_address_line_1','venue_address_line_2','city','region','postcode','latitude','longitude','venue_phone','venue_email','venue_website','venue_notes','accessibility','tags','sessions'];
    $q=$db->query("SELECT TABLE_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('bh_activities','bh_organisers','bh_venues')");
    $available=[];
    foreach($q->fetchAll(PDO::FETCH_ASSOC) as $col) $available[$col['TABLE_NAME']][$col['COLUMN_NAME']]=true;
    $a=$available['bh_activities']??[];$o=$available['bh_organisers']??[];$v=$available['bh_venues']??[];
    $expr=[];
    foreach($fields as $field){
      $source=null;
      if(isset($a[$field])) $source='a.'.$field;
      elseif(isset($o[$field]) && in_array($field,['organisation_name','facebook_url','instagram_url','email','phone','website'],true)) $source='o.'.$field;
      elseif(isset($v[$field]) && in_array($field,['venue_name','address','region','postcode','latitude','longitude'],true)) $source='v.'.$field;
      elseif($field==='city' && isset($v['town'])) $source='v.town';
      elseif(str_starts_with($field,'venue_') && isset($v[substr($field,6)])) $source='v.'.substr($field,6);
      elseif($field==='venue_notes' && isset($v['notes'])) $source='v.notes';
      elseif($field==='venue_address_line_1' && isset($v['address'])) $source='v.address';
      $expr[]=($source??"NULL").' AS `'.$field.'`';
    }
    $venueLink='1=0';
    $hasLinks=(bool)$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activity_venues'")->fetchColumn();
    if(isset($v['activity_id'])) $venueLink='v2.activity_id=a.id';
    if($hasLinks) $venueLink.= " OR EXISTS (SELECT 1 FROM bh_activity_venues av WHERE av.activity_id=a.id AND av.venue_id=v2.id)";
    $venueJoin=isset($v['id']) ? "LEFT JOIN bh_venues v ON v.id=(SELECT v2.id FROM bh_venues v2 WHERE ($venueLink) ORDER BY v2.id LIMIT 1)" : "LEFT JOIN bh_venues v ON 1=0";
    $sql="SELECT ".implode(',',$expr)." FROM bh_activities a LEFT JOIN bh_organisers o ON o.id=a.organiser_id $venueJoin WHERE a.id>:after ORDER BY a.id ASC LIMIT :lim";
    $stmt=$db->prepare($sql);
    $stmt->bindValue(':after',$after,PDO::PARAM_INT);
    $stmt->bindValue(':lim',$limit+1,PDO::PARAM_INT);
    $stmt->execute();
    $rows=$stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasMore=count($rows)>$limit;
    if($hasMore) array_pop($rows);
    $records=[];
    foreach($rows as $row){
      $record=[];
      foreach($fields as $field) $record[$field]=$row[$field]===null?'':(string)$row[$field];
      $record['id']=(int)$row['id'];
      $records[]=$record;
    }
    // Optional related fields: load per page without changing any database records.
    if($records){
      $ids=array_column($records,'id');
      $marks=implode(',',array_fill(0,count($ids),'?'));
      $index=[];foreach($records as $i=>$record)$index[$record['id']]=$i;
      $tables=[];
      $tq=$db->query("SELECT TABLE_NAME,COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('bh_activity_tags','bh_activity_images','bh_activity_schedules')");
      foreach($tq->fetchAll(PDO::FETCH_ASSOC) as $c)$tables[$c['TABLE_NAME']][$c['COLUMN_NAME']]=true;
      if(isset($tables['bh_activity_tags']['activity_id'])){
        $tagCol=isset($tables['bh_activity_tags']['tag'])?'tag':(isset($tables['bh_activity_tags']['name'])?'name':null);
        if($tagCol){
          $t=$db->prepare("SELECT activity_id,$tagCol AS tag_value FROM bh_activity_tags WHERE activity_id IN ($marks)");
          $t->execute($ids);$tags=[];
          foreach($t->fetchAll(PDO::FETCH_ASSOC) as $v)$tags[(int)$v['activity_id']][]=$v['tag_value'];
          foreach($tags as $id=>$list)$records[$index[$id]]['tags']=implode(', ',$list);
        }
      }
      if(isset($tables['bh_activity_images']['activity_id'],$tables['bh_activity_images']['image_path'])){
        $t=$db->prepare("SELECT activity_id,image_path FROM bh_activity_images WHERE activity_id IN ($marks) ORDER BY activity_id,id");
        $t->execute($ids);$images=[];
        foreach($t->fetchAll(PDO::FETCH_ASSOC) as $v)$images[(int)$v['activity_id']][]=$v['image_path'];
        foreach($images as $id=>$list)for($i=0;$i<3;$i++)$records[$index[$id]]['image_'.($i+1)]=(string)($list[$i]??'');
      }
      if(isset($tables['bh_activity_schedules']['activity_id'],$tables['bh_activity_schedules']['day_of_week'],$tables['bh_activity_schedules']['start_time'])){
        $t=$db->prepare("SELECT activity_id,day_of_week,start_time,end_time FROM bh_activity_schedules WHERE activity_id IN ($marks) ORDER BY activity_id,day_of_week,start_time");
        $t->execute($ids);$sessions=[];
        $days=[1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'];
        foreach($t->fetchAll(PDO::FETCH_ASSOC) as $v){
          $day=$days[(int)$v['day_of_week']]??'?';
          $sessions[(int)$v['activity_id']][]=$day.' '.substr((string)$v['start_time'],0,5).($v['end_time']?'-'.substr((string)$v['end_time'],0,5):'');
        }
        foreach($sessions as $id=>$list)$records[$index[$id]]['sessions']=implode('; ',$list);
      }
    }
    $next=$records?$records[count($records)-1]['id']:$after;
    bhSheetsExportReply(200,['ok'=>true,'mode'=>'read_only_export','fields'=>$fields,
        'records'=>$records,'has_more'=>$hasMore,'next_after_id'=>$next,'writes_performed'=>false]);
} catch (Throwable $e) {
    error_log('Sheets export failed: '.$e->getMessage());
    bhSheetsExportReply(500,['ok'=>false,'error'=>'export_failed']);
}
