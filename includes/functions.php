<?php
/**
 * Funções Auxiliares
 * MapsProspector Pro
 */

/**
 * Retorna resposta JSON padronizada
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Retorna resposta de erro
 */
function jsonError($message, $statusCode = 400, $code = null) {
    $response = ['success' => false, 'error' => $message];
    if ($code !== null) {
        $response['code'] = $code;
    }
    jsonResponse($response, $statusCode);
}

/**
 * Retorna resposta de sucesso
 */
function jsonSuccess($data = null, $message = null) {
    $response = ['success' => true];
    if ($message !== null) {
        $response['message'] = $message;
    }
    if ($data !== null) {
        $response['data'] = $data;
    }
    jsonResponse($response);
}

/**
 * Valida e sanitiza entrada
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

/**
 * Valida email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Verifica se usuário está autenticado
 */
function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        jsonError('Não autenticado', 401);
    }
    return $_SESSION['user_id'];
}

/**
 * Formata número de telefone brasileiro
 */
function formatPhoneNumber($phone) {
    $digits = preg_replace('/\D/', '', $phone);
    if (strlen($digits) < 8) return '';
    
    // Adiciona DDI 55 se necessário
    if (strlen($digits) >= 10 && substr($digits, 0, 2) !== '55') {
        return '55' . $digits;
    }
    return $digits;
}

/**
 * Criptografa o valor do apikey para armazenamento no banco (AES-256-CBC)
 */
function encryptApikey($plain) {
    if ($plain === null || $plain === '') return '';
    $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : hash('sha256', 'MapsProspector-Pro-Webhook-Key-2025', true);
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) return '';
    return base64_encode($iv . $encrypted);
}

/**
 * Descriptografa o apikey armazenado no banco.
 * Se o valor não for base64 válido ou falhar o decrypt, retorna o valor original (compatibilidade com tokens antigos em texto puro).
 */
function decryptApikey($encrypted) {
    if ($encrypted === null || $encrypted === '') return '';
    $key = defined('ENCRYPTION_KEY') ? ENCRYPTION_KEY : hash('sha256', 'MapsProspector-Pro-Webhook-Key-2025', true);
    $raw = base64_decode($encrypted, true);
    if ($raw === false || strlen($raw) < 17) {
        return trim($encrypted); // valor antigo em texto puro
    }
    $iv = substr($raw, 0, 16);
    $ciphertext = substr($raw, 16);
    $decrypted = openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? $decrypted : trim($encrypted);
}

/**
 * Monta o array de contato para envio ao webhook a partir de um lead (linha do banco).
 * Retorna null se o lead não tiver telefone válido.
 */
function buildContactDataFromLead(array $lead, $simplifiedPayload = false) {
    $phone = formatPhoneNumber($lead['phone'] ?? '');
    if (empty($phone)) {
        return null;
    }
    $contactData = [
        'name' => $lead['name'],
        'number' => $phone
    ];
    if (!$simplifiedPayload) {
        $contactData['email'] = $lead['email'] ?? '';
        $contactData['tag'] = $lead['tag'] ?? 'prospect_maps';
        $extraInfo = [];
        if (!empty($lead['address'])) {
            $extraInfo[] = ['name' => 'Endereço', 'value' => $lead['address']];
        }
        if (!empty($lead['cnpj'])) {
            $extraInfo[] = ['name' => 'CNPJ', 'value' => $lead['cnpj']];
        }
        if (!empty($lead['partners'])) {
            $extraInfo[] = ['name' => 'Sócios', 'value' => $lead['partners']];
        }
        if (!empty($lead['maps_uri'])) {
            $extraInfo[] = ['name' => 'Maps', 'value' => $lead['maps_uri']];
        }
        if (!empty($lead['website'])) {
            $extraInfo[] = ['name' => 'Site', 'value' => $lead['website']];
        }
        if (!empty($extraInfo)) {
            $contactData['extraInfo'] = $extraInfo;
        }
        $contactData['commentary'] = 'Lead capturado via Maps. Endereço: ' . ($lead['address'] ?? '');
    }
    $contactData = deepClean($contactData);
    unset($contactData['ticketId'], $contactData['contactId'], $contactData['id'], $contactData['messageId']);
    return $contactData;
}

/**
 * Limpa dados profundamente (remove null, empty, etc)
 */
function deepClean($data) {
    if (is_array($data)) {
        $cleaned = [];
        foreach ($data as $key => $value) {
            if ($value === null || $value === '') continue;
            
            if (is_array($value)) {
                $cleanedArr = deepClean($value);
                if (!empty($cleanedArr)) {
                    $cleaned[$key] = $cleanedArr;
                }
            } elseif (is_object($value)) {
                $cleanedObj = deepClean((array)$value);
                if (!empty($cleanedObj)) {
                    $cleaned[$key] = $cleanedObj;
                }
            } else {
                $cleaned[$key] = $value;
            }
        }
        return $cleaned;
    }
    return $data;
}
