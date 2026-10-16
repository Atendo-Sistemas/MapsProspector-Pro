<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/asaas-secrets.php';

/**
 * Gateway privado para cobranças Asaas disparadas pelo fluxo de compra.
 * Não expõe a API key, não é carregado no frontend e não cai para aprovação
 * manual quando o Asaas está ativo mas a chamada falha.
 */
final class MapsProspectorAsaasPaymentGateway
{
    public static function isOperational(PDO $db): bool
    {
        return self::setting($db, 'asaas_active') === '1'
            && self::setting($db, 'asaas_api_key') !== ''
            && self::setting($db, 'asaas_webhook_token') !== ''
            && self::tableReady($db, 'asaas_orders')
            && self::tableReady($db, 'asaas_subscriptions');
    }

    public static function creditPrice(PDO $db): float
    {
        $raw = '';
        if (function_exists('getPlatformSetting')) {
            $raw = trim((string) (getPlatformSetting($db, 'credit_price_avulso') ?? ''));
        }
        $raw = str_replace(',', '.', $raw);
        $price = is_numeric($raw) ? (float) $raw : 0.0;
        return $price > 0 ? $price : 0.10;
    }

    public static function createCredits(PDO $db, array $user, int $tenantId, int $credits): array
    {
        if ($credits < 100 || $credits > 10000) {
            throw new RuntimeException('A quantidade de créditos está fora do limite permitido.');
        }
        $amountCents = (int) round(self::creditPrice($db) * $credits * 100);
        if ($amountCents < 1) {
            throw new RuntimeException('O preço dos créditos ainda não está configurado.');
        }
        $description = 'Recarga de ' . $credits . ' créditos extras';
        $orderNsu = 'MP-' . strtolower(bin2hex(random_bytes(8))) . '-' . time();
        $insert = $db->prepare(
            'INSERT INTO asaas_orders (order_nsu, user_id, tenant_id, amount_cents, quantity, description, status)
             VALUES (?, ?, ?, ?, ?, ?, \'pending\')'
        );
        $insert->execute([$orderNsu, (int) $user['id'], $tenantId, $amountCents, $credits, $description]);
        $orderId = (int) $db->lastInsertId();

        try {
            $customerId = self::ensureCustomer($db, $user);
            $charge = self::request($db, '/payments', [
                'customer' => $customerId,
                'billingType' => 'UNDEFINED',
                'value' => round($amountCents / 100, 2),
                'dueDate' => (new DateTimeImmutable('+7 days'))->format('Y-m-d'),
                'description' => mb_substr($description, 0, 400),
                'externalReference' => $orderNsu,
            ]);
            $paymentId = trim((string) ($charge['id'] ?? ''));
            $paymentUrl = trim((string) ($charge['invoiceUrl'] ?? $charge['invoiceLink'] ?? ''));
            if ($paymentUrl === '' && $paymentId !== '') {
                $paymentUrl = self::paymentUrl($db, $paymentId);
            }
            $update = $db->prepare('UPDATE asaas_orders SET asaas_payment_id = ?, asaas_customer_id = ?, payment_url = ? WHERE id = ? LIMIT 1');
            $update->execute([$paymentId !== '' ? $paymentId : null, $customerId !== '' ? $customerId : null, $paymentUrl !== '' ? $paymentUrl : null, $orderId]);
            return [
                'mode' => 'asaas',
                'payment_url' => $paymentUrl,
                'payment_id' => $paymentId,
                'order_nsu' => $orderNsu,
                'amount_brl' => $amountCents / 100,
            ];
        } catch (Throwable $e) {
            $db->prepare("UPDATE asaas_orders SET status = 'failed' WHERE id = ? AND status = 'pending' LIMIT 1")
                ->execute([$orderId]);
            throw $e;
        }
    }

    public static function createPlan(PDO $db, array $user, int $tenantId, int $planId): array
    {
        $existing = $db->prepare("SELECT id FROM asaas_subscriptions WHERE tenant_id = ? AND COALESCE(product_kind, 'prospecting') = 'prospecting' AND status IN ('pending', 'active', 'overdue') ORDER BY id DESC LIMIT 1");
        $existing->execute([$tenantId]);
        if ($existing->fetchColumn()) {
            throw new RuntimeException('Já existe uma assinatura em andamento para esta empresa. Cancele-a antes de solicitar outro plano.');
        }

        $planStmt = $db->prepare("SELECT id, name, slug, price_monthly, period, status, catalog_group FROM plans WHERE id = ? AND COALESCE(catalog_group, 'current') <> 'crm_addon' LIMIT 1");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($plan) || (string) ($plan['status'] ?? '') !== 'active') {
            throw new RuntimeException('O plano selecionado não está disponível.');
        }
        $amountCents = (int) round(((float) ($plan['price_monthly'] ?? 0)) * 100);
        if ($amountCents <= 0 || (string) ($plan['period'] ?? 'monthly') !== 'monthly') {
            throw new RuntimeException('O plano selecionado não possui cobrança mensal válida.');
        }

        $customerId = self::ensureCustomer($db, $user);
        $subscription = self::request($db, '/subscriptions', [
            'customer' => $customerId,
            'billingType' => 'UNDEFINED',
            'value' => round($amountCents / 100, 2),
            'nextDueDate' => date('Y-m-d'),
            'cycle' => 'MONTHLY',
            'description' => mb_substr('Assinatura mensal — ' . (string) $plan['name'], 0, 400),
            'externalReference' => 'MP-SUB-' . $tenantId . '-' . (int) $plan['id'],
        ]);
        $subscriptionId = trim((string) ($subscription['id'] ?? ''));
        if ($subscriptionId === '') {
            throw new RuntimeException('O Asaas não retornou a identificação da assinatura.');
        }
        $paymentUrl = trim((string) ($subscription['invoiceUrl'] ?? $subscription['invoiceLink'] ?? ''));
        if ($paymentUrl === '') {
            $paymentUrl = self::subscriptionPaymentUrl($db, $subscriptionId);
        }
        $nextDueDate = trim((string) ($subscription['nextDueDate'] ?? date('Y-m-d')));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nextDueDate)) {
            $nextDueDate = date('Y-m-d');
        }
        $insert = $db->prepare(
            "INSERT INTO asaas_subscriptions
             (tenant_id, user_id, plan_id, plan_slug, product_kind, amount_cents, asaas_customer_id, asaas_subscription_id, payment_url, status, next_due_date)
             VALUES (?, ?, ?, ?, 'prospecting', ?, ?, ?, ?, 'pending', ?)"
        );
        $insert->execute([(int) $tenantId, (int) $user['id'], (int) $plan['id'], (string) $plan['slug'], $amountCents, $customerId, $subscriptionId, $paymentUrl !== '' ? $paymentUrl : null, $nextDueDate]);
        return [
            'mode' => 'asaas',
            'payment_url' => $paymentUrl,
            'subscription_id' => $subscriptionId,
            'plan_name' => (string) $plan['name'],
            'amount_brl' => $amountCents / 100,
        ];
    }

    public static function createCrmAddon(PDO $db, array $user, int $tenantId, int $planId): array
    {
        $existing = $db->prepare("SELECT id FROM asaas_subscriptions WHERE tenant_id = ? AND product_kind = 'crm_addon' AND status IN ('pending', 'active', 'overdue') ORDER BY id DESC LIMIT 1");
        $existing->execute([$tenantId]);
        if ($existing->fetchColumn()) {
            throw new RuntimeException('Já existe uma assinatura de CRM em andamento para esta empresa.');
        }
        $planStmt = $db->prepare("SELECT id, name, slug, price_monthly, period, status, catalog_group FROM plans WHERE id = ? AND catalog_group = 'crm_addon' LIMIT 1");
        $planStmt->execute([$planId]);
        $plan = $planStmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($plan) || (string) ($plan['status'] ?? '') !== 'active') {
            throw new RuntimeException('A oferta de CRM selecionada não está disponível.');
        }
        $amountCents = (int) round(((float) ($plan['price_monthly'] ?? 0)) * 100);
        if ($amountCents <= 0 || (string) ($plan['period'] ?? 'monthly') !== 'monthly') {
            throw new RuntimeException('A oferta de CRM não possui cobrança mensal válida.');
        }
        $customerId = self::ensureCustomer($db, $user);
        $subscription = self::request($db, '/subscriptions', [
            'customer' => $customerId,
            'billingType' => 'UNDEFINED',
            'value' => round($amountCents / 100, 2),
            'nextDueDate' => date('Y-m-d'),
            'cycle' => 'MONTHLY',
            'description' => mb_substr('CRM adicional — ' . (string) $plan['name'], 0, 400),
            'externalReference' => 'MP-CRM-SUB-' . $tenantId . '-' . (int) $plan['id'],
        ]);
        $subscriptionId = trim((string) ($subscription['id'] ?? ''));
        if ($subscriptionId === '') throw new RuntimeException('O Asaas não retornou a identificação da assinatura CRM.');
        $paymentUrl = trim((string) ($subscription['invoiceUrl'] ?? $subscription['invoiceLink'] ?? ''));
        if ($paymentUrl === '') $paymentUrl = self::subscriptionPaymentUrl($db, $subscriptionId);
        $nextDueDate = trim((string) ($subscription['nextDueDate'] ?? date('Y-m-d')));
        if (!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $nextDueDate)) $nextDueDate = date('Y-m-d');
        $insert = $db->prepare("INSERT INTO asaas_subscriptions
             (tenant_id, user_id, plan_id, plan_slug, product_kind, amount_cents, asaas_customer_id, asaas_subscription_id, payment_url, status, next_due_date)
             VALUES (?, ?, ?, ?, 'crm_addon', ?, ?, ?, ?, 'pending', ?)");
        $insert->execute([(int) $tenantId, (int) $user['id'], (int) $plan['id'], (string) $plan['slug'], $amountCents, $customerId, $subscriptionId, $paymentUrl !== '' ? $paymentUrl : null, $nextDueDate]);
        return [
            'mode' => 'asaas',
            'payment_url' => $paymentUrl,
            'subscription_id' => $subscriptionId,
            'addon_name' => (string) $plan['name'],
            'amount_brl' => $amountCents / 100,
            'preserves_main_plan' => true,
        ];
    }

    private static function tableReady(PDO $db, string $table): bool
    {
        $safe = str_replace('`', '', $table);
        try {
            return (bool) $db->query("SHOW TABLES LIKE '" . $safe . "'")->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function setting(PDO $db, string $key): string
    {
        if (asaSecretKeyAllowed($key)) {
            return asaSecretGetOrMigrate($db, $key);
        }
        try {
            $stmt = $db->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            return trim((string) ($stmt->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            error_log('asaas gateway setting: ' . $e->getMessage());
            return '';
        }
    }

    private static function baseUrl(PDO $db): string
    {
        return self::setting($db, 'asaas_environment') === 'sandbox'
            ? 'https://api-sandbox.asaas.com/v3'
            : 'https://api.asaas.com/v3';
    }

    private static function request(PDO $db, string $path, array $body, string $method = 'POST'): array
    {
        $apiKey = self::setting($db, 'asaas_api_key');
        if ($apiKey === '') {
            throw new RuntimeException('Chave Asaas não configurada.');
        }
        $headers = "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: MapsProspector/1.0 (https://prospec.atendo.log.br)\r\naccess_token: {$apiKey}\r\nX-ACCESS-TOKEN: {$apiKey}\r\n";
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $method === 'GET' ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $response = @file_get_contents(self::baseUrl($db) . $path, false, $context);
        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Resposta inválida da API do Asaas.');
        }
        if (isset($decoded['errors'][0]['description'])) {
            throw new RuntimeException((string) $decoded['errors'][0]['description']);
        }
        return $decoded;
    }

    private static function ensureCustomer(PDO $db, array $user): string
    {
        $email = trim((string) ($user['email'] ?? ''));
        if ($email === '') {
            throw new RuntimeException('O usuário responsável precisa ter um e-mail cadastrado antes do pagamento.');
        }
        $found = self::request($db, '/customers?email=' . rawurlencode($email) . '&limit=1', [], 'GET');
        $first = is_array($found['data'] ?? null) ? ($found['data'][0] ?? null) : null;
        if (is_array($first) && !empty($first['id'])) {
            return (string) $first['id'];
        }
        $customer = self::request($db, '/customers', [
            'name' => mb_substr(trim((string) ($user['name'] ?? 'Cliente da plataforma')) ?: 'Cliente da plataforma', 0, 120),
            'email' => $email,
        ]);
        $customerId = trim((string) ($customer['id'] ?? ''));
        if ($customerId === '') {
            throw new RuntimeException('O Asaas não retornou a identificação do cliente.');
        }
        return $customerId;
    }

    private static function paymentUrl(PDO $db, string $paymentId): string
    {
        $payment = self::request($db, '/payments/' . rawurlencode($paymentId), [], 'GET');
        return trim((string) ($payment['invoiceUrl'] ?? $payment['invoiceLink'] ?? ''));
    }

    private static function subscriptionPaymentUrl(PDO $db, string $subscriptionId): string
    {
        $payments = self::request($db, '/payments?subscription=' . rawurlencode($subscriptionId) . '&limit=1', [], 'GET');
        $first = is_array($payments['data'] ?? null) ? ($payments['data'][0] ?? null) : null;
        return is_array($first) ? trim((string) ($first['invoiceUrl'] ?? $first['invoiceLink'] ?? '')) : '';
    }

    private static function originUrl(): string
    {
        $forwardedProto = strtolower(trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        $scheme = in_array($forwardedProto, ['https', 'http'], true)
            ? $forwardedProto
            : ((!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ? 'https' : 'http');
        $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = preg_replace('/[^a-zA-Z0-9.\-:]/', '', (string) $host);
        return rtrim($scheme . '://' . $host, '/');
    }
}
