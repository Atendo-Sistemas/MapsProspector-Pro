-- Migração: Plano Período de teste (10 créditos grátis para novos usuários)
-- Execute uma vez no banco maps (phpMyAdmin ou MySQL).
-- Novos tenants passam a ser criados com este plano via api/register.php.

USE `maps`;

INSERT INTO `plans` (`name`, `slug`, `token_limit`, `price_monthly`, `period`, `status`) VALUES
('Período de teste', 'trial', 10, 0.00, 'monthly', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `token_limit` = VALUES(`token_limit`), `price_monthly` = VALUES(`price_monthly`), `period` = VALUES(`period`), `status` = VALUES(`status`);
