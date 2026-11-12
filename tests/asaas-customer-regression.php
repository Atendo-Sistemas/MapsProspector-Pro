<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/AsaasPaymentGateway.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, cnpj TEXT)');
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, email TEXT, profile TEXT)');
$db->exec("INSERT INTO tenants VALUES (1, NULL), (2, '12345678901'), (3, NULL)");
$db->exec("INSERT INTO users VALUES (7, 2, 'Cliente', 'cliente@example.test', 'admin'), (8, NULL, 'Super', 'super@example.test', 'super_admin')");
$load = new ReflectionMethod(MapsProspectorAsaasPaymentGateway::class, 'loadCustomerData');
$load->setAccessible(true);
$customer = $load->invoke(null, $db, ['id' => 7, 'tenant_id' => 2], 2);
if ($customer['name'] !== 'Cliente' || $customer['email'] !== 'cliente@example.test' || $customer['cnpj'] !== '12345678901') {
    throw new RuntimeException('Os dados do cliente não foram carregados do cadastro.');
}
$superCustomer = $load->invoke(null, $db, ['id' => 8, 'profile' => 'super_admin'], 1);
if ($superCustomer['email'] !== 'super@example.test') throw new RuntimeException('O super administrador deve poder contratar o CRM da plataforma.');
try {
    $load->invoke(null, $db, ['id' => 7, 'tenant_id' => 2], 3);
    throw new RuntimeException('Uma empresa diferente não deve acessar os dados do cliente.');
} catch (RuntimeException $error) {
    if ($error->getMessage() !== 'Usuário responsável não encontrado para esta empresa.') throw $error;
}
echo "Asaas customer regression: OK\n";
