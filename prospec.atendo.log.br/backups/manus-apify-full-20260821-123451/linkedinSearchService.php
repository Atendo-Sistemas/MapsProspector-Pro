<?php
/**
 * Serviço de pesquisa de leads LinkedIn via Apify (v2 — input validado).
 * O token Apify vem da rotação de chaves (getRotatingApifyKey) e nunca
 * deve ser enviado ao navegador.
 *
 * Descobertas em produção (2026-08-12):
 *   1. O ator de enriquecimento (LinkedIn Profile Scraper,
 *      yZnhB5JewWf9xSmoM) aceita APENAS URLs de perfis individuais no
 *      input:
 *      { "urls": [{ "url": "..." }], "scrapeCompany": false,
 *        "findContacts": false, "findContacts.contactCompassToken": "",
 *        "profileScraperMode": "short" }
 *      scrapeCompany EXIGE booleano; profileScraperMode é obrigatório.
 *
 *   2. O ator de busca por nome (harvestapi~linkedin-profile-search-by-name)
 *      EXIGE profileScraperMode ("Short"|"Full"|"Full + email search") e usa:
 *      firstName, lastName (busca por pessoa) e filtros:
 *      currentJobTitles, locations, currentCompanies, maxItems, strictSearch.
 *      Campo "name" (antigo input do serviço) NÃO existe no schema —
 *      causava o erro "Field input.profileScraperMode is required".
 *
 *   3. Estratégia do fluxo: a busca por nome não aceita "empresa genérica"
 *      (exige nome próprio como query). Usamos os cargos (jobTitles) como
 *      query principal (firstName vazio não é permitido), com filtros de
 *      cargo atual e localidade para direcionar ao contexto da empresa.
 *      As URLs de perfis retornadas (linkedin.com/in/...) passam então
 *      para o ator de enriquecimento.
 */

class LinkedInSearchService
{
    /**
     * Ator que enriquece perfis LinkedIn individuais.
     * Requer: ActorID -> actorId na API v2 (yZnhB5JewWf9xSmoM).
     */
    private const PROFILE_SCRAPER_ACTOR_ID = 'yZnhB5JewWf9xSmoM';

    /**
     * Ator de busca de perfis LinkedIn POR FILTROS (sem cookies/login).
     * harvestapi~linkedin-profile-search — melhor retorno nos testes
     * reais (25 perfis/15s contra 5 perfis/36s do search-by-name):
     * aceita searchQuery, currentJobTitles, locations (inglês),
     * currentCompanies, startPage/takePages, maxItems.
     */
    private const SEARCH_BY_FILTERS_ACTOR_ID = 'harvestapi~linkedin-profile-search';

    private const API_BASE = 'https://api.apify.com/v2';
    private string $apiKey;
    private int $timeout;

    public function __construct(string $apiKey, int $timeout = 5)
    {
        $this->apiKey = trim($apiKey);
        $this->timeout = max(3, $timeout);

        if ($this->apiKey === '') {
            throw new InvalidArgumentException('Chave da API Apify não configurada.');
        }
    }

    /**
     * Usa diretamente o retorno do ator de filtros, que já contém perfil,
     * posição atual, foto e localidade. Não inicia outro ator por perfil.
     */
    public function searchByCompany(string $company, array $jobTitles, string $location, int $limit = 10): array
    {
        $limit = max(1, min(200, $limit));
        $started = $this->startSearchByCompany($company, $jobTitles, $location, $limit);
        $pages = max(1, (int) ceil($limit / 25));
        $status = $this->waitForRun($started['run_id'], min(80, 24 + ($pages * 7)));
        if ($status !== 'SUCCEEDED') {
            throw new RuntimeException('A busca LinkedIn ainda não foi concluída.');
        }

        return [
            'actor' => self::SEARCH_BY_FILTERS_ACTOR_ID,
            'run_id' => $started['run_id'],
            'dataset_id' => $started['dataset_id'],
            'leads' => $this->collectSearchResults($started['dataset_id'], $limit),
        ];
    }

    /**
     * Inicia a execução sem aguardar a conclusão. Isso permite que a camada
     * HTTP responda rapidamente mesmo quando o usuário solicita até 200 perfis.
     */
    public function startSearchByCompany(string $company, array $jobTitles, string $location, int $limit = 10): array
    {
        $limit = max(1, min(200, $limit));
        $endpoint = self::API_BASE . '/acts/' . rawurlencode(self::SEARCH_BY_FILTERS_ACTOR_ID) . '/runs';
        $response = $this->request('POST', $endpoint, $this->buildSearchInput($company, $jobTitles, $location, $limit));
        $data = $response['data'] ?? [];
        $runId = trim((string) ($data['id'] ?? ''));
        $datasetId = trim((string) ($data['defaultDatasetId'] ?? ''));

        if ($runId === '' || $datasetId === '') {
            throw new RuntimeException('A Apify não retornou os identificadores da execução LinkedIn.');
        }

        return [
            'actor' => self::SEARCH_BY_FILTERS_ACTOR_ID,
            'run_id' => $runId,
            'dataset_id' => $datasetId,
        ];
    }

    /** Retorna o estado atual de uma execução iniciada anteriormente. */
    public function getSearchRunStatus(string $runId): string
    {
        $endpoint = self::API_BASE . '/actor-runs/' . rawurlencode($runId);
        $response = $this->request('GET', $endpoint);
        return strtoupper(trim((string) (($response['data'] ?? [])['status'] ?? 'UNKNOWN')));
    }

    /** Lê e normaliza os perfis publicados no dataset de uma execução concluída. */
    public function collectSearchResults(string $datasetId, int $limit = 10): array
    {
        $limit = max(1, min(200, $limit));
        $leads = [];

        foreach ($this->getDatasetItems($datasetId) as $profile) {
            if (!is_array($profile)) {
                continue;
            }
            $lead = $this->normalizeLead($profile);
            if ($lead !== null && $lead['profile_url'] !== '') {
                $leads[$lead['profile_url']] = $lead;
            }
            if (count($leads) >= $limit) {
                break;
            }
        }

        return array_values($leads);
    }

    /**
     * Busca perfis LinkedIn com o ator de busca por filtros (harvestapi~
     * linkedin-profile-search), usando a empresa + cargos como query e
     * aplicando filtros de localidade (inglês) e cargo atual.
     */
    private function buildSearchInput(string $company, array $jobTitles, string $location, int $limit): array
    {
        // Query composta: cargos informados pela empresa-alvo. O ator faz
        // busca de texto livre sobre a base pública do LinkedIn.
        $queryParts = $jobTitles;
        if ($company !== '' && count($queryParts) < 3) {
            array_unshift($queryParts, $company);
        }
        $query = trim(implode(' ', array_slice($queryParts, 0, 4)));
        if ($query === '') {
            throw new InvalidArgumentException('Informe ao menos um cargo para a busca LinkedIn.');
        }

        $limit = max(1, min(200, $limit));
        $pages = max(1, (int) ceil($limit / 25));

        $input = [
            'profileScraperMode' => 'Short',
            'searchQuery' => $query,
            'maxItems' => $limit,
            'startPage' => 1,
            'takePages' => $pages,
        ];

        if ($jobTitles) {
            $input['currentJobTitles'] = $jobTitles;
        }
        // O filtro locations exige o nome da localidade em inglês para o
        // Brasil ("Brazil"); traduções comuns de pt-BR para manter volume.
        $locationFilter = $this->normalizeLocation($location);
        if ($locationFilter !== null) {
            $input['locations'] = [$locationFilter];
        }
        // currentCompanies exige a URL oficial da empresa no LinkedIn
        // (não uma página de busca); resolvê-la exigiria mais uma chamada.
        // Por custo/eficiência, a empresa entra na query de busca.

        return $input;
    }

    /**
     * Executa o ator de enriquecimento de perfis usando URLs de perfis.
     * O input EXIGE o formato validado com scrapeCompany (booleano),
     * profileScraperMode e findContacts.
     */
    public function search(array $urls, int $limit = 10): array
    {
        $limit = max(1, min(100, $limit));

        $actorUrls = [];
        foreach ($urls as $url) {
            $normalized = $this->normalizeUrl($url);
            if ($normalized !== null && !in_array($normalized, array_column($actorUrls, 'url'), true)) {
                $actorUrls[] = ['url' => $normalized];
            }
        }

        if (!$actorUrls) {
            throw new InvalidArgumentException('Não foi possível gerar URLs de perfis LinkedIn válidas.');
        }

        $run = $this->startProfileRun($actorUrls);
        $this->waitForRun($run['runId'], 6);
        $items = $this->getDatasetItems($run['datasetId']);
        // Dataset pode retornar itens como objeto com .data ou array direto.
        if (is_array($items) && isset($items['data']) && is_array($items['data'])) {
            $items = $items['data'];
        }

        $leads = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            try {
                $lead = $this->normalizeLead($item);
                if ($lead !== null && isset($lead['profile_url']) && $lead['profile_url'] !== '') {
                    $leads[$lead['profile_url']] = $lead;
                }
            } catch (Throwable $e) {
                // Um item malformado não deve abortar a pesquisa.
                error_log('linkedinSearchService: item malformado ignorado: ' . $e->getMessage());
            }
            if (count($leads) >= $limit) {
                break;
            }
        }

        return [
            'actor' => self::PROFILE_SCRAPER_ACTOR_ID,
            'run_id' => $run['runId'],
            'dataset_id' => $run['datasetId'],
            'leads' => array_values($leads),
        ];
    }

    private function startProfileRun(array $actorUrls): array
    {
        $endpoint = self::API_BASE . '/acts/' . rawurlencode(self::PROFILE_SCRAPER_ACTOR_ID) . '/runs';
        $response = $this->request('POST', $endpoint, [
            'urls' => $actorUrls,
            'scrapeCompany' => false,
            'findContacts' => false,
            'findContacts.contactCompassToken' => '',
            'profileScraperMode' => 'short',
        ]);
        $data = $response['data'] ?? [];
        $runId = $data['id'] ?? null;
        $datasetId = $data['defaultDatasetId'] ?? null;

        if (!$runId) {
            throw new RuntimeException('A Apify não retornou o ID da execução.');
        }

        return [
            'runId' => (string) $runId,
            'datasetId' => $datasetId ? (string) $datasetId : null,
        ];
    }

    private function waitForRun(string $runId, int $maxAttempts = 6): string
    {
        $endpoint = self::API_BASE . '/actor-runs/' . rawurlencode($runId);
        $lastStatus = 'READY';

        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            usleep(1000000);
            $response = $this->request('GET', $endpoint);
            $data = $response['data'] ?? [];
            $lastStatus = (string) ($data['status'] ?? 'UNKNOWN');

            if ($lastStatus === 'SUCCEEDED') {
                return $lastStatus;
            }
            if (in_array($lastStatus, ['FAILED', 'ABORTED', 'TIMED-OUT'], true)) {
                throw new RuntimeException('A execução do Apify terminou com status: ' . $lastStatus . '.');
            }
        }

        throw new RuntimeException('Tempo limite da pesquisa LinkedIn excedido. Status atual: ' . $lastStatus . '.');
    }

    private function getDatasetItems(string $datasetId): array
    {
        // O endpoint /items retorna o JSON cru (array de objetos). O método
        // request() decodifica o JSON como array, mas em alguns builds o
        // Apify pode devolver itens com estrutura diferente; garantimos
        // sempre um array de itens.
        $endpoint = self::API_BASE . '/datasets/' . rawurlencode($datasetId) . '/items?clean=1&format=json';
        $curl = curl_init($endpoint);
        if ($curl === false) {
            throw new RuntimeException('Não foi possível iniciar a comunicação com a Apify.');
        }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPGET => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $body = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $curlError !== '') {
            throw new RuntimeException('Erro de comunicação com a Apify: ' . ($curlError ?: 'resposta vazia') . '.');
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('Apify respondeu HTTP ' . $httpCode . ' ao ler o dataset.');
        }

        $decoded = json_decode($body, true);
        // /items pode retornar um array direto ou um objeto de paginação.
        if (is_array($decoded)) {
            // Objetos de paginação trazem chave 'data' com os itens.
            if (array_is_list($decoded)) {
                return $decoded;
            }
            if (isset($decoded['data']) && is_array($decoded['data'])) {
                return $decoded['data'];
            }
            if (isset($decoded['items']) && is_array($decoded['items'])) {
                return $decoded['items'];
            }
            return [];
        }
        return [];
    }

    private function request(string $method, string $endpoint, ?array $payload = null): array
    {
        $curl = curl_init($endpoint);
        if ($curl === false) {
            throw new RuntimeException('Não foi possível iniciar a comunicação com a Apify.');
        }

        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_FOLLOWLOCATION => true,
        ];

        if ($payload !== null) {
            $options[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }

        curl_setopt_array($curl, $options);
        $body = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body === false || $curlError !== '') {
            throw new RuntimeException('Erro de comunicação com a Apify: ' . ($curlError ?: 'resposta vazia') . '.');
        }

        $decoded = json_decode($body, true);
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = is_array($decoded)
                ? ($decoded['error']['message'] ?? $decoded['message'] ?? '')
                : '';
            throw new RuntimeException('Apify respondeu HTTP ' . $httpCode . ($message ? ': ' . $message : '.'));
        }

        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da Apify.');
        }

        return $decoded;
    }

    private function normalizeLead(array $item): ?array
    {
        // inputUrl é a URL enviada na pesquisa; publicIdentifier permite
        // reconstruir a URL do perfil quando não há campo de URL.
        $profileUrl = $this->firstString($item, [
            'inputUrl', 'profileUrl', 'profile_url', 'linkedinUrl',
            'linkedin_url', 'url', 'link',
        ]);
        if (!$profileUrl && isset($item['publicIdentifier']) && $item['publicIdentifier'] !== '') {
            $profileUrl = 'https://www.linkedin.com/in/' . rawurlencode((string) $item['publicIdentifier']) . '/';
        }

        if (!$profileUrl || stripos($profileUrl, 'linkedin.com') === false) {
            return null;
        }

        $company = $this->firstString($item, [
            'companyName', 'company_name', 'company'
        ]);
        // companyCurrentTitle / jobTitle descrevem a empresa atual quando
        // currentCompany veio nulo/vazio.
        if (!$company) {
            $company = $this->firstString($item, ['companyCurrentTitle']);
        }
        // Experiências atuais (positions/positionsCurrent etc.).
        if (!$company) {
            foreach (['positions', 'experiences', 'experience', 'positionsCurrent', 'experienceCurrent'] as $positionsKey) {
                if (isset($item[$positionsKey]) && is_array($item[$positionsKey])) {
                    foreach ($item[$positionsKey] as $exp) {
                        if (!is_array($exp)) {
                            continue;
                        }
                        $company = $this->firstString($exp, ['companyName', 'company', 'name', 'title']);
                        if ($company) {
                            break 2;
                        }
                    }
                }
            }
        }
        // currentCompany pode ser um array de objetos no output do ator
        // search-by-name (empresa atual do perfil).
        if (!$company && isset($item['currentCompany']) && is_array($item['currentCompany'])) {
            foreach ($item['currentCompany'] as $exp) {
                if (is_array($exp)) {
                    $company = $this->firstString($exp, ['title', 'name', 'companyName', 'company']);
                    if ($company) {
                        break;
                    }
                }
            }
        }

        // O ator harvestapi~linkedin-profile-search retorna a posição atual
        // em currentPositions. Usa a posição marcada como atual ou a primeira
        // disponível para preencher empresa e cargo no painel.
        $currentPosition = null;
        if (isset($item['currentPositions']) && is_array($item['currentPositions'])) {
            foreach ($item['currentPositions'] as $position) {
                if (!is_array($position)) {
                    continue;
                }
                $currentPosition = $position;
                if (!empty($position['current'])) {
                    break;
                }
            }
        }
        if (!$company && is_array($currentPosition)) {
            $company = $this->firstString($currentPosition, ['companyName', 'company', 'name']);
        }

        $firstName = $this->firstString($item, ['firstName', 'first_name']);
        $lastName = $this->firstString($item, ['lastName', 'last_name']);
        $fullName = $firstName !== null && $lastName !== null
            ? trim($firstName . ' ' . $lastName)
            : $this->firstString($item, ['fullName', 'full_name', 'name']);

        $jobTitle = $this->firstString($item, ['jobTitle', 'job_title', 'title', 'headline']);
        if (!$jobTitle && is_array($currentPosition)) {
            $jobTitle = $this->firstString($currentPosition, ['title', 'jobTitle', 'job_title']);
        }

        $location = $this->firstString($item, ['locationName', 'city', 'geoLocationName']);
        if (!$location && isset($item['location']) && is_array($item['location'])) {
            $location = $this->firstString($item['location'], ['linkedinText', 'name', 'text', 'city']);
        }
        if (!$location && is_string($item['location'] ?? null)) {
            $location = trim((string) $item['location']);
        }

        return [
            'profile_url' => $this->normalizeUrl($profileUrl),
            'full_name' => $fullName,
            'job_title' => $jobTitle,
            'company_name' => $company,
            'location' => $location,
            'industry' => $this->firstString($item, ['industry', 'industryName']),
            'photo_url' => $this->firstString($item, ['pictureUrl', 'photoUrl', 'photo_url', 'profilePicture', 'picture']),
        ];
    }

    /**
     * Normaliza localidade para o formato esperado pelo filtro locations
     * do ator (nome em inglês). Retorna null quando não há equivalência.
     */
    private function normalizeLocation(string $location): ?string
    {
        $loc = trim($location);
        if ($loc === '') {
            return null;
        }
        $map = [
            'brasil' => 'Brazil',
            'brazil' => 'Brazil',
            'br' => 'Brazil',
            'são paulo' => 'São Paulo, Brazil',
            'sao paulo' => 'São Paulo, Brazil',
            'sp' => 'São Paulo, Brazil',
            'rio de janeiro' => 'Rio de Janeiro, Brazil',
            'rj' => 'Rio de Janeiro, Brazil',
            'belo horizonte' => 'Belo Horizonte, Brazil',
            'bh' => 'Belo Horizonte, Brazil',
            'curitiba' => 'Curitiba, Brazil',
            'porto alegre' => 'Porto Alegre, Brazil',
            'salvador' => 'Salvador, Brazil',
            'recife' => 'Recife, Brazil',
            'fortaleza' => 'Fortaleza, Brazil',
            'brasília' => 'Brasília, Brazil',
            'brasilia' => 'Brasília, Brazil',
            'manaus' => 'Manaus, Brazil',
        ];
        $key = mb_strtolower($loc);
        if (isset($map[$key])) {
            return $map[$key];
        }
        // Localidades não mapeadas (estrangeiras ou cidades menores): usar
        // o texto informado pelo usuário como está.
        return $loc;
    }

    private function firstString(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $value = trim((string) $data[$key]);
                if ($value !== '') {
                    return $value;
                }
            }
        }
        return null;
    }

    private function normalizeUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('~^https?://~i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }
        $parts = parse_url($url);
        if (!$parts || empty($parts['host']) || stripos($parts['host'], 'linkedin.com') === false) {
            return null;
        }
        return rtrim($url, '/');
    }
}
