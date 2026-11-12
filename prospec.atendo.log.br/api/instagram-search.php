<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/instagramApifyService.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') jsonError('Método não permitido.', 405);
    $userId = (int) requireAuth();
    $auth = getAuthUser();
    $profile = strtolower(trim((string) ($auth['profile'] ?? $auth['role'] ?? $_SESSION['profile'] ?? '')));
    $isSuperAdmin = $profile === 'super_admin';
    $db = Database::getInstance()->getConnection();
    $tenantId = $isSuperAdmin ? max(1, (int) ($auth['tenant_id'] ?? 1)) : mpResolveEffectiveTenantId($db, $auth);
    if (!$isSuperAdmin && $tenantId <= 1) jsonError('Sua conta não possui uma empresa vinculada.', 403);
    requireSearchPermission($db);
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) jsonError('Dados da pesquisa inválidos.', 422);
    $mode = 'post';
    if (isset($input['mode']) && strtolower(trim((string) $input['mode'])) === 'location') {
        jsonError('A busca por localidade foi desativada. Informe a URL de uma publicação ou Reel do Instagram.', 422);
    }
    $commentsLimit = max(1, min(250, (int) ($input['comments_limit'] ?? 50)));
    $totalLimit = max($commentsLimit, min(500, (int) ($input['total_comments_limit'] ?? 300)));
    $includeReplies = !empty($input['include_replies']);
    $value = trim((string) ($input['value'] ?? ''));
    if ($value === '') jsonError('Informe a URL da publicação ou Reel.', 422);

    if (!$isSuperAdmin && $tenantId > 1) {
        $balance = mpTenantCreditBalance($db, $tenantId);
        if ($balance['available'] !== null) {
            $available = max(0, (int) $balance['available']);
            if ($available < 1) jsonError('Saldo insuficiente para iniciar uma nova pesquisa Instagram.', 402);
            $commentsLimit = min($commentsLimit, $available);
            $totalLimit = min($totalLimit, max($commentsLimit, $available));
        }
    }

    $service = new InstagramApifyService($db);
    $normalized = null;
    $value = InstagramApifyService::normalizeInstagramUrl($value);
    $started = $service->startComments($value, $commentsLimit, $includeReplies);
    $stage = 'comments';

    $stmt = $db->prepare(
        'INSERT INTO instagram_runs
         (tenant_id, user_id, mode, input_value, normalized_location, status, stage, requested_posts, requested_comments, max_total_comments, external_run_id, dataset_id, actor_id, api_key_id, started_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmt->execute([
        $tenantId, $userId, $mode, $value, $normalized, 'running', $stage,
        0, $commentsLimit, $totalLimit,
        $started['run_id'], $started['dataset_id'], $started['actor'], $started['key_id']
    ]);
    $runId = (int) $db->lastInsertId();
    jsonSuccess([
        'runId' => $runId,
        'status' => 'running',
        'stage' => $stage,
        'mode' => $mode,
        'commentsLimit' => $commentsLimit,
        'totalCommentsLimit' => $totalLimit,
        'message' => 'Publicação recebida; comentários públicos estão sendo processados.'
    ]);
} catch (InvalidArgumentException $e) {
    jsonError($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('instagram-search: ' . $e->getMessage());
    jsonError('Não foi possível iniciar a pesquisa Instagram agora. Tente novamente.', 502);
}
