<?php
/**
 * Pesquisa de leads LinkedIn por empresa exclusivamente pela VPS.
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
foreach (['config/config.php', 'config/database.php', 'includes/functions.php', 'services/linkedinRunFallbackService.php'] as $rel) {
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

function linkedinReadSavedLeads(PDO $db, int $tenantId, int $runId): array
{
    try {
        if ((bool) $db->query("SHOW TABLES LIKE 'linkedin_run_results'")->fetch()) {
            $saved = $db->prepare('SELECT leads_json FROM linkedin_run_results WHERE tenant_id = ? AND run_id = ? LIMIT 1');
            $saved->execute([$tenantId, $runId]);
            $decoded = json_decode((string) $saved->fetchColumn(), true);
            if (is_array($decoded)) return $decoded;
        }
        $savedLeads = $db->prepare('SELECT profile_url, full_name, job_title, company_name, location, industry, photo_url FROM linkedin_leads WHERE tenant_id = ? AND run_id = ? ORDER BY id ASC');
        $savedLeads->execute([$tenantId, $runId]);
        return $savedLeads->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        error_log('linkedin-search.php read results: ' . $e->getMessage());
        return [];
    }
}

/**
 * Normaliza grafias comuns de cidade + UF para o padrão mais aceito pela busca
 * de pessoas do LinkedIn. Não tenta adivinhar a localização quando o usuário
 * informa somente uma sigla de estado.
 */
function linkedinNormalizeLocationInput(string $value): array {
    $location = trim(preg_replace('/\s+/u', ' ', $value));
    if ($location === '') {
        throw new InvalidArgumentException('Informe a cidade ou região da pesquisa LinkedIn.');
    }

    $states = 'AC|AL|AP|AM|BA|CE|DF|ES|GO|MA|MT|MS|MG|PA|PB|PR|PE|PI|RJ|RN|RS|RO|RR|SC|SP|SE|TO';
    if (preg_match('/^(' . $states . ')\.?$/iu', $location)) {
        throw new InvalidArgumentException('Informe a cidade ou região junto à UF. Exemplo: Campinas e Região, SP.');
    }

    if (!preg_match('/^(.*?)(?:,|\s)+(' . $states . ')\.?$/iu', $location, $matches)) {
        return ['value' => $location, 'normalized' => false];
    }

    $place = trim(rtrim((string) $matches[1], ', '));
    $state = strtoupper((string) $matches[2]);
    if ($place === '') {
        throw new InvalidArgumentException('Informe a cidade ou região junto à UF. Exemplo: Campinas e Região, SP.');
    }

    // A vírgula já indica uma grafia de cidade/UF legível para a fonte.
    if (strpos($location, ',') !== false) {
        return ['value' => $place . ', ' . $state, 'normalized' => $location !== ($place . ', ' . $state)];
    }

    // Para cidades que não são capitais, "Cidade e Região, UF" é a sugestão
    // mais segura para evitar o formato ambíguo "Cidade UF" do ator.
    $capitalCities = ['BRASILIA', 'SÃO PAULO', 'SAO PAULO', 'RIO DE JANEIRO', 'BELO HORIZONTE', 'CURITIBA', 'PORTO ALEGRE', 'SALVADOR', 'RECIFE', 'FORTALEZA', 'MANAUS', 'GOIÂNIA', 'GOIANIA', 'FLORIANÓPOLIS', 'FLORIANOPOLIS', 'VITÓRIA', 'VITORIA', 'BELÉM', 'BELEM', 'NATAL', 'JOÃO PESSOA', 'JOAO PESSOA', 'MACEIÓ', 'MACEIO', 'ARACAJU', 'TERESINA', 'SÃO LUÍS', 'SAO LUIS', 'CUIABÁ', 'CUIABA', 'CAMPO GRANDE', 'PALMAS', 'PORTO VELHO', 'BOA VISTA', 'MACAPÁ', 'MACAPA', 'RIO BRANCO'];
    $normalizedPlace = preg_match('/\be\s+regi[aã]o$/iu', $place) || in_array(mb_strtoupper($place), $capitalCities, true)
        ? $place
        : $place . ' e Região';

    return ['value' => $normalizedPlace . ', ' . $state, 'normalized' => true];
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
$requestedLimit = max(1, min(625, (int) ($input['limit'] ?? 10)));
$limit = $requestedLimit;
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

try {
    $locationInput = linkedinNormalizeLocationInput($location);
    $location = (string) $locationInput['value'];
    $locationWasNormalized = !empty($locationInput['normalized']);
} catch (InvalidArgumentException $e) {
    jsonError($e->getMessage(), 400);
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
$linkedinSearchStage = 'plan_credit_limit';

// A interface permite até 625 perfis detalhados pelo HarvestAPI Full. Para
// clientes com limite contratado, a solicitação é reduzida apenas ao saldo
// real do plano somado aos créditos extras.
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
    $linkedinSearchStage = 'consent_check';
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
    // dias, retorna do banco sem iniciar uma nova coleta na VPS. O cache reduz custo interno,
    // mas cada nova consulta confirmada continua consumindo créditos do cliente.
    $linkedinSearchStage = 'cache_schema_check';
    $cacheHits = null;
    $cacheTableExists = false;
    $cacheHasUpdatedAt = false;
    try {
        $cacheCheck = $db->query('SHOW TABLES LIKE \'linkedin_search_cache\'');
        $cacheTableExists = (bool) $cacheCheck->fetch();
        if ($cacheTableExists) {
            $updatedAtCheck = $db->query("SHOW COLUMNS FROM linkedin_search_cache LIKE 'updated_at'");
            $cacheHasUpdatedAt = (bool) $updatedAtCheck->fetch();
        }
    } catch (PDOException $e) {
        // Tabela de cache ainda não criada: segue sem cache.
    }

    if ($cacheTableExists) {
        $linkedinSearchStage = 'cache_lookup';
        $jobTitlesCache = json_encode($jobTitles, JSON_UNESCAPED_UNICODE);
        // Instalações antigas podem ter sido criadas antes da coluna updated_at.
        // Nesse caso, created_at passa a representar a validade do cache.
        $cacheFreshnessColumn = $cacheHasUpdatedAt
            ? 'COALESCE(lc.updated_at, lc.created_at)'
            : 'lc.created_at';
        $cacheUpdatedColumn = $cacheHasUpdatedAt ? ', lc.updated_at' : '';
        $stmtCache = $db->prepare(
            'SELECT lc.leads_json, lc.created_at' . $cacheUpdatedColumn . '
             FROM linkedin_search_cache lc
             WHERE COALESCE(NULLIF(lc.company_lower, \'\'), \'\') = ?
               AND lc.job_titles_lower = ?
               AND lc.location_lower = ?
               AND ' . $cacheFreshnessColumn . ' > DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY ' . $cacheFreshnessColumn . ' DESC
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
        // Não reutiliza registros vazios nem caches antigos do modo Short.
        // O produto agora promete perfis Full; uma consulta anterior só pode
        // ser reaproveitada se possuir os campos detalhados necessários.
        $cacheLooksFull = is_array($cachedLeads) && count($cachedLeads) > 0
            && is_array($cachedLeads[0] ?? null)
            && (array_key_exists('experience', $cachedLeads[0])
                || ($cachedLeads[0]['source'] ?? '') === 'harvestapi_full');
        if ($cacheLooksFull) {
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
                'cachedAt' => ($cacheHasUpdatedAt && !empty($cacheHits['updated_at']))
                    ? $cacheHits['updated_at']
                    : $cacheHits['created_at'],
                'creditsCharged' => $cachedCreditsCharged,
                'requestedLimit' => $requestedLimit,
                'effectiveLimit' => $limit,
                'location' => $location,
                'locationWasNormalized' => $locationWasNormalized,
                'source' => 'vps',
            ], 'Resultado recuperado do cache interno de consultas.');
        }
    }

    $linkedinSearchStage = 'run_prepare';
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
        'authenticated_session',
        // "running" já é aceito pelas instalações antigas. Evita depender do
        // estado "queued", ausente em alguns esquemas legados de linkedin_runs.
        'running',
    ]);
    $runId = (int) $db->lastInsertId();

    $linkedinSearchStage = 'authenticated_collect';
    if (!function_exists('linkedinRunAuthenticatedSearch') || !function_exists('linkedinRunApifyContingency')) {
        throw new RuntimeException('Serviço LinkedIn autenticado não está disponível nesta instalação.');
    }
    $db->prepare('UPDATE linkedin_runs SET status = ? WHERE id = ?')->execute(['running', $runId]);
    $runPayload = [
        'id' => $runId,
        'user_id' => $userId,
        'tenant_id' => $tenantId,
        'search_title' => 'LinkedIn: ' . ($company ?: $jobTitles[0]),
        'search_company' => $company,
        'search_location' => $location,
        'search_job_titles' => $jobTitlesJson,
        'limit_requested' => $limit,
    ];
    // HarvestAPI Full é a fonte principal: entrega perfis detalhados e permite
    // até 625 resultados por execução dentro da configuração do produto.
    $linkedinSearchStage = 'harvestapi_full';
    $completed = linkedinRunApifyContingency(
        $db,
        $userId,
        $runPayload,
        $tenantId,
        $profile,
        'pesquisa principal pelo HarvestAPI Full'
    );
    $source = 'apify_full';
    if (!$completed) {
        // O coletor autenticado permanece como fallback operacional para não
        // interromper o serviço caso o actor esteja indisponível.
        $linkedinSearchStage = 'authenticated_fallback';
        $completed = linkedinRunAuthenticatedSearch(
            $db,
            $userId,
            $runPayload,
            $tenantId,
            $profile,
            'fallback acionado após falha do HarvestAPI Full'
        );
        $source = 'authenticated';
    }
    if (!$completed) {
        throw new RuntimeException('A coleta autenticada e a contingência não foram concluídas.');
    }
    $leads = linkedinReadSavedLeads($db, $tenantId, $runId);
    $creditsStmt = $db->prepare('SELECT credits_charged, apify_actor FROM linkedin_runs WHERE id = ? LIMIT 1');
    $creditsStmt->execute([$runId]);
    $runSource = $creditsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $credits = (int) ($runSource['credits_charged'] ?? 0);
    if (($runSource['apify_actor'] ?? '') === 'harvestapi_full') {
        $source = 'apify_full';
    } elseif (($runSource['apify_actor'] ?? '') === 'authenticated_session') {
        $source = 'authenticated';
    }
    jsonSuccess([
        'runId' => (string) $runId,
        'searchUrl' => $searchUrl,
        'pending' => false,
        'leads' => $leads,
        'count' => count($leads),
        'creditsCharged' => $credits,
        'limit' => $limit,
        'requestedLimit' => $requestedLimit,
        'effectiveLimit' => $limit,
        'location' => $location,
        'locationWasNormalized' => $locationWasNormalized,
        'source' => $source,
        'sourceNotice' => $source === 'apify_full'
            ? 'Perfis detalhados localizados pelo actor HarvestAPI Full.'
            : ($source === 'authenticated'
                ? 'A consulta foi concluída pela fonte autenticada de contingência.'
                : 'Resultado recuperado do cache interno.'),
    ], 'Pesquisa LinkedIn concluída.');
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    $safeStage = isset($linkedinSearchStage) ? (string) $linkedinSearchStage : 'bootstrap';
    $safeError = mb_substr((string) $e->getMessage(), 0, 1000);
    error_log('linkedin-search.php [' . $safeStage . ']: ' . $safeError);
    @file_put_contents(
        __DIR__ . '/linkedin-search-error.log',
        '[' . gmdate('c') . '] stage=' . $safeStage . ' error=' . $safeError . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
    if (isset($runId) && $runId > 0 && isset($db) && $db instanceof PDO) {
        try {
            $db->prepare('UPDATE linkedin_runs SET status = ?, error_message = ?, finished_at = NOW() WHERE id = ?')
                ->execute(['failed', 'Coleta privada pela VPS não concluída.', $runId]);
        } catch (Throwable $ignored) {
        }
    }
    jsonError('Não foi possível concluir a pesquisa agora. Verifique os filtros e tente novamente em alguns instantes.', 500);
}
