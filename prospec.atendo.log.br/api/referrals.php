<?php
declare(strict_types=1);

/**
 * Painel de Indicações (v64f).
 *
 * Ações públicas (anonimas):
 *   click?code=...      — registra clique no link de indicação (INSERT sem auth)
 *
 * Ações autenticadas (qualquer perfil logado):
 *   stats               — cliques recebidos, indicações convertidas, saldo a receber,
 *                         código de indicação, link público e status de saque
 *   payment-profile     — lê/atualiza a chave PIX do próprio participante
 *
 * Ações super admin:
 *   admin-dashboard     — visão consolidada, ranking e usuários participantes
 *   admin-config        — lê/atualiza payout por conversão e saque mínimo
 *   admin-withdrawals   — lista solicitações de saque; action=admin-withdrawal-update
 *                         POST {id, status} para aprovar/rejeitar/marcar como pago
 *
 * Requer sessão padrão do sistema (mesmo padrão de app-versions.php).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/AsaasCommissionPayoutService.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, private, max-age=0');

const REF_VERSION = '20260820-64f4b';
const REFERRAL_TERMS_VERSION = '20260820-1';
const REFERRAL_PIX_KEY_TYPES = ['cpf', 'cnpj', 'email', 'telefone', 'aleatoria'];

function refError(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(1);
}

function refOk(array $data): void
{
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(0);
}

function refSessionUser(PDO $db): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }
    $candidates = [
        $_SESSION['user'] ?? null,
        $_SESSION['auth_user'] ?? null,
        $_SESSION['current_user'] ?? null,
        $_SESSION['user_data'] ?? null,
    ];
    foreach ($candidates as $candidate) {
        if (!is_array($candidate)) continue;
        $id = $candidate['id'] ?? $candidate['user_id'] ?? null;
        $profile = strtolower((string) ($candidate['profile'] ?? $candidate['role'] ?? $candidate['user_type'] ?? ''));
        if (($id === null || $id === '') && $profile !== 'super_admin') continue;
        return [
            'id' => $id === null || $id === '' ? 0 : (int) $id,
            'tenant_id' => $candidate['tenant_id'] ?? $candidate['tenantId'] ?? null,
            'profile' => $profile,
            'email' => (string) ($candidate['email'] ?? ''),
            'name' => (string) ($candidate['name'] ?? ''),
        ];
    }
    $sessionProfile = strtolower((string) ($_SESSION['profile'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? ''));
    if ($sessionProfile === 'super_admin' || !empty($_SESSION['is_super_admin'])) {
        return [
            'id' => 0,
            'tenant_id' => null,
            'profile' => 'super_admin',
            'email' => (string) ($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''),
            'name' => (string) ($_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Super administrador'),
        ];
    }
    $sessionId = $_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? null;
    $sessionEmail = $_SESSION['email'] ?? $_SESSION['user_email'] ?? null;
    if (($sessionId === null || $sessionId === '') && ($sessionEmail === null || $sessionEmail === '')) return null;
    try {
        if ($sessionId !== null && $sessionId !== '') {
            $stmt = $db->prepare('SELECT id, tenant_id, profile, email, name FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $sessionId]);
        } else {
            $stmt = $db->prepare('SELECT id, tenant_id, profile, email, name FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([(string) $sessionEmail]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        return [
            'id' => (int) ($row['id'] ?? 0),
            'tenant_id' => $row['tenant_id'] ?? null,
            'profile' => (string) ($row['profile'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
        ];
    } catch (Throwable $e) {
        error_log('referrals: ' . $e->getMessage());
        return null;
    }
}

function refSetting(PDO $db, string $key, string $default): string
{
    try {
        $stmt = $db->prepare("SELECT setting_value FROM referral_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null ? (string) $value : $default;
    } catch (Throwable $e) {
        error_log('referrals: ' . $e->getMessage());
        return $default;
    }
}

function refSettingFloat(PDO $db, string $key, float $default): float
{
    $value = refSetting($db, $key, '');
    if ($value === '') return $default;
    $float = str_replace(',', '.', $value);
    return is_numeric($float) ? (float) $float : $default;
}

function refVisitTableReady(PDO $db): bool
{
    try {
        return (bool) $db->query("SHOW TABLES LIKE 'referral_visits'")->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function refTermsAccepted(PDO $db, int $userId): bool
{
    if ($userId <= 0) return false;
    try {
        $stmt = $db->prepare('SELECT 1 FROM referral_term_acceptances WHERE user_id = ? AND terms_version = ? LIMIT 1');
        $stmt->execute([$userId, REFERRAL_TERMS_VERSION]);
        return (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function refPaymentProfileTableReady(PDO $db): bool
{
    try {
        return (bool) $db->query("SHOW TABLES LIKE 'referral_payment_profiles'")->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function refWithdrawalPaymentTableReady(PDO $db): bool
{
    try {
        return (bool) $db->query("SHOW TABLES LIKE 'referral_withdrawal_payment_details'")->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function refCommissionTransferTableReady(PDO $db): bool
{
    try {
        return (bool) $db->query("SHOW TABLES LIKE 'referral_withdrawal_transfers'")->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

/** Dados de recebimento pertencem apenas ao próprio participante e ao super admin. */
function refPaymentProfile(PDO $db, int $userId): array
{
    $empty = [
        'preferred_method' => 'pix',
        'pix_key_type' => '',
        'pix_key' => '',
        'configured' => false,
        'updated_at' => null,
    ];
    if ($userId <= 0 || !refPaymentProfileTableReady($db)) return $empty;
    try {
        $stmt = $db->prepare('SELECT preferred_method, pix_key_type, pix_key, updated_at FROM referral_payment_profiles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row) || empty($row['pix_key_type']) || empty($row['pix_key'])) return $empty;
        return [
            'preferred_method' => (string) ($row['preferred_method'] ?? 'pix'),
            'pix_key_type' => (string) $row['pix_key_type'],
            'pix_key' => (string) $row['pix_key'],
            'configured' => true,
            'updated_at' => $row['updated_at'] ? (string) $row['updated_at'] : null,
        ];
    } catch (Throwable $e) {
        error_log('referrals payment-profile: ' . $e->getMessage());
        return $empty;
    }
}

function refNormalizePixKey(string $type, string $value): string
{
    $value = trim($value);
    if (!in_array($type, REFERRAL_PIX_KEY_TYPES, true) || $value === '') {
        throw new InvalidArgumentException('Informe o tipo e a chave PIX.');
    }
    if ($type === 'cpf' || $type === 'cnpj') {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        $length = $type === 'cpf' ? 11 : 14;
        if (strlen($digits) !== $length) throw new InvalidArgumentException('A chave PIX informada não corresponde ao tipo selecionado.');
        return $digits;
    }
    if ($type === 'email') {
        $email = strtolower($value);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 160) {
            throw new InvalidArgumentException('Informe um e-mail válido como chave PIX.');
        }
        return $email;
    }
    if ($type === 'telefone') {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 10 || strlen($digits) === 11) $digits = '55' . $digits;
        if (strlen($digits) < 12 || strlen($digits) > 15) {
            throw new InvalidArgumentException('Informe um telefone com DDD válido como chave PIX.');
        }
        return '+' . $digits;
    }
    if (!preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/', $value)) {
        throw new InvalidArgumentException('A chave aleatória deve estar no formato UUID informado pela sua instituição financeira.');
    }
    return strtolower($value);
}

/**
 * Devolve (ou cria) o código de indicação do usuário.
 */
function refCode(PDO $db, int $userId): string
{
    try {
        $stmt = $db->prepare("SELECT referral_code FROM referrals WHERE user_id = ? ORDER BY id ASC LIMIT 1");
        $stmt->execute([$userId]);
        $code = $stmt->fetchColumn();
        if (is_string($code) && $code !== '') return $code;
        $code = 'ATD-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $db->prepare("INSERT INTO referrals (user_id, referral_code) VALUES (?, ?)");
        $stmt->execute([$userId, $code]);
        return $code;
    } catch (Throwable $e) {
        error_log('referrals: ' . $e->getMessage());
        return 'ATD-' . strtoupper(bin2hex(random_bytes(4)));
    }
}

try {
    $db = Database::getInstance()->getConnection();
    $action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
} catch (Throwable $e) {
    error_log('referrals: db connection: ' . $e->getMessage());
    $db = null;
    $action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
    if ($action === 'stats') {
        // Leitura tolerante: painel abre com estado pendente em vez de 401/500.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'prospec.atendo.log.br');
        refOk([
            'pending_install' => true,
            'hint' => 'Execute o arquivo sql/referrals.sql uma única vez no banco.',
            'referral_code' => '',
            'link' => $protocol . '://' . $host . '/?ref=PENDENTE',
            'clicks' => 0,
            'referrals' => 0,
            'converted' => 0,
            'earned' => 0.0,
            'payout_per_conversion' => 10.00,
            'minimum_withdrawal' => 100.00,
            'can_withdraw' => false,
            'withdrawals' => [],
        ]);
    }
    if ($action === 'click') refOk(['registered' => false, 'reason' => 'Módulo em instalação.']);
    refError(503, 'O módulo de indicações ainda não foi instalado. Execute o arquivo SQL do pacote.');
}

/* ----------------------------- registro com memória (50 dias) ------------- */
// Quando alguém clica em um link de indicação (?ref=ATD-XXXXXXXX), o código fica
// gravado no navegador (localStorage, 50 dias). Se essa pessoa se cadastrar
// dentro do período — mesmo voltando depois direto pelo endereço do sistema —
// o painel chama esta ação e a indicação é atribuída ao afiliado original.
if ($action === 'signup') {
    try {
        $auth = refSessionUser($db);
        if (!is_array($auth) || (int) ($auth['id'] ?? 0) <= 0) {
            refError(401, 'Sessão expirada. Faça login novamente.');
        }
        $input = json_decode((string) file_get_contents('php://input'), true);
        $code = is_array($input) && isset($input['referralCode']) ? trim((string) $input['referralCode']) : '';
        $visitToken = is_array($input) && isset($input['visitToken']) ? trim((string) $input['visitToken']) : '';
        if (!preg_match('/^ATD-[A-Z0-9]{8}$/', $code)) {
            refOk(['registered' => false, 'reason' => 'Nenhuma indicação pendente.']);
        }
        if (!preg_match('/^[a-f0-9]{32,64}$/i', $visitToken) || !refVisitTableReady($db)) {
            refOk(['registered' => false, 'reason' => 'A indicação ainda não foi registrada ou o módulo precisa ser atualizado.']);
        }
        $userId = (int) $auth['id'];
        // A atribuição é válida por 50 dias a partir do clique (memória do link).
        $stmt = $db->prepare(
            "UPDATE referral_visits SET signup_user_id = ?, signup_tenant_id = ?, attributed_at = NOW(), status = 'registered'
             WHERE referral_code = ? AND visit_token = ?
               AND referrer_user_id <> ?
               AND signup_user_id IS NULL
               AND clicked_at IS NOT NULL
               AND clicked_at >= (NOW() - INTERVAL 50 DAY)"
        );
        $stmt->execute([$userId, $auth['tenant_id'] === null ? null : (int) $auth['tenant_id'], $code, $visitToken, $userId]);
        $rows = $stmt->rowCount();
        refOk([
            'registered' => $rows > 0,
            'reason' => $rows > 0 ? 'Indicação atribuída ao afiliado.' : 'A indicação expirou ou já foi usada.',
        ]);
    } catch (Throwable $e) {
        error_log('referrals signup: ' . $e->getMessage());
        refOk(['registered' => false, 'reason' => 'Erro de gravação.']);
    }
}

/* ---------------------- aceite do termo de indicação ---------------------- */
if ($action === 'accept-terms') {
    $auth = refSessionUser($db);
    if (!is_array($auth) || (int) ($auth['id'] ?? 0) <= 0) {
        refError(401, 'Sessão expirada. Faça login novamente.');
    }
    $body = json_decode((string) file_get_contents('php://input'), true);
    $accepted = is_array($body) && !empty($body['accepted']);
    $version = is_array($body) ? (string) ($body['terms_version'] ?? '') : '';
    if (!$accepted || $version !== REFERRAL_TERMS_VERSION) {
        refError(422, 'Confirme a leitura e o aceite do Termo de Indicação vigente.');
    }
    try {
        $stmt = $db->prepare('INSERT INTO referral_term_acceptances (user_id, tenant_id, terms_version, accepted_at) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE accepted_at = accepted_at');
        $stmt->execute([(int) $auth['id'], $auth['tenant_id'] === null ? null : (int) $auth['tenant_id'], REFERRAL_TERMS_VERSION]);
        refOk(['accepted' => true, 'terms_version' => REFERRAL_TERMS_VERSION]);
    } catch (Throwable $e) {
        error_log('referrals accept-terms: ' . $e->getMessage());
        refError(500, 'Não foi possível registrar o aceite do termo.');
    }
}

/* ---------------------------- perfil de recebimento PIX ---------------------------- */
if ($action === 'payment-profile') {
    $auth = refSessionUser($db);
    if (!is_array($auth) || (int) ($auth['id'] ?? 0) <= 0) {
        refError(401, 'Sessão expirada. Faça login novamente.');
    }
    $userId = (int) $auth['id'];
    $method = strtolower((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method !== 'post') {
        refOk(['payment_profile' => refPaymentProfile($db, $userId)]);
    }
    if (!refPaymentProfileTableReady($db)) {
        refError(503, 'Atualize a estrutura SQL do programa de indicações antes de salvar os dados PIX.');
    }
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) refError(422, 'Requisição inválida.');
    try {
        $preferredMethod = strtolower(trim((string) ($body['preferred_method'] ?? 'pix')));
        $pixKeyType = strtolower(trim((string) ($body['pix_key_type'] ?? '')));
        if ($preferredMethod !== 'pix') throw new InvalidArgumentException('O recebimento de comissões é feito exclusivamente por PIX.');
        $pixKey = refNormalizePixKey($pixKeyType, (string) ($body['pix_key'] ?? ''));
        $stmt = $db->prepare(
            "INSERT INTO referral_payment_profiles (user_id, tenant_id, preferred_method, pix_key_type, pix_key, updated_at)
             VALUES (?, ?, 'pix', ?, ?, NOW())
             ON DUPLICATE KEY UPDATE tenant_id = VALUES(tenant_id), preferred_method = 'pix', pix_key_type = VALUES(pix_key_type), pix_key = VALUES(pix_key), updated_at = NOW()"
        );
        $stmt->execute([$userId, $auth['tenant_id'] === null ? null : (int) $auth['tenant_id'], $pixKeyType, $pixKey]);
        refOk(['payment_profile' => refPaymentProfile($db, $userId)]);
    } catch (InvalidArgumentException $e) {
        refError(422, $e->getMessage());
    } catch (Throwable $e) {
        error_log('referrals payment-profile save: ' . $e->getMessage());
        refError(500, 'Não foi possível salvar seus dados de recebimento.');
    }
}

/* ----------------------------- clique (pública) ----------------------------- */
if ($action === 'click') {
    try {
        $code = (string) ($_GET['code'] ?? '');
        if (!preg_match('/^ATD-[A-Z0-9]{8}$/', $code)) {
            refOk(['registered' => false, 'reason' => 'Código inválido.']);
        }
        $visitToken = (string) ($_GET['visitToken'] ?? '');
        if (!preg_match('/^[a-f0-9]{32,64}$/i', $visitToken) || !refVisitTableReady($db)) {
            refOk(['registered' => false, 'reason' => 'Atualize o módulo de indicações para registrar visitas individuais.']);
        }
        $owner = $db->prepare('SELECT r.user_id, COALESCE(r.tenant_id, u.tenant_id) AS tenant_id FROM referrals r INNER JOIN referral_term_acceptances ta ON ta.user_id = r.user_id AND ta.terms_version = ? LEFT JOIN users u ON u.id = r.user_id WHERE r.referral_code = ? AND r.user_id > 0 LIMIT 1');
        $owner->execute([REFERRAL_TERMS_VERSION, $code]);
        $ownerRow = $owner->fetch(PDO::FETCH_ASSOC);
        if (!is_array($ownerRow)) refOk(['registered' => false, 'reason' => 'Link de indicação não encontrado.']);
        $stmt = $db->prepare("INSERT INTO referral_visits (referrer_user_id, referrer_tenant_id, referral_code, visit_token, clicked_at)
            VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE clicked_at = clicked_at");
        $stmt->execute([(int) $ownerRow['user_id'], $ownerRow['tenant_id'] === null ? null : (int) $ownerRow['tenant_id'], $code, strtolower($visitToken)]);
        refOk(['registered' => true]);
    } catch (Throwable $e) {
        error_log('referrals click: ' . $e->getMessage());
        refOk(['registered' => false, 'reason' => 'Erro de gravação.']);
    }
}

if ($action === 'stats') {
    $auth = refSessionUser($db);
    if (!is_array($auth)) {
        refError(401, 'Sessão expirada. Faça login novamente.');
    }
    // O super administrador não possui registro de usuário (id 0): a aba abre
    // com estado vazio em vez de erro, para nunca quebrar o painel.
    $isSuperAdmin = strtolower((string) ($auth['profile'] ?? '')) === 'super_admin';
    if ((int) ($auth['id'] ?? 0) <= 0 && !$isSuperAdmin) {
        refError(401, 'Sessão expirada. Faça login novamente.');
    }
    if ((int) ($auth['id'] ?? 0) <= 0 && $isSuperAdmin) {
        refOk([
            'is_platform' => true,
            'referral_code' => '',
            'link' => '',
            'clicks' => 0,
            'referrals' => 0,
            'converted' => 0,
            'earned' => 0.0,
            'payout_per_conversion' => refSettingFloat($db, 'payout_per_conversion', 10.00),
            'minimum_withdrawal' => refSettingFloat($db, 'minimum_withdrawal', 100.00),
            'can_withdraw' => false,
            'withdrawals' => [],
            'payment_profile' => refPaymentProfile($db, 0),
            'terms_accepted' => true,
            'terms_version' => REFERRAL_TERMS_VERSION,
        ]);
    }
    try {
        $userId = (int) $auth['id'];
        $termsAccepted = refTermsAccepted($db, $userId);
        $code = refCode($db, $userId);
        $payout = refSettingFloat($db, 'payout_per_conversion', 10.00);
        $minimum = refSettingFloat($db, 'minimum_withdrawal', 100.00);
        $paymentProfile = refPaymentProfile($db, $userId);

        if (refVisitTableReady($db)) {
            $rows = $db->prepare("SELECT COUNT(*) AS clicks,
                COALESCE(SUM(CASE WHEN signup_user_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS referrals,
                COALESCE(SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END), 0) AS converted
                FROM referral_visits WHERE referrer_user_id = ?");
            $rows->execute([$userId]);
            $totals = $rows->fetch(PDO::FETCH_ASSOC) ?: ['referrals' => 0, 'converted' => 0, 'earned' => 0, 'clicks' => 0];
            $totals['earned'] = 0.0;
        } else {
            $rows = $db->prepare("SELECT COUNT(*) AS referrals,
                SUM(CASE WHEN status = 'converted' OR status = 'withdrawn' THEN 1 ELSE 0 END) AS converted,
                SUM(CASE WHEN payout_amount > 0 THEN payout_amount ELSE 0 END) AS earned,
                SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicks
                FROM referrals WHERE user_id = ?");
            $rows->execute([$userId]);
            $totals = $rows->fetch(PDO::FETCH_ASSOC) ?: ['referrals' => 0, 'converted' => 0, 'earned' => 0, 'clicks' => 0];
        }
        // Saldo a receber via módulo de pagamentos (Asaas): comissões pendentes/pagas.
        try {
            $com = $db->prepare(
                "SELECT COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending,
                        COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid
                 FROM referral_commissions WHERE referrer_user_id = ?"
            );
            $com->execute([$userId]);
            $comTotals = $com->fetch(PDO::FETCH_ASSOC) ?: ['pending' => 0, 'paid' => 0];
            $commissionTotal = (float) ($comTotals['pending'] ?? 0) + (float) ($comTotals['paid'] ?? 0);
            $availableCommissions = $commissionTotal;
            if (refCommissionTransferTableReady($db)) {
                $availableStmt = $db->prepare("SELECT COALESCE(SUM(rc.amount), 0)
                    FROM referral_commissions rc
                    LEFT JOIN referral_withdrawal_commissions wc ON wc.commission_id = rc.id
                    WHERE rc.referrer_user_id = ? AND wc.commission_id IS NULL AND rc.status IN ('pending', 'paid')");
                $availableStmt->execute([$userId]);
                $availableCommissions = (float) $availableStmt->fetchColumn();
            }
            $totals['earned_total'] = ((float) ($totals['earned'] ?? 0)) + $commissionTotal;
            $totals['earned'] = $availableCommissions;
        } catch (Throwable $ignored) {
            // referral_commissions ausente: mantém apenas o valor histórico da tabela referrals.
        }

        // Saques existentes do usuário, com o status externo quando a tabela já estiver instalada.
        $transferReady = refCommissionTransferTableReady($db);
        $transferSelect = $transferReady ? ', wt.asaas_transfer_id, wt.status AS transfer_status, wt.transaction_receipt_url' : ', NULL AS asaas_transfer_id, NULL AS transfer_status, NULL AS transaction_receipt_url';
        $transferJoin = $transferReady ? ' LEFT JOIN referral_withdrawal_transfers wt ON wt.withdrawal_id = w.id' : '';
        $ws = $db->prepare("SELECT w.id, w.amount, w.status, w.created_at{$transferSelect} FROM referral_withdrawals w{$transferJoin} WHERE w.user_id = ? ORDER BY w.id DESC LIMIT 10");
        $ws->execute([$userId]);
        $withdrawals = [];
        foreach ($ws->fetchAll(PDO::FETCH_ASSOC) as $w) {
            $withdrawals[] = [
                'id' => (int) $w['id'],
                'amount' => (float) $w['amount'],
                'status' => (string) $w['status'],
                'created_at' => (string) $w['created_at'],
                'transfer_id' => (string) ($w['asaas_transfer_id'] ?? ''),
                'transfer_status' => $w['transfer_status'] !== null ? (string) $w['transfer_status'] : null,
                'receipt_url' => $w['transaction_receipt_url'] ? (string) $w['transaction_receipt_url'] : null,
            ];
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? 'prospec.atendo.log.br');
        $link = $termsAccepted ? $protocol . '://' . $host . '/?ref=' . urlencode($code) : '';
        // Indicados com status individual (clique, registro e conversão) para o
        // painel do usuário acompanhar se as indicações estão sendo contadas.
        $referralsList = [];
        try {
            $list = refVisitTableReady($db)
                ? $db->prepare("SELECT referral_code, clicked_at, signup_user_id, status, 0.00 AS payout_amount, clicked_at AS created_at FROM referral_visits WHERE referrer_user_id = ? ORDER BY id DESC LIMIT 100")
                : $db->prepare("SELECT referral_code, clicked_at, signup_user_id, status, payout_amount, created_at FROM referrals WHERE user_id = ? ORDER BY id DESC LIMIT 100");
            $list->execute([$userId]);
            foreach ($list->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $referralsList[] = [
                    'code' => (string) $row['referral_code'],
                    'clicked_at' => $row['clicked_at'] ? (string) $row['clicked_at'] : null,
                    'registered' => !empty($row['signup_user_id']),
                    'status' => (string) ($row['status'] ?? 'pending'),
                    'payout_amount' => (float) ($row['payout_amount'] ?? 0),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                ];
            }
        } catch (Throwable $ignored) {
            // tabela referrals ausente ou coluna nova: lista vazia, totais seguem válidos.
        }
        refOk([
            'referral_code' => $code,
            'link' => $link,
            'clicks' => (int) ($totals['clicks'] ?? 0),
            'referrals' => (int) ($totals['referrals'] ?? 0),
            'converted' => (int) ($totals['converted'] ?? 0),
            'earned' => (float) ($totals['earned'] ?? 0),
            'payout_per_conversion' => $payout,
            'minimum_withdrawal' => $minimum,
            'can_withdraw' => ((float) ($totals['earned'] ?? 0)) >= $minimum,
            'withdrawals' => $withdrawals,
            'referrals_list' => $referralsList,
            'terms_accepted' => $termsAccepted,
            'terms_version' => REFERRAL_TERMS_VERSION,
            'payment_profile' => $paymentProfile,
        ]);
    } catch (Throwable $e) {
        error_log('referrals stats: ' . $e->getMessage());
        refError(500, 'Não foi possível carregar as suas indicações.');
    }
}

if ($action === 'withdraw-request') {
    $auth = refSessionUser($db);
    if (!is_array($auth) || (int) ($auth['id'] ?? 0) <= 0) {
        refError(401, 'Sessão expirada. Faça login novamente.');
    }
    try {
        $userId = (int) $auth['id'];
        $minimum = refSettingFloat($db, 'minimum_withdrawal', 100.00);
        $paymentProfile = refPaymentProfile($db, $userId);
        if (!$paymentProfile['configured'] || !refWithdrawalPaymentTableReady($db)) {
            refError(422, 'Informe e salve sua chave PIX antes de solicitar um saque.');
        }
        if (!refCommissionTransferTableReady($db)) {
            refError(503, 'O módulo de conciliação de comissões ainda não foi instalado.');
        }

        $db->beginTransaction();
        // Bloqueia as comissões elegíveis durante a montagem do saque. A reserva
        // é feita por uma tabela própria; nunca se marca comissão como paga aqui.
        $rows = $db->prepare(
            "SELECT rc.id, rc.amount
               FROM referral_commissions rc
              WHERE rc.referrer_user_id = ?
                AND rc.status IN ('pending', 'paid')
                AND NOT EXISTS (
                    SELECT 1 FROM referral_withdrawal_commissions wc WHERE wc.commission_id = rc.id
                )
              ORDER BY rc.id ASC
              FOR UPDATE"
        );
        $rows->execute([$userId]);
        $eligible = $rows->fetchAll(PDO::FETCH_ASSOC);
        $reserved = 0.0;
        foreach ($eligible as $row) $reserved += (float) ($row['amount'] ?? 0);
        if ($reserved < $minimum) {
            $db->rollBack();
            refError(422, 'O saldo disponível é de R$ ' . number_format($reserved, 2, ',', '.') . '. O saque mínimo é de R$ ' . number_format($minimum, 2, ',', '.') . '.');
        }

        $stmt = $db->prepare('INSERT INTO referral_withdrawals (user_id, tenant_id, amount) VALUES (?, ?, ?)');
        $stmt->execute([$userId, $auth['tenant_id'], number_format($reserved, 2, '.', '')]);
        $withdrawalId = (int) $db->lastInsertId();
        $snapshot = $db->prepare("INSERT INTO referral_withdrawal_payment_details (withdrawal_id, user_id, preferred_method, pix_key_type, pix_key, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())");
        $snapshot->execute([$withdrawalId, $userId, $paymentProfile['preferred_method'], $paymentProfile['pix_key_type'], $paymentProfile['pix_key']]);
        $allocation = $db->prepare('INSERT INTO referral_withdrawal_commissions (withdrawal_id, commission_id, amount_cents) VALUES (?, ?, ?)');
        foreach ($eligible as $row) {
            $allocation->execute([$withdrawalId, (int) $row['id'], (int) round(((float) $row['amount']) * 100)]);
        }
        $db->commit();
        refOk(['requested' => true, 'withdrawal_id' => $withdrawalId, 'amount' => $reserved, 'payment_method' => 'pix', 'status' => 'pending']);
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        if (strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false) {
            refError(409, 'Uma das comissões já está reservada em outro saque. Atualize o painel e tente novamente.');
        }
        error_log('referrals withdraw: ' . $e->getMessage());
        refError(500, 'Não foi possível solicitar o saque.');
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('referrals withdraw: ' . $e->getMessage());
        refError(500, 'Não foi possível solicitar o saque.');
    }
}

/* -------------------------------- super admin -------------------------------- */
if ($action === 'admin-dashboard') {
    $auth = refSessionUser($db);
    if (!is_array($auth) || strtolower((string) ($auth['profile'] ?? '')) !== 'super_admin') {
        refError(403, 'Somente o super administrador pode visualizar o painel de indicações.');
    }
    try {
        $visitsReady = refVisitTableReady($db);
        $paymentProfileReady = refPaymentProfileTableReady($db);
        $activeLinks = $db->prepare(
            "SELECT COUNT(DISTINCT r.user_id)
             FROM referrals r
             INNER JOIN referral_term_acceptances ta
                ON ta.user_id = r.user_id AND ta.terms_version = ?"
        );
        $activeLinks->execute([REFERRAL_TERMS_VERSION]);
        $activeLinksCount = (int) $activeLinks->fetchColumn();

        if ($visitsReady) {
            $visitTotalsStmt = $db->query(
                "SELECT COUNT(*) AS clicks,
                        COALESCE(SUM(CASE WHEN signup_user_id IS NOT NULL THEN 1 ELSE 0 END), 0) AS registered,
                        COALESCE(SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END), 0) AS converted
                 FROM referral_visits"
            );
            $visitTotals = $visitTotalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } else {
            $visitTotalsStmt = $db->query(
                "SELECT COALESCE(SUM(CASE WHEN clicked_at IS NOT NULL THEN 1 ELSE 0 END), 0) AS clicks,
                        COUNT(*) AS registered,
                        COALESCE(SUM(CASE WHEN status IN ('converted', 'withdrawn') THEN 1 ELSE 0 END), 0) AS converted
                 FROM referrals"
            );
            $visitTotals = $visitTotalsStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $commissionStmt = $db->query(
            "SELECT COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) AS pending_amount,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid_amount,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END), 0) AS pending_count,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count
             FROM referral_commissions"
        );
        $commissionTotals = $commissionStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $withdrawalStmt = $db->query(
            "SELECT COALESCE(SUM(CASE WHEN status IN ('pending', 'approved') THEN amount ELSE 0 END), 0) AS awaiting_amount,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END), 0) AS paid_amount,
                    COALESCE(SUM(CASE WHEN status IN ('pending', 'approved') THEN 1 ELSE 0 END), 0) AS awaiting_count,
                    COALESCE(SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END), 0) AS paid_count
             FROM referral_withdrawals"
        );
        $withdrawalTotals = $withdrawalStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $visitJoin = $visitsReady
            ? "LEFT JOIN referral_visits v ON v.referrer_user_id = r.user_id"
            : "LEFT JOIN referrals v ON v.user_id = r.user_id";
        $clicksSql = $visitsReady
            ? "COUNT(v.id)"
            : "COALESCE(SUM(CASE WHEN v.clicked_at IS NOT NULL THEN 1 ELSE 0 END), 0)";
        $registeredSql = $visitsReady
            ? "COALESCE(SUM(CASE WHEN v.signup_user_id IS NOT NULL THEN 1 ELSE 0 END), 0)"
            : "COUNT(v.id)";
        $convertedSql = $visitsReady
            ? "COALESCE(SUM(CASE WHEN v.status = 'converted' THEN 1 ELSE 0 END), 0)"
            : "COALESCE(SUM(CASE WHEN v.status IN ('converted', 'withdrawn') THEN 1 ELSE 0 END), 0)";
        $paymentSelect = $paymentProfileReady
            ? "COALESCE(pp.preferred_method, 'pix') AS preferred_method, COALESCE(pp.pix_key_type, '') AS pix_key_type, COALESCE(pp.pix_key, '') AS pix_key, pp.updated_at AS payment_updated_at,"
            : "'pix' AS preferred_method, '' AS pix_key_type, '' AS pix_key, NULL AS payment_updated_at,";
        $paymentJoin = $paymentProfileReady
            ? 'LEFT JOIN referral_payment_profiles pp ON pp.user_id = r.user_id'
            : '';
        $paymentGroup = $paymentProfileReady
            ? ', pp.preferred_method, pp.pix_key_type, pp.pix_key, pp.updated_at'
            : '';
        $participantSql =
            "SELECT r.user_id, r.referral_code,
                    COALESCE(u.name, u.email, CONCAT('Usuário ', r.user_id)) AS name,
                    COALESCE(u.email, '') AS email,
                    COALESCE(t.name, 'Sem empresa') AS tenant_name,
                    {$clicksSql} AS clicks,
                    {$registeredSql} AS registered,
                    {$convertedSql} AS converted,
                    {$paymentSelect}
                    COALESCE((SELECT SUM(rc.amount) FROM referral_commissions rc WHERE rc.referrer_user_id = r.user_id AND rc.status = 'pending'), 0) AS commission_pending,
                    COALESCE((SELECT SUM(rc.amount) FROM referral_commissions rc WHERE rc.referrer_user_id = r.user_id AND rc.status = 'paid'), 0) AS commission_paid
             FROM referrals r
             INNER JOIN referral_term_acceptances ta
                ON ta.user_id = r.user_id AND ta.terms_version = ?
             LEFT JOIN users u ON u.id = r.user_id
             LEFT JOIN tenants t ON t.id = COALESCE(r.tenant_id, u.tenant_id)
             {$paymentJoin}
             {$visitJoin}
             GROUP BY r.user_id, r.referral_code, u.name, u.email, t.name{$paymentGroup}
             ORDER BY converted DESC, registered DESC, clicks DESC, r.user_id ASC";
        $participantsStmt = $db->prepare($participantSql . ' LIMIT 100');
        $participantsStmt->execute([REFERRAL_TERMS_VERSION]);
        $participants = [];
        foreach ($participantsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $participants[] = [
                'user_id' => (int) ($row['user_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''),
                'tenant_name' => (string) ($row['tenant_name'] ?? 'Sem empresa'),
                'referral_code' => (string) ($row['referral_code'] ?? ''),
                'clicks' => (int) ($row['clicks'] ?? 0),
                'registered' => (int) ($row['registered'] ?? 0),
                'converted' => (int) ($row['converted'] ?? 0),
                'preferred_method' => (string) ($row['preferred_method'] ?? 'pix'),
                    'pix_key_type' => (string) ($row['pix_key_type'] ?? ''),
                    'pix_configured' => (string) ($row['pix_key'] ?? '') !== '',
                    'payment_updated_at' => $row['payment_updated_at'] ? (string) $row['payment_updated_at'] : null,
                'commission_pending' => (float) ($row['commission_pending'] ?? 0),
                'commission_paid' => (float) ($row['commission_paid'] ?? 0),
            ];
        }
        refOk([
            'overview' => [
                'active_links' => $activeLinksCount,
                'participating_users' => $activeLinksCount,
                'clicks' => (int) ($visitTotals['clicks'] ?? 0),
                'registered' => (int) ($visitTotals['registered'] ?? 0),
                'converted' => (int) ($visitTotals['converted'] ?? 0),
                'commission_pending' => (float) ($commissionTotals['pending_amount'] ?? 0),
                'commission_paid' => (float) ($commissionTotals['paid_amount'] ?? 0),
                'withdrawals_awaiting' => (float) ($withdrawalTotals['awaiting_amount'] ?? 0),
                'withdrawals_paid' => (float) ($withdrawalTotals['paid_amount'] ?? 0),
                'commission_pending_count' => (int) ($commissionTotals['pending_count'] ?? 0),
                'commission_paid_count' => (int) ($commissionTotals['paid_count'] ?? 0),
                'withdrawals_awaiting_count' => (int) ($withdrawalTotals['awaiting_count'] ?? 0),
                'withdrawals_paid_count' => (int) ($withdrawalTotals['paid_count'] ?? 0),
            ],
            'top_indicators' => array_slice($participants, 0, 5),
            'participants' => $participants,
            'terms_version' => REFERRAL_TERMS_VERSION,
        ]);
    } catch (Throwable $e) {
        error_log('referrals admin-dashboard: ' . $e->getMessage());
        refError(500, 'Não foi possível carregar o painel administrativo de indicações.');
    }
}

if ($action === 'admin-config') {
    $auth = refSessionUser($db);
    if (!is_array($auth) || strtolower((string) ($auth['profile'] ?? '')) !== 'super_admin') {
        refError(403, 'Somente o super administrador pode gerenciar as indicações.');
    }
    $method = strtolower((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'post') {
        $body = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($body)) refError(422, 'Requisição inválida.');
        try {
            $payout = isset($body['payout_per_conversion']) ? (float) str_replace(',', '.', (string) $body['payout_per_conversion']) : null;
            $minimum = isset($body['minimum_withdrawal']) ? (float) str_replace(',', '.', (string) $body['minimum_withdrawal']) : null;
            if ($payout !== null && $payout >= 0 && $payout <= 1000) {
                $db->prepare("INSERT INTO referral_settings (setting_key, setting_value) VALUES ('payout_per_conversion', ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([number_format($payout, 2, '.', '')]);
            }
            if ($minimum !== null && $minimum >= 1 && $minimum <= 100000) {
                $db->prepare("INSERT INTO referral_settings (setting_key, setting_value) VALUES ('minimum_withdrawal', ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")->execute([number_format($minimum, 2, '.', '')]);
            }
            refOk([
                'payout_per_conversion' => (float) refSetting($db, 'payout_per_conversion', '10.00'),
                'minimum_withdrawal' => (float) refSetting($db, 'minimum_withdrawal', '100.00'),
            ]);
        } catch (Throwable $e) {
            error_log('referrals admin-config: ' . $e->getMessage());
            refError(500, 'Não foi possível salvar as configurações.');
        }
    }
    refOk([
        'payout_per_conversion' => (float) refSetting($db, 'payout_per_conversion', '10.00'),
        'minimum_withdrawal' => (float) refSetting($db, 'minimum_withdrawal', '100.00'),
    ]);
}

if ($action === 'admin-withdrawals') {
    $auth = refSessionUser($db);
    if (!is_array($auth) || strtolower((string) ($auth['profile'] ?? '')) !== 'super_admin') {
        refError(403, 'Somente o super administrador pode gerenciar as indicações.');
    }
    try {
        $paymentDetailsReady = refWithdrawalPaymentTableReady($db);
        $paymentProfileReady = refPaymentProfileTableReady($db);
        $transferReady = refCommissionTransferTableReady($db);
        $paymentSelect = $paymentDetailsReady
            ? "COALESCE(wpd.preferred_method, 'pix') AS preferred_method, COALESCE(wpd.pix_key_type, '') AS pix_key_type, (COALESCE(wpd.pix_key, '') <> '') AS pix_configured"
            : ($paymentProfileReady
                ? "COALESCE(pp.preferred_method, 'pix') AS preferred_method, COALESCE(pp.pix_key_type, '') AS pix_key_type, (COALESCE(pp.pix_key, '') <> '') AS pix_configured"
                : "'pix' AS preferred_method, '' AS pix_key_type, 0 AS pix_configured");
        $paymentJoin = $paymentDetailsReady ? 'LEFT JOIN referral_withdrawal_payment_details wpd ON wpd.withdrawal_id = w.id' : '';
        $profileJoin = $paymentProfileReady ? 'LEFT JOIN referral_payment_profiles pp ON pp.user_id = w.user_id' : '';
        $transferSelect = $transferReady
            ? ", wt.asaas_transfer_id, wt.status AS transfer_status, wt.transfer_fee_cents, wt.net_amount_cents, wt.transaction_receipt_url, wt.fail_reason"
            : ", NULL AS asaas_transfer_id, NULL AS transfer_status, NULL AS transfer_fee_cents, NULL AS net_amount_cents, NULL AS transaction_receipt_url, NULL AS fail_reason";
        $transferJoin = $transferReady ? 'LEFT JOIN referral_withdrawal_transfers wt ON wt.withdrawal_id = w.id' : '';
        $rows = $db->query("SELECT w.id, w.user_id, w.amount, w.status, w.created_at, u.name, u.email, {$paymentSelect}{$transferSelect}
            FROM referral_withdrawals w
            LEFT JOIN users u ON u.id = w.user_id
            {$paymentJoin}
            {$profileJoin}
            {$transferJoin}
            ORDER BY w.id DESC LIMIT 200");
        $list = [];
        foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $w) {
            $list[] = [
                'id' => (int) $w['id'],
                'user_id' => (int) ($w['user_id'] ?? 0),
                'name' => (string) ($w['name'] ?? ''),
                'email' => (string) ($w['email'] ?? ''),
                'amount' => (float) $w['amount'],
                'status' => (string) $w['status'],
                'created_at' => (string) $w['created_at'],
                'preferred_method' => (string) ($w['preferred_method'] ?? 'pix'),
                'pix_key_type' => (string) ($w['pix_key_type'] ?? ''),
                'pix_configured' => !empty($w['pix_configured']),
                'transfer_id' => (string) ($w['asaas_transfer_id'] ?? ''),
                'transfer_status' => $w['transfer_status'] !== null ? (string) $w['transfer_status'] : null,
                'transfer_fee' => $w['transfer_fee_cents'] !== null ? ((int) $w['transfer_fee_cents']) / 100 : null,
                'net_amount' => $w['net_amount_cents'] !== null ? ((int) $w['net_amount_cents']) / 100 : null,
                'receipt_url' => $w['transaction_receipt_url'] ? (string) $w['transaction_receipt_url'] : null,
                'fail_reason' => $w['fail_reason'] ? (string) $w['fail_reason'] : null,
            ];
        }
        refOk(['withdrawals' => $list]);
    } catch (Throwable $e) {
        error_log('referrals admin-withdrawals: ' . $e->getMessage());
        refError(500, 'Não foi possível carregar as solicitações de saque.');
    }
}

if ($action === 'admin-withdrawal-update') {
    $auth = refSessionUser($db);
    if (!is_array($auth) || strtolower((string) ($auth['profile'] ?? '')) !== 'super_admin') {
        refError(403, 'Somente o super administrador pode gerenciar as indicações.');
    }
    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) refError(422, 'Requisição inválida.');
    $id = (int) ($body['id'] ?? 0);
    $status = strtolower(trim((string) ($body['status'] ?? '')));
    if ($id <= 0 || !in_array($status, ['approved', 'rejected'], true)) {
        refError(422, 'Status inválido. O pagamento somente é confirmado pelo retorno DONE do Asaas.');
    }
    try {
        $stmt = $db->prepare("UPDATE referral_withdrawals SET status = ? WHERE id = ? AND status = 'pending' LIMIT 1");
        $stmt->execute([$status, $id]);
        if ($stmt->rowCount() === 0) refError(409, 'A solicitação não está no estado esperado para esta ação.');
        refOk(['updated' => true, 'status' => $status]);
    } catch (Throwable $e) {
        error_log('referrals admin-withdrawal-update: ' . $e->getMessage());
        refError(500, 'Não foi possível atualizar a solicitação.');
    }
}

if ($action === 'admin-withdrawal-process') {
    $auth = refSessionUser($db);
    if (!is_array($auth) || strtolower((string) ($auth['profile'] ?? '')) !== 'super_admin') {
        refError(403, 'Somente o super administrador pode processar saques.');
    }
    $body = json_decode((string) file_get_contents('php://input'), true);
    $id = is_array($body) ? (int) ($body['id'] ?? 0) : 0;
    if ($id <= 0) refError(422, 'Informe a solicitação de saque.');
    try {
        if (!refCommissionTransferTableReady($db)) {
            refError(503, 'A estrutura de transferências Asaas ainda não foi instalada.');
        }
        $result = MapsProspectorAsaasCommissionPayoutService::process($db, $id);
        refOk(['updated' => true, 'transfer' => $result]);
    } catch (Throwable $e) {
        error_log('referrals admin-withdrawal-process: ' . $e->getMessage());
        refError(409, 'Não foi possível iniciar a transferência. Verifique se o Asaas está ativo em sandbox e se o saque foi aprovado.');
    }
}

if ($action === 'admin-withdrawal-sync') {
    $auth = refSessionUser($db);
    if (!is_array($auth) || strtolower((string) ($auth['profile'] ?? '')) !== 'super_admin') {
        refError(403, 'Somente o super administrador pode sincronizar saques.');
    }
    $body = json_decode((string) file_get_contents('php://input'), true);
    $id = is_array($body) ? (int) ($body['id'] ?? 0) : 0;
    if ($id <= 0) refError(422, 'Informe a solicitação de saque.');
    try {
        $result = MapsProspectorAsaasCommissionPayoutService::sync($db, $id);
        refOk(['updated' => true, 'transfer' => $result]);
    } catch (Throwable $e) {
        error_log('referrals admin-withdrawal-sync: ' . $e->getMessage());
        refError(409, 'Não foi possível sincronizar a transferência com o Asaas.');
    }
}

refError(422, 'Ação não reconhecida.');
