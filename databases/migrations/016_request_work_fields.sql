-- Single-project work fields on overtime_request (item / job / TOW / 2D3D / revision).
-- Project / item / job / TOW live on overtime_request (see 018 for dropping overtime_request_projects).

ALTER TABLE `overtime_request`
  ADD COLUMN `project_id` INT NULL AFTER `group_id`,
  ADD COLUMN `item_id` INT NULL AFTER `project_id`,
  ADD COLUMN `job_id` INT NULL COMMENT 'drawingreference.fldID' AFTER `item_id`,
  ADD COLUMN `tow_id` INT NULL COMMENT 'typesofworktable.fldID' AFTER `job_id`,
  ADD COLUMN `work_2d3d` VARCHAR(8) NULL COMMENT '2D|3D|2D3D|3D2D' AFTER `tow_id`,
  ADD COLUMN `revision` TINYINT NOT NULL DEFAULT 0 AFTER `work_2d3d`;
