<?php
/**
 * DIAGNÓSTICO 3 — descobre como preencher user_id.
 * Envie para /home/naciona4/public_html/api/diagnostico3.php
 * Teste: curl -s https://prospec.atendo.log.br/api/diagnostico3.php
 * REMOVA APÓS O DIAGNÓSTICO.
 */
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);

define('IP_DB_HOST', 'localhost');
define('IP_DB_NAME', 'naciona4_maps1243');
define('IP_DB_USER', 'naciona4_maps1243');
define('IP_DB_PASS', 'naciona4_maps1243');

$out = [];
try {
    $dsn = 'mysql:host=' . IP_DB_HOST . ';dbname=' . IP_DB_NAME . ';charset=utf8mb4';
    $db = new PDO($dsn, IP_DB_USER, IP_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Tabelas do banco
    $out['tabelas'] = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    // Um pagamento existente (para ver como o sistema preenche user_id)
    try {
        $out['payment_existente'] = $db->query("SELECT id, tenant_id, user_id, type, credits, plan_id, amount, amount_cents, payment_method, status, external_id FROM payments ORDER BY id DESC LIMIT 3")->fetchAll();
    } catch (Throwable $e) {
        $out['payment_existente'] = 'erro: ' . $e->getMessage();
    }

    // Usuários de cada tenant (procura tabelas users/admins/members)
    foreach (['users', 'admins', 'members', 'tenants_users'] as $t) {
        try {
            $cols = array_column($db->query("SHOW COLUMNS FROM $t")->fetchAll(), 'Field');
            $rows = $db->query("SELECT * FROM $t LIMIT 2")->fetchAll();
            $out["tabela_$t"] = ['colunas' => $cols, 'linhas' => $rows];
        } catch (Throwable $e) {
            // tabela não existe
        }
    }
} catch (Throwable $e) {
    $out['erro_geral'] = $e->getMessage();
}
echo json_encode($out, JSON_PRETTY_PRINT);
