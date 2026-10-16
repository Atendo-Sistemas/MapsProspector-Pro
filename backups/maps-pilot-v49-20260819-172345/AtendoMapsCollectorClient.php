<?php
declare(strict_types=1);

/**
 * Cliente servidor-a-servidor para o piloto Maps.
 *
 * Este arquivo não deve ser exposto no webroot e deve permanecer desativado
 * até a publicação aprovada da rota collector.atendo.log.br.
 */
final class AtendoMapsCollectorClient
{
    private array $config;

    public function __construct(string $configPath = '/home/naciona4/atendo-secrets/maps-collector.php')
    {
        if (!is_file($configPath) || !is_readable($configPath)) {
            throw new RuntimeException('Configuração privada do coletor Maps indisponível.');
        }

        $config = require $configPath;
        if (!is_array($config)) {
            throw new RuntimeException('Configuração privada do coletor Maps inválida.');
        }

        $this->config = $config;
    }

    public function isEnabled(): bool
    {
        return ($this->config['enabled'] ?? false) === true;
    }

    public function createJob(string $requestId, string $query, int $maxResults = 50, string $language = 'pt'): array
    {
        return $this->request('POST', '/v1/maps', [
            'request_id' => $requestId,
            'query' => $query,
            'max_results' => $maxResults,
            'language' => $language,
        ]);
    }

    public function getJob(string $jobId): array
    {
        return $this->request('GET', '/v1/maps/jobs/' . rawurlencode($jobId));
    }

    public function downloadAndDeleteJob(string $jobId): string
    {
        return $this->requestRaw('GET', '/v1/maps/jobs/' . rawurlencode($jobId) . '/download');
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $response = $this->send($method, $path, $body);

        try {
            $decoded = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new RuntimeException('Resposta inválida do coletor Maps.');
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException((string) ($decoded['message'] ?? 'Falha ao comunicar com o coletor Maps.'));
        }

        return $decoded;
    }

    private function requestRaw(string $method, string $path): string
    {
        $response = $this->send($method, $path, '');
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Resultado Maps ainda não disponível.');
        }

        return $response['body'];
    }

    private function send(string $method, string $path, string $body): array
    {
        if (!$this->isEnabled()) {
            throw new RuntimeException('Coletor Maps self-hosted ainda não está habilitado.');
        }

        $baseUrl = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $secret = (string) ($this->config['hmac_secret'] ?? '');
        if ($baseUrl === '' || strlen($secret) < 32) {
            throw new RuntimeException('Configuração privada do coletor Maps incompleta.');
        }

        $timestamp = (string) time();
        $nonce = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $signature = hash_hmac('sha256', $timestamp . '.' . $nonce . '.' . $body, $secret);

        $handle = curl_init($baseUrl . $path);
        if ($handle === false) {
            throw new RuntimeException('Não foi possível iniciar a conexão com o coletor Maps.');
        }

        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 130,
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
            throw new RuntimeException('Conexão com o coletor Maps indisponível' . ($error !== '' ? ': ' . $error : '.'));
        }

        return ['status' => $status, 'body' => $response];
    }
}
