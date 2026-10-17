<?php
declare(strict_types=1);
require dirname(__DIR__).'/api/sheets-sync-diff.php';
function check(bool $condition,string $name): void {
    if (!$condition) {fwrite(STDERR,"FAIL: ".$name."\n");exit(1);}
}
$base=['title'=>'Baby Club','description'=>'Original'];
$sheet=['title'=>'Baby Club Plus','description'=>'Original'];
$site=['title'=>'Baby Club','description'=>'Site edit'];
$p=bh_sheet_sync_plan($base,$sheet,$site,['title','description']);
check($p['inbound']===['title'=>'Baby Club Plus'],'sheet-only edit');
check($p['outbound']===['description'=>'Site edit'],'site-only edit');
$p=bh_sheet_sync_plan(['title'=>'Old'],['title'=>'Sheet'],['title'=>'Website'],['title']);
check(isset($p['conflicts']['title']),'conflict protected');
$p=bh_sheet_sync_plan(['title'=>'Old'],['title'=>'New'],['title'=>'New'],['title']);
check(!$p['conflicts'] && !$p['inbound'] && !$p['outbound'],'identical concurrent changes');
$p=bh_sheet_sync_plan(['title'=>'Old'],[],['title'=>'Old'],['title']);
check(($p['conflicts']['title']['reason']??'')==='missing_field','missing field protected');
echo "All Sheets sync diff tests passed\n";
