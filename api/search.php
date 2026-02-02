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
        $showDetail = (defined('ENVIRONMENT') && ENVIRONMENT === 'development' && ini_get('display_errors'));
        $msg = $showDetail ? ('Erro interno do servidor: ' . $error['message']) : 'Erro interno do servidor. Tente novamente.';
        echo json_encode([
            'success' => false,
            'error' => $msg
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
    $maxCrawledPlacesPerSearch = isset($input['maxCrawledPlacesPerSearch']) ? max(1, min(1000, (int) $input['maxCrawledPlacesPerSearch'])) : null;
    
    // Validações
    if (empty($query)) {
        jsonError('Digite o ramo de atividade (Ex: Petshop).', 400);
    }
    
    if (!$useGPS && empty($location)) {
        jsonError('Digite a cidade ou ative o GPS.', 400);
    }
    
    // Limite de tokens do plano (empresa): 1 token = 1 página (até 20 resultados); débito só após a busca
    $authUser = getAuthUser();
    $tenantId = $authUser['tenant_id'] ?? null;
    if ($tenantId !== null) {
        // Bloqueia se a empresa estiver suspensa (ex.: por limite de tokens)
        $stmtTenant = $db->prepare("SELECT status FROM tenants WHERE id = ?");
        $stmtTenant->execute([$tenantId]);
        $tenantRow = $stmtTenant->fetch();
        if ($tenantRow && isset($tenantRow['status']) && $tenantRow['status'] === 'suspended') {
            jsonError('Sua empresa está suspensa (limite de tokens atingido). Entre em contato com o administrador para aquisição de mais tokens.', 403);
        }
        $planLimit = getTenantPlanTokenLimit($db, $tenantId);
        $bonus = getTenantTokenBonus($db, $tenantId, null);
        $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
        if ($effectiveLimit > 0) {
            $used = getTenantTokensUsed($db, $tenantId, null);
            if ($used >= $effectiveLimit) {
                try {
                    $stmt = $db->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ? AND status = 'active'");
                    $stmt->execute([$tenantId]);
                } catch (PDOException $e) {
                    error_log("search.php: erro ao suspender tenant por limite de tokens: " . $e->getMessage());
                }
                jsonError('Limite de tokens do seu plano foi atingido para este período. Entre em contato com o administrador para alterar o plano.', 403);
            }
            // Não debita aqui: débito será feito após a busca, na quantidade exata de resultados
        }
    }
    
    // Chama o serviço de busca (Google Maps)
    require_once __DIR__ . '/../services/scraperService.php';
    
    // Chave da API de busca: única para toda a plataforma (configurada apenas pelo super_admin)
    $scraperApiKey = getPlatformSetting($db, 'scraper_api_key');
    if (empty(trim((string)$scraperApiKey)) && defined('SCRAPER_API_KEY')) {
        $scraperApiKey = SCRAPER_API_KEY;
    }
    
    // Inicializa o serviço Scraper com tratamento de erro
    try {
        $scraperService = new ScraperService($scraperApiKey);
    } catch (Exception $e) {
        error_log("Erro ao inicializar ScraperService: " . $e->getMessage());
        jsonError('Erro na configuração da API: ' . $e->getMessage(), 500);
    }
    
    // Tokens disponíveis para paginação: 1 token = 1 página (20 resultados). Enquanto houver crédito, aumenta "start" até retornar todos os dados ou acabar o crédito.
    $maxTokensAvailable = null;
    if ($tenantId !== null) {
        $planLimit = getTenantPlanTokenLimit($db, $tenantId);
        $bonus = getTenantTokenBonus($db, $tenantId, null);
        $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
        if ($effectiveLimit > 0) {
            $used = getTenantTokensUsed($db, $tenantId, null);
            $maxTokensAvailable = max(0, $effectiveLimit - $used);
            if ($maxTokensAvailable < 1) {
                jsonError('Você não tem tokens disponíveis para esta busca. Cada página (20 resultados) consome 1 token.', 403);
            }
        }
    }
    
    // Busca os leads; maxCrawledPlacesPerSearch vem do frontend (campo Limite) ou padrão 1000
    try {
        $searchResult = $scraperService->searchLeadsOnMaps(
            $query,
            $useGPS ? null : $location,
            [],
            null,
            $coords,
            $useGPS ? $locationName : null,
            $maxCrawledPlacesPerSearch ?? 1000,
            $maxTokensAvailable
        );
        $leads = $searchResult['leads'];
        $pagesUsed = isset($searchResult['pages_used']) ? (int) $searchResult['pages_used'] : (int) ceil(count($leads) / 20);
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
    $searchHistoryId = null;
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
    
    // Debita tokens: 1 token = 1 página (20 resultados). Debita apenas as páginas efetivamente consumidas.
    if ($tenantId !== null && isset($pagesUsed) && $pagesUsed > 0) {
        incrementTenantUsage($db, $tenantId, $pagesUsed);
    }
    
    // Dados sensíveis só são liberados no desbloqueio (1 token por lead); estado persiste em lead_unlocks no banco
    $searchIdKey = (string) ($searchHistoryId ?? 0);
    $leadsForFrontend = [];
    foreach ($leads as $lead) {
        $leadsForFrontend[] = [
            'id' => $lead['id'],
            'name' => $lead['name'] ?? '',
            'locked' => true,
            'dbId' => $lead['dbId'] ?? null,
            'website' => !empty($lead['website']) ? (string) $lead['website'] : '',
        ];
    }

    // Retorna tokenUsage atualizado para o frontend
    $tokenUsage = null;
    if ($tenantId !== null) {
        $planLimit = getTenantPlanTokenLimit($db, $tenantId);
        $bonus = getTenantTokenBonus($db, $tenantId, null);
        $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
        $used = getTenantTokensUsed($db, $tenantId, null);
        $tokenUsage = [
            'used' => $used,
            'limit' => $effectiveLimit,
            'limitReached' => $effectiveLimit > 0 && $used >= $effectiveLimit
        ];
    }
    jsonSuccess([
        'leads' => $leadsForFrontend,
        'searchId' => $searchIdKey,
        'count' => count($leadsForFrontend),
        'tokenUsage' => $tokenUsage
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
