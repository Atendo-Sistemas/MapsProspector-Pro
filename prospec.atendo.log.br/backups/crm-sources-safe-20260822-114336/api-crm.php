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

$userId = (int) requireAuth();
$authUser = getAuthUser();
$db = Database::getInstance()->getConnection();
$profile = strtolower((string) ($authUser['profile'] ?? ''));
$tenantId = (int) ($authUser['tenant_id'] ?? 0);
$input = [];
if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
    $raw = json_decode((string) file_get_contents('php://input'), true);
    if (is_array($raw)) {
        $input = $raw;
    }
}
if ($profile === 'super_admin') {
    $requestedTenant = (int) ($input['tenant_id'] ?? $_GET['tenant_id'] ?? 0);
    if ($requestedTenant > 0) {
        $tenantId = $requestedTenant;
    }
    if ($tenantId <= 0) {
        $tenantId = 1;
    }
}
if ($tenantId <= 0) {
    jsonError('Conta sem empresa vinculada.', 403);
}

if (!mpTenantHasModule($db, $tenantId, 'crm', $authUser)) {
    jsonError('O módulo CRM não está incluído no plano desta empresa. Contrate o CRM como módulo adicional.', 403);
}

const CRM_STAGES = ['novo', 'qualificado', 'contato', 'proposta', 'negociacao', 'ganho', 'perdido'];
const CRM_ACTIVITY_TYPES = ['nota', 'ligacao', 'email', 'reuniao', 'tarefa'];

function crmFail(string $message, int $status = 400): never
{
    jsonError($message, $status);
}

function crmReadTenantUser(PDO $db, int $tenantId, int $userId): ?array
{
    $stmt = $db->prepare('SELECT id, name, email, profile FROM users WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute([$userId, $tenantId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return is_array($row) ? $row : null;
}

function crmEnsureLicense(PDO $db, int $tenantId): array
{
    try {
        $stmt = $db->prepare('SELECT * FROM crm_licenses WHERE tenant_id = ? AND status = \'active\' ORDER BY id DESC LIMIT 1');
        $stmt->execute([$tenantId]);
        $license = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        throw new RuntimeException('license lookup: ' . $e->getMessage(), 0, $e);
    }
    if (is_array($license)) {
        return $license;
    }

    try {
        $planStmt = $db->prepare('SELECT t.plan_id, COALESCE(p.included_users, 3) AS included_users FROM tenants t LEFT JOIN plans p ON p.id = t.plan_id WHERE t.id = ? LIMIT 1');
        $planStmt->execute([$tenantId]);
    } catch (Throwable $e) {
        throw new RuntimeException('plan lookup: ' . $e->getMessage(), 0, $e);
    }
    $plan = $planStmt->fetch(PDO::FETCH_ASSOC) ?: ['plan_id' => null, 'included_users' => 3];
    $included = max(1, (int) ($plan['included_users'] ?? 3));
    try {
        $insert = $db->prepare('INSERT INTO crm_licenses (tenant_id, plan_id, included_users, max_users, additional_user_price_cents, status, created_at, updated_at) VALUES (?, ?, ?, ?, 1990, \'active\', NOW(), NOW())');
        $insert->execute([$tenantId, $plan['plan_id'] !== null ? (int) $plan['plan_id'] : null, $included, $included]);
    } catch (Throwable $e) {
        throw new RuntimeException('license insert: ' . $e->getMessage(), 0, $e);
    }
    $stmt->execute([$tenantId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
        'tenant_id' => $tenantId,
        'included_users' => $included,
        'max_users' => $included,
        'additional_user_price_cents' => 1990,
        'status' => 'active',
    ];
}

function crmLicensePayload(PDO $db, int $tenantId): array
{
    $license = crmEnsureLicense($db, $tenantId);
    try {
        $userStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE tenant_id = ? AND (status IS NULL OR status = 'active')");
        $userStmt->execute([$tenantId]);
        $currentUsers = (int) $userStmt->fetchColumn();
    } catch (Throwable $e) {
        try {
            $userStmt = $db->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ?');
            $userStmt->execute([$tenantId]);
            $currentUsers = (int) $userStmt->fetchColumn();
        } catch (Throwable $fallbackError) {
            throw new RuntimeException('user count fallback: ' . $fallbackError->getMessage(), 0, $fallbackError);
        }
    }
    $included = max(1, (int) ($license['included_users'] ?? 3));
    $excess = max(0, $currentUsers - $included);
    $unit = max(0, (int) ($license['additional_user_price_cents'] ?? 1990));
    return [
        'id' => (int) ($license['id'] ?? 0),
        'status' => (string) ($license['status'] ?? 'active'),
        'sourceType' => (string) ($license['source_type'] ?? 'legacy'),
        'planId' => $license['plan_id'] !== null ? (int) $license['plan_id'] : null,
        'includedUsers' => $included,
        'maxUsers' => max($included, (int) ($license['max_users'] ?? $included)),
        'currentUsers' => $currentUsers,
        'excessUsers' => $excess,
        'additionalUserPriceCents' => $unit,
        'estimatedExcessMonthlyCents' => $excess * $unit,
        'billingNote' => 'Usuários dentro da franquia incluída não geram cobrança adicional. Excedentes são calculados para a próxima cobrança e não são faturados automaticamente por esta API.',
    ];
}

function crmLeadOwnership(PDO $db, int $tenantId, ?int $leadId, ?int $linkedinLeadId): bool
{
    if ($leadId !== null && $leadId > 0) {
        $stmt = $db->prepare('SELECT l.id FROM leads l INNER JOIN search_history sh ON sh.id = l.search_history_id INNER JOIN users owner_user ON owner_user.id = sh.user_id AND owner_user.tenant_id = ? WHERE l.id = ? LIMIT 1');
        $stmt->execute([$tenantId, $leadId]);
        return (bool) $stmt->fetchColumn();
    }
    if ($linkedinLeadId !== null && $linkedinLeadId > 0) {
        $stmt = $db->prepare('SELECT id FROM linkedin_leads WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$linkedinLeadId, $tenantId]);
        return (bool) $stmt->fetchColumn();
    }
    return false;
}

function crmLeadSelectSql(): string
{
    return "SELECT c.id, c.tenant_id, c.lead_id, c.linkedin_lead_id, c.source, c.stage,
                   c.assignee_user_id, c.next_contact_at, c.notes, c.created_at, c.updated_at,
                   COALESCE(CASE WHEN lead_owner.id IS NOT NULL THEN l.name END, ll.full_name) AS lead_name,
                   COALESCE(NULLIF(l.name, ''), ll.company_name, '') AS company_name,
                   COALESCE(CASE WHEN lead_owner.id IS NOT NULL THEN l.email END, '') AS email,
                   COALESCE(CASE WHEN lead_owner.id IS NOT NULL THEN l.phone END, '') AS phone,
                   COALESCE(CASE WHEN lead_owner.id IS NOT NULL THEN l.website END, ll.profile_url) AS website,
                   COALESCE(CASE WHEN lead_owner.id IS NOT NULL THEN l.address END, ll.location) AS location,
                   u.name AS assignee_name
              FROM crm_leads c
              LEFT JOIN leads l ON l.id = c.lead_id
              LEFT JOIN search_history sh ON sh.id = l.search_history_id
              LEFT JOIN users lead_owner ON lead_owner.id = sh.user_id AND lead_owner.tenant_id = c.tenant_id
              LEFT JOIN linkedin_leads ll ON ll.id = c.linkedin_lead_id AND ll.tenant_id = c.tenant_id
              LEFT JOIN users u ON u.id = c.assignee_user_id AND u.tenant_id = c.tenant_id";
}

function crmSearchLeadCandidates(PDO $db, int $tenantId, string $query, string $source, int $limit = 25): array
{
    $query = trim($query);
    if (mb_strlen($query) < 2) {
        return [];
    }
    $source = strtolower(trim($source));
    if (!in_array($source, ['all', 'maps', 'linkedin'], true)) {
        $source = 'all';
    }
    $term = '%' . $query . '%';
    $limit = min(50, max(1, $limit));
    $items = [];

    if ($source === 'all' || $source === 'maps') {
        $sql = "SELECT l.id AS lead_id, l.name AS lead_name, l.name AS company_name, l.email, l.phone, l.address AS location, l.website,
                       l.cnpj, l.created_at, CASE WHEN existing.id IS NULL THEN 0 ELSE 1 END AS in_pipeline
                  FROM leads l
                  INNER JOIN search_history sh ON sh.id = l.search_history_id
                  INNER JOIN users lead_owner ON lead_owner.id = sh.user_id AND lead_owner.tenant_id = ?
                  LEFT JOIN crm_leads existing ON existing.tenant_id = ? AND existing.lead_id = l.id
                 WHERE (l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.address LIKE ? OR l.cnpj LIKE ?)
                 ORDER BY l.created_at DESC
                 LIMIT {$limit}";
        $stmt = $db->prepare($sql);
        $stmt->execute([$tenantId, $tenantId, $term, $term, $term, $term, $term]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'leadId' => (int) $row['lead_id'], 'linkedinLeadId' => null, 'source' => 'maps',
                'leadName' => (string) ($row['lead_name'] ?? 'Lead sem nome'), 'companyName' => (string) ($row['company_name'] ?? ''),
                'email' => (string) ($row['email'] ?? ''), 'phone' => (string) ($row['phone'] ?? ''),
                'location' => (string) ($row['location'] ?? ''), 'website' => (string) ($row['website'] ?? ''),
                'cnpj' => (string) ($row['cnpj'] ?? ''), 'inPipeline' => (bool) $row['in_pipeline'],
            ];
        }
    }

    if ($source === 'all' || $source === 'linkedin') {
        $sql = "SELECT ll.id AS linkedin_lead_id, ll.full_name AS lead_name, ll.company_name, ll.job_title,
                       ll.location, ll.profile_url, ll.photo_url, ll.created_at,
                       CASE WHEN existing.id IS NULL THEN 0 ELSE 1 END AS in_pipeline
                  FROM linkedin_leads ll
                  LEFT JOIN crm_leads existing ON existing.tenant_id = ? AND existing.linkedin_lead_id = ll.id
                 WHERE ll.tenant_id = ?
                   AND (ll.full_name LIKE ? OR ll.company_name LIKE ? OR ll.job_title LIKE ? OR ll.location LIKE ? OR ll.profile_url LIKE ?)
                 ORDER BY ll.created_at DESC
                 LIMIT {$limit}";
        $stmt = $db->prepare($sql);
        $stmt->execute([$tenantId, $tenantId, $term, $term, $term, $term, $term]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $items[] = [
                'leadId' => null, 'linkedinLeadId' => (int) $row['linkedin_lead_id'], 'source' => 'linkedin',
                'leadName' => (string) ($row['lead_name'] ?? 'Perfil sem nome'), 'companyName' => (string) ($row['company_name'] ?? ''),
                'jobTitle' => (string) ($row['job_title'] ?? ''), 'location' => (string) ($row['location'] ?? ''),
                'profileUrl' => (string) ($row['profile_url'] ?? ''), 'photoUrl' => (string) ($row['photo_url'] ?? ''),
                'inPipeline' => (bool) $row['in_pipeline'],
            ];
        }
    }

    usort($items, static function (array $a, array $b): int {
        if ($a['inPipeline'] !== $b['inPipeline']) return $a['inPipeline'] <=> $b['inPipeline'];
        return strcasecmp((string) ($a['leadName'] ?? ''), (string) ($b['leadName'] ?? ''));
    });
    return array_slice($items, 0, $limit);
}

function crmFormatLead(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'leadId' => $row['lead_id'] !== null ? (int) $row['lead_id'] : null,
        'linkedinLeadId' => $row['linkedin_lead_id'] !== null ? (int) $row['linkedin_lead_id'] : null,
        'source' => (string) ($row['source'] ?? 'maps'),
        'stage' => (string) ($row['stage'] ?? 'novo'),
        'leadName' => (string) ($row['lead_name'] ?? 'Lead sem nome'),
        'companyName' => (string) ($row['company_name'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'phone' => (string) ($row['phone'] ?? ''),
        'website' => (string) ($row['website'] ?? ''),
        'location' => (string) ($row['location'] ?? ''),
        'assigneeUserId' => $row['assignee_user_id'] !== null ? (int) $row['assignee_user_id'] : null,
        'assigneeName' => (string) ($row['assignee_name'] ?? ''),
        'nextContactAt' => $row['next_contact_at'] !== null ? (string) $row['next_contact_at'] : null,
        'notes' => (string) ($row['notes'] ?? ''),
        'createdAt' => (string) ($row['created_at'] ?? ''),
        'updatedAt' => (string) ($row['updated_at'] ?? ''),
    ];
}

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
try {
    if ($method === 'GET') {
        $action = strtolower(trim((string) ($_GET['action'] ?? 'list')));
        if ($action === 'meta') {
            try {
                $users = $db->prepare("SELECT id, name, email, profile FROM users WHERE tenant_id = ? AND (status IS NULL OR status = 'active') ORDER BY name ASC");
                $users->execute([$tenantId]);
            } catch (Throwable $e) {
                try {
                    $users = $db->prepare('SELECT id, name, email, profile FROM users WHERE tenant_id = ? ORDER BY name ASC');
                    $users->execute([$tenantId]);
                } catch (Throwable $fallbackError) {
                    throw new RuntimeException('meta users fallback: ' . $fallbackError->getMessage(), 0, $fallbackError);
                }
            }
            $people = array_map(static fn(array $u): array => [
                'id' => (int) $u['id'], 'name' => (string) $u['name'],
                'email' => (string) $u['email'], 'profile' => (string) $u['profile'],
            ], $users->fetchAll(PDO::FETCH_ASSOC));
            jsonSuccess(['stages' => CRM_STAGES, 'activityTypes' => CRM_ACTIVITY_TYPES, 'users' => $people, 'license' => crmLicensePayload($db, $tenantId)]);
        }

        if ($action === 'search-leads' || $action === 'lead-search') {
            $query = trim((string) ($_GET['q'] ?? $_GET['query'] ?? ''));
            $source = trim((string) ($_GET['source'] ?? 'all'));
            $items = crmSearchLeadCandidates($db, $tenantId, $query, $source, (int) ($_GET['limit'] ?? 25));
            jsonSuccess(['items' => $items, 'total' => count($items), 'query' => $query, 'source' => $source]);
        }

        if ($action === 'activities') {
            $crmLeadId = (int) ($_GET['crm_lead_id'] ?? 0);
            if ($crmLeadId <= 0) {
                crmFail('Informe o lead do CRM.', 400);
            }
            $stmt = $db->prepare('SELECT a.id, a.crm_lead_id, a.user_id, a.activity_type, a.body, a.due_at, a.created_at, u.name AS user_name FROM crm_activities a LEFT JOIN users u ON u.id = a.user_id WHERE a.tenant_id = ? AND a.crm_lead_id = ? ORDER BY a.created_at DESC');
            $stmt->execute([$tenantId, $crmLeadId]);
            $items = array_map(static function (array $a): array {
                return ['id' => (int) $a['id'], 'crmLeadId' => (int) $a['crm_lead_id'], 'userId' => (int) $a['user_id'], 'userName' => (string) ($a['user_name'] ?? ''), 'activityType' => (string) $a['activity_type'], 'body' => (string) $a['body'], 'dueAt' => $a['due_at'] !== null ? (string) $a['due_at'] : null, 'createdAt' => (string) $a['created_at']];
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
            jsonSuccess(['items' => $items]);
        }

        $where = ['c.tenant_id = ?'];
        $params = [$tenantId];
        $stage = strtolower(trim((string) ($_GET['stage'] ?? '')));
        if ($stage !== '' && in_array($stage, CRM_STAGES, true)) {
            $where[] = 'c.stage = ?';
            $params[] = $stage;
        }
        $search = trim((string) ($_GET['q'] ?? ''));
        if ($search !== '') {
            $where[] = '(l.name LIKE ? OR l.email LIKE ? OR l.phone LIKE ? OR l.address LIKE ? OR l.cnpj LIKE ? OR ll.full_name LIKE ? OR ll.company_name LIKE ? OR ll.job_title LIKE ? OR ll.location LIKE ? OR ll.profile_url LIKE ?)';
            $term = '%' . $search . '%';
            array_push($params, $term, $term, $term, $term, $term, $term, $term, $term, $term, $term);
        }
        $limit = min(200, max(1, (int) ($_GET['limit'] ?? 100)));
        $sql = crmLeadSelectSql() . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY FIELD(c.stage, \'novo\', \'qualificado\', \'contato\', \'proposta\', \'negociacao\', \'ganho\', \'perdido\'), c.updated_at DESC LIMIT ' . $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $items = array_map('crmFormatLead', $stmt->fetchAll(PDO::FETCH_ASSOC));
        jsonSuccess(['items' => $items, 'total' => count($items), 'stages' => CRM_STAGES, 'license' => crmLicensePayload($db, $tenantId)]);
    }

    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        $action = strtolower(trim((string) ($input['action'] ?? 'upsert')));
        if ($action === 'add' || ($action === 'upsert' && empty($input['id']))) {
            $leadId = isset($input['lead_id']) && (int) $input['lead_id'] > 0 ? (int) $input['lead_id'] : null;
            $linkedinLeadId = isset($input['linkedin_lead_id']) && (int) $input['linkedin_lead_id'] > 0 ? (int) $input['linkedin_lead_id'] : null;
            if (!$leadId && !$linkedinLeadId) {
                crmFail('Informe lead_id ou linkedin_lead_id para adicionar ao pipeline.', 400);
            }
            if ($leadId && $linkedinLeadId) {
                crmFail('Escolha apenas uma origem de lead.', 400);
            }
            if (!crmLeadOwnership($db, $tenantId, $leadId, $linkedinLeadId)) {
                crmFail('Lead não encontrado ou não pertence à sua empresa.', 404);
            }
            $stage = strtolower(trim((string) ($input['stage'] ?? 'novo')));
            if (!in_array($stage, CRM_STAGES, true)) {
                crmFail('Etapa de pipeline inválida.', 400);
            }
            $assignee = isset($input['assignee_user_id']) && (int) $input['assignee_user_id'] > 0 ? (int) $input['assignee_user_id'] : null;
            if ($assignee !== null && !crmReadTenantUser($db, $tenantId, $assignee)) {
                crmFail('Responsável não pertence à empresa.', 400);
            }
            $source = $linkedinLeadId ? 'linkedin' : 'maps';
            $checkSql = $leadId ? 'SELECT id FROM crm_leads WHERE tenant_id = ? AND lead_id = ? LIMIT 1' : 'SELECT id FROM crm_leads WHERE tenant_id = ? AND linkedin_lead_id = ? LIMIT 1';
            $check = $db->prepare($checkSql);
            $check->execute([$tenantId, $leadId ?? $linkedinLeadId]);
            $existingId = $check->fetchColumn();
            if ($existingId) {
                crmFail('Este lead já está no pipeline.', 409);
            }
            $stmt = $db->prepare('INSERT INTO crm_leads (tenant_id, lead_id, linkedin_lead_id, source, stage, assignee_user_id, next_contact_at, notes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $stmt->execute([$tenantId, $leadId, $linkedinLeadId, $source, $stage, $assignee, crmDateOrNull($input['next_contact_at'] ?? null), mb_substr(trim((string) ($input['notes'] ?? '')), 0, 10000)]);
            $newId = (int) $db->lastInsertId();
            $row = $db->prepare(crmLeadSelectSql() . ' WHERE c.tenant_id = ? AND c.id = ? LIMIT 1');
            $row->execute([$tenantId, $newId]);
            jsonSuccess(crmFormatLead($row->fetch(PDO::FETCH_ASSOC) ?: ['id' => $newId]), 'Lead adicionado ao pipeline.');
        }

        if (in_array($action, ['bulk-stage', 'bulk-update', 'move-selected'], true)) {
            $rawIds = $input['ids'] ?? $input['crm_lead_ids'] ?? [];
            if (!is_array($rawIds)) {
                crmFail('Selecione ao menos um lead para movimentar.', 400);
            }
            $crmIds = [];
            foreach ($rawIds as $rawId) {
                $id = (int) $rawId;
                if ($id > 0 && !in_array($id, $crmIds, true)) $crmIds[] = $id;
            }
            if (!$crmIds) {
                crmFail('Selecione ao menos um lead para movimentar.', 400);
            }
            if (count($crmIds) > 100) {
                crmFail('Movimente no máximo 100 leads por vez.', 400);
            }
            $bulkStage = strtolower(trim((string) ($input['stage'] ?? '')));
            if (!in_array($bulkStage, CRM_STAGES, true)) {
                crmFail('Escolha uma etapa válida para os leads selecionados.', 400);
            }
            $placeholders = implode(',', array_fill(0, count($crmIds), '?'));
            $countStmt = $db->prepare('SELECT COUNT(*) FROM crm_leads WHERE tenant_id = ? AND id IN (' . $placeholders . ')');
            $countStmt->execute(array_merge([$tenantId], $crmIds));
            $ownedCount = (int) $countStmt->fetchColumn();
            if ($ownedCount !== count($crmIds)) {
                crmFail('Um ou mais leads selecionados não pertencem à sua empresa.', 404);
            }
            $updateStmt = $db->prepare('UPDATE crm_leads SET stage = ?, updated_at = NOW() WHERE tenant_id = ? AND id IN (' . $placeholders . ')');
            $updateStmt->execute(array_merge([$bulkStage, $tenantId], $crmIds));
            jsonSuccess(['updated' => (int) $updateStmt->rowCount(), 'stage' => $bulkStage, 'ids' => $crmIds], 'Leads movimentados para a etapa selecionada.');
        }

        $crmLeadId = (int) ($input['id'] ?? $input['crm_lead_id'] ?? 0);
        if ($crmLeadId <= 0) {
            crmFail('Informe o identificador do registro CRM.', 400);
        }
        $exists = $db->prepare('SELECT id FROM crm_leads WHERE id = ? AND tenant_id = ? LIMIT 1');
        $exists->execute([$crmLeadId, $tenantId]);
        if (!$exists->fetchColumn()) {
            crmFail('Registro do CRM não encontrado.', 404);
        }

        if ($action === 'activity') {
            $type = strtolower(trim((string) ($input['activity_type'] ?? 'nota')));
            if (!in_array($type, CRM_ACTIVITY_TYPES, true)) {
                crmFail('Tipo de atividade inválido.', 400);
            }
            $body = mb_substr(trim((string) ($input['body'] ?? '')), 0, 10000);
            if ($body === '') {
                crmFail('Descreva a atividade.', 400);
            }
            $stmt = $db->prepare('INSERT INTO crm_activities (tenant_id, crm_lead_id, user_id, activity_type, body, due_at, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
            $stmt->execute([$tenantId, $crmLeadId, $userId, $type, $body, crmDateOrNull($input['due_at'] ?? null)]);
            jsonSuccess(['id' => (int) $db->lastInsertId()], 'Atividade registrada.');
        }

        $updates = [];
        $params = [];
        if (array_key_exists('stage', $input)) {
            $stage = strtolower(trim((string) $input['stage']));
            if (!in_array($stage, CRM_STAGES, true)) {
                crmFail('Etapa de pipeline inválida.', 400);
            }
            $updates[] = 'stage = ?';
            $params[] = $stage;
        }
        if (array_key_exists('assignee_user_id', $input)) {
            $assignee = (int) $input['assignee_user_id'];
            if ($assignee > 0 && !crmReadTenantUser($db, $tenantId, $assignee)) {
                crmFail('Responsável não pertence à empresa.', 400);
            }
            $updates[] = 'assignee_user_id = ?';
            $params[] = $assignee > 0 ? $assignee : null;
        }
        if (array_key_exists('next_contact_at', $input)) {
            $updates[] = 'next_contact_at = ?';
            $params[] = crmDateOrNull($input['next_contact_at']);
        }
        if (array_key_exists('notes', $input)) {
            $updates[] = 'notes = ?';
            $params[] = mb_substr(trim((string) $input['notes']), 0, 10000);
        }
        if (!$updates) {
            crmFail('Nenhum campo para atualizar.', 400);
        }
        $updates[] = 'updated_at = NOW()';
        $params[] = $crmLeadId;
        $params[] = $tenantId;
        $db->prepare('UPDATE crm_leads SET ' . implode(', ', $updates) . ' WHERE id = ? AND tenant_id = ?')->execute($params);
        $row = $db->prepare(crmLeadSelectSql() . ' WHERE c.tenant_id = ? AND c.id = ? LIMIT 1');
        $row->execute([$tenantId, $crmLeadId]);
        jsonSuccess(crmFormatLead($row->fetch(PDO::FETCH_ASSOC) ?: ['id' => $crmLeadId]), 'Pipeline atualizado.');
    }

    if ($method === 'DELETE') {
        $crmLeadId = (int) ($input['id'] ?? $_GET['id'] ?? 0);
        if ($crmLeadId <= 0) {
            crmFail('Informe o registro que será removido.', 400);
        }
        $stmt = $db->prepare('DELETE FROM crm_leads WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$crmLeadId, $tenantId]);
        if ($stmt->rowCount() < 1) {
            crmFail('Registro do CRM não encontrado.', 404);
        }
        jsonSuccess(null, 'Lead removido do pipeline.');
    }

    crmFail('Método não permitido.', 405);
} catch (PDOException $e) {
    error_log('crm.php PDO: ' . $e->getMessage());
    crmFail('Não foi possível concluir a operação do CRM. Verifique se a migração foi executada.', 500);
} catch (Throwable $e) {
    error_log('crm.php: ' . $e->getMessage());
    crmFail($e->getMessage(), 422);
}

function crmDateOrNull(mixed $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        crmFail('Data inválida.', 400);
    }
    return date('Y-m-d H:i:s', $timestamp);
}
