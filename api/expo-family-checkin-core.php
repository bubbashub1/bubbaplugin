<?php
declare(strict_types=1);
/** Admin-authenticated caller only. Counts are transactionally bounded by reservation headcount. */
function bh_expo_family_checkin(PDO $db,string $qrPayload,int $addAdults,int $addChildren): array {
 if($addAdults<0||$addChildren<0||($addAdults+$addChildren)<1||($addAdults+$addChildren)>12)
   return ['ok'=>false,'error'=>'invalid_counts'];
 require_once __DIR__.'/expo-booking-qr-core.php';
 $db->beginTransaction();
 try {
   $booking=bh_expo_resolve_booking_qr($db,$qrPayload);
   if(!$booking){$db->rollBack();return ['ok'=>false,'error'=>'booking_not_confirmed'];}
   $id=(int)$booking['id'];
   $lock=$db->prepare('SELECT id FROM bh_expo_reservations WHERE id=? FOR UPDATE');
   $lock->execute([$id]);
   if(!$lock->fetchColumn()){$db->rollBack();return ['ok'=>false,'error'=>'booking_not_found'];}
   $db->prepare('INSERT IGNORE INTO bh_expo_family_checkins (reservation_id) VALUES (?)')->execute([$id]);
   $q=$db->prepare('SELECT adults_checked_in,children_checked_in FROM bh_expo_family_checkins WHERE reservation_id=? FOR UPDATE');
   $q->execute([$id]);$count=$q->fetch(PDO::FETCH_ASSOC);
   $a=(int)$count['adults_checked_in'];$c=(int)$count['children_checked_in'];
   if($a+$addAdults>(int)$booking['adults']||$c+$addChildren>(int)$booking['children']){
      $db->rollBack();return ['ok'=>false,'error'=>'exceeds_booking','adults_remaining'=>(int)$booking['adults']-$a,'children_remaining'=>(int)$booking['children']-$c];
   }
   $q=$db->prepare('UPDATE bh_expo_family_checkins SET adults_checked_in=?,children_checked_in=? WHERE reservation_id=?');
   $q->execute([$a+$addAdults,$c+$addChildren,$id]);
   $db->commit();
   return ['ok'=>true,'reference'=>$booking['public_reference'],'adults_checked_in'=>$a+$addAdults,'children_checked_in'=>$c+$addChildren,'adults_total'=>(int)$booking['adults'],'children_total'=>(int)$booking['children']];
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
