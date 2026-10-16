<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
try {
    $db = Database::getInstance()->getConnection();
    $saasName = getPlatformSetting($db, 'saas_company_name') ?? 'Maps Prospector';
} catch (Throwable $e) {
    $saasName = 'Maps Prospector';
}
if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, private');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
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
    <meta name="theme-color" content="#2563eb">
    <meta name="application-name" content="<?php echo htmlspecialchars($saasName); ?>">
    <meta name="robots" content="noindex,nofollow,noarchive">
    <meta name="googlebot" content="noindex,nofollow,noarchive">
    <link rel="manifest" href="/manifest.webmanifest?v=20261009-traffic-pwa-1">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/pwa/icon-192.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/pwa/icon-180.png">
    <title>Área da aplicação | MapsProspector</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { darkMode: 'class' };</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
<style>
        /* Trava de visibilidade por perfil: itens admin-only nascem ocultos e só
           podem ser revelados para super administradores, nunca no HTML ou CSS. */
        .hidden { display: none !important; }
        .admin-only { display: none; }
        body { font-family: 'Inter', sans-serif; }
        .sidebar-nav-scroll { scrollbar-width: none; -ms-overflow-style: none; }
        .sidebar-nav-scroll::-webkit-scrollbar { display: none; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .animate-slide-in { animation: slideIn 0.3s ease-out; }/* Correção Global para Modo Escuro */
    .dark body, 
    .dark .bg-white, 
    .dark .bg-slate-50 {
        color: #e2e8f0 !important; /* Texto claro padrão no escuro */
    }
    
    .dark input, 
    .dark select, 
    .dark textarea {
        background-color: #1e293b !important; /* Fundo input escuro */
        color: #f8fafc !important; /* Texto input claro */
        border-color: #475569 !important;
    }

    .dark .text-slate-900, 
    .dark .text-gray-900 {
        color: #f8fafc !important; /* Força títulos para branco */
    }
    
    .dark .text-slate-500,
    .dark .text-slate-600 {
        color: #94a3b8 !important; /* Força textos secundários para cinza claro */
    }

    /* Responsividade Maps Prospector: preserva o desktop e cria navegação móvel. */
    /* O projeto usa .hidden { display:none !important }, portanto os estados
       do menu da landing precisam de seletores específicos e igualmente explícitos. */
    #landing-desktop-menu { display: flex !important; }
    #landing-menu-toggle { display: none !important; }
    #landing-mobile-menu { display: none !important; }
    #landing-mobile-menu:not(.hidden) { display: block !important; }
    #mobile-sidebar-backdrop, #btn-mobile-nav-toggle, #btn-close-mobile-nav { display: none; }
    .mobile-table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    /* Sidebar desktop recolhida: preserva o ícone ativo e abre por hover/foco. */
    @media (min-width: 768px) {
        #app-sidebar { width: 5.5rem; overflow: hidden; transition: width .24s cubic-bezier(.23, 1, .32, 1); }
        #app-sidebar:hover, #app-sidebar:has(:focus-visible) { width: 18rem; }
        #app-sidebar > div:first-child { padding: 1.5rem .75rem; }
        #app-sidebar > div:first-child > .flex { justify-content: center; gap: 0; }
        #app-sidebar .sidebar-brand-copy { display: none; opacity: 0; transition: opacity .18s ease; }
        #app-sidebar:hover .sidebar-brand-copy, #app-sidebar:has(:focus-visible) .sidebar-brand-copy { display: block; opacity: 1; }
        #app-sidebar nav { padding: 1rem .75rem; }
        #app-sidebar .sidebar-section-label { height: .25rem; margin-bottom: .25rem; padding: 0; overflow: hidden; visibility: hidden; font-size: 0; }
        #app-sidebar:hover .sidebar-section-label, #app-sidebar:has(:focus-visible) .sidebar-section-label { height: auto; margin-bottom: .75rem; padding: 0 1.25rem; visibility: visible; font-size: 11px; }
        #app-sidebar .tab-btn { justify-content: center; gap: 0; padding-left: 1rem; padding-right: 1rem; font-size: 0; }
        #app-sidebar .tab-btn svg { flex: 0 0 auto; }
        #app-sidebar:hover .tab-btn, #app-sidebar:has(:focus-visible) .tab-btn { justify-content: flex-start; gap: 1rem; padding-left: 1.25rem; padding-right: 1.25rem; font-size: .875rem; }
        #app-sidebar .sidebar-menu-toggle { justify-content: center; gap: 0; padding-left: 1rem; padding-right: 1rem; font-size: 0; }
        #app-sidebar .sidebar-menu-toggle > div { gap: 0; }
        #app-sidebar:hover .sidebar-menu-toggle, #app-sidebar:has(:focus-visible) .sidebar-menu-toggle { justify-content: space-between; gap: 1rem; padding-left: 1.25rem; padding-right: 1.25rem; font-size: .875rem; }
        #app-sidebar:hover .sidebar-menu-toggle > div, #app-sidebar:has(:focus-visible) .sidebar-menu-toggle > div { gap: 1rem; }
        #app-sidebar:not(:hover):not(:has(:focus-visible)) #submenu-cnpj { display: none !important; }
        #app-sidebar #sidebar-api-status-widget { padding: .8rem; text-align: center; }
        #app-sidebar #sidebar-api-status-widget > p:first-child { display: none; }
        #app-sidebar #sidebar-api-status-widget > p:nth-child(2) { justify-content: center; margin-bottom: 0; }
        #app-sidebar #search-api-status { display: none; }
        #app-sidebar:hover > div:last-child, #app-sidebar:has(:focus-visible) > div:last-child { padding: 1.5rem; }
        #app-sidebar:hover #sidebar-api-status-widget, #app-sidebar:has(:focus-visible) #sidebar-api-status-widget { padding: 1.25rem; text-align: left; }
        #app-sidebar:hover #sidebar-api-status-widget > p:first-child, #app-sidebar:has(:focus-visible) #sidebar-api-status-widget > p:first-child { display: block; }
        #app-sidebar:hover #sidebar-api-status-widget > p:nth-child(2), #app-sidebar:has(:focus-visible) #sidebar-api-status-widget > p:nth-child(2) { justify-content: flex-start; margin-bottom: .5rem; }
        #app-sidebar:hover #search-api-status, #app-sidebar:has(:focus-visible) #search-api-status { display: inline; }
        #app-sidebar:hover #submenu-cnpj .tab-btn, #app-sidebar:has(:focus-visible) #submenu-cnpj .tab-btn { font-size: .75rem; }
    }
    @media (prefers-reduced-motion: reduce) {
        #app-sidebar { transition: none !important; }
        #app-sidebar .sidebar-brand-copy { transition: none !important; }
    }
    @media (max-width: 767px) {
        #landing-desktop-menu { display: none !important; }
        #landing-menu-toggle { display: inline-flex !important; }
        html, body { min-height: 100%; }
        body.mobile-nav-open { overflow: hidden; }
        #dashboard { min-height: 100dvh; height: auto !important; overflow: visible !important; }
        #app-sidebar { position: fixed !important; inset: 0 auto 0 0; width: min(84vw, 20rem) !important; max-width: 20rem; height: 100dvh; transform: translateX(-105%); transition: transform .24s cubic-bezier(.23, 1, .32, 1); z-index: 80 !important; }
        body.mobile-nav-open #app-sidebar { transform: translateX(0); }
        #mobile-sidebar-backdrop { position: fixed; inset: 0; z-index: 70; display: block; background: rgba(15, 23, 42, .56); opacity: 0; pointer-events: none; transition: opacity .2s ease; }
        body.mobile-nav-open #mobile-sidebar-backdrop { opacity: 1; pointer-events: auto; }
        #btn-mobile-nav-toggle { display: inline-flex; width: 2.75rem; height: 2.75rem; align-items: center; justify-content: center; flex: 0 0 auto; }
        #btn-close-mobile-nav { display: inline-flex; width: 2.25rem; height: 2.25rem; align-items: center; justify-content: center; }
        #app-sidebar > div:first-child { padding: 1.25rem; }
        #app-sidebar nav { padding: 1rem; }
        #app-sidebar .tab-btn { min-height: 3rem; padding: .75rem 1rem; }
        #app-sidebar > div:last-child { padding: 1rem; }
        #dashboard main { width: 100%; min-width: 0; min-height: 100dvh; }
        #dashboard header { height: auto; min-height: 4.5rem; padding: .75rem 1rem; gap: .75rem; }
        #dashboard #header-token-warning, #dashboard #impersonation-banner { padding: .75rem 1rem; align-items: flex-start; justify-content: flex-start; line-height: 1.35; }
        #page-title { font-size: 1rem; line-height: 1.2; }
        #header-dashboard-subtitle { max-width: 9.5rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: .5rem; }
        #dashboard header > div:last-child { gap: .5rem; }
        #header-user-area { min-width: 0; max-width: 8.5rem; padding-left: 0; border-left: 0; }
        #user-name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 6.25rem; }
        #user-dropdown { width: min(12rem, calc(100vw - 2rem)); }
        #dashboard-content-scroll { overflow-y: visible; }
        #content-area { padding: 1rem !important; padding-bottom: 4.5rem !important; }
        #content-area .max-w-6xl, #content-area .max-w-5xl, #content-area .max-w-4xl, #content-area .max-w-3xl, #content-area .max-w-2xl { max-width: 100%; }
        #content-area [class~="grid-cols-2"] { grid-template-columns: minmax(0, 1fr) !important; }
        #content-area [class~="min-w-[200px]"] { min-width: 0 !important; }
        #content-area .flex.items-center.justify-between { flex-wrap: wrap; align-items: flex-start; }
        #content-area .flex.items-center.justify-between > .flex.items-center, #content-area #results-header-buttons { flex-wrap: wrap; }
        #content-area #results-header-buttons { width: 100%; }
        #content-area #results-header-buttons button { flex: 1 1 10rem; justify-content: center; }
        #content-area .mobile-table-scroll { margin: 0 -1rem; padding: 0 1rem; }
        #content-area .mobile-table-scroll table { min-width: 42rem; }
        #content-area table th, #content-area table td { white-space: nowrap; }
        #content-area .rounded-\[2rem\] { border-radius: 1.25rem; }
        #content-area .p-10 { padding: 1.5rem !important; }
        #content-area .p-8 { padding: 1.25rem !important; }
        #modal-perfil-overlay, #modal-cadastro-overlay { align-items: flex-start !important; overflow-y: auto; padding: 1rem !important; }
        #modal-perfil-overlay > div, #modal-cadastro-overlay > div { width: 100% !important; max-width: 100% !important; margin: 0 auto; border-radius: 1.5rem !important; }
        #modal-perfil-overlay .p-8, #modal-cadastro-overlay .p-8, #modal-cadastro-overlay .p-12 { padding: 1.5rem 1.25rem !important; }
        #modal-perfil-overlay .grid.grid-cols-2, #modal-cadastro-overlay .grid.grid-cols-2 { grid-template-columns: minmax(0, 1fr) !important; }
        #modal-cadastro-overlay { align-items: flex-start !important; overflow-y: auto; padding: 1rem !important; }
        #modal-cadastro-overlay > div { width: 100% !important; max-width: 100% !important; margin: 0 auto; padding: 1.5rem 1.25rem !important; border-radius: 1.5rem !important; }
        #login-screen { align-items: flex-start; padding-top: 4.5rem; padding-bottom: 1.5rem; overflow-y: auto; }
        #login-screen .max-w-md { max-width: 100%; }
        #login-screen .bg-white { padding: 2rem 1.25rem; border-radius: 1.75rem; }
        #login-screen .mb-10 { margin-bottom: 2rem; }
        #btn-login-home { left: 1rem !important; top: 1rem !important; }
        #btn-theme-toggle-login { right: 1rem !important; top: 1rem !important; }
        .fixed.inset-0 > .max-w-md, .fixed.inset-0 > .max-w-lg, .fixed.inset-0 > .max-w-xl { max-height: calc(100dvh - 2rem); overflow-y: auto; }
        #toast { left: 1rem; right: 1rem; bottom: 1rem; padding: 1rem; border-radius: 1rem; }
    }
    @media (max-width: 380px) {
        #header-user-area { display: none; }
        #content-area { padding-left: .75rem !important; padding-right: .75rem !important; }
        #content-area .mobile-table-scroll { margin-left: -.75rem; margin-right: -.75rem; padding-left: .75rem; padding-right: .75rem; }
    }
    </style>
</head>
<body class="bg-gray-50 text-gray-900 dark:bg-gray-900 dark:text-gray-100 transition-colors duration-200">
<script>window.PRIVATE_APP_MODE = true; window.API_BASE_URL = '/api/';</script>
<div id="app">
        <!-- Login e aplicação privada: nenhum conteúdo público é renderizado nesta entrada. -->
        <div id="login-screen" class="hidden min-h-screen flex items-center justify-center bg-[#0F172A] px-4 overflow-hidden relative">
            <div class="absolute top-0 left-0 w-full h-full overflow-hidden opacity-20 pointer-events-none">
                <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600 rounded-full blur-[120px]"></div>
                <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-indigo-900 rounded-full blur-[120px]"></div>
            </div>
            <a id="btn-login-home" href="/" class="absolute top-5 left-5 z-20 inline-flex items-center gap-2 rounded-xl bg-white/90 px-4 py-2 text-xs font-black text-slate-700 shadow-lg border border-slate-200 transition-colors hover:bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" aria-label="Voltar para a página inicial">
                <span aria-hidden="true">←</span> Voltar à home
            </a>
            <div class="max-w-md w-full z-10">
                <div class="bg-white rounded-[2.5rem] shadow-2xl p-12 border border-slate-200 text-center">
                    <div class="mb-10">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-600 rounded-3xl mb-6 shadow-xl shadow-blue-200 transform rotate-3 hover:rotate-0 transition-all duration-500">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-2"><?php echo htmlspecialchars($saasName); ?></h1>
                        <p class="text-slate-500 font-medium text-sm">Ferramenta de Prospecção Inteligente</p>
                        <p id="public-companies-using" class="mt-3 text-[11px] font-black uppercase tracking-wide text-blue-600">Empresas já utilizam a plataforma</p>
                        <p id="login-status" class="mt-2 text-xs font-semibold text-slate-500 hidden" role="status" aria-live="polite"></p>
                    </div>
                    <form id="login-form" class="space-y-6">
                        <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 text-left space-y-4">
                            <div>
                                <label for="login-email" class="block text-[10px] font-black text-slate-500 uppercase mb-2 ml-1">E-mail</label>
                                <input type="email" id="login-email" name="email" placeholder="seu@email.com" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" autocomplete="email" />
                            </div>
                            <div>
                                <label for="login-password" class="block text-[10px] font-black text-slate-500 uppercase mb-2 ml-1">Senha</label>
                                <input type="password" id="login-password" name="password" placeholder="••••••••" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" autocomplete="current-password" />
                            </div>
                            <p id="login-error" class="mt-2 text-xs font-bold text-red-500 hidden" role="alert" aria-live="assertive"></p>
                        </div>
                        <button type="submit" id="btn-login" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-5 rounded-2xl shadow-xl shadow-blue-100 transition-all active:scale-[0.98] disabled:opacity-70 flex items-center justify-center gap-3 text-sm tracking-wide uppercase">
                            Entrar
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                        </button>
                    </form>
                    <div class="mt-8 pt-6 border-t border-slate-100">
                        <p class="text-[11px] text-slate-500 font-medium mb-2">Ainda não tem empresa cadastrada?</p>
                        <button type="button" id="link-cadastro" class="text-blue-600 font-bold text-sm hover:underline">Cadastrar minha empresa</button>
                    </div>
                    <p class="mt-4 text-xs text-slate-500"><a href="/recursos/" class="font-semibold text-blue-600 hover:underline">Conheça as soluções e recursos da plataforma</a></p>
                    <div class="mt-10 pt-6 border-t border-slate-100">
                        <p class="text-center text-[10px] text-slate-400 font-semibold tracking-wider uppercase">
                            <?php echo htmlspecialchars($saasName); ?> © 2026
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Principal -->
        <div id="dashboard" class="hidden flex h-screen bg-slate-50 dark:bg-slate-900 font-sans antialiased text-slate-900 dark:text-slate-100 overflow-hidden transition-colors duration-200">
            <div id="mobile-sidebar-backdrop" aria-hidden="true"></div>
            <aside id="app-sidebar" class="w-72 bg-white dark:bg-slate-900 text-slate-900 dark:text-white flex flex-col shrink-0 shadow-2xl z-50 transition-colors duration-200 border-r border-slate-200 dark:border-slate-800" aria-label="Navegação principal">
                <div class="relative p-8 border-b border-slate-200 dark:border-slate-700/50">
                    <button type="button" id="btn-close-mobile-nav" class="absolute top-4 right-4 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800 text-slate-700 dark:text-slate-200" aria-label="Fechar menu de navegação">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12" /></svg>
                    </button>
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-900/40 border border-blue-400/20 text-white">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>
                        </div>
                        <div class="sidebar-brand-copy">
                            <span id="sidebar-company-name" class="font-black text-lg block leading-none tracking-tight italic text-slate-900 dark:text-white"><?php echo htmlspecialchars($saasName); ?></span>
                            <span id="sidebar-instance-name" class="text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-[0.2em]">Nome da Instância</span>
                        </div>
                    </div>
                </div>
                <nav class="sidebar-nav-scroll flex-grow min-h-0 overflow-y-auto p-6 flex flex-col gap-0">
                    <div class="pb-4">
                        <p class="sidebar-section-label text-[11px] font-black text-slate-500 dark:text-slate-300 uppercase tracking-widest px-5 mb-3">Normal</p>
                        <div class="space-y-2">
                            <button data-tab="dashboard" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm bg-blue-600 text-white shadow-xl shadow-blue-900/30">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>
                                Dashboard
                            </button>
                            <button data-tab="search" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                Prospecção
                            </button>
                            
                            <button id="nav-btn-linkedin" data-tab="linkedin" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300 hidden">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8a6 6 0 016 6v7h-4v-7a2 2 0 00-4 0v7h-4v-7a6 6 0 016-6zM2 9h4v12H2zM4 3a2 2 0 110 4 2 2 0 010-4z" /></svg>
    Pesquisa LinkedIn
</button>

                            <!-- Bloco Consultas CNPJ com Sub-menu -->
<div class="space-y-1">
    <button type="button" onclick="toggleSubmenu('submenu-cnpj')" class="sidebar-menu-toggle w-full flex items-center justify-between gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-700/50">
        <div class="flex items-center gap-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
            Consultas CNPJ
        </div>
        <svg id="arrow-cnpj" class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
    </button>
    
    <div id="submenu-cnpj" class="hidden pl-10 space-y-1">
        <button type="button" data-tab="cnpj-lookup" class="tab-btn w-full text-left px-5 py-3 rounded-xl text-xs font-bold text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition-colors">
            • Consulta Avançada
        </button>
        <!--<button type="button" data-tab="cnpj-advanced" class="tab-btn w-full text-left px-5 py-3 rounded-xl text-xs font-bold text-slate-500 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 transition-colors">
            • Buscar Empresas
        </button>-->
    </div>
</div>
                            <button data-tab="crm" id="nav-btn-crm" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300 hidden">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7zm4 0v12m6-9h5m-5 4h5m-5 4h3" /></svg>
                                CRM comercial
                            </button>
                            <button data-tab="history" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Histórico
                            </button>
                            <button data-tab="request-credits" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Solicitar Créditos
                            </button>
                            <button data-tab="choose-plan" id="nav-btn-choose-plan" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                Meu plano
                            </button>
                            <button data-tab="team" id="nav-btn-team" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300 hidden">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                Minha Equipe
                            </button>
<button data-tab="help" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
    Ajuda
</button>
                            <button data-tab="referrals" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" /></svg>
                                Indicações
                            </button>
                            <button data-tab="versions" id="nav-btn-versions" class="admin-only hidden tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" /></svg>
                                Versões e Atualizações
                            </button>
                    </div>
                    <div id="nav-block-administracao" class="hidden">
                        <div class="h-px w-full bg-slate-200 dark:bg-slate-500 my-2"></div>
                        <div class="pt-2 pb-4">
                            <p class="sidebar-section-label text-[11px] font-black text-slate-500 dark:text-slate-300 uppercase tracking-widest px-5 mb-3">Administração</p>
                            <div class="space-y-2">
                                <button data-tab="saas-config" id="tab-btn-saas-config" class="admin-only hidden tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    Empresa SaaS
                                </button>
                                <button data-tab="plans" id="tab-btn-plans" class="admin-only hidden tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                    Planos
                                </button>
                                <button data-tab="requests" id="tab-btn-requests" class="admin-only hidden tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300 relative">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
    Solicitações
    <span id="requests-badge" class="hidden absolute top-2 right-2 min-w-[20px] h-5 px-1 bg-red-600 text-white text-[10px] font-black rounded-full flex items-center justify-center">0</span>
</button>
                                <button data-tab="companies" id="tab-btn-companies" class="admin-only hidden tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    Empresas
                                </button>
                                <button data-tab="credits" id="tab-btn-credits" class="admin-only hidden tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Créditos
                                </button>
                                                                <button data-tab="landing-page" id="tab-btn-landing-page" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
                                    Landing Page
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="pt-2 space-y-2">
                        <!-- Botão Configurações -->
	            <button data-tab="settings" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 dark:text-slate-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            </svg>
                            Configurações
                        </button>

                        <!-- Central administrativa de modelos de mensagens -->
                        <button data-tab="notifications" id="tab-btn-notifications" class="admin-only hidden tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 dark:text-slate-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                            Central de Notificações
                        </button>

                        <!-- Botão API de Busca -->
                        <button data-tab="api-busca" class="admin-only hidden tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 dark:text-slate-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                            API de Busca
                        </button>

                        <!-- Botão Tráfego do site (exclusivo do super administrador) -->
                        <button data-tab="traffic" class="admin-only hidden tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 dark:text-slate-300">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Tráfego do site
                        </button>
                    </div>
                </nav>

                <!-- Status Widget na Sidebar -->
                <div class="p-6 border-t border-slate-200 dark:border-slate-700/50 space-y-4">
                    <div id="sidebar-api-status-widget" class="bg-slate-100 dark:bg-slate-700/40 p-5 rounded-[1.25rem] border border-slate-200 dark:border-slate-600/50">
                        <p class="text-[9px] font-black text-slate-500 dark:text-slate-400 uppercase mb-2 tracking-widest">API de Busca (Google Maps)</p>
                        <p class="text-[10px] font-bold text-slate-900 dark:text-white truncate flex items-center gap-2 mb-2">
                            <span id="search-api-indicator" class="w-2 h-2 rounded-full bg-red-500"></span>
                            <span id="search-api-status">Verificando...</span>
                        </p>
                    </div>
                </div>
            </aside>

            <!-- Área Principal -->
            <main class="flex-grow flex flex-col min-w-0">
                <div class="sticky top-0 z-50 bg-white dark:bg-slate-800 shadow-sm transition-colors duration-200">
                    <div id="header-token-warning" class="hidden bg-amber-500 text-amber-950 px-10 py-3 flex items-center justify-center gap-2 text-sm font-bold">
                        <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                        <span>Sua empresa atingiu o limite de créditos do plano. Entre em contato para aquisição de mais créditos.</span>
                    </div>
                    <div id="impersonation-banner" class="hidden bg-blue-600 text-white px-10 py-3 flex items-center justify-center gap-4 text-sm font-bold">
                        <span>Acessando como <strong id="impersonation-tenant-name"></strong></span>
                        <button type="button" id="btn-stop-impersonate" class="px-4 py-2 rounded-xl bg-white/20 hover:bg-white/30 font-bold text-xs uppercase">Sair do acesso</button>
                    </div>
                    <header class="bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 h-20 flex items-center px-10 justify-between gap-3 backdrop-blur-md bg-white/80 dark:bg-slate-800/95 transition-colors duration-200">
                        <button type="button" id="btn-mobile-nav-toggle" class="rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 text-slate-700 dark:text-slate-200 transition-colors" aria-label="Abrir menu de navegação" aria-controls="app-sidebar" aria-expanded="false">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                        </button>
                        <div class="flex flex-col">
                            <h2 id="page-title" class="text-slate-900 dark:text-slate-100 font-extrabold text-xl tracking-tight">Dashboard</h2>
                            <p id="header-dashboard-subtitle" class="text-[11px] text-slate-400 dark:text-slate-500 font-semibold uppercase tracking-wider">Dashboard</p>
                        </div>
                        <div class="flex items-center gap-4">
                            <button type="button" id="btn-notifications-inbox" class="relative flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 bg-slate-50 text-slate-600 transition-colors hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-200 dark:hover:bg-slate-600/50 dark:focus:ring-offset-slate-800" aria-label="Abrir notificações" title="Notificações">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
                                <span id="notifications-header-badge" class="hidden absolute -right-1 -top-1 min-w-[20px] rounded-full bg-red-600 px-1.5 py-0.5 text-center text-[10px] font-black leading-4 text-white" aria-label="Notificações não lidas">0</span>
                            </button>
                            <button type="button" id="btn-theme-toggle" class="p-2.5 rounded-xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700/50 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600/50 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-800" aria-label="Alternar tema (claro/escuro)" title="Alternar tema">
                                <svg id="theme-icon-sun" class="w-5 h-5 hidden dark:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" /></svg>
                                <svg id="theme-icon-moon" class="w-5 h-5 block dark:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" /></svg>
                            </button>
                            <div id="header-user-area" class="relative flex flex-col items-end border-l border-slate-200 dark:border-slate-600 pl-6 cursor-pointer group" title="Clique para abrir menu">
                                <div class="flex items-center gap-2">
                                    <span id="user-name" class="text-xs font-bold text-slate-900 dark:text-slate-100">Administrador</span>
                                    <svg class="w-4 h-4 text-slate-400 dark:text-slate-500 group-hover:text-slate-600 dark:group-hover:text-slate-300 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                                </div>
                                <span id="user-role" class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase">Admin</span>
                                <div id="user-dropdown" class="hidden absolute right-0 top-full mt-2 w-48 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-600 shadow-xl py-2 z-50">
                                    <button type="button" id="btn-perfil" class="w-full text-left px-4 py-3 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg flex items-center gap-2">
                                        <svg class="w-5 h-5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                                        Perfil
                                    </button>
                                    <button type="button" id="btn-logout" class="w-full text-left px-4 py-3 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg flex items-center gap-2">
                                        <svg class="w-5 h-5 text-slate-500 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                        Sair
                                    </button>
                                </div>
                            </div>
                        </div>
                    </header>
                </div>
                <div id="dashboard-content-scroll" class="flex-grow overflow-y-auto p-10 bg-[#F8FAFC] dark:bg-slate-900/50 transition-colors duration-200">
                    <div id="content-area"></div>
                </div>
            </main>
        </div>
    </div>
    <div id="toast" class="hidden fixed bottom-10 right-10 bg-slate-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-4 z-[9999] animate-slide-in">
        <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
        </div>
        <div>
            <h4 class="font-bold text-sm">Sucesso</h4>
            <p id="toast-message" class="text-xs text-slate-300"></p>
        </div>
    </div>

    <!-- Modal Perfil -->
    <div id="modal-perfil-overlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[60] flex items-center justify-center p-4 hidden">
        <div class="bg-white dark:bg-slate-800 w-full max-w-md rounded-[2.5rem] shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-8">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Meu Perfil</h3>
                    <button id="modal-perfil-fechar" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                    </button>
                </div>

                <form id="form-perfil-senha" class="space-y-4">
                    <div id="perfil-error" class="hidden p-4 bg-red-50 text-red-600 text-sm rounded-2xl border border-red-100 font-bold"></div>
                    <div id="perfil-success" class="hidden p-4 bg-emerald-50 text-emerald-600 text-sm rounded-2xl border border-emerald-100 font-bold"></div>

                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">Telefone / WhatsApp</label>
                        <input type="text" id="perfil-phone" class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all text-slate-900 dark:text-slate-100" placeholder="(00) 00000-0000">
                    </div>

                    <div class="pt-2">
                        <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">Senha Atual</label>
                        <input type="password" id="perfil-senha-atual" class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all text-slate-900 dark:text-slate-100" placeholder="••••••••">
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">Nova Senha</label>
                            <input type="password" id="perfil-senha-nova" class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all text-slate-900 dark:text-slate-100" placeholder="••••••••">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">Confirmar</label>
                            <input type="password" id="perfil-senha-confirm" class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all text-slate-900 dark:text-slate-100" placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit" id="btn-perfil-salvar" class="w-full py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-2xl transition-all shadow-lg shadow-blue-500/20 flex items-center justify-center gap-2 mt-4">
                        Salvar Alterações
                    </button>
                </form>
            </div>
        </div>
    </div>

        <!-- Script do Widget Atendo -->
    <!-- Modal Cadastro de Empresa -->
<div id="modal-cadastro-overlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-[60] flex items-center justify-center p-4 hidden">
    <div class="bg-white dark:bg-slate-800 w-full max-w-2xl rounded-[2.5rem] shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden max-h-[90vh] overflow-y-auto">
        <div class="p-8 md:p-12">
            <div class="flex justify-between items-center mb-8">
                <div>
                    <h3 class="text-3xl font-black text-slate-900 dark:text-slate-100">Cadastrar Empresa</h3>
                    <p class="text-slate-500 dark:text-slate-400 font-medium italic">Crie sua instância do MapsProspector Pro</p>
                </div>
                <button id="modal-cadastro-fechar" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <form id="form-cadastro" class="space-y-6">
                <div id="reg-error" class="hidden p-4 bg-red-50 text-red-600 text-sm rounded-2xl border border-red-100 font-bold"></div>
                <div id="cadastro-success" class="hidden p-4 bg-emerald-50 text-emerald-600 text-sm rounded-2xl border border-emerald-100 font-bold"></div>
                
                <div class="grid md:grid-cols-2 gap-6">
                    <!-- Dados da Empresa -->
                    <div class="space-y-4">
                        <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest border-b border-blue-100 pb-1">Empresa</p>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">CNPJ ou CPF</label>
                            <input type="text" id="reg-cnpj" class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="00.000.000/0000-00">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">Nome da Empresa</label>
                            <input type="text" id="reg-company" required class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="Ex: Minha Agência Digital">
                        </div>
                    </div>

                    <!-- Dados do Administrador -->
                    <div class="space-y-4">
                        <p class="text-[10px] font-black text-blue-600 uppercase tracking-widest border-b border-blue-100 pb-1">Administrador</p>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">Seu Nome</label>
                            <input type="text" id="reg-name" required class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="Nome Completo">
                        </div>
                        <div> 
                            <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">WhatsApp (Obrigatório)</label>
                            <input type="text" id="reg-phone" class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="(00) 00000-0000">
                        </div>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-6 pt-2">
                    <div>
                        <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">E-mail de Acesso</label>
                        <input type="email" id="reg-email" required class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="seu@email.com">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">Senha</label>
                            <input type="password" id="reg-password" required class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="••••••••">
                        </div>
                        <div>
                            <label class="block text-xs font-black text-slate-500 uppercase mb-2 ml-1">Confirmar</label>
                            <input type="password" id="reg-password-confirm" required class="w-full px-5 py-3 rounded-2xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:ring-2 focus:ring-blue-500 outline-none transition-all" placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <div class="pt-6">
                    <button type="submit" id="btn-cadastro" class="w-full py-5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-2xl transition-all shadow-xl shadow-blue-500/20 flex items-center justify-center gap-3 uppercase tracking-wide text-sm">
                        Criar Minha Conta e Acessar
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                    </button>
                    <label class="flex items-start gap-3 text-left text-[11px] text-slate-500 mt-4 cursor-pointer">
    <input id="cadastro-terms" type="checkbox" class="mt-0.5 w-4 h-4 accent-blue-600" required>
    <span>Li e aceito os <a href="termos.php" target="_blank" rel="noopener noreferrer" class="underline font-bold">Termos de Uso</a> e a <a href="privacidade.php" target="_blank" rel="noopener noreferrer" class="underline font-bold">Política de Privacidade</a>.</span>
</label>
                </div>
            </form>
        </div>
    </div>
</div>

</div>

<script src="/assets/js/export-replace.js?v=20260821-65"></script>
<script src="/assets/js/linkedin-ui.js?v=20261008-linkedin-card-fields-1"></script>
<script src="/assets/js/help-ui.js?v=20260821-65"></script>
<script src="/assets/js/apify-usage-ui.js?v=20260822-88-traffic-referrals-1"></script>
<script src="/assets/js/notification-ui.js?v=20261010-reengagement-editor-1"></script>
<script src="/assets/js/notification-center.js?v=20261009-reengagement-1"></script>
<script src="/app/crm-ui.js?v=20260822-91-menu-repeat-1"></script>
<script src="/assets/js/site-traffic.js?v=20261009-traffic-1" defer></script>
<script src="/app/app.js?v=20261016-billing-due-dates-1"></script>
<script src="/assets/js/help.js?v=20260821-65"></script>
<script src="/assets/js/help-admin.js?v=20260821-65"></script>
<script src="/assets/js/pwa.js?v=20261009-pwa-1"></script>

</body>
</html>
