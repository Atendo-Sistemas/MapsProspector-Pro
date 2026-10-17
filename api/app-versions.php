<?php
declare(strict_types=1);

/**
 * API de Versões e Atualizações.
 * Exibe o changelog do sistema em linguagem simples, sem revelar
 * nomes de serviços, provedores ou configurações internas.
 *
 * Requer sessão de usuário logado (qualquer perfil ativo).
 * Aceita o mesmo formato de sessão usado pelos demais endpoints
 * (usuário comum, admin, supervisor, manager/gerente e super admin).
 * v20260820-64b — resolve super administradores com perfil em
 * $_SESSION['profile'] mesmo sem $_SESSION['user'], e não exige
 * tenant_id (o super admin direto da plataforma não possui tenant).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private, max-age=0');

const IPA_VERSION = '20261017-linkedin-apify-search-modal-3';

/** Resposta de erro e encerramento. */
function ipavError(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(1);
}

function ipavOk(array $data): void
{
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

/**
 * Resolve o usuário da sessão com o mesmo padrão dos demais endpoints
 * (settings.php, dashboard-metrics.php, legal-consents.php).
 */
function ipavSessionUser(PDO $db): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $sessionCandidates = [
        $_SESSION['user'] ?? null,
        $_SESSION['auth_user'] ?? null,
        $_SESSION['current_user'] ?? null,
        $_SESSION['user_data'] ?? null,
    ];
    foreach ($sessionCandidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $id = $candidate['id'] ?? $candidate['user_id'] ?? null;
        $profile = strtolower((string) ($candidate['profile'] ?? $candidate['role'] ?? $candidate['user_type'] ?? ''));
        if (($id === null || $id === '') && $profile !== 'super_admin') {
            continue;
        }
        return [
            'id' => $id === null || $id === '' ? 0 : (int) $id,
            'tenant_id' => $candidate['tenant_id'] ?? $candidate['tenantId'] ?? null,
            'profile' => $profile,
            'email' => (string) ($candidate['email'] ?? ''),
            'name' => (string) ($candidate['name'] ?? ''),
        ];
    }
    $sessionProfile = strtolower((string) ($_SESSION['profile'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? ''));
    if ($sessionProfile === 'super_admin' || !empty($_SESSION['is_super_admin'])) {
        return [
            'id' => 0,
            'tenant_id' => null,
            'profile' => 'super_admin',
            'email' => (string) ($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''),
            'name' => (string) ($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Super administrador'),
        ];
    }
    $sessionId = $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? null;
    $sessionEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? null;
    if (($sessionId === null || $sessionId === '') && ($sessionEmail === null || $sessionEmail === '')) {
        return null;
    }
    try {
        if ($sessionId !== null && $sessionId !== '') {
            $stmt = $db->prepare('SELECT id, tenant_id, profile, email, name FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $sessionId]);
        } else {
            $stmt = $db->prepare('SELECT id, tenant_id, profile, email, name FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([(string) $sessionEmail]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => $row['tenant_id'] ?? null,
            'profile' => (string) ($row['profile'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
        ];
    } catch (Throwable $e) {
        error_log('app-versions: falha ao resolver usuário da sessão: ' . $e->getMessage());
        return null;
    }
}

/**
 * Lê o arquivo de notas de versão.
 * O arquivo fica em config/ (não exposto por URL), protegido do acesso direto.
 */
function ipavReadNotes(): array
{
    $notesFile = dirname(__DIR__) . '/config/release-notes.json';
    if (!is_file($notesFile)) {
        return [];
    }
    $raw = file_get_contents($notesFile);
    if ($raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function ipavNotesToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    if (empty($_SESSION['versions_notes_csrf']) || !is_string($_SESSION['versions_notes_csrf'])) {
        $_SESSION['versions_notes_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['versions_notes_csrf'];
}

/**
 * Normaliza uma entrada de changelog editável, removendo qualquer informação
 * sensível e limitando tamanhos. O texto permanece legível para o usuário final.
 */
function ipavSanitizeItem(array $item): array
{
    $title = mb_substr(trim((string) ($item['title'] ?? '')), 0, 300);
    $description = mb_substr(trim((string) ($item['description'] ?? '')), 0, 2000);
    return ['title' => $title, 'description' => $description];
}

function ipavSanitizeNote(array $note): array
{
    $version = preg_replace('/[^A-Za-z0-9\-.]/', '', (string) ($note['version'] ?? ''));
    $version = mb_substr($version === '' ? 'nova-versao' : $version, 0, 40);
    $date = preg_replace('/[^0-9]/', '', (string) ($note['date'] ?? ''));
    if (strlen($date) === 8) {
        $date = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
    }
    $date = preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', (string) $date) ? $date : date('Y-m-d');
    $title = mb_substr(trim((string) ($note['title'] ?? '')), 0, 200);
    $items = [];
    foreach ((array) ($note['items'] ?? []) as $item) {
        if (!is_array($item)) continue;
        $normalized = ipavSanitizeItem($item);
        if ($normalized['title'] === '' && $normalized['description'] === '') continue;
        $items[] = $normalized;
    }
    return ['version' => $version, 'date' => $date, 'title' => $title, 'items' => $items];
}

$action = (string) ($_GET['action'] ?? '');

try {
    $db = Database::getInstance()->getConnection();
    $authUser = ipavSessionUser($db);
    $profile = strtolower((string) ($authUser['profile'] ?? ''));
    if (!is_array($authUser) || (!(int) ($authUser['id'] ?? 0) > 0 && $profile !== 'super_admin')) {
        ipavError(401, 'Sessão expirada. Faça login novamente.');
    }

    $isSuperAdmin = $profile === 'super_admin';

    if ($action === 'manage') {
        if (!$isSuperAdmin) {
            ipavError(403, 'Somente o super administrador pode editar as versões e atualizações.');
        }
        ipavOk([
            'version' => IPA_VERSION,
            'notes' => ipavReadNotes(),
            'token' => ipavNotesToken(),
        ]);
    }

    if ($action === 'save') {
        if (!$isSuperAdmin) {
            ipavError(403, 'Somente o super administrador pode editar as versões e atualizações.');
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $expectedToken = $_SESSION['versions_notes_csrf'] ?? '';
        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) {
            ipavError(422, 'Requisição inválida.');
        }
        $receivedToken = (string) ($body['token'] ?? '');
        if (!is_string($expectedToken) || $expectedToken === '' || $receivedToken === '' || !hash_equals($expectedToken, $receivedToken)) {
            ipavError(419, 'Sua sessão de edição expirou. Abra o editor novamente.');
        }
        $notesFile = dirname(__DIR__) . '/config/release-notes.json';
        if (!is_file($notesFile)) {
            ipavError(500, 'O arquivo de versões do sistema não foi encontrado.');
        }
        $oldNotes = ipavReadNotes();
        $incoming = array_map(function ($note) {
            return is_array($note) ? ipavSanitizeNote($note) : [];
        }, (array) ($body['notes'] ?? $body['notes'] ?? []));
        $incoming = array_filter($incoming, function ($note) {
            return is_array($note) && $note['version'] !== '';
        });
        $incoming = array_values($incoming);
        if (count($incoming) === 0) {
            ipavError(422, 'É necessário manter ao menos uma versão no changelog.');
        }
        $encoded = json_encode($incoming, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($encoded === false) {
            ipavError(500, 'Não foi possível processar as versões informadas.');
        }
        $written = file_put_contents($notesFile, $encoded . "\n", LOCK_EX);
        if ($written === false) {
            ipavError(500, 'Não foi possível salvar as versões. Verifique as permissões do arquivo.');
        }
        @chmod($notesFile, 0640);
        $removed = count($oldNotes) - count($incoming);
        ipavOk([
            'version' => IPA_VERSION,
            'notes' => $incoming,
            'saved' => true,
            'entries_changed' => max(0, $removed),
        ]);
    }

    if ($action !== 'notes') {
        ipavError(422, 'Ação não reconhecida.');
    }
    ipavOk([
        'version' => IPA_VERSION,
        'current_version' => IPA_VERSION,
        'updated_at' => date('Y-m-d H:i:s'),
        'notes' => ipavReadNotes(),
    ]);
} catch (Throwable $error) {
    error_log('app-versions: ' . $error->getMessage());
    ipavError(500, 'Não foi possível carregar as versões do sistema.');
}
