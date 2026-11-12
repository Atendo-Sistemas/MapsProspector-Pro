<?php
declare(strict_types=1);

/**
 * Cliente servidor-a-servidor para o coletor LinkedIn self-hosted
 * (gateway privado em collector.atendo.log.br).
 *
 * Reutiliza o mesmo arquivo de configuração privada do coletor Maps
 * (/home/naciona4/atendo-secrets/maps-collector.php): a base_url e o
 * hmac_secret são idênticos, pois ambos os coletores passam pelo mesmo
 * gateway com o contrato de segurança Atendo (timestamp + nonce + HMAC).
 *
 * Este arquivo não deve ser exposto no webroot.
 */
final class AtendoLinkedinCollectorClient
{
    private array $config;

    public function __construct(string $configPath = '/home/naciona4/atendo-secrets/maps-collector.php')
    {
        if (!is_file($configPath) || !is_readable($configPath)) {
            throw new RuntimeException('Configuração privada do coletor indisponível.');
        }

        $config = require $configPath;
        if (!is_array($config)) {
            throw new RuntimeException('Configuração privada do coletor inválida.');
        }

        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return ($this->config['enabled'] ?? false) === true;
    }

    /**
     * Devolve exclusivamente o estado operacional da sessão temporária mantida
     * na VPS. Cookies e credenciais nunca atravessam este contrato.
     */
    public function getSessionStatus(): array
    {
        return $this->request('GET', '/v1/linkedin/session');
    }

    /**
     * Inicia uma coleta de perfis LinkedIn públicos.
     *
     * @param string $jobTitle cargo ou função (2 a 120 caracteres)
     * @param string $location localidade, ex.: "Campinas, SP"
     * @param string $company  empresa opcional para direcionar a busca
     * @param int    $maxResults limite por execução (1 a 50)
     * @return array{job_id: string, status: string, limit_requested: int}
     */
    public function createJob(string $jobTitle, string $location, string $company = '', int $maxResults = 50): array
    {
        $requestId = 'ln-' . bin2hex(random_bytes(16));
        $payload = [
            'request_id' => $requestId,
            'job_title' => $jobTitle,
            'location' => $location,
        ];
        if ($company !== '') {
            $payload['company'] = mb_substr($company, 0, 120);
        }
        if ($maxResults > 0 && $maxResults <= 50) {
            $payload['max_results'] = $maxResults;
        }

        return $this->request('POST', '/v1/linkedin', $payload);
    }

    public function getJob(string $jobId): array
    {
        return $this->request('GET', '/v1/linkedin/jobs/' . rawurlencode($jobId));
    }

    /**
     * Baixa o CSV do resultado e o remove do coletor em uma única chamada.
     * O CSV tem o mesmo formato do coletor Maps, com a coluna profile_url.
     */
    public function downloadAndDeleteJob(string $jobId): string
    {
        return $this->requestRaw('GET', '/v1/linkedin/jobs/' . rawurlencode($jobId) . '/download');
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $response = $this->send($method, $path, $body);

        try {
            $decoded = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Resposta inválida do coletor LinkedIn.');
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException(
                (string) ($decoded['message'] ?? 'Falha ao comunicar com o coletor LinkedIn.'),
                $response['status']
            );
        }

        return $decoded;
    }

    private function requestRaw(string $method, string $path): string
    {
        $response = $this->send($method, $path, '');
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Resultado LinkedIn ainda não disponível.');
        }

        return $response['body'];
    }

    private function send(string $method, string $path, string $body, int $timeoutSeconds = 130): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Coletor LinkedIn self-hosted ainda não está habilitado.');
        }

        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $secret = (string) ($this->config['hmac_secret'] ?? '');
        if ($baseUrl === '' || strlen($secret) < 32) {
            throw new RuntimeException('Configuração privada do coletor incompleta.');
        }

        $timestamp = (string) time();
        $nonce = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secret);

        $handle = curl_init($baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Não foi possível iniciar a conexão com o coletor LinkedIn.');
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => min(10, max(1, $timeoutSeconds)),
            CURLOPT_TIMEOUT => max(1, $timeoutSeconds),
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/csv',
                'Content-Type: application/json',
                'X-Atendo-Timestamp: ' . $timestamp,
                'X-Atendo-Nonce: ' . $nonce,
                'X-Atendo-Signature: sha256=' . $signature,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method !== 'GET') {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $error = curl_error($handle);
        curl_close($handle);

        if ($response === false) {
            throw new RuntimeException('Conexão com o coletor LinkedIn indisponível' . ($error !== '' ? ': ' . $error : '.'));
        }

        return ['status' => $status, 'body' => $response];
    }
}
