<?php
/**
 * Migration Runner - Execute database migrations
 * Access: Super Admin only
 * URL: /api/migrate.php
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();

// Check if user is super admin
$authUser = null;
try {
    $authUser = getAuthUser();
} catch (Exception $e) {
    jsonError('Unauthorized', 401);
}

if (!$authUser || strtolower($authUser['profile']) !== 'super_admin') {
    jsonError('Access denied. Super admin only.', 403);
}

$db = Database::getInstance()->getConnection();

$results = [];

try {
    // Create search_folders table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS `search_folders` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `name` varchar(255) NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = 'Table search_folders created successfully';
} catch (PDOException $e) {
    $results[] = 'Error creating search_folders: ' . $e->getMessage();
}

try {
    // Create landing_page_content table if not exists
    $db->exec("
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = 'Table landing_page_content created successfully';
    
    // Seed default content if empty
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM landing_page_content");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row['cnt'] == 0) {
        $sections = [
            ['hero', 'Encontre seus clientes идеальними', 'A ferramenta de prospecção B2B que transforma sua forma de buscar leads no Google Maps', '<p>Descubra empresas próximas ao seu negócio com dados precisos e atualizados. Nossa ferramenta de IA faz a busca automaticamente enquanto você foca em vender.</p>', 1, '{"cta_text": "Começar agora grátis", "cta_link": "#register"}'],
            ['features', 'Recursos Potentes', 'Tudo que você precisa para prospectar clientes', NULL, 2, '{"items": [{"icon": "search", "title": "Busca Inteligente", "description": "Encontre empresas por segmento, localização ou palavras-chave"}, {"icon": "map", "title": "Localização Precisa", "description": "Veja exatamente onde seus clientes potenciais estão"}, {"icon": "chart", "title": "Análise de Dados", "description": "Obtenha informações detalhadas sobre cada empresa"}]}'],
            ['how_it_works', 'Como Funciona', 'Três passos simples para encontrar seus clientes идеальivos', '<ol class="space-y-4"><li class="flex items-start gap-4"><span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">1</span><div><strong>Defina seu público</strong><p class="text-slate-600">Escolha o segmento, região e critérios de busca</p></div></li><li class="flex items-start gap-4"><span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">2</span><div><strong>Deixe a IA trabalhar</strong><p class="text-slate-600">Nossa ferramenta busca automaticamente no Google Maps</p></div></li><li class="flex items-start gap-4"><span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">3</span><div><strong>Converta clientes</strong><p class="text-slate-600">Exporte os dados para seu CRM e comece a vender</p></div></li></ol>', 3, NULL],
            ['benefits', 'Por Que Escolher o MapsProspector?', 'A solução completa para sua prospecção B2B', NULL, 4, '{"items": [{"title": "Economize Tempo", "description": "Automatize a busca de leads e focando apenas no que importa: vender", "icon": "clock"}, {"title": "Dados Precisos", "description": "Informações direto do Google Maps, sempre atualizadas e confiáveis", "icon": "check-circle"}]}'],
            ['testimonials', 'O Que Nossos Clientes Dizem', 'Veja o que empreendedores estão conseguindo com o MapsProspector', NULL, 5, '{"items": [{"name": "João Silva", "company": "Empresa Tech", "text": "Conseguimos aumentar em 300% nossa base de prospects em apenas 2 meses."}, {"name": "Maria Santos", "company": "Startup BH", "text": "A ferramenta mudou completamente nossa estratégia de prospecção."}]}'],
            ['faq', 'Perguntas Frequentes', 'Tire suas dúvidas sobre o MapsProspector', NULL, 6, '{"items": [{"question": "Como funciona o período de teste?", "answer": "Oferecemos 7 dias gratuitos para você testar todas as funcionalidades."}, {"question": "Posso cancelar a qualquer momento?", "answer": "Sim! Cancelamento sem burocracias."}]}'],
            ['cta', 'Pronto para Transformar sua Prospecção?', 'Comece hoje mesmo e conquiste mais clientes', '<p>Junte-se a milhares de empreendedores que já estão crescendo seus negócios com o MapsProspector.</p>', 7, '{"cta_text": "Cadastrar Minha Empresa Grátis", "cta_link": "#register", "secondary_cta_text": "Falar com Consultor", "secondary_cta_link": "#contact"}'],
            ['footer', NULL, NULL, '<p>&copy; 2024 MapsProspector. Todos os direitos reservados.</p>', 8, '{"company_name": "MapsProspector"}']
        ];
        
        $stmt = $db->prepare("INSERT INTO landing_page_content (section_key, section_title, section_subtitle, section_content, section_order, extra_data) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($sections as $section) {
            $stmt->execute($section);
        }
        $results[] = 'Default landing page content seeded';
    }
} catch (PDOException $e) {
    $results[] = 'Error creating landing_page_content: ' . $e->getMessage();
}

try {
    // Add folder_id column to search_history if not exists
    $stmt = $db->query("SHOW COLUMNS FROM search_history LIKE 'folder_id'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE `search_history` ADD COLUMN `folder_id` int(11) DEFAULT NULL AFTER `tag`");
        $db->exec("ALTER TABLE `search_history` ADD KEY `folder_id` (`folder_id`)");
        $results[] = 'Column folder_id added to search_history';
    } else {
        $results[] = 'Column folder_id already exists in search_history';
    }
} catch (PDOException $e) {
    $results[] = 'Error adding folder_id column: ' . $e->getMessage();
}

jsonSuccess([
    'message' => 'Migrations completed',
    'results' => $results
]);
