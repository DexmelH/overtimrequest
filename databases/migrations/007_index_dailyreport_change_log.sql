-- Speeds up duplicate checks when approved overtime is added to dailyreport.

ALTER TABLE `dailyreport`
  ADD INDEX IF NOT EXISTS `idx_dailyreport_change_log` (`fldChangeLog`);
