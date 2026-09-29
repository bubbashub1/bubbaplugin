<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_start();

function bh_demo_response(int $status, array $data): never {
  http_response_code($status);
  echo json_encode($data, JSON_UNESCAPED_SLASHES);
  exit;
}

if (empty($_SESSION['bh_admin_authenticated'])) {
  bh_demo_response(401, ['ok'=>false,'error'=>'Admin login required.']);
}

try {
  require __DIR__ . '/db.php';
  $db = bh_mysql();

  $published = (int)$db->query("SELECT COUNT(*) FROM bh_activities WHERE LOWER(TRIM(status)) IN ('published','publish')")->fetchColumn();
  $target = 3;
  $created = [];

  if ($published < $target) {
    $demoActivities = [
      [
        'title'=>'Little Explorers Play',
        'slug'=>'little-explorers-play',
        'category'=>'Family activities',
        'age'=>'0-5 years',
        'description'=>'A friendly family play session for babies, toddlers and preschoolers.',
        'town'=>'Torquay',
        'region'=>'Torbay',
        'county'=>'Torbay',
        'venue'=>'Bubba Hub Test Venue',
        'postcode'=>'TQ1 1AA',
        'lat'=>50.4619,'lon'=>-3.5253,'price'=>4.50,'day'=>2,'start'=>'10:00:00','end'=>'11:00:00'
      ],
      [
        'title'=>'Bubba Baby Beats',
        'slug'=>'bubba-baby-beats',
        'category'=>'Baby classes',
        'age'=>'0-3 years',
        'description'=>'A relaxed music and movement class for babies and toddlers.',
        'town'=>'Paignton',
        'region'=>'Torbay',
        'county'=>'Torbay',
        'venue'=>'Bubba Hub Test Venue',
        'postcode'=>'TQ3 3AA',
        'lat'=>50.4353,'lon'=>-3.5670,'price'=>5.00,'day'=>4,'start'=>'10:30:00','end'=>'11:30:00'
      ],
      [
        'title'=>'Family Fun Club',
        'slug'=>'family-fun-club',
        'category'=>'Family activities',
        'age'=>'1-9 years',
        'description'=>'A simple test family activity covering the full directory card, venue and session flow.',
        'town'=>'Newton Abbot',
        'region'=>'Teignbridge',
        'county'=>'Devon',
        'venue'=>'Bubba Hub Test Venue',
        'postcode'=>'TQ12 1AA',
        'lat'=>50.5287,'lon'=>-3.6110,'price'=>6.00,'day'=>6,'start'=>'11:00:00','end'=>'12:00:00'
      ]
    ];

    $orgName = 'Bubba Hub Test Listings';
    $q = $db->prepare("SELECT id FROM bh_organisers WHERE organisation_name=? LIMIT 1");
    $q->execute([$orgName]);
    $organiserId = (int)$q->fetchColumn();

    if (!$organiserId) {
      $q = $db->prepare("INSERT INTO bh_organisers (organisation_name,slug,description,status) VALUES (?,?,?, 'published')");
      $q->execute([$orgName,'bubba-hub-test-listings','Internal directory test listings']);
      $organiserId = (int)$db->lastInsertId();
    }

    $countyColumn = false;
    try {
      $check = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_activities' AND COLUMN_NAME='county'");
      $countyColumn = ((int)$check->fetchColumn()) > 0;
    } catch (Throwable $ignored) {}

    foreach ($demoActivities as $demo) {
      if ($published + count($created) >= $target) break;

      $q = $db->prepare("SELECT id FROM bh_activities WHERE slug=? OR title=? LIMIT 1");
      $q->execute([$demo['slug'],$demo['title']]);
      if ($q->fetchColumn()) continue;

      $slug = $demo['slug'];
      $n = 2;
      while (true) {
        $q = $db->prepare("SELECT id FROM bh_activities WHERE slug=? LIMIT 1");
        $q->execute([$slug]);
        if (!$q->fetchColumn()) break;
        $slug = $demo['slug'].'-'.$n++;
      }

      if ($countyColumn) {
        $q = $db->prepare("INSERT INTO bh_activities (organiser_id,title,slug,description,category,age_range,county,price_from,status) VALUES (?,?,?,?,?,?,?,?,'published')");
        $q->execute([$organiserId,$demo['title'],$slug,$demo['description'],$demo['category'],$demo['age'],$demo['county'],$demo['price']]);
      } else {
        $q = $db->prepare("INSERT INTO bh_activities (organiser_id,title,slug,description,category,age_range,price_from,status) VALUES (?,?,?,?,?,?,?,'published')");
        $q->execute([$organiserId,$demo['title'],$slug,$demo['description'],$demo['category'],$demo['age'],$demo['price']]);
      }
      $activityId = (int)$db->lastInsertId();

      $q = $db->prepare("INSERT INTO bh_venues (activity_id,venue_name,address,town,region,postcode,latitude,longitude,notes) VALUES (?,?,?,?,?,?,?,?,?)");
      $q->execute([$activityId,$demo['venue'],'Test address',$demo['town'],$demo['region'],$demo['postcode'],$demo['lat'],$demo['lon'],'Internal test listing']);
      $venueId = (int)$db->lastInsertId();

      $q = $db->prepare("INSERT INTO bh_sessions (venue_id,day_of_week,start_time,end_time,duration_minutes,price,frequency) VALUES (?,?,?,?,?,?,?)");
      $q->execute([$venueId,$demo['day'],$demo['start'],$demo['end'],60,$demo['price'],'weekly']);

      $created[] = ['id'=>$activityId,'title'=>$demo['title']];
    }
  }

  $finalCount = (int)$db->query("SELECT COUNT(*) FROM bh_activities WHERE LOWER(TRIM(status)) IN ('published','publish')")->fetchColumn();

  bh_demo_response(200, [
    'ok'=>true,
    'created'=>$created,
    'published_count'=>$finalCount,
    'message'=>$finalCount >= 3
      ? ($created ? 'Directory test listings created.' : 'Directory already has at least 3 published activities.')
      : 'Directory still has fewer than 3 published activities.'
  ]);
} catch (Throwable $e) {
  bh_demo_response(500, ['ok'=>false,'error'=>'Could not prepare directory test listings.','detail'=>$e->getMessage()]);
}
?>