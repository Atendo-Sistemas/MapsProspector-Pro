<?php
declare(strict_types=1);

/**
 * Maps Prospector — Métricas de tráfego do site v20260820-64f.
 *
 * GET  action=track&page=...     — registra a visita (público, assíncrono, sem auth)
 * GET  action=report              — agregados: visitas por dia, referrers, dispositivos, top páginas (super admin)
 * GET  action=counts              — totais gerais de visitantes/visitantes únicos da semana (super admin)
 *
 * O endpoint de tráfego é tolerante: erros de gravação nunca quebram a página do visitante.
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

function stJson(bool $success, array $data = [], string $message = '', int $status = 200): void
{
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($status);
    $payload = ['success' => $success];
    if ($data !== []) $payload['data'] = $data;
    if ($message !== '') $payload[$success ? 'message' : 'error'] = $message;
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function stSessionIsSuperAdmin(PDO $db): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $profile = strtolower((string) ($_SESSION['profile'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? ''));
    if ($profile === 'super_admin' || !empty($_SESSION['is_super_admin'])) return true;
    $candidates = [$_SESSION['user'] ?? null, $_SESSION['auth_user'] ?? null, $_SESSION['current_user'] ?? null, $_SESSION['user_data'] ?? null];
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) continue;
        $p = strtolower((string) ($candidate['profile'] ?? $candidate['role'] ?? $candidate['user_type'] ?? ''));
        if ($p === 'super_admin') return true;
    }
    $id = $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? null;
    if ($id) {
        try {
            $stmt = $db->prepare('SELECT profile FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row && strtolower((string) $row['profile']) === 'super_admin';
        } catch (Throwable $e) {
            error_log('site-traffic: ' . $e->getMessage());
        }
    }
    return false;
}

function stTablesReady(PDO $db): bool
{
    try {
        return (bool) $db->query("SHOW TABLES LIKE 'site_visits'")->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function stDevice(string $userAgent): string
{
    $ua = strtolower($userAgent);
    if (stripos($ua, 'mobile') !== false || stripos($ua, 'android') !== false || preg_match('/iphone|ipod|windows phone/i', $ua)) return 'mobile';
    if (preg_match('/ipad|tablet|sm-t|kindle|silk/i', $ua)) return 'tablet';
    return 'desktop';
}

try {
    $db = Database::getInstance()->getConnection();
    $action = (string) ($_GET['action'] ?? '');
} catch (Throwable $e) {
    error_log('site-traffic: db connection: ' . $e->getMessage());
    $db = null;
    $action = (string) ($_GET['action'] ?? '');
    if ($action === 'track') {
        stJson(true, ['registered' => false, 'reason' => 'Módulo de tráfego ainda não instalado.'], '', 202);
    }
    if ($action === 'counts' || $action === 'report') {
        stJson(true, [
            'pending_install' => true,
            'hint' => 'Execute o arquivo sql/site-traffic.sql uma única vez no banco.',
            'counts' => ['visits' => 0, 'unique_visitors' => 0, 'visits_7d' => 0, 'unique_visitors_7d' => 0],
            'daily' => [],
            'referrers' => [],
            'devices' => [],
            'top_pages' => [],
        ]);
    }
    stJson(false, [], 'O módulo de tráfego ainda não foi instalado. Execute o arquivo SQL do pacote.', 503);
}

/* ------------------------------ registro (público) ------------------------------ */
if ($action === 'track') {
    if (!stTablesReady($db)) {
        stJson(true, ['registered' => false, 'reason' => 'Módulo de tráfego ainda não instalado.'], '', 202);
    }
    try {
        $page = mb_substr((string) ($_GET['page'] ?? 'home'), 0, 255);
        $referer = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
        $userAgent = mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
        $ip = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '');
        $ip = mb_substr(preg_replace('/\s*\(.*?\)\s*$/', '', $ip) ?? $ip, 0, 45);
        $device = stDevice($userAgent);
        $refDomain = '';
        if ($referer !== '') {
            $parts = @parse_url($referer);
            $refDomain = (string) ($parts['host'] ?? '');
        }
        $ipHash = $ip !== '' ? substr(sha1($ip . date('Y-m-d')), 0, 24) : '';
        $stmt = $db->prepare("INSERT INTO site_visits (page, referer, ref_domain, user_agent, device, ip_hash, visited_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$page, $referer, $refDomain, $userAgent, $device, $ipHash]);
        stJson(true, ['registered' => true]);
    } catch (Throwable $e) {
        error_log('site-traffic track: ' . $e->getMessage());
        stJson(true, ['registered' => false, 'reason' => 'Erro de gravação silencioso.']);
    }
}

/* -------------------------------- relatórios -------------------------------- */
if (!in_array($action, ['report', 'counts'], true)) stJson(false, [], 'Ação não reconhecida.', 400);
if (!stTablesReady($db)) stJson(false, [], 'Tabela de tráfego ainda não instalada.', 503);
if (!stSessionIsSuperAdmin($db)) stJson(false, [], 'Somente o super administrador pode ver o tráfego do site.', 403);

try {
    $counts = $db->query("SELECT
        COUNT(*) AS visits,
        COUNT(DISTINCT ip_hash) AS unique_visitors,
        SUM(CASE WHEN visited_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) AS visits_7d,
        COUNT(DISTINCT CASE WHEN visited_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN ip_hash END) AS unique_visitors_7d
    FROM site_visits")->fetch(PDO::FETCH_ASSOC) ?: ['visits' => 0, 'unique_visitors' => 0, 'visits_7d' => 0, 'unique_visitors_7d' => 0];

    $report = [];

    if ($action === 'counts') {
        stJson(true, [
            'counts' => [
                'visits' => (int) $counts['visits'],
                'unique_visitors' => (int) $counts['unique_visitors'],
                'visits_7d' => (int) $counts['visits_7d'],
                'unique_visitors_7d' => (int) $counts['unique_visitors_7d'],
            ],
        ]);
    }

    // Visitas por dia: sempre devolve uma série contínua de 14 dias, inclusive
    // quando não houve acesso. Isso evita um gráfico vazio quando há registros
    // apenas no dia atual.
    $dailyMap = [];
    $rows = $db->query("SELECT DATE(visited_at) AS day, COUNT(*) AS visits, COUNT(DISTINCT ip_hash) AS uniques FROM site_visits WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(visited_at) ORDER BY day ASC");
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $dailyMap[(string) $row['day']] = ['visits' => (int) $row['visits'], 'uniques' => (int) $row['uniques']];
    }
    $days = [];
    $today = new DateTimeImmutable('today');
    for ($offset = 13; $offset >= 0; $offset--) {
        $day = $today->sub(new DateInterval('P' . $offset . 'D'))->format('Y-m-d');
        $values = $dailyMap[$day] ?? ['visits' => 0, 'uniques' => 0];
        $days[] = ['day' => $day, 'visits' => $values['visits'], 'uniques' => $values['uniques']];
    }
    $maxVisits = max(1, ...array_map(static fn(array $day): int => (int) $day['visits'], $days));

    // Referrers (excluindo o próprio domínio)
    $ownHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $refs = [];
    $rows = $db->prepare("SELECT COALESCE(NULLIF(ref_domain, ''), '(acesso direto)') AS source, COUNT(*) AS visits FROM site_visits WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY source ORDER BY visits DESC LIMIT 12");
    $rows->execute();
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) $refs[] = ['source' => (string) $row['source'], 'visits' => (int) $row['visits']];

    // Dispositivos
    $devices = [];
    $rows = $db->query("SELECT COALESCE(device, 'unknown') AS device, COUNT(*) AS visits FROM site_visits WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY device ORDER BY visits DESC");
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) $devices[] = ['device' => (string) $row['device'], 'visits' => (int) $row['visits']];

    // Top páginas
    $pages = [];
    $rows = $db->query("SELECT page, COUNT(*) AS visits FROM site_visits WHERE visited_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY page ORDER BY visits DESC LIMIT 10");
    foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) $pages[] = ['page' => (string) $row['page'], 'visits' => (int) $row['visits']];

    $report = [
        'counts' => [
            'visits' => (int) $counts['visits'],
            'unique_visitors' => (int) $counts['unique_visitors'],
            'visits_7d' => (int) $counts['visits_7d'],
            'unique_visitors_7d' => (int) $counts['unique_visitors_7d'],
        ],
        'daily' => $days,
        'maxVisits' => $maxVisits,
        'referrers' => $refs,
        'devices' => $devices,
        'top_pages' => $pages,
    ];

    stJson(true, $report);
} catch (Throwable $e) {
    error_log('site-traffic report: ' . $e->getMessage());
    stJson(false, [], 'Não foi possível gerar o relatório de tráfego.', 500);
}
