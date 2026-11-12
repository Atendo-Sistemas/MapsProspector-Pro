<?php
declare(strict_types=1);

/**
 * Maps Prospector — Serviço central de notificações.
 *
 * O WhatsApp usa a API Atendo configurada nas chaves atendo_wa_base_url /
 * atendo_wa_token, com fallback compatível para whatsapp_api_url /
 * whatsapp_api_token. Nenhuma credencial é devolvida ao navegador e nenhuma
 * mensagem inclui senha ou segredo de acesso.
 */
class NotificationService
{
    private const DISPATCH_RETRY_MINUTES = 30;

    public static function ensureTables(PDO $db): bool
    {
        try {
            $hasNotif = (bool) $db->query("SHOW TABLES LIKE 'internal_notifications'")->fetchColumn();
            $hasReads = (bool) $db->query("SHOW TABLES LIKE 'internal_notification_reads'")->fetchColumn();
            return $hasNotif && $hasReads;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function emit(PDO $db, array $event): ?int
    {
        if (!self::ensureTables($db)) return null;
        $allowedScopes = ['platform', 'profile', 'tenant', 'tenant_profile', 'user'];
        $allowedPriorities = ['low', 'normal', 'high'];
        $scope = (string) ($event['recipient_scope'] ?? 'platform');
        $priority = (string) ($event['priority'] ?? 'normal');
        $title = trim((string) ($event['title'] ?? ''));
        $message = trim((string) ($event['message'] ?? ''));
        if (!in_array($scope, $allowedScopes, true) || !in_array($priority, $allowedPriorities, true)
            || $title === '' || mb_strlen($title) > 160 || $message === '' || mb_strlen($message) > 5000) {
            return null;
        }
        try {
            $stmt = $db->prepare(
                'INSERT INTO internal_notifications
                 (recipient_scope, target_profile, target_tenant_id, target_user_id, category, priority,
                  title, message, source_type, source_reference, created_by_user_id, created_by_label, expires_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $scope,
                $event['target_profile'] ?? null,
                isset($event['target_tenant_id']) ? (int) $event['target_tenant_id'] : null,
                isset($event['target_user_id']) ? (int) $event['target_user_id'] : null,
                substr((string) ($event['category'] ?? 'general'), 0, 48),
                $priority,
                $title,
                $message,
                substr((string) ($event['source_type'] ?? 'system'), 0, 32),
                isset($event['source_reference']) ? substr((string) $event['source_reference'], 0, 100) : null,
                isset($event['created_by_user_id']) ? (int) $event['created_by_user_id'] : null,
                isset($event['created_by_label']) ? substr((string) $event['created_by_label'], 0, 160) : 'Sistema',
                $event['expires_at'] ?? null,
            ]);
            return (int) $db->lastInsertId();
        } catch (Throwable $e) {
            error_log('NotificationService: notificação interna não registrada.');
            return null;
        }
    }

    /** Compatível com a assinatura atual e com a chamada legada de solicitações. */
    public static function notifyCreditRequest(PDO $db, $requesterUser, int $amount, int $requestId = 0): ?int
    {
        if (is_array($requesterUser)) {
            $tenantName = (string) ($requesterUser['tenant_name'] ?? '');
            $userName = (string) ($requesterUser['name'] ?? 'Usuário');
            return self::emit($db, [
                'recipient_scope' => 'profile',
                'target_profile' => 'super_admin',
                'category' => 'credit_pending',
                'priority' => 'high',
                'title' => 'Solicitação de créditos pendente de análise',
                'message' => sprintf('%s (%s) solicitou %s créditos. Pedido #%d aguarda análise em Solicitar Créditos.', $userName ?: 'Usuário', $tenantName ?: 'Sem empresa', number_format($amount, 0, ',', '.'), $requestId),
                'source_type' => 'credit_request',
                'source_reference' => (string) $requestId,
                'expires_at' => (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s'),
            ]);
        }
        $companyName = (string) $requesterUser;
        $adminNumber = self::platformAdminNumber($db);
        $template = self::setting($db, 'notif_template:credit-request-whatsapp') ?: 'Nova solicitação de créditos: *{empresa}* solicitou *{creditos} créditos*.';
        $message = self::notifFill($template, ['plataforma' => self::platformName($db), 'empresa' => $companyName, 'usuario' => 'Administrador', 'creditos' => (string) $amount]);
        self::sendWhatsApp($db, $adminNumber, $message, self::makeEventKey('credit-request', [$companyName, (string) $amount, (string) $requestId]));
        return null;
    }

    public static function notifyCreditDecision(PDO $db, int $requesterUserId, bool $approved, int $amount): ?int
    {
        return self::emit($db, [
            'recipient_scope' => 'user',
            'target_user_id' => $requesterUserId,
            'category' => $approved ? 'credit_approved' : 'credit_rejected',
            'priority' => 'high',
            'title' => $approved ? 'Créditos aprovados' : 'Solicitação de créditos não aprovada',
            'message' => $approved ? sprintf('Sua solicitação de %s créditos foi aprovada e já está disponível na conta.', number_format($amount, 0, ',', '.')) : sprintf('Sua solicitação de %s créditos não foi aprovada. Entre em contato com o administrador da sua empresa para mais detalhes.', number_format($amount, 0, ',', '.')),
            'source_type' => 'credit_decision',
            'source_reference' => (string) $requesterUserId,
            'expires_at' => (new DateTimeImmutable('+60 days'))->format('Y-m-d H:i:s'),
        ]);
    }

    /** Mantém o aviso interno e também envia WhatsApp ao comprador quando houver telefone. */
    public static function notifyPaymentConfirmed(PDO $db, array $buyerUser, int $amountCents): ?int
    {
        $valueLabel = 'R$ ' . number_format(max(0, $amountCents) / 100, 2, ',', '.');
        $phone = (string) ($buyerUser['phone'] ?? '');
        if ($phone !== '') {
            $template = self::setting($db, 'notif_template:credits-approved-whatsapp') ?: 'Seu pagamento de {valor} foi confirmado e os créditos já estão disponíveis na conta.';
            self::sendWhatsApp($db, $phone, self::notifFill($template, ['plataforma' => self::platformName($db), 'empresa' => (string) ($buyerUser['tenant_name'] ?? ''), 'usuario' => (string) ($buyerUser['name'] ?? 'Usuário'), 'valor' => $valueLabel]), self::makeEventKey('payment-confirmed', [(string) ($buyerUser['id'] ?? ''), (string) $amountCents]));
        }
        self::emit($db, [
            'recipient_scope' => 'user',
            'target_user_id' => isset($buyerUser['id']) ? (int) $buyerUser['id'] : null,
            'category' => 'payment',
            'priority' => 'high',
            'title' => 'Pagamento confirmado',
            'message' => sprintf('Seu pagamento de %s foi confirmado e os créditos já estão disponíveis na conta.', $valueLabel),
            'source_type' => 'payment',
            'expires_at' => (new DateTimeImmutable('+60 days'))->format('Y-m-d H:i:s'),
        ]);
        return self::emit($db, [
            'recipient_scope' => 'profile',
            'target_profile' => 'super_admin',
            'category' => 'payment',
            'priority' => 'normal',
            'title' => 'Pagamento confirmado na plataforma',
            'message' => sprintf('Um pagamento de %s foi confirmado e os créditos foram creditados na empresa.', $valueLabel),
            'source_type' => 'payment',
            'expires_at' => (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s'),
        ]);
    }

    /** Aceita a chamada moderna (array) e a chamada de cadastro da versão anterior. */
    public static function notifyNewRegistration(PDO $db, $companyOrUser, $tenantIdOrEmail, $emailOrPhone = null, $ignoredPasswordOrName = null): ?int
    {
        if (is_array($companyOrUser)) {
            return self::emit($db, [
                'recipient_scope' => 'profile',
                'target_profile' => 'super_admin',
                'category' => 'account',
                'priority' => 'normal',
                'title' => 'Novo cadastro na plataforma',
                'message' => sprintf('%s cadastrou a empresa %s.', (string) ($companyOrUser['name'] ?? 'Usuário'), (string) ($companyOrUser['tenant_name'] ?? 'Sem empresa')),
                'source_type' => 'registration',
                'source_reference' => (string) ($companyOrUser['id'] ?? $tenantIdOrEmail ?? ''),
                'expires_at' => (new DateTimeImmutable('+14 days'))->format('Y-m-d H:i:s'),
            ]);
        }

        $companyName = trim((string) $companyOrUser);
        $adminEmail = trim((string) $tenantIdOrEmail);
        $adminPhone = $emailOrPhone !== null ? (string) $emailOrPhone : '';
        $platform = self::platformName($db);
        $template = self::setting($db, 'notif_template:welcome-whatsapp') ?: 'Olá. O cadastro da empresa *{empresa}* foi confirmado em *{plataforma}*. Use o seu e-mail cadastrado para acessar o painel.';
        if ($adminPhone !== '') {
            self::sendWhatsApp($db, $adminPhone, self::notifFill($template, ['plataforma' => $platform, 'empresa' => $companyName, 'usuario' => 'Administrador', 'acao' => 'acessar o painel']), self::makeEventKey('registration', [$adminEmail, $companyName]));
        }
        if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            self::sendEmail($db, $adminEmail, 'Cadastro confirmado em ' . $platform, '<p>O cadastro da empresa <strong>' . self::html($companyName) . '</strong> foi confirmado em <strong>' . self::html($platform) . '</strong>. Use o e-mail informado no cadastro para acessar o painel.</p>');
        }
        return null;
    }

    public static function notifyCreditApproval(PDO $db, string $companyName, int $credits, string $clientEmail, ?string $clientPhone = null): void
    {
        $template = self::setting($db, 'notif_template:credits-approved-whatsapp') ?: 'Olá. A solicitação de *{creditos} créditos* para *{empresa}* foi aprovada. O saldo já está disponível no painel.';
        if ($clientPhone) self::sendWhatsApp($db, $clientPhone, self::notifFill($template, ['plataforma' => self::platformName($db), 'empresa' => $companyName, 'usuario' => '', 'creditos' => (string) $credits]), self::makeEventKey('credits-approved', [$clientEmail, (string) $credits]));
    }

    public static function notifyPlanRequest(PDO $db, string $companyName, string $planName): void
    {
        $template = self::setting($db, 'notif_template:plan-request-whatsapp') ?: 'Nova solicitação de plano: *{empresa}* solicitou *{acao}*.';
        self::sendWhatsApp($db, self::platformAdminNumber($db), self::notifFill($template, ['plataforma' => self::platformName($db), 'empresa' => $companyName, 'acao' => $planName]), self::makeEventKey('plan-request', [$companyName, $planName]));
    }

    public static function notifyPlanApproval(PDO $db, string $companyName, string $planName, string $clientEmail, ?string $clientPhone = null): void
    {
        if ($clientPhone) {
            $template = self::setting($db, 'notif_template:plan-approved-whatsapp') ?: 'Olá. O plano da empresa *{empresa}* foi atualizado para *{acao}*. Os novos limites já estão ativos no painel.';
            self::sendWhatsApp($db, $clientPhone, self::notifFill($template, ['plataforma' => self::platformName($db), 'empresa' => $companyName, 'acao' => $planName]), self::makeEventKey('plan-approved', [$clientEmail, $planName]));
        }
    }

    public static function notifyLowBalance(PDO $db, int $tenantId, string $clientPhone, string $companyName, int $usedTokens, int $totalTokens, string $periodStart): bool
    {
        if ($tenantId <= 0 || $totalTokens <= 0 || self::normalizeBrazilianPhone($clientPhone) === '') return false;
        $remaining = max(0, $totalTokens - max(0, $usedTokens));
        $remainingPercent = ($remaining / $totalTokens) * 100;
        if ($remainingPercent > 10) return false;
        $template = self::setting($db, 'notif_template:low-balance-whatsapp') ?: 'Olá. O saldo de créditos da empresa *{empresa}* está baixo: restam *{restante} de {total} créditos* ({percentual}%). Acesse o painel para solicitar créditos.';
        $message = self::notifFill($template, ['plataforma' => self::platformName($db), 'empresa' => $companyName, 'restante' => (string) $remaining, 'total' => (string) $totalTokens, 'percentual' => (string) (int) floor($remainingPercent)]);
        if ($db->inTransaction()) return self::queueWhatsAppDispatch($db, self::makeEventKey('low-balance-10', [$tenantId, $periodStart]), $tenantId);
        return self::sendWhatsApp($db, $clientPhone, $message, self::makeEventKey('low-balance-10', [$tenantId, $periodStart]));
    }

    public static function deferLowBalanceDispatch(PDO $db, int $tenantId, string $clientPhone, string $companyName, int $usedTokens, int $totalTokens, string $periodStart): void
    {
        static $scheduled = [];
        $key = self::makeEventKey('low-balance-10', [$tenantId, $periodStart]);
        if (isset($scheduled[$key])) return;
        $scheduled[$key] = true;
        register_shutdown_function(static function () use ($db, $tenantId, $clientPhone, $companyName, $usedTokens, $totalTokens, $periodStart): void {
            if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
            try { self::notifyLowBalance($db, $tenantId, $clientPhone, $companyName, $usedTokens, $totalTokens, $periodStart); } catch (Throwable $e) { error_log('NotificationService: alerta pós-resposta não enviado.'); }
        });
    }

    public static function sendWhatsApp(PDO $db, string $phone, string $message, string $externalKey = ''): bool
    {
        $apiUrl = self::setting($db, 'atendo_wa_base_url') ?: self::setting($db, 'whatsapp_api_url');
        $apiToken = self::setting($db, 'atendo_wa_token') ?: self::setting($db, 'whatsapp_api_token');
        $number = self::normalizeBrazilianPhone($phone);
        if ($apiUrl === '' || $apiToken === '' || $number === '' || trim($message) === '') return false;
        if ($externalKey !== '' && !self::claimWhatsAppDispatch($db, $externalKey, null)) return false;
        if (!function_exists('curl_init')) { self::finishWhatsAppDispatch($db, $externalKey, false, 'Extensão cURL indisponível.'); return false; }
        $payload = json_encode(['number' => $number, 'body' => trim($message), 'externalKey' => $externalKey !== '' ? $externalKey : self::makeEventKey('message', [$number, microtime(true)])], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) { self::finishWhatsAppDispatch($db, $externalKey, false, 'Não foi possível codificar a mensagem.'); return false; }
        $curl = curl_init($apiUrl);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8', 'Authorization: Bearer ' . $apiToken]]);
        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $curlError = (string) curl_error($curl);
        curl_close($curl);
        $success = $response !== false && $httpCode >= 200 && $httpCode < 300;
        self::finishWhatsAppDispatch($db, $externalKey, $success, $success ? '' : ($curlError !== '' ? $curlError : 'Resposta HTTP ' . $httpCode));
        if (!$success) error_log('NotificationService WhatsApp: falha no envio. HTTP=' . $httpCode . ', evento=' . ($externalKey !== '' ? $externalKey : 'sem-chave'));
        return $success;
    }

    public static function normalizeBrazilianPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if ($digits === null) return '';
        if (str_starts_with($digits, '00')) $digits = substr($digits, 2);
        if (strlen($digits) === 10 || strlen($digits) === 11) $digits = '55' . $digits;
        return (strlen($digits) === 12 || strlen($digits) === 13) && str_starts_with($digits, '55') ? $digits : '';
    }

    public static function notifFill(string $template, array $values): string
    {
        $replace = [];
        foreach ($values as $key => $value) {
            $replace['{' . $key . '}'] = (string) $value;
            $replace['{{' . $key . '}}'] = (string) $value;
        }
        return strtr($template, $replace);
    }

    public static function sendEmail(PDO $db, string $to, string $subject, string $messageHtml): bool
    {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
        $fromEmail = trim((string) (self::setting($db, 'smtp_from_email') ?: 'noreply@atendo.log.br'));
        $fromName = trim((string) (self::setting($db, 'smtp_from_name') ?: self::platformName($db)));
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n" . 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . ">\r\n";
        return @mail($to, $subject, $messageHtml, $headers);
    }

    public static function getTenantNotificationContact(PDO $db, int $tenantId): ?array
    {
        try {
            $stmt = $db->prepare("SELECT t.name AS company_name, u.name AS user_name, u.email, u.phone FROM tenants t INNER JOIN users u ON u.tenant_id = t.id WHERE t.id = ? ORDER BY CASE WHEN u.profile = 'admin' THEN 0 WHEN u.profile IN ('manager', 'gerente', 'supervisor') THEN 1 ELSE 2 END, u.id ASC LIMIT 1");
            $stmt->execute([$tenantId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private static function setting(PDO $db, string $key): string
    {
        try {
            if (function_exists('getPlatformSetting')) return trim((string) (getPlatformSetting($db, $key) ?? ''));
            $stmt = $db->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1');
            $stmt->execute([$key]);
            return trim((string) ($stmt->fetchColumn() ?: ''));
        } catch (Throwable $e) {
            return '';
        }
    }

    private static function platformName(PDO $db): string
    {
        return self::setting($db, 'platform_name') ?: (self::setting($db, 'saas_company_name') ?: 'Maps Prospector');
    }

    private static function platformAdminNumber(PDO $db): string
    {
        return self::setting($db, 'atendo_wa_admin_number');
    }

    private static function html(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    private static function makeEventKey(string $prefix, array $parts): string
    {
        return $prefix . '-' . substr(hash('sha256', implode('|', array_map(static fn($part): string => (string) $part, $parts))), 0, 40);
    }

    private static function ensureDispatchTable(PDO $db): bool
    {
        static $available = null;
        if ($available !== null) return $available;
        try {
            $db->query('SELECT 1 FROM notification_dispatches LIMIT 1');
            return $available = true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) return $available = false;
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS notification_dispatches (event_key VARCHAR(100) NOT NULL PRIMARY KEY, tenant_id BIGINT NULL, channel VARCHAR(24) NOT NULL, status VARCHAR(24) NOT NULL, attempts INT NOT NULL DEFAULT 0, last_error VARCHAR(500) NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, sent_at DATETIME NULL, INDEX idx_notification_dispatches_tenant_status (tenant_id, status)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                return $available = true;
            } catch (Throwable $ignored) {
                return $available = false;
            }
        }
    }

    private static function claimWhatsAppDispatch(PDO $db, string $eventKey, ?int $tenantId): bool
    {
        if (!self::ensureDispatchTable($db)) return false;
        try {
            $insert = $db->prepare("INSERT IGNORE INTO notification_dispatches (event_key, tenant_id, channel, status, attempts, created_at, updated_at) VALUES (?, ?, 'whatsapp', 'processing', 1, NOW(), NOW())");
            $insert->execute([$eventKey, $tenantId]);
            if ($insert->rowCount() === 1) return true;
            $retry = $db->prepare("UPDATE notification_dispatches SET status = 'processing', attempts = attempts + 1, last_error = NULL, updated_at = NOW() WHERE event_key = ? AND (status = 'queued' OR (status = 'failed' AND updated_at < DATE_SUB(NOW(), INTERVAL " . self::DISPATCH_RETRY_MINUTES . " MINUTE)))");
            $retry->execute([$eventKey]);
            return $retry->rowCount() === 1;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function queueWhatsAppDispatch(PDO $db, string $eventKey, int $tenantId): bool
    {
        if (!self::ensureDispatchTable($db)) return false;
        try {
            $stmt = $db->prepare("INSERT IGNORE INTO notification_dispatches (event_key, tenant_id, channel, status, attempts, created_at, updated_at) VALUES (?, ?, 'whatsapp', 'queued', 0, NOW(), NOW())");
            $stmt->execute([$eventKey, $tenantId]);
            return $stmt->rowCount() === 1;
        } catch (Throwable $e) {
            return false;
        }
    }

    private static function finishWhatsAppDispatch(PDO $db, string $eventKey, bool $success, string $error = ''): void
    {
        if ($eventKey === '') return;
        try {
            $stmt = $db->prepare("UPDATE notification_dispatches SET status = ?, last_error = ?, updated_at = NOW(), sent_at = CASE WHEN ? = 1 THEN NOW() ELSE sent_at END WHERE event_key = ?");
            $stmt->execute([$success ? 'sent' : 'failed', $success ? null : mb_substr($error, 0, 500), $success ? 1 : 0, $eventKey]);
        } catch (Throwable $e) {
            error_log('NotificationService: status do dispatch não registrado.');
        }
    }
}
