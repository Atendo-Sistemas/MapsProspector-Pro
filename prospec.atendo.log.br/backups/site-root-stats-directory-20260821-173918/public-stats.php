<?php
/**
 * Estatísticas públicas da plataforma.
 * Endpoint: GET /api/public-stats.php
 * Retorna apenas contagens agregadas, sem dados pessoais ou identificadores.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $db = Database::getInstance()->getConnection();

    $companiesStmt = $db->query(
        "SELECT COUNT(*)
         FROM tenants
         WHERE status = 'active'"
    );
    $companies = (int) $companiesStmt->fetchColumn();

    $usersStmt = $db->query(
        "SELECT COUNT(*)
         FROM users u
         INNER JOIN tenants t ON t.id = u.tenant_id
         WHERE t.status = 'active'"
    );
    $users = (int) $usersStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'data' => [
            'companies' => $companies,
            'users' => $users,
            'updatedAt' => gmdate('c'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('public-stats.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Não foi possível carregar as estatísticas agora.',
    ], JSON_UNESCAPED_UNICODE);
}
