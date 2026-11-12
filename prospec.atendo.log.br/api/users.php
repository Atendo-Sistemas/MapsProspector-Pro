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

function usersNormalizeTenantName(string $name): string
{
    $name = trim((string) (preg_replace('/\s+/u', ' ', $name) ?: $name));
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

/**
 * Resolve somente os vínculos legados do tenant padrão quando houver uma
 * evidência única: tenant_id direto, aceite legal, nome de empresa único nas
 * configurações ou cadeia de autoria. Conflitos e órfãos ficam sem associação.
 */
function usersBuildTenantMembershipMap(PDO $db): array
{
    $validTenants = [];
    $tenantNameIds = [];
    try {
        foreach ($db->query('SELECT id, name FROM tenants') as $tenant) {
            $tenantId = (int) ($tenant['id'] ?? 0);
            if ($tenantId <= 0) {
                continue;
            }
            $validTenants[$tenantId] = true;
            $normalized = usersNormalizeTenantName((string) ($tenant['name'] ?? ''));
            if ($normalized !== '') {
                $tenantNameIds[$normalized][] = $tenantId;
            }
        }
        $userRows = [];
        foreach ($db->query('SELECT id, tenant_id, profile, created_by_user_id FROM users') as $row) {
            $userRows[(int) $row['id']] = [
                'tenant_id' => (int) ($row['tenant_id'] ?? 0),
                'profile' => strtolower((string) ($row['profile'] ?? '')),
                'created_by_user_id' => (int) ($row['created_by_user_id'] ?? 0),
            ];
        }
    } catch (Throwable $error) {
        error_log('users.php tenant membership map unavailable: ' . $error->getMessage());
        return [];
    }

    $evidence = [];
    $addEvidence = static function (int $userId, int $tenantId) use (&$evidence, $validTenants): void {
        if ($userId > 0 && isset($validTenants[$tenantId])) {
            $evidence[$userId][$tenantId] = true;
        }
    };
    try {
        if ($db->query("SHOW TABLES LIKE 'legal_consents'")->fetchColumn()) {
            foreach ($db->query('SELECT user_id, tenant_id FROM legal_consents WHERE user_id IS NOT NULL AND tenant_id IS NOT NULL') as $row) {
                $addEvidence((int) $row['user_id'], (int) $row['tenant_id']);
            }
        }
    } catch (Throwable $error) {
        error_log('users.php legal-consent tenant evidence unavailable.');
    }
    try {
        if ($db->query("SHOW TABLES LIKE 'settings'")->fetchColumn()) {
            foreach ($db->query('SELECT user_id, tenant_name FROM settings WHERE user_id IS NOT NULL AND tenant_name IS NOT NULL') as $row) {
                $normalized = usersNormalizeTenantName((string) ($row['tenant_name'] ?? ''));
                $matches = $tenantNameIds[$normalized] ?? [];
                if ($normalized !== '' && count($matches) === 1) {
                    $addEvidence((int) $row['user_id'], (int) $matches[0]);
                }
            }
        }
    } catch (Throwable $error) {
        error_log('users.php settings tenant evidence unavailable.');
    }

    $membership = [];
    $ambiguous = [];
    foreach ($userRows as $id => $row) {
        if ($row['profile'] === 'super_admin') {
            continue;
        }
        $storedTenantId = (int) $row['tenant_id'];
        if ($storedTenantId > 1 && isset($validTenants[$storedTenantId])) {
            $membership[$id] = $storedTenantId;
            continue;
        }
        $candidateTenants = array_map('intval', array_keys($evidence[$id] ?? []));
        if (count($candidateTenants) === 1) {
            $membership[$id] = $candidateTenants[0];
        } elseif (count($candidateTenants) > 1) {
            $ambiguous[$id] = true;
        }
    }

    // Usuários antigos sem evidência própria podem ser associados ao criador
    // somente quando esse criador já foi resolvido de forma inequívoca.
    for ($pass = 0; $pass < count($userRows); $pass++) {
        $changed = false;
        foreach ($userRows as $id => $row) {
            if (isset($membership[$id]) || isset($ambiguous[$id]) || $row['profile'] === 'super_admin') {
                continue;
            }
            $creatorId = (int) $row['created_by_user_id'];
            if ($creatorId > 0 && isset($membership[$creatorId])) {
                $membership[$id] = $membership[$creatorId];
                $changed = true;
            }
        }
        if (!$changed) {
            break;
        }
    }
    return $membership;
}

function usersSeatQuota(PDO $db, int $tenantId, ?array $tenantMembershipMap = null): array
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
    if ($tenantMembershipMap !== null) {
        $current = 0;
        foreach ($tenantMembershipMap as $memberTenantId) {
            if ((int) $memberTenantId === $tenantId) {
                $current++;
            }
        }
    } else {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND (status IS NULL OR status = 'active')");
            $stmt->execute([$tenantId]);
            $current = (int) $stmt->fetchColumn();
        } catch (Throwable $e) {
            $stmt = $db->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            $current = (int) $stmt->fetchColumn();
        }
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

$method = $_SERVER['REQUEST_METHOD'];
$isPlatformAdminGlobal = $isSuperAdmin && empty($_SESSION['impersonating']);

$tenantMembershipMap = usersBuildTenantMembershipMap($db);
if ($tenantId === null || (int) $tenantId <= 0) {
    if ($isSuperAdmin) {
        // Mantém a área padrão da plataforma sem transformar a lista em global.
        $tenantId = 1;
    } else {
        $resolvedTenantId = (int) ($tenantMembershipMap[(int) $userId] ?? 0);
        if ($resolvedTenantId <= 1) {
            jsonError('Não foi possível identificar com segurança a empresa desta equipe. Solicite a revisão do vínculo ao suporte.', 403);
        }
        $tenantId = $resolvedTenantId;
    }
}
$tenantId = (int) $tenantId;
if (!$isSuperAdmin && $tenantId === 1) {
    $resolvedTenantId = (int) ($tenantMembershipMap[(int) $userId] ?? 0);
    if ($resolvedTenantId <= 1) {
        jsonError('Não foi possível identificar com segurança a empresa desta equipe. Solicite a revisão do vínculo ao suporte.', 403);
    }
    $tenantId = $resolvedTenantId;
}

if ($method === 'GET') {
    if (strtolower((string) ($_GET['action'] ?? '')) === 'quota') {
        if ($isPlatformAdminGlobal) {
            jsonError('Acesse uma empresa específica para consultar o limite de usuários da equipe.', 403);
        }
        jsonSuccess(usersSeatQuota($db, $tenantId, $tenantMembershipMap));
    }
    if ($isPlatformAdminGlobal) {
        $stmt = $db->query("
            SELECT u.id, u.name, u.email, u.phone, u.profile, u.can_search, u.created_at,
                   u.created_by_user_id,
                   COALESCE(NULLIF(creator.name, ''), NULLIF(u.created_by_name, ''),
                            CASE WHEN u.created_by_user_id IS NULL THEN 'Cadastro anterior à auditoria'
                                 ELSE CONCAT('Usuário #', u.created_by_user_id) END) AS created_by_name
            FROM users u
            LEFT JOIN users creator ON creator.id = u.created_by_user_id
            WHERE LOWER(COALESCE(u.profile, '')) != 'super_admin'
            ORDER BY u.name ASC
        ");
        jsonSuccess($stmt->fetchAll(PDO::FETCH_ASSOC));
    }
    $teamUserIds = [];
    foreach ($tenantMembershipMap as $memberId => $memberTenantId) {
        if ((int) $memberTenantId === $tenantId) {
            $teamUserIds[] = (int) $memberId;
        }
    }
    if ($teamUserIds === []) {
        jsonSuccess([]);
    }
    $memberPlaceholders = implode(',', array_fill(0, count($teamUserIds), '?'));
    // A lista usa o vínculo empresarial resolvido. A autoria só é mostrada
    // quando o autor também pertence a esta equipe, evitando metadados cruzados.
    $stmt = $db->prepare("
        SELECT u.id, u.name, u.email, u.phone, u.profile, u.can_search, u.created_at,
               CASE WHEN creator.id IS NOT NULL THEN u.created_by_user_id ELSE NULL END AS created_by_user_id,
               CASE WHEN creator.id IS NOT NULL THEN COALESCE(NULLIF(creator.name, ''), 'Membro da equipe')
                    WHEN u.created_by_user_id IS NULL THEN 'Cadastro anterior à auditoria'
                    ELSE 'Equipe da empresa' END AS created_by_name
        FROM users u
        LEFT JOIN users creator ON creator.id = u.created_by_user_id AND creator.id IN ({$memberPlaceholders})
        WHERE u.id IN ({$memberPlaceholders}) AND LOWER(COALESCE(u.profile, '')) != 'super_admin'
        ORDER BY u.name ASC
    ");
    $stmt->execute(array_merge($teamUserIds, $teamUserIds));
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

        // Verifica o vínculo empresarial efetivo, inclusive para usuários
        // legados cujo tenant_id persistido ainda é o tenant padrão.
        $stmtCheck = $db->prepare('SELECT id, can_search, tenant_id FROM users WHERE id = ? LIMIT 1');
        $stmtCheck->execute([$targetUserId]);
        $existingUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        $targetEffectiveTenantId = (int) ($tenantMembershipMap[$targetUserId] ?? 0);
        if (!$existingUser || $targetEffectiveTenantId <= 0
            || (!$isPlatformAdminGlobal && $targetEffectiveTenantId !== $tenantId)
        ) {
            jsonError('Colaborador não encontrado na sua empresa.', 404);
        }
        $storedTargetTenantId = (int) ($existingUser['tenant_id'] ?? 0);

        $name = sanitizeInput($input['name'] ?? '');
        $email = sanitizeInput(trim(strtolower($input['email'] ?? '')));
        $phone = sanitizeInput(trim($input['phone'] ?? ''));
        $password = $input['password'] ?? '';
        
        // Valida e sanitiza o cargo (suporta admin, supervisor/manager e user)
        $profileInput = sanitizeInput($input['profile'] ?? 'user');
        $canSearch = array_key_exists('can_search', $input)
            ? (filter_var($input['can_search'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0)
            : (int) $existingUser['can_search'];
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
                SET name = ?, email = ?, phone = ?, password = ?, profile = ?, can_search = ?
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtUpdate->execute([$name, $email, $phone, $passwordHash, $profileInput, $canSearch, $targetUserId, $storedTargetTenantId]);
        } else {
            $stmtUpdate = $db->prepare("
                UPDATE users 
                SET name = ?, email = ?, phone = ?, profile = ?, can_search = ?
                WHERE id = ? AND tenant_id = ?
            ");
            $stmtUpdate->execute([$name, $email, $phone, $profileInput, $canSearch, $targetUserId, $storedTargetTenantId]);
        }

        jsonSuccess(null, 'Colaborador atualizado com sucesso!');
        exit;
    }

    if ($isPlatformAdminGlobal) {
        jsonError('Acesse uma empresa específica para adicionar usuários à equipe.', 403);
    }

    // =========================================================================
    // SUB-MÉTODO: ADICIONAR NOVO COLABORADOR À EQUIPE (PADRÃO)
    // =========================================================================
    $name = sanitizeInput($input['name'] ?? '');
    $email = sanitizeInput(trim(strtolower($input['email'] ?? '')));
    $phone = sanitizeInput(trim($input['phone'] ?? '')); 
    $password = $input['password'] ?? '';
    
    $profileInput = sanitizeInput($input['profile'] ?? 'user');
    $canSearch = array_key_exists('can_search', $input)
        ? (filter_var($input['can_search'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0)
        : 1;
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

    $quota = usersSeatQuota($db, $tenantId, $tenantMembershipMap);
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
            INSERT INTO users (tenant_id, name, email, phone, password, profile, can_search, created_by_user_id, created_by_name, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$tenantId, $name, $email, $phone, $passwordHash, $profileInput, $canSearch, $userId, $creatorLabel]);
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
            
            NotificationService::sendWhatsApp($db, $phone, $msgWhatsapp, 'user-registration-' . $userId);
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

    $stmtCheck = $db->prepare('SELECT id, tenant_id FROM users WHERE id = ? LIMIT 1');
    $stmtCheck->execute([$targetUserId]);
    $existingUser = $stmtCheck->fetch(PDO::FETCH_ASSOC);
    $targetEffectiveTenantId = (int) ($tenantMembershipMap[$targetUserId] ?? 0);
    if (!$existingUser || $targetEffectiveTenantId <= 0
        || (!$isPlatformAdminGlobal && $targetEffectiveTenantId !== $tenantId)
    ) {
        jsonError('Usuário não encontrado na sua empresa.', 404);
    }
    $storedTargetTenantId = (int) ($existingUser['tenant_id'] ?? 0);

    $stmtDelete = $db->prepare("DELETE FROM users WHERE id = ? AND tenant_id = ?");
    $stmtDelete->execute([$targetUserId, $storedTargetTenantId]);

    jsonSuccess(null, 'Colaborador removido da equipe com sucesso.');

} else {
    jsonError('Método não permitido', 405);
}
