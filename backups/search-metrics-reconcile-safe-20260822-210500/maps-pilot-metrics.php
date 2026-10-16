<?php
declare(strict_types=1);
/**
 * Métricas operacionais seguras da Coleta Atendo (Maps + LinkedIn).
 * Visíveis exclusivamente ao super_admin; nunca devolve IP, segredos, tokens
 * nem detalhes da infraestrutura privada do coletor.
 * v20260820-64c — usa o mesmo bootstrap dos demais endpoints da v64
 * (config + database + includes/functions.php, sem depender de
 * includes/auth.php) e autentica por candidatos de sessão como os demais
 * endpoints; cada consulta roda em try/catch próprio e as colunas
 * leads_found/credits_charged são verificadas antes de serem usadas.
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
if (is_file(__DIR__ . '/../includes/AtendoMapsCollectorClient.php')) {
    require_once __DIR__ . '/../includes/AtendoMapsCollectorClient.php';
}
if (!class_exists('Database')) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Dependência do projeto não carregada (Database).'], JSON_UNESCAPED_UNICODE);
    exit;
}
function mapsPilotMetricsResponse(array $payload, int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
function mapsPilotSafeQuery(PDO $db, string $sql, array $params = []): array
{
    try {
        $st = $db->prepare($sql);
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    } catch (Throwable $err) {
        error_log('maps-pilot-metrics (consulta segura): ' . $err->getMessage());
        return [];
    }
}
function mapsPilotColumnExists(PDO $db, string $table, string $column): bool
{
    try {
        $st = $db->prepare(
            "SELECT COUNT(*) AS n FROM information_schema.columns
              WHERE table_schema = DATABASE()
                AND table_name = ? AND column_name = ?"
        );
        $st->execute([$table, $column]);
        $row = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        return (int) ($row['n'] ?? 0) > 0;
    } catch (Throwable $err) {
        error_log('maps-pilot-metrics (coluna): ' . $err->getMessage());
        return false;
    }
}
function mapsPilotSessionUser(PDO $db): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $candidates = [
        $_SESSION['ncSessionUser'] ?? null,
        $_SESSION['user'] ?? null,
        $_SESSION['auth_user'] ?? null,
        $_SESSION['current_user'] ?? null,
        $_SESSION['user_data'] ?? null,
    ];
    $profile = '';
    $email = '';
    $id = null;
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $candidateProfile = strtolower((string) ($candidate['profile'] ?? $candidate['role'] ?? $candidate['user_type'] ?? ''));
        if ($id === null && $candidateProfile !== 'super_admin') {
            $candidateId = $candidate['id'] ?? $candidate['user_id'] ?? null;
            if ($candidateId === null || $candidateId === '') {
                continue;
            }
        }
        $profile = $profile === '' ? $candidateProfile : $profile;
        $email = $email === '' ? (string) ($candidate['email'] ?? '') : $email;
        $id = $candidate['id'] ?? $candidate['user_id'] ?? $id;
    }
    if (empty($profile)) {
        $profile = strtolower((string) ($_SESSION['profile'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? ''));
    }
    if ($profile === 'super_admin' || !empty($_SESSION['is_super_admin'])) {
        return [
            'id' => 0,
            'email' => (string) ($_SESSION['email'] ?? $email),
            'name' => 'Super Admin',
            'profile' => 'super_admin',
            'tenant_id' => null,
        ];
    }
    if (!$id) {
        $id = $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? null;
    }
    if ($id === null || $id === '') {
        mapsPilotMetricsResponse(['success' => false, 'error' => 'Sessão inválida. Faça login novamente.'], 401);
    }
    $stmt = $db->prepare('SELECT id, tenant_id, name, email, profile, current_session_token FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) {
        mapsPilotMetricsResponse(['success' => false, 'error' => 'Sessão inválida. Faça login novamente.'], 401);
    }
    if (isset($row['current_session_token']) && is_string($row['current_session_token']) && $row['current_session_token'] !== '') {
        $sessionToken = (string) ($_SESSION['session_token'] ?? $_SESSION['token'] ?? '');
        if ($sessionToken !== '' && !hash_equals($row['current_session_token'], $sessionToken)) {
            mapsPilotMetricsResponse(['success' => false, 'error' => 'Sessão inválida. Faça login novamente.'], 401);
        }
    }
    return [
        'id' => (int) $row['id'],
        'email' => (string) $row['email'],
        'name' => (string) $row['name'],
        'profile' => strtolower((string) $row['profile']),
        'tenant_id' => $row['tenant_id'] === null ? null : (int) $row['tenant_id'],
    ];
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    mapsPilotMetricsResponse(['success' => false, 'error' => 'Método não permitido.'], 405);
}
try {
    $db = Database::getInstance()->getConnection();
    $user = mapsPilotSessionUser($db);
    if (($user['profile'] ?? '') !== 'super_admin') {
        mapsPilotMetricsResponse(['success' => false, 'error' => 'Acesso restrito ao super administrador.'], 403);
    }
    $superAdminId = (int) ($user['id'] ?? 0);
    $superAdminEmail = trim((string) ($user['email'] ?? ''));
    $useEmailFallback = $superAdminId < 1 && $superAdminEmail !== '';

    // ---------- Métricas MAPS ----------
    $mapsSearches = 0;
    $mapsLeads = 0;
    $mapsLastAt = null;
    $mapsLatest = null;
    try {
        $hasSearchHistory = (bool) $db->query("SHOW TABLES LIKE 'search_history'")->fetchColumn();
        if ($hasSearchHistory) {
            $userCondition = $useEmailFallback
                ? 'email = ?'
                : 'user_id = ?';
            $userParam = $useEmailFallback ? $superAdminEmail : $superAdminId;
            $aggregate = $db->prepare(
                "SELECT COUNT(*) AS searches,
                        COALESCE(SUM(results_count), 0) AS leads,
                        MAX(created_at) AS last_search_at
                   FROM search_history
                  WHERE {$userCondition}
                    AND (tag LIKE '[Coleta Atendo]%' OR tag LIKE '[Piloto Maps]%')"
            );
            $aggregate->execute([$userParam]);
            $metrics = $aggregate->fetch(PDO::FETCH_ASSOC) ?: [];
            $mapsSearches = (int) ($metrics['searches'] ?? 0);
            $mapsLeads = (int) ($metrics['leads'] ?? 0);
            $mapsLastAt = $metrics['last_search_at'] ?? null;

            $latest = $db->prepare(
                "SELECT query, location, results_count, created_at
                   FROM search_history
                  WHERE {$userCondition}
                    AND (tag LIKE '[Coleta Atendo]%' OR tag LIKE '[Piloto Maps]%')
                  ORDER BY id DESC LIMIT 1"
            );
            $latest->execute([$userParam]);
            $mapsLatest = $latest->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $err) {
        error_log('maps-pilot-metrics: bloco maps falhou: ' . $err->getMessage());
    }

    // ---------- Métricas LINKEDIN ----------
    $linkedinSearches = 0;
    $linkedinLeads = 0;
    $linkedinCredits = 0;
    $linkedinLastAt = null;
    $linkedinLatest = null;
    try {
        $hasRuns = (bool) $db->query("SHOW TABLES LIKE 'linkedin_runs'")->fetchColumn();
        if ($hasRuns) {
            $hasLeadsFound = mapsPilotColumnExists($db, 'linkedin_runs', 'leads_found');
            $hasCreditsCharged = mapsPilotColumnExists($db, 'linkedin_runs', 'credits_charged');
            $hasFinishedAt = mapsPilotColumnExists($db, 'linkedin_runs', 'finished_at');
            $statusWhere = "status IN ('done', 'completed')";

            $leadsExpr = $hasLeadsFound ? 'COALESCE(SUM(leads_found), 0)' : '0';
            $creditsExpr = $hasCreditsCharged ? 'COALESCE(SUM(COALESCE(credits_charged, 0)), 0)' : '0';
            $lastExpr = $hasFinishedAt ? 'MAX(finished_at)' : 'MAX(created_at)';

            // Super admin usa o tenant da própria empresa (tenant_id pode
            // ser NULL); usuários comuns usam somente o tenant deles.
            $tenantWhere = ($user['tenant_id'] === null) ? '1 = 1' : 'tenant_id = ?';
            $tenantParams = ($user['tenant_id'] === null) ? [] : [(int) $user['tenant_id']];

            $agg = $db->prepare(
                "SELECT COUNT(*) AS searches,
                        {$leadsExpr} AS leads,
                        {$creditsExpr} AS credits,
                        {$lastExpr} AS last_at
                   FROM linkedin_runs
                  WHERE {$tenantWhere} AND {$statusWhere}"
            );
            $agg->execute($tenantParams);
            $lr = $agg->fetch(PDO::FETCH_ASSOC) ?: [];
            $linkedinSearches = (int) ($lr['searches'] ?? 0);
            $linkedinLeads = (int) ($lr['leads'] ?? 0);
            $linkedinCredits = (int) ($lr['credits'] ?? 0);
            $linkedinLastAt = $lr['last_at'] ?? null;

            $selectCols = 'created_at' . ($hasFinishedAt ? ', finished_at' : '')
                . ($hasLeadsFound ? ', leads_found' : '')
                . ($hasCreditsCharged ? ', credits_charged' : '')
                . ', apify_actor';
            $l = $db->prepare(
                "SELECT {$selectCols}
                   FROM linkedin_runs
                  WHERE {$tenantWhere} AND {$statusWhere}
                  ORDER BY id DESC LIMIT 1"
            );
            $l->execute($tenantParams);
            $linkedinLatest = $l->fetch(PDO::FETCH_ASSOC) ?: null;
        }
    } catch (Throwable $err) {
        error_log('maps-pilot-metrics: bloco linkedin falhou: ' . $err->getMessage());
    }

    // O monitor mostra somente o estado operacional agregado. A configuração
    // contém URL e segredo HMAC e permanece fora do webroot e da resposta JSON.
    $collectorStatus = 'offline';
    $collectorLabel = 'Coletor ainda não configurado';
    $collectorAuth = 'Aguardando configuração privada';
    try {
        if (!class_exists('AtendoMapsCollectorClient')) {
            throw new RuntimeException('Cliente do coletor não disponível.');
        }
        $collectorClient = new AtendoMapsCollectorClient('/home/naciona4/atendo-secrets/maps-collector.php');
        if (!$collectorClient->isEnabled()) {
            $collectorStatus = 'disabled';
            $collectorLabel = 'Coletor desativado na configuração privada';
            $collectorAuth = 'Ative o coletor no arquivo protegido do servidor';
        } else {
            // A sonda não cria busca, não baixa leads e não consome créditos.
            $collectorClient->probe(3);
            $collectorStatus = 'online';
            $collectorLabel = 'Coletor Google Maps pronto para uso';
            $collectorAuth = 'Conexão segura validada no servidor';
        }
    } catch (Throwable $collectorError) {
        error_log('maps-pilot-metrics collector: ' . $collectorError->getMessage());
        $collectorStatus = 'offline';
        $collectorLabel = 'Coletor temporariamente indisponível';
        $collectorAuth = 'Verifique a configuração privada do servidor';
    }

    mapsPilotMetricsResponse([
        'success' => true,
        'collector' => [
            'status' => $collectorStatus,
            'label' => $collectorLabel,
            'authentication' => $collectorAuth,
            'max_results_per_run' => 50,
            'test_mode' => true,
        ],
        'platform' => [
            'searches' => $mapsSearches,
            'leads' => $mapsLeads,
            'credits_protected' => $mapsLeads,
            'last_search_at' => $mapsLastAt,
            'latest_search' => $mapsLatest ? [
                'query' => (string) ($mapsLatest['query'] ?? ''),
                'location' => (string) ($mapsLatest['location'] ?? ''),
                'results_count' => (int) ($mapsLatest['results_count'] ?? 0),
                'created_at' => $mapsLatest['created_at'] ?? null,
            ] : null,
        ],
        'linkedin' => [
            'searches' => $linkedinSearches,
            'leads' => $linkedinLeads,
            'credits_charged' => $linkedinCredits,
            'last_search_at' => $linkedinLastAt,
            'latest_search' => $linkedinLatest ? [
                'source' => (string) ($linkedinLatest['apify_actor'] ?? 'Coleta Atendo'),
                'leads_found' => (int) ($linkedinLatest['leads_found'] ?? 0),
                'credits_charged' => (int) ($linkedinLatest['credits_charged'] ?? 0),
                'created_at' => (string) ($linkedinLatest['created_at'] ?? ''),
                'finished_at' => $linkedinLatest['finished_at'] ?? null,
            ] : null,
        ],
    ]);
} catch (Throwable $error) {
    error_log('maps-pilot-metrics: ' . $error->getMessage());
    mapsPilotMetricsResponse(['success' => false, 'error' => 'Não foi possível carregar as métricas da Coleta Atendo.'], 500);
}
