<?php
declare(strict_types=1);
/**
 * Expo-only SMTP mailer with inline QR PNGs.
 * Secrets stay server-side; no public endpoint calls this function.
 */
function bh_expo_send_qr_mail(string $to,string $subject,string $html,string $plain,array $images): bool {
    if(!filter_var($to,FILTER_VALIDATE_EMAIL) || !$images || count($images)>12)return false;
    $config=dirname(__DIR__).'/wp-config.php';
    if(!is_file($config))return false;
    require_once $config;
    $password=defined('BH_SMTP_PASSWORD')?(string)BH_SMTP_PASSWORD:'';
    if($password==='' || $password==='YOUR-NOREPLY-MAILBOX-PASSWORD')return false;
    foreach(['Exception.php','PHPMailer.php','SMTP.php'] as $file){
        $path=dirname(__DIR__).'/wp-includes/PHPMailer/'.$file;
        if(!is_file($path))return false;
        require_once $path;
    }
    try{
        $mail=new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host=defined('BH_SMTP_HOST')?(string)BH_SMTP_HOST:'smtp.bubbahub.co.uk';
        $mail->Port=defined('BH_SMTP_PORT')?(int)BH_SMTP_PORT:465;
        $secure=defined('BH_SMTP_SECURE')?strtolower((string)BH_SMTP_SECURE):'ssl';
        $mail->SMTPSecure=($secure==='tls'||$secure==='starttls')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->SMTPAuth=true;
        $mail->Username=defined('BH_SMTP_USERNAME')?(string)BH_SMTP_USERNAME:'noreply@bubbahub.co.uk';
        $mail->Password=$password;
        $mail->CharSet='UTF-8';
        $mail->setFrom(defined('BH_SMTP_FROM')?(string)BH_SMTP_FROM:'noreply@bubbahub.co.uk',defined('BH_SMTP_FROM_NAME')?(string)BH_SMTP_FROM_NAME:'Bubba Hub');
        $mail->addAddress($to);
        foreach($images as $image){
            if(!is_array($image) || !preg_match('/^expo-adult-(?:[1-9]|1[0-2])$/D',(string)($image['cid']??'')))return false;
            $bytes=$image['bytes']??'';
            if(!is_string($bytes) || strlen($bytes)>250000 || substr($bytes,0,8)!=="\x89PNG\r\n\x1a\n")return false;
            $mail->addStringEmbeddedImage($bytes,$image['cid'],$image['name']??'ticket.png','base64','image/png');
        }
        $mail->isHTML(true);
        $mail->Subject=$subject;
        $mail->Body=$html;
        $mail->AltBody=$plain;
        return $mail->send();
    }catch(Throwable $error){
        error_log('Expo QR email send failed: '.get_class($error));
        return false;
    }
}
