<?php
/**
 * API de Pastas de Pesquisas
 * Endpoint: /api/folders.php
 * GET: lista pastas; POST: cria pasta; PUT: atualiza; DELETE: exclui
 */

ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

ob_clean();

$userId = requireAuth();
$db = Database::getInstance()->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->prepare("
        SELECT 
            sf.id,
            sf.name,
            sf.created_at,
            COUNT(sh.id) as search_count
        FROM search_folders sf
        LEFT JOIN search_history sh ON sh.folder_id = sf.id
        WHERE sf.user_id = ?
        GROUP BY sf.id
        ORDER BY sf.created_at DESC
    ");
    $stmt->execute([$userId]);
    $folders = $stmt->fetchAll();
    
    foreach ($folders as &$folder) {
        $folder['id'] = (string) $folder['id'];
    }
    
    jsonSuccess($folders);
    
} elseif ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    $name = sanitizeInput($input['name'] ?? '');
    
    if (empty($name)) {
        jsonError('Nome da pasta é obrigatório', 400);
    }
    
    $stmt = $db->prepare("INSERT INTO search_folders (user_id, name) VALUES (?, ?)");
    $stmt->execute([$userId, $name]);
    $folderId = $db->lastInsertId();
    
    jsonSuccess([
        'id' => (string) $folderId,
        'name' => $name,
        'search_count' => 0
    ], 'Pasta criada com sucesso');
    
} elseif ($method === 'PUT') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    $folderId = isset($input['id']) ? (int) $input['id'] : null;
    $name = sanitizeInput($input['name'] ?? '');
    
    if (empty($folderId)) {
        jsonError('ID da pasta é obrigatório', 400);
    }
    
    if (empty($name)) {
        jsonError('Nome da pasta é obrigatório', 400);
    }
    
    $stmt = $db->prepare("UPDATE search_folders SET name = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$name, $folderId, $userId]);
    
    if ($stmt->rowCount() === 0) {
        jsonError('Pasta não encontrada', 404);
    }
    
    jsonSuccess(null, 'Pasta atualizada com sucesso');
    
} elseif ($method === 'DELETE') {
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);
    
    $folderId = isset($input['id']) ? (int) $input['id'] : null;
    
    if (empty($folderId)) {
        jsonError('ID da pasta é obrigatório', 400);
    }
    
    $stmt = $db->prepare("UPDATE search_history SET folder_id = NULL WHERE folder_id = ? AND user_id = ?");
    $stmt->execute([$folderId, $userId]);
    
    $stmt = $db->prepare("DELETE FROM search_folders WHERE id = ? AND user_id = ?");
    $stmt->execute([$folderId, $userId]);
    
    jsonSuccess(null, 'Pasta excluída com sucesso');
    
} else {
    jsonError('Método não permitido', 405);
}
