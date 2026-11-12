-- Migração: Nome do site / empresa id 1 — de "Atendo Maps" para "Nome da empresa SaaS"
-- Execute este script para atualizar o tenant padrão (id 1) e o nome da empresa SaaS na plataforma.

USE `maps`;

-- Atualizar tenant id 1 (empresa padrão)
UPDATE `tenants` SET `name` = 'Nome da empresa SaaS', `slug` = 'nome-da-empresa-saas' WHERE `id` = 1;

-- Atualizar nome da empresa SaaS nas configurações da plataforma (sidebar e título)
INSERT INTO `platform_settings` (`setting_key`, `setting_value`) VALUES ('saas_company_name', 'Nome da empresa SaaS')
ON DUPLICATE KEY UPDATE `setting_value` = 'Nome da empresa SaaS';

-- Atualizar "Nome da Instância" em Configurações: quem tinha "Atendo CRM" passa a "Nome da empresa SaaS" (evita "ATENDO CRM" após o | no título da aba)
UPDATE `settings` SET `tenant_name` = 'Nome da empresa SaaS' WHERE `tenant_name` = 'Atendo CRM';
