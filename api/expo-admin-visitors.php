<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function send(int $code,array $data): never {http_response_code($code);echo json_encode($data);exit;}
if(($_SERVER['REQUEST_METHOD']??'')!=='GET')send(405,['ok'=>false]);
ini_set('session.use_strict_mode','1');
session_name('BUBBAHUB_ADMINSESSID');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>true,'httponly'=>true,'samesite'=>'Lax']);
session_start();
if(empty($_SESSION['bh_admin_authenticated'])||time()-(int)($_SESSION['bh_admin_last_activity']??0)>1800)send(401,['ok'=>false,'error'=>'admin_login_required']);
$_SESSION['bh_admin_last_activity']=time();
try {
 require_once __DIR__.'/db.php';
 $db=bh_mysql();
 $capacity=defined('BUBBAHUB_EXPO_VISITOR_CAPACITY')?(int)BUBBAHUB_EXPO_VISITOR_CAPACITY:300;
 $summary=$db->query("SELECT COUNT(*) bookings,COALESCE(SUM(adults),0) adults,COALESCE(SUM(children),0) children,COALESCE(SUM(DATE(consent_at)=UTC_DATE()),0) today FROM bh_expo_reservations WHERE event_key='bubba-me-2027' AND booking_status IN ('pending','confirmed')")->fetch(PDO::FETCH_ASSOC);
 $attendance=$db->query("SELECT COALESCE(SUM(c.adults_checked_in),0) adults_arrived,COALESCE(SUM(c.children_checked_in),0) children_arrived FROM bh_expo_family_checkins c INNER JOIN bh_expo_reservations r ON r.id=c.reservation_id WHERE r.event_key='bubba-me-2027' AND r.booking_status='confirmed'")->fetch(PDO::FETCH_ASSOC);
 $rows=$db->query("SELECT r.public_reference,r.contact_name,r.contact_email,r.adults,r.children,r.booking_status,COALESCE(d.status,'not_recorded') email_status,r.consent_at,COALESCE(c.adults_checked_in,0) adults_checked_in,COALESCE(c.children_checked_in,0) children_checked_in FROM bh_expo_reservations r LEFT JOIN bh_expo_family_checkins c ON c.reservation_id=r.id LEFT JOIN bh_expo_family_email_delivery d ON d.reservation_id=r.id WHERE r.event_key='bubba-me-2027' AND r.booking_status IN ('pending','confirmed') ORDER BY r.id DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
 $visitors=(int)$summary['adults']+(int)$summary['children'];
 send(200,['ok'=>true,'summary'=>['bookings'=>(int)$summary['bookings'],'adults'=>(int)$summary['adults'],'children'=>(int)$summary['children'],'visitors'=>$visitors,'remaining'=>max(0,$capacity-$visitors),'capacity'=>$capacity,'today'=>(int)$summary['today'],'adults_arrived'=>(int)$attendance['adults_arrived'],'children_arrived'=>(int)$attendance['children_arrived'],'visitors_arrived'=>(int)$attendance['adults_arrived']+(int)$attendance['children_arrived']],'rows'=>$rows,'limit'=>500]);
} catch(Throwable $e){error_log('Expo dashboard error: '.get_class($e));send(503,['ok'=>false,'error'=>'dashboard_unavailable']);}
