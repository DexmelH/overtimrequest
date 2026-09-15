-- Speeds up email batch worker: WHERE status=... ORDER BY created_at
-- Safe to re-run.
-- Apply once on the server DB used by overtime (e.g. webjmrdb):
--   mysql -u root webjmrdb < databases/migrations/013_email_queue_worker_indexes.sql
-- See docs/EMAIL_QUEUE_WORKER.md

ALTER TABLE `email_queue`
  ADD INDEX IF NOT EXISTS `idx_status_created` (`status`, `created_at`),
  ADD INDEX IF NOT EXISTS `idx_status_attempt` (`status`, `last_attempt_at`);
