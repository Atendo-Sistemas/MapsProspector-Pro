<?php
/**
 * Página de retorno após o pagamento (redirect_url configurada na criação do link)
 * Endpoint: /pagamento-concluido.php
 *
 * A InfinitePay redireciona o cliente aqui com os parâmetros:
 *  - receipt_url      -> Link do comprovante de pagamento
 *  - order_nsu        -> Número do pedido no seu sistema
 *  - slug             -> Código da fatura na InfinitePay
 *  - capture_method   -> "credit_card" ou "pix"
 *  - transaction_nsu  -> ID único da transação
 *
 * Como o clique em "Continuar" não é garantido, o webhook
 * (/api/infinitepay-webhook.php) é o mecanismo oficial de confirmação.
 * Esta página apenas verifica/consulta o status e mostra o resultado.
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$db = Database::getInstance()->getConnection();

$receiptUrl    = $_GET['receipt_url'] ?? null;
$orderNsu      = $_GET['order_nsu'] ?? null;
$slug          = $_GET['slug'] ?? null;
$transactionNsu = $_GET['transaction_nsu'] ?? null;
$handle        = getPlatformSetting($db, 'infinitepay_handle') ?: 'ivan-jacomassi';

$statusLabel = 'aguardando';
$color       = '#f59e0b'; // laranja
$message     = 'Estamos processando seu pagamento. Em instantes ele será liberado automaticamente.';

if ($orderNsu) {
    // Consulta oficial de status (mesma lógica do webhook, para dar feedback imediato)
    try {
        $ch = curl_init('https://api.checkout.infinitepay.io/payment_check');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode([
                'handle'            => $handle,
                'order_nsu'         => $orderNsu,
                'transaction_nsu'   => $transactionNsu,
                'slug'              => $slug,
            ]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($resp, true);
        $paid = ($data['paid'] ?? false) === true && ($data['success'] ?? false) === true;

        if ($paid) {
            // Ativa o pedido em background (opcional: delega ao webhook)
            $stmt = $db->prepare(
                "UPDATE payments SET status = 'approved',
                 external_ref = ?, receipt_url = ?,
                 capture_method = ?, updated_at = NOW()
                 WHERE (external_id = ? OR id = ?) AND status = 'pending'"
            );
            $stmt->execute([
                $transactionNsu, $receiptUrl, $data['capture_method'] ?? null,
                $orderNsu, (is_numeric($orderNsu) ? (int)$orderNsu : null),
            ]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC)
                ?: ($db->prepare("SELECT tenant_id, credits, plan_id FROM payments WHERE external_id = ? OR id = ?")->execute([$orderNsu, is_numeric($orderNsu) ? (int)$orderNsu : null]) && $stmt->fetch(PDO::FETCH_ASSOC));

            $statusLabel = 'aprovado';
            $color       = '#16a34a'; // verde
            $message     = 'Seu pagamento foi confirmado! Agradecemos a confiança.';
        } else {
            $statusLabel = 'pendente';
            $color       = '#f59e0b';
            $message     = 'Pagamento ainda não confirmado. Aguarde alguns instantes — a liberação é automática.';
        }
    } catch (Throwable $e) {
        error_log('Erro ao consultar payment_check: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagamento <?php echo ucfirst($statusLabel); ?> — Atendo Sistemas</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; background: #f8fafc; display: flex;
               align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 16px; padding: 40px; max-width: 440px; width: 90%;
                box-shadow: 0 10px 30px rgba(0,0,0,.08); text-align: center; }
        .badge { display: inline-block; padding: 8px 20px; border-radius: 999px; color: #fff;
                 font-weight: 600; background: <?php echo $color; ?>; }
        h1 { color: #0f172a; margin: 20px 0 8px; }
        p { color: #475569; line-height: 1.6; }
        .btn { display: inline-block; margin-top: 24px; padding: 12px 28px; border-radius: 10px;
               background: #0f172a; color: #fff; text-decoration: none; font-weight: 600; }
        .small { font-size: 12px; color: #94a3b8; margin-top: 28px; word-break: break-all; }
    </style>
</head>
<body>
<div class="card">
    <span class="badge"><?php echo ucfirst($statusLabel); ?></span>
    <h1>Pagamento <?php echo $statusLabel === 'aprovado' ? 'confirmado!' : 'em processamento' ?></h1>
    <p><?php echo $message; ?></p>
    <?php if ($receiptUrl): ?>
        <a class="btn" href="<?php echo htmlspecialchars($receiptUrl); ?>" target="_blank">Ver comprovante</a>
    <?php else: ?>
        <a class="btn" href="/app">Acessar o sistema</a>
    <?php endif; ?>
    <?php if ($orderNsu): ?>
        <p class="small">Pedido: <?php echo htmlspecialchars($orderNsu); ?></p>
    <?php endif; ?>
</div>
</body>
</html>
