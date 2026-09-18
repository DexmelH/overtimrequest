-- Allow multiple approvers per approval level (L1–L4) for a group.
-- One person may only appear once per group (any level).
-- Safe to re-run after first apply only if indexes already match.

-- Keep newest row when the same person appears twice in a group (shouldn't happen under old unique key).
DELETE oga1 FROM `overtime_group_approvers` oga1
INNER JOIN `overtime_group_approvers` oga2
  ON oga1.`group_id` = oga2.`group_id`
 AND oga1.`approver_id` = oga2.`approver_id`
 AND oga1.`id` < oga2.`id`;

ALTER TABLE `overtime_group_approvers`
  DROP INDEX `uk_group_level`,
  ADD UNIQUE KEY `uk_group_approver` (`group_id`, `approver_id`),
  ADD KEY `idx_group_level` (`group_id`, `approval_level`);
