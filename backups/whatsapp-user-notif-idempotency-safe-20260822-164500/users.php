<?php
/**
 * API de Gerenciamento de Usuários (Minha Equipe)
 * Endpoint: /api/users.php
 */

// Previne qualquer output antes do JSON
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/CrmAddonService.php';

// Limpa o output buffer
ob_clean();

$userId = requireAuth();
$authUser = getAuthUser();
$db = Database::getInstance()->getConnection();

$tenantId = $authUser['tenant_id'] ?? null;
$profile = strtolower((string)($authUser['profile'] ?? ''));

// Permite administradores, supervisores/gerentes e Super Admins da plataforma
$isSuperAdmin = ($profile === 'super_admin');
$isAdmin = ($profile === 'admin');
$isSupervisor = in_array($profile, ['supervisor', 'manager', 'gerente'], true);

function usersSeatQuota(PDO $db, int $tenantId): array
{
    $included = 1;
    $source = 'Plano atual';
    $planName = '';
    try {
        $stmt = $db->prepare("SELECT p.name, p.slug, COALESCE(p.included_users, 1) AS included_users FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ? LIMIT 1");
        $stmt->execute([$tenantId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($plan)) {
            $included = max(1, (int) ($plan['included_users'] ?? 1));
            $planName = (string) ($plan['name'] ?? '');
        }
    } catch (Throwable $e) {
        error_log('users quota plan: ' . $e->getMessage());
    }
    try {
        $license = crmAddonActiveLicense($db, $tenantId);
        if (is_array($license)) {
            $included = max(1, (int) ($license['included_users'] ?? $included));
            $source = 'CRM adicional';
            $planName = 'CRM adicional';
        }
    } catch (Throwable $e) {
        error_log('users quota crm: ' . $e->getMessage());
    }
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND (status IS NULL OR status = 'active')");
        $stmt->execute([$tenantId]);
        $current = (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        $current = (int) $stmt->fetchColumn();
    }
    return [
        'includedUsers' => $included,
        'currentUsers' => $current,
        'availableUsers' => max(0, $included - $current),
        'excessUsers' => max(0, $current - $included),
        'source' => $source,
        'planName' => $planName,
    ];
}

if (!$isAdmin && !$isSuperAdmin && !$isSupervisor) {
    jsonError('Acesso restrito ao administrador ou supervisor da empresa.', 403);
}

// Se for Super Admin e não tiver tenant_id associado, vincula à empresa padrão da plataforma (ID 1)
if ($tenantId === null) {
    $tenantId = 1;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    if (strtolower((string) ($_GET['action'] ?? '')) === 'quota') {
        jsonSuccess(usersSeatQuota($db, (int) $tenantId));
    }
    // Listar somente usuários da mesma empresa. A autoria do cadastro é uma
    // trilha de auditoria interna; nunca é consultada fora do tenant atual.
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.profile, u.created_at,
               u.created_by_user_id,
               COALESCE(NULLIF(creator.name, ''), NULLIF(u.created_by_name, ''),
                        CASE WHEN u.created_by_user_id IS NULL THEN 'Cadastro anterior à auditoria'
                             ELSE CONCAT('Usuário #', u.created_by_user_id) END) AS created_by_name
        FROM users u
        LEFT JOIN users creator ON creator.id = u.created_by_user_id
        WHERE u.tenant_id = ? AND LOWER(u.profile) != 'super_admin'
        ORDER BY u.name ASC
    ");
    $stmt->execute([$tenantId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    jsonSuccess($users);

} elseif ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        jsonError('Dados não fornecidos', 400);
    }

    $action = $input['action'] ?? '';

    // =========================================================================
    // SUB-MÉTODO: ATUALIZAR COLABORADOR EXISTENTE (PROXY DO PUT)
    // =========================================================================
    if ($action === 'update') {
        $targetUserId = isset($input['id']) ? (int)$input['id'] : null;
        if (empty($targetUserId)) {
            jsonError('ID do colaborador não fornecido para edição.', 400);
        }

        // Verifica se o colaborador a ser editado realmente pertence à mesma empresa
        $stmtCheck = $db->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
        $stmtCheck->execute([$targetUserId, $tenantId]);
        if (!$stmtCheck->fetch()) {
            jsonError('Colaborador não encontrado na sua empresa.', 404);
        }

        $name = sanitizeInput($input['name'] ?? '');
        $email = sanitizeInput(trim(strtolower($input['email'] ?? '')));
        $phone = sanitizeInput(trim($input['phone'] ?? ''));
        $password = $input['password'] ?? '';
        
        // Valida e sanitiza o cargo (suporta admin, supervisor/manager e user)
        $profileInput = sanitizeInput($input['profile'] ?? 'user');
        $validProfiles = ['user', 'supervisor', 'manager', 'admin'];
        if (!in_array($profileInput, $validProfiles, true)) {
            $profileInput = 'user';
        }

        if (empty($name) || empty($email)) {
            jsonError('Os campos Nome e E-mail são obrigatórios.', 400);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            jsonError('Por favor, insira um e-mail válido.', 400);
        }

        // Verifica se o e-mail editado já pertence a outro usuário cadastrado no sistema
        $stmtCheckEmail = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $stmtCheckEmail->execute([$email, $targetUserId]);
        if ($stmtCheckEmail->fetch()) {
            jsonError('Este e-mail já está em uso por outro colaborador no sistema.', 400);
        }

        // Se uma nova senha foi digitada, atualiza com hash. Caso contrário, mantém a senha atual.
        if (!empty($password)) {
            if (strlen($password) < 6) {
                jsonError('A nova senha deve ter no mínimo 6 caracteres.', 400);
            }
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);
            $stmtUpdate = $db->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, password = ?, profile = ? 
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtUpdate->execute([$name, $email, $phone, $passwordHash, $profileInput, $targetUserId, $tenantId]);
        } else {
            $stmtUpdate = $db->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, profile = ? 
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtUpdate->execute([$name, $email, $phone, $profileInput, $targetUserId, $tenantId]);
        }

        jsonSuccess(null, 'Colaborador atualizado com sucesso!');
        exit;
    }

    // =========================================================================
    // SUB-MÉTODO: ADICIONAR NOVO COLABORADOR À EQUIPE (PADRÃO)
    // =========================================================================
    $name = sanitizeInput($input['name'] ?? '');
    $email = sanitizeInput(trim(strtolower($input['email'] ?? '')));
    $phone = sanitizeInput(trim($input['phone'] ?? '')); 
    $password = $input['password'] ?? '';
    
    $profileInput = sanitizeInput($input['profile'] ?? 'user');
    $validProfiles = ['user', 'supervisor', 'manager', 'admin'];
    if (!in_array($profileInput, $validProfiles, true)) {
        $profileInput = 'user';
    }

    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        jsonError('Preencha os campos obrigatórios (Nome, E-mail, Telefone e Senha).', 400);
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        jsonError('Por favor, insira um e-mail válido.', 400);
    }

    $phoneDigits = preg_replace('/\D+/', '', $phone);
    if (strlen($phoneDigits) < 8 || strlen($phoneDigits) > 15) {
        jsonError('Por favor, informe um telefone válido com DDD.', 400);
    }

    if (strlen($password) < 6) {
        jsonError('A senha do usuário deve ter no mínimo 6 caracteres.', 400);
    }

    // Verifica se o e-mail já está cadastrado globalmente no sistema
    $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmtCheck->execute([$email]);
    if ($stmtCheck->fetch()) {
        jsonError('Este e-mail já está em uso por outro usuário no sistema.', 409, 'duplicate_email');
    }

    $quota = usersSeatQuota($db, (int) $tenantId);
    if ((int) $quota['currentUsers'] >= (int) $quota['includedUsers']) {
        jsonError('O limite de usuários deste ' . strtolower((string) ($quota['source'] ?: 'plano')) . ' foi atingido (' . (int) $quota['includedUsers'] . '). Remova um usuário ou contrate uma franquia CRM maior antes de cadastrar outro.', 403, 'user_limit_reached');
    }

    // Hash seguro da senha
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    if ($passwordHash === false) {
        jsonError('Não foi possível proteger a senha do usuário. Tente novamente.', 500, 'password_hash_failed');
    }

    // Armazena a autoria no momento do cadastro. O nome é mantido como
    // referência histórica mesmo se a conta criadora for removida futuramente.
    $creatorLabel = trim((string)($authUser['name'] ?? ''));
    if ($creatorLabel === '') {
        $creatorLabel = trim((string)($authUser['email'] ?? 'Usuário não informado'));
    }

    // Insere o usuário com o cargo selecionado e auditoria de criação.
    try {
        $stmt = $db->prepare("
            INSERT INTO users (tenant_id, name, email, phone, password, profile, created_by_user_id, created_by_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$tenantId, $name, $email, $phone, $passwordHash, $profileInput, $userId, $creatorLabel]);
    } catch (PDOException $e) {
        $isDuplicate = ((string) $e->getCode() === '23000') || (($e->errorInfo[1] ?? null) === 1062);
        error_log('users.php create: ' . $e->getMessage());
        if ($isDuplicate) {
            jsonError('Este e-mail já está em uso por outro usuário no sistema.', 409, 'duplicate_email');
        }
        jsonError('Não foi possível cadastrar o usuário agora. Nenhuma alteração foi confirmada; tente novamente.', 500, 'user_create_failed');
    }

    // O cadastro já foi confirmado. Falhas em configurações ou notificações não
    // podem transformar uma inclusão concluída em uma resposta de erro ambígua.
    try {
        $saasName = getPlatformSetting($db, 'saas_company_name') ?? 'Maps Prospector';
    
    $stmtSettings = $db->prepare("SELECT tenant_name FROM settings WHERE user_id = ? LIMIT 1");
    $stmtSettings->execute([$userId]);
    $settingsRow = $stmtSettings->fetch();
    $companyName = (!empty($settingsRow['tenant_name'])) ? $settingsRow['tenant_name'] : 'sua empresa';

    // Dispara notificação de boas-vindas via WhatsApp (se o telefone foi preenchido)
    if (!empty($phone)) {
        try {
            require_once __DIR__ . '/../services/notificationService.php';
            $msgWhatsapp = "Olá *$name*!\n\nVocê foi cadastrado como colaborador no painel de prospecção da empresa *$companyName* na plataforma *$saasName*.\n\n*Dados de acesso:*\n🌐 *Link do Painel:* https://prospec.atendo.log.br\n✉️ *Seu e-mail:* $email\n\nPor segurança, a senha não é enviada por WhatsApp. Use a senha definida no cadastro ou solicite a redefinição ao administrador. Bons negócios!";
            
            NotificationService::sendWhatsApp($db, $phone, $msgWhatsapp);
        } catch (Exception $e) {
            error_log("users.php error sending onboarding whatsapp: " . $e->getMessage());
        }
    }

    // Dispara e-mail de onboarding
    try {
        require_once __DIR__ . '/../services/notificationService.php';
        $subject = "Sua conta de colaborador foi criada! 🚀";
        $messageHtml = "
            <div style='font-family: Arial, sans-serif; color: #1e293b; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 10px;'>
                <h2 style='color: #2563eb;'>Olá, $name!</h2>
                <p>Sua conta de colaborador foi criada com sucesso na plataforma <strong>$saasName</strong> para atuar na empresa <strong>$companyName</strong>.</p>
                <p><strong>Aqui estão os seus dados de acesso:</strong></p>
                <ul>
                    <li><strong>Link de Acesso:</strong> <a href='https://prospec.atendo.log.br'>https://prospec.atendo.log.br</a></li>
                    <li><strong>Seu E-mail:</strong> $email</li>
                    <li><strong>Senha:</strong> definida pelo administrador no cadastro</li>
                </ul>
                <p>Guarde estes dados com segurança e boas prospecções!</p>
                <p style='margin-top: 30px; font-size: 12px; color: #64748b;'>Atenciosamente,<br>Equipe $saasName</p>
            </div>
        ";
        NotificationService::sendEmail($db, $email, $subject, $messageHtml);
    } catch (Exception $e) {
        error_log("users.php: erro ao enviar e-mail de onboarding: " . $e->getMessage());
    }
    } catch (Throwable $postCreateError) {
        error_log('users.php post-create notification/config: ' . $postCreateError->getMessage());
    }

    jsonSuccess(['created' => true], 'Colaborador adicionado à equipe com sucesso!');

} elseif ($method === 'DELETE') {
    // Remover colaborador da equipe
    $input = json_decode(file_get_contents('php://input'), true);
    $targetUserId = isset($input['id']) ? (int)$input['id'] : null;

    if (empty($targetUserId)) {
        jsonError('ID do usuário não fornecido', 400);
    }

    if ($targetUserId === $userId) {
        jsonError('Você não pode excluir a sua própria conta.', 400);
    }

    $stmtCheck = $db->prepare("SELECT id FROM users WHERE id = ? AND tenant_id = ? LIMIT 1");
    $stmtCheck->execute([$targetUserId, $tenantId]);
    if (!$stmtCheck->fetch()) {
        jsonError('Usuário não encontrado na sua empresa.', 404);
    }

    $stmtDelete = $db->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?");
    $stmtDelete->execute([$targetUserId, $tenantId]);

    jsonSuccess(null, 'Colaborador removido da equipe com sucesso.');

} else {
    jsonError('Método não permitido', 405);
}
