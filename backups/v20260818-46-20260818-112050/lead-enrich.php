<?php
/**
 * Maps Prospector — Enriquecimento corporativo de lead LinkedIn.
 *
 * O navegador fala apenas com este endpoint autenticado. O ator Apify é chamado
 * exclusivamente daqui, usando a rotação de contas já administrada pelo sistema;
 * o token não entra no HTML, JavaScript, banco de auditoria ou respostas JSON.
 * O endpoint preserva resultados apenas de contato
 * corporativo publicado: contas de e-mail de função, telefones e links sociais
 * encontrados no domínio consultado.
 */
declare(strict_types=1);

ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');

function leadEnrichLoadFile(string $relativePath): ?string
{
    $roots = array_unique(array_filter([
        dirname(__DIR__),
        rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'),
        rtrim((string) (getenv('HOME') ?: ''), '/') . '/prospec.atendo.log.br',
    ], 'is_dir'));

    foreach ($roots as $root) {
        $file = rtrim($root, '/') . '/' . ltrim($relativePath, '/');
        if (is_file($file)) {
            return $file;
        }
    }
    return null;
}

function leadEnrichError(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function leadEnrichSuccess(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function leadEnrichTableExists(PDO $db): bool
{
    static $available = null;
    if ($available === null) {
        $available = (bool) $db->query("SHOW TABLES LIKE 'lead_enrichments'")->fetch();
    }
    return $available;
}

function leadEnrichNormalizeUrl(string $candidate): array
{
    $value = trim($candidate);
    if ($value === '') {
        throw new InvalidArgumentException('Informe o site público da empresa para continuar.');
    }
    if (!preg_match('#^https?://#i', $value)) {
        $value = 'https://' . $value;
    }
    if (strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
        throw new InvalidArgumentException('Informe uma URL pública válida, iniciando por https://.');
    }

    $parts = parse_url($value);
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
    if (!in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
        throw new InvalidArgumentException('A URL deve apontar para um site público HTTP ou HTTPS, sem credenciais.');
    }
    if ($host === 'localhost' || substr($host, -6) === '.local' || $host === 'metadata.google.internal' || strpos($host, '.') === false) {
        throw new InvalidArgumentException('Endereços locais ou internos não podem ser consultados.');
    }

    $ips = [];
    if (filter_var($host, FILTER_VALIDATE_IP)) {
        $ips[] = $host;
    } else {
        $resolved = gethostbynamel($host);
        if (is_array($resolved)) {
            $ips = $resolved;
        }
    }
    foreach ($ips as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw new InvalidArgumentException('Endereços privados, locais ou reservados não podem ser consultados.');
        }
    }

    $port = isset($parts['port']) ? (int) $parts['port'] : 0;
    if ($port !== 0 && !in_array($port, [80, 443], true)) {
        throw new InvalidArgumentException('A consulta aceita apenas as portas públicas HTTP e HTTPS.');
    }

    return [
        'url' => $value,
        'domain' => preg_replace('/^www\./i', '', $host),
        'hash' => hash('sha256', preg_replace('/^www\./i', '', $host)),
    ];
}

function leadEnrichDecodeArray($value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return [];
    }
    $decoded = json_decode($value, true);
    return is_array($decoded) ? $decoded : [];
}

function leadEnrichIsCorporateEmail(string $email, string $domain): bool
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    [$local, $emailDomain] = explode('@', $email, 2);
    $domain = strtolower($domain);
    if ($emailDomain !== $domain && substr($emailDomain, -strlen('.' . $domain)) !== '.' . $domain) {
        return false;
    }
    return (bool) preg_match('/^(contato|comercial|vendas|suporte|atendimento|sac|info|hello|marketing|financeiro|rh|people|press|imprensa|privacy|dpo|juridico|legal)([+._-].*)?$/i', $local);
}

function leadEnrichNormalizePhone(string $phone): ?string
{
    $candidate = trim($phone);
    $digits = preg_replace('/\D+/', '', $candidate);
    if ($digits === null || strlen($digits) < 8 || strlen($digits) > 18) {
        return null;
    }
    return $candidate;
}

function leadEnrichSafePublicUrl(string $url): ?string
{
    $url = trim($url);
    if ($url === '' || !preg_match('#^https?://#i', $url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return null;
    }
    return $url;
}

function leadEnrichFilterActorResult(array $items, string $domain): array
{
    $emails = [];
    $phones = [];
    $social = [];
    $socialFields = [
        'linkedIns' => 'linkedin',
        'facebooks' => 'facebook',
        'instagrams' => 'instagram',
        'twitters' => 'twitter',
        'youtubes' => 'youtube',
    ];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        foreach (leadEnrichDecodeArray($item['emails'] ?? []) as $email) {
            $email = strtolower(trim((string) $email));
            if (leadEnrichIsCorporateEmail($email, $domain)) {
                $emails[$email] = $email;
            }
        }
        foreach (leadEnrichDecodeArray($item['phones'] ?? []) as $phone) {
            $phone = leadEnrichNormalizePhone((string) $phone);
            if ($phone !== null) {
                $phones[$phone] = $phone;
            }
        }
        foreach ($socialFields as $field => $type) {
            foreach (leadEnrichDecodeArray($item[$field] ?? []) as $url) {
                $url = leadEnrichSafePublicUrl((string) $url);
                if ($url !== null) {
                    $social[$type . '|' . $url] = ['type' => $type, 'url' => $url];
                }
            }
        }
    }

    return [
        'emails' => array_values($emails),
        'phones' => array_values($phones),
        'social_links' => array_values($social),
    ];
}

function leadEnrichRecordPayload(array $row): array
{
    return [
        'id' => (string) ($row['id'] ?? ''),
        'pending' => in_array((string) ($row['status'] ?? ''), ['queued', 'running'], true),
        'status' => (string) ($row['status'] ?? ''),
        'domain' => (string) ($row['domain'] ?? ''),
        'emails' => leadEnrichDecodeArray($row['emails'] ?? []),
        'phones' => leadEnrichDecodeArray($row['phones'] ?? []),
        'socialLinks' => leadEnrichDecodeArray($row['social_links'] ?? []),
        'source' => (string) ($row['source'] ?? ''),
        'creditsCharged' => (int) ($row['credits_charged'] ?? 0),
        'requestedAt' => (string) ($row['requested_at'] ?? ''),
        'completedAt' => (string) ($row['completed_at'] ?? ''),
        'expiresAt' => (string) ($row['expires_at'] ?? ''),
        'lastError' => (string) ($row['last_error'] ?? ''),
    ];
}

function leadEnrichApiRequest(string $method, string $url, string $token, ?array $body = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('A extensão cURL não está habilitada neste servidor.');
    }
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    $separator = strpos($url, '?') === false ? '?' : '&';
    $requestUrl = $url . $separator . 'token=' . rawurlencode($token);
    $curl = curl_init($requestUrl);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 55,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($body !== null) {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new RuntimeException('Não foi possível preparar a consulta de enriquecimento.');
        }
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
    }
    $response = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($response === false) {
        throw new RuntimeException('Não foi possível conectar ao serviço de enriquecimento: ' . $error);
    }
    $decoded = json_decode((string) $response, true);
    if ($status < 200 || $status >= 300 || !is_array($decoded)) {
        $details = is_array($decoded) ? (string) ($decoded['error']['message'] ?? $decoded['message'] ?? '') : '';
        throw new RuntimeException('A consulta de enriquecimento não foi aceita.' . ($details !== '' ? ' ' . $details : ''));
    }
    return $decoded;
}

/**
 * Reutiliza a mesma rotação de contas da busca LinkedIn. A função central escolhe
 * produção, promove reserva quando aplicável e usa o fallback já definido pela
 * plataforma. Nenhum token é persistido ou devolvido pelo enriquecimento.
 */
function leadEnrichGetRotatingApifyKey(PDO $db): string
{
    if (!function_exists('getRotatingApifyKey')) {
        throw new RuntimeException('A rotação de contas Apify não está disponível neste servidor.');
    }
    $token = trim((string) getRotatingApifyKey($db));
    if ($token === '') {
        throw new RuntimeException('Nenhuma conta Apify disponível no momento. Verifique as chaves ativas em API de Busca.');
    }
    return $token;
}

function leadEnrichCreditCost(PDO $db): int
{
    $configured = null;
    if (function_exists('getPlatformSetting')) {
        $configured = getPlatformSetting($db, 'lead_enrichment_credits');
    }
    $value = is_numeric($configured) ? (int) $configured : 1;
    return max(0, min(10, $value));
}

function leadEnrichFinalizeRun(PDO $db, array $row, string $token): array
{
    $runId = trim((string) ($row['apify_run_id'] ?? ''));
    $datasetId = trim((string) ($row['apify_dataset_id'] ?? ''));
    if ($runId === '' || $datasetId === '') {
        throw new RuntimeException('A execução de enriquecimento não possui identificadores válidos.');
    }
    $run = leadEnrichApiRequest('GET', 'https://api.apify.com/v2/actor-runs/' . rawurlencode($runId), $token);
    $status = strtoupper((string) ($run['data']['status'] ?? ''));
    if (in_array($status, ['READY', 'RUNNING'], true)) {
        return ['pending' => true, 'status' => strtolower($status)];
    }
    if ($status !== 'SUCCEEDED') {
        $message = 'A execução Apify terminou com status ' . ($status ?: 'desconhecido') . '.';
        $db->prepare('UPDATE lead_enrichments SET status = ?, last_error = ?, completed_at = NOW() WHERE id = ?')
            ->execute(['failed', $message, (int) $row['id']]);
        throw new RuntimeException($message);
    }

    $itemsResponse = leadEnrichApiRequest('GET', 'https://api.apify.com/v2/datasets/' . rawurlencode($datasetId) . '/items?clean=true&format=json', $token);
    $items = $itemsResponse;
    // A API de datasets entrega um array. Mantemos este fallback para respostas
    // encapsuladas de compatibilidade sem aceitar formato arbitrário.
    if (isset($itemsResponse['data']) && is_array($itemsResponse['data'])) {
        $items = $itemsResponse['data'];
    }
    if (!is_array($items) || (isset($items['data']) && !isset($items[0]))) {
        $items = [];
    }

    $filtered = leadEnrichFilterActorResult($items, (string) $row['domain']);
    $db->prepare(
        'UPDATE lead_enrichments
         SET status = ?, emails = ?, phones = ?, social_links = ?, completed_at = NOW(),
             expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY), last_error = NULL
         WHERE id = ?'
    )->execute([
        'done',
        json_encode($filtered['emails'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($filtered['phones'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($filtered['social_links'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        (int) $row['id'],
    ]);

    $done = $db->prepare('SELECT * FROM lead_enrichments WHERE id = ? LIMIT 1');
    $done->execute([(int) $row['id']]);
    return ['pending' => false, 'row' => $done->fetch(PDO::FETCH_ASSOC) ?: []];
}

foreach (['config/config.php', 'config/database.php', 'includes/functions.php'] as $relative) {
    $file = leadEnrichLoadFile($relative);
    if ($file === null) {
        leadEnrichError('Arquivo obrigatório não encontrado: ' . $relative, 500);
    }
    require_once $file;
}

if (!function_exists('requireAuth') || !function_exists('getAuthUser') || !class_exists('Database')) {
    leadEnrichError('As dependências de autenticação do sistema não foram carregadas.', 500);
}

try {
    $authenticatedUserId = requireAuth();
    $userId = is_numeric($authenticatedUserId) && (int) $authenticatedUserId > 0
        ? (int) $authenticatedUserId
        : null;
    $authUser = getAuthUser();
    $profile = strtolower((string) ($authUser['profile'] ?? ''));
    $tenantId = (int) ($authUser['tenant_id'] ?? 0);
    if ($tenantId <= 0) {
        $tenantId = 1;
    }
    $db = Database::getInstance()->getConnection();
    if (!leadEnrichTableExists($db)) {
        leadEnrichError('A tabela de enriquecimento ainda não foi instalada. Importe sql/lead-enrichments.sql no phpMyAdmin.', 503);
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        $recordId = max(0, (int) ($_GET['id'] ?? 0));
        $profileUrl = trim((string) ($_GET['lead_profile_url'] ?? ''));
        if ($recordId > 0) {
            $stmt = $db->prepare('SELECT * FROM lead_enrichments WHERE id = ? AND tenant_id = ? LIMIT 1');
            $stmt->execute([$recordId, $tenantId]);
        } elseif ($profileUrl !== '') {
            $stmt = $db->prepare(
                'SELECT * FROM lead_enrichments
                 WHERE tenant_id = ?
                   AND ((? IS NOT NULL AND user_id = ?) OR (? IS NULL AND user_id IS NULL))
                   AND lead_profile_url = ?
                 ORDER BY created_at DESC LIMIT 1'
            );
            $stmt->execute([$tenantId, $userId, $userId, $userId, mb_substr($profileUrl, 0, 512)]);
        } else {
            leadEnrichError('Informe o identificador do enriquecimento ou o perfil do lead.', 400);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            leadEnrichSuccess(['found' => false]);
        }
        if (in_array((string) $row['status'], ['queued', 'running'], true)) {
            $token = leadEnrichGetRotatingApifyKey($db);
            $finalized = leadEnrichFinalizeRun($db, $row, $token);
            if (!empty($finalized['row'])) {
                $row = $finalized['row'];
                $creditCost = leadEnrichCreditCost($db);
                $isPlatformAccount = ((int) ($row['tenant_id'] ?? 0) === 1);
                if (!$isPlatformAccount && (string) ($row['source'] ?? '') === 'apify'
                    && (string) ($row['status'] ?? '') === 'done'
                    && (int) ($row['credits_charged'] ?? 0) === 0
                    && $creditCost > 0 && function_exists('incrementTenantUsage')) {
                    $db->beginTransaction();
                    $db->prepare('UPDATE lead_enrichments SET credits_charged = ? WHERE id = ? AND credits_charged = 0')
                        ->execute([$creditCost, (int) $row['id']]);
                    if ($db->query('SELECT ROW_COUNT()')->fetchColumn()) {
                        incrementTenantUsage($db, (int) $row['tenant_id'], $creditCost);
                    }
                    $db->commit();
                    $charged = $db->prepare('SELECT * FROM lead_enrichments WHERE id = ? LIMIT 1');
                    $charged->execute([(int) $row['id']]);
                    $row = $charged->fetch(PDO::FETCH_ASSOC) ?: $row;
                }
            }
        }
        leadEnrichSuccess(['found' => true, 'enrichment' => leadEnrichRecordPayload($row)]);
    }

    if ($method !== 'POST') {
        leadEnrichError('Método não permitido.', 405);
    }

    $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($origin !== '' && $host !== '' && parse_url($origin, PHP_URL_HOST) !== preg_replace('/:\d+$/', '', $host)) {
        leadEnrichError('Origem da solicitação não autorizada.', 403);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        leadEnrichError('Dados da solicitação inválidos.', 400);
    }
    $leadProfileUrl = mb_substr(trim((string) ($input['lead_profile_url'] ?? '')), 0, 512);
    $leadName = mb_substr(trim((string) ($input['lead_name'] ?? '')), 0, 255);
    $companyName = mb_substr(trim((string) ($input['company_name'] ?? '')), 0, 255);
    if ($leadProfileUrl === '' || !preg_match('#^https://(www\.)?linkedin\.com/#i', $leadProfileUrl)) {
        leadEnrichError('O perfil LinkedIn do lead é obrigatório para vincular o resultado.', 400);
    }
    $website = leadEnrichNormalizeUrl((string) ($input['website'] ?? ''));

    $leadId = null;
    try {
        $lead = $db->prepare(
            'SELECT id FROM linkedin_leads WHERE tenant_id = ? AND profile_url = ? ORDER BY id DESC LIMIT 1'
        );
        $lead->execute([$tenantId, $leadProfileUrl]);
        $foundLeadId = $lead->fetchColumn();
        $leadId = $foundLeadId !== false ? (int) $foundLeadId : null;
    } catch (Throwable $ignored) {
        // A associação por URL continua disponível se a busca veio do cache e o
        // perfil ainda não foi materializado em linkedin_leads para este tenant.
    }

    $token = leadEnrichGetRotatingApifyKey($db);
    // A Task é vinculada a uma única conta. Para a rotação atual funcionar entre
    // todas as contas, executamos o mesmo ator público com os parâmetros da Task.
    $actorId = 'vdrmota~contact-info-scraper';

    $fresh = $db->prepare(
        'SELECT * FROM lead_enrichments
         WHERE domain_hash = ? AND status = ? AND expires_at > NOW()
         ORDER BY completed_at DESC, id DESC LIMIT 1'
    );
    $fresh->execute([$website['hash'], 'done']);
    $cached = $fresh->fetch(PDO::FETCH_ASSOC);
    $creditCost = leadEnrichCreditCost($db);
    $shouldCharge = !($profile === 'super_admin' || $tenantId === 1) && $creditCost > 0;

    if ($cached) {
        $db->beginTransaction();
        $insert = $db->prepare(
            'INSERT INTO lead_enrichments
             (tenant_id, user_id, lead_id, lead_profile_url, lead_name, company_name, domain, domain_hash,
              emails, phones, social_links, apify_run_id, apify_dataset_id, source, status, credits_charged,
              requested_at, completed_at, expires_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?, NOW(), NOW())'
        );
        $insert->execute([
            $tenantId, $userId, $leadId, $leadProfileUrl, $leadName ?: null, $companyName ?: null,
            $website['domain'], $website['hash'], $cached['emails'], $cached['phones'], $cached['social_links'],
            $cached['apify_run_id'], $cached['apify_dataset_id'], 'cache', 'done', 0, $cached['expires_at'],
        ]);
        $recordId = (int) $db->lastInsertId();
        if ($shouldCharge && function_exists('incrementTenantUsage')) {
            incrementTenantUsage($db, $tenantId, $creditCost);
            $db->prepare('UPDATE lead_enrichments SET credits_charged = ? WHERE id = ?')->execute([$creditCost, $recordId]);
        }
        $db->commit();
        $saved = $db->prepare('SELECT * FROM lead_enrichments WHERE id = ? LIMIT 1');
        $saved->execute([$recordId]);
        leadEnrichSuccess(['enrichment' => leadEnrichRecordPayload($saved->fetch(PDO::FETCH_ASSOC) ?: []), 'message' => 'Dados corporativos recuperados do cache interno.']);
    }

    $running = $db->prepare(
        'SELECT id FROM lead_enrichments
         WHERE domain_hash = ? AND status IN (\'queued\', \'running\')
           AND requested_at > DATE_SUB(NOW(), INTERVAL 20 MINUTE)
         ORDER BY id DESC LIMIT 1'
    );
    $running->execute([$website['hash']]);
    if ($runningId = $running->fetchColumn()) {
        leadEnrichSuccess(['enrichment' => ['id' => (string) $runningId, 'pending' => true, 'status' => 'running'], 'message' => 'Uma consulta para este domínio já está em andamento.'], 202);
    }

    $db->prepare(
        'INSERT INTO lead_enrichments
         (tenant_id, user_id, lead_id, lead_profile_url, lead_name, company_name, domain, domain_hash,
          source, status, credits_charged, requested_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW(), NOW(), NOW())'
    )->execute([$tenantId, $userId, $leadId, $leadProfileUrl, $leadName ?: null, $companyName ?: null, $website['domain'], $website['hash'], 'apify', 'running']);
    $recordId = (int) $db->lastInsertId();

    try {
        $inputData = [
            'startUrls' => [['url' => $website['url']]],
            'maxRequestsPerStartUrl' => 3,
            'mergeContacts' => true,
            'maxDepth' => 1,
            'maxRequests' => 3,
            'sameDomain' => true,
            'considerChildFrames' => false,
            'maximumLeadsEnrichmentRecords' => 0,
            'verifyLeadsEnrichmentEmails' => false,
            'scrapeSocialMediaProfiles' => [
                'facebooks' => false, 'instagrams' => false, 'youtubes' => false, 'tiktoks' => false, 'twitters' => false,
            ],
            'useBrowser' => false,
            'waitUntil' => 'domcontentloaded',
            'proxyConfig' => ['useApifyProxy' => true],
        ];
        $started = leadEnrichApiRequest('POST', 'https://api.apify.com/v2/acts/' . rawurlencode($actorId) . '/runs?waitForFinish=45', $token, $inputData);
        $runData = is_array($started['data'] ?? null) ? $started['data'] : [];
        $runId = trim((string) ($runData['id'] ?? ''));
        $datasetId = trim((string) ($runData['defaultDatasetId'] ?? ''));
        if ($runId === '' || $datasetId === '') {
            throw new RuntimeException('A Apify não retornou os identificadores da execução.');
        }
        $db->prepare('UPDATE lead_enrichments SET apify_run_id = ?, apify_dataset_id = ? WHERE id = ?')
            ->execute([$runId, $datasetId, $recordId]);
        $record = $db->prepare('SELECT * FROM lead_enrichments WHERE id = ? LIMIT 1');
        $record->execute([$recordId]);
        $row = $record->fetch(PDO::FETCH_ASSOC) ?: [];
        $finalized = leadEnrichFinalizeRun($db, $row, $token);
        if (!empty($finalized['pending'])) {
            leadEnrichSuccess(['enrichment' => ['id' => (string) $recordId, 'pending' => true, 'status' => 'running'], 'message' => 'Consulta iniciada. Aguarde a conclusão.'], 202);
        }

        $doneRow = $finalized['row'] ?? [];
        if ($shouldCharge && function_exists('incrementTenantUsage')) {
            $db->beginTransaction();
            incrementTenantUsage($db, $tenantId, $creditCost);
            $db->prepare('UPDATE lead_enrichments SET credits_charged = ? WHERE id = ?')->execute([$creditCost, $recordId]);
            $db->commit();
            $done = $db->prepare('SELECT * FROM lead_enrichments WHERE id = ? LIMIT 1');
            $done->execute([$recordId]);
            $doneRow = $done->fetch(PDO::FETCH_ASSOC) ?: $doneRow;
        }
        leadEnrichSuccess(['enrichment' => leadEnrichRecordPayload($doneRow), 'message' => 'Dados corporativos encontrados e salvos no histórico do lead.']);
    } catch (Throwable $error) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $db->prepare('UPDATE lead_enrichments SET status = ?, last_error = ?, completed_at = NOW() WHERE id = ?')
            ->execute(['failed', mb_substr($error->getMessage(), 0, 1000), $recordId]);
        throw $error;
    }
} catch (InvalidArgumentException $error) {
    leadEnrichError($error->getMessage(), 400);
} catch (RuntimeException $error) {
    error_log('lead-enrich.php: ' . $error->getMessage());
    leadEnrichError($error->getMessage(), 422);
} catch (Throwable $error) {
    error_log('lead-enrich.php: ' . $error->getMessage());
    leadEnrichError('Não foi possível concluir o enriquecimento agora. Tente novamente em alguns instantes.', 500);
}
