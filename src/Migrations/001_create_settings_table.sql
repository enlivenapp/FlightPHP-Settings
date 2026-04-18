-- Flight Settings: Settings table

CREATE TABLE IF NOT EXISTS `settings` (
    `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `class` VARCHAR(255) NOT NULL,
    `key` VARCHAR(255) NOT NULL,
    `value` TEXT NULL DEFAULT NULL,
    `type` VARCHAR(31) NOT NULL DEFAULT 'string',
    `context` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` DATETIME NULL DEFAULT NULL,
    `updated_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `class_key_context` (`class`, `key`, `context`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
