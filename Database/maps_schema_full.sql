-- =============================================================================
-- MapsProspector Pro - Schema completo para nova instalação limpa
-- Banco: maps | Charset: utf8mb4_unicode_ci
-- Execute este arquivo em um banco vazio para criar todas as tabelas e dados iniciais.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Criar banco
-- -----------------------------------------------------------------------------
CREATE DATABASE IF NOT EXISTS `maps` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `maps`;

-- -----------------------------------------------------------------------------
-- Tabela: plans (planos e limites de tokens) — deve existir antes de tenants
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL,
  `token_limit` int(11) NOT NULL DEFAULT 100 COMMENT 'Limite de tokens por período',
  `price_monthly` decimal(10,2) DEFAULT 0.00 COMMENT 'Valor mensal (R$)',
  `period` varchar(20) NOT NULL DEFAULT 'monthly' COMMENT 'monthly | yearly',
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active | inactive',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: tenants (empresas / multi-tenant)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `plan_id` int(11) DEFAULT 1,
  `plan` varchar(50) DEFAULT 'basic',
  `status` varchar(20) DEFAULT 'active' COMMENT 'active | suspended',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`),
  KEY `status` (`status`),
  KEY `plan_id` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: users (usuários)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) DEFAULT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `profile` varchar(50) DEFAULT 'user' COMMENT 'super_admin | admin | user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: settings (configurações por usuário — webhook, CRM, etc.)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `base_url` text DEFAULT NULL,
  `token` text DEFAULT NULL,
  `tenant_name` varchar(255) DEFAULT 'Atendo CRM',
  `use_proxy` tinyint(1) DEFAULT 0,
  `wrap_in_body` tinyint(1) DEFAULT 0,
  `simplified_payload` tinyint(1) DEFAULT 0,
  `selected_model` varchar(100) DEFAULT 'gemini-2.0-flash',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: search_history (histórico de buscas)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `search_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `query` varchar(255) NOT NULL,
  `location` varchar(255) NOT NULL,
  `tag` varchar(100) DEFAULT NULL,
  `results_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: leads (leads das buscas)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `leads` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `search_history_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `website` varchar(500) DEFAULT NULL,
  `maps_uri` text DEFAULT NULL,
  `cnpj` varchar(20) DEFAULT NULL,
  `partners` text DEFAULT NULL,
  `tag` varchar(100) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `exported` tinyint(1) DEFAULT 0,
  `exported_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `search_history_id` (`search_history_id`),
  KEY `exported` (`exported`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: sessions (sessões de usuário)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: tenant_usage (uso de tokens por tenant por período)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tenant_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `period_start` date NOT NULL COMMENT 'Primeiro dia do período (ex: 2025-01-01)',
  `tokens_used` int(11) NOT NULL DEFAULT 0,
  `token_bonus` int(11) NOT NULL DEFAULT 0 COMMENT 'Créditos extras aprovados',
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_period` (`tenant_id`, `period_start`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: credit_requests (solicitações de créditos)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `credit_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `requested_by_user_id` int(11) NOT NULL,
  `tokens_requested` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: plan_requests (solicitações de plano por empresa)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `plan_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `requested_by_user_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | approved | rejected',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by_user_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `plan_id` (`plan_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: lead_unlocks (leads desbloqueados por usuário)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `lead_unlocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `search_history_id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_search_lead` (`user_id`, `search_history_id`, `lead_id`),
  KEY `search_history_id` (`search_history_id`),
  KEY `user_id` (`user_id`),
  KEY `lead_id` (`lead_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Tabela: platform_settings (configurações globais da plataforma)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `platform_settings` (
  `setting_key` varchar(64) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Chaves estrangeiras (ordem: plans já existe; tenants referencia plans; users referencia tenants)
-- -----------------------------------------------------------------------------
ALTER TABLE `tenants`
  ADD CONSTRAINT `fk_tenants_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT;

ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL;

ALTER TABLE `settings`
  ADD CONSTRAINT `fk_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `search_history`
  ADD CONSTRAINT `fk_search_history_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `leads`
  ADD CONSTRAINT `fk_leads_search_history` FOREIGN KEY (`search_history_id`) REFERENCES `search_history` (`id`) ON DELETE CASCADE;

ALTER TABLE `sessions`
  ADD CONSTRAINT `fk_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `tenant_usage`
  ADD CONSTRAINT `fk_tenant_usage_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

ALTER TABLE `credit_requests`
  ADD CONSTRAINT `fk_credit_requests_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_credit_requests_user` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

ALTER TABLE `plan_requests`
  ADD CONSTRAINT `fk_plan_requests_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_plan_requests_user` FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_plan_requests_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE RESTRICT;

ALTER TABLE `lead_unlocks`
  ADD CONSTRAINT `fk_lead_unlocks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lead_unlocks_search` FOREIGN KEY (`search_history_id`) REFERENCES `search_history` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_lead_unlocks_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE;

-- -----------------------------------------------------------------------------
-- Dados iniciais (seed)
-- -----------------------------------------------------------------------------
INSERT INTO `plans` (`id`, `name`, `slug`, `token_limit`, `price_monthly`, `period`, `status`) VALUES
(1, 'Básico', 'basic', 100, 0.00, 'monthly', 'active'),
(2, 'Período de teste', 'trial', 10, 0.00, 'monthly', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `token_limit` = VALUES(`token_limit`), `price_monthly` = VALUES(`price_monthly`), `period` = VALUES(`period`), `status` = VALUES(`status`);

INSERT INTO `tenants` (`id`, `name`, `slug`, `plan_id`, `plan`, `status`) VALUES
(1, 'Atendo Maps', 'atendo-maps', 1, 'basic', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `plan_id` = VALUES(`plan_id`);

INSERT INTO `users` (`id`, `name`, `email`, `password`, `tenant_id`, `profile`) VALUES
(1, 'Administrador', 'admin@atendo.maps', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, 'super_admin')
ON DUPLICATE KEY UPDATE `profile` = 'super_admin', `tenant_id` = NULL;
-- Senha do admin: admin123 (bcrypt)

INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`) VALUES
('scraper_api_key', NULL),
('credit_price_avulso', '2.00'),
('saas_company_name', '');

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- Fim do schema - MapsProspector Pro
-- Planos adicionais: execute database_seed_plans.sql se desejar mais planos.
-- =============================================================================
