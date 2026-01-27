<?php
/**
 * API de Busca de Leads
 * Endpoint: /api/search.php
 */

// Previne qualquer output antes do JSON
ob_start();

// Registra handler de erros fatais para garantir JSON válido
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Erro interno do servidor: ' . $error['message']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
});

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    
    // Limpa qualquer output buffer
    ob_clean();
    
    // Verifica autenticação com tratamento de erro
    try {
        $userId = requireAuth();
    } catch (Exception $e) {
        jsonError('Erro de autenticação: ' . $e->getMessage(), 401);
    }
    
    // Conecta ao banco de dados com tratamento de erro
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Exception $e) {
        jsonError('Erro ao conectar ao banco de dados: ' . $e->getMessage(), 500);
    }
    
    // Recebe dados via POST
    $rawInput = file_get_contents('php://input');
    if (empty($rawInput)) {
        jsonError('Dados não fornecidos', 400);
    }
    
    $input = json_decode($rawInput, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonError('JSON inválido: ' . json_last_error_msg(), 400);
    }
    
    $query = sanitizeInput($input['query'] ?? '');
    $location = sanitizeInput($input['location'] ?? '');
    $tag = sanitizeInput($input['tag'] ?? '');
    $useGPS = isset($input['useGPS']) && $input['useGPS'];
    $coords = $input['coords'] ?? null;
    $locationName = sanitizeInput($input['locationName'] ?? '');
    
    // Validações
    if (empty($query)) {
        jsonError('Digite o ramo de atividade (Ex: Petshop).', 400);
    }
    
    if (!$useGPS && empty($location)) {
        jsonError('Digite a cidade ou ative o GPS.', 400);
    }
    
    // Chama o serviço ScraperAPI (Thordata)
    require_once __DIR__ . '/../services/scraperService.php';
    
    // Busca a chave da API nas configurações do usuário
    $scraperApiKey = null;
    try {
        // Verifica se a coluna scraper_api_key existe
        $stmtSettings = $db->prepare("SELECT scraper_api_key FROM settings WHERE user_id = ?");
        $stmtSettings->execute([$userId]);
        $userSettings = $stmtSettings->fetch();
        if ($userSettings && isset($userSettings['scraper_api_key'])) {
            $scraperApiKey = $userSettings['scraper_api_key'];
        }
    } catch (PDOException $e) {
        // Se a tabela ou coluna não existir, usa a chave padrão do config
        error_log("Erro ao buscar settings (pode ser coluna não existente): " . $e->getMessage());
        // Tenta usar a chave padrão do config.php
        $scraperApiKey = defined('SCRAPER_API_KEY') ? SCRAPER_API_KEY : null;
    }
    
    // Se não encontrou chave, usa a do config
    if (empty($scraperApiKey) && defined('SCRAPER_API_KEY')) {
        $scraperApiKey = SCRAPER_API_KEY;
    }
    
    // Inicializa o serviço Scraper com tratamento de erro
    try {
        $scraperService = new ScraperService($scraperApiKey);
    } catch (Exception $e) {
        error_log("Erro ao inicializar ScraperService: " . $e->getMessage());
        jsonError('Erro na configuração da API: ' . $e->getMessage(), 500);
    }
    
    // Busca os leads com tratamento de erro
    try {
        $leads = $scraperService->searchLeadsOnMaps(
            $query,
            $useGPS ? null : $location,
            [],
            null, // modelName não é usado no ScraperService
            $coords,
            $useGPS ? $locationName : null
        );
    } catch (Exception $e) {
        error_log("Erro ao buscar leads: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        jsonError('Erro ao buscar leads: ' . $e->getMessage(), 500);
    }
    
    if (empty($leads)) {
        $locationText = $useGPS ? ($locationName ? "em $locationName" : 'ao seu redor') : "em $location";
        error_log("Nenhum lead retornado para query: '$query' em '$locationText'");
        jsonError("Nenhum resultado encontrado para \"$query\" $locationText. Tente um termo mais amplo ou verifique se a API está retornando dados.", 404);
    }
    
    // Salva no histórico
    $locationText = $useGPS ? ($locationName ?: 'Localização GPS') : $location;
    
    try {
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
    } catch (PDOException $e) {
        error_log("Erro ao salvar no banco: " . $e->getMessage());
        // Continua mesmo se não conseguir salvar no banco, retorna os leads mesmo assim
    }
    
    jsonSuccess([
        'leads' => $leads,
        'searchId' => $searchHistoryId ?? null,
        'count' => count($leads)
    ]);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Erro na busca: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    jsonError($e->getMessage(), 500);
} catch (Error $e) {
    ob_clean();
    error_log("Erro fatal na busca: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    jsonError('Erro interno: ' . $e->getMessage(), 500);
}
