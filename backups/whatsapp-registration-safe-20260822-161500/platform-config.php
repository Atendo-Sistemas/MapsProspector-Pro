<?php
/**
 * API de Configuração Global da Plataforma (Super Admin)
 * Endpoint: /api/platform-config.php
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
ob_clean();

try {
    $userId = requireAuth();
    $authUser = getAuthUser();
    $db = Database::getInstance()->getConnection();

    $profile = strtolower((string)($authUser['profile'] ?? ''));

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $profile !== 'super_admin') {
        jsonError('Acesso restrito ao administrador global.', 403);
    }

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $saasCompanyName = getPlatformSetting($db, 'saas_company_name') ?? 'Maps Prospector';
        $creditPriceAvulso = getPlatformSetting($db, 'credit_price_avulso') ?? 0.00;
        
        $whatsappApiUrl = getPlatformSetting($db, 'whatsapp_api_url') ?? '';
        $whatsappApiToken = getPlatformSetting($db, 'whatsapp_api_token') ?? '';
        $cnpjApiKey = getPlatformSetting($db, 'cnpj_api_key') ?? '';
        $cnpjApiUrl = getPlatformSetting($db, 'cnpj_api_url') ?? 'https://publica.cnpj.ws/cnpj/{cnpj}';

        // 💡 InfinitePay Handle com fallback do seu usuário ivan-jacomassi
        $infinitepayHandle = getPlatformSetting($db, 'infinitepay_handle');
        if (empty(trim((string)$infinitepayHandle))) {
            $infinitepayHandle = 'ivan-jacomassi';
        }
        $infinitepayApiKey = getPlatformSetting($db, 'infinitepay_api_key') ?? '';

        $smtpHost = getPlatformSetting($db, 'smtp_host') ?? '';
        $smtpPort = getPlatformSetting($db, 'smtp_port') ?? '587';
        $smtpUser = getPlatformSetting($db, 'smtp_user') ?? '';
        $smtpPass = getPlatformSetting($db, 'smtp_pass') ?? '';
        $smtpSecure = getPlatformSetting($db, 'smtp_secure') ?? 'tls';
        $smtpFromEmail = getPlatformSetting($db, 'smtp_from_email') ?? '';
        $smtpFromName = getPlatformSetting($db, 'smtp_from_name') ?? '';

        jsonSuccess([
            'saasCompanyName' => $saasCompanyName,
            'creditPriceAvulso' => (float) $creditPriceAvulso,
            'whatsappApiUrl' => $whatsappApiUrl,
            'whatsappApiToken' => $whatsappApiToken,
            'cnpjApiKey' => $cnpjApiKey,
            'cnpjApiKeyConfigured' => trim($cnpjApiKey) !== '',
            'cnpjApiUrl' => $cnpjApiUrl,
            'infinitepayHandle' => $infinitepayHandle,
            'infinitepayApiKey' => $infinitepayApiKey,
            'smtpHost' => $smtpHost,
            'smtpPort' => $smtpPort,
            'smtpUser' => $smtpUser,
            'smtpPass' => $smtpPass,
            'smtpSecure' => $smtpSecure,
            'smtpFromEmail' => $smtpFromEmail,
            'smtpFromName' => $smtpFromName
        ]);

    } elseif ($method === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!is_array($input)) {
            jsonError('Dados não fornecidos', 400);
        }

        if (array_key_exists('saasCompanyName', $input)) setPlatformSetting($db, 'saas_company_name', sanitizeInput($input['saasCompanyName']));
        if (array_key_exists('creditPriceAvulso', $input)) setPlatformSetting($db, 'credit_price_avulso', (float) $input['creditPriceAvulso']);
        if (array_key_exists('whatsappApiUrl', $input)) setPlatformSetting($db, 'whatsapp_api_url', trim($input['whatsappApiUrl']));
        if (array_key_exists('whatsappApiToken', $input)) setPlatformSetting($db, 'whatsapp_api_token', trim($input['whatsappApiToken']));
        if (array_key_exists('cnpjApiKey', $input)) setPlatformSetting($db, 'cnpj_api_key', trim($input['cnpjApiKey']));
        if (array_key_exists('cnpjApiUrl', $input)) setPlatformSetting($db, 'cnpj_api_url', trim($input['cnpjApiUrl']));
        
        // 💡 Grava a InfinitePay
        if (array_key_exists('infinitepayHandle', $input)) setPlatformSetting($db, 'infinitepay_handle', trim($input['infinitepayHandle']));
        if (array_key_exists('infinitepayApiKey', $input)) setPlatformSetting($db, 'infinitepay_api_key', trim($input['infinitepayApiKey']));

        if (array_key_exists('smtpHost', $input)) setPlatformSetting($db, 'smtp_host', trim($input['smtpHost']));
        if (array_key_exists('smtpPort', $input)) setPlatformSetting($db, 'smtp_port', trim($input['smtpPort']));
        if (array_key_exists('smtpUser', $input)) setPlatformSetting($db, 'smtp_user', trim($input['smtpUser']));
        if (array_key_exists('smtpPass', $input) && $input['smtpPass'] !== '') setPlatformSetting($db, 'smtp_pass', trim($input['smtpPass']));
        if (array_key_exists('smtpSecure', $input)) setPlatformSetting($db, 'smtp_secure', trim($input['smtpSecure']));
        if (array_key_exists('smtpFromEmail', $input)) setPlatformSetting($db, 'smtp_from_email', trim($input['smtpFromEmail']));
        if (array_key_exists('smtpFromName', $input)) setPlatformSetting($db, 'smtp_from_name', trim($input['smtpFromName']));

        jsonSuccess(null, 'Configurações globais salvas com sucesso!');
    }

} catch (Throwable $e) {
    ob_clean();
    error_log("platform-config.php error: " . $e->getMessage());
    jsonError('Erro interno: ' . $e->getMessage(), 500);
}