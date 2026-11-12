<?php
/**
 * Criação de Link de Pagamento InfinitePay — Versão AUTOCONTIDA
 * Substitua: api/infinitepay-create-link.php
 *
 * Não depende de config/config.php, config/database.php
 * ou includes/functions.php. Ajuste as constantes abaixo.
 */

header('Content-Type: application/json; charset=utf-8');

// ============================================
// CONFIGURAÇÃO — AJUSTE AQUI
// ============================================
define('IP_HANDLE', 'ivan-jacomassi');            // Sua InfiniteTag (sem o $)
define('IP_DB_HOST', 'localhost');                // Host do MySQL
define('IP_DB_NAME', 'naciona4_maps1243');        // Nome do banco
define('IP_DB_USER', 'naciona4_maps1243');                          // Usuário MySQL (ex.: naciona4_maps)
define('IP_DB_PASS', 'naciona4_maps1243');                          // Senha MySQL
define('IP_SITE_URL', 'https://prospec.atendo.log.br'); // URL pública do site (HTTPS)

// ============================================
// INÍCIO DO SCRIPT
// ============================================

$db = null;
try {
    $dsn = 'mysql:host=' . IP_DB_HOST . ';dbname=' . IP_DB_NAME . ';charset=utf8mb4';
    $db = new PDO($dsn, IP_DB_USER, IP_DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro de conexão com o banco: ' . $e->getMessage()]);
    exit;
}

// --- 1. Recebe os dados do pedido ---
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$tenantId    = (int)($input['tenant_id'] ?? 0);
$type        = $input['type'] ?? ''; // 'credits' | 'plan'
$credits     = (int)($input['credits'] ?? 0);
$planId      = (int)($input['plan_id'] ?? 0);
$totalCents  = (int)($input['total_cents'] ?? 0); // R$ 10,00 = 1000 centavos
$description = $input['description'] ?? 'Pagamento Atendo Sistemas';

if (!$tenantId || !$totalCents || !in_array($type, ['credits', 'plan'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos. Envie tenant_id, type (credits|plan) e total_cents (em centavos).']);
    exit;
}
if (($type === 'credits' && $credits <= 0) || ($type === 'plan' && $planId <= 0)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Informe credits (>0) para créditos ou plan_id (>0) para planos.']);
    exit;
}

// --- 2. Cria o pagamento pendente ---
$orderNsu = 'PAY_' . time() . '_' . substr(bin2hex(random_bytes(3)), 0, 6);

$stmt = $db->prepare(
    "INSERT INTO payments (tenant_id, type, credits, plan_id, amount_cents, external_id, status, created_at)
     VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())"
);
$stmt->execute([$tenantId, $type, $credits, $planId ?: null, $totalCents, $orderNsu]);
$paymentId = $db->lastInsertId();

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

// Dados do cliente (opcional)
try {
    $t = $db->prepare("SELECT name, email, phone FROM tenants WHERE id = ?");
    $t->execute([$tenantId]);
    $td = $t->fetch(PDO::FETCH_ASSOC);
    if ($td) {
        $payload['customer'] = ['name' => $td['name'], 'email' => $td['email']];
        if (!empty($td['phone'])) {
            $payload['customer']['phone_number'] = $td['phone'];
        }
    }
} catch (Throwable $e) {
    error_log('Create-link: não foi possível ler dados do tenant — ' . $e->getMessage());
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
    http_response_code(502);
    echo json_encode([
        'success'    => false,
        'error'      => 'Erro ao criar o link de pagamento na InfinitePay.',
        'payment_id' => $paymentId,
    ]);
    exit;
}

$apiData   = json_decode($resp, true);
$checkoutUrl = $apiData['url'] ?? $apiData['checkout_url'] ?? ($apiData['data']['url'] ?? null);

if (!$checkoutUrl) {
    error_log('InfinitePay respondeu sem URL: ' . $resp);
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => 'A InfinitePay não retornou um link de checkout.']);
    exit;
}

echo json_encode([
    'success'      => true,
    'payment_id'   => $paymentId,
    'order_nsu'    => $orderNsu,
    'checkout_url' => $checkoutUrl,
    'message'      => 'Redirecione o cliente para checkout_url.',
]);
exit;
