<?php
/**
 * API Híbrida de Consulta de CNPJ com Cache Global de 15 dias
 * Endpoint: /api/cnpj.php
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();

try {
    $userId = requireAuth();
    $db = Database::getInstance()->getConnection();
    $tenantId = getAuthUser()['tenant_id'] ?? null;
} catch (Exception $e) {
    jsonError('Não autenticado.', 401);
}

// Custo fixo: 5 tokens por consulta
$cnpjCost = 5;

// Função auxiliar para formatar CNPJ
function formatCnpj($cnpj) {
    return preg_replace("/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/", "$1.$2.$3/$4-$5", $cnpj);
}

$method = $_SERVER['REQUEST_METHOD'];

// Captura CNPJ
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$cnpjRaw = isset($_GET['cnpj']) ? $_GET['cnpj'] : ($input['cnpj'] ?? '');
$cnpj = preg_replace('/[^0-9]/', '', $cnpjRaw);

if (empty($cnpj) || strlen($cnpj) !== 14) {
    jsonError('CNPJ inválido.', 400);
}
$cnpjFormatted = formatCnpj($cnpj);

// =============================================================================
// CACHE GLOBAL (15 dias)
// =============================================================================
try {
    // Busca qualquer registro desse CNPJ nos últimos 15 dias, não importa quem pesquisou
    $stmtCache = $db->prepare("
        SELECT l.name, l.address, l.phone, l.email, l.website, l.maps_uri, l.cnpj, l.partners 
        FROM leads l
        INNER JOIN search_history sh ON sh.id = l.search_history_id
        WHERE l.cnpj = ? AND sh.created_at >= DATE_SUB(NOW(), INTERVAL 15 DAY)
        ORDER BY sh.created_at DESC LIMIT 1
    ");
    $stmtCache->execute([$cnpjFormatted]);
    $localLead = $stmtCache->fetch(PDO::FETCH_ASSOC);

    if ($localLead) {
        jsonSuccess([
            'razao_social' => $localLead['name'],
            'estabelecimento' => [
                'cnpj' => $localLead['cnpj'],
                'nome_fantasia' => $localLead['website'],
                'logradouro' => $localLead['address'],
                'situacao_cadastral' => 'Ativa',
                'telefone1' => $localLead['phone'],
                'email' => $localLead['email']
            ],
            'socios' => $localLead['partners'] ?: [],
            'qsa' => $localLead['partners'] ?: [],
            'fromCache' => true
        ]);
        exit;
    }
} catch (PDOException $e) {
    error_log("cnpj.php cache error: " . $e->getMessage());
}

// =============================================================================
// CONSULTA REAL E SALVAMENTO
// =============================================================================

if ($method === 'GET') {
    // Verifica saldo
    if ($tenantId !== null) {
        $used = getTenantTokensUsed($db, $tenantId, null);
        $effectiveLimit = (getTenantPlanTokenLimit($db, $tenantId) ?: 0) + (getTenantTokenBonus($db, $tenantId, null) ?: 0);
        if ($effectiveLimit > 0 && ($effectiveLimit - $used) < $cnpjCost) {
            jsonError("Saldo insuficiente.", 403);
        }
    }

    $cnpjApiKey = getPlatformSetting($db, 'cnpj_api_key') ?? '';
    if (empty(trim($cnpjApiKey))) {
        jsonError('Chave API não configurada no painel.', 400);
    }

    $url = "https://publica.cnpj.ws/cnpj/" . urlencode($cnpj) . "?token=" . urlencode(trim($cnpjApiKey));
    $response = @file_get_contents($url);
    
    if ($response) {
        $data = json_decode($response, true);
        if ($tenantId !== null) incrementTenantUsage($db, $tenantId, $cnpjCost);
        saveCnpjToHistory($db, $userId, $cnpj, $data);
        jsonSuccess($data);
    } else {
        jsonError('Erro ao consultar CNPJ.', 500);
    }

} elseif ($method === 'POST') {
    // Modo para uso via frontend (CORS)
    $companyData = $input['companyData'] ?? null;
    if ($tenantId !== null) incrementTenantUsage($db, $tenantId, $cnpjCost);
    saveCnpjToHistory($db, $userId, $cnpj, $companyData);
    jsonSuccess(['message' => 'Salvo com sucesso']);
}

function saveCnpjToHistory($db, $userId, $cnpj, $data) {
    $est = $data['estabelecimento'] ?? [];
    $stmtHist = $db->prepare("INSERT INTO search_history (user_id, query, location, tag, results_count, created_at) VALUES (?, ?, 'Receita Federal', 'cnpj_cache', 1, NOW())");
    $stmtHist->execute([$userId, "CNPJ: " . formatCnpj($cnpj)]);
    $shId = $db->lastInsertId();
    
    $stmtLead = $db->prepare("INSERT INTO leads (search_history_id, name, address, phone, email, website, cnpj, partners, tag) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'cnpj_cache')");
    $rawPartners = $data['socios'] ?? $data['qsa'] ?? [];
    $partnerNames = [];
    foreach ((array) $rawPartners as $partner) {
        if (is_array($partner)) {
            $name = $partner['nome'] ?? $partner['nome_socio'] ?? $partner['name'] ?? '';
            if (trim((string) $name) !== '') $partnerNames[] = trim((string) $name);
        } elseif (trim((string) $partner) !== '') {
            $partnerNames[] = trim((string) $partner);
        }
    }
    $stmtLead->execute([
        $shId, $data['razao_social'] ?? '', ($est['logradouro'] ?? '') . ', ' . ($est['numero'] ?? ''),
        ($est['ddd1'] ?? '') . ($est['telefone1'] ?? ''), $est['email'] ?? '', $est['nome_fantasia'] ?? '', formatCnpj($cnpj), implode(', ', array_unique($partnerNames))
    ]);
}