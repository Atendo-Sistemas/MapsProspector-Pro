-- Migração: Estrutura SaaS - Tenants (Empresas) e perfis de usuário
-- Execute este script no banco de dados para habilitar multi-empresa e administrador da plataforma

USE `maps`;

-- Tabela de empresas (tenants)
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL UNIQUE,
  `plan` varchar(50) DEFAULT 'basic',
  `status` varchar(20) DEFAULT 'active' COMMENT 'active | suspended',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Inserir tenant padrão (id=1) para compatibilidade com dados existentes
INSERT INTO `tenants` (`id`, `name`, `slug`, `plan`, `status`) VALUES
(1, 'Nome da empresa SaaS', 'nome-da-empresa-saas', 'basic', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Ajustar users: FK para tenants e perfil super_admin | admin | user
-- Garante que a coluna tenant_id existe e aceita NULL (super_admin)
ALTER TABLE `users`
  MODIFY COLUMN `tenant_id` int(11) DEFAULT NULL,
  MODIFY COLUMN `profile` varchar(50) DEFAULT 'user' COMMENT 'super_admin | admin | user';

-- FK opcional: descomente se quiser integridade referencial (tenant_id -> tenants.id)
-- ALTER TABLE `users` ADD CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE SET NULL;

-- Atualizar usuário administrador padrão para super_admin (tenant_id NULL)
UPDATE `users` SET `profile` = 'super_admin', `tenant_id` = NULL WHERE `email` = 'admin@atendo.maps' LIMIT 1;

-- Garantir que usuários sem tenant válido fiquem no tenant padrão (id=1)
UPDATE `users` u SET u.`tenant_id` = 1 WHERE u.`tenant_id` IS NOT NULL AND u.`profile` != 'super_admin';
