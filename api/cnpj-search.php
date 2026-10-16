<?php
/**
 * api/cnpj-search.php - Motor de Busca CNPJ + Proxy VPS Local Seguro
 */

header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0); 
error_reporting(E_ALL);

// Evita que qualquer aviso suje o JSON
ob_start();

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../includes/module-access.php';

    // Inicializa a conexão primeiro para evitar erros de variável indefinida
    $pdo = Database::getInstance()->getConnection();
    $qualityStartedAt = microtime(true);
    
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (!isset($_SESSION['user_id'])) {
        ob_clean();
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sessão expirada.']);
        exit;
    }

    $user_id = (int) $_SESSION['user_id'];
    requireSearchPermission($pdo);
    $tenant_id = (int) ($_SESSION['tenant_id'] ?? 0);
    $sessionProfile = strtolower((string) ($_SESSION['profile'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? ''));
    if (!mpTenantHasModule($pdo, $tenant_id, 'cnpj', ['profile' => $sessionProfile])) {
        ob_clean();
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'O módulo de consulta CNPJ não está incluído no plano desta empresa.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    if (!$data || !isset($data['type'])) {
        throw new Exception("Dados de busca inválidos.");
    }

    $type = $data['type']; 
    $params = $data['params'] ?? [];

    // --- LÓGICA DE INTEGRAÇÃO COM A VPS LOCAL (PROSPEÇÃO AVANÇADA) ---
    if ($type === 'apify_busca_avancada') {
        // Salva os parâmetros de busca na sessão para o polling ler na sequência
        $_SESSION['last_adv_search'] = $params;
        
        ob_clean();
        // Devolve o runId fictício para o app.js iniciar o polling
        echo json_encode(['success' => true, 'runId' => 'vps_local_search']);
        exit;
    }

    // --- LÓGICA DE CHECAGEM DE STATUS (PROXY VPS) ---
    if ($type === 'check_apify_status') {
        $runId = $params['runId'];
        
        // Se for a busca direcionada para a nossa VPS local segura
        if ($runId === 'vps_local_search') {
            $savedParams = $_SESSION['last_adv_search'] ?? null;
            if (!$savedParams) {
                ob_clean();
                echo json_encode(['success' => false, 'error' => 'Parâmetros de busca perdidos. Tente pesquisar novamente.']);
                exit;
            }

            $privateCnpjConfig = [];
            $privateCnpjPath = '/home/naciona4/atendo-secrets/cnpj-vps.php';
            if (is_readable($privateCnpjPath)) {
                $loadedCnpj = require $privateCnpjPath;
                if (is_array($loadedCnpj)) { $privateCnpjConfig = $loadedCnpj; }
            }
            $vpsUrl = rtrim((string) ($privateCnpjConfig['url'] ?? ''), '/');
            $vpsToken = trim((string) ($privateCnpjConfig['token'] ?? ''));
            $payload = [
                'type' => 'local_busca',
                'params' => $savedParams
            ];

            $ch = curl_init($vpsUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $vpsToken // Envia o Token de Segurança
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60);
            $response = curl_exec($ch);
            curl_close($ch);

            ob_clean();
            // Retorna diretamente a resposta formatada da VPS para o navegador
            echo $response;
            exit;
        }

        // Fallback caso você ainda tenha tarefas antigas na Apify oficial pendentes
        $apifyToken = getPlatformSetting($pdo, 'scraper_api_key');
        $ch = curl_init("https://api.apify.com/v2/acts/runs/{$runId}?token=" . $apifyToken);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $dataStatus = json_decode($response, true);
        curl_close($ch);

        ob_clean();
        if (isset($dataStatus['data']['status']) && $dataStatus['data']['status'] === 'SUCCEEDED') {
            $datasetId = $dataStatus['data']['defaultDatasetId'];
            $resItems = file_get_contents("https://api.apify.com/v2/datasets/{$datasetId}/items?token={$apifyToken}");
            $items = json_decode($resItems, true);
            echo json_encode(['success' => true, 'status' => 'SUCCEEDED', 'results' => $items]);
        } else {
            echo json_encode(['success' => true, 'status' => 'RUNNING']);
        }
        exit;
    }

    // --- LÓGICA ORIGINAL (CNPJ.WS / PW) ---
    $costs = ['data' => 5, 'difusa' => 2, 'lookup' => 1];
    $tokenCost = $costs[$type] ?? 1;

    $stmt = $pdo->prepare("SELECT tokens_used, tokens_limit FROM tenants WHERE id = ?");
    $stmt->execute([$tenant_id]);
    $tenant = $stmt->fetch();

    if ($tenant && $tenant['tokens_limit'] > 0 && ($tenant['tokens_used'] + $tokenCost) > $tenant['tokens_limit']) {
        ob_clean();
        echo json_encode(['success' => false, 'error' => 'Saldo insuficiente de tokens.']);
        exit;
    }

    $premiumKey = null;
    try {
        $stmtCfg = $pdo->query("SELECT setting_value FROM platform_settings WHERE setting_key = 'cnpj_api_key' LIMIT 1");
        $config = $stmtCfg->fetch();
        $premiumKey = $config['setting_value'] ?? null;
    } catch (Exception $e) {}

    if ($type == 'lookup') {
        $cnpj = preg_replace('/\D/', '', $params['cnpj']);
        $url = $premiumKey ? "https://pro.cnpj.ws/cnpj/{$cnpj}?token={$premiumKey}" : "https://publica.cnpj.ws/cnpj/{$cnpj}";
    } elseif ($type == 'data') {
        $url = "https://api.cnpj.pw/data/" . $params['date'];
    } else {
        $url = "https://api.cnpj.pw/busca_difusa/?" . http_build_query($params);
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    ob_clean();
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        $pdo->prepare("UPDATE tenants SET tokens_used = tokens_used + ? WHERE id = ?")->execute([$tokenCost, $tenant_id]);
        $qualityResultCount = 0;
        if (is_array($result) && $result) {
            foreach (['results', 'resultados', 'items', 'estabelecimentos'] as $listKey) {
                if (isset($result[$listKey]) && is_array($result[$listKey])) {
                    $qualityResultCount = count($result[$listKey]);
                    break;
                }
            }
            if ($qualityResultCount === 0 && !isset($result['error'])) {
                $qualityResultCount = 1;
            }
        }
        $qualityCompany = is_array($result) ? $result : [];
        $qualityHasContact = trim((string) ($qualityCompany['phone'] ?? $qualityCompany['telefone'] ?? $qualityCompany['telefone_1'] ?? '')) !== ''
            || trim((string) ($qualityCompany['email'] ?? $qualityCompany['correio_eletronico'] ?? '')) !== '';
        $qualityProvider = (string) (parse_url($url, PHP_URL_HOST) ?: 'cnpj_provider');
        recordSearchQualityEvent($pdo, 'cnpj', [
            'provider' => $qualityProvider,
            'status' => $qualityResultCount > 0 ? 'success' : 'empty',
            'result_count' => $qualityResultCount,
            'missing_contact_count' => $qualityResultCount > 0 && !$qualityHasContact ? 1 : 0,
            'latency_ms' => (int) round((microtime(true) - $qualityStartedAt) * 1000),
            'credits_spent' => $tokenCost,
        ]);
        
        $log_query = "CNPJ/Busca: " . ($params['cnpj'] ?? $params['razao_social'] ?? $params['date'] ?? 'Consulta');
        $pdo->prepare("INSERT INTO searches (tenant_id, user_id, query, results_count, created_at) VALUES (?, ?, ?, ?, NOW())")
            ->execute([$tenant_id, $user_id, $log_query, 1]);
            
        echo json_encode([
            'success' => true,
            'data' => $result,
            'emptyReason' => $qualityResultCount === 0 ? 'no_results' : null,
            'emptyMessage' => $qualityResultCount === 0 ? 'Nenhuma empresa foi encontrada nesta consulta. Confira o CNPJ ou ajuste os filtros informados.' : null,
        ]);
    } else {
        recordSearchQualityEvent($pdo, 'cnpj', [
            'provider' => (string) (parse_url($url, PHP_URL_HOST) ?: 'cnpj_provider'),
            'status' => 'error',
            'latency_ms' => (int) round((microtime(true) - $qualityStartedAt) * 1000),
            'error_category' => 'provider_http_error',
        ]);
        echo json_encode(['success' => false, 'error' => 'Erro na API CNPJ. HTTP Code: ' . $httpCode]);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo instanceof PDO && isset($qualityStartedAt)) {
        recordSearchQualityEvent($pdo, 'cnpj', [
            'provider' => 'cnpj_provider',
            'status' => 'error',
            'latency_ms' => (int) round((microtime(true) - $qualityStartedAt) * 1000),
            'error_category' => 'cnpj_request_failed',
        ]);
    }
    ob_clean();
    echo json_encode(['success' => false, 'error' => 'Erro interno: ' . $e->getMessage()]);
}
