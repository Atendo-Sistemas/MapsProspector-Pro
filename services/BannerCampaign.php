<?php

declare(strict_types=1);

function bannerCampaignValidate(array $input): array
{
    foreach (['image', 'link', 'description', 'start', 'end'] as $field) {
        if (isset($input[$field]) && !is_string($input[$field])) {
            throw new InvalidArgumentException('Configuração de banner inválida.');
        }
    }
    $image = trim((string) ($input['image'] ?? ''));
    $link = trim((string) ($input['link'] ?? ''));
    $description = trim((string) ($input['description'] ?? ''));
    $start = trim((string) ($input['start'] ?? ''));
    $end = trim((string) ($input['end'] ?? ''));
    $pages = $input['pages'] ?? [];
    $enabled = $input['enabled'] ?? false;
    if (!is_bool($enabled) || !is_array($pages) || count($pages) > 5) {
        throw new InvalidArgumentException('Configuração de banner inválida.');
    }
    foreach ($pages as $page) {
        if (!is_string($page) || !in_array($page, ['maps', 'linkedin', 'instagram', 'cnpj', 'history'], true)) {
            throw new InvalidArgumentException('Página de exibição inválida.');
        }
    }
    if (count($pages) !== count(array_unique($pages))) {
        throw new InvalidArgumentException('Página de exibição duplicada.');
    }
    foreach ([$image, $link] as $url) {
        if ($url !== '' && (strlen($url) > 2048 || !filter_var($url, FILTER_VALIDATE_URL) || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https')) {
            throw new InvalidArgumentException('Informe URLs HTTPS válidas para a imagem e o destino.');
        }
    }
    foreach ([$start, $end] as $date) {
        if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4)))) {
            throw new InvalidArgumentException('Informe datas válidas.');
        }
    }
    if ($start !== '' && $end !== '' && $start > $end) {
        throw new InvalidArgumentException('A data final deve ser igual ou posterior à inicial.');
    }
    if (mb_strlen($description) > 160 || ($enabled && ($image === '' || $description === '' || !$pages))) {
        throw new InvalidArgumentException('Para ativar o banner, informe imagem, descrição de até 160 caracteres e ao menos uma página.');
    }
    return compact('enabled', 'image', 'link', 'description', 'start', 'end', 'pages');
}

function bannerCampaignVisible(array $campaign, string $page, string $today): bool
{
    return !empty($campaign['enabled'])
        && in_array($page, $campaign['pages'], true)
        && ($campaign['start'] === '' || $campaign['start'] <= $today)
        && ($campaign['end'] === '' || $campaign['end'] >= $today);
}
