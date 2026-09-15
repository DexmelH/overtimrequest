# Email queue worker (batch drain)

Short-lived PHP job that drains `email_queue`. Prefer this over a long-running daemon so MySQL/PHP are not exhausted.

## How it works

1. App inserts rows with `status = pending`.
2. Task Scheduler runs `scripts/start_email_worker.bat` **every 1 minute**.
3. Worker reclaims stale `sending` rows, processes up to **10** pending emails, then **exits**.
4. File lock: `storage/email_worker.lock` (overlap skipped if a previous run is still going).

## Server setup

1. Stop/kill any old always-on `email_worker` / extra `php.exe` processes.
2. Apply indexes (once):

```bash
mysql -u root webjmrdb < databases/migrations/013_email_queue_worker_indexes.sql
```

3. Reset stuck rows if needed:

```sql
UPDATE email_queue SET status = 'pending' WHERE status = 'sending';
```

4. Task Scheduler:
   - Action: `...\overtime\scripts\start_email_worker.bat`
   - Trigger: every **1 minute**
   - Setting: **Do not start a new instance** if already running

5. Verify within 1–2 minutes: pending rows get `last_attempt_at` set; then `sent` or `failed` with `last_error`.

## Compatibility

Claim SQL uses `SELECT … FOR UPDATE` (no `SKIP LOCKED`). That works on MariaDB 10.4 / typical XAMPP. Overlap is prevented by `storage/email_worker.lock` plus Task Scheduler “Do not start a new instance”.

## Manual run

```bash
php src/usr/bin/email_worker.php
php src/usr/bin/email_worker.php --limit=20
```

## Migration

See [databases/migrations/013_email_queue_worker_indexes.sql](../databases/migrations/013_email_queue_worker_indexes.sql).
