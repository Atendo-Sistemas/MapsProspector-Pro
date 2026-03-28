-- =============================================================================
-- MapsProspector Pro - Migração: Tabela de conteúdo da Landing Page
-- =============================================================================

SET NAMES utf8mb4;

USE `maps`;

-- -----------------------------------------------------------------------------
-- Tabela: landing_page_content (conteúdo editável da landing page)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `landing_page_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `section_key` varchar(100) NOT NULL COMMENT 'Identificador da seção',
  `section_title` varchar(255) DEFAULT NULL COMMENT 'Título da seção',
  `section_subtitle` varchar(500) DEFAULT NULL COMMENT 'Subtítulo da seção',
  `section_content` text DEFAULT NULL COMMENT 'Conteúdo principal (HTML)',
  `section_image` varchar(500) DEFAULT NULL COMMENT 'URL da imagem',
  `section_order` int(11) DEFAULT 0 COMMENT 'Ordem de exibição',
  `is_active` tinyint(1) DEFAULT 1 COMMENT 'Seção ativa',
  `extra_data` json DEFAULT NULL COMMENT 'Dados extras (botões, links, etc)',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `section_key` (`section_key`),
  KEY `section_order` (`section_order`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Dados iniciais (seed) - Conteúdo padrão da landing page
-- -----------------------------------------------------------------------------
INSERT INTO `landing_page_content` (`section_key`, `section_title`, `section_subtitle`, `section_content`, `section_order`, `is_active`, `extra_data`) VALUES
('hero', 'Encontre seus clientes идеальними', 'A ferramenta de prospecção B2B que transforma sua forma de buscar leads no Google Maps', '<p>Descubra empresas próximas ao seu negócio com dados precisos e atualizados. Nossa ferramenta de IA faz a busca automaticamente enquanto você foca em vender.</p>', 1, 1, '{"cta_text": "Começar agora grátis", "cta_link": "#register", "video_url": null}'),

('features', 'Recursos Potentes', 'Tudo que você precisa para prospectar clientes', NULL, 2, 1, '{"items": [
  {"icon": "search", "title": "Busca Inteligente", "description": "Encontre empresas por segmento, localização ou palavras-chave"},
  {"icon": "map", "title": "Localização Precisa", "description": "Veja exatamente onde seus clientes potenciais estãono mapa"},
  {"icon": "chart", "title": "Análise de Dados", "description": "Obtenha informações detalhadas sobre cada empresa"},
  {"icon": "zap", "title": "Automação com IA", "description": "Nossa IA enrichment seus dados automaticamente"},
  {"icon": "shield", "title": "Dados Verificados", "description": "Informações sempre atualizadas do Google Maps"},
  {"icon": "users", "title": "Gestão de Leads", "description": "Organize e acompanhe todos os seus prospectos"}
]}'),

('how_it_works', 'Como Funciona', 'Três passos simples para encontrar seus clientes идеальними', '<ol class="space-y-4"><li class="flex items-start gap-4"><span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">1</span><div><strong>Defina seu público</strong><p class="text-slate-600">Escolha o segmento, região e critérios de busca</p></div></li><li class="flex items-start gap-4"><span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">2</span><div><strong>Deixe a IA trabalhar</strong><p class="text-slate-600">Nossa ferramenta busca automaticamente no Google Maps</p></div></li><li class="flex items-start gap-4"><span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">3</span><div><strong>Converta clientes</strong><p class="text-slate-600">Exporte os dados para seu CRM e comece a vender</p></div></li></ol>', 3, 1, NULL),

('benefits', 'Por Que Escolher o MapsProspector?', 'A solução completa para sua prospecção B2B', NULL, 4, 1, '{"items": [
  {"title": "Economize Tempo", "description": "Automatize a busca de leads e focando apenas no que importa: vender", "icon": "clock"},
  {"title": "Dados Precisos", "description": "Informações direto do Google Maps, sempre atualizadas e confiáveis", "icon": "check-circle"},
  {"title": "Escalável", "description": "Planos que crescem com seu negócio, desde pequenas empresas até grandes corporações", "icon": "trending-up"},
  {"title": "Suporte Dedicado", "description": "Nossa equipe está sempre pronta para ajudar você a atingir seus objetivos", "icon": "headphones"}
]}'),

('testimonials', 'O Que Nossos Clientes Dizem', 'Veja o que empreended ores estão conseguindo com o MapsProspector', NULL, 5, 1, '{"items": [
  {"name": "João Silva", "company": "Empresa Tech", "text": "Conseguimos aumentar em 300% nossa base de prospects em apenas 2 meses."},
  {"name": "Maria Santos", "company": "Startup BH", "text": "A ferramenta mudou completamente nossa estratégia de prospecção."},
  {"name": "Pedro Oliveira", "company": "Corp Solutions", "text": "Os dados são sempre precisos e o suporte é excelente."}
]}'),

('faq', 'Perguntas Frequentes', 'Tire suas dúvidas sobre o MapsProspector', NULL, 6, 1, '{"items": [
  {"question": "Como funciona o período de teste?", "answer": "Oferecemos 7 dias gratuitos para você testar todas as funcionalidades. Não precisa de cartão de crédito."},
  {"question": "Posso cancelar a qualquer momento?", "answer": "Sim! Cancelamento sem burocracias. Você mantém acesso até o final do período pago."},
  {"question": "Os dados são atualizados?", "answer": "Sim, buscamos diretamente do Google Maps e atualizamos conforme você solicita novas buscas."},
  {"question": "Posso exportar para meu CRM?", "answer": "Claro! Exportamos para Excel, CSV ou/enviamos automaticamente via webhook para seu CRM."}
]}'),

('cta', 'Pronto para Transformar sua Prospecção?', 'Comece hoje mesmo e conquiste mais clientes', '<p>Junte-se a milhares de empreended ores que já estão crescendo seus negócios com o MapsProspector.</p>', 7, 1, '{"cta_text": "Cadastrar Minha Empresa Grátis", "cta_link": "#register", "secondary_cta_text": "Falar com Consultor", "secondary_cta_link": "#contact"}'),

('footer', NULL, NULL, '<p>&copy; 2024 MapsProspector. Todos os direitos reservados.</p>', 8, 1, '{"company_name": "MapsProspector", "social_links": {"email": "contato@mapsprospector.com", "whatsapp": "5511999999999"}}');

-- =============================================================================
-- Fim da migração
-- =============================================================================
