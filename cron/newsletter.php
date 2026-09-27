<?php
// Run from a server cron once per hour.
// Configure newsletter.cron_secret in api/config.php or BUBBAHUB_NEWSLETTER_SECRET.
// Example: php /path/to/cron/newsletter.php
declare(strict_types=1);

$configFile=__DIR__.'/../api/config.php';
$config=is_file($configFile)?require $configFile:[];
$base=rtrim((string)($config['app']['base_url'] ?? getenv('BUBBAHUB_BASE_URL') ?: ''),'/');
$secret=trim((string)($config['newsletter']['cron_secret'] ?? getenv('BUBBAHUB_NEWSLETTER_SECRET') ?: ''));

if($base===''||$secret===''){
    fwrite(STDERR,"Bubba Hub newsletter cron is not configured.\n");
    exit(1);
}

$url=$base.'/api/newsletter.php?action=run&token='.rawurlencode($secret);
$ch=curl_init($url);
curl_setopt_array($ch,[
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_TIMEOUT=>60,
    CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: BubbaHub-Newsletter-Cron']
]);
$response=curl_exec($ch);
$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
$error=curl_error($ch);
curl_close($ch);

if($response===false||$status<200||$status>=300){
    fwrite(STDERR,"Newsletter cron failed: ".($error!==''?$error:'HTTP '.$status)."\n");
    exit(1);
}

echo $response."\n";
