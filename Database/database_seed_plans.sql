-- Seed: Planos (somente tokens) para clientes escolherem
-- Planos recorrentes: 1K, 5K, 15K. Avulso (opcional, mais caro): 1K, 5K, 15K.
-- Execute após database_migration_plans.sql e database_migration_plan_requests.sql (se usar plan_requests).

USE `maps`;

-- Planos recorrentes (mensal) — 1 token = 1 página (até 20 resultados)
INSERT INTO `plans` (`name`, `slug`, `token_limit`, `price_monthly`, `period`, `status`) VALUES
('Plano 1K', 'plano-1k', 1000, 40.80, 'monthly', 'active'),
('Plano 5K', 'plano-5k', 5000, 204.00, 'monthly', 'active'),
('Plano 15K', 'plano-15k', 15000, 612.00, 'monthly', 'active'),
('Avulso 1K', 'avulso-1k', 1000, 45.00, 'monthly', 'active'),
('Avulso 5K', 'avulso-5k', 5000, 225.00, 'monthly', 'active'),
('Avulso 15K', 'avulso-15k', 15000, 675.00, 'monthly', 'active')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `token_limit` = VALUES(`token_limit`), `price_monthly` = VALUES(`price_monthly`), `period` = VALUES(`period`), `status` = VALUES(`status`);
