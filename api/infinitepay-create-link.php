<?php
/**
 * Criação de Link de Pagamento InfinitePay — Versão v2 (padrão real do sistema)
 * Substitua: /home/naciona4/public_html/api/infinitepay-create-link.php
 *
 * Padrão detectado nos pagamentos existentes:
 *  - user_id: ID real da tabela users (NOT NULL) — OBRIGATÓRIO no payload
 *  - external_id: "INF_{id_do_payment}"
 *  - payment_method: "pix" (aqui deixamos genérico pois InfinitePay aceita card+pix)
 *  - amount: valor em reais (ex.: "30.00")
 *
 * Como chamar:
 * POST /api/infinitepay-create-link.php
 * {
 *   "tenant_id": 12,
 *   "user_id": 19,          <-- OBRIGATÓRIO: usuário da tabela users
 *   "type": "credits",      <-- "credits" ou "plan"
 *   "credits": 100,         <-- obrigatório se type=credits
 *   "plan_id": 4,           <-- obrigatório se type=plan
 *   "total_cents": 3000,    <-- valor em centavos (R$ 30,00 = 3000)
 *   "description": "Compra de créditos"
 * }
 */

header('Content-Type: application/json; charset=utf-8');
error_reporting(0);
ini_set('display_errors', 0);

// ============================================
// CONFIGURAÇÃO
// ============================================
define('IP_HANDLE', 'ivan-jacomassi');
define('IP_DB_HOST', 'localhost');
define('IP_DB_NAME', 'naciona4_maps1243');
define('IP_DB_USER', 'naciona4_maps1243');
define('IP_DB_PASS', 'naciona4_maps1243');
define('IP_SITE_URL', 'https://prospec.atendo.log.br');

// Resposta segura — nunca corpo vazio
function ipResponde($sucesso, $mensagem, $http = 200, $extra = []) {
    http_response_code($http);
    $resp = ['success' => $sucesso] + $extra;
    $resp[$sucesso ? 'message' : 'error'] = $mensagem;
    echo json_encode($resp);
    exit;
}

// --- Conexão com o banco ---
try {
    $dsn = 'mysql:host=' . IP_DB_HOST . ';dbname=' . IP_DB_NAME . ';charset=utf8mb4';
    $db = new PDO($dsn, IP_DB_USER, IP_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    ipResponde(false, 'Erro de conexão com o banco: ' . $e->getMessage(), 500);
}

// --- 1. Recebe e valida os dados do pedido ---
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$tenantId    = (int)($input['tenant_id'] ?? 0);
$userId      = (int)($input['user_id'] ?? 0);   // OBRIGATÓRIO — tabela users NOT NULL
$type        = $input['type'] ?? '';             // 'credits' | 'plan'
$credits     = (int)($input['credits'] ?? 0);
$planId      = (int)($input['plan_id'] ?? 0);
$totalCents  = (int)($input['total_cents'] ?? 0); // R$ 30,00 = 3000
$description = $input['description'] ?? 'Pagamento Atendo Sistemas';

if (!$tenantId || !$userId || !$totalCents || !in_array($type, ['credits', 'plan'])) {
    ipResponde(false, 'Parâmetros inválidos. Envie tenant_id, user_id, type (credits|plan) e total_cents (em centavos).', 400);
}
if (($type === 'credits' && $credits <= 0) || ($type === 'plan' && $planId <= 0)) {
    ipResponde(false, 'Informe credits (>0) para créditos ou plan_id (>0) para planos.', 400);
}

// Valida que o usuário pertence ao tenant (ou é admin global)
try {
    $u = $db->prepare("SELECT id, tenant_id, profile FROM users WHERE id = ?");
    $u->execute([$userId]);
    $userData = $u->fetch(PDO::FETCH_ASSOC);
    if (!$userData) {
        ipResponde(false, "O usuário {$userId} não existe.", 400);
    }
    if ($userData['tenant_id'] !== null && (int)$userData['tenant_id'] !== $tenantId && $userData['profile'] !== 'super_admin') {
        ipResponde(false, 'O usuário não pertence a este tenant.', 400);
    }
} catch (Throwable $e) {
    error_log('Create-link: falha ao validar usuário — ' . $e->getMessage());
}

// --- 2. Cria o pagamento pendente (padrão do sistema: external_id = INF_{id}) ---
$stmt = $db->prepare(
    "INSERT INTO payments
     (tenant_id, user_id, type, credits, plan_id, amount, amount_cents,
      payment_method, status, external_id, created_at)
     VALUES (?, ?, ?, ?, ?, ?, ?, 'pix', 'pending', 'INF_PLACEHOLDER', NOW())"
);
$stmt->execute([
    $tenantId,
    $userId,
    $type,
    $credits ?: null,
    $planId ?: null,
    number_format($totalCents / 100, 2, '.', ''), // coluna amount em reais (ex.: "30.00")
    $totalCents,                                  // coluna amount_cents
]);
$paymentId = (int)$db->lastInsertId();

// Atualiza external_id para o padrão "INF_{id}"
$db->prepare("UPDATE payments SET external_id = ? WHERE id = ?")->execute(["INF_{$paymentId}", $paymentId]);
$orderNsu = "INF_{$paymentId}"; // usa o mesmo external_id como order_nsu ( rastreável )

// --- 3. Monta o payload para a InfinitePay ---
$payload = [
    'handle'    => IP_HANDLE,
    'items'     => [
        [
            'quantity'    => 1,
            'price'       => $totalCents,
            'description' => $description,
        ],
    ],
    'order_nsu'     => $orderNsu,
    'redirect_url'  => rtrim(IP_SITE_URL, '/') . '/pagamento-concluido.php',
    'webhook_url'   => rtrim(IP_SITE_URL, '/') . '/api/infinitepay-webhook.php',
];

// Dados do cliente (opcional — agiliza o checkout)
try {
    $t = $db->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $t->execute([$userId]);
    $ud = $t->fetch(PDO::FETCH_ASSOC);
    if ($ud) {
        $payload['customer'] = ['name' => $ud['name']];
        if (!empty($ud['email'])) {
            $payload['customer']['email'] = $ud['email'];
        }
        if (!empty($ud['phone'])) {
            $payload['customer']['phone_number'] = '+55' . ltrim($ud['phone'], '+55');
        }
    }
} catch (Throwable $e) {
    error_log('Create-link: não foi possível ler dados do usuário — ' . $e->getMessage());
}

// --- 4. Chama a API da InfinitePay ---
$ch = curl_init('https://api.checkout.infinitepay.io/links');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 20,
]);
$resp     = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($httpCode !== 200 || !$resp) {
    error_log("InfinitePay create-link falhou (HTTP {$httpCode}): {$curlErr} | " . $resp);
    ipResponde(false, 'Erro ao criar o link de pagamento na InfinitePay. Tente novamente.', 502, ['payment_id' => $paymentId]);
}

$apiData     = json_decode($resp, true);
$checkoutUrl = $apiData['url'] ?? null;

if (!$checkoutUrl) {
    error_log('InfinitePay respondeu sem URL: ' . $resp);
    ipResponde(false, 'A InfinitePay não retornou um link de checkout.', 502, ['payment_id' => $paymentId]);
}

ipResponde(true, 'Redirecione o cliente para checkout_url.', 200, [
    'payment_id'   => $paymentId,
    'order_nsu'    => $orderNsu,
    'checkout_url' => $checkoutUrl,
]);
