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

// Prepara dados do contato (reutiliza helper usado no bulk)
$contactData = buildContactDataFromLead($lead);
if ($contactData === null) {
    jsonError('O contato não possui um número de telefone válido.');
}

// URL do Webhook: usada exatamente como configurada (n8n, Atendo etc. — não acrescentar /createcontact)
$targetUrl = rtrim($config['base_url'], '/');

// Payload enviado diretamente (sem encapsulamento em body)
$payload = $contactData;

// Headers (Authentication Header: nome fixo "apikey", valor descriptografado)
$headers = ['Content-Type: application/json'];
$apikeyValue = decryptApikey($config['token'] ?? '');
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
