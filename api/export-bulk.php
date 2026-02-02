<?php
/**
 * API de Exportação em Lote para Webhook
 * Endpoint: /api/export-bulk.php
 * Recebe uma lista de leadIds e envia todos os leads em um único request ao webhook.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();

$userId = requireAuth();
$db = Database::getInstance()->getConnection();

$input = json_decode(file_get_contents('php://input'), true);
$leadIds = $input['leadIds'] ?? [];

if (!is_array($leadIds) || empty($leadIds)) {
    jsonError('Informe leadIds (array de IDs dos leads).', 400);
}

// Configurações
$stmt = $db->prepare("SELECT * FROM settings WHERE user_id = ?");
$stmt->execute([$userId]);
$config = $stmt->fetch();

if (!$config || empty($config['base_url'])) {
    jsonError('URL do Webhook não configurada. Configure nas Configurações.', 400);
}


// Extrai IDs numéricos
$numericIds = [];
foreach ($leadIds as $id) {
    if (preg_match('/lead-(\d+)/', (string)$id, $m)) {
        $numericIds[] = (int)$m[1];
    } elseif (is_numeric($id)) {
        $numericIds[] = (int)$id;
    }
}

if (empty($numericIds)) {
    jsonError('Nenhum ID de lead válido.', 400);
}

$placeholders = implode(',', array_fill(0, count($numericIds), '?'));
$stmt = $db->prepare("
    SELECT l.* 
    FROM leads l
    INNER JOIN search_history sh ON sh.id = l.search_history_id
    WHERE l.id IN ($placeholders) AND sh.user_id = ?
");
$stmt->execute(array_merge($numericIds, [$userId]));
$leads = $stmt->fetchAll();

$contacts = [];
$skipped = 0;
foreach ($leads as $lead) {
    $contact = buildContactDataFromLead($lead);
    if ($contact !== null) {
        $contacts[] = $contact;
    } else {
        $skipped++;
    }
}

if (empty($contacts)) {
    jsonError('Nenhum lead com telefone válido para enviar.', 400);
}

$targetUrl = rtrim($config['base_url'], '/');

$payload = ['leads' => $contacts];

$headers = ['Content-Type: application/json'];
$apikeyValue = decryptApikey($config['token'] ?? '');
if ($apikeyValue !== '') {
    $headers[] = 'apikey: ' . trim($apikeyValue);
}

$ch = curl_init($targetUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 60
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
    jsonError("Webhook ($httpCode): $errorMsg", $httpCode);
}

// Marca leads como exportados
foreach ($leads as $lead) {
    $stmt = $db->prepare("UPDATE leads SET exported = 1, exported_at = NOW() WHERE id = ?");
    $stmt->execute([$lead['id']]);
}

$message = count($contacts) . ' lead(s) enviado(s) para o Webhook.';
if ($skipped > 0) {
    $message .= ' ' . $skipped . ' ignorado(s) (sem telefone válido).';
}
jsonSuccess(['sent' => count($contacts), 'skipped' => $skipped], $message);
