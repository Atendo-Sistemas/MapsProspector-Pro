<?php
declare(strict_types=1);

require_once __DIR__ . '/../services/AsaasSubscriptionService.php';

$cases = [
    ['2026-10-16', '2026-11-16', '2026-11-15'],
    ['2026-01-31', '2026-02-28', '2026-02-27'],
    ['2024-01-31', '2024-02-29', '2024-02-28'],
];

foreach ($cases as [$paymentDue, $nextDue, $accessUntil]) {
    if (mpAsaasNextDueDate($paymentDue) !== $nextDue) {
        fwrite(STDERR, "Falha ao calcular o próximo vencimento para {$paymentDue}.\n");
        exit(1);
    }
    if (mpAsaasAccessUntil($paymentDue) !== $accessUntil) {
        fwrite(STDERR, "Falha ao calcular o fim do acesso para {$paymentDue}.\n");
        exit(1);
    }
}

fwrite(STDOUT, "Testes de regressão de cobrança concluídos.\n");
