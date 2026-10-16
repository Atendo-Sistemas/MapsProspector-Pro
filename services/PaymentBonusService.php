<?php
/**
 * Bônus promocional de contratação de plano.
 * O bônus é separado da franquia e só pode ser concedido uma vez por empresa.
 */

declare(strict_types=1);

function mpGrantPlanContractBonus(PDO $db, int $tenantId, string $sourceType, string $sourceId, int $credits = 50): bool
{
    if ($tenantId <= 0 || $credits <= 0 || trim($sourceType) === '' || trim($sourceId) === '') {
        return false;
    }

    $insert = $db->prepare(
        'INSERT IGNORE INTO payment_bonus_grants
            (tenant_id, bonus_code, source_type, source_id, credits, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())'
    );
    $insert->execute([
        $tenantId,
        'plan_contract_bonus_v1',
        substr(trim($sourceType), 0, 32),
        substr(trim($sourceId), 0, 100),
        $credits,
    ]);

    if ($insert->rowCount() === 0) {
        return false;
    }

    $period = 'monthly';
    try {
        $plan = $db->prepare('SELECT p.period FROM tenants t INNER JOIN plans p ON p.id = t.plan_id WHERE t.id = ? LIMIT 1');
        $plan->execute([$tenantId]);
        $period = (string) (($plan->fetch(PDO::FETCH_ASSOC)['period'] ?? 'monthly'));
    } catch (Throwable $e) {
        error_log('payment bonus period lookup: ' . $e->getMessage());
    }

    $periodStart = function_exists('getCurrentPeriodStart')
        ? getCurrentPeriodStart($period)
        : ($period === 'yearly' ? date('Y-01-01') : date('Y-m-01'));
    $usage = $db->prepare(
        'INSERT INTO tenant_usage (tenant_id, period_start, tokens_used, token_bonus)
         VALUES (?, ?, 0, ?)
         ON DUPLICATE KEY UPDATE token_bonus = COALESCE(token_bonus, 0) + ?'
    );
    $usage->execute([$tenantId, $periodStart, $credits, $credits]);
    return true;
}
