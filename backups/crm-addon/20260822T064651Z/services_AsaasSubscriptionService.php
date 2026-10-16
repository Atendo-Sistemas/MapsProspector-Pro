<?php
/**
 * Regras locais de acesso para assinaturas Asaas.
 * Este arquivo não chama a API externa: webhook e conferência diária podem
 * reutilizá-lo de modo idempotente e com decisões inteiramente no servidor.
 */

declare(strict_types=1);

require_once __DIR__ . '/PaymentBonusService.php';

function mpAsaasSubscriptionTableReady(PDO $db): bool
{
    return (bool) $db->query("SHOW TABLES LIKE 'asaas_subscriptions'")->fetchColumn();
}

function mpAsaasFreePlanId(PDO $db): int
{
    $stmt = $db->query(
        "SELECT id FROM plans
          WHERE status = 'active' AND COALESCE(price_monthly, 0) <= 0
          ORDER BY CASE WHEN id = 1 THEN 0 ELSE 1 END, id ASC LIMIT 1"
    );
    $id = (int) $stmt->fetchColumn();
    if ($id <= 0) {
        throw new RuntimeException('Não foi encontrado um plano gratuito ativo para o rebaixamento seguro.');
    }
    return $id;
}

function mpAsaasAccessUntil(?string $dueDate): string
{
    try {
        $base = new DateTimeImmutable($dueDate ?: 'today');
    } catch (Throwable $e) {
        $base = new DateTimeImmutable('today');
    }
    return $base->modify('+1 month')->modify('-1 day')->format('Y-m-d');
}

function mpAsaasNextDueDate(?string $dueDate): string
{
    try {
        $base = new DateTimeImmutable($dueDate ?: 'today');
    } catch (Throwable $e) {
        $base = new DateTimeImmutable('today');
    }
    return $base->modify('+1 month')->format('Y-m-d');
}

/** Ativa ou renova o plano apenas quando o pagamento recebido é confirmado. */
function mpAsaasConfirmSubscriptionPayment(PDO $db, string $subscriptionId, array $payment): bool
{
    if ($subscriptionId === '' || !mpAsaasSubscriptionTableReady($db)) {
        return false;
    }
    $paymentId = substr((string) ($payment['id'] ?? ''), 0, 64);
    $paymentStatus = strtoupper((string) ($payment['status'] ?? 'RECEIVED'));
    $dueDate = (string) ($payment['dueDate'] ?? date('Y-m-d'));
    $accessUntil = mpAsaasAccessUntil($dueDate);
    $nextDueDate = mpAsaasNextDueDate($dueDate);

    $db->beginTransaction();
    try {
        $select = $db->prepare('SELECT id, tenant_id, plan_id, last_payment_id FROM asaas_subscriptions WHERE asaas_subscription_id = ? LIMIT 1 FOR UPDATE');
        $select->execute([$subscriptionId]);
        $subscription = $select->fetch(PDO::FETCH_ASSOC);
        if (!is_array($subscription)) {
            $db->commit();
            return false;
        }
        $update = $db->prepare(
            "UPDATE asaas_subscriptions
                SET status = 'active', next_due_date = ?,
                    access_until = CASE WHEN access_until IS NULL OR access_until < ? THEN ? ELSE access_until END,
                    last_payment_id = ?, last_payment_status = ?, cancelled_at = NULL
              WHERE id = ?"
        );
        $update->execute([$nextDueDate, $accessUntil, $accessUntil, $paymentId !== '' ? $paymentId : null, $paymentStatus, (int) $subscription['id']]);
        $tenant = $db->prepare('UPDATE tenants SET plan_id = ? WHERE id = ?');
        $tenant->execute([(int) $subscription['plan_id'], (int) $subscription['tenant_id']]);
        mpGrantPlanContractBonus($db, (int) $subscription['tenant_id'], 'asaas_subscription', $subscriptionId, 50);
        $db->commit();
        return true;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}

/** Inadimplência não reduz acesso: o rebaixamento é deliberadamente posterior. */
function mpAsaasMarkSubscriptionOverdue(PDO $db, string $subscriptionId, array $payment): bool
{
    if ($subscriptionId === '' || !mpAsaasSubscriptionTableReady($db)) {
        return false;
    }
    $stmt = $db->prepare(
        "UPDATE asaas_subscriptions
            SET status = 'overdue', last_payment_id = ?, last_payment_status = 'OVERDUE'
          WHERE asaas_subscription_id = ? AND status IN ('active', 'overdue')"
    );
    $stmt->execute([
        !empty($payment['id']) ? substr((string) $payment['id'], 0, 64) : null,
        $subscriptionId,
    ]);
    return $stmt->rowCount() > 0;
}

/** Cancela novas cobranças; a data de acesso já paga não é reduzida. */
function mpAsaasMarkSubscriptionCancelled(PDO $db, string $subscriptionId): bool
{
    if ($subscriptionId === '' || !mpAsaasSubscriptionTableReady($db)) {
        return false;
    }
    $stmt = $db->prepare(
        "UPDATE asaas_subscriptions
            SET status = 'cancelled', cancelled_at = COALESCE(cancelled_at, NOW())
          WHERE asaas_subscription_id = ? AND status IN ('pending', 'active', 'overdue')"
    );
    $stmt->execute([$subscriptionId]);
    return $stmt->rowCount() > 0;
}

/** Rebaixa somente assinaturas vencidas, canceladas ou inadimplentes, em operação idempotente. */
function mpAsaasExpireDueSubscriptions(PDO $db, ?string $today = null): array
{
    if (!mpAsaasSubscriptionTableReady($db)) {
        return ['checked' => 0, 'downgraded' => 0];
    }
    $today = $today ?: date('Y-m-d');
    $freePlanId = mpAsaasFreePlanId($db);
    $query = $db->prepare(
        "SELECT id, tenant_id FROM asaas_subscriptions
          WHERE status IN ('cancelled', 'overdue', 'active')
            AND access_until IS NOT NULL AND access_until < ?
          ORDER BY id ASC"
    );
    $query->execute([$today]);
    $rows = $query->fetchAll(PDO::FETCH_ASSOC);
    $downgraded = 0;
    foreach ($rows as $row) {
        $db->beginTransaction();
        try {
            $expire = $db->prepare(
                "UPDATE asaas_subscriptions
                    SET status = 'expired'
                  WHERE id = ? AND status IN ('cancelled', 'overdue', 'active') AND access_until < ?"
            );
            $expire->execute([(int) $row['id'], $today]);
            if ($expire->rowCount() > 0) {
                $db->prepare('UPDATE tenants SET plan_id = ? WHERE id = ?')->execute([$freePlanId, (int) $row['tenant_id']]);
                $downgraded++;
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
    return ['checked' => count($rows), 'downgraded' => $downgraded];
}
