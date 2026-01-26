<?php
/**
 * API de Exportação para CRM
 * Endpoint: /api/export.php
 */

// Previne qualquer output antes do JSON
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Limpa qualquer output buffer
ob_clean();

$userId = requireAuth();
$db = Database::getInstance()->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$leadIdStr = $input['leadId'] ?? '';

// Extrai ID numérico do formato "lead-123" ou apenas número
$leadId = null;
if (preg_match('/lead-(\d+)/', $leadIdStr, $matches)) {
    // Formato "lead-123" - extrai o número
    $leadId = intval($matches[1]);
} else {
    // Tenta como número direto
    $leadId = intval($leadIdStr);
}

if (!$leadId) {
    // Fallback: busca por nome e endereço
    $leadName = $input['leadName'] ?? '';
    $leadAddress = $input['leadAddress'] ?? '';
    
    if ($leadName && $leadAddress) {
        $stmt = $db->prepare("
            SELECT l.*, sh.user_id 
            FROM leads l
            INNER JOIN search_history sh ON sh.id = l.search_history_id
            WHERE l.name = ? AND l.address = ? AND sh.user_id = ?
            ORDER BY l.id DESC
            LIMIT 1
        ");
        $stmt->execute([$leadName, $leadAddress, $userId]);
        $lead = $stmt->fetch();
    } else {
        jsonError('ID do lead não informado');
    }
} else {
    // Busca pelo ID numérico
    $stmt = $db->prepare("
        SELECT l.*, sh.user_id 
        FROM leads l
        INNER JOIN search_history sh ON sh.id = l.search_history_id
        WHERE l.id = ? AND sh.user_id = ?
    ");
    $stmt->execute([$leadId, $userId]);
    $lead = $stmt->fetch();
}

if (!$lead) {
    jsonError('Lead não encontrado', 404);
}

// Busca configurações
$stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
$stmt->execute([$userId]);
$config = $stmt->fetch();

if (!$config || empty($config['base_url'])) {
    jsonError('URL do CRM não configurada. Configure nas Configurações.', 400);
}

// Prepara dados do contato
$phone = formatPhoneNumber($lead['phone'] ?? '');
if (empty($phone)) {
    jsonError('O contato não possui um número de telefone válido.');
}

$contactData = [
    'name' => $lead['name'],
    'number' => $phone
];

if (!$config['simplified_payload']) {
    $contactData['email'] = $lead['email'] ?? '';
    $contactData['tag'] = $lead['tag'] ?? 'prospect_maps';
    
    $extraInfo = [];
    if (!empty($lead['address'])) {
        $extraInfo[] = ['name' => 'Endereço', 'value' => $lead['address']];
    }
    if (!empty($lead['cnpj'])) {
        $extraInfo[] = ['name' => 'CNPJ', 'value' => $lead['cnpj']];
    }
    if (!empty($lead['partners'])) {
        $extraInfo[] = ['name' => 'Sócios', 'value' => $lead['partners']];
    }
    if (!empty($lead['maps_uri'])) {
        $extraInfo[] = ['name' => 'Maps', 'value' => $lead['maps_uri']];
    }
    if (!empty($lead['website'])) {
        $extraInfo[] = ['name' => 'Site', 'value' => $lead['website']];
    }
    
    if (!empty($extraInfo)) {
        $contactData['extraInfo'] = $extraInfo;
    }
    
    $contactData['commentary'] = "Lead capturado via Maps. Endereço: " . ($lead['address'] ?? '');
}

// Limpa dados
$contactData = deepClean($contactData);

// Remove campos que causam erro 400
unset($contactData['ticketId'], $contactData['contactId'], $contactData['id'], $contactData['messageId']);

// Prepara URL
$targetUrl = $config['base_url'];
$isDirectWebhook = strpos($targetUrl, '/external/') !== false || 
                   strpos($targetUrl, '/webhook/') !== false || 
                   strpos($targetUrl, 'n8n') !== false;

if (!$isDirectWebhook) {
    $targetUrl = rtrim($targetUrl, '/') . '/createcontact';
}

if ($config['use_proxy']) {
    $targetUrl = 'https://corsproxy.io/?' . urlencode($targetUrl);
}

// Prepara payload
$payload = $config['wrap_in_body'] ? ['body' => $contactData] : $contactData;

// Headers
$headers = ['Content-Type: application/json'];
if (!empty($config['token'])) {
    $token = trim($config['token']);
    if (strpos($token, 'Bearer ') === 0) {
        $headers[] = "Authorization: $token";
    } else {
        $headers[] = "Authorization: Bearer $token";
    }
    $headers[] = "apikey: $token";
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
        jsonError("Erro 400 (Dados Inválidos): $errorMsg. Tente ativar o 'Modo Estrito' nas configurações.", 400);
    }
    
    jsonError("CRM ($httpCode): $errorMsg", $httpCode);
}

// Marca como exportado
$leadDbId = $lead['id'];
$stmt = $db->prepare("UPDATE leads SET exported = 1, exported_at = NOW() WHERE id = ?");
$stmt->execute([$leadDbId]);

jsonSuccess(null, 'Enviado com sucesso para o CRM!');
