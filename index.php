<?php
/**
 * Página Principal - MapsProspector Pro
 * Frontend PHP com JavaScript
 */

require_once __DIR__ . '/config/config.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MapsProspector Pro | CRM Integration</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .animate-slide-in { animation: slideIn 0.3s ease-out; }
    </style>
</head>
<body class="bg-gray-50 text-gray-900">
    <div id="app">
        <!-- Tela de Login -->
        <div id="login-screen" class="min-h-screen flex items-center justify-center bg-[#0F172A] px-4 overflow-hidden relative">
            <div class="absolute top-0 left-0 w-full h-full overflow-hidden opacity-20 pointer-events-none">
                <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-blue-600 rounded-full blur-[120px]"></div>
                <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-indigo-900 rounded-full blur-[120px]"></div>
            </div>
            <div class="max-w-md w-full z-10">
                <div class="bg-white rounded-[2.5rem] shadow-2xl p-12 border border-slate-200 text-center">
                    <div class="mb-10">
                        <div class="inline-flex items-center justify-center w-20 h-20 bg-blue-600 rounded-3xl mb-6 shadow-xl shadow-blue-200 transform rotate-3 hover:rotate-0 transition-all duration-500">
                            <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <h1 class="text-3xl font-black text-slate-900 tracking-tight mb-2">Atendo Maps</h1>
                        <p class="text-slate-500 font-medium text-sm">Ferramenta de Prospecção Inteligente</p>
                    </div>
                    <form id="login-form" class="space-y-6">
                        <div class="bg-slate-50 p-6 rounded-2xl border border-slate-100 text-left">
                            <label for="login-email" class="block text-[10px] font-black text-slate-500 uppercase mb-2 ml-1">E-mail</label>
                            <input type="email" id="login-email" name="email" placeholder="seu@email.com" required
                                class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"
                                autocomplete="email" />
                            <p id="login-error" class="mt-2 text-xs font-bold text-red-500 hidden"></p>
                            <p class="text-[11px] text-slate-500 font-medium leading-relaxed mt-3">
                                Use o e-mail cadastrado na plataforma. Após entrar, configure a integração em <span class="font-bold text-slate-700">Configurações</span>.
                            </p>
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
                    <div id="cadastro-box" class="hidden mt-8 p-6 rounded-2xl border border-slate-200 bg-slate-50 text-left">
                        <h3 class="text-lg font-black text-slate-900 mb-4">Nova empresa</h3>
                        <form id="form-cadastro">
                            <div class="space-y-4">
                                <div>
                                    <label for="reg-company" class="block text-[10px] font-black text-slate-500 uppercase mb-1">Nome da empresa</label>
                                    <input type="text" id="reg-company" placeholder="Razão social ou nome fantasia" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" />
                                </div>
                                <div>
                                    <label for="reg-email" class="block text-[10px] font-black text-slate-500 uppercase mb-1">E-mail do administrador</label>
                                    <input type="email" id="reg-email" placeholder="admin@empresa.com" required class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" />
                                </div>
                                <div>
                                    <label for="reg-name" class="block text-[10px] font-black text-slate-500 uppercase mb-1">Seu nome (opcional)</label>
                                    <input type="text" id="reg-name" placeholder="Nome do responsável" class="w-full bg-white border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" />
                                </div>
                                <p id="reg-error" class="text-xs font-bold text-red-500 hidden"></p>
                                <div class="flex gap-3">
                                    <button type="button" id="btn-cadastro-voltar" class="flex-1 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-100 text-sm">Voltar</button>
                                    <button type="submit" id="btn-cadastro" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700 disabled:opacity-70 text-sm">Cadastrar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                    <p id="cadastro-success" class="hidden mt-4 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-700 text-sm font-medium"></p>
                    <div class="mt-10 pt-6 border-t border-slate-100">
                        <p class="text-center text-[10px] text-slate-400 font-semibold tracking-wider uppercase">
                            Atendo Tecnologia © 2024
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dashboard Principal (oculto inicialmente) -->
        <div id="dashboard" class="hidden flex h-screen bg-slate-50 font-sans antialiased text-slate-900 overflow-hidden">
            <!-- Sidebar -->
            <aside class="w-72 bg-[#0F172A] text-white flex flex-col shrink-0 shadow-2xl z-50">
                <div class="p-8 border-b border-slate-800/50">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-blue-600 rounded-2xl flex items-center justify-center shadow-lg shadow-blue-900/40 border border-blue-400/20">
                            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <span class="font-black text-lg block leading-none tracking-tight italic">ATENDO</span>
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-[0.2em]">Maps Prospector</span>
                        </div>
                    </div>
                </div>
                <nav class="flex-grow p-6 flex flex-col gap-0">
                    <!-- Normal: para todos -->
                    <div class="pb-4">
                        <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest px-5 mb-3">Normal</p>
                        <div class="space-y-2">
                            <button data-tab="dashboard" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm bg-blue-600 text-white shadow-xl shadow-blue-900/30">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" /></svg>
                                Dashboard
                            </button>
                            <button data-tab="search" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-800/50 text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                                Prospecção
                            </button>
                            <button data-tab="history" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-800/50 text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Histórico
                            </button>
                            <button data-tab="request-credits" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-800/50 text-slate-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                Solicitar Créditos
                            </button>
                        </div>
                    </div>
                    <!-- Para usuários normais: uma única linha entre Normal e Configurações -->
                    <div id="nav-divider-normal" class="h-px w-full bg-slate-600 my-2" style="min-height: 1px;" aria-hidden="true"></div>
                    <!-- Bloco completo (linhas + Administração) só para super_admin -->
                    <div id="nav-block-administracao" class="hidden">
                        <div class="h-px w-full bg-slate-600 my-2" style="min-height: 1px;" aria-hidden="true"></div>
                        <div class="pt-2 pb-4">
                            <p class="text-[11px] font-black text-slate-400 uppercase tracking-widest px-5 mb-3">Administração</p>
                            <div class="space-y-2">
                                <button data-tab="plans" id="tab-btn-plans" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-800/50 text-slate-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>
                                    Planos
                                </button>
                                <button data-tab="companies" id="tab-btn-companies" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-800/50 text-slate-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>
                                    Empresas
                                </button>
                                <button data-tab="credits" id="tab-btn-credits" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-800/50 text-slate-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                                    Créditos
                                </button>
                            </div>
                        </div>
                        <div class="h-px w-full bg-slate-600 my-2" style="min-height: 1px;" aria-hidden="true"></div>
                    </div>
                    <div class="pt-2 space-y-2">
                        <button data-tab="settings" class="tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-800/50 text-slate-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" /></svg>
                            Configurações
                        </button>
                    </div>
                </nav>
                <div class="p-6 border-t border-slate-800/50 space-y-4">
                    <div class="bg-slate-800/40 p-5 rounded-[1.25rem] border border-slate-700/50">
                        <p class="text-[9px] font-black text-slate-500 uppercase mb-2 tracking-widest">GPS Status</p>
                        <p class="text-[10px] font-bold text-white truncate flex items-center gap-2 mb-2">
                            <span id="gps-indicator" class="w-2 h-2 rounded-full bg-red-500"></span>
                            <span id="gps-status">GPS Inativo</span>
                        </p>
                        <div id="gps-location" class="hidden pt-2 border-t border-slate-700/50">
                            <p class="text-[9px] text-slate-400 font-bold uppercase mb-0.5">Detectado:</p>
                            <p id="gps-location-name" class="text-[10px] text-emerald-300 font-bold leading-tight"></p>
                        </div>
                    </div>
                </div>
            </aside>

            <!-- Área Principal -->
            <main class="flex-grow flex flex-col min-w-0">
                <div class="sticky top-0 z-50 bg-white shadow-sm">
                <div id="header-token-warning" class="hidden bg-amber-500 text-amber-950 px-10 py-3 flex items-center justify-center gap-2 text-sm font-bold">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
                    <span>Sua empresa atingiu o limite de tokens do plano. Entre em contato para aquisição de mais tokens.</span>
                </div>
                <header class="bg-white border-b border-slate-200 h-20 flex items-center px-10 justify-between backdrop-blur-md bg-white/80">
                    <div class="flex flex-col">
                        <h2 id="page-title" class="text-slate-900 font-extrabold text-xl tracking-tight">Dashboard</h2>
                        <p class="text-[11px] text-slate-400 font-semibold uppercase tracking-wider">Dashboard Atendo</p>
                    </div>
                    <div class="flex items-center gap-6">
                        <button id="btn-refresh-gps" class="flex items-center gap-2 px-4 py-2 bg-slate-100 hover:bg-slate-200 rounded-xl transition-all text-[10px] font-black uppercase text-slate-600">
                            <svg id="gps-icon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <span id="gps-btn-text">Recarregar GPS</span>
                        </button>
                        <div id="header-user-area" class="relative flex flex-col items-end border-l border-slate-200 pl-6 cursor-pointer group" title="Clique para abrir menu">
                            <div class="flex items-center gap-2">
                                <span id="user-name" class="text-xs font-bold text-slate-900">Administrador</span>
                                <svg class="w-4 h-4 text-slate-400 group-hover:text-slate-600 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" /></svg>
                            </div>
                            <span id="user-role" class="text-[10px] font-bold text-slate-400 uppercase">Admin</span>
                            <div id="user-dropdown" class="hidden absolute right-0 top-full mt-2 w-48 bg-white rounded-xl border border-slate-200 shadow-xl py-2 z-50">
                                <button type="button" id="btn-logout" class="w-full text-left px-4 py-3 text-sm font-bold text-slate-700 hover:bg-slate-100 rounded-lg flex items-center gap-2">
                                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
                                    Sair
                                </button>
                            </div>
                        </div>
                    </div>
                </header>
                </div>

                <div class="flex-grow overflow-y-auto p-10 bg-[#F8FAFC]">
                    <!-- Conteúdo será carregado dinamicamente via JavaScript -->
                    <div id="content-area"></div>
                </div>
            </main>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="hidden fixed bottom-10 right-10 bg-slate-900 text-white px-6 py-4 rounded-2xl shadow-2xl flex items-center gap-4 z-[9999] animate-slide-in">
        <div class="w-8 h-8 bg-emerald-500 rounded-full flex items-center justify-center text-white">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
        </div>
        <div>
            <h4 class="font-bold text-sm">Sucesso</h4>
            <p id="toast-message" class="text-xs text-slate-300"></p>
        </div>
    </div>

    <script>
        window.API_BASE_URL = '<?php echo rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/") . "/api/"; ?>';
    </script>
    <script src="assets/js/app.js?v=<?php echo time(); ?>"></script>
</body>
</html>
