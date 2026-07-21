-- Speed up approval-chain lookups by overtime request and approver.

ALTER TABLE `overtime_accept`
  ADD INDEX IF NOT EXISTS `idx_oa_overtime` (`overtime_id`),
  ADD INDEX IF NOT EXISTS `idx_oa_approver` (`approver_id`),
  ADD INDEX IF NOT EXISTS `idx_oa_overtime_approver` (`overtime_id`, `approver_id`);
