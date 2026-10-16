<?php
/**
 * API de Planos - SaaS
 * Endpoint: /api/plans.php
 * O super_admin e o admin do tenant principal (tenant 1) podem listar, criar, editar e desativar planos.
 * A exceção do tenant 1 é limitada ao catálogo e não concede acesso às demais áreas globais da plataforma.
 * Planos definem limite de tokens (ex.: buscas) por período; empresas são vinculadas a um plano.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/module-access.php';

ob_clean();

$userId = requireAuth();
$catalogUser = getAuthUser();
$catalogProfile = strtolower((string) ($catalogUser['profile'] ?? ''));
$catalogTenantId = (int) ($catalogUser['tenant_id'] ?? 0);
if ($catalogProfile !== 'super_admin' && !($catalogProfile === 'admin' && $catalogTenantId === 1)) {
    jsonError('Acesso negado. Apenas o administrador da plataforma ou o administrador do tenant principal pode gerenciar o catálogo.', 403);
}
$db = Database::getInstance()->getConnection();

function planModuleKeys(PDO $db, int $planId): array
{
    try {
        $stmt = $db->prepare('SELECT module_key FROM plan_modules WHERE plan_id = ? AND is_active = 1 ORDER BY module_key ASC');
        $stmt->execute([$planId]);
        return array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
    } catch (Throwable $e) {
        return [];
    }
}

function savePlanModuleKeys(PDO $db, int $planId, mixed $modules): void
{
    if (!is_array($modules)) return;
    $allowed = array_keys(mpModuleCatalog());
    $keys = array_values(array_unique(array_intersect($allowed, array_map('strval', $modules))));
    $db->prepare('DELETE FROM plan_modules WHERE plan_id = ?')->execute([$planId]);
    $insert = $db->prepare('INSERT INTO plan_modules (plan_id, module_key, is_active) VALUES (?, ?, 1)');
    foreach ($keys as $key) $insert->execute([$planId, $key]);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if ($id) {
        try {
            $stmt = $db->prepare("SELECT id, name, slug, token_limit, price_monthly, period, status, catalog_group, included_users, created_at, updated_at FROM plans WHERE id = ?");
            $stmt->execute([$id]);
            $plan = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("plans.php GET id: " . $e->getMessage());
            jsonError('Erro ao buscar plano. Verifique se a tabela plans existe.', 500);
        }
        if (!$plan) {
            jsonError('Plano não encontrado', 404);
        }
        $plan['id'] = (string) $plan['id'];
        $plan['tokenLimit'] = (int) $plan['token_limit'];
        $plan['priceMonthly'] = isset($plan['price_monthly']) ? (float) $plan['price_monthly'] : 0;
        $plan['includedUsers'] = max(1, (int) ($plan['included_users'] ?? 1));
        $plan['catalogGroup'] = in_array(($plan['catalog_group'] ?? 'current'), ['current', 'suggested'], true) ? $plan['catalog_group'] : 'current';
        $plan['modules'] = planModuleKeys($db, (int) $plan['id']);
        $plan['createdAt'] = $plan['created_at'];
        $plan['updatedAt'] = $plan['updated_at'];
        unset($plan['token_limit'], $plan['price_monthly'], $plan['included_users'], $plan['catalog_group'], $plan['created_at'], $plan['updated_at']);
        jsonSuccess($plan);
    }

    try {
        $stmt = $db->query("
            SELECT p.id, p.name, p.slug, p.token_limit, p.price_monthly, p.period, p.status, p.catalog_group, p.included_users, p.created_at,
                   (SELECT COUNT(*) FROM tenants t WHERE t.plan_id = p.id) as tenants_count
            FROM plans p
            ORDER BY p.token_limit ASC, p.name ASC
        ");
        $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalCount = count($plans);
        foreach ($plans as &$row) {
            $row['id'] = (string) $row['id'];
            $row['tokenLimit'] = (int) $row['token_limit'];
            $row['priceMonthly'] = isset($row['price_monthly']) ? (float) $row['price_monthly'] : 0;
            $row['includedUsers'] = max(1, (int) ($row['included_users'] ?? 1));
            $row['catalogGroup'] = in_array(($row['catalog_group'] ?? 'current'), ['current', 'suggested'], true) ? $row['catalog_group'] : 'current';
            $row['modules'] = planModuleKeys($db, (int) $row['id']);
            $row['tenantsCount'] = (int) ($row['tenants_count'] ?? 0);
            $row['createdAt'] = isset($row['created_at']) ? (string) $row['created_at'] : '';
            unset($row['token_limit'], $row['price_monthly'], $row['included_users'], $row['catalog_group'], $row['tenants_count'], $row['created_at']);
        }
        unset($row);
        jsonSuccess(['items' => $plans, 'total' => $totalCount]);
    } catch (PDOException $e) {
        error_log("plans.php GET list: " . $e->getMessage());
        $msg = $e->getMessage();
        if (strpos($msg, 'exist') !== false || strpos($msg, '1146') !== false) {
            jsonError('Tabela de planos não existe. Execute database_migration_plans.sql no banco de dados.', 500);
        }
        jsonError('Erro ao listar planos.', 500);
    }

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = sanitizeInput($input['name'] ?? '');
    $slug = sanitizeInput($input['slug'] ?? '');
    $tokenLimit = isset($input['tokenLimit']) ? (int) $input['tokenLimit'] : 100;
    $priceMonthly = isset($input['priceMonthly']) ? (float) str_replace(',', '.', $input['priceMonthly']) : 0;
    $period = sanitizeInput($input['period'] ?? 'monthly');
    $status = isset($input['status']) ? sanitizeInput($input['status']) : 'active';
    $includedUsers = max(1, (int) ($input['includedUsers'] ?? 1));
    $catalogGroup = sanitizeInput($input['catalogGroup'] ?? 'current');
    if (!in_array($catalogGroup, ['current', 'suggested'], true)) $catalogGroup = 'current';
    $modulesInput = $input['modules'] ?? null;

    if (empty($name)) {
        jsonError('Nome do plano é obrigatório', 400);
    }

    if (empty($slug)) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slug = trim($slug, '-');
    }
    if (empty($slug)) {
        jsonError('Slug inválido. Informe um identificador único.', 400);
    }

    if ($tokenLimit < 0) {
        $tokenLimit = 0;
    }
    $allowedPeriod = ['monthly', 'yearly'];
    if (!in_array($period, $allowedPeriod)) {
        $period = 'monthly';
    }
    $allowedStatus = ['active', 'inactive'];
    if (!in_array($status, $allowedStatus)) {
        $status = 'active';
    }

    try {
        $stmt = $db->prepare("SELECT id FROM plans WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            jsonError('Já existe um plano com este identificador (slug). Escolha outro.', 400);
        }

        $stmt = $db->prepare("INSERT INTO plans (name, slug, token_limit, price_monthly, period, status, catalog_group, included_users) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $tokenLimit, $priceMonthly, $period, $status, $catalogGroup, $includedUsers]);
        $newId = (int) $db->lastInsertId();
        savePlanModuleKeys($db, $newId, $modulesInput);

        $stmt = $db->prepare("SELECT id, name, slug, token_limit, price_monthly, period, status, catalog_group, included_users, created_at FROM plans WHERE id = ?");
        $stmt->execute([$newId]);
        $plan = $stmt->fetch();
        $plan['id'] = (string) $plan['id'];
        $plan['tokenLimit'] = (int) $plan['token_limit'];
        $plan['priceMonthly'] = isset($plan['price_monthly']) ? (float) $plan['price_monthly'] : 0;
        $plan['includedUsers'] = max(1, (int) ($plan['included_users'] ?? 1));
        $plan['catalogGroup'] = in_array(($plan['catalog_group'] ?? 'current'), ['current', 'suggested'], true) ? $plan['catalog_group'] : 'current';
        $plan['modules'] = planModuleKeys($db, (int) $plan['id']);
        $plan['createdAt'] = $plan['created_at'];
        unset($plan['token_limit'], $plan['price_monthly'], $plan['included_users'], $plan['catalog_group'], $plan['created_at']);
        jsonSuccess($plan, 'Plano criado com sucesso');
    } catch (PDOException $e) {
        error_log("Erro ao criar plano: " . $e->getMessage());
        jsonError('Erro ao criar plano. Execute database_migration_plans.sql se a tabela plans não existir.', 500);
    }

} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if (!$id) {
        jsonError('ID do plano é obrigatório', 400);
    }

    $name = sanitizeInput($input['name'] ?? '');
    $slug = sanitizeInput($input['slug'] ?? '');
    $tokenLimit = isset($input['tokenLimit']) ? (int) $input['tokenLimit'] : null;
    $priceMonthly = isset($input['priceMonthly']) ? (float) str_replace(',', '.', $input['priceMonthly']) : null;
    $period = isset($input['period']) ? sanitizeInput($input['period']) : null;
    $status = isset($input['status']) ? sanitizeInput($input['status']) : null;
    $includedUsers = array_key_exists('includedUsers', $input) ? max(1, (int) $input['includedUsers']) : null;
    $catalogGroup = array_key_exists('catalogGroup', $input) ? sanitizeInput($input['catalogGroup']) : null;
    if ($catalogGroup !== null && !in_array($catalogGroup, ['current', 'suggested'], true)) $catalogGroup = 'current';
    $modulesInput = array_key_exists('modules', $input) ? $input['modules'] : null;

    $stmt = $db->prepare("SELECT id FROM plans WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonError('Plano não encontrado', 404);
    }

    $updates = [];
    $params = [];
    if ($name !== '') {
        $updates[] = "name = ?";
        $params[] = $name;
    }
    if ($slug !== '') {
        $stmt = $db->prepare("SELECT id FROM plans WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            jsonError('Já existe outro plano com este identificador (slug).', 400);
        }
        $updates[] = "slug = ?";
        $params[] = $slug;
    }
    if ($tokenLimit !== null && $tokenLimit >= 0) {
        $updates[] = "token_limit = ?";
        $params[] = $tokenLimit;
    }
    if ($priceMonthly !== null) {
        $updates[] = "price_monthly = ?";
        $params[] = $priceMonthly;
    }
    if ($period !== null && in_array($period, ['monthly', 'yearly'])) {
        $updates[] = "period = ?";
        $params[] = $period;
    }
    if ($status !== null && in_array($status, ['active', 'inactive'])) {
        $updates[] = "status = ?";
        $params[] = $status;
    }
    if ($includedUsers !== null) {
        $updates[] = "included_users = ?";
        $params[] = $includedUsers;
    }
    if ($catalogGroup !== null) {
        $updates[] = "catalog_group = ?";
        $params[] = $catalogGroup;
    }

    if (empty($updates) && !is_array($modulesInput)) {
        jsonError('Nenhum campo para atualizar', 400);
    }

    if ($updates) {
        $params[] = $id;
        $sql = "UPDATE plans SET " . implode(', ', $updates) . " WHERE id = ?";
        $db->prepare($sql)->execute($params);
    }
    if (is_array($modulesInput)) {
        savePlanModuleKeys($db, $id, $modulesInput);
    }

    $stmt = $db->prepare("SELECT id, name, slug, token_limit, price_monthly, period, status, catalog_group, included_users, created_at, updated_at FROM plans WHERE id = ?");
    $stmt->execute([$id]);
    $plan = $stmt->fetch();
    $plan['id'] = (string) $plan['id'];
    $plan['tokenLimit'] = (int) $plan['token_limit'];
    $plan['priceMonthly'] = isset($plan['price_monthly']) ? (float) $plan['price_monthly'] : 0;
    $plan['includedUsers'] = max(1, (int) ($plan['included_users'] ?? 1));
    $plan['catalogGroup'] = in_array(($plan['catalog_group'] ?? 'current'), ['current', 'suggested'], true) ? $plan['catalog_group'] : 'current';
    $plan['modules'] = planModuleKeys($db, (int) $plan['id']);
    $plan['createdAt'] = $plan['created_at'];
    $plan['updatedAt'] = $plan['updated_at'];
    unset($plan['token_limit'], $plan['price_monthly'], $plan['included_users'], $plan['catalog_group'], $plan['created_at'], $plan['updated_at']);
    jsonSuccess($plan, 'Plano atualizado com sucesso');

} elseif ($method === 'DELETE') {
    // A exclusão permanece protegida pela autorização do catálogo no topo do arquivo.
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if (!$id) {
        jsonError('ID do plano é obrigatório', 400);
    }
    if ($id === 1) {
        jsonError('Não é permitido excluir o plano padrão (Básico).', 400);
    }

    $stmt = $db->prepare("SELECT id, slug FROM plans WHERE id = ?");
    $stmt->execute([$id]);
    $planRow = $stmt->fetch();
    if (!$planRow) {
        jsonError('Plano não encontrado', 404);
    }
    if (isset($planRow['slug']) && $planRow['slug'] === 'trial') {
        jsonError('Não é permitido excluir o plano Período de teste (trial).', 400);
    }

    $stmt = $db->prepare("SELECT COUNT(*) FROM tenants WHERE plan_id = ?");
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        jsonError('Existem empresas vinculadas a este plano. Altere o plano delas antes de excluir.', 400);
    }

    $db->prepare("DELETE FROM plans WHERE id = ?")->execute([$id]);
    jsonSuccess(null, 'Plano removido com sucesso');

} else {
    jsonError('Método não permitido', 405);
}
