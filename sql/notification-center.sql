-- Maps Prospector — Central de Notificações internas
-- Versão 20260817-41
-- Execute uma única vez no banco naciona4_maps1243 antes de publicar o endpoint.

CREATE TABLE IF NOT EXISTS internal_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_scope ENUM('platform', 'profile', 'tenant', 'tenant_profile', 'user') NOT NULL,
    target_profile VARCHAR(32) NULL,
    target_tenant_id INT UNSIGNED NULL,
    target_user_id INT UNSIGNED NULL,
    category VARCHAR(48) NOT NULL DEFAULT 'general',
    priority ENUM('low', 'normal', 'high') NOT NULL DEFAULT 'normal',
    title VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    source_type VARCHAR(32) NOT NULL DEFAULT 'manual',
    source_reference VARCHAR(100) NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_by_label VARCHAR(160) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_internal_notifications_visibility (recipient_scope, target_tenant_id, target_profile, target_user_id, created_at),
    KEY idx_internal_notifications_created_at (created_at),
    KEY idx_internal_notifications_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS internal_notification_reads (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    notification_id BIGINT UNSIGNED NOT NULL,
    reader_key VARCHAR(160) NOT NULL,
    read_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_internal_notification_reader (notification_id, reader_key),
    KEY idx_internal_notification_reads_reader (reader_key, read_at),
    CONSTRAINT fk_internal_notification_reads_notification
        FOREIGN KEY (notification_id) REFERENCES internal_notifications(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
