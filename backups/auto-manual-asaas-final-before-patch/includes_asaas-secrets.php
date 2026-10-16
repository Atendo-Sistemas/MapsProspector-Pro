<?php

declare(strict_types=1);

/**
 * Armazenamento privado das credenciais Asaas.
 * O arquivo fica fora do document root e nunca é retornado ao navegador.
 */
function asaSecretKeyAllowed(string $key): bool
{
    return in_array($key, ['asaas_api_key', 'asaas_webhook_token'], true);
}

function asaSecretFilePath(): string
{
    // __DIR__ = /home/naciona4/prospec.atendo.log.br/includes
    return dirname(__DIR__, 2) . '/private/asaas-secrets.json';
}

function asaSecretReadAll(): array
{
    $path = asaSecretFilePath();
    if (!is_file($path)) {
        return [];
    }
    $raw = @file_get_contents($path);
    $data = json_decode((string) $raw, true);
    if (!is_array($data)) {
        return [];
    }
    $out = [];
    foreach (['asaas_api_key', 'asaas_webhook_token'] as $key) {
        if (isset($data[$key]) && is_string($data[$key]) && trim($data[$key]) !== '') {
            $out[$key] = trim($data[$key]);
        }
    }
    return $out;
}

function asaSecretGet(string $key): string
{
    if (!asaSecretKeyAllowed($key)) {
        return '';
    }
    $data = asaSecretReadAll();
    return trim((string) ($data[$key] ?? ''));
}

function asaSecretWrite(string $key, string $value): bool
{
    if (!asaSecretKeyAllowed($key)) {
        return false;
    }
    $path = asaSecretFilePath();
    $directory = dirname($path);
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        error_log('asaas secrets: não foi possível criar o diretório privado.');
        return false;
    }
    @chmod($directory, 0700);
    $data = asaSecretReadAll();
    $value = trim($value);
    if ($value === '') {
        unset($data[$key]);
    } else {
        $data[$key] = $value;
    }
    $tmp = $path . '.tmp.' . bin2hex(random_bytes(8));
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false || @file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
        @unlink($tmp);
        error_log('asaas secrets: não foi possível gravar o arquivo privado.');
        return false;
    }
    @chmod($tmp, 0600);
    if (!@rename($tmp, $path)) {
        @unlink($tmp);
        error_log('asaas secrets: não foi possível concluir a troca atômica do arquivo.');
        return false;
    }
    @chmod($path, 0600);
    return true;
}

/**
 * Lê o arquivo privado e migra um segredo legado de platform_settings quando
 * necessário. Depois de copiar com sucesso, remove o valor do banco.
 */
function asaSecretGetOrMigrate(PDO $db, string $key): string
{
    if (!asaSecretKeyAllowed($key)) {
        return '';
    }
    $stored = asaSecretGet($key);
    if ($stored !== '') {
        return $stored;
    }
    try {
        $stmt = $db->prepare('SELECT setting_value FROM platform_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $legacy = trim((string) ($stmt->fetchColumn() ?: ''));
        if ($legacy === '') {
            return '';
        }
        if (asaSecretWrite($key, $legacy)) {
            $clear = $db->prepare('UPDATE platform_settings SET setting_value = \'\', updated_at = NOW() WHERE setting_key = ? LIMIT 1');
            $clear->execute([$key]);
            return asaSecretGet($key);
        }
        return $legacy;
    } catch (Throwable $e) {
        error_log('asaas secrets: migração protegida falhou: ' . $e->getMessage());
        return '';
    }
}

/** Persiste o segredo fora do document root e mantém o banco sem o valor. */
function asaSecretPersist(PDO $db, string $key, string $value): bool
{
    if (!asaSecretKeyAllowed($key) || !asaSecretWrite($key, $value)) {
        return false;
    }
    try {
        $stmt = $db->prepare(
            'INSERT INTO platform_settings (setting_key, setting_value, updated_at) VALUES (?, \'\', NOW())
             ON DUPLICATE KEY UPDATE setting_value = \'\', updated_at = NOW()'
        );
        $stmt->execute([$key]);
        return true;
    } catch (Throwable $e) {
        error_log('asaas secrets: limpeza do legado falhou: ' . $e->getMessage());
        return false;
    }
}
