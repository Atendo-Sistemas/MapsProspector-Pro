<?php
/**
 * API administrativa da Landing Page.
 * Apenas super_admin pode consultar, criar, editar ou remover seções.
 * Endpoint: /api/landing-page.php
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (ob_get_level() > 0) {
    ob_end_clean();
}

if (!function_exists('requireSuperAdmin') || !function_exists('jsonSuccess') || !function_exists('jsonError')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Funções administrativas indisponíveis.'], JSON_UNESCAPED_UNICODE);
    exit;
}

requireSuperAdmin();
$db = Database::getInstance()->getConnection();
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function landingAdminExtraData($value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (is_string($value) && trim($value) !== '') {
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

function landingAdminFormatSection(array $section): array
{
    return [
        'id' => (int) ($section['id'] ?? 0),
        'sectionKey' => (string) ($section['section_key'] ?? ''),
        'sectionTitle' => $section['section_title'] ?? null,
        'sectionSubtitle' => $section['section_subtitle'] ?? null,
        'sectionContent' => $section['section_content'] ?? null,
        'sectionImage' => $section['section_image'] ?? null,
        'sectionOrder' => (int) ($section['section_order'] ?? 0),
        'isActive' => (bool) ($section['is_active'] ?? 0),
        'extraData' => landingAdminExtraData($section['extra_data'] ?? null),
    ];
}

if ($method === 'GET') {
    try {
        $sectionKey = isset($_GET['section']) ? trim((string) $_GET['section']) : '';
        if ($sectionKey !== '') {
            $stmt = $db->prepare('SELECT * FROM landing_page_content WHERE section_key = ? LIMIT 1');
            $stmt->execute([$sectionKey]);
            $section = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$section) {
                jsonError('Seção não encontrada.', 404);
            }
            jsonSuccess(landingAdminFormatSection($section));
        }

        $stmt = $db->query('SELECT * FROM landing_page_content ORDER BY section_order ASC, id ASC');
        $sections = array_map('landingAdminFormatSection', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        jsonSuccess($sections);
    } catch (Throwable $e) {
        jsonError('Erro ao carregar as seções da landing.', 500);
    }
}

if ($method === 'POST' || $method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonError('Corpo JSON inválido.', 400);
    }

    $sectionKey = trim((string) ($input['section_key'] ?? $input['sectionKey'] ?? ''));
    if ($sectionKey === '') {
        jsonError('O campo section_key é obrigatório.', 400);
    }

    $sectionTitle = $input['section_title'] ?? $input['sectionTitle'] ?? null;
    $sectionSubtitle = $input['section_subtitle'] ?? $input['sectionSubtitle'] ?? null;
    $sectionContent = $input['section_content'] ?? $input['sectionContent'] ?? null;
    $sectionImage = $input['section_image'] ?? $input['sectionImage'] ?? null;
    $sectionOrder = (int) ($input['section_order'] ?? $input['sectionOrder'] ?? 0);
    $isActive = !empty($input['is_active']) || !empty($input['isActive']) ? 1 : 0;
    $extraData = landingAdminExtraData($input['extra_data'] ?? $input['extraData'] ?? []);
    $extraJson = json_encode($extraData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    try {
        $existsStmt = $db->prepare('SELECT id FROM landing_page_content WHERE section_key = ? LIMIT 1');
        $existsStmt->execute([$sectionKey]);
        $existingId = $existsStmt->fetchColumn();

        if ($existingId) {
            $stmt = $db->prepare(
                'UPDATE landing_page_content
                    SET section_title = ?, section_subtitle = ?, section_content = ?, section_image = ?,
                        section_order = ?, is_active = ?, extra_data = ?
                  WHERE section_key = ?'
            );
            $stmt->execute([$sectionTitle, $sectionSubtitle, $sectionContent, $sectionImage, $sectionOrder, $isActive, $extraJson, $sectionKey]);
            jsonSuccess(['message' => 'Seção atualizada com sucesso.', 'id' => (int) $existingId]);
        }

        $stmt = $db->prepare(
            'INSERT INTO landing_page_content
                (section_key, section_title, section_subtitle, section_content, section_image, section_order, is_active, extra_data)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$sectionKey, $sectionTitle, $sectionSubtitle, $sectionContent, $sectionImage, $sectionOrder, $isActive, $extraJson]);
        jsonSuccess(['message' => 'Seção criada com sucesso.', 'id' => (int) $db->lastInsertId()]);
    } catch (Throwable $e) {
        jsonError('Erro ao salvar a seção.', 500);
    }
}

if ($method === 'DELETE') {
    $sectionKey = trim((string) ($_GET['section'] ?? ''));
    if ($sectionKey === '') {
        jsonError('Seção não especificada.', 400);
    }

    try {
        $stmt = $db->prepare('DELETE FROM landing_page_content WHERE section_key = ?');
        $stmt->execute([$sectionKey]);
        jsonSuccess(['message' => 'Seção removida com sucesso.']);
    } catch (Throwable $e) {
        jsonError('Erro ao remover a seção.', 500);
    }
}

jsonError('Método não permitido.', 405);

