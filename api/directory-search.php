<?php
declare(strict_types=1);

/**
 * Public, read-only search across Bubba Hub's six directory sources.
 * Only published rows are returned. Column identifiers are discovered from
 * the database and checked against each widget's selected field settings.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=30, stale-while-revalidate=60');

function bh_ds_reply(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    require_once __DIR__ . '/db.php';
    $db = bh_mysql();

    $sources = [
        'activities' => ['label'=>'Activities / Classes','table'=>'bh_activities','url'=>'/activity.html','title'=>'title'],
        'businesses' => ['label'=>'Businesses','table'=>'bh_directory_businesses','url'=>'/business.html','title'=>'title'],
        'childcare' => ['label'=>'Childcare','table'=>'bh_directory_childcare','url'=>'/childcare.html','title'=>'title'],
        'events' => ['label'=>'Events','table'=>'bh_directory_events','url'=>'/event.html','title'=>'title'],
        'places' => ['label'=>'Places','table'=>'bh_directory_places','url'=>'/place.html','title'=>'title'],
        'school_clubs' => ['label'=>'School Clubs','table'=>'bh_directory_school_clubs','url'=>'/school-club.html','title'=>'title'],
    ];

    $raw = file_get_contents('php://input');
    $body = is_string($raw) && trim($raw) !== '' ? json_decode($raw, true) : [];
    if (!is_array($body)) $body = [];
    $input = array_merge($_GET, $body);

    $q = trim((string)($input['q'] ?? $input['search'] ?? ''));
    if (mb_strlen($q) > 120) $q = mb_substr($q, 0, 120);
    $scope = strtolower(trim((string)($input['scope'] ?? 'all')));
    if ($scope !== 'all' && !isset($sources[$scope])) bh_ds_reply(422, ['ok'=>false,'error'=>'Invalid directory scope.']);
    $limit = max(1, min(60, (int)($input['limit'] ?? 30)));

    $decodeMap = static function ($value): array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? $value : [];
    };
    $searchMap = $decodeMap($input['search_fields'] ?? []);
    $filterMap = $decodeMap($input['filter_fields'] ?? []);
    $filters = $decodeMap($input['filters'] ?? []);

    $dateMode = strtolower(trim((string)($input['event_date'] ?? '')));
    $dateStart = trim((string)($input['date_from'] ?? ''));
    $dateEnd = trim((string)($input['date_to'] ?? ''));
    $validDate = static fn(string $v): bool => $v === '' || (bool)preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $v);
    if (!$validDate($dateStart) || !$validDate($dateEnd)) bh_ds_reply(422, ['ok'=>false,'error'=>'Dates must use YYYY-MM-DD.']);

    $results = [];
    $warnings = [];
    $keys = $scope === 'all' ? array_keys($sources) : [$scope];

    foreach ($keys as $key) {
        $source = $sources[$key];
        try {
            $columnRows = $db->query("SHOW COLUMNS FROM `".$source['table']."`")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $warnings[] = $key . ': directory unavailable';
            continue;
        }
        $columns = [];
        foreach ($columnRows as $row) {
            $name = (string)$row['Field'];
            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name)) $columns[$name] = strtolower((string)$row['Type']);
        }
        if (!isset($columns['id'], $columns['title'], $columns['status'])) {
            $warnings[] = $key . ': required directory fields unavailable';
            continue;
        }

        $textColumns = [];
        $filterableColumns = [];
        foreach ($columns as $name => $type) {
            if (preg_match('/^(varchar|char|text|mediumtext|longtext|tinytext)/', $type)
                && !in_array($name, ['status','slug','contact_email','contact_phone','website','website_url','booking_url','verification_url','facebook_url','instagram_url'], true)) {
                $textColumns[] = $name;
            }
            if (in_array($name, ['category','town','region','age_min','age_max','age_min_months','age_max_months','price','price_from','price_type','term_time_only','school_name','venue_name'], true)) {
                $filterableColumns[] = $name;
            }
        }

        $selectedSearch = $searchMap[$key] ?? [];
        if (!is_array($selectedSearch) || !$selectedSearch) $selectedSearch = array_values(array_intersect(['title','summary','description','town','region','category','school_name','venue_name'], $textColumns));
        $selectedSearch = array_values(array_unique(array_intersect(array_filter($selectedSearch, 'is_string'), $textColumns)));
        $selectedFilters = $filterMap[$key] ?? [];
        if (!is_array($selectedFilters) || !$selectedFilters) $selectedFilters = $filterableColumns;
        $selectedFilters = array_values(array_unique(array_intersect(array_filter($selectedFilters, 'is_string'), $filterableColumns)));

        $where = ["LOWER(TRIM(COALESCE(status,''))) IN ('published','publish')"];
        $params = [];
        if ($q !== '' && $selectedSearch) {
            $likes = [];
            foreach ($selectedSearch as $i => $field) {
                $param = ':q'.$i;
                $likes[] = "`".$field."` LIKE ".$param;
                $params[$param] = '%'.$q.'%';
            }
            $where[] = '(' . implode(' OR ', $likes) . ')';
        }

        foreach ($selectedFilters as $field) {
            if (!array_key_exists($field, $filters) || is_array($filters[$field]) || is_object($filters[$field])) continue;
            $value = trim((string)$filters[$field]);
            if ($value === '') continue;
            $param = ':f_' . $field;
            $where[] = "`".$field."` = ".$param;
            $params[$param] = $value;
        }

        $eventJoin = '';
        $eventSelect = 'NULL AS next_occurrence';
        if ($key === 'events') {
            $hasOccurrences = (bool)$db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_directory_event_occurrences'")->fetchColumn();
            if ($hasOccurrences) {
                $eventJoin = " LEFT JOIN (SELECT event_id, MIN(starts_at) AS next_occurrence FROM bh_directory_event_occurrences WHERE occurrence_status='scheduled' AND starts_at >= NOW() GROUP BY event_id) occ ON occ.event_id = d.id ";
                $eventSelect = 'occ.next_occurrence';
                if ($dateMode !== '' || $dateStart !== '' || $dateEnd !== '') {
                    $dateWhere = [];
                    if ($dateMode === 'today') $dateWhere[] = 'DATE(occ.next_occurrence)=CURDATE()';
                    elseif ($dateMode === 'weekend') $dateWhere[] = 'WEEKDAY(occ.next_occurrence) IN (5,6) AND occ.next_occurrence < DATE_ADD(CURDATE(), INTERVAL (7-WEEKDAY(CURDATE())) DAY)';
                    elseif ($dateMode === 'month') $dateWhere[] = 'YEAR(occ.next_occurrence)=YEAR(CURDATE()) AND MONTH(occ.next_occurrence)=MONTH(CURDATE())';
                    if ($dateStart !== '') { $dateWhere[] = 'DATE(occ.next_occurrence) >= :date_from'; $params[':date_from'] = $dateStart; }
                    if ($dateEnd !== '') { $dateWhere[] = 'DATE(occ.next_occurrence) <= :date_to'; $params[':date_to'] = $dateEnd; }
                    if ($dateWhere) $where[] = 'EXISTS (SELECT 1 FROM bh_directory_event_occurrences eo WHERE eo.event_id=d.id AND eo.occurrence_status=\'scheduled\' AND eo.starts_at >= NOW() AND '.implode(' AND ', $dateWhere).')';
                }
            }
        }

        $selectFields = ['d.id','d.title'];
        foreach (['slug','summary','description','category','town','region','address','postcode','image_path','image_url','price_from','age_min','age_max','age_min_months','age_max_months','school_name','venue_name'] as $optional) {
            if (isset($columns[$optional])) $selectFields[] = 'd.`'.$optional.'`';
        }
        $sql = 'SELECT '.implode(',', $selectFields).', '.$eventSelect.' FROM `'.$source['table'].'` d '.$eventJoin.' WHERE '.implode(' AND ', array_map(static fn($w) => str_replace(['status','title','id'], ['d.status','d.title','d.id'], $w), $where)).' ORDER BY '.($key === 'events' && $eventSelect !== 'NULL AS next_occurrence' ? 'occ.next_occurrence IS NULL, occ.next_occurrence ASC, ' : '').'d.title ASC LIMIT '.(int)$limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $id = (int)$row['id'];
            $slug = trim((string)($row['slug'] ?? ''));
            $href = $source['url'] . ($key === 'activities' && $slug !== '' ? '?slug='.rawurlencode($slug) : '?id='.$id);
            $results[] = [
                'id'=>$id, 'directory'=>$key, 'directory_label'=>$source['label'],
                'title'=>(string)$row['title'], 'summary'=>(string)($row['summary'] ?? ''),
                'description'=>(string)($row['description'] ?? ''), 'category'=>(string)($row['category'] ?? ''),
                'town'=>(string)($row['town'] ?? ''), 'region'=>(string)($row['region'] ?? ''),
                'image'=>(string)($row['image_path'] ?? $row['image_url'] ?? ''),
                'price_from'=>$row['price_from'] ?? null, 'next_occurrence'=>$row['next_occurrence'] ?? null,
                'href'=>$href,
            ];
        }
    }

    usort($results, static fn($a,$b) => strcmp($a['title'],$b['title']));
    $results = array_slice($results, 0, $limit);
    bh_ds_reply(200, ['ok'=>true,'query'=>$q,'scope'=>$scope,'count'=>count($results),'results'=>$results,'warnings'=>$warnings]);
} catch (Throwable $e) {
    error_log('Public directory search: '.$e->getMessage());
    bh_ds_reply(500, ['ok'=>false,'error'=>'Directory search is temporarily unavailable.']);
}
