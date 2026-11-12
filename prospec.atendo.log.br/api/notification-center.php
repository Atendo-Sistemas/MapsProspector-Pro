<?php
/**
 * Maps Prospector — Central de Notificações internas v20260817-41.
 *
 * GET  ?action=list&filter=all|unread&limit=50
 * GET  ?action=count
 * GET  ?action=directory
 * POST action=markRead|markAllRead|create
 *
 * A API usa sessão no servidor e reaplica todas as regras de escopo. O cliente
 * não escolhe livremente empresa ou destinatário fora das permissões da conta.
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

function ncJson(bool $success, array $data = [], string $message = '', int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    $payload = ['success' => $success];
    if ($data !== []) $payload['data'] = $data;
    if ($message !== '') $payload[$success ? 'message' : 'error'] = $message;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function ncInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function ncSessionUser(PDO $db): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $candidates = [$_SESSION['ncSessionUser'] ?? null, $_SESSION['user'] ?? null, $_SESSION['auth_user'] ?? null, $_SESSION['current_user'] ?? null, $_SESSION['user_data'] ?? null];
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) continue;
        $profile = strtolower((string)($candidate['profile'] ?? $candidate['role'] ?? $candidate['user_type'] ?? ''));
        $id = $candidate['id'] ?? $candidate['user_id'] ?? null;
        if (($id === null || $id === '') && $profile !== 'super_admin') continue;
        return [
            'id' => $id === null || $id === '' ? 0 : (int)$id,
            'email' => (string)($candidate['email'] ?? ''),
            'name' => (string)($candidate['name'] ?? $candidate['full_name'] ?? $candidate['email'] ?? 'Usuário'),
            'profile' => $profile,
            'tenant_id' => isset($candidate['tenant_id']) ? (int)$candidate['tenant_id'] : (isset($candidate['tenantId']) ? (int)$candidate['tenantId'] : null),
        ];
    }
    $profile = strtolower((string)($_SESSION['profile'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? ''));
    if ($profile === 'super_admin' || !empty($_SESSION['is_super_admin'])) {
        return ['id' => 0, 'email' => (string)($_SESSION['email'] ?? ''), 'name' => 'Super Admin', 'profile' => 'super_admin', 'tenant_id' => null];
    }
    $id = $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? null;
    if (!$id) return null;
    $stmt = $db->prepare('SELECT id, tenant_id, name, email, profile FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($row)) return null;
    return ['id' => (int)$row['id'], 'email' => (string)$row['email'], 'name' => (string)$row['name'], 'profile' => strtolower((string)$row['profile']), 'tenant_id' => $row['tenant_id'] === null ? null : (int)$row['tenant_id']];
}

function ncReaderKey(array $user): string
{
    if ((int)$user['id'] > 0) return 'u:' . (int)$user['id'];
    return 'sa:' . hash('sha256', strtolower(trim((string)$user['email'])) ?: 'platform');
}

function ncTableReady(PDO $db): bool
{
    return (bool)$db->query("SHOW TABLES LIKE 'internal_notifications'")->fetchColumn()
        && (bool)$db->query("SHOW TABLES LIKE 'internal_notification_reads'")->fetchColumn();
}

function ncVisibleClause(array $user, array &$params): string
{
    $profile = (string)$user['profile'];
    $profile = in_array($profile, ['super_admin', 'admin', 'supervisor', 'manager', 'gerente', 'normal', 'user'], true) ? $profile : 'normal';
    $tenantId = $user['tenant_id'];
    $params[':nc_profile_only'] = $profile;
    $params[':nc_user_only'] = (int)$user['id'];
    $base = "(n.expires_at IS NULL OR n.expires_at > NOW()) AND (n.recipient_scope = 'platform' OR (n.recipient_scope = 'profile' AND n.target_profile = :nc_profile_only) OR (n.recipient_scope = 'user' AND n.target_user_id = :nc_user_only)";
    if ($tenantId !== null) {
        $params[':nc_tenant_only'] = $tenantId;
        $params[':nc_tenant_profile_tenant'] = $tenantId;
        $params[':nc_tenant_profile_profile'] = $profile;
        $base .= " OR (n.recipient_scope = 'tenant' AND n.target_tenant_id = :nc_tenant_only) OR (n.recipient_scope = 'tenant_profile' AND n.target_tenant_id = :nc_tenant_profile_tenant AND n.target_profile = :nc_tenant_profile_profile)";
    }
    return $base . ')';
}

function ncCanCompose(array $user): bool
{
    return in_array($user['profile'], ['super_admin', 'admin'], true);
}

function ncNormalizeProfile(string $profile): string
{
    $allowed = ['super_admin', 'admin', 'supervisor', 'manager', 'gerente', 'normal', 'user'];
    return in_array($profile, $allowed, true) ? $profile : '';
}

function ncAssertVisible(PDO $db, array $user, int $notificationId): bool
{
    $params = [':id' => $notificationId];
    $clause = ncVisibleClause($user, $params);
    $stmt = $db->prepare("SELECT n.id FROM internal_notifications n WHERE n.id = :id AND {$clause} LIMIT 1");
    $stmt->execute($params);
    return (bool)$stmt->fetchColumn();
}

try {
    $db = null;
    try {
        $db = Database::getInstance()->getConnection();
    } catch (Throwable $connectionError) {
        // Falha de conexão com o banco: leitura tolerante em vez de derrubar o painel.
        error_log('notification-center: db connection: ' . $connectionError->getMessage());
        $db = null;
    }
    $user = $db ? ncSessionUser($db) : null;
    if (!$user) {
        // Sem sessão (nem banco disponível): estado vazio para não travar o painel.
        $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? ncInput() : $_GET;
        $action = (string)($input['action'] ?? 'list');
        if (in_array($action, ['list', 'count'], true)) {
            ncJson(true, $action === 'count' ? ['unread' => 0, 'pending_install' => true] : ['items' => [], 'total' => 0, 'canCompose' => false, 'pending_install' => true]);
        }
        ncJson(false, [], 'Sessão não encontrada. Faça login novamente.', 401);
    }
    // Garantir que o perfil detectado é válido — nunca passar valor vazio para a cláusula SQL.
    $user['profile'] = in_array(strtolower((string)$user['profile']), ['super_admin', 'admin', 'supervisor', 'manager', 'gerente', 'normal', 'user'], true) ? strtolower((string)$user['profile']) : 'normal';
    $tablesReady = $db ? ncTableReady($db) : false;

    $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? ncInput() : $_GET;
    $action = (string)($input['action'] ?? 'list');
    $readerKey = ncReaderKey($user);

    // Leitura tolerante: enquanto o SQL de instalação não for executado, as
    // consultas retornam estado vazio em vez de derrubar o painel com 503.
    if (!$tablesReady && in_array($action, ['list', 'count'], true)) {
        $flag = ['pending_install' => true, 'hint' => 'Execute o arquivo sql/notification-center.sql uma única vez no banco.'];
        if ($action === 'count') ncJson(true, array_merge(['unread' => 0], $flag));
        ncJson(true, array_merge(['items' => [], 'total' => 0, 'canCompose' => ncCanCompose($user)], $flag));
    }
    if (!$tablesReady) ncJson(false, [], 'A Central de Notificações ainda não foi instalada. Execute o arquivo SQL do pacote.', 503);


    if ($action === 'count') {
        $params = [':reader_key_count' => $readerKey];
        $clause = ncVisibleClause($user, $params);
        $stmt = $db->prepare("SELECT COUNT(*) FROM internal_notifications n LEFT JOIN internal_notification_reads r ON r.notification_id = n.id AND r.reader_key = :reader_key_count WHERE {$clause} AND r.id IS NULL");
        $stmt->execute($params);
        ncJson(true, ['unread' => (int)$stmt->fetchColumn()]);
    }

    if ($action === 'list') {
        $filter = (string)($input['filter'] ?? 'all');
        $limit = max(1, min(50, (int)($input['limit'] ?? 30)));
        $params = [':reader_key' => $readerKey];
        $clause = ncVisibleClause($user, $params);
        $unread = $filter === 'unread' ? ' AND r.id IS NULL' : '';
        $sql = "SELECT n.id, n.category, n.priority, n.title, n.message, n.source_type, n.created_by_label, n.created_at, n.expires_at, r.read_at
                FROM internal_notifications n
                LEFT JOIN internal_notification_reads r ON r.notification_id = n.id AND r.reader_key = :reader_key
                WHERE {$clause}{$unread}
                ORDER BY CASE n.priority WHEN 'high' THEN 0 WHEN 'normal' THEN 1 ELSE 2 END, n.created_at DESC
                LIMIT {$limit}";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        ncJson(true, ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'canCompose' => ncCanCompose($user), 'profile' => $user['profile']]);
    }

    if ($action === 'directory') {
        if (!ncCanCompose($user)) ncJson(false, [], 'Seu perfil não pode enviar comunicados internos.', 403);
        $profile = $user['profile'];
        $tenantClause = '';
        $params = [];
        if ($profile !== 'super_admin') {
            $tenantClause = ' WHERE u.tenant_id = :tenant_id';
            $params[':tenant_id'] = (int)$user['tenant_id'];
        }
        $usersStmt = $db->prepare("SELECT u.id, u.tenant_id, u.name, u.email, u.profile, COALESCE(t.name, 'Sem empresa') AS tenant_name FROM users u LEFT JOIN tenants t ON t.id = u.tenant_id {$tenantClause} ORDER BY tenant_name, u.name LIMIT 500");
        $usersStmt->execute($params);
        $tenants = [];
        if ($profile === 'super_admin') {
            $tenants = $db->query('SELECT id, name FROM tenants ORDER BY name LIMIT 500')->fetchAll(PDO::FETCH_ASSOC);
        }
        ncJson(true, ['users' => $usersStmt->fetchAll(PDO::FETCH_ASSOC), 'tenants' => $tenants]);
    }

    if ($action === 'markRead') {
        $id = (int)($input['notificationId'] ?? 0);
        if ($id < 1 || !ncAssertVisible($db, $user, $id)) ncJson(false, [], 'Notificação não disponível para esta conta.', 404);
        $stmt = $db->prepare('INSERT INTO internal_notification_reads (notification_id, reader_key, read_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE read_at = read_at');
        $stmt->execute([$id, $readerKey]);
        ncJson(true, ['notificationId' => $id], 'Notificação marcada como lida.');
    }

    if ($action === 'markAllRead') {
        $params = [':reader_key_insert' => $readerKey, ':reader_key_join' => $readerKey];
        $clause = ncVisibleClause($user, $params);
        $sql = "INSERT IGNORE INTO internal_notification_reads (notification_id, reader_key, read_at)
                SELECT n.id, :reader_key_insert, NOW() FROM internal_notifications n
                LEFT JOIN internal_notification_reads r ON r.notification_id = n.id AND r.reader_key = :reader_key_join
                WHERE {$clause} AND r.id IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        ncJson(true, [], 'Notificações marcadas como lidas.');
    }

    if ($action === 'create') {
        if (!ncCanCompose($user)) ncJson(false, [], 'Seu perfil não pode enviar comunicados internos.', 403);
        $scope = (string)($input['recipientScope'] ?? 'tenant');
        $title = trim((string)($input['title'] ?? ''));
        $message = trim((string)($input['message'] ?? ''));
        $category = (string)($input['category'] ?? 'general');
        $priority = (string)($input['priority'] ?? 'normal');
        $allowedScopes = ['platform', 'profile', 'tenant', 'tenant_profile', 'user'];
        $allowedCategories = ['general', 'credit_pending', 'credit_approved', 'credit_low', 'plan', 'team', 'account', 'system'];
        if (!in_array($scope, $allowedScopes, true) || !in_array($category, $allowedCategories, true)
            || !in_array($priority, ['low', 'normal', 'high'], true) || mb_strlen($title) < 3 || mb_strlen($title) > 160 || mb_strlen($message) < 1 || mb_strlen($message) > 5000) {
            ncJson(false, [], 'Revise destinatário, título e mensagem antes de enviar.', 422);
        }

        $targetTenantId = isset($input['targetTenantId']) && $input['targetTenantId'] !== '' ? (int)$input['targetTenantId'] : null;
        $targetUserId = isset($input['targetUserId']) && $input['targetUserId'] !== '' ? (int)$input['targetUserId'] : null;
        $targetProfile = ncNormalizeProfile((string)($input['targetProfile'] ?? ''));
        if ($user['profile'] !== 'super_admin') {
            $targetTenantId = (int)$user['tenant_id'];
            if ($scope === 'platform') $scope = 'tenant';
        }
        if (in_array($scope, ['tenant', 'tenant_profile'], true) && !$targetTenantId) ncJson(false, [], 'Selecione a empresa destinatária.', 422);
        if (in_array($scope, ['profile', 'tenant_profile'], true) && $targetProfile === '') ncJson(false, [], 'Selecione o perfil destinatário.', 422);
        if ($scope === 'user') {
            if (!$targetUserId) ncJson(false, [], 'Selecione o usuário destinatário.', 422);
            $targetStmt = $db->prepare('SELECT id, tenant_id FROM users WHERE id = ? LIMIT 1');
            $targetStmt->execute([$targetUserId]);
            $target = $targetStmt->fetch(PDO::FETCH_ASSOC);
            if (!$target || ($user['profile'] !== 'super_admin' && (int)$target['tenant_id'] !== (int)$user['tenant_id'])) {
                ncJson(false, [], 'Usuário destinatário inválido para seu escopo.', 403);
            }
            $targetTenantId = $target['tenant_id'] === null ? null : (int)$target['tenant_id'];
        }
        if ($user['profile'] !== 'super_admin' && $targetTenantId !== (int)$user['tenant_id']) ncJson(false, [], 'Empresa destinatária fora do seu escopo.', 403);

        $stmt = $db->prepare('INSERT INTO internal_notifications (recipient_scope, target_profile, target_tenant_id, target_user_id, category, priority, title, message, source_type, created_by_user_id, created_by_label, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'manual\', ?, ?, NOW())');
        $stmt->execute([$scope, $targetProfile ?: null, $targetTenantId, $targetUserId, $category, $priority, $title, $message, (int)$user['id'] ?: null, mb_substr((string)$user['name'], 0, 160)]);
        ncJson(true, ['notificationId' => (int)$db->lastInsertId()], 'Comunicado enviado para os destinatários selecionados.');
    }

    ncJson(false, [], 'Ação não reconhecida.', 400);
} catch (Throwable $e) {
    error_log('notification-center: ' . $e->getMessage());
    ncJson(false, [], 'Não foi possível concluir a operação de notificações.', 500);
}
