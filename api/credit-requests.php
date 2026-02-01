<?php
/**
 * API de Solicitação de Créditos (tokens)
 * Endpoint: /api/credit-requests.php
 * Usuários com empresa solicitam créditos; Super Admin aprova ou recusa.
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
                SELECT cr.id, cr.tenant_id, cr.requested_by_user_id, cr.tokens_requested, cr.status, cr.created_at, cr.reviewed_at,
                       t.name as tenant_name,
                       u.name as requested_by_name, u.email as requested_by_email
                FROM credit_requests cr
                INNER JOIN tenants t ON t.id = cr.tenant_id
                INNER JOIN users u ON u.id = cr.requested_by_user_id
                ORDER BY cr.status = 'pending' DESC, cr.created_at DESC
            ");
        } else {
            if (!$tenantId) {
                $priceAvulso = getPlatformSetting($db, 'credit_price_avulso');
                $pricePerCredit = ($priceAvulso !== null && $priceAvulso !== '') ? (float) str_replace(',', '.', trim($priceAvulso)) : 0;
                jsonSuccess(['items' => [], 'total' => 0, 'pricePerCredit' => $pricePerCredit]);
                exit;
            }
            $stmt = $db->prepare("
                SELECT cr.id, cr.tenant_id, cr.requested_by_user_id, cr.tokens_requested, cr.status, cr.created_at, cr.reviewed_at
                FROM credit_requests cr
                WHERE cr.tenant_id = ?
                ORDER BY cr.created_at DESC
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
                'tokensRequested' => (int) $r['tokens_requested'],
                'status' => $r['status'],
                'createdAt' => $r['created_at'],
                'reviewedAt' => $r['reviewed_at'],
            ];
        }
        $priceAvulso = getPlatformSetting($db, 'credit_price_avulso');
        $pricePerCredit = ($priceAvulso !== null && $priceAvulso !== '') ? (float) str_replace(',', '.', trim($priceAvulso)) : 0;
        jsonSuccess(['items' => $items, 'total' => count($items), 'pricePerCredit' => $pricePerCredit]);
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'exist') !== false || strpos($e->getMessage(), '1146') !== false) {
            $priceAvulso = getPlatformSetting($db, 'credit_price_avulso');
            $pricePerCredit = ($priceAvulso !== null && $priceAvulso !== '') ? (float) str_replace(',', '.', trim($priceAvulso)) : 0;
            jsonSuccess(['items' => [], 'total' => 0, 'pricePerCredit' => $pricePerCredit]);
            exit;
        }
        error_log("credit-requests GET: " . $e->getMessage());
        jsonError('Erro ao listar solicitações. Execute database_migration_credit_requests.sql no banco.', 500);
    }
} elseif ($method === 'POST') {
    if ($tenantId === null) {
        jsonError('Apenas usuários vinculados a uma empresa podem solicitar créditos.', 403);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $tokensRequested = isset($input['tokensRequested']) ? (int) $input['tokensRequested'] : 0;
    if ($tokensRequested < 100 || $tokensRequested > 10000) {
        jsonError('Informe uma quantidade entre 100 e 10.000 tokens.', 400);
    }
    try {
        $stmt = $db->prepare("INSERT INTO credit_requests (tenant_id, requested_by_user_id, tokens_requested, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$tenantId, $userId, $tokensRequested]);
        $newId = (int) $db->lastInsertId();
        jsonSuccess(['id' => (string) $newId, 'status' => 'pending'], 'Solicitação enviada.');
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'exist') !== false || strpos($e->getMessage(), '1146') !== false) {
            jsonError('Tabela de solicitações não existe. Execute database_migration_credit_requests.sql no banco.', 500);
        }
        error_log("credit-requests POST: " . $e->getMessage());
        jsonError('Erro ao criar solicitação.', 500);
    }
} elseif ($method === 'PUT') {
    if (!$isSuperAdmin) {
        jsonError('Apenas o administrador da plataforma pode aprovar ou recusar solicitações.', 403);
    }
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $id = isset($input['id']) ? (int) $input['id'] : 0;
    $status = isset($input['status']) ? trim(strtolower($input['status'])) : '';
    if (!$id || !in_array($status, ['approved', 'rejected'], true)) {
        jsonError('Informe o id da solicitação e o status (approved ou rejected).', 400);
    }
    try {
        $stmt = $db->prepare("SELECT id, tenant_id, tokens_requested, status FROM credit_requests WHERE id = ?");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) {
            jsonError('Solicitação não encontrada.', 404);
        }
        if ($req['status'] !== 'pending') {
            jsonError('Esta solicitação já foi avaliada.', 400);
        }
        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE credit_requests SET status = ?, reviewed_at = NOW(), reviewed_by_user_id = ? WHERE id = ?");
        $stmt->execute([$status, $userId, $id]);
        if ($status === 'approved') {
            $tid = (int) $req['tenant_id'];
            $amount = (int) $req['tokens_requested'];
            $stmt = $db->prepare("SELECT p.period FROM tenants t INNER JOIN plans p ON p.id = t.plan_id WHERE t.id = ?");
            $stmt->execute([$tid]);
            $row = $stmt->fetch();
            $period = $row['period'] ?? 'monthly';
            $periodStart = getCurrentPeriodStart($period);
            $stmt = $db->prepare("
                INSERT INTO tenant_usage (tenant_id, period_start, tokens_used, token_bonus)
                VALUES (?, ?, 0, ?)
                ON DUPLICATE KEY UPDATE token_bonus = COALESCE(token_bonus, 0) + ?
            ");
            $stmt->execute([$tid, $periodStart, $amount, $amount]);
        }
        $db->commit();
        jsonSuccess(['id' => (string) $id, 'status' => $status], $status === 'approved' ? 'Créditos aprovados e adicionados à empresa.' : 'Solicitação recusada.');
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("credit-requests PUT: " . $e->getMessage());
        jsonError('Erro ao processar solicitação.', 500);
    }
} else {
    jsonError('Método não permitido', 405);
}
