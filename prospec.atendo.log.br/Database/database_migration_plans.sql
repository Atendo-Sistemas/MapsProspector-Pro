-- Migração: Planos e limite de tokens por empresa
-- Super admin cadastra planos (nome, limite de tokens); empresas são vinculadas a um plano.
-- Uso de tokens é contabilizado por tenant por período (mensal).

USE `maps`;

-- Tabela de planos (cadastrados pelo super_admin)
CREATE TABLE IF NOT EXISTS `plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(50) NOT NULL UNIQUE,
  `token_limit` int(11) NOT NULL DEFAULT 100 COMMENT 'Limite de tokens (ex: buscas) por período',
  `price_monthly` decimal(10,2) DEFAULT 0.00 COMMENT 'Valor mensal do plano (R$)',
  `period` varchar(20) NOT NULL DEFAULT 'monthly' COMMENT 'monthly | yearly',
  `status` varchar(20) NOT NULL DEFAULT 'active' COMMENT 'active | inactive',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Plano padrão (compatibilidade com tenants existentes)
INSERT INTO `plans` (`id`, `name`, `slug`, `token_limit`, `price_monthly`, `period`, `status`) VALUES
(1, 'Básico', 'basic', 100, 0.00, 'monthly', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `token_limit` = VALUES(`token_limit`);

-- Coluna plan_id em tenants (vincula empresa ao plano; mantém coluna plan para exibição legada)
-- Execute apenas uma vez; se plan_id já existir, comente as duas linhas abaixo.
ALTER TABLE `tenants` ADD COLUMN `plan_id` int(11) DEFAULT 1 AFTER `slug`;
ALTER TABLE `tenants` ADD KEY `plan_id` (`plan_id`);

-- Atualizar tenants existentes: plan_id = 1 onde não definido
UPDATE `tenants` SET `plan_id` = 1 WHERE `plan_id` IS NULL;

-- Tabela de uso de tokens por tenant por período (mensal)
CREATE TABLE IF NOT EXISTS `tenant_usage` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `period_start` date NOT NULL COMMENT 'Primeiro dia do período (ex: 2025-01-01 para janeiro)',
  `tokens_used` int(11) NOT NULL DEFAULT 0,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_period` (`tenant_id`, `period_start`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
