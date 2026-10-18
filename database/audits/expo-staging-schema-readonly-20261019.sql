-- Bubba & Me Expo 2027: READ-ONLY staging schema and assignment audit.
-- Run in phpMyAdmin against STAGING database only.
-- This script does not change data or schema.
SELECT DATABASE() AS connected_database;
SHOW TABLES LIKE 'bh_expo_%';
SHOW CREATE TABLE bh_expo_exhibitors;
SHOW CREATE TABLE bh_expo_tables;
SELECT event_key, application_status, COUNT(*) AS applications
FROM bh_expo_exhibitors GROUP BY event_key, application_status;
SELECT event_key, table_number, status, exhibitor_id
FROM bh_expo_tables WHERE event_key='bubba-me-2027' ORDER BY table_number;
SELECT event_key, assigned_table, COUNT(*) AS approved_exhibitors
FROM bh_expo_exhibitors
WHERE event_key='bubba-me-2027' AND application_status='approved'
AND assigned_table IS NOT NULL
GROUP BY event_key, assigned_table HAVING COUNT(*)>1;
SELECT e.id, e.business_name, e.assigned_table, t.status, t.exhibitor_id
FROM bh_expo_exhibitors e
LEFT JOIN bh_expo_tables t ON t.event_key=e.event_key AND t.table_number=e.assigned_table
WHERE e.event_key='bubba-me-2027' AND e.application_status='approved'
AND e.assigned_table IS NOT NULL
AND (t.id IS NULL OR t.status<>'assigned' OR t.exhibitor_id<>e.id);
