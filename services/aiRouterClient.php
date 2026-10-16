<?php
declare(strict_types=1);

/**
 * Cliente interno para APIs OpenAI-compatíveis configuradas pelo super admin.
 * O token nunca é retornado ao navegador nem escrito em logs.
 */
if (!function_exists('aiRouterReadConfig')) {
    function aiRouterReadConfig(PDO $db): array
    {
        $read = static function (string $key) use ($db): string {
            if (!function_exists('getPlatformSetting')) return '';
            return trim((string) (getPlatformSetting($db, $key) ?? ''));
        };

        $storedKey = $read('ai_router_api_key');
        $apiKey = function_exists('decryptApikey')
            ? trim((string) decryptApikey($storedKey))
            : $storedKey;
        $baseUrl = $read('ai_router_base_url');
        $model = $read('ai_router_model');
        if ($model === '') $model = 'gemini/gemini-3-flash-preview';
        $enabled = in_array(strtolower($read('ai_router_enabled')), ['1', 'true', 'yes', 'on'], true);

        return [
            'base_url' => rtrim($baseUrl, '/'),
            'api_key' => $apiKey,
            'model' => $model,
            'enabled' => $enabled,
            'configured' => $baseUrl !== '' && $apiKey !== '',
        ];
    }

    function aiRouterEndpoint(string $baseUrl): string
    {
        return rtrim($baseUrl, '/') . '/chat/completions';
    }

    function aiRouterChatCompletion(array $config, array $messages, int $maxTokens = 256, bool $jsonObject = false): array
    {
        $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
        $apiKey = trim((string) ($config['api_key'] ?? ''));
        $model = trim((string) ($config['model'] ?? 'gemini/gemini-3-flash-preview')) ?: 'gemini/gemini-3-flash-preview';
        if ($baseUrl === '' || $apiKey === '') {
            throw new RuntimeException('A configuração do 9router está incompleta.');
        }
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.1,
            'max_tokens' => max(8, min(2048, $maxTokens)),
            'stream' => false,
        ];
        if ($jsonObject) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) throw new RuntimeException('Não foi possível preparar a solicitação ao 9router.');

        $ch = curl_init(aiRouterEndpoint($baseUrl));
        if ($ch === false) throw new RuntimeException('Não foi possível iniciar a comunicação com o 9router.');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 45,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $curlError !== '') {
            error_log('ai-router: falha de comunicação HTTP ' . $httpCode);
            throw new RuntimeException('O 9router não respondeu.');
        }
        $rawBody = (string) $body;
        $decoded = json_decode($rawBody, true);
        if (!is_array($decoded) && str_contains($rawBody, 'data:')) {
            $contentParts = [];
            $sseUsage = null;
            $sseModel = $model;
            foreach (preg_split('/\R/', $rawBody) as $line) {
                $line = trim($line);
                if (!str_starts_with($line, 'data:')) continue;
                $chunk = json_decode(trim(substr($line, 5)), true);
                if (!is_array($chunk)) continue;
                $sseModel = (string) ($chunk['model'] ?? $sseModel);
                $sseUsage = is_array($chunk['usage'] ?? null) ? $chunk['usage'] : $sseUsage;
                $delta = $chunk['choices'][0]['delta']['content'] ?? '';
                if (is_scalar($delta)) $contentParts[] = (string) $delta;
            }
            if ($contentParts !== []) {
                $decoded = [
                    'object' => 'chat.completion',
                    'model' => $sseModel,
                    'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => implode('', $contentParts)], 'finish_reason' => 'stop']],
                    'usage' => $sseUsage,
                ];
            }
        }
        if ($httpCode < 200 || $httpCode >= 300 || !is_array($decoded)) {
            error_log('ai-router: resposta HTTP ' . $httpCode);
            if ($httpCode === 401 || $httpCode === 403) throw new RuntimeException('O token do 9router foi recusado.');
            if ($httpCode === 429) throw new RuntimeException('O limite temporário do 9router foi atingido.');
            throw new RuntimeException('O 9router apresentou uma falha.');
        }
        if (!isset($decoded['choices']) || !is_array($decoded['choices'])) {
            throw new RuntimeException('Resposta incompatível do 9router.');
        }
        return $decoded;
    }

    function aiRouterMessageContent(array $response): string
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        if (is_array($content)) {
            $parts = [];
            foreach ($content as $part) {
                if (is_array($part) && isset($part['text']) && is_scalar($part['text'])) $parts[] = (string) $part['text'];
                elseif (is_scalar($part)) $parts[] = (string) $part;
            }
            $content = implode("\n", $parts);
        }
        return trim((string) $content);
    }
}
