-- Maps Prospector — Pedidos de pagamento InfinitePay
-- Versão 20260819-63
-- Execute uma única vez no banco antes de publicar os endpoints de pagamento.
CREATE TABLE IF NOT EXISTS infinitepay_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tenant_id INT UNSIGNED NULL,
    user_id INT UNSIGNED NOT NULL,
    order_nsu VARCHAR(120) NOT NULL,
    amount_cents INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    description VARCHAR(255) NOT NULL DEFAULT 'Compra de créditos',
    invoice_slug VARCHAR(160) NULL,
    transaction_nsu VARCHAR(160) NULL,
    capture_method VARCHAR(32) NULL,
    receipt_url VARCHAR(400) NULL,
    status ENUM('pending', 'paid', 'failed', 'unknown') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_infinitepay_order_nsu (order_nsu),
    KEY idx_infinitepay_orders_tenant (tenant_id, status, created_at),
    KEY idx_infinitepay_orders_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
