-- App administrators who can access the overtime admin panel.
-- Managers (MNG), IT (IT), and System (SYS) group members are default admins via group membership (not stored here).

CREATE TABLE IF NOT EXISTS `overtime_app_admins` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `employee_id` INT NOT NULL COMMENT 'kdtphdb_new.employee_list.id',
  `notes` VARCHAR(255) NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` INT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee` (`employee_id`),
  KEY `idx_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
