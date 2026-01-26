<?php
/**
 * API de Busca de Leads
 * Endpoint: /api/search.php
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

// Recebe dados via POST
$input = json_decode(file_get_contents('php://input'), true);
$query = sanitizeInput($input['query'] ?? '');
$location = sanitizeInput($input['location'] ?? '');
$tag = sanitizeInput($input['tag'] ?? '');
$useGPS = isset($input['useGPS']) && $input['useGPS'];
$coords = $input['coords'] ?? null;
$locationName = sanitizeInput($input['locationName'] ?? '');
$modelName = sanitizeInput($input['model'] ?? 'gemini-2.0-flash');

// Validações
if (empty($query)) {
    jsonError('Digite o ramo de atividade (Ex: Petshop).');
}

if (!$useGPS && empty($location)) {
    jsonError('Digite a cidade ou ative o GPS.');
}

// Chama o serviço Gemini
require_once __DIR__ . '/../services/gemini.php';

try {
    $geminiService = new GeminiService();
    $leads = $geminiService->searchLeadsOnMaps(
        $query,
        $useGPS ? null : $location,
        [],
        $modelName,
        $coords,
        $useGPS ? $locationName : null
    );
    
    if (empty($leads)) {
        $locationText = $useGPS ? ($locationName ? "em $locationName" : 'ao seu redor') : "em $location";
        jsonError("Nenhum resultado encontrado para \"$query\" $locationText. Tente um termo mais amplo.");
    }
    
    // Salva no histórico
    $locationText = $useGPS ? ($locationName ?: 'Localização GPS') : $location;
    
    $stmt = $db->prepare("
        INSERT INTO search_history (user_id, query, location, tag, results_count) 
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $query, $locationText, $tag, count($leads)]);
    $searchHistoryId = $db->lastInsertId();
    
    // Salva os leads
    $stmtLead = $db->prepare("
        INSERT INTO leads (search_history_id, name, address, phone, email, website, maps_uri, cnpj, partners, tag, latitude, longitude)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($leads as $lead) {
        $stmtLead->execute([
            $searchHistoryId,
            $lead['name'] ?? '',
            $lead['address'] ?? '',
            $lead['phone'] ?? null,
            $lead['email'] ?? null,
            $lead['website'] ?? null,
            $lead['mapsUri'] ?? null,
            $lead['cnpj'] ?? null,
            $lead['partners'] ?? null,
            $tag ?: 'prospect_maps',
            $lead['latitude'] ?? null,
            $lead['longitude'] ?? null
        ]);
    }
    
    // Busca os IDs reais dos leads inseridos
    $stmtLeads = $db->prepare("SELECT id, name, address FROM leads WHERE search_history_id = ? ORDER BY id ASC");
    $stmtLeads->execute([$searchHistoryId]);
    $insertedLeads = $stmtLeads->fetchAll();
    
    // Adiciona IDs reais aos leads para o frontend
    foreach ($leads as $idx => &$lead) {
        if (isset($insertedLeads[$idx])) {
            $lead['id'] = 'lead-' . $insertedLeads[$idx]['id'];
            $lead['dbId'] = $insertedLeads[$idx]['id']; // ID real do banco
        } else {
            $lead['id'] = 'lead-' . time() . '-' . $idx;
        }
    }
    
    jsonSuccess([
        'leads' => $leads,
        'searchId' => $searchHistoryId,
        'count' => count($leads)
    ]);
    
} catch (Exception $e) {
    error_log("Erro na busca: " . $e->getMessage());
    jsonError($e->getMessage(), 500);
}
