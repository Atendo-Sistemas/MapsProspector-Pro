-- Migração: Configurações globais da plataforma (chave API Thordata)
-- Apenas super_admin pode alterar; todas as empresas utilizam a mesma chave.

USE `maps`;

CREATE TABLE IF NOT EXISTS `platform_settings` (
  `setting_key` VARCHAR(64) NOT NULL PRIMARY KEY,
  `setting_value` TEXT DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Chave inicial (opcional): scraper_api_key será gerenciada pelo super_admin na interface
INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`) VALUES ('scraper_api_key', NULL);
