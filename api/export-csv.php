<?php
/**
 * Exportação CSV da pesquisa Maps. A autorização e o escopo dos leads são
 * sempre verificados no servidor antes de produzir o arquivo.
 */
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/module-access.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    jsonError('Método não permitido.', 405);
}

$userId = (int) requireAuth();
$user = getAuthUser();
if (!$user) {
    jsonError('Sessão expirada. Faça login novamente.', 401);
}

$db = Database::getInstance()->getConnection();
$tenantId = (int) ($user['tenant_id'] ?? $user['tenantId'] ?? 0);
$profile = strtolower((string) ($user['profile'] ?? $user['role'] ?? ''));
$isSuperAdmin = $profile === 'super_admin';
if (!mpTenantHasModule($db, $tenantId, 'export', $user)) {
    jsonError('Seu plano não permite exportar pesquisas. Conheça os planos profissionais.', 403, 'export_not_allowed');
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonError('Dados de exportação inválidos.', 400);
}
$searchId = max(0, (int) ($input['searchId'] ?? 0));
$rawIds = is_array($input['leadIds'] ?? null) ? $input['leadIds'] : [];
$leadIds = [];
foreach ($rawIds as $rawId) {
    if (!preg_match('/^(?:lead-)?(\d+)$/', (string) $rawId, $matches)) continue;
    $id = (int) $matches[1];
    if ($id > 0) $leadIds[$id] = $id;
}
$leadIds = array_values($leadIds);
if ($searchId <= 0 || $leadIds === []) {
    jsonError('Selecione uma pesquisa e ao menos um resultado para exportar.', 422);
}
if (count($leadIds) > 2000) {
    jsonError('A exportação excede o limite de resultados por arquivo.', 422);
}

$placeholders = implode(',', array_fill(0, count($leadIds), '?'));
$params = [$searchId];
$scopeSql = '';
if ($isSuperAdmin && $tenantId <= 0) {
    $scopeSql = ' AND sh.user_id = ?';
    $params[] = $userId;
} elseif ($tenantId > 0 && in_array($profile, ['admin', 'supervisor', 'manager', 'gerente'], true)) {
    $scopeSql = ' AND lead_owner.tenant_id = ?';
    $params[] = $tenantId;
} else {
    $scopeSql = ' AND sh.user_id = ?';
    $params[] = $userId;
}
$params = array_merge($params, $leadIds);
$stmt = $db->prepare(
    "SELECT l.id, l.name, l.phone, l.email, l.address, l.website, l.cnpj, l.partners, l.maps_uri, l.created_at,
            sh.query AS search_query, sh.location AS search_location, sh.tag AS search_tag, sh.created_at AS search_created_at
       FROM leads l
       INNER JOIN search_history sh ON sh.id = l.search_history_id
       INNER JOIN users lead_owner ON lead_owner.id = sh.user_id
      WHERE sh.id = ?{$scopeSql} AND l.id IN ({$placeholders})
      ORDER BY l.id ASC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($rows) !== count($leadIds)) {
    jsonError('Um ou mais resultados não pertencem à pesquisa ou ao escopo desta conta.', 403);
}

$escapeCell = static function ($value): string {
    $text = trim((string) ($value ?? ''));
    if (preg_match('/^[\t\r ]*[=+\-@]/u', $text)) $text = "'" . $text;
    return '"' . str_replace('"', '""', $text) . '"';
};
$csvRows = [];
$csvRows[] = implode(';', ['Pesquisa', 'Local', 'Tag', 'Data da pesquisa', 'Nome', 'Telefone', 'E-mail', 'Endereço', 'CNPJ/CPF', 'Sócios', 'Site', 'Maps']);
foreach ($rows as $row) {
    $csvRows[] = implode(';', array_map($escapeCell, [
        $row['search_query'] ?? '',
        $row['search_location'] ?? '',
        $row['search_tag'] ?? '',
        $row['search_created_at'] ?? '',
        $row['name'] ?? '',
        $row['phone'] ?? '',
        $row['email'] ?? '',
        $row['address'] ?? '',
        $row['cnpj'] ?? '',
        $row['partners'] ?? '',
        $row['website'] ?? '',
        $row['maps_uri'] ?? '',
    ]));
}

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="mapsprospector-' . date('Y-m-d') . '.csv"');
header('X-Content-Type-Options: nosniff');
echo "\xEF\xBB\xBF" . implode("\r\n", $csvRows);
exit;
