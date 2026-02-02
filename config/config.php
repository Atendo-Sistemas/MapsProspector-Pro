<?php
/**
 * Configurações Gerais do Sistema
 * MapsProspector Pro - XAMPP
 */

// Configurações de ambiente
define('ENVIRONMENT', 'development'); // development | production
define('BASE_URL', 'http://localhost/MapsProspector-Pro/');

// Configurações de sessão
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Mude para 1 em produção com HTTPS
session_start();

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações de erro (desenvolvimento)
// IMPORTANTE: Não exibir erros na tela para APIs JSON
// Os erros serão logados, mas não exibidos no output
error_reporting(E_ALL);
ini_set('display_errors', 0); // Desabilita exibição de erros na tela
ini_set('log_errors', 1); // Habilita log de erros
ini_set('error_log', __DIR__ . '/../logs/php_errors.log'); // Define arquivo de log

// Chave da API do Google Gemini — use variável de ambiente; em produção não use fallback
$geminiKey = getenv('GEMINI_API_KEY');
if (ENVIRONMENT === 'production' && (empty($geminiKey) || $geminiKey === 'SUA_CHAVE_AQUI')) {
    throw new RuntimeException('GEMINI_API_KEY deve ser definida em variável de ambiente em produção.');
}
define('GEMINI_API_KEY', $geminiKey ?: (ENVIRONMENT === 'development' ? '' : ''));

// Chave da API de Busca (Google Maps) — use variável de ambiente; em produção não use fallback
$scraperKey = getenv('SCRAPER_API_KEY');
if (ENVIRONMENT === 'production' && empty(trim((string)$scraperKey))) {
    throw new RuntimeException('SCRAPER_API_KEY deve ser definida em variável de ambiente em produção.');
}
define('SCRAPER_API_KEY', $scraperKey ?: (ENVIRONMENT === 'development' ? '' : ''));

// Chave para criptografia do apikey do Webhook (32 bytes para AES-256) — em produção use env
$encKey = getenv('ENCRYPTION_KEY');
if (ENVIRONMENT === 'production' && empty($encKey)) {
    throw new RuntimeException('ENCRYPTION_KEY deve ser definida em variável de ambiente em produção.');
}
define('ENCRYPTION_KEY', $encKey ?: (ENVIRONMENT === 'development' ? hash('sha256', 'MapsProspector-Pro-Webhook-Key-2025', true) : ''));

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
