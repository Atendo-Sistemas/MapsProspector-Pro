<?php

declare(strict_types=1);
require_once __DIR__ . '/../services/BannerCampaign.php';

$campaign = bannerCampaignValidate([
    'enabled' => true,
    'image' => 'https://example.com/banner.png',
    'link' => 'https://example.com/',
    'description' => 'Campanha de teste',
    'pages' => ['maps', 'instagram', 'history'],
    'start' => '2026-11-12',
    'end' => '2026-11-15',
]);

foreach ([
    ['maps', '2026-11-11', false],
    ['maps', '2026-11-12', true],
    ['history', '2026-11-15', true],
    ['instagram', '2026-11-13', true],
    ['history', '2026-11-16', false],
    ['linkedin', '2026-11-13', false],
] as [$page, $day, $expected]) {
    if (bannerCampaignVisible($campaign, $page, $day) !== $expected) {
        throw new RuntimeException("Visibilidade incorreta: {$page}, {$day}");
    }
}
$campaign['enabled'] = false;
if (bannerCampaignVisible($campaign, 'maps', '2026-11-12')) {
    throw new RuntimeException('Campanha desativada está visível.');
}

foreach ([
    ['enabled' => true],
    ['image' => 'javascript:alert(1)'],
    ['image' => 'http://example.com/banner.png'],
    ['start' => '2026-02-30'],
    ['start' => '2026-11-16', 'end' => '2026-11-15'],
    ['pages' => ['maps', 'other']],
] as $invalid) {
    try {
        bannerCampaignValidate($invalid);
        throw new RuntimeException('Configuração inválida foi aceita.');
    } catch (InvalidArgumentException $expected) {
    }
}
echo "Testes de banner concluídos.\n";
