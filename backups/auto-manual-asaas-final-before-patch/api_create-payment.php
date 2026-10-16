<?php
/**
 * Entrada única para créditos e planos.
 *
 * Regra operacional:
 * - Asaas ativo e com credenciais/tabelas prontas: cria cobrança/assinatura;
 * - Asaas ausente, incompleto ou desativado: cria solicitação manual;
 * - Asaas ativo, mas com falha na criação: retorna erro e não abre uma
 *   solicitação manual silenciosa, evitando cobrança duplicada ou ambígua.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
ob_start();

function manualRequestResponse(bool $success, $data = null, string $message = '', int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $success ? $message : '',
        'error' => $success ? '' : $message,
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../services/AsaasPaymentGateway.php';

    $userId = requireAuth();
    $authUser = getAuthUser();
    $tenantId = (int) ($authUser['tenant_id'] ?? 0);
    if ($tenantId <= 0) {
        manualRequestResponse(false, null, 'Empresa não encontrada para esta conta.', 400);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        manualRequestResponse(false, null, 'Dados inválidos.', 400);
    }

    $type = (string) ($input['type'] ?? '');
    $credits = (int) ($input['credits'] ?? 0);
    $planId = isset($input['planId']) ? (int) $input['planId'] : 0;
    if (!in_array($type, ['credits', 'plan'], true)) {
        manualRequestResponse(false, null, 'Tipo de solicitação inválido.', 400);
    }
    if ($type === 'credits' && ($credits < 100 || $credits > 10000)) {
        manualRequestResponse(false, null, 'Informe uma quantidade entre 100 e 10.000 créditos.', 400);
    }
    if ($type === 'plan' && $planId < 1) {
        manualRequestResponse(false, null, 'Informe o plano a ser solicitado.', 400);
    }

    $db = Database::getInstance()->getConnection();

    // Idempotência do fluxo manual: não abre uma segunda solicitação igual.
    if ($type === 'credits') {
        $pending = $db->prepare("SELECT id FROM credit_requests WHERE tenant_id = ? AND tokens_requested = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        $pending->execute([$tenantId, $credits]);
    } else {
        $pending = $db->prepare("SELECT id FROM plan_requests WHERE tenant_id = ? AND plan_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        $pending->execute([$tenantId, $planId]);
    }
    $existing = $pending->fetch(PDO::FETCH_ASSOC);
    if ($existing) {
        manualRequestResponse(true, [
            'mode' => 'manual',
            'requestId' => (int) $existing['id'],
            'alreadyPending' => true,
        ], 'Já existe uma solicitação manual pendente para análise.');
    }

    if (MapsProspectorAsaasPaymentGateway::isOperational($db)) {
        try {
            if ($type === 'credits') {
                $existingAsaas = $db->prepare("SELECT order_nsu, payment_url, amount_cents FROM asaas_orders WHERE tenant_id = ? AND quantity = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
                $existingAsaas->execute([$tenantId, $credits]);
                $existingOrder = $existingAsaas->fetch(PDO::FETCH_ASSOC);
                if (is_array($existingOrder) && trim((string) ($existingOrder['payment_url'] ?? '')) !== '') {
                    manualRequestResponse(true, [
                        'mode' => 'asaas',
                        'payment_url' => (string) $existingOrder['payment_url'],
                        'order_nsu' => (string) $existingOrder['order_nsu'],
                        'amount_brl' => ((int) ($existingOrder['amount_cents'] ?? 0)) / 100,
                        'alreadyPending' => true,
                    ], 'Já existe uma cobrança Asaas pendente para esta recarga.');
                }
                $result = MapsProspectorAsaasPaymentGateway::createCredits($db, $authUser, $tenantId, $credits);
                manualRequestResponse(true, $result, 'Cobrança criada no Asaas. Abra o link para concluir o pagamento.');
            }

            $existingAsaas = $db->prepare("SELECT asaas_subscription_id, payment_url, amount_cents FROM asaas_subscriptions WHERE tenant_id = ? AND status IN ('pending', 'active', 'overdue') ORDER BY id DESC LIMIT 1");
            $existingAsaas->execute([$tenantId]);
            $existingSubscription = $existingAsaas->fetch(PDO::FETCH_ASSOC);
            if (is_array($existingSubscription)) {
                if (trim((string) ($existingSubscription['payment_url'] ?? '')) !== '') {
                    manualRequestResponse(true, [
                        'mode' => 'asaas',
                        'payment_url' => (string) $existingSubscription['payment_url'],
                        'subscription_id' => (string) $existingSubscription['asaas_subscription_id'],
                        'amount_brl' => ((int) ($existingSubscription['amount_cents'] ?? 0)) / 100,
                        'alreadyPending' => true,
                    ], 'Já existe uma assinatura Asaas em andamento para esta empresa.');
                }
                manualRequestResponse(false, null, 'Já existe uma assinatura Asaas em andamento para esta empresa, mas ainda não há link de pagamento disponível.', 409);
            }
            $result = MapsProspectorAsaasPaymentGateway::createPlan($db, $authUser, $tenantId, $planId);
            manualRequestResponse(true, $result, 'Assinatura criada no Asaas. Abra o link para concluir o primeiro pagamento.');
        } catch (Throwable $e) {
            error_log('automatic payment creation error: ' . $e->getMessage());
            manualRequestResponse(false, null, 'O Asaas está configurado, mas não foi possível criar a cobrança agora. Nenhuma solicitação manual foi aberta automaticamente; tente novamente ou verifique a configuração do Asaas.', 502);
        }
    }

    // Fallback manual, usado quando o Asaas não está configurado ou está inativo.
    $db->beginTransaction();
    $stmt = $db->prepare('INSERT INTO payments (tenant_id, user_id, type, amount, amount_cents, credits, plan_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', NOW())');
    $stmt->execute([$tenantId, (int) $userId, $type, 0, 0, $type === 'credits' ? $credits : 0, $type === 'plan' ? $planId : null]);
    $paymentId = (int) $db->lastInsertId();
    $db->prepare('UPDATE payments SET external_id = ? WHERE id = ?')->execute(['REQ_' . $paymentId, $paymentId]);

    if ($type === 'credits') {
        $db->prepare('INSERT INTO credit_requests (tenant_id, requested_by_user_id, tokens_requested, status, created_at) VALUES (?, ?, ?, \'pending\', NOW())')
            ->execute([$tenantId, (int) $userId, $credits]);
    } else {
        $planCheck = $db->prepare("SELECT id FROM plans WHERE id = ? AND status = 'active' LIMIT 1");
        $planCheck->execute([$planId]);
        if (!$planCheck->fetchColumn()) {
            $db->rollBack();
            manualRequestResponse(false, null, 'Plano não encontrado ou inativo.', 400);
        }
        $db->prepare('INSERT INTO plan_requests (tenant_id, requested_by_user_id, plan_id, status, created_at) VALUES (?, ?, ?, \'pending\', NOW())')
            ->execute([$tenantId, (int) $userId, $planId]);
    }
    $db->commit();

    manualRequestResponse(true, ['mode' => 'manual', 'requestId' => $paymentId], $type === 'credits'
        ? 'Solicitação de créditos enviada para aprovação manual do administrador.'
        : 'Solicitação de plano enviada para aprovação manual do administrador.');
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('payment request error: ' . $e->getMessage());
    manualRequestResponse(false, null, 'Não foi possível registrar a solicitação agora. Tente novamente.', 500);
}
