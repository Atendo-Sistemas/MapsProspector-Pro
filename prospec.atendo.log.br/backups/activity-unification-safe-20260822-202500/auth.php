<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
/**
 * API de Autenticação com Cálculo de Teste de 30 Dias
 * Endpoint: /api/auth.php
 */

// Previne qualquer output antes do JSON
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/module-access.php';

// Limpa qualquer output buffer
ob_clean();

$method = $_SERVER['REQUEST_METHOD'];

// Este endpoint recebe comandos JSON via POST. Evita que GET/HEAD alcancem
// o código legado de finalização de sessão e retornem erro 500 por variáveis ausentes.
if ($method !== 'POST') {
    header('Allow: POST');
    jsonError('Método não permitido. Use POST.', 405, 'method_not_allowed');
}

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
        $tokenUsage = ['used' => 0, 'limit' => 0, 'planLimit' => 0, 'bonus' => 0, 'available' => 0, 'limitReached' => false, 'modules' => []];
        $tenantPlanId = '';
        $tenantPlanName = '';
        $trialDaysLeft = 30;
        $trialExpired = false;

        if ($tenantId) {
            $stmtT = $db->prepare("SELECT t.name, t.status, t.plan_id, t.created_at, p.name as plan_name FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ?");
            $stmtT->execute([$tenantId]);
            $row = $stmtT->fetch();
            if ($row) {
                $tenantName = $row['name'];
                $tenantStatus = $row['status'] ?? 'active';
                $tenantPlanId = isset($row['plan_id']) ? (string) $row['plan_id'] : '';
                $tenantPlanName = $row['plan_name'] ?? '';
                
                // Cálculo de expiração do teste de 30 dias a partir da data de cadastro
                if (!empty($row['created_at'])) {
                    $createdAt = new DateTime($row['created_at']);
                    $now = new DateTime();
                    $daysUsed = (int) $now->diff($createdAt)->days;
                    $trialDaysLeft = max(0, 30 - $daysUsed);
                    $trialExpired = ($daysUsed >= 30);
                }

                $planLimit = getTenantPlanTokenLimit($db, $tenantId);
                $bonus = getTenantTokenBonus($db, $tenantId, null);
                $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
                $used = getTenantTokensUsed($db, $tenantId, null);
                $tokenUsage = [
                    'used' => $used,
                    'limit' => $effectiveLimit,
                    'planLimit' => $planLimit,
                            'bonus' => $bonus,
                            'available' => $effectiveLimit > 0 ? max(0, $effectiveLimit - $used) : null,
                            'limitReached' => ($effectiveLimit > 0 && $used >= $effectiveLimit) || $trialExpired,
                            'modules' => mpTenantModules($db, $tenantId, $user)
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
                'phone' => $user['phone'],
                'tenantId' => $tenantId !== null ? (string)$tenantId : '',
                'profile' => $user['profile']
            ],
            'tenant' => [
                'id' => $tenantId !== null ? (string)$tenantId : '',
                'name' => $tenantName,
                'status' => $tenantStatus,
                'planId' => $tenantPlanId,
                'planName' => $tenantPlanName,
                'trialDaysLeft' => $trialDaysLeft,
                'trialExpired' => $trialExpired
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
                $tokenUsage = ['used' => 0, 'limit' => 0, 'planLimit' => 0, 'bonus' => 0, 'available' => 0, 'limitReached' => false, 'modules' => []];
                $tenantPlanId = '';
                $tenantPlanName = '';
                $trialDaysLeft = 30;
                $trialExpired = false;

                if ($tenantId) {
                    $stmtT = $db->prepare("SELECT t.name, t.status, t.plan_id, t.created_at, p.name as plan_name FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ?");
                    $stmtT->execute([$tenantId]);
                    $row = $stmtT->fetch();
                    if ($row) {
                        $tenantName = $row['name'];
                        $tenantStatus = $row['status'] ?? 'active';
                        $tenantPlanId = isset($row['plan_id']) ? (string) $row['plan_id'] : '';
                        $tenantPlanName = $row['plan_name'] ?? '';
                        
                        // Cálculo de expiração do teste de 30 dias
                        if (!empty($row['created_at'])) {
                            $createdAt = new DateTime($row['created_at']);
                            $now = new DateTime();
                            $daysUsed = (int) $now->diff($createdAt)->days;
                            $trialDaysLeft = max(0, 30 - $daysUsed);
                            $trialExpired = ($daysUsed >= 30);
                        }

                        $planLimit = getTenantPlanTokenLimit($db, $tenantId);
                        $bonus = getTenantTokenBonus($db, $tenantId, null);
                        $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
                        $used = getTenantTokensUsed($db, $tenantId, null);
                        $tokenUsage = [
                            'used' => $used,
                            'limit' => $effectiveLimit,
                            'planLimit' => $planLimit,
                            'bonus' => $bonus,
                            'available' => $effectiveLimit > 0 ? max(0, $effectiveLimit - $used) : null,
                            'limitReached' => ($effectiveLimit > 0 && $used >= $effectiveLimit) || $trialExpired,
                            'modules' => mpTenantModules($db, $tenantId, $user)
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
                        'phone' => $user['phone'],
                        'tenantId' => $tenantId !== null ? (string)$tenantId : '',
                        'profile' => $user['profile']
                    ],
                    'tenant' => [
                        'id' => $tenantId !== null ? (string)$tenantId : '',
                        'name' => $tenantName,
                        'status' => $tenantStatus,
                        'planId' => $tenantPlanId,
                        'planName' => $tenantPlanName,
                        'trialDaysLeft' => $trialDaysLeft,
                        'trialExpired' => $trialExpired
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
        $tokenUsage = ['used' => 0, 'limit' => 0, 'planLimit' => 0, 'bonus' => 0, 'available' => 0, 'limitReached' => false, 'modules' => []];
        $tenantPlanId = '';
        $tenantPlanName = '';
        $trialDaysLeft = 30;
        $trialExpired = false;

        $stmtT = $db->prepare("SELECT t.name, t.status, t.plan_id, t.created_at, p.name as plan_name FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ?");
        $stmtT->execute([$tenantId]);
        $row = $stmtT->fetch();
        if ($row) {
            $tenantName = $row['name'];
            $tenantStatus = $row['status'] ?? 'active';
            $tenantPlanId = isset($row['plan_id']) ? (string) $row['plan_id'] : '';
            $tenantPlanName = $row['plan_name'] ?? '';
            
            // Cálculo de expiração do teste
            if (!empty($row['created_at'])) {
                $createdAt = new DateTime($row['created_at']);
                $now = new DateTime();
                $daysUsed = (int) $now->diff($createdAt)->days;
                $trialDaysLeft = max(0, 30 - $daysUsed);
                $trialExpired = ($daysUsed >= 30);
            }

            $planLimit = getTenantPlanTokenLimit($db, $tenantId);
            $bonus = getTenantTokenBonus($db, $tenantId, null);
            $effectiveLimit = $planLimit > 0 ? $planLimit + $bonus : 0;
            $used = getTenantTokensUsed($db, $tenantId, null);
            $tokenUsage = [
                'used' => $used,
                'limit' => $effectiveLimit,
                'planLimit' => $planLimit,
                            'bonus' => $bonus,
                            'available' => $effectiveLimit > 0 ? max(0, $effectiveLimit - $used) : null,
                            'limitReached' => ($effectiveLimit > 0 && $used >= $effectiveLimit) || $trialExpired,
'modules' => mpTenantModules($db, $tenantId, $targetUser)
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
                'planName' => $tenantPlanName,
                'trialDaysLeft' => $trialDaysLeft,
                'trialExpired' => $trialExpired
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

    } elseif ($action === 'update_profile') {
        // Alterar senha ou atualizar telefone do usuário logado
        if (empty($_SESSION['user_id'])) {
            jsonError('Faça login para alterar o perfil.', 401);
        }
        
        $currentPassword = $input['currentPassword'] ?? '';
        $newPassword = $input['newPassword'] ?? '';
        $newPhone = isset($input['phone']) ? sanitizeInput($input['phone']) : null;
        
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

        // Verifica a senha atual se o usuário estiver tentando mudar a senha
        if (!empty($newPassword)) {
            if (empty($currentPassword) || !password_verify($currentPassword, $user['password'])) {
                jsonError('Senha atual incorreta.');
            }
            if (strlen($newPassword) < 6) {
                jsonError('A nova senha deve ter no mínimo 6 caracteres.');
            }
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ?, phone = ? WHERE id = ?");
            $stmt->execute([$newHash, $newPhone, $_SESSION['user_id']]);
        } else {
            // Apenas atualiza o telefone, sem mudar a senha
            $stmt = $db->prepare("UPDATE users SET phone = ? WHERE id = ?");
            $stmt->execute([$newPhone, $_SESSION['user_id']]);
        }
        
        jsonSuccess(null, 'Perfil atualizado com sucesso.');
        
    }
}
// Gerar novo token único de sessão no login bem-sucedido
$newSessionToken = bin2hex(random_bytes(32));
$_SESSION['session_token'] = $newSessionToken;

// Salva o token atual no banco de dados
$stmtToken = $db->prepare("UPDATE users SET current_session_token = ? WHERE id = ?");
$stmtToken->execute([$newSessionToken, $user['id']]);