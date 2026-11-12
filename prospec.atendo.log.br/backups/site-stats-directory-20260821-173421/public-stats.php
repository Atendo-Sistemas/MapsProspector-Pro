<?php
declare(strict_types=1);

// Estatísticas públicas agregadas: não expõe usuários, empresas ou dados de uso.
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance()->getConnection();
    $hasTenants = (bool) $db->query("SHOW TABLES LIKE 'tenants'")->fetchColumn();
    $count = 0;
    if ($hasTenants) {
        $hasStatus = (bool) $db->query("SHOW COLUMNS FROM tenants LIKE 'status'")->fetchColumn();
        if ($hasStatus) {
            $stmt = $db->query("SELECT COUNT(*) FROM tenants WHERE COALESCE(status, 'active') IN ('active', 'trial', 'enabled')");
        } else {
            $stmt = $db->query('SELECT COUNT(*) FROM tenants');
        }
        $count = (int) $stmt->fetchColumn();
    } else {
        $count = (int) $db->query("SELECT COUNT(DISTINCT tenant_id) FROM users WHERE tenant_id IS NOT NULL")->fetchColumn();
    }
    echo json_encode(['success' => true, 'data' => ['companies_using' => max(0, $count)]], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    error_log('public-stats: ' . $error->getMessage());
    echo json_encode(['success' => false, 'data' => ['companies_using' => 0]], JSON_UNESCAPED_UNICODE);
}
