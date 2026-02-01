<?php
/**
 * API de Solicitação de Plano
 * Endpoint: /api/plan-requests.php
 * Cliente solicita um plano; Super Admin confirma ou recusa. Ao confirmar, tenant.plan_id é atualizado.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();

$method = $_SERVER['REQUEST_METHOD'];

try {
    $userId = requireAuth();
    $db = Database::getInstance()->getConnection();
    $authUser = getAuthUser();
    $profile = $authUser['profile'] ?? 'user';
    $tenantId = $authUser['tenant_id'] ?? null;
    $isSuperAdmin = (strtolower($profile) === 'super_admin');
} catch (Exception $e) {
    jsonError('Não autenticado.', 401);
}

if ($method === 'GET') {
    try {
        if ($isSuperAdmin) {
            $stmt = $db->query("
                SELECT pr.id, pr.tenant_id, pr.requested_by_user_id, pr.plan_id, pr.status, pr.created_at, pr.reviewed_at,
                       t.name as tenant_name,
                       u.name as requested_by_name, u.email as requested_by_email,
                       p.name as plan_name, p.token_limit as plan_token_limit, p.price_monthly as plan_price
                FROM plan_requests pr
                INNER JOIN tenants t ON t.id = pr.tenant_id
                INNER JOIN users u ON u.id = pr.requested_by_user_id
                INNER JOIN plans p ON p.id = pr.plan_id
                ORDER BY pr.status = 'pending' DESC, pr.created_at DESC
            ");
        } else {
            if (!$tenantId) {
                jsonSuccess(['items' => [], 'total' => 0]);
                exit;
            }
            $stmt = $db->prepare("
                SELECT pr.id, pr.tenant_id, pr.requested_by_user_id, pr.plan_id, pr.status, pr.created_at, pr.reviewed_at,
                       p.name as plan_name, p.token_limit as plan_token_limit, p.price_monthly as plan_price
                FROM plan_requests pr
                INNER JOIN plans p ON p.id = pr.plan_id
                WHERE pr.tenant_id = ?
                ORDER BY pr.created_at DESC
            ");
            $stmt->execute([$tenantId]);
        }
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $items = [];
        foreach ($rows as $r) {
            $items[] = [
                'id' => (string) $r['id'],
                'tenantId' => (string) $r['tenant_id'],
                'tenantName' => $r['tenant_name'] ?? null,
                'requestedByUserId' => (string) $r['requested_by_user_id'],
                'requestedByName' => $r['requested_by_name'] ?? null,
                'requestedByEmail' => $r['requested_by_email'] ?? null,
                'planId' => (string) $r['plan_id'],
                'planName' => $r['plan_name'] ?? null,
                'planTokenLimit' => isset($r['plan_token_limit']) ? (int) $r['plan_token_limit'] : 0,
                'planPrice' => isset($r['plan_price']) ? (float) $r['plan_price'] : 0,
                'status' => $r['status'],
                'createdAt' => $r['created_at'],
                'reviewedAt' => $r['reviewed_at'] ?? null,
            ];
        }
        jsonSuccess(['items' => $items, 'total' => count($items)]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'exist') !== false || strpos($e->getMessage(), '1146') !== false) {
            jsonSuccess(['items' => [], 'total' => 0]);
            exit;
        }
        error_log("plan-requests GET: " . $e->getMessage());
        jsonError('Erro ao listar solicitações de plano. Execute database_migration_plan_requests.sql no banco.', 500);
    }
} elseif ($method === 'POST') {
    if ($tenantId === null) {
        jsonError('Apenas usuários vinculados a uma empresa podem solicitar plano.', 403);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $planId = isset($input['planId']) ? (int) $input['planId'] : 0;
    if ($planId < 1) {
        jsonError('Informe o ID do plano desejado.', 400);
    }
    try {
        $stmt = $db->prepare("SELECT id FROM plans WHERE id = ? AND status = 'active'");
        $stmt->execute([$planId]);
        if (!$stmt->fetch()) {
            jsonError('Plano não encontrado ou inativo.', 400);
        }
        $stmt = $db->prepare("INSERT INTO plan_requests (tenant_id, requested_by_user_id, plan_id, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$tenantId, $userId, $planId]);
        $newId = (int) $db->lastInsertId();
        jsonSuccess(['id' => (string) $newId, 'status' => 'pending'], 'Solicitação de plano enviada. Aguarde a confirmação do administrador.');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'exist') !== false || strpos($e->getMessage(), '1146') !== false) {
            jsonError('Tabela de solicitações de plano não existe. Execute database_migration_plan_requests.sql no banco.', 500);
        }
        error_log("plan-requests POST: " . $e->getMessage());
        jsonError('Erro ao criar solicitação.', 500);
    }
} elseif ($method === 'PUT') {
    if (!$isSuperAdmin) {
        jsonError('Apenas o administrador da plataforma pode confirmar ou recusar solicitações de plano.', 403);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    $status = isset($input['status']) ? trim(strtolower($input['status'])) : '';
    if (!$id || !in_array($status, ['approved', 'rejected'], true)) {
        jsonError('Informe o id da solicitação e o status (approved ou rejected).', 400);
    }
    try {
        $stmt = $db->prepare("SELECT id, tenant_id, plan_id, status FROM plan_requests WHERE id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) {
            jsonError('Solicitação não encontrada.', 404);
        }
        if ($req['status'] !== 'pending') {
            jsonError('Esta solicitação já foi avaliada.', 400);
        }
        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE plan_requests SET status = ?, reviewed_at = NOW(), reviewed_by_user_id = ? WHERE id = ?");
        $stmt->execute([$status, $userId, $id]);
        if ($status === 'approved') {
            $tid = (int) $req['tenant_id'];
            $planId = (int) $req['plan_id'];
$stmt = $db->prepare("UPDATE tenants SET plan_id = ? WHERE id = ?");
        $stmt->execute([$planId, $tid]);
        }
        $db->commit();
        jsonSuccess(['id' => (string) $id, 'status' => $status], $status === 'approved' ? 'Plano confirmado e vinculado à empresa.' : 'Solicitação recusada.');
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("plan-requests PUT: " . $e->getMessage());
        jsonError('Erro ao processar solicitação.', 500);
    }
} else {
    jsonError('Método não permitido', 405);
}
