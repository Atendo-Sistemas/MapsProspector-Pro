<?php
/**
 * API de Planos - SaaS
 * Endpoint: /api/plans.php
 * Apenas super_admin pode listar, criar, editar e desativar planos.
 * Planos definem limite de tokens (ex.: buscas) por período; empresas são vinculadas a um plano.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();

$userId = requireSuperAdmin();
$db = Database::getInstance()->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : null;

    if ($id) {
        try {
            $stmt = $db->prepare("SELECT id, name, slug, token_limit, price_monthly, period, status, created_at, updated_at FROM plans WHERE id = ?");
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
        $plan['createdAt'] = $plan['created_at'];
        $plan['updatedAt'] = $plan['updated_at'];
        unset($plan['token_limit'], $plan['price_monthly'], $plan['created_at'], $plan['updated_at']);
        jsonSuccess($plan);
    }

    try {
        $stmt = $db->query("
            SELECT p.id, p.name, p.slug, p.token_limit, p.price_monthly, p.period, p.status, p.created_at,
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
            $row['tenantsCount'] = (int) ($row['tenants_count'] ?? 0);
            $row['createdAt'] = isset($row['created_at']) ? (string) $row['created_at'] : '';
            unset($row['token_limit'], $row['price_monthly'], $row['tenants_count'], $row['created_at']);
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

        $stmt = $db->prepare("INSERT INTO plans (name, slug, token_limit, price_monthly, period, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $tokenLimit, $priceMonthly, $period, $status]);
        $newId = (int) $db->lastInsertId();

        $stmt = $db->prepare("SELECT id, name, slug, token_limit, price_monthly, period, status, created_at FROM plans WHERE id = ?");
        $stmt->execute([$newId]);
        $plan = $stmt->fetch();
        $plan['id'] = (string) $plan['id'];
        $plan['tokenLimit'] = (int) $plan['token_limit'];
        $plan['priceMonthly'] = isset($plan['price_monthly']) ? (float) $plan['price_monthly'] : 0;
        $plan['createdAt'] = $plan['created_at'];
        unset($plan['token_limit'], $plan['price_monthly'], $plan['created_at']);
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

    if (empty($updates)) {
        jsonError('Nenhum campo para atualizar', 400);
    }

    $params[] = $id;
    $sql = "UPDATE plans SET " . implode(', ', $updates) . " WHERE id = ?";
    $db->prepare($sql)->execute($params);

    $stmt = $db->prepare("SELECT id, name, slug, token_limit, price_monthly, period, status, created_at, updated_at FROM plans WHERE id = ?");
    $stmt->execute([$id]);
    $plan = $stmt->fetch();
    $plan['id'] = (string) $plan['id'];
    $plan['tokenLimit'] = (int) $plan['token_limit'];
    $plan['priceMonthly'] = isset($plan['price_monthly']) ? (float) $plan['price_monthly'] : 0;
    $plan['createdAt'] = $plan['created_at'];
    $plan['updatedAt'] = $plan['updated_at'];
    unset($plan['token_limit'], $plan['price_monthly'], $plan['created_at'], $plan['updated_at']);
    jsonSuccess($plan, 'Plano atualizado com sucesso');

} elseif ($method === 'DELETE') {
    // Excluir plano: somente super_admin (já garantido por requireSuperAdmin() no topo do arquivo).
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
