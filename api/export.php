<?php
/**
 * API de Exportação para CRM (Com suporte a Webhook por Empresa/Tenant)
 * Endpoint: /api/export.php
 */

// Previne qualquer output antes do JSON
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Limpa o output buffer
ob_clean();

$userId = requireAuth();
$authUser = getAuthUser();
$db = Database::getInstance()->getConnection();

$tenantId = (int)($authUser['tenant_id'] ?? $authUser['tenantId'] ?? 0);
$profile = strtolower(trim((string)($authUser['profile'] ?? $authUser['role'] ?? $authUser['user_type'] ?? '')));
$isSuperAdmin = in_array(str_replace(['-', ' '], '_', $profile), ['super_admin', 'superadmin'], true);

$input = json_decode(file_get_contents('php://input'), true);
$leadIdStr = $input['leadId'] ?? '';

// Extrai ID numérico do formato "lead-123" ou apenas número
$leadId = null;
if (preg_match('/lead-(\d+)/', $leadIdStr, $matches)) {
    $leadId = intval($matches[1]);
} else {
    $leadId = intval($leadIdStr);
}

// Lógica de busca do lead considerando o isolamento por empresa (Tenant)
$isCompanyAdmin = in_array($profile, ['admin', 'supervisor', 'manager', 'gerente'], true) || $isSuperAdmin;

if (!$leadId) {
    $leadName = $input['leadName'] ?? '';
    $leadAddress = $input['leadAddress'] ?? '';
    
    if ($leadName && $leadAddress) {
        if ($isCompanyAdmin && $tenantId > 0) {
            $stmt = $db->prepare("
                SELECT l.*, sh.user_id 
                FROM leads l
                INNER JOIN search_history sh ON sh.id = l.search_history_id
                INNER JOIN users u ON u.id = sh.user_id
                WHERE l.name = ? AND l.address = ? AND u.tenant_id = ?
                ORDER BY l.id DESC
                LIMIT 1
            ");
            $stmt->execute([$leadName, $leadAddress, $tenantId]);
        } else {
            $stmt = $db->prepare("
                SELECT l.*, sh.user_id 
                FROM leads l
                INNER JOIN search_history sh ON sh.id = l.search_history_id
                WHERE l.name = ? AND l.address = ? AND sh.user_id = ?
                ORDER BY l.id DESC
                LIMIT 1
            ");
            $stmt->execute([$leadName, $leadAddress, $userId]);
        }
        $lead = $stmt->fetch();
    } else {
        jsonError('ID do lead não informado');
    }
} else {
    if ($isCompanyAdmin && $tenantId > 0) {
        $stmt = $db->prepare("
            SELECT l.*, sh.user_id 
            FROM leads l
            INNER JOIN search_history sh ON sh.id = l.search_history_id
            INNER JOIN users u ON u.id = sh.user_id
            WHERE l.id = ? AND u.tenant_id = ?
        ");
        $stmt->execute([$leadId, $tenantId]);
    } else {
        $stmt = $db->prepare("
            SELECT l.*, sh.user_id 
            FROM leads l
            INNER JOIN search_history sh ON sh.id = l.search_history_id
            WHERE l.id = ? AND sh.user_id = ?
        ");
        $stmt->execute([$leadId, $userId]);
    }
    $lead = $stmt->fetch();
}

if (!$lead) {
    jsonError('Lead não encontrado', 404);
}

// Resolve o destino na mesma ordem da exportação em lote. O endpoint antigo
// consultava apenas settings.base_url e, por isso, ignorava o webhook geral
// configurado pelo super administrador e o webhook pessoal salvo no painel.
$companyWebhookUrl = '';
if ($tenantId > 0) {
    $stmtT = $db->prepare("SELECT webhook_url FROM tenants WHERE id = ? LIMIT 1");
    $stmtT->execute([$tenantId]);
    $rowT = $stmtT->fetch(PDO::FETCH_ASSOC);
    $companyWebhookUrl = trim((string)($rowT['webhook_url'] ?? ''));
}

$personalWebhookUrl = '';
$personalToken = '';
$stmtS = $db->prepare("SELECT base_url, token FROM settings WHERE user_id = ? LIMIT 1");
$stmtS->execute([(int)$userId]);
$config = $stmtS->fetch(PDO::FETCH_ASSOC);
if ($config) {
    $personalWebhookUrl = trim((string)($config['base_url'] ?? ''));
    $personalToken = decryptApikey($config['token'] ?? '');
}

$platformWebhookUrl = '';
if (function_exists('getPlatformSetting')) {
    $platformWebhookUrl = trim((string)(getPlatformSetting($db, 'webhook_default_url') ?? ''));
}

if ($isSuperAdmin && $tenantId <= 0) {
    // A conta da plataforma não possui tenant próprio: usa o webhook geral.
    $webhookUrl = $platformWebhookUrl !== '' ? $platformWebhookUrl : $personalWebhookUrl;
} else {
    $webhookUrl = $personalWebhookUrl !== ''
        ? $personalWebhookUrl
        : ($companyWebhookUrl !== '' ? $companyWebhookUrl : $platformWebhookUrl);
}

if (empty($webhookUrl)) {
    jsonError('URL do CRM / Webhook não configurada para esta empresa. Configure nas Configurações.', 400);
}

$targetUrl = validateWebhookUrl($webhookUrl);
if ($targetUrl === null) {
    jsonError('URL do CRM configurada é inválida ou não permitida por segurança. Configure uma URL HTTPS válida nas Configurações.', 400);
}

// Prepara dados do contato
$contactData = buildContactDataFromLead($lead);
if ($contactData === null) {
    jsonError('O contato não possui um número de telefone válido.');
}

$payload = $contactData;

// Headers
$headers = ['Content-Type: application/json'];

// Busca token/apikey se existir na configuração pessoal.
$apikeyValue = $personalToken;
if ($apikeyValue !== '') {
    $headers[] = 'apikey: ' . trim($apikeyValue);
}

// Envia para CRM
$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    jsonError("Erro de Conexão: $error", 500);
}

if ($httpCode !== 200 && $httpCode !== 201) {
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['message'] ?? $errorData['error'] ?? $response;
    
    if ($httpCode === 400) {
        jsonError("Erro 400 (Dados Inválidos): $errorMsg", 400);
    }
    
    jsonError("CRM ($httpCode): $errorMsg", $httpCode);
}

// Marca como exportado
$leadDbId = $lead['id'];
$stmt = $db->prepare("UPDATE leads SET exported = 1, exported_at = NOW() WHERE id = ?");
$stmt->execute([$leadDbId]);

jsonSuccess(null, 'Enviado com sucesso para o CRM!');
