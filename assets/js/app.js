/**
 * MapsProspector Pro - JavaScript Principal (interface única PHP/XAMPP)
 * Histórico de pesquisas: gravado e listado via banco de dados (api/search.php e api/history.php).
 */

var API_BASE = (typeof window.API_BASE_URL !== 'undefined' ? window.API_BASE_URL : 'api/');

// Estado da aplicação (histórico vem do banco via history.php)
const AppState = {
    user: null,
    tenant: null,
    tokenUsage: null,  // { used, limit, limitReached } para aviso de limite de tokens
    config: null,
    activeTab: 'dashboard',
    userCoords: null,
    userLocationName: '',
    locStatus: 'idle', // idle | loading | success | error
    leads: [],
    searchId: null,  // ID da pesquisa atual (para desbloqueio; dados vêm bloqueados)
    history: [],     // preenchido por loadHistory() -> api/history.php (banco)
    visibleCount: 12,
    currentSearch: { query: '', location: '', tag: '' }  // contexto da pesquisa atual (para exportar)
};

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    checkAuth();
    setupEventListeners();
    refreshLocation();
});

// Verifica autenticação
async function checkAuth() {
    try {
        const res = await fetch(API_BASE + 'auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'check' })
        });
        
        if (res.ok) {
            const data = await res.json();
            if (data.success) {
                AppState.user = data.data.user;
                AppState.tenant = data.data.tenant || null;
                AppState.tokenUsage = data.data.tokenUsage || null;
                showDashboard();
                loadConfig();
            } else {
                showLogin();
            }
        } else {
            showLogin();
        }
    } catch (e) {
        console.error('Erro ao verificar auth:', e);
        showLogin();
    }
}

// Login
async function handleLogin(e) {
    if (e && e.preventDefault) e.preventDefault();
    const emailInput = document.getElementById('login-email');
    const errorEl = document.getElementById('login-error');
    const btn = document.getElementById('btn-login');
    const email = (emailInput && emailInput.value ? emailInput.value.trim().toLowerCase() : '') || '';
    if (errorEl) {
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
    }
    if (!email || !email.includes('@')) {
        if (errorEl) {
            errorEl.textContent = 'Por favor, insira um e-mail válido.';
            errorEl.classList.remove('hidden');
        }
        return;
    }
    btn.disabled = true;
    btn.innerHTML = '<span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin inline-block"></span> Entrando...';
    try {
        const res = await fetch(API_BASE + 'auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', email: email })
        });
        const data = await res.json();
        if (data.success) {
            AppState.user = data.data.user;
            AppState.tenant = data.data.tenant || null;
            AppState.tokenUsage = data.data.tokenUsage || null;
            showDashboard();
            loadConfig();
        } else {
            if (errorEl) {
                errorEl.textContent = data.error || 'Falha no login. Tente novamente.';
                errorEl.classList.remove('hidden');
            } else {
                alert('Erro ao fazer login: ' + (data.error || 'Tente novamente.'));
            }
        }
    } catch (err) {
        if (errorEl) {
            errorEl.textContent = 'Erro de conexão. Verifique o servidor.';
            errorEl.classList.remove('hidden');
        } else {
            alert('Erro de conexão: ' + (err && err.message ? err.message : 'Verifique o servidor.'));
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Entrar <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>';
    }
}

// Mostra/Oculta telas
function showLogin() {
    document.getElementById('login-screen').classList.remove('hidden');
    document.getElementById('dashboard').classList.add('hidden');
}

function renderHeaderTokenWarning() {
    var tokenBanner = document.getElementById('header-token-warning');
    if (!tokenBanner) return;
    var showTokenWarning = AppState.tokenUsage && AppState.tokenUsage.limitReached || AppState.tenant && AppState.tenant.status === 'suspended';
    if (showTokenWarning) {
        tokenBanner.classList.remove('hidden');
        tokenBanner.classList.add('flex');
    } else {
        tokenBanner.classList.add('hidden');
        tokenBanner.classList.remove('flex');
    }
}

function showDashboard() {
    document.getElementById('login-screen').classList.add('hidden');
    document.getElementById('dashboard').classList.remove('hidden');
    renderHeaderTokenWarning();
    if (AppState.user) {
        document.getElementById('user-name').textContent = AppState.user.name;
        var roleEl = document.getElementById('user-role');
        if (roleEl) {
            var roleLabels = { super_admin: 'Super Admin', admin: 'Admin', user: 'Usuário' };
            roleEl.textContent = roleLabels[AppState.user.profile] || AppState.user.profile || 'Usuário';
        }
        var isSuperAdmin = String(AppState.user.profile).toLowerCase() === 'super_admin';
        var blockAdmin = document.getElementById('nav-block-administracao');
        var dividerNormal = document.getElementById('nav-divider-normal');
        if (blockAdmin) {
            if (isSuperAdmin) {
                blockAdmin.classList.remove('hidden');
            } else {
                blockAdmin.classList.add('hidden');
            }
        }
        if (dividerNormal) {
            if (isSuperAdmin) {
                dividerNormal.classList.add('hidden');
            } else {
                dividerNormal.classList.remove('hidden');
            }
        }
        var hasTenant = AppState.tenant && AppState.tenant.id;
        var btnMeuPlano = document.getElementById('nav-btn-choose-plan');
        if (btnMeuPlano) {
            if (hasTenant && !isSuperAdmin) {
                btnMeuPlano.classList.remove('hidden');
            } else {
                btnMeuPlano.classList.add('hidden');
            }
        }
    }
    setActiveTab(AppState.activeTab);
}

function logout() {
    fetch(API_BASE + 'auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' }),
        credentials: 'same-origin'
    }).then(function() {
        AppState.user = null;
        AppState.tenant = null;
        AppState.tokenUsage = null;
        var tokenBanner = document.getElementById('header-token-warning');
        if (tokenBanner) tokenBanner.classList.add('hidden');
        document.getElementById('user-dropdown').classList.add('hidden');
        showLogin();
    }).catch(function() {
        AppState.user = null;
        AppState.tenant = null;
        AppState.tokenUsage = null;
        showLogin();
    });
}

// Cadastro de empresa (toggle + submit)
function showCadastro() {
    const box = document.getElementById('cadastro-box');
    const success = document.getElementById('cadastro-success');
    if (box) box.classList.remove('hidden');
    if (success) { success.classList.add('hidden'); success.textContent = ''; }
}
function hideCadastro() {
    const box = document.getElementById('cadastro-box');
    if (box) box.classList.add('hidden');
}
async function handleCadastroSubmit(e) {
    if (e && e.preventDefault) e.preventDefault();
    const company = (document.getElementById('reg-company') && document.getElementById('reg-company').value || '').trim();
    const email = (document.getElementById('reg-email') && document.getElementById('reg-email').value || '').trim().toLowerCase();
    const name = (document.getElementById('reg-name') && document.getElementById('reg-name').value || '').trim();
    const errorEl = document.getElementById('reg-error');
    const btn = document.getElementById('btn-cadastro');
    if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }
    if (!company) { if (errorEl) { errorEl.textContent = 'Nome da empresa é obrigatório.'; errorEl.classList.remove('hidden'); } return; }
    if (!email || !email.includes('@')) { if (errorEl) { errorEl.textContent = 'E-mail do administrador é obrigatório e deve ser válido.'; errorEl.classList.remove('hidden'); } return; }
    if (btn) btn.disabled = true;
    try {
        const res = await fetch(API_BASE + 'register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ companyName: company, adminEmail: email, adminName: name || undefined })
        });
        const data = await res.json();
        if (data.success) {
            hideCadastro();
            const successEl = document.getElementById('cadastro-success');
            const loginEmail = document.getElementById('login-email');
            if (successEl) { successEl.textContent = data.message || 'Empresa cadastrada. Faça login com seu e-mail.'; successEl.classList.remove('hidden'); }
            if (loginEmail) loginEmail.value = email;
            if (document.getElementById('reg-company')) document.getElementById('reg-company').value = '';
            if (document.getElementById('reg-email')) document.getElementById('reg-email').value = '';
            if (document.getElementById('reg-name')) document.getElementById('reg-name').value = '';
        } else {
            if (errorEl) { errorEl.textContent = data.error || 'Erro ao cadastrar.'; errorEl.classList.remove('hidden'); }
        }
    } catch (err) {
        if (errorEl) { errorEl.textContent = 'Erro de conexão. Verifique o servidor.'; errorEl.classList.remove('hidden'); }
    } finally {
        if (btn) btn.disabled = false;
    }
}

// Event Listeners
function setupEventListeners() {
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', handleLogin);
    } else {
        const btn = document.getElementById('btn-login');
        if (btn) btn.addEventListener('click', (e) => { e.preventDefault(); handleLogin(); });
    }
    const linkCadastro = document.getElementById('link-cadastro');
    if (linkCadastro) linkCadastro.addEventListener('click', showCadastro);
    const btnCadastroVoltar = document.getElementById('btn-cadastro-voltar');
    if (btnCadastroVoltar) btnCadastroVoltar.addEventListener('click', hideCadastro);
    const formCadastro = document.getElementById('form-cadastro');
    if (formCadastro) formCadastro.addEventListener('submit', handleCadastroSubmit);

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            setActiveTab(tab);
        });
    });
    
    // GPS
    document.getElementById('btn-refresh-gps').addEventListener('click', refreshLocation);

    // Menu do usuário (dropdown + Sair)
    var headerUserArea = document.getElementById('header-user-area');
    var userDropdown = document.getElementById('user-dropdown');
    var btnLogout = document.getElementById('btn-logout');
    if (headerUserArea && userDropdown) {
        headerUserArea.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
        });
    }
    if (btnLogout) {
        btnLogout.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.add('hidden');
            logout();
        });
    }
    document.addEventListener('click', function() {
        if (userDropdown && !userDropdown.classList.contains('hidden')) {
            userDropdown.classList.add('hidden');
        }
    });
}

// Dashboard: estatísticas da conta (tokens utilizados / permitidos)
function renderDashboardTab(contentArea) {
    var u = AppState.tokenUsage || { used: 0, limit: 0, limitReached: false };
    var used = u.used || 0;
    var limit = u.limit != null ? u.limit : 0;
    var limitLabel = limit === 0 ? 'Ilimitado' : limit;
    var available = limit === 0 ? 'Ilimitado' : Math.max(0, limit - used);
    var tenantName = (AppState.tenant && AppState.tenant.name) ? AppState.tenant.name : '—';
    var hasTenant = AppState.tenant && AppState.tenant.id;
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto">' +
        '<h3 class="text-2xl font-black text-slate-900 mb-8">Estatísticas da conta</h3>' +
        '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">' +
        '<div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm">' +
        '<div class="flex items-center gap-4 mb-4"><div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center">' +
        '<svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg></div>' +
        '<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Tokens utilizados</p><p class="text-3xl font-black text-slate-900">' + used + '</p></div></div>' +
        '<p class="text-xs text-slate-500">Tokens usados no período: 1 token = 1 página de resultados (até 20 itens por página)</p></div>' +
        '<div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm">' +
        '<div class="flex items-center gap-4 mb-4"><div class="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center">' +
        '<svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg></div>' +
        '<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Tokens permitidos (plano)</p><p class="text-3xl font-black text-slate-900">' + limitLabel + '</p></div></div>' +
        '<p class="text-xs text-slate-500">Limite do plano vinculado à sua empresa neste período</p></div>' +
        '<div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm sm:col-span-2 lg:col-span-1">' +
        '<div class="flex items-center gap-4 mb-4"><div class="w-14 h-14 bg-slate-100 rounded-2xl flex items-center justify-center">' +
        '<svg class="w-7 h-7 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg></div>' +
        '<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Disponível</p><p class="text-3xl font-black text-slate-900">' + available + '</p></div></div>' +
        '<p class="text-xs text-slate-500">Tokens restantes para novas buscas neste período</p></div>' +
        '</div>' +
        '<div class="mt-8 p-6 bg-slate-50 rounded-2xl border border-slate-200">' +
        '<p class="text-sm font-bold text-slate-700"><span class="text-slate-500">Empresa:</span> ' + tenantName + '</p>' +
        (!hasTenant ? '<p class="text-xs text-slate-500 mt-2">Conta plataforma (Super Admin) — não há limite de tokens por empresa.</p>' : '') +
        '</div></div>';
}

// Solicitar Créditos (Normal): usuário solicita X créditos
function renderRequestCreditsTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-2xl mx-auto">' +
        '<h3 class="text-2xl font-black text-slate-900 mb-6">Solicitar créditos</h3>' +
        '<p class="text-sm text-slate-500 mb-8">Solicite créditos (tokens) adicionais para sua empresa. O administrador da plataforma analisará e poderá aprovar ou recusar.</p>' +
        '<div id="request-credits-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>' +
        '<div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm mb-10">' +
        '<form id="request-credits-form" class="space-y-4">' +
        '<div><div class="flex flex-wrap items-end gap-4">' +
        '<div class="flex-1 min-w-[140px]"><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Quantidade de créditos (tokens)</label>' +
        '<input id="request-credits-amount" type="number" min="100" max="10000" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" placeholder="Ex: 100" required /></div>' +
        '<div id="request-credits-total-wrap" class="flex-1 min-w-[140px] pb-1 hidden"><p class="text-[10px] font-black text-slate-500 uppercase mb-1">Valor a pagar</p>' +
        '<p id="request-credits-total-line" class="text-lg font-bold text-blue-600"><span id="request-credits-total-value">—</span></p></div></div>' +
        '<div class="mt-3"><label class="block text-[10px] font-bold text-slate-500 uppercase mb-2">Ou arraste até 10.000</label>' +
        '<input id="request-credits-slider" type="range" min="100" max="10000" step="1" value="100" class="w-full h-3 rounded-full appearance-none bg-slate-200 accent-blue-600 cursor-pointer" />' +
        '<p id="request-credits-slider-label" class="text-[10px] text-slate-400 mt-1 text-right">100 créditos</p></div>' +
        '<p class="text-[10px] text-slate-400 mt-1">Cada crédito = 1 busca no período atual.</p>' +
        '<p id="request-credits-price-line" class="text-sm font-medium text-slate-600 mt-2 hidden">Valor avulso: <span class="font-bold text-slate-800"></span> por crédito</p></div>' +
        '<button type="submit" id="request-credits-submit" class="w-full py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Enviar solicitação</button>' +
        '</form></div>' +
        '<h4 class="text-lg font-black text-slate-900 mb-4">Minhas solicitações</h4>' +
        '<div id="request-credits-list"></div></div>';
    setupRequestCreditsEvents();
    loadRequestCreditsList();
}

function setupRequestCreditsEvents() {
    var form = document.getElementById('request-credits-form');
    if (!form) return;
    var amountEl = document.getElementById('request-credits-amount');
    var sliderEl = document.getElementById('request-credits-slider');
    var sliderLabel = document.getElementById('request-credits-slider-label');
    var totalWrap = document.getElementById('request-credits-total-wrap');
    var totalValue = document.getElementById('request-credits-total-value');

    function getAmountNum() {
        var n = parseInt(amountEl.value, 10);
        if (isNaN(n) || n < 100) return 100;
        if (n > 10000) return 10000;
        return n;
    }
    function getPricePerCredit() {
        return parseFloat(form.dataset.pricePerCredit || '0', 10);
    }
    function syncFromInput() {
        var num = getAmountNum();
        var pricePerCredit = getPricePerCredit();
        if (sliderEl) sliderEl.value = num;
        if (sliderLabel) sliderLabel.textContent = num.toLocaleString('pt-BR') + ' créditos';
        if (totalWrap && totalValue) {
            if (pricePerCredit > 0 && num >= 100) {
                totalValue.textContent = 'R$ ' + (num * pricePerCredit).toFixed(2).replace('.', ',');
                totalWrap.classList.remove('hidden');
            } else {
                totalValue.textContent = '—';
            }
        }
    }
    function syncFromSlider() {
        var num = parseInt(sliderEl.value, 10);
        var pricePerCredit = getPricePerCredit();
        amountEl.value = num;
        if (sliderLabel) sliderLabel.textContent = num.toLocaleString('pt-BR') + ' créditos';
        if (totalWrap && totalValue) {
            if (pricePerCredit > 0 && num >= 100) {
                totalValue.textContent = 'R$ ' + (num * pricePerCredit).toFixed(2).replace('.', ',');
                totalWrap.classList.remove('hidden');
            } else {
                totalValue.textContent = '—';
            }
        }
    }
    if (amountEl) {
        amountEl.addEventListener('input', syncFromInput);
        amountEl.addEventListener('change', syncFromInput);
    }
    if (sliderEl) sliderEl.addEventListener('input', syncFromSlider);

    form.onsubmit = function(e) {
        e.preventDefault();
        var num = getAmountNum();
        if (num < 100 || num > 10000) {
            var err = document.getElementById('request-credits-error');
            err.textContent = 'Informe uma quantidade entre 100 e 10.000.';
            err.classList.remove('hidden');
            return;
        }
        document.getElementById('request-credits-error').classList.add('hidden');
        var btn = document.getElementById('request-credits-submit');
        btn.disabled = true;
        btn.textContent = 'Enviando...';
        fetch(API_BASE + 'credit-requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ tokensRequested: num })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                amountEl.value = '';
                if (sliderEl) sliderEl.value = 100;
                if (sliderLabel) sliderLabel.textContent = '100 créditos';
                if (totalValue) totalValue.textContent = '—';
                if (totalWrap) totalWrap.classList.add('hidden');
                loadRequestCreditsList();
            } else {
                var err = document.getElementById('request-credits-error');
                err.textContent = data.error || 'Erro ao enviar solicitação.';
                err.classList.remove('hidden');
            }
        }).catch(function() {
            var err = document.getElementById('request-credits-error');
            err.textContent = 'Erro de conexão.';
            err.classList.remove('hidden');
        }).finally(function() {
            btn.disabled = false;
            btn.textContent = 'Enviar solicitação';
        });
    };
}

function loadRequestCreditsList() {
    var listEl = document.getElementById('request-credits-list');
    var form = document.getElementById('request-credits-form');
    var priceLine = document.getElementById('request-credits-price-line');
    if (!listEl) return;
    fetch(API_BASE + 'credit-requests.php', { credentials: 'include' }).then(function(r) { return r.json(); }).then(function(data) {
        var pricePerCredit = (data.success && data.data && data.data.hasOwnProperty('pricePerCredit')) ? parseFloat(data.data.pricePerCredit, 10) : 0;
        if (isNaN(pricePerCredit)) pricePerCredit = 0;
        if (form) form.dataset.pricePerCredit = String(pricePerCredit);
        if (priceLine) {
            if (pricePerCredit > 0) {
                var priceStr = 'R$ ' + pricePerCredit.toFixed(2).replace('.', ',');
                priceLine.querySelector('span').textContent = priceStr + ' por crédito';
                priceLine.classList.remove('hidden');
            } else {
                priceLine.classList.add('hidden');
            }
        }
        if (!data.success || !data.data || !data.data.items) {
            listEl.innerHTML = '<div class="py-12 text-center border-2 border-dashed border-slate-200 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhuma solicitação</p></div>';
            return;
        }
        var items = data.data.items;
        if (items.length === 0) {
            listEl.innerHTML = '<div class="py-12 text-center border-2 border-dashed border-slate-200 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhuma solicitação</p></div>';
            return;
        }
        var html = '<div class="space-y-4">';
        items.forEach(function(r) {
            var statusLabel = r.status === 'pending' ? 'Pendente' : r.status === 'approved' ? 'Aprovado' : 'Recusado';
            var statusClass = r.status === 'pending' ? 'text-amber-600' : r.status === 'approved' ? 'text-emerald-600' : 'text-red-600';
            var dateStr = r.createdAt ? new Date(r.createdAt).toLocaleString('pt-BR') : '';
            html += '<div class="bg-white p-6 rounded-[2rem] border border-slate-200 flex items-center justify-between">';
            var valueStr = pricePerCredit > 0 ? ' · <span class="text-blue-600 font-bold">R$ ' + (r.tokensRequested * pricePerCredit).toFixed(2).replace('.', ',') + '</span>' : '';
            html += '<div><p class="font-extrabold text-slate-900">' + r.tokensRequested + ' créditos' + valueStr + '</p>';
            html += '<p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + dateStr + ' · <span class="' + statusClass + '">' + statusLabel + '</span></p></div>';
            if (r.status === 'pending') html += '<span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-bold">Aguardando</span>';
            html += '</div>';
        });
        html += '</div>';
        listEl.innerHTML = html;
        var amountEl = document.getElementById('request-credits-amount');
        var totalWrap = document.getElementById('request-credits-total-wrap');
        var totalValue = document.getElementById('request-credits-total-value');
        if (pricePerCredit > 0 && totalWrap && totalValue) {
            totalWrap.classList.remove('hidden');
            var num = parseInt(amountEl ? amountEl.value : '', 10);
            totalValue.textContent = (!isNaN(num) && num >= 100) ? 'R$ ' + (num * pricePerCredit).toFixed(2).replace('.', ',') : '—';
        }
    }).catch(function() {
        listEl.innerHTML = '<div class="py-12 text-center border-2 border-dashed border-slate-200 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Erro ao carregar</p></div>';
    });
}

// Meu plano: usuário escolhe plano e solicita; fica pendente até Super Admin liberar
function renderChoosePlanTab(contentArea) {
    var currentPlanName = (AppState.tenant && AppState.tenant.planName) ? AppState.tenant.planName : '';
    var currentPlanId = (AppState.tenant && AppState.tenant.planId) ? String(AppState.tenant.planId) : '';
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto">' +
        '<h3 class="text-2xl font-black text-slate-900 mb-2">Meu plano</h3>' +
        '<p class="text-sm text-slate-500 mb-8">Escolha um plano para sua empresa. Após solicitar, o administrador confirmará e seu plano será atualizado.</p>' +
        (currentPlanName ? '<div class="mb-8 p-6 bg-slate-50 rounded-2xl border border-slate-200"><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Plano atual</p><p class="text-lg font-bold text-slate-900">' + currentPlanName + '</p></div>' : '') +
        '<div id="choose-plan-pending" class="hidden mb-8 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium"></div>' +
        '<div id="choose-plan-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>' +
        '<div id="choose-plan-spinner" class="py-24 text-center"><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block"></span></div>' +
        '<div id="choose-plan-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10 hidden"></div>' +
        '<div id="choose-plan-history" class="hidden mt-10"><h4 class="text-lg font-black text-slate-900 mb-4">Histórico de solicitações</h4><div id="choose-plan-history-list"></div></div></div>';
    loadChoosePlanData(currentPlanId);
}

function loadChoosePlanData(currentPlanId) {
    var spinnerEl = document.getElementById('choose-plan-spinner');
    var gridEl = document.getElementById('choose-plan-grid');
    var errorEl = document.getElementById('choose-plan-error');
    var pendingEl = document.getElementById('choose-plan-pending');
    var historyWrap = document.getElementById('choose-plan-history');
    var historyList = document.getElementById('choose-plan-history-list');
    if (!gridEl) return;
    Promise.all([
        fetch(API_BASE + 'plans-public.php', { credentials: 'include' }).then(function(r) { return r.json(); }),
        fetch(API_BASE + 'plan-requests.php', { credentials: 'include' }).then(function(r) { return r.json(); })
    ]).then(function(results) {
        if (spinnerEl) spinnerEl.classList.add('hidden');
        var plansData = results[0];
        var requestsData = results[1];
        var plans = (plansData.success && plansData.data && plansData.data.items) ? plansData.data.items : [];
        var requests = (requestsData.success && requestsData.data && requestsData.data.items) ? requestsData.data.items : [];
        var pendingReq = requests.filter(function(r) { return r.status === 'pending'; })[0];
        if (pendingReq) {
            pendingEl.textContent = 'Solicitação pendente: ' + (pendingReq.planName || 'Plano') + ' — aguardando confirmação do administrador.';
            pendingEl.classList.remove('hidden');
        } else {
            pendingEl.classList.add('hidden');
        }
        var hasPending = !!pendingReq;
        var html = '';
        plans.forEach(function(p) {
            var isCurrent = currentPlanId === String(p.id);
            var canRequest = !hasPending && !isCurrent;
            var priceText = (p.priceMonthly != null && parseFloat(p.priceMonthly) > 0) ? ('R$ ' + parseFloat(p.priceMonthly).toFixed(2).replace('.', ',')) : '—';
            var tokenText = (p.tokenLimit || 0).toLocaleString('pt-BR') + ' tokens';
            html += '<div class="bg-white p-6 rounded-[2rem] border-2 ' + (isCurrent ? 'border-blue-400 bg-blue-50/50' : 'border-slate-200') + '">';
            html += '<div class="flex items-center gap-4 mb-4"><div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-lg font-black text-blue-600">' + (p.tokenLimit >= 1000 ? (p.tokenLimit / 1000) + 'K' : p.tokenLimit) + '</div>';
            html += '<div><h4 class="font-extrabold text-slate-900 text-lg">' + (p.name || '') + '</h4><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + tokenText + ' · ' + (p.period === 'yearly' ? 'ano' : 'mês') + '</p></div></div>';
            html += '<p class="text-2xl font-black text-slate-900 mb-4">' + priceText + '<span class="text-sm font-bold text-slate-400">/mês</span></p>';
            if (isCurrent) {
                html += '<p class="text-sm font-bold text-blue-600">Plano atual</p>';
            } else if (canRequest) {
                html += '<button type="button" class="btn-request-plan w-full py-3 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700" data-plan-id="' + p.id + '">Solicitar este plano</button>';
            } else {
                html += '<p class="text-sm text-slate-400">Aguarde a confirmação da solicitação em andamento.</p>';
            }
            html += '</div>';
        });
        gridEl.innerHTML = html;
        gridEl.classList.remove('hidden');
        gridEl.querySelectorAll('.btn-request-plan').forEach(function(btn) {
            btn.onclick = function() {
                var planId = btn.getAttribute('data-plan-id');
                if (!planId) return;
                btn.disabled = true;
                btn.textContent = 'Enviando...';
                if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }
                fetch(API_BASE + 'plan-requests.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ planId: parseInt(planId, 10) })
                }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data.success) {
                        loadChoosePlanData(currentPlanId);
                        var toast = document.getElementById('toast');
                        if (toast) {
                            var msg = document.getElementById('toast-message');
                            if (msg) msg.textContent = data.message || 'Solicitação enviada.';
                            toast.classList.remove('hidden');
                            setTimeout(function() { toast.classList.add('hidden'); }, 3000);
                        }
                    } else {
                        if (errorEl) { errorEl.textContent = data.error || 'Erro ao enviar.'; errorEl.classList.remove('hidden'); }
                    }
                }).catch(function() {
                    if (errorEl) { errorEl.textContent = 'Erro de conexão.'; errorEl.classList.remove('hidden'); }
                }).finally(function() { btn.disabled = false; btn.textContent = 'Solicitar este plano'; });
            };
        });
        if (requests.length > 0) {
            historyWrap.classList.remove('hidden');
            var hHtml = '<div class="space-y-3">';
            requests.forEach(function(r) {
                var statusLabel = r.status === 'pending' ? 'Pendente' : r.status === 'approved' ? 'Confirmado' : 'Recusado';
                var statusClass = r.status === 'pending' ? 'text-amber-600' : r.status === 'approved' ? 'text-emerald-600' : 'text-red-600';
                var dateStr = r.reviewedAt ? new Date(r.reviewedAt).toLocaleString('pt-BR') : (r.createdAt ? new Date(r.createdAt).toLocaleString('pt-BR') : '');
                hHtml += '<div class="bg-white p-4 rounded-xl border border-slate-200 flex items-center justify-between"><span class="font-bold text-slate-800">' + (r.planName || 'Plano') + '</span><span class="text-xs font-bold ' + statusClass + '">' + statusLabel + (dateStr ? ' · ' + dateStr : '') + '</span></div>';
            });
            hHtml += '</div>';
            historyList.innerHTML = hHtml;
        } else {
            if (historyWrap) historyWrap.classList.add('hidden');
        }
    }).catch(function() {
        if (spinnerEl) spinnerEl.classList.add('hidden');
        if (errorEl) { errorEl.textContent = 'Erro ao carregar planos.'; errorEl.classList.remove('hidden'); }
    });
}

// Créditos (Administração): lista solicitações, Aceitar / Recusar
function renderCreditsAdminTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto">' +
        '<div class="flex justify-between items-center mb-10">' +
        '<h3 class="text-2xl font-black text-slate-900">Solicitações de créditos</h3>' +
        '<button type="button" id="credits-admin-refresh" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2">Atualizar</button></div>' +
        '<div id="credits-admin-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>' +
        '<div id="credits-admin-list"></div></div>';
    document.getElementById('credits-admin-refresh').onclick = function() { loadCreditsAdminList(); };
    loadCreditsAdminList();
}

function loadCreditsAdminList() {
    var listEl = document.getElementById('credits-admin-list');
    var errEl = document.getElementById('credits-admin-error');
    if (!listEl) return;
    listEl.innerHTML = '<div class="py-12 text-center"><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block"></span></div>';
    if (errEl) errEl.classList.add('hidden');
    fetch(API_BASE + 'credit-requests.php', { credentials: 'include' }).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success || !data.data || !data.data.items) {
            listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhuma solicitação de créditos</p></div>';
            return;
        }
        var items = data.data.items;
        if (items.length === 0) {
            listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhuma solicitação de créditos</p></div>';
            return;
        }
        var html = '<div class="space-y-4">';
        items.forEach(function(r) {
            var statusLabel = r.status === 'pending' ? 'Pendente' : r.status === 'approved' ? 'Aprovado' : 'Recusado';
            var statusClass = r.status === 'pending' ? 'text-amber-600' : r.status === 'approved' ? 'text-emerald-600' : 'text-red-600';
            var dateStr = r.createdAt ? new Date(r.createdAt).toLocaleString('pt-BR') : '';
            var reviewedStr = r.reviewedAt ? ' · ' + new Date(r.reviewedAt).toLocaleString('pt-BR') : '';
            var who = (r.requestedByName || r.requestedByEmail || '—');
            html += '<div class="bg-white p-6 rounded-[2rem] border border-slate-200 flex flex-wrap items-center justify-between gap-4">';
            html += '<div class="flex items-center gap-6">';
            html += '<div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-xl font-black text-slate-600">' + (r.tenantName ? r.tenantName.charAt(0).toUpperCase() : 'E') + '</div>';
            html += '<div><h4 class="font-extrabold text-slate-900 text-lg">' + (r.tenantName || 'Empresa') + '</h4>';
            html += '<p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + who + ' · ' + r.tokensRequested + ' créditos · ' + dateStr + '</p>';
            html += '<p class="text-xs font-bold mt-1 ' + statusClass + '">' + statusLabel + reviewedStr + '</p></div></div>';
            if (r.status === 'pending') {
                html += '<div class="flex items-center gap-2"><button type="button" class="btn-credit-approve bg-emerald-100 text-emerald-700 hover:bg-emerald-200 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + r.id + '">Aceitar</button>';
                html += '<button type="button" class="btn-credit-reject bg-red-100 text-red-700 hover:bg-red-200 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + r.id + '">Recusar</button></div>';
            }
            html += '</div>';
        });
        html += '</div>';
        listEl.innerHTML = html;
        listEl.querySelectorAll('.btn-credit-approve').forEach(function(btn) {
            btn.onclick = function() { reviewCreditRequest(btn.dataset.id, 'approved'); };
        });
        listEl.querySelectorAll('.btn-credit-reject').forEach(function(btn) {
            btn.onclick = function() { reviewCreditRequest(btn.dataset.id, 'rejected'); };
        });
    }).catch(function() {
        listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Erro ao carregar</p></div>';
    });
}

function reviewCreditRequest(id, status) {
    var errEl = document.getElementById('credits-admin-error');
    if (errEl) errEl.classList.add('hidden');
    fetch(API_BASE + 'credit-requests.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id: id, status: status })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) loadCreditsAdminList();
        else if (errEl) { errEl.textContent = data.error || 'Erro ao processar.'; errEl.classList.remove('hidden'); }
    }).catch(function() {
        if (errEl) { errEl.textContent = 'Erro de conexão.'; errEl.classList.remove('hidden'); }
    });
}

// Tabs
function setActiveTab(tab) {
    AppState.activeTab = tab;
    
    // Atualiza botões
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.tab === tab) {
            btn.className = 'tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm bg-blue-600 text-white shadow-xl shadow-blue-900/30';
        } else {
            btn.className = 'tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-800/50 text-slate-400';
        }
    });
    
    // Atualiza título
    const titles = {
        dashboard: 'Dashboard',
        search: 'Prospecção Inteligente',
        history: 'Arquivo de Buscas',
        'request-credits': 'Solicitar Créditos',
        'choose-plan': 'Meu plano',
        'saas-config': 'Empresa SaaS',
        plans: 'Planos',
        companies: 'Empresas',
        credits: 'Créditos',
        settings: 'Integração CRM'
    };
    document.getElementById('page-title').textContent = titles[tab] || 'Dashboard';
    
    loadTab(tab);
}

// Carrega conteúdo da tab
function loadTab(tab) {
    const contentArea = document.getElementById('content-area');
    
    if (tab === 'dashboard') {
        renderDashboardTab(contentArea);
    } else if (tab === 'request-credits') {
        renderRequestCreditsTab(contentArea);
    } else if (tab === 'credits') {
        var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
        if (isSuperAdmin) renderCreditsAdminTab(contentArea);
        else contentArea.innerHTML = '<div class="max-w-4xl mx-auto py-24 text-center text-slate-500 font-bold">Acesso restrito ao administrador da plataforma.</div>';
    } else if (tab === 'search') {
        contentArea.innerHTML = getProspectingHTML();
        setupProspectingEvents();
    } else if (tab === 'history') {
        loadHistory();
    } else if (tab === 'choose-plan') {
        var isSuperAdminChoose = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
        if (isSuperAdminChoose) {
            contentArea.innerHTML = '<div class="max-w-4xl mx-auto py-24 text-center"><p class="text-slate-600 font-bold mb-2">Conta Super Admin — ilimitada</p><p class="text-sm text-slate-500">Não é possível alterar plano; sua conta não possui limite de tokens. Cuidado, pois API de Scrapy será contabilizada.</p></div>';
        } else {
            renderChoosePlanTab(contentArea);
        }
    } else if (tab === 'saas-config') {
        renderSaasConfigTab(contentArea);
    } else if (tab === 'plans') {
        loadPlansTab(contentArea);
    } else if (tab === 'companies') {
        loadCompaniesTab(contentArea);
    } else if (tab === 'settings') {
        contentArea.innerHTML = getSettingsHTML();
        var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
        var blockScraper = document.getElementById('block-scraper-api-key-admin');
        if (blockScraper && !isSuperAdmin) blockScraper.style.display = 'none';
        setupSettingsEvents();
        loadSettingsForm();
    }
}

// Empresa SaaS (super_admin): nome da empresa, valor plano mensal, valor plano avulso
function renderSaasConfigTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-2xl mx-auto">' +
        '<h3 class="text-2xl font-black text-slate-900 mb-2">Configuração da empresa SaaS</h3>' +
        '<p class="text-sm text-slate-500 mb-8">Configure o nome da empresa e o valor por crédito avulso (usado em Solicitar créditos).</p>' +
        '<div id="saas-config-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>' +
        '<div id="saas-config-success" class="hidden mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-700 text-sm font-medium"></div>' +
        '<div class="bg-white p-8 rounded-[2rem] border border-slate-200 shadow-sm">' +
        '<form id="saas-config-form" class="space-y-6">' +
        '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Nome da empresa SaaS</label>' +
        '<input id="saas-config-company" type="text" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" placeholder="Ex: MapsProspector Pro" /></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Valor avulso por crédito (R$)</label>' +
        '<input id="saas-config-avulso" type="text" inputmode="decimal" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" placeholder="Ex: 2,00" />' +
        '<p class="text-[10px] text-slate-400 mt-1">Este valor é usado em Solicitar créditos para calcular o total a pagar (quantidade × valor por crédito).</p></div>' +
        '<button type="submit" id="saas-config-submit" class="w-full py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Salvar configurações</button>' +
        '</form></div></div>';
    loadSaasConfigForm();
    setupSaasConfigEvents();
}

function loadSaasConfigForm() {
    fetch(API_BASE + 'platform-config.php', { credentials: 'include' }).then(function(r) { return r.json(); }).then(function(data) {
        if (!data.success || !data.data) return;
        var d = data.data;
        var companyEl = document.getElementById('saas-config-company');
        var avulsoEl = document.getElementById('saas-config-avulso');
        if (companyEl) companyEl.value = d.saasCompanyName || '';
        if (avulsoEl) avulsoEl.value = (d.creditPriceAvulso != null && d.creditPriceAvulso > 0) ? String(d.creditPriceAvulso) : '';
    }).catch(function() {});
}

function setupSaasConfigEvents() {
    var form = document.getElementById('saas-config-form');
    if (!form) return;
    form.onsubmit = function(e) {
        e.preventDefault();
        var companyEl = document.getElementById('saas-config-company');
        var avulsoEl = document.getElementById('saas-config-avulso');
        var avulso = avulsoEl && avulsoEl.value ? parseFloat(String(avulsoEl.value).replace(',', '.')) : 0;
        if (avulso < 0) {
            var err = document.getElementById('saas-config-error');
            if (err) { err.textContent = 'O valor avulso não pode ser negativo.'; err.classList.remove('hidden'); }
            return;
        }
        document.getElementById('saas-config-error').classList.add('hidden');
        document.getElementById('saas-config-success').classList.add('hidden');
        var btn = document.getElementById('saas-config-submit');
        if (btn) { btn.disabled = true; btn.textContent = 'Salvando...'; }
        fetch(API_BASE + 'platform-config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                saasCompanyName: companyEl ? companyEl.value.trim() : '',
                creditPriceAvulso: avulso
            })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                var successEl = document.getElementById('saas-config-success');
                if (successEl) { successEl.textContent = data.message || 'Configurações salvas.'; successEl.classList.remove('hidden'); }
            } else {
                var err = document.getElementById('saas-config-error');
                if (err) { err.textContent = data.error || 'Erro ao salvar.'; err.classList.remove('hidden'); }
            }
        }).catch(function() {
            var err = document.getElementById('saas-config-error');
            if (err) { err.textContent = 'Erro de conexão.'; err.classList.remove('hidden'); }
        }).finally(function() {
            if (btn) { btn.disabled = false; btn.textContent = 'Salvar configurações'; }
        });
    };
}

// Planos (super_admin): solicitações de plano pendentes (Confirmar/Recusar) + listar planos, criar plano
function loadPlansTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto py-10">' +
        '<div id="plans-requests-section" class="mb-10 hidden"></div>' +
        '<div class="flex justify-between items-center mb-10 flex-wrap gap-4"><h3 class="text-2xl font-black text-slate-900">Planos e limite de tokens <span id="plans-total" class="text-slate-500 font-normal text-lg"></span></h3><div class="flex items-center gap-3"><button type="button" id="plans-refresh-btn" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2">Atualizar lista</button><button type="button" id="plans-create-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-2xl text-xs flex items-center gap-2">Criar plano</button><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block" id="plans-spinner"></span></div></div>' +
        '<div id="plans-list"></div></div>';
    var listEl = document.getElementById('plans-list');
    var spinnerEl = document.getElementById('plans-spinner');
    var requestsSection = document.getElementById('plans-requests-section');
    var refreshBtn = document.getElementById('plans-refresh-btn');
    if (refreshBtn) {
        refreshBtn.onclick = function() {
            loadPlansTab(document.getElementById('content-area'));
        };
    }
    var createBtn = document.getElementById('plans-create-btn');
    if (createBtn) {
        createBtn.onclick = function() {
            showPlansCreateModal(contentArea);
        };
    }
    Promise.all([
        fetch(API_BASE + 'plan-requests.php', { credentials: 'include' }).then(function(r) { return r.json(); }),
        fetch(API_BASE + 'plans.php', { method: 'GET', credentials: 'include', cache: 'no-store' }).then(function(r) {
            if (spinnerEl) spinnerEl.remove();
            return r.json().catch(function() { return { success: false, error: 'Resposta inválida do servidor.' }; });
        })
    ]).then(function(results) {
        var requestsData = results[0];
        var data = results[1];
        var planRequests = (requestsData.success && requestsData.data && requestsData.data.items) ? requestsData.data.items : [];
        var pendingRequests = planRequests.filter(function(r) { return r.status === 'pending'; });
        if (requestsSection && pendingRequests.length > 0) {
            requestsSection.classList.remove('hidden');
            var rHtml = '<h4 class="text-lg font-black text-slate-900 mb-4">Solicitações de plano (pendentes)</h4><div class="space-y-4">';
            pendingRequests.forEach(function(r) {
                var dateStr = r.createdAt ? new Date(r.createdAt).toLocaleString('pt-BR') : '';
                var planInfo = (r.planName || '—') + ' (' + (r.planTokenLimit ? r.planTokenLimit.toLocaleString('pt-BR') : '') + ' tokens · R$ ' + (r.planPrice != null ? Number(r.planPrice).toFixed(2).replace('.', ',') : '0,00') + '/mês)';
                rHtml += '<div class="bg-white p-6 rounded-[2rem] border border-slate-200 flex flex-wrap items-center justify-between gap-4">';
                rHtml += '<div class="flex items-center gap-6"><div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-xl font-black text-slate-600">' + (r.tenantName ? r.tenantName.charAt(0).toUpperCase() : 'E') + '</div>';
                rHtml += '<div><h4 class="font-extrabold text-slate-900 text-lg">' + (r.tenantName || 'Empresa') + '</h4><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + (r.requestedByName || r.requestedByEmail || '—') + ' · Plano: ' + planInfo + '</p><p class="text-xs text-amber-600 font-bold mt-1">' + dateStr + ' · Pendente</p></div></div>';
                rHtml += '<div class="flex items-center gap-2"><button type="button" class="btn-plan-request-approve bg-emerald-100 text-emerald-700 hover:bg-emerald-200 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + r.id + '">Confirmar</button><button type="button" class="btn-plan-request-reject bg-red-100 text-red-700 hover:bg-red-200 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + r.id + '">Recusar</button></div></div>';
            });
            rHtml += '</div>';
            requestsSection.innerHTML = rHtml;
            requestsSection.querySelectorAll('.btn-plan-request-approve').forEach(function(btn) {
                btn.onclick = function() { reviewPlanRequest(btn.getAttribute('data-id'), 'approved', contentArea); };
            });
            requestsSection.querySelectorAll('.btn-plan-request-reject').forEach(function(btn) {
                btn.onclick = function() { reviewPlanRequest(btn.getAttribute('data-id'), 'rejected', contentArea); };
            });
        } else {
            if (requestsSection) requestsSection.classList.add('hidden');
        }
        if (!listEl) return;
        if (!data.success) {
            listEl.innerHTML = '<div class="py-8 p-5 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium">' + (data.error || 'Erro ao carregar planos. Execute database_migration_plans.sql no banco.') + '</div>';
            return;
        }
        var items = (data.data && data.data.items) ? data.data.items : (Array.isArray(data.data) ? data.data : []);
        var total = (data.data && typeof data.data.total === 'number') ? data.data.total : items.length;
        var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
        if (!Array.isArray(items) || items.length === 0) {
            listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhum plano cadastrado</p></div>';
            var totalEl = document.getElementById('plans-total');
            if (totalEl) totalEl.textContent = '0 plano(s)';
            return;
        }
        var html = '<div class="space-y-4">';
        items.forEach(function(p) {
            var tokenText = (p.tokenLimit === 0 || p.tokenLimit === '0') ? 'Ilimitado' : (p.tokenLimit + ' tokens/' + (p.period === 'yearly' ? 'ano' : 'mês'));
            var priceText = (p.priceMonthly != null && parseFloat(p.priceMonthly) > 0) ? (' · R$ ' + parseFloat(p.priceMonthly).toFixed(2).replace('.', ',') + '/mês') : '';
            var statusLabel = p.status === 'active' ? 'Ativo' : 'Inativo';
            var statusClass = p.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600';
            var planId = String(p.id);
            var planName = (p.name || '').replace(/"/g, '&quot;');
            html += '<div class="bg-white p-6 rounded-[2rem] border border-slate-200 flex items-center justify-between flex-wrap gap-4">';
            html += '<div class="flex items-center gap-6">';
            html += '<div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-lg font-black text-blue-600">' + ((p.tokenLimit === 0 || p.tokenLimit === '0') ? '∞' : p.tokenLimit) + '</div>';
            html += '<div><h4 class="font-extrabold text-slate-900 text-lg">' + (p.name || '') + '</h4>';
            html += '<p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + (p.slug || '') + ' · ' + tokenText + priceText + ' · ' + (p.tenantsCount || 0) + ' empresa(s) · <span class="' + statusClass + ' px-2 py-0.5 rounded-full text-xs font-bold">' + statusLabel + '</span></p></div></div>';
            html += '<div class="flex items-center gap-2">';
            html += '<button type="button" class="btn-plan-edit bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + planId + '" data-name="' + planName + '">Editar</button>';
            if (isSuperAdmin && planId !== '1') {
                html += '<button type="button" class="btn-plan-delete text-red-500 hover:bg-red-50 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + planId + '" data-name="' + planName + '" title="Apenas Super Admin pode excluir planos">Excluir</button>';
            }
            html += '</div></div>';
        });
        html += '</div>';
        listEl.innerHTML = html;
        listEl.querySelectorAll('.btn-plan-delete').forEach(function(btn) {
            btn.onclick = function() {
                var id = btn.getAttribute('data-id');
                var name = btn.getAttribute('data-name') || 'este plano';
                if (id === '1') { alert('Não é permitido excluir o plano padrão (Básico).'); return; }
                showPlansDeleteModal(contentArea, id, name);
            };
        });
        listEl.querySelectorAll('.btn-plan-edit').forEach(function(btn) {
            btn.onclick = function() {
                var id = btn.getAttribute('data-id');
                showPlansEditModal(contentArea, id);
            };
        });
        var totalEl = document.getElementById('plans-total');
        if (totalEl) totalEl.textContent = total + ' plano(s)';
    }).catch(function() {
        if (document.getElementById('plans-spinner')) document.getElementById('plans-spinner').remove();
        var list = document.getElementById('plans-list');
        if (list) list.innerHTML = '<div class="py-8 p-5 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium">Erro ao carregar planos. Verifique a conexão e se você está logado como Super Admin.</div>';
    });
}

function reviewPlanRequest(id, status, contentArea) {
    fetch(API_BASE + 'plan-requests.php', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ id: parseInt(id, 10), status: status })
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) {
            loadPlansTab(contentArea);
        }
    });
}

function showPlansDeleteModal(contentArea, planId, planName) {
    var overlay = document.createElement('div');
    overlay.id = 'plans-delete-overlay';
    overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4';
    var safeName = (planName || 'este plano').replace(/"/g, '&quot;');
    overlay.innerHTML = '<div class="bg-white rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
        '<h4 class="text-xl font-black text-slate-900 mb-2">Excluir plano</h4>' +
        '<p class="text-slate-600 text-sm mb-6">Excluir o plano <strong>"' + safeName + '"</strong>? Nenhuma empresa pode estar vinculada a ele.</p>' +
        '<p id="plans-delete-error" class="text-red-600 text-sm font-medium mb-4 hidden"></p>' +
        '<div class="flex gap-3">' +
        '<button type="button" id="plans-delete-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>' +
        '<button type="button" id="plans-delete-confirm" class="flex-1 py-3 rounded-xl bg-red-600 text-white font-bold hover:bg-red-700 disabled:opacity-70">Excluir</button>' +
        '</div></div>';
    overlay.onclick = function(e) {
        if (e.target === overlay) overlay.remove();
    };
    document.body.appendChild(overlay);
    var cancelBtn = document.getElementById('plans-delete-cancel');
    var confirmBtn = document.getElementById('plans-delete-confirm');
    var errorEl = document.getElementById('plans-delete-error');
    cancelBtn.onclick = function() { overlay.remove(); };
    confirmBtn.onclick = function() {
        errorEl.classList.add('hidden');
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'Excluindo...';
        fetch(API_BASE + 'plans.php?id=' + encodeURIComponent(planId), { method: 'DELETE', credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    overlay.remove();
                    if (contentArea) loadPlansTab(contentArea);
                    if (document.getElementById('toast') && document.getElementById('toast-message')) {
                        document.getElementById('toast-message').textContent = res.message || 'Plano removido com sucesso.';
                        document.getElementById('toast').classList.remove('hidden');
                        setTimeout(function() { document.getElementById('toast').classList.add('hidden'); }, 3000);
                    }
                } else {
                    errorEl.textContent = res.error || 'Erro ao excluir.';
                    errorEl.classList.remove('hidden');
                    confirmBtn.disabled = false;
                    confirmBtn.textContent = 'Excluir';
                }
            })
            .catch(function() {
                errorEl.textContent = 'Erro de conexão.';
                errorEl.classList.remove('hidden');
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Excluir';
            });
    };
}

function showPlansCreateModal(contentArea) {
    var overlay = document.createElement('div');
    overlay.id = 'plans-create-overlay';
    overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4';
    overlay.innerHTML = '<div class="bg-white rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
        '<h4 class="text-xl font-black text-slate-900 mb-6">Criar plano</h4>' +
        '<form id="plans-create-form" class="space-y-4">' +
        '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Nome</label>' +
        '<input type="text" id="plan-name" required placeholder="Ex: Básico, Pro" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" /></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Quantos tokens</label>' +
        '<input type="number" id="plan-tokens" min="0" value="100" placeholder="100" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" />' +
        '<p class="text-[10px] text-slate-400 mt-1">0 = ilimitado. 1 token = 1 página de resultados (até 20 itens por página).</p></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Valor mensal (R$)</label>' +
        '<input type="number" id="plan-price" min="0" step="0.01" value="0" placeholder="0,00" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" /></div>' +
        '<p id="plans-create-error" class="text-red-600 text-sm font-medium hidden"></p>' +
        '<div class="flex gap-3 pt-4">' +
        '<button type="button" id="plans-create-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>' +
        '<button type="submit" id="plans-create-submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Criar plano</button>' +
        '</div></form></div>';
    overlay.onclick = function(e) {
        if (e.target === overlay) {
            overlay.remove();
        }
    };
    document.body.appendChild(overlay);
    var form = document.getElementById('plans-create-form');
    var cancelBtn = document.getElementById('plans-create-cancel');
    var errorEl = document.getElementById('plans-create-error');
    cancelBtn.onclick = function() { overlay.remove(); };
    form.onsubmit = function(e) {
        e.preventDefault();
        var name = (document.getElementById('plan-name').value || '').trim();
        var tokenLimit = parseInt(document.getElementById('plan-tokens').value, 10) || 0;
        if (tokenLimit < 0) tokenLimit = 0;
        var priceMonthly = parseFloat((document.getElementById('plan-price').value || '0').toString().replace(',', '.')) || 0;
        if (!name) {
            errorEl.textContent = 'Nome é obrigatório.';
            errorEl.classList.remove('hidden');
            return;
        }
        var slug = name.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'plano';
        errorEl.classList.add('hidden');
        var submitBtn = document.getElementById('plans-create-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Salvando...';
        fetch(API_BASE + 'plans.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ name: name, slug: slug, tokenLimit: tokenLimit, priceMonthly: priceMonthly, period: 'monthly', status: 'active' })
        })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    overlay.remove();
                    if (contentArea) loadPlansTab(contentArea);
                    if (document.getElementById('toast') && document.getElementById('toast-message')) {
                        document.getElementById('toast-message').textContent = 'Plano criado com sucesso.';
                        document.getElementById('toast').classList.remove('hidden');
                        setTimeout(function() { document.getElementById('toast').classList.add('hidden'); }, 3000);
                    }
                } else {
                    errorEl.textContent = data.error || 'Erro ao criar plano.';
                    errorEl.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Criar plano';
                }
            })
            .catch(function() {
                errorEl.textContent = 'Erro de conexão. Tente novamente.';
                errorEl.classList.remove('hidden');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Criar plano';
            });
    };
}

function showPlansEditModal(contentArea, planId) {
    fetch(API_BASE + 'plans.php?id=' + encodeURIComponent(planId), { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.data) {
                alert(data.error || 'Plano não encontrado.');
                return;
            }
            var p = data.data;
            var overlay = document.createElement('div');
            overlay.id = 'plans-edit-overlay';
            overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4';
            overlay.innerHTML = '<div class="bg-white rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
                '<h4 class="text-xl font-black text-slate-900 mb-6">Editar plano</h4>' +
                '<form id="plans-edit-form" class="space-y-4">' +
                '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Nome</label>' +
                '<input type="text" id="plan-edit-name" required placeholder="Ex: Básico, Pro" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" value="' + (p.name || '').replace(/"/g, '&quot;') + '" /></div>' +
                '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Identificador (slug)</label>' +
                '<input type="text" id="plan-edit-slug" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" value="' + (p.slug || '').replace(/"/g, '&quot;') + '" /></div>' +
                '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Limite de tokens</label>' +
                '<input type="number" id="plan-edit-tokens" min="0" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" value="' + (p.tokenLimit != null ? p.tokenLimit : 100) + '" />' +
                '<p class="text-[10px] text-slate-400 mt-1">0 = ilimitado.</p></div>' +
                '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Valor mensal (R$)</label>' +
                '<input type="number" id="plan-edit-price" min="0" step="0.01" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" value="' + (p.priceMonthly != null ? p.priceMonthly : 0) + '" /></div>' +
                '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Período</label>' +
                '<select id="plan-edit-period" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">' +
                '<option value="monthly"' + (p.period === 'yearly' ? '' : ' selected') + '>Mensal</option><option value="yearly"' + (p.period === 'yearly' ? ' selected' : '') + '>Anual</option></select></div>' +
                '<div><label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Status</label>' +
                '<select id="plan-edit-status" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">' +
                '<option value="active"' + (p.status === 'inactive' ? '' : ' selected') + '>Ativo</option><option value="inactive"' + (p.status === 'inactive' ? ' selected' : '') + '>Inativo</option></select></div>' +
                '<p id="plans-edit-error" class="text-red-600 text-sm font-medium hidden"></p>' +
                '<div class="flex gap-3 pt-4">' +
                '<button type="button" id="plans-edit-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>' +
                '<button type="submit" id="plans-edit-submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Salvar</button>' +
                '</div></form></div>';
            overlay.onclick = function(e) { if (e.target === overlay) overlay.remove(); };
            document.body.appendChild(overlay);
            var form = document.getElementById('plans-edit-form');
            var cancelBtn = document.getElementById('plans-edit-cancel');
            var errorEl = document.getElementById('plans-edit-error');
            cancelBtn.onclick = function() { overlay.remove(); };
            form.onsubmit = function(e) {
                e.preventDefault();
                var name = (document.getElementById('plan-edit-name').value || '').trim();
                var slug = (document.getElementById('plan-edit-slug').value || '').trim();
                var tokenLimit = parseInt(document.getElementById('plan-edit-tokens').value, 10) || 0;
                if (tokenLimit < 0) tokenLimit = 0;
                var priceMonthly = parseFloat((document.getElementById('plan-edit-price').value || '0').toString().replace(',', '.')) || 0;
                var period = document.getElementById('plan-edit-period').value || 'monthly';
                var status = document.getElementById('plan-edit-status').value || 'active';
                if (!name) { errorEl.textContent = 'Nome é obrigatório.'; errorEl.classList.remove('hidden'); return; }
                if (!slug) slug = name.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'plano';
                errorEl.classList.add('hidden');
                var submitBtn = document.getElementById('plans-edit-submit');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Salvando...';
                fetch(API_BASE + 'plans.php', {
                    method: 'PUT',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ id: planId, name: name, slug: slug, tokenLimit: tokenLimit, priceMonthly: priceMonthly, period: period, status: status })
                })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            overlay.remove();
                            if (contentArea) loadPlansTab(contentArea);
                            if (document.getElementById('toast') && document.getElementById('toast-message')) {
                                document.getElementById('toast-message').textContent = 'Plano atualizado com sucesso.';
                                document.getElementById('toast').classList.remove('hidden');
                                setTimeout(function() { document.getElementById('toast').classList.add('hidden'); }, 3000);
                            }
                        } else {
                            errorEl.textContent = res.error || 'Erro ao atualizar.';
                            errorEl.classList.remove('hidden');
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Salvar';
                        }
                    })
                    .catch(function() {
                        errorEl.textContent = 'Erro de conexão.';
                        errorEl.classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Salvar';
                    });
            };
        })
        .catch(function() { alert('Erro ao carregar plano.'); });
}

// Empresas (super_admin): listar e ativar/desativar
function loadCompaniesTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto py-10"><div class="flex justify-between items-center mb-10 flex-wrap gap-4"><h3 class="text-2xl font-black text-slate-900">Empresas cadastradas <span id="companies-total" class="text-slate-500 font-normal text-lg"></span></h3><div class="flex items-center gap-3"><button type="button" id="companies-refresh-btn" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2">Atualizar lista</button><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block" id="companies-spinner"></span></div></div><div id="companies-list"></div></div>';
    var listEl = document.getElementById('companies-list');
    var spinnerEl = document.getElementById('companies-spinner');
    var refreshBtn = document.getElementById('companies-refresh-btn');
    if (refreshBtn) {
        refreshBtn.onclick = function() {
            loadCompaniesTab(document.getElementById('content-area'));
        };
    }
    var apiUrl = API_BASE + 'tenants.php';
    var plansUrl = API_BASE + 'plans.php';
    Promise.all([
        fetch(apiUrl, { method: 'GET', credentials: 'include', cache: 'no-store' }).then(function(r) { return r.json().catch(function() { return { success: false, error: 'Resposta inválida do servidor.' }; }); }),
        fetch(plansUrl, { method: 'GET', credentials: 'include', cache: 'no-store' }).then(function(r) { return r.json().catch(function() { return { success: false, data: { items: [] } }; }); })
    ]).then(function(results) {
            var data = results[0];
            var plansData = results[1];
            if (spinnerEl) spinnerEl.remove();
            if (!listEl) return;
            if (!data.success) {
                listEl.innerHTML = '<div class="py-8 p-5 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium">' + (data.error || 'Erro ao carregar empresas.') + '</div>';
                return;
            }
            var items = (data.data && data.data.items) ? data.data.items : (Array.isArray(data.data) ? data.data : []);
            var total = (data.data && typeof data.data.total === 'number') ? data.data.total : items.length;
            var plans = (plansData.success && plansData.data && plansData.data.items) ? plansData.data.items : (Array.isArray(plansData.data) ? plansData.data : []);
            var activePlans = plans.filter(function(p) { return p.status === 'active'; });
            if (!Array.isArray(items) || items.length === 0) {
                listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhuma empresa cadastrada</p></div>';
                return;
            }
            var html = '<div class="space-y-4">';
            items.forEach(function(t) {
                var statusLabel = t.status === 'active' ? 'Ativa' : 'Suspensa';
                var statusClass = t.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700';
                var toggleLabel = t.status === 'active' ? 'Desativar' : 'Ativar';
                var isDefault = String(t.id) === '1';
                var planInfo = (t.plan || '') + (t.planTokenLimit != null ? (t.planTokenLimit === 0 ? ' (ilimitado)' : ' (' + t.planTokenLimit + ' tokens)') : '');
                html += '<div class="bg-white p-6 rounded-[2rem] border border-slate-200 flex items-center justify-between flex-wrap gap-4">';
                html += '<div class="flex items-center gap-6">';
                html += '<div class="w-12 h-12 bg-slate-100 rounded-xl flex items-center justify-center text-xl font-black text-slate-600">' + (t.name ? t.name.charAt(0).toUpperCase() : '') + '</div>';
                html += '<div><h4 class="font-extrabold text-slate-900 text-lg">' + (t.name || '') + '</h4>';
                html += '<p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + (t.slug || '') + ' · Plano: ' + planInfo + ' · ' + (t.usersCount || 0) + ' usuário(s) · <span class="' + statusClass + ' px-2 py-0.5 rounded-full text-xs font-bold">' + statusLabel + '</span></p></div></div>';
                html += '<div class="flex items-center gap-2">';
                if (!isDefault) {
                    html += '<button type="button" class="btn-toggle-tenant px-4 py-2 rounded-xl text-xs font-bold ' + (t.status === 'active' ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200') + '" data-id="' + t.id + '" data-name="' + (t.name || '').replace(/"/g, '&quot;') + '" data-status="' + (t.status === 'active' ? 'suspended' : 'active') + '">' + toggleLabel + '</button>';
                    html += '<button type="button" class="btn-link-plan px-4 py-2 rounded-xl text-xs font-bold bg-blue-100 text-blue-700 hover:bg-blue-200" data-id="' + t.id + '" data-name="' + (t.name || '').replace(/"/g, '&quot;') + '" data-plan-id="' + (t.planId || '1') + '">Vincular plano</button>';
                } else {
                    html += '<span class="text-[10px] text-slate-400 font-bold uppercase">Empresa padrão</span>';
                }
                html += '</div></div>';
            });
            html += '</div>';
            listEl.innerHTML = html;
            var totalEl = document.getElementById('companies-total');
            if (totalEl) totalEl.textContent = total + ' empresa(s)';
            listEl.querySelectorAll('.btn-toggle-tenant').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = btn.dataset.id;
                    var name = btn.dataset.name;
                    var newStatus = btn.dataset.status;
                    var action = newStatus === 'active' ? 'ativar' : 'desativar';
                    if (!confirm('Deseja ' + action + ' a empresa "' + name + '"?')) return;
                    btn.disabled = true;
                    fetch(API_BASE + 'tenants.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ id: id, status: newStatus })
                    })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success) {
                                setActiveTab('companies');
                            } else {
                                alert(res.error || 'Erro ao atualizar.');
                            }
                        })
                        .catch(function() { alert('Erro de conexão.'); })
                        .finally(function() { btn.disabled = false; });
                });
            });

            listEl.querySelectorAll('.btn-link-plan').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tenantId = btn.dataset.id;
                    var tenantName = btn.dataset.name;
                    var currentPlanId = btn.dataset.planId || '1';
                    var optionsHtml = activePlans.map(function(p) {
                        var label = p.name + (p.tokenLimit === 0 ? ' (ilimitado)' : ' (' + p.tokenLimit + ' tokens)');
                        return '<option value="' + p.id + '"' + (String(p.id) === String(currentPlanId) ? ' selected' : '') + '>' + label + '</option>';
                    }).join('');
                    if (!optionsHtml) optionsHtml = '<option value="1">Básico</option>';
                    var overlay = document.getElementById('companies-link-plan-overlay');
                    if (overlay) overlay.remove();
                    overlay = document.createElement('div');
                    overlay.id = 'companies-link-plan-overlay';
                    overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4';
                    overlay.innerHTML = '<div class="bg-white rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
                        '<h4 class="text-xl font-black text-slate-900 mb-2">Vincular empresa ao plano</h4>' +
                        '<p class="text-sm text-slate-500 mb-4">' + (tenantName ? 'Empresa: ' + tenantName : '') + '</p>' +
                        '<form id="companies-link-plan-form" class="space-y-4">' +
                        '<label class="block text-[10px] font-black text-slate-500 uppercase mb-1">Plano</label>' +
                        '<select id="companies-link-plan-select" class="w-full border border-slate-200 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">' + optionsHtml + '</select>' +
                        '<p id="companies-link-plan-error" class="text-red-600 text-sm font-medium hidden"></p>' +
                        '<div class="flex gap-3 pt-4">' +
                        '<button type="button" id="companies-link-plan-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 font-bold text-slate-600 hover:bg-slate-50">Cancelar</button>' +
                        '<button type="submit" id="companies-link-plan-submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Vincular</button>' +
                        '</div></form></div>';
                    overlay.onclick = function(ev) { if (ev.target === overlay) overlay.remove(); };
                    document.body.appendChild(overlay);
                    var form = document.getElementById('companies-link-plan-form');
                    var cancelBtn = document.getElementById('companies-link-plan-cancel');
                    var errorEl = document.getElementById('companies-link-plan-error');
                    var submitBtn = document.getElementById('companies-link-plan-submit');
                    form.onsubmit = function(e) {
                        e.preventDefault();
                        var planId = document.getElementById('companies-link-plan-select').value;
                        if (!planId) return;
                        errorEl.classList.add('hidden');
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Salvando...';
                        fetch(API_BASE + 'tenants.php', {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ id: tenantId, planId: parseInt(planId, 10) })
                        })
                            .then(function(r) { return r.json(); })
                            .then(function(res) {
                                if (res.success) {
                                    overlay.remove();
                                    loadCompaniesTab(document.getElementById('content-area'));
                                } else {
                                    errorEl.textContent = res.error || 'Erro ao vincular plano.';
                                    errorEl.classList.remove('hidden');
                                }
                            })
                            .catch(function() {
                                errorEl.textContent = 'Erro de conexão.';
                                errorEl.classList.remove('hidden');
                            })
                            .finally(function() {
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Vincular';
                            });
                    };
                    cancelBtn.onclick = function() { overlay.remove(); };
                });
            });
        })
        .catch(function(err) {
            var s = document.getElementById('companies-spinner');
            if (s) s.remove();
            var list = document.getElementById('companies-list');
            if (list) list.innerHTML = '<div class="py-8 p-5 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium">Erro ao carregar empresas. Verifique a conexão e se você está logado como Super Admin.</div>';
        });
}

// GPS
function refreshLocation() {
    AppState.locStatus = 'loading';
    updateGPSUI();
    
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                AppState.userCoords = {
                    latitude: pos.coords.latitude,
                    longitude: pos.coords.longitude
                };
                AppState.locStatus = 'success';
                
                // Reverse geocoding
                try {
                    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${AppState.userCoords.latitude}&lon=${AppState.userCoords.longitude}&zoom=10`);
                    if (res.ok) {
                        const data = await res.json();
                        const addr = data.address;
                        const city = addr.city || addr.town || addr.municipality || addr.village || '';
                        const state = addr.state_district || addr.state || '';
                        let readable = '';
                        if (city) readable = city;
                        if (readable && state) readable += ` - ${state}`;
                        if (readable) AppState.userLocationName = readable;
                    }
                } catch (e) {
                    console.warn('Erro ao obter nome do local:', e);
                }
                
                updateGPSUI();
            },
            (err) => {
                console.warn('Geolocalização negada:', err);
                AppState.locStatus = 'error';
                updateGPSUI();
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    } else {
        AppState.locStatus = 'error';
        updateGPSUI();
    }
}

function updateGPSUI() {
    const indicator = document.getElementById('gps-indicator');
    const status = document.getElementById('gps-status');
    const locationDiv = document.getElementById('gps-location');
    const locationName = document.getElementById('gps-location-name');
    const btnText = document.getElementById('gps-btn-text');
    const icon = document.getElementById('gps-icon');
    
    if (AppState.locStatus === 'success') {
        indicator.className = 'w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981]';
        status.textContent = 'Localização Ativa';
        if (AppState.userLocationName) {
            locationDiv.classList.remove('hidden');
            locationName.textContent = AppState.userLocationName;
        }
        btnText.textContent = 'Recarregar GPS';
        icon.classList.remove('animate-spin');
    } else if (AppState.locStatus === 'loading') {
        indicator.className = 'w-2 h-2 rounded-full bg-yellow-500 animate-pulse';
        status.textContent = 'Detectando...';
        locationDiv.classList.add('hidden');
        btnText.textContent = 'Localizando...';
        icon.classList.add('animate-spin');
    } else {
        indicator.className = 'w-2 h-2 rounded-full bg-red-500';
        status.textContent = 'GPS Inativo';
        locationDiv.classList.add('hidden');
        btnText.textContent = 'Recarregar GPS';
        icon.classList.remove('animate-spin');
    }
}

// Prospecção HTML
function getProspectingHTML() {
    return `
        <div class="max-w-[1400px] mx-auto">
            <div id="search-token-limit-banner" class="hidden mb-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium flex items-center gap-2">
                <span class="text-lg">⚠️</span>
                <span>Você atingiu o limite de tokens do seu plano para este período. Cada página de resultados (até 20 itens) consome 1 token. Solicite mais créditos em <strong>Solicitar Créditos</strong> no menu ou aguarde o próximo período.</span>
            </div>
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 mb-10">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-4">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">O que busca?</label>
                        <input id="search-query" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm transition-all" placeholder="Ex: Petshop, Clínica, Padaria...">
                    </div>
                    <div class="md:col-span-3">
                        <div class="flex justify-between items-center mb-1">
                            <label class="block text-[10px] font-black text-slate-400 uppercase ml-1 tracking-widest">Onde?</label>
                            <button id="toggle-gps" class="text-[9px] font-black px-2 py-0.5 rounded-full transition-all bg-slate-100 text-slate-500 hover:bg-slate-200">USAR MEU GPS</button>
                        </div>
                        <div id="location-input-container">
                            <input id="search-location" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm transition-all" placeholder="Cidade ou Região">
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Tag CRM</label>
                        <input id="search-tag" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm" placeholder="Ex: leads_novos">
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest" title="Enviado à API como maxCrawledPlacesPerSearch">Limite (lugares)</label>
                        <input id="search-max-places" type="number" min="1" max="1000" class="w-full px-3 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm" placeholder="20" value="20">
                    </div>
                    <div class="md:col-span-2 flex items-end">
                        <button id="btn-search" class="w-full py-3 bg-slate-900 hover:bg-blue-600 text-white font-black rounded-xl transition-all shadow-xl shadow-slate-100 disabled:opacity-50 flex items-center justify-center uppercase tracking-wider text-xs">
                            Buscar Tudo
                        </button>
                    </div>
                </div>
            </div>
            
            <div id="error-info" class="hidden mb-8 p-5 bg-amber-50 border-l-4 border-amber-400 text-amber-900 rounded-r-2xl shadow-sm"></div>
            
            <div id="results-header" class="hidden mb-6 flex flex-wrap justify-between items-end gap-4 px-2">
                <div>
                    <h3 class="text-xl font-black text-slate-900 tracking-tight">Resultados da Busca</h3>
                    <p class="text-xs text-slate-500 font-medium">
                        Exibindo <span id="visible-count" class="font-bold text-slate-900">0</span> de <span id="total-count" class="font-bold text-slate-900">0</span> empresas encontradas
                    </p>
                    <p class="text-xs text-slate-500 font-medium mt-1">
                        Tokens disponíveis na conta: <span id="available-tokens" class="font-bold text-slate-900">—</span>
                    </p>
                </div>
                <div id="results-header-buttons" class="flex items-center gap-3">
                    <div id="results-unlock-page-wrap" class="hidden"></div>
                    <button id="btn-export-excel" type="button" class="flex items-center gap-2 px-5 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl transition-all text-xs font-black uppercase shadow-lg shadow-emerald-900/20" title="Exportar pesquisa atual para Excel">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        Exportar para Excel
                    </button>
                    <button id="btn-export-webhook" type="button" class="hidden flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl transition-all text-xs font-black uppercase shadow-lg shadow-blue-900/20" title="Enviar pesquisa atual para o Webhook">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                        Exportar para Webhook
                    </button>
                </div>
            </div>
            
            <div id="leads-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8 mb-12"></div>
            
            <div id="load-more-container" class="hidden flex justify-center mb-20"></div>
            
            <div id="empty-state" class="py-32 text-center opacity-30">
                <div class="w-24 h-24 bg-slate-200 rounded-full flex items-center justify-center mx-auto mb-4">
                    <span class="text-4xl grayscale">🗺️</span>
                </div>
                <p class="text-[10px] font-black uppercase tracking-[0.3em] text-slate-500">Aguardando pesquisa</p>
            </div>
        </div>
    `;
}

// Setup eventos de prospecção
function setupProspectingEvents() {
    let useGPS = false;
    
    document.getElementById('toggle-gps').addEventListener('click', () => {
        useGPS = !useGPS;
        const btn = document.getElementById('toggle-gps');
        const container = document.getElementById('location-input-container');
        
        if (useGPS) {
            btn.className = 'text-[9px] font-black px-2 py-0.5 rounded-full transition-all bg-blue-600 text-white shadow-lg shadow-blue-200';
            btn.textContent = 'GPS ATIVO';
            container.innerHTML = `
                <div class="w-full px-5 py-3 rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700 text-xs font-bold flex items-center gap-2 overflow-hidden">
                    <span class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse flex-shrink-0"></span>
                    <span class="truncate">${AppState.userLocationName || 'Detectando sua localização...'}</span>
                </div>
            `;
        } else {
            btn.className = 'text-[9px] font-black px-2 py-0.5 rounded-full transition-all bg-slate-100 text-slate-500 hover:bg-slate-200';
            btn.textContent = 'USAR MEU GPS';
            container.innerHTML = '<input id="search-location" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm transition-all" placeholder="Cidade ou Região">';
        }
    });
    
    document.getElementById('btn-search').addEventListener('click', performSearch);
    document.getElementById('search-query').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') performSearch();
    });

    var limitReached = (AppState.tokenUsage && AppState.tokenUsage.limitReached) || (AppState.tenant && AppState.tenant.status === 'suspended');
    if (limitReached) {
        var btnSearch = document.getElementById('btn-search');
        var banner = document.getElementById('search-token-limit-banner');
        if (btnSearch) { btnSearch.disabled = true; btnSearch.textContent = 'Sem créditos disponíveis'; }
        if (banner) banner.classList.remove('hidden');
    }

    const btnExportExcel = document.getElementById('btn-export-excel');
    if (btnExportExcel) {
        btnExportExcel.addEventListener('click', exportCurrentSearchToExcel);
    }
    const btnExportWebhook = document.getElementById('btn-export-webhook');
    if (btnExportWebhook) {
        btnExportWebhook.addEventListener('click', exportCurrentSearchToWebhook);
    }
}

// Escapa valor para célula CSV (vírgula, aspas, quebra de linha)
function escapeCsv(val) {
    if (val == null || val === undefined) return '';
    const s = String(val).trim();
    if (s.includes(',') || s.includes('"') || s.includes('\n') || s.includes('\r')) {
        return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
}

// Exporta apenas a pesquisa atual (leads listados) para CSV (abre no Excel)
function exportCurrentSearchToExcel() {
    if (!AppState.searchId) {
        showError('Sessão da pesquisa expirada. Faça uma nova busca.');
        updateExportExcelButtonState();
        return;
    }
    const leads = (AppState.leads || []).filter(function(l) { return l.locked === false; });
    if (leads.length === 0) {
        alert('Nenhum lead desbloqueado na pesquisa atual para exportar. Desbloqueie leads para incluí-los no Excel.');
        return;
    }
    const ctx = AppState.currentSearch || {};
    const queryEl = document.getElementById('search-query');
    const locationEl = document.getElementById('search-location');
    const tagEl = document.getElementById('search-tag');
    const query = (ctx.query !== undefined && ctx.query !== '') ? ctx.query : (queryEl ? queryEl.value.trim() : '');
    const location = (ctx.location !== undefined && ctx.location !== '') ? ctx.location : ((locationEl && locationEl.value !== undefined) ? String(locationEl.value || '').trim() : '');
    const tag = (ctx.tag !== undefined && ctx.tag !== '') ? ctx.tag : (tagEl ? tagEl.value.trim() : '');
    const dateStr = new Date().toLocaleString('pt-BR');

    const rows = [];
    const headers = ['Pesquisa', 'Local', 'Tag', 'Data da Pesquisa', 'Nome', 'Telefone', 'Email', 'Endereço', 'CNPJ', 'Sócios', 'Site', 'Maps'];
    rows.push(headers);

    for (const lead of leads) {
        rows.push([
            escapeCsv(query),
            escapeCsv(location),
            escapeCsv(tag),
            escapeCsv(dateStr),
            escapeCsv(lead.name),
            escapeCsv(lead.phone || ''),
            escapeCsv(lead.email || ''),
            escapeCsv(lead.address || ''),
            escapeCsv(lead.cnpj || ''),
            escapeCsv(lead.partners || ''),
            escapeCsv(lead.website || ''),
            escapeCsv(lead.mapsUri || '')
        ]);
    }

    const csvContent = rows.map(row => row.join(';')).join('\r\n');
    const BOM = '\uFEFF';
    const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'pesquisa_atual_' + new Date().toISOString().slice(0, 10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// Envia todos os leads da pesquisa atual para o Webhook em um único request (api/export-bulk.php)
async function exportCurrentSearchToWebhook() {
    const leads = AppState.leads || [];
    if (leads.length === 0) {
        alert('Nenhum lead na pesquisa atual para enviar.');
        return;
    }
    if (!AppState.config || !AppState.config.baseUrl || String(AppState.config.baseUrl).trim() === '') {
        alert('Configure a URL do Webhook em Configurações para usar esta função.');
        return;
    }
    const btn = document.getElementById('btn-export-webhook');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="animate-pulse">Enviando...</span>';
    }
    try {
        const res = await fetch(API_BASE + 'export-bulk.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                leadIds: leads.map(l => l.id)
            })
        });
        const data = await res.json();
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg> Exportar para Webhook';
        }
        if (data.success) {
            showToast(data.message || 'Enviado para o Webhook.');
        } else {
            alert('❌ Erro: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (e) {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg> Exportar para Webhook';
        }
        alert('❌ Erro de conexão: ' + e.message);
    }
}

// Realiza busca (api/search.php grava pesquisa e leads no banco; respeita limite de tokens do usuário)
async function performSearch() {
    var limitReached = (AppState.tokenUsage && AppState.tokenUsage.limitReached) || (AppState.tenant && AppState.tenant.status === 'suspended');
    if (limitReached) {
        showError('Você atingiu o limite de tokens do seu plano para este período. Solicite mais créditos em "Solicitar Créditos" ou aguarde o próximo período.');
        return;
    }

    const query = document.getElementById('search-query').value.trim();
    const location = document.getElementById('search-location')?.value.trim() || '';
    const tag = document.getElementById('search-tag').value.trim();
    const useGPS = document.getElementById('toggle-gps').textContent === 'GPS ATIVO';
    
    if (!query) {
        showError('Digite o ramo de atividade (Ex: Petshop).');
        return;
    }
    
    if (!useGPS && !location) {
        showError('Digite a cidade ou ative o GPS.');
        return;
    }
    
    const btn = document.getElementById('btn-search');
    btn.disabled = true;
    btn.innerHTML = '<div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>';
    
    hideError();
    AppState.leads = [];
    AppState.visibleCount = 12;
    
    try {
        var maxPlacesEl = document.getElementById('search-max-places');
        var maxPlaces = 20;
        if (maxPlacesEl && maxPlacesEl.value) {
            var v = parseInt(maxPlacesEl.value, 10);
            if (!isNaN(v)) maxPlaces = Math.max(1, Math.min(1000, v));
        }
        const res = await fetch(API_BASE + 'search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                query,
                location: useGPS ? null : location,
                tag,
                useGPS,
                coords: useGPS ? AppState.userCoords : null,
                locationName: useGPS ? AppState.userLocationName : null,
                maxCrawledPlacesPerSearch: maxPlaces
            })
        });
        
        // Verifica se a resposta está OK
        if (!res.ok) {
            const text = await res.text();
            let errorMsg = `Erro ${res.status}: Erro interno do servidor`;
            try {
                if (text) {
                    const errorData = JSON.parse(text);
                    errorMsg = errorData.error || errorData.message || errorMsg;
                }
            } catch (e) {
                // Se não conseguir parsear, usa a mensagem padrão
            }
            showError(errorMsg);
            return;
        }
        
        // Verifica se há conteúdo antes de parsear
        const text = await res.text();
        if (!text || text.trim() === '') {
            showError('Erro: Resposta vazia do servidor. Verifique os logs do servidor.');
            return;
        }
        
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            showError('Erro ao processar resposta do servidor: ' + e.message);
            return;
        }
        
        if (data.success) {
            AppState.leads = data.data.leads;
            AppState.searchId = data.data.searchId || null;
            if (data.data.tokenUsage) AppState.tokenUsage = data.data.tokenUsage;
            var locationText = useGPS ? (AppState.userLocationName || 'Localização GPS') : location;
            AppState.currentSearch = { query: query, location: locationText, tag: tag };
            // Incluir pesquisa atual no histórico local para "Ver novamente" manter desbloqueados após unlock
            var newItem = { id: String(Date.now()), query: query, location: locationText, tag: tag, timestamp: new Date().toISOString(), resultsCount: AppState.leads.length, leads: AppState.leads.slice() };
            AppState.history = [newItem].concat(AppState.history.filter(function(h) { return h.query !== query || h.location !== locationText; })).slice(0, 50);
            displayLeads();
        } else {
            showError(data.error || 'Erro ao buscar leads');
        }
    } catch (e) {
        showError('Erro de conexão: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Buscar Tudo';
    }
}

// Exibe leads
function displayLeads() {
    const grid = document.getElementById('leads-grid');
    const emptyState = document.getElementById('empty-state');
    const resultsHeader = document.getElementById('results-header');
    const loadMoreContainer = document.getElementById('load-more-container');
    
    if (AppState.leads.length === 0) {
        grid.innerHTML = '';
        emptyState.classList.remove('hidden');
        resultsHeader.classList.add('hidden');
        loadMoreContainer.classList.add('hidden');
        return;
    }
    
    emptyState.classList.add('hidden');
    resultsHeader.classList.remove('hidden');

    const btnExportWebhook = document.getElementById('btn-export-webhook');
    if (btnExportWebhook) {
        const hasWebhookUrl = AppState.config && AppState.config.baseUrl && String(AppState.config.baseUrl).trim() !== '';
        btnExportWebhook.classList.toggle('hidden', !hasWebhookUrl);
    }
    updateExportExcelButtonState();

    const visible = AppState.leads.slice(0, AppState.visibleCount);
    document.getElementById('visible-count').textContent = visible.length;
    document.getElementById('total-count').textContent = AppState.leads.length;
    var u = AppState.tokenUsage;
    var availableEl = document.getElementById('available-tokens');
    if (availableEl) {
        if (u == null) availableEl.textContent = '—';
        else if (u.limit === 0) availableEl.textContent = 'Ilimitado';
        else availableEl.textContent = Math.max(0, u.limit - u.used);
    }
    
    grid.innerHTML = visible.map(lead => getLeadCardHTML(lead)).join('');
    
    // Botões de exportar
    document.querySelectorAll('.btn-export').forEach(btn => {
        btn.addEventListener('click', () => {
            const leadId = btn.dataset.leadId;
            exportLead(leadId);
        });
    });
    
    // Botões Desbloquear (não debita tokens)
    document.querySelectorAll('.btn-unlock').forEach(btn => {
        btn.addEventListener('click', function() {
            var leadId = this.dataset.leadId;
            if (leadId) doUnlockLeads([leadId]);
        });
    });
    
    // Botão Desbloquear página (visível quando há leads bloqueados)
    var unlockPageWrap = document.getElementById('results-unlock-page-wrap');
    var lockedVisible = visible.filter(function(l) { return l.locked; });
    if (unlockPageWrap) {
        if (lockedVisible.length > 0 && AppState.searchId) {
            unlockPageWrap.innerHTML = '<button type="button" id="btn-unlock-page" class="flex items-center gap-2 px-5 py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl transition-all text-xs font-black uppercase shadow-lg disabled:opacity-50">Desbloquear página (' + lockedVisible.length + ')</button>';
            unlockPageWrap.classList.remove('hidden');
            var bp = document.getElementById('btn-unlock-page');
            if (bp) bp.addEventListener('click', function() {
                doUnlockLeads(lockedVisible.map(function(l) { return l.id; }));
            });
        } else {
            unlockPageWrap.innerHTML = '';
            unlockPageWrap.classList.add('hidden');
        }
    }
    
    // Load more
    if (AppState.leads.length > AppState.visibleCount) {
        loadMoreContainer.classList.remove('hidden');
        loadMoreContainer.innerHTML = `
            <button id="btn-load-more" class="group relative px-8 py-4 bg-white border-2 border-slate-200 rounded-full shadow-lg hover:shadow-xl hover:border-blue-500 transition-all active:scale-95">
                <div class="flex items-center gap-3">
                    <div class="bg-slate-100 rounded-full p-2 group-hover:bg-blue-100 transition-colors">
                        <svg class="w-4 h-4 text-slate-600 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" /></svg>
                    </div>
                    <span class="text-xs font-black text-slate-700 uppercase tracking-widest group-hover:text-blue-700">
                        Carregar Mais Resultados (${AppState.leads.length - AppState.visibleCount} restantes)
                    </span>
                </div>
            </button>
        `;
        document.getElementById('btn-load-more').addEventListener('click', () => {
            AppState.visibleCount += 12;
            displayLeads();
        });
    } else {
        loadMoreContainer.classList.add('hidden');
    }
}

// HTML do card de lead (bloqueado: só nome + botão Desbloquear; desbloqueado: dados completos)
function getLeadCardHTML(lead) {
    if (lead.locked) {
        return `
        <div class="relative mt-4 pt-10 pb-6 px-6 bg-white border border-slate-200 rounded-[1.5rem] hover:shadow-2xl hover:shadow-blue-900/5 transition-all duration-300 flex flex-col justify-between group">
            <div class="absolute -top-3 left-6 bg-amber-500 text-white text-[9px] font-black py-1.5 px-3 rounded-lg uppercase tracking-wider shadow-lg z-10">Bloqueado</div>
            <div>
                <h3 class="font-extrabold text-slate-900 text-sm uppercase leading-snug mb-4 min-h-[2.5rem]">${lead.name || ''}</h3>
                <div class="space-y-4 mb-6">
                    <div class="flex items-center gap-3 bg-slate-50 p-3 rounded-lg -mx-2 border border-slate-200">
                        <span class="text-slate-400 text-xs">🔒</span>
                        <span class="text-slate-500 font-bold text-xs">Telefone, email e endereço bloqueados. Desbloqueie para visualizar.</span>
                    </div>
                    <button type="button" class="btn-unlock w-full py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black text-[10px] uppercase tracking-wide transition-all disabled:opacity-50 flex justify-center items-center gap-2" data-lead-id="${lead.id}" title="Desbloquear">Desbloquear para ver dados</button>
                </div>
            </div>
        </div>
        `;
    }
    return `
        <div class="relative mt-4 pt-10 pb-6 px-6 bg-white border border-slate-200 rounded-[1.5rem] hover:shadow-2xl hover:shadow-blue-900/5 transition-all duration-300 flex flex-col justify-between group">
            <div class="absolute -top-3 left-6 bg-blue-600 text-white text-[9px] font-black py-1.5 px-3 rounded-lg uppercase tracking-wider shadow-lg shadow-blue-200 z-10">
                ${lead.partners ? 'Dados Ricos' : 'Lead'}
            </div>
            <div>
                <h3 class="font-extrabold text-slate-900 text-sm uppercase leading-snug mb-4 min-h-[2.5rem]">${lead.name || ''}</h3>
                <div class="space-y-3 mb-6">
                    <div class="flex items-center gap-3 bg-emerald-50/50 p-2 rounded-lg -mx-2">
                        <span class="text-emerald-500 text-xs">📞</span>
                        <span class="text-emerald-700 font-bold text-xs">${lead.phone || 'Sem telefone'}</span>
                    </div>
                    ${lead.partners ? `
                        <div class="flex items-start gap-3">
                            <span class="text-purple-400 text-xs mt-0.5">👥</span>
                            <div class="flex flex-col">
                                <span class="text-[9px] font-black text-slate-400 uppercase">Sócios/Resp.</span>
                                <p class="text-slate-700 font-bold text-[10px] leading-tight">${lead.partners}</p>
                            </div>
                        </div>
                    ` : ''}
                    ${lead.cnpj ? `
                        <div class="flex items-center gap-3">
                            <span class="text-amber-400 text-xs">🏢</span>
                            <div class="flex flex-col">
                                <span class="text-[9px] font-black text-slate-400 uppercase">CNPJ</span>
                                <span class="text-slate-700 font-mono font-bold text-[10px]">${lead.cnpj}</span>
                            </div>
                        </div>
                    ` : ''}
                    ${lead.email ? `
                        <div class="flex items-center gap-3">
                            <span class="text-blue-400 text-xs">✉️</span>
                            <div class="flex flex-col overflow-hidden">
                                <span class="text-[9px] font-black text-slate-400 uppercase">Email</span>
                                <a href="mailto:${lead.email}" class="text-blue-600 font-bold text-[10px] hover:underline truncate w-full block">${lead.email}</a>
                            </div>
                        </div>
                    ` : ''}
                    <div class="flex items-start gap-3 pt-2 border-t border-slate-100">
                        <span class="text-rose-400 text-xs mt-0.5">📍</span>
                        <p class="text-slate-500 font-semibold text-[10px] leading-relaxed line-clamp-2">${lead.address || ''}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-4 border-t border-slate-100 mt-auto">
                <button class="btn-export flex-grow bg-white border border-slate-200 text-slate-900 hover:bg-slate-50 hover:border-slate-300 font-black text-[10px] py-3 rounded-xl uppercase tracking-wide transition-all shadow-sm active:scale-95 disabled:opacity-50 flex justify-center items-center gap-2" data-lead-id="${lead.id}">
                    Exportar WEBHOOK
                </button>
                ${lead.mapsUri ? `
                    <a href="${lead.mapsUri}" target="_blank" rel="noopener noreferrer" class="w-12 h-[38px] flex-shrink-0 bg-white border border-slate-200 text-blue-500 hover:bg-blue-50 hover:border-blue-200 rounded-xl flex items-center justify-center transition-all shadow-sm" title="Ver no Google Maps">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.7 3.8C15 .1 9 .1 5.3 3.8c-3.7 3.7-3.7 9.8 0 13.5L12 24l6.7-6.7c3.7-3.7 3.7-9.8 0-13.5zm-6.7 10c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/></svg>
                    </a>
                ` : ''}
            </div>
        </div>
    `;
}

// Atualiza estado do botão Exportar Excel (desabilitado quando sessão expirada)
function updateExportExcelButtonState() {
    var btn = document.getElementById('btn-export-excel');
    if (btn) btn.disabled = !AppState.searchId;
}

// Desbloqueia um ou mais leads; retorna dados sensíveis (não debita tokens)
async function doUnlockLeads(leadIds) {
    if (!AppState.searchId) {
        showError('Sessão da pesquisa expirada. Faça uma nova busca.');
        updateExportExcelButtonState();
        return;
    }
    if (!leadIds || leadIds.length === 0) return;
    try {
        var res = await fetch(API_BASE + 'unlock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ searchId: AppState.searchId, leadIds: leadIds })
        });
        var text = await res.text();
        var data = JSON.parse(text || '{}');
        if (!data.success) {
            showError(data.error || 'Erro ao desbloquear');
            return;
        }
        var unlocked = data.data && data.data.unlocked ? data.data.unlocked : {};
        leadIds.forEach(function(leadId) {
            var lead = AppState.leads.find(function(l) { return l.id === leadId; });
            if (lead && unlocked[leadId]) {
                Object.keys(unlocked[leadId]).forEach(function(k) {
                    lead[k] = unlocked[leadId][k];
                });
                lead.locked = false;
            }
        });
        if (data.data && data.data.tokenUsage) AppState.tokenUsage = data.data.tokenUsage;
        displayLeads();
        renderHeaderTokenWarning();
        // Atualizar o item da pesquisa atual no histórico para "Ver novamente" manter os desbloqueados
        if (AppState.currentSearch && AppState.currentSearch.query) {
            var match = AppState.history.find(function(h) { return h.query === AppState.currentSearch.query && h.location === AppState.currentSearch.location; });
            if (match) {
                match.leads = AppState.leads.slice();
            }
        }
    } catch (e) {
        showError('Erro: ' + (e.message || 'ao desbloquear'));
    }
}

// Exporta lead
async function exportLead(leadId) {
    // Encontra o lead completo
    const lead = AppState.leads.find(l => l.id === leadId);
    if (!lead) {
        alert('Lead não encontrado');
        return;
    }
    
    // Extrai ID numérico do banco (formato: lead-123)
    const numericId = leadId.replace('lead-', '');
    
    try {
        const res = await fetch(API_BASE + 'export.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                leadId: numericId,
                leadName: lead.name,
                leadAddress: lead.address
            })
        });
        
        const data = await res.json();
        
        if (data.success) {
            showToast(data.message || 'Enviado com sucesso para o CRM!');
        } else {
            alert('❌ Erro no Envio: ' + data.error);
        }
    } catch (e) {
        alert('❌ Erro: ' + e.message);
    }
}

// Histórico
async function loadHistory() {
    const contentArea = document.getElementById('content-area');
    contentArea.innerHTML = '<div class="max-w-5xl mx-auto"><div class="text-center py-10"><div class="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto"></div></div></div>';
    
    try {
        const res = await fetch(API_BASE + 'history.php');
        const data = await res.json();
        
        if (data.success) {
            AppState.history = data.data;
            displayHistory();
        }
    } catch (e) {
        contentArea.innerHTML = `<div class="max-w-5xl mx-auto"><div class="bg-red-50 p-5 rounded-2xl text-red-700">Erro ao carregar histórico: ${e.message}</div></div>`;
    }
}

function displayHistory() {
    const contentArea = document.getElementById('content-area');
    
    if (AppState.history.length === 0) {
        contentArea.innerHTML = `
            <div class="max-w-5xl mx-auto">
                <div class="py-24 text-center border-2 border-dashed border-slate-200 rounded-[2rem]">
                    <p class="text-slate-400 font-bold uppercase text-[10px]">Nenhum registro encontrado</p>
                </div>
            </div>
        `;
        return;
    }
    
    contentArea.innerHTML = `
        <div class="max-w-5xl mx-auto">
            <div class="flex justify-between items-center mb-10">
                <div><h3 class="text-2xl font-black text-slate-900">Histórico Recente</h3></div>
                <button id="btn-clear-history" class="text-xs font-extrabold text-red-500 hover:bg-red-50 px-5 py-2 rounded-xl">Limpar Tudo</button>
            </div>
            <div class="space-y-4" id="history-list"></div>
        </div>
    `;
    
    const list = document.getElementById('history-list');
    list.innerHTML = AppState.history.map(item => `
        <div class="bg-white p-6 rounded-[2rem] border border-slate-200 flex items-center justify-between group hover:border-blue-300 transition-all hover:shadow-md">
            <div class="flex items-center gap-6">
                <div class="w-12 h-12 bg-slate-50 rounded-xl flex items-center justify-center text-xl">🔎</div>
                <div>
                    <h4 class="font-extrabold text-slate-900 text-lg capitalize">${item.query}</h4>
                    <p class="text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-wider">📍 ${item.location || 'Local Automático'} <span class="ml-2 text-blue-500">(${item.resultsCount} leads)</span></p>
                </div>
            </div>
            <button class="btn-use-history bg-slate-900 text-white font-bold px-6 py-3 rounded-2xl text-xs hover:bg-blue-600 transition-colors" data-history-id="${item.id}">Ver Novamente</button>
        </div>
    `).join('');
    
    document.getElementById('btn-clear-history').addEventListener('click', async () => {
        if (confirm('Deseja apagar o histórico de buscas?')) {
            try {
                const res = await fetch(API_BASE + 'history.php', { method: 'DELETE' });
                const data = await res.json();
                if (data.success) {
                    AppState.history = [];
                    displayHistory();
                }
            } catch (e) {
                alert('Erro ao limpar histórico: ' + e.message);
            }
        }
    });
    
    document.querySelectorAll('.btn-use-history').forEach(btn => {
        btn.addEventListener('click', () => {
            const historyId = btn.dataset.historyId;
            const item = AppState.history.find(h => h.id == historyId);
            if (item && item.leads) {
                AppState.searchId = String(item.id);
                AppState.leads = item.leads.map(function(l) {
                    if (l.locked === false) return l;
                    return { id: l.id, name: l.name || '', locked: true, dbId: l.dbId };
                });
                AppState.currentSearch = {
                    query: item.query || '',
                    location: item.location || '',
                    tag: item.tag || ''
                };
                setActiveTab('search');
                setTimeout(() => {
                    displayLeads();
                }, 100);
            }
        });
    });
}

// Configurações
function getSettingsHTML() {
    return `
        <div class="max-w-3xl mx-auto space-y-8 pb-20">
            <div class="bg-white p-10 rounded-[2.5rem] border border-slate-200 shadow-sm">
                <h3 class="text-2xl font-black text-slate-900 mb-8 tracking-tight">Configurações de Conexão</h3>
                <div class="space-y-8">
                    <div class="bg-[#0F172A] p-8 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/10 rounded-full blur-3xl"></div>
                        <h4 id="api-section-title" class="font-black text-xl mb-4 flex items-center gap-2">API de Busca (Google Maps)</h4>
                        <div id="api-status" class="p-5 bg-amber-500/20 border border-amber-500/50 rounded-2xl text-amber-400 text-center font-bold">Nenhuma API de busca configurada</div>
                        <p id="api-status-subtitle" class="text-[10px] text-slate-400 mt-4 text-center italic">Configure a chave abaixo para buscar leads no Google Maps.</p>
                    </div>
                    
                    <div class="grid grid-cols-1 gap-4">
                        <div class="bg-emerald-50 p-6 rounded-2xl border border-emerald-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h5 class="font-black text-slate-900 text-sm">Modo Estrito (Fix 400: number/ticketId)</h5>
                                    <p class="text-[10px] text-slate-500 font-medium">Obrigatório para Atendo/Evolution API. Envia apenas o essencial.</p>
                                </div>
                                <button id="toggle-simplified" class="w-12 h-6 rounded-full transition-all relative bg-slate-300">
                                    <div class="absolute top-1 w-4 h-4 bg-white rounded-full transition-all left-1"></div>
                                </button>
                            </div>
                        </div>
                        <div class="bg-amber-50 p-6 rounded-2xl border border-amber-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h5 class="font-black text-slate-900 text-sm">Contornar CORS (Modo Proxy)</h5>
                                    <p class="text-[10px] text-slate-500 font-medium">Ative se houver erro ao conectar com seu n8n/webhook.</p>
                                </div>
                                <button id="toggle-proxy" class="w-12 h-6 rounded-full transition-all relative bg-slate-300">
                                    <div class="absolute top-1 w-4 h-4 bg-white rounded-full transition-all left-1"></div>
                                </button>
                            </div>
                        </div>
                        <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100">
                            <div class="flex items-center justify-between">
                                <div>
                                    <h5 class="font-black text-slate-900 text-sm">Encapsular Dados (Wrap em 'body')</h5>
                                    <p class="text-[10px] text-slate-500 font-medium">Necessário para alguns Webhooks do n8n.</p>
                                </div>
                                <button id="toggle-wrap" class="w-12 h-6 rounded-full transition-all relative bg-slate-300">
                                    <div class="absolute top-1 w-4 h-4 bg-white rounded-full transition-all left-1"></div>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Nome da Instância</label>
                            <input id="setting-tenant" type="text" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold" placeholder="Atendo CRM">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">URL do Webhook</label>
                        <input id="setting-url" type="url" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold" placeholder="https://seu-webhook.com/...">
                    </div>
                    
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-2 ml-1">Authentication Header: apikey</label>
                        <p class="text-[10px] text-slate-500 mb-1 ml-1">Nome do header fixo: <code class="bg-slate-100 px-1 rounded">apikey</code>. Valor (preenchido abaixo) é salvo criptografado no banco.</p>
                        <input id="setting-token" type="password" class="w-full bg-slate-50 border border-slate-200 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold" placeholder="Valor do header apikey (deixe em branco para manter o atual)">
                    </div>
                    
                    <div id="block-scraper-api-key-admin" class="mt-6 bg-[#0F172A] p-6 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-purple-600/10 rounded-full blur-3xl"></div>
                        <h4 class="font-black text-lg mb-3 flex items-center gap-2">🔑 API Apify (Google Places)</h4>
                        <p class="text-xs text-slate-400 mb-4">Chave de API para busca direta no Google Maps. Apenas o Super Admin pode alterar; todas as empresas utilizam esta chave.</p>
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-2 ml-1">Chave da API Apify</label>
                            <input id="setting-scraper-api" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl px-6 py-4 outline-none focus:border-purple-500 font-bold text-white placeholder:text-slate-500" placeholder="Insira a chave da API Apify">
                        </div>
                    </div>
                    
                    <div class="mt-6">
                        <button id="btn-save-settings" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-[1.5rem] shadow-xl shadow-blue-100 transition-all active:scale-[0.98]">Salvar Alterações</button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function setupSettingsEvents() {
    document.getElementById('toggle-simplified').addEventListener('click', () => {
        toggleSwitch('toggle-simplified', 'simplifiedPayload');
    });
    document.getElementById('toggle-proxy').addEventListener('click', () => {
        toggleSwitch('toggle-proxy', 'useProxy');
    });
    document.getElementById('toggle-wrap').addEventListener('click', () => {
        toggleSwitch('toggle-wrap', 'wrapInBody');
    });
    
    document.getElementById('btn-save-settings').addEventListener('click', saveSettings);

    const scraperApiInput = document.getElementById('setting-scraper-api');
    if (scraperApiInput) {
        scraperApiInput.addEventListener('input', () => updateApiStatusDisplay(scraperApiInput.value));
    }
}

function toggleSwitch(btnId, configKey) {
    const btn = document.getElementById(btnId);
    const toggle = btn.querySelector('div');
    const isActive = toggle.classList.contains('left-7');
    
    if (isActive) {
        toggle.classList.remove('left-7');
        toggle.classList.add('left-1');
        btn.classList.remove('bg-emerald-600', 'bg-amber-600', 'bg-blue-600');
        btn.classList.add('bg-slate-300');
        AppState.config[configKey] = false;
    } else {
        toggle.classList.remove('left-1');
        toggle.classList.add('left-7');
        if (btnId.includes('simplified')) btn.classList.add('bg-emerald-600');
        else if (btnId.includes('proxy')) btn.classList.add('bg-amber-600');
        else btn.classList.add('bg-blue-600');
        btn.classList.remove('bg-slate-300');
        AppState.config[configKey] = true;
    }
}

async function loadSettingsForm() {
    try {
        const res = await fetch(API_BASE + 'settings.php');
        const data = await res.json();
        
        if (data.success) {
            AppState.config = data.data;
            
            document.getElementById('setting-url').value = AppState.config.baseUrl || '';
            document.getElementById('setting-token').value = AppState.config.token || '';
            document.getElementById('setting-tenant').value = AppState.config.tenantName || 'Atendo CRM';
            // Carrega a chave da API Apify se o elemento existir
            const scraperApiInput = document.getElementById('setting-scraper-api');
            if (scraperApiInput) {
                scraperApiInput.value = AppState.config.scraperApiKey || '';
            }
            
            updateApiStatusDisplay();
            updateSwitch('toggle-simplified', AppState.config.simplifiedPayload);
            updateSwitch('toggle-proxy', AppState.config.useProxy);
            updateSwitch('toggle-wrap', AppState.config.wrapInBody);
        }
    } catch (e) {
        console.error('Erro ao carregar configurações:', e);
    }
}

function updateApiStatusDisplay(overrideKey) {
    const titleEl = document.getElementById('api-section-title');
    const statusEl = document.getElementById('api-status');
    const subtitleEl = document.getElementById('api-status-subtitle');
    if (!statusEl || !subtitleEl) return;
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
    if (titleEl) titleEl.textContent = isSuperAdmin ? 'API Apify (Google Places)' : 'API de Busca (Google Maps)';
    var configured = isSuperAdmin
        ? (overrideKey !== undefined ? String(overrideKey || '').trim() : (AppState.config && AppState.config.scraperApiKey) ? String(AppState.config.scraperApiKey).trim() : '')
        : (AppState.config && AppState.config.scraperApiKeyConfigured);
    if (configured) {
        statusEl.textContent = isSuperAdmin ? '✓ Conectado ao Google Maps via Apify' : '✓ Conectado ao Google Maps';
        statusEl.className = 'p-5 bg-emerald-500/20 border border-emerald-500/50 rounded-2xl text-emerald-400 text-center font-bold';
        subtitleEl.textContent = isSuperAdmin ? 'API configurada no servidor. Todas as empresas utilizam esta chave.' : 'API configurada no servidor pelo administrador da plataforma.';
    } else {
        statusEl.textContent = isSuperAdmin ? 'Nenhuma API Apify configurada' : 'Nenhuma API de busca configurada';
        statusEl.className = 'p-5 bg-amber-500/20 border border-amber-500/50 rounded-2xl text-amber-400 text-center font-bold';
        subtitleEl.textContent = isSuperAdmin ? 'Configure a chave abaixo para que todas as empresas possam buscar leads no Google Maps.' : 'O administrador da plataforma deve configurar a chave nas Configurações.';
    }
}

function updateSwitch(btnId, isActive) {
    const btn = document.getElementById(btnId);
    const toggle = btn.querySelector('div');
    
    if (isActive) {
        toggle.classList.remove('left-1');
        toggle.classList.add('left-7');
        if (btnId.includes('simplified')) btn.classList.add('bg-emerald-600');
        else if (btnId.includes('proxy')) btn.classList.add('bg-amber-600');
        else btn.classList.add('bg-blue-600');
        btn.classList.remove('bg-slate-300');
    } else {
        toggle.classList.remove('left-7');
        toggle.classList.add('left-1');
        btn.classList.remove('bg-emerald-600', 'bg-amber-600', 'bg-blue-600');
        btn.classList.add('bg-slate-300');
    }
}

async function saveSettings() {
    const btn = document.getElementById('btn-save-settings');
    btn.disabled = true;
    btn.textContent = 'Salvando...';
    
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
    var payload = {
        baseUrl: document.getElementById('setting-url').value,
        token: document.getElementById('setting-token').value,
        tenantName: document.getElementById('setting-tenant').value,
        simplifiedPayload: AppState.config.simplifiedPayload || false,
        useProxy: AppState.config.useProxy || false,
        wrapInBody: AppState.config.wrapInBody || false
    };
    if (isSuperAdmin) {
        payload.scraperApiKey = document.getElementById('setting-scraper-api') ? document.getElementById('setting-scraper-api').value : '';
    }
    try {
        const res = await fetch(API_BASE + 'settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const data = await res.json();
        
        if (data.success) {
            showToast('Configurações salvas no sistema!');
            loadConfig();
        } else {
            alert('Erro ao salvar: ' + data.error);
        }
    } catch (e) {
        alert('Erro de conexão: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Salvar Alterações';
    }
}

async function loadConfig() {
    try {
        const res = await fetch(API_BASE + 'settings.php');
        const data = await res.json();
        if (data.success) {
            AppState.config = data.data;
        }
    } catch (e) {
        console.error('Erro ao carregar config:', e);
    }
}

// Utilitários
function showError(message) {
    const errorDiv = document.getElementById('error-info');
    if (errorDiv) {
        errorDiv.classList.remove('hidden');
        errorDiv.innerHTML = `
            <div class="flex items-center gap-3">
                <span class="text-xl">⚠️</span>
                <p class="text-xs font-bold leading-relaxed">${message}</p>
            </div>
        `;
    }
}

function hideError() {
    const errorDiv = document.getElementById('error-info');
    if (errorDiv) {
        errorDiv.classList.add('hidden');
    }
}

function showToast(message) {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toast-message');
    toastMsg.textContent = message;
    toast.classList.remove('hidden');
    
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 3000);
}
