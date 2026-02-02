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
        // Login: e-mail + senha (usuários criados via registro de empresa)
        $email = sanitizeInput($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($email) || !validateEmail($email)) {
            jsonError('Por favor, insira um e-mail válido.');
        }
        
        if (empty($password)) {
            jsonError('Por favor, insira sua senha.');
        }
        
        try {
            $db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            jsonError($e->getMessage(), 500);
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar usuário: " . $e->getMessage());
            jsonError("Erro ao processar login. Verifique se o banco de dados foi criado corretamente.", 500);
        }
        
        if (!$user) {
            jsonError('E-mail ou senha incorretos.');
        }
        
        $storedHash = $user['password'] ?? null;
        if (empty($storedHash) || !password_verify($password, $storedHash)) {
            jsonError('E-mail ou senha incorretos.');
        }

        session_regenerate_id(true);

        $tenantId = isset($user['tenant_id']) ? (int) $user['tenant_id'] : null;
        $tenantName = 'Nome da empresa SaaS';
        $tenantStatus = 'active';
        $tokenUsage = ['used' => 0, 'limit' => 0, 'limitReached' => false];
        $tenantPlanId = '';
        $tenantPlanName = '';
        if ($tenantId) {
            $stmtT = $db->prepare("SELECT t.name, t.status, t.plan_id, p.name as plan_name FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ?");
            $stmtT->execute([$tenantId]);
            $row = $stmtT->fetch();
            if ($row) {
                $tenantName = $row['name'];
                $tenantStatus = $row['status'] ?? 'active';
                $tenantPlanId = isset($row['plan_id']) ? (string) $row['plan_id'] : '';
                $tenantPlanName = $row['plan_name'] ?? '';
                $planLimit = getTenantPlanTokenLimit($db, $tenantId);
                $bonus = getTenantTokenBonus($db, $tenantId, null);
                $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
                $used = getTenantTokensUsed($db, $tenantId, null);
                $tokenUsage = [
                    'used' => $used,
                    'limit' => $effectiveLimit,
                    'limitReached' => $effectiveLimit > 0 && $used >= $effectiveLimit
                ];
            }
        } else {
            $tenantName = 'Plataforma (Super Admin)';
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['tenant_id'] = $tenantId;
        $_SESSION['profile'] = $user['profile'];
        
        jsonSuccess([
            'user' => [
                'id' => (string)$user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'tenantId' => $tenantId !== null ? (string)$tenantId : '',
                'profile' => $user['profile']
            ],
            'tenant' => [
                'id' => $tenantId !== null ? (string)$tenantId : '',
                'name' => $tenantName,
                'status' => $tenantStatus,
                'planId' => $tenantPlanId,
                'planName' => $tenantPlanName
            ],
            'tokenUsage' => $tokenUsage
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
                $tenantId = isset($user['tenant_id']) ? (int) $user['tenant_id'] : null;
                $tenantName = 'Nome da empresa SaaS';
                $tenantStatus = 'active';
                $tokenUsage = ['used' => 0, 'limit' => 0, 'limitReached' => false];
                $tenantPlanId = '';
                $tenantPlanName = '';
                if ($tenantId) {
                    $stmtT = $db->prepare("SELECT t.name, t.status, t.plan_id, p.name as plan_name FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ?");
                    $stmtT->execute([$tenantId]);
                    $row = $stmtT->fetch();
                    if ($row) {
                        $tenantName = $row['name'];
                        $tenantStatus = $row['status'] ?? 'active';
                        $tenantPlanId = isset($row['plan_id']) ? (string) $row['plan_id'] : '';
                        $tenantPlanName = $row['plan_name'] ?? '';
                        $planLimit = getTenantPlanTokenLimit($db, $tenantId);
                        $bonus = getTenantTokenBonus($db, $tenantId, null);
                        $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
                        $used = getTenantTokensUsed($db, $tenantId, null);
                        $tokenUsage = [
                            'used' => $used,
                            'limit' => $effectiveLimit,
                            'limitReached' => $effectiveLimit > 0 && $used >= $effectiveLimit
                        ];
                    }
                } else {
                    $tenantName = 'Plataforma (Super Admin)';
                }
                $payload = [
                    'user' => [
                        'id' => (string)$user['id'],
                        'name' => $user['name'],
                        'email' => $user['email'],
                        'tenantId' => $tenantId !== null ? (string)$tenantId : '',
                        'profile' => $user['profile']
                    ],
                    'tenant' => [
                        'id' => $tenantId !== null ? (string)$tenantId : '',
                        'name' => $tenantName,
                        'status' => $tenantStatus,
                        'planId' => $tenantPlanId,
                        'planName' => $tenantPlanName
                    ],
                    'tokenUsage' => $tokenUsage
                ];
                if (!empty($_SESSION['impersonating']) && $tenantId !== null) {
                    $payload['impersonating'] = true;
                    $payload['impersonatingTenantName'] = $tenantName;
                }
                jsonSuccess($payload);
            }
        }
        
        jsonError('Não autenticado', 401);
        
    } elseif ($action === 'impersonate') {
        // Apenas super_admin pode acessar outra empresa como se fosse o cliente (sem senha)
        $profile = $_SESSION['profile'] ?? '';
        if ($profile !== 'super_admin') {
            jsonError('Acesso negado. Apenas o administrador da plataforma pode acessar outras empresas.', 403);
        }
        $tenantId = isset($input['tenantId']) ? (int) $input['tenantId'] : 0;
        if (!$tenantId) {
            jsonError('ID da empresa é obrigatório', 400);
        }
        if ($tenantId === 1) {
            jsonError('Não é permitido acessar a empresa padrão.', 400);
        }
        try {
            $db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            jsonError($e->getMessage(), 500);
        }
        $stmt = $db->prepare("SELECT id FROM tenants WHERE id = ?");
        $stmt->execute([$tenantId]);
        if (!$stmt->fetch()) {
            jsonError('Empresa não encontrada', 404);
        }
        $stmt = $db->prepare("SELECT id, name, email, tenant_id, profile FROM users WHERE tenant_id = ? ORDER BY (profile = 'admin') DESC, id ASC LIMIT 1");
        $stmt->execute([$tenantId]);
        $targetUser = $stmt->fetch();
        if (!$targetUser) {
            jsonError('Nenhum usuário encontrado nesta empresa.', 404);
        }
        $_SESSION['impersonate_original_user_id'] = $_SESSION['user_id'];
        $_SESSION['impersonate_original_user_name'] = $_SESSION['user_name'] ?? '';
        $_SESSION['impersonate_original_user_email'] = $_SESSION['user_email'] ?? '';
        $_SESSION['impersonate_original_tenant_id'] = isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : null;
        $_SESSION['impersonate_original_profile'] = $_SESSION['profile'] ?? '';
        $_SESSION['user_id'] = $targetUser['id'];
        $_SESSION['user_name'] = $targetUser['name'];
        $_SESSION['user_email'] = $targetUser['email'];
        $_SESSION['tenant_id'] = (int) $targetUser['tenant_id'];
        $_SESSION['profile'] = $targetUser['profile'];
        $_SESSION['impersonating'] = true;

        $tenantId = (int) $targetUser['tenant_id'];
        $tenantName = 'Nome da empresa SaaS';
        $tenantStatus = 'active';
        $tokenUsage = ['used' => 0, 'limit' => 0, 'limitReached' => false];
        $tenantPlanId = '';
        $tenantPlanName = '';
        $stmtT = $db->prepare("SELECT t.name, t.status, t.plan_id, p.name as plan_name FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ?");
        $stmtT->execute([$tenantId]);
        $row = $stmtT->fetch();
        if ($row) {
            $tenantName = $row['name'];
            $tenantStatus = $row['status'] ?? 'active';
            $tenantPlanId = isset($row['plan_id']) ? (string) $row['plan_id'] : '';
            $tenantPlanName = $row['plan_name'] ?? '';
            $planLimit = getTenantPlanTokenLimit($db, $tenantId);
            $bonus = getTenantTokenBonus($db, $tenantId, null);
            $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
            $used = getTenantTokensUsed($db, $tenantId, null);
            $tokenUsage = [
                'used' => $used,
                'limit' => $effectiveLimit,
                'limitReached' => $effectiveLimit > 0 && $used >= $effectiveLimit
            ];
        }
        jsonSuccess([
            'user' => [
                'id' => (string) $targetUser['id'],
                'name' => $targetUser['name'],
                'email' => $targetUser['email'],
                'tenantId' => (string) $tenantId,
                'profile' => $targetUser['profile']
            ],
            'tenant' => [
                'id' => (string) $tenantId,
                'name' => $tenantName,
                'status' => $tenantStatus,
                'planId' => $tenantPlanId,
                'planName' => $tenantPlanName
            ],
            'tokenUsage' => $tokenUsage,
            'impersonating' => true,
            'impersonatingTenantName' => $tenantName
        ]);
    } elseif ($action === 'stop_impersonate') {
        if (empty($_SESSION['impersonating'])) {
            jsonError('Você não está acessando outra empresa.', 400);
        }
        $_SESSION['user_id'] = $_SESSION['impersonate_original_user_id'];
        $_SESSION['user_name'] = $_SESSION['impersonate_original_user_name'] ?? '';
        $_SESSION['user_email'] = $_SESSION['impersonate_original_user_email'] ?? '';
        $_SESSION['tenant_id'] = $_SESSION['impersonate_original_tenant_id'] ?? null;
        $_SESSION['profile'] = $_SESSION['impersonate_original_profile'] ?? 'super_admin';
        unset($_SESSION['impersonating'], $_SESSION['impersonate_original_user_id'], $_SESSION['impersonate_original_user_name'],
              $_SESSION['impersonate_original_user_email'], $_SESSION['impersonate_original_tenant_id'], $_SESSION['impersonate_original_profile']);
        jsonSuccess(['restored' => true], 'Voltou à sua conta');
    } elseif ($action === 'logout') {
        session_destroy();
        jsonSuccess(null, 'Logout realizado com sucesso');

    } elseif ($action === 'change_password') {
        // Alterar senha do usuário logado (requer senha atual)
        if (empty($_SESSION['user_id'])) {
            jsonError('Faça login para alterar a senha.', 401);
        }
        $currentPassword = $input['currentPassword'] ?? '';
        $newPassword = $input['newPassword'] ?? '';
        if (empty($currentPassword)) {
            jsonError('Informe a senha atual.');
        }
        if (strlen($newPassword) < 6) {
            jsonError('A nova senha deve ter no mínimo 6 caracteres.');
        }
        try {
            $db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            jsonError($e->getMessage(), 500);
        }
        $stmt = $db->prepare("SELECT id, password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        if (!$user) {
            jsonError('Usuário não encontrado.', 404);
        }
        $storedHash = $user['password'] ?? null;
        if (empty($storedHash) || !password_verify($currentPassword, $storedHash)) {
            jsonError('Senha atual incorreta.');
        }
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$newHash, $_SESSION['user_id']]);
        jsonSuccess(null, 'Senha alterada com sucesso.');
        
    } else {
        jsonError('Ação inválida', 400);
    }
    
} else {
    jsonError('Método não permitido', 405);
}
