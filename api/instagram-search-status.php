<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../services/instagramApifyService.php';

function instagramRunRow(PDO $db, int $runId, int $tenantId): ?array {
    $stmt = $db->prepare('SELECT * FROM instagram_runs WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute([$runId, $tenantId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
function instagramSaveRun(PDO $db, int $runId, array $values): void {
    $sets = [];
    $params = [];
    foreach ($values as $column => $value) { $sets[] = $column . ' = ?'; $params[] = $value; }
    if (!$sets) return;
    $params[] = $runId;
    $db->prepare('UPDATE instagram_runs SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($params);
}
function instagramPublicRun(array $run): array {
    $leads = json_decode((string) ($run['leads_json'] ?? ''), true);
    return [
        'runId' => (int) $run['id'],
        'status' => (string) $run['status'],
        'stage' => (string) $run['stage'],
        'mode' => (string) $run['mode'],
        'pending' => in_array((string) $run['status'], ['queued','running'], true),
        'postsFound' => (int) $run['posts_found'],
        'commentsCollected' => (int) $run['comments_collected'],
        'leadsFound' => (int) $run['leads_found'],
        'duplicateCount' => (int) $run['duplicate_count'],
        'creditsCharged' => (int) $run['credits_charged'],
        'apifyItemsCount' => (int) $run['apify_items_count'],
        'location' => (string) ($run['normalized_location'] ?? ''),
        'leads' => is_array($leads) ? $leads : [],
        'message' => $run['status'] === 'failed' ? 'A pesquisa não pôde ser concluída nesta tentativa.' : ($run['status'] === 'done' ? 'Pesquisa concluída.' : 'A pesquisa está sendo processada.'),
    ];
}
function instagramFinalize(PDO $db, array $run, array $leads, int $commentsCollected, int $apifyItems): array {
    $tenantId = (int) $run['tenant_id'];
    $unique = [];
    foreach ($leads as $lead) {
        if (!is_array($lead)) continue;
        $key = (string) ($lead['owner_id'] ?? '');
        if ($key === '') $key = strtolower((string) ($lead['username'] ?? ''));
        if ($key === '') $key = strtolower((string) ($lead['profile_url'] ?? ''));
        if ($key !== '') $unique['ig:' . $key] = $lead;
    }
    $leads = array_values($unique);
    $new = 0; $duplicate = 0;
    $db->beginTransaction();
    try {
        $insert = $db->prepare(
            'INSERT INTO instagram_leads (tenant_id, run_id, profile_key, owner_id, username, full_name, profile_url, comment_text, commented_at, source_post_url, interest_level, comments_count, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
             ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), comment_text = VALUES(comment_text), commented_at = VALUES(commented_at), source_post_url = VALUES(source_post_url), interest_level = VALUES(interest_level), comments_count = VALUES(comments_count), updated_at = NOW()'
        );
        foreach ($leads as $lead) {
            $profileKey = 'ig:' . ((string) ($lead['owner_id'] ?? '') !== '' ? 'id:' . $lead['owner_id'] : 'user:' . strtolower((string) ($lead['username'] ?? '')));
            $exists = $db->prepare('SELECT id FROM instagram_leads WHERE tenant_id = ? AND profile_key = ? LIMIT 1');
            $exists->execute([$tenantId, $profileKey]);
            if ($exists->fetchColumn()) $duplicate++; else $new++;
            $interestLevel = InstagramApifyService::normalizeInterestLevel((string) ($lead['interest_level'] ?? 'low'));
            $insert->execute([$tenantId, (int) $run['id'], $profileKey, $lead['owner_id'] ?? null, $lead['username'] ?? null, $lead['name'] ?? null, $lead['profile_url'] ?? null, $lead['comment_text'] ?? null, $lead['commented_at'] ?? null, $lead['source_post_url'] ?? '', $interestLevel, max(1, (int) ($lead['comments_count'] ?? 1))]);
        }
        $charged = 0;
        $profile = strtolower((string) ($_SESSION['profile'] ?? ''));
        if ($new > 0 && !($profile === 'super_admin' || $tenantId === 1) && function_exists('incrementTenantUsage')) {
            incrementTenantUsage($db, $tenantId, $new);
            $charged = $new;
        }
        $json = json_encode($leads, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $db->prepare('UPDATE instagram_runs SET status = ?, stage = ?, leads_json = ?, comments_collected = ?, leads_found = ?, duplicate_count = ?, credits_charged = ?, apify_items_count = ?, error_message = NULL, finished_at = NOW() WHERE id = ?')->execute(['done', 'completed', $json, $commentsCollected, count($leads), $duplicate, $charged, $apifyItems, (int) $run['id']]);
        recordSearchQualityEvent($db, 'instagram', ['provider' => 'apify', 'status' => $leads ? 'success' : 'empty', 'result_count' => count($leads), 'duplicate_count' => $duplicate, 'credits_spent' => $charged]);
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    return instagramRunRow($db, (int) $run['id'], $tenantId) ?: $run;
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') jsonError('Método não permitido.', 405);
    $userId = (int) requireAuth();
    $auth = getAuthUser();
    $profile = strtolower(trim((string) ($auth['profile'] ?? $auth['role'] ?? $_SESSION['profile'] ?? '')));
    $isSuperAdmin = $profile === 'super_admin';
    $tenantId = (int) ($auth['tenant_id'] ?? 0);
    // Super Admin opera no tenant global de validação, mesmo sem tenant_id na sessão.
    if ($isSuperAdmin && $tenantId <= 0) $tenantId = 1;
    if ($tenantId <= 0) jsonError('Sua conta não possui uma empresa vinculada.', 403);
    $runId = max(0, (int) ($_GET['run_id'] ?? 0));
    if ($runId <= 0) jsonError('Identificador da pesquisa inválido.', 422);
    $db = Database::getInstance()->getConnection();
    $run = instagramRunRow($db, $runId, $tenantId);
    if (!$run) jsonError('Pesquisa Instagram não encontrada.', 404);
    if (in_array((string) $run['status'], ['done', 'failed'], true)) jsonSuccess(instagramPublicRun($run));

    $service = new InstagramApifyService($db);
    $commentLeads = [];
    $commentsCollected = 0;
    $apifyItems = 0;

    if ((string) $run['stage'] === 'posts') {
        $token = $service->tokenById((int) $run['api_key_id']);
        if ($token === '') throw new RuntimeException('Chave de pesquisa indisponível.');
        $state = $service->status((string) $run['external_run_id'], $token);
        if (in_array($state, ['RUNNING','READY','STARTING','ABORTING'], true)) jsonSuccess(array_merge(instagramPublicRun($run), ['pending' => true, 'providerStatus' => $state]));
        if ($state !== 'SUCCEEDED') throw new RuntimeException('A etapa de publicações não foi concluída.');
        $rows = $service->items((string) $run['dataset_id'], $token, (int) $run['requested_posts'] * 3);
        $postUrls = InstagramApifyService::normalizePostUrls($rows, (int) $run['requested_posts']);
        if (!$postUrls) {
            instagramSaveRun($db, $runId, ['status' => 'done', 'stage' => 'completed', 'posts_found' => 0, 'leads_json' => '[]', 'finished_at' => date('Y-m-d H:i:s')]);
            jsonSuccess(instagramPublicRun(instagramRunRow($db, $runId, $tenantId) ?: $run));
        }
        $children = [];
        $maxChildren = min(count($postUrls), max(1, (int) floor((int) $run['max_total_comments'] / max(1, (int) $run['requested_comments']))));
        foreach (array_slice($postUrls, 0, $maxChildren) as $postUrl) {
            $child = $service->startComments($postUrl, min((int) $run['requested_comments'], (int) $run['max_total_comments']), false);
            $children[] = ['run_id' => $child['run_id'], 'dataset_id' => $child['dataset_id'], 'key_id' => $child['key_id'], 'post_url' => $postUrl, 'status' => 'running'];
        }
        instagramSaveRun($db, $runId, ['stage' => 'comments', 'posts_found' => count($postUrls), 'child_runs_json' => json_encode($children, JSON_UNESCAPED_SLASHES), 'external_run_id' => null, 'dataset_id' => null]);
        $run = instagramRunRow($db, $runId, $tenantId) ?: $run;
    }

    $children = json_decode((string) ($run['child_runs_json'] ?? ''), true);
    if (!is_array($children) || !$children) {
        $children = [['run_id' => $run['external_run_id'], 'dataset_id' => $run['dataset_id'], 'key_id' => (int) $run['api_key_id'], 'post_url' => (string) $run['input_value'], 'status' => 'running']];
    }
    $allDone = true;
    foreach ($children as &$child) {
        $token = $service->tokenById((int) ($child['key_id'] ?? 0));
        if ($token === '') throw new RuntimeException('Chave de pesquisa indisponível.');
        $state = $service->status((string) ($child['run_id'] ?? ''), $token);
        $child['status'] = strtolower($state);
        if ($state === 'SUCCEEDED') {
            $rows = $service->items((string) $child['dataset_id'], $token, (int) $run['requested_comments']);
            $apifyItems += count($rows);
            $normalized = InstagramApifyService::normalizeComments($rows, (string) $child['post_url'], (int) $run['max_total_comments']);
            $commentsCollected += count($rows);
            foreach ($normalized as $lead) $commentLeads[] = $lead;
        } elseif (in_array($state, ['RUNNING','READY','STARTING','ABORTING'], true)) {
            $allDone = false;
        } else {
            $allDone = false;
        }
    }
    unset($child);
    instagramSaveRun($db, $runId, ['child_runs_json' => json_encode($children, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
    if (!$allDone) {
        jsonSuccess(array_merge(instagramPublicRun($run), ['pending' => true, 'providerStatus' => 'RUNNING']));
    }
    $dedup = [];
    foreach ($commentLeads as $lead) {
        $key = (string) ($lead['owner_id'] ?? '') ?: strtolower((string) ($lead['username'] ?? ''));
        if ($key !== '') $dedup[$key] = $lead;
    }
    $finalLeads = array_slice(array_values($dedup), 0, (int) $run['max_total_comments']);
    $final = instagramFinalize($db, $run, $finalLeads, min((int) $run['max_total_comments'], $commentsCollected), $apifyItems);
    jsonSuccess(instagramPublicRun($final));
} catch (Throwable $e) {
    if (isset($db, $runId)) {
        try { instagramSaveRun($db, (int) $runId, ['status' => 'failed', 'stage' => 'failed', 'error_category' => 'provider', 'error_message' => $e->getMessage(), 'finished_at' => date('Y-m-d H:i:s')]); } catch (Throwable $ignored) {}
    }
    error_log('instagram-search-status: ' . $e->getMessage());
    jsonError('Não foi possível atualizar a pesquisa Instagram agora. Tente novamente em alguns instantes.', 502);
}
