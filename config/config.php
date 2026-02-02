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

// Chave da API do Google Gemini
// IMPORTANTE: Configure sua chave aqui ou via variável de ambiente
define('GEMINI_API_KEY', getenv('GEMINI_API_KEY') ?: 'AIzaSyB-vO2vH6m6G9r7m7v8z2x1c5v4b3n2m1');

// Chave da API de Busca (Google Maps)
// IMPORTANTE: Configure sua chave aqui ou via variável de ambiente
define('SCRAPER_API_KEY', getenv('SCRAPER_API_KEY') ?: '0e510c30f65a8b3abfbfad5090d47f79');

// Chave para criptografia do apikey do Webhook (32 bytes para AES-256)
// IMPORTANTE: Em produção use variável de ambiente e valor forte
define('ENCRYPTION_KEY', getenv('ENCRYPTION_KEY') ?: hash('sha256', 'MapsProspector-Pro-Webhook-Key-2025', true));

// Configurações de CORS (se necessário)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Responde OPTIONS para CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
