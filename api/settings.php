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
$isSuperAdmin = ($authUser && strtolower((string)($authUser['profile'] ?? '')) === 'super_admin');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Chave da API de busca: apenas super_admin vê o valor; demais recebem apenas se está configurada
    $platformScraperKey = getPlatformSetting($db, 'scraper_api_key');
    $platformScraperKey = $platformScraperKey === null ? '' : (string) $platformScraperKey;
    $scraperApiKeyConfigured = trim($platformScraperKey) !== '';
    
    // Chave da API de IA (OpenRouter)
    $platformOpenRouterKey = getPlatformSetting($db, 'openrouter_api_key');
    $platformOpenRouterKey = $platformOpenRouterKey === null ? '' : (string) $platformOpenRouterKey;
    $openrouterApiKeyConfigured = trim($platformOpenRouterKey) !== '';

    // Modelo de IA selecionado (OpenRouter)
    $platformIaModel = getPlatformSetting($db, 'ia_model');
    $iaModel = $platformIaModel !== null ? (string) $platformIaModel : 'google/gemini-2.0-flash-001';
    
    // Modelo de IA fallback (OpenRouter)
    $platformIaModelFallback = getPlatformSetting($db, 'ia_model_fallback');
    $iaModelFallback = $platformIaModelFallback !== null ? (string) $platformIaModelFallback : '';

    // Busca configurações do usuário (webhook, token, etc.)
    $stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settings = $stmt->fetch();
    $settings = $settings ?: [];
    
    $base = [
        'baseUrl' => $settings['base_url'] ?? '',
        'token' => '',  // nunca enviado em texto; front usa campo para valor do header apikey
        'tenantName' => $settings['tenant_name'] ?? 'Nome da empresa SaaS',
        'scraperApiKeyConfigured' => $scraperApiKeyConfigured,
        'openrouterApiKeyConfigured' => $openrouterApiKeyConfigured,
        'iaModel' => $iaModel,
        'iaModelFallback' => $iaModelFallback
    ];
    if ($isSuperAdmin) {
        $base['scraperApiKey'] = $platformScraperKey ?? '';
        $base['openrouterApiKey'] = $platformOpenRouterKey ?? '';
    } else {
        $base['scraperApiKey'] = '';  // demais não veem a chave
        $base['openrouterApiKey'] = '';
    }
    jsonSuccess($base);
    
} elseif ($method === 'POST' || $method === 'PUT') {
    // Salva configurações
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $baseUrlRaw = trim($input['baseUrl'] ?? '');
    $baseUrl = $baseUrlRaw !== '' ? validateWebhookUrl($baseUrlRaw) : '';
    if ($baseUrlRaw !== '' && $baseUrl === null) {
        jsonError('URL do Webhook/CRM inválida. Use apenas HTTPS (ou HTTP em ambiente controlado) e evite IPs internos.', 400);
    }
    $baseUrl = $baseUrl ?? '';
    $apikeyPlain = trim($input['token'] ?? '');
    $tenantName = sanitizeInput($input['tenantName'] ?? 'Nome da empresa SaaS');

    // Chave da API de busca: apenas super_admin pode alterar; salva em platform_settings (toda a plataforma usa)
    if ($isSuperAdmin && array_key_exists('scraperApiKey', $input)) {
        $scraperApiKey = sanitizeInput($input['scraperApiKey'] ?? '');
        try {
            setPlatformSetting($db, 'scraper_api_key', $scraperApiKey);
        } catch (PDOException $e) {
            error_log("settings.php setPlatformSetting: " . $e->getMessage());
            jsonError('Não foi possível salvar a chave da API de busca. Execute o script database_migration_platform_settings.sql no banco de dados.', 500);
        }
    }
    
    // Chave da API de IA (OpenRouter): apenas super_admin pode alterar
    if ($isSuperAdmin && array_key_exists('openrouterApiKey', $input)) {
        $openrouterApiKey = sanitizeInput($input['openrouterApiKey'] ?? '');
        try {
            setPlatformSetting($db, 'openrouter_api_key', $openrouterApiKey);
        } catch (PDOException $e) {
            error_log("settings.php setPlatformSetting openrouter: " . $e->getMessage());
            jsonError('Não foi possível salvar a chave da API de IA.', 500);
        }
    }

    // Modelo de IA: apenas super_admin pode alterar
    if ($isSuperAdmin && array_key_exists('iaModel', $input)) {
        $iaModel = sanitizeInput($input['iaModel'] ?? 'google/gemini-2.0-flash-001');
        if (empty(trim($iaModel))) {
            $iaModel = 'google/gemini-2.0-flash-001';
        }
        try {
            setPlatformSetting($db, 'ia_model', $iaModel);
        } catch (PDOException $e) {
            error_log("settings.php setPlatformSetting ia_model: " . $e->getMessage());
            jsonError('Não foi possível salvar o modelo de IA.', 500);
        }
    }
    
    // Modelo de IA fallback: apenas super_admin pode alterar
    if ($isSuperAdmin && array_key_exists('iaModelFallback', $input)) {
        $iaModelFallback = sanitizeInput($input['iaModelFallback'] ?? '');
        try {
            setPlatformSetting($db, 'ia_model_fallback', $iaModelFallback);
        } catch (PDOException $e) {
            error_log("settings.php setPlatformSetting ia_model_fallback: " . $e->getMessage());
            jsonError('Não foi possível salvar o modelo fallback de IA.', 500);
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
                updated_at = NOW()
            WHERE user_id = ?
        ");
        $stmt->execute([
            $baseUrl, $tokenToSave, $tenantName, $userId
        ]);
    } else {
        $tokenToSave = $apikeyPlain !== '' ? encryptApikey($apikeyPlain) : '';
        $stmt = $db->prepare("
            INSERT INTO settings 
            (user_id, base_url, token, tenant_name)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $userId, $baseUrl, $tokenToSave, $tenantName
        ]);
    }
    
    jsonSuccess(null, 'Configurações salvas com sucesso');
    
} else {
    jsonError('Método não permitido', 405);
}
