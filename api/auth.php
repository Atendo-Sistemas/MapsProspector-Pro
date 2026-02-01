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
        // Login: apenas e-mails já cadastrados (usuários criados via registro de empresa)
        $email = sanitizeInput($input['email'] ?? '');
        
        if (empty($email) || !validateEmail($email)) {
            jsonError('Por favor, insira um e-mail válido.');
        }
        
        try {
            $db = Database::getInstance()->getConnection();
        } catch (Exception $e) {
            jsonError($e->getMessage(), 500);
        }
        
        // Busca usuário (apenas e-mails já cadastrados podem acessar)
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Erro ao buscar usuário: " . $e->getMessage());
            jsonError("Erro ao processar login. Verifique se o banco de dados foi criado corretamente.", 500);
        }
        
        if (!$user) {
            jsonError('E-mail não cadastrado. Cadastre sua empresa primeiro ou use o e-mail já vinculado à plataforma.');
        }

        $tenantId = isset($user['tenant_id']) ? (int) $user['tenant_id'] : null;
        $tenantName = 'Atendo Maps';
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
                $tenantName = 'Atendo Maps';
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
