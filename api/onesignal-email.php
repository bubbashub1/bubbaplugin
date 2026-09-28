<?php
declare(strict_types=1);

/**
 * Send a transactional email through OneSignal.
 *
 * The REST API key stays in the server-only api/config.php:
 * 'onesignal' => [
 *   'app_id' => '...',
 *   'rest_api_key' => '...'
 * ]
 *
 * Email failures are deliberately non-fatal to the originating action.
 */
function bh_send_onesignal_email(string $email, string $subject, string $html, string $plainText=''): bool {
    $email=strtolower(trim($email));
    if (!filter_var($email,FILTER_VALIDATE_EMAIL)) return false;

    $configFile=__DIR__.'/config.php';
    if (!is_file($configFile)) return false;
    $config=require $configFile;
    $os=is_array($config['onesignal']??null)?$config['onesignal']:[];
    $appId=trim((string)($os['app_id']??'0c4e3bc3-2049-413a-b19c-cb7823a1c861'));
    $apiKey=trim((string)($os['rest_api_key']??$os['api_key']??$os['rest_api_key']??''));
    if ($appId===''||$apiKey==='') return false;

    $payload=[
        'app_id'=>$appId,
        'target_channel'=>'email',
        'include_email_tokens'=>[$email],
        'email_subject'=>$subject,
        'email_body'=>$html,
    ];
    if ($plainText!=='') $payload['email_body_plain_text']=$plainText;

    $ch=curl_init('https://api.onesignal.com/notifications');
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>[
            'Content-Type: application/json; charset=utf-8',
            'Authorization'=>'Key '.$apiKey,
        ],
        CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        CURLOPT_TIMEOUT=>15,
        CURLOPT_CONNECTTIMEOUT=>5,
    ]);
    $response=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $response!==false && $status>=200 && $status<300;
}
?>