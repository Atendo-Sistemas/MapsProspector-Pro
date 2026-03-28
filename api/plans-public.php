<?php
/**
 * API de Planos (listagem pública para cliente)
 * Endpoint: /api/plans-public.php
 * Qualquer usuário pode listar planos ativos para escolher e solicitar.
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

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
    echo json_encode(['success' => true, 'data' => ['items' => $items, 'total' => count($items)]]);
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'exist') !== false || strpos($e->getMessage(), '1146') !== false) {
        echo json_encode(['success' => true, 'data' => ['items' => [], 'total' => 0]]);
        exit;
    }
    error_log("plans-public.php GET: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro ao listar planos.']);
}
