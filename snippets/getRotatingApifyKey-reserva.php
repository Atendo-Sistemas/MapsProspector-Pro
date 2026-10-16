<?php
/**
 * Substitui a função getRotatingApifyKey() existente.
 *
 * Produção: usa somente contas role=production sem estado de cooldown e sem
 * snapshot recente crítico/atingido.
 * Reserva: só é promovida quando nenhuma conta de produção está disponível.
 * O token nunca deve ser retornado ao frontend ou registrado em log.
 */
function getRotatingApifyKey($db) {
    try {
        $sql = "
            SELECT k.id, k.api_key,
                   COALESCE(p.role, 'production') AS key_role,
                   COALESCE(p.state, 'active') AS policy_state,
                   au.status AS usage_status,
                   au.checked_at AS usage_checked_at
            FROM apify_keys k
            LEFT JOIN apify_key_policies p ON p.provider_key_id = k.id
            LEFT JOIN apify_account_usage au ON au.id = (
                SELECT latest.id
                FROM apify_account_usage latest
                WHERE latest.provider_key_id = k.id
                ORDER BY latest.checked_at DESC
                LIMIT 1
            )
            WHERE k.is_active = 1
              AND (
                    COALESCE(p.role, 'production') = 'production'
                    OR (p.role = 'reserve' AND p.state = 'active')
              )
              AND COALESCE(p.state, 'active') NOT IN ('cooldown', 'disabled')
              AND COALESCE(au.status, 'unknown') NOT IN ('critical', 'limit_reached', 'error')
            ORDER BY CASE WHEN COALESCE(p.role, 'production') = 'production' THEN 0 ELSE 1 END,
                     k.last_used_at ASC, k.id ASC
            LIMIT 1
        ";
        $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $reserveSql = "
                SELECT k.id, k.api_key
                FROM apify_keys k
                INNER JOIN apify_key_policies p ON p.provider_key_id = k.id
                LEFT JOIN apify_account_usage au ON au.id = (
                    SELECT latest.id
                    FROM apify_account_usage latest
                    WHERE latest.provider_key_id = k.id
                    ORDER BY latest.checked_at DESC
                    LIMIT 1
                )
                WHERE k.is_active = 1
                  AND p.role = 'reserve'
                  AND p.state = 'standby'
                  AND COALESCE(au.status, 'unknown') NOT IN ('critical', 'limit_reached', 'error')
                ORDER BY p.priority ASC, k.last_used_at ASC, k.id ASC
                LIMIT 1
            ";
            $row = $db->query($reserveSql)->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $promote = $db->prepare("UPDATE apify_key_policies
                    SET state = 'active', promoted_at = NOW(), last_transition_at = NOW(),
                        transition_reason = 'Failover automático: nenhuma conta de produção disponível'
                    WHERE provider_key_id = ? AND role = 'reserve' AND state = 'standby'");
                $promote->execute([(int) $row['id']]);
                error_log('Conta reserva Apify promovida: ID ' . (int) $row['id']);
            }
        }

        if ($row) {
            $update = $db->prepare('UPDATE apify_keys SET tokens_used = tokens_used + 1, last_used_at = NOW() WHERE id = ?');
            $update->execute([(int) $row['id']]);
            return trim((string) $row['api_key']);
        }
    } catch (Throwable $e) {
        error_log('Erro na rotação de chaves Apify: ' . $e->getMessage());
    }

    return getPlatformSetting($db, 'scraper_api_key') ?: (defined('SCRAPER_API_KEY') ? SCRAPER_API_KEY : '');
}
