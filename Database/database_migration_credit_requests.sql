-- Migração: Solicitação de créditos (tokens) por empresa
-- Usuários solicitam X créditos; Super Admin aprova ou recusa.
-- Ao aprovar, os tokens são adicionados como bônus no período atual da empresa.

USE `maps`;

-- Bônus de tokens no período (adicionado ao limite do plano quando aprovado)
-- Se a coluna token_bonus já existir, comente a linha abaixo.
ALTER TABLE `tenant_usage` ADD COLUMN `token_bonus` int(11) NOT NULL DEFAULT 0 COMMENT 'Créditos extras aprovados para o período' AFTER `tokens_used`;

-- Tabela de solicitações de créditos
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
