-- Execute após o schema principal e sql/asaas-finance.sql, com o banco `maps`
-- selecionado. Completa os campos de catálogo usados pelo fluxo de planos e
-- habilita vencimentos de acesso sem alterar datas de cobrança no Asaas.
-- Os vencimentos controlam o acesso no MapsProspector; não alteram a data
-- programada para cobrança recorrente diretamente no Asaas.

USE `maps`;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tenants' AND COLUMN_NAME = 'plan_access_until') = 0,
  'ALTER TABLE `tenants` ADD COLUMN `plan_access_until` DATE NULL AFTER `plan_id`',
  'SELECT 1'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'asaas_subscriptions' AND COLUMN_NAME = 'product_kind') = 0,
  'ALTER TABLE `asaas_subscriptions` ADD COLUMN `product_kind` VARCHAR(32) NOT NULL DEFAULT ''prospecting'' AFTER `plan_slug`',
  'SELECT 1'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'catalog_group') = 0,
  'ALTER TABLE `plans` ADD COLUMN `catalog_group` VARCHAR(32) NOT NULL DEFAULT ''current''',
  'SELECT 1'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'included_users') = 0,
  'ALTER TABLE `plans` ADD COLUMN `included_users` INT NOT NULL DEFAULT 1',
  'SELECT 1'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

SET @ddl := IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'plans' AND COLUMN_NAME = 'addon_user_price_cents') = 0,
  'ALTER TABLE `plans` ADD COLUMN `addon_user_price_cents` INT UNSIGNED NOT NULL DEFAULT 0',
  'SELECT 1'
);
PREPARE migration_stmt FROM @ddl;
EXECUTE migration_stmt;
DEALLOCATE PREPARE migration_stmt;

CREATE TABLE IF NOT EXISTS `tenant_plan_due_date_audit` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` INT UNSIGNED NOT NULL,
  `old_due_date` DATE NULL,
  `new_due_date` DATE NULL,
  `changed_by_user_id` INT UNSIGNED NOT NULL,
  `changed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_due_date_audit` (`tenant_id`, `changed_at`),
  KEY `idx_due_date_audit_actor` (`changed_by_user_id`, `changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
