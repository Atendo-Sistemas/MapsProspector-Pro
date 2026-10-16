<?php
/**
 * API de Consulta de CNPJ Dinâmica com Cache Local e Fallback para API Externa
 * Endpoint: /api/cnpj-advanced-search.php
 */

header('Content-Type: application/json; charset=utf-8');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Garante autenticação
$userId = requireAuth();
$authUser = getAuthUser();
$tenantId = $authUser['tenant_id'] ?? null;
$db = Database::getInstance()->getConnection();
requireSearchPermission($db);

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    jsonError('Dados inválidos', 400);
}

$cnpjBusca = preg_replace('/[^0-9]/', '', $input['cnpj'] ?? '');

if (empty($cnpjBusca) || strlen($cnpjBusca) !== 14) {
    jsonError('CNPJ inválido. Digite os 14 números.', 400);
}

// 💡 1. CRIA A TABELA DE CACHE AUTOMATICAMENTE (Se não existir)
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS cnpj_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cnpj VARCHAR(14) NOT NULL UNIQUE,
            data_json LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_cnpj (cnpj)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (Exception $e) {}

try {
    $empresasEncontradas = [];
    $uf = '';
    $municipio = '';
    $fromCache = false;
    $revalidatedCache = false;
    $staleCachedData = [];
    $normalizationVersion = 'v67.0';
    $userIp = $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (strpos($userIp, ',') !== false) {
        $userIp = trim(explode(',', $userIp)[0]);
    }

    $asText = static function ($value): string {
        if (is_string($value) || is_numeric($value)) {
            return trim((string)$value);
        }
        if (is_array($value)) {
            foreach (['descricao', 'nome', 'texto', 'sigla', 'id'] as $key) {
                if (isset($value[$key]) && (is_string($value[$key]) || is_numeric($value[$key]))) {
                    return trim((string)$value[$key]);
                }
            }
        }
        return '';
    };

    $formatPhone = static function ($ddd, $number): string {
        $ddd = preg_replace('/\D+/', '', (string)$ddd);
        $number = preg_replace('/\D+/', '', (string)$number);
        if ($number === '') return '';
        return $ddd !== '' ? '(' . $ddd . ') ' . $number : $number;
    };

    $uniqueTexts = static function (array $values): array {
        $normalized = [];
        foreach ($values as $value) {
            $value = trim((string)$value);
            if ($value !== '') $normalized[$value] = $value;
        }
        return array_values($normalized);
    };

    $normalizePartners = static function ($rawPartners) use ($asText): array {
        $partners = [];
        foreach ((array) $rawPartners as $partner) {
            if (!is_array($partner)) continue;
            $name = $asText($partner['nome'] ?? $partner['nome_socio'] ?? $partner['name'] ?? '');
            if ($name === '') continue;
            $partners[] = [
                'name' => $name,
                'type' => $asText($partner['tipo'] ?? $partner['tipo_socio'] ?? $partner['identificador_de_socio'] ?? ''),
                'qualification' => $asText($partner['qualificacao_socio'] ?? $partner['qualificacao'] ?? ''),
                'entry_date' => $asText($partner['data_entrada_sociedade'] ?? $partner['data_entrada'] ?? ''),
                'country' => $asText($partner['pais'] ?? ''),
                'document' => $asText($partner['cnpj_cpf_do_socio'] ?? $partner['documento'] ?? ''),
                'age_range' => $asText($partner['faixa_etaria'] ?? ''),
                'representative_name' => $asText($partner['nome_representante_legal'] ?? ''),
                'representative_qualification' => $asText($partner['qualificacao_representante_legal'] ?? '')
            ];
        }
        return $partners;
    };
    $normalizeActivities = static function ($rawActivities, bool $principal = false) use ($asText): array {
        $activities = [];
        foreach ((array) $rawActivities as $activity) {
            if (!is_array($activity)) continue;
            $code = $asText($activity['id'] ?? $activity['codigo'] ?? $activity['cnae_fiscal'] ?? '');
            $description = $asText($activity['descricao'] ?? $activity['cnae_fiscal_descricao'] ?? '');
            if ($code === '' && $description === '') continue;
            $activities[] = ['code' => $code, 'description' => $description, 'principal' => $principal];
        }
        return $activities;
    };

    // 💡 2. BUSCA PRIMEIRO NO CACHE DO BANCO DE DADOS LOCAL
    $stmtCache = $db->prepare("SELECT data_json FROM cnpj_cache WHERE cnpj = ? LIMIT 1");
    $stmtCache->execute([$cnpjBusca]);
    $cachedRow = $stmtCache->fetch(PDO::FETCH_ASSOC);

    if ($cachedRow && !empty($cachedRow['data_json'])) {
        $decoded = json_decode($cachedRow['data_json'], true);
        // v65 já preserva os dados estruturados de sócios e atividades. Ele
        // continua válido para evitar nova cobrança; v66 apenas acrescenta
        // aliases e campos complementares na resposta.
        $cachedVersion = is_array($decoded) && isset($decoded[0]) && is_array($decoded[0])
            ? (string) ($decoded[0]['data_version'] ?? '')
            : '';
        if (is_array($decoded) && count($decoded) > 0 && in_array($cachedVersion, ['v65.0', 'v66.0', $normalizationVersion], true)) {
            $hasCompleteGroups = isset($decoded[0]['registration'], $decoded[0]['establishment_details'], $decoded[0]['tax_details']);
            if ($hasCompleteGroups) {
                $empresasEncontradas = array_map(static function (array $empresa): array {
                    if (!isset($empresa['partners']) || !is_array($empresa['partners'])) {
                        $empresa['partners'] = [];
                    }
                    if (!isset($empresa['qsa']) || !is_array($empresa['qsa'])) {
                        $empresa['qsa'] = $empresa['partners'];
                    }
                    if (!isset($empresa['activities']) || !is_array($empresa['activities'])) {
                        $empresa['activities'] = [];
                    }
                    if (!isset($empresa['secondary_activities']) || !is_array($empresa['secondary_activities'])) {
                        $empresa['secondary_activities'] = array_values(array_filter($empresa['activities'], static fn($activity) => empty($activity['principal'])));
                    }
                    return $empresa;
                }, $decoded);
                $fromCache = true;
            } else {
                // Reconsulta única para completar caches antigos sem debitar
                // novamente o cliente; o resultado atualizado substitui o cache.
                $staleCachedData = $decoded;
                $revalidatedCache = true;
            }
        }
    }

    // 💡 3. SE NÃO ACHOU NO BANCO LOCAL, CONSULTA NA API EXTERNA
    if (empty($empresasEncontradas)) {
        $customCnpjUrl = getPlatformSetting($db, 'cnpj_api_url');
        if (empty(trim($customCnpjUrl))) {
            $customCnpjUrl = 'https://publica.cnpj.ws/cnpj/{cnpj}';
        }

        if (strpos($customCnpjUrl, '{cnpj}') !== false) {
            $url = str_replace('{cnpj}', $cnpjBusca, $customCnpjUrl);
        } else {
            $url = rtrim($customCnpjUrl, '/') . '/' . $cnpjBusca;
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        
        $headers = [
            "X-Forwarded-For: " . $userIp,
            "Client-IP: " . $userIp
        ];

        $cnpjApiKey = getPlatformSetting($db, 'cnpj_api_key');
        if (!empty(trim($cnpjApiKey))) {
            $headers[] = "x-api-key: " . trim($cnpjApiKey);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if ($data) {
                if (isset($data['cnpj_raiz'])) {
                    // Formato CNPJ.ws
                    $estab = is_array($data['estabelecimento'] ?? null) ? $data['estabelecimento'] : [];
                    $state = is_array($estab['estado'] ?? null) ? $estab['estado'] : [];
                    $city = is_array($estab['cidade'] ?? null) ? $estab['cidade'] : [];

                    $phones = $uniqueTexts([
                        $formatPhone($estab['ddd1'] ?? '', $estab['telefone1'] ?? ''),
                        $formatPhone($estab['ddd2'] ?? '', $estab['telefone2'] ?? '')
                    ]);
                    $emails = $uniqueTexts([$estab['email'] ?? '', $data['email'] ?? '']);

                    $partners = $normalizePartners($data['socios'] ?? $data['qsa'] ?? $data['partners'] ?? []);
                    $activities = [];
                    $principal = is_array($estab['atividade_principal'] ?? null) ? $estab['atividade_principal'] : [];
                    if (!empty($principal)) {
                        $activities[] = [
                            'code' => $asText($principal['id'] ?? $principal['codigo'] ?? ''),
                            'description' => $asText($principal['descricao'] ?? ''),
                            'principal' => true
                        ];
                    }
                    foreach ($normalizeActivities($estab['atividades_secundarias'] ?? [], false) as $activity) {
                        $activities[] = $activity;
                    }
                    $secondaryActivities = array_values(array_filter($activities, static fn($activity) => empty($activity['principal'])));
                    $registration = [
                        'cnpj_root' => $asText($data['cnpj_raiz'] ?? ''),
                        'created_at' => $asText($data['criado_em'] ?? ''),
                        'updated_at' => $asText($data['atualizado_em'] ?? ''),
                        'federal_responsible' => $asText($data['responsavel_federativo'] ?? ''),
                        'responsible_qualification' => $asText($data['qualificacao_do_responsavel'] ?? '')
                    ];
                    $establishmentDetails = [
                        'branch_type' => $asText($estab['tipo'] ?? ''),
                        'branch_identifier' => $asText($estab['identificador_matriz_filial'] ?? ''),
                        'special_situation' => $asText($estab['situacao_especial'] ?? ''),
                        'special_situation_date' => $asText($estab['data_situacao_especial'] ?? ''),
                        'state_registrations' => is_array($estab['inscricoes_estaduais'] ?? null) ? $estab['inscricoes_estaduais'] : [],
                        'suframa' => is_array($estab['suframa'] ?? null) ? $estab['suframa'] : []
                    ];
                    $taxDetails = is_array($data['simples'] ?? null) ? $data['simples'] : [];

                    $uf = $asText($state['sigla'] ?? '');
                    $municipio = $asText($city['nome'] ?? '');
                    $address = trim(implode(', ', array_filter([
                        trim(($estab['tipo_logradouro'] ?? '') . ' ' . ($estab['logradouro'] ?? '') . ', ' . ($estab['numero'] ?? '')),
                        $estab['complemento'] ?? '',
                        $estab['bairro'] ?? '',
                        trim($municipio . ($uf !== '' ? '/' . $uf : '')),
                        !empty($estab['cep']) ? 'CEP ' . $estab['cep'] : ''
                    ])));

                    $empresasEncontradas[] = [
                        'data_version' => $normalizationVersion,
                        'cnpj' => $data['cnpj_completo'] ?? $cnpjBusca,
                        'name' => $data['razao_social'] ?? '',
                        'tradeName' => $estab['nome_fantasia'] ?? '',
                        'situation' => $asText($estab['situacao_cadastral'] ?? $data['situacao_cadastral'] ?? '') ?: 'Não informada',
                        'situation_date' => $asText($estab['data_situacao_cadastral'] ?? ''),
                        'situation_reason' => $asText($estab['motivo_situacao_cadastral'] ?? ''),
                        'cnae_codigo' => $activities[0]['code'] ?? '',
                        'cnae_descricao' => $activities[0]['description'] ?? '',
                        'activities' => $activities,
                        'secondary_activities' => $secondaryActivities,
                        'phones' => $phones,
                        'phone' => implode(' · ', $phones) ?: 'Não informado',
                        'emails' => $emails,
                        'email' => implode(' · ', $emails) ?: 'Não informado',
                        'address' => $address ?: 'Não informado',
                        'address_data' => [
                            'street' => trim(($estab['tipo_logradouro'] ?? '') . ' ' . ($estab['logradouro'] ?? '')),
                            'number' => $asText($estab['numero'] ?? ''),
                            'complement' => $asText($estab['complemento'] ?? ''),
                            'district' => $asText($estab['bairro'] ?? ''),
                            'city' => $municipio,
                            'state' => $uf,
                            'zip_code' => $asText($estab['cep'] ?? '')
                        ],
                        'capital' => $data['capital_social'] ?? 0,
                        'porte' => $asText($data['porte'] ?? ''),
                        'legal_nature' => $asText($data['natureza_juridica'] ?? ''),
                        'registration_type' => $asText($estab['tipo'] ?? ''),
                        'data_abertura' => $asText($estab['data_inicio_atividade'] ?? $data['data_inicio_atividade'] ?? ''),
                        'partners' => $partners,
                        'qsa' => $partners,
                        'socios' => implode(', ', array_column($partners, 'name')) ?: 'Não informado',
                        'simple' => $taxDetails,
                        'registration' => $registration,
                        'establishment_details' => $establishmentDetails,
                        'tax_details' => $taxDetails
                    ];
                } elseif (isset($data['cnpj'])) {
                    // Formato BrasilAPI / Minha Receita. Este formato usa qsa
                    // para o quadro societário e cnaes_secundarios para as
                    // atividades adicionais.
                    $uf = $asText($data['uf'] ?? '');
                    $municipio = $asText($data['municipio'] ?? '');
                    $partners = $normalizePartners($data['qsa'] ?? $data['socios'] ?? []);
                    $primaryActivity = $normalizeActivities([[
                        'codigo' => $data['cnae_fiscal'] ?? '',
                        'descricao' => $data['cnae_fiscal_descricao'] ?? ''
                    ]], true);
                    $secondaryActivities = $normalizeActivities($data['cnaes_secundarios'] ?? $data['atividades_secundarias'] ?? [], false);
                    $activities = array_merge($primaryActivity, $secondaryActivities);
                    $phones = $uniqueTexts([
                        $formatPhone('', $data['ddd_telefone_1'] ?? ''),
                        $formatPhone('', $data['ddd_telefone_2'] ?? '')
                    ]);
                    $emails = $uniqueTexts([$data['email'] ?? '']);
                    $addressData = [
                        'street' => $asText(($data['descricao_tipo_de_logradouro'] ?? '') . ' ' . ($data['logradouro'] ?? '')),
                        'number' => $asText($data['numero'] ?? ''),
                        'complement' => $asText($data['complemento'] ?? ''),
                        'district' => $asText($data['bairro'] ?? ''),
                        'city' => $municipio,
                        'state' => $uf,
                        'zip_code' => $asText($data['cep'] ?? '')
                    ];
                    $registration = [
                        'cnpj_root' => substr(preg_replace('/\D+/', '', (string) ($data['cnpj'] ?? '')), 0, 8),
                        'created_at' => '',
                        'updated_at' => '',
                        'federal_responsible' => $asText($data['ente_federativo_responsavel'] ?? ''),
                        'responsible_qualification' => $asText($data['qualificacao_do_responsavel'] ?? '')
                    ];
                    $establishmentDetails = [
                        'branch_type' => $asText($data['descricao_identificador_matriz_filial'] ?? ''),
                        'branch_identifier' => $asText($data['identificador_matriz_filial'] ?? ''),
                        'special_situation' => $asText($data['situacao_especial'] ?? ''),
                        'special_situation_date' => $asText($data['data_situacao_especial'] ?? ''),
                        'state_registrations' => [],
                        'suframa' => []
                    ];
                    $taxDetails = [
                        'opcao_pelo_simples' => $data['opcao_pelo_simples'] ?? null,
                        'opcao_pelo_mei' => $data['opcao_pelo_mei'] ?? null,
                        'regime_tributario' => is_array($data['regime_tributario'] ?? null) ? $data['regime_tributario'] : []
                    ];
                    $empresasEncontradas[] = [
                        'data_version' => $normalizationVersion,
                        'cnpj' => $data['cnpj'],
                        'name' => $data['razao_social'] ?? '',
                        'tradeName' => $data['nome_fantasia'] ?? '',
                        'situation' => $data['descricao_situacao_cadastral'] ?? $data['situacao_cadastral'] ?? 'Não informada',
                        'situation_date' => $asText($data['data_situacao_cadastral'] ?? ''),
                        'situation_reason' => $asText($data['descricao_motivo_situacao_cadastral'] ?? $data['motivo_situacao_cadastral'] ?? ''),
                        'cnae_codigo' => $primaryActivity[0]['code'] ?? '',
                        'cnae_descricao' => $primaryActivity[0]['description'] ?? '',
                        'activities' => $activities,
                        'secondary_activities' => $secondaryActivities,
                        'phones' => $phones,
                        'phone' => implode(' · ', $phones) ?: 'Não informado',
                        'emails' => $emails,
                        'email' => implode(' · ', $emails) ?: 'Não informado',
                        'address' => trim(implode(', ', array_filter([
                            trim(($addressData['street'] ?? '') . ', ' . ($addressData['number'] ?? '')),
                            $addressData['complement'] ?? '',
                            $addressData['district'] ?? '',
                            trim($municipio . ($uf !== '' ? '/' . $uf : '')),
                            $addressData['zip_code'] !== '' ? 'CEP ' . $addressData['zip_code'] : ''
                        ]))),
                        'address_data' => $addressData,
                        'capital' => $data['capital_social'] ?? 0,
                        'porte' => $asText($data['descricao_porte'] ?? $data['porte'] ?? ''),
                        'legal_nature' => $asText($data['natureza_juridica'] ?? ''),
                        'registration_type' => $asText($data['descricao_tipo_de_logradouro'] ?? ''),
                        'data_abertura' => $asText($data['data_inicio_atividade'] ?? ''),
                        'partners' => $partners,
                        'qsa' => $partners,
                        'socios' => implode(', ', array_column($partners, 'name')) ?: 'Não informado',
                        'simple' => $taxDetails,
                        'registration' => $registration,
                        'establishment_details' => $establishmentDetails,
                        'tax_details' => $taxDetails
                    ];
                }

                // 💡 4. SALVA O RESULTADO NO BANCO LOCAL PARA AS PRÓXIMAS CONSULTAS
                if (!empty($empresasEncontradas)) {
                    try {
                        $stmtSaveCache = $db->prepare("INSERT INTO cnpj_cache (cnpj, data_json) VALUES (?, ?) ON DUPLICATE KEY UPDATE data_json = VALUES(data_json)");
                        $stmtSaveCache->execute([$cnpjBusca, json_encode($empresasEncontradas)]);
                    } catch (Exception $eCache) {
                        error_log("Erro ao salvar cache de CNPJ: " . $eCache->getMessage());
                    }
                }
            }
        }
    }

    if (empty($empresasEncontradas) && !empty($staleCachedData)) {
        // A fonte externa pode falhar momentaneamente. Nesse caso, devolve o
        // último resultado conhecido em vez de transformar uma consulta válida
        // em uma tela vazia; a próxima consulta tentará atualizar novamente.
        $empresasEncontradas = array_map(static function (array $empresa): array {
            $empresa['partners'] = is_array($empresa['partners'] ?? null) ? $empresa['partners'] : [];
            $empresa['qsa'] = is_array($empresa['qsa'] ?? null) ? $empresa['qsa'] : $empresa['partners'];
            $empresa['activities'] = is_array($empresa['activities'] ?? null) ? $empresa['activities'] : [];
            $empresa['secondary_activities'] = is_array($empresa['secondary_activities'] ?? null)
                ? $empresa['secondary_activities']
                : array_values(array_filter($empresa['activities'], static fn($activity) => empty($activity['principal'])));
            return $empresa;
        }, $staleCachedData);
        $fromCache = true;
    }

    $validCount = count($empresasEncontradas);

    // 💡 5. GRAVA NO HISTÓRICO DE BUSCAS
    if ($validCount > 0) {
        try {
            $locais = [];
            if (!empty($uf)) { $locais[] = "UF: " . $uf; }
            if (!empty($municipio)) { $locais[] = "Cidade: " . $municipio; }
            $locationText = !empty($locais) ? implode(' | ', $locais) : 'Filtro Geral';
            $firstCompanyName = trim((string) ($empresasEncontradas[0]['name'] ?? ''));
            $historyQuery = 'Consulta CNPJ: ' . ($cnpjBusca !== '' ? $cnpjBusca : 'Busca avançada');
            if ($firstCompanyName !== '') $historyQuery .= ' — ' . $firstCompanyName;

            $db->beginTransaction();
            $stmtHist = $db->prepare(
                "INSERT INTO search_history (user_id, query, location, tag, results_count, user_ip, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmtHist->execute([$userId, $historyQuery, $locationText, 'CNPJ_AVANCADA', $validCount, $userIp]);
            $historyId = (int) $db->lastInsertId();
            if ($historyId < 1) throw new RuntimeException('Histórico CNPJ não retornou identificador válido.');

            $leadInsert = $db->prepare(
                'INSERT INTO leads (search_history_id, name, address, phone, email, website, maps_uri, cnpj, partners, tag, latitude, longitude)
                 VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, NULL, NULL)'
            );
            foreach ($empresasEncontradas as $empresa) {
                $leadName = trim((string) ($empresa['name'] ?? ''));
                if ($leadName === '') $leadName = trim((string) ($empresa['tradeName'] ?? '')) ?: 'Empresa sem razão social';
                $partnerText = (string) ($empresa['socios'] ?? '');
                $leadInsert->execute([
                    $historyId,
                    mb_substr($leadName, 0, 255),
                    mb_substr((string) ($empresa['address'] ?? ''), 0, 1000),
                    mb_substr((string) ($empresa['phone'] ?? ''), 0, 255),
                    mb_substr((string) ($empresa['email'] ?? ''), 0, 255),
                    preg_replace('/\\D+/', '', (string) ($empresa['cnpj'] ?? $cnpjBusca)),
                    mb_substr($partnerText, 0, 2000),
                    'CNPJ_AVANCADA'
                ]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            error_log("Erro ao salvar histórico/leads CNPJ: " . $e->getMessage());
        }
    }

    // 💡 6. DEBITA CRÉDITOS APENAS SE A BUSCA NÃO VEIO DO CACHE
    if ($tenantId !== null && $validCount > 0 && !$fromCache && !$revalidatedCache) {
        $creditsToDebit = $validCount * 5; 
        incrementTenantUsage($db, $tenantId, $creditsToDebit);
    }

    // Retorna saldo de tokens atualizado
    $tokenUsage = null;
    if ($tenantId !== null) {
        $planLimit = getTenantPlanTokenLimit($db, $tenantId);
        $bonus = getTenantTokenBonus($db, $tenantId, null);
        $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
        $used = getTenantTokensUsed($db, $tenantId, null);
        $tokenUsage = [
            'used' => $used,
            'limit' => $effectiveLimit,
            'limitReached' => $effectiveLimit > 0 && $used >= $effectiveLimit
        ];
    }

    echo json_encode([
        'success' => true,
        'data' => $empresasEncontradas,
        'fromCache' => $fromCache,
        'tokenUsage' => $tokenUsage
    ]);

} catch (Exception $e) {
    jsonError('Erro ao consultar a API de CNPJ configurada: ' . $e->getMessage(), 500);
}
