<?php
/**
 * API de Gerenciamento de Chaves da Apify com Consulta de Consumo em Tempo Real
 * Endpoint: /api/apify-keys.php
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
ob_clean();

$userId = requireAuth();
$authUser = getAuthUser();
$profile = strtolower((string)($authUser['profile'] ?? ''));

if ($profile !== 'super_admin') {
    jsonError('Acesso restrito ao Super Admin.', 403);
}

$db = Database::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $stmt = $db->query("SELECT id, label, api_key, tokens_used, is_active, last_used_at FROM apify_keys ORDER BY id DESC");
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Consulta o saldo/consumo na API da Apify para cada chave cadastrada
    foreach ($keys as &$k) {
        $keyVal = $k['api_key'];
        
        // Máscara da chave
        if (strlen($keyVal) > 10) {
            $k['masked_key'] = substr($keyVal, 0, 6) . '...' . substr($keyVal, -4);
        } else {
            $k['masked_key'] = '******';
        }
        // O token completo nunca deve sair da API; a substituição é feita
        // somente pelo formulário de edição, quando o administrador informar um novo valor.
        unset($k['api_key']);

        // Consulta o uso atual na API da Apify (/v2/users/me)
        $k['usage_usd'] = 0.00;
        $k['limit_usd'] = 5.00; // Seu limite padrão de $5

        try {
            $ch = curl_init("https://api.apify.com/v2/users/me?token=" . trim($keyVal));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 5
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200 && $response) {
                $userData = json_decode($response, true);
                // A Apify retorna o gasto atual do mês na propriedade 'currentMonthlyUsageUsd' ou similar
                if (isset($userData['data']['currentMonthlyUsageUsd'])) {
                    $k['usage_usd'] = (float)$userData['data']['currentMonthlyUsageUsd'];
                }
            }
        } catch (Exception $e) {
            // Se falhar ao consultar a Apify individual, apenas mantém 0.00 para não quebrar a listagem
        }
    }

    jsonSuccess($keys);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'create';

    if ($action === 'create') {
        $apiKey = trim($input['api_key'] ?? '');
        $label = sanitizeInput($input['label'] ?? 'Chave Apify');

        if (empty($apiKey)) {
            jsonError('A chave da Apify é obrigatória.', 400);
        }

        $stmt = $db->prepare("INSERT INTO apify_keys (api_key, label, is_active) VALUES (?, ?, 1)");
        $stmt->execute([$apiKey, $label]);

        jsonSuccess(null, 'Chave Apify adicionada com sucesso!');

    } elseif ($action === 'toggle') {
        $keyId = (int)($input['id'] ?? 0);
        $isActive = (int)($input['is_active'] ?? 1);

        $stmt = $db->prepare("UPDATE apify_keys SET is_active = ? WHERE id = ?");
        $stmt->execute([$isActive, $keyId]);

        jsonSuccess(null, 'Status da chave atualizado!');
    } elseif ($action === 'update') {
        $keyId = (int)($input['id'] ?? 0);
        $label = sanitizeInput((string)($input['label'] ?? ''));
        $apiKey = trim((string)($input['api_key'] ?? ''));

        if ($keyId <= 0) {
            jsonError('ID da chave não fornecido.', 400);
        }
        if ($label === '') {
            jsonError('A identificação da chave é obrigatória.', 400);
        }

        if ($apiKey !== '') {
            $stmt = $db->prepare("UPDATE apify_keys SET label = ?, api_key = ? WHERE id = ?");
            $stmt->execute([$label, $apiKey, $keyId]);
        } else {
            $stmt = $db->prepare("UPDATE apify_keys SET label = ? WHERE id = ?");
            $stmt->execute([$label, $keyId]);
        }

        if ($stmt->rowCount() < 1) {
            $existsStmt = $db->prepare("SELECT COUNT(*) FROM apify_keys WHERE id = ?");
            $existsStmt->execute([$keyId]);
            if ((int)$existsStmt->fetchColumn() === 0) {
                jsonError('Chave de pesquisa não encontrada.', 404);
            }
        }

        jsonSuccess(null, 'Chave de pesquisa atualizada com sucesso!');
    }

} elseif ($method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $keyId = (int)($input['id'] ?? 0);

    if (!$keyId) {
        jsonError('ID da chave não fornecido.', 400);
    }

    $stmt = $db->prepare("DELETE FROM apify_keys WHERE id = ?");
    $stmt->execute([$keyId]);

    jsonSuccess(null, 'Chave Apify removida com sucesso!');

} else {
    jsonError('Método não permitido', 405);
}