-- Group approvers by approval level (L1–L4) for overtime requests.

CREATE TABLE IF NOT EXISTS `overtime_group_approvers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `group_id` INT NOT NULL COMMENT 'kdtphdb_new.group_list.id',
  `approval_level` TINYINT NOT NULL COMMENT '1=L1, 2=L2, 3=L3, 4=L4',
  `approver_id` INT NOT NULL COMMENT 'kdtphdb_new.employee_list.id',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_group_level` (`group_id`, `approval_level`),
  KEY `idx_group` (`group_id`),
  KEY `idx_approver` (`approver_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
