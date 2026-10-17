<?php
declare(strict_types=1);
/**
 * Pure, side-effect-free matching helpers for Google Sheets sync.
 * Caller supplies trusted existing records; this file never connects to a DB.
 */
function bh_sync_normalize_id(mixed $value): ?string {
    if (!is_string($value) && !is_int($value)) return null;
    $id = trim((string)$value);
    return preg_match('/^[1-9][0-9]{0,18}$/D', $id) ? $id : null;
}
function bh_sync_normalize_url(mixed $value): ?string {
    if (!is_string($value) || trim($value)==='') return null;
    $url = trim($value);
    $parts = parse_url($url);
    if ($parts === false || !isset($parts['scheme'], $parts['host']) ||
        !in_array(strtolower($parts['scheme']), ['https','http'], true) ||
        isset($parts['user']) || isset($parts['pass'])) return null;
    $host = strtolower(rtrim($parts['host'], '.'));
    if ($host === '') return null;
    $path = rtrim($parts['path'] ?? '', '/');
    return $host . ($path === '' ? '' : $path);
}
function bh_sync_preview_matches(array $incoming, array $existing): array {
    $byId=[]; $byUrl=[]; $ambiguousId=[]; $ambiguousUrl=[];
    foreach ($existing as $record) {
        if (!is_array($record)) continue;
        $id=bh_sync_normalize_id($record['id'] ?? null);
        $url=bh_sync_normalize_url($record['booking_url'] ?? null);
        if ($id!==null) {
            if (isset($byId[$id])) $ambiguousId[$id]=true;
            else $byId[$id]=$record;
        }
        if ($url!==null) {
            if (isset($byUrl[$url])) $ambiguousUrl[$url]=true;
            else $byUrl[$url]=$record;
        }
    }
    $results=[]; $seenIds=[]; $seenUrls=[];
    foreach ($incoming as $index=>$row) {
        if (!is_array($row)) { $results[]=['row'=>$index+1,'status'=>'invalid']; continue; }
        $id=bh_sync_normalize_id($row['id'] ?? $row['activity_id'] ?? null);
        $url=bh_sync_normalize_url($row['booking_url'] ?? null);
        if (($id!==null && isset($seenIds[$id])) || ($url!==null && isset($seenUrls[$url]))) {
            $results[]=['row'=>$index+1,'status'=>'duplicate_input']; continue;
        }
        if ($id!==null) $seenIds[$id]=true;
        if ($url!==null) $seenUrls[$url]=true;
        if (($id!==null && isset($ambiguousId[$id])) || ($url!==null && isset($ambiguousUrl[$url]))) {
            $results[]=['row'=>$index+1,'status'=>'ambiguous']; continue;
        }
        $idRecord=$id!==null ? ($byId[$id] ?? null) : null;
        $urlRecord=$url!==null ? ($byUrl[$url] ?? null) : null;
        if ($idRecord!==null && $urlRecord!==null &&
            (string)$idRecord['id'] !== (string)$urlRecord['id']) {
            $results[]=['row'=>$index+1,'status'=>'conflict','reason'=>'id_url_disagree']; continue;
        }
        if ($id!==null && $idRecord===null) {
            $results[]=['row'=>$index+1,'status'=>'review','reason'=>'unknown_explicit_id']; continue;
        }
        $match=$idRecord ?? $urlRecord;
        $results[]=['row'=>$index+1,'status'=>$match===null?'new':'matched',
            'matched_id'=>$match===null?null:(string)($match['id'] ?? '')];
    }
    return $results;
}
