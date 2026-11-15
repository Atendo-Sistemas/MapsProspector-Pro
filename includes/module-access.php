<?php
/**
 * Controle central de módulos do MapsProspector.
 * O período gratuito libera recursos de pesquisa; exportação fica reservada
 * a planos pagos com direito explícito, validado no servidor.
 */

declare(strict_types=1);

function mpModuleCatalog(): array
{
    return [
        'maps' => 'Prospecção no Google Maps',
        'linkedin' => 'Prospecção no LinkedIn',
        'cnpj' => 'Consulta e enriquecimento CNPJ',
        'crm' => 'CRM comercial',
        'export' => 'Exportações',
    ];
}

function mpModuleTablesReady(PDO $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    try {
        $ready = (bool) $db->query("SHOW TABLES LIKE 'plan_modules'")->fetchColumn();
    } catch (Throwable $e) {
        $ready = false;
    }
    return $ready;
}

function mpTenantIsTrial(PDO $db, int $tenantId): bool
{
    if ($tenantId <= 0) {
        return false;
    }
    try {
        $stmt = $db->prepare(
            'SELECT p.slug FROM tenants t INNER JOIN plans p ON p.id = t.plan_id WHERE t.id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $slug = (string) $stmt->fetchColumn();
        if ($slug === 'freemium-20') {
            return true;
        }
        if ($slug !== 'trial') {
            return false;
        }
        try {
            $stmt = $db->prepare('SELECT trial_ends_at FROM tenants WHERE id = ? LIMIT 1');
            $stmt->execute([$tenantId]);
            $trialEndsAt = $stmt->fetchColumn();
            return empty($trialEndsAt) || (string) $trialEndsAt >= date('Y-m-d H:i:s');
        } catch (Throwable $ignored) {
            // Schemas legados sem a coluna mantêm o comportamento de teste atual.
            return true;
        }
    } catch (Throwable $e) {
        return false;
    }
}

function mpTenantCanExport(PDO $db, int $tenantId, ?array $user = null): bool
{
    $profile = strtolower((string) (($user['profile'] ?? $_SESSION['profile'] ?? '') ?: ''));
    if ($profile === 'super_admin' || ($profile === 'admin' && $tenantId === 1)) {
        return true;
    }
    if ($tenantId <= 0) {
        return false;
    }

    try {
        $hasAccessUntil = false;
        try {
            $hasAccessUntil = (bool) $db->query("SHOW COLUMNS FROM tenants LIKE 'plan_access_until'")->fetchColumn();
        } catch (Throwable $ignored) {
        }
        $accessUntilSelect = $hasAccessUntil ? ', t.plan_access_until' : '';
        $stmt = $db->prepare(
            "SELECT t.status AS tenant_status, p.id AS plan_id, p.slug AS plan_slug,
                    p.status AS plan_status, COALESCE(p.price_monthly, 0) AS plan_price{$accessUntilSelect}
               FROM tenants t
               INNER JOIN plans p ON p.id = t.plan_id
              WHERE t.id = ? LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($plan)
            || strtolower((string) ($plan['tenant_status'] ?? '')) !== 'active'
            || strtolower((string) ($plan['plan_status'] ?? '')) !== 'active'
            || in_array((string) ($plan['plan_slug'] ?? ''), ['trial', 'freemium-20'], true)
            || (float) ($plan['plan_price'] ?? 0) <= 0) {
            return false;
        }
        if ($hasAccessUntil && !empty($plan['plan_access_until']) && (string) $plan['plan_access_until'] < date('Y-m-d')) {
            return false;
        }
        if (!mpModuleTablesReady($db)) {
            // Planos pagos anteriores à matriz mantêm a permissão que já tinham.
            return true;
        }
        $module = $db->prepare('SELECT 1 FROM plan_modules WHERE plan_id = ? AND module_key = \'export\' AND is_active = 1 LIMIT 1');
        $module->execute([(int) $plan['plan_id']]);
        return (bool) $module->fetchColumn();
    } catch (Throwable $e) {
        error_log('module access: export permission lookup failed.');
        return false;
    }
}

function mpTenantHasActiveCrmAddon(PDO $db, int $tenantId): bool
{
    if ($tenantId <= 0) return false;
    try {
        $stmt = $db->prepare(
            "SELECT 1
               FROM crm_licenses cl
               INNER JOIN tenants t ON t.id = cl.tenant_id
               LEFT JOIN plan_modules pm
                 ON pm.plan_id = t.plan_id AND pm.module_key = 'crm' AND pm.is_active = 1
              WHERE cl.tenant_id = ? AND cl.status = 'active'
                AND (
                    COALESCE(cl.source_type, 'legacy') IN ('manual', 'asaas')
                    OR (COALESCE(cl.source_type, 'legacy') = 'legacy' AND pm.plan_id IS NOT NULL)
                )
              LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function mpTenantHasModule(PDO $db, int $tenantId, string $moduleKey, ?array $user = null): bool
{
    $moduleKey = strtolower(trim($moduleKey));
    if (!array_key_exists($moduleKey, mpModuleCatalog())) {
        return false;
    }
    $profile = strtolower((string) (($user['profile'] ?? $_SESSION['profile'] ?? '') ?: ''));
    if ($profile === 'super_admin' || ($profile === 'admin' && $tenantId === 1)) {
        return true;
    }
    if ($tenantId <= 0) {
        return false;
    }
    if ($moduleKey === 'export') {
        return mpTenantCanExport($db, $tenantId, $user);
    }
    if ($moduleKey === 'crm' && mpTenantHasActiveCrmAddon($db, $tenantId)) {
        return true;
    }
    if (mpTenantIsTrial($db, $tenantId)) {
        return true;
    }
    if (!mpModuleTablesReady($db)) {
        // Compatibilidade segura: antes da migração, os planos existentes
        // mantêm acesso aos recursos atuais em vez de causar bloqueio abrupto.
        return true;
    }
    try {
        $stmt = $db->prepare(
            'SELECT 1
               FROM plan_modules pm
               INNER JOIN tenants t ON t.plan_id = pm.plan_id
              WHERE t.id = ? AND pm.module_key = ? AND pm.is_active = 1
              LIMIT 1'
        );
        $stmt->execute([$tenantId, $moduleKey]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        error_log('module access: ' . $e->getMessage());
        return false;
    }
}

function mpTenantModules(PDO $db, int $tenantId, ?array $user = null): array
{
    $modules = [];
    foreach (mpModuleCatalog() as $key => $label) {
        $modules[$key] = [
            'key' => $key,
            'label' => $label,
            'enabled' => mpTenantHasModule($db, $tenantId, $key, $user),
        ];
    }
    return $modules;
}
