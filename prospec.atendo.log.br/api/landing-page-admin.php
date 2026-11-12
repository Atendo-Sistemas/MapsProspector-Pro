<?php
/**
 * API de Gerenciamento da Landing Page (Super Admin)
 * Endpoint: /api/landing-page-admin.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $userId = requireAuth();
    $authUser = getAuthUser();
    $profile = strtolower((string)($authUser['profile'] ?? ''));

    if ($profile !== 'super_admin') {
        throw new Exception('Acesso restrito ao Super Admin.');
    }

    $db = Database::getInstance()->getConnection();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $id = (int)($input['id'] ?? 0);
        $title = $input['sectionTitle'] ?? '';
        $subtitle = $input['sectionSubtitle'] ?? '';
        $content = $input['sectionContent'] ?? '';

        if (!$id) {
            throw new Exception('ID da seção não informado.');
        }

        // Verifica se a seção existe antes de atualizar
        $stmtCheck = $db->prepare("SELECT id FROM landing_page_sections WHERE id = ?");
        $stmtCheck->execute([$id]);
        if (!$stmtCheck->fetch()) {
            throw new Exception("Seção com ID $id não encontrada.");
        }

        // Executa o UPDATE com os nomes de colunas corretos (section_title, section_subtitle, section_content)
        $stmt = $db->prepare("
            UPDATE landing_page_sections 
            SET section_title = ?, section_subtitle = ?, section_content = ? 
            WHERE id = ?
        ");
        $stmt->execute([$title, $subtitle, $content, $id]);

        echo json_encode(['success' => true, 'message' => 'Seção atualizada com sucesso!']);
        exit;
    } else {
        throw new Exception('Método não permitido');
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
    exit;
}