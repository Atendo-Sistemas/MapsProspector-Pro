<?php
/**
 * API de Cadastro Público de Empresa (Registro SaaS)
 * Endpoint: /api/register.php
 * Cria uma nova empresa (tenant) e o primeiro usuário (admin). Público, sem autenticação.
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
$slug = isset($input['slug']) ? sanitizeInput(trim($input['slug'])) : '';
$adminEmail = trim(strtolower($input['adminEmail'] ?? $input['email'] ?? ''));
$adminName = sanitizeInput(trim($input['adminName'] ?? ''));
$adminPassword = $input['adminPassword'] ?? $input['password'] ?? '';

if (empty($companyName)) {
    jsonError('Nome da empresa é obrigatório', 400);
}

if (empty($adminEmail) || !validateEmail($adminEmail)) {
    jsonError('E-mail do administrador é obrigatório e deve ser válido', 400);
}

if (strlen($adminPassword) < 6) {
    jsonError('A senha deve ter no mínimo 6 caracteres', 400);
}

try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    jsonError($e->getMessage(), 500);
}

// Garante que a tabela tenants existe (migração SaaS)
try {
    $db->query("SELECT 1 FROM tenants LIMIT 1");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'exist') !== false || strpos($e->getMessage(), '1146') !== false) {
        jsonError('Tabela de empresas não existe. Execute database_migration_saas.sql no banco de dados (phpMyAdmin ou MySQL).', 500);
    }
    throw $e;
}

if (empty($slug)) {
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $companyName));
    $slug = trim($slug, '-');
}
if (empty($slug)) {
    jsonError('Não foi possível gerar um identificador para a empresa. Informe um nome válido.', 400);
}

if (strlen($slug) > 100) {
    jsonError('Identificador da empresa muito longo.', 400);
}

if (empty($adminName)) {
    $adminName = ucfirst(explode('@', $adminEmail)[0]);
}

try {
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$adminEmail]);
    if ($stmt->fetch()) {
        jsonError('Este e-mail já está cadastrado. Use a tela de login para entrar.', 400);
    }

    // Garante slug único: se já existir, acrescenta sufixo numérico (-2, -3, ...)
    $slugBase = $slug;
    $suffix = 1;
    do {
        $slugToUse = $suffix === 1 ? $slugBase : $slugBase . '-' . $suffix;
        $stmt = $db->prepare("SELECT id FROM tenants WHERE slug = ?");
        $stmt->execute([$slugToUse]);
        if (!$stmt->fetch()) {
            $slug = $slugToUse;
            break;
        }
        $suffix++;
        if ($suffix > 9999) {
            jsonError('Não foi possível gerar um identificador único. Tente outro nome.', 400);
        }
    } while (true);

    // Plano de período de teste: 10 créditos grátis para novos usuários
    $planId = 1;
    $planSlug = 'basic';
    try {
        $stmt = $db->prepare("SELECT id, slug FROM plans WHERE slug = 'trial' AND status = 'active' LIMIT 1");
        $stmt->execute();
        $trial = $stmt->fetch();
        if ($trial) {
            $planId = (int) $trial['id'];
            $planSlug = 'trial';
        }
    } catch (PDOException $e) {
        // Fallback: usa plano básico se tabela plans não existir
    }

    $db->beginTransaction();

    $stmt = $db->prepare("INSERT INTO tenants (name, slug, plan_id, plan, status) VALUES (?, ?, ?, ?, 'active')");
    $stmt->execute([$companyName, $slug, $planId, $planSlug]);
    $tenantId = (int) $db->lastInsertId();

    $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (name, email, password, tenant_id, profile) VALUES (?, ?, ?, ?, 'admin')");
    $stmt->execute([$adminName, $adminEmail, $passwordHash, $tenantId]);
    $userId = (int) $db->lastInsertId();

    $db->commit();

    jsonSuccess([
        'tenantId' => (string) $tenantId,
        'userId' => (string) $userId,
        'tenantName' => $companyName,
        'message' => 'Empresa cadastrada com sucesso. Faça login com seu e-mail.',
    ], 'Empresa cadastrada com sucesso. Faça login com seu e-mail.');

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Erro ao registrar empresa: " . $e->getMessage());
    jsonError('Erro ao cadastrar. Tente novamente.', 500);
}
