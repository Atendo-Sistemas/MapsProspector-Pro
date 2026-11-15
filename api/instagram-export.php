<?php
/** Exportação CSV das linhas de uma pesquisa Instagram, com autorização no servidor. */
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
if (!$isSuperAdmin && !mpTenantHasModule($db, $tenantId, 'export', $user)) {
    jsonError('Seu plano não permite exportar pesquisas. Conheça os planos profissionais.', 403, 'export_not_allowed');
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonError('Dados de exportação inválidos.', 400);
}
$runId = max(0, (int) ($input['runId'] ?? 0));
$rawIndexes = is_array($input['leadIndexes'] ?? null) ? $input['leadIndexes'] : [];
if ($runId <= 0 || $rawIndexes === []) {
    jsonError('Selecione uma pesquisa e ao menos um perfil para exportar.', 422);
}
if (count($rawIndexes) > 500) {
    jsonError('A exportação excede o limite de perfis por arquivo.', 422);
}

if ($isSuperAdmin) {
    $runStmt = $db->prepare('SELECT id, tenant_id, status, leads_json FROM instagram_runs WHERE id = ? LIMIT 1');
    $runStmt->execute([$runId]);
} else {
    if ($tenantId <= 0) jsonError('Sua conta não possui uma empresa vinculada.', 403);
    $runStmt = $db->prepare('SELECT id, tenant_id, status, leads_json FROM instagram_runs WHERE id = ? AND tenant_id = ? LIMIT 1');
    $runStmt->execute([$runId, $tenantId]);
}
$run = $runStmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($run)) {
    jsonError('Pesquisa Instagram não encontrada.', 404);
}
$runLeads = json_decode((string) ($run['leads_json'] ?? ''), true);
if ((string) $run['status'] !== 'done' || !is_array($runLeads)) {
    jsonError('A pesquisa ainda não está pronta para exportação.', 409);
}

$indexes = [];
foreach ($rawIndexes as $rawIndex) {
    if (filter_var($rawIndex, FILTER_VALIDATE_INT) === false) continue;
    $index = (int) $rawIndex;
    if ($index >= 0 && isset($runLeads[$index]) && is_array($runLeads[$index])) $indexes[$index] = $index;
}
if ($indexes === []) {
    jsonError('Nenhum perfil válido foi selecionado.', 422);
}

$escapeCell = static function ($value): string {
    $text = trim((string) ($value ?? ''));
    if (preg_match('/^[\t\r ]*[=+\-@]/u', $text)) $text = "'" . $text;
    return '"' . str_replace('"', '""', $text) . '"';
};
$csvRows = [];
$csvRows[] = implode(',', ['Usuário', 'Nome', 'Perfil', 'Comentário', 'Interesse', 'Data', 'Publicação']);
foreach ($indexes as $index) {
    $lead = $runLeads[$index];
    $csvRows[] = implode(',', array_map($escapeCell, [
        $lead['username'] ?? '',
        $lead['name'] ?? $lead['full_name'] ?? '',
        $lead['profile_url'] ?? '',
        $lead['comment_text'] ?? '',
        $lead['interest_level'] ?? '',
        $lead['commented_at'] ?? '',
        $lead['source_post_url'] ?? '',
    ]));
}

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="instagram-leads-' . date('Y-m-d') . '.csv"');
header('X-Content-Type-Options: nosniff');
echo "\xEF\xBB\xBF" . implode("\r\n", $csvRows);
exit;
