<?php
declare(strict_types=1);

/**
 * Orquestrador de pesquisas LinkedIn com HarvestAPI Full como fonte
 * principal e sessão autenticada temporária como fallback operacional.
 *
 * A pesquisa principal usa o actor HarvestAPI para obter perfis detalhados;
 * o coletor LinkedIn self-hosted permanece disponível quando o actor falhar.
 * O usuário nunca vê o motivo técnico da falha; o detalhe permanece apenas
 * em registro interno, e a mensagem de retorno é genérica e positiva.
 *
 * Garantias:
 *  - Os perfis localizados são gravados em linkedin_leads,
 *    linkedin_run_results e no cache compartilhado (linkedin_search_cache).
 *  - A cobrança segue a regra atual: créditos consumidos pelos leads
 *    vinculados à pesquisa (super_admin e tenant de validação ficam isentos).
 *  - O run original termina com status "done", igual ao fluxo normal.
 *
 * Retorna true quando a pesquisa foi concluída pela fonte alternativa e
 * false quando não foi possível (coletor indisponível, sem resultados ou
 * falha operacional), caso em que o endpoint devolve erro genérico.
 */

if (!defined('LINKEDIN_RUN_FALLBACK_SERVICE_LOADED')) {
    define('LINKEDIN_RUN_FALLBACK_SERVICE_LOADED', true);

    /**
     * Lê o CSV bruto devolvido pelo coletor e converte para leads LinkedIn,
     * preservando profile_url, nome e demais colunas quando presentes.
     *
     * @return array<int, array<string, mixed>>
     */
    function linkedinFallbackReadCsvLeads(string $csv, int $maxLeads = 200): array
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            throw new RuntimeException('Não foi possível ler o resultado do coletor.');
        }
        fwrite($handle, $csv);
        rewind($handle);

        $headers = fgetcsv($handle);
        if (!is_array($headers)) {
            fclose($handle);
            throw new RuntimeException('O coletor retornou um resultado sem cabeçalho.');
        }
        $headers = array_map(static function ($header): string {
            return strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $header) ?: ''));
        }, $headers);

        $leads = [];
        while (($values = fgetcsv($handle)) !== false && count($leads) < $maxLeads) {
            if (count($values) === 1 && trim((string) $values[0]) === '') {
                continue;
            }
            $row = [];
            foreach ($headers as $index => $header) {
                if ($header !== '') {
                    $row[$header] = trim((string) ($values[$index] ?? ''));
                }
            }

            $profileUrl = '';
            $rawUrl = trim((string) ($row['profile_url'] ?? ''));
            if (preg_match('#^https?://[a-z0-9.-]*linkedin\.com/in/[a-z0-9_%-]+#i', $rawUrl)) {
                $profileUrl = $rawUrl;
            } elseif ($rawUrl !== '') {
                // O coletor às vezes devolve o slug cru; normaliza para URL completa.
                $profileUrl = 'https://www.linkedin.com/in/' . ltrim($rawUrl, '/');
            }

            $name = trim((string) ($row['full_name'] ?? $row['name'] ?? ''));
            if ($profileUrl === '' && $name === '') {
                continue;
            }

            $leads[] = [
                'profile_url' => $profileUrl,
                'full_name' => $name,
                'job_title' => trim((string) ($row['job_title'] ?? $row['title'] ?? '')) ?: null,
                'company_name' => trim((string) ($row['company_name'] ?? $row['company'] ?? '')) ?: null,
                'location' => trim((string) ($row['location'] ?? '')) ?: null,
                'industry' => trim((string) ($row['industry'] ?? '')) ?: null,
                'photo_url' => trim((string) ($row['photo_url'] ?? '')) ?: null,
                'phone' => trim((string) ($row['phone'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'website' => trim((string) ($row['website'] ?? '')),
            ];
        }
        fclose($handle);
        return $leads;
    }

    /**
     * Carrega o cliente do coletor LinkedIn, buscando o arquivo em várias
     * posições possíveis (o endpoint pode ser servido de subdiretórios).
     */
    function linkedinFallbackLoadCollectorClient(): ?AtendoLinkedinCollectorClient
    {
        static $client = null;
        static $attempted = false;

        if ($attempted && $client === null) {
            return null;
        }

        if ($client !== null) {
            return $client;
        }

        $attempted = true;
        foreach ([
            dirname(__DIR__) . '/includes/AtendoLinkedinCollectorClient.php',
            dirname(__DIR__, 2) . '/includes/AtendoLinkedinCollectorClient.php',
        ] as $clientPath) {
            if (is_file($clientPath)) {
                require_once $clientPath;
                break;
            }
        }
        if (!class_exists('AtendoLinkedinCollectorClient')) {
            return null;
        }

        try {
            $client = new AtendoLinkedinCollectorClient();
            if (!$client->isEnabled()) {
                $client = null;
            }
        } catch (Throwable $error) {
            error_log('linkedin fallback client: ' . $error->getMessage());
            $client = null;
        }
        return $client;
    }

    /**
     * Inicia a coleta no coletor autenticado isolado e aguarda a conclusão por polling
     * síncrono curto (o painel acompanha, e o endpoint do painel também faz
     * polling — o tempo total fica dentro do limite de requisição).
     *
     * @return array{csv: string, limit: int}|null
     */
    function linkedinFallbackCollect(AtendoLinkedinCollectorClient $collector, string $jobTitle, string $location, string $company, int $limit): ?array
    {
        $created = $collector->createJob($jobTitle, $location, $company, min($limit, 50));
        $jobId = trim((string) ($created['job_id'] ?? ''));
        if (!preg_match('/^[0-9a-f-]{36}$/i', $jobId)) {
            return null;
        }

        $terminalStates = ['done', 'finished', 'completed', 'success', 'ok'];
        $failedStates = ['failed', 'error', 'cancelled', 'canceled'];
        $deadline = time() + 122;
        $state = 'pending';
        while (time() < $deadline) {
            sleep(3);
            $job = $collector->getJob($jobId);
            $state = strtolower(trim((string) ($job['status'] ?? $state)));
            if (in_array($state, $terminalStates, true)) {
                break;
            }
            if (in_array($state, $failedStates, true)) {
                error_log('linkedin fallback: estado do coletor ' . $state);
                return null;
            }
        }
        if (!in_array($state, $terminalStates, true)) {
            error_log('linkedin fallback: coletor não concluiu no prazo.');
            return null;
        }

        try {
            $csv = $collector->downloadAndDeleteJob($jobId);
        } catch (Throwable $error) {
            error_log('linkedin fallback download: ' . $error->getMessage());
            return null;
        }

        return ['csv' => $csv, 'limit' => $limit];
    }

    /**
     * Consulta somente o estado sem expor cookies, senha ou qualquer dado de
     * sessão ao navegador. O gateway converte sessão ausente/expirada em 409.
     */
    function linkedinAuthenticatedSessionStatus(): array
    {
        $collector = linkedinFallbackLoadCollectorClient();
        if ($collector === null) {
            return ['available' => false, 'reason' => 'collector_unavailable'];
        }
        try {
            $status = $collector->getSessionStatus();
            $active = !empty($status['active']) || !empty($status['session_active']) || (string) ($status['status'] ?? '') === 'active';
            return ['available' => true, 'active' => $active, 'expires_at' => $status['expires_at'] ?? null];
        } catch (Throwable $error) {
            return ['available' => false, 'reason' => 'session_status_unavailable'];
        }
    }

    /**
     * Fonte primária: conclui a pesquisa pelo coletor autenticado isolado.
     * Mantém o nome histórico da função de coleta como compatibilidade com
     * instalações v64, mas não usa o script público antigo.
     *
     * @return string completed, empty ou failed
     */
    function linkedinRunAuthenticatedSearch(
        PDO $db,
        int $userId,
        array $run,
        int $tenantId,
        string $profile,
        string $internalMessage = 'coletor autenticado'
    ): string {
        return linkedinRunFallbackToMapsCollector($db, $userId, $run, $tenantId, $profile, $internalMessage);
    }

    function linkedinRunFinalizeEmptySearch(PDO $db, array $run, int $tenantId, string $actor, string $message): bool
    {
        $runId = (int) ($run['id'] ?? 0);
        if ($runId <= 0) return false;
        try {
            $db->prepare(
                'UPDATE linkedin_runs
                 SET status = ?, apify_actor = ?, leads_found = 0, credits_charged = 0, error_message = ?, finished_at = NOW()
                 WHERE id = ?'
            )->execute(['done', $actor, $message, $runId]);
            if (function_exists('linkedinStatusSaveResults')) {
                linkedinStatusSaveResults($db, $tenantId, $runId, []);
            } elseif (function_exists('saveLinkedInRunResults')) {
                saveLinkedInRunResults($db, $tenantId, $runId, []);
            }
            return true;
        } catch (Throwable $error) {
            error_log('linkedin empty result finalization: ' . $error->getMessage());
            return false;
        }
    }

    function linkedinRunFinalizeFallbackEmpty(PDO $db, array $run, int $tenantId, string $apifyResult, string $authenticatedResult): bool
    {
        if ($apifyResult === 'filtered') {
            if ($authenticatedResult !== 'empty') return false;
            $actor = 'harvestapi_full';
            $message = 'Nenhum perfil novo compatível foi encontrado para estes filtros.';
        } elseif ($apifyResult === 'empty') {
            $actor = 'harvestapi_full';
            $message = 'Nenhum perfil retornado pelas chaves elegíveis do actor.';
        } else {
            return false;
        }
        return linkedinRunFinalizeEmptySearch($db, $run, $tenantId, $actor, $message);
    }

    function linkedinRunFinalizeApifyFailure(PDO $db, array $run): void
    {
        $message = linkedinApifyContingencyPublicMessage();
        if ($message === '') return;
        try {
            $db->prepare(
                'UPDATE linkedin_runs
                 SET status = ?, apify_actor = ?, leads_found = 0, credits_charged = 0, error_message = ?, finished_at = NOW()
                 WHERE id = ?'
            )->execute(['failed', 'harvestapi_full', $message, (int) ($run['id'] ?? 0)]);
        } catch (Throwable $error) {
            error_log('linkedin apify limit finalization: ' . $error->getMessage());
        }
    }

    /**
     * Conclui uma pesquisa LinkedIn pelo coletor autenticado isolado.
     *
     * @return string completed, empty ou failed
     */
    function linkedinRunFallbackToMapsCollector(
        PDO $db,
        int $userId,
        array $run,
        int $tenantId,
        string $profile,
        string $internalMessage
    ): string {
        try {
            $collector = linkedinFallbackLoadCollectorClient();
            if ($collector === null) {
                return 'failed';
            }

            $jobTitles = json_decode((string) ($run['search_job_titles'] ?? ''), true);
            $jobTitle = '';
            if (is_array($jobTitles) && $jobTitles !== []) {
                $jobTitle = trim((string) $jobTitles[0]);
            }
            if ($jobTitle === '') {
                $jobTitle = trim((string) ($run['search_title'] ?? ''));
                if ($jobTitle !== '') {
                    $jobTitle = preg_replace('/^linkedin:\s*/iu', '', $jobTitle);
                }
            }
            $location = trim((string) ($run['search_location'] ?? ''));
            if ($jobTitle === '' || $location === '') {
                return 'failed';
            }

            // Normalização simples para o formato aceito pelo coletor.
            if (preg_match('/^(.+?)\s+(SP|MG|RJ|PR|SC|RS|BA|PE|CE|GO|PA|AM|ES|MT|MS|AL|RN|MA|PB|SE|TO|PI|DF)\s*$/i', $location, $m)) {
                $location = trim($m[1]) . ', ' . strtoupper($m[2]);
            }

            $limit = max(1, min(200, (int) ($run['limit_requested'] ?? 10)));
            $company = trim((string) ($run['search_company'] ?? ''));
            $collected = linkedinFallbackCollect($collector, $jobTitle, $location, $company, $limit);
            if ($collected === null) {
                return 'failed';
            }

            $leads = linkedinFallbackReadCsvLeads($collected['csv'], min($limit, 200));
            if (!$leads) {
                return 'empty';
            }

            $charged = 0;
            if (!($profile === 'super_admin' || $tenantId === 1) && function_exists('incrementTenantUsage')) {
                incrementTenantUsage($db, $tenantId, count($leads));
                $charged = count($leads);
            }

            $db->beginTransaction();
            try {
                $leadStmt = $db->prepare(
                    'INSERT INTO linkedin_leads
                       (tenant_id, run_id, profile_url, full_name, job_title, company_name, location, industry, photo_url, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), job_title = VALUES(job_title),
                       company_name = VALUES(company_name), location = VALUES(location), industry = VALUES(industry)'
                );
                foreach ($leads as $lead) {
                    $leadStmt->execute([
                        $tenantId,
                        (int) $run['id'],
                        $lead['profile_url'],
                        $lead['full_name'],
                        $lead['job_title'],
                        $lead['company_name'],
                        $lead['location'],
                        $lead['industry'],
                        $lead['photo_url'],
                    ]);
                }

                $db->prepare(
                    'UPDATE linkedin_runs
                     SET status = ?, apify_actor = ?, leads_found = ?, credits_charged = ?, finished_at = NOW()
                     WHERE id = ?'
                )->execute(['done', 'authenticated_session', count($leads), $charged, (int) $run['id']]);
                $db->commit();
            } catch (Throwable $transactionError) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $transactionError;
            }

            // Persistência e cache seguem o mesmo padrão do fluxo normal.
            try {
                if (function_exists('linkedinStatusSaveResults')) {
                    linkedinStatusSaveResults($db, $tenantId, (int) $run['id'], $leads);
                }
                $jobTitlesForCache = json_decode((string) ($run['search_job_titles'] ?? ''), true);
                if (function_exists('linkedinStatusWriteCache')) {
                    linkedinStatusWriteCache(
                        $db,
                        $tenantId,
                        (int) ($run['user_id'] ?? $userId),
                        (string) ($run['search_company'] ?? ''),
                        is_array($jobTitlesForCache) ? $jobTitlesForCache : [],
                        (string) ($run['search_location'] ?? ''),
                        $leads
                    );
                }
            } catch (Throwable $cacheError) {
                error_log('linkedin fallback cache (run ' . $run['id'] . '): ' . $cacheError->getMessage());
            }

            error_log('linkedin fallback (run ' . $run['id'] . '): concluído pelo coletor próprio com ' . count($leads) . ' perfis — ' . $internalMessage);
            return 'completed';
        } catch (Throwable $error) {
            error_log('linkedin fallback (run ' . $run['id'] . '): ' . $error->getMessage());
            return 'failed';
        }
    }

    /**
     * Converte e preserva o retorno completo do actor HarvestAPI Full. Os campos
     * legados continuam presentes para manter compatibilidade com o frontend e
     * com as tabelas existentes; os campos adicionais ficam em linkedin_run_results.
     *
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    function linkedinApifyNormalizeLeads(array $rows, int $maxLeads): array
    {
        $leads = [];
        $maxLeads = max(1, min(625, $maxLeads));
        foreach ($rows as $row) {
            if (!is_array($row) || count($leads) >= $maxLeads) {
                continue;
            }

            $profileUrl = trim((string) ($row['linkedinUrl'] ?? $row['profile_url'] ?? $row['profileUrl'] ?? ''));
            $publicIdentifier = trim((string) ($row['publicIdentifier'] ?? $row['public_identifier'] ?? ''));
            if ($profileUrl === '' && $publicIdentifier !== '') {
                $profileUrl = 'https://www.linkedin.com/in/' . rawurlencode($publicIdentifier) . '/';
            }
            $firstName = trim((string) ($row['firstName'] ?? $row['first_name'] ?? ''));
            $lastName = trim((string) ($row['lastName'] ?? $row['last_name'] ?? ''));
            $fullName = trim((string) ($row['full_name'] ?? $row['fullName'] ?? $row['name'] ?? trim($firstName . ' ' . $lastName)));
            if ($profileUrl === '' && $fullName === '') {
                continue;
            }

            $position = [];
            foreach (['currentPosition', 'currentPositions', 'positionsCurrent'] as $positionsKey) {
                if (!empty($row[$positionsKey]) && is_array($row[$positionsKey])) {
                    $position = is_array($row[$positionsKey][0] ?? null) ? $row[$positionsKey][0] : [];
                    if ($position) {
                        break;
                    }
                }
            }

            $location = $row['location'] ?? '';
            if (is_array($location)) {
                $location = $location['linkedinText'] ?? ($location['parsed']['text'] ?? ($location['text'] ?? ($location['city'] ?? '')));
            }
            $headline = trim((string) ($row['headline'] ?? $row['job_title'] ?? $position['position'] ?? $position['title'] ?? ''));
            $companyName = trim((string) ($row['company_name'] ?? $row['companyName'] ?? $position['companyName'] ?? $position['company'] ?? ''));
            $photoUrl = '';
            $picture = $row['profilePicture'] ?? $row['profile_picture'] ?? $row['photo'] ?? '';
            if (is_string($picture)) {
                $photoUrl = trim($picture);
            } elseif (is_array($picture)) {
                $photoUrl = trim((string) ($picture['url'] ?? ''));
                if ($photoUrl === '' && is_array($picture['sizes'] ?? null)) {
                    foreach ($picture['sizes'] as $size) {
                        if (is_array($size) && !empty($size['url'])) {
                            $photoUrl = trim((string) $size['url']);
                            break;
                        }
                    }
                }
            }

            $skills = [];
            if (is_array($row['skills'] ?? null)) {
                foreach ($row['skills'] as $skill) {
                    if (is_array($skill)) {
                        $skillName = trim((string) ($skill['name'] ?? $skill['title'] ?? ''));
                    } else {
                        $skillName = trim((string) $skill);
                    }
                    if ($skillName !== '') {
                        $skills[] = $skillName;
                    }
                }
            }
            if (!$skills && !empty($row['topSkills'])) {
                $skills = array_values(array_filter(array_map('trim', preg_split('/[•,;\\n]+/u', (string) $row['topSkills']) ?: [])));
            }

            $experience = [];
            $experienceRows = $row['experience'] ?? ($row['experiences'] ?? []);
            if (is_array($experienceRows)) {
                foreach ($experienceRows as $experienceRow) {
                    if (!is_array($experienceRow)) {
                        continue;
                    }
                    $experience[] = [
                        'position' => trim((string) ($experienceRow['position'] ?? $experienceRow['title'] ?? '')),
                        'companyName' => trim((string) ($experienceRow['companyName'] ?? $experienceRow['company'] ?? '')),
                        'companyLinkedinUrl' => trim((string) ($experienceRow['companyLinkedinUrl'] ?? '')),
                        'companyId' => trim((string) ($experienceRow['companyId'] ?? '')),
                        'companyUniversalName' => trim((string) ($experienceRow['companyUniversalName'] ?? '')),
                        'companyLogo' => is_array($experienceRow['companyLogo'] ?? null) ? $experienceRow['companyLogo'] : null,
                        'location' => trim((string) ($experienceRow['location'] ?? '')),
                        'dateRange' => $experienceRow['dateRange'] ?? null,
                        'duration' => trim((string) ($experienceRow['duration'] ?? '')),
                        'startDate' => is_array($experienceRow['startDate'] ?? null) ? $experienceRow['startDate'] : null,
                        'endDate' => is_array($experienceRow['endDate'] ?? null) ? $experienceRow['endDate'] : null,
                        'description' => trim((string) ($experienceRow['description'] ?? '')),
                        'skills' => is_array($experienceRow['skills'] ?? null) ? $experienceRow['skills'] : [],
                        'employmentType' => trim((string) ($experienceRow['employmentType'] ?? '')),
                        'workplaceType' => trim((string) ($experienceRow['workplaceType'] ?? '')),
                    ];
                }
            }

            $education = $row['education'] ?? ($row['profileTopEducation'] ?? []);
            if (!is_array($education)) {
                $education = [];
            }

            $leads[] = [
                'profile_url' => $profileUrl,
                'full_name' => $fullName,
                'first_name' => $firstName ?: null,
                'last_name' => $lastName ?: null,
                'public_identifier' => $publicIdentifier ?: null,
                'linkedin_id' => trim((string) ($row['id'] ?? $row['objectUrn'] ?? '')) ?: null,
                'job_title' => $headline ?: null,
                'headline' => $headline ?: null,
                'company_name' => $companyName ?: null,
                'location' => is_scalar($location) ? trim((string) $location) : null,
                'industry' => trim((string) ($row['industry'] ?? $row['industryName'] ?? '')) ?: null,
                'photo_url' => $photoUrl ?: null,
                'cover_photo_url' => trim((string) ($row['coverPicture']['url'] ?? '')) ?: null,
                'about' => trim((string) ($row['about'] ?? '')) ?: null,
                'skills' => $skills,
                'experience' => $experience,
                'education' => $education,
                'certifications' => is_array($row['certifications'] ?? null) ? $row['certifications'] : [],
                'languages' => is_array($row['languages'] ?? null) ? $row['languages'] : [],
                'projects' => is_array($row['projects'] ?? null) ? $row['projects'] : [],
                'connections_count' => isset($row['connectionsCount']) ? (int) $row['connectionsCount'] : null,
                'followers_count' => isset($row['followerCount']) ? (int) $row['followerCount'] : null,
                'open_to_work' => !empty($row['openToWork']),
                'hiring' => !empty($row['hiring']),
                'premium' => !empty($row['premium']),
                'verified' => !empty($row['verified']),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'website' => trim((string) ($row['website'] ?? '')),
                'source' => 'harvestapi_full',
                'raw_profile' => $row,
            ];
        }
        return $leads;
    }

    /**
     * Garante a tabela local de deduplicação e semeia perfis legados já gravados.
     * A tabela é isolada por tenant para impedir que uma empresa afete outra.
     */
    function linkedinEnsureSeenProfilesTable(PDO $db): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS linkedin_seen_profiles (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    tenant_id INT UNSIGNED NOT NULL,
                    profile_key CHAR(64) NOT NULL,
                    profile_url VARCHAR(512) NULL,
                    public_identifier VARCHAR(190) NULL,
                    first_seen_run_id INT UNSIGNED NULL,
                    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY uq_linkedin_seen_tenant_profile (tenant_id, profile_key),
                    KEY idx_linkedin_seen_tenant_date (tenant_id, last_seen_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $hasLegacyTable = (bool) $db->query("SHOW TABLES LIKE 'linkedin_leads'")->fetch();
            if ($hasLegacyTable) {
                $db->exec(
                    "INSERT IGNORE INTO linkedin_seen_profiles
                        (tenant_id, profile_key, profile_url, first_seen_at, last_seen_at)
                     SELECT tenant_id,
                            SHA2(CONCAT('url:', LOWER(TRIM(TRAILING '/' FROM profile_url))), 256),
                            MIN(profile_url),
                            COALESCE(MIN(created_at), NOW()),
                            MAX(created_at)
                     FROM linkedin_leads
                     WHERE profile_url IS NOT NULL AND TRIM(profile_url) <> ''
                     GROUP BY tenant_id, LOWER(TRIM(TRAILING '/' FROM profile_url))"
                );
            }
            $ready = true;
        } catch (Throwable $error) {
            error_log('linkedin dedup table: ' . $error->getMessage());
            $ready = false;
        }
        return $ready;
    }

    /** Retorna uma chave estável para URL ou identificador público do perfil. */
    function linkedinProfileDedupKey(array $lead): string
    {
        $identifier = trim((string) ($lead['public_identifier'] ?? ''));
        if ($identifier !== '') {
            return hash('sha256', 'identifier:' . mb_strtolower($identifier));
        }
        $profileUrl = trim((string) ($lead['profile_url'] ?? ''));
        if ($profileUrl !== '') {
            $profileUrl = preg_replace('/[?#].*$/', '', $profileUrl) ?: $profileUrl;
            $profileUrl = rtrim(mb_strtolower($profileUrl), '/');
            return hash('sha256', 'url:' . $profileUrl);
        }
        $fallback = implode('|', [
            mb_strtolower(trim((string) ($lead['full_name'] ?? ''))),
            mb_strtolower(trim((string) ($lead['company_name'] ?? ''))),
            mb_strtolower(trim((string) ($lead['location'] ?? ''))),
        ]);
        return $fallback !== '||' ? hash('sha256', 'fallback:' . $fallback) : '';
    }

    /**
     * Reserva os primeiros perfis ainda não vistos em uma transação.
     * INSERT IGNORE com índice único também evita duplicatas em pesquisas
     * concorrentes do mesmo tenant.
     *
     * @param array<int, array<string, mixed>> $candidates
     * @return array<int, array<string, mixed>>
     */
    function linkedinReserveUnseenLeads(PDO $db, int $tenantId, int $runId, array $candidates, int $maxLeads): array
    {
        if (!linkedinEnsureSeenProfilesTable($db)) {
            return array_slice($candidates, 0, max(1, $maxLeads));
        }
        $maxLeads = max(1, min(625, $maxLeads));
        $insert = $db->prepare(
            'INSERT IGNORE INTO linkedin_seen_profiles
                (tenant_id, profile_key, profile_url, public_identifier, first_seen_run_id)
             VALUES (?, ?, ?, ?, ?)'
        );
        $fresh = [];
        foreach ($candidates as $lead) {
            if (!is_array($lead) || count($fresh) >= $maxLeads) {
                continue;
            }
            $key = linkedinProfileDedupKey($lead);
            if ($key === '') {
                $fresh[] = $lead;
                continue;
            }
            $insert->execute([
                $tenantId,
                $key,
                trim((string) ($lead['profile_url'] ?? '')) ?: null,
                trim((string) ($lead['public_identifier'] ?? '')) ?: null,
                $runId,
            ]);
            if ($insert->rowCount() === 1) {
                $fresh[] = $lead;
            }
        }
        return $fresh;
    }

    /** @return string Chave rotativa existente, nunca exposta em respostas. */
    function linkedinApifyContingencyKey(PDO $db): string
    {
        if (function_exists('getRotatingApifyKey')) {
            try {
                return trim((string) getRotatingApifyKey($db));
            } catch (Throwable $error) {
                error_log('linkedin apify contingency key: ' . $error->getMessage());
            }
        }
        return '';
    }

    /** Lê o status do último run da conta sem registrar IDs ou credenciais. */
    function linkedinApifyLastRunStatusMessage(string $token): string
    {
        if ($token === '') {
            return '';
        }
        $request = static function (string $url) use ($token): array {
            $curl = curl_init($url);
            if ($curl === false) {
                return [];
            }
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Authorization: Bearer ' . $token,
                ],
            ]);
            $body = curl_exec($curl);
            curl_close($curl);
            $decoded = json_decode(is_string($body) ? $body : '', true);
            return is_array($decoded) ? $decoded : [];
        };
        $runs = $request('https://api.apify.com/v2/acts/harvestapi~linkedin-profile-search/runs?limit=1&desc=true');
        $runId = trim((string) ($runs['data']['items'][0]['id'] ?? ''));
        if ($runId === '') {
            return '';
        }
        $detail = $request('https://api.apify.com/v2/actor-runs/' . rawurlencode($runId));
        return trim((string) ($detail['data']['statusMessage'] ?? ''));
    }

    /** Retorna somente mensagens externas seguras e orientadas à ação. */
    function linkedinApifyContingencyPublicMessage(): string
    {
        return (string) ($GLOBALS['linkedin_apify_public_error'] ?? '');
    }

    /**
     * Consulta o actor HarvestAPI Full e conclui o mesmo run.
     * A cobrança acontece uma única vez, após a gravação dos leads retornados.
     */
    function linkedinRunApifyContingencySingle(
        PDO $db,
        int $userId,
        array $run,
        int $tenantId,
        string $profile,
        string $internalMessage
    ): string {
        try {
            $token = trim((string) ($run['apify_key_override'] ?? ''));
            if ($token === '') {
                $token = linkedinApifyContingencyKey($db);
            }
            if ($token === '') {
                error_log('linkedin apify contingency: chave indisponível para run ' . ($run['id'] ?? '?'));
                return 'failed';
            }
            $jobTitles = json_decode((string) ($run['search_job_titles'] ?? ''), true);
            $jobTitle = is_array($jobTitles) && $jobTitles !== [] ? trim((string) $jobTitles[0]) : '';
            if ($jobTitle === '') {
                $jobTitle = preg_replace('/^linkedin:\s*/iu', '', trim((string) ($run['search_title'] ?? ''))) ?: '';
            }
            $location = trim((string) ($run['search_location'] ?? ''));
            if ($jobTitle === '' || $location === '') {
                return 'failed';
            }
            $limit = max(1, min(625, (int) ($run['limit_requested'] ?? 10)));
            $onlyNew = !array_key_exists('only_new', $run) || (bool) $run['only_new'];
            // Sobredescarga moderada para compensar perfis já vistos; o teto
            // continua em 625 para manter o custo e o tempo previsíveis.
            $fetchLimit = $onlyNew
                ? min(625, max($limit, (int) ceil($limit * 2.5)))
                : $limit;
            $jobTitles = is_array($jobTitles) ? array_values(array_filter(array_map(static function ($value): string {
                return trim((string) $value);
            }, $jobTitles))) : [];
            // O filtro locations do actor usa o nome da localidade como aparece
            // no LinkedIn. O estado/país acrescentado ao texto brasileiro pode
            // ser interpretado como uma localidade diferente e gerar dataset
            // vazio mesmo quando existem perfis na cidade. Mantemos somente a
            // cidade/região informada pelo usuário.
            $actorLocation = trim($location);
            $actorLocation = preg_replace('/,\\s*[A-Z]{2}$/u', '', $actorLocation) ?: $actorLocation;
            $actorLocation = preg_replace('/,\\s*(Brasil|Brazil)$/iu', '', $actorLocation) ?: $actorLocation;
            $actorLocation = trim($actorLocation, " ,");
            $companyQuery = trim((string) ($run['search_company'] ?? ''));
            $titleQuery = implode(' ', array_slice($jobTitles, 0, 3));
            $textQuery = trim($companyQuery !== '' ? $companyQuery . ' ' . $titleQuery : $titleQuery);
            $payload = [
                'profileScraperMode' => 'Full',
                'startPage' => 1,
                'currentJobTitles' => array_slice($jobTitles, 0, 50),
                'locations' => [$actorLocation],
                'takePages' => min(100, max(1, (int) ceil($fetchLimit / 25))),
                'maxItems' => $fetchLimit,
                'autoQuerySegmentation' => false,
            ];
            if ($textQuery !== '') {
                $payload['searchQuery'] = $textQuery;
            }
            // DDL (CREATE TABLE) causa commit implícito em MySQL; garanta a
            // estrutura antes de iniciar a transação que grava os resultados.
            linkedinEnsureSeenProfilesTable($db);
            $url = 'https://api.apify.com/v2/acts/harvestapi~linkedin-profile-search/run-sync-get-dataset-items?timeout=300&clean=true&maxTotalChargeUsd=5.00';
            $curl = curl_init($url);
            if ($curl === false) {
                return 'failed';
            }
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_TIMEOUT => 315,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json',
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $token,
                ],
            ]);
            $body = curl_exec($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            $providerMessage = '';
            if (is_string($body) && $body !== '') {
                $decodedError = json_decode($body, true);
                if (is_array($decodedError)) {
                    $providerMessage = trim((string) ($decodedError['message'] ?? $decodedError['error'] ?? $decodedError['detail'] ?? ''));
                }
                if ($providerMessage === '' && strlen($body) < 400) {
                    $providerMessage = trim($body);
                }
            }
            if ($body === false || $httpCode < 200 || $httpCode >= 300) {
                error_log('linkedin apify contingency HTTP ' . $httpCode . ' ' . $curlError . ($providerMessage !== '' ? ' message=' . mb_substr($providerMessage, 0, 300) : ''));
                $providerStatus = linkedinApifyLastRunStatusMessage($token);
                if (preg_match('/free user run limit|run limit reached|quota|limit.*run/iu', $providerStatus)) {
                    $GLOBALS['linkedin_apify_public_error'] = 'O limite de pesquisas gratuitas da conta de integração foi atingido. O administrador precisa ativar um plano da Apify ou configurar outra conta para continuar as pesquisas LinkedIn.';
                }
                return 'failed';
            }
            $rows = json_decode((string) $body, true);
            if (is_array($rows) && isset($rows['data']) && is_array($rows['data'])) {
                $rows = $rows['data'];
            } elseif (is_array($rows) && isset($rows['items']) && is_array($rows['items'])) {
                $rows = $rows['items'];
            } elseif (is_array($rows) && (isset($rows['error']) || isset($rows['message']))) {
                error_log('linkedin apify contingency returned an error payload: ' . mb_substr((string) ($rows['message'] ?? $rows['error'] ?? ''), 0, 300));
                return 'failed';
            }
            $candidates = linkedinApifyNormalizeLeads(is_array($rows) ? $rows : [], $fetchLimit);
            if (!is_array($candidates) || !$candidates) {
                $validEmptyDataset = is_array($rows)
                    && ($rows === [] || array_keys($rows) === range(0, count($rows) - 1));
                $providerStatus = linkedinApifyLastRunStatusMessage($token);
                if (preg_match('/free user run limit|run limit reached|quota|limit.*run/iu', $providerStatus)) {
                    $GLOBALS['linkedin_apify_public_error'] = 'O limite de pesquisas gratuitas da conta de integração foi atingido. O administrador precisa ativar um plano da Apify ou configurar outra conta para continuar as pesquisas LinkedIn.';
                }
                return $validEmptyDataset ? 'empty' : 'failed';
            }

            $db->beginTransaction();
            try {
                $leads = $onlyNew
                    ? linkedinReserveUnseenLeads($db, $tenantId, (int) $run['id'], $candidates, $limit)
                    : array_slice($candidates, 0, $limit);
                if (!$leads) {
                    // No modo de novos leads, perfis já vistos liberam a próxima
                    // chave; não finaliza uma tentativa vazia prematuramente.
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    return 'filtered';
                }
                $leadStmt = $db->prepare(
                    'INSERT INTO linkedin_leads
                       (tenant_id, run_id, profile_url, full_name, job_title, company_name, location, industry, photo_url, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), job_title = VALUES(job_title),
                       company_name = VALUES(company_name), location = VALUES(location), industry = VALUES(industry)'
                );
                foreach ($leads as $lead) {
                    $leadStmt->execute([$tenantId, (int) $run['id'], $lead['profile_url'], $lead['full_name'], $lead['job_title'], $lead['company_name'], $lead['location'], $lead['industry'], $lead['photo_url']]);
                }
                $charged = 0;
                if (!($profile === 'super_admin' || $tenantId === 1) && function_exists('incrementTenantUsage')) {
                    incrementTenantUsage($db, $tenantId, count($leads));
                    $charged = count($leads);
                }
                $db->prepare(
                    'UPDATE linkedin_runs
                     SET status = ?, apify_actor = ?, leads_found = ?, credits_charged = ?, error_message = ?, finished_at = NOW()
                     WHERE id = ?'
                )->execute(['done', 'harvestapi_full', count($leads), $charged, $internalMessage, (int) $run['id']]);
                $db->commit();
            } catch (Throwable $transactionError) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $transactionError;
            }
            if (function_exists('linkedinStatusSaveResults')) {
                linkedinStatusSaveResults($db, $tenantId, (int) $run['id'], $leads);
            } elseif (function_exists('saveLinkedInRunResults')) {
                saveLinkedInRunResults($db, $tenantId, (int) $run['id'], $leads);
            }
            $cacheTitles = json_decode((string) ($run['search_job_titles'] ?? ''), true);
            if (function_exists('linkedinStatusWriteCache')) {
                linkedinStatusWriteCache($db, $tenantId, (int) ($run['user_id'] ?? $userId), (string) ($run['search_company'] ?? ''), is_array($cacheTitles) ? $cacheTitles : [], (string) ($run['search_location'] ?? ''), $leads);
            }
            error_log('linkedin HarvestAPI Full (run ' . $run['id'] . '): concluída — ' . $internalMessage);
            return 'completed';
        } catch (Throwable $error) {
            error_log('linkedin apify contingency (run ' . ($run['id'] ?? '?') . '): ' . $error->getMessage());
            return 'failed';
        }
    }

    /** Tenta as chaves Apify elegíveis em sequência. */
    function linkedinRunApifyContingency(
        PDO $db,
        int $userId,
        array $run,
        int $tenantId,
        string $profile,
        string $internalMessage
    ): string {
        $GLOBALS['linkedin_apify_public_error'] = '';
        $candidates = function_exists('getRotatingApifyKeyCandidates') ? getRotatingApifyKeyCandidates($db) : [];
        if (!$candidates) {
            $result = linkedinRunApifyContingencySingle($db, $userId, $run, $tenantId, $profile, $internalMessage);
            if (linkedinApifyContingencyPublicMessage() !== '') {
                linkedinRunFinalizeApifyFailure($db, $run);
                return 'failed';
            }
            return $result;
        }
        $hadEmptyResult = false;
        $hadFilteredResult = false;
        $hadProviderFailure = false;
        foreach ($candidates as $candidate) {
            $keyId = (int) ($candidate['id'] ?? 0);
            $token = trim((string) ($candidate['api_key'] ?? ''));
            if ($token === '') continue;
            if (function_exists('markRotatingApifyKeyUsed')) markRotatingApifyKeyUsed($db, $keyId);
            $attempt = $run;
            $attempt['apify_key_override'] = $token;
            $result = linkedinRunApifyContingencySingle($db, $userId, $attempt, $tenantId, $profile, $internalMessage . ' (chave elegível ' . $keyId . ')');
            if ($result === 'completed') return 'completed';
            if (linkedinApifyContingencyPublicMessage() !== '') {
                linkedinRunFinalizeApifyFailure($db, $run);
                return 'failed';
            }
            if ($result === 'empty') {
                $hadEmptyResult = true;
            } elseif ($result === 'filtered') {
                $hadFilteredResult = true;
            } else {
                $hadProviderFailure = true;
            }
            error_log('linkedin apify contingency: tentativa sem perfis ou com falha; avaliando a próxima chave elegível.');
        }
        if ($hadProviderFailure) return 'failed';
        if ($hadFilteredResult) return 'filtered';
        return $hadEmptyResult ? 'empty' : 'failed';
    }

}
