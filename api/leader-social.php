<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function bh_ls_response(int $status, array $data): never {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES);
    exit;
}
function bh_ls_wp_constant(string $name): string {
    foreach (['/public_html/wp-config.php', dirname(__DIR__).'/wp-config.php', dirname(__DIR__,2).'/wp-config.php'] as $file) {
        if (!is_file($file)) continue;
        $contents=@file_get_contents($file);
        if ($contents===false) continue;
        $pattern='/define\\s*\\(\\s*[\\\'"]'.preg_quote($name,'/').'[\\\'"]\\s*,\\s*[\\\'"](.*?)[\\\'"]\\s*\\)\\s*;/s';
        if (preg_match($pattern,$contents,$m)) return stripcslashes($m[1]);
    }
    return '';
}
try {
    require __DIR__.'/db.php';
    if (session_status()!==PHP_SESSION_ACTIVE) session_start();
    $body=json_decode((string)file_get_contents('php://input'),true);
    if (!is_array($body)) bh_ls_response(400,['ok'=>false,'message'=>'Invalid request.']);
    $provider=strtolower(trim((string)($body['provider']??'')));
    $email='';
    if ($provider==='google') {
        $credential=trim((string)($body['credential']??''));
        $clientId=bh_ls_wp_constant('BH_GOOGLE_CLIENT_ID');
        if ($clientId==='') bh_ls_response(503,['ok'=>false,'message'=>'Google sign-in is not configured yet.']);
        $ctx=stream_context_create(['http'=>['method'=>'GET','timeout'=>8,'ignore_errors'=>true,'header'=>"Accept: application/json\\r\\n"]]);
        $raw=@file_get_contents('https://oauth2.googleapis.com/tokeninfo?id_token='.rawurlencode($credential),false,$ctx);
        $g=is_string($raw)?json_decode($raw,true):null;
        if(!is_array($g)||!empty($g['error'])||(string)($g['aud']??'')!==$clientId||(string)($g['iss']??'')!=='https://accounts.google.com') bh_ls_response(401,['ok'=>false,'message'=>'Google could not verify this sign-in.']);
        $email=strtolower(trim((string)($g['email']??'')));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||!filter_var($g['email_verified']??false,FILTER_VALIDATE_BOOLEAN)) bh_ls_response(401,['ok'=>false,'message'=>'Google did not provide a verified email address.']);
    } elseif ($provider==='facebook') {
        $token=trim((string)($body['access_token']??''));
        $appId=bh_ls_wp_constant('BH_FACEBOOK_APP_ID'); $secret=bh_ls_wp_constant('BH_FACEBOOK_APP_SECRET');
        if($appId===''||$secret==='') bh_ls_response(503,['ok'=>false,'message'=>'Facebook sign-in is not configured yet.']);
        $ctx=stream_context_create(['http'=>['method'=>'GET','timeout'=>8,'ignore_errors'=>true,'header'=>"Accept: application/json\\r\\n"]]);
        $debug=@file_get_contents('https://graph.facebook.com/debug_token?input_token='.rawurlencode($token).'&access_token='.rawurlencode($appId.'|'.$secret),false,$ctx);
        $d=is_string($debug)?json_decode($debug,true):null; $data=is_array($d['data']??null)?$d['data']:[];
        if(empty($data['is_valid'])||(string)($data['app_id']??'')!==$appId) bh_ls_response(401,['ok'=>false,'message'=>'Facebook could not verify this sign-in.']);
        $me=@file_get_contents('https://graph.facebook.com/me?fields=id,name,email&access_token='.rawurlencode($token),false,$ctx);
        $m=is_string($me)?json_decode($me,true):null;
        $email=strtolower(trim((string)($m['email']??'')));
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)) bh_ls_response(422,['ok'=>false,'message'=>'Facebook did not provide an email address. Please allow email access.']);
    } else bh_ls_response(422,['ok'=>false,'message'=>'Unsupported sign-in provider.']);

    $db=bh_mysql();
    $q=$db->prepare("SELECT id,email,role,status FROM bh_users WHERE LOWER(email)=? LIMIT 1"); $q->execute([$email]); $user=$q->fetch();
    if(!$user || ($user['role']??'')!=='leader') bh_ls_response(403,['ok'=>false,'message'=>'Create your leader account first. You can then use Google or Facebook to sign in.']);
    if(($user['status']??'')!=='active') bh_ls_response(403,['ok'=>false,'message'=>'This leader account is not currently active.']);

    session_regenerate_id(true);
    $_SESSION['bh_user_id']=(int)$user['id'];
    unset($_SESSION['bh_family_authenticated']);
    $_SESSION['bh_leader_authenticated']=true;
    $_SESSION['bh_csrf']=bin2hex(random_bytes(24));
    bh_ls_response(200,['ok'=>true,'authenticated'=>true,'leader_authenticated'=>true,'user'=>['id'=>(int)$user['id'],'email'=>$user['email'],'role'=>'leader','status'=>$user['status']],'csrf'=>$_SESSION['bh_csrf']]);
} catch(Throwable $e) {
    bh_ls_response(500,['ok'=>false,'message'=>'Leader social sign-in is temporarily unavailable.']);
}