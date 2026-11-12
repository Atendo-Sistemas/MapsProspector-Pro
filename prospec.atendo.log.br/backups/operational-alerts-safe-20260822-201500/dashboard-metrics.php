<?php
declare(strict_types=1);

/**
 * Métricas de adoção e atividade do painel.
 * A visão global só é exposta ao super administrador; os demais perfis recebem
 * somente métricas do próprio usuário ou de sua empresa, conforme o cargo.
 */
ob_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function dashboardResponse(array $data, int $status = 200): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function dashboardHasTable(PDO $db, string $table): bool
{
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $error) {
        return false;
    }
}

function dashboardHasColumn(PDO $db, string $table, string $column): bool
{
    try {
        $stmt = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $error) {
        return false;
    }
}

function dashboardScalar(PDO $db, string $sql, array $params = []): int
{
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    } catch (Throwable $error) {
        error_log('dashboard-metrics: ' . $error->getMessage());
        return 0;
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        dashboardResponse(['success' => false, 'error' => 'Método não permitido.'], 405);
    }

    $userId = requireAuth();
    $authUser = getAuthUser();
    $db = Database::getInstance()->getConnection();
    $profile = strtolower((string) ($authUser['profile'] ?? 'user'));
    $tenantId = isset($authUser['tenant_id']) && $authUser['tenant_id'] !== null ? (int) $authUser['tenant_id'] : null;
    $hasUsers = dashboardHasTable($db, 'users');
    $hasTenants = dashboardHasTable($db, 'tenants');
    $hasSearchHistory = dashboardHasTable($db, 'search_history');
    $hasLinkedinRuns = dashboardHasTable($db, 'linkedin_runs');
    $hasLastActivity = $hasUsers && dashboardHasColumn($db, 'users', 'last_activity');

    // Registra a atividade do usuário atual quando a coluna já existe. Nunca
    // cria esquema automaticamente para não causar alteração estrutural em uso.
    if ($hasLastActivity) {
        try {
            $touch = $db->prepare('UPDATE users SET last_activity = NOW() WHERE id = ?');
            $touch->execute([(int) $userId]);
        } catch (Throwable $error) {
            error_log('dashboard-metrics touch: ' . $error->getMessage());
        }
    }

    if ($profile === 'super_admin') {
        $companiesTotal = $hasTenants
            ? dashboardScalar($db, 'SELECT COUNT(*) FROM tenants')
            : ($hasUsers ? dashboardScalar($db, 'SELECT COUNT(DISTINCT tenant_id) FROM users WHERE tenant_id IS NOT NULL') : 0);
        $companiesActive = $companiesTotal;
        if ($hasTenants && dashboardHasColumn($db, 'tenants', 'status')) {
            $companiesActive = dashboardScalar($db, "SELECT COUNT(*) FROM tenants WHERE COALESCE(status, 'active') IN ('active', 'trial', 'enabled')");
        }
        $totalUsers = $hasUsers ? dashboardScalar($db, "SELECT COUNT(*) FROM users WHERE LOWER(COALESCE(profile, 'user')) <> 'super_admin'") : 0;
        $onlineUsers = $hasLastActivity
            ? dashboardScalar($db, "SELECT COUNT(*) FROM users WHERE LOWER(COALESCE(profile, 'user')) <> 'super_admin' AND last_activity >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)")
            : 0;
        $mapsSearches = $hasSearchHistory ? dashboardScalar($db, 'SELECT COUNT(*) FROM search_history') : 0;
        $leadsGenerated = ($hasSearchHistory && dashboardHasColumn($db, 'search_history', 'results_count'))
            ? dashboardScalar($db, 'SELECT COALESCE(SUM(results_count), 0) FROM search_history') : 0;
        $linkedinSearches = $hasLinkedinRuns ? dashboardScalar($db, "SELECT COUNT(*) FROM linkedin_runs WHERE status IN ('done', 'completed')") : 0;

        $companies = [];
        if ($hasTenants) {
            try {
                $nameColumn = dashboardHasColumn($db, 'tenants', 'name') ? 't.name' : "CONCAT('Empresa #', t.id)";
                $planColumn = dashboardHasColumn($db, 'tenants', 'plan') ? 't.plan' : "'Plano não informado'";
                $stmt = $db->query("SELECT t.id, {$nameColumn} AS name, {$planColumn} AS plan, COUNT(u.id) AS users FROM tenants t LEFT JOIN users u ON u.tenant_id = t.id GROUP BY t.id, name, plan ORDER BY users DESC, name ASC LIMIT 10");
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                    $companies[] = [
                        'name' => (string) ($row['name'] ?? 'Empresa'),
                        'plan' => (string) ($row['plan'] ?? ''),
                        'users' => (int) ($row['users'] ?? 0),
                        'leads_generated' => 0,
                        'credits' => 0,
                    ];
                }
            } catch (Throwable $error) {
                error_log('dashboard-metrics companies: ' . $error->getMessage());
            }
        }

        dashboardResponse(['success' => true, 'data' => [
            'scope' => 'platform',
            'platform' => [
                'companies_total' => $companiesTotal,
                'companies_active' => $companiesActive,
                'total_users' => $totalUsers,
                'online_users' => $onlineUsers,
                'online_window_minutes' => 15,
                'online_tracking_available' => $hasLastActivity,
                'maps_searches' => $mapsSearches,
                'cnpj_queries' => 0,
                'linkedin_searches' => $linkedinSearches,
                'leads_generated' => $leadsGenerated,
            ],
            'companies' => $companies,
            'alerts' => [],
        ]]);
    }

    $mapsSearches = $hasSearchHistory ? dashboardScalar($db, 'SELECT COUNT(*) FROM search_history WHERE user_id = ?', [(int) $userId]) : 0;
    $linkedinSearches = ($hasLinkedinRuns && dashboardHasColumn($db, 'linkedin_runs', 'user_id'))
        ? dashboardScalar($db, "SELECT COUNT(*) FROM linkedin_runs WHERE user_id = ? AND status IN ('done', 'completed')", [(int) $userId]) : 0;
    $leads = ($hasSearchHistory && dashboardHasColumn($db, 'search_history', 'results_count'))
        ? dashboardScalar($db, 'SELECT COALESCE(SUM(results_count), 0) FROM search_history WHERE user_id = ?', [(int) $userId]) : 0;
    dashboardResponse(['success' => true, 'data' => [
        'scope' => 'personal',
        'activity' => [
            'maps_searches' => $mapsSearches,
            'cnpj_queries' => 0,
            'linkedin_searches' => $linkedinSearches,
            'leads_generated' => $leads,
            'last_activity' => null,
        ],
        'alerts' => [],
    ]]);
} catch (Throwable $error) {
    error_log('dashboard-metrics fatal: ' . $error->getMessage());
    dashboardResponse(['success' => false, 'error' => 'Não foi possível carregar as métricas do dashboard.'], 500);
}
