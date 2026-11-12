<?php
declare(strict_types=1);

/**
 * Orquestrador de pesquisas LinkedIn com sessão autenticada temporária como
 * fonte primária. Apify permanece disponível somente como contingência
 * controlada quando a sessão estiver ausente, expirada ou o coletor falhar.
 *
 * A pesquisa é concluída pelo coletor LinkedIn self-hosted da plataforma,
 * que localiza perfis públicos em bases de busca da mesma região informada.
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
     * @return bool true quando o run foi concluído com perfis autenticados
     */
    function linkedinRunAuthenticatedSearch(
        PDO $db,
        int $userId,
        array $run,
        int $tenantId,
        string $profile,
        string $internalMessage = 'coletor autenticado'
    ): bool {
        return linkedinRunFallbackToMapsCollector($db, $userId, $run, $tenantId, $profile, $internalMessage);
    }

    /**
     * Conclui uma pesquisa LinkedIn pelo coletor autenticado isolado.
     *
     * @return bool true quando o run ficou concluído com perfis da fonte alternativa
     */
    function linkedinRunFallbackToMapsCollector(
        PDO $db,
        int $userId,
        array $run,
        int $tenantId,
        string $profile,
        string $internalMessage
    ): bool {
        try {
            $collector = linkedinFallbackLoadCollectorClient();
            if ($collector === null) {
                return false;
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
                return false;
            }

            // Normalização simples para o formato aceito pelo coletor.
            if (preg_match('/^(.+?)\s+(SP|MG|RJ|PR|SC|RS|BA|PE|CE|GO|PA|AM|ES|MT|MS|AL|RN|MA|PB|SE|TO|PI|DF)\s*$/i', $location, $m)) {
                $location = trim($m[1]) . ', ' . strtoupper($m[2]);
            }

            $limit = max(1, min(200, (int) ($run['limit_requested'] ?? 10)));
            $company = trim((string) ($run['search_company'] ?? ''));
            $collected = linkedinFallbackCollect($collector, $jobTitle, $location, $company, $limit);
            if ($collected === null) {
                return false;
            }

            $leads = linkedinFallbackReadCsvLeads($collected['csv'], min($limit, 200));
            if (!$leads) {
                return false;
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
            return true;
        } catch (Throwable $error) {
            error_log('linkedin fallback (run ' . $run['id'] . '): ' . $error->getMessage());
            return false;
        }
    }

    /**
     * Normaliza o retorno do ator Apify para o contrato interno de leads.
     * Esta função não é uma fonte normal: é chamada apenas após indisponibilidade
     * da sessão autenticada ou falha operacional do coletor privado.
     *
     * @param array<int, mixed> $rows
     * @return array<int, array<string, mixed>>
     */
    function linkedinApifyNormalizeLeads(array $rows, int $maxLeads): array
    {
        $leads = [];
        foreach ($rows as $row) {
            if (!is_array($row) || count($leads) >= $maxLeads) {
                continue;
            }
            $profileUrl = trim((string) ($row['linkedinUrl'] ?? $row['profile_url'] ?? ''));
            $firstName = trim((string) ($row['firstName'] ?? ''));
            $lastName = trim((string) ($row['lastName'] ?? ''));
            $fullName = trim((string) ($row['full_name'] ?? $row['name'] ?? trim($firstName . ' ' . $lastName)));
            if ($profileUrl === '' && $fullName === '') {
                continue;
            }
            $position = $row['currentPosition'][0] ?? [];
            $location = $row['location']['linkedinText'] ?? $row['location'] ?? '';
            $leads[] = [
                'profile_url' => $profileUrl,
                'full_name' => $fullName,
                'job_title' => trim((string) ($row['headline'] ?? $row['job_title'] ?? $position['position'] ?? '')) ?: null,
                'company_name' => trim((string) ($row['company_name'] ?? $position['companyName'] ?? '')) ?: null,
                'location' => is_string($location) ? trim($location) : null,
                'industry' => trim((string) ($row['industry'] ?? '')) ?: null,
                'photo_url' => trim((string) ($row['profilePicture'] ?? $row['profile_picture'] ?? '')) ?: null,
                'phone' => '',
                'email' => trim((string) ($row['email'] ?? '')),
                'website' => '',
            ];
        }
        return $leads;
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

    /**
     * Consulta o ator Apify somente como contingência e conclui o mesmo run.
     * A cobrança acontece uma única vez, após a gravação dos leads retornados.
     */
    function linkedinRunApifyContingency(
        PDO $db,
        int $userId,
        array $run,
        int $tenantId,
        string $profile,
        string $internalMessage
    ): bool {
        try {
            $token = linkedinApifyContingencyKey($db);
            if ($token === '') {
                error_log('linkedin apify contingency: chave indisponível para run ' . ($run['id'] ?? '?'));
                return false;
            }
            $jobTitles = json_decode((string) ($run['search_job_titles'] ?? ''), true);
            $jobTitle = is_array($jobTitles) && $jobTitles !== [] ? trim((string) $jobTitles[0]) : '';
            if ($jobTitle === '') {
                $jobTitle = preg_replace('/^linkedin:\s*/iu', '', trim((string) ($run['search_title'] ?? ''))) ?: '';
            }
            $location = trim((string) ($run['search_location'] ?? ''));
            if ($jobTitle === '' || $location === '') {
                return false;
            }
            $limit = max(1, min(200, (int) ($run['limit_requested'] ?? 10)));
            $payload = [
                'profileScraperMode' => 'Short',
                'searchQuery' => trim((string) ($run['search_company'] ?? '') . ' ' . $jobTitle . ' ' . $location),
                'takePages' => max(1, (int) ceil($limit / 25)),
                'maxItems' => $limit,
            ];
            $url = 'https://api.apify.com/v2/acts/harvestapi~linkedin-profile-search/run-sync-get-dataset-items?token='
                . rawurlencode($token) . '&timeout=120&clean=true';
            $curl = curl_init($url);
            if ($curl === false) {
                return false;
            }
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 130,
                CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
            ]);
            $body = curl_exec($curl);
            $httpCode = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $curlError = curl_error($curl);
            curl_close($curl);
            if ($body === false || $httpCode < 200 || $httpCode >= 300) {
                error_log('linkedin apify contingency HTTP ' . $httpCode . ' ' . $curlError);
                return false;
            }
            $rows = json_decode((string) $body, true);
            $leads = linkedinApifyNormalizeLeads(is_array($rows) ? $rows : [], $limit);
            if (!$leads) {
                return false;
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
                )->execute(['done', 'apify_fallback', count($leads), $charged, $internalMessage, (int) $run['id']]);
                $db->commit();
            } catch (Throwable $transactionError) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                throw $transactionError;
            }
            if (function_exists('linkedinStatusSaveResults')) {
                linkedinStatusSaveResults($db, $tenantId, (int) $run['id'], $leads);
            }
            $cacheTitles = json_decode((string) ($run['search_job_titles'] ?? ''), true);
            if (function_exists('linkedinStatusWriteCache')) {
                linkedinStatusWriteCache($db, $tenantId, (int) ($run['user_id'] ?? $userId), (string) ($run['search_company'] ?? ''), is_array($cacheTitles) ? $cacheTitles : [], (string) ($run['search_location'] ?? ''), $leads);
            }
            error_log('linkedin apify contingency (run ' . $run['id'] . '): concluída — ' . $internalMessage);
            return true;
        } catch (Throwable $error) {
            error_log('linkedin apify contingency (run ' . ($run['id'] ?? '?') . '): ' . $error->getMessage());
            return false;
        }
    }
}
