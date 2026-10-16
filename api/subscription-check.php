<?php
/**
 * Conferência diária de assinaturas recorrentes Asaas.
 *
 * Uso exclusivo por cron local do cPanel. Este arquivo não aceita chamadas
 * HTTP, evitando que um visitante force rebaixamentos fora da rotina prevista.
 * Exemplo de cron diário (03:17):
 * 17 3 * * * /usr/local/bin/php /home/USUARIO/prospec.atendo.log.br/api/subscription-check.php >/dev/null 2>&1
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Rotina disponível somente pelo cron local.']);
    exit;
}

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/services/AsaasSubscriptionService.php';
require_once dirname(__DIR__) . '/services/notificationService.php';

try {
    $db = Database::getInstance()->getConnection();
    $result = mpAsaasExpireDueSubscriptions($db);
    $reengagement = NotificationService::notifyInactiveUsers($db, 2, 100);
    echo json_encode([
        'ok' => true,
        'checked' => (int) ($result['checked'] ?? 0),
        'downgraded' => (int) ($result['downgraded'] ?? 0),
        'reengagement' => [
            'checked' => (int) ($reengagement['checked'] ?? 0),
            'notified' => (int) ($reengagement['notified'] ?? 0),
            'internal' => (int) ($reengagement['internal'] ?? 0),
            'whatsapp' => (int) ($reengagement['whatsapp'] ?? 0),
            'email' => (int) ($reengagement['email'] ?? 0),
            'skipped' => (int) ($reengagement['skipped'] ?? 0),
        ],
        'checked_at' => gmdate('c'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit;
} catch (Throwable $e) {
    error_log('subscription-check: ' . $e->getMessage());
    fwrite(STDERR, "subscription-check failed\n");
    exit(1);
}
