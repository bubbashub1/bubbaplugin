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
    $apiKey=trim((string)($os['rest_api_key']??$os['api_key']??''));
    if ($appId===''||$apiKey==='') return false;

    // The email address is an alias, not an email subscription token.
    // The recipient is registered with OneSignal by User.addEmail() on the
    // Notifications page, so target the email alias here.
    $payload=[
        'app_id'=>$appId,
        'target_channel'=>'email',
        'include_aliases'=>[
            'email'=>[$email],
        ],
        'email_subject'=>$subject,
        'email_body'=>$html,
    ];
    if ($plainText!=='') $payload['email_body_plain_text']=$plainText;

    $encoded=json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    if ($encoded===false) return false;

    $ch=curl_init('https://api.onesignal.com/notifications');
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_HTTPHEADER=>[
            'Content-Type: application/json; charset=utf-8',
            'Authorization'=>'Key '.$apiKey,
        ],
        CURLOPT_POSTFIELDS=>$encoded,
        CURLOPT_TIMEOUT=>15,
        CURLOPT_CONNECTTIMEOUT=>5,
    ]);
    $response=curl_exec($ch);
    $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $curlError=curl_error($ch);
    curl_close($ch);

    if ($response===false || $status<200 || $status>=300) {
        error_log('Bubba Hub OneSignal email failed: HTTP '.$status.' '.($curlError?:$response?:'unknown error'));
        return false;
    }

    return true;
}
?>