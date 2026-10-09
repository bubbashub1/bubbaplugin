<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_newsletter_json(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
function bh_newsletter_subject(string $frequency): string {
    $label = ucfirst($frequency);
    return "Bubba Hub {$label} Round Up";
}
function bh_newsletter_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
function bh_newsletter_base_url(): string {
    $configFile = __DIR__.'/config.php';
    $config = is_file($configFile) ? require $configFile : [];
    return rtrim((string)($config['app']['base_url'] ?? getenv('BUBBAHUB_BASE_URL') ?: 'https://bubbahub.co.uk'), '/');
}
function bh_newsletter_window(string $frequency): array {
    $now = new DateTimeImmutable('now');
    $days = $frequency === 'daily' ? 1 : ($frequency === 'monthly' ? 30 : 7);
    return [$now->modify("-{$days} days"), $now];
}
function bh_newsletter_send(PDO $db, array $user, string $frequency, bool $force=false): bool {
    $now = new DateTimeImmutable('now');
    $last = !empty($user['newsletter_last_sent_at']) ? new DateTimeImmutable((string)$user['newsletter_last_sent_at']) : null;
    if (!$force && $last) {
        $days = $frequency === 'daily' ? 1 : ($frequency === 'monthly' ? 30 : 7);
        if ($last > $now->modify("-{$days} days")) return false;
    }
    [$from, $to] = bh_newsletter_window($frequency);

    $stmt = $db->prepare("SELECT a.id,a.title,a.description,a.category,a.age_range,a.price_from,a.booking_url,a.image_path,
        v.venue_name,v.town,v.region
        FROM bh_activities a
        LEFT JOIN bh_venues v ON v.activity_id=a.id
        WHERE a.status='published' AND a.created_at>=? AND a.created_at<=?
        ORDER BY a.created_at DESC LIMIT 8");
    $stmt->execute([$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $upcoming = $db->prepare("SELECT a.id,a.title,s.starts_at,s.price,v.venue_name,v.town
        FROM bh_booking_slots s
        INNER JOIN bh_activities a ON a.id=s.activity_id
        LEFT JOIN bh_venues v ON v.id=s.venue_id
        WHERE a.status='published' AND s.status='open' AND s.starts_at>=? AND s.starts_at<=?
        ORDER BY s.starts_at ASC LIMIT 8");
    $futureDays = $frequency === 'daily' ? 7 : ($frequency === 'monthly' ? 60 : 21);
    $upcoming->execute([$to->format('Y-m-d H:i:s'), $to->modify("+{$futureDays} days")->format('Y-m-d H:i:s')]);
    $slots = $upcoming->fetchAll(PDO::FETCH_ASSOC);

    $base = bh_newsletter_base_url();
    $html = '<!doctype html><html><body style="margin:0;background:#f5f7f2;font-family:Arial,sans-serif;color:#25352d">';
    $html .= '<div style="max-width:680px;margin:0 auto;padding:24px">';
    $html .= '<div style="background:#1f4f3d;color:#fff;padding:28px;border-radius:18px 18px 0 0"><div style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;opacity:.85">Bubba Hub</div><h1 style="margin:8px 0 4px;font-size:30px">Your '.bh_newsletter_escape(ucfirst($frequency)).' Round Up</h1><p style="margin:0;opacity:.9">A little nudge towards what is happening for families.</p></div>';
    $html .= '<div style="background:#fff;padding:24px;border-radius:0 0 18px 18px">';
    $html .= '<h2 style="font-size:20px">New on Bubba Hub</h2>';
    if ($activities) {
        foreach ($activities as $a) {
            $title=bh_newsletter_escape((string)$a['title']);
            $meta=trim(implode(' · ',array_filter([(string)($a['town']??''),(string)($a['age_range']??''),$a['price_from']!==null?'From £'.number_format((float)$a['price_from'],2):''])));
            $url=$base.'/activity.html?id='.(int)$a['id'];
            $html .= '<div style="padding:14px 0;border-top:1px solid #e8ece7"><h3 style="margin:0 0 6px;font-size:17px"><a style="color:#1f4f3d;text-decoration:none" href="'.bh_newsletter_escape($url).'">'.$title.'</a></h3><div style="font-size:13px;color:#65736b">'.$meta.'</div></div>';
        }
    } else {
        $html .= '<p style="color:#65736b">No new activities were added during this period. Here are some upcoming sessions instead.</p>';
    }
    $html .= '<h2 style="font-size:20px;margin-top:26px">Coming up</h2>';
    if ($slots) {
        foreach ($slots as $s) {
            $date=(new DateTimeImmutable((string)$s['starts_at']))->format('D j M · g:ia');
            $meta=trim(implode(' · ',array_filter([$date,(string)($s['town']??''),$s['price']!==null?'£'.number_format((float)$s['price'],2):''])));
            $url=$base.'/activity.html?id='.(int)$s['id'];
            $html .= '<div style="padding:14px 0;border-top:1px solid #e8ece7"><h3 style="margin:0 0 6px;font-size:17px"><a style="color:#1f4f3d;text-decoration:none" href="'.bh_newsletter_escape($url).'">'.bh_newsletter_escape((string)$s['title']).'</a></h3><div style="font-size:13px;color:#65736b">'.bh_newsletter_escape($meta).'</div></div>';
        }
    } else {
        $html .= '<p style="color:#65736b">No upcoming bookable sessions are currently showing.</p>';
    }
    $html .= '<div style="margin-top:26px;padding:18px;background:#f1f5ef;border-radius:12px"><strong>Find more family activities</strong><p style="margin:6px 0 12px;color:#65736b">Browse Bubba Hub for local groups, classes, events and support.</p><a href="'.bh_newsletter_escape($base.'/directory.html').'" style="display:inline-block;background:#1f4f3d;color:#fff;text-decoration:none;padding:10px 16px;border-radius:9px">Explore Bubba Hub</a></div>';
    $html .= '<p style="font-size:12px;color:#7a857f;margin-top:24px">You are receiving this optional Round Up because it is enabled in your Bubba Hub notification settings. You can change the frequency or switch it off at any time in My account → Notifications.</p>';
    $html .= '</div></div></body></html>';

    $toEmail=(string)$user['email'];
    $subject=bh_newsletter_subject($frequency);
    require_once __DIR__.'/mailer.php';
    $sent=bh_send_smtp_mail($toEmail,$subject,$html,strip_tags($html),'','transactional');
    if ($sent) {
        $u=$db->prepare("UPDATE bh_user_preferences SET newsletter_last_sent_at=NOW() WHERE user_id=?");
        $u->execute([(int)$user['id']]);
    }
    return $sent;
}

try {
    require __DIR__.'/db.php';
    $db=bh_mysql();
    $db->exec("CREATE TABLE IF NOT EXISTS bh_user_preferences (
      user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      email_enabled TINYINT(1) NOT NULL DEFAULT 1,
      push_enabled TINYINT(1) NOT NULL DEFAULT 0,
      planner_reminders TINYINT(1) NOT NULL DEFAULT 1,
      booking_updates TINYINT(1) NOT NULL DEFAULT 1,
      saved_searches TINYINT(1) NOT NULL DEFAULT 0,
      support_replies TINYINT(1) NOT NULL DEFAULT 1,
      event_reminders TINYINT(1) NOT NULL DEFAULT 1,
      region VARCHAR(120) NULL,town VARCHAR(120) NULL,preferred_day VARCHAR(20) NULL,categories_json TEXT NULL,
      free_activities TINYINT(1) NOT NULL DEFAULT 0,term_time TINYINT(1) NOT NULL DEFAULT 0,max_price DECIMAL(10,2) NULL,
      newsletter_enabled TINYINT(1) NOT NULL DEFAULT 0,
      newsletter_frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
      newsletter_last_sent_at DATETIME NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->exec("ALTER TABLE bh_user_preferences ADD COLUMN IF NOT EXISTS newsletter_enabled TINYINT(1) NOT NULL DEFAULT 0");
    $db->exec("ALTER TABLE bh_user_preferences ADD COLUMN IF NOT EXISTS newsletter_frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly'");
    $db->exec("ALTER TABLE bh_user_preferences ADD COLUMN IF NOT EXISTS newsletter_last_sent_at DATETIME NULL");

    $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
    if(session_status()!==PHP_SESSION_ACTIVE) {
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
        session_start();
    }
    if(!isset($_SESSION['bh_csrf'])) $_SESSION['bh_csrf']=bin2hex(random_bytes(24));
    $userId=(int)($_SESSION['bh_user_id']??0);

    $action=trim((string)($_GET['action']??''));
    if($action==='run') {
        $configFile=__DIR__.'/config.php';
        $config=is_file($configFile)?require $configFile:[];
        $secret=trim((string)($config['newsletter']['cron_secret']??getenv('BUBBAHUB_NEWSLETTER_SECRET')?:''));
        $token=trim((string)($_GET['token']??''));
        if($secret===''||$token===''||!hash_equals($secret,$token)) bh_newsletter_json(403,['ok'=>false,'error'=>'cron_forbidden']);
        $rows=$db->query("SELECT u.id,u.email,p.newsletter_frequency,p.newsletter_last_sent_at
          FROM bh_users u INNER JOIN bh_user_preferences p ON p.user_id=u.id
          WHERE u.status='active' AND p.newsletter_enabled=1 AND p.email_enabled=1")->fetchAll(PDO::FETCH_ASSOC);
        $sent=0;
        foreach($rows as $row) if(bh_newsletter_send($db,$row,(string)$row['newsletter_frequency'])) $sent++;
        bh_newsletter_json(200,['ok'=>true,'sent'=>$sent,'checked'=>count($rows)]);
    }
    if($userId<1) bh_newsletter_json(401,['ok'=>false,'error'=>'login_required']);
    $user=$db->prepare("SELECT id,email,status FROM bh_users WHERE id=? LIMIT 1"); $user->execute([$userId]); $account=$user->fetch();
    if(!$account||$account['status']!=='active') bh_newsletter_json(401,['ok'=>false,'error'=>'login_required']);

    if($_SERVER['REQUEST_METHOD']==='GET') {
        $s=$db->prepare("SELECT newsletter_enabled,newsletter_frequency,newsletter_last_sent_at FROM bh_user_preferences WHERE user_id=?");
        $s->execute([$userId]); $p=$s->fetch()?:[];
        bh_newsletter_json(200,['ok'=>true,'newsletter'=>[
          'enabled'=>(bool)($p['newsletter_enabled']??false),
          'frequency'=>(string)($p['newsletter_frequency']??'weekly'),
          'lastSentAt'=>$p['newsletter_last_sent_at']??null
        ],'csrf'=>$_SESSION['bh_csrf']]);
    }
    if($_SERVER['REQUEST_METHOD']!=='POST') bh_newsletter_json(405,['ok'=>false,'error'=>'method_not_allowed']);
    $body=json_decode((string)file_get_contents('php://input'),true);
    if(!is_array($body)||!hash_equals((string)$_SESSION['bh_csrf'],(string)($body['csrf']??''))) bh_newsletter_json(403,['ok'=>false,'error'=>'csrf_invalid']);
    $enabled=!empty($body['enabled'])?1:0;
    $frequency=(string)($body['frequency']??'weekly');
    if(!in_array($frequency,['daily','weekly','monthly'],true)) bh_newsletter_json(422,['ok'=>false,'error'=>'invalid_frequency']);
    $sql="INSERT INTO bh_user_preferences (user_id,newsletter_enabled,newsletter_frequency) VALUES (?,?,?)
      ON DUPLICATE KEY UPDATE newsletter_enabled=VALUES(newsletter_enabled),newsletter_frequency=VALUES(newsletter_frequency)";
    $db->prepare($sql)->execute([$userId,$enabled,$frequency]);
    bh_newsletter_json(200,['ok'=>true,'newsletter'=>['enabled'=>(bool)$enabled,'frequency'=>$frequency],'csrf'=>$_SESSION['bh_csrf']]);
} catch(Throwable $e) {
    bh_newsletter_json(500,['ok'=>false,'error'=>'newsletter_error','message'=>$e->getMessage()]);
}
?>