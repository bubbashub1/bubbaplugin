<?php
declare(strict_types=1);
/**
 * CLI-only existing-listing checker. Run from server cron every four hours.
 * Requires database/listing-checker-schema.sql. Never changes live listings.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/db.php';
$db=bh_mysql();
$required=['bh_listing_checker_settings','bh_listing_check_queue','bh_listing_check_runs','bh_listing_check_findings'];
foreach($required as $table){$q=$db->prepare('SHOW TABLES LIKE ?');$q->execute([$table]);if(!$q->fetchColumn()){fwrite(STDERR,"Migration required: apply database/listing-checker-schema.sql
");exit(2);}}
$settings=[];foreach($db->query('SELECT setting_key,setting_value FROM bh_listing_checker_settings')->fetchAll(PDO::FETCH_ASSOC) as $r)$settings[$r['setting_key']]=$r['setting_value'];
$get=static fn($k,$d='')=>(string)($settings[$k]??$d);
if($get('health_enabled','1')!=='1'){echo "Health checks disabled
";exit;}
if($get('health_paused','0')==='1'){echo "Health checks paused
";exit;}
$manualRun=$get('health_run_now','0')==='1';
$tz=new DateTimeZone($get('timezone','Europe/London'));$now=new DateTimeImmutable('now',$tz);
$days=json_decode($get('active_days','[1,2,3,4,5,6,7]'),true)?:[1,2,3,4,5,6,7];
if(!in_array((int)$now->format('N'),array_map('intval',$days),true)){echo "Not an active day
";exit;}
$start=$get('window_start','00:00');$end=$get('window_end','23:59');$clock=$now->format('H:i');
if(($start<=$end&&($clock<$start||$clock>$end))||($start>$end&&$clock>$end&&$clock<$start)){echo "Outside run window
";exit;}
$interval=max(1,min(24,(int)$get('interval_hours','4')));
$preferred=$get('preferred_start_time','00:00');
$last=$db->query("SELECT MAX(finished_at) FROM bh_listing_check_runs WHERE check_type='health' AND status='completed'")->fetchColumn();
if(!$manualRun&&!$last&&$clock<$preferred){echo "Waiting for preferred first-run time
";exit;}
if(!$manualRun&&$last&&new DateTimeImmutable((string)$last,$tz)>$now->modify('-'.$interval.' hours')){echo "Not due yet
";exit;}
$lock=$db->query("SELECT GET_LOCK('bh_listing_checker_health',0)")->fetchColumn();if((int)$lock!==1){echo "Worker already running
";exit;}
$runId=0;
try{\n if($manualRun){$clear=$db->prepare("INSERT INTO bh_listing_checker_settings(setting_key,setting_value) VALUES(?, '0') ON DUPLICATE KEY UPDATE setting_value='0'");$clear->execute(['health_run_now']);}
 $cols=$db->query("SHOW COLUMNS FROM bh_activities")->fetchAll(PDO::FETCH_COLUMN);if(!$cols||!in_array('id',$cols,true))throw new RuntimeException('bh_activities table unavailable');
 // INSERT IGNORE discovers newly-created listings; existing queue rows are retained for recurring checks.
 $db->exec("INSERT IGNORE INTO bh_listing_check_queue(check_type,target_id,target_term_id,status,next_check_at) SELECT 'health',id,0,'pending',NOW() FROM bh_activities");
 $q=$db->prepare("INSERT INTO bh_listing_check_runs(check_type,status,started_at) VALUES('health','running',NOW())");$q->execute();$runId=(int)$db->lastInsertId();
 $limit=max(1,min(100,(int)$get('batch_size','30')));$runtime=max(30,min(600,(int)$get('max_runtime_seconds','240')));$deadline=microtime(true)+$runtime;
 $due=$db->prepare("SELECT id,target_id FROM bh_listing_check_queue WHERE check_type='health' AND status IN('pending','failed') AND (next_check_at IS NULL OR next_check_at<=NOW()) ORDER BY COALESCE(next_check_at,created_at),id LIMIT ?");$due->bindValue(1,$limit,PDO::PARAM_INT);$due->execute();$queue=$due->fetchAll(PDO::FETCH_ASSOC);
 $fields=['title','description','short_description','category','age_range','website','booking_url','contact_email','contact_phone','facebook_url','instagram_url','town','region','postcode','image_path','start_date','end_date','next_session_date','updated_at'];
 $select=['id'];foreach($fields as $f)if(in_array($f,$cols,true))$select[]='`'.$f.'`';
 $read=$db->prepare('SELECT '.implode(',',$select).' FROM bh_activities WHERE id=?');
 // Refresh an existing matching finding instead of creating duplicate review items on every scheduled pass.
 $findExisting=$db->prepare("SELECT id FROM bh_listing_check_findings WHERE check_type='health' AND activity_id=? AND finding_type=? ORDER BY id LIMIT 1");
 $refreshFinding=$db->prepare("UPDATE bh_listing_check_findings SET candidate_json=?,confidence=100,checked_at=NOW() WHERE id=?");
 $insertFinding=$db->prepare("INSERT INTO bh_listing_check_findings(check_type,activity_id,candidate_json,finding_type,confidence,checked_at,review_status) VALUES('health',?,?,?,100,NOW(),'new')");
 $record=static function(int $activityId,string $type,array $data) use($findExisting,$refreshFinding,$insertFinding): void {
   $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
   $findExisting->execute([$activityId,$type]);$existing=$findExisting->fetchColumn();
   if($existing){$refreshFinding->execute([$json,(int)$existing]);return;}
   $insertFinding->execute([$activityId,$json,$type]);
 };
 // A successful check is re-queued for the configured interval. Failures use bounded exponential backoff.
 $markDone=$db->prepare("UPDATE bh_listing_check_queue SET status='pending',attempts=0,last_checked_at=NOW(),last_error=NULL,next_check_at=DATE_ADD(NOW(), INTERVAL ".$interval." HOUR) WHERE id=?");
 $markFailed=$db->prepare("UPDATE bh_listing_check_queue SET status='failed',attempts=attempts+1,last_checked_at=NOW(),last_error=?,next_check_at=DATE_ADD(NOW(), INTERVAL LEAST(24,POW(2,LEAST(attempts,4))) HOUR) WHERE id=?");
 $markSkipped=$db->prepare("UPDATE bh_listing_check_queue SET status='skipped',attempts=attempts+1,last_checked_at=NOW(),last_error=?,next_check_at=NULL WHERE id=?");
 $staleAfterDays=max(30,min(1095,(int)$get('stale_after_days','180')));
 $done=0;$errors=0;$skipped=0;
 foreach($queue as $item){
  if(microtime(true)>$deadline)break;
  $qid=(int)$item['id'];$aid=(int)$item['target_id'];
  try{
   $read->execute([$aid]);$row=$read->fetch(PDO::FETCH_ASSOC);
   if(!$row){$markSkipped->execute(['Activity no longer exists',$qid]);$skipped++;continue;}
   $checks=['description'=>['description','short_description'],'website'=>['website'],'booking_link'=>['booking_url'],'email'=>['contact_email'],'telephone'=>['contact_phone'],'facebook'=>['facebook_url'],'instagram'=>['instagram_url'],'age_range'=>['age_range'],'category'=>['category'],'location'=>['town'],'postcode'=>['postcode'],'image'=>['image_path']];
   foreach($checks as $type=>$keys){
    // Only assess fields supported by this installation's actual bh_activities schema.
    $availableKeys=array_values(array_filter($keys,static fn($key)=>in_array($key,$cols,true)));
    if(!$availableKeys)continue;
    $present=false;foreach($availableKeys as $key)if(trim((string)($row[$key]??''))!=='')$present=true;
    if(!$present)$record($aid,'missing_'.$type,['field'=>$type,'checked_columns'=>$availableKeys]);
   }
   foreach(['website','booking_url','facebook_url','instagram_url'] as $key){if(!in_array($key,$cols,true))continue;$v=trim((string)($row[$key]??''));if($v!==''&&!filter_var($v,FILTER_VALIDATE_URL))$record($aid,'invalid_url',['field'=>$key,'value'=>$v]);}
   if(in_array('contact_email',$cols,true)){$email=trim((string)($row['contact_email']??''));if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$record($aid,'invalid_email',['field'=>'contact_email']);}
   if(in_array('postcode',$cols,true)){$postcode=trim((string)($row['postcode']??''));if($postcode!==''&&!preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?\\s*[0-9][A-Z]{2}$/i',$postcode))$record($aid,'invalid_postcode',['field'=>'postcode','value'=>$postcode]);}
   foreach(['start_date','end_date','next_session_date'] as $key){if(!in_array($key,$cols,true))continue;$v=trim((string)($row[$key]??''));if($v!==''&&strtotime($v)!==false&&strtotime($v)<strtotime('today')){$record($aid,'past_session_date',['field'=>$key,'value'=>$v]);break;}}
   $updated=trim((string)($row['updated_at']??''));if($updated!==''&&strtotime($updated)!==false&&strtotime($updated)<strtotime('-'.$staleAfterDays.' days'))$record($aid,'stale_listing',['field'=>'updated_at','value'=>$updated,'stale_after_days'=>$staleAfterDays]);
   if(in_array('title',$cols,true)&&in_array('town',$cols,true)){
    $d=$db->prepare("SELECT id FROM bh_activities WHERE id<>? AND LOWER(TRIM(title))=LOWER(TRIM(?)) AND LOWER(TRIM(COALESCE(town,'')))=LOWER(TRIM(COALESCE(?,''))) LIMIT 1");
    $d->execute([$aid,(string)($row['title']??''),(string)($row['town']??'')]);
    if($d->fetchColumn())$record($aid,'possible_duplicate',['title'=>$row['title']??'','town'=>$row['town']??'']);
   }
   $markDone->execute([$qid]);$done++;
  }catch(Throwable $e){$errors++;$markFailed->execute([substr($e->getMessage(),0,900),$qid]);}
 }
 $remaining=(int)$db->query("SELECT COUNT(*) FROM bh_listing_check_queue WHERE check_type='health' AND status IN('pending','failed')")->fetchColumn();
 $summary=json_encode(['processed'=>$done,'skipped'=>$skipped,'errors'=>$errors,'remaining'=>$remaining,'next_check_hours'=>$interval]);
 $finish=$db->prepare("UPDATE bh_listing_check_runs SET status='completed',finished_at=NOW(),processed_count=?,error_count=?,summary_json=? WHERE id=?");$finish->execute([$done,$errors,$summary,$runId]);echo $summary."
";
}catch(Throwable $e){if($runId){$s=$db->prepare("UPDATE bh_listing_check_runs SET status='failed',finished_at=NOW(),summary_json=? WHERE id=?");$s->execute([json_encode(['error'=>$e->getMessage()]),$runId]);}error_log('Listing checker: '.$e->getMessage());fwrite(STDERR,"Worker failed; see PHP error log
");exit(1);}
finally{$db->query("SELECT RELEASE_LOCK('bh_listing_checker_health')");}
