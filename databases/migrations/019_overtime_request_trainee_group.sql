-- Group of trainees (item of work 14) stored until the request is posted to Daily Report.

ALTER TABLE `overtime_request`
  ADD COLUMN `trainee_group` VARCHAR(5) NULL COMMENT 'group_list.abbreviation; copied to dailyreport.fldTrGroup on accept' AFTER `revision`;
