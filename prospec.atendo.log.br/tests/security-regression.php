<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

function expectSame($expected, $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "Falha: {$message}\n");
        exit(1);
    }
}

expectSame('https://8.8.8.8/hooks/leads', validateWebhookUrl('https://8.8.8.8/hooks/leads/'), 'aceita destino HTTPS público');
expectSame(null, validateWebhookUrl('http://127.0.0.1/internal'), 'bloqueia loopback');
expectSame(null, validateWebhookUrl('http://10.0.0.10/internal'), 'bloqueia endereço privado');
expectSame(null, validateWebhookUrl('file:///etc/passwd'), 'bloqueia esquema não HTTP');

if (extension_loaded('openssl')) {
    try {
        encryptApikey('regression-test-token');
        fwrite(STDERR, "Falha: rejeita criptografia sem chave configurada\n");
        exit(1);
    } catch (RuntimeException $error) {
    }
    define('ENCRYPTION_KEY', random_bytes(32));
    $encrypted = encryptApikey('regression-test-token');
    expectSame('regression-test-token', decryptApikey($encrypted), 'criptografa e descriptografa API key');
}

fwrite(STDOUT, "Testes de regressão de segurança concluídos.\n");
