<?php
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function groqConnectionJson(bool $success, $data = null, string $message = '', int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    $payload = ['success' => $success];
    if ($success) {
        $payload['data'] = $data;
        if ($message !== '') $payload['message'] = $message;
    } else {
        $payload['error'] = $message ?: 'Não foi possível processar a configuração.';
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function groqConnectionMaskSecret(string $secret): string
{
    $secret = trim($secret);
    if ($secret === '') {
        return '';
    }
    if (strlen($secret) <= 8) {
        return str_repeat("\u{2022}", 8);
    }
    return str_repeat("\u{2022}", 8) . substr($secret, -4);
}

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../services/groqLinkedInProvider.php';

    if (!function_exists('requireAuth') || !function_exists('getAuthUser')) {
        groqConnectionJson(false, null, 'Dependências de autenticação indisponíveis.', 500);
    }
    requireAuth();
    $user = getAuthUser();
    $profile = strtolower((string) ($user['profile'] ?? ''));
    if ($profile !== 'super_admin') {
        groqConnectionJson(false, null, 'Acesso restrito ao administrador da plataforma.', 403);
    }
    $db = Database::getInstance()->getConnection();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $config = linkedinGroqConfig($db);
        $stored = function_exists('getPlatformSetting') ? trim((string) (getPlatformSetting($db, 'groq_api_key') ?? '')) : '';
        $validatedAt = function_exists('getPlatformSetting') ? trim((string) (getPlatformSetting($db, 'groq_validated_at') ?? '')) : '';
        $status = function_exists('getPlatformSetting') ? trim((string) (getPlatformSetting($db, 'groq_status') ?? '')) : '';
        $maskedKey = $config['api_key'] !== ''
            ? groqConnectionMaskSecret($config['api_key'])
            : ($stored !== '' ? str_repeat("\u{2022}", 8) : '');
        groqConnectionJson(true, [
            'provider' => 'Groq',
            'model' => $config['model'],
            'configured' => $stored !== '',
            'apiKeyConfigured' => $stored !== '',
            'apiKeyMasked' => $maskedKey,
            'status' => $status !== '' ? $status : ($stored !== '' ? 'configured' : 'not_configured'),
            'lastValidatedAt' => $validatedAt !== '' ? $validatedAt : null,
        ]);
    }

    if ($method !== 'POST') {
        groqConnectionJson(false, null, 'Método não permitido.', 405);
    }
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        groqConnectionJson(false, null, 'Dados inválidos.', 400);
    }
    $action = (string) ($input['action'] ?? 'save');

    if ($action === 'save') {
        $apiKey = trim((string) ($input['apiKey'] ?? ''));
        $model = trim((string) ($input['model'] ?? 'openai/gpt-oss-20b'));
        if ($model === '') $model = 'openai/gpt-oss-20b';
        if (!in_array($model, ['openai/gpt-oss-20b', 'openai/gpt-oss-120b'], true)) {
            groqConnectionJson(false, null, 'Modelo Groq não permitido.', 422);
        }
        if ($apiKey === '') {
            groqConnectionJson(false, null, 'Informe uma nova chave Groq para salvar.', 422);
        }
        $encrypted = function_exists('encryptApikey') ? encryptApikey($apiKey) : '';
        if ($encrypted === '') {
            groqConnectionJson(false, null, 'Não foi possível proteger a chave Groq.', 500);
        }
        setPlatformSetting($db, 'groq_api_key', $encrypted);
        setPlatformSetting($db, 'groq_model', $model);
        setPlatformSetting($db, 'groq_status', 'configured');
        groqConnectionJson(true, [
            'provider' => 'Groq',
            'model' => $model,
            'configured' => true,
            'apiKeyConfigured' => true,
            'apiKeyMasked' => groqConnectionMaskSecret($apiKey),
            'status' => 'configured',
            'lastValidatedAt' => null,
        ], 'Configuração Groq salva com segurança. Teste a conexão antes de usar a pesquisa.');
    }

    if ($action === 'saveModel') {
        $model = trim((string) ($input['model'] ?? 'openai/gpt-oss-20b'));
        if (!in_array($model, ['openai/gpt-oss-20b', 'openai/gpt-oss-120b'], true)) {
            groqConnectionJson(false, null, 'Modelo Groq não permitido.', 422);
        }
        setPlatformSetting($db, 'groq_model', $model);
        groqConnectionJson(true, ['model' => $model], 'Modelo Groq atualizado.');
    }

    if ($action === 'test') {
        try {
            linkedinGroqTestConnection($db);
            $validatedAt = gmdate('Y-m-d H:i:s');
            setPlatformSetting($db, 'groq_status', 'connected');
            setPlatformSetting($db, 'groq_validated_at', $validatedAt);
            $config = linkedinGroqConfig($db);
            groqConnectionJson(true, [
                'provider' => 'Groq',
                'model' => $config['model'],
                'configured' => true,
                'status' => 'connected',
                'lastValidatedAt' => $validatedAt,
            ], 'Conexão Groq confirmada pelo backend.');
        } catch (Throwable $error) {
            setPlatformSetting($db, 'groq_status', 'error');
            groqConnectionJson(false, null, 'A conexão Groq não foi confirmada. Verifique a chave e tente novamente.', 502);
        }
    }

    groqConnectionJson(false, null, 'Ação de configuração não reconhecida.', 400);
} catch (Throwable $error) {
    error_log('groq-connection: falha controlada no endpoint.');
    groqConnectionJson(false, null, 'Não foi possível processar a configuração Groq.', 500);
}
