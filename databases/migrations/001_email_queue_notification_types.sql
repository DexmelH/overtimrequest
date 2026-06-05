-- Run once on webjmrdb to support requestor status notification emails.

ALTER TABLE `email_queue`
  ADD COLUMN `email_type` VARCHAR(32) NOT NULL DEFAULT 'new_request' AFTER `overtime_id`,
  ADD COLUMN `decision` TINYINT NULL DEFAULT NULL COMMENT '1=approved, 0=rejected' AFTER `email_type`,
  ADD COLUMN `actor_name` VARCHAR(255) NULL DEFAULT NULL AFTER `decision`;
