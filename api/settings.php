<?php
/**
 * API de Configurações
 * Endpoint: /api/settings.php
 */

// Previne qualquer output antes do JSON
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Limpa qualquer output buffer
ob_clean();

$userId = requireAuth();
$db = Database::getInstance()->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Busca configurações
    $stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settings = $stmt->fetch();
    
    if (!$settings) {
        // Cria configuração padrão
        $defaultSettings = [
            'baseUrl' => '',
            'token' => '',
            'tenantName' => 'Atendo CRM',
            'useProxy' => 0,
            'wrapInBody' => 0,
            'simplifiedPayload' => 0,
            'scraperApiKey' => ''
        ];
        jsonSuccess($defaultSettings);
    } else {
        jsonSuccess([
            'baseUrl' => $settings['base_url'] ?? '',
            'token' => $settings['token'] ?? '',
            'tenantName' => $settings['tenant_name'] ?? 'Atendo CRM',
            'useProxy' => (bool)($settings['use_proxy'] ?? 0),
            'wrapInBody' => (bool)($settings['wrap_in_body'] ?? 0),
            'simplifiedPayload' => (bool)($settings['simplified_payload'] ?? 0),
            'scraperApiKey' => $settings['scraper_api_key'] ?? ''
        ]);
    }
    
} elseif ($method === 'POST' || $method === 'PUT') {
    // Salva configurações
    $input = json_decode(file_get_contents('php://input'), true);
    
    $baseUrl = sanitizeInput($input['baseUrl'] ?? '');
    $token = sanitizeInput($input['token'] ?? '');
    $tenantName = sanitizeInput($input['tenantName'] ?? 'Atendo CRM');
    $useProxy = isset($input['useProxy']) && $input['useProxy'] ? 1 : 0;
    $wrapInBody = isset($input['wrapInBody']) && $input['wrapInBody'] ? 1 : 0;
    $simplifiedPayload = isset($input['simplifiedPayload']) && $input['simplifiedPayload'] ? 1 : 0;
    $scraperApiKey = sanitizeInput($input['scraperApiKey'] ?? '');
    
    // Verifica se já existe
    $stmt = $db->prepare("SELECT id FROM settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        // Atualiza (mantém selected_model se existir, mas não atualiza)
        $stmt = $db->prepare("
            UPDATE settings SET
                base_url = ?,
                token = ?,
                tenant_name = ?,
                use_proxy = ?,
                wrap_in_body = ?,
                simplified_payload = ?,
                scraper_api_key = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $baseUrl, $token, $tenantName, $useProxy, $wrapInBody, 
            $simplifiedPayload, $scraperApiKey, $userId
        ]);
    } else {
        // Insere (não inclui selected_model)
        $stmt = $db->prepare("
            INSERT INTO settings 
            (user_id, base_url, token, tenant_name, use_proxy, wrap_in_body, simplified_payload, scraper_api_key)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $baseUrl, $token, $tenantName, $useProxy, 
            $wrapInBody, $simplifiedPayload, $scraperApiKey
        ]);
    }
    
    jsonSuccess(null, 'Configurações salvas com sucesso');
    
} else {
    jsonError('Método não permitido', 405);
}
