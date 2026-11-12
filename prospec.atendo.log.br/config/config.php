<?php
/**
 * Configurações Gerais do Sistema
 * MapsProspector Pro — compatível com XAMPP (local) e cPanel (produção).
 *
 * No cPanel não existe arquivo .env; as credenciais ficam nos
 * fallbacks do config/database.php e nas constantes abaixo.
 */

// Carrega variáveis do arquivo .env quando existir (apenas local/XAMPP).
// Nunca sobrescreve variáveis já injetadas pelo servidor (evita derrubar a sessão).
$envFile = __DIR__ . '/../.env';
if (is_file($envFile) && is_readable($envFile)) {
    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }
        if (strpos($line, '=') === false) {
            continue;
        }
        $key = trim(substr($line, 0, strpos($line, '=')));
        $value = trim(substr($line, strpos($line, '=') + 1));
        if ($key === '') {
            continue;
        }
        if (preg_match('/^["\'](.+)["\']\s*$/', $value, $m)) {
            $value = $m[1];
        }
        // Só define se ainda não existir (não sobrescreve variáveis injetadas pelo servidor)
        if (!isset($_ENV[$key])) {
            $_ENV[$key] = $value;
        }
        if (!isset($_SERVER[$key])) {
            $_SERVER[$key] = $value;
        }
    }
}

// Configurações de ambiente (podem vir do .env; fallback de produção)
define('ENVIRONMENT', getenv('ENVIRONMENT') ?: ($_ENV['ENVIRONMENT'] ?? 'production'));
define('BASE_URL', rtrim(getenv('BASE_URL') ?: ($_ENV['BASE_URL'] ?? 'https://prospec.atendo.log.br/'), '/') . '/');

// Configurações de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);

// cookie_secure só quando a requisição realmente é HTTPS (detecta Cloudflare via CF-Visitor)
$https = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    || (isset($_SERVER['HTTP_CF_VISITOR']) && strpos($_SERVER['HTTP_CF_VISITOR'], 'https') !== false);
ini_set('session.cookie_secure', $https ? '1' : '0');
ini_set('session.cookie_samesite', 'Lax');

// Evita o aviso "session_start() because a session is already active"
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro (desenvolvimento)
// IMPORTANTE: Não exibir erros na tela para APIs JSON
// Os erros serão logados, mas não exibidos no output
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desabilita exibição de erros na tela
ini_set('log_errors', 1); // Habilita log de erros
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // Define arquivo de log

// Chave da API de Busca — opcional no arranque; super admin cadastra no painel (Configurações)
$scraperKey = getenv('SCRAPER_API_KEY');
define('SCRAPER_API_KEY', $scraperKey !== false ? trim((string)$scraperKey) : '');

// Chave para criptografia do apikey do Webhook (32 bytes AES-256). Se não estiver no .env, gera e persiste em storage/
$encKeyRaw = getenv('ENCRYPTION_KEY');
$encKeyBinary = '';
if ($encKeyRaw !== false && trim((string)$encKeyRaw) !== '') {
    $encKeyRaw = trim((string)$encKeyRaw);
    $decoded = base64_decode($encKeyRaw, true) ?: @hex2bin($encKeyRaw);
    $encKeyBinary = ($decoded !== false && strlen($decoded) >= 32) ? substr($decoded, 0, 32) : hash('sha256', $encKeyRaw, true);
}
if ($encKeyBinary === '') {
    $storageDir = __DIR__ . '/../storage';
    $encFile = $storageDir . '/encryption_key';
    if (is_file($encFile) && is_readable($encFile)) {
        $stored = trim((string)file_get_contents($encFile));
        $decoded = base64_decode($stored, true);
        $encKeyBinary = ($decoded !== false && strlen($decoded) >= 32) ? substr($decoded, 0, 32) : '';
    }
    if ($encKeyBinary === '' && (!is_dir($storageDir) || is_writable($storageDir))) {
        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0750, true);
        }
        if (is_dir($storageDir) && is_writable($storageDir)) {
            $generated = random_bytes(32);
            if (@file_put_contents($encFile, base64_encode($generated), LOCK_EX) !== false) {
                $encKeyBinary = $generated;
            }
        }
    }
}
define('ENCRYPTION_KEY', $encKeyBinary ?: hash('sha256', 'MapsProspector-Pro-Webhook-Key-2025', true));

// Permitir exibição em iframe (outro domínio). Desative em produção se não precisar.
// Env ALLOW_IFRAME: '1' ou 'true' = qualquer site; lista de URLs = só esses domínios; '0' ou vazio = SAMEORIGIN
$allowIframeRaw = getenv('ALLOW_IFRAME');
$allowIframe = ($allowIframeRaw === false || $allowIframeRaw === '') ? true : $allowIframeRaw;
define('ALLOW_IFRAME', $allowIframe);

if ($allowIframe === '1' || $allowIframe === 'true' || $allowIframe === true) {
    header_remove('X-Frame-Options');
    header('Content-Security-Policy: frame-ancestors *');
} elseif (is_string($allowIframe) && trim($allowIframe) !== '' && $allowIframe !== '0') {
    $origins = preg_replace('/[^\w\-\.:\/\s]/', '', $allowIframe);
    header_remove('X-Frame-Options');
    header("Content-Security-Policy: frame-ancestors 'self' " . trim($origins));
} else {
    header('X-Frame-Options: SAMEORIGIN');
}

// Configurações de CORS — em produção defina origem específica via ALLOWED_ORIGIN
$allowedOrigin = getenv('ALLOWED_ORIGIN');
if (ENVIRONMENT === 'production' && !empty($allowedOrigin)) {
    header('Access-Control-Allow-Origin: ' . preg_replace('/[^\w\-\.:\/]/', '', $allowedOrigin));
} else {
    header('Access-Control-Allow-Origin: *');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responde OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
