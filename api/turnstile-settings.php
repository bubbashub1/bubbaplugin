<?php
declare(strict_types=1);
/** Read Turnstile settings without bootstrapping WordPress. */
function bh_turnstile_setting(string $name): string {
    $value=getenv($name);
    if(is_string($value)&&$value!=='')return $value;
    if(!in_array($name,['BUBBAHUB_TURNSTILE_SITE_KEY','BUBBAHUB_TURNSTILE_SECRET_KEY'],true))return '';
    $file=dirname(__DIR__).'/wp-config.php';
    if(!is_readable($file))return '';
    $contents=file_get_contents($file);
    if($contents===false)return '';
    $quoted=preg_quote($name,'/');
    if(preg_match('/^[ \t]*putenv\(\s*([\x27\x22])'.$quoted.'=([^\x27\x22\r\n]*)\1\s*\)\s*;/m',$contents,$m))return trim($m[2]);
    return '';
}
