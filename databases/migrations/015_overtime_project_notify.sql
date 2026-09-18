-- Project-scoped notify-only recipients (not approvers).
-- When an OT request includes a project, these people get new_request email only.

CREATE TABLE IF NOT EXISTS `overtime_project_notify` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `project_id` INT NOT NULL COMMENT 'webjmr.projectstable.fldID',
  `employee_id` INT NOT NULL COMMENT 'kdtphdb_new.employee_list.id',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_project_employee` (`project_id`, `employee_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
