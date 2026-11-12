<?php
declare(strict_types=1);
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: SAMEORIGIN');
}
require_once __DIR__ . '/includes_seo-clean-pages.php';
$pages = mapsProspectorCleanSeoPages();
$seoPageKey = isset($seoPageKey) ? (string) $seoPageKey : '';
$page = $pages[$seoPageKey] ?? null;
if (!$page) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    exit('Página não encontrada.');
}
$baseUrl = 'https://prospec.atendo.log.br';
$canonicalSlug = trim($seoPageKey, '/');
$canonical = $baseUrl . '/' . rawurlencode($canonicalSlug) . '/';
$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$related = [];
foreach (($page['related'] ?? []) as $slug) {
    if (isset($pages[$slug])) {
        $relatedItem = $pages[$slug];
        $relatedItem['slug'] = $slug;
        $related[] = $relatedItem;
    }
}
$pricingPlans = $page['plans'] ?? [];
if ($seoPageKey === 'precos') {
    $pricingFallback = $pricingPlans;
    try {
        ob_start();
        require_once __DIR__ . '/../config/config.php';
        require_once __DIR__ . '/../config/database.php';
        $db = Database::getInstance()->getConnection();
        $moduleLabels = [
            'maps' => 'Prospecção no Google Maps',
            'linkedin' => 'Prospecção no LinkedIn',
            'cnpj' => 'Consulta e enriquecimento CNPJ',
            'export' => 'Exportações',
            'crm' => 'CRM comercial',
        ];
        $catalogStmt = $db->query("SELECT p.id, p.name, p.slug, p.token_limit, p.price_monthly, COALESCE(p.included_users, 1) AS included_users, COALESCE(p.catalog_group, 'current') AS catalog_group, COALESCE(p.addon_user_price_cents, 0) AS addon_user_price_cents FROM plans p WHERE p.status = 'active' ORDER BY p.token_limit ASC, p.name ASC");
        $catalogRows = $catalogStmt->fetchAll(PDO::FETCH_ASSOC);
        $moduleStmt = $db->prepare('SELECT module_key FROM plan_modules WHERE plan_id = ? AND is_active = 1 ORDER BY module_key ASC');
        $pricingPlans = [];
        foreach ($catalogRows as $row) {
            $isCrmAddon = $row['catalog_group'] === 'crm_addon';
            $price = (float) ($row['price_monthly'] ?? 0);
            $priceLabel = $price > 0 ? 'R$ ' . number_format($price, 2, ',', '.') . '/mês' : 'Grátis';
            $includedUsers = max(1, (int) ($row['included_users'] ?? 1));
            if ($isCrmAddon) {
                $details = 'Licença adicional · CRM sem consumo de créditos · ' . $includedUsers . ' usuários incluídos';
                $addonPrice = (int) ($row['addon_user_price_cents'] ?? 0);
                if ($addonPrice > 0) $details .= ' · usuário excedente informativo de R$ ' . number_format($addonPrice / 100, 2, ',', '.') . '/mês';
            } else {
                $moduleStmt->execute([(int) $row['id']]);
                $modules = array_values(array_filter(array_map('strval', $moduleStmt->fetchAll(PDO::FETCH_COLUMN))));
                $labels = array_values(array_filter(array_map(static fn(string $module): string => $moduleLabels[$module] ?? $module, $modules)));
                $detailsParts = [];
                $credits = max(0, (int) ($row['token_limit'] ?? 0));
                if ($credits > 0) $detailsParts[] = number_format($credits, 0, ',', '.') . ' créditos';
                if ($labels) $detailsParts[] = implode(', ', $labels);
                if (($row['slug'] ?? '') !== 'trial' && $price > 0) $detailsParts[] = '+50 créditos de bônus na contratação';
                $detailsParts[] = $includedUsers . ' usuário' . ($includedUsers === 1 ? '' : 's') . ' incluído' . ($includedUsers === 1 ? '' : 's');
                $details = implode(' · ', $detailsParts);
            }
            $pricingPlans[] = ['name' => (string) $row['name'], 'price' => $priceLabel, 'details' => $details];
        }
        if (!$pricingPlans) $pricingPlans = $pricingFallback;
        ob_end_clean();
    } catch (Throwable $pricingError) {
        if (ob_get_level() > 0) ob_end_clean();
        $pricingPlans = $pricingFallback;
    }
}
$faqEntities = [];
foreach (($page['faq'] ?? []) as $question => $answer) {
    $faqEntities[] = ['@type' => 'Question', 'name' => $question, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $answer]];
}
$jsonLd = [
    '@context' => 'https://schema.org',
    '@graph' => [
        ['@type' => 'WebPage', 'name' => $page['title'] . ' | MapsProspector', 'description' => $page['description'], 'url' => $canonical, 'inLanguage' => 'pt-BR', 'isPartOf' => ['@type' => 'WebSite', 'name' => 'MapsProspector', 'url' => $baseUrl . '/']],
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => $baseUrl . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $page['section'], 'item' => $baseUrl . '/recursos/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $page['title'], 'item' => $canonical],
        ]],
        ['@type' => 'FAQPage', 'mainEntity' => $faqEntities],
    ],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="index,follow,max-image-preview:large">
    <title><?= $e($page['title']) ?> | MapsProspector</title>
    <meta name="description" content="<?= $e($page['description']) ?>">
    <link rel="canonical" href="<?= $e($canonical) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="MapsProspector">
    <meta property="og:title" content="<?= $e($page['title']) ?> | MapsProspector">
    <meta property="og:description" content="<?= $e($page['description']) ?>">
    <meta property="og:url" content="<?= $e($canonical) ?>">
    <meta property="og:locale" content="pt_BR">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= $e($page['title']) ?> | MapsProspector">
    <meta name="twitter:description" content="<?= $e($page['description']) ?>">
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <style>
        :root{color-scheme:light}*{box-sizing:border-box}body{margin:0;background:#f8fafc;color:#334155;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}a{color:inherit}.wrap{width:min(1120px,calc(100% - 40px));margin-inline:auto}.topbar{position:sticky;top:0;z-index:10;border-bottom:1px solid #e2e8f0;background:rgba(255,255,255,.96);backdrop-filter:blur(10px)}.topbar-inner{display:flex;align-items:center;justify-content:space-between;gap:20px;padding:16px 0}.brand{font-size:1.25rem;font-weight:900;letter-spacing:-.04em;color:#1d4ed8;text-decoration:none}.nav{display:flex;align-items:center;flex-wrap:wrap;gap:18px;font-size:.9rem;font-weight:700;color:#475569}.nav a{text-decoration:none}.nav a:hover{color:#1d4ed8}.button{display:inline-flex;align-items:center;justify-content:center;border-radius:12px;padding:12px 18px;background:#1d4ed8;color:#fff;text-decoration:none;font-weight:800;box-shadow:0 8px 20px rgba(29,78,216,.18)}.button.light{background:#fff;color:#1e40af;border:1px solid #bfdbfe;box-shadow:none}.hero{background:linear-gradient(135deg,#020617 0%,#172554 54%,#2563eb 100%);color:#fff}.hero-inner{padding:76px 0 82px}.eyebrow{font-size:.78rem;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#bfdbfe}.hero h1{max-width:880px;margin:18px 0 0;font-size:clamp(2.35rem,6vw,4.7rem);line-height:1.03;letter-spacing:-.055em}.hero p{max-width:780px;margin:22px 0 0;color:#dbeafe;font-size:1.18rem;line-height:1.7}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:30px}.crumbs{padding:20px 0;color:#64748b;font-size:.9rem}.crumbs a{color:#1d4ed8;text-decoration:none;font-weight:700}.article{padding:10px 0 70px}.article h2,.faq h2,.related h2{margin:42px 0 14px;color:#0f172a;font-size:clamp(1.45rem,3vw,2rem);line-height:1.2;letter-spacing:-.03em}.article>p,.article li{max-width:820px;font-size:1.06rem;line-height:1.85}.section{max-width:820px;padding-top:12px}.section p{margin:0 0 16px;font-size:1.06rem;line-height:1.85}.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-top:24px}.card{padding:22px;border:1px solid #e2e8f0;border-radius:18px;background:#fff;box-shadow:0 8px 25px rgba(15,23,42,.04)}.card h3{margin:0;color:#0f172a;font-size:1.08rem}.card p{margin:10px 0 0;line-height:1.65}.faq{border-block:1px solid #e2e8f0;background:#fff;padding:28px 0 56px}.faq details{margin-top:12px;padding:18px 20px;border:1px solid #e2e8f0;border-radius:16px;background:#fff}.faq summary{cursor:pointer;color:#0f172a;font-weight:800}.faq details p{margin:12px 0 0;line-height:1.7}.related{padding:18px 0 62px}.related .card{display:block;text-decoration:none}.related .card:hover{border-color:#60a5fa}.cta{background:#1d4ed8;color:#fff}.cta-inner{display:flex;align-items:center;justify-content:space-between;gap:28px;padding:44px 0}.cta h2{margin:0;font-size:1.7rem}.cta p{margin:8px 0 0;color:#dbeafe}.cta .button{background:#fff;color:#1e40af;box-shadow:none}.footer{background:#020617;color:#cbd5e1}.footer-inner{display:flex;justify-content:space-between;gap:20px;flex-wrap:wrap;padding:32px 0;font-size:.9rem}.footer a{color:#bfdbfe;text-decoration:none;font-weight:700}@media(max-width:720px){.topbar-inner{align-items:flex-start;flex-direction:column}.nav{gap:12px}.hero-inner{padding:54px 0 60px}.cta-inner{align-items:flex-start;flex-direction:column}.wrap{width:min(100% - 28px,1120px)}}
    </style>
</head>
<body>
<header class="topbar"><div class="wrap topbar-inner"><a class="brand" href="/">MapsProspector</a><nav class="nav" aria-label="Navegação principal"><a href="/recursos/">Recursos</a><a href="/prospeccao-b2b/">Prospecção B2B</a><a href="/precos/">Preços</a><a href="/contato.php">Contato</a><a class="button" href="/app/">Entrar</a></nav></div></header>
<main>
<section class="hero"><div class="wrap hero-inner"><div class="eyebrow"><?= $e($page['section']) ?></div><h1><?= $e($page['title']) ?></h1><p><?= $e($page['intro']) ?></p><div class="actions"><a class="button light" href="/app/?register=1">Criar acesso gratuito</a><a class="button" href="#conteudo">Entender o funcionamento</a></div></div></section>
<nav class="wrap crumbs" aria-label="Trilha de navegação"><a href="/">Início</a><span aria-hidden="true"> / </span><a href="/recursos/">Recursos</a><span aria-hidden="true"> / </span><span><?= $e($page['title']) ?></span></nav>
<article id="conteudo" class="wrap article"><p><?= $e($page['intro']) ?></p>
<?php foreach (($page['sections'] ?? []) as $section): ?><section class="section"><h2><?= $e($section['heading']) ?></h2><?php foreach (($section['paragraphs'] ?? []) as $paragraph): ?><p><?= $e($paragraph) ?></p><?php endforeach; ?></section><?php endforeach; ?>
<?php if (!empty($page['plans'])): ?><section class="section"><h2>Opções atuais</h2><div class="cards"><?php foreach ($page['plans'] as $plan): ?><div class="card"><h3><?= $e($plan['name']) ?></h3><p><strong><?= $e($plan['price']) ?></strong></p><p><?= $e($plan['details']) ?></p></div><?php endforeach; ?></div></section><?php endif; ?></article>
<section class="faq"><div class="wrap"><h2>Perguntas frequentes</h2><?php foreach (($page['faq'] ?? []) as $question => $answer): ?><details><summary><?= $e($question) ?></summary><p><?= $e($answer) ?></p></details><?php endforeach; ?></div></section>
<section class="related"><div class="wrap"><h2>Continue a leitura</h2><div class="cards"><?php foreach ($related as $item): ?><a class="card" href="/<?= $e($item['slug']) ?>/" ><h3><?= $e($item['title']) ?></h3><p><?= $e($item['description']) ?></p></a><?php endforeach; ?></div></div></section>
<section class="cta"><div class="wrap cta-inner"><div><h2>Pronto para organizar sua prospecção?</h2><p>Conheça os recursos do MapsProspector e comece com um fluxo adequado ao seu processo comercial.</p></div><a class="button" href="/app/?register=1">Criar acesso gratuito</a></div></section>
</main>
<footer class="footer"><div class="wrap footer-inner"><span>© <?= date('Y') ?> MapsProspector. Uma plataforma da Atendo Sistemas.</span><span><a href="/">Início</a> · <a href="/termos.php">Termos</a> · <a href="/privacidade.php">Privacidade</a> · <a href="/contato.php">Contato</a></span></div></footer>
<script src="/assets/js/site-traffic.js?v=20261009-traffic-1" defer></script></body></html>
