<?php
declare(strict_types=1);
/**
 * CLI-only existing-listing checker. Run from server cron every four hours:
 * php /path/to/api/listing-checker-health-cron.php
 * Requires database/listing-checker-schema.sql. Never changes live listings.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/db.php';
$db=bh_mysql();
$required=['bh_listing_checker_settings','bh_listing_check_queue','bh_listing_check_runs','bh_listing_check_findings'];
foreach($required as $table){$q=$db->prepare('SHOW TABLES LIKE ?');$q->execute([$table]);if(!$q->fetchColumn()){fwrite(STDERR,"Migration required: apply database/listing-checker-schema.sql\n");exit(2);}}
$settings=[];foreach($db->query('SELECT setting_key,setting_value FROM bh_listing_checker_settings')->fetchAll(PDO::FETCH_ASSOC) as $r)$settings[$r['setting_key']]=$r['setting_value'];
$get=static fn($k,$d='')=>(string)($settings[$k]??$d);
if($get('health_enabled','1')!=='1'){echo "Health checks disabled\n";exit;}
$tz=new DateTimeZone($get('timezone','Europe/London'));$now=new DateTimeImmutable('now',$tz);
$days=json_decode($get('active_days','[1,2,3,4,5,6,7]'),true)?:[1,2,3,4,5,6,7];
if(!in_array((int)$now->format('N'),array_map('intval',$days),true)){echo "Not an active day\n";exit;}
$start=$get('window_start','00:00');$end=$get('window_end','23:59');$clock=$now->format('H:i');
if(($start<=$end&&($clock<$start||$clock>$end))||($start>$end&&$clock<$start&&$clock>$end)){echo "Outside run window\n";exit;}
$interval=max(1,min(24,(int)$get('interval_hours','4')));
$preferred=$get('preferred_start_time','00:00');
$last=$db->query("SELECT MAX(finished_at) FROM bh_listing_check_runs WHERE check_type='health' AND status='completed'")->fetchColumn();
if(!$last&&$clock<$preferred){echo "Waiting for preferred first-run time\n";exit;}
if($last&&new DateTimeImmutable((string)$last,$tz)>$now->modify('-'.$interval.' hours')){echo "Not due yet\n";exit;}
$lock=$db->query("SELECT GET_LOCK('bh_listing_checker_health',0)")->fetchColumn();if((int)$lock!==1){echo "Worker already running\n";exit;}
$runId=0;
try{
 $cols=$db->query("SHOW COLUMNS FROM bh_activities")->fetchAll(PDO::FETCH_COLUMN);if(!$cols||!in_array('id',$cols,true))throw new RuntimeException('bh_activities table unavailable');
 $db->exec("INSERT IGNORE INTO bh_listing_check_queue(check_type,target_id,target_term_id,status,next_check_at) SELECT 'health',id,0,'pending',NOW() FROM bh_activities");
 $q=$db->prepare("INSERT INTO bh_listing_check_runs(check_type,status,started_at) VALUES('health','running',NOW())");$q->execute();$runId=(int)$db->lastInsertId();
 $limit=max(1,min(100,(int)$get('batch_size','30')));$runtime=max(30,min(600,(int)$get('max_runtime_seconds','240')));$deadline=microtime(true)+$runtime;
 $due=$db->prepare("SELECT id,target_id FROM bh_listing_check_queue WHERE check_type='health' AND status IN('pending','failed') AND (next_check_at IS NULL OR next_check_at<=NOW()) ORDER BY id LIMIT ?");$due->bindValue(1,$limit,PDO::PARAM_INT);$due->execute();$queue=$due->fetchAll(PDO::FETCH_ASSOC);
 $fields=['title','description','short_description','category','age_range','website','booking_url','contact_email','phone','facebook_url','instagram_url','town','region','postcode','image_path','start_date','end_date','next_session_date'];$select=['id'];foreach($fields as $f)if(in_array($f,$cols,true))$select[]='`'.$f.'`';
 $read=$db->prepare('SELECT '.implode(',',$select).' FROM bh_activities WHERE id=?');$finding=$db->prepare("INSERT INTO bh_listing_check_findings(check_type,activity_id,candidate_json,finding_type,confidence,checked_at,review_status) VALUES('health',?,?,?,100,NOW(),'new')");
 $mark=$db->prepare("UPDATE bh_listing_check_queue SET status=?,attempts=attempts+1,last_checked_at=NOW(),last_error=?,next_check_at=NULL WHERE id=?");
 $done=0;$errors=0;
 foreach($queue as $item){if(microtime(true)>$deadline)break;$qid=(int)$item['id'];$aid=(int)$item['target_id'];try{
  $read->execute([$aid]);$row=$read->fetch(PDO::FETCH_ASSOC);if(!$row){$mark->execute(['skipped','Activity no longer exists',$qid]);continue;}
  $checks=['description'=>['description','short_description'],'website'=>['website'],'booking_link'=>['booking_url'],'email'=>['contact_email'],'telephone'=>['phone'],'facebook'=>['facebook_url'],'instagram'=>['instagram_url'],'age_range'=>['age_range'],'category'=>['category'],'location'=>['town'],'postcode'=>['postcode'],'image'=>['image_path']];
  foreach($checks as $type=>$keys){$present=false;foreach($keys as $key)if(trim((string)($row[$key]??''))!=='')$present=true;if(!$present)$finding->execute([$aid,json_encode(['field'=>$type]),'missing_'.$type]);}
  foreach(['website','booking_url','facebook_url','instagram_url'] as $key){$v=trim((string)($row[$key]??''));if($v!==''&&!filter_var($v,FILTER_VALIDATE_URL))$finding->execute([$aid,json_encode(['field'=>$key,'value'=>$v]),'invalid_url']);}
  $email=trim((string)($row['contact_email']??''));if($email!==''&&!filter_var($email,FILTER_VALIDATE_EMAIL))$finding->execute([$aid,json_encode(['field'=>'contact_email']),'invalid_email']);
  $postcode=trim((string)($row['postcode']??''));if($postcode!==''&&!preg_match('/^[A-Z]{1,2}[0-9][A-Z0-9]?\s*[0-9][A-Z]{2}$/i',$postcode))$finding->execute([$aid,json_encode(['field'=>'postcode']),'invalid_postcode']);
  foreach(['start_date','end_date','next_session_date'] as $key){$v=trim((string)($row[$key]??''));if($v!==''&&strtotime($v)!==false&&strtotime($v)<strtotime('today')){$finding->execute([$aid,json_encode(['field'=>$key,'value'=>$v]),'past_session_date']);break;}}
  if(in_array('title',$cols,true)&&in_array('town',$cols,true)){$d=$db->prepare("SELECT id FROM bh_activities WHERE id<>? AND LOWER(TRIM(title))=LOWER(TRIM(?)) AND LOWER(TRIM(COALESCE(town,'')))=LOWER(TRIM(COALESCE(?,''))) LIMIT 1");$d->execute([$aid,(string)($row['title']??''),(string)($row['town']??'')]);if($d->fetchColumn())$finding->execute([$aid,json_encode(['title'=>$row['title']??'','town'=>$row['town']??'']),'possible_duplicate']);}
  $mark->execute(['checked',null,$qid]);$done++;
 }catch(Throwable $e){$errors++;$mark->execute(['failed',substr($e->getMessage(),0,900),$qid]);}}
 $remaining=(int)$db->query("SELECT COUNT(*) FROM bh_listing_check_queue WHERE check_type='health' AND status IN('pending','failed')")->fetchColumn();$summary=json_encode(['processed'=>$done,'errors'=>$errors,'remaining'=>$remaining]);
 $finish=$db->prepare("UPDATE bh_listing_check_runs SET status='completed',finished_at=NOW(),processed_count=?,error_count=?,summary_json=? WHERE id=?");$finish->execute([$done,$errors,$summary,$runId]);echo $summary."\n";
}catch(Throwable $e){if($runId){$s=$db->prepare("UPDATE bh_listing_check_runs SET status='failed',finished_at=NOW(),summary_json=? WHERE id=?");$s->execute([json_encode(['error'=>$e->getMessage()]),$runId]);}error_log('Listing checker: '.$e->getMessage());fwrite(STDERR,"Worker failed; see PHP error log\n");exit(1);}
finally{$db->query("SELECT RELEASE_LOCK('bh_listing_checker_health')");}
