<?php
/**
 * API de Planos (listagem pública para cliente)
 * Endpoint: /api/plans-public.php
 * Qualquer usuário pode listar planos ativos para escolher e solicitar.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/module-access.php';

ob_clean();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido']);
    exit;
}

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("
        SELECT p.id, p.name, p.slug, p.token_limit, p.price_monthly, p.period,
               COALESCE(p.included_users, 1) AS included_users
        FROM plans p
        WHERE p.status = 'active'
        ORDER BY p.token_limit ASC, p.name ASC
    ");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $items = [];
    $moduleStmt = null;
    try {
        $moduleStmt = $db->prepare('SELECT module_key FROM plan_modules WHERE plan_id = ? AND is_active = 1 ORDER BY module_key ASC');
    } catch (Throwable $ignored) {
        $moduleStmt = null;
    }
    foreach ($plans as $row) {
        $modules = [];
        if ($moduleStmt) {
            try {
                $moduleStmt->execute([(int) $row['id']]);
                $modules = array_values(array_map('strval', $moduleStmt->fetchAll(PDO::FETCH_COLUMN)));
            } catch (Throwable $ignored) {
                $modules = [];
            }
        }
        $items[] = [
            'id' => (string) $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'tokenLimit' => (int) $row['token_limit'],
            'priceMonthly' => isset($row['price_monthly']) ? (float) $row['price_monthly'] : 0,
            'period' => $row['period'] ?? 'monthly',
            'includedUsers' => max(1, (int) ($row['included_users'] ?? 1)),
            'modules' => $modules,
            'moduleLabels' => array_values(array_map(static function (string $module): string {
                $catalog = mpModuleCatalog();
                return $catalog[$module] ?? $module;
            }, $modules)),
            'contractBonusCredits' => $row['slug'] === 'trial' ? 0 : 50,
        ];
    }
    echo json_encode(['success' => true, 'data' => ['items' => $items, 'total' => count($items)]]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'exist') !== false || strpos($e->getMessage(), '1146') !== false) {
        echo json_encode(['success' => true, 'data' => ['items' => [], 'total' => 0]]);
        exit;
    }
    error_log("plans-public.php GET: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao listar planos.']);
}
