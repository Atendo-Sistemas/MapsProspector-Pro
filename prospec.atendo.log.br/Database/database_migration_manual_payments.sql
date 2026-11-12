-- Execute em bancos existentes para habilitar solicitações e aprovações manuais.
-- O schema completo já inclui estas tabelas.
USE `maps`;

CREATE TABLE IF NOT EXISTS `payments` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `user_id` INT DEFAULT NULL,
  `type` VARCHAR(32) NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `amount_cents` INT UNSIGNED NOT NULL DEFAULT 0,
  `credits` INT UNSIGNED NOT NULL DEFAULT 0,
  `plan_id` INT DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `external_id` VARCHAR(100) DEFAULT NULL,
  `external_ref` VARCHAR(100) DEFAULT NULL,
  `receipt_url` TEXT DEFAULT NULL,
  `capture_method` VARCHAR(40) DEFAULT NULL,
  `paid_amount` DECIMAL(12,2) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payments_tenant_status` (`tenant_id`, `status`),
  KEY `idx_payments_external_id` (`external_id`),
  KEY `idx_payments_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payment_bonus_grants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT NOT NULL,
  `bonus_code` VARCHAR(64) NOT NULL,
  `source_type` VARCHAR(32) NOT NULL,
  `source_id` VARCHAR(100) NOT NULL,
  `credits` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_payment_bonus_source` (`tenant_id`, `bonus_code`, `source_type`, `source_id`),
  KEY `idx_payment_bonus_tenant` (`tenant_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
