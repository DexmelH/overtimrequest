-- Fractional overtime: keep duration as whole hours; minutes in a separate column.
-- Daily report manhours = duration * 60 + duration_minutes.

ALTER TABLE `overtime_request`
  ADD COLUMN `duration_minutes` TINYINT NOT NULL DEFAULT 0
    COMMENT '0-59; total man-minutes = duration*60 + duration_minutes'
    AFTER `duration`;
