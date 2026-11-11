-- Bubba Hub migration 008: add the activity featured flag.
-- Apply once to each environment before deploying code that reads/writes featured.
ALTER TABLE bh_activities
  ADD COLUMN IF NOT EXISTS featured TINYINT(1) NOT NULL DEFAULT 0 AFTER status;
