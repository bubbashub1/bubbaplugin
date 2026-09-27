<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_preferences_json(int $status,array $data): never{
    http_response_code($status);
    echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}
function bh_preferences_default(): array{
    return [
        'emailEnabled'=>true,'smsEnabled'=>false,'pushEnabled'=>false,'phone'=>'','smsMarketing'=>false,
        'plannerReminders'=>true,'bookingUpdates'=>true,'savedSearches'=>false,'supportReplies'=>true,'eventReminders'=>true,
        'region'=>'','town'=>'','day'=>'','categories'=>[],'freeActivities'=>false,'termTime'=>false,'maxPrice'=>''
    ];
}
try{
    require __DIR__.'/db.php';
    $secure=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off');
    if(session_status()!==PHP_SESSION_ACTIVE){
        session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
        session_start();
    }
    $userId=(int)($_SESSION['bh_user_id']??0);
    if($userId<1) bh_preferences_json(401,['ok'=>false,'error'=>'login_required','message'=>'Please sign in to save your preferences.']);
    $db=bh_mysql();
    $user=$db->prepare("SELECT id,status FROM bh_users WHERE id=? LIMIT 1");
    $user->execute([$userId]);
    $account=$user->fetch();
    if(!$account||($account['status']??'')!=='active') bh_preferences_json(401,['ok'=>false,'error'=>'login_required']);

    $db->exec("CREATE TABLE IF NOT EXISTS bh_user_preferences (
      user_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
      email_enabled TINYINT(1) NOT NULL DEFAULT 1,
      sms_enabled TINYINT(1) NOT NULL DEFAULT 0,
      push_enabled TINYINT(1) NOT NULL DEFAULT 0,
      phone VARCHAR(80) NULL,
      sms_marketing TINYINT(1) NOT NULL DEFAULT 0,
      planner_reminders TINYINT(1) NOT NULL DEFAULT 1,
      booking_updates TINYINT(1) NOT NULL DEFAULT 1,
      saved_searches TINYINT(1) NOT NULL DEFAULT 0,
      support_replies TINYINT(1) NOT NULL DEFAULT 1,
      event_reminders TINYINT(1) NOT NULL DEFAULT 1,
      region VARCHAR(120) NULL,
      town VARCHAR(120) NULL,
      preferred_day VARCHAR(20) NULL,
      categories_json TEXT NULL,
      free_activities TINYINT(1) NOT NULL DEFAULT 0,
      term_time TINYINT(1) NOT NULL DEFAULT 0,
      max_price DECIMAL(10,2) NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    if(!isset($_SESSION['bh_csrf'])) $_SESSION['bh_csrf']=bin2hex(random_bytes(24));

    $stmt=$db->prepare("SELECT * FROM bh_user_preferences WHERE user_id=? LIMIT 1");
    $stmt->execute([$userId]);
    $row=$stmt->fetch();

    if($_SERVER['REQUEST_METHOD']==='GET'){
        $p=bh_preferences_default();
        if($row){
            $p=array_merge($p,[
                'emailEnabled'=>(bool)$row['email_enabled'],'smsEnabled'=>(bool)$row['sms_enabled'],'pushEnabled'=>(bool)$row['push_enabled'],
                'phone'=>(string)($row['phone']??''),'smsMarketing'=>(bool)$row['sms_marketing'],
                'plannerReminders'=>(bool)$row['planner_reminders'],'bookingUpdates'=>(bool)$row['booking_updates'],
                'savedSearches'=>(bool)$row['saved_searches'],'supportReplies'=>(bool)$row['support_replies'],
                'eventReminders'=>(bool)$row['event_reminders'],'region'=>(string)($row['region']??''),'town'=>(string)($row['town']??''),
                'day'=>(string)($row['preferred_day']??''),'freeActivities'=>(bool)$row['free_activities'],
                'termTime'=>(bool)$row['term_time'],'maxPrice'=>$row['max_price']===null?'':(string)$row['max_price']
            ]);
            $decoded=json_decode((string)($row['categories_json']??''),true);
            $p['categories']=is_array($decoded)?array_values(array_map('strval',$decoded)):[];
        }
        bh_preferences_json(200,['ok'=>true,'preferences'=>$p,'csrf'=>$_SESSION['bh_csrf']]);
    }

    if($_SERVER['REQUEST_METHOD']!=='POST') bh_preferences_json(405,['ok'=>false,'error'=>'method_not_allowed']);
    $body=json_decode((string)file_get_contents('php://input'),true);
    if(!is_array($body)) bh_preferences_json(400,['ok'=>false,'error'=>'invalid_json']);
    $csrf=(string)($body['csrf']??'');
    if($csrf===''||!hash_equals((string)$_SESSION['bh_csrf'],$csrf)) bh_preferences_json(403,['ok'=>false,'error'=>'csrf_invalid']);

    $categories=array_values(array_unique(array_filter(array_map('trim',(array)($body['categories']??[])),static fn($v)=>$v!=='')));
    $maxPrice=$body['maxPrice']??'';
    if($maxPrice!=='' && (!is_numeric($maxPrice)||((float)$maxPrice<0))) bh_preferences_json(422,['ok'=>false,'error'=>'invalid_max_price']);
    $maxPriceValue=$maxPrice===''?null:(float)$maxPrice;

    $values=[
        !empty($body['emailEnabled'])?1:0,!empty($body['smsEnabled'])?1:0,!empty($body['pushEnabled'])?1:0,
        trim((string)($body['phone']??''))?:null,!empty($body['smsMarketing'])?1:0,
        !empty($body['plannerReminders'])?1:0,!empty($body['bookingUpdates'])?1:0,!empty($body['savedSearches'])?1:0,
        !empty($body['supportReplies'])?1:0,!empty($body['eventReminders'])?1:0,
        trim((string)($body['region']??''))?:null,trim((string)($body['town']??''))?:null,
        trim((string)($body['day']??''))?:null,json_encode($categories,JSON_UNESCAPED_UNICODE),!empty($body['freeActivities'])?1:0,
        !empty($body['termTime'])?1:0,$maxPriceValue
    ];
    $sql="INSERT INTO bh_user_preferences
      (user_id,email_enabled,sms_enabled,push_enabled,phone,sms_marketing,planner_reminders,booking_updates,saved_searches,support_replies,event_reminders,region,town,preferred_day,categories_json,free_activities,term_time,max_price)
      VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE
      email_enabled=VALUES(email_enabled),sms_enabled=VALUES(sms_enabled),push_enabled=VALUES(push_enabled),phone=VALUES(phone),
      sms_marketing=VALUES(sms_marketing),planner_reminders=VALUES(planner_reminders),booking_updates=VALUES(booking_updates),
      saved_searches=VALUES(saved_searches),support_replies=VALUES(support_replies),event_reminders=VALUES(event_reminders),
      region=VALUES(region),town=VALUES(town),preferred_day=VALUES(preferred_day),categories_json=VALUES(categories_json),
      free_activities=VALUES(free_activities),term_time=VALUES(term_time),max_price=VALUES(max_price)";
    $db->prepare($sql)->execute(array_merge([$userId],$values));
    bh_preferences_json(200,['ok'=>true,'preferences'=>array_merge(bh_preferences_default(),$body),'csrf'=>$_SESSION['bh_csrf']]);
}catch(Throwable $e){
    bh_preferences_json(500,['ok'=>false,'error'=>'preferences_error','message'=>$e->getMessage()]);
}
?>