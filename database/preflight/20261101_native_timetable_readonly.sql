-- READ ONLY. Run against staging after taking a backup.
-- This does not create, update or delete anything.
SELECT DATABASE() AS connected_database;
SELECT TABLE_NAME, TABLE_ROWS FROM information_schema.TABLES
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN
 ('bh_sessions','bh_venues','bh_activities','bh_session_verifications','bh_session_exceptions','bh_term_calendars','bh_session_term_links');
SELECT COLUMN_NAME,DATA_TYPE,IS_NULLABLE,COLUMN_DEFAULT FROM information_schema.COLUMNS
 WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_sessions' ORDER BY ORDINAL_POSITION;
SELECT day_of_week,COALESCE(frequency,'(null)') AS frequency,COUNT(*) AS sessions
 FROM bh_sessions GROUP BY day_of_week,COALESCE(frequency,'(null)') ORDER BY day_of_week,frequency;
SELECT term_time_only,COUNT(*) AS sessions FROM bh_sessions GROUP BY term_time_only;
SELECT COUNT(*) AS orphaned_sessions FROM bh_sessions s
 LEFT JOIN bh_venues v ON v.id=s.venue_id WHERE v.id IS NULL;
SELECT COUNT(*) AS missing_end_times FROM bh_sessions WHERE end_time IS NULL;
SELECT COUNT(*) AS invalid_time_order FROM bh_sessions WHERE end_time IS NOT NULL AND end_time<=start_time;
SELECT COUNT(*) AS invalid_date_order FROM bh_sessions
 WHERE start_date IS NOT NULL AND end_date IS NOT NULL AND end_date<start_date;
