<?php
/**
 * Métricas do Dashboard por perfil.
 *
 * Perfis atendidos:
 * - super_admin: acompanhamento da plataforma e das empresas;
 * - admin: resumo operacional da própria empresa;
 * - supervisor: acompanhamento consolidado da equipe;
 * - normal/user: atividade individual.
 */

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'");
header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function dashboardReply(bool $success, $data = null, string $error = '', int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($status);
    echo json_encode(
        $success ? ['success' => true, 'data' => $data] : ['success' => false, 'error' => $error],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
    );
    exit;
}

function dashboardStartSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    @session_start();
}

function dashboardRateLimit(): void
{
    dashboardStartSecureSession();
    $window = 60;
    $limit = 60;
    $now = time();
    $state = $_SESSION['_dashboard_metrics_rate'] ?? ['started_at' => $now, 'count' => 0];
    if (!is_array($state) || ($now - (int)($state['started_at'] ?? 0)) >= $window) {
        $state = ['started_at' => $now, 'count' => 0];
    }

    $state['count'] = (int)($state['count'] ?? 0) + 1;
    $_SESSION['_dashboard_metrics_rate'] = $state;
    if ($state['count'] > $limit) {
        header('Retry-After: ' . max(1, $window - ($now - (int)$state['started_at'])));
        dashboardReply(false, null, 'Muitas solicitações. Aguarde um instante e tente novamente.', 429);
    }
}

function dashboardSessionUser(PDO $db): ?array
{
    dashboardStartSecureSession();

    $candidates = [
        $_SESSION['user'] ?? null,
        $_SESSION['auth_user'] ?? null,
        $_SESSION['current_user'] ?? null,
        $_SESSION['user_data'] ?? null,
    ];

    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $profile = strtolower((string)($candidate['profile'] ?? $candidate['role'] ?? $candidate['user_type'] ?? ''));
        $id = $candidate['id'] ?? $candidate['user_id'] ?? null;
        if (($id === null || $id === '') && $profile !== 'super_admin') {
            continue;
        }

        if ($profile === 'super_admin') {
            return ['id' => 0, 'tenant_id' => null, 'profile' => 'super_admin', 'name' => 'Super administrador'];
        }

        $stmt = $db->prepare('SELECT id, tenant_id, profile, name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int)$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            continue;
        }
        return [
            'id' => (int)$row['id'],
            'tenant_id' => $row['tenant_id'] ?? null,
            'profile' => strtolower((string)($row['profile'] ?? 'user')),
            'name' => (string)($row['name'] ?? ''),
        ];
    }

    $profile = strtolower((string)($_SESSION['profile'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? ''));
    if ($profile === 'super_admin' || !empty($_SESSION['is_super_admin'])) {
        return ['id' => 0, 'tenant_id' => null, 'profile' => 'super_admin', 'name' => 'Super administrador'];
    }

    $id = $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? null;
    $email = $_SESSION['email'] ?? $_SESSION['user_email'] ?? null;
    if (($id === null || $id === '') && ($email === null || $email === '')) {
        return null;
    }

    $stmt = $id !== null && $id !== ''
        ? $db->prepare('SELECT id, tenant_id, profile, name FROM users WHERE id = ? LIMIT 1')
        : $db->prepare('SELECT id, tenant_id, profile, name FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$id !== null && $id !== '' ? (int)$id : (string)$email]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'id' => (int)$row['id'],
        'tenant_id' => $row['tenant_id'] ?? null,
        'profile' => strtolower((string)($row['profile'] ?? 'user')),
        'name' => (string)($row['name'] ?? ''),
    ];
}

function dashboardTableExists(PDO $db, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$table]);
    return $cache[$table] = ((int)$stmt->fetchColumn() > 0);
}

function dashboardColumns(PDO $db, string $table): array
{
    static $cache = [];
    if (isset($cache[$table])) {
        return $cache[$table];
    }
    if (!dashboardTableExists($db, $table)) {
        return $cache[$table] = [];
    }
    $rows = $db->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`')->fetchAll(PDO::FETCH_ASSOC);
    $columns = [];
    foreach ($rows as $row) {
        $columns[(string)$row['Field']] = true;
    }
    return $cache[$table] = $columns;
}

function dashboardUsageRows(PDO $db, ?int $tenantId = null, ?int $userId = null): array
{
    $hasLinkedIn = dashboardTableExists($db, 'linkedin_runs');
    $linkedInJoin = $hasLinkedIn
        ? ' LEFT JOIN (SELECT user_id, COUNT(*) AS linkedin_searches, MAX(created_at) AS last_linkedin FROM linkedin_runs GROUP BY user_id) lr ON lr.user_id = u.id '
        : '';
    $linkedInSelect = $hasLinkedIn
        ? 'COALESCE(lr.linkedin_searches, 0) AS linkedin_searches, lr.last_linkedin AS last_linkedin'
        : '0 AS linkedin_searches, NULL AS last_linkedin';

    $where = [];
    $params = [];
    if ($tenantId !== null) {
        $where[] = 'u.tenant_id = ?';
        $params[] = $tenantId;
    }
    if ($userId !== null) {
        $where[] = 'u.id = ?';
        $params[] = $userId;
    }

    $sql = 'SELECT
                u.id, u.tenant_id, u.name, u.profile,
                SUM(CASE WHEN sh.id IS NOT NULL AND COALESCE(sh.tag, \'\') <> \'CNPJ_AVANCADA\' THEN 1 ELSE 0 END) AS maps_searches,
                SUM(CASE WHEN sh.id IS NOT NULL AND sh.tag = \'CNPJ_AVANCADA\' THEN 1 ELSE 0 END) AS cnpj_queries,
                SUM(CASE WHEN sh.id IS NOT NULL AND COALESCE(sh.tag, \'\') <> \'CNPJ_AVANCADA\' THEN COALESCE(sh.results_count, 0) ELSE 0 END) AS leads_generated,
                MAX(sh.created_at) AS last_maps,
                ' . $linkedInSelect . '
            FROM users u
            LEFT JOIN search_history sh ON sh.user_id = u.id
            ' . $linkedInJoin . '
            ' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . '
            GROUP BY u.id, u.tenant_id, u.name, u.profile' . ($hasLinkedIn ? ', lr.linkedin_searches, lr.last_linkedin' : '') . '
            ORDER BY last_maps DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['maps_searches'] = (int)$row['maps_searches'];
        $row['cnpj_queries'] = (int)$row['cnpj_queries'];
        $row['linkedin_searches'] = (int)$row['linkedin_searches'];
        $row['leads_generated'] = (int)$row['leads_generated'];
        $mapTime = !empty($row['last_maps']) ? strtotime((string)$row['last_maps']) : 0;
        $linkedinTime = !empty($row['last_linkedin']) ? strtotime((string)$row['last_linkedin']) : 0;
        $row['last_activity'] = max($mapTime, $linkedinTime) > 0
            ? gmdate('c', max($mapTime, $linkedinTime))
            : null;
    }
    unset($row);
    return $rows;
}

function dashboardTotals(array $rows): array
{
    $totals = ['maps_searches' => 0, 'cnpj_queries' => 0, 'linkedin_searches' => 0, 'leads_generated' => 0, 'active_users' => 0, 'last_activity' => null];
    foreach ($rows as $row) {
        $totals['maps_searches'] += (int)($row['maps_searches'] ?? 0);
        $totals['cnpj_queries'] += (int)($row['cnpj_queries'] ?? 0);
        $totals['linkedin_searches'] += (int)($row['linkedin_searches'] ?? 0);
        $totals['leads_generated'] += (int)($row['leads_generated'] ?? 0);
        if (!empty($row['last_activity'])) {
            $totals['active_users']++;
            if ($totals['last_activity'] === null || strtotime($row['last_activity']) > strtotime($totals['last_activity'])) {
                $totals['last_activity'] = $row['last_activity'];
            }
        }
    }
    return $totals;
}

function dashboardMaskName(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $first = (string)($parts[0] ?? 'Membro');
    $last = (string)($parts[count($parts) - 1] ?? '');
    return count($parts) > 1 && $last !== '' ? $first . ' ' . strtoupper(substr($last, 0, 1)) . '.' : $first;
}

function dashboardTeamRows(array $rows, bool $maskNames): array
{
    return array_map(static function (array $row) use ($maskNames): array {
        return [
            'member' => $maskNames ? dashboardMaskName((string)($row['name'] ?? '')) : (string)($row['name'] ?? 'Membro'),
            'profile' => (string)($row['profile'] ?? ''),
            'maps_searches' => (int)($row['maps_searches'] ?? 0),
            'cnpj_queries' => (int)($row['cnpj_queries'] ?? 0),
            'linkedin_searches' => (int)($row['linkedin_searches'] ?? 0),
            'leads_generated' => (int)($row['leads_generated'] ?? 0),
            'last_activity' => $row['last_activity'] ?? null,
        ];
    }, $rows);
}

function dashboardTenantCreditField(PDO $db): ?string
{
    $columns = dashboardColumns($db, 'tenants');
    foreach (['credits', 'credit_balance', 'tokens', 'token_balance', 'balance'] as $candidate) {
        if (isset($columns[$candidate])) {
            return $candidate;
        }
    }
    return null;
}

/**
 * Retorna apenas agregados locais e seguros do piloto Maps. Esta função não
 * consulta a infraestrutura privada do coletor, portanto não pode atrasar o
 * carregamento da visão principal do Dashboard.
 */
function dashboardMapsPilotMetrics(PDO $db, int $superAdminId): array
{
    $collector = [
        'status' => 'deferred',
        'label' => 'Disponibilidade confirmada durante a busca',
        'authentication' => 'Proteção validada no servidor',
        'max_results_per_run' => 50,
        'test_mode' => true,
    ];
    $platform = [
        'searches' => 0,
        'leads' => 0,
        'credits_protected' => 0,
        'last_search_at' => null,
        'latest_search' => null,
    ];

    if ($superAdminId < 1 || !dashboardTableExists($db, 'search_history')) {
        return ['collector' => $collector, 'platform' => $platform];
    }

    try {
        $aggregate = $db->prepare(
            "SELECT COUNT(*) AS searches,
                    COALESCE(SUM(results_count), 0) AS leads,
                    MAX(created_at) AS last_search_at
               FROM search_history
              WHERE user_id = ? AND tag LIKE '[Piloto Maps]%'"
        );
        $aggregate->execute([$superAdminId]);
        $metrics = $aggregate->fetch(PDO::FETCH_ASSOC) ?: [];

        $latest = $db->prepare(
            "SELECT query, location, results_count, created_at
               FROM search_history
              WHERE user_id = ? AND tag LIKE '[Piloto Maps]%'
              ORDER BY id DESC LIMIT 1"
        );
        $latest->execute([$superAdminId]);
        $latestSearch = $latest->fetch(PDO::FETCH_ASSOC) ?: null;

        $platform['searches'] = (int)($metrics['searches'] ?? 0);
        $platform['leads'] = (int)($metrics['leads'] ?? 0);
        $platform['credits_protected'] = (int)($metrics['leads'] ?? 0);
        $platform['last_search_at'] = $metrics['last_search_at'] ?? null;
        if ($latestSearch) {
            $platform['latest_search'] = [
                'query' => (string)($latestSearch['query'] ?? ''),
                'location' => (string)($latestSearch['location'] ?? ''),
                'results_count' => (int)($latestSearch['results_count'] ?? 0),
                'created_at' => $latestSearch['created_at'] ?? null,
            ];
        }
    } catch (Throwable $e) {
        error_log('dashboard-metrics maps-pilot: ' . $e->getMessage());
    }

    return ['collector' => $collector, 'platform' => $platform];
}

function dashboardMapsPilotOwnerId(PDO $db, array $auth): int
{
    $sessionUserId = (int)($auth['id'] ?? 0);
    if ($sessionUserId > 0) {
        return $sessionUserId;
    }

    if (!dashboardTableExists($db, 'users')) {
        return 0;
    }

    try {
        $stmt = $db->query("SELECT id FROM users WHERE LOWER(COALESCE(profile, '')) = 'super_admin' ORDER BY id ASC LIMIT 1");
        return (int)$stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('dashboard-metrics maps-pilot owner: ' . $e->getMessage());
        return 0;
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        dashboardReply(false, null, 'Método não permitido.', 405);
    }
    dashboardRateLimit();
    $db = Database::getInstance()->getConnection();
    $auth = dashboardSessionUser($db);
    if (!$auth || ($auth['profile'] !== 'super_admin' && empty($auth['id']))) {
        dashboardReply(false, null, 'Não autenticado.', 401);
    }

    $profile = strtolower((string)$auth['profile']);
    $tenantId = isset($auth['tenant_id']) && $auth['tenant_id'] !== null ? (int)$auth['tenant_id'] : null;
    $regularProfile = in_array($profile, ['normal', 'user'], true);

    if ($profile === 'super_admin') {
        $creditField = dashboardTenantCreditField($db);
        $creditSelect = $creditField ? 't.`' . $creditField . '` AS credits' : 'NULL AS credits';
        $tenants = $db->query('SELECT t.id, t.name, t.plan, t.status, t.created_at, ' . $creditSelect . ' FROM tenants t ORDER BY t.name ASC')->fetchAll(PDO::FETCH_ASSOC);
        $usageRows = dashboardUsageRows($db);
        $byTenant = [];
        foreach ($usageRows as $row) {
            $byTenant[(int)$row['tenant_id']][] = $row;
        }

        $companies = [];
        $alerts = [];
        $now = time();
        foreach ($tenants as $tenant) {
            $id = (int)$tenant['id'];
            $total = dashboardTotals($byTenant[$id] ?? []);
            $credits = $tenant['credits'] === null ? null : (int)$tenant['credits'];
            $company = [
                'id' => $id,
                'name' => (string)($tenant['name'] ?? 'Empresa'),
                'plan' => (string)($tenant['plan'] ?? ''),
                'status' => (string)($tenant['status'] ?? ''),
                'credits' => $credits,
                'users' => count($byTenant[$id] ?? []),
            ] + $total;
            $companies[] = $company;
            if ($credits !== null && $credits <= 20) {
                $alerts[] = ['type' => 'low_credits', 'tenant_id' => $id, 'tenant_name' => $company['name'], 'message' => 'Créditos baixos: ' . $credits];
            }
            if (empty($total['last_activity']) || ($now - strtotime($total['last_activity'])) > 604800) {
                $alerts[] = ['type' => 'inactive', 'tenant_id' => $id, 'tenant_name' => $company['name'], 'message' => 'Sem atividade há mais de 7 dias'];
            }
        }
        $platform = dashboardTotals($usageRows);
        $platform['companies_total'] = count($tenants);
        $platform['companies_active'] = count(array_filter($tenants, static function ($tenant) { return strtolower((string)($tenant['status'] ?? '')) === 'active'; }));
        $platform['companies_trial'] = count(array_filter($tenants, static function ($tenant) { return in_array(strtolower((string)($tenant['status'] ?? '')), ['trial', 'teste'], true); }));
        dashboardReply(true, [
            'scope' => 'platform',
            'platform' => $platform,
            'companies' => $companies,
            'alerts' => $alerts,
            'maps_pilot' => dashboardMapsPilotMetrics($db, dashboardMapsPilotOwnerId($db, $auth)),
        ]);
    }

    if ($tenantId === null || $tenantId <= 0) {
        dashboardReply(false, null, 'Empresa não identificada para este usuário.', 403);
    }

    $rows = dashboardUsageRows($db, $tenantId, $regularProfile ? (int)$auth['id'] : null);
    if ($regularProfile) {
        $activity = $rows[0] ?? ['maps_searches' => 0, 'cnpj_queries' => 0, 'linkedin_searches' => 0, 'leads_generated' => 0, 'last_activity' => null];
        dashboardReply(true, ['scope' => 'personal', 'activity' => $activity]);
    }

    $tenantStmt = $db->prepare('SELECT id, name, plan, status FROM tenants WHERE id = ? LIMIT 1');
    $tenantStmt->execute([$tenantId]);
    $tenant = $tenantStmt->fetch(PDO::FETCH_ASSOC) ?: ['id' => $tenantId, 'name' => 'Empresa'];
    dashboardReply(true, [
        'scope' => $profile === 'supervisor' ? 'team' : 'company',
        'company' => $tenant,
        'totals' => dashboardTotals($rows),
        'users' => dashboardTeamRows($rows, $profile === 'supervisor'),
    ]);
} catch (Throwable $e) {
    error_log('dashboard-metrics.php: ' . $e->getMessage());
    dashboardReply(false, null, 'Não foi possível carregar as métricas do Dashboard.', 500);
}
