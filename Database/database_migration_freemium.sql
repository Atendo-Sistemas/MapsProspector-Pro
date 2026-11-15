-- MapsProspector Pro — plano freemium de 20 créditos e controle de cadastros repetidos.
-- Selecione o schema da aplicação antes de executar esta migração.
-- Não altera o plano trial nem os saldos/planos de tenants existentes.

INSERT INTO `plans`
    (`name`, `slug`, `token_limit`, `price_monthly`, `period`, `status`, `catalog_group`, `included_users`, `addon_user_price_cents`)
VALUES
    ('Freemium 20', 'freemium-20', 20, 0.00, 'monthly', 'active', 'current', 1, 0)
ON DUPLICATE KEY UPDATE `slug` = VALUES(`slug`);

-- Identificadores são armazenados como HMAC-SHA256, não como e-mail, telefone
-- ou documento em texto puro. A unicidade bloqueia um novo benefício gratuito
-- mesmo que o cadastro/empresa anterior seja removido depois.
CREATE TABLE IF NOT EXISTS `free_signup_credit_claims` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `identity_type` VARCHAR(16) NOT NULL,
    `identity_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `tenant_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `claimed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_free_signup_identity` (`identity_type`, `identity_hash`),
    KEY `idx_free_signup_tenant` (`tenant_id`),
    KEY `idx_free_signup_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
