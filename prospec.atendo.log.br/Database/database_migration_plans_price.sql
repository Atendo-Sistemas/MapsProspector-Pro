-- Migração: Valor mensal nos planos (para bancos que já rodaram database_migration_plans.sql)
-- Execute uma vez. Se a coluna price_monthly já existir, comente a linha ALTER abaixo.

USE `maps`;

ALTER TABLE `plans` ADD COLUMN `price_monthly` decimal(10,2) DEFAULT 0.00 COMMENT 'Valor mensal do plano (R$)' AFTER `token_limit`;

UPDATE `plans` SET `price_monthly` = 0.00 WHERE `price_monthly` IS NULL;
