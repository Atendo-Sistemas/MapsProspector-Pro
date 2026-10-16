<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function aiRouterConnectionJson(bool $success, $data = null, string $message = '', int $status = 200): never
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    $payload = ['success' => $success];
    if ($success) {
        $payload['data'] = $data;
        if ($message !== '') $payload['message'] = $message;
    } else {
        $payload['error'] = $message ?: 'Não foi possível processar a configuração do 9router.';
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function aiRouterConnectionMaskSecret(string $secret): string
{
    $secret = trim($secret);
    if ($secret === '') return '';
    if (strlen($secret) <= 8) return str_repeat("\u{2022}", 8);
    return str_repeat("\u{2022}", 8) . substr($secret, -4);
}

function aiRouterConnectionValidBaseUrl(string $url): bool
{
    if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) return false;
    $parts = parse_url($url);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($scheme !== 'https' || $host === '') return false;
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    }
    return !in_array($host, ['localhost', 'localhost.localdomain', 'metadata.google.internal'], true);
}

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../services/aiRouterClient.php';
    if (!function_exists('requireAuth') || !function_exists('getAuthUser')) {
        aiRouterConnectionJson(false, null, 'Dependências de autenticação indisponíveis.', 500);
    }
    requireAuth();
    $user = getAuthUser();
    if (strtolower((string) ($user['profile'] ?? '')) !== 'super_admin') {
        aiRouterConnectionJson(false, null, 'Acesso restrito ao administrador da plataforma.', 403);
    }
    $db = Database::getInstance()->getConnection();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $read = static function (string $key) use ($db): string {
        return function_exists('getPlatformSetting') ? trim((string) (getPlatformSetting($db, $key) ?? '')) : '';
    };
    $stored = $read('ai_router_api_key');
    $config = aiRouterReadConfig($db);

    if ($method === 'GET') {
        aiRouterConnectionJson(true, [
            'provider' => '9router',
            'baseUrl' => $config['base_url'],
            'model' => $config['model'],
            'enabled' => $config['enabled'],
            'configured' => $config['configured'],
            'apiKeyConfigured' => $stored !== '',
            'apiKeyMasked' => $config['api_key'] !== '' ? aiRouterConnectionMaskSecret($config['api_key']) : ($stored !== '' ? str_repeat("\u{2022}", 8) : ''),
            'status' => $read('ai_router_status') ?: ($config['configured'] ? 'configured' : 'not_configured'),
            'lastValidatedAt' => $read('ai_router_validated_at') ?: null,
        ]);
    }
    if ($method !== 'POST') aiRouterConnectionJson(false, null, 'Método não permitido.', 405);
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) aiRouterConnectionJson(false, null, 'Dados inválidos.', 400);
    $action = (string) ($input['action'] ?? 'save');

    if ($action === 'save') {
        $baseUrl = rtrim(trim((string) ($input['baseUrl'] ?? '')), '/');
    $model = trim((string) ($input['model'] ?? 'gemini/gemini-3-flash-preview')) ?: 'gemini/gemini-3-flash-preview';
        $token = trim((string) ($input['apiKey'] ?? ''));
        $enabled = !empty($input['enabled']);
        if (!aiRouterConnectionValidBaseUrl($baseUrl)) {
            aiRouterConnectionJson(false, null, 'Informe uma URL HTTPS pública válida, por exemplo: https://9router.atendo.log.br/v1', 422);
        }
        if ($token === '') {
            $token = function_exists('decryptApikey') ? trim((string) decryptApikey($stored)) : $stored;
        }
        if ($token === '') aiRouterConnectionJson(false, null, 'Informe o token do 9router para salvar.', 422);
        $encrypted = function_exists('encryptApikey') ? encryptApikey($token) : '';
        if ($encrypted === '') aiRouterConnectionJson(false, null, 'Não foi possível proteger o token do 9router.', 500);
        setPlatformSetting($db, 'ai_router_base_url', $baseUrl);
        setPlatformSetting($db, 'ai_router_api_key', $encrypted);
        setPlatformSetting($db, 'ai_router_model', $model);
        setPlatformSetting($db, 'ai_router_enabled', $enabled ? '1' : '0');
        setPlatformSetting($db, 'ai_router_status', 'configured');
        aiRouterConnectionJson(true, [
            'provider' => '9router', 'baseUrl' => $baseUrl, 'model' => $model,
            'enabled' => $enabled, 'configured' => true,
            'apiKeyConfigured' => true, 'apiKeyMasked' => aiRouterConnectionMaskSecret($token),
            'status' => 'configured', 'lastValidatedAt' => null,
        ], 'Configuração do 9router salva com segurança. Teste a conexão antes de usar.');
    }

    if ($action === 'test') {
        try {
            $config = aiRouterReadConfig($db);
            if (!$config['configured']) throw new RuntimeException('URL e token do 9router ainda não estão configurados.');
            $testResponse = aiRouterChatCompletion($config, [['role' => 'user', 'content' => 'Responda somente OK.']], 512, false);
            if (aiRouterMessageContent($testResponse) === '') throw new RuntimeException('O modelo não devolveu conteúdo.');
            $validatedAt = gmdate('Y-m-d H:i:s');
            setPlatformSetting($db, 'ai_router_status', 'connected');
            setPlatformSetting($db, 'ai_router_validated_at', $validatedAt);
            aiRouterConnectionJson(true, [
                'provider' => '9router', 'baseUrl' => $config['base_url'], 'model' => $config['model'],
                'enabled' => $config['enabled'], 'configured' => true, 'status' => 'connected',
                'lastValidatedAt' => $validatedAt,
            ], 'Conexão com o 9router confirmada pelo backend.');
        } catch (Throwable $error) {
            setPlatformSetting($db, 'ai_router_status', 'error');
            aiRouterConnectionJson(false, null, 'A conexão com o 9router não foi confirmada. Verifique URL, modelo e token.', 502);
        }
    }

    if ($action === 'toggle') {
        $enabled = !empty($input['enabled']);
        if ($config['base_url'] === '' || $config['api_key'] === '') aiRouterConnectionJson(false, null, 'Configure e salve o 9router antes de ativá-lo.', 422);
        setPlatformSetting($db, 'ai_router_enabled', $enabled ? '1' : '0');
        aiRouterConnectionJson(true, ['enabled' => $enabled], $enabled ? '9router ativado para a pesquisa de IA.' : '9router desativado; a pesquisa de IA usará o provedor legado.');
    }
    aiRouterConnectionJson(false, null, 'Ação de configuração não reconhecida.', 400);
} catch (Throwable $error) {
    error_log('ai-router-connection: falha controlada no endpoint.');
    aiRouterConnectionJson(false, null, 'Não foi possível processar a configuração do 9router.', 500);
}
