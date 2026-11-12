<?php
/**
 * cnae-ia-matcher.php - Seleção Inteligente de Provedor (Groq vs OpenRouter)
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0); 
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ob_start();

try {
    require_once __DIR__ . '/../config/config.php';
    require_once __DIR__ . '/../config/database.php';
    require_once __DIR__ . '/../includes/functions.php';
    require_once __DIR__ . '/../services/aiRouterClient.php';

    $db = Database::getInstance()->getConnection();
    ob_clean();

    $authUser = getAuthUser();
    if (!$authUser) die(json_encode(['error' => 'Não autorizado']));

    $tenantId = $authUser['tenant_id'] ?? null;
    $profile = strtolower((string)($authUser['profile'] ?? ''));
    $isSuperAdmin = ($profile === 'super_admin');

    // O 9router é configurável pelo super admin. Quando ativo, ele é usado
    // por usuários autorizados nesta pesquisa IA; Maps/VPS e LinkedIn possuem
    // fluxos próprios e não são alterados por esta seleção.
    $routerConfig = aiRouterReadConfig($db);
    $apiKey = '';
    $apiUrl = '';
    $model = '';
    if ($routerConfig['enabled'] && $routerConfig['configured']) {
        $apiKey = $routerConfig['api_key'];
        $apiUrl = aiRouterEndpoint($routerConfig['base_url']);
        $model = $routerConfig['model'];
    } else {
        if ($isSuperAdmin) {
            $apiKey = trim((string) (getPlatformSetting($db, 'openrouter_api_key') ?? ''));
        } elseif ($tenantId) {
            $stmt = $db->prepare("SELECT ai_api_key FROM tenants WHERE id = ?");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch();
            $apiKey = trim((string) ($row['ai_api_key'] ?? ''));
        }
        if (empty($apiKey)) die(json_encode(['error' => 'Configure o 9router no painel API de Buscas ou informe uma chave de IA para esta empresa.']));
        if (strpos($apiKey, 'gsk_') === 0) {
            $apiUrl = 'https://api.groq.com/openai/v1/chat/completions';
            $model = 'llama-3.3-70b-versatile';
        } else {
            $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
            $model = 'google/gemini-2.0-flash-001';
        }
    }

    $segmento = $_GET['segmento'] ?? '';
    if (empty($segmento )) die(json_encode(['error' => 'Informe o segmento.']));

    // 3. PROMPT E CHAMADA
    $prompt = "Atue como um especialista em CNAE do Brasil. O usuário informou o segmento: '$segmento'. 
    Retorne APENAS um JSON no formato: {\"cnaes\": [{\"codigo\": \"0000-0/00\", \"descricao\": \"nome do cnae\"}]}.";

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    
    $payload = [
        'model' => $model,
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.1,
        'max_tokens' => 512
    ];

    // Groq e OpenRouter aceitam o formato de resposta JSON
    $payload['response_format'] = ['type' => 'json_object'];

    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE );
    curl_close($ch);

    if ($httpCode !== 200 ) {
        $err = json_decode($response, true);
        throw new Exception("Erro no provedor (Status $httpCode ): " . ($err['error']['message'] ?? 'Verifique sua chave.'));
    }

    $resData = json_decode($response, true);
    $content = $resData['choices'][0]['message']['content'] ?? '';
    
    ob_clean();
    echo $content;

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
