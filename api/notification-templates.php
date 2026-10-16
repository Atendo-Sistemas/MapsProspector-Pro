<?php
/**
 * Central de notificações do painel administrativo.
 *
 * Permite ao administrador editar os templates de mensagem por evento,
 * visualizar o histórico de envios e disparar testes de WhatsApp/e-mail
 * para destinatários informados no próprio painel. Nenhum segredo é
 * devolvido ao navegador e nenhuma operação depende do payload do cliente.
 *
 * GET  -> lista templates (com valores mascarados apenas para tokens)
 * GET  ?action=history&channel=whatsapp|email&limit=N -> histórico de envios
 * POST action=saveTemplates       -> persiste os templates editados
 * POST action=testWhatsApp        -> envia template de teste para telefone
 * POST action=testEmail           -> envia template de teste para e-mail
 * POST action=testTemplate        -> envia o texto exato de um template
 *                                 para o telefone ou e-mail de teste
 *
 * Endpoint: /api/notification-templates.php
 */
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

register_shutdown_function(function (): void {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
    if (!$error || !in_array($error['type'], $fatalTypes, true)) {
        return;
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode([
        'success' => false,
        'error' => 'Falha fatal no endpoint de notificações.',
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
});

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

/**
 * A leitura e a edição de templates não dependem do serviço de envio. Em alguns
 * ambientes cPanel, um erro de compatibilidade no serviço não pode impedir que
 * a Central abra. Carregamos o serviço apenas quando um teste é realmente
 * solicitado e devolvemos uma mensagem JSON segura se ele não estiver disponível.
 */
function notifLoadService(): bool
{
    if (class_exists('NotificationService', false)) {
        return true;
    }

    $serviceFile = __DIR__ . '/../services/notificationService.php';
    if (!is_file($serviceFile)) {
        error_log('notification-templates: notificationService.php não encontrado.');
        return false;
    }

    try {
        require_once $serviceFile;
        return class_exists('NotificationService', false);
    } catch (Throwable $e) {
        error_log('notification-templates: serviço de envio indisponível: ' . $e->getMessage());
        return false;
    }
}

/**
 * Resolve o usuário autenticado a partir da sessão, incluindo o super
 * administrador que não possui linha na tabela de configurações do tenant.
 */
function notifSessionUser(PDO $db): ?array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    // O login da plataforma pode gravar o usuário em estruturas diferentes
    // conforme a versão do auth.php. Lemos as mesmas chaves compatíveis do
    // settings.php para não tratar um super administrador autenticado como
    // uma sessão ausente.
    $sessionCandidates = [
        $_SESSION['user'] ?? null,
        $_SESSION['auth_user'] ?? null,
        $_SESSION['current_user'] ?? null,
        $_SESSION['user_data'] ?? null,
    ];

    foreach ($sessionCandidates as $candidate) {
        if (!is_array($candidate)) {
            continue;
        }

        $id = $candidate['id'] ?? $candidate['user_id'] ?? null;
        $profile = strtolower((string)($candidate['profile'] ?? $candidate['role'] ?? $candidate['user_type'] ?? ''));
        if (($id === null || $id === '') && $profile !== 'super_admin') {
            continue;
        }

        return [
            'id' => $id === null || $id === '' ? 0 : (int)$id,
            'email' => (string)($candidate['email'] ?? ''),
            'profile' => $profile,
            'tenant_id' => $candidate['tenant_id'] ?? $candidate['tenantId'] ?? null,
        ];
    }

    // Perfis administrativos vindos diretamente da sessão de login.
    $sessionProfile = strtolower((string)($_SESSION['profile'] ?? $_SESSION['role'] ?? $_SESSION['user_type'] ?? ''));
    if ($sessionProfile === 'super_admin' || !empty($_SESSION['is_super_admin'])) {
        return [
            'id' => (int)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? 0),
            'email' => (string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? ''),
            'profile' => 'super_admin',
            'tenant_id' => null,
        ];
    }

    $sessionId = (string)($_SESSION['user_id'] ?? $_SESSION['auth_user_id'] ?? '');
    $sessionEmail = (string)($_SESSION['email'] ?? $_SESSION['user_email'] ?? '');
    if ($sessionId === '' && $sessionEmail === '') {
        return null;
    }

    try {
        if ($sessionId !== '') {
            $stmt = $db->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $sessionId]);
        } else {
            $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$sessionEmail]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }
        return [
            'id' => (int) $row['id'],
            'email' => (string) $row['email'],
            'profile' => strtolower((string)($row['profile'] ?? $row['role'] ?? $row['user_type'] ?? '')),
            'tenant_id' => $row['tenant_id'] ?? $row['tenantId'] ?? null,
        ];
    } catch (Throwable $e) {
        error_log('notification-templates: sessão não resolvida: ' . $e->getMessage());
        return null;
    }
}

/**
 * Os textos em platform_settings são globais para toda a plataforma. Por isso
 * somente o super administrador pode editá-los, testar envios ou consultar o
 * histórico global de notificações.
 */
function notifRequireAuthorized(?array $user): ?array
{
    if (!is_array($user)) {
        return null;
    }

    $profile = strtolower((string) $user['profile']);
    if ($profile !== 'super_admin') {
        return null;
    }

    return $user;
}

function notifJson(bool $success, $data = null, string $message = '', int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    $payload = array_merge(
        ['success' => $success],
        $data !== null ? ['data' => $data] : [],
        $message !== '' ? ($success ? ['message' => $message] : ['error' => $message]) : []
    );
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        http_response_code(500);
        $json = '{"success":false,"error":"Falha ao serializar a resposta da central de notificações."}';
    }
    echo $json;
}

function notifGetJson(): array
{
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '[]', true);
    return is_array($decoded) ? $decoded : [];
}

function notifReadSetting(PDO $db, string $key): string
{
    try {
        // Reutiliza o mesmo helper global que já atende settings.php no cPanel.
        // Algumas cópias antigas de includes/functions.php não expõem a rotina
        // uma rotina de criação preventiva, embora a tabela já exista e esteja em uso.
        if (function_exists('getPlatformSetting')) {
            return (string) (getPlatformSetting($db, $key) ?? '');
        }

        $stmt = $db->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? (string) $row['setting_value'] : '';
    } catch (Throwable $e) {
        error_log('notification-templates: leitura falhou: ' . $e->getMessage());
        return '';
    }
}

function notifDefaultTemplate(string $key): string
{
    $defaults = [
        'reengagement-2d-whatsapp' => 'Olá, *{usuario}*! Sentimos sua falta na {plataforma}. Faz {dias} dias que você não acessa o sistema. Volte ao painel para continuar suas pesquisas. Acesse: https://prospec.atendo.log.br/app/',
        'reengagement-2d-email-subject' => 'Sentimos sua falta na {plataforma}',
        'reengagement-2d-email' => '<div style="font-family:Arial,sans-serif;color:#1e293b;max-width:600px;margin:0 auto;padding:20px"><h2 style="color:#2563eb">Sentimos sua falta, {usuario}!</h2><p>Faz <strong>{dias} dias</strong> que você não acessa a plataforma <strong>{plataforma}</strong>.</p><p>Volte ao painel para continuar suas pesquisas e aproveitar os recursos disponíveis para a empresa <strong>{empresa}</strong>.</p><p><a href="https://prospec.atendo.log.br/app/" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px">Acessar o painel</a></p><p style="font-size:12px;color:#64748b">Se você já voltou a acessar o sistema, ignore esta mensagem.</p></div>',
    ];
    return $defaults[$key] ?? '';
}

function notifWriteSetting(PDO $db, string $key, string $value): bool
{
    try {
        $stmt = $db->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value, updated_at)
             VALUES (?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()'
        );
        $stmt->execute([$key, $value, $value]);
        return true;
    } catch (Throwable $e) {
        error_log('notification-templates: escrita falhou: ' . $e->getMessage());
        return false;
    }
}

// Eventos exibidos na central, na ordem do painel.
const NOTIF_TEMPLATE_KEYS = [
    'welcome-whatsapp'       => ['label' => 'Boas-vindas (WhatsApp)', 'channel' => 'whatsapp'],
    'welcome-email-subject'  => ['label' => 'Assunto do e-mail de boas-vindas', 'channel' => 'email'],
    'welcome-email'          => ['label' => 'E-mail de boas-vindas', 'channel' => 'email'],
    'credit-request-whatsapp' => ['label' => 'Solicitação de créditos (WhatsApp admin)', 'channel' => 'whatsapp'],
    'credit-request-email-subject' => ['label' => 'Assunto: solicitação de créditos', 'channel' => 'email'],
    'credit-request-email'   => ['label' => 'E-mail: solicitação de créditos', 'channel' => 'email'],
    'credits-approved-whatsapp' => ['label' => 'Créditos aprovados (WhatsApp cliente)', 'channel' => 'whatsapp'],
    'credits-approved-email-subject' => ['label' => 'Assunto: créditos aprovados', 'channel' => 'email'],
    'credits-approved-email' => ['label' => 'E-mail: créditos aprovados', 'channel' => 'email'],
    'credit-rejected-whatsapp' => ['label' => 'Créditos recusados (WhatsApp cliente)', 'channel' => 'whatsapp'],
    'credit-rejected-email-subject' => ['label' => 'Assunto: créditos recusados', 'channel' => 'email'],
    'credit-rejected-email'  => ['label' => 'E-mail: créditos recusados', 'channel' => 'email'],
    'plan-request-whatsapp'  => ['label' => 'Solicitação de plano (WhatsApp admin)', 'channel' => 'whatsapp'],
    'plan-request-email-subject' => ['label' => 'Assunto: solicitação de plano', 'channel' => 'email'],
    'plan-request-email'     => ['label' => 'E-mail: solicitação de plano', 'channel' => 'email'],
    'plan-approved-whatsapp' => ['label' => 'Plano aprovado (WhatsApp cliente)', 'channel' => 'whatsapp'],
    'crm-approved-whatsapp' => ['label' => 'CRM aprovado (WhatsApp cliente)', 'channel' => 'whatsapp'],
    'reengagement-2d-whatsapp' => ['label' => 'Reengajamento após 2 dias (WhatsApp)', 'channel' => 'whatsapp'],
    'reengagement-2d-email-subject' => ['label' => 'Assunto: reengajamento após 2 dias', 'channel' => 'email'],
    'reengagement-2d-email' => ['label' => 'E-mail: reengajamento após 2 dias', 'channel' => 'email'],
    'plan-approved-email-subject' => ['label' => 'Assunto: plano aprovado', 'channel' => 'email'],
    'plan-approved-email'    => ['label' => 'E-mail: plano aprovado', 'channel' => 'email'],
    'plan-rejected-whatsapp' => ['label' => 'Plano recusado (WhatsApp cliente)', 'channel' => 'whatsapp'],
    'plan-rejected-email-subject' => ['label' => 'Assunto: plano recusado', 'channel' => 'email'],
    'plan-rejected-email'    => ['label' => 'E-mail: plano recusado', 'channel' => 'email'],
    'low-balance-whatsapp'   => ['label' => 'Saldo baixo — 10% (WhatsApp cliente)', 'channel' => 'whatsapp'],
    'test-whatsapp'          => ['label' => 'Teste de WhatsApp', 'channel' => 'whatsapp'],
];

// Não há verificação/criação preventiva da tabela aqui. A plataforma já usa
// platform_settings em settings.php; chamar uma rotina de criação preventiva torna
// este endpoint incompatível com versões do includes/functions.php que não
// exportam esse helper. Leituras e escritas usam os mesmos acessos globais.

// Usa o mesmo bootstrap de conexão que já funciona no settings.php. Sem esta
// inicialização, $db chegava indefinido à leitura da sessão e o PHP encerrava
// com erro fatal antes que a Central pudesse emitir sua resposta JSON.
try {
    $db = Database::getInstance()->getConnection();
} catch (Throwable $e) {
    error_log('notification-templates: conexão com banco indisponível: ' . $e->getMessage());
    notifJson(false, null, 'Não foi possível iniciar a conexão de configurações da plataforma.', 500);
    exit;
}

$method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
// O painel envia a ação dentro do corpo JSON (fetch com Content-Type
// application/json), que não popula $_POST. Por isso a ação é resolvida
// também a partir do payload decodificado, além da query string.
$payload = $method === 'POST' ? notifGetJson() : [];
$action = strtolower((string)($_GET['action'] ?? ($_POST['action'] ?? ($payload['action'] ?? ''))));

$user = notifRequireAuthorized(notifSessionUser($db));
if ($user === null) {
    notifJson(false, null, 'Acesso negado. A central de notificações é exclusiva do super administrador.', 403);
    exit;
}

if ($method === 'GET') {
    if ($action === 'history') {
        // Histórico de envios automáticos e testes (super_admin vê tudo;
        // administradores de empresa veem somente o próprio tenant).
        $channel = in_array(strtolower((string)($payload['channel'] ?? $_GET['channel'] ?? '')), ['whatsapp', 'email'], true)
            ? strtolower((string)($payload['channel'] ?? $_GET['channel'] ?? ''))
            : '';
        $limit = min(max((int)($_GET['limit'] ?? $payload['limit'] ?? 30), 1), 100);

        try {
            // A tabela de controle registra o canal e o status de cada evento.
            $stmt = $db->query('SELECT 1 FROM notification_dispatches LIMIT 1');
            $conditions = [];
            $params = [];
            if ($channel !== '') {
                $conditions[] = 'channel = ?';
                $params[] = $channel;
            }
            if ($user['profile'] !== 'super_admin') {
                $conditions[] = 'tenant_id = ?';
                $params[] = (int) $user['tenant_id'];
            }
            $where = $conditions !== [] ? 'WHERE ' . implode(' AND ', $conditions) : '';
            $rows = $db->prepare(
                "SELECT event_key, tenant_id, channel, status, attempts, last_error, created_at, sent_at
                 FROM notification_dispatches
                 {$where}
                 ORDER BY created_at DESC LIMIT ?"
            );
            $rows->execute(array_merge($params, [$limit]));
            $history = [];
            foreach ($rows->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $history[] = [
                    'eventKey' => (string) $row['event_key'],
                    'tenantId' => $row['tenant_id'] !== null ? (int) $row['tenant_id'] : null,
                    'channel' => (string) $row['channel'],
                    'status' => (string) $row['status'],
                    'attempts' => (int) $row['attempts'],
                    'lastError' => $row['last_error'] !== null ? mb_substr((string) $row['last_error'], 0, 500) : null,
                    'createdAt' => (string) $row['created_at'],
                    'sentAt' => $row['sent_at'] !== null ? (string) $row['sent_at'] : null,
                ];
            }
            notifJson(true, ['history' => $history]);
            exit;
        } catch (Throwable $e) {
            error_log('notification-templates: histórico indisponível: ' . $e->getMessage());
            notifJson(true, ['history' => [], 'missingTable' => true]);
            exit;
        }
    }

    // GET padrão: devolve os templates completos para o editor.
    // Os templates não contêm credenciais; apenas textos de mensagem.
    $templates = [];
    foreach (NOTIF_TEMPLATE_KEYS as $key => $meta) {
        $templates[$key] = [
            'key' => $key,
            'label' => $meta['label'],
            'channel' => $meta['channel'],
            'value' => notifReadSetting($db, 'notif_template:' . $key) ?: notifDefaultTemplate($key),
        ];
    }

    // Contatos úteis para o painel: e-mail e telefone administrativos da plataforma.
    notifJson(true, [
        'templates' => array_values($templates),
        'adminEmail' => notifReadSetting($db, 'atendo_wa_admin_email') ?: 'admin@atendo.log.br',
        'adminNumber' => notifReadSetting($db, 'atendo_wa_admin_number'),
        'platformName' => notifReadSetting($db, 'platform_name') ?: (notifReadSetting($db, 'saas_company_name') ?: 'Maps Prospector'),
    ]);
    exit;
}

if ($method !== 'POST') {
    notifJson(false, null, 'Método não permitido.');
    exit;
}

if ($action === 'savetemplates') {
    $items = is_array($payload['templates'] ?? null) ? $payload['templates'] : [];
    $saved = 0;
    foreach ($items as $item) {
        $key = (string)($item['key'] ?? '');
        if (!isset(NOTIF_TEMPLATE_KEYS[$key])) {
            continue; // Chaves desconhecidas são descartadas: o cliente não define eventos.
        }

        $value = (string)($item['value'] ?? '');
        $value = mb_substr($value, 0, $key === 'test-whatsapp' ? 2000 : 2000);
        if (notifWriteSetting($db, 'notif_template:' . $key, $value)) {
            ++$saved;
        }
    }

    notifJson(true, ['saved' => $saved]);
    exit;
}

if ($action === 'testwhatsapp') {
    if (!notifLoadService()) {
        notifJson(false, null, 'O serviço de envio não pôde ser iniciado. Confirme o arquivo services/notificationService.php.', 500);
        exit;
    }

    $phone = (string)($payload['phone'] ?? '');
    $normalized = NotificationService::normalizeBrazilianPhone($phone);
    if ($normalized === '') {
        notifJson(false, null, 'Telefone inválido. Informe um número brasileiro no formato +55 17 99999-9999.');
        exit;
    }

    $template = notifReadSetting($db, 'notif_template:test-whatsapp');
    $platform = notifReadSetting($db, 'platform_name') ?: (notifReadSetting($db, 'saas_company_name') ?: 'Maps Prospector');
    if (trim($template) === '') {
        $template = 'Olá. Esta é uma mensagem de teste da central de notificações de *{plataforma}*.';
    }
    $message = NotificationService::notifFill($template, [
        'plataforma' => $platform,
        'empresa' => $platform,
        'usuario' => 'Super administrador',
        'acao' => 'executa um teste de notificação',
    ]);

    $eventKey = 'test-wa-' . substr(hash('sha256', $normalized . microtime(true)), 0, 40);
    $sent = NotificationService::sendWhatsApp($db, $normalized, $message, $eventKey);
    notifJson($sent, $sent ? ['message' => 'Mensagem de teste disparada para ' . $normalized] : null,
        $sent ? '' : 'O WhatsApp não foi entregue. Verifique a configuração da API Atendo em Configurações.');
    exit;
}

if ($action === 'testemail') {
    if (!notifLoadService()) {
        notifJson(false, null, 'O serviço de envio não pôde ser iniciado. Confirme o arquivo services/notificationService.php.', 500);
        exit;
    }

    $to = trim((string)($payload['email'] ?? ''));
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        notifJson(false, null, 'E-mail inválido. Informe um endereço como teste@empresa.com.');
        exit;
    }

    $platform = notifReadSetting($db, 'platform_name') ?: (notifReadSetting($db, 'saas_company_name') ?: 'Maps Prospector');
    $testValues = [
        'plataforma' => $platform,
        'empresa' => $platform,
        'usuario' => 'Super administrador',
        'acao' => 'executa um teste de notificação',
    ];
    $subject = NotificationService::notifFill(
        notifReadSetting($db, 'notif_template:welcome-email-subject') ?: 'Teste de notificação em {plataforma}',
        $testValues
    );
    $body = NotificationService::notifFill(
        notifReadSetting($db, 'notif_template:welcome-email') ?: '<p>Teste de notificação da plataforma.</p>',
        $testValues
    );

    $sent = NotificationService::sendEmail($db, $to, $subject, $body);
    notifJson($sent, $sent ? ['message' => 'E-mail de teste disparado para ' . $to] : null,
        $sent ? '' : 'O e-mail não foi entregue. Verifique a configuração SMTP da plataforma.');
    exit;
}

if ($action === 'testtemplate') {
    if (!notifLoadService()) {
        notifJson(false, null, 'O serviço de envio não pôde ser iniciado. Confirme o arquivo services/notificationService.php.', 500);
        exit;
    }

    $templateKey = (string)($payload['templateKey'] ?? '');
    if (!isset(NOTIF_TEMPLATE_KEYS[$templateKey])) {
        notifJson(false, null, 'Template inválido para o teste. Escolha uma das mensagens da central.');
        exit;
    }

    $channel = (string) NOTIF_TEMPLATE_KEYS[$templateKey]['channel'];
    $template = notifReadSetting($db, 'notif_template:' . $templateKey);
    if (trim($template) === '') {
        notifJson(false, null, 'Esta mensagem ainda não tem texto salvo. Edite o template e tente novamente.');
        exit;
    }

    $platform = notifReadSetting($db, 'platform_name') ?: (notifReadSetting($db, 'saas_company_name') ?: 'Maps Prospector');
    $testValues = [
        'plataforma' => $platform,
        'empresa' => $platform,
        'usuario' => 'Super administrador',
        'acao' => 'executa um teste de notificação',
        'email' => '',
        'creditos' => '100',
        'restante' => '10',
        'total' => '100',
        'percentual' => '10',
    ];

    if ($channel === 'whatsapp') {
        $phone = (string)($payload['phone'] ?? '');
        $normalized = NotificationService::normalizeBrazilianPhone($phone);
        if ($normalized === '') {
            notifJson(false, null, 'Telefone de teste inválido. Configure um número brasileiro no campo "Telefone de teste" do topo.');
            exit;
        }

        $testValues['email'] = notifReadSetting($db, 'atendo_wa_admin_email') ?: 'admin@atendo.log.br';
        $message = NotificationService::notifFill($template, $testValues);

        $eventKey = 'test-tpl-' . $templateKey . '-' . substr(hash('sha256', $normalized . microtime(true)), 0, 32);
        $sent = NotificationService::sendWhatsApp($db, $normalized, $message, $eventKey);
        notifJson($sent, $sent ? ['message' => 'Mensagem de teste disparada para ' . $normalized] : null,
            $sent ? '' : 'O WhatsApp não foi entregue. Verifique a configuração da API Atendo em Configurações.');
        exit;
    }

    $email = trim((string)($payload['email'] ?? ''));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        notifJson(false, null, 'E-mail de teste inválido. Configure um endereço como teste@empresa.com no campo "E-mail de teste" do topo.');
        exit;
    }

    $testValues['email'] = $email;
    $subject = NotificationService::notifFill(
        notifReadSetting($db, 'notif_template:' . $templateKey . '-subject') ?: '',
        $testValues
    );
    $body = NotificationService::notifFill($template, $testValues);

    $eventKey = 'test-tpl-' . $templateKey . '-' . substr(hash('sha256', $email . microtime(true)), 0, 32);
    $sent = NotificationService::sendEmail($db, $email, $subject !== '' ? $subject : 'Teste: ' . NOTIF_TEMPLATE_KEYS[$templateKey]['label'], $body);
    notifJson($sent, $sent ? ['message' => 'E-mail de teste disparado para ' . $email] : null,
        $sent ? '' : 'O e-mail não foi entregue. Verifique a configuração SMTP da plataforma.');
    exit;
}

notifJson(false, null, 'Ação desconhecida.');
