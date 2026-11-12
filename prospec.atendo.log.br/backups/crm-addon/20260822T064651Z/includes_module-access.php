<?php
/**
 * Controle central de módulos do MapsProspector.
 * O teste libera todos os módulos; fora do teste, a relação plan_modules
 * determina o acesso da empresa.
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
            "SELECT p.slug, t.trial_ends_at
               FROM tenants t
               INNER JOIN plans p ON p.id = t.plan_id
              WHERE t.id = ? LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || (string) ($row['slug'] ?? '') !== 'trial') {
            return false;
        }
        // Contas legadas sem data continuam compatíveis com o teste atual.
        return empty($row['trial_ends_at']) || (string) $row['trial_ends_at'] >= date('Y-m-d H:i:s');
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
