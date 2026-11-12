-- Migração: tabela lead_unlocks
-- Registra quais leads foram desbloqueados por cada usuário (1 token por lead).
-- Permite desbloquear a partir do Histórico (dados vêm do banco).

USE `maps`;

CREATE TABLE IF NOT EXISTS `lead_unlocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `search_history_id` int(11) NOT NULL,
  `lead_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_search_lead` (`user_id`, `search_history_id`, `lead_id`),
  KEY `search_history_id` (`search_history_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_lead_unlocks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lead_unlocks_search` FOREIGN KEY (`search_history_id`) REFERENCES `search_history` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lead_unlocks_lead` FOREIGN KEY (`lead_id`) REFERENCES `leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
