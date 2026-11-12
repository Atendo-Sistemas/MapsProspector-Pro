<?php
/**
 * API de Histórico de Buscas (Roteamento Inteligente por Cargo - Dados Completos)
 * Endpoint: /api/history.php
 */

ini_set('display_errors', 0);
error_reporting(0);

// Previne qualquer output antes do JSON
ob_start();

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Erro Fatal no PHP: ' . $error['message'] . ' na linha ' . $error['line']
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
});

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';

    ob_clean();

    $userId = requireAuth();
    $authUser = getAuthUser();
    $db = Database::getInstance()->getConnection();

    $tenantId = $authUser['tenant_id'] ?? null;
    $profile = strtolower((string)($authUser['profile'] ?? ''));

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        $canSeeCompanyHistory = in_array($profile, ['admin', 'supervisor', 'manager', 'gerente'], true);
        $isSuperAdmin = ($profile === 'super_admin');

        $hasFolderId = false;
        try {
            $db->query("SELECT folder_id FROM search_history LIMIT 1");
            $hasFolderId = true;
        } catch (Exception $e) {}

        $folderSelect = $hasFolderId ? "sh.folder_id," : "NULL as folder_id,";
        $hasUserIp = false;
        try {
            $db->query("SELECT user_ip FROM search_history LIMIT 1");
            $hasUserIp = true;
        } catch (Exception $e) {}
        $userIpSelect = $hasUserIp ? 'sh.user_ip,' : 'NULL as user_ip,';

        if ($canSeeCompanyHistory && $tenantId !== null) {
            $stmt = $db->prepare("
                SELECT sh.id, sh.query, sh.location, sh.tag, {$folderSelect} sh.results_count, {$userIpSelect} sh.created_at as timestamp,
                       u.name as user_name, t.name as tenant_name
                FROM search_history sh
                INNER JOIN users u ON u.id = sh.user_id
                LEFT JOIN tenants t ON t.id = u.tenant_id
                WHERE u.tenant_id = ?
                ORDER BY sh.created_at DESC
                LIMIT 100
            ");
            $stmt->execute([$tenantId]);
        } elseif ($isSuperAdmin) {
            $stmt = $db->query("
                SELECT sh.id, sh.query, sh.location, sh.tag, {$folderSelect} sh.results_count, {$userIpSelect} sh.created_at as timestamp,
                       u.name as user_name, t.name as tenant_name
                FROM search_history sh
                INNER JOIN users u ON u.id = sh.user_id
                LEFT JOIN tenants t ON t.id = u.tenant_id
                ORDER BY sh.created_at DESC
                LIMIT 100
            ");
        } else {
            $stmt = $db->prepare("
                SELECT sh.id, sh.query, sh.location, sh.tag, {$folderSelect} sh.results_count, {$userIpSelect} sh.created_at as timestamp
                FROM search_history sh
                WHERE sh.user_id = ?
                ORDER BY sh.created_at DESC
                LIMIT 100
            ");
            $stmt->execute([$userId]);
        }

        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($history as &$item) {
            $searchHistoryId = (int) $item['id'];

            $stmtLeads = $db->prepare("
                SELECT id, name, address, phone, email, website, maps_uri, cnpj, partners, tag, latitude, longitude
                FROM leads
                WHERE search_history_id = ?
                ORDER BY id ASC
            ");
            $stmtLeads->execute([$searchHistoryId]);
            $rawLeads = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);

            $leads = [];
            foreach ($rawLeads as $row) {
                $leadDbId = (int) $row['id'];
                $mapsLink = $row['maps_uri'] ?? '';

                $leads[] = [
                    'id' => 'lead-' . $leadDbId,
                    'name' => $row['name'] ?? '',
                    'phone' => $row['phone'] ?? '',
                    'email' => $row['email'] ?? '',
                    'address' => $row['address'] ?? '',
                    'website' => $row['website'] ?? '',
                    'mapsUri' => $mapsLink,
                    'maps_uri' => $mapsLink,
                    'cnpj' => $row['cnpj'] ?? '',
                    'partners' => $row['partners'] ?? '',
                    'tag' => $row['tag'] ?? '',
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                    'locked' => false,
                    'dbId' => $leadDbId,
                ];
            }

            $item['leads'] = $leads;
            $tagUpper = strtoupper((string) ($item['tag'] ?? ''));
            $isCnpjHistory = in_array($tagUpper, ['CNPJ_AVANCADA', 'CNPJ_CACHE'], true)
                || stripos((string) ($item['query'] ?? ''), 'CNPJ:') === 0
                || stripos((string) ($item['query'] ?? ''), 'Consulta CNPJ:') === 0;
            $item['companyName'] = $isCnpjHistory && !empty($leads[0]['name']) ? (string) $leads[0]['name'] : '';
            if ($isCnpjHistory && $item['companyName'] === '') {
                $cnpjDigits = preg_replace('/\\D+/', '', (string) ($item['query'] ?? ''));
                if (strlen($cnpjDigits) >= 14) {
                    try {
                        $cacheStmt = $db->prepare('SELECT data_json FROM cnpj_cache WHERE cnpj = ? LIMIT 1');
                        $cacheStmt->execute([substr($cnpjDigits, 0, 14)]);
                        $cached = json_decode((string) ($cacheStmt->fetchColumn() ?: ''), true);
                        if (is_array($cached) && isset($cached[0]) && is_array($cached[0])) {
                            $item['companyName'] = (string) ($cached[0]['name'] ?? '');
                            $item['tradeName'] = (string) ($cached[0]['tradeName'] ?? '');
                            $item['cnpj'] = (string) ($cached[0]['cnpj'] ?? substr($cnpjDigits, 0, 14));
                        }
                    } catch (Throwable $cacheError) {
                        error_log('history.php CNPJ cache: ' . $cacheError->getMessage());
                    }
                }
            }
            $item['id'] = (string) $item['id'];
            $item['folderId'] = !empty($item['folder_id']) ? (string) $item['folder_id'] : null;
            $item['folderName'] = $item['folder_name'] ?? null;
            $item['source'] = $isCnpjHistory ? 'cnpj' : 'maps';
            $item['sourceLabel'] = $isCnpjHistory ? 'CNPJ' : 'Maps';
            $item['isLinkedIn'] = false;
            $item['userName'] = isset($item['user_name']) ? (string) $item['user_name'] : '';
            $item['tenantName'] = $isSuperAdmin && isset($item['tenant_name']) ? (string) $item['tenant_name'] : '';
            if (isset($item['user_name'])) {
                $item['location'] = $item['location'] . " (por " . $item['user_name'] . ")";
            }
        }

        // Unifica as pesquisas LinkedIn no mesmo histórico, mantendo o
        // mesmo escopo de visibilidade aplicado às pesquisas Maps.
        $linkedinWhere = [];
        $linkedinParams = [];
        $linkedinCanSeePeople = $isSuperAdmin || $canSeeCompanyHistory;
        if ($isSuperAdmin) {
            $linkedinWhere[] = '1 = 1';
        } elseif ($canSeeCompanyHistory && $tenantId !== null) {
            $linkedinWhere[] = 'lr.tenant_id = ?';
            $linkedinParams[] = (int) $tenantId;
        } else {
            $linkedinWhere[] = 'lr.user_id = ?';
            $linkedinParams[] = (int) $userId;
        }
        try {
            $linkedinTableExists = (bool) $db->query("SHOW TABLES LIKE 'linkedin_runs'")->fetch();
            if ($linkedinTableExists) {
                $linkedinStmt = $db->prepare(
                    'SELECT lr.id, lr.tenant_id, lr.user_id, lr.search_title, lr.search_location,
                            lr.search_company, lr.search_job_titles, lr.status, lr.leads_found,
                            lr.credits_charged, lr.created_at, lr.finished_at,
                            u.name AS user_name, t.name AS tenant_name
                       FROM linkedin_runs lr
                  LEFT JOIN users u ON u.id = lr.user_id
                  LEFT JOIN tenants t ON t.id = lr.tenant_id
                      WHERE ' . implode(' AND ', $linkedinWhere) . '
                   ORDER BY lr.created_at DESC, lr.id DESC
                      LIMIT 200'
                );
                $linkedinStmt->execute($linkedinParams);
                foreach ($linkedinStmt->fetchAll(PDO::FETCH_ASSOC) as $run) {
                    $jobTitles = json_decode((string) ($run['search_job_titles'] ?? ''), true);
                    if (!is_array($jobTitles)) {
                        $jobTitles = array_values(array_filter(array_map('trim', preg_split('/[,;\n]+/', (string) ($run['search_job_titles'] ?? '')) ?: [])));
                    }
                    $jobTitles = array_values(array_filter(array_map('strval', $jobTitles)));
                    $status = strtolower(trim((string) ($run['status'] ?? '')));
                    $query = trim((string) ($run['search_company'] ?? ''));
                    if ($query === '') {
                        $query = trim(implode(', ', $jobTitles));
                    }
                    if ($query === '') {
                        $query = trim((string) ($run['search_title'] ?? 'Pesquisa LinkedIn'));
                    }
                    $history[] = [
                        'id' => 'linkedin-' . (int) $run['id'],
                        'source' => 'linkedin',
                        'sourceLabel' => 'LinkedIn',
                        'isLinkedIn' => true,
                        'linkedinRunId' => (string) $run['id'],
                        'query' => $query,
                        'companyName' => (string) ($run['search_company'] ?? ''),
                        'jobTitles' => $jobTitles,
                        'location' => (string) ($run['search_location'] ?? ''),
                        'tag' => 'LinkedIn',
                        'folderId' => null,
                        'folderName' => null,
                        'resultsCount' => (int) ($run['leads_found'] ?? 0),
                        'creditsCharged' => (int) ($run['credits_charged'] ?? 0),
                        'timestamp' => (string) ($run['created_at'] ?? ''),
                        'finishedAt' => $run['finished_at'] !== null ? (string) $run['finished_at'] : null,
                        'linkedinStatus' => $status !== '' ? $status : 'unknown',
                        'leads' => [],
                        'userName' => $linkedinCanSeePeople ? (string) ($run['user_name'] ?? '') : '',
                        'tenantName' => $isSuperAdmin ? (string) ($run['tenant_name'] ?? '') : '',
                    ];
                }
            }
            usort($history, static function (array $left, array $right): int {
                $leftTime = strtotime((string) ($left['timestamp'] ?? '')) ?: 0;
                $rightTime = strtotime((string) ($right['timestamp'] ?? '')) ?: 0;
                return $rightTime <=> $leftTime;
            });
            $history = array_slice($history, 0, 100);
        } catch (Throwable $linkedinHistoryError) {
            // A tabela LinkedIn pode não existir em instalações antigas; o
            // histórico Maps continua funcionando normalmente nesse cenário.
            error_log('history.php LinkedIn merge: ' . $linkedinHistoryError->getMessage());
        }

        // Unifica as pesquisas Instagram no mesmo histórico, mantendo o
        // mesmo escopo de visibilidade aplicado às pesquisas Maps e LinkedIn.
        try {
            $instagramTableExists = (bool) $db->query("SHOW TABLES LIKE 'instagram_runs'")->fetch();
            if ($instagramTableExists) {
                $instagramWhere = [];
                $instagramParams = [];
                if ($isSuperAdmin) {
                    $instagramWhere[] = '1 = 1';
                } elseif ($canSeeCompanyHistory && $tenantId !== null) {
                    $instagramWhere[] = 'ir.tenant_id = ?';
                    $instagramParams[] = (int) $tenantId;
                } else {
                    $instagramWhere[] = 'ir.user_id = ?';
                    $instagramParams[] = (int) $userId;
                }
                $instagramStmt = $db->prepare(
                    'SELECT ir.id, ir.tenant_id, ir.user_id, ir.mode, ir.input_value,
                            ir.normalized_location, ir.status, ir.leads_json, ir.leads_found,
                            ir.comments_collected, ir.duplicate_count, ir.credits_charged,
                            ir.created_at, ir.finished_at,
                            u.name AS user_name, t.name AS tenant_name
                       FROM instagram_runs ir
                  LEFT JOIN users u ON u.id = ir.user_id
                  LEFT JOIN tenants t ON t.id = ir.tenant_id
                      WHERE ' . implode(' AND ', $instagramWhere) . '
                   ORDER BY ir.created_at DESC, ir.id DESC
                      LIMIT 200'
                );
                $instagramStmt->execute($instagramParams);
                foreach ($instagramStmt->fetchAll(PDO::FETCH_ASSOC) as $run) {
                    $storedLeads = json_decode((string) ($run['leads_json'] ?? ''), true);
                    $leads = [];
                    if (is_array($storedLeads)) {
                        foreach ($storedLeads as $lead) {
                            if (!is_array($lead)) continue;
                            $leads[] = [
                                'id' => 'instagram-lead-' . substr(sha1((string) ($lead['owner_id'] ?? $lead['username'] ?? $lead['profile_url'] ?? '')), 0, 16),
                                'name' => (string) ($lead['name'] ?? ''),
                                'username' => (string) ($lead['username'] ?? ''),
                                'ownerId' => (string) ($lead['owner_id'] ?? ''),
                                'profileUrl' => (string) ($lead['profile_url'] ?? ''),
                                'profile_url' => (string) ($lead['profile_url'] ?? ''),
                                'commentText' => (string) ($lead['comment_text'] ?? ''),
                                'comment_text' => (string) ($lead['comment_text'] ?? ''),
                                'commentedAt' => (string) ($lead['commented_at'] ?? ''),
                                'sourcePostUrl' => (string) ($lead['source_post_url'] ?? ''),
                                'source_post_url' => (string) ($lead['source_post_url'] ?? ''),
                                'interestLevel' => (string) ($lead['interest_level'] ?? 'low'),
                                'interest_level' => (string) ($lead['interest_level'] ?? 'low'),
                                'commentsCount' => (int) ($lead['comments_count'] ?? 1),
                            ];
                        }
                    }
                    $mode = strtolower((string) ($run['mode'] ?? 'post'));
                    $inputValue = (string) ($run['input_value'] ?? '');
                    $location = (string) ($run['normalized_location'] ?? '');
                    $history[] = [
                        'id' => 'instagram-' . (int) $run['id'],
                        'source' => 'instagram',
                        'sourceLabel' => 'Instagram',
                        'isInstagram' => true,
                        'instagramRunId' => (string) $run['id'],
                        'query' => $mode === 'location' ? $location : $inputValue,
                        'companyName' => '',
                        'location' => $mode === 'location' ? $location : 'Publicação do Instagram',
                        'tag' => $mode === 'location' ? 'Instagram · Local' : 'Instagram · Comentários',
                        'folderId' => null,
                        'folderName' => null,
                        'resultsCount' => (int) ($run['leads_found'] ?? 0),
                        'commentsCollected' => (int) ($run['comments_collected'] ?? 0),
                        'duplicateCount' => (int) ($run['duplicate_count'] ?? 0),
                        'creditsCharged' => (int) ($run['credits_charged'] ?? 0),
                        'timestamp' => (string) ($run['created_at'] ?? ''),
                        'finishedAt' => $run['finished_at'] !== null ? (string) $run['finished_at'] : null,
                        'instagramStatus' => strtolower((string) ($run['status'] ?? 'unknown')),
                        'leads' => $leads,
                        'userName' => ($isSuperAdmin || $canSeeCompanyHistory) ? (string) ($run['user_name'] ?? '') : '',
                        'tenantName' => $isSuperAdmin ? (string) ($run['tenant_name'] ?? '') : '',
                    ];
                }
            }
        } catch (Throwable $instagramHistoryError) {
            error_log('history.php Instagram merge: ' . $instagramHistoryError->getMessage());
        }
        jsonSuccess($history);
        exit;

    } elseif ($method === 'DELETE') {
        $canClearCompanyHistory = in_array($profile, ['admin', 'supervisor', 'manager', 'gerente'], true);
        $linkedinRunsStmt = null;
        $linkedinRunsTableExists = false;
        $instagramRunsStmt = null;
        $instagramRunsTableExists = false;
        try {
            $linkedinRunsTableExists = (bool) $db->query("SHOW TABLES LIKE 'linkedin_runs'")->fetch();
        } catch (Throwable $tableCheckError) {
            error_log('history.php LinkedIn table check: ' . $tableCheckError->getMessage());
        }
        try {
            $instagramRunsTableExists = (bool) $db->query("SHOW TABLES LIKE 'instagram_runs'")->fetch();
        } catch (Throwable $tableCheckError) {
            error_log('history.php Instagram table check: ' . $tableCheckError->getMessage());
        }
        $db->beginTransaction();
        try {
            if ($canClearCompanyHistory && $tenantId !== null) {
                $stmt = $db->prepare("
                    DELETE sh FROM search_history sh
                    INNER JOIN users u ON u.id = sh.user_id
                    WHERE u.tenant_id = ?
                ");
                $stmt->execute([$tenantId]);
                if ($linkedinRunsTableExists) {
                    $linkedinRunsStmt = $db->prepare('SELECT id FROM linkedin_runs WHERE tenant_id = ?');
                    $linkedinRunsStmt->execute([(int) $tenantId]);
                }
                if ($instagramRunsTableExists) {
                    $instagramRunsStmt = $db->prepare('SELECT id FROM instagram_runs WHERE tenant_id = ?');
                    $instagramRunsStmt->execute([(int) $tenantId]);
                }
            } elseif ($isSuperAdmin) {
                $db->exec('DELETE FROM search_history');
                if ($linkedinRunsTableExists) {
                    $linkedinRunsStmt = $db->query('SELECT id FROM linkedin_runs');
                }
                if ($instagramRunsTableExists) {
                    $instagramRunsStmt = $db->query('SELECT id FROM instagram_runs');
                }
            } else {
                $stmt = $db->prepare("DELETE FROM search_history WHERE user_id = ?");
                $stmt->execute([$userId]);
                if ($linkedinRunsTableExists) {
                    $linkedinRunsStmt = $db->prepare('SELECT id FROM linkedin_runs WHERE user_id = ?');
                    $linkedinRunsStmt->execute([(int) $userId]);
                }
                if ($instagramRunsTableExists) {
                    $instagramRunsStmt = $db->prepare('SELECT id FROM instagram_runs WHERE user_id = ?');
                    $instagramRunsStmt->execute([(int) $userId]);
                }
            }
            $linkedinRunIds = $linkedinRunsStmt instanceof PDOStatement
                ? array_values(array_filter(array_map('intval', $linkedinRunsStmt->fetchAll(PDO::FETCH_COLUMN))))
                : [];
            if ($linkedinRunIds) {
                $placeholders = implode(',', array_fill(0, count($linkedinRunIds), '?'));
                foreach (['linkedin_run_results', 'linkedin_leads'] as $childTable) {
                    try {
                        if ((bool) $db->query("SHOW TABLES LIKE '{$childTable}'")->fetch()) {
                            $childDelete = $db->prepare("DELETE FROM {$childTable} WHERE run_id IN ({$placeholders})");
                            $childDelete->execute($linkedinRunIds);
                        }
                    } catch (Throwable $childError) {
                        error_log('history.php LinkedIn clear ' . $childTable . ': ' . $childError->getMessage());
                    }
                }
                $runDelete = $db->prepare("DELETE FROM linkedin_runs WHERE id IN ({$placeholders})");
                $runDelete->execute($linkedinRunIds);
            }
            $instagramRunIds = $instagramRunsStmt instanceof PDOStatement
                ? array_values(array_filter(array_map('intval', $instagramRunsStmt->fetchAll(PDO::FETCH_COLUMN))))
                : [];
            if ($instagramRunIds) {
                $placeholders = implode(',', array_fill(0, count($instagramRunIds), '?'));
                if ((bool) $db->query("SHOW TABLES LIKE 'instagram_leads'")->fetch()) {
                    $leadDelete = $db->prepare("DELETE FROM instagram_leads WHERE run_id IN ({$placeholders})");
                    $leadDelete->execute($instagramRunIds);
                }
                $runDelete = $db->prepare("DELETE FROM instagram_runs WHERE id IN ({$placeholders})");
                $runDelete->execute($instagramRunIds);
            }
            $db->commit();
        } catch (Throwable $clearError) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $clearError;
        }
        jsonSuccess(null, 'Histórico limpo com sucesso');
        exit;

    } else {
        jsonError('Método não permitido', 405);
        exit;
    }

} catch (Throwable $e) {
    ob_clean();
    error_log("history.php error: " . $e->getMessage());
    jsonError('Erro no histórico: ' . $e->getMessage(), 500);
    exit;
}
