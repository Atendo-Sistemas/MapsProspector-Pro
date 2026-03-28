<?php
/**
 * API Landing Page - Conteúdo editável pelo SuperAdmin
 * Endpoint: /api/landing-page.php
 * Apenas super_admin pode gerenciar o conteúdo.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();

$userId = requireSuperAdmin();
$db = Database::getInstance()->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $sectionKey = isset($_GET['section']) ? $_GET['section'] : null;

    if ($sectionKey) {
        try {
            $stmt = $db->prepare("SELECT * FROM landing_page_content WHERE section_key = ?");
            $stmt->execute([$sectionKey]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            jsonError('Erro ao buscar seção: ' . $e->getMessage(), 500);
        }
        if (!$section) {
            jsonError('Seção não encontrada', 404);
        }
        jsonSuccess(formatSection($section));
    }

    try {
        $stmt = $db->query("SELECT * FROM landing_page_content ORDER BY section_order ASC");
        $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $formatted = array_map('formatSection', $sections);
        jsonSuccess($formatted);
    } catch (PDOException $e) {
        jsonError('Erro ao buscar conteúdo: ' . $e->getMessage(), 500);
    }
}

if ($method === 'POST' || $method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['section_key'])) {
        jsonError('Dados inválidos. Campo section_key é obrigatório.', 400);
    }

    $sectionKey = $input['section_key'];
    $sectionTitle = isset($input['section_title']) ? $input['section_title'] : null;
    $sectionSubtitle = isset($input['section_subtitle']) ? $input['section_subtitle'] : null;
    $sectionContent = isset($input['section_content']) ? $input['section_content'] : null;
    $sectionImage = isset($input['section_image']) ? $input['section_image'] : null;
    $sectionOrder = isset($input['section_order']) ? (int) $input['section_order'] : 0;
    $isActive = isset($input['is_active']) ? (int) $input['is_active'] : 1;
    $extraData = isset($input['extra_data']) ? json_encode($input['extra_data']) : null;

    try {
        $stmt = $db->prepare("SELECT id FROM landing_page_content WHERE section_key = ?");
        $stmt->execute([$sectionKey]);
        $exists = $stmt->fetch();

        if ($exists) {
            $stmt = $db->prepare("UPDATE landing_page_content SET 
                section_title = ?, 
                section_subtitle = ?, 
                section_content = ?, 
                section_image = ?,
                section_order = ?,
                is_active = ?,
                extra_data = ?
            WHERE section_key = ?");
            $stmt->execute([$sectionTitle, $sectionSubtitle, $sectionContent, $sectionImage, $sectionOrder, $isActive, $extraData, $sectionKey]);
            jsonSuccess(['message' => 'Seção atualizada com sucesso']);
        } else {
            $stmt = $db->prepare("INSERT INTO landing_page_content 
                (section_key, section_title, section_subtitle, section_content, section_image, section_order, is_active, extra_data) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$sectionKey, $sectionTitle, $sectionSubtitle, $sectionContent, $sectionImage, $sectionOrder, $isActive, $extraData]);
            jsonSuccess(['message' => 'Seção criada com sucesso', 'id' => $db->lastInsertId()]);
        }
    } catch (PDOException $e) {
        jsonError('Erro ao salvar: ' . $e->getMessage(), 500);
    }
}

if ($method === 'DELETE') {
    $sectionKey = isset($_GET['section']) ? $_GET['section'] : null;
    
    if (!$sectionKey) {
        jsonError('Seção não especificada', 400);
    }

    try {
        $stmt = $db->prepare("DELETE FROM landing_page_content WHERE section_key = ?");
        $stmt->execute([$sectionKey]);
        jsonSuccess(['message' => 'Seção removida com sucesso']);
    } catch (PDOException $e) {
        jsonError('Erro ao remover: ' . $e->getMessage(), 500);
    }
}

function formatSection($section) {
    return [
        'id' => (int) $section['id'],
        'sectionKey' => $section['section_key'],
        'sectionTitle' => $section['section_title'],
        'sectionSubtitle' => $section['section_subtitle'],
        'sectionContent' => $section['section_content'],
        'sectionImage' => $section['section_image'],
        'sectionOrder' => (int) $section['section_order'],
        'isActive' => (bool) $section['is_active'],
        'extraData' => $section['extra_data'] ? json_decode($section['extra_data'], true) : null
    ];
}
