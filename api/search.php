<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_time_limit(300); // 💡 Aumenta o tempo limite para 5 minutos
ini_set('max_execution_time', '300');

// Previne qualquer output antes do JSON
ob_start();

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        error_log("search.php fatal error: {$error['message']} in {$error['file']}:{$error['line']}");
        echo json_encode([
            'success' => false,
            'error' => 'Não foi possível concluir a busca agora. Tente novamente mais tarde.'
        ]);
        exit;
    }
});

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';

    ob_clean();

    $userId = requireAuth();
    $db = Database::getInstance()->getConnection();
    $authUser = getAuthUser();
    $tenantId = $authUser['tenant_id'] ?? null;
    $profile = strtolower((string)($authUser['profile'] ?? ''));

    // =========================================================================
    // 💡 ISENÇÃO TOTAL DE BLOQUEIO
    // Isenta se for Super Admin, se for a empresa ID 1, ou se o tenant_id for nulo.
    // Também podemos isentar se o e-mail for o do Super Admin principal.
    // =========================================================================
    $isPlatformSuperAdmin = (
        $profile === 'super_admin' || 
        $tenantId === 1 || 
        $tenantId === null || 
        $authUser['email'] === 'seu-email-de-admin@seu-dominio.com' // Opcional: coloque seu e-mail aqui se quiser
    );

    if (!$isPlatformSuperAdmin && $tenantId !== null) {
        try {
            $stmtTenant = $db->prepare("SELECT created_at, status FROM tenants WHERE id = ?");
            $stmtTenant->execute([$tenantId]);
            $tenantRow = $stmtTenant->fetch(PDO::FETCH_ASSOC);

            if ($tenantRow) {
                if (isset($tenantRow['status']) && $tenantRow['status'] === 'suspended') {
                    jsonError('Sua empresa está suspensa (período de teste expirado ou limite atingido). Escolha um plano para continuar.', 403);
                }

                if (!empty($tenantRow['created_at'])) {
                    $createdAt = new DateTime($tenantRow['created_at']);
                    $now = new DateTime();
                    $interval = $now->diff($createdAt);
                    $daysSinceRegistration = (int)$interval->format('%a');

                    if ($daysSinceRegistration >= 30) {
                        try {
                            $stmtUpdate = $db->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ?");
                            $stmtUpdate->execute([$tenantId]);
                        } catch (PDOException $e) {}

                        jsonError('Seu período de teste de 30 dias expirou. Por favor, acesse "Meu Plano" para contratar um plano e continuar utilizando a plataforma.', 403);
                    }
                }
            }
        } catch (Exception $e) {
            error_log("search.php: erro ao validar período de teste: " . $e->getMessage());
        }
    }

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
    $maxCrawledPlacesPerSearch = isset($input['maxCrawledPlacesPerSearch']) ? max(1, min(1000, (int) $input['maxCrawledPlacesPerSearch'])) : 200;
    if (empty($query)) {
        jsonError('Digite o ramo de atividade (Ex: Petshop).', 400);
    }
    if (!$useGPS && empty($location)) {
        jsonError('Digite a cidade ou ative o GPS.', 400);
    }

    // Chama o serviço de busca
    require_once __DIR__ . '/../services/scraperService.php';
    $scraperApiKey = getRotatingApifyKey($db);
    
    if (empty(trim((string)$scraperApiKey))) {
        jsonError('Chave da API de busca não configurada.', 500);
    }

    $searchService = new ScraperService($scraperApiKey);
    $locationText = $useGPS ? ($locationName ?: 'Localização GPS') : $location;

    // Executa a busca
    $searchResult = $searchService->searchLeadsOnMaps(
        $query,
        $useGPS ? null : $location,
        [],
        null,
        $coords,
        $useGPS ? $locationName : null,
        $maxCrawledPlacesPerSearch,
        null
    );

    $leads = $searchResult['leads'] ?? [];

    if (empty($leads)) {
        jsonError("Nenhum resultado encontrado para \"$query\" em $locationText.", 204);
    }

    // Salva no histórico
    // Captura o IP real do usu�rio
    $userIp = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (strpos($userIp, ',') !== false) {
        $userIp = trim(explode(',', $userIp)[0]);
    }

    // Salva no hist�rico com o IP do usu�rio
    $stmt = $db->prepare("INSERT INTO search_history (user_id, query, location, tag, results_count, user_ip) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $query, $locationText, $tag, count($leads), $userIp]);
    $searchHistoryId = $db->lastInsertId();

    $stmtLead = $db->prepare("
        INSERT INTO leads (search_history_id, name, address, phone, email, website, maps_uri, cnpj, partners, tag, latitude, longitude)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $leadsForFrontend = [];
    foreach ($leads as $idx => $lead) {
        $mapsLink = $lead['mapsUri'] ?? $lead['maps_uri'] ?? '';
        $stmtLead->execute([
            $searchHistoryId,
            $lead['name'] ?? '',
            $lead['address'] ?? '',
            $lead['phone'] ?? null,
            $lead['email'] ?? null,
            $lead['website'] ?? null,
            $mapsLink,
            $lead['cnpj'] ?? null,
            $lead['partners'] ?? null,
            $tag ?: 'prospect_maps',
            $lead['latitude'] ?? null,
            $lead['longitude'] ?? null
        ]);
        
        $dbId = $db->lastInsertId();
        $leadsForFrontend[] = [
            'id' => 'lead-' . $dbId,
            'dbId' => $dbId,
            'name' => $lead['name'] ?? '',
            'address' => $lead['address'] ?? '',
            'phone' => $lead['phone'] ?? '',
            'email' => $lead['email'] ?? '',
            'website' => $lead['website'] ?? '',
            'mapsUri' => $mapsLink,
            'cnpj' => $lead['cnpj'] ?? '',
            'partners' => $lead['partners'] ?? '',
            'locked' => false
        ];
    }

    // Debita tokens se houver tenant e não for a conta mestre/super admin
    if (!$isPlatformSuperAdmin && $tenantId !== null && count($leadsForFrontend) > 0) {
        incrementTenantUsage($db, $tenantId, count($leadsForFrontend));
    }

    jsonSuccess([
        'leads' => $leadsForFrontend,
        'searchId' => (string)$searchHistoryId,
        'count' => count($leadsForFrontend),
        'isNewSearch' => true,
        'sourceNotice' => 'Dados coletados do Google Maps pelo serviço de prospecção. Confirme as informações antes de entrar em contato.'
    ]);

} catch (Throwable $e) {
    ob_clean();
    error_log("search.php error: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Não foi possível concluir a busca agora. Tente novamente mais tarde.'
    ]);
    exit;
}