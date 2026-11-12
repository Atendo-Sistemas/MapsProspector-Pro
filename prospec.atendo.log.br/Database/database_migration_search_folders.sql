-- Migration: Add search_folders table and folder_id to search_history
-- Adds folder/group functionality to organize searches

-- Table: search_folders (pastas para organizar pesquisas)
CREATE TABLE IF NOT EXISTS `search_folders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add folder_id column to search_history
ALTER TABLE `search_history` ADD COLUMN `folder_id` int(11) DEFAULT NULL AFTER `tag`;
ALTER TABLE `search_history` ADD KEY `folder_id` (`folder_id`);
