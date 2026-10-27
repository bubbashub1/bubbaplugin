<?php
declare(strict_types=1);
/**
 * Run: php tests/sheets-sync-baseline-guard-test.php
 * Pure regression checks: no database, HTTP, credentials or website writes.
 */
require_once dirname(__DIR__).'/api/sheets-sync-baseline-guard.php';
function checkStatus(string $label,array $sheet,array $website,?array $baseline,string $expected):void {
    $result=bh_sync_baseline_field_status($sheet,$website,$baseline,['price_from']);
    $actual=$result['price_from']['status']??'missing';
    if($actual!==$expected) {
        fwrite(STDERR,"FAIL {$label}: expected {$expected}, got {$actual}\n");
        exit(1);
    }
    echo "PASS {$label}: {$actual}\n";
}
checkStatus('matching values',['price_from'=>'2'],['price_from'=>'2.00'],['price_from'=>'2.00'],'same');
checkStatus('sheet changed',['price_from'=>'3.00'],['price_from'=>'2.00'],['price_from'=>'2.00'],'sheet_changed_review');
checkStatus('blank ignored',['price_from'=>''],['price_from'=>'2.00'],['price_from'=>'2.00'],'blank_sheet_ignored');
checkStatus('whitespace ignored',['price_from'=>'   '],['price_from'=>'2.00'],['price_from'=>'2.00'],'blank_sheet_ignored');
checkStatus('both changed',['price_from'=>'3.00'],['price_from'=>'4.00'],['price_from'=>'2.00'],'conflict_review');
checkStatus('website changed',['price_from'=>'2.00'],['price_from'=>'4.00'],['price_from'=>'2.00'],'website_changed_review');
checkStatus('missing baseline',['price_from'=>'3.00'],['price_from'=>'2.00'],null,'baseline_required');
checkStatus('partial baseline',['price_from'=>'3.00'],['price_from'=>'2.00'],['title'=>'Example'],'baseline_required');
checkStatus('invalid sheet',['price_from'=>['3']],['price_from'=>'2.00'],['price_from'=>'2.00'],'invalid_sheet_value');
checkStatus('invalid website',['price_from'=>'3'],['price_from'=>['2']],['price_from'=>'2.00'],'invalid_website_value');
checkStatus('invalid baseline',['price_from'=>'3'],['price_from'=>'2.00'],['price_from'=>['2']],'invalid_baseline');
echo "All baseline guard regression tests passed. No writes performed.\n";
