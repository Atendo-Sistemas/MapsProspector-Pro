<?php
declare(strict_types=1);

function crmAddonTableReady(PDO $db, string $table = 'crm_addon_requests'): bool
{
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function crmAddonFindPlan(PDO $db, int $planId, bool $activeOnly = true): ?array
{
    if ($planId <= 0) return null;
    $sql = 'SELECT id, name, slug, price_monthly, period, status, catalog_group, included_users, addon_user_price_cents FROM plans WHERE id = ? AND catalog_group = \'crm_addon\'';
    if ($activeOnly) $sql .= " AND status = 'active'";
    $sql .= ' LIMIT 1';
    $stmt = $db->prepare($sql);
    $stmt->execute([$planId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function crmAddonActiveLicense(PDO $db, int $tenantId): ?array
{
    if ($tenantId <= 0) return null;
    try {
        $stmt = $db->prepare(
            "SELECT cl.*
               FROM crm_licenses cl
               INNER JOIN tenants t ON t.id = cl.tenant_id
               LEFT JOIN plan_modules pm
                 ON pm.plan_id = t.plan_id AND pm.module_key = 'crm' AND pm.is_active = 1
              WHERE cl.tenant_id = ? AND cl.status = 'active'
                AND (
                    COALESCE(cl.source_type, 'legacy') IN ('manual', 'asaas')
                    OR (COALESCE(cl.source_type, 'legacy') = 'legacy' AND pm.plan_id IS NOT NULL)
                )
              ORDER BY cl.id DESC LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

function crmAddonAvailablePlans(PDO $db): array
{
    try {
        $stmt = $db->query("SELECT id, name, slug, price_monthly, period, status, catalog_group, included_users, addon_user_price_cents FROM plans WHERE catalog_group = 'crm_addon' AND status = 'active' ORDER BY price_monthly ASC, included_users ASC, name ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return [];
    }
    return array_map(static function (array $row): array {
        return [
            'id' => (string) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'priceMonthly' => (float) ($row['price_monthly'] ?? 0),
            'period' => (string) ($row['period'] ?? 'monthly'),
            'includedUsers' => max(1, (int) ($row['included_users'] ?? 3)),
            'addonUserPriceCents' => max(0, (int) ($row['addon_user_price_cents'] ?? 1990)),
            'catalogGroup' => 'crm_addon',
            'contractBonusCredits' => 0,
        ];
    }, $rows);
}

function crmAddonLicensePayload(PDO $db, int $tenantId, ?array $license = null): ?array
{
    $license = $license ?: crmAddonActiveLicense($db, $tenantId);
    if (!is_array($license)) return null;
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND (status IS NULL OR status = 'active')");
        $stmt->execute([$tenantId]);
        $currentUsers = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $currentUsers = (int) $stmt->fetchColumn();
    }
    $included = max(1, (int) ($license['included_users'] ?? 3));
    $unit = max(0, (int) ($license['additional_user_price_cents'] ?? 1990));
    $excess = max(0, $currentUsers - $included);
    return [
        'id' => (int) ($license['id'] ?? 0),
        'status' => (string) ($license['status'] ?? 'active'),
        'planId' => isset($license['plan_id']) && $license['plan_id'] !== null ? (int) $license['plan_id'] : null,
        'sourceType' => (string) ($license['source_type'] ?? 'legacy'),
        'includedUsers' => $included,
        'maxUsers' => max($included, (int) ($license['max_users'] ?? $included)),
        'currentUsers' => $currentUsers,
        'excessUsers' => $excess,
        'additionalUserPriceCents' => $unit,
        'estimatedExcessMonthlyCents' => $excess * $unit,
        'billingNote' => 'Usuários dentro da franquia incluída não geram cobrança adicional. Excedentes são calculados para a próxima cobrança e não são faturados automaticamente por esta API.',
    ];
}

function crmAddonCreateManualRequest(PDO $db, array $user, int $tenantId, int $planId): array
{
    if ($tenantId <= 0) throw new InvalidArgumentException('Empresa não encontrada para esta conta.');
    $plan = crmAddonFindPlan($db, $planId, true);
    if (!$plan) throw new InvalidArgumentException('O plano de CRM adicional não está ativo para contratação.');
    if (crmAddonActiveLicense($db, $tenantId)) {
        throw new InvalidArgumentException('Sua empresa já possui uma licença CRM ativa.');
    }
    $pending = $db->prepare("SELECT id FROM crm_addon_requests WHERE tenant_id = ? AND plan_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
    $pending->execute([$tenantId, $planId]);
    $existing = $pending->fetch(PDO::FETCH_ASSOC);
    if (is_array($existing)) {
        return ['mode' => 'manual', 'requestId' => (int) $existing['id'], 'alreadyPending' => true, 'addonName' => (string) $plan['name']];
    }
    $amountCents = max(0, (int) round(((float) ($plan['price_monthly'] ?? 0)) * 100));
    $included = max(1, (int) ($plan['included_users'] ?? 3));
    $unit = max(0, (int) ($plan['addon_user_price_cents'] ?? 1990));
    $db->beginTransaction();
    try {
        $payment = $db->prepare("INSERT INTO payments (tenant_id, user_id, type, amount, amount_cents, credits, plan_id, status, external_id, created_at) VALUES (?, ?, 'crm_addon', ?, ?, 0, ?, 'pending', ?, NOW())");
        $externalId = 'MANUAL-CRM-' . strtolower(bin2hex(random_bytes(8)));
        $payment->execute([$tenantId, (int) ($user['id'] ?? 0), $amountCents / 100, $amountCents, $planId, $externalId]);
        $request = $db->prepare("INSERT INTO crm_addon_requests (tenant_id, requested_by_user_id, plan_id, status, included_users, additional_user_price_cents, created_at) VALUES (?, ?, ?, 'pending', ?, ?, NOW())");
        $request->execute([$tenantId, (int) ($user['id'] ?? 0), $planId, $included, $unit]);
        $requestId = (int) $db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    try {
        require_once __DIR__ . '/notificationService.php';
        $settings = $db->prepare('SELECT tenant_name FROM settings WHERE user_id = ? LIMIT 1');
        $settings->execute([(int) ($user['id'] ?? 0)]);
        $companyName = trim((string) (($settings->fetch(PDO::FETCH_ASSOC)['tenant_name'] ?? '') ?: 'sua empresa'));
        if (method_exists('NotificationService', 'notifyPlanRequest')) {
            NotificationService::notifyPlanRequest($db, $companyName, 'CRM adicional — ' . (string) $plan['name']);
        }
    } catch (Throwable $notificationError) {
        error_log('crm addon request notification: ' . $notificationError->getMessage());
    }
    return ['mode' => 'manual', 'requestId' => $requestId, 'alreadyPending' => false, 'addonName' => (string) $plan['name']];
}

function crmAddonActivateLicense(PDO $db, int $tenantId, int $planId, string $sourceType): array
{
    $plan = crmAddonFindPlan($db, $planId, false);
    if (!$plan) throw new RuntimeException('Oferta de CRM adicional não encontrada.');
    $existingStmt = $db->prepare("SELECT * FROM crm_licenses WHERE tenant_id = ? ORDER BY CASE status WHEN 'active' THEN 0 WHEN 'paused' THEN 1 WHEN 'cancelled' THEN 2 ELSE 3 END, id DESC LIMIT 1 FOR UPDATE");
    $existingStmt->execute([$tenantId]);
    $existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
    $included = max(1, (int) ($plan['included_users'] ?? 3));
    $unit = max(0, (int) ($plan['addon_user_price_cents'] ?? 1990));
    if (is_array($existing)) {
        $stmt = $db->prepare("UPDATE crm_licenses SET plan_id = ?, included_users = ?, max_users = ?, additional_user_price_cents = ?, source_type = ?, status = 'active', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$planId, $included, $included, $unit, $sourceType, (int) $existing['id']]);
        $id = (int) $existing['id'];
    } else {
        $stmt = $db->prepare("INSERT INTO crm_licenses (tenant_id, plan_id, included_users, max_users, additional_user_price_cents, source_type, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())");
        $stmt->execute([$tenantId, $planId, $included, $included, $unit, $sourceType]);
        $id = (int) $db->lastInsertId();
    }
    return ['id' => $id, 'planId' => $planId, 'includedUsers' => $included, 'additionalUserPriceCents' => $unit];
}

function crmAddonPauseLicense(PDO $db, int $tenantId, int $planId): bool
{
    $stmt = $db->prepare("UPDATE crm_licenses SET status = 'paused', updated_at = NOW() WHERE tenant_id = ? AND plan_id = ? AND status = 'active'");
    $stmt->execute([$tenantId, $planId]);
    return $stmt->rowCount() > 0;
}
