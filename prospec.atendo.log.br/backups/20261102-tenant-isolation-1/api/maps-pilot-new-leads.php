<?php
declare(strict_types=1);
/**
 * Botão "Verificar novos leads" da aba Maps.
 * Endpoint: GET /api/maps-pilot-new-leads.php?search_history_id=N
 *
 * Recarrega os leads do banco da pesquisa (sem tocar em nenhuma fonte
 * externa) e os devolve com as mesmas chaves do endpoint principal, para que
 * a sessão do navegador consiga remarcar quais leads ainda não foram
 * exibidos com a tag "Novo". Não consome créditos — apenas lê o banco.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();
register_shutdown_function(static function (): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (ob_get_length()) {
            ob_clean();
        }
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Não foi possível verificar novos leads.'], JSON_UNESCAPED_UNICODE);
    }
});
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function mapsPilotNewLeadsError(string $message, int $status = 400): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    jsonError($message, $status);
    exit;
}

function mapsPilotNewLeadsSessionUser(PDO $db): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $userId = (int) ($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? ($_SESSION['ncSessionUser']['id'] ?? 0));
    $email = trim((string) ($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''));
    foreach (['ncSessionUser', 'user', 'auth_user', 'current_user', 'user_data'] as $sessionKey) {
        $candidate = $_SESSION[$sessionKey] ?? null;
        if (is_array($candidate)) {
            $userId = $userId ?: (int) ($candidate['id'] ?? $candidate['user_id'] ?? 0);
            $email = $email !== '' ? $email : trim((string) ($candidate['email'] ?? ''));
        }
    }
    $tenantId = (int) ($_SESSION['tenant_id'] ?? ($_SESSION['ncSessionUser']['tenant_id'] ?? 0));
    $profile = strtolower((string) ($_SESSION['profile'] ?? ($_SESSION['ncSessionUser']['profile'] ?? '')));
    if ($userId <= 0) {
        mapsPilotNewLeadsError('Autenticação necessária.', 401);
    }
    $tenantStmt = $db->prepare('SELECT tenant_id, profile FROM users WHERE id = ? LIMIT 1');
    $tenantStmt->execute([$userId]);
    $tenantRow = $tenantStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $tenantId = (int) ($tenantRow['tenant_id'] ?? $tenantId);
    $profile = strtolower((string) ($tenantRow['profile'] ?? $profile));
    $isPlatformAdmin = $profile === 'super_admin';
    if ($tenantId <= 0 && !$isPlatformAdmin) {
        mapsPilotNewLeadsError('Conta sem empresa vinculada.', 403);
    }
    return ['id' => $userId, 'email' => $email, 'tenant_id' => $tenantId, 'profile' => $profile];
}

try {
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') {
        mapsPilotNewLeadsError('Método não permitido.', 405);
    }
    $db = Database::getInstance()->getConnection();
    $user = mapsPilotNewLeadsSessionUser($db);
    $userId = (int) $user['id'];
    $tenantId = (int) $user['tenant_id'];
    $profile = strtolower((string) ($user['profile'] ?? ''));
    $isPlatformAdmin = $profile === 'super_admin';

    $searchHistoryId = filter_var($_GET['search_history_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($searchHistoryId === false || $searchHistoryId <= 0) {
        mapsPilotNewLeadsError('Informe a pesquisa salva que deseja verificar (search_history_id).');
    }

    // search_history não possui tenant_id: a empresa é resolvida pelo usuário
    // proprietário do histórico. Usuários comuns veem apenas o próprio histórico;
    // gestores podem consultar históricos da própria empresa; o super_admin pode
    // consultar seu próprio histórico de plataforma sem tenant.
    $canManageCompanyHistory = in_array($profile, ['admin', 'supervisor', 'manager', 'gerente'], true) && $tenantId > 0;
    if ($isPlatformAdmin && $tenantId <= 0) {
        $historyStmt = $db->prepare('SELECT id, query, location, results_count FROM search_history WHERE id = ? AND user_id = ?');
        $historyStmt->execute([$searchHistoryId, $userId]);
    } elseif ($canManageCompanyHistory) {
        $historyStmt = $db->prepare(
            'SELECT sh.id, sh.query, sh.location, sh.results_count
               FROM search_history sh
               INNER JOIN users owner ON owner.id = sh.user_id
              WHERE sh.id = ? AND (sh.user_id = ? OR owner.tenant_id = ?)
              LIMIT 1'
        );
        $historyStmt->execute([$searchHistoryId, $userId, $tenantId]);
    } else {
        $historyStmt = $db->prepare('SELECT id, query, location, results_count FROM search_history WHERE id = ? AND user_id = ?');
        $historyStmt->execute([$searchHistoryId, $userId]);
    }
    $historyRow = $historyStmt->fetch(PDO::FETCH_ASSOC);
    if (!$historyRow) {
        mapsPilotNewLeadsError('Pesquisa não encontrada ou pertence a outra conta.', 404);
    }

    $leadsStmt = $db->prepare('SELECT id, name, address, phone, email, website, maps_uri, cnpj, partners, tag, latitude, longitude FROM leads WHERE search_history_id = ? ORDER BY id ASC');
    $leadsStmt->execute([$searchHistoryId]);
    $leads = $leadsStmt->fetchAll(PDO::FETCH_ASSOC);

    if (ob_get_length()) {
        ob_clean();
    }
    jsonSuccess([
        'searchId' => (string) $searchHistoryId,
        'leads' => array_map(static function (array $lead): array {
            return [
                'id' => (string) ($lead['id'] ?? ''),
                'name' => $lead['name'] ?? '',
                'address' => $lead['address'] ?? null,
                'phone' => $lead['phone'] ?? null,
                'email' => $lead['email'] ?? null,
                'website' => $lead['website'] ?? null,
                'maps_uri' => $lead['maps_uri'] ?? null,
                'cnpj' => $lead['cnpj'] ?? null,
                'partners' => $lead['partners'] ?? null,
                'tag' => $lead['tag'] ?? null,
                'latitude' => isset($lead['latitude']) ? (float) $lead['latitude'] : null,
                'longitude' => isset($lead['longitude']) ? (float) $lead['longitude'] : null,
            ];
        }, $leads),
        'total_count' => count($leads),
        'query' => $historyRow['query'] ?? null,
        'location' => $historyRow['location'] ?? null,
        'meta' => ['source' => 'bank', 'credits_charged' => 0],
    ], 'Leads da pesquisa recarregados do banco. Os novos estão marcados com a tag "Novo".');
} catch (Throwable $error) {
    error_log('maps-pilot-new-leads.php: ' . $error->getMessage());
    mapsPilotNewLeadsError('Não foi possível verificar novos leads. Tente novamente em alguns instantes.', 502);
}
