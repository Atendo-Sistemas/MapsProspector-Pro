<?php
declare(strict_types=1);

/** Integração privada com apify/instagram-scraper. Tokens nunca saem do backend. */
final class InstagramApifyService
{
    public const ACTOR = 'apify~instagram-scraper';
    private PDO $db;
    private int $timeout;

    public function __construct(PDO $db, int $timeout = 45)
    {
        $this->db = $db;
        $this->timeout = max(10, min(120, $timeout));
    }

    public static function normalizeInstagramUrl(string $url, bool $allowPlace = false): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        if (!in_array($host, ['instagram.com', 'www.instagram.com'], true)) {
            throw new InvalidArgumentException('Informe uma URL válida do Instagram.');
        }
        if (preg_match('#^(p|reel|tv)/[A-Za-z0-9_-]+$#', $path)) {
            [$kind, $id] = explode('/', $path, 2);
            return 'https://www.instagram.com/' . $kind . '/' . $id . '/';
        }
        if ($allowPlace && preg_match('#^explore/locations/[0-9]+(?:/[^/]*)?$#i', $path)) {
            return 'https://www.instagram.com/' . $path . '/';
        }
        throw new InvalidArgumentException('Use a URL de uma publicação ou Reel do Instagram.');
    }

    public static function normalizeLocation(string $location): string
    {
        $location = preg_replace('/\s+/u', ' ', trim($location)) ?? '';
        $location = trim($location, " ,;-\t\n\r\0\x0B");
        $length = function_exists('mb_strlen') ? mb_strlen($location) : strlen($location);
        if ($location === '' || $length < 2 || $length > 160) {
            throw new InvalidArgumentException('Informe um local válido.');
        }
        $ufs = [
            'AC'=>'Acre','AL'=>'Alagoas','AP'=>'Amapá','AM'=>'Amazonas','BA'=>'Bahia','CE'=>'Ceará','DF'=>'Distrito Federal','ES'=>'Espírito Santo','GO'=>'Goiás','MA'=>'Maranhão','MT'=>'Mato Grosso','MS'=>'Mato Grosso do Sul','MG'=>'Minas Gerais','PA'=>'Pará','PB'=>'Paraíba','PR'=>'Paraná','PE'=>'Pernambuco','PI'=>'Piauí','RJ'=>'Rio de Janeiro','RN'=>'Rio Grande do Norte','RS'=>'Rio Grande do Sul','RO'=>'Rondônia','RR'=>'Roraima','SC'=>'Santa Catarina','SP'=>'São Paulo','SE'=>'Sergipe','TO'=>'Tocantins'
        ];
        if (preg_match('/(?:^|[\s,\-])([A-Za-z]{2})\s*$/u', $location, $m)) {
            $uf = strtoupper($m[1]);
            if (isset($ufs[$uf])) {
                $location = preg_replace('/(^|[\s,\-])' . preg_quote($m[1], '/') . '(?=$|[\s,\-])/iu', '$1' . $ufs[$uf], $location) ?? $location;
            }
        }
        // O Actor usa a consulta para localizar lugares no Instagram. Anexar
        // "Brazil" força frequentemente a resolução para o lugar genérico
        // "Brazil", em vez da cidade informada. O país fica implícito para
        // consultas brasileiras e não deve ser acrescentado aqui.
        $location = preg_replace('/\s*,?\s*(brasil|brazil)\s*$/iu', '', $location) ?? $location;
        $location = trim($location, " ,;-	\n\r\0\x0B");
        return $location;
    }

    public function startComments(string $url, int $limit, bool $includeReplies = false): array
    {
        $input = [
            'resultsType' => 'comments',
            'directUrls' => [$url],
            'resultsLimit' => max(1, min(1000, $limit)),
            'isNewestComments' => true,
            'includeNestedComments' => $includeReplies,
        ];
        return $this->startActor($input);
    }

    public function startLocation(string $location, int $postsLimit): array
    {
        $input = [
            'resultsType' => 'posts',
            'search' => $location,
            'searchType' => 'place',
            'resultsLimit' => max(1, min(50, $postsLimit)),
            'searchLimit' => max(1, min(20, $postsLimit)),
            'addParentData' => true,
        ];
        return $this->startActor($input);
    }

    public function startActor(array $input): array
    {
        $key = $this->selectKey();
        if ($key === null) throw new RuntimeException('O serviço de pesquisa não está configurado.');
        $url = 'https://api.apify.com/v2/actors/' . self::ACTOR . '/runs?token=' . rawurlencode($key['api_key']);
        $response = $this->request('POST', $url, $input);
        $data = is_array($response['data'] ?? null) ? $response['data'] : $response;
        $runId = trim((string) ($data['id'] ?? ''));
        $datasetId = trim((string) ($data['defaultDatasetId'] ?? ''));
        if ($runId === '' || $datasetId === '') throw new RuntimeException('Não foi possível iniciar a pesquisa.');
        return ['run_id' => $runId, 'dataset_id' => $datasetId, 'key_id' => (int) $key['id'], 'actor' => self::ACTOR];
    }

    public function status(string $runId, string $token): string
    {
        $response = $this->request('GET', 'https://api.apify.com/v2/actor-runs/' . rawurlencode($runId) . '?token=' . rawurlencode($token));
        return strtoupper(trim((string) (($response['data'] ?? [])['status'] ?? 'UNKNOWN')));
    }

    public function items(string $datasetId, string $token, int $limit = 1000): array
    {
        $url = 'https://api.apify.com/v2/datasets/' . rawurlencode($datasetId) . '/items?clean=true&limit=' . max(1, min(10000, $limit)) . '&token=' . rawurlencode($token);
        $response = $this->request('GET', $url);
        if (isset($response['items']) && is_array($response['items'])) return $response['items'];
        return array_is_list($response) ? $response : [];
    }

    public function tokenById(int $keyId): string
    {
        $stmt = $this->db->prepare('SELECT api_key FROM apify_keys WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$keyId]);
        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    private function selectKey(): ?array
    {
        $stmt = $this->db->query("SELECT id, api_key FROM apify_keys WHERE is_active = 1 AND TRIM(api_key) <> '' ORDER BY (last_used_at IS NULL) DESC, last_used_at ASC, id ASC LIMIT 1");
        $key = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($key) $this->db->prepare('UPDATE apify_keys SET last_used_at = NOW() WHERE id = ?')->execute([(int) $key['id']]);
        return $key;
    }

    public static function normalizePostUrls(array $rows, int $limit): array
    {
        $urls = [];
        $visit = static function (array $row) use (&$visit, &$urls, $limit): void {
            if (count($urls) >= $limit) return;
            $url = trim((string) ($row['url'] ?? $row['inputUrl'] ?? $row['postUrl'] ?? $row['permalink'] ?? ''));
            if ($url !== '' && preg_match('~https?://(www\.)?instagram\.com/(p|reel|tv)/[^/?#]+~i', $url)) {
                $url = preg_replace('/\?.*$/', '', $url) ?? $url;
                $urls[strtolower(rtrim($url, '/'))] = rtrim($url, '/') . '/';
            }
            // Place search returns each place at the top level and its posts
            // inside row[posts]. These posts normally contain only `code`.
            $code = trim((string) ($row['code'] ?? $row['shortCode'] ?? $row['shortcode'] ?? ''));
            if ($code !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $code)) {
                $urls['code:' . strtolower($code)] = 'https://www.instagram.com/p/' . $code . '/';
            }
            foreach (['posts', 'items', 'latestPosts'] as $nestedKey) {
                if (!isset($row[$nestedKey]) || !is_array($row[$nestedKey])) continue;
                foreach ($row[$nestedKey] as $nested) {
                    if (is_array($nested)) $visit($nested);
                    if (count($urls) >= $limit) return;
                }
            }
        };
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $visit($row);
            if (count($urls) >= $limit) break;
        }
        return array_values($urls);
    }

    public static function normalizeComments(array $rows, string $postUrl, int $limit): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $username = trim((string) ($row['ownerUsername'] ?? $row['username'] ?? $row['userName'] ?? $row['owner']['username'] ?? ''));
            $ownerId = trim((string) ($row['ownerId'] ?? $row['owner']['id'] ?? $row['userId'] ?? ''));
            $username = ltrim($username, '@');
            if ($username === '' && $ownerId === '') continue;
            $key = strtolower($ownerId !== '' ? 'id:' . $ownerId : 'user:' . $username);
            $text = trim((string) ($row['text'] ?? $row['comment'] ?? $row['caption'] ?? ''));
            $current = $out[$key] ?? null;
            $lead = [
                'owner_id' => $ownerId,
                'username' => $username,
                'name' => trim((string) ($row['ownerFullName'] ?? $row['fullName'] ?? $row['owner']['fullName'] ?? $row['owner']['name'] ?? '')),
                'profile_url' => $username !== '' ? 'https://www.instagram.com/' . rawurlencode($username) . '/' : '',
                'comment_text' => $text,
                'commented_at' => trim((string) ($row['timestamp'] ?? $row['createdAt'] ?? $row['takenAt'] ?? '')),
                'source_post_url' => $postUrl,
                'interest_level' => self::normalizeInterestLevel(self::interest($text)),
                'comments_count' => (int) (($current['comments_count'] ?? 0) + 1),
            ];
            if ($current === null || strtotime($lead['commented_at']) >= strtotime((string) ($current['commented_at'] ?? ''))) $out[$key] = $lead;
            else $out[$key]['comments_count']++;
            if (count($out) >= $limit) break;
        }
        return array_values($out);
    }

    public static function normalizeInterestLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (in_array($level, ['high', 'alto', 'alta'], true)) return 'high';
        if (in_array($level, ['medium', 'médio', 'medio', 'média', 'media'], true)) return 'medium';
        return 'low';
    }

    private static function interest(string $text): string
    {
        $text = function_exists('mb_strtolower') ? mb_strtolower($text) : strtolower($text);
        if ($text === '') return 'low';
        if (preg_match('/\b(interesse|comprar|pre[çc]o|valor|or[çc]amento|contratar|me chama|me envie|quero|dispon[ií]vel|como fa[çc]o|whatsapp)\b/u', $text)) return 'high';
        if (preg_match('/\b(onde fica|atende|disponibilidade|como funciona|informa[çc][õo]es)\b/u', $text)) return 'medium';
        return 'low';
    }

    private function request(string $method, string $url, ?array $payload = null): array
    {
        $ch = curl_init($url);
        if ($ch === false) throw new RuntimeException('Não foi possível conectar ao serviço de pesquisa.');
        $body = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => $this->timeout, CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'], CURLOPT_POSTFIELDS => $method === 'POST' ? $body : null, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
        $raw = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE); $error = curl_error($ch); curl_close($ch);
        if ($raw === false || $error !== '') throw new RuntimeException('O serviço de pesquisa não respondeu.');
        $decoded = json_decode((string) $raw, true);
        if ($status < 200 || $status >= 300 || !is_array($decoded)) throw new RuntimeException('A pesquisa não pôde ser concluída.');
        return $decoded;
    }
}
