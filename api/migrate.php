<?php
/**
 * Migration Runner - Execute database migrations
 * Access: Super Admin only
 * URL: /api/migrate.php
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();

// Check if user is super admin
$authUser = null;
try {
    $authUser = getAuthUser();
} catch (Exception $e) {
    jsonError('Unauthorized', 401);
}

if (!$authUser || strtolower($authUser['profile']) !== 'super_admin') {
    jsonError('Access denied. Super admin only.', 403);
}

$db = Database::getInstance()->getConnection();

$results = [];

try {
    // Create search_folders table if not exists
    $db->exec("
        CREATE TABLE IF NOT EXISTS `search_folders` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `user_id` int(11) NOT NULL,
          `name` varchar(255) NOT NULL,
          `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    $results[] = 'Table search_folders created successfully';
} catch (PDOException $e) {
    $results[] = 'Error creating search_folders: ' . $e->getMessage();
}

try {
    // Add folder_id column to search_history if not exists
    $stmt = $db->query("SHOW COLUMNS FROM search_history LIKE 'folder_id'");
    if ($stmt->rowCount() === 0) {
        $db->exec("ALTER TABLE `search_history` ADD COLUMN `folder_id` int(11) DEFAULT NULL AFTER `tag`");
        $db->exec("ALTER TABLE `search_history` ADD KEY `folder_id` (`folder_id`)");
        $results[] = 'Column folder_id added to search_history';
    } else {
        $results[] = 'Column folder_id already exists in search_history';
    }
} catch (PDOException $e) {
    $results[] = 'Error adding folder_id column: ' . $e->getMessage();
}

jsonSuccess([
    'message' => 'Migrations completed',
    'results' => $results
]);
