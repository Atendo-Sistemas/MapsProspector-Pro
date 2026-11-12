<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/BannerCampaign.php';

header('Cache-Control: no-store');
requireAuth();
$user = getAuthUser();
$isSuperAdmin = strtolower((string) ($user['profile'] ?? '')) === 'super_admin';

try {
    $db = Database::getInstance()->getConnection();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$isSuperAdmin) jsonError('Apenas o super administrador pode configurar banners.', 403);
        $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
        if ($contentType !== 'application/json') jsonError('Envie os dados em formato JSON.', 415);
        $input = json_decode((string) file_get_contents('php://input'), true);
        if (!is_array($input)) jsonError('Dados inválidos.', 400);
        try {
            $campaign = bannerCampaignValidate($input);
        } catch (InvalidArgumentException $e) {
            jsonError($e->getMessage(), 422);
        }
        setPlatformSetting($db, 'advertising_banner', json_encode($campaign, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        jsonSuccess($campaign, 'Banner salvo com sucesso.');
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') jsonError('Método não permitido.', 405);

    $stmt = $db->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ?');
    $stmt->execute(['advertising_banner']);
    $stored = $stmt->fetchColumn();
    $campaign = $stored === false ? bannerCampaignValidate([]) : bannerCampaignValidate(json_decode((string) $stored, true, 512, JSON_THROW_ON_ERROR));
    if (($_GET['action'] ?? '') === 'edit') {
        if (!$isSuperAdmin) jsonError('Apenas o super administrador pode configurar banners.', 403);
        jsonSuccess($campaign);
    }
    $page = (string) ($_GET['page'] ?? '');
    if (!in_array($page, ['maps', 'linkedin', 'instagram', 'cnpj', 'history'], true)) jsonError('Página inválida.', 400);
    jsonSuccess(bannerCampaignVisible($campaign, $page, date('Y-m-d'))
        ? ['image' => $campaign['image'], 'link' => $campaign['link'], 'description' => $campaign['description']]
        : null);
} catch (Throwable $e) {
    error_log('banner-campaign: ' . $e->getMessage());
    jsonError('Não foi possível carregar ou salvar a campanha.', 500);
}
