<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/api/sheets-sync-pilot-approval-binding.php';
$a=['activity_id'=>955,'field'=>'price_from','baseline_version'=>1,'website_value'=>'2.00','sheet_value'=>'2.50','snapshot_run_id'=>'pilot-1','snapshot_received_utc'=>'2026-10-27 15:58:41'];
function checkBinding($label,$a,$b,$expected) {
 $actual=bh_pilot_approval_binding($a,$b);
 if($actual!==$expected) throw new RuntimeException($label);
 echo "PASS: ".$label.PHP_EOL;
}
checkBinding('exact approval',$a,$a,true);
$b=$a;$b['sheet_value']='2.5';checkBinding('equivalent price',$a,$b,true);
foreach(['activity_id'=>956,'field'=>'title','baseline_version'=>2,'website_value'=>'3.00','sheet_value'=>'2.75','snapshot_run_id'=>'pilot-2','snapshot_received_utc'=>'2026-10-27 16:00:00'] as $key=>$value){
 $b=$a;$b[$key]=$value;checkBinding('changed '.$key,$a,$b,false);
}
$b=$a;unset($b['sheet_value']);checkBinding('missing field',$a,$b,false);
