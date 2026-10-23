-- Apply to staging after backup, before enabling timed booking.
ALTER TABLE bh_expo_reservations ADD COLUMN arrival_slot VARCHAR(5) NULL DEFAULT NULL;
CREATE INDEX idx_expo_arrival_slot ON bh_expo_reservations (event_key, arrival_slot, booking_status);
-- Existing bookings remain unassigned and need manual allocation.
