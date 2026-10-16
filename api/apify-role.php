<?php
/**
 * Atualiza o papel operacional de uma conta de pesquisa.
 * Endpoint: POST /api/apify-role.php
 * Body: {"id": 1, "role": "production|reserve|disabled"}
 *
 * Somente o super administrador pode alterar esta política. O token da conta
 * jamais é lido para a resposta e nunca é enviado ao navegador.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }
    if (ob_get_length()) {
        ob_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'error' => 'Não foi possível atualizar a função da conta.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
});

function apifyRoleLoadFile(string $relativePath): ?string
{
    static $roots = null;
    if ($roots === null) {
        $documentRoot = rtrim((string) (getenv('DOCUMENT_ROOT') ?: ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
        $home = '';
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $user = @posix_getpwuid(posix_geteuid());
            $home = rtrim((string) ($user['dir'] ?? ''), '/');
        }
        $roots = array_values(array_unique(array_filter([
            dirname(__DIR__),
            $documentRoot,
            $documentRoot ? dirname($documentRoot) : '',
            $home ? $home . '/prospec.atendo.log.br' : '',
            $home ? $home . '/public_html' : '',
        ], 'is_dir')));
    }

    foreach ($roots as $root) {
        $candidate = rtrim($root, '/') . '/' . ltrim($relativePath, '/');
        if (is_file($candidate)) {
            return $candidate;
        }
    }
    return null;
}

function apifyRoleJson(array $payload, int $status = 200): never
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

foreach (['config/config.php', 'config/database.php', 'includes/functions.php'] as $dependency) {
    $path = apifyRoleLoadFile($dependency);
    if ($path === null) {
        apifyRoleJson(['success' => false, 'error' => 'Dependência do sistema não encontrada.'], 500);
    }
    require_once $path;
}

if (!function_exists('requireAuth') || !function_exists('getAuthUser') || !class_exists('Database')) {
    apifyRoleJson(['success' => false, 'error' => 'Dependências de autenticação não carregadas.'], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    apifyRoleJson(['success' => false, 'error' => 'Método não permitido.'], 405);
}

requireAuth();
$authUser = getAuthUser();
$profile = strtolower((string) ($authUser['profile'] ?? $authUser['role'] ?? ''));
if ($profile !== 'super_admin') {
    apifyRoleJson(['success' => false, 'error' => 'Apenas o super administrador pode alterar as contas.'], 403);
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$keyId = filter_var($payload['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$requestedRole = strtolower(trim((string) ($payload['role'] ?? '')));

if ($keyId === false || $keyId === null || !in_array($requestedRole, ['production', 'reserve', 'disabled'], true)) {
    apifyRoleJson(['success' => false, 'error' => 'Informe uma conta e uma função válidas.'], 422);
}

try {
    $db = Database::getInstance()->getConnection();
    $keyStmt = $db->prepare('SELECT id FROM apify_keys WHERE id = ? LIMIT 1');
    $keyStmt->execute([(int) $keyId]);
    if (!$keyStmt->fetch(PDO::FETCH_ASSOC)) {
        apifyRoleJson(['success' => false, 'error' => 'Conta de pesquisa não encontrada.'], 404);
    }

    $policyStmt = $db->prepare('SELECT role, priority FROM apify_key_policies WHERE provider_key_id = ? LIMIT 1');
    $policyStmt->execute([(int) $keyId]);
    $current = $policyStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $priority = max(1, (int) ($current['priority'] ?? 100));
    $storedRole = in_array(($current['role'] ?? ''), ['production', 'reserve'], true)
        ? $current['role']
        : 'production';

    if ($requestedRole === 'production') {
        $storedRole = 'production';
        $state = 'active';
        $reason = 'Definida manualmente como produção';
    } elseif ($requestedRole === 'reserve') {
        $storedRole = 'reserve';
        $state = 'standby';
        $reason = 'Definida manualmente como reserva';
    } else {
        $state = 'disabled';
        $reason = 'Desativada manualmente pelo super administrador';
    }

    $upsert = $db->prepare(
        'INSERT INTO apify_key_policies
            (provider_key_id, role, priority, state, promoted_at, last_transition_at, transition_reason)
         VALUES (?, ?, ?, ?, NULL, NOW(), ?)
         ON DUPLICATE KEY UPDATE
            role = VALUES(role),
            priority = VALUES(priority),
            state = VALUES(state),
            promoted_at = CASE WHEN VALUES(state) = "active" AND VALUES(role) = "reserve" THEN COALESCE(promoted_at, NOW()) ELSE NULL END,
            last_transition_at = NOW(),
            transition_reason = VALUES(transition_reason)'
    );
    $upsert->execute([(int) $keyId, $storedRole, $priority, $state, $reason]);

    apifyRoleJson([
        'success' => true,
        'message' => 'Função da conta atualizada com sucesso.',
        'data' => [
            'id' => (int) $keyId,
            'role' => $requestedRole,
            'state' => $state,
        ],
    ]);
} catch (Throwable $e) {
    apifyRoleJson(['success' => false, 'error' => 'Não foi possível salvar a função da conta.'], 500);
}
