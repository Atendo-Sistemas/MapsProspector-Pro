<?php
declare(strict_types=1);

/**
 * Busca Maps self-hosted para perfis autenticados e elegíveis.
 *
 * Atualiza uma pesquisa Maps já existente ou cria uma nova pesquisa para o
 * usuário autenticado. Antes da coleta, resultados recentes de termos
 * normalizados podem ser reaproveitados de forma isolada: a empresa atual
 * recebe somente seus próprios registros de histórico, sem acesso à origem.
 * O endpoint nunca usa ou devolve tokens da Apify nem o segredo HMAC.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
@set_time_limit(330);
ob_start();

register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Não foi possível concluir a atualização do piloto Maps.'], JSON_UNESCAPED_UNICODE);
    }
});

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/AtendoMapsCollectorClient.php';

function mapsPilotError(string $message, int $status = 400): never
{
    if (ob_get_length()) {
        ob_clean();
    }
    jsonError($message, $status);
    exit;
}

function mapsPilotSessionUser(PDO $db): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $userId = (int) ($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? ($_SESSION['ncSessionUser']['id'] ?? 0));
    $email = trim((string) ($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''));

    foreach (['ncSessionUser', 'user', 'auth_user', 'current_user', 'user_data'] as $sessionKey) {
        $candidate = $_SESSION[$sessionKey] ?? null;
        if (is_array($candidate)) {
            $userId = $userId ?: (int) ($candidate['id'] ?? $candidate['user_id'] ?? 0);
            $email = $email !== '' ? $email : trim((string) ($candidate['email'] ?? ''));
        }
    }

    if ($userId <= 0 && $email === '') {
        mapsPilotError('Não autenticado.', 401);
    }

    $stmt = $userId > 0
        ? $db->prepare('SELECT id, tenant_id, profile, current_session_token FROM users WHERE id = ? LIMIT 1')
        : $db->prepare('SELECT id, tenant_id, profile, current_session_token FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$userId > 0 ? $userId : $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        mapsPilotError('Sessão não reconhecida. Faça login novamente.', 401);
    }

    $sessionToken = (string) ($_SESSION['session_token'] ?? '');
    if ($sessionToken !== '' && !empty($user['current_session_token']) && !hash_equals((string) $user['current_session_token'], $sessionToken)) {
        mapsPilotError('Sua sessão foi substituída por outro login. Faça login novamente.', 401);
    }

    $allowedProfiles = ['super_admin', 'admin', 'supervisor', 'normal', 'user', 'manager', 'gerente'];
    if (!in_array(strtolower((string) ($user['profile'] ?? '')), $allowedProfiles, true)) {
        mapsPilotError('Seu perfil não possui permissão para usar a busca Maps.', 403);
    }

    try {
        $touch = $db->prepare('UPDATE users SET last_activity = NOW() WHERE id = ?');
        $touch->execute([(int) $user['id']]);
    } catch (Throwable $activityError) {
        error_log('maps-pilot activity touch: ' . $activityError->getMessage());
    }

    return $user;
}

function mapsPilotNormalizeText(?string $value): string
{
    $value = mb_strtolower(trim((string) $value), 'UTF-8');
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    return preg_replace('/[^a-z0-9]+/', '', $value) ?: '';
}

function mapsPilotNormalizePhone(?string $value): string
{
    return preg_replace('/\D+/', '', (string) $value) ?: '';
}

function mapsPilotNormalizeDomain(?string $value): string
{
    $website = trim((string) $value);
    if ($website === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $website)) {
        $website = 'https://' . $website;
    }
    $host = strtolower((string) parse_url($website, PHP_URL_HOST));
    return preg_replace('/^www\./', '', $host) ?: '';
}

function mapsPilotNormalizeMapsUri(?string $value): string
{
    $url = trim((string) $value);
    if ($url === '') {
        return '';
    }
    $parts = parse_url($url);
    if ($parts === false || empty($parts['host'])) {
        return mapsPilotNormalizeText($url);
    }
    $host = strtolower((string) $parts['host']);
    $path = rtrim((string) ($parts['path'] ?? ''), '/');
    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $query);
    foreach (array_keys($query) as $key) {
        if (preg_match('/^(utm_|gclid|fbclid)/i', (string) $key)) {
            unset($query[$key]);
        }
    }
    ksort($query);
    return $host . $path . ($query ? '?' . http_build_query($query) : '');
}

function mapsPilotFallbackKey(array $lead): string
{
    return implode('|', [
        mapsPilotNormalizePhone($lead['phone'] ?? ''),
        mapsPilotNormalizeDomain($lead['website'] ?? ''),
        mapsPilotNormalizeText($lead['name'] ?? ''),
        mapsPilotNormalizeText($lead['address'] ?? ''),
    ]);
}

function mapsPilotLeadKeys(array $lead): array
{
    $keys = [];
    $mapsKey = mapsPilotNormalizeMapsUri($lead['maps_uri'] ?? $lead['mapsUri'] ?? '');
    if ($mapsKey !== '') {
        $keys[] = 'maps:' . $mapsKey;
    }
    $fallback = mapsPilotFallbackKey($lead);
    if ($fallback !== '|||') {
        $keys[] = 'fallback:' . $fallback;
    }
    return $keys;
}

function mapsPilotReadCsv(string $csv): array
{
    $handle = fopen('php://temp', 'r+');
    if ($handle === false) {
        throw new RuntimeException('Não foi possível ler o resultado do coletor Maps.');
    }
    fwrite($handle, $csv);
    rewind($handle);
    $headers = fgetcsv($handle);
    if (!is_array($headers)) {
        fclose($handle);
        throw new RuntimeException('O coletor Maps retornou um CSV sem cabeçalho.');
    }
    $headers = array_map(static function ($header): string {
        return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?: ''));
    }, $headers);

    $rows = [];
    while (($values = fgetcsv($handle)) !== false) {
        if (count($values) === 1 && trim((string) $values[0]) === '') {
            continue;
        }
        $row = [];
        foreach ($headers as $index => $header) {
            if ($header !== '') {
                $row[$header] = trim((string) ($values[$index] ?? ''));
            }
        }
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

function mapsPilotEmailFromCsv(array $row): string
{
    $raw = trim((string) ($row['email'] ?? $row['emails'] ?? ''));
    if ($raw === '') {
        return '';
    }
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        foreach ($decoded as $value) {
            if (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return $value;
            }
        }
    }
    if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $raw, $match)) {
        return $match[0];
    }
    return '';
}

function mapsPilotFrontendLead(array $row): array
{
    $mapsUri = (string) ($row['maps_uri'] ?? '');
    return [
        'id' => 'lead-' . (int) $row['id'],
        'dbId' => (int) $row['id'],
        'name' => $row['name'] ?? '',
        'address' => $row['address'] ?? '',
        'phone' => $row['phone'] ?? '',
        'email' => $row['email'] ?? '',
        'website' => $row['website'] ?? '',
        'mapsUri' => $mapsUri,
        'maps_uri' => $mapsUri,
        'cnpj' => $row['cnpj'] ?? '',
        'partners' => $row['partners'] ?? '',
        'tag' => $row['tag'] ?? '',
        'latitude' => $row['latitude'] ?? null,
        'longitude' => $row['longitude'] ?? null,
        'locked' => false,
    ];
}

function mapsPilotCharge(PDO $db, int $tenantId, int $credits): void
{
    if ($credits < 1) {
        return;
    }
    $plan = $db->prepare('SELECT p.period FROM tenants t INNER JOIN plans p ON p.id = t.plan_id WHERE t.id = ? LIMIT 1');
    $plan->execute([$tenantId]);
    $period = (string) (($plan->fetch(PDO::FETCH_ASSOC)['period'] ?? 'monthly'));
    $periodStart = $period === 'yearly' ? date('Y-01-01') : date('Y-m-01');
    $usage = $db->prepare('INSERT INTO tenant_usage (tenant_id, period_start, tokens_used, token_bonus) VALUES (?, ?, ?, 0) ON DUPLICATE KEY UPDATE tokens_used = tokens_used + ?');
    $usage->execute([$tenantId, $periodStart, $credits, $credits]);
}

function mapsPilotSearchSignature(string $query, string $location): string
{
    return mapsPilotNormalizeText($query) . '|' . mapsPilotNormalizeText($location);
}

/**
 * Reaproveita somente os resultados de uma consulta recente com termos
 * equivalentes. O histórico de origem nunca é devolvido ao navegador.
 */
function mapsPilotLoadSharedLeadCandidates(PDO $db, int $currentHistoryId, string $query, string $location, int $currentTenantId = 0, int $currentUserId = 0): array
{
    $signature = mapsPilotSearchSignature($query, $location);
    if ($signature === '|') {
        return ['history_id' => 0, 'history_ids' => [], 'leads' => [], 'tenant_seen_keys' => []];
    }

    $historyStmt = $db->prepare(
        'SELECT sh.id, sh.query, sh.location
           FROM search_history sh
          WHERE sh.id <> ?
          ORDER BY sh.id DESC'
    );
    $historyStmt->execute([$currentHistoryId]);
    $matchingHistoryIds = [];
    foreach ($historyStmt->fetchAll(PDO::FETCH_ASSOC) as $candidateHistory) {
        if (mapsPilotSearchSignature((string) $candidateHistory['query'], (string) $candidateHistory['location']) === $signature) {
            $matchingHistoryIds[] = (int) $candidateHistory['id'];
        }
    }
    if (!$matchingHistoryIds) {
        return ['history_id' => 0, 'history_ids' => [], 'leads' => [], 'tenant_seen_keys' => []];
    }

    $placeholders = implode(',', array_fill(0, count($matchingHistoryIds), '?'));
    $leadStmt = $db->prepare(
        "SELECT name, address, phone, email, website, maps_uri, latitude, longitude
           FROM leads
          WHERE search_history_id IN ({$placeholders})
          ORDER BY id ASC"
    );
    $leadStmt->execute($matchingHistoryIds);
    $leads = [];
    $seenKeys = [];
    $tenantSeenKeys = [];
    if ($currentTenantId > 0 || $currentUserId > 0) {
        $tenantLeadStmt = $db->prepare(
            "SELECT l.name, l.address, l.phone, l.email, l.website, l.maps_uri
               FROM leads l
               INNER JOIN search_history prior_sh ON prior_sh.id = l.search_history_id
               INNER JOIN users prior_owner ON prior_owner.id = prior_sh.user_id
              WHERE prior_sh.id IN ({$placeholders})
                AND (prior_owner.tenant_id = ? OR prior_sh.user_id = ?)"
        );
        $tenantLeadStmt->execute(array_merge($matchingHistoryIds, [$currentTenantId, $currentUserId]));
        foreach ($tenantLeadStmt->fetchAll(PDO::FETCH_ASSOC) as $priorLead) {
            foreach (mapsPilotLeadKeys($priorLead) as $key) {
                $tenantSeenKeys[$key] = true;
            }
        }
    }
    foreach ($leadStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $name = trim((string) ($row['name'] ?? ''));
        $address = trim((string) ($row['address'] ?? ''));
        if ($name === '' && $address === '') {
            continue;
        }
        $lead = [
            'name' => $name,
            'address' => $address,
            'phone' => trim((string) ($row['phone'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'website' => trim((string) ($row['website'] ?? '')),
            'maps_uri' => trim((string) ($row['maps_uri'] ?? '')),
            'latitude' => is_numeric($row['latitude'] ?? null) ? (float) $row['latitude'] : null,
            'longitude' => is_numeric($row['longitude'] ?? null) ? (float) $row['longitude'] : null,
        ];
        $keys = mapsPilotLeadKeys($lead);
        $isDuplicate = false;
        foreach ($keys as $key) {
            if (isset($tenantSeenKeys[$key])) {
                $isDuplicate = true;
                break;
            }
        }
        foreach ($keys as $key) {
            if (isset($seenKeys[$key])) {
                $isDuplicate = true;
                break;
            }
        }
        if ($isDuplicate) {
            continue;
        }
        foreach ($keys as $key) {
            $seenKeys[$key] = true;
        }
        $leads[] = $lead;
    }
    return [
        'history_id' => $matchingHistoryIds[0] ?? 0,
        'history_ids' => $matchingHistoryIds,
        'leads' => $leads,
        'tenant_seen_keys' => array_keys($tenantSeenKeys),
    ];
}

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) !== 'POST') {
        mapsPilotError('Método não permitido.', 405);
    }

    $db = Database::getInstance()->getConnection();
    $currentUser = mapsPilotSessionUser($db);
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        mapsPilotError('Dados de atualização inválidos.', 400);
    }

    $action = strtolower(trim((string) ($input['action'] ?? 'refresh')));
    $isDirectPlatformSearch = in_array($action, ['create', 'new', 'direct'], true);
    $searchHistoryId = filter_var($input['search_history_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $directQuery = trim((string) ($input['query'] ?? ''));
    $directLocation = trim((string) ($input['location'] ?? ''));
    // O painel pode exibir a origem administrativa entre parênteses; essa
    // etiqueta não deve ser enviada ao coletor nem gravada no histórico.
    $directLocation = trim((string) (preg_replace('/\s*\(por\s+[^)]+\)\s*$/iu', '', $directLocation) ?: $directLocation));
    $directTag = trim((string) ($input['tag'] ?? ''));
    $mapsSearchTag = $directTag !== '' ? $directTag : 'Pesquisa Maps';
    if (!$isDirectPlatformSearch && !$searchHistoryId) {
        mapsPilotError('Selecione uma pesquisa Maps válida para atualizar.', 422);
    }
    if ($isDirectPlatformSearch && $directQuery === '') {
        mapsPilotError('Informe o ramo de atividade para a busca direta do piloto.', 422);
    }
    if ($isDirectPlatformSearch && $directLocation === '') {
        mapsPilotError('Informe a cidade ou região para a busca direta do piloto.', 422);
    }
    // A quantidade vem da interface. Zero mantém o modo compatível sem teto
    // comercial explícito; a quantidade final respeita o saldo disponível e o
    // teto técnico de 1.000 leads por execução.
    $requestedMax = min(1000, max(0, (int) ($input['max_results'] ?? 0)));

    if ($isDirectPlatformSearch) {
        $history = [
            'id' => null,
            'query' => $directQuery,
            'location' => $directLocation,
            'tag' => $mapsSearchTag,
            'tenant_id' => (int) ($currentUser['tenant_id'] ?? 0),
        ];
        $newHistory = $db->prepare('INSERT INTO search_history (user_id, query, location, tag, results_count, user_ip) VALUES (?, ?, ?, ?, 0, ?)');
        $newHistory->execute([
            (int) $currentUser['id'],
            $directQuery,
            $directLocation,
            $mapsSearchTag,
            substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64),
        ]);
        $searchHistoryId = (int) $db->lastInsertId();
        if ($searchHistoryId < 1) {
            throw new RuntimeException('Não foi possível registrar a nova pesquisa Maps.');
        }
    } else {
        $currentUserTenantId = (int) ($currentUser['tenant_id'] ?? 0);
        $currentProfile = strtolower((string) ($currentUser['profile'] ?? ''));
        $canManageCompanyHistory = in_array($currentProfile, ['admin', 'supervisor', 'manager', 'gerente'], true)
            && $currentUserTenantId > 0;
        $historyStmt = $db->prepare(
            'SELECT sh.id, sh.query, sh.location, sh.tag, u.tenant_id'
            . ' FROM search_history sh'
            . ' INNER JOIN users u ON u.id = sh.user_id'
            . ' WHERE sh.id = ? AND (sh.user_id = ? OR (u.tenant_id = ? AND ? = 1 AND u.tenant_id > 0))'
            . ' LIMIT 1'
        );
        $historyStmt->execute([
            $searchHistoryId,
            (int) $currentUser['id'],
            $currentUserTenantId,
            $canManageCompanyHistory ? 1 : 0,
        ]);
        $history = $historyStmt->fetch(PDO::FETCH_ASSOC);
        if (!$history) {
            mapsPilotError('A pesquisa selecionada não existe mais ou não está disponível para o seu perfil.', 404);
        }
        // O histórico visível a gestores da mesma empresa mantém o tenant do
        // autor como referência de cobrança, sem revelar identidades externas.
        if ((int) ($history['tenant_id'] ?? 0) <= 0 && !$canManageCompanyHistory && !$isPlatformAdmin) {
            mapsPilotError('A pesquisa selecionada não está vinculada a uma empresa disponível para este perfil.', 404);
        }
    }
    $billingTenantId = (int) ($history['tenant_id'] ?? 0);
    // O super_admin de plataforma pode manter pesquisas próprias, sem tenant.
    // Nesse caso o piloto continua disponível exclusivamente para validação,
    // mas não há empresa a debitar; portanto a execução é explicitamente
    // identificada como teste de plataforma e não registra consumo de créditos.
    $isPlatformTestWithoutBillingTenant = $billingTenantId <= 0 && strtolower((string) ($currentUser['profile'] ?? '')) === 'super_admin';
    if ($billingTenantId <= 0 && !$isPlatformTestWithoutBillingTenant) {
        mapsPilotError('Sua conta não está vinculada a uma empresa apta para realizar pesquisas Maps.', 403);
    }
    $billingMode = $isPlatformTestWithoutBillingTenant ? 'platform_test_no_charge' : 'tenant';

    $planLimit = $billingTenantId > 0 && function_exists('getTenantPlanTokenLimit') ? (int) getTenantPlanTokenLimit($db, $billingTenantId) : 0;
    $effectiveMax = $requestedMax;
    $availableCredits = null;
    if ($planLimit > 0) {
        $used = function_exists('getTenantTokensUsed') ? (int) getTenantTokensUsed($db, $billingTenantId) : 0;
        $bonus = function_exists('getTenantTokenBonus') ? (int) getTenantTokenBonus($db, $billingTenantId) : 0;
        $available = max(0, $planLimit + $bonus - $used);
        if ($available < 1) {
            mapsPilotError('Esta empresa não possui créditos disponíveis para atualizar a pesquisa.', 403);
        }
        $availableCredits = $available;
        $effectiveMax = $requestedMax > 0 ? min($requestedMax, $available) : $available;
    }
    if ($effectiveMax < 1) {
        // Sem teto comercial informado, permite-se a quantidade configurável
        // até o teto técnico de 1.000 leads por execução.
        $effectiveMax = 1000;
    }

    $shared = mapsPilotLoadSharedLeadCandidates(
        $db,
        (int) $searchHistoryId,
        (string) $history['query'],
        (string) $history['location'],
        (int) ($currentUser['tenant_id'] ?? 0),
        (int) ($currentUser['id'] ?? 0)
    );
    $reusedSharedHistoryId = (int) ($shared['history_id'] ?? 0);
    $candidateLeads = is_array($shared['leads'] ?? null) ? $shared['leads'] : [];
    $tenantSeenKeys = array_fill_keys(
        is_array($shared['tenant_seen_keys'] ?? null) ? $shared['tenant_seen_keys'] : [],
        true
    );
    // A origem dos candidatos é detalhe operacional: para o usuário a resposta
    // é sempre a de uma busca comum, sem revelar reaproveitamento interno.
    $searchSource = 'maps_self_hosted';

    // O banco é sempre a primeira fonte. Se ainda não houver candidatos
    // suficientes para a quantidade solicitada, o coletor busca somente um
    // complemento; a deduplicação final acontece dentro da transação.
    if (count($candidateLeads) < $effectiveMax) {
        $collector = new AtendoMapsCollectorClient('/home/naciona4/atendo-secrets/maps-collector.php');
        if (!$collector->isEnabled()) {
            mapsPilotError('A busca Maps está temporariamente indisponível. Tente novamente em alguns instantes.', 503);
        }

        $collectorQuery = trim((string) $history['query'] . ' em ' . (string) $history['location']);
        $collectorLimit = max(1, $effectiveMax - count($candidateLeads));
        $requestId = 'maps-' . $searchHistoryId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $created = $collector->createJob($requestId, $collectorQuery, $collectorLimit, 'pt');
        $jobId = trim((string) ($created['job_id'] ?? ''));
        if (!preg_match('/^[0-9a-f-]{36}$/i', $jobId)) {
            throw new RuntimeException('A busca Maps não retornou um identificador de coleta válido.');
        }

        $terminalStates = ['finished', 'completed', 'done', 'success', 'ok'];
        $failedStates = ['failed', 'error', 'cancelled', 'canceled'];
        $deadline = time() + 300;
        $state = 'pending';
        while (time() < $deadline) {
            sleep(3);
            $job = $collector->getJob($jobId);
            $state = strtolower(trim((string) ($job['Status'] ?? $job['status'] ?? $state)));
            if (in_array($state, $terminalStates, true)) {
                break;
            }
            if (in_array($state, $failedStates, true)) {
                throw new RuntimeException('A busca Maps encerrou a coleta com estado: ' . $state . '.');
            }
        }
        if (!in_array($state, $terminalStates, true)) {
            throw new RuntimeException('A busca Maps não concluiu a coleta dentro do tempo esperado.');
        }

        $candidateRows = mapsPilotReadCsv($collector->downloadAndDeleteJob($jobId));
        foreach ($candidateRows as $row) {
            $candidate = [
                'name' => trim((string) ($row['title'] ?? $row['name'] ?? '')),
                'address' => trim((string) ($row['address'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'email' => mapsPilotEmailFromCsv($row),
                'website' => trim((string) ($row['website'] ?? '')),
                'maps_uri' => trim((string) ($row['link'] ?? $row['maps_uri'] ?? '')),
                'latitude' => is_numeric($row['latitude'] ?? null) ? (float) $row['latitude'] : null,
                'longitude' => is_numeric($row['longitude'] ?? null) ? (float) $row['longitude'] : null,
            ];
            if ($candidate['name'] === '' && $candidate['address'] === '') {
                continue;
            }
            $candidateLeads[] = $candidate;
        }
    }

    $db->beginTransaction();
    try {
        $lockedHistory = $db->prepare('SELECT id, tag FROM search_history WHERE id = ? FOR UPDATE');
        $lockedHistory->execute([$searchHistoryId]);
        $locked = $lockedHistory->fetch(PDO::FETCH_ASSOC);
        if (!$locked) {
            throw new RuntimeException('A pesquisa selecionada deixou de existir durante a atualização.');
        }

        $existingStmt = $db->prepare('SELECT id, name, address, phone, website, maps_uri FROM leads WHERE search_history_id = ? ORDER BY id ASC');
        $existingStmt->execute([$searchHistoryId]);
        $existingRows = $existingStmt->fetchAll(PDO::FETCH_ASSOC);
        $seenKeys = $tenantSeenKeys;
        foreach ($existingRows as $lead) {
            foreach (mapsPilotLeadKeys($lead) as $key) {
                $seenKeys[$key] = true;
            }
        }

        $newLeads = [];
        foreach ($candidateLeads as $candidate) {
            $candidateKeys = mapsPilotLeadKeys($candidate);
            $duplicate = false;
            foreach ($candidateKeys as $key) {
                if (isset($seenKeys[$key])) {
                    $duplicate = true;
                    break;
                }
            }
            if ($duplicate) {
                continue;
            }
            foreach ($candidateKeys as $key) {
                $seenKeys[$key] = true;
            }
            if (count($newLeads) >= $effectiveMax) {
                break;
            }
            if ($availableCredits !== null && count($newLeads) >= $availableCredits) {
                break;
            }
            $newLeads[] = $candidate;
        }

        $insert = $db->prepare('INSERT INTO leads (search_history_id, name, address, phone, email, website, maps_uri, cnpj, partners, tag, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL, ?, ?, ?)');
        $newLeadIds = [];
        foreach ($newLeads as $lead) {
            $insert->execute([$searchHistoryId, $lead['name'], $lead['address'], $lead['phone'] ?: null, $lead['email'] ?: null, $lead['website'] ?: null, $lead['maps_uri'] ?: null, $locked['tag'] ?: 'prospect_maps', $lead['latitude'], $lead['longitude']]);
            $insertedLeadId = (int) $db->lastInsertId();
            if ($insertedLeadId > 0) {
                $newLeadIds[] = $insertedLeadId;
            }
        }

        $newCount = count($newLeads);
        $totalCount = count($existingRows) + $newCount;
        $updateHistory = $db->prepare('UPDATE search_history SET results_count = ? WHERE id = ?');
        $updateHistory->execute([$totalCount, $searchHistoryId]);
        if (!$isPlatformTestWithoutBillingTenant) {
            mapsPilotCharge($db, $billingTenantId, $newCount);
        }
        $db->commit();
    } catch (Throwable $transactionError) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $transactionError;
    }

    $allLeadsStmt = $db->prepare('SELECT id, name, address, phone, email, website, maps_uri, cnpj, partners, tag, latitude, longitude FROM leads WHERE search_history_id = ? ORDER BY id ASC');
    $allLeadsStmt->execute([$searchHistoryId]);
    $allLeads = array_map('mapsPilotFrontendLead', $allLeadsStmt->fetchAll(PDO::FETCH_ASSOC));

    if (ob_get_length()) {
        ob_clean();
    }
    jsonSuccess([
        'searchId' => (string) $searchHistoryId,
        'leads' => $allLeads,
        'leads_existing' => count($existingRows),
        'leads_new' => $newCount,
        'new_lead_ids' => $newLeadIds,
        'credits_charged' => $isPlatformTestWithoutBillingTenant ? 0 : $newCount,
        'billing_mode' => $billingMode,
        'search_source' => $searchSource,
        'reused_cached_results' => $reusedSharedHistoryId > 0,
        'reused_history_count' => is_array($shared['history_ids'] ?? null) ? count($shared['history_ids']) : 0,
        'billing_notice' => $isPlatformTestWithoutBillingTenant
            ? 'Teste de plataforma sem empresa de cobrança vinculada; nenhum crédito foi consumido.'
            : null,
        'is_new_search' => $isDirectPlatformSearch,
        'total_count' => count($allLeads),
        'requested_limit' => $requestedMax,
        'effective_limit' => $effectiveMax,
        'new_lead_limit_applied' => min($effectiveMax, $availableCredits ?? $effectiveMax),
    ], $newCount > 0 ? '+' . $newCount . ' novos leads encontrados.' : 'Nenhum novo lead nesta atualização.');
} catch (Throwable $error) {
    error_log('maps-pilot.php: ' . $error->getMessage());
    $profile = strtolower((string) ($currentUser['profile'] ?? ''));
    $detail = $profile === 'super_admin'
        ? ' Detalhe técnico: ' . substr((string) $error->getMessage(), 0, 240)
        : '';
    mapsPilotError('Não foi possível concluir a busca Maps agora. Tente novamente em alguns instantes.' . $detail, 502);
}
