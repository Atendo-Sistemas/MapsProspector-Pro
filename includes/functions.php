<?php
/**
 * Funções Auxiliares
 * MapsProspector Pro
 */

/**
 * Retorna resposta JSON padronizada
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Retorna resposta de erro
 */
function jsonError($message, $statusCode = 400, $code = null) {
    $response = ['success' => false, 'error' => $message];
    if ($code !== null) {
        $response['code'] = $code;
    }
    jsonResponse($response, $statusCode);
}

/**
 * Retorna resposta de sucesso
 */
function jsonSuccess($data = null, $message = null) {
    $response = ['success' => true];
    if ($message !== null) {
        $response['message'] = $message;
    }
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response);
}

/**
 * Valida e sanitiza entrada
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Valida email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Verifica se usuário está autenticado e retorna o ID
 */
function requireAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (empty($_SESSION['user_id'])) {
        jsonError('Não autenticado.', 401);
    }

    // Validação de Sessão Única (Bloqueio de Múltiplos Logins)
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare("SELECT current_session_token FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $dbSessionToken = $stmt->fetchColumn();

    if ($dbSessionToken && isset($_SESSION['session_token']) && $_SESSION['session_token'] !== $dbSessionToken) {
        // Sessão foi derrubada por outro login
        session_destroy();
        jsonError('Sua conta foi acessada em outro dispositivo. Faça login novamente.', 401);
    }

    // O campo é criado por migração idempotente. A atividade é atualizada
    // somente após a sessão ser validada, sem alterar o token de sessão.
    try {
        $touch = $db->prepare('UPDATE users SET last_activity = NOW() WHERE id = ?');
        $touch->execute([(int) $_SESSION['user_id']]);
    } catch (Throwable $error) {
        error_log('auth activity touch: ' . $error->getMessage());
    }

    return $_SESSION['user_id'];
}

/**
 * Retorna dados do usuário autenticado (id, tenant_id, profile)
 */
function getAuthUser() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => (int) $_SESSION['user_id'],
        'tenant_id' => isset($_SESSION['tenant_id']) ? (int) $_SESSION['tenant_id'] : null,
        'profile' => $_SESSION['profile'] ?? 'user',
    ];
}

function requireSearchPermission(PDO $db): void {
    $userId = requireAuth();
    if (strtolower((string) ($_SESSION['profile'] ?? '')) === 'super_admin') {
        return;
    }

    try {
        $stmt = $db->prepare('SELECT can_search FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $userId]);
        $canSearch = $stmt->fetchColumn();
    } catch (Throwable $error) {
        error_log('Search permission check failed: ' . $error->getMessage());
        jsonError('A permissão de pesquisa não está disponível. Atualize o banco de dados.', 503, 'search_permission_unavailable');
    }

    if ($canSearch === false || !(bool) $canSearch) {
        jsonError('Seu administrador desativou a permissão para realizar pesquisas.', 403, 'search_permission_denied');
    }
}

/**
 * Exige que o usuário seja super_admin (administrador da plataforma)
 */
function requireSuperAdmin() {
    $userId = requireAuth();
    $profile = $_SESSION['profile'] ?? '';
    if ($profile !== 'super_admin') {
        jsonError('Acesso negado. Apenas o administrador da plataforma pode realizar esta ação.', 403);
    }
    return $userId;
}

/**
 * Obtém valor de configuração global da plataforma (ex: scraper_api_key).
 * Retorna null se a tabela não existir ou a chave não estiver definida.
 *
 * @param PDO $db Conexão com o banco
 * @param string $key Nome da chave (ex: 'scraper_api_key')
 * @return string|null
 */
function getPlatformSetting($db, $key) {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM platform_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : null;
    } catch (PDOException $e) {
        error_log("getPlatformSetting: " . $e->getMessage());
        return null;
    }
}

/**
 * Garante que a tabela platform_settings existe (cria se não existir).
 *
 * @param PDO $db Conexão com o banco
 */
function ensurePlatformSettingsTable($db) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS platform_settings (
                setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
                setting_value TEXT DEFAULT NULL,
                updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) {
        error_log("ensurePlatformSettingsTable: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Define valor de configuração global da plataforma.
 * Apenas super_admin deve chamar para chaves sensíveis (ex: scraper_api_key).
 * Cria a tabela platform_settings automaticamente se não existir.
 *
 * @param PDO $db Conexão com o banco
 * @param string $key Nome da chave
 * @param string|null $value Valor a salvar
 */
function setPlatformSetting($db, $key, $value) {
    try {
        $stmt = $db->prepare("INSERT INTO platform_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
        $stmt->execute([$key, $value, $value]);
    } catch (PDOException $e) {
        error_log("setPlatformSetting: " . $e->getMessage());
        // Tabela pode não existir: cria e tenta novamente
        ensurePlatformSettingsTable($db);
        try {
            $stmt = $db->prepare("INSERT INTO platform_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()");
            $stmt->execute([$key, $value, $value]);
        } catch (PDOException $e2) {
            error_log("setPlatformSetting retry: " . $e2->getMessage());
            throw $e2;
        }
    }
}

/**
 * Valida URL do webhook/CRM para evitar SSRF (apenas https, sem IPs privados/metadados).
 * Retorna a URL normalizada se válida; caso contrário retorna null.
 *
 * @param string $url URL informada pelo usuário
 * @return string|null URL válida ou null
 */
function validateWebhookUrl($url) {
    $url = trim($url);
    if ($url === '') {
        return null;
    }
    $parsed = @parse_url($url);
    if ($parsed === false || !isset($parsed['scheme'], $parsed['host'])) {
        return null;
    }
    $scheme = strtolower($parsed['scheme']);
    if ($scheme !== 'https' && $scheme !== 'http') {
        return null;
    }
    $host = $parsed['host'];
    if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host)) {
        $ip = $host;
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }
        if (strpos($ip, '169.254.') === 0 || $ip === '0.0.0.0') {
            return null;
        }
    } else {
        $resolved = @gethostbynamel($host);
        if ($resolved) {
            foreach ($resolved as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                    return null;
                }
                if (strpos($ip, '169.254.') === 0) {
                    return null;
                }
            }
        }
    }
    return rtrim($url, '/');
}

/**
 * Formata número de telefone brasileiro
 */
function formatPhoneNumber($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) < 8) return '';
    
    // Adiciona DDI 55 se necessário
    if (strlen($digits) >= 10 && substr($digits, 0, 2) !== '55') {
        return '55' . $digits;
    }
    return $digits;
}

/**
 * Criptografa o valor do apikey para armazenamento no banco (AES-256-CBC)
 */
function encryptApikey($plain) {
    if ($plain === null || $plain === '') return '';
    if (!defined('ENCRYPTION_KEY')) {
        throw new RuntimeException('Chave de criptografia não configurada.');
    }
    $key = ENCRYPTION_KEY;
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) return '';
    return base64_encode($iv . $encrypted);
}

/**
 * Descriptografa o apikey armazenado no banco.
 * Se o valor não for base64 válido ou falhar o decrypt, retorna o valor original (compatibilidade com tokens antigos em texto puro).
 */
function decryptApikey($encrypted) {
    if ($encrypted === null || $encrypted === '') return '';
    if (!defined('ENCRYPTION_KEY')) {
        throw new RuntimeException('Chave de criptografia não configurada.');
    }
    $key = ENCRYPTION_KEY;
    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) < 17) {
        return trim($encrypted); // valor antigo em texto puro
    }
    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);
    $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? $decrypted : trim($encrypted);
}

/**
 * Chave para criptografia de dados de leads (desbloqueio). Usa ENCRYPTION_KEY ou derivada.
 */
function getLeadEncryptionKey() {
    if (defined('ENCRYPTION_KEY') && strlen(ENCRYPTION_KEY) >= 32) {
        return substr(hash('sha256', ENCRYPTION_KEY, true), 0, 32);
    }
    return hash('sha256', 'MapsProspector-Pro-Lead-Encrypt-2025', true);
}

/**
 * Criptografa o payload sensível de um lead (telefone, email, endereço, etc.) para exibição apenas após desbloqueio.
 * Retorna string base64 (IV + ciphertext). Usado para enviar dados bloqueados ao frontend.
 *
 * @param array $payload Array associativo com phone, email, address, website, cnpj, partners, mapsUri, latitude, longitude
 * @return string
 */
function encryptLeadPayload(array $payload) {
    $key = getLeadEncryptionKey();
    $plain = json_encode($payload, JSON_UNESCAPED_UNICODE);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) return '';
    return base64_encode($iv . $encrypted);
}

/**
 * Descriptografa o payload de um lead (apenas no processo de liberação/desbloqueio).
 *
 * @param string $encrypted String base64 retornada por encryptLeadPayload
 * @return array|null Array com phone, email, address, etc. ou null se falhar
 */
function decryptLeadPayload($encrypted) {
    if ($encrypted === null || $encrypted === '') return null;
    $key = getLeadEncryptionKey();
    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) < 17) return null;
    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);
    $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($decrypted === false) return null;
    $data = json_decode($decrypted, true);
    return is_array($data) ? $data : null;
}

/**
 * Monta o array de contato para envio ao webhook a partir de um lead (linha do banco).
 * Retorna null se o lead não tiver telefone válido.
 */
function buildContactDataFromLead(array $lead) {
    $phone = formatPhoneNumber($lead['phone'] ?? '');
    if (empty($phone)) {
        return null;
    }
    $contactData = [
        'name' => $lead['name'],
        'number' => $phone,
        'email' => $lead['email'] ?? '',
        'tag' => $lead['tag'] ?? 'prospect_maps'
    ];
    $extraInfo = [];
    if (!empty($lead['address'])) {
        $extraInfo[] = ['name' => 'Endereço', 'value' => $lead['address']];
    }
    if (!empty($lead['cnpj'])) {
        $extraInfo[] = ['name' => 'CNPJ', 'value' => $lead['cnpj']];
    }
    if (!empty($lead['partners'])) {
        $extraInfo[] = ['name' => 'Sócios', 'value' => $lead['partners']];
    }
    if (!empty($lead['maps_uri'])) {
        $extraInfo[] = ['name' => 'Maps', 'value' => $lead['maps_uri']];
    }
    if (!empty($lead['website'])) {
        $extraInfo[] = ['name' => 'Site', 'value' => $lead['website']];
    }
    if (!empty($extraInfo)) {
        $contactData['extraInfo'] = $extraInfo;
    }
    $contactData['commentary'] = 'Lead capturado via Maps. Endereço: ' . ($lead['address'] ?? '');
    $contactData = deepClean($contactData);
    unset($contactData['ticketId'], $contactData['contactId'], $contactData['id'], $contactData['messageId']);
    return $contactData;
}

/**
 * Limpa dados profundamente (remove null, empty, etc)
 */
function deepClean($data) {
    if (is_array($data)) {
        $cleaned = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') continue;
            
            if (is_array($value)) {
                $cleanedArr = deepClean($value);
                if (!empty($cleanedArr)) {
                    $cleaned[$key] = $cleanedArr;
                }
            } elseif (is_object($value)) {
                $cleanedObj = deepClean((array)$value);
                if (!empty($cleanedObj)) {
                    $cleaned[$key] = $cleanedObj;
                }
            } else {
                $cleaned[$key] = $value;
            }
        }
        return $cleaned;
    }
    return $data;
}

/**
 * Retorna o limite de tokens do plano vinculado ao tenant.
 * Se a tabela plans não existir ou o tenant não tiver plano, retorna 0 (sem limite).
 *
 * @param PDO $db Conexão com o banco
 * @param int $tenantId ID do tenant (empresa)
 * @return int Limite de tokens do plano (0 = ilimitado ou tabela inexistente)
 */
function getTenantPlanTokenLimit($db, $tenantId) {
    try {
        $stmt = $db->prepare("
            SELECT p.token_limit
            FROM tenants t
            INNER JOIN plans p ON p.id = t.plan_id AND p.status = 'active'
            WHERE t.id = ?
        ");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();
        if ($row && isset($row['token_limit'])) {
            return (int) $row['token_limit'];
        }
        return 0;
    } catch (PDOException $e) {
        error_log("getTenantPlanTokenLimit: " . $e->getMessage());
        return 0;
    }
}

/**
 * Retorna a data de início do período atual (mensal: primeiro dia do mês).
 *
 * @param string $period 'monthly' ou 'yearly'
 * @return string Data no formato Y-m-d
 */
function getCurrentPeriodStart($period = 'monthly') {
    if ($period === 'yearly') {
        return date('Y-01-01');
    }
    return date('Y-m-01');
}

/**
 * Retorna a quantidade de tokens já consumidos pelo tenant no período atual.
 *
 * @param PDO $db Conexão com o banco
 * @param int $tenantId ID do tenant
 * @param string|null $periodStart Data início do período (Y-m-d); null = período atual
 * @return int Tokens usados
 */
function getTenantTokensUsed($db, $tenantId, $periodStart = null) {
    try {
        if ($periodStart === null) {
            $stmt = $db->prepare("SELECT p.period FROM tenants t INNER JOIN plans p ON p.id = t.plan_id WHERE t.id = ?");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch();
            $period = $row['period'] ?? 'monthly';
            $periodStart = getCurrentPeriodStart($period);
        }
        $stmt = $db->prepare("SELECT tokens_used FROM tenant_usage WHERE tenant_id = ? AND period_start = ?");
        $stmt->execute([$tenantId, $periodStart]);
        $row = $stmt->fetch();
        return $row ? (int) $row['tokens_used'] : 0;
    } catch (PDOException $e) {
        error_log("getTenantTokensUsed: " . $e->getMessage());
        return 0;
    }
}

/**
 * Incrementa o uso de tokens do tenant no período atual (ex.: 1 por busca).
 *
 * @param PDO $db Conexão com o banco
 * @param int $tenantId ID do tenant
 * @param int $tokens Quantidade de tokens a somar (default 1)
 */
function incrementTenantUsage($db, $tenantId, $tokens = 1) {
    try {
        $stmt = $db->prepare("SELECT p.period FROM tenants t INNER JOIN plans p ON p.id = t.plan_id WHERE t.id = ?");
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();
        $period = $row['period'] ?? 'monthly';
        $periodStart = getCurrentPeriodStart($period);

        $stmt = $db->prepare("
            INSERT INTO tenant_usage (tenant_id, period_start, tokens_used, token_bonus)
            VALUES (?, ?, ?, 0)
            ON DUPLICATE KEY UPDATE tokens_used = tokens_used + ?
        ");
        $stmt->execute([$tenantId, $periodStart, $tokens, $tokens]);

        // A indisponibilidade do canal de mensagens não pode impedir a busca
        // nem a atualização do consumo de créditos do tenant.
        try {
            $planLimit = getTenantPlanTokenLimit($db, $tenantId);
            $bonus = getTenantTokenBonus($db, $tenantId, $periodStart);
            $totalTokens = $planLimit + $bonus;

            if ($totalTokens > 0 && file_exists(__DIR__ . '/../services/notificationService.php')) {
                require_once __DIR__ . '/../services/notificationService.php';
                $contact = NotificationService::getTenantNotificationContact($db, (int) $tenantId);

                if (is_array($contact) && !empty($contact['phone'])) {
                    $usedTokens = getTenantTokensUsed($db, $tenantId, $periodStart);
                    // Em transação, reserva o evento já persistido antes do
                    // commit. O cURL real sempre ocorre no shutdown, depois
                    // de a resposta HTTP ser entregue ao navegador.
                    if ($db->inTransaction()) {
                        NotificationService::notifyLowBalance(
                            $db,
                            (int) $tenantId,
                            (string) $contact['phone'],
                            (string) ($contact['company_name'] ?? 'sua empresa'),
                            $usedTokens,
                            $totalTokens,
                            $periodStart
                        );
                    }

                    NotificationService::deferLowBalanceDispatch(
                        $db,
                        (int) $tenantId,
                        (string) $contact['phone'],
                        (string) ($contact['company_name'] ?? 'sua empresa'),
                        $usedTokens,
                        $totalTokens,
                        $periodStart
                    );
                }
            }
        } catch (Throwable $notificationError) {
            error_log('incrementTenantUsage: alerta de saldo baixo não enviado.');
        }
    } catch (PDOException $e) {
        error_log("incrementTenantUsage: " . $e->getMessage());
    }
}

/**
 * Retorna o bônus de tokens aprovados para o tenant no período (créditos extras).
 *
 * @param PDO $db Conexão com o banco
 * @param int $tenantId ID do tenant
 * @param string|null $periodStart Data início do período (Y-m-d); null = período atual
 * @return int Tokens de bônus
 */
function getTenantTokenBonus($db, $tenantId, $periodStart = null) {
    try {
        if ($periodStart === null) {
            $stmt = $db->prepare("SELECT p.period FROM tenants t INNER JOIN plans p ON p.id = t.plan_id WHERE t.id = ?");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch();
            $period = $row['period'] ?? 'monthly';
            $periodStart = getCurrentPeriodStart($period);
        }
        $stmt = $db->prepare("SELECT COALESCE(token_bonus, 0) as token_bonus FROM tenant_usage WHERE tenant_id = ? AND period_start = ?");
        $stmt->execute([$tenantId, $periodStart]);
        $row = $stmt->fetch();
        return $row ? (int) $row['token_bonus'] : 0;
    } catch (PDOException $e) {
        error_log("getTenantTokenBonus: " . $e->getMessage());
        return 0;
    }
}
/**
 * Retorna a próxima chave da Apify ativa utilizando rotação automática (Round-Robin / Menor uso)
 */
function getRotatingApifyKey($db) {
    try {
        // Primeiro tenta uma conta de produção saudável ou uma reserva já promovida.
        $sql = "
            SELECT
                k.id,
                k.api_key,
                COALESCE(p.role, 'production') AS key_role,
                COALESCE(p.state, 'active') AS policy_state,
                au.status AS usage_status,
                au.checked_at AS usage_checked_at
            FROM apify_keys k
            LEFT JOIN apify_key_policies p
                ON p.provider_key_id = k.id
            LEFT JOIN apify_account_usage au
                ON au.id = (
                    SELECT latest.id
                    FROM apify_account_usage latest
                    WHERE latest.provider_key_id = k.id
                    ORDER BY latest.checked_at DESC
                    LIMIT 1
                )
            WHERE k.is_active = 1
              AND (
                    COALESCE(p.role, 'production') = 'production'
                    OR (p.role = 'reserve' AND p.state = 'active')
              )
              AND COALESCE(p.state, 'active') NOT IN ('cooldown', 'disabled')
              AND COALESCE(au.status, 'unknown') NOT IN ('critical', 'limit_reached', 'error')
            ORDER BY
                CASE WHEN COALESCE(p.role, 'production') = 'production' THEN 0 ELSE 1 END,
                k.last_used_at ASC,
                k.id ASC
            LIMIT 1
        ";

        $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

        // Se a produção estiver indisponível, promove a reserva standby de menor prioridade.
        if (!$row) {
            $reserveSql = "
                SELECT
                    k.id,
                    k.api_key
                FROM apify_keys k
                INNER JOIN apify_key_policies p
                    ON p.provider_key_id = k.id
                LEFT JOIN apify_account_usage au
                    ON au.id = (
                        SELECT latest.id
                        FROM apify_account_usage latest
                        WHERE latest.provider_key_id = k.id
                        ORDER BY latest.checked_at DESC
                        LIMIT 1
                    )
                WHERE k.is_active = 1
                  AND p.role = 'reserve'
                  AND p.state = 'standby'
                  AND COALESCE(au.status, 'unknown') NOT IN ('critical', 'limit_reached', 'error')
                ORDER BY
                    p.priority ASC,
                    k.last_used_at ASC,
                    k.id ASC
                LIMIT 1
            ";

            $row = $db->query($reserveSql)->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $promote = $db->prepare(" 
                    UPDATE apify_key_policies
                    SET
                        state = 'active',
                        promoted_at = NOW(),
                        last_transition_at = NOW(),
                        transition_reason = 'Failover automático: nenhuma conta de produção disponível'
                    WHERE provider_key_id = ?
                      AND role = 'reserve'
                      AND state = 'standby'
                ");
                $promote->execute([(int) $row['id']]);
            }
        }

        if ($row && !empty($row['api_key'])) {
            $update = $db->prepare(
                'UPDATE apify_keys
                 SET tokens_used = tokens_used + 1,
                     last_used_at = NOW()
                 WHERE id = ?'
            );
            $update->execute([(int) $row['id']]);

            return trim((string) $row['api_key']);
        }
    } catch (Throwable $e) {
        // Não exponha detalhes do banco ou qualquer token ao usuário final.
        error_log('Erro na rotação de chaves de pesquisa: ' . $e->getMessage());
    }

    // Compatibilidade com a chave padrão antiga, caso nenhuma conta esteja disponível.
    return getPlatformSetting($db, 'scraper_api_key')
        ?: (defined('SCRAPER_API_KEY') ? SCRAPER_API_KEY : '');
}


/**
 * Retorna as chaves Apify elegíveis em ordem de rotação, sem expor os valores.
 * A lista é usada somente para tentar a próxima conta quando a anterior
 * retornar erro ou zero perfis.
 *
 * @return array<int, array{id:int,api_key:string}>
 */
function getRotatingApifyKeyCandidates($db): array
{
    try {
        $sql = "
            SELECT
                k.id,
                k.api_key,
                COALESCE(p.role, 'production') AS key_role
            FROM apify_keys k
            LEFT JOIN apify_key_policies p
                ON p.provider_key_id = k.id
            LEFT JOIN apify_account_usage au
                ON au.id = (
                    SELECT latest.id
                    FROM apify_account_usage latest
                    WHERE latest.provider_key_id = k.id
                    ORDER BY latest.checked_at DESC
                    LIMIT 1
                )
            WHERE k.is_active = 1
              AND (
                    COALESCE(p.role, 'production') = 'production'
                    OR (p.role = 'reserve' AND p.state = 'active')
              )
              AND COALESCE(p.state, 'active') NOT IN ('cooldown', 'disabled')
              AND COALESCE(au.status, 'unknown') NOT IN ('critical', 'limit_reached', 'error')
            ORDER BY
                CASE WHEN COALESCE(p.role, 'production') = 'production' THEN 0 ELSE 1 END,
                k.last_used_at ASC,
                k.id ASC
        ";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        // Se não houver produção saudável, usa as reservas standby em ordem de prioridade.
        if (!$rows) {
            $reserveSql = "
                SELECT k.id, k.api_key
                FROM apify_keys k
                INNER JOIN apify_key_policies p ON p.provider_key_id = k.id
                LEFT JOIN apify_account_usage au
                    ON au.id = (
                        SELECT latest.id
                        FROM apify_account_usage latest
                        WHERE latest.provider_key_id = k.id
                        ORDER BY latest.checked_at DESC
                        LIMIT 1
                    )
                WHERE k.is_active = 1
                  AND p.role = 'reserve'
                  AND p.state = 'standby'
                  AND COALESCE(au.status, 'unknown') NOT IN ('critical', 'limit_reached', 'error')
                ORDER BY p.priority ASC, k.last_used_at ASC, k.id ASC
            ";
            $rows = $db->query($reserveSql)->fetchAll(PDO::FETCH_ASSOC);
        }

        return array_values(array_filter(array_map(static function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'api_key' => trim((string) ($row['api_key'] ?? '')),
            ];
        }, $rows), static function (array $row): bool {
            return $row['id'] > 0 && $row['api_key'] !== '';
        }));
    } catch (Throwable $e) {
        error_log('Erro ao listar chaves elegíveis de pesquisa: ' . $e->getMessage());
        return [];
    }
}

/** Atualiza o contador interno quando uma tentativa usa uma chave específica. */
function markRotatingApifyKeyUsed($db, int $keyId): void
{
    if ($keyId < 1) {
        return;
    }
    try {
        $stmt = $db->prepare(
            'UPDATE apify_keys
             SET tokens_used = tokens_used + 1, last_used_at = NOW()
             WHERE id = ?'
        );
        $stmt->execute([$keyId]);
    } catch (Throwable $e) {
        error_log('Erro ao registrar uso de chave de pesquisa: ' . $e->getMessage());
    }
}
