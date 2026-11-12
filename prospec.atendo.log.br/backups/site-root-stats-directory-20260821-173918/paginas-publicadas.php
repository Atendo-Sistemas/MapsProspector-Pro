<?php
/**
 * Diretório público de páginas publicadas.
 * A página não é adicionada ao sitemap nem à navegação principal.
 */

$siteName = 'MapsProspector';
$siteUrl = 'https://prospec.atendo.log.br';
$directoryUrl = '/paginas-publicadas.php';

$pageMeta = [
    '/' => ['title' => 'Página inicial', 'category' => 'Institucional', 'description' => 'Conheça a plataforma de prospecção comercial e seus principais recursos.'],
    'solucoes.php' => ['title' => 'Soluções de prospecção', 'category' => 'Institucional', 'description' => 'Veja como a plataforma apoia pesquisa, organização e acompanhamento comercial.'],
    'termos.php' => ['title' => 'Termos de uso', 'category' => 'Institucional', 'description' => 'Consulte as condições de uso da plataforma.'],
    'privacidade.php' => ['title' => 'Política de privacidade', 'category' => 'Institucional', 'description' => 'Entenda como os dados são tratados e protegidos.'],
    'contato.php' => ['title' => 'Entre em contato', 'category' => 'Institucional', 'description' => 'Encontre os canais de contato da plataforma.'],
    'prospeccao-google-maps.php' => ['title' => 'Prospecção no Google Maps', 'category' => 'Pesquisa comercial', 'description' => 'Encontre empresas por segmento e localização.'],
    'buscar-empresas-no-google-maps.php' => ['title' => 'Buscar empresas no Google Maps', 'category' => 'Pesquisa comercial', 'description' => 'Organize buscas por segmento, cidade e região.'],
    'encontrar-leads-locais.php' => ['title' => 'Encontrar leads locais', 'category' => 'Pesquisa comercial', 'description' => 'Descubra empresas próximas ao público que você deseja alcançar.'],
    'filtros-de-segmento-e-localizacao.php' => ['title' => 'Filtros de segmento e localização', 'category' => 'Pesquisa comercial', 'description' => 'Refine sua prospecção com critérios mais precisos.'],
    'novos-leads-google-maps.php' => ['title' => 'Novos leads do Google Maps', 'category' => 'Pesquisa comercial', 'description' => 'Acompanhe oportunidades novas encontradas nas pesquisas.'],
    'prospeccao-linkedin.php' => ['title' => 'Prospecção no LinkedIn', 'category' => 'Pesquisa comercial', 'description' => 'Conheça o fluxo de pesquisa de perfis profissionais.'],
    'buscar-leads-no-linkedin.php' => ['title' => 'Buscar leads no LinkedIn', 'category' => 'Pesquisa comercial', 'description' => 'Pesquise perfis por cargo, localidade e critérios comerciais.'],
    'filtros-de-cargos-no-linkedin.php' => ['title' => 'Filtros de cargos no LinkedIn', 'category' => 'Pesquisa comercial', 'description' => 'Use funções e cargos para encontrar perfis mais relevantes.'],
    'pesquisa-autenticada-linkedin.php' => ['title' => 'Pesquisa autenticada no LinkedIn', 'category' => 'Pesquisa comercial', 'description' => 'Entenda as opções disponíveis para pesquisas autenticadas.'],
    'novos-leads-linkedin.php' => ['title' => 'Novos leads do LinkedIn', 'category' => 'Pesquisa comercial', 'description' => 'Veja como acompanhar perfis inéditos encontrados nas pesquisas.'],
    'gestao-de-leads.php' => ['title' => 'Gestão de leads', 'category' => 'Gestão comercial', 'description' => 'Centralize os resultados e acompanhe o trabalho comercial.'],
    'organizar-leads-comerciais.php' => ['title' => 'Organizar leads comerciais', 'category' => 'Gestão comercial', 'description' => 'Transforme resultados de pesquisa em uma rotina organizada.'],
    'historico-de-prospeccao.php' => ['title' => 'Histórico de prospecção', 'category' => 'Gestão comercial', 'description' => 'Consulte pesquisas e acompanhe a evolução do trabalho.'],
    'minha-equipe-prospeccao.php' => ['title' => 'Minha equipe de prospecção', 'category' => 'Gestão comercial', 'description' => 'Estruture acessos e responsabilidades da equipe.'],
    'dashboard-comercial.php' => ['title' => 'Dashboard comercial', 'category' => 'Gestão comercial', 'description' => 'Visualize indicadores para orientar suas próximas ações.'],
    'planos-de-prospeccao.php' => ['title' => 'Planos de prospecção', 'category' => 'Planos e recursos', 'description' => 'Compare os recursos e limites disponíveis.'],
    'assinatura-mensal.php' => ['title' => 'Assinatura mensal', 'category' => 'Planos e recursos', 'description' => 'Conheça o funcionamento da assinatura mensal.'],
    'cancelar-assinatura.php' => ['title' => 'Cancelar assinatura', 'category' => 'Planos e recursos', 'description' => 'Consulte como solicitar o cancelamento do plano.'],
    'plano-gratuito.php' => ['title' => 'Plano gratuito', 'category' => 'Planos e recursos', 'description' => 'Veja como começar com os recursos gratuitos.'],
    'como-funcionam-creditos-de-busca.php' => ['title' => 'Como funcionam os créditos de busca', 'category' => 'Planos e recursos', 'description' => 'Entenda os limites e o consumo das pesquisas.'],
    'programa-de-indicacao.php' => ['title' => 'Programa de indicação', 'category' => 'Relacionamento', 'description' => 'Conheça as regras e vantagens do programa de indicação.'],
    'link-de-indicacao.php' => ['title' => 'Seu link de indicação', 'category' => 'Relacionamento', 'description' => 'Acesse e compartilhe seu link de indicação.'],
    'acompanhar-indicacoes.php' => ['title' => 'Acompanhar indicações', 'category' => 'Relacionamento', 'description' => 'Consulte o andamento das suas indicações.'],
    'comissao-de-indicacao.php' => ['title' => 'Comissão de indicação', 'category' => 'Relacionamento', 'description' => 'Entenda como funcionam as comissões.'],
    'solicitar-saque-de-comissao.php' => ['title' => 'Solicitar saque de comissão', 'category' => 'Relacionamento', 'description' => 'Veja como solicitar o recebimento de comissões.'],
    'consulta-cnpj.php' => ['title' => 'Consulta de CNPJ', 'category' => 'Dados empresariais', 'description' => 'Consulte informações cadastrais de empresas.'],
    'dados-cadastrais-de-empresas.php' => ['title' => 'Dados cadastrais de empresas', 'category' => 'Dados empresariais', 'description' => 'Entenda quais informações cadastrais podem ser consultadas.'],
    'quadro-de-socios-cnpj.php' => ['title' => 'Quadro de sócios por CNPJ', 'category' => 'Dados empresariais', 'description' => 'Consulte o quadro societário quando disponível na fonte.'],
    'enriquecer-leads-com-cnpj.php' => ['title' => 'Enriquecer leads com CNPJ', 'category' => 'Dados empresariais', 'description' => 'Adicione informações cadastrais aos seus leads.'],
    'telefones-e-emails-de-empresas.php' => ['title' => 'Telefones e e-mails de empresas', 'category' => 'Dados empresariais', 'description' => 'Conheça os recursos de enriquecimento de contatos.'],
];

function directoryEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function directorySlugTitle(string $filename): string
{
    $name = preg_replace('/\.php$/i', '', $filename) ?: $filename;
    return ucwords(str_replace('-', ' ', $name));
}

$pages = [];
$sitemapPath = __DIR__ . '/sitemap.xml';
if (is_file($sitemapPath) && class_exists('SimpleXMLElement')) {
    $xml = @simplexml_load_file($sitemapPath);
    if ($xml !== false) {
        $sitemapNamespace = 'http://www.sitemaps.org/schemas/sitemap/0.9';
        $urlNodes = isset($xml->url) ? $xml->url : $xml->children($sitemapNamespace)->url;
        foreach ($urlNodes as $urlNode) {
            $loc = trim((string) ($urlNode->loc ?? ''));
            if ($loc === '') {
                continue;
            }
            $path = parse_url($loc, PHP_URL_PATH) ?: '/';
            $filename = basename($path) ?: '/';
            if ($filename === basename($directoryUrl) || $filename === 'sitemap.xml') {
                continue;
            }
            $meta = $pageMeta[$filename] ?? [
                'title' => directorySlugTitle($filename),
                'category' => 'Outros recursos',
                'description' => 'Explore este recurso publicado na plataforma.',
            ];
            $pages[] = [
                'url' => $loc,
                'filename' => $filename,
                'title' => $meta['title'],
                'category' => $meta['category'],
                'description' => $meta['description'],
            ];
        }
    }
}

$categoryOrder = ['Pesquisa comercial', 'Gestão comercial', 'Dados empresariais', 'Planos e recursos', 'Relacionamento', 'Institucional', 'Outros recursos'];
usort($pages, static function (array $a, array $b) use ($categoryOrder): int {
    $categoryA = array_search($a['category'], $categoryOrder, true);
    $categoryB = array_search($b['category'], $categoryOrder, true);
    $categoryA = $categoryA === false ? 99 : $categoryA;
    $categoryB = $categoryB === false ? 99 : $categoryB;
    return $categoryA === $categoryB
        ? strcasecmp($a['title'], $b['title'])
        : $categoryA <=> $categoryB;
});

$groupedPages = [];
foreach ($pages as $page) {
    $groupedPages[$page['category']][] = $page;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Explore as páginas da plataforma | <?php echo directoryEscape($siteName); ?></title>
    <meta name="description" content="Explore os recursos, soluções e informações públicas da plataforma de prospecção comercial.">
    <meta name="robots" content="noindex,follow">
    <link rel="canonical" href="<?php echo directoryEscape($siteUrl . $directoryUrl); ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        html { scroll-behavior: smooth; }
        .directory-card { transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
        .directory-card:hover { transform: translateY(-3px); box-shadow: 0 18px 35px rgba(15, 23, 42, .10); border-color: #93c5fd; }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
    <header class="border-b border-slate-200 bg-white/90 backdrop-blur">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-5 sm:px-6 lg:px-8">
            <a href="/" class="text-lg font-black tracking-tight text-blue-700">MapsProspector</a>
            <a href="/" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-bold text-slate-600 transition hover:border-blue-300 hover:text-blue-700">Voltar para o início</a>
        </div>
    </header>

    <main>
        <section class="bg-gradient-to-br from-slate-950 via-blue-950 to-blue-700 px-4 py-20 text-white sm:px-6 lg:px-8">
            <div class="mx-auto max-w-4xl text-center">
                <span class="mb-5 inline-flex rounded-full border border-blue-300/30 bg-white/10 px-4 py-2 text-xs font-extrabold uppercase tracking-[.18em] text-blue-100">Diretório da plataforma</span>
                <h1 class="text-4xl font-black tracking-tight sm:text-5xl">Explore os recursos do MapsProspector</h1>
                <p class="mx-auto mt-5 max-w-2xl text-lg leading-relaxed text-blue-100">Encontre rapidamente soluções, guias e informações para organizar sua prospecção comercial com mais clareza.</p>
                <div class="mt-8 flex flex-wrap justify-center gap-3 text-sm font-bold text-blue-50">
                    <span class="rounded-full bg-white/10 px-4 py-2"><?php echo count($pages); ?> páginas públicas</span>
                    <span class="rounded-full bg-white/10 px-4 py-2">Conteúdo organizado por tema</span>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
            <?php if (empty($groupedPages)): ?>
                <div class="rounded-3xl border border-slate-200 bg-white p-10 text-center text-slate-500">Nenhuma página publicada foi encontrada no sitemap.</div>
            <?php else: ?>
                <?php foreach ($groupedPages as $category => $categoryPages): ?>
                    <div class="mb-14 last:mb-0">
                        <div class="mb-6 flex items-end justify-between gap-4">
                            <div>
                                <p class="text-xs font-black uppercase tracking-[.18em] text-blue-600">Coleção de recursos</p>
                                <h2 class="mt-2 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl"><?php echo directoryEscape($category); ?></h2>
                            </div>
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-500"><?php echo count($categoryPages); ?> páginas</span>
                        </div>
                        <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                            <?php foreach ($categoryPages as $page): ?>
                                <a class="directory-card group flex min-h-[190px] flex-col rounded-2xl border border-slate-200 bg-white p-6" href="<?php echo directoryEscape($page['url']); ?>">
                                    <div class="mb-5 flex items-center justify-between">
                                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                        </span>
                                        <svg class="h-5 w-5 text-slate-300 transition group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 17 17 7m0 0H7m10 0v10" /></svg>
                                    </div>
                                    <h3 class="text-lg font-extrabold text-slate-950 group-hover:text-blue-700"><?php echo directoryEscape($page['title']); ?></h3>
                                    <p class="mt-2 flex-1 text-sm leading-relaxed text-slate-500"><?php echo directoryEscape($page['description']); ?></p>
                                    <span class="mt-5 text-xs font-black uppercase tracking-wider text-blue-600">Acessar página</span>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>

        <section class="bg-white px-4 py-16 sm:px-6 lg:px-8">
            <div class="mx-auto flex max-w-5xl flex-col items-center justify-between gap-6 rounded-3xl bg-blue-50 p-8 text-center sm:flex-row sm:text-left sm:p-10">
                <div>
                    <p class="text-xs font-black uppercase tracking-[.18em] text-blue-700">Pronto para começar?</p>
                    <h2 class="mt-2 text-2xl font-black text-slate-950">Leve sua prospecção para um fluxo mais organizado.</h2>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600">Crie seu acesso e conheça os recursos disponíveis para sua rotina comercial.</p>
                </div>
                <a href="/index.php?register=1" class="shrink-0 rounded-xl bg-blue-700 px-6 py-3 text-sm font-black text-white shadow-lg shadow-blue-700/20 transition hover:bg-blue-800">Criar acesso gratuito</a>
            </div>
        </section>
    </main>

    <footer class="bg-slate-950 px-4 py-10 text-slate-400 sm:px-6 lg:px-8">
        <div class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-4 text-center sm:flex-row sm:text-left">
            <div>
                <div class="text-lg font-black text-white">MapsProspector</div>
                <p class="mt-1 text-xs">Prospecção comercial com mais clareza e organização.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-x-5 gap-y-2 text-sm">
                <a href="/termos.php" class="transition hover:text-white">Termos</a>
                <a href="/privacidade.php" class="transition hover:text-white">Privacidade</a>
                <a href="/contato.php" class="transition hover:text-white">Contato</a>
                <a href="/" class="font-bold text-blue-300 transition hover:text-white">Início</a>
            </div>
        </div>
    </footer>
</body>
</html>
