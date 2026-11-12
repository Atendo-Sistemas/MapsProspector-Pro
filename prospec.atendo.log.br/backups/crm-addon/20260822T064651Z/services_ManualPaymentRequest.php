<?php

declare(strict_types=1);

/**
 * Registra uma solicitação manual quando o gateway automático não está pronto.
 * O método é idempotente para pedidos pendentes iguais por empresa.
 */
function mapsCreateManualPaymentRequest(PDO $db, array $user, int $tenantId, string $type, int $credits = 0, int $planId = 0): array
{
    if ($tenantId <= 0 || !in_array($type, ['credits', 'plan'], true)) {
        throw new InvalidArgumentException('Solicitação de pagamento inválida.');
    }
    if ($type === 'credits' && ($credits < 100 || $credits > 10000)) {
        throw new InvalidArgumentException('Informe uma quantidade entre 100 e 10.000 créditos.');
    }
    if ($type === 'plan' && $planId < 1) {
        throw new InvalidArgumentException('Informe o plano a ser solicitado.');
    }

    if ($type === 'credits') {
        $pending = $db->prepare("SELECT id FROM credit_requests WHERE tenant_id = ? AND tokens_requested = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        $pending->execute([$tenantId, $credits]);
    } else {
        $pending = $db->prepare("SELECT id FROM plan_requests WHERE tenant_id = ? AND plan_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
        $pending->execute([$tenantId, $planId]);
    }
    $existing = $pending->fetch(PDO::FETCH_ASSOC);
    if (is_array($existing)) {
        return ['mode' => 'manual', 'requestId' => (int) $existing['id'], 'alreadyPending' => true];
    }

    $db->beginTransaction();
    try {
        if ($type === 'plan') {
            $planCheck = $db->prepare("SELECT id FROM plans WHERE id = ? AND status = 'active' LIMIT 1");
            $planCheck->execute([$planId]);
            if (!$planCheck->fetchColumn()) {
                throw new InvalidArgumentException('Plano não encontrado ou inativo.');
            }
        }

        $amountCents = 0;
        if ($type === 'credits') {
            $rawPrice = function_exists('getPlatformSetting') ? (string) (getPlatformSetting($db, 'credit_price_avulso') ?? '') : '';
            $price = is_numeric(str_replace(',', '.', trim($rawPrice))) ? (float) str_replace(',', '.', trim($rawPrice)) : 0.10;
            $amountCents = max(0, (int) round($price * $credits * 100));
        } else {
            $priceStmt = $db->prepare('SELECT price_monthly FROM plans WHERE id = ? LIMIT 1');
            $priceStmt->execute([$planId]);
            $amountCents = max(0, (int) round(((float) ($priceStmt->fetchColumn() ?: 0)) * 100));
        }

        $payment = $db->prepare(
            "INSERT INTO payments (tenant_id, user_id, type, amount, amount_cents, credits, plan_id, status, external_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, NOW())"
        );
        $payment->execute([
            $tenantId,
            (int) ($user['id'] ?? 0),
            $type,
            $amountCents / 100,
            $amountCents,
            $type === 'credits' ? $credits : 0,
            $type === 'plan' ? $planId : null,
            'MANUAL-' . strtolower(bin2hex(random_bytes(8))),
        ]);

        if ($type === 'credits') {
            $request = $db->prepare('INSERT INTO credit_requests (tenant_id, requested_by_user_id, tokens_requested, status, created_at) VALUES (?, ?, ?, \'pending\', NOW())');
            $request->execute([$tenantId, (int) ($user['id'] ?? 0), $credits]);
        } else {
            $request = $db->prepare('INSERT INTO plan_requests (tenant_id, requested_by_user_id, plan_id, status, created_at) VALUES (?, ?, ?, \'pending\', NOW())');
            $request->execute([$tenantId, (int) ($user['id'] ?? 0), $planId]);
        }
        $requestId = (int) $db->lastInsertId();
        $db->commit();

        // A solicitação manual precisa chegar ao administrador mesmo quando o
        // usuário veio pelo caminho automático que caiu no modo manual.
        try {
            require_once __DIR__ . '/notificationService.php';
            $settingsStmt = $db->prepare('SELECT tenant_name FROM settings WHERE user_id = ? LIMIT 1');
            $settingsStmt->execute([(int) ($user['id'] ?? 0)]);
            $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
            $companyName = trim((string) ($settings['tenant_name'] ?? '')) ?: 'sua empresa';
            if ($type === 'credits') {
                NotificationService::notifyCreditRequest($db, $companyName, $credits);
            } else {
                $planStmt = $db->prepare('SELECT name FROM plans WHERE id = ? LIMIT 1');
                $planStmt->execute([$planId]);
                $planName = trim((string) ($planStmt->fetchColumn() ?: 'Plano'));
                NotificationService::notifyPlanRequest($db, $companyName, $planName);
            }
        } catch (Throwable $notificationError) {
            error_log('manual payment request notification: ' . $notificationError->getMessage());
        }

        return ['mode' => 'manual', 'requestId' => $requestId, 'alreadyPending' => false];
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
