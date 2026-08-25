-- Optional manual reset for webjmrdb (phpMyAdmin / MySQL client).
-- Prefer: php scripts/reset_overtime_data.php --confirm
--
-- Default transactional wipe (keeps OGA + app admins + activity_logs):

SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `overtime_request_projects`;
DELETE FROM `overtime_accept`;
DELETE FROM `overtime_request`;
DELETE FROM `email_queue` WHERE `overtime_id` IS NOT NULL AND `overtime_id` > 0;

-- Optional: OT-like daily report rows written by this app
-- DELETE FROM `dailyreport` WHERE `fldMHType` = 1 AND `fldItem` = 0 AND `fldRevision` = 0;

-- Optional:
-- TRUNCATE TABLE `activity_logs`;
-- DELETE FROM `overtime_group_approvers`;
-- DELETE FROM `overtime_app_admins`;

SET FOREIGN_KEY_CHECKS = 1;
