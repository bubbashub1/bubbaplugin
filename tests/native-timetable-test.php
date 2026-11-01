<?php
declare(strict_types=1);
require_once __DIR__.'/../api/lib/native-timetable.php';
function check(bool $ok, string $label): void {if (!$ok) {fwrite(STDERR,"FAIL: $label\n");exit(1);}echo "PASS: $label\n";}
$s=['id'=>12,'day_of_week'=>1,'start_time'=>'10:00:00','end_time'=>'11:00:00','frequency'=>'weekly','term_time_only'=>0];
$r=bhExpandWeeklyOccurrences($s,'2026-11-01','2026-11-16');
check(count($r)===3,'three Mondays in bounded window');
check($r[0]['date']==='2026-11-02','ISO Monday convention');
check($r[0]['starts_at']==='2026-11-02T10:00:00+00:00','London winter offset');
$term=$s;$term['term_time_only']=1;
check(count(bhExpandWeeklyOccurrences($term,'2026-11-01','2026-11-16'))===0,'term-only fails closed with no term data');
check(count(bhExpandWeeklyOccurrences($term,'2026-11-01','2026-11-16',[['starts_on'=>'2026-11-02','ends_on'=>'2026-11-09']]))===2,'term boundary inclusive');
$cancel=bhExpandWeeklyOccurrences($s,'2026-11-01','2026-11-16',[],['2026-11-09'=>['exception_type'=>'cancelled']]);
check(count($cancel)===2,'one-off cancellation');
$move=bhExpandWeeklyOccurrences($s,'2026-11-01','2026-11-16',[],['2026-11-09'=>['exception_type'=>'rescheduled','replacement_date'=>'2026-11-10','replacement_start_time'=>'12:00:00','replacement_end_time'=>'13:00:00']]);
check($move[1]['date']==='2026-11-10' && $move[1]['rescheduled'],'one-off reschedule');
check(bhOccurrencesClash($r[0],['starts_at'=>'2026-11-02T10:30:00+00:00','ends_at'=>'2026-11-02T11:30:00+00:00']),'overlap flagged');
check(!bhOccurrencesClash($r[0],['starts_at'=>'2026-11-02T11:00:00+00:00','ends_at'=>'2026-11-02T12:00:00+00:00']),'back-to-back is not a clash');
$sunday=$s;$sunday['day_of_week']=7;$sunday['start_time']='10:00:00';$sunday['end_time']='11:00:00';
$dst=bhExpandWeeklyOccurrences($sunday,'2027-03-21','2027-04-04');
check($dst[0]['starts_at']==='2027-03-21T10:00:00+00:00' && $dst[1]['starts_at']==='2027-03-28T10:00:00+01:00','spring DST changes offset');
try {bhExpandWeeklyOccurrences($s,'2026-01-01','2027-01-01');check(false,'long range rejected');}catch(InvalidArgumentException $e){check(true,'long range rejected');}
echo "Native timetable tests completed.\n";
