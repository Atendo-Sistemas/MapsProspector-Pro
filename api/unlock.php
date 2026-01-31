<?php
/**
 * API de Desbloqueio de Leads
 * Endpoint: /api/unlock.php
 * POST: { searchId, leadIds: ["lead-1", "lead-2"] }
 * searchId = id da pesquisa no banco (search_history.id).
 * Desbloqueia dados sensíveis (1 token por lead); estado persiste em lead_unlocks.
 */

ob_start();

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    ob_clean();
} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'error' => 'Erro ao carregar configuração.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'POST') {
    jsonError('Método não permitido', 405);
}

try {
    $userId = requireAuth();
} catch (Exception $e) {
    jsonError('Erro de autenticação: ' . $e->getMessage(), 401);
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    jsonError('Erro ao conectar ao banco de dados.', 500);
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput ?: '{}', true);
if (!is_array($input)) {
    jsonError('JSON inválido', 400);
}

$searchIdRaw = isset($input['searchId']) ? trim((string) $input['searchId']) : '';
$leadIds = isset($input['leadIds']) && is_array($input['leadIds']) ? $input['leadIds'] : [];

if ($searchIdRaw === '') {
    jsonError('searchId é obrigatório', 400);
}

$searchHistoryId = (int) $searchIdRaw;
if ($searchHistoryId <= 0) {
    jsonError('Busca não encontrada. Faça uma nova pesquisa.', 404);
}

if (empty($leadIds)) {
    jsonSuccess(['unlocked' => (object) [], 'tokenUsage' => null]);
}

$leadIds = array_values(array_unique(array_filter(array_map(function ($id) {
    return is_string($id) ? trim($id) : (is_scalar($id) ? trim((string) $id) : '');
}, $leadIds))));

if (empty($leadIds)) {
    jsonSuccess(['unlocked' => (object) [], 'tokenUsage' => null]);
}

// Verifica se a pesquisa pertence ao usuário
$stmtSh = $db->prepare("SELECT id FROM search_history WHERE id = ? AND user_id = ?");
$stmtSh->execute([$searchHistoryId, $userId]);
if (!$stmtSh->fetch()) {
    jsonError('Busca não encontrada ou sessão expirada. Faça uma nova pesquisa.', 404);
}

// Extrai IDs numéricos dos leads (formato "lead-123" -> 123)
$numericIds = [];
foreach ($leadIds as $lid) {
    if (preg_match('/^lead-(\d+)$/', $lid, $m)) {
        $numericIds[(int) $m[1]] = $lid;
    }
}
if (empty($numericIds)) {
    jsonSuccess(['unlocked' => (object) [], 'tokenUsage' => null]);
}

// Carrega leads do banco para esta pesquisa
$placeholders = implode(',', array_fill(0, count($numericIds), '?'));
$stmtLeads = $db->prepare("
    SELECT id, name, address, phone, email, website, maps_uri, cnpj, partners, tag, latitude, longitude
    FROM leads
    WHERE search_history_id = ? AND id IN ($placeholders)
");
$params = array_merge([$searchHistoryId], array_keys($numericIds));
$stmtLeads->execute($params);
$leadsRows = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);

// Quais já estão desbloqueados (lead_unlocks)
$alreadyUnlocked = [];
try {
    $placeholders2 = implode(',', array_fill(0, count($numericIds), '?'));
    $stmtU = $db->prepare("
        SELECT lead_id FROM lead_unlocks
        WHERE user_id = ? AND search_history_id = ? AND lead_id IN ($placeholders2)
    ");
    $stmtU->execute(array_merge([$userId, $searchHistoryId], array_keys($numericIds)));
    $alreadyUnlocked = array_flip(array_column($stmtU->fetchAll(PDO::FETCH_ASSOC), 'lead_id'));
} catch (PDOException $e) {
    // Tabela lead_unlocks pode não existir
}

$result = [];
$toUnlock = [];

foreach ($leadsRows as $row) {
    $leadDbId = (int) $row['id'];
    $frontId = 'lead-' . $leadDbId;

    $data = [
        'phone' => $row['phone'] ?? '',
        'email' => $row['email'] ?? '',
        'address' => $row['address'] ?? '',
        'website' => $row['website'] ?? '',
        'mapsUri' => $row['maps_uri'] ?? '',
        'cnpj' => $row['cnpj'] ?? '',
        'partners' => $row['partners'] ?? '',
        'latitude' => $row['latitude'] ?? null,
        'longitude' => $row['longitude'] ?? null,
    ];

    if (isset($alreadyUnlocked[$leadDbId])) {
        $result[$frontId] = $data;
    } else {
        $toUnlock[] = ['id' => $leadDbId, 'frontId' => $frontId, 'data' => $data];
    }
}

$authUser = getAuthUser();
$tenantId = $authUser['tenant_id'] ?? null;

if (empty($toUnlock)) {
    $tokenUsage = null;
    if ($tenantId !== null) {
        $planLimit = getTenantPlanTokenLimit($db, $tenantId);
        $bonus = getTenantTokenBonus($db, $tenantId, null);
        $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
        $used = getTenantTokensUsed($db, $tenantId, null);
        $tokenUsage = [
            'used' => $used,
            'limit' => $effectiveLimit,
            'limitReached' => $effectiveLimit > 0 && $used >= $effectiveLimit,
        ];
    }
    jsonSuccess(['unlocked' => $result, 'tokenUsage' => $tokenUsage]);
}

$countToUnlock = count($toUnlock);

if ($tenantId === null) {
    // Super admin sem tenant: libera sem debitar
    try {
        $stmtIns = $db->prepare("INSERT IGNORE INTO lead_unlocks (user_id, search_history_id, lead_id) VALUES (?, ?, ?)");
        foreach ($toUnlock as $u) {
            $result[$u['frontId']] = $u['data'];
            $stmtIns->execute([$userId, $searchHistoryId, $u['id']]);
        }
    } catch (PDOException $e) {
        foreach ($toUnlock as $u) {
            $result[$u['frontId']] = $u['data'];
        }
    }
    jsonSuccess(['unlocked' => $result, 'tokenUsage' => ['used' => 0, 'limit' => 0, 'limitReached' => false]]);
}

$planLimit = getTenantPlanTokenLimit($db, $tenantId);
$bonus = getTenantTokenBonus($db, $tenantId, null);
$effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
$used = getTenantTokensUsed($db, $tenantId, null);
$available = $effectiveLimit > 0 ? max(0, $effectiveLimit - $used) : PHP_INT_MAX;

if ($effectiveLimit > 0 && $available < $countToUnlock) {
    jsonError('Tokens insuficientes para desbloquear ' . $countToUnlock . ' lead(s). Disponível: ' . $available . '. Cada lead desbloqueado consome 1 token.', 403);
}

incrementTenantUsage($db, $tenantId, $countToUnlock);

try {
    $stmtIns = $db->prepare("INSERT IGNORE INTO lead_unlocks (user_id, search_history_id, lead_id) VALUES (?, ?, ?)");
    foreach ($toUnlock as $u) {
        $result[$u['frontId']] = $u['data'];
        $stmtIns->execute([$userId, $searchHistoryId, $u['id']]);
    }
} catch (PDOException $e) {
    foreach ($toUnlock as $u) {
        $result[$u['frontId']] = $u['data'];
    }
}

$used = getTenantTokensUsed($db, $tenantId, null);
$tokenUsage = [
    'used' => $used,
    'limit' => $effectiveLimit,
    'limitReached' => $effectiveLimit > 0 && $used >= $effectiveLimit,
];

jsonSuccess(['unlocked' => $result, 'tokenUsage' => $tokenUsage]);
