<?php
/**
 * API de Autenticação
 * Endpoint: /api/auth.php
 */

// Previne qualquer output antes do JSON
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Limpa qualquer output buffer
ob_clean();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'login';
    
    if ($action === 'login') {
        // Login simplificado (sem senha por enquanto, apenas valida email)
        $email = sanitizeInput($input['email'] ?? '');
        
        if (empty($email) || !validateEmail($email)) {
            jsonError('Por favor, insira um e-mail válido.');
        }
        
        try {
            $db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            jsonError($e->getMessage(), 500);
        }
        
        // Busca ou cria usuário
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar/criar usuário: " . $e->getMessage());
            jsonError("Erro ao processar login. Verifique se o banco de dados foi criado corretamente.", 500);
        }
        
        if (!$user) {
            // Cria usuário padrão
            try {
                $name = ucfirst(explode('@', $email)[0]);
                $stmt = $db->prepare("INSERT INTO users (name, email, tenant_id, profile) VALUES (?, ?, 1, 'admin')");
                $stmt->execute([$name, $email]);
                $userId = $db->lastInsertId();
                
                $user = [
                    'id' => $userId,
                    'name' => $name,
                    'email' => $email,
                    'tenant_id' => 1,
                    'profile' => 'admin'
                ];
            } catch (PDOException $e) {
                error_log("Erro ao criar usuário: " . $e->getMessage());
                jsonError("Erro ao criar usuário. Verifique se o banco de dados foi criado corretamente.", 500);
            }
        }
        
        // Cria sessão
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['tenant_id'] = $user['tenant_id'];
        
        jsonSuccess([
            'user' => [
                'id' => (string)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'tenantId' => (string)$user['tenant_id'],
                'profile' => $user['profile']
            ],
            'tenant' => [
                'id' => (string)$user['tenant_id'],
                'name' => 'Atendo Maps'
            ]
        ]);
        
    } elseif ($action === 'check') {
        // Verifica se está autenticado
        if (isset($_SESSION['user_id'])) {
            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['user_id']]);
                $user = $stmt->fetch();
            } catch (Exception $e) {
                error_log("Erro ao verificar autenticação: " . $e->getMessage());
                jsonError("Erro ao verificar autenticação.", 500);
            }
            
            if ($user) {
                jsonSuccess([
                    'user' => [
                        'id' => (string)$user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'tenantId' => (string)$user['tenant_id'],
                        'profile' => $user['profile']
                    ],
                    'tenant' => [
                        'id' => (string)$user['tenant_id'],
                        'name' => 'Atendo Maps'
                    ]
                ]);
            }
        }
        
        jsonError('Não autenticado', 401);
        
    } elseif ($action === 'logout') {
        session_destroy();
        jsonSuccess(null, 'Logout realizado com sucesso');
        
    } else {
        jsonError('Ação inválida', 400);
    }
    
} else {
    jsonError('Método não permitido', 405);
}
