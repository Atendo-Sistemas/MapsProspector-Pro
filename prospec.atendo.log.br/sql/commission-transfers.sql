-- Transferências de comissões via Asaas.
-- Migração idempotente: não altera pagamentos, comissões ou saques existentes.
CREATE TABLE IF NOT EXISTS referral_withdrawal_transfers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS referral_withdrawal_commissions (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
