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
