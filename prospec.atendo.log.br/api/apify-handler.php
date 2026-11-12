<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';


// Se for um GET com 'check', ele apenas verifica o status
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['check'])) {
    $runId = $_GET['check'];
    $apifyToken = getPlatformSetting($db, 'scraper_api_key');
    
    // Consulta status da Run na API da Apify
    $ch = curl_init("https://api.cpify.com/v2/acts/runs/{$runId}?token={$apifyToken}");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    
    if ($data['data']['status'] === 'SUCCEEDED') {
        // Busca os resultados (dataset)
        $datasetId = $data['data']['defaultDatasetId'];
        $resItems = file_get_contents("https://api.apify.com/v2/datasets/{$datasetId}/items?token={$apifyToken}");
        echo json_encode(['status' => 'SUCCEEDED', 'results' => json_decode($resItems)]);
    } else {
        echo json_encode(['status' => $data['data']['status']]);
    }
    exit;
}    
// Pegar chave global do Super Admin que você já configurou no sistema
$db = Database::getInstance()->getConnection();
$apifyToken = getPlatformSetting($db, 'scraper_api_key'); 

$input = json_decode(file_get_contents('php://input'), true);
$cnae = $input['cnae'] ?? '';
$uf = $input['uf'] ?? '';
$city = $input['city'] ?? '';

if (empty($cnae)) {
    die(json_encode(['success' => false, 'error' => 'CNAE não identificado.']));
}

// Payload de envio para a Apify
$actorId = 'geodetic_marigold/br-company-finder';
$payload = [
    "cnae" => $cnae,
    "uf" => $uf,
    "municipio" => $city,
    "situacao_cadastral" => "Ativa",
    "max_results" => 20
];

// Disparar o Actor
$ch = curl_init("https://api.apify.com/v2/acts/" . urlencode($actorId) . "/runs?token=" . $apifyToken);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

// Retorna o ID da execução para o front-end monitorar
if (isset($data['data']['id'])) {
    echo json_encode(['success' => true, 'runId' => $data['data']['id']]);
} else {
    echo json_encode(['success' => false, 'error' => 'Falha ao disparar Apify']);
}