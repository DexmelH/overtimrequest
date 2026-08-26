-- Provenance for on-behalf submissions and follow-up copies of auto-rejected requests.
-- Run against webjmrdb.

ALTER TABLE `overtime_request`
  ADD COLUMN IF NOT EXISTS `submitted_by` INT NULL
    COMMENT 'Approver who filed on behalf; NULL = self-filed'
    AFTER `user_id`,
  ADD COLUMN IF NOT EXISTS `origin_request_id` INT NULL
    COMMENT 'Auto-rejected request this one was re-submitted from'
    AFTER `submitted_by`,
  ADD INDEX IF NOT EXISTS `idx_or_origin` (`origin_request_id`);

-- Backfill historical on-behalf submissions from the audit log.
UPDATE `overtime_request` orq
INNER JOIN `activity_logs` al
  ON al.`entity_type` = 'overtime_request'
 AND al.`entity_id` = orq.`id`
 AND al.`action` = 'request.submit.on_behalf'
SET orq.`submitted_by` = al.`user_id`
WHERE orq.`submitted_by` IS NULL
  AND al.`user_id` IS NOT NULL;
