<?php
declare(strict_types=1);
/** Read-only, allowlisted comparison. No write operations or sync baselines. */
function bh_sync_field_preview(array $sheet, array $website): array {
    $limits = ['title'=>500, 'description'=>20000, 'age_range'=>250, 'booking_url'=>2048];
    $result = [];
    foreach ($limits as $field => $maxLength) {
        if (!array_key_exists($field, $sheet)) continue;
        $incoming = $sheet[$field];
        if (!is_string($incoming) || strlen($incoming) > $maxLength ||
            ($field === 'title' && trim($incoming) === '')) {
            $result[$field] = ['status'=>'invalid_sheet_value'];
            continue;
        }
        if ($field === 'booking_url' && trim($incoming) !== '' &&
            bh_sync_normalize_url($incoming) === null) {
            $result[$field] = ['status'=>'invalid_sheet_value'];
            continue;
        }
        $current = (string)($website[$field] ?? '');
        $same = trim($incoming) === trim($current);
        $result[$field] = [
            'status'=>$same?'same':'different_review',
            'website_value'=>$current,
            'sheet_value'=>$incoming,
            'requires_review'=>!$same,
            'conflict_confirmed'=>false
        ];
    }
    if (array_key_exists('price_from', $sheet)) {
        $incoming = $sheet['price_from'];
        if ((!is_string($incoming) && !is_int($incoming) && !is_float($incoming)) ||
            !preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\\.[0-9]{1,2})?$/D', trim((string)$incoming))) {
            $result['price_from'] = ['status'=>'invalid_sheet_value'];
        } else {
            $current = (string)($website['price_from'] ?? '');
            $same = $current !== '' && abs((float)$current - (float)$incoming) < 0.001;
            $result['price_from'] = [
                'status'=>$same?'same':'different_review',
                'website_value'=>$current,
                'sheet_value'=>(string)$incoming,
                'requires_review'=>!$same,
                'conflict_confirmed'=>false
            ];
        }
    }
    return $result;
}
