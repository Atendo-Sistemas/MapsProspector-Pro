<?php
/**
 * Maps Prospector — Módulo de pagamentos Asaas v20260820-64f3.
 *
 * O Asaas substitui a InfinitePay nesta versão. Fluxo de recarga de créditos:
 *
 * GET  ?action=getConfig      (super admin)  estado da configuração + resumo de pedidos
 * POST ?action=saveConfig     (super admin)  grava api key e webhook token em platform_settings
 * GET  ?action=orders         (super admin)  últimos pedidos com filtros
 * POST ?action=create         (autenticado)  cria cobrança Asaas e retorna o link de pagamento
 * GET  ?action=done           (autenticado)  consulta o Asaas para confirmar o status do pedido
 *
 * A confirmação definitiva e a liberação de créditos ocorrem pelo webhook
 * (webhook-asaas.php, na raiz do sistema), que o Asaas chama quando o
 * pagamento é recebido. A ação `done` serve apenas para o usuário voltar ao
 * sistema logo após pagar: ela pergunta ao Asaas o status real da cobrança.
 */

declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/asaas-secrets.php';
require_once __DIR__ . '/../services/AsaasPaymentGateway.php';
require_once __DIR__ . '/../services/ManualPaymentRequest.php';
require_once __DIR__ . '/../services/CrmAddonService.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function asaJson(bool $success, array $data = [], string $message = '', int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    echo json_encode(
        [
            'success' => $success,
            'data' => $data,
            'message' => $message ?: ($success ? 'OK' : 'Erro'),
            'error' => $success ? null : $message,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/** Lê o corpo JSON recebido em POST. */
function asaInput(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string) $raw ?: '{}', true);
    return is_array($data) ? $data : [];
}

function asaRequireTable(PDO $db): void
{
    if (!(bool) $db->query("SHOW TABLES LIKE 'asaas_orders'")->fetchColumn()) {
        asaJson(false, ['pending_install' => true], 'O módulo de pagamentos Asaas ainda não foi instalado. Execute o arquivo sql/asaas-finance.sql uma única vez no banco.', 503);
    }
}

/** Sessão do usuário autenticado, mesmo padrão dos demais endpoints. */
function asaSessionUser(PDO $db): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $candidates = [
        $_SESSION['ncSessionUser'] ?? null,
        $_SESSION['user'] ?? null,
        $_SESSION['auth_user'] ?? null,
        $_SESSION['current_user'] ?? null,
        $_SESSION['user_data'] ?? null,
    ];
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }
        $profile = strtolower((string) ($candidate['profile'] ?? $candidate['role'] ?? $candidate['user_type'] ?? ''));
        $id = $candidate['id'] ?? $candidate['user_id'] ?? null;
        if ($profile === 'super_admin' || (!empty($candidate['is_super_admin']) && $id !== null)) {
            return [
                'id' => $id === null || $id === '' ? 0 : (int) $id,
                'email' => (string) ($candidate['email'] ?? ''),
                'name' => (string) ($candidate['name'] ?? $candidate['full_name'] ?? $candidate['email'] ?? 'Usuário'),
                'profile' => 'super_admin',
                'tenant_id' => null,
            ];
        }
        if ($id !== null && $id !== '') {
            return [
                'id' => (int) $id,
                'email' => (string) ($candidate['email'] ?? ''),
                'name' => (string) ($candidate['name'] ?? $candidate['full_name'] ?? $candidate['email'] ?? 'Usuário'),
                'profile' => $profile,
                'tenant_id' => isset($candidate['tenant_id']) ? (int) $candidate['tenant_id'] : (isset($candidate['tenantId']) ? (int) $candidate['tenantId'] : null),
            ];
        }
    }
    $profile = strtolower((string) ($_SESSION['profile'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? ''));
    if ($profile === 'super_admin' || !empty($_SESSION['is_super_admin'])) {
        return [
            'id' => 0,
            'email' => (string) ($_SESSION['email'] ?? ''),
            'name' => (string) ($_SESSION['name'] ?? 'Super administrador'),
            'profile' => 'super_admin',
            'tenant_id' => null,
        ];
    }
    $id = $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? null;
    if (!$id) {
        return null;
    }
    try {
        $stmt = $db->prepare('SELECT id, tenant_id, name, email, profile FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([(int) $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'name' => (string) $row['name'],
            'profile' => strtolower((string) $row['profile']),
            'tenant_id' => $row['tenant_id'] === null ? null : (int) $row['tenant_id'],
        ];
    } catch (Throwable $e) {
        error_log('asaas: session lookup: ' . $e->getMessage());
        return null;
    }
}

function asaRequireAdmin(PDO $db): array
{
    $user = asaSessionUser($db);
    if ($user === null) {
        asaJson(false, [], 'Sua sessão expirou. Faça login novamente.', 401);
    }
    if (strtolower((string) $user['profile']) !== 'super_admin') {
        asaJson(false, [], 'Apenas a plataforma pode administrar os pagamentos.', 403);
    }
    return $user;
}

function asaRequireUser(PDO $db): array
{
    $user = asaSessionUser($db);
    if ($user === null) {
        asaJson(false, [], 'Sua sessão expirou. Faça login novamente.', 401);
    }
    return $user;
}

function asaSettingsTableReady(PDO $db): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $ready = (bool) $db->query("SHOW TABLES LIKE 'platform_settings'")->fetchColumn();
        if (!$ready) {
            $db->exec(
                'CREATE TABLE IF NOT EXISTS platform_settings ('
                . ' setting_key VARCHAR(64) NOT NULL PRIMARY KEY,'
                . ' setting_value TEXT DEFAULT NULL,'
                . ' updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
            $ready = true;
        }
    } catch (Throwable $e) {
        error_log('asaas: platform_settings: ' . $e->getMessage());
        $ready = false;
    }
    return $ready;
}

/**
 * Lê uma configuração global da tabela platform_settings diretamente.
 * Não depende da versão do functions.php do servidor (que pode gravar em
 * outra tabela), garantindo que o Asaas sempre leia o que ele próprio gravou.
 */
function asaGetSetting(PDO $db, string $key): string
{
    if (asaSecretKeyAllowed($key)) {
        return asaSecretGetOrMigrate($db, $key);
    }
    if (!asaSettingsTableReady($db)) {
        return '';
    }
    try {
        $stmt = $db->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        return trim((string) ($stmt->fetchColumn() ?: ''));
    } catch (Throwable $e) {
        error_log('asaas: get setting: ' . $e->getMessage());
        return '';
    }
}

/**
 * Grava uma configuração global na tabela platform_settings diretamente.
 * Cria a tabela se necessário e não depende do functions.php do servidor.
 */
function asaSetSetting(PDO $db, string $key, string $value): bool
{
    if (asaSecretKeyAllowed($key)) {
        return asaSettingsTableReady($db) && asaSecretPersist($db, $key, $value);
    }
    if (!asaSettingsTableReady($db)) {
        return false;
    }
    try {
        $stmt = $db->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        );
        $stmt->execute([$key, $value]);
        return true;
    } catch (Throwable $e) {
        error_log('asaas: set setting: ' . $e->getMessage());
        return false;
    }
}

function asaEnvironment(PDO $db): string
{
    return asaGetSetting($db, 'asaas_environment') === 'sandbox' ? 'sandbox' : 'production';
}

function asaApiBase(PDO $db): string
{
    return asaEnvironment($db) === 'sandbox'
        ? 'https://api-sandbox.asaas.com/v3'
        : 'https://api.asaas.com/v3';
}

function asaBalancePath(): string
{
    // Rota oficial atual para validar a chave e o acesso à conta.
    return '/finance/balance';
}

function asaGatewayActive(PDO $db): bool
{
    return asaGetSetting($db, 'asaas_active') === '1';
}

function asaSubscriptionsTableReady(PDO $db): bool
{
    return (bool) $db->query("SHOW TABLES LIKE 'asaas_subscriptions'")->fetchColumn();
}

function asaRequireSubscriptionsTable(PDO $db): void
{
    if (!asaSubscriptionsTableReady($db)) {
        asaJson(false, ['pending_install' => true], 'A atualização de assinaturas ainda não foi instalada. Execute o arquivo sql/asaas-finance.sql atualizado uma única vez.', 503);
    }
}

/** Chama a API do Asaas e devolve o corpo decodificado. */
function asaasRequest(PDO $db, string $path, array $body, string $method = 'POST'): array
{
    $apiKey = asaGetSetting($db, 'asaas_api_key');
    if ($apiKey === '') {
        throw new RuntimeException('Chave de API do Asaas não configurada.');
    }
    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: MapsProspector/1.0 (https://prospec.atendo.log.br)\r\naccess_token: {$apiKey}\r\nX-ACCESS-TOKEN: {$apiKey}\r\n",
            'content' => in_array(strtoupper($method), ['GET', 'DELETE'], true) ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => 25,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents(asaApiBase($db) . $path, false, $context);
    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Resposta inválida da API do Asaas.');
    }
    if (isset($decoded['errors'][0]['description'])) {
        throw new RuntimeException((string) $decoded['errors'][0]['description']);
    }
    return $decoded;
}

function asaasOriginUrl(): string
{
    $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
    $scheme = in_array($forwardedProto, ['https', 'http'], true)
        ? $forwardedProto
        : ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http');
    $host = ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost');
    $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string) $host);
    return rtrim($scheme . '://' . $host, '/');
}

/** Teste simples de conexão usando o saldo da conta. */
function asaasTestConnection(PDO $db): array
{
    $result = asaasRequest($db, asaBalancePath(), [], 'GET');
    $available = (string) ($result['balance'] ?? $result['availableBalance'] ?? '');
    return [
        'connected' => true,
        'available_balance' => $available !== '' ? (float) $available : null,
        'account_name' => (string) ($result['name'] ?? ''),
    ];
}

function asaEnsureCustomer(PDO $db, array $user): string
{
    $email = trim((string) ($user['email'] ?? ''));
    if ($email === '') {
        throw new RuntimeException('O usuário responsável precisa ter um e-mail cadastrado antes de contratar um plano.');
    }
    $found = asaasRequest($db, '/customers?email=' . rawurlencode($email) . '&limit=1', [], 'GET');
    $first = is_array($found['data'] ?? null) ? ($found['data'][0] ?? null) : null;
    if (is_array($first) && !empty($first['id'])) {
        return (string) $first['id'];
    }
    $customer = asaasRequest($db, '/customers', [
        'name' => mb_substr(trim((string) ($user['name'] ?? 'Cliente da plataforma')) ?: 'Cliente da plataforma', 0, 120),
        'email' => $email,
    ]);
    $customerId = trim((string) ($customer['id'] ?? ''));
    if ($customerId === '') {
        throw new RuntimeException('O Asaas não retornou a identificação do cliente.');
    }
    return $customerId;
}

function asaPaidPlan(PDO $db, int $planId): array
{
    $stmt = $db->prepare("SELECT id, name, slug, price_monthly, period, status, catalog_group FROM plans WHERE id = ? AND COALESCE(catalog_group, 'current') <> 'crm_addon' LIMIT 1");
    $stmt->execute([$planId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($plan) || (string) ($plan['status'] ?? '') !== 'active') {
        throw new RuntimeException('O plano selecionado não está disponível.');
    }
    $amountCents = (int) round(((float) ($plan['price_monthly'] ?? 0)) * 100);
    if ($amountCents <= 0) {
        throw new RuntimeException('O plano selecionado não possui cobrança mensal.');
    }
    if ((string) ($plan['period'] ?? 'monthly') !== 'monthly') {
        throw new RuntimeException('Somente planos mensais podem ser contratados por assinatura nesta etapa.');
    }
    $plan['amount_cents'] = $amountCents;
    return $plan;
}

function asaSubscriptionPaymentUrl(PDO $db, string $subscriptionId): string
{
    $payments = asaasRequest($db, '/payments?subscription=' . rawurlencode($subscriptionId) . '&limit=1', [], 'GET');
    $first = is_array($payments['data'] ?? null) ? ($payments['data'][0] ?? null) : null;
    return is_array($first) ? (string) ($first['invoiceUrl'] ?? $first['invoiceLink'] ?? '') : '';
}

function asaCreateSubscription(PDO $db, array $user, int $tenantId, int $planId): array
{
    asaRequireSubscriptionsTable($db);
    $existing = $db->prepare("SELECT id FROM asaas_subscriptions WHERE tenant_id = ? AND COALESCE(product_kind, 'prospecting') = 'prospecting' AND status IN ('pending', 'active', 'overdue') ORDER BY id DESC LIMIT 1");
    $existing->execute([$tenantId]);
    if ($existing->fetchColumn()) {
        throw new RuntimeException('Já existe uma assinatura em andamento para esta empresa. Cancele-a antes de solicitar outro plano.');
    }
    $plan = asaPaidPlan($db, $planId);
    $customerId = asaEnsureCustomer($db, $user);
    $subscription = asaasRequest($db, '/subscriptions', [
        'customer' => $customerId,
        'billingType' => 'UNDEFINED',
        'value' => round(((int) $plan['amount_cents']) / 100, 2),
        'nextDueDate' => date('Y-m-d'),
        'cycle' => 'MONTHLY',
        'description' => mb_substr('Assinatura mensal — ' . (string) $plan['name'], 0, 400),
        'externalReference' => 'MP-SUB-' . $tenantId . '-' . (int) $plan['id'],
    ]);
    $subscriptionId = trim((string) ($subscription['id'] ?? ''));
    if ($subscriptionId === '') {
        throw new RuntimeException('O Asaas não retornou a identificação da assinatura.');
    }
    $paymentUrl = (string) ($subscription['invoiceUrl'] ?? $subscription['invoiceLink'] ?? '');
    if ($paymentUrl === '') {
        $paymentUrl = asaSubscriptionPaymentUrl($db, $subscriptionId);
    }
    $nextDueDate = trim((string) ($subscription['nextDueDate'] ?? date('Y-m-d')));
    $insert = $db->prepare(
        "INSERT INTO asaas_subscriptions
         (tenant_id, user_id, plan_id, plan_slug, product_kind, amount_cents, asaas_customer_id, asaas_subscription_id, payment_url, status, next_due_date)
         VALUES (?, ?, ?, ?, 'prospecting', ?, ?, ?, ?, 'pending', ?)"
    );
    $insert->execute([
        $tenantId,
        (int) $user['id'],
        (int) $plan['id'],
        (string) $plan['slug'],
        (int) $plan['amount_cents'],
        $customerId,
        $subscriptionId,
        $paymentUrl !== '' ? $paymentUrl : null,
        preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextDueDate) ? $nextDueDate : date('Y-m-d'),
    ]);
    return [
        'subscription_id' => $subscriptionId,
        'payment_url' => $paymentUrl,
        'plan_name' => (string) $plan['name'],
        'amount_brl' => ((int) $plan['amount_cents']) / 100,
    ];
}

/** Registra o pedido e cria a cobrança no Asaas; preenche $orderNsu por referência. */
function asaasCreateOrder(PDO $db, array $user, int $amountCents, int $quantity, string $description, string &$orderNsu): bool
{
    $orderNsu = 'MP-' . strtolower(bin2hex(random_bytes(8))) . '-' . time();
    $insert = $db->prepare(
        'INSERT INTO asaas_orders (order_nsu, user_id, tenant_id, amount_cents, quantity, description, status)
         VALUES (?, ?, ?, ?, ?, ?, \'pending\')'
    );
    $insert->execute([$orderNsu, (int) $user['id'], $user['tenant_id'], $amountCents, $quantity, $description]);
    return true;
}

/**
 * Cria (ou reutiliza) o cliente no Asaas a partir do e-mail do usuário e
 * gera o link de cobrança (PIX com opção de cartão pela fatura).
 */
function asaasCreateCharge(PDO $db, string $orderNsu, int $amountCents, int $quantity, string $description, array $user): array
{
    $paymentStmt = $db->prepare('SELECT * FROM asaas_orders WHERE order_nsu = ? LIMIT 1');
    $paymentStmt->execute([$orderNsu]);
    $order = $paymentStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($order)) {
        throw new RuntimeException('Pedido não encontrado.');
    }
    $customerId = (string) ($order['asaas_customer_id'] ?? '');
    $email = (string) ($user['email'] ?? '');
    if ($customerId === '' && $email !== '') {
        try {
            $customer = asaasRequest($db, '/customers', [
                'name' => (string) ($user['name'] ?: 'Cliente da plataforma'),
                'email' => $email,
                'mobilePhone' => null,
            ]);
            $customerId = (string) ($customer['id'] ?? '');
            if ($customerId !== '') {
                $upd = $db->prepare('UPDATE asaas_orders SET asaas_customer_id = ? WHERE id = ? LIMIT 1');
                $upd->execute([$customerId, (int) $order['id']]);
            }
        } catch (Throwable $e) {
            // Cliente não criado: segue com cobrança anônima quando possível.
            $customerId = '';
        }
    }

    $dueDate = (new DateTimeImmutable('+7 days'))->format('Y-m-d');
    $charge = asaasRequest($db, '/payments', [
        'customer' => $customerId !== '' ? $customerId : null,
        'billingType' => 'UNDEFINED',
        'value' => round($amountCents / 100, 2),
        'dueDate' => $dueDate,
        'description' => mb_substr($description, 0, 400),
        'externalReference' => $orderNsu,
    ]);

    $paymentId = (string) ($charge['id'] ?? '');
    $invoiceUrl = (string) ($charge['invoiceUrl'] ?? $charge['invoiceLink'] ?? '');
    $paymentUrl = $invoiceUrl !== '' ? $invoiceUrl : ((string) ($charge['pixQrCode'] ?? ''));
    $upd = $db->prepare('UPDATE asaas_orders SET asaas_payment_id = ?, payment_url = ? WHERE id = ? LIMIT 1');
    $upd->execute([$paymentId, $paymentUrl !== '' ? $paymentUrl : null, (int) $order['id']]);
    return ['payment_url' => $paymentUrl, 'payment_id' => $paymentId];
}

/** Libera os créditos do pedido e notifica (chamado pelo webhook e pela ação done). */
function asaasMarkPaid(PDO $db, array $order, array $checkResult): void
{
    $amountCents = (int) $order['amount_cents'];
    $updateStmt = $db->prepare(
        'UPDATE asaas_orders
           SET status = \'paid\', paid_at = NOW(),
               asaas_payment_id = COALESCE(asaas_payment_id, ?)
         WHERE id = ? AND status <> \'paid\''
    );
    $updateStmt->execute([
        isset($checkResult['id']) ? substr((string) $checkResult['id'], 0, 64) : null,
        (int) $order['id'],
    ]);
    $creditQuantity = (int) ($order['quantity'] ?? 0);
    if ((int) $updateStmt->rowCount() > 0 && $creditQuantity > 0) {
        $plan = $db->prepare('SELECT p.period FROM tenants t INNER JOIN plans p ON p.id = t.plan_id WHERE t.id = ? LIMIT 1');
        $plan->execute([$order['tenant_id']]);
        $period = (string) (($plan->fetch(PDO::FETCH_ASSOC)['period'] ?? 'monthly'));
        $periodStart = $period === 'yearly' ? date('Y-01-01') : date('Y-m-01');
        $usage = $db->prepare('INSERT INTO tenant_usage (tenant_id, period_start, tokens_used, token_bonus) VALUES (?, ?, 0, ?) ON DUPLICATE KEY UPDATE token_bonus = COALESCE(token_bonus, 0) + ?');
        $usage->execute([$order['tenant_id'], $periodStart, $creditQuantity, $creditQuantity]);
        if (asaasTablesReady($db)) {
            asaasCreateReferralCommission($db, (int) ($order['tenant_id'] ?? 0), (int) ($order['id'] ?? 0));
        }
    }
    if (is_file(__DIR__ . '/../services/notificationService.php')) {
        require_once __DIR__ . '/../services/notificationService.php';
        if (class_exists('NotificationService')) {
            NotificationService::notifyPaymentConfirmed(
                $db,
                ['id' => $order['user_id'], 'tenant_id' => $order['tenant_id'], 'name' => '', 'email' => ''],
                $amountCents
            );
        }
    }
}

/** Consulta direto no Asaas o status de uma cobrança. */
function asaasFetchPayment(PDO $db, string $paymentId): ?array
{
    if ($paymentId === '') {
        return null;
    }
    $apiKey = asaGetSetting($db, 'asaas_api_key');
    if ($apiKey === '') {
        return null;
    }
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "Accept: application/json\r\nUser-Agent: MapsProspector/1.0 (https://prospec.atendo.log.br)\r\naccess_token: {$apiKey}\r\nX-ACCESS-TOKEN: {$apiKey}\r\n",
            'timeout' => 20,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents(asaApiBase($db) . "/payments/{$paymentId}", false, $context);
    $decoded = json_decode((string) $response, true);
    return is_array($decoded) && !isset($decoded['errors'][0]['description']) ? $decoded : null;
}

/**
 * Registra a comissão do indicador do tenant pagante (R$ por conversão).
 * A comissão entra como "pendente" e é quitada quando o indicador solicitar
 * o saque pela aba de indicações.
 */
function asaasCreateReferralCommission(PDO $db, int $payerTenantId, int $orderId): void
{
    if ($payerTenantId <= 0) {
        return;
    }
    try {
        $payout = 10.00;
        if (function_exists('refSettingFloat')) {
            $payout = refSettingFloat($db, 'payout_per_conversion', 10.00);
        } else {
            $stmt = $db->prepare('SELECT setting_value FROM referral_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute(['payout_per_conversion']);
            $value = $stmt->fetchColumn();
            if ($value !== false && is_numeric((string) $value)) {
                $payout = (float) $value;
            }
        }
        $ref = $db->prepare(
            "SELECT id, referrer_user_id AS user_id, referrer_tenant_id AS tenant_id, 0.00 AS payout_amount
               FROM referral_visits
              WHERE signup_tenant_id = ? AND status IN ('registered', 'converted')
              ORDER BY attributed_at DESC, id DESC LIMIT 1"
        );
        $ref->execute([$payerTenantId]);
        $row = $ref->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return;
        }
        $db->prepare("UPDATE referral_visits SET status = 'converted', converted_at = COALESCE(converted_at, NOW()) WHERE id = ?")
            ->execute([(int) $row['id']]);
        $amount = $row['payout_amount'] > 0 ? (float) $row['payout_amount'] : $payout;
        $insert = $db->prepare(
            'INSERT INTO referral_commissions (referrer_user_id, referrer_tenant_id, payer_tenant_id, order_id, amount, status)
             VALUES (?, ?, ?, ?, ?, \'pending\')
             ON DUPLICATE KEY UPDATE status = status'
        );
        $insert->execute([(int) $row['user_id'], $row['tenant_id'], $payerTenantId, $orderId, $amount]);
    } catch (Throwable $e) {
        error_log('asaas commission: ' . $e->getMessage());
    }
}

function asaasTablesReady(PDO $db): bool
{
    return (bool) $db->query("SHOW TABLES LIKE 'asaas_orders'")->fetchColumn();
}

try {
    $db = Database::getInstance()->getConnection();
    asaRequireTable($db);
    $action = trim((string) ($_GET['action'] ?? ($_POST['action'] ?? '')));
} catch (Throwable $e) {
    error_log('asaas: db connection: ' . $e->getMessage());
    asaJson(false, ['pending_install' => true], 'O módulo de pagamentos ainda não foi instalado. Execute o arquivo sql/asaas-finance.sql.', 503);
}

if ($action === 'getConfig') {
    $admin = asaRequireAdmin($db);
    $apiKey = asaGetSetting($db, 'asaas_api_key');
    $maskedKey = $apiKey !== '' ? preg_replace('/^(.{6}).+(.{4})$/', '$1***********$2', $apiKey) : '';
    $webhookToken = asaGetSetting($db, 'asaas_webhook_token');
    $maskedToken = $webhookToken !== '' ? preg_replace('/^(.{4}).+(.{4})$/', '$1*****$2', $webhookToken) : '';
    $ordersStmt = $db->prepare('SELECT status, COUNT(*) AS total, COALESCE(SUM(amount_cents), 0) AS value_cents FROM asaas_orders GROUP BY status');
    $ordersStmt->execute();
    $totals = [];
    $valueByStatus = [];
    foreach ($ordersStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $totals[$row['status']] = (int) $row['total'];
        $valueByStatus[$row['status']] = (float) $row['value_cents'] / 100;
    }
    $connected = null;
    try {
        if ($apiKey !== '') {
            $test = asaasTestConnection($db);
            $connected = $test['connected'];
        }
    } catch (Throwable $e) {
        $connected = false;
    }
    $subscriptionTotals = [];
    if (asaSubscriptionsTableReady($db)) {
        foreach ($db->query('SELECT status, COUNT(*) AS total FROM asaas_subscriptions GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $subscriptionTotals[(string) $row['status']] = (int) $row['total'];
        }
    }
    asaJson(true, [
        'api_key_configured' => $apiKey !== '',
        'api_key_masked' => $maskedKey,
        'webhook_token_configured' => $webhookToken !== '',
        'webhook_token_masked' => $maskedToken,
        'environment' => asaEnvironment($db),
        'active' => asaGatewayActive($db),
        'webhook_url' => asaasOriginUrl() . '/webhook-asaas.php',
        'orders_total' => array_sum($totals),
        'orders_by_status' => $totals,
        'orders_value_brl' => $valueByStatus,
        'subscriptions_by_status' => $subscriptionTotals,
        'asaas_connected' => $connected,
    ]);
}

if ($action === 'testConnection') {
    asaRequireAdmin($db);
    try {
        $test = asaasTestConnection($db);
        asaJson(true, [
            'connected' => true,
            'available_balance' => $test['available_balance'],
            'webhook_url' => asaasOriginUrl() . '/webhook-asaas.php',
        ], 'Conexão com o Asaas confirmada. Nenhuma cobrança foi criada.');
    } catch (Throwable $e) {
        error_log('asaas: teste de conexão: ' . $e->getMessage());
        asaJson(false, ['connected' => false], 'O Asaas não confirmou a conexão. Verifique se a chave de Integrações está ativa e tente novamente.');
    }
}

if ($action === 'saveConfig') {
    $admin = asaRequireAdmin($db);
    $input = asaInput();
    $remove = !empty($input['remove']);
    $providedApiKey = trim((string) ($input['api_key'] ?? ''));
    $providedWebhookToken = trim((string) ($input['webhook_token'] ?? ''));
    $environment = (string) ($input['environment'] ?? asaEnvironment($db)) === 'sandbox' ? 'sandbox' : 'production';
    $active = !empty($input['active']) ? '1' : '0';
    if ($remove) {
        $cleared = asaSetSetting($db, 'asaas_api_key', '')
            && asaSetSetting($db, 'asaas_webhook_token', '')
            && asaSetSetting($db, 'asaas_active', '0');
        if (!$cleared || asaGetSetting($db, 'asaas_api_key') !== '') {
            asaJson(false, [], 'Não foi possível confirmar a remoção da configuração Asaas no banco. Tente novamente.');
        }
        asaJson(true, ['api_key_configured' => false, 'webhook_token_configured' => false], 'Configuração de pagamento removida.');
    }
    $apiKey = $providedApiKey !== '' ? $providedApiKey : asaGetSetting($db, 'asaas_api_key');
    $webhookToken = $providedWebhookToken !== '' ? $providedWebhookToken : asaGetSetting($db, 'asaas_webhook_token');
    if ($apiKey === '') {
        asaJson(false, [], 'Informe a chave de API do Asaas para configurar o pagamento.');
    }
    if ($environment === 'sandbox' && str_starts_with($apiKey, '$aact_prod_')) {
        asaJson(false, [], 'Esta chave aparenta ser de produção. Use uma chave do Sandbox para configurar o ambiente de testes.', 422);
    }
    try {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: application/json\r\nUser-Agent: MapsProspector/1.0 (https://prospec.atendo.log.br)\r\naccess_token: {$apiKey}\r\nX-ACCESS-TOKEN: {$apiKey}\r\n",
                'timeout' => 25,
                'ignore_errors' => true,
            ],
        ]);
        $baseUrl = $environment === 'sandbox' ? 'https://api-sandbox.asaas.com/v3' : 'https://api.asaas.com/v3';
        $response = @file_get_contents($baseUrl . asaBalancePath(), false, $context);
        $test = json_decode((string) $response, true);
        if (!is_array($test) || isset($test['errors'][0]['description'])) {
            $error = (string) ($test['errors'][0]['description'] ?? 'A chave informada não foi aceita pelo Asaas. Verifique a chave de Integrações (X-ACCESS-TOKEN).');
            asaJson(false, [], $error);
        }
    } catch (Throwable $e) {
        asaJson(false, [], 'Não foi possível conectar ao Asaas. Confira a chave e a conexão do servidor.');
    }
    if ($webhookToken === '' && asaGetSetting($db, 'asaas_webhook_token') === '') {
        $webhookToken = strtolower(bin2hex(random_bytes(16)));
    }
    $saved = asaSetSetting($db, 'asaas_api_key', $apiKey)
        && asaSetSetting($db, 'asaas_webhook_token', $webhookToken)
        && asaSetSetting($db, 'asaas_environment', $environment)
        && asaSetSetting($db, 'asaas_active', $active);
    $persistedApiKey = asaGetSetting($db, 'asaas_api_key');
    $persistedEnvironment = asaGetSetting($db, 'asaas_environment');
    $persistedActive = asaGetSetting($db, 'asaas_active');
    $maskedToken = $webhookToken !== '' ? preg_replace('/^(.{4}).+(.{4})$/', '$1*****$2', $webhookToken) : '';
    if (!$saved
        || !hash_equals($apiKey, $persistedApiKey)
        || $persistedEnvironment !== $environment
        || $persistedActive !== $active) {
        asaJson(false, [], 'A conexão foi validada, mas não foi possível confirmar a gravação da configuração no banco. Nenhuma ativação foi assumida.');
    }
    asaJson(true, [
        'api_key_configured' => true,
        'webhook_token_configured' => $webhookToken !== '',
        'environment' => $environment,
        'active' => $active === '1',
        'webhook_url' => asaasOriginUrl() . '/webhook-asaas.php',
        'webhook_token' => '',
        'webhook_token_masked' => $maskedToken,
    ], $active === '1' ? 'Pagamento Asaas configurado, validado e ativado.' : 'Configuração Asaas validada e mantida desativada para cobrança.');
}

if ($action === 'create_subscription') {
    $user = asaRequireUser($db);
    $tenantId = (int) ($user['tenant_id'] ?? 0);
    if ($tenantId <= 0 || !in_array((string) $user['profile'], ['admin'], true)) {
        asaJson(false, [], 'A contratação de plano deve ser feita pelo administrador da empresa vinculada à conta.', 403);
    }
    $input = asaInput();
    $planId = max(0, (int) ($input['plan_id'] ?? 0));
    if ($planId <= 0) {
        asaJson(false, [], 'Selecione um plano mensal para continuar.', 422);
    }

    if (class_exists('MapsProspectorAsaasPaymentGateway') && MapsProspectorAsaasPaymentGateway::isOperational($db)) {
        try {
            $created = MapsProspectorAsaasPaymentGateway::createPlan($db, $user, $tenantId, $planId);
            asaJson(true, $created, 'Assinatura criada no Asaas. Abra o link para concluir o primeiro pagamento.');
        } catch (Throwable $e) {
            error_log('asaas automatic subscription creation: ' . $e->getMessage());
            asaJson(false, [], 'O Asaas está configurado, mas não foi possível criar a assinatura agora. Nenhuma solicitação manual foi aberta automaticamente.', 502);
        }
    }

    try {
        $created = mapsCreateManualPaymentRequest($db, $user, $tenantId, 'plan', 0, $planId);
        asaJson(true, $created, !empty($created['alreadyPending'])
            ? 'Já existe uma solicitação manual pendente para este plano.'
            : 'Solicitação de plano enviada para aprovação manual do administrador.');
    } catch (Throwable $e) {
        error_log('manual plan request from asaas endpoint: ' . $e->getMessage());
        asaJson(false, [], 'Não foi possível registrar a solicitação manual agora. Tente novamente.', 500);
    }
}

if ($action === 'subscription_status') {
    $user = asaRequireUser($db);
    $tenantId = (int) ($user['tenant_id'] ?? 0);
    if ($tenantId <= 0) {
        asaJson(true, ['subscription' => null]);
    }
    asaRequireSubscriptionsTable($db);
    $stmt = $db->prepare(
        "SELECT s.plan_slug, s.amount_cents, s.status, s.next_due_date, s.access_until, s.cancelled_at, s.payment_url,
                p.name AS plan_name
           FROM asaas_subscriptions s
           LEFT JOIN plans p ON p.id = s.plan_id
          WHERE s.tenant_id = ? AND COALESCE(s.product_kind, 'prospecting') = 'prospecting'
          ORDER BY s.id DESC LIMIT 1"
    );
    $stmt->execute([$tenantId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($subscription)) {
        asaJson(true, ['subscription' => null]);
    }
    asaJson(true, ['subscription' => [
        'plan_name' => (string) ($subscription['plan_name'] ?? $subscription['plan_slug']),
        'status' => (string) $subscription['status'],
        'amount_brl' => ((int) $subscription['amount_cents']) / 100,
        'next_due_date' => $subscription['next_due_date'],
        'access_until' => $subscription['access_until'],
        'cancelled_at' => $subscription['cancelled_at'],
        'payment_url' => $subscription['payment_url'],
        'can_cancel' => in_array((string) $subscription['status'], ['pending', 'active', 'overdue'], true),
    ]]);
}

if ($action === 'cancel_subscription') {
    $user = asaRequireUser($db);
    $tenantId = (int) ($user['tenant_id'] ?? 0);
    if ($tenantId <= 0 || !in_array((string) $user['profile'], ['admin'], true)) {
        asaJson(false, [], 'O cancelamento deve ser solicitado pelo administrador da empresa.', 403);
    }
    asaRequireSubscriptionsTable($db);
    $stmt = $db->prepare("SELECT * FROM asaas_subscriptions WHERE tenant_id = ? AND COALESCE(product_kind, 'prospecting') = 'prospecting' AND status IN ('pending', 'active', 'overdue') ORDER BY id DESC LIMIT 1");
    $stmt->execute([$tenantId]);
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($subscription)) {
        asaJson(false, [], 'Não há uma assinatura ativa para cancelar.', 404);
    }
    try {
        asaasRequest($db, '/subscriptions/' . rawurlencode((string) $subscription['asaas_subscription_id']), [], 'DELETE');
        $update = $db->prepare("UPDATE asaas_subscriptions SET status = 'cancelled', cancelled_at = NOW() WHERE id = ? AND status IN ('pending', 'active', 'overdue')");
        $update->execute([(int) $subscription['id']]);
        asaJson(true, [
            'access_until' => $subscription['access_until'],
        ], 'Assinatura cancelada. Não haverá nova cobrança; o acesso permanece disponível até o fim do período já pago.');
    } catch (Throwable $e) {
        error_log('asaas subscription cancel: ' . $e->getMessage());
        asaJson(false, [], 'Não foi possível cancelar a assinatura agora. Tente novamente em instantes.', 502);
    }
}

if ($action === 'orders') {
    $admin = asaRequireAdmin($db);
    $input = asaInput();
    $status = in_array((string) ($input['status'] ?? ''), ['pending', 'paid', 'failed', 'canceled', 'unknown'], true) ? (string) $input['status'] : null;
    $tenantId = isset($input['tenant_id']) && $input['tenant_id'] !== '' ? (int) $input['tenant_id'] : null;
    $limit = min(100, max(5, (int) ($input['limit'] ?? 25)));
    $offset = max(0, (int) ($input['offset'] ?? 0));
    $where = [];
    $params = [];
    if ($status !== null) {
        $where[] = 'o.status = :status';
        $params[':status'] = $status;
    }
    if ($tenantId !== null) {
        $where[] = 'o.tenant_id = :tenant_id';
        $params[':tenant_id'] = $tenantId;
    }
    $whereSql = $where !== [] ? 'WHERE ' . implode(' AND ', $where) : '';
    $stmt = $db->prepare("SELECT o.id, o.order_nsu, o.user_id, o.tenant_id, o.amount_cents, o.quantity, o.description,
        o.status, o.asaas_payment_id, o.payment_url, o.created_at, o.paid_at,
        COALESCE(t.name, 'Sem empresa') AS tenant_name, COALESCE(u.name, u.email, 'Usuário') AS user_name
        FROM asaas_orders o
        LEFT JOIN tenants t ON t.id = o.tenant_id
        LEFT JOIN users u ON u.id = o.user_id
        {$whereSql}
        ORDER BY o.id DESC LIMIT {$limit} OFFSET {$offset}");
    $stmt->execute($params);
    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'id' => (int) $row['id'],
            'order_nsu' => (string) $row['order_nsu'],
            'amount_brl' => (float) $row['amount_cents'] / 100,
            'quantity' => (int) $row['quantity'],
            'description' => (string) $row['description'],
            'status' => (string) $row['status'],
            'tenant_name' => (string) $row['tenant_name'],
            'user_name' => (string) $row['user_name'],
            'created_at' => (string) $row['created_at'],
            'paid_at' => $row['paid_at'] !== null ? (string) $row['paid_at'] : null,
        ];
    }
    asaJson(true, ['orders' => $rows, 'limit' => $limit, 'offset' => $offset]);
}

if ($action === 'create') {
    $user = asaRequireUser($db);
    if (!in_array(strtolower((string) $user['profile']), ['super_admin', 'admin'], true)) {
        asaJson(false, [], 'Seu perfil não pode solicitar pagamentos de créditos.', 403);
    }
    $tenantId = (int) ($user['tenant_id'] ?? 0);
    if ($tenantId <= 0) {
        asaJson(false, [], 'Solicite os créditos pela empresa vinculada à sua conta.', 422);
    }
    $input = asaInput();
    $quantity = isset($input['quantity']) ? max(1, (int) $input['quantity']) : 100;
    if ($quantity < 100 || $quantity > 10000) {
        asaJson(false, [], 'Informe uma quantidade entre 100 e 10.000 créditos.', 422);
    }

    if (class_exists('MapsProspectorAsaasPaymentGateway') && MapsProspectorAsaasPaymentGateway::isOperational($db)) {
        try {
            $result = MapsProspectorAsaasPaymentGateway::createCredits($db, $user, $tenantId, $quantity);
            asaJson(true, $result, 'Cobrança criada no Asaas. Abra o link para concluir o pagamento.');
        } catch (Throwable $e) {
            error_log('asaas automatic credit creation: ' . $e->getMessage());
            asaJson(false, [], 'O Asaas está configurado, mas não foi possível criar a cobrança agora. Nenhuma solicitação manual foi aberta automaticamente.', 502);
        }
    }

    try {
        $result = mapsCreateManualPaymentRequest($db, $user, $tenantId, 'credits', $quantity, 0);
        asaJson(true, $result, !empty($result['alreadyPending'])
            ? 'Já existe uma solicitação manual pendente para esta recarga.'
            : 'Solicitação de créditos enviada para aprovação manual do administrador.');
    } catch (Throwable $e) {
        error_log('manual credit request from asaas endpoint: ' . $e->getMessage());
        asaJson(false, [], 'Não foi possível registrar a solicitação manual agora. Tente novamente.', 500);
    }
}

if ($action === 'done') {
    $user = asaRequireUser($db);
    $orderNsu = trim((string) ($_GET['order_nsu'] ?? ''));
    if ($orderNsu === '') {
        asaJson(false, [], 'Pedido não identificado. Acesse a central de créditos do sistema.', 422);
    }
    $stmt = $db->prepare('SELECT * FROM asaas_orders WHERE order_nsu = ? LIMIT 1');
    $stmt->execute([$orderNsu]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($order)) {
        asaJson(false, [], 'Pedido não encontrado.', 404);
    }
    if ((string) $order['status'] === 'paid') {
        asaJson(true, ['status' => 'paid', 'order_nsu' => $orderNsu], 'Pagamento confirmado. Seus créditos já estão disponíveis.');
    }
    $check = null;
    if ((string) $order['status'] === 'pending' && (string) ($order['asaas_payment_id'] ?? '') !== '') {
        $check = asaasFetchPayment($db, (string) $order['asaas_payment_id']);
    }
    if (is_array($check) && in_array((string) ($check['status'] ?? ''), ['RECEIVED', 'CONFIRMED', 'SETTLED'], true)) {
        asaasMarkPaid($db, $order, $check);
        $order['status'] = 'paid';
    }
    asaJson(
        true,
        ['status' => (string) ($order['status'] ?? 'unknown'), 'order_nsu' => $orderNsu],
        (string) ($order['status'] ?? '') === 'paid'
            ? 'Pagamento confirmado. Seus créditos já estão disponíveis.'
            : 'Pagamento ainda em processamento. Os créditos serão liberados assim que a confirmação chegar.'
    );
}

asaJson(false, [], 'Ação não reconhecida.', 400);
