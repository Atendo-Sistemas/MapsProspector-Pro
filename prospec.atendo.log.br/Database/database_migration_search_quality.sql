CREATE TABLE IF NOT EXISTS `search_quality_events` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `service` varchar(24) NOT NULL,
  `provider` varchar(64) NOT NULL,
  `status` varchar(16) NOT NULL,
  `result_count` int(11) NOT NULL DEFAULT 0,
  `duplicate_count` int(11) NOT NULL DEFAULT 0,
  `missing_contact_count` int(11) NOT NULL DEFAULT 0,
  `latency_ms` int(11) NOT NULL DEFAULT 0,
  `cache_hit` tinyint(1) NOT NULL DEFAULT 0,
  `credits_spent` int(11) NOT NULL DEFAULT 0,
  `error_category` varchar(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `service_created_at` (`service`, `created_at`),
  KEY `status_created_at` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
