<?php
declare(strict_types=1);

/**
 * Consulta o estado de uma pesquisa LinkedIn já criada. A execução normal é
 * síncrona e usa a sessão autenticada temporária. Este endpoint permanece
 * compatível com pesquisas antigas e reprocessa runs falhos de forma segura:
 * sessão autenticada primeiro e Apify somente como contingência.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function linkedinStatusLoadFile(string $relativePath): ?string
{
    $roots = array_unique(array_filter([
        dirname(__DIR__),
        rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'),
        rtrim((string) (getenv('HOME') ?: ''), '/') . '/prospec.atendo.log.br',
    ], 'is_dir'));
    foreach ($roots as $root) {
        $path = rtrim($root, '/') . '/' . ltrim($relativePath, '/');
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

foreach (['config/config.php', 'config/database.php', 'includes/functions.php', 'services/groqLinkedInProvider.php', 'services/linkedinRunFallbackService.php'] as $rel) {
    $path = linkedinStatusLoadFile($rel);
    if ($path === null) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Arquivo obrigatório não encontrado: ' . $rel]);
        exit;
    }
    require_once $path;
}

function linkedinStatusSaveResults(PDO $db, int $tenantId, int $runId, array $leads): void
{
    try {
        if (!(bool) $db->query("SHOW TABLES LIKE 'linkedin_run_results'")->fetch()) {
            return;
        }
        $stmt = $db->prepare(
            'INSERT INTO linkedin_run_results (tenant_id, run_id, leads_json, leads_count, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE leads_json = VALUES(leads_json), leads_count = VALUES(leads_count), updated_at = NOW()'
        );
        $stmt->execute([$tenantId, $runId, json_encode($leads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE), count($leads)]);
    } catch (Throwable $error) {
        error_log('linkedin status results: ' . $error->getMessage());
    }
}

function linkedinStatusWriteCache(PDO $db, int $tenantId, int $userId, string $company, array $jobTitles, string $location, array $leads): void
{
    if (!$leads) {
        return;
    }
    try {
        if (!(bool) $db->query("SHOW TABLES LIKE 'linkedin_search_cache'")->fetch()) {
            return;
        }
        $hasUpdatedAt = (bool) $db->query("SHOW COLUMNS FROM linkedin_search_cache LIKE 'updated_at'")->fetch();
        $companyLower = mb_strtolower(trim($company));
        $jobTitlesLower = mb_strtolower((string) json_encode($jobTitles, JSON_UNESCAPED_UNICODE));
        $locationLower = mb_strtolower(trim($location));
        $find = $db->prepare(
            'SELECT id FROM linkedin_search_cache
             WHERE COALESCE(NULLIF(company_lower, \'\'), \'\') = ?
               AND job_titles_lower = ? AND location_lower = ? LIMIT 1'
        );
        $find->execute([$companyLower, $jobTitlesLower, $locationLower]);
        $existing = $find->fetch(PDO::FETCH_ASSOC);
        $json = json_encode($leads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($existing) {
            $stmt = $db->prepare($hasUpdatedAt
                ? 'UPDATE linkedin_search_cache SET leads_json = ?, leads_count = ?, updated_at = NOW() WHERE id = ?'
                : 'UPDATE linkedin_search_cache SET leads_json = ?, leads_count = ?, created_at = NOW() WHERE id = ?');
            $stmt->execute([$json, count($leads), (int) $existing['id']]);
            return;
        }
        $stmt = $db->prepare($hasUpdatedAt
            ? 'INSERT INTO linkedin_search_cache (tenant_id, user_id, company_lower, job_titles_lower, location_lower, leads_json, leads_count, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            : 'INSERT INTO linkedin_search_cache (tenant_id, user_id, company_lower, job_titles_lower, location_lower, leads_json, leads_count, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$tenantId, $userId, $companyLower, $jobTitlesLower, $locationLower, $json, count($leads)]);
    } catch (Throwable $error) {
        error_log('linkedin status cache: ' . $error->getMessage());
    }
}

function linkedinStatusLoadRunLeads(PDO $db, int $tenantId, int $runId): array
{
    try {
        if ((bool) $db->query("SHOW TABLES LIKE 'linkedin_run_results'")->fetch()) {
            $saved = $db->prepare('SELECT leads_json FROM linkedin_run_results WHERE tenant_id = ? AND run_id = ? LIMIT 1');
            $saved->execute([$tenantId, $runId]);
            $decoded = json_decode((string) $saved->fetchColumn(), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $saved = $db->prepare('SELECT profile_url, full_name, job_title, company_name, location, industry, photo_url FROM linkedin_leads WHERE tenant_id = ? AND run_id = ? ORDER BY id ASC');
        $saved->execute([$tenantId, $runId]);
        return $saved->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $error) {
        error_log('linkedin status load results: ' . $error->getMessage());
        return [];
    }
}

function linkedinStatusResultMeta(array $leads, string $location): array
{
    return $leads ? [] : [
        'emptyReason' => 'no_results',
        'emptyMessage' => 'Nenhum perfil compatível foi encontrado para esta combinação. Tente ampliar a localidade ou revisar os cargos informados.',
        'location' => $location,
    ];
}

function linkedinStatusSource(string $actor): string
{
    // A origem técnica não é necessária para o usuário e não sai para o
    // navegador. O valor interno continua registrado em linkedin_runs.
    return 'search';
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonError('Método não permitido.', 405);
    }
    $userId = requireAuth();
    $authUser = getAuthUser();
    $profile = strtolower((string) ($authUser['profile'] ?? ''));
    $tenantId = max(1, (int) ($authUser['tenant_id'] ?? 1));
    $runId = max(0, (int) ($_GET['run_id'] ?? 0));
    if ($runId <= 0) {
        jsonError('Identificador da pesquisa inválido.', 400);
    }
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'SELECT id, tenant_id, user_id, search_title, search_company, search_location, search_job_titles,
                apify_actor, status, leads_found, credits_charged, error_message
         FROM linkedin_runs WHERE id = ? AND tenant_id = ? LIMIT 1'
    );
    $stmt->execute([$runId, $tenantId]);
    $run = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$run) {
        jsonError('Pesquisa LinkedIn não encontrada.', 404);
    }

    if ($run['status'] === 'done') {
        $leads = linkedinStatusLoadRunLeads($db, $tenantId, $runId);
        jsonSuccess(array_merge([
            'runId' => (string) $runId,
            'pending' => false,
            'leads' => $leads,
            'count' => (int) $run['leads_found'],
            'creditsCharged' => (int) $run['credits_charged'],
            'source' => linkedinStatusSource((string) $run['apify_actor']),
            'sourceNotice' => 'Perfis públicos compatíveis com os filtros foram processados e validados.',
        ], linkedinStatusResultMeta($leads, (string) $run['search_location'])));
    }

    if (in_array((string) $run['status'], ['running', 'queued', 'pending'], true)) {
        jsonSuccess(['runId' => (string) $runId, 'pending' => true, 'status' => $run['status'], 'pollAfterMs' => 2000]);
    }

    $internalMessage = trim((string) ($run['error_message'] ?? ''));
    $internalMessage = $internalMessage !== '' ? $internalMessage : 'execução incompleta reprocessada';
    $completed = function_exists('linkedinRunGroqBrowserSearch')
        ? linkedinRunGroqBrowserSearch($db, $userId, $run, $tenantId, $profile, 'reprocessamento da pesquisa')
        : false;
    $source = 'search';
    if (!$completed) {
        $completed = function_exists('linkedinRunApifyContingency')
            && linkedinRunApifyContingency($db, $userId, $run, $tenantId, $profile, 'contingência Apify após pesquisa principal sem resultados');
    }
    if (!$completed) {
        $completed = linkedinRunAuthenticatedSearch($db, $userId, $run, $tenantId, $profile, 'fallback operacional após indisponibilidade da pesquisa principal e Apify');
    }
    if (!$completed && !empty($GLOBALS['linkedin_groq_empty_result'])) {
        $completed = linkedinRunFinalizeEmptyGroqSearch($db, $run, $tenantId);
    }
    if (!$completed) {
        jsonError('A fonte de pesquisa do LinkedIn não respondeu nesta tentativa. Tente novamente em alguns instantes; os filtros e o histórico foram preservados.', 502);
    }
    $leads = linkedinStatusLoadRunLeads($db, $tenantId, $runId);
    $reloaded = $db->prepare('SELECT leads_found, credits_charged, apify_actor FROM linkedin_runs WHERE id = ? LIMIT 1');
    $reloaded->execute([$runId]);
    $state = $reloaded->fetch(PDO::FETCH_ASSOC) ?: [];
    $source = linkedinStatusSource((string) ($state['apify_actor'] ?? $source));
    jsonSuccess(array_merge([
        'runId' => (string) $runId,
        'pending' => false,
        'leads' => $leads,
        'count' => (int) ($state['leads_found'] ?? count($leads)),
        'creditsCharged' => (int) ($state['credits_charged'] ?? 0),
        'source' => $source,
        'sourceNotice' => 'Perfis públicos compatíveis com os filtros foram processados e validados.',
    ], linkedinStatusResultMeta($leads, (string) $run['search_location'])));
} catch (Throwable $error) {
    error_log('linkedin-search-status.php: ' . $error->getMessage());
    jsonError('Não foi possível atualizar a pesquisa LinkedIn agora.', 500);
}
