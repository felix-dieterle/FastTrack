-- FastTrack Time Tracking - Add service_type to time_entries
-- Run this script against your MySQL database after 001_initial_schema.sql:
--   mysql -u root -p fasttrack < database/002_add_service_type.sql

ALTER TABLE time_entries
  ADD COLUMN service_type ENUM('haushaltshilfe', 'dorfhelferin') NULL DEFAULT NULL
  AFTER note;
