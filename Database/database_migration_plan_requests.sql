-- Migração: Solicitações de plano por empresa
-- Cliente escolhe um plano e solicita; Super Admin confirma ou recusa.
-- Ao confirmar, a empresa (tenant) passa a ter o plano solicitado (plan_id).

USE `maps`;

-- Tabela de solicitações de plano
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
