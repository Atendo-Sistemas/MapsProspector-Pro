<?php
/**
 * API pública da Landing Page.
 * Fonte única: landing_page_content.
 * Endpoint: GET /api/landing-page-public.php
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function landingPublicFormatSection(array $section): array
{
    $extraData = [];
    if (!empty($section['extra_data'])) {
        $decoded = is_array($section['extra_data'])
            ? $section['extra_data']
            : json_decode((string) $section['extra_data'], true);
        $extraData = is_array($decoded) ? $decoded : [];
    }

    return [
        'id' => (int) ($section['id'] ?? 0),
        'sectionKey' => (string) ($section['section_key'] ?? ''),
        'sectionTitle' => $section['section_title'] ?? null,
        'sectionSubtitle' => $section['section_subtitle'] ?? null,
        'sectionContent' => $section['section_content'] ?? null,
        'sectionImage' => $section['section_image'] ?? null,
        'sectionOrder' => (int) ($section['section_order'] ?? 0),
        'isActive' => (bool) ($section['is_active'] ?? 0),
        'extraData' => $extraData,
    ];
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = Database::getInstance()->getConnection();
    $stmt = $db->query(
        "SELECT id, section_key, section_title, section_subtitle, section_content,
                section_image, section_order, is_active, extra_data
         FROM landing_page_content
         WHERE is_active = 1
         ORDER BY section_order ASC, id ASC"
    );

    $sections = array_map('landingPublicFormatSection', $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    echo json_encode(['success' => true, 'sections' => $sections], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Não foi possível carregar o conteúdo da landing.'], JSON_UNESCAPED_UNICODE);
}

