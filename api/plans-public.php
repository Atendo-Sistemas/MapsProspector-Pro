<?php
/**
 * API de Planos (listagem pública para cliente)
 * Endpoint: /api/plans-public.php
 * Qualquer usuário autenticado pode listar planos ativos para escolher e solicitar.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();

$userId = requireAuth();
$db = Database::getInstance()->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    jsonError('Método não permitido', 405);
}

try {
    // Inclui todos os planos ativos (incluindo trial) para exibir em "Meu plano" com créditos/tokens
    $stmt = $db->query("
        SELECT p.id, p.name, p.slug, p.token_limit, p.price_monthly, p.period
        FROM plans p
        WHERE p.status = 'active'
        ORDER BY p.token_limit ASC, p.name ASC
    ");
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $items = [];
    foreach ($plans as $row) {
        $items[] = [
            'id' => (string) $row['id'],
            'name' => $row['name'],
            'slug' => $row['slug'],
            'tokenLimit' => (int) $row['token_limit'],
            'priceMonthly' => isset($row['price_monthly']) ? (float) $row['price_monthly'] : 0,
            'period' => $row['period'] ?? 'monthly',
        ];
    }
    jsonSuccess(['items' => $items, 'total' => count($items)]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'exist') !== false || strpos($e->getMessage(), '1146') !== false) {
        jsonSuccess(['items' => [], 'total' => 0]);
        exit;
    }
    error_log("plans-public.php GET: " . $e->getMessage());
    jsonError('Erro ao listar planos.', 500);
}
