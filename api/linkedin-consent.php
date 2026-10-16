<?php
/**
 * Consulta e registra o aceite de uso responsável da pesquisa LinkedIn.
 * Endpoint: /api/linkedin-consent.php
 */

ob_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
ob_clean();

$userId = requireAuth();
$authUser = getAuthUser();
$profile = strtolower((string) ($authUser['profile'] ?? ''));
$isSuperAdmin = ($profile === 'super_admin');
$tenantId = $isSuperAdmin ? 0 : (int) ($authUser['tenant_id'] ?? 0);
$version = '2026-08-11';
$consentKey = 'linkedin_responsible_use';

if ($tenantId <= 0 && !$isSuperAdmin) {
    jsonError('Selecione uma empresa/tenant antes de utilizar a pesquisa LinkedIn.', 400);
}

$db = Database::getInstance()->getConnection();

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $stmt = $db->prepare(
            'SELECT id, document_version, accepted_at FROM legal_consents
             WHERE user_id = ? AND tenant_id = ? AND consent_key = ?
             ORDER BY accepted_at DESC LIMIT 1'
        );
        $stmt->execute([$userId, $tenantId, $consentKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        jsonSuccess([
            'accepted' => (bool) $row,
            'version' => $row['document_version'] ?? null,
            'acceptedAt' => $row['accepted_at'] ?? null,
        ]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonError('Método não permitido.', 405);
    }

    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    if (($input['accepted'] ?? false) !== true) {
        jsonError('É necessário confirmar o aceite para continuar.', 400);
    }

    $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    $stmt = $db->prepare(
        'INSERT INTO legal_consents
          (tenant_id, user_id, consent_key, document_version, document_url, source, accepted_at, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
         ON DUPLICATE KEY UPDATE accepted_at = NOW(), user_agent = VALUES(user_agent)'
    );
    $stmt->execute([
        $tenantId,
        $userId,
        $consentKey,
        $version,
        'https://www.linkedin.com/legal/user-agreement',
        'linkedin_first_search',
        $userAgent,
    ]);

    jsonSuccess([
        'accepted' => true,
        'version' => $version,
    ], 'Aceite registrado com sucesso.');
} catch (PDOException $e) {
    error_log('linkedin-consent.php: ' . $e->getMessage());
    jsonError('A tabela de aceites ainda não está configurada. Execute sql/linkedin-implementacao.sql no banco.', 500);
}
