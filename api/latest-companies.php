<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/scraperService.php';

$userId = requireAuth();
$db = Database::getInstance()->getConnection();
$tenantId = getAuthUser()['tenant_id'] ?? null;

$city = sanitizeInput($_GET['city'] ?? '');
if (empty($city)) jsonError('Informe a cidade.', 400);

// Busca a chave configurada no painel
$scraperApiKey = getPlatformSetting($db, 'scraper_api_key');

// O Apify permite buscar por "Nova empresa em Jales"
$query = "nova empresa em " . $city;

$searchService = new ScraperService($scraperApiKey);
// Busca 20 resultados ordenados pela busca de "nova empresa"
$searchResult = $searchService->searchLeadsOnMaps($query, $city, [], null, null, null, 20, null);

if (!empty($searchResult['leads'])) {
    // Debita 5 tokens
    if ($tenantId !== null) incrementTenantUsage($db, $tenantId, 5);
    jsonSuccess($searchResult['leads']);
} else {
    jsonError('Nenhuma empresa recente encontrada.', 404);
}