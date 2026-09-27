<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();

function bh_admin_response(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

if (empty($_SESSION['bh_admin_authenticated'])) {
    bh_admin_response(401, ['ok'=>false,'error'=>'Admin login required.']);
}

try {
    require __DIR__ . '/db.php';
    $db = bh_mysql();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->query(
            "SELECT a.id,a.title,a.category,a.age_range,a.price_from,a.status,
                    o.id AS organiser_id,o.organisation_name,
                    COUNT(DISTINCT v.id) AS venue_count
             FROM bh_activities a
             INNER JOIN bh_organisers o ON o.id=a.organiser_id
             LEFT JOIN bh_venues v ON v.activity_id=a.id
             GROUP BY a.id
             ORDER BY a.updated_at DESC,a.id DESC"
        );
        bh_admin_response(200, ['ok'=>true,'data'=>$stmt->fetchAll()]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        bh_admin_response(405, ['ok'=>false,'error'=>'GET or POST required.']);
    }

    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) bh_admin_response(400,['ok'=>false,'error'=>'Invalid JSON.']);

    $title = trim((string)($input['title'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $category = trim((string)($input['category'] ?? ''));
    $ageRange = trim((string)($input['age_range'] ?? ''));
    $priceFrom = $input['price_from'] === '' || !isset($input['price_from']) ? null : (float)$input['price_from'];
    $bookingUrl = trim((string)($input['booking_url'] ?? ''));
    $organisationName = trim((string)($input['organisation_name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $phone = trim((string)($input['phone'] ?? ''));
    $website = trim((string)($input['website'] ?? ''));
    $venueName = trim((string)($input['venue_name'] ?? ''));
    $address = trim((string)($input['address'] ?? ''));
    $town = trim((string)($input['town'] ?? ''));
    $region = trim((string)($input['region'] ?? ''));
    $postcode = trim((string)($input['postcode'] ?? ''));
    $latitude = $input['latitude'] === '' || !isset($input['latitude']) ? null : (float)$input['latitude'];
    $longitude = $input['longitude'] === '' || !isset($input['longitude']) ? null : (float)$input['longitude'];

    if ($title==='' || $organisationName==='' || $venueName==='' || $town==='' || $category==='') {
        bh_admin_response(422,['ok'=>false,'error'=>'Title, organisation, category, venue and town are required.']);
    }

    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $title), '-'));
    $baseSlug=$slug;
    $n=2;
    while (true) {
        $check=$db->prepare("SELECT id FROM bh_activities WHERE slug=? LIMIT 1");
        $check->execute([$slug]);
        if (!$check->fetch()) break;
        $slug=$baseSlug.'-'.$n++;
    }

    $db->beginTransaction();
    try {
        $find=$db->prepare("SELECT id FROM bh_organisers WHERE organisation_name=? LIMIT 1");
        $find->execute([$organisationName]);
        $organiserId=$find->fetchColumn();

        if (!$organiserId) {
            $oslug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-', $organisationName), '-'));
            $base=$oslug; $n=2;
            while (true) {
                $q=$db->prepare("SELECT id FROM bh_organisers WHERE slug=? LIMIT 1");
                $q->execute([$oslug]);
                if (!$q->fetch()) break;
                $oslug=$base.'-'.$n++;
            }
            $q=$db->prepare("INSERT INTO bh_organisers (organisation_name,slug,description,email,phone,website,status) VALUES (?,?,?,?,?,?, 'published')");
            $q->execute([$organisationName,$oslug,'',$email,$phone,$website]);
            $organiserId=(int)$db->lastInsertId();
        }

        $q=$db->prepare("INSERT INTO bh_activities (organiser_id,title,slug,description,category,age_range,price_from,booking_url,status) VALUES (?,?,?,?,?,?,?,?, 'published')");
        $q->execute([$organiserId,$title,$slug,$description,$category,$ageRange,$priceFrom,$bookingUrl]);
        $activityId=(int)$db->lastInsertId();

        $q=$db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");
        $q->execute([$activityId,$venueName,$address,$town,$region,$postcode,$latitude,$longitude,'']);
        $venueId=(int)$db->lastInsertId();

        $sessions=is_array($input['sessions'] ?? null) ? $input['sessions'] : [];
        foreach ($sessions as $s) {
            $day=(int)($s['day_of_week'] ?? 0);
            $start=trim((string)($s['start_time'] ?? ''));
            $end=trim((string)($s['end_time'] ?? ''));
            if ($day<1 || $day>7 || $start==='') continue;
            $duration=null;
            if ($end!=='') {
                $a=strtotime($start); $b=strtotime($end);
                if ($a!==false && $b!==false) { $duration=(int)(($b-$a)/60); if ($duration<0) $duration+=1440; }
            }
            $price=($s['price'] ?? '')==='' ? $priceFrom : (float)$s['price'];
            $term=!empty($s['term_time_only']) ? 1 : 0;
            $frequency=trim((string)($s['frequency'] ?? 'weekly')) ?: 'weekly';
            $q=$db->prepare("INSERT INTO bh_sessions (venue_id,day_of_week,start_time,end_time,duration_minutes,price,term_time_only,frequency,start_date,end_date) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $q->execute([$venueId,$day,$start,$end!==''?$end:null,$duration,$price,$term,$frequency,null,null]);
        }

        $db->commit();
        bh_admin_response(201,['ok'=>true,'id'=>$activityId,'slug'=>$slug]);
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
} catch (Throwable $e) {
    bh_admin_response(500,['ok'=>false,'error_type'=>get_class($e),'error'=>$e->getMessage()]);
}
