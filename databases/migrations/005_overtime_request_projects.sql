-- Multiple project/hour allocations for one overtime request.

CREATE TABLE IF NOT EXISTS `overtime_request_projects` (
  `id` BIGINT NOT NULL AUTO_INCREMENT,
  `overtime_request_id` INT NOT NULL,
  `project_id` BIGINT NOT NULL,
  `hours` INT NOT NULL,
  `sort_order` SMALLINT NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_request_project` (`overtime_request_id`, `project_id`),
  KEY `idx_request` (`overtime_request_id`),
  KEY `idx_project` (`project_id`),
  CONSTRAINT `fk_orp_request`
    FOREIGN KEY (`overtime_request_id`) REFERENCES `overtime_request` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `overtime_request_projects`
  (`overtime_request_id`, `project_id`, `hours`, `sort_order`)
SELECT `id`, `project_id`, `duration`, 0
FROM `overtime_request`
WHERE `project_id` > 0 AND `duration` > 0
ON DUPLICATE KEY UPDATE
  `overtime_request_id` = VALUES(`overtime_request_id`);
