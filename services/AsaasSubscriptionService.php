<?php
/**
 * Regras locais de acesso para assinaturas Asaas.
 * Este arquivo não chama a API externa: webhook e conferência diária podem
 * reutilizá-lo de modo idempotente e com decisões inteiramente no servidor.
 */

declare(strict_types=1);

require_once __DIR__ . '/PaymentBonusService.php';
require_once __DIR__ . '/CrmAddonService.php';

function mpAsaasSubscriptionTableReady(PDO $db): bool
{
    try {
        if (!(bool) $db->query("SHOW TABLES LIKE 'asaas_subscriptions'")->fetchColumn()) {
            return false;
        }
        $column = $db->query("SHOW COLUMNS FROM asaas_subscriptions LIKE 'product_kind'");
        return (bool) $column->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
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
    $nextDueDate = new DateTimeImmutable(mpAsaasNextDueDate($dueDate));
    return $nextDueDate->modify('-1 day')->format('Y-m-d');
}

function mpAsaasNextDueDate(?string $dueDate): string
{
    try {
        $base = new DateTimeImmutable($dueDate ?: 'today');
    } catch (Throwable $e) {
        $base = new DateTimeImmutable('today');
    }
    $nextMonth = $base->modify('first day of next month');
    $day = min((int) $base->format('d'), (int) $nextMonth->format('t'));
    return $nextMonth->setDate((int) $nextMonth->format('Y'), (int) $nextMonth->format('m'), $day)->format('Y-m-d');
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
        $select = $db->prepare('SELECT id, tenant_id, plan_id, product_kind, last_payment_id FROM asaas_subscriptions WHERE asaas_subscription_id = ? LIMIT 1 FOR UPDATE');
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
        if ((string) ($subscription['product_kind'] ?? 'prospecting') === 'crm_addon') {
            crmAddonActivateLicense($db, (int) $subscription['tenant_id'], (int) $subscription['plan_id'], 'asaas');
        } else {
            $tenant = $db->prepare(
                'UPDATE tenants
                    SET plan_id = ?,
                        plan_access_until = CASE
                            WHEN plan_access_until IS NULL OR plan_access_until < ? THEN ?
                            ELSE plan_access_until
                        END
                  WHERE id = ?'
            );
            $tenant->execute([(int) $subscription['plan_id'], $accessUntil, $accessUntil, (int) $subscription['tenant_id']]);
            mpGrantPlanContractBonus($db, (int) $subscription['tenant_id'], 'asaas_subscription', $subscriptionId, 50);
        }
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
    $today = $today ?: date('Y-m-d');
    $freePlanId = mpAsaasFreePlanId($db);
    $rows = [];
    $downgraded = 0;
    if (mpAsaasSubscriptionTableReady($db)) {
        $query = $db->prepare(
            "SELECT id, tenant_id, plan_id, product_kind FROM asaas_subscriptions
              WHERE status IN ('cancelled', 'overdue', 'active')
                AND access_until IS NOT NULL AND access_until < ?
              ORDER BY id ASC"
        );
        $query->execute([$today]);
        $rows = $query->fetchAll(PDO::FETCH_ASSOC);
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
                    if ((string) ($row['product_kind'] ?? 'prospecting') === 'crm_addon') {
                        crmAddonPauseLicense($db, (int) $row['tenant_id'], (int) $row['plan_id']);
                    } else {
                        $db->prepare('UPDATE tenants SET plan_id = ?, plan_access_until = NULL WHERE id = ?')
                            ->execute([$freePlanId, (int) $row['tenant_id']]);
                    }
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
    }
    $manualQuery = $db->prepare(
        'SELECT id FROM tenants
          WHERE id <> 1 AND plan_id <> ? AND plan_access_until IS NOT NULL AND plan_access_until < ?
          ORDER BY id ASC'
    );
    $manualQuery->execute([$freePlanId, $today]);
    $manualRows = $manualQuery->fetchAll(PDO::FETCH_COLUMN);
    $manualDowngraded = 0;
    foreach ($manualRows as $tenantId) {
        $db->beginTransaction();
        try {
            $expire = $db->prepare(
                'UPDATE tenants SET plan_id = ?, plan_access_until = NULL
                  WHERE id = ? AND plan_id <> ? AND plan_access_until IS NOT NULL AND plan_access_until < ?'
            );
            $expire->execute([$freePlanId, (int) $tenantId, $freePlanId, $today]);
            $manualDowngraded += $expire->rowCount();
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
    return [
        'checked' => count($rows) + count($manualRows),
        'downgraded' => $downgraded + $manualDowngraded,
    ];
}
