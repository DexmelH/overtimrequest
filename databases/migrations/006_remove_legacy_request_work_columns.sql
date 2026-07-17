-- Project/hour allocations now live in overtime_request_projects.
-- Item, job, and type-of-work are no longer part of overtime requests.

ALTER TABLE `overtime_request`
  DROP COLUMN IF EXISTS `project_id`,
  DROP COLUMN IF EXISTS `item_id`,
  DROP COLUMN IF EXISTS `job_id`,
  DROP COLUMN IF EXISTS `tow_id`;
