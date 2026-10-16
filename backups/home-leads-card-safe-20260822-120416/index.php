<?php
declare(strict_types=1);
if (isset($_GET['register'])) {
    header('Location: /app/?register=1', true, 302);
    exit;
}
if (!headers_sent()) {
    header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
    header('X-Robots-Tag: index, follow');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: SAMEORIGIN');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MapsProspector | Prospecção B2B e organização de leads</title>
    <meta name="description" content="Conheça os recursos do MapsProspector para prospecção B2B no Google Maps, pesquisa no LinkedIn autenticado, consulta de CNPJ e organização de leads.">
    <meta name="robots" content="index,follow">
    <link rel="canonical" href="https://prospec.atendo.log.br/">
    <meta property="og:type" content="website">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:site_name" content="MapsProspector">
    <meta property="og:title" content="MapsProspector | Prospecção B2B e organização de leads">
    <meta property="og:description" content="Recursos para pesquisar empresas, consultar CNPJ e organizar o processo comercial.">
    <meta property="og:url" content="https://prospec.atendo.log.br/">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="MapsProspector | Prospecção B2B e organização de leads">
    <meta name="twitter:description" content="Recursos para pesquisar empresas, consultar CNPJ e organizar o processo comercial.">
    <script type="application/ld+json">{"@context":"https://schema.org","@type":"SoftwareApplication","name":"MapsProspector","applicationCategory":"BusinessApplication","operatingSystem":"Web","inLanguage":"pt-BR","url":"https://prospec.atendo.log.br/","description":"Plataforma com recursos para prospecção B2B, consulta de CNPJ e organização de leads."}</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        blue: { 50: '#eff6ff', 100: '#dbeafe', 200: '#bfdbfe', 300: '#93c5fd', 400: '#60a5fa', 500: '#3b82f6', 600: '#2563eb', 700: '#1d4ed8', 800: '#1e40af', 900: '#1e3a8a' }
                    }
                }
            }
        }
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
        html { scroll-behavior: smooth; }
        .animate-float { animation: float 3s ease-in-out infinite; }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-10px); } }
        .gradient-bg { background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 50%, #3b82f6 100%); }
    </style>
</head>
<body class="bg-slate-50 text-slate-900">
    <!-- Navigation -->
    <nav class="fixed top-0 w-full bg-white/80 backdrop-blur-md border-b border-slate-200 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <a href="/" class="text-xl font-black text-blue-600">MapsProspector</a>
                </div>
                <div class="hidden md:flex items-center gap-8">
                    <a href="/recursos/" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition">Recursos</a>
                    <a href="/recursos/" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition">Soluções</a>
                    <a href="/geracao-de-leads/" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition">Como funciona</a>
                    <a href="/precos/" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition">Planos</a>
                    <a href="#faq" class="text-sm font-semibold text-slate-600 hover:text-blue-600 transition">FAQ</a>
                </div>
                <div class="flex items-center gap-3">
                    <a href="/app/" class="px-4 py-2 text-sm font-bold text-slate-600 hover:text-blue-600 transition">Entrar</a>
                    <a href="/app/?register=1" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-xl transition">Cadastrar</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="hero" class="pt-32 pb-20 px-4">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-4xl mx-auto">
                <span id="hero-badge" class="inline-block px-4 py-1.5 bg-blue-100 text-blue-700 text-xs font-bold rounded-full mb-6">Plataforma de prospecção B2B</span>
                <h1 id="hero-title" class="text-4xl md:text-5xl lg:text-6xl font-black text-slate-900 mb-6 leading-tight">Prospecção B2B com Google Maps, LinkedIn e dados de CNPJ</h1>
                <p id="hero-subtitle" class="text-lg md:text-xl text-slate-600 mb-8">Planeje buscas por segmento e localidade, acompanhe leads e organize o trabalho comercial em um único painel.</p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a id="hero-cta" href="/app/?register=1" class="px-8 py-4 bg-blue-600 hover:bg-blue-700 text-white text-lg font-bold rounded-2xl transition shadow-lg shadow-blue-600/25">Criar acesso gratuito</a>
                    <a href="/geracao-de-leads/" class="px-8 py-4 bg-white border-2 border-slate-200 hover:border-slate-300 text-slate-700 text-lg font-bold rounded-2xl transition">Ver como funciona</a>
                </div>
            </div>
            <div id="hero-content" class="mt-12 max-w-3xl mx-auto text-center text-slate-600"><p>O MapsProspector reúne recursos de prospecção para pesquisar empresas, consultar dados cadastrais quando a fonte configurada os disponibilizar e organizar a rotina da equipe. A disponibilidade e o retorno de cada pesquisa dependem da fonte conectada, dos filtros usados e das regras aplicáveis.</p></div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 px-4 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 id="features-title" class="text-3xl md:text-4xl font-black text-slate-900 mb-4">Recursos para organizar a prospecção</h2>
                <p id="features-subtitle" class="text-lg text-slate-600">Conheça as funções disponíveis e escolha a forma de pesquisa adequada ao seu processo comercial.</p>
            </div>
            <div id="features-grid" class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div class="bg-slate-50 rounded-2xl p-6"><h3 class="text-lg font-bold text-slate-900 mb-2">Prospecção no Google Maps</h3><p class="text-slate-600 text-sm">Pesquise empresas por segmento e localidade e trabalhe os resultados retornados pela fonte configurada.</p></div>
        <div class="bg-slate-50 rounded-2xl p-6"><h3 class="text-lg font-bold text-slate-900 mb-2">Pesquisa no LinkedIn</h3><p class="text-slate-600 text-sm">Consulte perfis por cargo e localidade quando a sessão autenticada estiver disponível e autorizada.</p></div>
        <div class="bg-slate-50 rounded-2xl p-6"><h3 class="text-lg font-bold text-slate-900 mb-2">Consulta de CNPJ</h3><p class="text-slate-600 text-sm">Visualize atributos cadastrais e contatos que a fonte configurada retornar para a empresa pesquisada.</p></div>
        <div class="bg-slate-50 rounded-2xl p-6"><h3 class="text-lg font-bold text-slate-900 mb-2">Organização de leads</h3><p class="text-slate-600 text-sm">Acompanhe resultados, histórico e dados de trabalho em um painel compartilhado conforme as permissões da equipe.</p></div>
        <div class="bg-slate-50 rounded-2xl p-6"><h3 class="text-lg font-bold text-slate-900 mb-2">Planos e créditos</h3><p class="text-slate-600 text-sm">Acompanhe os recursos do plano e os créditos aplicáveis às buscas contratadas.</p></div>
    </div>
        </div>
    </section>


    <!-- Rotas SEO prioritárias: conteúdo HTML rastreável e navegação pública sem carregar o dashboard. -->
    <section id="seo-priority-links" class="py-16 px-4 bg-slate-100">
        <div class="max-w-6xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-10">
                <h2 class="text-2xl md:text-3xl font-black text-slate-900 mb-3">Explore as soluções por objetivo</h2>
                <p class="text-base text-slate-600">Acesse informações específicas sobre cada recurso de prospecção e organização comercial.</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a href="/prospeccao-b2b/" class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm hover:border-blue-300 hover:shadow-md transition"><strong class="block text-slate-900">Prospecção B2B</strong><span class="mt-1 block text-sm text-slate-600">Estratégias para encontrar empresas.</span></a>
                <a href="/prospeccao-google-maps/" class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm hover:border-blue-300 hover:shadow-md transition"><strong class="block text-slate-900">Google Maps</strong><span class="mt-1 block text-sm text-slate-600">Pesquisas por segmento e localidade.</span></a>
                <a href="/prospeccao-linkedin/" class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm hover:border-blue-300 hover:shadow-md transition"><strong class="block text-slate-900">LinkedIn</strong><span class="mt-1 block text-sm text-slate-600">Perfis profissionais conforme a fonte.</span></a>
                <a href="/geracao-de-leads/" class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm hover:border-blue-300 hover:shadow-md transition"><strong class="block text-slate-900">Geração de leads</strong><span class="mt-1 block text-sm text-slate-600">Organize o fluxo de prospecção.</span></a>
                <a href="/lista-de-empresas/" class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm hover:border-blue-300 hover:shadow-md transition"><strong class="block text-slate-900">Lista de empresas</strong><span class="mt-1 block text-sm text-slate-600">Consulte e organize oportunidades.</span></a>
                <a href="/pesquisa-cnpj/" class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm hover:border-blue-300 hover:shadow-md transition"><strong class="block text-slate-900">Pesquisa de CNPJ</strong><span class="mt-1 block text-sm text-slate-600">Confira dados cadastrais retornados.</span></a>
                <a href="/crm-comercial/" class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm hover:border-blue-300 hover:shadow-md transition"><strong class="block text-slate-900">CRM comercial</strong><span class="mt-1 block text-sm text-slate-600">Acompanhe e movimente seus leads.</span></a>
                <a href="/creditos/" class="rounded-2xl bg-white border border-slate-200 p-5 shadow-sm hover:border-blue-300 hover:shadow-md transition"><strong class="block text-slate-900">Créditos</strong><span class="mt-1 block text-sm text-slate-600">Entenda o uso conforme o plano.</span></a>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-20 px-4 bg-slate-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 id="how-title" class="text-3xl md:text-4xl font-black text-slate-900 mb-4">Como começar a prospectar</h2>
                <p id="how-subtitle" class="text-lg text-slate-600">Um fluxo simples para transformar critérios comerciais em uma lista de trabalho.</p>
            </div>
            <div class="max-w-4xl mx-auto">
                <div id="how-content" class="bg-white rounded-3xl p-8 md:p-12 shadow-xl">
        <ol class="space-y-4">
            <li><strong>1. Defina seu recorte.</strong> Escolha segmento, localidade e demais critérios coerentes com o público que deseja trabalhar.</li>
            <li><strong>2. Inicie a pesquisa disponível.</strong> Use Google Maps, LinkedIn autenticado ou consulta de CNPJ conforme a finalidade e a disponibilidade de cada fonte.</li>
            <li><strong>3. Revise e organize os resultados.</strong> Avalie os dados retornados, registre o andamento e distribua o trabalho para a equipe quando necessário.</li>
        </ol>
    </div>
            </div>
        </div>
    </section>

    <!-- Pricing / Plans -->
    <section id="pricing" class="py-20 px-4 bg-white">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 mb-4">Nossos Planos</h2>
                <p class="text-lg text-slate-600">Consulte as opções de acesso e os recursos disponíveis em cada plano.</p>
            </div>
            <div id="plans-grid" class="grid md:grid-cols-2 lg:grid-cols-4 gap-8 max-w-6xl mx-auto">

        <div class="bg-slate-50 rounded-2xl p-6 ring-2 ring-blue-500"><h3 class="text-xl font-black text-slate-900 mb-2">Plano gratuito</h3><div class="mb-4"><span class="text-3xl font-black text-slate-900">Grátis</span></div><p class="text-slate-600 text-sm mb-6">100 créditos · todos os módulos no teste</p><a href="/app/?register=1" class="block w-full py-3 text-center bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition">Começar gratuitamente</a></div>
        <div class="bg-slate-50 rounded-2xl p-6"><h3 class="text-xl font-black text-slate-900 mb-2">Plano Start</h3><div class="mb-4"><span class="text-3xl font-black text-slate-900">R$ 49,90</span><span class="text-slate-500">/mês</span></div><p class="text-slate-600 text-sm mb-6">1.500 créditos · Maps, LinkedIn, CNPJ e Exportação</p><a href="/precos/" class="block w-full py-3 text-center bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl transition">Ver detalhes</a></div>
        <div class="bg-slate-50 rounded-2xl p-6"><h3 class="text-xl font-black text-slate-900 mb-2">Plano Pro</h3><div class="mb-4"><span class="text-3xl font-black text-slate-900">R$ 97,90</span><span class="text-slate-500">/mês</span></div><p class="text-slate-600 text-sm mb-6">5.000 créditos · módulos completos de prospecção</p><a href="/precos/" class="block w-full py-3 text-center bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl transition">Ver detalhes</a></div>
        <div class="bg-slate-50 rounded-2xl p-6"><h3 class="text-xl font-black text-slate-900 mb-2">Plano Advanced</h3><div class="mb-4"><span class="text-3xl font-black text-slate-900">R$ 197,90</span><span class="text-slate-500">/mês</span></div><p class="text-slate-600 text-sm mb-6">15.000 créditos · módulos completos de prospecção</p><a href="/precos/" class="block w-full py-3 text-center bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl transition">Ver detalhes</a></div>
        <div class="bg-slate-50 rounded-2xl p-6"><h3 class="text-xl font-black text-slate-900 mb-2">Plano Ultimate</h3><div class="mb-4"><span class="text-3xl font-black text-slate-900">R$ 397,90</span><span class="text-slate-500">/mês</span></div><p class="text-slate-600 text-sm mb-6">40.000 créditos · módulos completos de prospecção</p><a href="/precos/" class="block w-full py-3 text-center bg-slate-900 hover:bg-slate-800 text-white font-bold rounded-xl transition">Ver detalhes</a></div>
                <!-- Plans will be loaded dynamically -->
            </div>
        </div>
    </section>

    <!-- Benefits -->
    <section id="benefits" class="py-20 px-4 bg-slate-50">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 id="benefits-title" class="text-3xl md:text-4xl font-black text-slate-900 mb-4">Um painel para o processo comercial</h2>
                <p id="benefits-subtitle" class="text-lg text-slate-600">Funções para apoiar a pesquisa, a organização e o acompanhamento de leads.</p>
            </div>
            <div id="benefits-grid" class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
        <div class="bg-white rounded-2xl p-6 shadow-sm"><h3 class="text-lg font-bold text-slate-900 mb-2">Critérios de pesquisa</h3><p class="text-slate-600 text-sm">Organize pesquisas por segmento, localização e outros filtros disponíveis em cada fonte.</p></div>
        <div class="bg-white rounded-2xl p-6 shadow-sm"><h3 class="text-lg font-bold text-slate-900 mb-2">Consulta orientada por fonte</h3><p class="text-slate-600 text-sm">Identifique de qual recurso veio cada retorno e revise os dados antes de utilizá-los comercialmente.</p></div>
        <div class="bg-white rounded-2xl p-6 shadow-sm"><h3 class="text-lg font-bold text-slate-900 mb-2">Histórico de trabalho</h3><p class="text-slate-600 text-sm">Mantenha o acompanhamento de pesquisas e leads dentro do ambiente da empresa.</p></div>
        <div class="bg-white rounded-2xl p-6 shadow-sm"><h3 class="text-lg font-bold text-slate-900 mb-2">Equipe com permissões</h3><p class="text-slate-600 text-sm">Estruture os acessos conforme o papel de cada integrante e a política da organização.</p></div>
    </div>
        </div>
    </section>

    <!-- Social proof / Platform stats -->
    <section id="platform-stats" class="py-16 px-4 bg-white">
        <div class="max-w-6xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-10">
                <span class="inline-flex px-4 py-1.5 bg-blue-50 text-blue-700 text-xs font-black uppercase tracking-wider rounded-full mb-4">Uma plataforma em crescimento</span>
                <h2 class="text-3xl md:text-4xl font-black text-slate-900 mb-4">Empresas e profissionais confiam no nosso sistema</h2>
                <p class="text-lg text-slate-600">Números atualizados automaticamente para mostrar o alcance real da plataforma.</p>
            </div>
            <div id="platform-stats-grid" class="grid gap-6 md:grid-cols-2 max-w-4xl mx-auto">
                <div class="rounded-3xl border border-blue-100 bg-blue-50 p-8 text-center shadow-sm">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-blue-600 text-white shadow-lg shadow-blue-600/20">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21h18M5 21V5a2 2 0 012-2h10a2 2 0 012 2v16M8 7h2m-2 4h2m4-4h2m-2 4h2M9 21v-4h6v4" /></svg>
                    </div>
                    <p id="platform-companies-count" class="text-4xl font-black text-slate-950">—</p>
                    <h3 class="mt-2 text-lg font-extrabold text-slate-900">Empresas cadastradas</h3>
                    <p class="mt-2 text-sm text-slate-600">Negócios que já fazem parte da plataforma.</p>
                </div>
                <div class="rounded-3xl border border-emerald-100 bg-emerald-50 p-8 text-center shadow-sm">
                    <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-600 text-white shadow-lg shadow-emerald-600/20">
                        <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                    </div>
                    <p id="platform-users-count" class="text-4xl font-black text-slate-950">—</p>
                    <h3 class="mt-2 text-lg font-extrabold text-slate-900">Usuários na plataforma</h3>
                    <p class="mt-2 text-sm text-slate-600">Profissionais que utilizam nossos recursos de prospecção.</p>
                </div>
            </div>
            <div class="mt-8 text-center">
                <a href="/app/?register=1" class="inline-flex items-center justify-center rounded-xl bg-blue-700 px-6 py-3 text-sm font-black text-white shadow-lg shadow-blue-700/20 transition hover:bg-blue-800">Faça parte dessa rede</a>
            </div>
        </div>
    </section>

    <!-- Testimonials -->
    <section id="testimonials" class="hidden" aria-hidden="true">
        <div class="max-w-7xl mx-auto">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 id="testimonials-title" class="text-3xl md:text-4xl font-black text-slate-900 mb-4"></h2>
                <p id="testimonials-subtitle" class="text-lg text-slate-600"></p>
            </div>
            <div id="testimonials-grid" class="grid md:grid-cols-3 gap-8">
                <!-- Testimonials will be loaded dynamically -->
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section id="faq" class="py-20 px-4 bg-slate-50">
        <div class="max-w-3xl mx-auto">
            <div class="text-center mb-16">
                <h2 id="faq-title" class="text-3xl md:text-4xl font-black text-slate-900 mb-4">Perguntas frequentes</h2>
                <p id="faq-subtitle" class="text-lg text-slate-600">Informações para entender os recursos e os limites de uso da plataforma.</p>
            </div>
            <div id="faq-list" class="space-y-4">
        <div class="bg-white rounded-xl p-6 shadow-sm"><h3 class="font-bold text-slate-900 mb-2">Quais fontes de pesquisa estão disponíveis?</h3><p class="text-slate-600 text-sm">A plataforma disponibiliza recursos para Google Maps, LinkedIn autenticado e consulta de CNPJ. O retorno depende da configuração, disponibilidade da fonte e dos filtros aplicados.</p></div>
        <div class="bg-white rounded-xl p-6 shadow-sm"><h3 class="font-bold text-slate-900 mb-2">Posso cancelar uma assinatura mensal?</h3><p class="text-slate-600 text-sm">O cancelamento pode ser solicitado pelo painel. Quando houver período já pago, o acesso permanece até o seu encerramento, sem estorno automático.</p></div>
        <div class="bg-white rounded-xl p-6 shadow-sm"><h3 class="font-bold text-slate-900 mb-2">Os dados de CNPJ sempre incluem contatos e quadro societário?</h3><p class="text-slate-600 text-sm">A tela apresenta os campos que a fonte de dados configurada retornar. Alguns atributos podem não estar disponíveis para todos os CNPJs consultados.</p></div>
        <div class="bg-white rounded-xl p-6 shadow-sm"><h3 class="font-bold text-slate-900 mb-2">Como funcionam os créditos de busca?</h3><p class="text-slate-600 text-sm">Os créditos e limites são definidos pelo plano e pelas regras da função utilizada. Consulte os detalhes do plano antes de iniciar pesquisas em volume.</p></div>
    </div>
        </div>
    </section>

    <!-- CTA -->
    <section id="register" class="py-20 px-4 gradient-bg">
        <div class="max-w-4xl mx-auto text-center">
            <h2 id="cta-title" class="text-3xl md:text-4xl font-black text-white mb-4">Conheça os recursos do MapsProspector</h2>
            <p id="cta-subtitle" class="text-lg text-blue-100 mb-8">Explore as soluções, consulte os planos disponíveis e crie seu acesso quando estiver pronto.</p>
            <div id="cta-content" class="text-blue-50 mb-8"><p>Veja como cada recurso funciona e escolha os fluxos compatíveis com a rotina comercial da sua empresa.</p></div>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a id="cta-button" href="/app/?register=1" class="px-8 py-4 bg-white text-blue-600 text-lg font-bold rounded-2xl transition shadow-lg hover:shadow-xl">Criar acesso gratuito</a>
                <a id="cta-secondary" href="/recursos/" class="px-8 py-4 bg-blue-500 hover:bg-blue-400 text-white text-lg font-bold rounded-2xl transition">Ver soluções</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="py-12 px-4 bg-slate-900 text-slate-400">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row justify-between items-center gap-4">
                <div class="text-2xl font-black text-white">MapsProspector</div>
                <div id="footer-content" class="text-sm"><p>© 2026 MapsProspector. Todos os direitos reservados.</p></div>
                <div class="flex gap-6">
                    <a href="/termos.php" class="hover:text-white transition">Termos</a>
                    <a href="/privacidade.php" class="hover:text-white transition">Privacidade</a>
                    <a href="/contato.php" class="hover:text-white transition">Contato</a>
                    <a href="/paginas-publicadas.php" class="font-bold text-blue-300 hover:text-white transition">Explorar páginas</a>
                </div>
            </div>
        </div>
    </footer>

    <script>
    const API_BASE = '/api/';
    function normalizePublicCtaLink(value, fallback) {
        const raw = String(value || '').trim();
        if (!raw) return fallback;
        if (raw === '#register' || /^\/?index\.php\?register=1$/.test(raw)) return '/app/?register=1';
        if (/^\/?index\.php$/.test(raw)) return '/app/';
        if (/^\/?solucoes\.php$/.test(raw)) return '/recursos/';
        return raw;
    }
    
    // Icon SVGs
    const icons = {
        search: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>',
        map: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
        chart: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>',
        zap: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>',
        shield: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>',
        users: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
        clock: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        'check-circle': '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        'trending-up': '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg>',
        headphones: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 18v-6a9 9 0 0118 0v6M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3zM3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3z" /></svg>'
    };

    function renderSection(data) {
        if (!data || !data.sectionKey) return '';
        
        const section = data;
        
        switch(section.sectionKey) {
            case 'hero':
                document.getElementById('hero-title').textContent = section.sectionTitle || '';
                document.getElementById('hero-subtitle').textContent = section.sectionSubtitle || '';
                document.getElementById('hero-content').innerHTML = section.sectionContent || '';
                if (section.extraData && section.extraData.cta_text) {
                    document.getElementById('hero-cta').textContent = section.extraData.cta_text;
                }
                break;
                
            case 'features':
                document.getElementById('features-title').textContent = section.sectionTitle || '';
                document.getElementById('features-subtitle').textContent = section.sectionSubtitle || '';
                if (section.extraData && section.extraData.items) {
                    document.getElementById('features-grid').innerHTML = section.extraData.items.map(item => `
                        <div class="bg-slate-50 rounded-2xl p-6 hover:shadow-lg transition">
                            <div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center text-blue-600 mb-4">${icons[item.icon] || icons.search}</div>
                            <h3 class="text-lg font-bold text-slate-900 mb-2">${item.title}</h3>
                            <p class="text-slate-600 text-sm">${item.description}</p>
                        </div>
                    `).join('');
                }
                break;
                
            case 'how_it_works':
                document.getElementById('how-title').textContent = section.sectionTitle || '';
                document.getElementById('how-subtitle').textContent = section.sectionSubtitle || '';
                document.getElementById('how-content').innerHTML = section.sectionContent || '';
                break;
                
            case 'benefits':
                document.getElementById('benefits-title').textContent = section.sectionTitle || '';
                document.getElementById('benefits-subtitle').textContent = section.sectionSubtitle || '';
                if (section.extraData && section.extraData.items) {
                    document.getElementById('benefits-grid').innerHTML = section.extraData.items.map(item => `
                        <div class="bg-white rounded-2xl p-6 shadow-sm">
                            <div class="w-12 h-12 bg-emerald-100 rounded-xl flex items-center justify-center text-emerald-600 mb-4">${icons[item.icon] || icons['check-circle']}</div>
                            <h3 class="text-lg font-bold text-slate-900 mb-2">${item.title}</h3>
                            <p class="text-slate-600 text-sm">${item.description}</p>
                        </div>
                    `).join('');
                }
                break;
                
            case 'testimonials':
                document.getElementById('testimonials-title').textContent = section.sectionTitle || '';
                document.getElementById('testimonials-subtitle').textContent = section.sectionSubtitle || '';
                if (section.extraData && section.extraData.items) {
                    document.getElementById('testimonials-grid').innerHTML = section.extraData.items.map(item => `
                        <div class="bg-slate-50 rounded-2xl p-6">
                            <p class="text-slate-700 mb-4">"${item.text}"</p>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">${item.name.charAt(0)}</div>
                                <div>
                                    <p class="font-bold text-slate-900 text-sm">${item.name}</p>
                                    <p class="text-slate-500 text-xs">${item.company}</p>
                                </div>
                            </div>
                        </div>
                    `).join('');
                }
                break;
                
            case 'faq':
                document.getElementById('faq-title').textContent = section.sectionTitle || '';
                document.getElementById('faq-subtitle').textContent = section.sectionSubtitle || '';
                if (section.extraData && section.extraData.items) {
                    document.getElementById('faq-list').innerHTML = section.extraData.items.map(item => `
                        <div class="bg-white rounded-xl p-6 shadow-sm">
                            <h3 class="font-bold text-slate-900 mb-2">${item.question}</h3>
                            <p class="text-slate-600 text-sm">${item.answer}</p>
                        </div>
                    `).join('');
                }
                break;
                
            case 'cta':
                document.getElementById('cta-title').textContent = section.sectionTitle || '';
                document.getElementById('cta-subtitle').textContent = section.sectionSubtitle || '';
                document.getElementById('cta-content').innerHTML = section.sectionContent || '';
                if (section.extraData && section.extraData.cta_text) {
                    document.getElementById('cta-button').textContent = section.extraData.cta_text;
                }
                if (section.extraData && section.extraData.cta_link) {
                    document.getElementById('cta-button').href = normalizePublicCtaLink(section.extraData.cta_link, '/app/?register=1');
                }
                if (section.extraData && section.extraData.secondary_cta_text) {
                    document.getElementById('cta-secondary').textContent = section.extraData.secondary_cta_text;
                }
                if (section.extraData && section.extraData.secondary_cta_link) {
                    document.getElementById('cta-secondary').href = normalizePublicCtaLink(section.extraData.secondary_cta_link, '/recursos/');
                }
                break;
                
            case 'footer':
                document.getElementById('footer-content').innerHTML = section.sectionContent || '';
                break;
        }
    }

    function renderPlans(plans) {
        const plansGrid = document.getElementById('plans-grid');
        if (!plans || plans.length === 0) {
            plansGrid.innerHTML = '<p class="text-center col-span-full text-slate-500">Nenhum plano disponível no momento.</p>';
            return;
        }
        
        plansGrid.innerHTML = plans.map(plan => {
            const isFree = plan.priceMonthly === 0 || plan.priceMonthly === '0.00' || !plan.priceMonthly;
            const price = isFree ? 'Grátis' : 'R$ ' + parseFloat(plan.priceMonthly).toFixed(2).replace('.', ',');
            
            return `
                <div class="bg-slate-50 rounded-2xl p-6 ${isFree ? 'ring-2 ring-blue-500' : ''}">
                    <h3 class="text-xl font-black text-slate-900 mb-2">${plan.name}</h3>
                    <div class="mb-4">
                        <span class="text-3xl font-black text-slate-900">${price}</span>
                        ${!isFree ? '<span class="text-slate-500">/mês</span>' : ''}
                    </div>
                    <p class="text-slate-600 text-sm mb-6">${plan.tokenLimit || 0} buscas por mês</p>
                    <a href="/app/?register=1&plan=${plan.slug}" class="block w-full py-3 text-center ${isFree ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-900 hover:bg-slate-800'} text-white font-bold rounded-xl transition">Assinar</a>
                </div>
            `;
        }).join('');
    }

    // Load content
    async function loadContent() {
        try {
            const res = await fetch(API_BASE + 'landing-page-public.php');
            const data = await res.json();
            
            if (data.success && data.sections) {
                data.sections.forEach(renderSection);
            }
        } catch (err) {
            console.error('Error loading landing page content:', err);
        }
    }

    async function loadPlans() {
        try {
            const res = await fetch(API_BASE + 'plans-public.php');
            const data = await res.json();
            
            if (data.success && data.data && data.data.items) {
                renderPlans(data.data.items);
            }
        } catch (err) {
            console.error('Error loading plans:', err);
        }
    }

    async function loadPlatformStats() {
        try {
            const res = await fetch(API_BASE + 'public-stats.php', { cache: 'no-store' });
            const data = await res.json();
            if (!data.success || !data.data) return;
            const formatNumber = value => new Intl.NumberFormat('pt-BR').format(Number(value || 0));
            const companiesCount = document.getElementById('platform-companies-count');
            const usersCount = document.getElementById('platform-users-count');
            if (companiesCount) companiesCount.textContent = formatNumber(data.data.companies);
            if (usersCount) usersCount.textContent = formatNumber(data.data.users);
        } catch (err) {
            console.error('Error loading platform stats:', err);
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
        loadContent();
        loadPlans();
        loadPlatformStats();
    });
    </script>
</body>
</html>
