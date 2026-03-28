<?php
/**
 * API Landing Page (Pública)
 * Endpoint: /api/landing-page-public.php
 * Retorna o conteúdo da landing page para exibição pública.
 * Não requer autenticação.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

ob_clean();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("SELECT * FROM landing_page_content WHERE is_active = 1 ORDER BY section_order ASC");
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $items = [];
    foreach ($sections as $section) {
        $items[] = [
            'id' => (int) $section['id'],
            'sectionKey' => $section['section_key'],
            'sectionTitle' => $section['section_title'],
            'sectionSubtitle' => $section['section_subtitle'],
            'sectionContent' => $section['section_content'],
            'sectionImage' => $section['section_image'],
            'sectionOrder' => (int) $section['section_order'],
            'extraData' => $section['extra_data'] ? json_decode($section['extra_data'], true) : null
        ];
    }
    
    echo json_encode(['success' => true, 'sections' => $items, 'total' => count($items)]);
} catch (PDOException $e) {
    error_log("landing-page-public.php GET: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar conteúdo.']);
}
