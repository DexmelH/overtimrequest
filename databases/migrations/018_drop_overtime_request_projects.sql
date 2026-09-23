-- Project lives on overtime_request.project_id only.
-- Copy any leftover allocation onto the request, then drop the child table.
-- Safe to re-run after the table is already gone.

DROP PROCEDURE IF EXISTS `ot_drop_request_projects`;

DELIMITER //
CREATE PROCEDURE `ot_drop_request_projects`()
BEGIN
  IF EXISTS (
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE()
      AND table_name = 'overtime_request_projects'
  ) THEN
    UPDATE `overtime_request` orq
    INNER JOIN (
      SELECT orp.`overtime_request_id`, orp.`project_id`
      FROM `overtime_request_projects` orp
      INNER JOIN (
        SELECT `overtime_request_id`, MIN(`id`) AS `first_id`
        FROM `overtime_request_projects`
        GROUP BY `overtime_request_id`
      ) pick ON pick.`first_id` = orp.`id`
    ) src ON src.`overtime_request_id` = orq.`id`
    SET orq.`project_id` = src.`project_id`
    WHERE orq.`project_id` IS NULL OR orq.`project_id` = 0;

    DROP TABLE `overtime_request_projects`;
  END IF;
END //
DELIMITER ;

CALL `ot_drop_request_projects`();
DROP PROCEDURE IF EXISTS `ot_drop_request_projects`;
