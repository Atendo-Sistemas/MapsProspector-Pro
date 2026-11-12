-- =============================================================================
-- MapsProspector Pro - Migração: OpenRouter API
-- Adiciona configurações da API de IA via OpenRouter
-- =============================================================================

-- Adiciona as configurações da plataforma para OpenRouter
INSERT IGNORE INTO `platform_settings` (`setting_key`, `setting_value`) VALUES
('openrouter_api_key', NULL),
('ia_model', 'google/gemini-2.0-flash-001');

-- =============================================================================
-- Fim da migração
-- Execute este arquivo para adicionar suporte à OpenRouter API
-- =============================================================================
