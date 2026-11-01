<?php
declare(strict_types=1);
/**
 * Pure, side-effect-free timetable occurrence expansion for Bubba Hub 2.0.
 * Not wired to production routes. Caller must resolve weekday convention,
 * permissions, and DB rows before calling.
 */
function bhExpandWeeklyOccurrences(array $session, string $from, string $to, array $termWindows = [], array $exceptions = []): array
{
    $tz = new DateTimeZone('Europe/London');
    $first = DateTimeImmutable::createFromFormat('!Y-m-d', $from, $tz);
    $last = DateTimeImmutable::createFromFormat('!Y-m-d', $to, $tz);
    if (!$first || !$last || $first->format('Y-m-d') !== $from || $last->format('Y-m-d') !== $to || $last < $first || $first->diff($last)->days > 92) {
        throw new InvalidArgumentException('Date range must be valid and no longer than 92 days');
    }
    // ISO weekday: Monday=1 ... Sunday=7. Do not call until DB convention is verified.
    $weekday = (int)($session['day_of_week'] ?? 0);
    if ($weekday < 1 || $weekday > 7) throw new InvalidArgumentException('Expected ISO weekday 1-7');
    if (strtolower(trim((string)($session['frequency'] ?? 'weekly'))) !== 'weekly') {
        throw new InvalidArgumentException('Unsupported recurrence frequency');
    }
    $startTime = (string)($session['start_time'] ?? '');
    $endTime = (string)($session['end_time'] ?? '');
    foreach ([$startTime, $endTime] as $time) {
        if (!preg_match('/^([01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/', $time)) {
            throw new InvalidArgumentException('Invalid session time');
        }
    }
    if ($endTime <= $startTime) throw new InvalidArgumentException('End time must be later on the same day');
    $boundsStart = (string)($session['start_date'] ?? '');
    $boundsEnd = (string)($session['end_date'] ?? '');
    $termOnly = (bool)($session['term_time_only'] ?? false);
    $items = [];
    for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
        if ((int)$day->format('N') !== $weekday) continue;
        $date = $day->format('Y-m-d');
        if ($boundsStart !== '' && $date < $boundsStart || $boundsEnd !== '' && $date > $boundsEnd) continue;
        if ($termOnly) {
            $inTerm = false;
            foreach ($termWindows as $term) {
                if (isset($term['starts_on'], $term['ends_on']) && $date >= $term['starts_on'] && $date <= $term['ends_on']) {$inTerm = true;break;}
            }
            if (!$inTerm) continue; // fail closed: missing terms do not imply school is open
        }
        $exception = $exceptions[$date] ?? null;
        if (is_array($exception) && ($exception['exception_type'] ?? '') === 'cancelled') continue;
        $occDate = $date;
        $occStart = $startTime;
        $occEnd = $endTime;
        if (is_array($exception) && ($exception['exception_type'] ?? '') === 'rescheduled') {
            $occDate = (string)($exception['replacement_date'] ?? '');
            $occStart = (string)($exception['replacement_start_time'] ?? '');
            $occEnd = (string)($exception['replacement_end_time'] ?? '');
            if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $occDate) || !preg_match('/^([01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/', $occStart) || !preg_match('/^([01]\\d|2[0-3]):[0-5]\\d(?::[0-5]\\d)?$/', $occEnd) || $occEnd <= $occStart) {
                throw new InvalidArgumentException('Invalid rescheduled occurrence');
            }
        }
        $starts = new DateTimeImmutable($occDate.' '.$occStart, $tz);
        $ends = new DateTimeImmutable($occDate.' '.$occEnd, $tz);
        // Reject nonexistent local wall times during the spring DST jump.
        if ($starts->format('Y-m-d H:i:s') !== $occDate.' '.(strlen($occStart)===5?$occStart.':00':$occStart)
            || $ends->format('Y-m-d H:i:s') !== $occDate.' '.(strlen($occEnd)===5?$occEnd.':00':$occEnd)) {
            throw new InvalidArgumentException('Nonexistent local time on DST transition');
        }
        $items[] = ['session_id'=>(int)($session['id'] ?? 0),'original_date'=>$date,'date'=>$occDate,'starts_at'=>$starts->format(DateTimeInterface::ATOM),'ends_at'=>$ends->format(DateTimeInterface::ATOM),'rescheduled'=>$occDate!==$date || $occStart!==$startTime];
    }
    usort($items,static fn(array $a,array $b):int=>strcmp($a['starts_at'],$b['starts_at']));
    return $items;
}

/** Return true only for overlapping actual time intervals. */
function bhOccurrencesClash(array $a, array $b): bool
{
    $aStart = new DateTimeImmutable((string)$a['starts_at']);
    $aEnd = new DateTimeImmutable((string)$a['ends_at']);
    $bStart = new DateTimeImmutable((string)$b['starts_at']);
    $bEnd = new DateTimeImmutable((string)$b['ends_at']);
    return $aStart < $bEnd && $bStart < $aEnd;
}
