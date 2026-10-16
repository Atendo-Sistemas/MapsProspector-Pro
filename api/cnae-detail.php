<?php
/**
 * cnae-detail.php — Proxy PHP para detalhes de um CNAE
 * 
 * Colocá-lo em: /caminho/do/seu/site/api/cnae-detail.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ============================================================
// CONFIGURAÇÃO — ALTERE PARA O ENDEREÇO DA SUA API CNAE
// ============================================================
$CNAE_API_URL = 'http://localhost:5000/api/cnae';

// ============================================================

$codigo = isset($_GET['codigo']) ? trim($_GET['codigo']) : '';

if (empty($codigo) || strlen($codigo) < 6) {
    echo json_encode([
        'success' => false,
        'error' => 'Código CNAE inválido'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Limpar código (remover pontos, traços)
$codigoLimpo = preg_replace('/[^0-9]/', '', $codigo);

$apiUrl = $CNAE_API_URL . '/' . $codigoLimpo;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'User-Agent: MapsProspector/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false || $httpCode !== 200) {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao consultar API CNAE'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$data = json_decode($response, true);

if ($data && isset($data['codigo_cnae'])) {
    echo json_encode([
        'success' => true,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} else {
    echo json_encode([
        'success' => false,
        'error' => 'CNAE não encontrado'
    ], JSON_UNESCAPED_UNICODE);
}
