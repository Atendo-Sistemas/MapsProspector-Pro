<?php
/**
 * cnae-secoes.php — Proxy PHP para listar seções CNAE
 * 
 * Colocá-lo em: /caminho/do/seu/site/api/cnae-secoes.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$CNAE_API_URL = 'http://localhost:5000/api/secoes';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $CNAE_API_URL);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'User-Agent: MapsProspector/1.0'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response && $httpCode === 200) {
    echo $response;
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Erro ao consultar seções'
    ], JSON_UNESCAPED_UNICODE);
}
