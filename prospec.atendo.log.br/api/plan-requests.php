<?php
/**
 * API de Solicitação de Plano com Notificações Integradas
 * Endpoint: /api/plan-requests.php
 * Cliente solicita um plano; Super Admin confirma ou recusa. Ao confirmar, tenant.plan_id é atualizado.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/PaymentBonusService.php';
require_once __DIR__ . '/../services/AsaasPaymentGateway.php';
require_once __DIR__ . '/../services/ManualPaymentRequest.php';

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
    if ($isSuperAdmin) {
        jsonError('Super Admin não pode solicitar mudança de plano; a conta é ilimitada.', 403);
    }
    if ($tenantId === null) {
        jsonError('Apenas usuários vinculados a uma empresa podem solicitar plano.', 403);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $planId = isset($input['planId']) ? (int) $input['planId'] : 0;
    if ($planId < 1) {
        jsonError('Informe o ID do plano desejado.', 400);
    }
    try {
        $stmt = $db->prepare("SELECT id, name FROM plans WHERE id = ? AND status = 'active'");
        $stmt->execute([$planId]);
        $planRow = $stmt->fetch();
        if (!$planRow) {
            jsonError('Plano não encontrado ou inativo.', 400);
        }
        $pending = $db->prepare("SELECT id FROM plan_requests WHERE tenant_id = ? AND plan_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        $pending->execute([(int) $tenantId, $planId]);
        $pendingId = $pending->fetchColumn();
        if ($pendingId) {
            jsonSuccess(
                ['id' => (string) $pendingId, 'status' => 'pending', 'mode' => 'manual'],
                'Já existe uma solicitação manual pendente para análise.'
            );
        }
        if (class_exists('MapsProspectorAsaasPaymentGateway') && MapsProspectorAsaasPaymentGateway::isOperational($db)) {
            try {
                $created = MapsProspectorAsaasPaymentGateway::createPlan($db, $authUser, (int) $tenantId, $planId);
                jsonSuccess($created, 'Assinatura do plano criada no Asaas. Abra o link para concluir o primeiro pagamento.');
            } catch (Throwable $e) {
                error_log('plan-requests automatic Asaas creation: ' . $e->getMessage());
                jsonError('O Asaas está configurado, mas não foi possível criar a assinatura agora. Nenhuma solicitação manual foi aberta automaticamente.', 502);
            }
        }

        $manual = mapsCreateManualPaymentRequest($db, $authUser, (int) $tenantId, 'plan', 0, $planId);
        jsonSuccess(
            ['id' => (string) ($manual['requestId'] ?? ''), 'status' => 'pending', 'mode' => 'manual'],
            !empty($manual['alreadyPending'])
                ? 'Já existe uma solicitação manual pendente para análise.'
                : 'Solicitação de plano enviada. Aguarde a confirmação do administrador.'
        );
    } catch (InvalidArgumentException $e) {
        jsonError($e->getMessage(), 409);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'exist') !== false || strpos($e->getMessage(), '1146') !== false) {
            jsonError('Tabela de solicitações de plano não existe. Execute database_migration_plan_requests.sql no banco.', 500);
        }
        error_log("plan-requests POST: " . $e->getMessage());
        jsonError('Erro ao criar solicitação.', 500);
    } catch (Throwable $e) {
        error_log("plan-requests POST unexpected: " . $e->getMessage());
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
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT id, tenant_id, plan_id, requested_by_user_id, status FROM plan_requests WHERE id = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req) {
            $db->rollBack();
            jsonError('Solicitação não encontrada.', 404);
        }
        if ($req['status'] !== 'pending') {
            $db->rollBack();
            jsonError('Esta solicitação já foi avaliada.', 409);
        }
        if ($status === 'approved') {
            if ((bool) $db->query("SHOW TABLES LIKE 'asaas_subscriptions'")->fetchColumn()) {
                $hasProductKind = (bool) $db->query("SHOW COLUMNS FROM asaas_subscriptions LIKE 'product_kind'")->fetchColumn();
                $productFilter = $hasProductKind ? " AND COALESCE(product_kind, 'prospecting') = 'prospecting'" : '';
                $subscription = $db->prepare(
                    "SELECT id FROM asaas_subscriptions
                      WHERE tenant_id = ?{$productFilter} AND status IN ('pending', 'active', 'overdue')
                      ORDER BY id DESC LIMIT 1"
                );
                $subscription->execute([(int) $req['tenant_id']]);
                if ($subscription->fetchColumn()) {
                    $db->rollBack();
                    jsonError('Esta empresa já possui uma assinatura Asaas em andamento. Cancele-a antes de aprovar manualmente.', 409);
                }
            }
            $planCheck = $db->prepare("SELECT id FROM plans WHERE id = ? AND status = 'active' AND COALESCE(catalog_group, 'current') <> 'crm_addon' LIMIT 1");
            $planCheck->execute([(int) $req['plan_id']]);
            if (!$planCheck->fetchColumn()) {
                $db->rollBack();
                jsonError('O plano solicitado está inativo ou não é um plano principal.', 409);
            }
        }
        $stmt = $db->prepare("UPDATE plan_requests SET status = ?, reviewed_at = NOW(), reviewed_by_user_id = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$status, $userId, $id]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('A solicitação já foi atualizada por outro administrador.');
        }
        if ($status === 'approved') {
            $tid = (int) $req['tenant_id'];
            $planId = (int) $req['plan_id'];
            $stmt = $db->prepare("UPDATE tenants SET plan_id = ?, status = 'active' WHERE id = ?");
            $stmt->execute([$planId, $tid]);
            mpGrantPlanContractBonus($db, $tid, 'manual_plan_request', (string) $id, 50);
        }
        $db->commit();

        if ($status === 'approved') {
            try {
                require_once __DIR__ . '/../services/notificationService.php';
                $stmtClient = $db->prepare(
                    "SELECT t.name AS tenant_name, u.email, u.phone, p.name AS plan_name
                       FROM tenants t
                       LEFT JOIN users u ON u.id = ?
                       LEFT JOIN plans p ON p.id = ?
                      WHERE t.id = ? LIMIT 1"
                );
                $stmtClient->execute([(int) $req['requested_by_user_id'], (int) $req['plan_id'], (int) $req['tenant_id']]);
                $clientRow = $stmtClient->fetch(PDO::FETCH_ASSOC);
                if ($clientRow) {
                    NotificationService::notifyPlanApproval(
                        $db,
                        (string) ($clientRow['tenant_name'] ?? 'sua empresa'),
                        (string) ($clientRow['plan_name'] ?? 'Plano'),
                        (string) ($clientRow['email'] ?? ''),
                        (string) ($clientRow['phone'] ?? '')
                    );
                }
            } catch (Throwable $e) {
                error_log("plan-requests.php PUT notification: " . $e->getMessage());
            }
        }

        jsonSuccess(['id' => (string) $id, 'status' => $status], $status === 'approved' ? 'Plano confirmado e vinculado à empresa.' : 'Solicitação recusada.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("plan-requests PUT: " . $e->getMessage());
        jsonError('Erro ao processar solicitação.', 500);
    }
} else {
    jsonError('Método não permitido', 405);
}