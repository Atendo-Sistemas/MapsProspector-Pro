<?php
/**
 * Webhook Oficial de Validação de Pagamento da InfinitePay (/payment_check)
 * Endpoint: /api/infinitepay-webhook.php
 *
 * Configuração na InfinitePay:
 * - Envie o campo "webhook_url" ao criar o link, ou configure no painel
 *   do Checkout Integrado (https://app.infinitepay.io/external-checkout)
 *
 * Regras importantes:
 * - Responder rapidamente (< 1 segundo) com 200 OK (sucesso) ou 400 (retry)
 * - A InfinitePay reenvia o webhook se você responder 400 ou der timeout
 * - NUNCA confiar apenas no webhook: validar com /payment_check antes de liberar
 *
 * Documentação oficial: https://www.infinitepay.io/checkout-documentacao
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance()->getConnection();

// ============================================
// 1. Lê o corpo enviado pela InfinitePay
// ============================================
$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true) ?? $_POST;

error_log('InfinitePay Webhook recebido: ' . print_r($data, true));

// O corpo do webhook NÃO traz slug/transaction_nsu de forma consistente.
// Por isso, identificamos o pedido pelo order_nsu e validamos com /payment_check.
$orderNsu = $data['order_nsu'] ?? $data['invoice_slug'] ?? null;

if (!$orderNsu) {
    // Nada a fazer: responde 200 para a InfinitePay não reenviar
    echo json_encode(['success' => true, 'message' => 'Webhook recebido.']);
    exit;
}

try {
    $handle           = getPlatformSetting($db, 'infinitepay_handle') ?: 'ivan-jacomassi';
    $transactionNsu   = $data['transaction_nsu'] ?? null;
    $slug             = $data['invoice_slug'] ?? null;

    // ============================================
    // 2. Consulta a API oficial /payment_check
    // ============================================
    $checkPayload = [
        'handle'            => $handle,
        'order_nsu'         => $orderNsu,
        'transaction_nsu'   => $transactionNsu,
        'slug'              => $slug,
    ];

    $chCheck = curl_init('https://api.checkout.infinitepay.io/payment_check');
    curl_setopt_array($chCheck, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($checkPayload),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $checkResp = curl_exec($chCheck);
    $checkHttp = curl_getinfo($chCheck, CURLINFO_HTTP_CODE);
    curl_close($chCheck);

    $checkData = json_decode($checkResp, true);

    // Resposta oficial: { "success": true, "paid": true, "amount": 1500, ... }
    $isPaid = ($checkData['paid'] ?? false) === true
           && ($checkData['success'] ?? false) === true;

    // Segurança extra: conferir se o valor pago bate com o pedido
    if ($isPaid && ($checkData['amount'] ?? 0) <= 0) {
        $isPaid = false;
        error_log('InfinitePay payment_check: amount zerado para order_nsu ' . $orderNsu);
    }

    if (!$isPaid) {
        // Pagamento ainda não confirmado: apenas registrar o evento
        $db->prepare(
            "INSERT INTO payment_events (order_nsu, event, payload, created_at)
             VALUES (?, 'webhook_not_paid', ?, NOW())
             ON DUPLICATE KEY UPDATE updated_at = NOW()"
        )->execute([
            $orderNsu,
            json_encode(['check_response' => $checkData, 'webhook_payload' => $data]),
        ]);
        echo json_encode(['success' => true, 'message' => 'Pagamento ainda não confirmado.']);
        exit;
    }

    // ============================================
    // 3. Busca o pagamento pendente no banco
    // ============================================
    $stmt = $db->prepare(
        "SELECT * FROM payments
         WHERE (external_id = ? OR id = ?) AND status = 'pending'"
    );
    $stmt->execute([
        $orderNsu,
        (is_numeric($orderNsu) ? (int)$orderNsu : null),
    ]);
    $payment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$payment) {
        error_log('InfinitePay: pagamento pendente não encontrado para order_nsu ' . $orderNsu);
        echo json_encode(['success' => true, 'message' => 'Pedido não localizado ou já processado.']);
        exit;
    }

    $paymentId = $payment['id'];
    $tenantId  = $payment['tenant_id'];
    $credits   = (int)$payment['credits'];
    $planId    = $payment['plan_id'];

    $db->beginTransaction();

    // 1. Marca o pagamento como aprovado (e grava dados da transação para auditoria)
    $db->prepare(
        "UPDATE payments
         SET status = 'approved',
             external_ref = ?,
             receipt_url = ?,
             capture_method = ?,
             paid_amount = ?,
             updated_at = NOW()
         WHERE id = ? AND status = 'pending'"
    )->execute([
        $checkData['transaction_nsu'] ?? null,
        $data['receipt_url'] ?? null,
        $checkData['capture_method'] ?? null,
        $checkData['paid_amount'] ?? null,
        $paymentId,
    ]);

    // Proteção contra processamento duplicado (webhook pode reenviar)
    if ($db->rowCount() === 0) {
        $db->commit();
        echo json_encode(['success' => true, 'message' => 'Pagamento já processado anteriormente.']);
        exit;
    }

    // 2. Se for compra de créditos, adiciona o saldo de bônus
    if ($credits > 0) {
        $db->prepare(
            "INSERT INTO tenant_token_bonuses (tenant_id, bonus_tokens, reason, created_at)
             VALUES (?, ?, 'Compra de Créditos Avulsos', NOW())"
        )->execute([$tenantId, $credits]);

        $db->prepare(
            "UPDATE credit_requests
             SET status = 'approved'
             WHERE tenant_id = ? AND status = 'pending'
             ORDER BY id DESC LIMIT 1"
        )->execute([$tenantId]);
    }

    // 3. Se for plano, altera o plano da empresa
    if ($planId) {
        $db->prepare(
            "UPDATE tenants SET plan_id = ?, status = 'active' WHERE id = ?"
        )->execute([$planId, $tenantId]);

        $db->prepare(
            "UPDATE plan_requests
             SET status = 'approved'
             WHERE tenant_id = ? AND status = 'pending'
             ORDER BY id DESC LIMIT 1"
        )->execute([$tenantId]);
    }

    // Reativa a empresa caso estivesse suspensa
    $db->prepare(
        "UPDATE tenants SET status = 'active' WHERE id = ?"
    )->execute([$tenantId]);

    $db->commit();

    error_log('InfinitePay: pagamento ' . $paymentId . ' aprovado para tenant ' . $tenantId);
    echo json_encode(['success' => true, 'message' => 'Pagamento confirmado e liberado com sucesso!']);
    exit;

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Erro no Webhook da InfinitePay: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
