<?php
declare(strict_types=1);

ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/module-access.php';
require_once __DIR__ . '/../services/CrmAddonService.php';
ob_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function crmAddonJson(bool $success, mixed $data = null, string $message = '', int $status = 200): never
{
    http_response_code($status);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $success ? $message : '',
        'error' => $success ? '' : $message,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

try {
    $userId = (int) requireAuth();
    $user = getAuthUser() ?: [];
    $db = Database::getInstance()->getConnection();
    $profile = strtolower((string) ($user['profile'] ?? ''));
    $tenantId = (int) ($user['tenant_id'] ?? 0);
    if ($tenantId <= 0 && $profile === 'super_admin') $tenantId = 1;
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) $input = [];

    if ($method === 'GET') {
        $action = strtolower(trim((string) ($_GET['action'] ?? 'status')));
        if ($action === 'requests') {
            if ($profile !== 'super_admin') crmAddonJson(false, null, 'Apenas o administrador da plataforma pode listar solicitações de CRM.', 403);
            $stmt = $db->query("SELECT r.id, r.tenant_id, r.requested_by_user_id, r.plan_id, r.status, r.included_users, r.additional_user_price_cents, r.created_at, r.reviewed_at, t.name AS tenant_name, u.name AS requested_by_name, u.email AS requested_by_email, p.name AS plan_name, p.price_monthly AS plan_price FROM crm_addon_requests r INNER JOIN tenants t ON t.id = r.tenant_id INNER JOIN users u ON u.id = r.requested_by_user_id INNER JOIN plans p ON p.id = r.plan_id ORDER BY r.status = 'pending' DESC, r.created_at DESC");
            $items = array_map(static function (array $row): array {
                return [
                    'id' => (string) $row['id'],
                    'tenantId' => (string) $row['tenant_id'],
                    'requestedByUserId' => (string) $row['requested_by_user_id'],
                    'tenantName' => (string) ($row['tenant_name'] ?? ''),
                    'requestedByName' => (string) ($row['requested_by_name'] ?? ''),
                    'requestedByEmail' => (string) ($row['requested_by_email'] ?? ''),
                    'planId' => (string) $row['plan_id'],
                    'planName' => (string) ($row['plan_name'] ?? 'CRM adicional'),
                    'planPrice' => (float) ($row['plan_price'] ?? 0),
                    'includedUsers' => max(1, (int) ($row['included_users'] ?? 3)),
                    'additionalUserPriceCents' => max(0, (int) ($row['additional_user_price_cents'] ?? 1990)),
                    'status' => (string) $row['status'],
                    'createdAt' => (string) $row['created_at'],
                    'reviewedAt' => $row['reviewed_at'] !== null ? (string) $row['reviewed_at'] : null,
                ];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
            crmAddonJson(true, ['items' => $items, 'total' => count($items)]);
        }
        if ($tenantId <= 0) crmAddonJson(true, ['hasAccess' => false, 'license' => null, 'offers' => crmAddonAvailablePlans($db)]);
        $license = crmAddonActiveLicense($db, $tenantId);
        crmAddonJson(true, [
            'hasAccess' => $license !== null || mpTenantHasModule($db, $tenantId, 'crm', $user),
            'license' => $license ? crmAddonLicensePayload($db, $tenantId, $license) : null,
            'offers' => crmAddonAvailablePlans($db),
            'product' => 'crm_addon',
            'preservesMainPlan' => true,
        ]);
    }

    if ($method === 'POST') {
        if ($profile !== 'admin' && $profile !== 'super_admin') crmAddonJson(false, null, 'A contratação do CRM adicional deve ser solicitada pelo administrador da empresa.', 403);
        if ($tenantId <= 0) crmAddonJson(false, null, 'Empresa não encontrada para esta conta.', 400);
        $planId = max(0, (int) ($input['planId'] ?? $input['plan_id'] ?? 0));
        if ($planId <= 0) crmAddonJson(false, null, 'Selecione uma oferta de CRM adicional.', 422);
        $plan = crmAddonFindPlan($db, $planId, true);
        if (!$plan) crmAddonJson(false, null, 'A oferta de CRM selecionada não está ativa para contratação.', 400);
        $activeLicense = crmAddonActiveLicense($db, $tenantId);
        if ($activeLicense) crmAddonJson(false, null, 'Sua empresa já possui uma licença CRM ativa.', 409);
        if (!class_exists('MapsProspectorAsaasPaymentGateway')) require_once __DIR__ . '/../services/AsaasPaymentGateway.php';
        if (MapsProspectorAsaasPaymentGateway::isOperational($db)) {
            try {
                $result = MapsProspectorAsaasPaymentGateway::createCrmAddon($db, $user, $tenantId, $planId);
                crmAddonJson(true, $result, 'Assinatura do CRM criada no Asaas. Abra o link para concluir o primeiro pagamento.');
            } catch (Throwable $e) {
                error_log('crm addon automatic payment creation: ' . $e->getMessage());
                crmAddonJson(false, null, 'O Asaas está configurado, mas não foi possível criar a assinatura do CRM agora. Nenhuma solicitação manual foi aberta automaticamente.', 502);
            }
        }
        try {
            $result = crmAddonCreateManualRequest($db, $user, $tenantId, $planId);
            crmAddonJson(true, $result, !empty($result['alreadyPending']) ? 'Já existe uma solicitação manual pendente para esta licença CRM.' : 'Solicitação de CRM adicional enviada para aprovação manual.');
        } catch (InvalidArgumentException $e) {
            crmAddonJson(false, null, $e->getMessage(), 400);
        } catch (Throwable $e) {
            error_log('crm addon manual request: ' . $e->getMessage());
            crmAddonJson(false, null, 'Não foi possível registrar a solicitação do CRM adicional agora.', 500);
        }
    }

    if ($method === 'PUT') {
        if ($profile !== 'super_admin') crmAddonJson(false, null, 'Apenas o administrador da plataforma pode aprovar solicitações de CRM.', 403);
        $id = max(0, (int) ($input['id'] ?? 0));
        $status = strtolower(trim((string) ($input['status'] ?? '')));
        if ($id <= 0 || !in_array($status, ['approved', 'rejected'], true)) crmAddonJson(false, null, 'Informe id e status approved ou rejected.', 400);
        $stmt = $db->prepare('SELECT * FROM crm_addon_requests WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $request = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($request)) crmAddonJson(false, null, 'Solicitação de CRM não encontrada.', 404);
        if ((string) $request['status'] !== 'pending') crmAddonJson(false, null, 'Esta solicitação de CRM já foi avaliada.', 400);
        $db->beginTransaction();
        try {
            $update = $db->prepare('UPDATE crm_addon_requests SET status = ?, reviewed_at = NOW(), reviewed_by_user_id = ? WHERE id = ? AND status = \'pending\'');
            $update->execute([$status, $userId, $id]);
            if ($status === 'approved') {
                crmAddonActivateLicense($db, (int) $request['tenant_id'], (int) $request['plan_id'], 'manual');
                $db->prepare("UPDATE payments SET status = 'paid', paid_amount = COALESCE(amount_cents, 0), updated_at = NOW() WHERE type = 'crm_addon' AND tenant_id = ? AND plan_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1")->execute([(int) $request['tenant_id'], (int) $request['plan_id']]);
            } else {
                $db->prepare("UPDATE payments SET status = 'rejected', updated_at = NOW() WHERE type = 'crm_addon' AND tenant_id = ? AND plan_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1")->execute([(int) $request['tenant_id'], (int) $request['plan_id']]);
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        crmAddonJson(true, ['id' => (string) $id, 'status' => $status], $status === 'approved' ? 'CRM adicional ativado sem alterar o plano principal.' : 'Solicitação de CRM adicional recusada.');
    }

    crmAddonJson(false, null, 'Método não permitido.', 405);
} catch (Throwable $e) {
    error_log('crm-addon.php: ' . $e->getMessage());
    crmAddonJson(false, null, 'Não foi possível concluir a operação do CRM adicional.', 500);
}
