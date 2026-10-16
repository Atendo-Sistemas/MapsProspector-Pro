<?php
/**
 * Botão "Verificar novos leads" da aba LinkedIn.
 * Endpoint: GET /api/linkedin-new-leads.php
 *
 * Recarrega os leads da última pesquisa concluída e os marca como "Novo"
 * quando ainda não foram exibidos na sessão atual (comparação client-side
 * com os perfis já vistos). Não consome créditos — apenas lê o banco.
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
 */
function linkedinNewLeadsLoadFile($relativePath) {
    static $absRoots = null;
    if ($absRoots === null) {
        $absRoots = [
            dirname(__DIR__),
            rtrim(getenv('DOCUMENT_ROOT') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/'),
        ];
        $docRoot = rtrim(getenv('DOCUMENT_ROOT') ?: ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $parent = dirname($docRoot);
        if ($parent && $parent !== $docRoot) {
            $absRoots[] = $parent;
        }
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
    $candidates[] = __DIR__ . '/' . $relativePath;
    foreach ($candidates as $path) {
        if (is_file($path)) {
            return $path;
        }
    }
    return null;
}
foreach (['config/config.php', 'config/database.php', 'includes/functions.php'] as $rel) {
    $path = linkedinNewLeadsLoadFile($rel);
    if ($path === null) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => "Arquivo não encontrado: {$rel}. Verifique a estrutura de pastas do projeto.",
        ]);
        exit;
    }
    require_once $path;
}
ob_clean();

$missing = [];
if (!function_exists('requireAuth')) {
    $missing[] = 'requireAuth';
}
if (!function_exists('getAuthUser')) {
    $missing[] = 'getAuthUser';
}
if (!class_exists('Database')) {
    $missing[] = 'Database';
}
if ($missing) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error' => 'Dependências do projeto não carregadas: ' . implode(', ', $missing) . '.',
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('X-Frame-Options: DENY');

try {
    requireAuth();
    $user = getAuthUser();
    if (!is_array($user)) {
        throw new RuntimeException('Sessão inválida.');
    }
    $userId = (int) ($user['id'] ?? 0);
    $tenantId = (int) ($user['tenant_id'] ?? 0);
    if ($userId <= 0) {
        throw new RuntimeException('Sessão inválida.');
    }

    $db = Database::getInstance()->getConnection();

    // Última pesquisa concluída do tenant (o histórico é compartilhado na empresa).
    $runQuery = $db->prepare(
        'SELECT id, tenant_id, user_id, status, leads_found, created_at
         FROM linkedin_runs
         WHERE tenant_id = ? AND status = ?
         ORDER BY id DESC LIMIT 1'
    );
    $runQuery->execute([$tenantId, 'done']);
    $run = $runQuery->fetch(PDO::FETCH_ASSOC);
    if (!$run) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Nenhuma pesquisa LinkedIn concluída encontrada. Faça uma pesquisa para verificar os novos leads.',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Leads salvos na tabela de resultados da pesquisa.
    $tableAvailable = (bool) $db->query("SHOW TABLES LIKE 'linkedin_run_results'")->fetch();
    $leads = [];
    if ($tableAvailable) {
        $resultsQuery = $db->prepare('SELECT leads_json FROM linkedin_run_results WHERE run_id = ? LIMIT 1');
        $resultsQuery->execute([(int) $run['id']]);
        $row = $resultsQuery->fetch(PDO::FETCH_ASSOC);
        if (is_array($row) && is_string($row['leads_json'])) {
            $decoded = json_decode($row['leads_json'], true);
            if (is_array($decoded)) {
                $leads = $decoded;
            }
        }
    }
    if (!$leads) {
        $fallback = $db->prepare(
            'SELECT profile_url, full_name, job_title, company_name, location, photo_url
             FROM linkedin_leads WHERE run_id = ?'
        );
        $fallback->execute([(int) $run['id']]);
        foreach ($fallback->fetchAll(PDO::FETCH_ASSOC) as $lead) {
            $leads[] = [
                'profile_url' => $lead['profile_url'] ?? '',
                'full_name' => $lead['full_name'] ?? '',
                'job_title' => $lead['job_title'] ?? null,
                'company_name' => $lead['company_name'] ?? null,
                'location' => $lead['location'] ?? null,
                'photo_url' => $lead['photo_url'] ?? null,
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'leads' => array_values($leads),
            'new_count' => count($leads),
            'existing_count' => 0,
            'run_id' => (int) $run['id'],
            'meta' => ['source' => 'bank'],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
} catch (RuntimeException $e) {
    $message = $e->getMessage();
    // Apenas falhas explicitamente relacionadas à sessão devem ser 401. Falhas
    // de consulta, tabela ou infraestrutura não podem induzir o painel a tratar
    // o usuário como deslogado.
    if (preg_match('/sessão|sessao|autentica|login/i', $message)) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    error_log('linkedin-new-leads runtime: ' . $message);
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Não foi possível verificar novos leads agora. Tente novamente em instantes.'], JSON_UNESCAPED_UNICODE);
    exit;
} catch (Throwable $e) {
    error_log('linkedin-new-leads: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Não foi possível verificar novos leads.'], JSON_UNESCAPED_UNICODE);
    exit;
}
