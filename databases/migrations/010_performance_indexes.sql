-- Performance indexes for overtime lookups.
-- Run the first ALTER against webjmrdb.
-- Run the second ALTER against kdtphdb_new.

-- overtime_accept: already added in 009_index_overtime_accept.sql
-- overtime_group_approvers.approver_id: already in 003_group_approvers.sql
-- overtime_request_projects.overtime_request_id: already in 005_overtime_request_projects.sql
-- activity_logs.created_at: already in 002_activity_logs.sql

ALTER TABLE `overtime_request`
  ADD INDEX IF NOT EXISTS `idx_or_user_id` (`user_id`),
  ADD INDEX IF NOT EXISTS `idx_or_date_status` (`request_date`, `status`);

-- kdtphdb_new
ALTER TABLE `employee_group`
  ADD INDEX IF NOT EXISTS `idx_eg_employee` (`employee_number`),
  ADD INDEX IF NOT EXISTS `idx_eg_group` (`group_id`);
