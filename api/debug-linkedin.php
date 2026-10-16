<?php
/**
 * Diagnóstico do endpoint linkedin-search.php.
 * Rode: curl -s -b /tmp/cookies.txt https://prospec.atendo.log.br/api/debug-linkedin.php
 * Apague este arquivo após o teste: rm .../api/debug-linkedin.php
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<pre>";

// 1. Funções existentes
echo "== 1. Funções críticas ==\n";
foreach (['requireAuth', 'getAuthUser', 'jsonError', 'jsonSuccess', 'incrementTenantUsage', 'getRotatingApifyKey'] as $fn) {
    echo $fn . ': ' . (function_exists($fn) ? 'OK' : 'FALTA') . "\n";
}

// 2. Classes existentes
echo "\n== 2. Classes críticas ==\n";
foreach (['Database', 'LinkedInSearchService'] as $cls) {
    echo $cls . ': ' . (class_exists($cls) ? 'OK' : 'FALTA') . "\n";
}

// 3. Tabelas
echo "\n== 3. Tabelas LinkedIn ==\n";
try {
    require_once __DIR__ . '/../config/database.php';
    $db = Database::getInstance()->getConnection();
    foreach (['linkedin_leads', 'linkedin_runs', 'legal_consents', 'apify_keys', 'tenant_usage'] as $t) {
        try {
            $r = $db->query("SHOW TABLES LIKE '{$t}'");
            echo $t . ': ' . ($r->rowCount() ? 'OK' : 'FALTA') . "\n";
        } catch (Throwable $e) {
            echo $t . ': ERRO ' . $e->getMessage() . "\n";
        }
    }
    if ($r = $db->query("SHOW COLUMNS FROM legal_consents")) {
        echo "\nColunas legal_consents:\n";
        foreach ($r as $c) {
            echo '  ' . $c['Field'] . "\n";
        }
    }
} catch (Throwable $e) {
    echo "DB: " . $e->getMessage() . "\n";
}

// 4. Simular as primeiras linhas do endpoint
echo "\n== 4. Simulação do fluxo ==\n";
try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../services/linkedinSearchService.php';
    $userId = requireAuth();
    $authUser = getAuthUser();
    $profile = strtolower((string) ($authUser['profile'] ?? ''));
    $tenantId = (int) ($authUser['tenant_id'] ?? 0);
    echo "userId: {$userId} | profile: {$profile} | tenant_id: {$tenantId}\n";
    echo "authUser completo: " . json_encode($authUser, JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    echo "FALHA: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ':' . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}

// 5. Testar execução do serviço (busca real)
echo "\n== 5. Execução real do serviço ==\n";
try {
    $stmt = $db->prepare('SELECT api_key FROM apify_keys WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
    $stmt->execute();
    $apiKey = (string) ($stmt->fetchColumn() ?: '');
    echo "Apify key configurada: " . ($apiKey ? 'SIM (' . substr($apiKey, 0, 12) . '...)' : 'NAO') . "\n";
    if ($apiKey) {
        $svc = new LinkedInSearchService($apiKey);
        $res = $svc->searchByCompany('Unilever', ['CEO'], 'Brasil', 5);
        echo "Leads encontrados: " . count($res['leads']) . "\n";
        echo json_encode($res['leads'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
} catch (Throwable $e) {
    echo "FALHA: " . $e->getMessage() . "\n";
    echo "Arquivo: " . $e->getFile() . ':' . $e->getLine() . "\n";
}
echo "</pre>";
