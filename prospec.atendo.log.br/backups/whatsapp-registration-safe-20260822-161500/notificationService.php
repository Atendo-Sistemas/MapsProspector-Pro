<?php
/**
 * Maps Prospector — Serviço interno da Central de Notificações (v20260819-63).
 *
 * Registrado por endpoints confiáveis do servidor: eventos de cadastro,
 * solicitações de créditos, decisões de aprovação/recusa e crédito baixo.
 * Nunca exponha estas funções diretamente ao navegador para gerar eventos.
 */

class NotificationService
{
    public static function ensureTables(PDO $db): bool
    {
        $hasNotif = (bool) $db->query("SHOW TABLES LIKE 'internal_notifications'")->fetchColumn();
        $hasReads = (bool) $db->query("SHOW TABLES LIKE 'internal_notification_reads'")->fetchColumn();
        return $hasNotif && $hasReads;
    }

    private static function emit(PDO $db, array $event): ?int
    {
        if (!self::ensureTables($db)) {
            return null;
        }
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
    }

    /**
     * Notifica os administradores responsáveis (super_admin + admin da plataforma)
     * quando uma empresa solicita créditos.
     */
    public static function notifyCreditRequest(PDO $db, array $requesterUser, int $amount, int $requestId): ?int
    {
        $tenantName = isset($requesterUser['tenant_name']) ? (string) $requesterUser['tenant_name'] : '';
        $userName = isset($requesterUser['name']) ? (string) $requesterUser['name'] : 'Usuário';
        return self::emit($db, [
            'recipient_scope' => 'profile',
            'target_profile' => 'super_admin',
            'category' => 'credit_pending',
            'priority' => 'high',
            'title' => 'Solicitação de créditos pendente de análise',
            'message' => sprintf(
                '%s (%s) solicitou %s créditos. Pedido #%d aguarda análise em Solicitar Créditos.',
                $userName ?: 'Usuário',
                $tenantName ?: 'Sem empresa',
                number_format($amount, 0, ',', '.'),
                $requestId
            ),
            'source_type' => 'credit_request',
            'source_reference' => (string) $requestId,
            'expires_at' => (new DateTimeImmutable('+30 days'))->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Notifica o solicitante quando sua solicitação de créditos é aprovada ou recusada.
     */
    public static function notifyCreditDecision(PDO $db, int $requesterUserId, bool $approved, int $amount): ?int
    {
        return self::emit($db, [
            'recipient_scope' => 'user',
            'target_user_id' => $requesterUserId,
            'category' => $approved ? 'credit_approved' : 'credit_rejected',
            'priority' => 'high',
            'title' => $approved ? 'Créditos aprovados' : 'Solicitação de créditos não aprovada',
            'message' => $approved
                ? sprintf('Sua solicitação de %s créditos foi aprovada e já está disponível na conta.', number_format($amount, 0, ',', '.'))
                : sprintf('Sua solicitação de %s créditos não foi aprovada. Entre em contato com o administrador da sua empresa para mais detalhes.', number_format($amount, 0, ',', '.')),
            'source_type' => 'credit_decision',
            'source_reference' => (string) $requesterUserId,
            'expires_at' => (new DateTimeImmutable('+60 days'))->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Notifica o comprador do pagamento confirmado e alerta a plataforma.
     * Chamado pelos endpoints de pagamento após a confirmação idempotente.
     */
    public static function notifyPaymentConfirmed(PDO $db, array $buyerUser, int $amountCents): ?int
    {
        $valueLabel = 'R$ ' . number_format(max(0, (int) $amountCents) / 100, 2, ',', '.');
        self::emit($db, [
            'recipient_scope' => 'user',
            'target_user_id' => isset($buyerUser['id']) ? (int) $buyerUser['id'] : null,
            'category' => 'payment',
            'priority' => 'high',
            'title' => 'Pagamento confirmado',
            'message' => sprintf(
                'Seu pagamento de %s foi confirmado e os créditos já estão disponíveis na conta.',
                $valueLabel
            ),
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

    /**
     * Notifica o administrador da empresa sobre um novo cadastro na plataforma.
     * Chamado pelo register.php imediatamente após criar o usuário.
     */
    public static function notifyNewRegistration(PDO $db, array $user, ?int $tenantId, string $email, string $name): ?int
    {
        $tenantName = (string) ($user['tenant_name'] ?? '');
        return self::emit($db, [
            'recipient_scope' => 'profile',
            'target_profile' => 'super_admin',
            'category' => 'account',
            'priority' => 'normal',
            'title' => 'Novo cadastro na plataforma',
            'message' => sprintf(
                '%s cadastrou a empresa %s.',
                $name ?: 'Usuário',
                $tenantName ?: 'Sem empresa'
            ),
            'source_type' => 'registration',
            'source_reference' => (string) ($user['id'] ?? $tenantId ?? ''),
            'expires_at' => (new DateTimeImmutable('+14 days'))->format('Y-m-d H:i:s'),
        ]);
    }
}
