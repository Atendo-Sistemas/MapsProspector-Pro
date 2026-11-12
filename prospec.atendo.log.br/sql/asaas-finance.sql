-- ============================================================
-- Módulo de pagamentos Asaas
-- Substitui o fluxo InfinitePay para compra de créditos e
-- comissões do programa de indicações.
-- Instalação idempotente (CREATE TABLE IF NOT EXISTS).
-- ============================================================

-- Pedidos de recarga de créditos via Asaas
CREATE TABLE IF NOT EXISTS asaas_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_nsu VARCHAR(64) NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NULL,
    amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    description VARCHAR(255) NOT NULL DEFAULT 'Recarga de créditos',
    asaas_payment_id VARCHAR(64) NULL,
    asaas_customer_id VARCHAR(64) NULL,
    payment_url VARCHAR(500) NULL,
    status ENUM('pending', 'paid', 'failed', 'canceled', 'unknown') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_nsu (order_nsu),
    KEY idx_tenant_id (tenant_id),
    KEY idx_status (status),
    KEY idx_asaas_payment_id (asaas_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Eventos de webhook do Asaas (idempotência: mesmo evt não é processado 2x)
CREATE TABLE IF NOT EXISTS asaas_webhook_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    webhook_event_id VARCHAR(64) NOT NULL,
    payment_id VARCHAR(64) NULL,
    event_type VARCHAR(64) NULL,
    payload_json JSON NULL,
    processed TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_webhook_event_id (webhook_event_id),
    KEY idx_payment_id (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assinaturas recorrentes de planos. O valor e o plano são registrados no
-- servidor no momento da contratação; o navegador nunca controla o preço.
CREATE TABLE IF NOT EXISTS asaas_subscriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    plan_slug VARCHAR(64) NOT NULL,
    product_kind VARCHAR(32) NOT NULL DEFAULT 'prospecting',
    amount_cents INT UNSIGNED NOT NULL,
    asaas_customer_id VARCHAR(64) NULL,
    asaas_subscription_id VARCHAR(128) NOT NULL,
    payment_url VARCHAR(500) NULL,
    status ENUM('pending', 'active', 'cancelled', 'overdue', 'expired') NOT NULL DEFAULT 'pending',
    next_due_date DATE NULL,
    access_until DATE NULL,
    cancelled_at DATETIME NULL,
    last_payment_id VARCHAR(64) NULL,
    last_payment_status VARCHAR(32) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asaas_subscription_id (asaas_subscription_id),
    KEY idx_subscription_tenant_status (tenant_id, status),
    KEY idx_subscription_access_until (access_until),
    KEY idx_subscription_next_due_date (next_due_date),
    KEY idx_subscription_last_payment (last_payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Comissões do programa de indicações (a pagar por pagamento confirmado)
CREATE TABLE IF NOT EXISTS referral_commissions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    referrer_user_id INT UNSIGNED NOT NULL,
    referrer_tenant_id INT UNSIGNED NULL,
    payer_tenant_id INT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'paid') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_referrer (referrer_user_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Migração de comissão automática a partir da tabela referrals:
-- indicações já convertidas passam a valer comissão por pagamento.
-- (Nada é alterado se não houver linhas convertidas; seguro reexecutar.)
-- ============================================================
INSERT INTO referral_commissions (referrer_user_id, referrer_tenant_id, amount, status)
SELECT r.user_id, r.tenant_id, r.payout_amount, 'paid'
FROM referrals r
WHERE r.status IN ('converted', 'withdrawn')
  AND NOT EXISTS (
    SELECT 1
    FROM referral_commissions rc
    WHERE rc.referrer_user_id = r.user_id
      AND rc.referrer_tenant_id <=> r.tenant_id
      AND rc.order_id IS NULL
      AND rc.status = 'paid'
  );
