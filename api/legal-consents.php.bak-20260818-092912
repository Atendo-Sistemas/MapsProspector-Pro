<?php
/**
 * Consulta e registra aceites legais versionados.
 * Cada combinação de usuário, chave e versão é preservada como histórico.
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: no-referrer');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'self'");

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

function legalReply(bool $success, $data = null, string $error = '', int $status = 200): void {
    while (ob_get_level() > 0) { ob_end_clean(); }
    http_response_code($status);
    echo json_encode($success ? ['success' => true, 'data' => $data] : ['success' => false, 'error' => $error], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function legalSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    @session_start();
}

function legalUser(PDO $db): ?array {
    legalSession();
    foreach ([ $_SESSION['user'] ?? null, $_SESSION['auth_user'] ?? null, $_SESSION['current_user'] ?? null, $_SESSION['user_data'] ?? null ] as $candidate) {
        if (!is_array($candidate)) continue;
        $profile = strtolower((string)($candidate['profile'] ?? $candidate['role'] ?? ''));
        $id = $candidate['id'] ?? $candidate['user_id'] ?? null;
        if ($profile === 'super_admin') return ['id' => 0, 'tenant_id' => null, 'profile' => $profile];
        if ($id === null || $id === '') continue;
        $st = $db->prepare('SELECT id, tenant_id, profile FROM users WHERE id = ? LIMIT 1');
        $st->execute([(int)$id]);
        if ($row = $st->fetch(PDO::FETCH_ASSOC)) return $row;
    }
    $id = $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? null;
    if ($id === null || $id === '') return null;
    $st = $db->prepare('SELECT id, tenant_id, profile FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int)$id]);
    return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true)) legalReply(false, null, 'Método não permitido.', 405);
legalSession();
$now = time();
$rate = $_SESSION['_legal_consents_rate'] ?? ['start' => $now, 'count' => 0];
if (!is_array($rate) || $now - (int)($rate['start'] ?? 0) >= 60) $rate = ['start' => $now, 'count' => 0];
if (++$rate['count'] > 30) legalReply(false, null, 'Muitas solicitações. Tente novamente em instantes.', 429);
$_SESSION['_legal_consents_rate'] = $rate;

try {
    $db = Database::getInstance()->getConnection();
    $user = legalUser($db);
    if (!$user) legalReply(false, null, 'Não autenticado.', 401);
    if (strtolower((string)($user['profile'] ?? '')) === 'super_admin') legalReply(true, ['required' => false, 'missing' => []]);

    $documents = [
        'terms_of_use' => ['version' => '2026-08-17', 'url' => 'termos.php', 'label' => 'Termos de Uso'],
        'privacy_policy' => ['version' => '2026-08-17', 'url' => 'privacidade.php', 'label' => 'Política de Privacidade'],
    ];
    $findMissing = function () use ($db, $user, $documents): array {
        $missing = [];
        $st = $db->prepare('SELECT id FROM legal_consents WHERE user_id = ? AND consent_key = ? AND document_version = ? LIMIT 1');
        foreach ($documents as $key => $document) {
            $st->execute([(int)$user['id'], $key, $document['version']]);
            if (!$st->fetchColumn()) $missing[$key] = $document;
        }
        return $missing;
    };

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $missing = $findMissing();
        legalReply(true, ['required' => !empty($missing), 'missing' => $missing]);
    }

    $payload = json_decode((string)file_get_contents('php://input'), true);
    $accepted = is_array($payload['accepted'] ?? null) ? $payload['accepted'] : [];
    foreach ($documents as $key => $document) {
        if (($accepted[$key] ?? false) !== true) legalReply(false, null, 'É necessário aceitar todos os documentos legais atuais.', 422);
    }

    $db->beginTransaction();
    $check = $db->prepare('SELECT id FROM legal_consents WHERE user_id = ? AND consent_key = ? AND document_version = ? LIMIT 1');
    $insert = $db->prepare('INSERT INTO legal_consents (tenant_id, user_id, consent_key, document_version, document_url, source, accepted_at, user_agent) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)');
    $ua = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    foreach ($documents as $key => $document) {
        $check->execute([(int)$user['id'], $key, $document['version']]);
        if (!$check->fetchColumn()) $insert->execute([$user['tenant_id'] ?? null, (int)$user['id'], $key, $document['version'], $document['url'], 'legal_update_prompt', $ua]);
    }
    $db->commit();
    legalReply(true, ['required' => false, 'missing' => []]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) $db->rollBack();
    error_log('legal-consents.php: ' . $e->getMessage());
    legalReply(false, null, 'Não foi possível registrar o aceite agora.', 500);
}
