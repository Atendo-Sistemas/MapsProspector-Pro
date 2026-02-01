-- Migração: Adicionar campo scraper_api_key na tabela settings
-- Execute este script no banco de dados para adicionar suporte à API Thordata

USE `maps`;

ALTER TABLE `settings` 
ADD COLUMN `scraper_api_key` TEXT DEFAULT NULL AFTER `selected_model`;
