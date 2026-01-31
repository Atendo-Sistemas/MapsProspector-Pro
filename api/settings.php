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
$authUser = getAuthUser();
$db = Database::getInstance()->getConnection();
$isSuperAdmin = ($authUser && ($authUser['profile'] ?? '') === 'super_admin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Chave Thordata: apenas super_admin vê o valor; demais recebem apenas se está configurada
    $platformScraperKey = getPlatformSetting($db, 'scraper_api_key');
    $platformScraperKey = $platformScraperKey === null ? '' : (string) $platformScraperKey;
    $scraperApiKeyConfigured = trim($platformScraperKey) !== '';

    // Busca configurações do usuário (webhook, token, etc.)
    $stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settings = $stmt->fetch();
    $settings = $settings ?: [];
    
    $base = [
        'baseUrl' => $settings['base_url'] ?? '',
        'token' => '',  // nunca enviado em texto; front usa campo para valor do header apikey
        'tenantName' => $settings['tenant_name'] ?? 'Atendo CRM',
        'useProxy' => (bool)($settings['use_proxy'] ?? 0),
        'wrapInBody' => (bool)($settings['wrap_in_body'] ?? 0),
        'simplifiedPayload' => (bool)($settings['simplified_payload'] ?? 0),
        'scraperApiKeyConfigured' => $scraperApiKeyConfigured
    ];
    if ($isSuperAdmin) {
        $base['scraperApiKey'] = $platformScraperKey ?? '';
    } else {
        $base['scraperApiKey'] = '';  // demais não veem a chave
    }
    jsonSuccess($base);
    
} elseif ($method === 'POST' || $method === 'PUT') {
    // Salva configurações
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $baseUrl = sanitizeInput($input['baseUrl'] ?? '');
    $apikeyPlain = trim($input['token'] ?? '');
    $tenantName = sanitizeInput($input['tenantName'] ?? 'Atendo CRM');
    $useProxy = isset($input['useProxy']) && $input['useProxy'] ? 1 : 0;
    $wrapInBody = isset($input['wrapInBody']) && $input['wrapInBody'] ? 1 : 0;
    $simplifiedPayload = isset($input['simplifiedPayload']) && $input['simplifiedPayload'] ? 1 : 0;

    // Chave Thordata: apenas super_admin pode alterar; salva em platform_settings (toda a plataforma usa)
    if ($isSuperAdmin && array_key_exists('scraperApiKey', $input)) {
        $scraperApiKey = sanitizeInput($input['scraperApiKey'] ?? '');
        try {
            setPlatformSetting($db, 'scraper_api_key', $scraperApiKey);
        } catch (PDOException $e) {
            error_log("settings.php setPlatformSetting: " . $e->getMessage());
            jsonError('Não foi possível salvar a chave Thordata. Execute o script database_migration_platform_settings.sql no banco de dados.', 500);
        }
    }

    $stmt = $db->prepare("SELECT id, token FROM settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $exists = $stmt->fetch();

    if ($exists) {
        $tokenToSave = $exists['token'];
        if ($apikeyPlain !== '') {
            $tokenToSave = encryptApikey($apikeyPlain);
        }
        $stmt = $db->prepare("
            UPDATE settings SET
                base_url = ?,
                token = ?,
                tenant_name = ?,
                use_proxy = ?,
                wrap_in_body = ?,
                simplified_payload = ?,
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $baseUrl, $tokenToSave, $tenantName, $useProxy, $wrapInBody,
            $simplifiedPayload, $userId
        ]);
    } else {
        $tokenToSave = $apikeyPlain !== '' ? encryptApikey($apikeyPlain) : '';
        $stmt = $db->prepare("
            INSERT INTO settings 
            (user_id, base_url, token, tenant_name, use_proxy, wrap_in_body, simplified_payload)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $baseUrl, $tokenToSave, $tenantName, $useProxy,
            $wrapInBody, $simplifiedPayload
        ]);
    }
    
    jsonSuccess(null, 'Configurações salvas com sucesso');
    
} else {
    jsonError('Método não permitido', 405);
}
