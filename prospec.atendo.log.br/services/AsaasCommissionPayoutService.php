<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/asaas-secrets.php';

/**
 * Transferências de comissões para o Asaas.
 *
 * A classe nunca decide sozinha que um saque pode ser pago: o endpoint chama
 * este serviço somente após aprovação explícita do super administrador.
 */
final class MapsProspectorAsaasCommissionPayoutService
{
    private const TABLE = 'referral_withdrawal_transfers';

    public static function isOperational(PDO $db): bool
    {
        return self::setting($db, 'asaas_active') === '1'
            && self::setting($db, 'asaas_api_key') !== ''
            && self::setting($db, 'asaas_webhook_token') !== ''
            && self::tableReady($db, self::TABLE);
    }

    public static function ensureTable(PDO $db): bool
    {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS referral_withdrawal_transfers (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                withdrawal_id BIGINT UNSIGNED NOT NULL,
                external_reference VARCHAR(128) NOT NULL,
                asaas_transfer_id VARCHAR(128) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'CREATING',
                amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
                pix_key_type VARCHAR(16) NOT NULL,
                pix_key VARCHAR(160) NOT NULL,
                transfer_fee_cents INT UNSIGNED NULL,
                net_amount_cents INT UNSIGNED NULL,
                transaction_receipt_url VARCHAR(500) NULL,
                fail_reason VARCHAR(500) NULL,
                raw_response_json JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_withdrawal_transfer_withdrawal (withdrawal_id),
                UNIQUE KEY uq_withdrawal_transfer_reference (external_reference),
                KEY idx_withdrawal_transfer_asaas_id (asaas_transfer_id),
                KEY idx_withdrawal_transfer_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $db->exec("CREATE TABLE IF NOT EXISTS referral_withdrawal_commissions (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                withdrawal_id BIGINT UNSIGNED NOT NULL,
                commission_id BIGINT UNSIGNED NOT NULL,
                amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uq_withdrawal_commission (withdrawal_id, commission_id),
                UNIQUE KEY uq_withdrawal_commission_once (commission_id),
                KEY idx_withdrawal_commission_withdrawal (withdrawal_id),
                KEY idx_withdrawal_commission_commission (commission_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            return true;
        } catch (Throwable $e) {
            error_log('asaas commission transfer table: ' . $e->getMessage());
            return false;
        }
    }

    public static function process(PDO $db, int $withdrawalId): array
    {
        if (!self::isOperational($db)) {
            throw new RuntimeException('O processamento automático de comissões pelo Asaas ainda não está ativo.');
        }
        self::ensureTable($db);
        $withdrawal = self::loadWithdrawal($db, $withdrawalId);
        if (!is_array($withdrawal)) {
            throw new RuntimeException('Solicitação de saque não encontrada.');
        }
        if ((string) ($withdrawal['status'] ?? '') !== 'approved') {
            throw new RuntimeException('A solicitação precisa estar aprovada antes do processamento pelo Asaas.');
        }
        $pixType = strtolower(trim((string) ($withdrawal['pix_key_type'] ?? '')));
        $pixKey = trim((string) ($withdrawal['pix_key'] ?? ''));
        $asaasType = self::asaasPixType($pixType);
        if ($asaasType === '' || $pixKey === '') {
            throw new RuntimeException('A solicitação aprovada não possui uma chave PIX válida congelada.');
        }
        $amountCents = (int) round(((float) ($withdrawal['amount'] ?? 0)) * 100);
        if ($amountCents < 1) {
            throw new RuntimeException('O valor da solicitação de saque é inválido.');
        }
        $reference = 'MP-COMMISSION-WITHDRAW-' . $withdrawalId;
        $existing = self::loadTransfer($db, $withdrawalId);
        if (is_array($existing)) {
            $existingStatus = strtoupper((string) ($existing['status'] ?? ''));
            if ((string) ($existing['asaas_transfer_id'] ?? '') !== '' || in_array($existingStatus, ['PENDING', 'BANK_PROCESSING', 'DONE', 'BLOCKED'], true)) {
                return self::payload($existing, true);
            }
            self::updateTransfer($db, $withdrawalId, ['status' => 'CREATING', 'fail_reason' => null]);
        } else {
            $insert = $db->prepare("INSERT INTO " . self::TABLE . "
                (withdrawal_id, external_reference, status, amount_cents, pix_key_type, pix_key)
                VALUES (?, ?, 'CREATING', ?, ?, ?)");
            $insert->execute([$withdrawalId, $reference, $amountCents, $pixType, $pixKey]);
        }

        // Reconcile a timeout or a lost response before creating another transfer.
        $recovered = self::findTransferByReference($db, $reference);
        if (is_array($recovered) && !empty($recovered['id'])) {
            self::updateTransfer($db, $withdrawalId, self::transferFields($recovered));
            $row = self::loadTransfer($db, $withdrawalId);
            if (is_array($row)) {
                self::markLocalPaidIfDone($db, $withdrawalId, $row);
                return self::payload($row, true);
            }
        }

        try {
            $response = self::request($db, '/transfers', [
                'value' => round($amountCents / 100, 2),
                'operationType' => 'PIX',
                'pixAddressKey' => $pixKey,
                'pixAddressKeyType' => $asaasType,
                'description' => mb_substr('Comissão MapsProspector — saque #' . $withdrawalId, 0, 100),
                'externalReference' => $reference,
            ]);
            self::updateTransfer($db, $withdrawalId, self::transferFields($response));
        } catch (Throwable $e) {
            self::updateTransfer($db, $withdrawalId, [
                'status' => 'FAILED',
                'fail_reason' => mb_substr($e->getMessage(), 0, 500),
            ]);
            throw $e;
        }
        $row = self::loadTransfer($db, $withdrawalId);
        if (!is_array($row)) {
            throw new RuntimeException('A transferência foi enviada, mas não foi possível salvar seu retorno local.');
        }
        self::markLocalPaidIfDone($db, $withdrawalId, $row);
        return self::payload($row, false);
    }

    public static function sync(PDO $db, int $withdrawalId): array
    {
        if (!self::isOperational($db)) {
            throw new RuntimeException('O processamento automático de comissões pelo Asaas não está ativo.');
        }
        $row = self::loadTransfer($db, $withdrawalId);
        if (!is_array($row) || trim((string) ($row['asaas_transfer_id'] ?? '')) === '') {
            throw new RuntimeException('Ainda não existe uma transferência Asaas para esta solicitação.');
        }
        $response = self::request($db, '/transfers/' . rawurlencode((string) $row['asaas_transfer_id']), [], 'GET');
        self::updateTransfer($db, $withdrawalId, self::transferFields($response));
        $row = self::loadTransfer($db, $withdrawalId);
        if (!is_array($row)) {
            throw new RuntimeException('Não foi possível atualizar o status da transferência.');
        }
        self::markLocalPaidIfDone($db, $withdrawalId, $row);
        return self::payload($row, true);
    }

    public static function handleWebhook(PDO $db, string $eventType, array $payload): void
    {
        $transfer = is_array($payload['transfer'] ?? null) ? $payload['transfer'] : [];
        $transferId = trim((string) ($transfer['id'] ?? ''));
        $reference = trim((string) ($transfer['externalReference'] ?? ''));
        if ($transferId === '' && $reference === '') return;
        $row = null;
        if ($transferId !== '') {
            $stmt = $db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE asaas_transfer_id = ? LIMIT 1');
            $stmt->execute([$transferId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!is_array($row) && $reference !== '') {
            $stmt = $db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE external_reference = ? LIMIT 1');
            $stmt->execute([$reference]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        if (!is_array($row)) return;
        $fields = self::transferFields($transfer);
        if (trim((string) ($transfer['status'] ?? '')) === '') {
            $fields['status'] = self::statusFromEvent($eventType);
        }
        self::updateTransfer($db, (int) $row['withdrawal_id'], $fields);
        $fresh = self::loadTransfer($db, (int) $row['withdrawal_id']);
        if (is_array($fresh)) self::markLocalPaidIfDone($db, (int) $row['withdrawal_id'], $fresh);
    }

    public static function pixKeyType(string $value): string
    {
        return self::asaasPixType(strtolower(trim($value)));
    }

    private static function loadWithdrawal(PDO $db, int $id): ?array
    {
        $stmt = $db->prepare("SELECT w.id, w.amount, w.status, w.user_id, d.pix_key_type, d.pix_key
            FROM referral_withdrawals w
            LEFT JOIN referral_withdrawal_payment_details d ON d.withdrawal_id = w.id
            WHERE w.id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function loadTransfer(PDO $db, int $withdrawalId): ?array
    {
        $stmt = $db->prepare('SELECT * FROM ' . self::TABLE . ' WHERE withdrawal_id = ? LIMIT 1');
        $stmt->execute([$withdrawalId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private static function updateTransfer(PDO $db, int $withdrawalId, array $fields): void
    {
        $allowed = [
            'external_reference', 'asaas_transfer_id', 'status', 'transfer_fee_cents',
            'net_amount_cents', 'transaction_receipt_url', 'fail_reason', 'raw_response_json'
        ];
        $set = [];
        $params = [];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $fields)) {
                $set[] = $field . ' = ?';
                $params[] = $fields[$field];
            }
        }
        if ($set === []) return;
        $params[] = $withdrawalId;
        $stmt = $db->prepare('UPDATE ' . self::TABLE . ' SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE withdrawal_id = ? LIMIT 1');
        $stmt->execute($params);
    }

    private static function markLocalPaidIfDone(PDO $db, int $withdrawalId, array $row): void
    {
        if (strtoupper((string) ($row['status'] ?? '')) !== 'DONE') return;
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("UPDATE referral_withdrawals SET status = 'paid' WHERE id = ? AND status = 'approved' LIMIT 1");
            $stmt->execute([$withdrawalId]);
            $commissions = $db->prepare("UPDATE referral_commissions rc
                INNER JOIN referral_withdrawal_commissions wc ON wc.commission_id = rc.id
                SET rc.status = 'paid', rc.paid_at = COALESCE(rc.paid_at, NOW())
                WHERE wc.withdrawal_id = ?");
            $commissions->execute([$withdrawalId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private static function transferFields(array $response): array
    {
        $status = strtoupper(trim((string) ($response['status'] ?? 'PENDING')));
        $fee = isset($response['transferFee']) ? (int) round(((float) $response['transferFee']) * 100) : null;
        $net = isset($response['netValue']) ? (int) round(((float) $response['netValue']) * 100) : null;
        return [
            'asaas_transfer_id' => trim((string) ($response['id'] ?? '')) ?: null,
            'status' => $status,
            'transfer_fee_cents' => $fee,
            'net_amount_cents' => $net,
            'transaction_receipt_url' => trim((string) ($response['transactionReceiptUrl'] ?? '')) ?: null,
            'fail_reason' => trim((string) ($response['failReason'] ?? '')) ?: null,
            'external_reference' => trim((string) ($response['externalReference'] ?? '')) ?: null,
            'raw_response_json' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];
    }

    private static function payload(array $row, bool $reused): array
    {
        return [
            'withdrawal_id' => (int) ($row['withdrawal_id'] ?? 0),
            'transfer_id' => (string) ($row['asaas_transfer_id'] ?? ''),
            'status' => (string) ($row['status'] ?? 'CREATING'),
            'amount_brl' => ((int) ($row['amount_cents'] ?? 0)) / 100,
            'net_amount_brl' => $row['net_amount_cents'] !== null ? ((int) $row['net_amount_cents']) / 100 : null,
            'transfer_fee_brl' => $row['transfer_fee_cents'] !== null ? ((int) $row['transfer_fee_cents']) / 100 : null,
            'receipt_url' => $row['transaction_receipt_url'] ?: null,
            'fail_reason' => $row['fail_reason'] ?: null,
            'reused' => $reused,
        ];
    }

    private static function findTransferByReference(PDO $db, string $reference): ?array
    {
        try {
            $from = (new DateTimeImmutable('-2 days'))->format('Y-m-d');
            $to = (new DateTimeImmutable('+1 day'))->format('Y-m-d');
            $response = self::request($db, '/transfers?dateCreated[ge]=' . rawurlencode($from) . '&dateCreated[le]=' . rawurlencode($to) . '&limit=100', [], 'GET');
            foreach ((array) ($response['data'] ?? []) as $item) {
                if (is_array($item) && (string) ($item['externalReference'] ?? '') === $reference) return $item;
            }
        } catch (Throwable $e) {
            error_log('asaas commission transfer reconcile: ' . $e->getMessage());
        }
        return null;
    }

    private static function statusFromEvent(string $eventType): string
    {
        return [
            'TRANSFER_CREATED' => 'PENDING',
            'TRANSFER_PENDING' => 'PENDING',
            'TRANSFER_IN_BANK_PROCESSING' => 'BANK_PROCESSING',
            'TRANSFER_BLOCKED' => 'BLOCKED',
            'TRANSFER_DONE' => 'DONE',
            'TRANSFER_FAILED' => 'FAILED',
            'TRANSFER_CANCELLED' => 'CANCELLED',
        ][$eventType] ?? 'PENDING';
    }

    private static function asaasPixType(string $type): string
    {
        return [
            'cpf' => 'CPF',
            'cnpj' => 'CNPJ',
            'email' => 'EMAIL',
            'telefone' => 'PHONE',
            'aleatoria' => 'EVP',
        ][$type] ?? '';
    }

    private static function tableReady(PDO $db, string $table): bool
    {
        try {
            return (bool) $db->query("SHOW TABLES LIKE '" . str_replace('`', '', $table) . "'")->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function setting(PDO $db, string $key): string
    {
        if (asaSecretKeyAllowed($key)) return asaSecretGetOrMigrate($db, $key);
        try {
            $stmt = $db->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            return trim((string) ($stmt->fetchColumn() ?: ''));
        } catch (Throwable $e) {
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
        if ($apiKey === '') throw new RuntimeException('Chave de API do Asaas não configurada.');
        $headers = "Content-Type: application/json\r\nAccept: application/json\r\nUser-Agent: MapsProspector/1.0 (https://prospec.atendo.log.br)\r\naccess_token: {$apiKey}\r\nX-ACCESS-TOKEN: {$apiKey}\r\n";
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $headers,
            'content' => in_array(strtoupper($method), ['GET', 'DELETE'], true) ? '' : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'timeout' => 30,
            'ignore_errors' => true,
        ]]);
        $response = @file_get_contents(self::baseUrl($db) . $path, false, $context);
        $decoded = json_decode((string) $response, true);
        if (!is_array($decoded)) throw new RuntimeException('Resposta inválida da API do Asaas.');
        if (isset($decoded['errors'][0]['description'])) throw new RuntimeException((string) $decoded['errors'][0]['description']);
        return $decoded;
    }
}
