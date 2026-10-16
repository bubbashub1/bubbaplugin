<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
ini_set('session.use_strict_mode','1');
ini_set('session.use_only_cookies','1');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
session_name('BUBBAHUB_ADMINSESSID');
session_start();
if(empty($_SESSION['bh_admin_authenticated'])){http_response_code(401);echo json_encode(['ok'=>false,'error'=>'admin_login_required']);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);echo json_encode(['ok'=>false,'error'=>'method_not_allowed']);exit;}
echo json_encode(['ok'=>true,'read_only'=>true,'queues'=>[
['label'=>'Class leader accounts','description'=>'Review registrations, verification and account status.','url'=>'/admin/admin-organisers-class-leaders.html'],
['label'=>'Activity listings','description'=>'Review listing details and publication status.','url'=>'/admin/admin-activities-listings.html'],
['label'=>'Organiser profiles','description'=>'Review organiser information and ownership.','url'=>'/admin/admin-organisers.html'],
['label'=>'Support requests','description'=>'Review messages and submissions.','url'=>'/admin/']
]],JSON_UNESCAPED_SLASHES);
