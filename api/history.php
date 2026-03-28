<?php
/**
 * API de Histórico de Buscas
 * Endpoint: /api/history.php
 * GET: retorna pesquisas do banco; leads bloqueados (só id/nome) ou desbloqueados (dados completos conforme lead_unlocks).
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
    // Verifica se a tabela search_folders existe
    $hasSearchFolders = false;
    try {
        $db->query("SELECT 1 FROM search_folders LIMIT 1");
        $hasSearchFolders = true;
    } catch (PDOException $e) {
        // Tabela search_folders pode não existir
    }

    // Monta a query condicionalmente
    $folderJoin = $hasSearchFolders ? "LEFT JOIN search_folders sf ON sf.id = sh.folder_id" : "";
    $folderSelect = $hasSearchFolders ? "sh.folder_id, sf.name as folder_name," : "sh.folder_id,";

    $stmt = $db->prepare("
        SELECT 
            sh.id,
            sh.query,
            sh.location,
            sh.tag,
            $folderSelect
            sh.results_count,
            sh.created_at as timestamp,
            COUNT(l.id) as leads_count
        FROM search_history sh
        $folderJoin
        LEFT JOIN leads l ON l.search_history_id = sh.id
        WHERE sh.user_id = ?
        GROUP BY sh.id
        ORDER BY sh.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$userId]);
    $history = $stmt->fetchAll();

    $hasLeadUnlocks = false;
    try {
        $db->query("SELECT 1 FROM lead_unlocks LIMIT 1");
        $hasLeadUnlocks = true;
    } catch (PDOException $e) {
        // Tabela lead_unlocks pode não existir ainda
    }

    // Verifica se a API do scraper está configurada
    $scraperApiKey = getPlatformSetting($db, 'scraper_api_key');
    if (empty(trim((string)$scraperApiKey)) && defined('SCRAPER_API_KEY')) {
        $scraperApiKey = SCRAPER_API_KEY;
    }
    $scraperConfigured = !empty(trim((string)$scraperApiKey));

    foreach ($history as &$item) {
        $searchHistoryId = (int) $item['id'];

        $stmtLeads = $db->prepare("
            SELECT id, name, address, phone, email, website, maps_uri, cnpj, partners, tag, latitude, longitude
            FROM leads
            WHERE search_history_id = ?
            ORDER BY id ASC
        ");
        $stmtLeads->execute([$searchHistoryId]);
        $rawLeads = $stmtLeads->fetchAll(PDO::FETCH_ASSOC);

        $unlockedIds = [];
        if ($hasLeadUnlocks) {
            $stmtUnlocks = $db->prepare("
                SELECT lead_id FROM lead_unlocks
                WHERE user_id = ? AND search_history_id = ?
            ");
            $stmtUnlocks->execute([$userId, $searchHistoryId]);
            $unlockedIds = array_column($stmtUnlocks->fetchAll(PDO::FETCH_ASSOC), 'lead_id');
            $unlockedIds = array_flip($unlockedIds);
        }

        $leads = [];
        foreach ($rawLeads as $row) {
            $leadDbId = (int) $row['id'];
            $isUnlocked = isset($unlockedIds[$leadDbId]);

            // Se API não configurada, mostra todos os dados normalmente
            if (!$scraperConfigured) {
                $leads[] = [
                    'id' => 'lead-' . $leadDbId,
                    'name' => $row['name'] ?? '',
                    'locked' => false,
                    'dbId' => $leadDbId,
                    'phone' => $row['phone'] ?? '',
                    'email' => $row['email'] ?? '',
                    'address' => $row['address'] ?? '',
                    'website' => $row['website'] ?? '',
                    'mapsUri' => $row['maps_uri'] ?? '',
                    'cnpj' => $row['cnpj'] ?? '',
                    'partners' => $row['partners'] ?? '',
                    'tag' => $row['tag'] ?? '',
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ];
            } elseif ($isUnlocked) {
                $leads[] = [
                    'id' => 'lead-' . $leadDbId,
                    'name' => $row['name'] ?? '',
                    'locked' => false,
                    'dbId' => $leadDbId,
                    'phone' => $row['phone'] ?? '',
                    'email' => $row['email'] ?? '',
                    'address' => $row['address'] ?? '',
                    'website' => $row['website'] ?? '',
                    'mapsUri' => $row['maps_uri'] ?? '',
                    'cnpj' => $row['cnpj'] ?? '',
                    'partners' => $row['partners'] ?? '',
                    'tag' => $row['tag'] ?? '',
                    'latitude' => $row['latitude'] ?? null,
                    'longitude' => $row['longitude'] ?? null,
                ];
            } else {
                $leads[] = [
                    'id' => 'lead-' . $leadDbId,
                    'name' => $row['name'] ?? '',
                    'locked' => true,
                    'dbId' => $leadDbId,
                ];
            }
        }

        $item['leads'] = $leads;
        $item['id'] = (string) $item['id'];
        $item['folderId'] = $item['folder_id'] ? (string) $item['folder_id'] : null;
        $item['folderName'] = $item['folder_name'] ?? null;
    }

    jsonSuccess($history);

} elseif ($method === 'DELETE') {
    $stmt = $db->prepare("DELETE FROM search_history WHERE user_id = ?");
    $stmt->execute([$userId]);

    try {
        $stmtU = $db->prepare("DELETE FROM lead_unlocks WHERE user_id = ?");
        $stmtU->execute([$userId]);
    } catch (PDOException $e) {
        // Tabela lead_unlocks pode não existir
    }

    jsonSuccess(null, 'Histórico limpo com sucesso');

} else {
    jsonError('Método não permitido', 405);
}
