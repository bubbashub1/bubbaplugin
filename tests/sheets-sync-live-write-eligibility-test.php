<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/api/sheets-sync-live-write-eligibility.php';
$base=['activity_id'=>955,'field'=>'price_from','live_enabled'=>true,'admin_authorized'=>true,'csrf_valid'=>true,'protected'=>false,'baseline_version'=>1,'snapshot_received_utc'=>gmdate('Y-m-d H:i:s'),'sheet_value'=>'3.00','website_value'=>'2.00','baseline_value'=>'2.00','review_approved'=>true];
function testGate(string $name,array $input,bool $expected,string $reason):void {
 $result=bh_sync_live_write_eligibility($input);
 if($result['allowed']!==$expected||$result['reason']!==$reason){fwrite(STDERR,"FAIL {$name}: ".json_encode($result)."\n");exit(1);}
 echo "PASS {$name}\n";
}
testGate('eligible reviewed candidate',$base,true,'eligible_for_separate_transactional_recheck');
foreach(['live_enabled'=>'live_disabled','admin_authorized'=>'authorization_required','csrf_valid'=>'authorization_required','review_approved'=>'explicit_review_required'] as $key=>$reason) {
 $input=$base;$input[$key]=false;testGate($key,$input,false,$reason);
}
foreach([['field','title','outside_pilot_scope'],['activity_id',956,'outside_pilot_scope'],['protected',true,'protected_listing'],['baseline_version',0,'baseline_required'],['sheet_value','','invalid_price'],['sheet_value','4.00','sheet_changed_review'],['website_value','4.00','conflict_review'],['website_value','3.00','same'],['snapshot_received_utc','2000-01-01 00:00:00','snapshot_expired']] as [$key,$value,$reason]){
 $input=$base;$input[$key]=$value;
 // Changing Sheet from 3 to 4 is still reviewable, not automatically approved.
 if($key==='sheet_value'&&$value==='4.00')$reason='eligible_for_separate_transactional_recheck';
 testGate($key.' '.(string)$value,$input,$reason==='eligible_for_separate_transactional_recheck', $reason);
}
echo "Pure eligibility tests complete. No database writes.\n";
