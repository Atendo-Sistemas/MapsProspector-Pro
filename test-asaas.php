<?php
/**
 * Teste rápido da integração Asaas (v64f3).
 * Uso: php /home/naciona4/prospec.atendo.log.br/test-asaas.php
 * Valida: chave API salva + conexão com a API do Asaas + configurações do BD.
 * Não exige usuário logado (lê apenas a config salva em platform_settings).
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');

$roots = array_values(array_unique(array_filter([
    getenv('APP') ?: null,
    __DIR__,
    getcwd() ?: null,
])));
$loadedRoot = null;
$db = null;

// O cPanel mantém os arquivos de conexão fora dos pacotes de atualização.
// Por isso o diagnóstico procura primeiro a estrutura normal config/config.php
// e config/database.php dentro da raiz da aplicação informada em APP.
foreach ($roots as $root) {
    $root = rtrim((string) $root, '/\\');
    $configCandidates = [
        $root . '/config/config.php',
        $root . '/config.php',
    ];
    $databaseCandidates = [
        $root . '/config/database.php',
        $root . '/database.php',
    ];
    $configFile = null;
    $databaseFile = null;
    foreach ($configCandidates as $try) {
        if (is_file($try)) { $configFile = $try; break; }
    }
    foreach ($databaseCandidates as $try) {
        if (is_file($try)) { $databaseFile = $try; break; }
    }
    if (!$configFile || !$databaseFile) continue;

    try {
        require_once $configFile;
        require_once $databaseFile;
        if (class_exists('Database')) {
            $db = new Database();
            $loadedRoot = $root;
            break;
        }
    } catch (Throwable $e) {
        echo "AVISO bootstrap {$root}: " . $e->getMessage() . "\n";
    }
}
if (!$db) {
    echo "BANCO=INDISPONIVEL (use: APP=/caminho/da/aplicacao php /caminho/da/aplicacao/test-asaas.php)\n";
    exit(1);
}

echo "BANCO=OK\n";
echo "APP_ENCONTRADO={$loadedRoot}\n";

// Ler configurações salvas.
$settings = [];
foreach (['asaas_api_key', 'asaas_webhook_token'] as $key) {
    try {
        $stmt = $db->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $settings[$key] = (string) $stmt->fetchColumn();
    } catch (Throwable $e) {
        try {
            $stmt = $db->prepare('SELECT value FROM settings WHERE `key` = ? LIMIT 1');
            $stmt->execute([$key]);
            $settings[$key] = (string) $stmt->fetchColumn();
        } catch (Throwable $e2) {
            $settings[$key] = '';
        }
    }
}

$key = $settings['asaas_api_key'] ?? '';
$token = $settings['asaas_webhook_token'] ?? '';
echo "CHAVE_API_CONFIGURADA=" . ($key !== '' ? 'SIM (' . strlen($key) . ' caracteres)' : 'NAO') . "\n";
echo "TOKEN_WEBHOOK_CONFIGURADO=" . ($token !== '' ? 'SIM' : 'NAO') . "\n";

if ($key === '') {
    echo "\nCONCLUSAO: A chave de API do Asaas ainda nao esta configurada.\n";
    echo "Acesse o painel super admin -> Configuracoes -> Financeiro (Asaas) e cole o X-ACCESS-TOKEN.\n";
    exit(2);
}

// Teste de conexão com a API Asaas.
echo "\nTestando conexao com a API Asaas...\n";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "X-ACCESS-TOKEN: {$key}\r\n",
        'timeout' => 25,
        'ignore_errors' => true,
    ],
]);
$response = @file_get_contents('https://api.asaas.com/v3/myAccount/finances', false, $context);
$test = json_decode((string) $response, true);
if (!is_array($test) || isset($test['errors'][0]['description'])) {
    $error = (string) ($test['errors'][0]['description'] ?? 'Resposta invalida do Asaas.');
    echo "API_ASAAS=ERRO: {$error}\n";
    echo "CONCLUSAO: A chave salva nao foi aceita pelo Asaas. Reconfigure no painel.\n";
    exit(3);
}
echo "API_ASAAS=OK\n";
if (isset($test['name'], $test['company'])) {
    echo "CONTA_ASAAS={$test['name']} / {$test['company']}\n";
}
echo "\nCONCLUSAO: A integracao Asaas esta funcionando.\n";
echo "URL do webhook a cadastrar no painel Asaas (Integracoes -> Webhooks): https://" . ($_SERVER['HTTP_HOST'] ?? 'SEU-DOMINIO') . "/webhook-asaas.php\n";
echo "Cabeçalho exigido pelo webhook: asaas-access-token = " . ($token !== '' ? $token : '(gerado no salvar da config)') . "\n";
echo "Eventos a marcar: PAYMENT_CONFIRMED e PAYMENT_RECEIVED\n";
exit(0);
