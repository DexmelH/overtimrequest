-- Speeds up email worker polls: WHERE status=... ORDER BY created_at
-- Safe to re-run.

ALTER TABLE `email_queue`
  ADD INDEX IF NOT EXISTS `idx_status_created` (`status`, `created_at`),
  ADD INDEX IF NOT EXISTS `idx_status_attempt` (`status`, `last_attempt_at`);
