# Expo hourly entry: safe rollout checklist

Event: Saturday 9 January 2027, Acorn Centre, Torquay.
Slots: 10:30, 11:30, 12:30, 13:30; 75 people each (adults + children).
Existing QR tokens and check-in data must not be reset.

## Stage first
1. Back up staging database.
2. Apply migrations/expo-hourly-arrival-slots.sql in staging phpMyAdmin.
3. Run these read-only checks:

```sql
SELECT arrival_slot, booking_status, COUNT(*) AS bookings,
       SUM(adults + children) AS people
FROM bh_expo_reservations
WHERE event_key='bubba-me-2027'
  AND booking_status IN ('pending','confirmed')
GROUP BY arrival_slot, booking_status
ORDER BY arrival_slot, booking_status;

SELECT COALESCE(SUM(adults+children),0) AS total_people
FROM bh_expo_reservations
WHERE event_key='bubba-me-2027'
  AND booking_status IN ('pending','confirmed');
```

4. Assign each existing NULL arrival_slot reservation an agreed arrival window.
   Contact guests first if they have already received unrestricted entry tickets.
   Do not reissue or invalidate existing QR codes.
5. Check every slot has <=75 people and total <=300 before opening bookings.
6. Test a family booking in each slot, confirmation email wording and QR check-in.
7. Test 76th visitor rejected in a slot, 301st visitor rejected overall, and 12-person booking validation.
8. Confirm the 4 arrival windows display correctly on mobile and desktop.

## Live rollout
- Back up live database.
- Apply migration before deploying booking code. Deployment without the column breaks reservations.
- Audit and allocate existing live reservations first; the API rejects new bookings with arrival_slots_not_ready while any active booking has a NULL slot.
- Verify the server config BUBBAHUB_EXPO_VISITOR_CAPACITY is 300.
- Verify QR emails and scanners continue to work.
- No raffle, Stripe or exhibitor changes are included.

## Rollback
If the feature must be rolled back, revert code first; retain the new nullable database column and data. Do not drop or rewrite existing bookings or QR data.
