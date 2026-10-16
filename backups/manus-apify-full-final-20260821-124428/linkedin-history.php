<?php
/**
 * Histórico de pesquisas LinkedIn.
 * Endpoint: GET /api/linkedin-history.php?limit=50
 *
 * Usa linkedin_runs, tabela já alimentada tanto por pesquisas novas quanto por
 * resultados recuperados do cache. O retorno é restrito ao tenant da sessão.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (!headers_sent()) {
            if (ob_get_length() > 0) {
                ob_clean();
            }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => 'Não foi possível carregar o histórico de pesquisas agora.',
            ], JSON_UNESCAPED_UNICODE);
        }
    }
});

function linkedinHistoryLoadFile($relativePath) {
    static $roots = null;

    if ($roots === null) {
        $documentRoot = rtrim((string) (getenv('DOCUMENT_ROOT') ?: ($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
        $home = rtrim((string) (getenv('HOME') ?: ''), '/');
        $roots = array_values(array_unique(array_filter([
            dirname(__DIR__),
            $documentRoot,
            $documentRoot ? dirname($documentRoot) : null,
            $home ? $home . '/prospec.atendo.log.br' : null,
            $home ? $home . '/public_html' : null,
        ], 'is_dir')));
    }

    foreach ($roots as $root) {
        $path = rtrim($root, '/') . '/' . ltrim($relativePath, '/');
        if (is_file($path)) {
            return $path;
        }
    }

    return null;
}

foreach (['config/config.php', 'config/database.php', 'includes/functions.php'] as $relativePath) {
    $path = linkedinHistoryLoadFile($relativePath);
    if ($path === null) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Não foi possível inicializar o histórico de pesquisas.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    require_once $path;
}

if (!function_exists('requireAuth') || !function_exists('getAuthUser') || !function_exists('jsonSuccess') || !function_exists('jsonError') || !class_exists('Database')) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Não foi possível inicializar o histórico de pesquisas.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonError('Método não permitido.', 405);
}

try {
    requireAuth();
    $authUser = getAuthUser();
    $profile = strtolower((string) ($authUser['profile'] ?? ''));
    $tenantId = (int) ($authUser['tenant_id'] ?? 0);

    // Mantém o mesmo comportamento da busca: super administrador sem tenant
    // consulta o tenant principal, salvo quando informar tenant_id explicitamente.
    if ($profile === 'super_admin' && !empty($_GET['tenant_id'])) {
        $tenantId = (int) $_GET['tenant_id'];
    }
    if ($tenantId <= 0) {
        $tenantId = 1;
    }

    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 30;
    $limit = max(1, min(100, $limit));

    $db = Database::getInstance()->getConnection();

    $tableExists = $db->query("SHOW TABLES LIKE 'linkedin_runs'")->fetch();
    if (!$tableExists) {
        jsonSuccess([
            'items' => [],
            'total' => 0,
        ], 'Histórico ainda não está disponível.');
    }

    // Ao informar run_id, devolve somente os perfis vinculados à pesquisa.
    // A consulta é sempre limitada ao tenant da sessão para impedir acesso cruzado.
    $requestedRunId = isset($_GET['run_id']) ? (int) $_GET['run_id'] : 0;
    if ($requestedRunId > 0) {
        $runStmt = $db->prepare(
            "SELECT id, leads_found, search_company, search_job_titles, search_location
             FROM linkedin_runs
             WHERE id = ? AND tenant_id = ? AND status = 'done'
             LIMIT 1"
        );
        $runStmt->execute([$requestedRunId, $tenantId]);
        $run = $runStmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) {
            jsonError('Pesquisa não encontrada.', 404);
        }

        $leads = [];
        $savedResultsTableExists = (bool) $db->query("SHOW TABLES LIKE 'linkedin_run_results'")->fetch();
        if ($savedResultsTableExists) {
            $savedStmt = $db->prepare(
                'SELECT leads_json FROM linkedin_run_results WHERE tenant_id = ? AND run_id = ? LIMIT 1'
            );
            $savedStmt->execute([$tenantId, $requestedRunId]);
            $savedJson = $savedStmt->fetchColumn();
            $savedLeads = is_string($savedJson) ? json_decode($savedJson, true) : null;
            if (is_array($savedLeads)) {
                $leads = $savedLeads;
            }
        }

        // Compatibilidade com buscas já concluídas antes desta melhoria.
        if (!$leads) {
            $leadsTableExists = (bool) $db->query("SHOW TABLES LIKE 'linkedin_leads'")->fetch();
            if ($leadsTableExists) {
                $leadStmt = $db->prepare(
                    'SELECT profile_url, full_name, job_title, company_name, location, industry, photo_url
                     FROM linkedin_leads
                     WHERE tenant_id = ? AND run_id = ?
                     ORDER BY id ASC'
                );
                $leadStmt->execute([$tenantId, $requestedRunId]);
                $leads = $leadStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            }
        }

        // Para consultas antigas que ainda não possuíam resultados próprios,
        // tenta recuperar a cópia correspondente de resultados da pesquisa.
        if (!$leads && (int) ($run['leads_found'] ?? 0) > 0) {
            $cacheTableExists = (bool) $db->query("SHOW TABLES LIKE 'linkedin_search_cache'")->fetch();
            if ($cacheTableExists) {
                $jobTitles = json_decode((string) ($run['search_job_titles'] ?? ''), true);
                if (!is_array($jobTitles)) {
                    $jobTitles = array_values(array_filter(array_map('trim', preg_split('/[,;\\n]+/', (string) ($run['search_job_titles'] ?? '')) ?: [])));
                }
                $jobTitlesJson = json_encode($jobTitles, JSON_UNESCAPED_UNICODE);
                $cacheStmt = $db->prepare(
                    'SELECT leads_json
                     FROM linkedin_search_cache
                     WHERE tenant_id = ?
                       AND COALESCE(NULLIF(company_lower, \'\'), \'\') = ?
                       AND job_titles_lower = ?
                       AND location_lower = ?
                     ORDER BY created_at DESC
                     LIMIT 1'
                );
                $cacheStmt->execute([
                    $tenantId,
                    mb_strtolower(trim((string) ($run['search_company'] ?? ''))),
                    mb_strtolower((string) $jobTitlesJson),
                    mb_strtolower(trim((string) ($run['search_location'] ?? ''))),
                ]);
                $cacheJson = $cacheStmt->fetchColumn();
                $cachedLeads = is_string($cacheJson) ? json_decode($cacheJson, true) : null;
                if (is_array($cachedLeads)) {
                    $leads = $cachedLeads;
                }
            }
        }

        jsonSuccess([
            'runId' => (string) $requestedRunId,
            'leads' => array_map(function ($lead) {
                if (!is_array($lead)) {
                    return [];
                }
                return [
                    'profile_url' => (string) ($lead['profile_url'] ?? ''),
                    'full_name' => (string) ($lead['full_name'] ?? ''),
                    'first_name' => (string) ($lead['first_name'] ?? ''),
                    'last_name' => (string) ($lead['last_name'] ?? ''),
                    'public_identifier' => (string) ($lead['public_identifier'] ?? ''),
                    'linkedin_id' => (string) ($lead['linkedin_id'] ?? ''),
                    'job_title' => (string) ($lead['job_title'] ?? ''),
                    'headline' => (string) ($lead['headline'] ?? $lead['job_title'] ?? ''),
                    'company_name' => (string) ($lead['company_name'] ?? ''),
                    'location' => (string) ($lead['location'] ?? ''),
                    'industry' => (string) ($lead['industry'] ?? ''),
                    'photo_url' => (string) ($lead['photo_url'] ?? ''),
                    'cover_photo_url' => (string) ($lead['cover_photo_url'] ?? ''),
                    'about' => (string) ($lead['about'] ?? ''),
                    'skills' => is_array($lead['skills'] ?? null) ? $lead['skills'] : [],
                    'experience' => is_array($lead['experience'] ?? null) ? $lead['experience'] : [],
                    'education' => is_array($lead['education'] ?? null) ? $lead['education'] : [],
                    'certifications' => is_array($lead['certifications'] ?? null) ? $lead['certifications'] : [],
                    'languages' => is_array($lead['languages'] ?? null) ? $lead['languages'] : [],
                    'projects' => is_array($lead['projects'] ?? null) ? $lead['projects'] : [],
                    'connections_count' => isset($lead['connections_count']) ? (int) $lead['connections_count'] : null,
                    'followers_count' => isset($lead['followers_count']) ? (int) $lead['followers_count'] : null,
                    'open_to_work' => !empty($lead['open_to_work']),
                    'hiring' => !empty($lead['hiring']),
                    'premium' => !empty($lead['premium']),
                    'verified' => !empty($lead['verified']),
                    'email' => (string) ($lead['email'] ?? ''),
                    'website' => (string) ($lead['website'] ?? ''),
                    'source' => (string) ($lead['source'] ?? 'harvestapi_full'),
                ];
            }, $leads),
            'count' => count($leads),
            'expectedCount' => (int) ($run['leads_found'] ?? 0),
        ], 'Perfis carregados com sucesso.');
    }

    $stmtTotal = $db->prepare("SELECT COUNT(*) FROM linkedin_runs WHERE tenant_id = ? AND status = 'done'");
    $stmtTotal->execute([$tenantId]);
    $total = (int) $stmtTotal->fetchColumn();

    $stmt = $db->prepare(
        "SELECT id, search_title, search_company, search_location, search_job_titles,
                status, leads_found, credits_charged, created_at, finished_at
         FROM linkedin_runs
         WHERE tenant_id = ?
           AND status = 'done'
         ORDER BY created_at DESC, id DESC
         LIMIT " . $limit
    );
    $stmt->execute([$tenantId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = array_map(function ($row) {
        $jobTitles = json_decode((string) ($row['search_job_titles'] ?? ''), true);
        if (!is_array($jobTitles)) {
            $jobTitles = array_values(array_filter(array_map('trim', preg_split('/[,;\\n]+/', (string) ($row['search_job_titles'] ?? '')) ?: [])));
        }

        return [
            'id' => (string) $row['id'],
            'title' => (string) ($row['search_title'] ?: 'Pesquisa LinkedIn'),
            'company' => (string) ($row['search_company'] ?? ''),
            'location' => (string) ($row['search_location'] ?? ''),
            'jobTitles' => array_values(array_filter(array_map('strval', $jobTitles))),
            'status' => (string) ($row['status'] ?? 'done'),
            'leadsFound' => (int) ($row['leads_found'] ?? 0),
            'creditsCharged' => (int) ($row['credits_charged'] ?? 0),
            'createdAt' => (string) ($row['created_at'] ?? ''),
            'finishedAt' => $row['finished_at'] !== null ? (string) $row['finished_at'] : null,
        ];
    }, $rows);

    jsonSuccess([
        'items' => $items,
        'total' => $total,
    ], 'Histórico carregado com sucesso.');
} catch (Throwable $e) {
    error_log('linkedin-history.php: ' . $e->getMessage());
    jsonError('Não foi possível carregar o histórico de pesquisas agora.', 500);
}
