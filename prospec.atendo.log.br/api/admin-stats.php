<?php
/**
 * API de Estatísticas Globais para o Super Admin (Dashboard)
 * Endpoint: /api/admin-stats.php
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
ob_clean();

$userId = requireAuth();
$authUser = getAuthUser();
$profile = strtolower((string)($authUser['profile'] ?? ''));

if ($profile !== 'super_admin') {
    jsonError('Acesso restrito ao Super Admin.', 403);
}

$db = Database::getInstance()->getConnection();

try {
    // 1. Total de Empresas (Tenants) ativas e suspensas (ignorando a ID 1)
    $stmtTenants = $db->query("
        SELECT 
            COUNT(*) as total_companies,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_companies,
            SUM(CASE WHEN status = 'suspended' THEN 1 ELSE 0 END) as suspended_companies
        FROM tenants 
        WHERE id != 1
    ");
    $tenantStats = $stmtTenants->fetch(PDO::FETCH_ASSOC);

    // 2. Total de Usuários cadastrados na plataforma
    $stmtUsers = $db->query("SELECT COUNT(*) as total_users FROM users WHERE LOWER(profile) != 'super_admin'");
    $userStats = $stmtUsers->fetch(PDO::FETCH_ASSOC);

    // 3. Total de Buscas e Leads gerados na plataforma
    $stmtSearches = $db->query("SELECT COUNT(*) as total_searches, SUM(results_count) as total_leads FROM search_history");
    $searchStats = $stmtSearches->fetch(PDO::FETCH_ASSOC);

    // 4. Status das Chaves da Apify (Rotação)
    $stmtApify = $db->query("
        SELECT 
            COUNT(*) as total_keys,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_keys,
            SUM(tokens_used) as total_apify_used
        FROM apify_keys
    ");
    $apifyStats = $stmtApify->fetch(PDO::FETCH_ASSOC);

    // 5. Solicitações pendentes (Planos e Créditos)
    $stmtPlanReq = $db->query("SELECT COUNT(*) as pending_plans FROM plan_requests WHERE status = 'pending'");
    $pendingPlans = $stmtPlanReq->fetch(PDO::FETCH_ASSOC)['pending_plans'] ?? 0;

    $stmtCredReq = $db->query("SELECT COUNT(*) as pending_credits FROM credit_requests WHERE status = 'pending'");
    $pendingCredits = $stmtCredReq->fetch(PDO::FETCH_ASSOC)['pending_credits'] ?? 0;

    jsonSuccess([
        'companies' => [
            'total' => (int)($tenantStats['total_companies'] ?? 0),
            'active' => (int)($tenantStats['active_companies'] ?? 0),
            'suspended' => (int)($tenantStats['suspended_companies'] ?? 0)
        ],
        'users' => (int)($userStats['total_users'] ?? 0),
        'searches' => (int)($searchStats['total_searches'] ?? 0),
        'leads' => (int)($searchStats['total_leads'] ?? 0),
        'apify' => [
            'totalKeys' => (int)($apifyStats['total_keys'] ?? 0),
            'activeKeys' => (int)($apifyStats['active_keys'] ?? 0),
            'totalUsed' => (int)($apifyStats['total_apify_used'] ?? 0)
        ],
        'pendingRequests' => (int)($pendingPlans + $pendingCredits)
    ]);

} catch (Exception $e) {
    jsonError('Erro ao carregar estatísticas: ' . $e->getMessage(), 500);
}