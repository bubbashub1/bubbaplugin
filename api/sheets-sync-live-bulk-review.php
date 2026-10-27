<?php
declare(strict_types=1);
/** Admin-only bulk baseline review. No writes occur until an explicit per-listing POST. */
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
if (strtolower((string)($_SERVER['HTTP_HOST']??''))!=='bubbahub.co.uk') { http_response_code(403); exit('Live host required'); }
require_once __DIR__.'/admin-session-guard.php';
require_once __DIR__.'/sheets-sync-baseline-guard.php';
$settings=dirname(__DIR__,2).'/bh-sheets-sync-secret.php';
if (is_file($settings) && is_readable($settings)) require_once $settings;
function h($x):string {return htmlspecialchars((string)$x,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$enabled=getenv('BH_SHEETS_LIVE_BASELINE_APPROVALS')==='enabled';
$after=max(0,min(999999999,(int)($_GET['after']??0)));
$fields=['title','price_from','age_range'];
$items=[];$error='';$next=0;
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 // Keyset pagination; only rows with stored Sheet snapshots, no existing live baseline.
 $q=$db->prepare("SELECT a.id,a.title,a.price_from,a.age_range,a.status,a.leader_id,a.created_by,o.email AS organiser_email
 FROM bh_activities a
 LEFT JOIN bh_organisers o ON o.id=a.organiser_id
 WHERE a.id>:after AND EXISTS (SELECT 1 FROM bh_sheets_sync_live_sheet_snapshots s WHERE s.activity_id=a.id)
 AND NOT EXISTS (SELECT 1 FROM bh_sheets_sync_live_baselines b WHERE b.environment='live' AND b.activity_id=a.id)
 ORDER BY a.id LIMIT 11");
 $q->bindValue(':after',$after,PDO::PARAM_INT);$q->execute();
 $rows=$q->fetchAll(PDO::FETCH_ASSOC);
 $hasMore=count($rows)>10;
 $rows=array_slice($rows,0,10);
 if ($rows) {
   $ids=array_map(static fn($r)=>(int)$r['id'],$rows);
   $marks=implode(',',array_fill(0,count($ids),'?'));
   $q=$db->prepare("SELECT activity_id,field_name,sheet_value,received_at_utc FROM bh_sheets_sync_live_sheet_snapshots WHERE activity_id IN ($marks) AND field_name IN ('title','price_from','age_range')");
   $q->execute($ids);
   $snapshots=[];
   foreach($q->fetchAll(PDO::FETCH_ASSOC) as $s) $snapshots[(int)$s['activity_id']][$s['field_name']]=$s;
   $emails=array_values(array_unique(array_filter(array_map(static fn($r)=>strtolower(trim((string)($r['organiser_email']??''))),$rows))));
   $owned=[];
   if($emails) {
     $marks=implode(',',array_fill(0,count($emails),'?'));
     $q=$db->prepare("SELECT LOWER(TRIM(email)) FROM bh_users WHERE role='leader' AND status='active' AND LOWER(TRIM(email)) IN ($marks)");
     $q->execute($emails);
     $owned=array_fill_keys($q->fetchAll(PDO::FETCH_COLUMN),true);
   }
   foreach($rows as $r) {
     $id=(int)$r['id'];
     $protected=$r['status']!=='published'||$r['leader_id']!==null||$r['created_by']!==null||isset($owned[strtolower(trim((string)($r['organiser_email']??'')))]);
     $values=[];$matches=[];$reason=[];
     foreach($fields as $f) {
       $s=$snapshots[$id][$f]??null;
       $v=(string)($r[$f]??'');
       $sv=$s===null?null:(string)$s['sheet_value'];
       $fresh=$s!==null && strtotime((string)$s['received_at_utc'].' UTC')>=time()-86400;
       $ok=!$protected && $enabled && trim($v)!=='' && $sv!==null && $fresh && bh_sync_field_equal($f,$v,$sv);
       $values[$f]=$v;
       $matches[$f]=['sheet'=>$sv,'fresh'=>$fresh,'ok'=>$ok];
       if(!$ok)$reason[$f]=$protected?'Protected listing':(!$enabled?'Approvals disabled':($s===null?'No snapshot':(!$fresh?'Snapshot expired':'Values differ or blank')));
     }
     $items[]=['id'=>$id,'title'=>$r['title'],'values'=>$values,'checks'=>$matches,'reasons'=>$reason,'protected'=>$protected];
   }
   if($hasMore)$next=(int)end($rows)['id'];
 }
} catch(Throwable $e){error_log('Bulk baseline preview failed: '.$e->getMessage());$error='Unable to load preview. No changes were made.';}
?>
<!doctype html><html lang="en-GB"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bulk Sheets baseline review · Bubba Hub</title>
<style>body{font:15px/1.5 system-ui,sans-serif;background:#f5faf6;color:#173e2c;margin:0}main{max-width:1150px;margin:24px auto;padding:20px}h1{margin-bottom:4px}.muted{color:#526b5c}.panel{background:white;border:1px solid #d6e7d9;border-radius:18px;padding:20px;margin:18px 0;overflow:auto}table{width:100%;border-collapse:collapse;min-width:760px}th,td{padding:9px;text-align:left;border-bottom:1px solid #e0ebe2;vertical-align:top}th{background:#eef6ef}button{border:0;border-radius:22px;padding:12px 18px;background:#145a3c;color:white;font-weight:700;cursor:pointer}button:disabled{background:#a4b6aa;cursor:not-allowed}a{color:#16583c}small{display:block;color:#607568}.good{color:#17683b}.bad{color:#915128}.rowcheck{width:18px;height:18px}#result{white-space:pre-wrap}</style></head><body><main>
<a href="/admin.html">← Back to Admin</a> · <a href="/api/sheets-sync-live-baseline-review.php">Single listing review</a>
<h1>Google Sheets bulk baseline approval</h1>
<p class="muted">Review up to 10 listings at a time. Only matching, fresh, unprotected fields can be approved. No activity listings are updated.</p>
<?php if($error):?><div class="panel" role="alert"><?=h($error)?></div><?php else:?>
<div class="panel"><p><strong>Approvals:</strong> <?= $enabled?'Enabled':'Disabled' ?> · <strong>Listings displayed:</strong> <?=count($items)?> · <strong>Already approved:</strong> excluded</p>
<p>Only select listings after checking the exact website and Sheet values. Each selected listing is approved in its own protected request; processing stops if any request fails.</p></div>
<div class="panel"><table><thead><tr><th>Select</th><th>Activity</th><th>Field to approve</th><th>Website</th><th>Google Sheet</th><th>Status</th></tr></thead><tbody>
<?php foreach($items as $item): $first=true; foreach($fields as $f): $check=$item['checks'][$f]; ?>
<tr><td><?php if($first):?><input type="checkbox" class="rowcheck" data-id="<?= $item['id'] ?>" <?= count(array_filter(array_column($item['checks'],'ok')))>0?'':'disabled' ?> aria-label="Select activity <?= $item['id'] ?>"><?php endif;?></td>
<td><?php if($first):?><strong><?=h($item['id'])?></strong><small><?=h($item['title'])?></small><?php endif;?></td>
<td><label><input type="checkbox" class="fieldcheck" data-id="<?= $item['id'] ?>" data-field="<?=h($f)?>" <?= $check['ok']?'':'disabled' ?>> <?=h($f)?></label></td><td><?=h($item['values'][$f])?></td><td><?=h($check['sheet']??'—')?></td>
<td class="<?= $check['ok']?'good':'bad' ?>"><?= $check['ok']?'Matches · fresh':h($item['reasons'][$f]??'Unavailable') ?></td></tr>
<?php $first=false;endforeach;endforeach;?>
</tbody></table></div>
<div class="panel"><p id="selectionSummary" class="muted">Select an activity and tick the individual fields you wish to approve. Nothing is preselected.</p><label><input id="partial" type="checkbox"> I understand that approving fewer than all three fields creates a partial baseline; missing fields will not be covered.</label><p><label><input id="confirm" type="checkbox"> I have reviewed the exact selected fields and approve creating their trusted baselines.</label></p>
<p><button type="button" id="approve" disabled>Approve selected baselines (maximum 10)</button></p>
<p id="result" role="status" aria-live="polite"></p></div>
<?php if($next):?><p><a href="?after=<?=$next?>">Next 10 listings →</a></p><?php endif;?>
<?php endif;?>
<script>
const rows=<?=json_encode($items,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?>;
const confirmBox=document.getElementById('confirm'),partialBox=document.getElementById('partial'),button=document.getElementById('approve'),result=document.getElementById('result'),summary=document.getElementById('selectionSummary');
const selected=()=>Array.from(document.querySelectorAll('.rowcheck:checked')).map(el=>Number(el.dataset.id));
const fieldsFor=id=>Array.from(document.querySelectorAll('.fieldcheck:checked')).filter(el=>Number(el.dataset.id)===id).map(el=>el.dataset.field);
const update=()=>{
 const ids=selected(),partials=ids.filter(id=>fieldsFor(id).length<3),missing=ids.filter(id=>fieldsFor(id).length===0);
 summary.textContent=ids.length ? ids.map(id=>'Activity '+id+': '+(fieldsFor(id).join(', ')||'no fields selected')).join(' | ')+(partials.length?' — Partial baselines: '+partials.join(', '):'') : 'Select activities and tick each field to approve. No fields are preselected.';
 button.disabled=!confirmBox.checked||ids.length===0||missing.length>0||(partials.length>0&&!partialBox.checked);
};
confirmBox?.addEventListener('change',update);
document.querySelectorAll('.rowcheck').forEach(el=>el.addEventListener('change',()=>{
 if(!el.checked)document.querySelectorAll('.fieldcheck').forEach(f=>{if(f.dataset.id===el.dataset.id)f.checked=false;});
 confirmBox.checked=false;update();
}));
document.querySelectorAll('.fieldcheck').forEach(el=>el.addEventListener('change',()=>{
 if(el.checked){const row=document.querySelector('.rowcheck[data-id="'+el.dataset.id+'"]');if(row)row.checked=true;}
 confirmBox.checked=false;update();
}));
partialBox?.addEventListener('change',update);
button?.addEventListener('click',async()=>{
 const ids=selected();if(!confirmBox.checked||ids.length<1||ids.length>10||ids.some(id=>fieldsFor(id).length===0)||(ids.some(id=>fieldsFor(id).length<3)&&!partialBox.checked))return;
 const detail=ids.map(id=>'Activity '+id+': '+fieldsFor(id).join(', ')).join('\n');
 if(!window.confirm('Approve exactly these baseline fields?\n\n'+detail+'\n\nThis does not update activity listings.'))return;
 button.disabled=true;result.textContent='Approving reviewed listings…';
 let success=[];
 try {
   const endpoint='/api/sheets-sync-live-baseline-approve.php';
   for(const id of ids){
     const row=rows.find(x=>x.id===id);
     const fields=fieldsFor(id);
     if(fields.some(f=>!row.checks[f]?.ok))throw Error('Field eligibility changed for activity '+id);
     if(!fields.length)throw Error('No eligible fields for activity '+id);
     const expected={};expected[String(id)]={};
     for(const f of fields)expected[String(id)][f]=String(row.values[f]);
     const get=await fetch(endpoint,{credentials:'same-origin',cache:'no-store'});
     const state=await get.json();
     if(!get.ok||!state.ok||!state.enabled||!state.csrf_token)throw Error('Approval session unavailable');
     const res=await fetch(endpoint,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json'},body:JSON.stringify({confirmed:true,activity_ids:[id],fields,expected_values:expected,csrf_token:state.csrf_token})});
     const data=await res.json();
     if(!res.ok||!data.ok)throw Error('Activity '+id+': '+(data.error||'Approval failed'));
     success.push(id);
     result.textContent='Approved: '+success.join(', ')+' ('+success.length+'/'+ids.length+'). Activity writes: 0.';
   }
   result.textContent+='\nFinished. Refresh to verify approved listings disappear from this queue.';
   confirmBox.checked=false;document.querySelectorAll('.rowcheck').forEach(el=>{if(success.includes(Number(el.dataset.id))){el.checked=false;el.disabled=true;}});
   document.querySelectorAll('.fieldcheck').forEach(el=>{if(success.includes(Number(el.dataset.id))){el.checked=false;el.disabled=true;}});
 }catch(e){result.textContent='Approved before stop: '+(success.join(', ')||'none')+'\nStopped: '+e.message+'\nNo automatic retry. Refresh before continuing.';}
 update();
});
</script></main></body></html>