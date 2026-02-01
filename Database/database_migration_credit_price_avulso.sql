-- Migração: Preço avulso por crédito (exibido em Solicitar créditos)
-- Valor em reais por crédito; usado para calcular "Valor a pagar" ao digitar a quantidade.

USE `maps`;

INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`) VALUES
('credit_price_avulso', '2.00');
