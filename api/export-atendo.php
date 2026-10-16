<?php
/**
 * API de Exportação Direta para o Atendo (CRM / Canal de Atendimento)
 * Endpoint: /api/export-atendo.php
 *
 * POST { leadId: 'lead-42' }
 * Resposta: { success: true, message: 'Enviado com sucesso para o Atendo!' }
 */

define('BASE_PATH', __DIR__ . '/../');
require_once BASE_PATH . 'config/config.php';
require_once BASE_PATH . 'config/database.php';
require_once BASE_PATH . 'includes/functions.php';

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'error' => 'Falha fatal ao exportar para o Atendo.'], JSON_UNESCAPED_UNICODE);
});

function atendoJsonResponse(bool $success, $data = null, string $message = '', int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    $body = ['success' => $success];
    if ($data !== null) {
        $body['data'] = $data;
    }
    if ($message !== '') {
        $body[$success ? 'message' : 'error'] = $message;
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Somente usuários autenticados exportam.
if (!requireAuth()) {
    atendoJsonResponse(false, null, 'Sessão expirada. Faça login novamente.', 401);
}
$currentUser = getAuthUser();
if (!$currentUser) {
    atendoJsonResponse(false, null, 'Sessão expirada. Faça login novamente.', 401);
}

$input = json_decode(file_get_contents('php://input'), true);
$leadIdStr = (string)($input['leadId'] ?? '');

// Extrai ID numérico do lead (aceita 'lead-42' ou apenas '42').
$leadId = null;
if (preg_match('/lead-(\d+)/', $leadIdStr, $matches)) {
    $leadId = (int)$matches[1];
} elseif (ctype_digit($leadIdStr)) {
    $leadId = (int)$leadIdStr;
}

if (!$leadId) {
    atendoJsonResponse(false, null, 'ID do lead não informado ou inválido.', 400);
}

$db = Database::getInstance()->getConnection();
$tenantId = $currentUser['tenant_id'] ?? null;

// Busca o lead no banco, garantindo que pertence ao tenant do usuário.
$stmt = $db->prepare("SELECT * FROM leads WHERE id = ?" . ($tenantId ? " AND tenant_id = ?" : ""));
if ($tenantId) {
    $stmt->execute([$leadId, $tenantId]);
} else {
    $stmt->execute([$leadId]);
}
$lead = $stmt->fetch();

if (!$lead) {
    atendoJsonResponse(false, null, 'Lead não encontrado.', 404);
}

// Busca as configurações globais da plataforma (URL base e Token JWT da API Atendo).
$atendoUrl = getPlatformSetting($db, 'whatsapp_api_url') ?? '';
$atendoToken = getPlatformSetting($db, 'whatsapp_api_token') ?? '';

if (empty($atendoUrl) || empty($atendoToken)) {
    atendoJsonResponse(false, null, 'A API do Atendo não está configurada nas configurações globais da plataforma.', 400);
}

$targetUrl = rtrim($atendoUrl, '/') . '/createcontact';

// Formata o telefone (remove caracteres não numéricos).
$phoneClean = preg_replace('/[^0-9]/', '', $lead['phone'] ?? '');
if (empty($phoneClean)) {
    atendoJsonResponse(false, null, 'Este lead não possui um número de telefone válido.');
}

// Payload compatível com o endpoint /createcontact do Atendo.
$payload = [
    'name' => $lead['name'] ?? 'Cliente Prospect',
    'number' => '55' . $phoneClean, // Garante o DDI 55 do Brasil.
    'email' => $lead['email'] ?? '',
    'commentary' => 'Capturado via Maps Prospector - Endereço: ' . ($lead['address'] ?? ''),
    'extraInfo' => [
        [
            'name' => 'Empresa / CNPJ',
            'value' => trim(($lead['cnpj'] ?? '') . ' - ' . ($lead['partners'] ?? '')),
        ],
    ],
];

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . trim($atendoToken),
    ],
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    atendoJsonResponse(false, null, 'Erro de conexão com o Atendo: ' . $error, 500);
}

if ($httpCode !== 200 && $httpCode !== 201) {
    $errorData = json_decode($response, true);
    $errorMsg = $errorData['message'] ?? $errorData['error'] ?? $response;
    atendoJsonResponse(false, null, "Atendo (HTTP $httpCode): " . $errorMsg, $httpCode);
}

// Marca o lead como exportado.
$stmtUp = $db->prepare("UPDATE leads SET exported = 1, exported_at = NOW() WHERE id = ?");
$stmtUp->execute([$leadId]);

atendoJsonResponse(true, null, 'Enviado com sucesso para o Atendo!');
