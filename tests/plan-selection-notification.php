<?php
declare(strict_types=1);

$readKeys = [];
function getPlatformSetting(PDO $db, string $key): ?string
{
    global $readKeys;
    $readKeys[] = $key;
    return null;
}

require_once __DIR__ . '/../services/notificationService.php';

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE tenants (id INTEGER PRIMARY KEY, name TEXT, cnpj TEXT)');
$db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, tenant_id INTEGER, name TEXT, phone TEXT)');
$db->exec('CREATE TABLE plans (id INTEGER PRIMARY KEY, name TEXT)');
$db->exec("INSERT INTO tenants VALUES (2, 'Empresa', NULL)");
$db->exec("INSERT INTO users VALUES (7, 2, 'Usuário', '11999999999')");
$db->exec("INSERT INTO plans VALUES (3, 'Plano')");

foreach ([
    [null, 'notif_template:plan-selected-whatsapp'],
    ['123', 'notif_template:plan-selected-whatsapp'],
    ['123.456.789-01', 'notif_template:plan-selected-complete-whatsapp'],
    ['12.345.678/0001-90', 'notif_template:plan-selected-complete-whatsapp'],
] as [$document, $expected]) {
    $db->prepare('UPDATE tenants SET cnpj = ? WHERE id = 2')->execute([$document]);
    $readKeys = [];
    NotificationService::notifyPlanSelection($db, 7, 3, 'test');
    if (!in_array($expected, $readKeys, true)) {
        throw new RuntimeException('Template incorreto para a situação cadastral.');
    }
}
echo "Plan selection notification: OK\n";
