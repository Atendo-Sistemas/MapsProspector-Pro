<?php
/**
 * API de Histórico de Buscas
 * Endpoint: /api/history.php
 */

// Previne qualquer output antes do JSON
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Limpa qualquer output buffer
ob_clean();

$userId = requireAuth();
$db = Database::getInstance()->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Lista histórico
    $stmt = $db->prepare("
        SELECT 
            sh.id,
            sh.query,
            sh.location,
            sh.tag,
            sh.results_count,
            sh.created_at as timestamp,
            COUNT(l.id) as leads_count
        FROM search_history sh
        LEFT JOIN leads l ON l.search_history_id = sh.id
        WHERE sh.user_id = ?
        GROUP BY sh.id
        ORDER BY sh.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll();
    
    // Para cada item, busca os leads
    foreach ($history as &$item) {
        $stmtLeads = $db->prepare("
            SELECT 
                id, name, address, phone, email, website, maps_uri as mapsUri, 
                cnpj, partners, tag, latitude, longitude
            FROM leads
            WHERE search_history_id = ?
        ");
        $stmtLeads->execute([$item['id']]);
        $leads = $stmtLeads->fetchAll();
        
        // Adiciona IDs formatados
        foreach ($leads as &$lead) {
            $lead['id'] = 'lead-' . $lead['id'];
        }
        
        $item['leads'] = $leads;
        $item['id'] = (string)$item['id'];
    }
    
    jsonSuccess($history);
    
} elseif ($method === 'DELETE') {
    // Limpa histórico
    $stmt = $db->prepare("DELETE FROM search_history WHERE user_id = ?");
    $stmt->execute([$userId]);
    
    jsonSuccess(null, 'Histórico limpo com sucesso');
    
} else {
    jsonError('Método não permitido', 405);
}
