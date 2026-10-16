<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
header('Pragma: no-cache');

requireSuperAdmin();
$db = Database::getInstance()->getConnection();

try {
    $stmt = $db->query(
        "SELECT service, provider,
                COUNT(*) AS searches,
                SUM(status = 'empty') AS empty_searches,
                SUM(status = 'partial') AS partial_searches,
                SUM(status = 'error') AS failed_searches,
                SUM(result_count) AS results,
                SUM(duplicate_count) AS duplicates,
                SUM(missing_contact_count) AS missing_contacts,
                ROUND(AVG(latency_ms)) AS avg_latency_ms,
                SUM(cache_hit) AS cache_hits,
                SUM(credits_spent) AS credits_spent
           FROM search_quality_events
          WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
          GROUP BY service, provider
          ORDER BY service, provider"
    );
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    jsonSuccess(['periodDays' => 30, 'services' => $rows]);
} catch (Throwable $error) {
    error_log('search-quality-metrics: ' . $error->getMessage());
    jsonError('Métricas de qualidade indisponíveis. Verifique se a migração de qualidade das pesquisas foi aplicada.', 503);
}
