<?php
/**
 * API Mapbox Search - MapsProspector Pro
 * Este arquivo processa a busca de lugares via Mapbox API.
 */

header('Content-Type: application/json');

// Carregar configurações e funções do sistema
require_once 'config.php';
require_once 'functions.php';

// Verificar autenticação (ajuste conforme seu auth.php)
// session_start();
// if (!isset($_SESSION['user_id'])) {
//     echo json_encode(['success' => false, 'error' => 'Não autorizado']);
//     exit;
// }

$input = json_decode(file_get_contents('php://input'), true);

$query = $input['query'] ?? '';
$location = $input['location'] ?? '';
$lat = $input['lat'] ?? null;
$lng = $input['lng'] ?? null;
$raio = $input['raio'] ?? 5; // Raio em KM
$tag = $input['tag'] ?? '';

if (empty($query)) {
    echo json_encode(['success' => false, 'error' => 'Termo de busca vazio.']);
    exit;
}

// 1. Recuperar o Token do Mapbox das configurações globais (Super Admin)
// A função getPlatformSetting deve buscar na tabela platform_settings
$mapboxToken = getPlatformSetting('mapbox_access_token');

if (empty($mapboxToken)) {
    echo json_encode(['success' => false, 'error' => 'Token do Mapbox não configurado no painel Admin.']);
    exit;
}

// 2. Geocodificação (se não tiver lat/lng)
if (!$lat || !$lng) {
    $geoUrl = "https://api.mapbox.com/search/geocode/v6/forward?q=" . urlencode($location) . "&access_token=" . $mapboxToken . "&limit=1";
    
    $geoRes = file_get_contents($geoUrl);
    $geoData = json_decode($geoRes, true);
    
    if (isset($geoData['features'][0]['geometry']['coordinates'])) {
        $lng = $geoData['features'][0]['geometry']['coordinates'][0];
        $lat = $geoData['features'][0]['geometry']['coordinates'][1];
    } else {
        echo json_encode(['success' => false, 'error' => 'Não foi possível localizar o endereço informado.']);
        exit;
    }
}

// 2. Busca de Lugares (POI) via Mapbox Search Box API
// Nota: O Mapbox Search Box API /suggest + /retrieve ou o Tilequery API podem ser usados.
// Para prospecção massiva, o Mapbox Search Box API v1 /forward é recomendado.

$searchUrl = "https://api.mapbox.com/search/searchbox/v1/forward?q=" . urlencode($query) . "&proximity=$lng,$lat&access_token=$mapboxToken&limit=25&types=poi";

$searchRes = file_get_contents($searchUrl);
$searchData = json_decode($searchRes, true);

$leads = [];

if (isset($searchData['features'])) {
    foreach ($searchData['features'] as $feature) {
        $props = $feature['properties'];
        $coords = $feature['geometry']['coordinates'];
        
        // Calcular distância se necessário ou filtrar por raio no backend
        // Aqui o Mapbox já traz por proximidade.
        
        $leads[] = [
            'placeId' => $props['mapbox_id'] ?? uniqid(),
            'razao_social' => $props['name'] ?? 'Empresa sem nome',
            'cnpj' => $props['external_ids']['safegraph'] ?? '—', // Mapbox não fornece CNPJ diretamente
            'telefone_1' => $props['metadata']['phone'] ?? 'Sem telefone',
            'correio_eletronico' => $props['metadata']['website'] ?? 'Sem site/e-mail',
            'municipio' => $props['context']['place']['name'] ?? '',
            'uf' => $props['context']['region']['region_code'] ?? '',
            'endereco' => $props['full_address'] ?? '',
            'lat' => $coords[1],
            'lng' => $coords[0]
        ];
    }
}

echo json_encode([
    'success' => true,
    'leads' => $leads,
    'searchId' => time()
]);
