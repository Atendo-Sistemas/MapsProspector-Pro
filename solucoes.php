<?php
require_once __DIR__ . '/includes/seo-pages.php';
$pages = mapsProspectorSeoPages();
$groups = [];
foreach ($pages as $page) {
    $groups[$page['groupKey']]['label'] = $page['label'];
    $groups[$page['groupKey']]['pages'][] = $page;
}
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Soluções de prospecção | MapsProspector',
    'description' => 'Conteúdos sobre as funções de prospecção, gestão, planos, indicação e consulta de CNPJ do MapsProspector.',
    'url' => 'https://prospec.atendo.log.br/solucoes.php',
    'inLanguage' => 'pt-BR',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="index,follow">
    <title>Soluções de prospecção | MapsProspector</title>
    <meta name="description" content="Conheça as funções de prospecção, gestão, planos, indicação e consulta de CNPJ do MapsProspector.">
    <link rel="canonical" href="https://prospec.atendo.log.br/solucoes.php">
    <meta property="og:type" content="website">
    <meta property="og:title" content="Soluções de prospecção | MapsProspector">
    <meta property="og:description" content="Conteúdos sobre as funções disponíveis na plataforma MapsProspector.">
    <meta property="og:url" content="https://prospec.atendo.log.br/solucoes.php">
    <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <style>body{font-family:Inter,system-ui,sans-serif}</style>
</head>
<body class="bg-slate-50 text-slate-700">
    <header class="border-b border-slate-200 bg-white">
        <div class="max-w-6xl mx-auto px-5 py-4 flex justify-between items-center">
            <a href="/" class="text-xl font-black text-blue-700">MapsProspector</a>
            <a href="/index.php?register=1" class="rounded-xl bg-blue-700 px-4 py-2 text-sm font-bold text-white">Criar conta</a>
        </div>
    </header>
    <main>
        <section class="bg-slate-950 text-white">
            <div class="max-w-6xl mx-auto px-5 py-16">
                <p class="font-bold text-blue-200">Soluções MapsProspector</p>
                <h1 class="mt-3 max-w-4xl text-4xl md:text-5xl font-black">Recursos para organizar sua rotina de prospecção</h1>
                <p class="mt-5 max-w-3xl text-lg text-slate-200">Explore conteúdos sobre as funções disponíveis na plataforma, seus usos operacionais e limites importantes.</p>
            </div>
        </section>
        <div class="max-w-6xl mx-auto px-5 py-12 space-y-12">
            <?php foreach ($groups as $group): ?>
                <section>
                    <h2 class="text-2xl font-black text-slate-900"><?= htmlspecialchars($group['label'], ENT_QUOTES, 'UTF-8') ?></h2>
                    <div class="mt-5 grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        <?php foreach ($group['pages'] as $item): ?>
                            <a href="/<?= htmlspecialchars($item['slug'], ENT_QUOTES, 'UTF-8') ?>.php" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-400">
                                <h3 class="font-black text-slate-900"><?= htmlspecialchars($item['title'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <p class="mt-2 text-sm leading-relaxed"><?= htmlspecialchars($item['focus'], ENT_QUOTES, 'UTF-8') ?></p>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    </main>
    <footer class="bg-slate-950 text-slate-300"><div class="max-w-6xl mx-auto px-5 py-8 text-sm">© <?= date('Y') ?> MapsProspector · Atendo Sistemas</div></footer>
</body>
</html>
