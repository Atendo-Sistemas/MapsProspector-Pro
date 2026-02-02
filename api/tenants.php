<?php
/**
 * API de Empresas (Tenants) - SaaS
 * Endpoint: /api/tenants.php
 * Apenas super_admin pode listar, criar, editar e desativar empresas.
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
            $stmt = $db->prepare("
                SELECT t.id, t.name, t.slug, t.plan_id, t.status, t.created_at, t.updated_at,
                       p.name as plan_name, p.token_limit as plan_token_limit,
                       (SELECT u.email FROM users u WHERE u.tenant_id = t.id ORDER BY (u.profile = 'admin') DESC, u.id ASC LIMIT 1) as admin_email
                FROM tenants t
                LEFT JOIN plans p ON p.id = t.plan_id
                WHERE t.id = ?
            ");
            $stmt->execute([$id]);
            $tenant = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("tenants.php GET id: " . $e->getMessage());
            jsonError('Erro ao buscar empresa. Verifique se a tabela tenants existe.', 500);
        }
        if (!$tenant) {
            jsonError('Empresa não encontrada', 404);
        }
        $tenant['id'] = (string) $tenant['id'];
        $tenant['planId'] = isset($tenant['plan_id']) ? (string) $tenant['plan_id'] : '1';
        $tenant['plan'] = $tenant['plan_name'] ?? 'Básico';
        $tenant['planTokenLimit'] = isset($tenant['plan_token_limit']) ? (int) $tenant['plan_token_limit'] : 100;
        $tenant['email'] = isset($tenant['admin_email']) ? trim($tenant['admin_email']) : '';
        $tenant['createdAt'] = $tenant['created_at'];
        $tenant['updatedAt'] = $tenant['updated_at'];
        unset($tenant['plan_id'], $tenant['plan_name'], $tenant['plan_token_limit'], $tenant['admin_email'], $tenant['created_at'], $tenant['updated_at']);
        jsonSuccess($tenant);
    }

    try {
        $stmt = $db->query("
            SELECT t.id, t.name, t.slug, t.plan_id, t.status, t.created_at,
                   p.name as plan_name, p.token_limit as plan_token_limit,
                   (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) as users_count,
                   (SELECT u.email FROM users u WHERE u.tenant_id = t.id ORDER BY (u.profile = 'admin') DESC, u.id ASC LIMIT 1) as admin_email
            FROM tenants t
            LEFT JOIN plans p ON p.id = t.plan_id
            WHERE t.id != 1
            ORDER BY t.created_at DESC, t.name ASC
        ");
        $tenants = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $totalCount = count($tenants);
        foreach ($tenants as &$row) {
            $row['id'] = (string) $row['id'];
            $row['planId'] = isset($row['plan_id']) ? (string) $row['plan_id'] : '1';
            $row['plan'] = $row['plan_name'] ?? 'Básico';
            $row['planTokenLimit'] = isset($row['plan_token_limit']) ? (int) $row['plan_token_limit'] : 100;
            $row['usersCount'] = (int) ($row['users_count'] ?? 0);
            $row['email'] = isset($row['admin_email']) ? trim($row['admin_email']) : '';
            $row['createdAt'] = isset($row['created_at']) ? (string) $row['created_at'] : '';
            unset($row['plan_id'], $row['plan_name'], $row['plan_token_limit'], $row['users_count'], $row['admin_email'], $row['created_at']);
        }
        unset($row);
        jsonSuccess(['items' => $tenants, 'total' => $totalCount]);
    } catch (PDOException $e) {
        error_log("tenants.php GET list: " . $e->getMessage());
        $msg = $e->getMessage();
        if (strpos($msg, 'exist') !== false || strpos($msg, '1146') !== false) {
            jsonError('Tabela de empresas não existe. Execute database_migration_saas.sql no banco de dados.', 500);
        }
        jsonError('Erro ao listar empresas. Execute a migração database_migration_saas.sql no banco de dados.', 500);
    }

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $name = sanitizeInput($input['name'] ?? '');
    $slug = sanitizeInput($input['slug'] ?? '');
    $planId = isset($input['planId']) ? (int) $input['planId'] : 1;
    $status = isset($input['status']) ? sanitizeInput($input['status']) : 'active';

    if (empty($name)) {
        jsonError('Nome da empresa é obrigatório', 400);
    }

    if (empty($slug)) {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slug = trim($slug, '-');
    }
    if (empty($slug)) {
        jsonError('Slug inválido. Informe um identificador único (ex: minha-empresa).', 400);
    }

    $allowedStatus = ['active', 'suspended'];
    if (!in_array($status, $allowedStatus)) {
        $status = 'active';
    }

    try {
        $stmt = $db->prepare("SELECT id FROM plans WHERE id = ? AND status = 'active'");
        $stmt->execute([$planId]);
        if (!$stmt->fetch()) {
            jsonError('Plano selecionado não existe ou está inativo. Escolha um plano válido.', 400);
        }

        $stmt = $db->prepare("SELECT id FROM tenants WHERE slug = ?");
        $stmt->execute([$slug]);
        if ($stmt->fetch()) {
            jsonError('Já existe uma empresa com este identificador (slug). Escolha outro.', 400);
        }

        $stmt = $db->prepare("INSERT INTO tenants (name, slug, plan_id, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$name, $slug, $planId, $status]);
        $newId = (int) $db->lastInsertId();

        $stmt = $db->prepare("
            SELECT t.id, t.name, t.slug, t.plan_id, t.status, t.created_at, p.name as plan_name, p.token_limit as plan_token_limit
            FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ?
        ");
        $stmt->execute([$newId]);
        $tenant = $stmt->fetch();
        $tenant['id'] = (string) $tenant['id'];
        $tenant['planId'] = (string) $tenant['plan_id'];
        $tenant['plan'] = $tenant['plan_name'] ?? 'Básico';
        $tenant['planTokenLimit'] = isset($tenant['plan_token_limit']) ? (int) $tenant['plan_token_limit'] : 100;
        $tenant['createdAt'] = $tenant['created_at'];
        unset($tenant['plan_id'], $tenant['plan_name'], $tenant['plan_token_limit'], $tenant['created_at']);
        jsonSuccess($tenant, 'Empresa criada com sucesso');
    } catch (PDOException $e) {
        error_log("Erro ao criar tenant: " . $e->getMessage());
        jsonError('Erro ao criar empresa. Execute database_migration_plans.sql se a coluna plan_id não existir.', 500);
    }

} elseif ($method === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    if (!$id) {
        jsonError('ID da empresa é obrigatório', 400);
    }

    $name = sanitizeInput($input['name'] ?? '');
    $slug = sanitizeInput($input['slug'] ?? '');
    $planId = isset($input['planId']) ? (int) $input['planId'] : null;
    $status = isset($input['status']) ? sanitizeInput($input['status']) : null;
    $emailRaw = isset($input['email']) ? trim(strtolower($input['email'])) : '';

    $stmt = $db->prepare("SELECT id FROM tenants WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        jsonError('Empresa não encontrada', 404);
    }

    $updates = [];
    $params = [];
    if ($name !== '') {
        $updates[] = "name = ?";
        $params[] = $name;
    }
    if ($slug !== '') {
        $stmt = $db->prepare("SELECT id FROM tenants WHERE slug = ? AND id != ?");
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            jsonError('Já existe outra empresa com este identificador (slug).', 400);
        }
        $updates[] = "slug = ?";
        $params[] = $slug;
    }
    if ($planId !== null && $planId > 0) {
        $stmt = $db->prepare("SELECT id FROM plans WHERE id = ? AND status = 'active'");
        $stmt->execute([$planId]);
        if (!$stmt->fetch()) {
            jsonError('Plano selecionado não existe ou está inativo.', 400);
        }
        $updates[] = "plan_id = ?";
        $params[] = $planId;
    }
    if ($status !== null && in_array($status, ['active', 'suspended'])) {
        $updates[] = "status = ?";
        $params[] = $status;
    }

    if ($emailRaw !== '') {
        if (!validateEmail($emailRaw)) {
            jsonError('E-mail inválido.', 400);
        }
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND tenant_id = ?");
        $stmt->execute([$emailRaw, $id]);
        $currentUser = $stmt->fetch();
        $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND (tenant_id IS NULL OR tenant_id != ?)");
        $stmt->execute([$emailRaw, $id]);
        if ($stmt->fetch()) {
            jsonError('Este e-mail já está em uso por outra conta.', 400);
        }
        $adminUser = $db->prepare("SELECT id FROM users WHERE tenant_id = ? ORDER BY (profile = 'admin') DESC, id ASC LIMIT 1");
        $adminUser->execute([$id]);
        $adminRow = $adminUser->fetch();
        if ($adminRow) {
            $db->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$emailRaw, $adminRow['id']]);
        }
    }

    if (empty($updates)) {
        if ($emailRaw === '') {
            jsonError('Nenhum campo para atualizar', 400);
        }
    } else {
        $params[] = $id;
        $sql = "UPDATE tenants SET " . implode(', ', $updates) . " WHERE id = ?";
        $db->prepare($sql)->execute($params);
    }

    $stmt = $db->prepare("
        SELECT t.id, t.name, t.slug, t.plan_id, t.status, t.created_at, t.updated_at, p.name as plan_name, p.token_limit as plan_token_limit,
               (SELECT u.email FROM users u WHERE u.tenant_id = t.id ORDER BY (u.profile = 'admin') DESC, u.id ASC LIMIT 1) as admin_email
        FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ?
    ");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();
    $tenant['id'] = (string) $tenant['id'];
    $tenant['planId'] = isset($tenant['plan_id']) ? (string) $tenant['plan_id'] : '1';
    $tenant['plan'] = $tenant['plan_name'] ?? 'Básico';
    $tenant['planTokenLimit'] = isset($tenant['plan_token_limit']) ? (int) $tenant['plan_token_limit'] : 100;
    $tenant['email'] = isset($tenant['admin_email']) ? trim($tenant['admin_email']) : '';
    $tenant['createdAt'] = $tenant['created_at'];
    $tenant['updatedAt'] = $tenant['updated_at'];
    unset($tenant['plan_id'], $tenant['plan_name'], $tenant['plan_token_limit'], $tenant['admin_email'], $tenant['created_at'], $tenant['updated_at']);
    jsonSuccess($tenant, 'Empresa atualizada com sucesso');

} elseif ($method === 'DELETE') {
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if (!$id) {
        jsonError('ID da empresa é obrigatório', 400);
    }
    if ($id === 1) {
        jsonError('Não é permitido excluir a empresa padrão.', 400);
    }

    $stmt = $db->prepare("SELECT id, status FROM tenants WHERE id = ?");
    $stmt->execute([$id]);
    $tenant = $stmt->fetch();
    if (!$tenant) {
        jsonError('Empresa não encontrada', 404);
    }
    if (($tenant['status'] ?? '') !== 'suspended') {
        jsonError('Só é possível excluir uma empresa que esteja desativada. Desative-a antes de excluir.', 400);
    }

    $db->prepare("UPDATE users SET tenant_id = 1 WHERE tenant_id = ?")->execute([$id]);
    $db->prepare("DELETE FROM tenants WHERE id = ?")->execute([$id]);
    jsonSuccess(null, 'Empresa removida com sucesso');

} else {
    jsonError('Método não permitido', 405);
}
