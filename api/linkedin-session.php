<?php
declare(strict_types=1);

/**
 * Estado público-controlado da sessão LinkedIn temporária. Não retorna cookies,
 * identificadores de conta, senha ou qualquer dado que permita reutilizar a sessão.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function linkedinSessionLoadFile(string $relativePath): ?string
{
    foreach (array_unique(array_filter([
        dirname(__DIR__),
        rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'),
        rtrim((string) (getenv('HOME') ?: ''), '/') . '/prospec.atendo.log.br',
    ], 'is_dir')) as $root) {
        $path = rtrim($root, '/') . '/' . ltrim($relativePath, '/');
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

try {
    foreach (['config/config.php', 'config/database.php', 'includes/functions.php', 'includes/AtendoLinkedinCollectorClient.php'] as $rel) {
        $path = linkedinSessionLoadFile($rel);
        if ($path === null) {
            throw new RuntimeException('Dependência da sessão LinkedIn não encontrada.');
        }
        require_once $path;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('Método não permitido.', 405);
    }
    requireAuth();
    $authUser = getAuthUser();
    $profile = strtolower((string) ($authUser['profile'] ?? ''));
    $canManage = in_array($profile, ['super_admin', 'admin', 'tenant_admin', 'company_admin'], true);
    $client = new AtendoLinkedinCollectorClient();
    $status = $client->getSessionStatus();
    $active = !empty($status['active']);
    jsonSuccess([
        'active' => $active,
        'state' => $active ? 'active' : (!empty($status['expired']) ? 'expired' : 'inactive'),
        'expiresAt' => isset($status['expiresAt']) && is_string($status['expiresAt']) ? $status['expiresAt'] : null,
        'canManage' => $canManage,
        'notice' => $active
            ? 'Sessão LinkedIn temporária disponível para pesquisas.'
            : 'A sessão temporária não está disponível. A pesquisa usará a contingência automática quando necessária.',
    ]);
} catch (Throwable $error) {
    error_log('linkedin-session.php: ' . $error->getMessage());
    jsonError('Não foi possível consultar o estado da sessão LinkedIn.', 500);
}
