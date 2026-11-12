<?php
/**
 * Maps Prospector — API da Central de Ajuda administrável v20260817-42.
 *
 * GET  action=public                    -> conteúdo publicado do perfil da sessão
 * GET  action=manage&profile=admin      -> conteúdo editável (somente super admin)
 * POST action=save|delete               -> edição (somente super admin)
 * POST multipart action=uploadImage     -> imagem PNG, JPEG ou WEBP (somente super admin)
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

function hcJson(bool $success, array $data = [], string $message = '', int $status = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    $payload = ['success' => $success];
    if ($data !== []) $payload['data'] = $data;
    if ($message !== '') $payload[$success ? 'message' : 'error'] = $message;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function hcInput(): array
{
    if (str_starts_with(strtolower((string)($_SERVER['CONTENT_TYPE'] ?? '')), 'multipart/form-data')) return $_POST;
    $input = json_decode(file_get_contents('php://input') ?: '{}', true);
    return is_array($input) ? $input : [];
}

function hcSessionUser(PDO $db): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $candidates = [$_SESSION['user'] ?? null, $_SESSION['auth_user'] ?? null, $_SESSION['current_user'] ?? null, $_SESSION['user_data'] ?? null];
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) continue;
        $profile = strtolower((string)($candidate['profile'] ?? $candidate['role'] ?? $candidate['user_type'] ?? ''));
        $id = $candidate['id'] ?? $candidate['user_id'] ?? null;
        if (($id === null || $id === '') && $profile !== 'super_admin') continue;
        return [
            'id' => $id === null || $id === '' ? 0 : (int)$id,
            'name' => (string)($candidate['name'] ?? $candidate['full_name'] ?? $candidate['email'] ?? 'Super Admin'),
            'profile' => $profile,
        ];
    }
    $profile = strtolower((string)($_SESSION['profile'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? ''));
    if ($profile === 'super_admin' || !empty($_SESSION['is_super_admin'])) {
        return ['id' => 0, 'name' => (string)($_SESSION['email'] ?? 'Super Admin'), 'profile' => 'super_admin'];
    }
    $id = $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? null;
    if (!$id) return null;
    $stmt = $db->prepare('SELECT id, name, profile FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? ['id' => (int)$row['id'], 'name' => (string)$row['name'], 'profile' => strtolower((string)$row['profile'])] : null;
}

function hcProfile(string $profile): string
{
    $profile = strtolower(trim($profile));
    if (in_array($profile, ['manager', 'gerente'], true)) return 'supervisor';
    if ($profile === 'normal') return 'user';
    return in_array($profile, ['super_admin', 'admin', 'supervisor', 'user'], true) ? $profile : 'user';
}

function hcCanManage(array $user): bool
{
    return ($user['profile'] ?? '') === 'super_admin';
}

function hcCsrfToken(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    if (empty($_SESSION['help_center_csrf']) || !is_string($_SESSION['help_center_csrf'])) {
        $_SESSION['help_center_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['help_center_csrf'];
}

function hcRequireCsrf(array $input): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $expected = $_SESSION['help_center_csrf'] ?? '';
    $received = (string)($input['csrfToken'] ?? '');
    if (!is_string($expected) || $expected === '' || $received === '' || !hash_equals($expected, $received)) {
        hcJson(false, [], 'Sua sessão de edição expirou. Abra o editor novamente.', 419);
    }
}

function hcTablesReady(PDO $db): bool
{
    return (bool)$db->query("SHOW TABLES LIKE 'help_content_overrides'")->fetchColumn()
        && (bool)$db->query("SHOW TABLES LIKE 'help_content_audit'")->fetchColumn();
}

function hcSection(string $section): string
{
    $section = strtolower(trim($section));
    return in_array($section, ['meta', 'overview', 'research', 'routine'], true) ? $section : '';
}

function hcSnapshot(?array $record): ?string
{
    if (!$record) return null;
    unset($record['id'], $record['created_by_user_id'], $record['updated_by_user_id'], $record['created_at'], $record['updated_at']);
    return json_encode($record, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
}

function hcRecord(array $row): array
{
    return [
        'profile' => $row['profile_key'],
        'section' => $row['section_key'],
        'heading' => (string)($row['heading'] ?? ''),
        'body' => (string)($row['body_text'] ?? ''),
        'imageUrl' => (string)($row['image_url'] ?? ''),
        'imageAlt' => (string)($row['image_alt'] ?? ''),
        'replaceDefault' => (bool)($row['replace_default'] ?? false),
        'isPublished' => (bool)($row['is_published'] ?? false),
        'updatedAt' => (string)($row['updated_at'] ?? ''),
    ];
}

function hcReadDefaults(): array
{
    $defaultsFile = dirname(__DIR__) . '/config/help-defaults.json';
    if (!is_file($defaultsFile)) return [];
    $raw = file_get_contents($defaultsFile);
    $data = $raw === false ? [] : json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function hcFetchProfile(PDO $db, string $profile, bool $publishedOnly): array
{
    $sql = 'SELECT * FROM help_content_overrides WHERE profile_key = ?';
    if ($publishedOnly) $sql .= ' AND is_published = 1';
    $sql .= ' ORDER BY FIELD(section_key, "meta", "overview", "research", "routine")';
    $stmt = $db->prepare($sql);
    $stmt->execute([$profile]);
    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $items[$row['section_key']] = hcRecord($row);
    return $items;
}

function hcImageUrl(string $value): string
{
    $value = trim($value);
    if ($value === '') return '';
    return preg_match('#^/uploads/help-images/[a-f0-9]{32}\.(?:png|jpg|webp)$#', $value) ? $value : '';
}

try {
    $db = Database::getInstance()->getConnection();
    $user = hcSessionUser($db);
    if (!$user) hcJson(false, [], 'Sessão não encontrada. Faça login novamente.', 401);
    $input = $_SERVER['REQUEST_METHOD'] === 'POST' ? hcInput() : $_GET;
    $action = (string)($input['action'] ?? 'public');
    $tablesReady = hcTablesReady($db);

    if ($action === 'public') {
        if (!$tablesReady) hcJson(true, ['profile' => hcProfile((string)$user['profile']), 'items' => [], 'installed' => false]);
        $profile = hcProfile((string)$user['profile']);
        hcJson(true, ['profile' => $profile, 'items' => hcFetchProfile($db, $profile, true), 'installed' => true]);
    }

    if (!hcCanManage($user)) hcJson(false, [], 'Apenas o super admin pode editar a Central de Ajuda.', 403);
    if (!$tablesReady) hcJson(false, [], 'A Central de Ajuda administrável ainda não foi instalada. Execute o arquivo SQL do pacote.', 503);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') hcRequireCsrf($input);

    if ($action === 'manage') {
        $profile = hcProfile((string)($input['profile'] ?? 'admin'));
        $defaults = hcReadDefaults();
        $profilesDefaults = $defaults['profiles'] ?? [];
        $profileDefaults = $profilesDefaults[$profile] ?? [];
        $metaDefaults = $profileDefaults['meta'] ?? [];
        $sectionsDefaults = $profileDefaults['sections'] ?? [];
        hcJson(true, [
            'profile' => $profile,
            'items' => hcFetchProfile($db, $profile, false),
            'defaults' => [
                'meta' => $metaDefaults,
                'intro' => (string) ($profileDefaults['intro'] ?? ''),
                'eyebrow' => (string) ($profileDefaults['eyebrow'] ?? ''),
                'sections' => $sectionsDefaults,
            ],
            'sharedResearchGuide' => $defaults['shared_research_guide'] ?? [],
            'csrfToken' => hcCsrfToken(),
        ]);
    }

    if ($action === 'uploadImage') {
        $file = $_FILES['image'] ?? null;
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) hcJson(false, [], 'Selecione uma imagem PNG, JPEG ou WEBP.', 422);
        if ((int)$file['size'] < 1 || (int)$file['size'] > 5 * 1024 * 1024 || !is_uploaded_file($file['tmp_name'])) hcJson(false, [], 'A imagem deve ter no máximo 5 MB.', 422);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
        if ($finfo) finfo_close($finfo);
        $extensions = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
        if (!isset($extensions[$mime])) hcJson(false, [], 'Formato inválido. Envie PNG, JPEG ou WEBP.', 422);
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo || (int)$imageInfo[0] > 4096 || (int)$imageInfo[1] > 4096 || ((int)$imageInfo[0] * (int)$imageInfo[1]) > 16000000) hcJson(false, [], 'A imagem excede os limites de dimensão permitidos.', 422);
        $directory = dirname(__DIR__) . '/uploads/help-images';
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) hcJson(false, [], 'Não foi possível preparar a pasta de imagens.', 500);
        $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
        if (!move_uploaded_file($file['tmp_name'], $directory . '/' . $filename)) hcJson(false, [], 'Não foi possível salvar a imagem.', 500);
        @chmod($directory . '/' . $filename, 0640);
        hcJson(true, ['imageUrl' => '/uploads/help-images/' . $filename], 'Imagem enviada. Salve o tópico para publicá-la na ajuda.');
    }

    $profile = hcProfile((string)($input['profile'] ?? ''));
    $section = hcSection((string)($input['section'] ?? ''));
    if ($section === '') hcJson(false, [], 'Selecione o perfil e a seção da ajuda.', 422);
    $previousStmt = $db->prepare('SELECT * FROM help_content_overrides WHERE profile_key = ? AND section_key = ? LIMIT 1');
    $previousStmt->execute([$profile, $section]);
    $previous = $previousStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($action === 'delete') {
        if (!$previous) hcJson(true, [], 'Não havia personalização para remover.');
        $db->beginTransaction();
        $db->prepare('DELETE FROM help_content_overrides WHERE id = ?')->execute([(int)$previous['id']]);
        $db->prepare('INSERT INTO help_content_audit (content_id, profile_key, section_key, action_type, old_snapshot, new_snapshot, actor_user_id, actor_label, created_at) VALUES (?, ?, ?, ?, ?, NULL, ?, ?, NOW())')
            ->execute([(int)$previous['id'], $profile, $section, 'delete', hcSnapshot($previous), (int)$user['id'] ?: null, mb_substr((string)$user['name'], 0, 160)]);
        $db->commit();
        hcJson(true, [], 'Personalização removida. O guia padrão voltou a ser exibido.');
    }

    if ($action !== 'save') hcJson(false, [], 'Ação não reconhecida.', 400);
    $heading = trim((string)($input['heading'] ?? ''));
    $body = trim((string)($input['body'] ?? ''));
    $imageUrl = hcImageUrl((string)($input['imageUrl'] ?? ''));
    $imageAlt = trim((string)($input['imageAlt'] ?? ''));
    $replaceDefault = !empty($input['replaceDefault']) ? 1 : 0;
    $isPublished = !empty($input['isPublished']) ? 1 : 0;
    if (mb_strlen($heading) > 200 || mb_strlen($body) > 12000 || mb_strlen($imageAlt) > 255) hcJson(false, [], 'Os campos excedem o tamanho permitido.', 422);
    if ($section === 'meta' && ($heading === '' || $body === '')) hcJson(false, [], 'O cabeçalho da ajuda exige título e introdução.', 422);
    if ($section !== 'meta' && $heading === '' && $body === '' && $imageUrl === '') hcJson(false, [], 'Informe texto ou imagem para personalizar este tópico.', 422);
    if ($imageUrl !== '' && $imageAlt === '') hcJson(false, [], 'Informe um texto alternativo para a imagem.', 422);
    $db->beginTransaction();
    if ($previous) {
        $stmt = $db->prepare('UPDATE help_content_overrides SET heading = ?, body_text = ?, image_url = ?, image_alt = ?, replace_default = ?, is_published = ?, updated_by_user_id = ? WHERE id = ?');
        $stmt->execute([$heading ?: null, $body ?: null, $imageUrl ?: null, $imageAlt ?: null, $replaceDefault, $isPublished, (int)$user['id'] ?: null, (int)$previous['id']]);
        $contentId = (int)$previous['id'];
        $actionType = 'update';
    } else {
        $stmt = $db->prepare('INSERT INTO help_content_overrides (profile_key, section_key, heading, body_text, image_url, image_alt, replace_default, is_published, created_by_user_id, updated_by_user_id, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
        $stmt->execute([$profile, $section, $heading ?: null, $body ?: null, $imageUrl ?: null, $imageAlt ?: null, $replaceDefault, $isPublished, (int)$user['id'] ?: null, (int)$user['id'] ?: null]);
        $contentId = (int)$db->lastInsertId();
        $actionType = 'create';
    }
    $currentStmt = $db->prepare('SELECT * FROM help_content_overrides WHERE id = ? LIMIT 1');
    $currentStmt->execute([$contentId]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $db->prepare('INSERT INTO help_content_audit (content_id, profile_key, section_key, action_type, old_snapshot, new_snapshot, actor_user_id, actor_label, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())')
        ->execute([$contentId, $profile, $section, $actionType, hcSnapshot($previous), hcSnapshot($current), (int)$user['id'] ?: null, mb_substr((string)$user['name'], 0, 160)]);
    $db->commit();
    hcJson(true, ['item' => hcRecord($current)], 'Conteúdo da ajuda salvo.');
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('help-center: ' . $e->getMessage());
    hcJson(false, [], 'Não foi possível concluir a alteração da Central de Ajuda.', 500);
}
