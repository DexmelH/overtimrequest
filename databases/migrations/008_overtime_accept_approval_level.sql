-- Snapshot approval level on each accept row so cutoff / L4 logic
-- does not depend on live overtime_group_approvers config.

ALTER TABLE `overtime_accept`
  ADD COLUMN IF NOT EXISTS `approval_level` TINYINT NULL
    COMMENT '1=L1 .. 4=L4 snapshot at submit'
    AFTER `approver_id`;

UPDATE `overtime_accept` oa
INNER JOIN `overtime_request` orq ON orq.`id` = oa.`overtime_id`
INNER JOIN `overtime_group_approvers` oga
  ON oga.`approver_id` = oa.`approver_id` AND oga.`group_id` = orq.`group_id`
SET oa.`approval_level` = oga.`approval_level`
WHERE oa.`approval_level` IS NULL;

UPDATE `overtime_accept`
SET `approval_level` = 1
WHERE `approval_level` IS NULL;
