<?php
declare(strict_types=1);

require dirname(__DIR__).'/api/db.php';

$base = rtrim((string)(getenv('BUBBAHUB_BASE_URL') ?: 'https://bubbahub.co.uk/beta'), '/');
$secret = trim((string)(getenv('BUBBAHUB_PUSH_SECRET') ?: ''));
if ($secret === '') {
    fwrite(STDERR, "BUBBAHUB_PUSH_SECRET is not configured.\n");
    exit(1);
}

$db = bh_mysql();
$db->exec("CREATE TABLE IF NOT EXISTS bh_push_notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  slot_id BIGINT UNSIGNED NULL,
  notification_type VARCHAR(60) NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_push_notification (user_id,slot_id,notification_type),
  INDEX idx_push_notification_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$from = (new DateTimeImmutable('now'))->modify('+23 hours');
$to = (new DateTimeImmutable('now'))->modify('+25 hours');

$stmt = $db->prepare("SELECT DISTINCT
    p.user_id, s.id AS slot_id, a.title, s.starts_at,
    v.venue_name, v.town
  FROM bh_planner p
  INNER JOIN bh_user_preferences up ON up.user_id=p.user_id
  INNER JOIN bh_booking_slots s ON s.activity_id=p.activity_id
  INNER JOIN bh_activities a ON a.id=s.activity_id
  LEFT JOIN bh_venues v ON v.id=s.venue_id
  LEFT JOIN bh_push_notifications pn
    ON pn.user_id=p.user_id AND pn.slot_id=s.id AND pn.notification_type='planner_reminder'
  WHERE up.push_enabled=1 AND up.planner_reminders=1
    AND s.status='open' AND a.status='published'
    AND s.starts_at>=? AND s.starts_at<=? AND pn.id IS NULL
  ORDER BY s.starts_at ASC");
$stmt->execute([$from->format('Y-m-d H:i:s'),$to->format('Y-m-d H:i:s')]);
$rows=$stmt->fetchAll(PDO::FETCH_ASSOC);

$sent=0; $failed=0;
foreach($rows as $row){
    $start=(new DateTimeImmutable((string)$row['starts_at']))->format('D j M \\a\\t g:ia');
    $where=trim(implode(' · ',array_filter([(string)($row['venue_name']??''),(string)($row['town']??'')])));

    $payload=[
      'user_id'=>(int)$row['user_id'],
      'title'=>'Coming up tomorrow',
      'message'=>(string)$row['title'].' is coming up '.$start.($where?' · '.$where:'').'.',
      'url'=>$base.'/activity.html?id='.(int)$row['activity_id'],
      'tag'=>'planner-'.$row['slot_id']
    ];

    $ch=curl_init($base.'/api/push.php?action=send');
    curl_setopt_array($ch,[
      CURLOPT_RETURNTRANSFER=>true,
      CURLOPT_POST=>true,
      CURLOPT_HTTPHEADER=>['Content-Type: application/json','Accept: application/json','X-BubbaHub-Push-Secret: '.$secret],
      CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
      CURLOPT_TIMEOUT=>20
    ]);
    $response=curl_exec($ch);
    $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data=json_decode((string)$response,true);
    if($http>=200 && $http<300 && !empty($data['ok']) && (int)($data['sent']??0)>0){
        $db->prepare("INSERT IGNORE INTO bh_push_notifications (user_id,slot_id,notification_type) VALUES (?,?,?)")
           ->execute([(int)$row['user_id'],(int)$row['slot_id'],'planner_reminder']);
        $sent++;
    }else{
        $failed++;
    }
}
echo json_encode(['ok'=>true,'checked'=>count($rows),'sent'=>$sent,'failed'=>$failed],JSON_UNESCAPED_SLASHES).PHP_EOL;
?>