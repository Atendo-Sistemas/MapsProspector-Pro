<?php
/**
 * API de Configuração da Plataforma SaaS (Super Admin)
 * Endpoint: /api/platform-config.php
 * Nome da empresa SaaS e valor avulso por crédito (usado em Solicitar créditos).
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
ob_clean();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $userId = requireAuth();
    $authUser = getAuthUser();
    $isSuperAdmin = ($authUser && strtolower((string)($authUser['profile'] ?? '')) === 'super_admin');
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    jsonError('Não autenticado.', 401);
}

if ($method === 'GET') {
    $saasCompanyName = getPlatformSetting($db, 'saas_company_name');
    $data = [
        'saasCompanyName' => $saasCompanyName !== null && $saasCompanyName !== '' ? trim((string) $saasCompanyName) : '',
    ];
    if ($isSuperAdmin) {
        $creditPriceAvulso = getPlatformSetting($db, 'credit_price_avulso');
        $data['creditPriceAvulso'] = ($creditPriceAvulso !== null && $creditPriceAvulso !== '') ? (float) str_replace(',', '.', trim($creditPriceAvulso)) : 0;
    }
    jsonSuccess($data);
    exit;
}

if ($method === 'POST' || $method === 'PUT') {
    if (!$isSuperAdmin) {
        jsonError('Apenas o administrador da plataforma pode alterar esta configuração.', 403);
    }
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $saasCompanyName = isset($input['saasCompanyName']) ? trim((string) $input['saasCompanyName']) : '';
    $creditPriceAvulso = isset($input['creditPriceAvulso']) ? (float) str_replace(',', '.', (string) $input['creditPriceAvulso']) : 0;

    if ($creditPriceAvulso < 0) {
        $creditPriceAvulso = 0;
    }

    try {
        setPlatformSetting($db, 'saas_company_name', $saasCompanyName);
        setPlatformSetting($db, 'credit_price_avulso', (string) $creditPriceAvulso);
    } catch (PDOException $e) {
        error_log("platform-config save: " . $e->getMessage());
        jsonError('Erro ao salvar configurações. Verifique se a tabela platform_settings existe.', 500);
    }

    jsonSuccess([
        'saasCompanyName' => $saasCompanyName,
        'creditPriceAvulso' => $creditPriceAvulso,
    ], 'Configurações da empresa SaaS salvas com sucesso.');
    exit;
}

jsonError('Método não permitido', 405);
