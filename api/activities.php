<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    require __DIR__ . '/db.php';
    $db = bh_mysql();
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = (int)($_GET['per_page'] ?? 12);
    $perPage = max(1, min(50, $perPage));
    $offset = ($page - 1) * $perPage;

    $search = trim((string)($_GET['search'] ?? ''));
    $slug = trim((string)($_GET['slug'] ?? ''));
    $activityId = isset($_GET['id']) && $_GET['id'] !== '' ? (int)$_GET['id'] : null;
    $organiserSlug = trim((string)($_GET['organiser_slug'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $town = trim((string)($_GET['town'] ?? ''));
    $region = trim((string)($_GET['region'] ?? ''));
    $day = isset($_GET['day']) ? (int)$_GET['day'] : null;
    $minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
    $maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
    $minAge = isset($_GET['min_age']) && $_GET['min_age'] !== '' ? max(0, (float)$_GET['min_age']) : null;
    $maxAge = isset($_GET['max_age']) && $_GET['max_age'] !== '' ? max(0, (float)$_GET['max_age']) : null;

    $countyColumn = false;
    try {
        $columnCheck = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bh_activities' AND COLUMN_NAME = 'county'");
        $columnCheck->execute();
        $countyColumn = ((int)$columnCheck->fetchColumn()) > 0;
    } catch (Throwable $ignored) {
        $countyColumn = false;
    }

    $accessibilityColumn = false;
    try {
        $accessibilityCheck = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bh_activities' AND COLUMN_NAME = 'accessibility'");
        $accessibilityCheck->execute();
        $accessibilityColumn = ((int)$accessibilityCheck->fetchColumn()) > 0;
    } catch (Throwable $ignored) {
        $accessibilityColumn = false;
    }

    $countySelect = $countyColumn ? 'a.county' : 'NULL AS county';
    $accessibilitySelect = $accessibilityColumn ? 'a.accessibility' : 'NULL AS accessibility';
    $infoColumns = [];
    foreach (['booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome','what_to_bring','good_to_know'] as $infoColumn) {
        try {
            $check = $db->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bh_activities' AND COLUMN_NAME = ?");
            $check->execute([$infoColumn]);
            if ((int)$check->fetchColumn() > 0) $infoColumns[$infoColumn] = true;
        } catch (Throwable $ignored) {}
    }
    $infoSelect = '';
    foreach (['booking_required','drop_in_welcome','trial_available','term_time_only','holiday_sessions','siblings_welcome','what_to_bring','good_to_know'] as $infoColumn) {
        $infoSelect .= ($infoColumns[$infoColumn] ?? false) ? ", a.$infoColumn" : ", NULL AS $infoColumn";
    }
    $where = ["LOWER(TRIM(COALESCE(a.status, ''))) IN ('published', 'publish')"];
    $params = [];

    if ($slug !== '') {
        $where[] = "LOWER(TRIM(a.slug)) = LOWER(TRIM(:slug))";
        $params[':slug'] = $slug;
    }

    if ($activityId !== null && $activityId > 0) {
        $where[] = "a.id = :activity_id";
        $params[':activity_id'] = $activityId;
    }

    if ($search !== '') {
        $where[] = "(a.title LIKE :search_title OR a.description LIKE :search_description OR a.category LIKE :search_category OR o.organisation_name LIKE :search_organisation OR v.venue_name LIKE :search_venue OR v.town LIKE :search_town)";
        $searchLike = '%' . $search . '%';
        $params[':search_title'] = $searchLike;
        $params[':search_description'] = $searchLike;
        $params[':search_category'] = $searchLike;
        $params[':search_organisation'] = $searchLike;
        $params[':search_venue'] = $searchLike;
        $params[':search_town'] = $searchLike;
    }

    if ($category !== '') {
        $where[] = "a.category = :category";
        $params[':category'] = $category;
    }

    if ($town !== '') {
        $where[] = "v.town LIKE :town";
        $params[':town'] = '%' . $town . '%';
    }

    if ($region !== '') {
        $where[] = "v.region LIKE :region";
        $params[':region'] = '%' . $region . '%';
    }

    if ($minPrice !== null) {
        $where[] = "COALESCE(a.price_from, 0) >= :min_price";
        $params[':min_price'] = $minPrice;
    }

    if ($maxPrice !== null) {
        $where[] = "COALESCE(a.price_from, 999999) <= :max_price";
        $params[':max_price'] = $maxPrice;
    }

    if ($day !== null && $day >= 1 && $day <= 7) {
        $where[] = "EXISTS (
            SELECT 1
            FROM bh_sessions s2
            JOIN bh_venues v2 ON v2.id = s2.venue_id
            WHERE v2.activity_id = a.id
              AND s2.day_of_week = :day
        )";
        $params[':day'] = $day;
    }

    $sql = "
        SELECT
            a.id,
            a.title,
            a.slug,
            a.description,
            a.category,
            a.age_range,
            $accessibilitySelect,
            $countySelect,
            a.price_from,
            a.booking_url
            $infoSelect,
            a.image_path,
            o.id AS organiser_id,
            o.organisation_name,
            o.email AS organiser_email,
            o.phone AS organiser_phone,
            o.website AS organiser_website,
            v.id AS venue_id,
            v.venue_name,
            v.address,
            v.town,
            v.region,
            v.postcode,
            v.latitude,
            v.longitude,
            v.notes AS venue_notes
        FROM bh_activities a
        LEFT JOIN bh_organisers o ON o.id = a.organiser_id
        LEFT JOIN bh_venues v ON v.activity_id = a.id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY a.title ASC, a.id ASC
    ";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $activities = [];
    foreach ($rows as $row) {
        $id = (int)$row['id'];

        if (!isset($activities[$id])) {
            $activities[$id] = [
                'id' => $id,
                'title' => $row['title'],
                'slug' => $row['slug'],
                'description' => $row['description'],
                'category' => $row['category'],
                'age_range' => $row['age_range'],
                'accessibility' => $row['accessibility'] ? (json_decode($row['accessibility'], true) ?: []) : [],
                'county' => $row['county'],
                'price_from' => $row['price_from'] !== null ? (float)$row['price_from'] : null,
                'booking_url' => $row['booking_url'],
                'booking_required' => (bool)$row['booking_required'],
                'drop_in_welcome' => (bool)$row['drop_in_welcome'],
                'trial_available' => (bool)$row['trial_available'],
                'term_time_only' => (bool)$row['term_time_only'],
                'holiday_sessions' => (bool)$row['holiday_sessions'],
                'siblings_welcome' => (bool)$row['siblings_welcome'],
                'what_to_bring' => $row['what_to_bring'] ?: '',
                'good_to_know' => $row['good_to_know'] ?: '',
                'image_path' => $row['image_path'],
                'organiser' => [
                    'id' => $row['organiser_id'] !== null ? (int)$row['organiser_id'] : null,
                    'name' => $row['organisation_name'] ?: 'Bubba Hub organiser',
                    'email' => $row['organiser_email'] ?: '',
                    'phone' => $row['organiser_phone'] ?: '',
                    'website' => $row['organiser_website'] ?: '',
                    'slug' => strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', (string)($row['organisation_name'] ?: 'organiser')), '-')),
                ],
                'venues' => [],
                'sessions' => [],
            ];
        }

        if ($row['venue_id'] !== null) {
            $venueId = (int)$row['venue_id'];

            if (!isset($activities[$id]['venues'][$venueId])) {
                $activities[$id]['venues'][$venueId] = [
                    'id' => $venueId,
                    'name' => $row['venue_name'],
                    'address' => $row['address'],
                    'town' => $row['town'],
                    'region' => $row['region'],
                    'postcode' => $row['postcode'],
                    'latitude' => $row['latitude'] !== null ? (float)$row['latitude'] : null,
                    'longitude' => $row['longitude'] !== null ? (float)$row['longitude'] : null,
                    'notes' => $row['venue_notes'],
                ];
            }
        }
    }

    if ($activities) {
        $ids = array_keys($activities);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $sessionSql = "
            SELECT
                s.id,
                v.activity_id,
                s.venue_id,
                s.day_of_week,
                s.start_time,
                s.end_time,
                s.duration_minutes,
                s.price,
                s.term_time_only,
                s.frequency,
                s.start_date,
                s.end_date
            FROM bh_sessions s
            INNER JOIN bh_venues v ON v.id = s.venue_id
            WHERE v.activity_id IN ($placeholders)
            ORDER BY s.day_of_week ASC, s.start_time ASC
        ";

        $sessionStmt = $db->prepare($sessionSql);
        $sessionStmt->execute($ids);

        foreach ($sessionStmt->fetchAll() as $session) {
            $activityId = (int)$session['activity_id'];
            if (!isset($activities[$activityId])) continue;

            $activities[$activityId]['sessions'][] = [
                'id' => (int)$session['id'],
                'venue_id' => (int)$session['venue_id'],
                'day_of_week' => (int)$session['day_of_week'],
                'start_time' => $session['start_time'],
                'end_time' => $session['end_time'],
                'duration_minutes' => $session['duration_minutes'] !== null ? (int)$session['duration_minutes'] : null,
                'price' => $session['price'] !== null ? (float)$session['price'] : null,
                'term_time_only' => (bool)$session['term_time_only'],
                'frequency' => $session['frequency'],
                'start_date' => $session['start_date'],
                'end_date' => $session['end_date'],
            ];
        }
    }

    foreach ($activities as &$activity) {
        $activity['venues'] = array_values($activity['venues']);
    }
    unset($activity);

    $activities = array_values($activities);

    if ($organiserSlug !== '') {
        $normaliseSlug = static function ($value): string {
            $value = strtolower(trim((string)$value));
            $value = str_replace('&', 'and', $value);
            $value = preg_replace('/[^a-z0-9]+/', '-', $value);
            return trim((string)$value, '-');
        };
        $wantedOrganiser = $normaliseSlug($organiserSlug);
        $activities = array_values(array_filter($activities, static function (array $activity) use ($normaliseSlug, $wantedOrganiser): bool {
            return $normaliseSlug($activity['organiser']['slug'] ?? $activity['organiser']['name'] ?? '') === $wantedOrganiser;
        }));
    }

    if ($minAge !== null || $maxAge !== null) {
        $activities = array_values(array_filter($activities, static function (array $activity) use ($minAge, $maxAge): bool {
            $text = strtolower(trim((string)($activity['age_range'] ?? '')));
            if ($text === '') return false;

            preg_match_all('/(?<!\\d)(\\d+(?:\\.\\d+)?)/', $text, $matches);
            $nums = array_map('floatval', $matches[1] ?? []);

            if (!$nums) return false;

            $rangeMin = $nums[0];
            $rangeMax = $nums[0];

            if (str_contains($text, '+')) {
                $rangeMax = 99;
            } elseif (count($nums) >= 2) {
                $rangeMax = $nums[1];
            } elseif (str_contains($text, 'under') || str_contains($text, 'less')) {
                $rangeMin = 0;
            }

            if ($minAge !== null && $rangeMax < $minAge) return false;
            if ($maxAge !== null && $rangeMin > $maxAge) return false;

            return true;
        }));
    }

    $total = count($activities);
    $paged = array_slice($activities, $offset, $perPage);

    echo json_encode([
        'ok' => true,
        'data' => $paged,
        'pagination' => [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $total > 0 ? (int)ceil($total / $perPage) : 0,
        ],
        'filters' => [
            'search' => $search,
            'category' => $category,
            'town' => $town,
            'region' => $region,
            'day' => $day,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'min_age' => $minAge,
            'max_age' => $maxAge,
            'organiser_slug' => $organiserSlug,
            'activity_id' => $activityId,
        ],
    ], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'error' => 'Unable to load activities.',
        'error_type' => get_class($e),
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_SLASHES);
}
