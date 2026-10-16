-- ============================================================
-- Painel de Indicações (v64f)
-- Tabela referrals: rastrea indicações de usuários
-- - referral_code: código público do usuário que indica
-- - clicks: quantos cliques o link de indicação recebeu
-- - converted_at: quando a indicação virou cliente pagante
-- - payout_amount: valor previsto para o indicador (R$ 10 por indicação paga; configurável)
-- - withdrawal_*: controle de saque (mínimo R$ 100)
-- Instalação idempotente (CREATE TABLE IF NOT EXISTS).
-- ============================================================
CREATE TABLE IF NOT EXISTS referrals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NULL,
    referral_code VARCHAR(32) NOT NULL,
    clicked_at DATETIME NULL,
    signup_user_id INT UNSIGNED NULL,
    converted_at DATETIME NULL,
    payout_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'converted', 'withdrawn') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_referral_code (referral_code),
    KEY idx_user_id (user_id),
    KEY idx_signup_user_id (signup_user_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Visitas e atribuições individuais de indicação.
-- Um mesmo link pode gerar várias visitas e vários cadastros; cada navegador
-- recebe um token aleatório e a atribuição expira 50 dias após o clique.
-- ============================================================
CREATE TABLE IF NOT EXISTS referral_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    referrer_user_id INT UNSIGNED NOT NULL,
    referrer_tenant_id INT UNSIGNED NULL,
    referral_code VARCHAR(32) NOT NULL,
    visit_token CHAR(64) NOT NULL,
    clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    signup_user_id INT UNSIGNED NULL,
    signup_tenant_id INT UNSIGNED NULL,
    attributed_at DATETIME NULL,
    converted_at DATETIME NULL,
    status ENUM('clicked', 'registered', 'converted') NOT NULL DEFAULT 'clicked',
    PRIMARY KEY (id),
    UNIQUE KEY uq_referral_visit_token (referral_code, visit_token),
    UNIQUE KEY uq_referral_signup_user (signup_user_id),
    KEY idx_referral_visit_referrer (referrer_user_id),
    KEY idx_referral_visit_tenant (signup_tenant_id),
    KEY idx_referral_visit_status (status),
    KEY idx_referral_visit_clicked_at (clicked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aceites versionados do Termo de Indicação. O link só é ativado depois do
-- aceite explícito da versão vigente pelo usuário responsável.
CREATE TABLE IF NOT EXISTS referral_term_acceptances (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NULL,
    terms_version VARCHAR(32) NOT NULL,
    accepted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_referral_terms_user_version (user_id, terms_version),
    KEY idx_referral_terms_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Tabela referral_settings: configurações globais do programa
-- - payout_per_conversion: valor pago por indicação convertida (default 10.00)
-- - minimum_withdrawal: valor mínimo para solicitar saque (default 100.00)
-- ============================================================
CREATE TABLE IF NOT EXISTS referral_settings (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key VARCHAR(64) NOT NULL,
    setting_value VARCHAR(64) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_setting_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO referral_settings (setting_key, setting_value)
SELECT 'payout_per_conversion', '10.00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM referral_settings WHERE setting_key = 'payout_per_conversion');

INSERT INTO referral_settings (setting_key, setting_value)
SELECT 'minimum_withdrawal', '100.00' FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM referral_settings WHERE setting_key = 'minimum_withdrawal');

-- ============================================================
-- Tabela referral_withdrawals: solicitações de saque
-- ============================================================
CREATE TABLE IF NOT EXISTS referral_withdrawals (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'paid') NOT NULL DEFAULT 'pending',
    notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Perfil de recebimento do indicador. A plataforma trabalha com PIX como
-- forma preferencial; a chave fica disponível apenas ao próprio usuário e
-- ao super administrador responsável por processar os saques.
-- ============================================================
CREATE TABLE IF NOT EXISTS referral_payment_profiles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    tenant_id INT UNSIGNED NULL,
    preferred_method ENUM('pix') NOT NULL DEFAULT 'pix',
    pix_key_type ENUM('cpf', 'cnpj', 'email', 'telefone', 'aleatoria') NOT NULL,
    pix_key VARCHAR(160) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_referral_payment_user (user_id),
    KEY idx_referral_payment_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A chave é copiada no momento do pedido para preservar o destino informado
-- para aquele saque, mesmo se o participante atualizar seu perfil depois.
CREATE TABLE IF NOT EXISTS referral_withdrawal_payment_details (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    withdrawal_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    preferred_method ENUM('pix') NOT NULL DEFAULT 'pix',
    pix_key_type ENUM('cpf', 'cnpj', 'email', 'telefone', 'aleatoria') NOT NULL,
    pix_key VARCHAR(160) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_referral_withdrawal_payment (withdrawal_id),
    KEY idx_referral_withdrawal_payment_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Tabela referral_commissions: comissões pagas pelo módulo de
-- pagamentos (Asaas) a cada recarga de créditos confirmada.
-- ============================================================
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
