-- Bubba Hub migration 012: family profile fields on bh_users.
-- Inspect each column first and run only ALTER statements for columns that are absent.
-- Do not rerun a statement for a column that already exists.
ALTER TABLE bh_users
  ADD COLUMN first_name VARCHAR(80) NULL;

ALTER TABLE bh_users
  ADD COLUMN last_name VARCHAR(80) NULL;

ALTER TABLE bh_users
  ADD COLUMN phone VARCHAR(40) NULL;

ALTER TABLE bh_users
  ADD COLUMN date_of_birth DATE NULL;
