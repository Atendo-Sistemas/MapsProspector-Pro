-- Maps Prospector v20260820-64f — Métricas de tráfego do site.
-- Executar no phpMyAdmin (ou importar) na base do sistema.
CREATE TABLE IF NOT EXISTS site_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    page VARCHAR(255) NOT NULL DEFAULT 'home',
    referer VARCHAR(500) NOT NULL DEFAULT '',
    ref_domain VARCHAR(255) NOT NULL DEFAULT '',
    user_agent VARCHAR(500) NOT NULL DEFAULT '',
    device VARCHAR(16) NOT NULL DEFAULT 'desktop',
    ip_hash VARCHAR(24) NOT NULL DEFAULT '',
    visited_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_visited_at (visited_at),
    INDEX idx_page (page),
    INDEX idx_device (device)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
