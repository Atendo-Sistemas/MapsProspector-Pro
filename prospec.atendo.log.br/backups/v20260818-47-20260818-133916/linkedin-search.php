<?php
/**
 * Pesquisa de leads LinkedIn por empresa via Apify.
 * Endpoint: POST /api/linkedin-search.php
 *
 * Inclui mecanismos robustos de inicialização:
 *  - Mensagem de erro clara se os includes falharem (ajuda a diagnosticar o ambiente).
 *  - Shutdown handler que devolve JSON legível mesmo com erro fatal do PHP.
 */
ini_set('display_errors', 0);
error_reporting(E_ALL);
ob_start();
register_shutdown_function(function () {
    if (!headers_sent()) {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            if (ob_get_length() !== false && ob_get_length() > 0) {
                ob_clean();
            }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => "Erro Fatal no PHP: {$error['message']} em {$error['file']} na linha {$error['line']}",
            ]);
            exit;
        }
    }
});

/**
 * Carrega os arquivos comuns do projeto, tentando vários caminhos possíveis.
 * O servidor do usuário pode servir o projeto de diretórios diferentes
 * (public_html, subdir etc.), então tentamos caminhos relativos e absolutos.
 */
function linkedinLoadFile($relativePath) {
    static $absRoots = null;
    if ($absRoots === null) {
        $absRoots = [
            dirname(__DIR__),                                  // /home/.../prospec.atendo.log.br
            rtrim(getenv('DOCUMENT_ROOT') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'),
        ];
        // também tenta o diretório pai do DOCUMENT_ROOT (caso o site esteja em subdir)
        $docRoot = rtrim(getenv('DOCUMENT_ROOT') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $parent = dirname($docRoot);
        if ($parent && $parent !== $docRoot) {
            $absRoots[] = $parent;
        }
        // e o diretório home do usuário do processo
        $home = rtrim(getenv('HOME') ?: (posix_getpwuid(posix_geteuid())['dir'] ?? ''), '/');
        if ($home) {
            $absRoots[] = $home . '/prospec.atendo.log.br';
            $absRoots[] = $home . '/public_html';
        }
        $absRoots = array_unique(array_filter($absRoots, 'is_dir'));
    }
    $candidates = [];
    foreach ($absRoots as $root) {
        $candidates[] = rtrim($root, '/') . '/' . ltrim($relativePath, '/');
    }
    // caminho relativo como último recurso
    $candidates[] = __DIR__ . '/' . $relativePath;
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}

$loaded = [];
foreach (['config/config.php', 'config/database.php', 'includes/functions.php', 'services/linkedinSearchService.php'] as $rel) {
    $path = linkedinLoadFile($rel);
    if ($path === null) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => "Arquivo não encontrado: {$rel}. Verifique a estrutura de pastas do projeto (esperado: config/, includes/, services/ ao lado de api/).",
        ]);
        exit;
    }
    require_once $path;
    $loaded[] = $rel;
}

ob_clean();

// Conferência das dependências: se os includes não definiram as funções/classes, avisa.
$missing = [];
if (!function_exists('requireAuth')) {
    $missing[] = 'requireAuth';
}
if (!function_exists('getAuthUser')) {
    $missing[] = 'getAuthUser';
}
if (!function_exists('jsonError')) {
    $missing[] = 'jsonError';
}
if (!class_exists('LinkedInSearchService')) {
    $missing[] = 'LinkedInSearchService';
}
if (!class_exists('Database')) {
    $missing[] = 'Database';
}
if ($missing) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Dependências do projeto não carregadas: ' . implode(', ', $missing) . '. Verifique se o arquivo inclui a versão correta do seu sistema.',
    ]);
    exit;
}

/**
 * Preserva os perfis devolvidos em cada consulta. A tabela é opcional até a
 * migração ser aplicada; sua indisponibilidade não interrompe a pesquisa.
 */
function saveLinkedInRunResults(PDO $db, int $tenantId, int $runId, array $leads): void {
    static $tableAvailable = null;

    try {
        if ($tableAvailable === null) {
            $tableAvailable = (bool) $db->query("SHOW TABLES LIKE 'linkedin_run_results'")->fetch();
        }
        if (!$tableAvailable || $runId <= 0) {
            return;
        }

        $json = json_encode($leads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return;
        }

        $stmt = $db->prepare(
            'INSERT INTO linkedin_run_results (tenant_id, run_id, leads_json, leads_count, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE
               tenant_id = VALUES(tenant_id), leads_json = VALUES(leads_json),
               leads_count = VALUES(leads_count), updated_at = NOW()'
        );
        $stmt->execute([$tenantId, $runId, $json, count($leads)]);
    } catch (Throwable $e) {
        error_log('linkedin-search.php run results: ' . $e->getMessage());
    }
}

$userId = requireAuth();
$authUser = getAuthUser();
$profile = strtolower((string) ($authUser['profile'] ?? ''));
$tenantId = (int) ($authUser['tenant_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$company = trim((string) ($input['company'] ?? ''));
$location = trim((string) ($input['location'] ?? ''));
$limit = max(1, min(200, (int) ($input['limit'] ?? 10)));
$jobTitles = $input['jobTitles'] ?? [];

if (is_string($jobTitles)) {
    $jobTitles = preg_split('/[,;\n]+/', $jobTitles, -1, PREG_SPLIT_NO_EMPTY);
}
$jobTitles = array_values(array_filter(array_map(function ($value) {
    return trim((string) $value);
}, is_array($jobTitles) ? $jobTitles : [])));
$jobTitles = array_slice($jobTitles, 0, 10);

// Empresa é um termo de direcionamento da busca, não um filtro obrigatório.
if ($company !== '' && mb_strlen($company) < 2) {
    jsonError('Se informar uma empresa, o nome deve ter ao menos 2 caracteres.', 400);
}
if ($location === '') {
    jsonError('Informe a localidade da pesquisa.', 400);
}
if (!$jobTitles) {
    jsonError('Informe ao menos um cargo ou função, por exemplo: Diretor ou Gerente Comercial.', 400);
}

// O Super Admin pode informar explicitamente o tenant que receberá a pesquisa;
// se não informar e não tiver tenant na sessão, usa o tenant principal (1).
if ($profile === 'super_admin' && !empty($input['tenant_id'])) {
    $tenantId = (int) $input['tenant_id'];
}
if ($tenantId <= 0) {
    $tenantId = 1; // Super admin sem tenant usa o tenant principal da plataforma.
}

$db = Database::getInstance()->getConnection();

// A interface permite até 200 perfis. Para clientes com limite contratado,
// a solicitação é reduzida apenas ao saldo real do plano somado aos créditos
// extras, sem manter um teto artificial de 25 ou 50 perfis.
$isPlatformSuperAdmin = $profile === 'super_admin' || $tenantId === 1;
if (!$isPlatformSuperAdmin && function_exists('getTenantPlanTokenLimit') && function_exists('getTenantTokensUsed') && function_exists('getTenantTokenBonus')) {
    $planLimit = max(0, (int) getTenantPlanTokenLimit($db, $tenantId));
    $bonus = max(0, (int) getTenantTokenBonus($db, $tenantId));
    $totalCredits = $planLimit + $bonus;
    if ($totalCredits > 0) {
        $availableCredits = max(0, $totalCredits - max(0, (int) getTenantTokensUsed($db, $tenantId)));
        if ($availableCredits < 1) {
            jsonError('Seu saldo de créditos está esgotado. Solicite novos créditos para continuar a pesquisa LinkedIn.', 402);
        }
        $limit = min($limit, $availableCredits);
    }
}

$consentKey = 'linkedin_responsible_use';
$consentVersion = '2026-08-11';

// O aceite do termo é gravado com UNIQUE KEY (user_id, consent_key, document_version) —
// sem tenant_id. Por isso, para usuários sem tenant (ex.: super admin) ou quando o
// aceite foi gravado com outro tenant_id, a verificação por (user_id + tenant_id)
// pode não localizar o registro. Verifica primeiro com tenant, e se não achar,
// verifica pelo usuário independentemente do tenant.
try {
    $consentOk = false;
    $stmtConsent = $db->prepare(
        'SELECT id FROM legal_consents
         WHERE user_id = ? AND tenant_id = ? AND consent_key = ? AND document_version = ?
         LIMIT 1'
    );
    $stmtConsent->execute([$userId, $tenantId, $consentKey, $consentVersion]);
    if ($stmtConsent->fetchColumn()) {
        $consentOk = true;
    } else {
        $stmtConsent2 = $db->prepare(
            'SELECT id FROM legal_consents
             WHERE user_id = ? AND consent_key = ? AND document_version = ?
             LIMIT 1'
        );
        $stmtConsent2->execute([$userId, $consentKey, $consentVersion]);
        if ($stmtConsent2->fetchColumn()) {
            $consentOk = true;
        }
    }
    if (!$consentOk) {
        jsonError('É necessário aceitar os termos de uso responsável antes da primeira pesquisa.', 428);
    }

    $keywords = trim($company . ' ' . implode(' ', $jobTitles) . ' ' . $location);
    $searchUrl = $keywords !== ''
        ? 'https://www.linkedin.com/search/results/people/?keywords=' . rawurlencode($keywords)
        : 'https://www.linkedin.com/search/results/people/';

    // Cache de consultas compartilhado entre todas as empresas da plataforma (mesmo
    // modelo do Google Maps): se a mesma pesquisa (empresa + cargos + localidade) já
    // foi executada com sucesso em qualquer tenant e foi atualizada nos últimos 30
    // dias, retorna do banco sem consumir a API da Apify. O cache reduz custo interno,
    // mas cada nova consulta confirmada continua consumindo créditos do cliente.
    $cacheHits = null;
    $cacheTableExists = false;
    try {
        $cacheCheck = $db->query('SHOW TABLES LIKE \'linkedin_search_cache\'');
        $cacheTableExists = (bool) $cacheCheck->fetch();
    } catch (PDOException $e) {
        // Tabela de cache ainda não criada: segue sem cache.
    }

    if ($cacheTableExists) {
        $jobTitlesCache = json_encode($jobTitles, JSON_UNESCAPED_UNICODE);
        $stmtCache = $db->prepare(
            'SELECT lc.leads_json, lc.created_at, lc.updated_at
             FROM linkedin_search_cache lc
             WHERE COALESCE(NULLIF(lc.company_lower, \'\'), \'\') = ?
               AND lc.job_titles_lower = ?
               AND lc.location_lower = ?
               AND COALESCE(lc.updated_at, lc.created_at) > DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY COALESCE(lc.updated_at, lc.created_at) DESC
             LIMIT 1'
        );
        $stmtCache->execute([
            mb_strtolower(trim((string) $company)),
            mb_strtolower($jobTitlesCache),
            mb_strtolower(trim((string) $location)),
        ]);
        $cacheHits = $stmtCache->fetch(PDO::FETCH_ASSOC);
    }

    if ($cacheHits && $cacheHits['leads_json'] !== null && $cacheHits['leads_json'] !== '') {
        $cachedLeads = json_decode($cacheHits['leads_json'], true);
        // Não reutiliza registros vazios: eles podem representar uma falha
        // temporária do ator ou um mapeamento antigo, e não uma busca válida.
        if (is_array($cachedLeads) && count($cachedLeads) > 0) {
            // O cache pode conter mais perfis do que a consulta atual solicitou. A
            // entrega e a cobrança devem respeitar o limite já reduzido pelo saldo.
            $cachedLeads = array_slice($cachedLeads, 0, $limit);
            $jobTitlesJson = json_encode($jobTitles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $keywords = trim($company . ' ' . implode(' ', $jobTitles) . ' ' . $location);
            $searchUrl = $keywords !== ''
                ? 'https://www.linkedin.com/search/results/people/?keywords=' . rawurlencode($keywords)
                : 'https://www.linkedin.com/search/results/people/';
            $db->beginTransaction();
            $db->prepare(
                'INSERT INTO linkedin_runs
                  (tenant_id, user_id, search_title, search_location, search_company, search_job_titles, search_url, apify_actor, status, leads_found, credits_charged, created_at, finished_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            )->execute([
                $tenantId,
                $userId,
                'LinkedIn: ' . ($company ?: $jobTitles[0]),
                $location,
                $company,
                $jobTitlesJson,
                $searchUrl,
                'cache',
                'done',
                count($cachedLeads),
                0,
            ]);
            $cachedRunId = (int) $db->lastInsertId();
            saveLinkedInRunResults($db, $tenantId, $cachedRunId, $cachedLeads);
            $cachedCreditsCharged = 0;
            if (!($profile === 'super_admin' || $tenantId === 1) && function_exists('incrementTenantUsage')) {
                incrementTenantUsage($db, $tenantId, count($cachedLeads));
                $cachedCreditsCharged = count($cachedLeads);
                $db->prepare('UPDATE linkedin_runs SET credits_charged = ? WHERE id = ?')
                    ->execute([$cachedCreditsCharged, $cachedRunId]);
            }
            $db->commit();
            jsonSuccess([
                'runId' => (string) $cachedRunId,
                'searchUrl' => $searchUrl,
                'leads' => $cachedLeads,
                'cached' => true,
                'cachedAt' => $cacheHits['updated_at'] ?: $cacheHits['created_at'],
                'creditsCharged' => $cachedCreditsCharged,
            ], 'Resultado recuperado do cache interno de consultas.');
        }
    }

    $jobTitlesJson = json_encode($jobTitles, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $stmtRun = $db->prepare(
        'INSERT INTO linkedin_runs
          (tenant_id, user_id, search_title, search_location, search_company, search_job_titles, search_url, apify_actor, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
    );
    $stmtRun->execute([
        $tenantId,
        $userId,
        'LinkedIn: ' . ($company ?: $jobTitles[0]),
        $location,
        $company,
        $jobTitlesJson,
        $searchUrl,
        'harvestapi~linkedin-profile-search',
        'queued',
    ]);
    $runId = (int) $db->lastInsertId();

    $apiKey = getRotatingApifyKey($db);
    if (trim((string) $apiKey) === '') {
        $db->prepare('UPDATE linkedin_runs SET status = ?, error_message = ?, finished_at = NOW() WHERE id = ?')
            ->execute(['failed', 'Chave da API Apify não configurada.', $runId]);
        jsonError('Chave da API de busca não configurada. Cadastre uma chave em API de Busca.', 500);
    }

    $db->prepare('UPDATE linkedin_runs SET status = ? WHERE id = ?')->execute(['running', $runId]);

    // A execução é iniciada e acompanhada em requisições curtas pelo painel.
    // Assim, buscas de até 200 perfis não mantêm uma única conexão HTTP aberta
    // até o limite do Cloudflare.
    $service = new LinkedInSearchService((string) $apiKey);
    $started = $service->startSearchByCompany($company, $jobTitles, $location, $limit);
    $db->prepare(
        'UPDATE linkedin_runs SET apify_run_id = ?, dataset_id = ?, status = ? WHERE id = ?'
    )->execute([
        $started['run_id'],
        $started['dataset_id'],
        'running',
        $runId,
    ]);

    jsonSuccess([
        'runId' => (string) $runId,
        'searchUrl' => $searchUrl,
        'pending' => true,
        'pollAfterMs' => 2000,
        'limit' => $limit,
    ], 'Pesquisa LinkedIn iniciada. Os perfis serão carregados assim que a execução terminar.');
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    if (isset($runId) && isset($db)) {
        try {
            $db->prepare('UPDATE linkedin_runs SET status = ?, error_message = ?, finished_at = NOW() WHERE id = ?')
                ->execute(['failed', mb_substr($e->getMessage(), 0, 1000), $runId]);
        } catch (Throwable $ignored) {
        }
    }
    error_log('linkedin-search.php: ' . $e->getMessage());
    jsonError('Não foi possível concluir a pesquisa agora. Verifique os filtros e tente novamente em alguns instantes.', 500);
}
