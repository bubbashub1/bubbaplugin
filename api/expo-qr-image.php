<?php
declare(strict_types=1);
/**
 * Render a confirmed adult Expo ticket as a PNG QR image on the server.
 * Never submit QR payloads to external QR services or log the bearer token.
 * Requires composer install of endroid/qr-code and the GD PHP extension.
 */
function bh_expo_adult_ticket_qr_png(string $payload): string {
    require_once __DIR__.'/expo-ticket-payload.php';
    if(bh_expo_parse_ticket_qr_payload($payload)===null){
        throw new InvalidArgumentException('Invalid adult Expo ticket payload');
    }
    $autoload=dirname(__DIR__).'/vendor/autoload.php';
    if(!is_file($autoload)){
        throw new RuntimeException('QR library unavailable');
    }
    require_once $autoload;
    if(!extension_loaded('gd')){
        throw new RuntimeException('QR image extension unavailable');
    }
    $qr=new \Endroid\QrCode\QrCode(
        data:$payload,
        errorCorrectionLevel:\Endroid\QrCode\ErrorCorrectionLevel::High,
        size:320,
        margin:16
    );
    $writer=new \Endroid\QrCode\Writer\PngWriter();
    $result=$writer->write($qr);
    $png=$result->getString();
    if(substr($png,0,8)!=="\x89PNG\r\n\x1a\n"){
        throw new RuntimeException('QR image encoding failed');
    }
    return $png;
}
