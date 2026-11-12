<?php
/**
 * API de Solicitação de Créditos (tokens) com Notificações Integradas
 * Endpoint: /api/credit-requests.php
 * Usuários com empresa solicitam créditos; Super Admin aprova ou recusa.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/AsaasPaymentGateway.php';

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
        error_log("credit-requests GET: " . $e->getMessage());
        jsonError('Erro ao listar solicitações de créditos. Consulte os registros do servidor.', 500);
    }
} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (($input['action'] ?? '') === 'grant') {
        if (!$isSuperAdmin) jsonError('Apenas o administrador da plataforma pode adicionar créditos.', 403);
        $targetTenant = filter_var($input['tenantId'] ?? null, FILTER_VALIDATE_INT);
        $amount = filter_var($input['credits'] ?? null, FILTER_VALIDATE_INT);
        $reference = (string) ($input['reference'] ?? '');
        if (!$targetTenant || $targetTenant < 1 || !$amount || $amount < 1 || $amount > 10000 || !preg_match('/^[a-f0-9]{32}$/', $reference)) {
            jsonError('Selecione uma empresa e informe entre 1 e 10.000 créditos.', 422);
        }
        try {
            $db->beginTransaction();
            $tenant = $db->prepare('SELECT t.id, p.period FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ? FOR UPDATE');
            $tenant->execute([$targetTenant]);
            $row = $tenant->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                $db->rollBack();
                jsonError('Empresa não encontrada.', 404);
            }
            $bonusCode = 'admin_credit_' . (int) $userId;
            $grant = $db->prepare("INSERT INTO payment_bonus_grants (tenant_id, bonus_code, source_type, source_id, credits, created_at) VALUES (?, ?, 'admin_credit', ?, ?, NOW()) ON DUPLICATE KEY UPDATE id = id");
            $grant->execute([$targetTenant, $bonusCode, $reference, $amount]);
            if ($grant->rowCount() === 0) {
                $existing = $db->prepare("SELECT credits FROM payment_bonus_grants WHERE tenant_id = ? AND bonus_code = ? AND source_type = 'admin_credit' AND source_id = ?");
                $existing->execute([$targetTenant, $bonusCode, $reference]);
                if ((int) $existing->fetchColumn() !== $amount) {
                    $db->rollBack();
                    jsonError('Esta operação já foi registrada com outra quantidade.', 409);
                }
                $db->commit();
                jsonSuccess(['alreadyApplied' => true], 'Esta operação já havia sido aplicada.');
            }
            $periodStart = getCurrentPeriodStart((string) ($row['period'] ?? 'monthly'));
            $usage = $db->prepare('INSERT INTO tenant_usage (tenant_id, period_start, tokens_used, token_bonus) VALUES (?, ?, 0, ?) ON DUPLICATE KEY UPDATE token_bonus = COALESCE(token_bonus, 0) + ?');
            $usage->execute([$targetTenant, $periodStart, $amount, $amount]);
            $db->commit();
            jsonSuccess(['alreadyApplied' => false, 'periodStart' => $periodStart], 'Créditos adicionados ao período atual da empresa.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log('credit-requests grant: ' . $e->getMessage());
            jsonError('Não foi possível adicionar créditos. Consulte os registros do servidor.', 500);
        }
    }
    if ($tenantId === null) {
        jsonError('Apenas usuários vinculados a uma empresa podem solicitar créditos.', 403);
    }
    $tokensRequested = isset($input['tokensRequested']) ? (int) $input['tokensRequested'] : 0;
    if ($tokensRequested < 100 || $tokensRequested > 10000) {
        jsonError('Informe uma quantidade entre 100 e 10.000 tokens.', 400);
    }
    if (class_exists('MapsProspectorAsaasPaymentGateway') && MapsProspectorAsaasPaymentGateway::isOperational($db)) {
        try {
            $created = MapsProspectorAsaasPaymentGateway::createCredits($db, $authUser, (int) $tenantId, $tokensRequested);
            jsonSuccess($created, 'Cobrança de créditos criada no Asaas. Abra o link para concluir o pagamento.');
        } catch (Throwable $e) {
            error_log('credit-requests automatic Asaas creation: ' . $e->getMessage());
            jsonError('O Asaas está configurado, mas não foi possível criar a cobrança agora. Nenhuma solicitação manual foi aberta automaticamente.', 502);
        }
    }
    try {
        $stmt = $db->prepare("INSERT INTO credit_requests (tenant_id, requested_by_user_id, tokens_requested, status) VALUES (?, ?, ?, 'pending')");
        $stmt->execute([$tenantId, $userId, $tokensRequested]);
        $newId = (int) $db->lastInsertId();

        // DISPARA NOTIFICAÇÃO DE SOLICITAÇÃO DE CRÉDITO PARA O SUPER ADMIN
        try {
            require_once __DIR__ . '/../services/notificationService.php';
            
            // Busca o Nome da Instância do cliente
            $stmtSettings = $db->prepare("SELECT tenant_name FROM settings WHERE user_id = ? LIMIT 1");
            $stmtSettings->execute([$userId]);
            $settingsRow = $stmtSettings->fetch();
            $companyName = (!empty($settingsRow['tenant_name'])) ? $settingsRow['tenant_name'] : 'sua empresa';

            NotificationService::notifyCreditRequest($db, $companyName, $tokensRequested);
        } catch (Exception $e) {
            error_log("credit-requests.php POST: erro ao enviar notificação de pedido: " . $e->getMessage());
        }

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
        $db->beginTransaction();
        $stmt = $db->prepare("SELECT id, tenant_id, requested_by_user_id, tokens_requested, status FROM credit_requests WHERE id = ? FOR UPDATE");
        $stmt->execute([$id]);
        $req = $stmt->fetch();
        if (!$req) {
            $db->rollBack();
            jsonError('Solicitação não encontrada.', 404);
        }
        if ($req['status'] !== 'pending') {
            $db->rollBack();
            jsonError('Esta solicitação já foi avaliada.', 409);
        }
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

        // DISPARA NOTIFICAÇÃO DE CRÉDITOS LIBERADOS PARA O CLIENTE
        try {
            require_once __DIR__ . '/../services/notificationService.php';
            
            // Busca os dados de contato do cliente (e-mail, telefone e nome da empresa)
            $stmtClient = $db->prepare("
                SELECT u.email, u.phone, s.tenant_name 
                FROM users u 
                LEFT JOIN settings s ON s.user_id = u.id 
                WHERE u.id = ? LIMIT 1
            ");
            $stmtClient->execute([$req['requested_by_user_id']]);
            $clientRow = $stmtClient->fetch();

            if ($clientRow) {
                $clientEmail = $clientRow['email'];
                $clientPhone = $clientRow['phone'];
                $companyName = (!empty($clientRow['tenant_name'])) ? $clientRow['tenant_name'] : 'sua empresa';
                $amount = (int) $req['tokens_requested'];

                if ($status === 'approved') {
                    NotificationService::notifyCreditApproval($db, $companyName, $amount, $clientEmail, $clientPhone);
                }
            }
        } catch (Exception $e) {
            error_log("credit-requests.php PUT: erro ao enviar notificações de liberação: " . $e->getMessage());
        }

        jsonSuccess(['id' => (string) $id, 'status' => $status], $status === 'approved' ? 'Créditos aprovados e adicionados à empresa.' : 'Solicitação recusada.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log("credit-requests PUT: " . $e->getMessage());
        jsonError('Erro ao processar solicitação.', 500);
    }
} else {
    jsonError('Método não permitido', 405);
}