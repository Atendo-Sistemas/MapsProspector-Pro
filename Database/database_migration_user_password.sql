-- Migração: Campo de senha para usuários (login com e-mail + senha)
-- Execute no banco `maps` se a tabela users não tiver a coluna password.
-- Instalações novas via maps_schema_full.sql já incluem a coluna; ignore erro "Duplicate column" se já existir.

USE `maps`;

ALTER TABLE `users` ADD COLUMN `password` varchar(255) DEFAULT NULL AFTER `email`;
