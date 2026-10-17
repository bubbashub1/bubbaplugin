<?php
declare(strict_types=1);
/**
 * Pure three-way merge planner. No database or network writes.
 * Inputs are canonical field => scalar/null maps.
 */
function bh_sheet_sync_plan(array $base, array $sheet, array $site, array $allowedFields): array {
    $inbound=[]; $outbound=[]; $conflicts=[]; $unchanged=[];
    foreach ($allowedFields as $field) {
        if (!array_key_exists($field,$base) || !array_key_exists($field,$sheet) || !array_key_exists($field,$site)) {
            $conflicts[$field]=['reason'=>'missing_field']; continue;
        }
        $original=$base[$field]; $remote=$sheet[$field]; $local=$site[$field];
        // Strict comparison prevents accidental coercion of IDs, booleans or prices.
        $sheetChanged=$remote!==$original;
        $siteChanged=$local!==$original;
        if ($sheetChanged && $siteChanged && $remote!==$local) {
            $conflicts[$field]=['reason'=>'concurrent_edit','base'=>$original,'sheet'=>$remote,'site'=>$local];
        } elseif ($sheetChanged && !$siteChanged) {
            $inbound[$field]=$remote;
        } elseif ($siteChanged && !$sheetChanged) {
            $outbound[$field]=$local;
        } else {
            $unchanged[]=$field;
        }
    }
    return ['inbound'=>$inbound,'outbound'=>$outbound,'conflicts'=>$conflicts,'unchanged'=>$unchanged];
}
