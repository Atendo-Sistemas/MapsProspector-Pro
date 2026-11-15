<?php
/**
 * API de Cadastro Público de Empresa com registro explícito de aceite.
 * Substitua /api/register.php por este arquivo somente após executar
 * sql/linkedin-implementacao.sql no banco.
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
ob_clean();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido', 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$companyName = sanitizeInput(trim($input['companyName'] ?? $input['name'] ?? ''));
$companyDocument = preg_replace('/\D+/', '', (string) ($input['companyDocument'] ?? $input['cnpj'] ?? ''));
$slug = isset($input['slug']) ? sanitizeInput(trim($input['slug'])) : '';
$adminEmail = trim(strtolower($input['adminEmail'] ?? $input['email'] ?? ''));
$adminPhone = sanitizeInput(trim($input['adminPhone'] ?? $input['phone'] ?? ''));
$adminName = sanitizeInput(trim($input['adminName'] ?? ''));
$adminPassword = $input['adminPassword'] ?? $input['password'] ?? '';
$legalConsents = is_array($input['legalConsents'] ?? null) ? $input['legalConsents'] : [];
$termsConsent = is_array($legalConsents['terms_of_use'] ?? null) ? $legalConsents['terms_of_use'] : [];
$privacyConsent = is_array($legalConsents['privacy_policy'] ?? null) ? $legalConsents['privacy_policy'] : [];
$termsAccepted = ($termsConsent['accepted'] ?? false) === true;
$privacyAccepted = ($privacyConsent['accepted'] ?? false) === true;
// As versões são controladas no servidor. Uma alteração exige novo aceite,
// sem apagar as versões anteriores registradas em legal_consents.
$termsVersion = '2026-08-17';
$privacyVersion = '2026-08-17';
$termsUrl = 'termos.php';
$privacyUrl = 'privacidade.php';

function registerNormalizePhoneIdentity(string $phone): string
{
    $digits = preg_replace('/\\D+/', '', $phone) ?? '';
    if (substr($digits, 0, 2) === '00') $digits = substr($digits, 2);
    if (in_array(strlen($digits), [10, 11], true)) $digits = '55' . $digits;
    return $digits;
}

function registerIdentityFingerprint(string $type, string $value): string
{
    return hash_hmac('sha256', $type . ':' . strtolower(trim($value)), getLeadEncryptionKey());
}

if ($companyName === '') {
    jsonError('Nome da empresa é obrigatório', 400);
}
if ($companyDocument !== '' && !in_array(strlen($companyDocument), [11, 14], true)) {
    jsonError('Informe um CPF ou CNPJ válido, ou deixe o campo em branco.', 400);
}
if ($adminPhone === '') {
    jsonError('O número de WhatsApp é obrigatório para o cadastro', 400);
}
$adminPhoneDigits = preg_replace('/\\D+/', '', $adminPhone) ?? '';
if (strlen($adminPhoneDigits) < 8 || strlen($adminPhoneDigits) > 15) {
    jsonError('Informe um telefone válido para o cadastro.', 400);
}
$phoneIdentity = registerNormalizePhoneIdentity($adminPhone);
if ($adminEmail === '' || !validateEmail($adminEmail)) {
    jsonError('E-mail do administrador é obrigatório e deve ser válido', 400);
}
if (strlen($adminPassword) < 6) {
    jsonError('A senha deve ter no mínimo 6 caracteres', 400);
}
if (!$termsAccepted || !$privacyAccepted) {
    jsonError('É necessário aceitar os Termos de Uso e a Política de Privacidade para criar a conta.', 400);
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}

try {
    $db->query('SELECT 1 FROM tenants LIMIT 1');
    $db->query('SELECT 1 FROM legal_consents LIMIT 1');
    $claimsTableReady = (bool) $db->query("SHOW TABLES LIKE 'free_signup_credit_claims'")->fetchColumn();
    if (!$claimsTableReady) {
        jsonError('O cadastro freemium ainda não foi preparado. Execute Database/database_migration_freemium.sql.', 503);
    }
    $freePlanStmt = $db->prepare("SELECT id, token_limit, price_monthly, status FROM plans WHERE slug = 'freemium-20' LIMIT 1");
    $freePlanStmt->execute();
    $freePlan = $freePlanStmt->fetch(PDO::FETCH_ASSOC);
    if (!is_array($freePlan) || (string) $freePlan['status'] !== 'active' || (int) $freePlan['token_limit'] !== 20 || (float) $freePlan['price_monthly'] !== 0.0) {
        jsonError('O plano freemium de 20 créditos não está disponível. Verifique Database/database_migration_freemium.sql.', 503);
    }
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'legal_consents') !== false || strpos($e->getMessage(), '1146') !== false) {
        jsonError('A tabela de aceites não existe. Execute sql/linkedin-implementacao.sql no banco antes de habilitar o cadastro com termos.', 500);
    }
    jsonError('Estrutura de banco necessária para o cadastro não encontrada.', 500);
}

if ($slug === '') {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $companyName));
    $slug = trim($slug, '-');
}
if ($slug === '' || strlen($slug) > 100) {
    jsonError('Não foi possível gerar um identificador válido para a empresa.', 400);
}
if ($adminName === '') {
    $adminName = ucfirst(explode('@', $adminEmail)[0]);
}

try {
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$adminEmail]);
    if ($stmt->fetch()) {
        jsonError('Este e-mail já está cadastrado. Use a tela de login para entrar.', 400);
    }

    $phoneLocalIdentity = substr($phoneIdentity, 0, 2) === '55' && in_array(strlen($phoneIdentity) - 2, [10, 11], true)
        ? substr($phoneIdentity, 2)
        : $phoneIdentity;
    $phoneVariants = array_values(array_unique([$phoneIdentity, $phoneLocalIdentity, '00' . $phoneIdentity]));
    $phonePlaceholders = implode(',', array_fill(0, count($phoneVariants), '?'));
    $phoneDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', ''), '/', '')";
    $existingPhone = $db->prepare("SELECT id FROM users WHERE {$phoneDigitsSql} IN ({$phonePlaceholders}) LIMIT 1");
    $existingPhone->execute($phoneVariants);
    if ($existingPhone->fetchColumn()) {
        jsonError('Este telefone já foi usado em um cadastro. Entre na conta existente ou fale com o suporte.', 409, 'free_signup_identity_used');
    }

    if ($companyDocument !== '') {
        $documentDigitsSql = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cnpj, ''), '.', ''), '/', ''), '-', ''), ' ', '')";
        $existingDocument = $db->prepare("SELECT id FROM tenants WHERE {$documentDigitsSql} = ? LIMIT 1");
        $existingDocument->execute([$companyDocument]);
        if ($existingDocument->fetchColumn()) {
            jsonError('Este CPF/CNPJ já está vinculado a uma empresa cadastrada.', 409, 'free_signup_identity_used');
        }
    }

    $identityClaims = [
        ['email', registerIdentityFingerprint('email', $adminEmail)],
        ['phone', registerIdentityFingerprint('phone', $phoneIdentity)],
    ];
    if ($companyDocument !== '') {
        $identityClaims[] = ['document', registerIdentityFingerprint('document', $companyDocument)];
    }
    $claimCheck = $db->prepare('SELECT 1 FROM free_signup_credit_claims WHERE identity_type = ? AND identity_hash = ? LIMIT 1');
    foreach ($identityClaims as [$identityType, $identityHash]) {
        $claimCheck->execute([$identityType, $identityHash]);
        if ($claimCheck->fetchColumn()) {
            jsonError('Este e-mail, telefone ou CPF/CNPJ já recebeu o cadastro gratuito.', 409, 'free_signup_identity_used');
        }
    }

    $slugBase = $slug;
    for ($suffix = 1; $suffix <= 9999; $suffix++) {
        $slugToUse = $suffix === 1 ? $slugBase : $slugBase . '-' . $suffix;
        $stmt = $db->prepare('SELECT id FROM tenants WHERE slug = ?');
        $stmt->execute([$slugToUse]);
        if (!$stmt->fetch()) {
            $slug = $slugToUse;
            break;
        }
    }
    if ($suffix > 9999) {
        jsonError('Não foi possível gerar um identificador único. Tente outro nome.', 400);
    }

    $planId = (int) $freePlan['id'];
    $planSlug = 'freemium-20';

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO tenants (name, cnpj, slug, plan_id, plan, status) VALUES (?, ?, ?, ?, ?, 'active')");
    $stmt->execute([$companyName, $companyDocument !== '' ? $companyDocument : null, $slug, $planId, $planSlug]);
    $tenantId = (int) $db->lastInsertId();

    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (name, email, phone, password, tenant_id, profile) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$adminName, $adminEmail, $adminPhone, $passwordHash, $tenantId, 'admin']);
    $userId = (int) $db->lastInsertId();

    $claimInsert = $db->prepare('INSERT INTO free_signup_credit_claims (identity_type, identity_hash, tenant_id, user_id) VALUES (?, ?, ?, ?)');
    foreach ($identityClaims as [$identityType, $identityHash]) {
        $claimInsert->execute([$identityType, $identityHash, $tenantId, $userId]);
    }

    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $stmt = $db->prepare(
        'INSERT INTO legal_consents
         (tenant_id, user_id, consent_key, document_version, document_url, source, accepted_at, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)'
    );
    $stmt->execute([
        $tenantId, $userId, 'terms_of_use', $termsVersion,
        $termsUrl, 'company_registration', $userAgent,
    ]);
    $stmt->execute([
        $tenantId, $userId, 'privacy_policy', $privacyVersion,
        $privacyUrl, 'company_registration', $userAgent,
    ]);

    try {
        $stmt = $db->prepare('INSERT INTO settings (user_id, tenant_name) VALUES (?, ?)');
        $stmt->execute([$userId, $companyName]);
    } catch (PDOException $e) {
        error_log('register.php: não foi possível criar settings iniciais: ' . $e->getMessage());
    }

    $db->commit();

    try {
        require_once __DIR__ . '/../services/notificationService.php';
        NotificationService::notifyNewRegistration($db, $companyName, $adminEmail, $adminPhone, $adminName);
    } catch (Exception $e) {
        error_log('register.php: erro ao enviar notificações de boas-vindas: ' . $e->getMessage());
    }

    jsonSuccess([
        'tenantId' => (string) $tenantId,
        'userId' => (string) $userId,
        'tenantName' => $companyName,
    ], 'Empresa cadastrada com sucesso. Faça login com seu e-mail.');
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    $isDuplicate = (string) $e->getCode() === '23000' || (int) ($e->errorInfo[1] ?? 0) === 1062;
    if ($isDuplicate) {
        jsonError('Este e-mail, telefone ou CPF/CNPJ já recebeu o cadastro gratuito.', 409, 'free_signup_identity_used');
    }
    error_log('Erro ao registrar empresa: ' . $e->getMessage());
    jsonError('Erro ao cadastrar. Tente novamente.', 500);
}
