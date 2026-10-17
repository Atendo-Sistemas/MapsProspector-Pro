var API_BASE = (typeof window.API_BASE_URL !== 'undefined' ? window.API_BASE_URL : 'api/');

// Estado global da aplicação
var AppState = window.AppState || {
    user: null,
    tenant: null,
    tokenUsage: null,
    impersonating: false,
    impersonatingTenantName: '',
    platformCompanyName: null,
    config: null,
    activeTab: 'dashboard',
    userCoords: null,
    userLocationName: '',
    locStatus: 'idle',
    leads: [],
    searchId: null,
    history: [],
    folders: [],
    visibleCount: 12,
    currentSearch: { query: '', location: '', tag: '', folderId: null, folderName: '' },
    mapsPilotNewLeadIds: [],
    mapsViewedLeadIds: [],
    mapsPilotShowOnlyNew: false,
    localData: {
        municipios: [],
        cnaes: [],
        loaded: false
    }
};

var THEME_STORAGE_KEY = 'mapsprospector-theme';
function getStoredTheme() {
    try {
        var stored = localStorage.getItem(THEME_STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') {
            return stored;
        }
        if (stored === null) {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
        }
        return 'light';
    } catch (e) { return 'light'; }
}
function getStoredTheme() {
    try {
        var stored = localStorage.getItem(THEME_STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') {
            return stored;
        }
        if (stored === null) {
            if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                return 'dark';
            }
        }
        return 'light';
    } catch (e) { return 'light'; }
}

function applyTheme(theme) {
    var root = document.documentElement; // <html> tag
    if (theme === 'dark') {
        root.classList.add('dark');
        root.setAttribute('data-theme', 'dark'); // Opcional, ajuda em alguns frameworks
    } else {
        root.classList.remove('dark');
        root.removeAttribute('data-theme');
    }
    
    try { 
        localStorage.setItem(THEME_STORAGE_KEY, theme); 
    } catch (e) {}
    
    // Atualiza ícones do topo (Dashboard)
    var sunIcon = document.getElementById('theme-icon-sun');
    var moonIcon = document.getElementById('theme-icon-moon');
    if (sunIcon && moonIcon) {
        if (theme === 'dark') {
            sunIcon.classList.remove('hidden');
            moonIcon.classList.add('hidden');
        } else {
            sunIcon.classList.add('hidden');
            moonIcon.classList.remove('hidden');
        }
    }
    updateThemeIconsLanding();
}

function toggleTheme() {
    var current = getStoredTheme();
    var next = current === 'dark' ? 'light' : 'dark';
    applyTheme(next);
    updateThemeIconsLanding();
}   
function updateThemeIconsLanding() {
    var sunIcon = document.getElementById('theme-icon-sun-landing');
    var moonIcon = document.getElementById('theme-icon-moon-landing');
    var currentTheme = getStoredTheme();
    if (sunIcon && moonIcon) {
        if (currentTheme === 'dark') {
            sunIcon.classList.remove('hidden');
            moonIcon.classList.add('hidden');
        } else {
            sunIcon.classList.add('hidden');
            moonIcon.classList.remove('hidden');
        }
    }
}

function ensureLoginThemeToggle() {
    var loginScreen = document.getElementById('login-screen');
    if (!loginScreen || document.getElementById('btn-theme-toggle-login')) return;
    var button = document.createElement('button');
    button.type = 'button';
    button.id = 'btn-theme-toggle-login';
    button.setAttribute('aria-label', 'Alternar tema claro e escuro');
    button.className = 'absolute top-5 right-5 z-20 w-11 h-11 rounded-2xl bg-white/90 dark:bg-slate-800/90 text-slate-700 dark:text-slate-100 shadow-lg border border-slate-200 dark:border-slate-700 flex items-center justify-center transition-colors';
    button.innerHTML = '<span id="theme-icon-sun-login" class="hidden" aria-hidden="true">☀</span><span id="theme-icon-moon-login" aria-hidden="true">☾</span>';
    button.onclick = function(e) {
        e.stopPropagation();
        toggleTheme();
        var isDark = getStoredTheme() === 'dark';
        var sun = document.getElementById('theme-icon-sun-login');
        var moon = document.getElementById('theme-icon-moon-login');
        if (sun && moon) {
            sun.classList.toggle('hidden', !isDark);
            moon.classList.toggle('hidden', isDark);
        }
    };
    loginScreen.appendChild(button);
    var isDark = getStoredTheme() === 'dark';
    var sun = document.getElementById('theme-icon-sun-login');
    var moon = document.getElementById('theme-icon-moon-login');
    if (sun && moon) {
        sun.classList.toggle('hidden', !isDark);
        moon.classList.toggle('hidden', isDark);
    }
}
function initApp() {
    console.log('DOM loaded, initializing...');
    applyTheme(getStoredTheme());
    setupEventListeners();
    const resetToken = new URLSearchParams(window.location.search).get('reset');
    if (resetToken) {
        showLogin();
        openResetPasswordModal(resetToken);
    } else {
        showLandingPage();
        checkAuth();
    }
    console.log('Initialization complete');
}

// Executa a inicialização assim que o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}
async function checkAuth() {
    // Estando instalado, o PWA é destinado a usuários já cadastrados. Se a
    // sessão não existir ou tiver expirado, abre o login existente diretamente.
    const showUnauthenticatedStart = function () {
        const isStandalonePwa = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        if (isStandalonePwa) {
            showLogin();
            return;
        }
        showLandingPage();
    };

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
                AppState.activeTab = 'dashboard';
                await loadConfig();
                showDashboard();
            } else {
                showUnauthenticatedStart();
            }
        } else {
            showUnauthenticatedStart();
        }
    } catch (e) {
        console.error('Erro ao verificar auth:', e);
        showUnauthenticatedStart();
    }
}
async function loadConfig() {
    try {
        const res = await fetch(API_BASE + 'settings.php', { credentials: 'include' });
        const raw = await res.text();
        if (!raw.trim()) {
            throw new Error(`Resposta vazia de settings.php (HTTP ${res.status})`);
        }

        let data;
        try {
            data = JSON.parse(raw);
        } catch (parseError) {
            const preview = raw.replace(/\s+/g, ' ').slice(0, 240);
            throw new Error(`Resposta inválida de settings.php (HTTP ${res.status}): ${preview}`);
        }

        if (!res.ok) {
            throw new Error(data.error || `settings.php respondeu HTTP ${res.status}`);
        }

        if (data.success) {
            AppState.config = data.data;
            
            // Atualiza o indicador se a função existir
            if (typeof updateSearchApiUI === 'function') updateSearchApiUI();
            if (typeof updateApiStatusDisplay === 'function') updateApiStatusDisplay();

            var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
            var instanceName = (AppState.config.tenantName && String(AppState.config.tenantName).trim()) ? String(AppState.config.tenantName).trim() : (AppState.tenant && AppState.tenant.name) ? AppState.tenant.name : 'Nome da empresa SaaS';
            var instanceEl = document.getElementById('sidebar-instance-name');
            if (instanceEl) instanceEl.textContent = instanceName;
            if (!isSuperAdmin) {
                var subtitleEl = document.getElementById('header-dashboard-subtitle');
                if (subtitleEl) subtitleEl.textContent = 'Dashboard ' + instanceName.toUpperCase();
            }
            updateDocumentTitle();
        } else {
            throw new Error(data.error || 'Não foi possível carregar as configurações.');
        }
    } catch (e) {
        console.error('Erro ao carregar config:', e);
        if (typeof updateSearchApiUI === 'function') updateSearchApiUI();
    }
}
async function handleLogin(e) {
    if (e && e.preventDefault) e.preventDefault();
    const emailInput = document.getElementById('login-email');
    const passwordInput = document.getElementById('login-password');
    const errorEl = document.getElementById('login-error');
    const btn = document.getElementById('btn-login');
    
    const email = (emailInput && emailInput.value ? emailInput.value.trim().toLowerCase() : '') || '';
    const password = (passwordInput && passwordInput.value ? passwordInput.value : '') || '';
    
    if (errorEl) {
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
    }
    const loginSuccess = document.getElementById('login-success');
    if (loginSuccess) {
        loginSuccess.classList.add('hidden');
        loginSuccess.textContent = '';
    }
    if (!email || !email.includes('@')) {
        if (errorEl) {
            errorEl.textContent = 'Por favor, insira um e-mail válido.';
            errorEl.classList.remove('hidden');
        }
        return;
    }
    btn.innerHTML = '<span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin inline-block"></span> Entrando...';
    try {
        const res = await fetch(API_BASE + 'auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', email: email, password: password })
        });
        const text = await res.text();
        
        if (!text || text.trim() === '') {
            throw new Error('Resposta vazia do servidor');
        }
        
        const data = JSON.parse(text);
        
        if (data.success) {
            AppState.user = data.data.user;
            AppState.tenant = data.data.tenant || null;
            AppState.tokenUsage = data.data.tokenUsage || null;
            AppState.impersonating = !!(data.data && data.data.impersonating);
            AppState.impersonatingTenantName = (data.data && data.data.impersonatingTenantName) || '';
            // A sessão acabou de ser criada: é o ponto seguro para atribuir uma
            // indicação persistida. Uma falha de rede não bloqueia o login.
            try { await claimStoredReferral(); } catch (ignore) {}
            window.location.reload();
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
            errorEl.textContent = 'Erro de conexão: ' + (err.message || 'Verifique o servidor');
            errorEl.classList.remove('hidden');
        } else {
            alert('Erro de conexão: ' + (err.message || 'Verifique o servidor'));
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Entrar <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>';
    }
}

async function claimStoredReferral() {
    var referral = null;
    try {
        var raw = localStorage.getItem('mp-referral');
        referral = raw ? JSON.parse(raw) : null;
    } catch (ignore) {}
    var code = (referral && /^ATD-[A-Z0-9]{8}$/.test(String(referral.code || '')) && Number(referral.expiresAt || 0) > Date.now()) ? String(referral.code) : '';
    var visitToken = (referral && /^[a-f0-9]{32,64}$/i.test(String(referral.visitToken || ''))) ? String(referral.visitToken) : '';
    if (!code || !visitToken) {
        if (referral && Number(referral.expiresAt || 0) <= Date.now()) {
            try { localStorage.removeItem('mp-referral'); } catch (ignore) {}
        }
        return false;
    }
    var response = await fetch(API_BASE + 'referrals.php?action=signup', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify({ referralCode: code, visitToken: visitToken }),
        credentials: 'same-origin'
    });
    var payload = await response.json().catch(function () { return null; });
    if (payload && payload.success && payload.data && payload.data.registered) {
        try { localStorage.removeItem('mp-referral'); } catch (ignore) {}
        return true;
    }
    return false;
}
function showLogin() {
    var loginScreen = document.getElementById('login-screen');
    var dashboard = document.getElementById('dashboard');
    var landingPage = document.getElementById('landing-page');
    var themeToggle = document.getElementById('btn-theme-toggle');
    if (landingPage) landingPage.style.display = 'none';
    // A regra .hidden usa !important para manter menus administrativos
    // bloqueados por padrão. O login também nasce com essa classe no HTML;
    // portanto, removê-la é obrigatório antes de aplicar display:flex, ou a
    // página fica sem nenhuma camada visível após o retorno 401 sem sessão.
    if (loginScreen) {
        loginScreen.classList.remove('hidden');
        loginScreen.style.setProperty('display', 'flex', 'important');
    }
    if (dashboard) dashboard.style.display = 'none';
    if (themeToggle) themeToggle.classList.add('hidden');
    ensureLoginThemeToggle();
    ensurePublicHomeActions();
}
function showLandingPage() {
    var loginScreen = document.getElementById('login-screen');
    var dashboard = document.getElementById('dashboard');
    var landingPage = document.getElementById('landing-page');
    var themeToggle = document.getElementById('btn-theme-toggle');
    
    if (loginScreen) loginScreen.style.display = 'none';
    if (dashboard) dashboard.style.display = 'none';
    if (landingPage) {
        landingPage.style.display = 'block';
    }
    
    if (themeToggle) {
        themeToggle.classList.remove('hidden');
    }
    
    updateThemeIconsLanding();
    bindPublicEntryActions();
    loadLandingPageContent();
    loadLandingPagePlans();
    loadPlatformStats();
}

function returnToPublicHome() {
    hideCadastro();
    showLandingPage();
}

function ensurePublicHomeActions() {
    var loginScreen = document.getElementById('login-screen');
    if (loginScreen && !document.getElementById('btn-login-home')) {
        var loginHome = document.createElement('button');
        loginHome.type = 'button';
        loginHome.id = 'btn-login-home';
        loginHome.className = 'absolute top-5 left-5 z-20 px-4 py-2 rounded-xl bg-white/90 dark:bg-slate-800/90 text-slate-700 dark:text-slate-100 text-xs font-black shadow-lg border border-slate-200 dark:border-slate-700 transition-colors';
        loginHome.textContent = '← Página inicial';
        loginHome.onclick = function(e) {
            e.preventDefault();
            showLandingPage();
        };
        loginScreen.appendChild(loginHome);
    }

    var cadastroOverlay = document.getElementById('modal-cadastro-overlay');
    var cadastroHeader = cadastroOverlay ? cadastroOverlay.querySelector('.flex.justify-between.items-center.mb-8') : null;
    if (cadastroHeader && !document.getElementById('btn-cadastro-home')) {
        var cadastroHome = document.createElement('button');
        cadastroHome.type = 'button';
        cadastroHome.id = 'btn-cadastro-home';
        cadastroHome.className = 'mr-auto mr-4 text-xs font-black text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300 transition-colors';
        cadastroHome.textContent = '← Página inicial';
        cadastroHome.onclick = function(e) {
            e.preventDefault();
            returnToPublicHome();
        };
        cadastroHeader.insertBefore(cadastroHome, cadastroHeader.firstChild);
    }
}

function showLoginSuccess(message) {
    var loginError = document.getElementById('login-error');
    if (!loginError) return;
    var success = document.getElementById('login-success');
    if (!success) {
        success = document.createElement('p');
        success.id = 'login-success';
        success.className = 'mt-2 text-xs font-bold text-emerald-600 hidden';
        loginError.insertAdjacentElement('afterend', success);
    }
    success.textContent = message;
    success.classList.remove('hidden');
}

function toggleLandingMenu() {
    var menu = document.getElementById('landing-mobile-menu');
    var toggle = document.getElementById('landing-menu-toggle');
    if (!menu) return;
    var isHidden = menu.classList.toggle('hidden');
    if (toggle) {
        toggle.setAttribute('aria-expanded', isHidden ? 'false' : 'true');
        toggle.setAttribute('aria-label', isHidden ? 'Abrir menu' : 'Fechar menu');
    }
}

window.toggleLandingMenu = toggleLandingMenu;

function bindPublicEntryActions() {
    var landing = document.getElementById('landing-page');
    if (!landing) return;
    landing.querySelectorAll('button').forEach(function(button) {
        var label = (button.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
        if (label === 'cadastrar') {
            button.removeAttribute('onclick');
            button.onclick = function(e) {
                e.preventDefault();
                openCadastroModal();
            };
        }
    });
}
function loadLandingPageContent() {
    // O endpoint pode devolver as seções na raiz ou dentro de data.
    // Se a tabela ainda estiver vazia, a landing continua funcional com conteúdo padrão.
    fetch(API_BASE + 'landing-page-public.php?t=' + new Date().getTime(), { cache: 'no-store' })
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(function(text) {
            var data = {};
            try { data = text ? JSON.parse(text) : {}; } catch (e) { data = {}; }
            var sections = (data && Array.isArray(data.sections))
                ? data.sections
                : (data && data.data && Array.isArray(data.data.sections) ? data.data.sections : []);
            if (sections.length > 0) {
                renderLandingSections(sections);
            } else {
                renderLandingSections(getDefaultLandingSections());
            }
        })
        .catch(function() {
            renderLandingSections(getDefaultLandingSections());
        });
}
function loadLandingPagePlans() {
    fetch(API_BASE + 'plans-public.php', { cache: 'no-store' })
        .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
        })
        .then(function(data) {
            var parsed = {};
            try { parsed = data ? JSON.parse(data) : {}; } catch (e) { parsed = {}; }
            var items = parsed && parsed.data && Array.isArray(parsed.data.items)
                ? parsed.data.items
                : (parsed && Array.isArray(parsed.items) ? parsed.items : []);
            renderLandingPlans(items);
        })
        .catch(function() {
            renderLandingPlans([]);
        });
}
function loadPlatformStats() {
    var companiesEl = document.getElementById('platform-companies-count');
    var usersEl = document.getElementById('platform-users-count');
    var leadsEl = document.getElementById('platform-leads-count');
    if (!companiesEl || !usersEl || !leadsEl) return;

    fetch(API_BASE + 'public-stats.php?t=' + new Date().getTime(), { cache: 'no-store' })
        .then(function(response) {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then(function(payload) {
            if (!payload || !payload.success || !payload.data) return;
            var formatNumber = function(value) {
                return new Intl.NumberFormat('pt-BR').format(Number(value || 0));
            };
            companiesEl.textContent = formatNumber(payload.data.companies);
            usersEl.textContent = formatNumber(payload.data.users);
            leadsEl.textContent = formatNumber(payload.data.leadsFound);
        })
        .catch(function(error) {
            console.error('Erro ao carregar estatísticas públicas:', error);
        });
}

function getDefaultLandingSections() {
    return [
        {
            sectionKey: 'hero',
            sectionTitle: 'Prospecção comercial com mais clareza',
            sectionSubtitle: 'Encontre empresas, organize oportunidades e transforme buscas em ações comerciais.',
            sectionContent: '<p>Centralize suas pesquisas de mercado em uma experiência simples para sua equipe, com histórico e próximos passos no mesmo fluxo.</p>',
            extraData: { cta_text: 'Começar agora' }
        },
        {
            sectionKey: 'features',
            sectionTitle: 'Ferramentas para sua rotina comercial',
            sectionSubtitle: 'Uma base organizada para pesquisar, consultar e acompanhar seus próximos contatos.',
            extraData: { items: [
                { icon: 'map', title: 'Pesquisa no Maps', description: 'Encontre empresas por categoria e localização.' },
                { icon: 'search', title: 'Pesquisa LinkedIn', description: 'Organize buscas por cargo, localidade e empresa.' },
                { icon: 'chart', title: 'Acompanhamento', description: 'Consulte seu histórico e mantenha o trabalho comercial organizado.' },
                { icon: 'check-circle', title: 'Consulta de CNPJ', description: 'Consulte dados cadastrais por CNPJ, sem confundir a busca com razão social.' }
            ] }
        },
        {
            sectionKey: 'how_it_works',
            sectionTitle: 'Como funciona',
            sectionSubtitle: 'Comece com uma busca objetiva e avance com os dados que fazem sentido para sua equipe.',
            sectionContent: '<p>Escolha o tipo de pesquisa, informe os critérios e analise os resultados antes de iniciar o contato.</p>',
            extraData: { steps: [
                { number: '01', title: 'Escolha a pesquisa', description: 'Selecione Maps, LinkedIn ou consulta de CNPJ conforme o objetivo comercial.' },
                { number: '02', title: 'Defina os critérios', description: 'Informe localização, categoria, cargo ou CNPJ com o máximo de clareza.' },
                { number: '03', title: 'Revise e avance', description: 'Analise os dados retornados, consulte o histórico e organize a próxima ação.' }
            ] }
        },
        {
            sectionKey: 'benefits',
            sectionTitle: 'Mais foco para sua prospecção',
            sectionSubtitle: 'Reduza tarefas dispersas e mantenha as informações importantes em um só lugar.',
            extraData: { items: [
                { icon: 'clock', title: 'Mais agilidade', description: 'Encontre informações essenciais com menos etapas.' },
                { icon: 'shield', title: 'Uso responsável', description: 'Consulte os dados de acordo com as regras e os termos aplicáveis.' },
                { icon: 'check-circle', title: 'Fluxo organizado', description: 'Retome suas pesquisas pelo histórico sempre que precisar.' },
                { icon: 'users', title: 'Equipe alinhada', description: 'Dê visibilidade aos critérios e às pesquisas que orientam o trabalho comercial.' }
            ] }
        },
        {
            sectionKey: 'faq',
            sectionTitle: 'Dúvidas frequentes',
            sectionSubtitle: 'Veja os pontos principais antes de iniciar.',
            extraData: { items: [
                { question: 'Preciso criar uma conta para utilizar o sistema?', answer: 'Sim. O acesso às ferramentas depende de uma conta ativa.' },
                { question: 'Posso consultar pesquisas anteriores?', answer: 'Sim. As pesquisas salvas ficam disponíveis no histórico da conta conforme as regras configuradas para o seu acesso.' },
                { question: 'A pesquisa LinkedIn exige uma empresa?', answer: 'Não. A empresa é opcional; cargo e localização são os critérios obrigatórios.' },
                { question: 'A consulta de CNPJ busca razão social?', answer: 'Não. Nesta versão, a ferramenta realiza consulta cadastral por CNPJ.' }
            ] }
        },
        {
            sectionKey: 'cta',
            sectionTitle: 'Pronto para organizar sua prospecção?',
            sectionSubtitle: 'Entre na sua conta ou conheça o fluxo de cadastro.',
            sectionContent: '<p>Comece com uma pesquisa e avance no seu ritmo.</p>',
            extraData: { cta_text: 'Entrar', secondary_cta_text: 'Criar conta' }
        },
        {
            sectionKey: 'footer',
            sectionContent: '<p>Maps Prospector — prospecção comercial organizada.</p>'
        }
    ];
}
function renderLandingHero(section) {
    var heroTitle = document.getElementById('lp-hero-title');
    var heroSubtitle = document.getElementById('lp-hero-subtitle');
    var heroContent = document.getElementById('lp-hero-content');
    var heroCta = document.getElementById('lp-hero-cta');

    var title = section.sectionTitle || section.section_title || '';
    var subtitle = section.sectionSubtitle || section.section_subtitle || '';
    var content = section.sectionContent || section.section_content || '';

    if (heroTitle) heroTitle.textContent = title;
    if (heroSubtitle) heroSubtitle.textContent = subtitle;
    if (heroContent) heroContent.innerHTML = content;
    if (heroCta) {
        var heroCtaText = (section.extraData && (section.extraData.cta_text || section.extraData.ctaText)) || 'Começar agora';
        heroCta.textContent = heroCtaText;
        heroCta.classList.remove('hidden');
    }
}

function renderLandingFeatures(section, icons) {
    var featuresTitle = document.getElementById('lp-features-title');
    var featuresSubtitle = document.getElementById('lp-features-subtitle');
    var featuresGrid = document.getElementById('lp-features-grid');

    var title = section.sectionTitle || section.section_title || '';
    var subtitle = section.sectionSubtitle || section.section_subtitle || '';

    if (featuresTitle) featuresTitle.textContent = title;
    if (featuresSubtitle) featuresSubtitle.textContent = subtitle;
    
    var items = (section.extraData && section.extraData.items) || (section.extra_data && section.extra_data.items);
    if (featuresGrid && items) {
        featuresGrid.innerHTML = items.map(function(item) {
            return '<div class="bg-slate-50 dark:bg-slate-800 rounded-2xl p-6 hover:shadow-lg transition">' +
                '<div class="w-14 h-14 bg-blue-100 dark:bg-blue-900/30 rounded-2xl flex items-center justify-center text-blue-600 dark:text-blue-400 mb-4">' + (icons[item.icon] || icons.search) + '</div>' +
                '<h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">' + item.title + '</h3>' +
                '<p class="text-slate-600 dark:text-slate-400 text-sm">' + item.description + '</p></div>';
        }).join('');
    }
}

function renderLandingHowItWorks(section) {
    var howTitle = document.getElementById('lp-how-title');
    var howSubtitle = document.getElementById('lp-how-subtitle');
    var howContent = document.getElementById('lp-how-content');

    var title = section.sectionTitle || section.section_title || '';
    var subtitle = section.sectionSubtitle || section.section_subtitle || '';
    var content = section.sectionContent || section.section_content || '';

    if (howTitle) howTitle.textContent = title;
    if (howSubtitle) howSubtitle.textContent = subtitle;
    if (howContent) {
        var steps = (section.extraData && section.extraData.steps) || (section.extra_data && section.extra_data.steps);
        var stepsHtml = Array.isArray(steps) && steps.length > 0
            ? '<div class="grid gap-4 md:grid-cols-3 mt-6">' + steps.map(function(step) {
                return '<div class="bg-slate-50 dark:bg-slate-800 rounded-2xl p-5 border border-slate-100 dark:border-slate-700">' +
                    '<div class="text-blue-600 dark:text-blue-400 font-black text-sm mb-3">' + (step.number || '') + '</div>' +
                    '<h3 class="text-base font-bold text-slate-900 dark:text-white mb-2">' + (step.title || '') + '</h3>' +
                    '<p class="text-slate-600 dark:text-slate-400 text-sm">' + (step.description || '') + '</p></div>';
            }).join('') + '</div>'
            : '';
        howContent.innerHTML = content + stepsHtml;
    }
}

function renderLandingBenefits(section, icons) {
    var benefitsTitle = document.getElementById('lp-benefits-title');
    var benefitsSubtitle = document.getElementById('lp-benefits-subtitle');
    var benefitsGrid = document.getElementById('lp-benefits-grid');

    var title = section.sectionTitle || section.section_title || '';
    var subtitle = section.sectionSubtitle || section.section_subtitle || '';

    if (benefitsTitle) benefitsTitle.textContent = title;
    if (benefitsSubtitle) benefitsSubtitle.textContent = subtitle;

    var items = (section.extraData && section.extraData.items) || (section.extra_data && section.extra_data.items);
    if (benefitsGrid && items) {
        benefitsGrid.innerHTML = items.map(function(item) {
            return '<div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm">' +
                '<div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center text-emerald-600 dark:text-emerald-400 mb-4">' + (icons[item.icon] || icons['check-circle']) + '</div>' +
                '<h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">' + item.title + '</h3>' +
                '<p class="text-slate-600 dark:text-slate-400 text-sm">' + item.description + '</p></div>';
        }).join('');
    }
}

function renderLandingTestimonials(section) {
    var testimonialsTitle = document.getElementById('lp-testimonials-title');
    var testimonialsSubtitle = document.getElementById('lp-testimonials-subtitle');
    var testimonialsGrid = document.getElementById('lp-testimonials-grid');

    var title = section.sectionTitle || section.section_title || '';
    var subtitle = section.sectionSubtitle || section.section_subtitle || '';

    if (testimonialsTitle) testimonialsTitle.textContent = title;
    if (testimonialsSubtitle) testimonialsSubtitle.textContent = subtitle;

    var items = (section.extraData && section.extraData.items) || (section.extra_data && section.extra_data.items);
    var testimonialsSection = document.getElementById('testimonials');
    if (!testimonialsSection && testimonialsGrid && testimonialsGrid.closest) testimonialsSection = testimonialsGrid.closest('section');
    if (!testimonialsSection && testimonialsTitle && testimonialsTitle.closest) testimonialsSection = testimonialsTitle.closest('section');
    var hasItems = Array.isArray(items) && items.length > 0;
    if (testimonialsSection) testimonialsSection.classList.toggle('hidden', !hasItems);
    if (!hasItems) {
        if (testimonialsTitle) testimonialsTitle.textContent = '';
        if (testimonialsSubtitle) testimonialsSubtitle.textContent = '';
        if (testimonialsGrid) testimonialsGrid.innerHTML = '';
        return;
    }
    if (testimonialsGrid && hasItems) {
        testimonialsGrid.innerHTML = items.map(function(item) {
            return '<div class="bg-slate-50 dark:bg-slate-800 rounded-2xl p-6">' +
                '<p class="text-slate-700 dark:text-slate-300 mb-4">"' + item.text + '"</p>' +
                '<div class="flex items-center gap-3">' +
                '<div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">' + item.name.charAt(0) + '</div>' +
                '<div><p class="font-bold text-slate-900 dark:text-white text-sm">' + item.name + '</p>' +
                '<p class="text-slate-500 dark:text-slate-400 text-xs">' + item.company + '</p></div></div></div>';
        }).join('');
    }
}

function renderLandingFaq(section) {
    var faqTitle = document.getElementById('lp-faq-title');
    var faqSubtitle = document.getElementById('lp-faq-subtitle');
    var faqList = document.getElementById('lp-faq-list');

    var title = section.sectionTitle || section.section_title || '';
    var subtitle = section.sectionSubtitle || section.section_subtitle || '';

    if (faqTitle) faqTitle.textContent = title;
    if (faqSubtitle) faqSubtitle.textContent = subtitle;

    var items = (section.extraData && section.extraData.items) || (section.extra_data && section.extra_data.items);
    if (faqList && items) {
        faqList.innerHTML = items.map(function(item) {
            return '<div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm">' +
                '<h3 class="font-bold text-slate-900 dark:text-white mb-2">' + item.question + '</h3>' +
                '<p class="text-slate-600 dark:text-slate-400 text-sm">' + item.answer + '</p></div>';
        }).join('');
    }
}

function renderLandingCta(section) {
    var ctaTitle = document.getElementById('lp-cta-title');
    var ctaSubtitle = document.getElementById('lp-cta-subtitle');
    var ctaContent = document.getElementById('lp-cta-content');
    var ctaButton = document.getElementById('lp-cta-button');
    var ctaSecondary = document.getElementById('lp-cta-secondary');

    if (ctaTitle) ctaTitle.textContent = section.sectionTitle || '';
    if (ctaSubtitle) ctaSubtitle.textContent = section.sectionSubtitle || '';
    if (ctaContent) ctaContent.innerHTML = section.sectionContent || '';
    if (ctaButton && section.extraData && section.extraData.cta_text) {
        ctaButton.textContent = section.extraData.cta_text;
    }
    if (ctaSecondary && section.extraData && section.extraData.secondary_cta_text) {
        ctaSecondary.textContent = section.extraData.secondary_cta_text;
        var secondaryHref = section.extraData.secondary_cta_link || section.extraData.secondaryCtaLink || 'solucoes.php';
        if (ctaSecondary.tagName === 'A' && /^(?:[a-z0-9][a-z0-9-]*\.php|\/|#)/i.test(secondaryHref)) {
            ctaSecondary.setAttribute('href', secondaryHref);
        }
        ctaSecondary.classList.remove('hidden');
    }
}

function renderLandingFooter(section) {
    var footerContent = document.getElementById('lp-footer-content');
    var content = section.sectionContent || section.section_content || '';
    if (footerContent) footerContent.innerHTML = content;
}

function renderLandingSections(sections) {
    var icons = {
        search: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>',
        map: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" /></svg>',
        chart: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>',
        zap: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>',
        shield: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>',
        users: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" /></svg>',
        clock: '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        'check-circle': '<svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>'
    };

    if (!Array.isArray(sections)) return;

    // Não exibe uma seção de depoimentos vazia. Conteúdo de clientes só pode
    // aparecer quando houver dados reais cadastrados pelo administrador.
    renderLandingTestimonials({});

    sections.forEach(function(section) {
        var key = section.sectionKey || section.section_key;
        switch(key) {
            case 'hero': renderLandingHero(section); break;
            case 'features': renderLandingFeatures(section, icons); break;            case 'how_it_works': renderLandingHowItWorks(section); break;
            case 'benefits': renderLandingBenefits(section, icons); break;
            case 'testimonials': renderLandingTestimonials(section); break;
            case 'faq': renderLandingFaq(section); break;
            case 'cta': renderLandingCta(section); break;
            case 'footer': renderLandingFooter(section); break;
        }
    });
}

function renderLandingPlans(plans) {
    var container = document.getElementById('landing-plans-grid');
    if (!container) return;
    if (!plans || plans.length === 0) {
        container.innerHTML = '<p class="text-center col-span-full text-slate-500">Nenhum plano disponível no momento.</p>';
        return;
    }
    container.innerHTML = plans.map(function(plan) {
        var isFree = plan.priceMonthly === 0 || plan.priceMonthly === '0.00' || !plan.priceMonthly;
        var price = isFree ? 'Grátis' : 'R$ ' + parseFloat(plan.priceMonthly).toFixed(2).replace('.', ',');
        var limitText = (plan.tokenLimit || 0).toLocaleString ? Number(plan.tokenLimit || 0).toLocaleString('pt-BR') + ' créditos' : (plan.tokenLimit || 0) + ' créditos';
        var escape = function (value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char]; }); };
        var moduleLabels = Array.isArray(plan.moduleLabels) ? plan.moduleLabels : [];
        var modulesText = moduleLabels.length ? moduleLabels.join(' · ') : 'Módulos conforme configuração do plano';
        var includedUsersText = Math.max(1, Number(plan.includedUsers || 1)) + ' usuário(s) incluído(s)';
        var bonus = Number(plan.contractBonusCredits || 0);
        var bonusText = bonus > 0 ? '<div class="mt-3 rounded-xl bg-emerald-100 px-3 py-2 text-xs font-black text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">+ ' + bonus + ' créditos de bônus na contratação</div>' : '<div class="mt-3 rounded-xl bg-blue-100 px-3 py-2 text-xs font-black text-blue-700 dark:bg-blue-950/40 dark:text-blue-300">Teste com todos os módulos liberados</div>';

        // Botão direcionando diretamente para o cadastro, sem iniciar cobrança.
        return '<div class="bg-slate-50 dark:bg-slate-800 rounded-2xl p-6 ' + (isFree ? 'ring-2 ring-blue-500' : '') + '">' +
            '<h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">' + escape(plan.name) + '</h3>' +
            '<div class="mb-4"><span class="text-3xl font-black text-slate-900 dark:text-white">' + price + '</span>' +
            (!isFree ? '<span class="text-slate-500 dark:text-slate-400">/mês</span>' : '') + '</div>' +
            '<p class="text-slate-600 dark:text-slate-400 text-sm mb-2">' + limitText + '</p>' +
            '<p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400"><strong>Módulos:</strong> ' + escape(modulesText) + '</p>' +
            '<p class="mt-1 text-xs font-bold text-slate-500 dark:text-slate-400">' + escape(includedUsersText) + '</p>' +
            bonusText +
            '<button type="button" onclick="openCadastroModal()" class="mt-4 block w-full py-3 text-center ' + (isFree ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-900 hover:bg-slate-800') + ' text-white font-bold rounded-xl transition">Assinar</button></div>';
    }).join('');
}

function openLoginModal() {
    showLogin();
}

// Compatibilidade com os botões da página pública que usam onclick inline.
window.openLoginModal = openLoginModal;

function renderHeaderTokenWarning() {
    var tokenBanner = document.getElementById('header-token-warning');
    if (!tokenBanner) return;

    var tenant = AppState.tenant || {};
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';

    if (isSuperAdmin) {
        tokenBanner.classList.add('hidden');
        return;
    }

    var daysLeft = tenant.trialDaysLeft !== undefined ? tenant.trialDaysLeft : 30;
    var isExpired = tenant.trialExpired || tenant.status === 'suspended' || daysLeft <= 0;

    if (isExpired) {
        tokenBanner.className = 'p-3 bg-red-600 text-white text-xs font-bold flex items-center justify-between px-6 shadow-md';
        tokenBanner.innerHTML = `
            <span>⚠️ <strong>Seu período de teste de 30 dias expirou!</strong> Adquira mais créditos ou assine um plano para liberar o acesso total.</span>
            <button onclick="setActiveTab('choose-plan')" class="bg-white text-red-600 px-3 py-1 rounded-lg text-xs font-black uppercase hover:bg-slate-100">Ver Planos</button>
        `;
        tokenBanner.classList.remove('hidden');
    } else if (daysLeft <= 7) {
        tokenBanner.className = 'p-3 bg-amber-500 text-white text-xs font-bold flex items-center justify-between px-6 shadow-md';
        tokenBanner.innerHTML = `
            <span>⏳ Você tem <strong>${daysLeft} dia(s) restante(s)</strong> no seu período de teste.</span>
            <button onclick="setActiveTab('choose-plan')" class="bg-slate-900 text-white px-3 py-1 rounded-lg text-xs font-black uppercase hover:bg-slate-800">Ver Planos</button>
        `;
        tokenBanner.classList.remove('hidden');
    } else {
        tokenBanner.classList.add('hidden');
    }
}

function updateSearchApiUI() {
    const indicator = document.getElementById('search-api-indicator');
    const status = document.getElementById('search-api-status');
    if (!indicator || !status) return;
    
    var configured = false;
    if (AppState.config) {
        var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
        configured = isSuperAdmin
            ? (AppState.config.scraperApiKey && String(AppState.config.scraperApiKey).trim()) !== ''
            : !!AppState.config.scraperApiKeyConfigured;
    }
    
    if (configured) {
        indicator.className = 'w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981]';
        status.textContent = 'Conectado ao Google Maps';
    } else if (!AppState.config) {
        indicator.className = 'w-2 h-2 rounded-full bg-yellow-500 animate-pulse';
        status.textContent = 'Verificando...';
    } else {
        indicator.className = 'w-2 h-2 rounded-full bg-red-500';
        status.textContent = 'API não configurada';
    }
}
function updateDocumentTitle() {
    if (!AppState.user) return;
    var isSuperAdmin = String(AppState.user.profile).toLowerCase() === 'super_admin';
    syncApiBuscaMenuAccess();
    var companyName = isSuperAdmin
        ? (AppState.platformCompanyName && String(AppState.platformCompanyName).trim() ? AppState.platformCompanyName.trim() : 'MapsProspector Pro')
        : (AppState.tenant && AppState.tenant.name ? String(AppState.tenant.name).trim() : 'Empresa');
    var instanceName = (AppState.config && AppState.config.tenantName && String(AppState.config.tenantName).trim()) ? String(AppState.config.tenantName).trim() : 'Nome da empresa SaaS';
    document.title = companyName + ' | ' + instanceName;
}

// Controle de acesso visual da API de Busca. O item pode variar entre
// versões do index.php, por isso a regra combina atributos, IDs, classes e
// o texto efetivamente renderizado na barra lateral.
function syncApiBuscaMenuAccess() {
    if (!AppState.user) return;

    var isSuperAdmin = String(AppState.user.profile || '').toLowerCase() === 'super_admin';
    var restricted = !isSuperAdmin;
    var body = document.body;

    if (body) {
        body.setAttribute('data-api-busca-restricted', restricted ? 'true' : 'false');
    }

    if (!document.getElementById('api-busca-access-style')) {
        var style = document.createElement('style');
        style.id = 'api-busca-access-style';
        style.textContent = [
            'body[data-api-busca-restricted="true"] [data-tab="api-busca"],',
            'body[data-api-busca-restricted="true"] [data-tab="api_busca"],',
            'body[data-api-busca-restricted="true"] [data-tab="apiBusca"],',
            'body[data-api-busca-restricted="true"] #nav-api-busca,',
            'body[data-api-busca-restricted="true"] #nav-btn-api-busca,',
            'body[data-api-busca-restricted="true"] .nav-item-api-busca,',
            'body[data-api-busca-restricted="true"] .api-busca-nav { display: none !important; }'
        ].join('\n');
        document.head.appendChild(style);
    }

    var knownSelectors = [
        '[data-tab="api-busca"]',
        '[data-tab="api_busca"]',
        '[data-tab="apiBusca"]',
        '#nav-api-busca',
        '#nav-btn-api-busca',
        '.nav-item-api-busca',
        '.api-busca-nav'
    ];

    document.querySelectorAll(knownSelectors.join(',')).forEach(function(menuItem) {
        menuItem.classList.toggle('hidden', restricted);
        menuItem.style.display = restricted ? 'none' : '';
        menuItem.setAttribute('aria-hidden', restricted ? 'true' : 'false');
        if (restricted) menuItem.setAttribute('tabindex', '-1');
        else menuItem.removeAttribute('tabindex');
    });

    // Fallback para estruturas antigas que não têm data-tab no botão.
    document.querySelectorAll('#dashboard .tab-btn, #dashboard button, #dashboard a, #dashboard [role="button"]').forEach(function(candidate) {
        var label = (candidate.getAttribute('aria-label') || candidate.textContent || '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLocaleLowerCase('pt-BR');

        if (label === 'api de busca' || label.indexOf('api de busca') !== -1) {
            var menuItem = candidate.closest('[data-tab], .tab-btn, li, button, a, [role="button"]') || candidate;
            menuItem.classList.toggle('hidden', restricted);
            menuItem.style.display = restricted ? 'none' : '';
            menuItem.setAttribute('aria-hidden', restricted ? 'true' : 'false');
            if (restricted) menuItem.setAttribute('tabindex', '-1');
            else menuItem.removeAttribute('tabindex');
        }
    });

    // Mantém a regra se a navegação for reconstruída posteriormente por outro script.
    if (!window.__apiBuscaMenuObserver && body) {
        var refreshTimer;
        window.__apiBuscaMenuObserver = new MutationObserver(function() {
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(syncApiBuscaMenuAccess, 0);
        });
        window.__apiBuscaMenuObserver.observe(body, { childList: true, subtree: true });
    }
}

function loadSidebarCompanyName() {
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
    
    fetch(API_BASE + 'platform-config.php', { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var d = data.data || data;
            
            // 1. Nome da Marca Principal / SaaS (Título em Negrito)
            var saasName = (d && d.saasCompanyName && String(d.saasCompanyName).trim()) 
                ? String(d.saasCompanyName).trim() 
                : 'Maps Prospector';
            AppState.platformCompanyName = saasName;
            
            var el = document.getElementById('sidebar-company-name');
            if (el) el.textContent = saasName;

            // 2. Nome do Cliente / Instância (Texto Secundário em Cinza)
            // Prioridade: Configuração de Instância -> Nome da Empresa Cadastrada -> Nome do SaaS
            var instanceName = '';
            
            if (AppState.config && AppState.config.tenantName && String(AppState.config.tenantName).trim() !== '') {
                instanceName = String(AppState.config.tenantName).trim();
            } else if (AppState.tenant && AppState.tenant.name && String(AppState.tenant.name).trim() !== '') {
                instanceName = String(AppState.tenant.name).trim();
            } else if (AppState.user && AppState.user.companyName && String(AppState.user.companyName).trim() !== '') {
                instanceName = String(AppState.user.companyName).trim();
            } else {
                instanceName = isSuperAdmin ? 'Painel Plataforma' : 'Minha Empresa';
            }

            var instanceEl = document.getElementById('sidebar-instance-name');
            if (instanceEl) instanceEl.textContent = instanceName;

            // Atualiza o subtítulo no topo do Dashboard
            var subtitleEl = document.getElementById('header-dashboard-subtitle');
            if (subtitleEl) {
                subtitleEl.textContent = 'Dashboard ' + instanceName.toUpperCase();
            }

            updateDocumentTitle();
        })
        .catch(function(err) {
            console.warn('Erro ao carregar nome da marca:', err);
        });
}

// Onboarding de ajuda: a marcação é local a cada conta/navegador e não usa
// dados sensíveis. Assim, a abertura do guia não depende de uma migração no
// banco e não interfere com sessões existentes ou com os aceites legais.
const HELP_ONBOARDING_VERSION = '20260817-39';

function getHelpOnboardingProfile(profile) {
    var normalized = String(profile || '').toLowerCase();
    if (normalized === 'super_admin' || normalized === 'admin') return 'admin';
    if (['supervisor', 'manager', 'gerente'].includes(normalized)) return 'supervisor';
    return 'user';
}

function getHelpOnboardingStorageKey() {
    if (!AppState.user) return '';
    var userIdentifier = AppState.user.id || AppState.user.email || AppState.user.name || 'anonymous';
    var role = getHelpOnboardingProfile(AppState.user.profile);
    return 'mapsprospector:help-onboarding:' + HELP_ONBOARDING_VERSION + ':' + role + ':' + encodeURIComponent(String(userIdentifier));
}

function shouldOpenHelpOnFirstAccess() {
    var storageKey = getHelpOnboardingStorageKey();
    if (!storageKey) return false;
    try {
        if (window.localStorage.getItem(storageKey) === 'seen') return false;
        window.localStorage.setItem(storageKey, 'seen');
        return true;
    } catch (error) {
        // Se o navegador bloquear armazenamento local, não força redirecionamentos
        // repetidos. A Central de Ajuda continua disponível normalmente pelo menu.
        return false;
    }
}

function showDashboard() {
    var loginScreen = document.getElementById('login-screen');
    var dashboard = document.getElementById('dashboard');
    var landingPage = document.getElementById('landing-page');
    
    // 💡 Declaração da variável do tema
    var themeToggle = document.getElementById('btn-theme-toggle');
    if (themeToggle) {
        themeToggle.onclick = function(e) {
            e.stopPropagation();
            toggleTheme();
        };
    }

    if (landingPage) landingPage.style.display = 'none';
    if (loginScreen) {
        loginScreen.style.setProperty('display', 'none', 'important');
        loginScreen.classList.add('hidden');
    }
    if (dashboard) {
        dashboard.style.setProperty('display', 'flex', 'important');
        dashboard.classList.remove('hidden');
    }
    
    if (themeToggle) themeToggle.classList.remove('hidden');
    
    loadSidebarCompanyName();
    renderHeaderTokenWarning();
    updateSearchApiUI();
    if (window.NotificationCenter && typeof window.NotificationCenter.refreshBadge === 'function') {
        window.NotificationCenter.refreshBadge();
    }
    
    if (AppState.user) {
        var userNameEl = document.getElementById('user-name');
        if (userNameEl) userNameEl.textContent = AppState.user.name;
        
        var roleEl = document.getElementById('user-role');
        if (roleEl) {
            var roleLabels = { super_admin: 'Super Admin', admin: 'Admin', manager: 'Gerente', gerente: 'Gerente', user: 'Usuário' };
            roleEl.textContent = roleLabels[AppState.user.profile] || AppState.user.profile || 'Usuário';
        }
        
        var isSuperAdmin = String(AppState.user.profile).toLowerCase() === 'super_admin';
        var profileForCatalog = String(AppState.user.profile || '').toLowerCase();
        var tenantIdForCatalog = Number(AppState.user.tenant_id || AppState.user.tenantId || (AppState.tenant && AppState.tenant.id) || 0);
        var canManagePlanCatalog = isSuperAdmin || (profileForCatalog === 'admin' && tenantIdForCatalog === 1);
        syncApiBuscaMenuAccess();
        var blockAdmin = document.getElementById('nav-block-administracao');
        var dividerNormal = document.getElementById('nav-divider-normal');
        
        if (blockAdmin) blockAdmin.classList.toggle('hidden', !canManagePlanCatalog);
        if (dividerNormal) dividerNormal.classList.toggle('hidden', canManagePlanCatalog);
        
        // Meu Plano é exclusivo do administrador da empresa. A existência de
        // tenant, isoladamente, não pode reexibir a opção a membros da equipe.
        var profileForPlan = String(AppState.user.profile || '').toLowerCase();
        var canSeeCompanyPlan = profileForPlan === 'admin';
        var btnMeuPlano = document.getElementById('nav-btn-choose-plan');
        if (btnMeuPlano) btnMeuPlano.classList.toggle('hidden', !canSeeCompanyPlan);
        
        var apiStatusWidget = document.getElementById('sidebar-api-status-widget');
        if (apiStatusWidget) apiStatusWidget.classList.toggle('hidden', !isSuperAdmin);
        
        const profile = String(AppState.user.profile).toLowerCase();
        const canUseResearchModules = !!AppState.user && (
            isSuperAdmin || (AppState.user.can_search !== false && Number(AppState.user.can_search) !== 0)
        );
        const canUseCrm = isSuperAdmin || profile === 'admin' || appHasModule('crm');
        const btnCrm = document.getElementById('nav-btn-crm');
        if (btnCrm) btnCrm.classList.toggle('hidden', !canUseCrm);

        // Os módulos de pesquisa são operacionais e ficam visíveis para toda
        // conta autenticada; validações comerciais permanecem nos endpoints.
        const btnLinkedIn = document.getElementById('nav-btn-linkedin');
        if (btnLinkedIn) btnLinkedIn.classList.toggle('hidden', !canUseResearchModules);
        document.querySelectorAll('[data-tab="search"], [data-tab="cnpj-lookup"], [data-tab="linkedin"]').forEach(function(item) {
            item.classList.toggle('hidden', !canUseResearchModules);
        });
        
        // Todo item com a classe admin-only pertence à administração da
        // plataforma. Administradores e gerentes de empresas não podem vê-lo.
        document.querySelectorAll('.admin-only').forEach(function(el) {
            var allowed = isSuperAdmin || (el.id === 'tab-btn-plans' && canManagePlanCatalog);
            el.classList.toggle('hidden', !allowed);
        });
        const btnLandingPage = document.querySelector('[data-tab="landing-page"]');
	if (btnLandingPage) {
	     btnLandingPage.classList.toggle('hidden', !isSuperAdmin);
}
        // Tráfego do site é exclusivo da conta plataforma (super admin).
        // Não fica disponível para administradores de empresas.
        const btnTraffic = document.querySelector('[data-tab="traffic"]');
	if (btnTraffic) {
	     btnTraffic.classList.toggle('hidden', !isSuperAdmin);
}
        // Regra explícita de menu: usuário comum não administra equipe,
        // créditos ou plano. A seleção por data-tab cobre variações do menu.
        const btnTeam = document.getElementById('nav-btn-team');
        const teamMenuItems = document.querySelectorAll('#nav-btn-team, [data-tab="team"]');
        const btnCnpj = document.querySelector('[data-tab="cnpj-lookup"]');
        const creditMenuItems = document.querySelectorAll('[data-tab="request-credits"]');
        const planMenuItems = document.querySelectorAll('[data-tab="choose-plan"]');
        const btnSettings = document.querySelector('[data-tab="settings"]');
        // Configurações de webhook: plataforma (super_admin), empresa e pessoal
        // (admin) ou somente pessoal (supervisor e usuário comum).
        const isRegularUser = profile === 'normal' || profile === 'user';
        const canAccessSettings = isSuperAdmin || profile === 'admin' || profile === 'supervisor' || isRegularUser;
        const canManageTeam = ['super_admin', 'admin', 'supervisor', 'manager', 'gerente'].includes(profile);
        const canManageCompanyBilling = profile === 'admin';
        
        if (btnTeam) btnTeam.classList.toggle('hidden', !canManageTeam);
        teamMenuItems.forEach(function(item) { item.classList.toggle('hidden', !canManageTeam); });
        if (btnCnpj) btnCnpj.classList.toggle('hidden', !canUseResearchModules);
        creditMenuItems.forEach(function(item) { item.classList.toggle('hidden', !canManageCompanyBilling); });
        planMenuItems.forEach(function(item) { item.classList.toggle('hidden', !canManageCompanyBilling); });
        if (btnSettings) btnSettings.classList.toggle('hidden', !canAccessSettings);
        if (btnCrm) btnCrm.classList.toggle('hidden', !canUseCrm);
        // Reaplica no próximo frame e após atualizações tardias da interface,
        // impedindo que cards ou dados assíncronos restaurem itens indevidos.
        enforceProfileMenuVisibility(profile);
        window.requestAnimationFrame(function() { enforceProfileMenuVisibility(profile); });
        window.setTimeout(function() { enforceProfileMenuVisibility(profile); }, 250);
        startProfileMenuVisibilityGuard();
    }
    
    var impersonationBanner = document.getElementById('impersonation-banner');
    var impersonationTenantName = document.getElementById('impersonation-tenant-name');
    if (impersonationBanner) {
        if (AppState.impersonating && AppState.impersonatingTenantName) {
            if (impersonationTenantName) impersonationTenantName.textContent = AppState.impersonatingTenantName;
            impersonationBanner.classList.remove('hidden');
        } else {
            impersonationBanner.classList.add('hidden');
        }
    }
    // No primeiro acesso desta versão, substitui somente a aba inicial pelo
    // guia compatível com o perfil. Nas entradas seguintes, preserva a aba
    // normal do dashboard e não altera a navegação do usuário.
    var initialTab = shouldOpenHelpOnFirstAccess() ? 'help' : AppState.activeTab;
    setActiveTab(initialTab);
    window.setTimeout(checkCurrentLegalConsents, 120);
}

// Navegação móvel: o menu lateral permanece intacto no desktop e é exibido em
// formato de painel retrátil em telas estreitas, evitando perda de conteúdo.
function isMobileViewport() {
    return !!(window.matchMedia && window.matchMedia('(max-width: 767px)').matches);
}

function setMobileSidebarOpen(shouldOpen) {
    var toggle = document.getElementById('btn-mobile-nav-toggle');
    var isOpen = !!shouldOpen && isMobileViewport();
    document.body.classList.toggle('mobile-nav-open', isOpen);
    if (toggle) toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}

function openMobileSidebar() {
    setMobileSidebarOpen(true);
}

function closeMobileSidebar() {
    setMobileSidebarOpen(false);
}

function applyMobileContentRules() {
    var contentArea = document.getElementById('content-area');
    if (!contentArea) return;

    // Tabelas administrativas continuam completas; no celular passam a ter
    // rolagem horizontal própria em vez de comprimir ou cortar as colunas.
    contentArea.querySelectorAll('table').forEach(function(table) {
        var existingScrollable = table.closest('.overflow-x-auto, .mobile-table-scroll');
        if (existingScrollable) {
            existingScrollable.classList.add('mobile-table-scroll');
            return;
        }
        var wrapper = document.createElement('div');
        wrapper.className = 'mobile-table-scroll';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
}

function setupMobileNavigation() {
    if (window.__mapsProspectorMobileNavigationReady) return;
    window.__mapsProspectorMobileNavigationReady = true;

    var toggle = document.getElementById('btn-mobile-nav-toggle');
    var close = document.getElementById('btn-close-mobile-nav');
    var backdrop = document.getElementById('mobile-sidebar-backdrop');

    if (toggle) toggle.addEventListener('click', openMobileSidebar);
    if (close) close.addEventListener('click', closeMobileSidebar);
    if (backdrop) backdrop.addEventListener('click', closeMobileSidebar);

    window.addEventListener('resize', function() {
        if (!isMobileViewport()) closeMobileSidebar();
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape' && document.body.classList.contains('mobile-nav-open')) {
            closeMobileSidebar();
        }
    });

    var contentArea = document.getElementById('content-area');
    if (contentArea && !window.__mapsProspectorMobileContentObserver) {
        var queued = false;
        window.__mapsProspectorMobileContentObserver = new MutationObserver(function() {
            if (queued) return;
            queued = true;
            window.requestAnimationFrame(function() {
                queued = false;
                applyMobileContentRules();
            });
        });
        window.__mapsProspectorMobileContentObserver.observe(contentArea, { childList: true, subtree: true });
    }
    applyMobileContentRules();
}

async function checkCurrentLegalConsents() {
    if (!AppState.user || String(AppState.user.profile || '').toLowerCase() === 'super_admin') return;
    if (document.getElementById('legal-consents-update-modal')) return;
    try {
        const response = await fetch('/api/legal-consents.php', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
        const payload = await response.json();
        if (!response.ok || !payload.success || !payload.data?.required) return;
        showLegalConsentsUpdateModal(payload.data.missing || {});
    } catch (error) {
        console.warn('Não foi possível verificar os documentos legais atuais.', error);
    }
}

function showLegalConsentsUpdateModal(missing) {
    const documents = Object.entries(missing);
    if (!documents.length || document.getElementById('legal-consents-update-modal')) return;
    const options = documents.map(([key, doc]) => `
        <label class="flex items-start gap-3 rounded-xl border border-slate-200 dark:border-slate-600 p-4 cursor-pointer">
            <input type="checkbox" data-legal-key="${key}" class="mt-1 h-4 w-4 accent-blue-600">
            <span class="text-sm text-slate-700 dark:text-slate-200">Li e aceito os <a class="font-bold underline" target="_blank" rel="noopener noreferrer" href="${doc.url}">${doc.label}</a> (versão ${doc.version}).</span>
        </label>`).join('');
    const modal = document.createElement('div');
    modal.id = 'legal-consents-update-modal';
    modal.className = 'fixed inset-0 z-[1000] flex items-center justify-center bg-slate-950/70 p-4';
    modal.innerHTML = `<div role="dialog" aria-modal="true" aria-labelledby="legal-consents-update-title" class="w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-900">
        <p class="text-xs font-black uppercase tracking-wider text-blue-600">Atualização de documentos</p>
        <h2 id="legal-consents-update-title" class="mt-2 text-2xl font-black text-slate-900 dark:text-white">Confirme os documentos atualizados</h2>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Para continuar usando a plataforma, revise e aceite os documentos abaixo. Os aceites anteriores permanecem preservados no histórico.</p>
        <div class="mt-5 space-y-3">${options}</div>
        <p id="legal-consents-update-error" class="mt-4 hidden text-sm font-bold text-red-600" role="alert"></p>
        <button id="btn-confirm-legal-consents" type="button" class="mt-6 w-full rounded-2xl bg-blue-600 px-5 py-4 font-black text-white transition hover:bg-blue-700 active:scale-[.98]">ACEITAR E CONTINUAR</button>
    </div>`;
    document.body.appendChild(modal);
    document.getElementById('btn-confirm-legal-consents')?.addEventListener('click', async function () {
        const errorEl = document.getElementById('legal-consents-update-error');
        const accepted = {};
        let complete = true;
        documents.forEach(([key]) => { const checked = modal.querySelector(`[data-legal-key="${key}"]`)?.checked === true; accepted[key] = checked; complete = complete && checked; });
        if (!complete) { errorEl.textContent = 'É necessário aceitar todos os documentos atualizados.'; errorEl.classList.remove('hidden'); return; }
        this.disabled = true;
        this.textContent = 'REGISTRANDO...';
        try {
            const response = await fetch('/api/legal-consents.php', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ accepted }) });
            const payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.error || 'Não foi possível registrar os aceites.');
            modal.remove();
        } catch (error) {
            errorEl.textContent = error.message || 'Não foi possível registrar os aceites.';
            errorEl.classList.remove('hidden');
            this.disabled = false;
            this.textContent = 'ACEITAR E CONTINUAR';
        }
    });
}

function appHasModule(moduleKey) {
    var modules = AppState.tokenUsage && AppState.tokenUsage.modules;
    if (!modules || typeof modules !== 'object') return false;
    var entry = modules[moduleKey];
    return entry === true || !!(entry && entry.enabled);
}

function enforceProfileMenuVisibility(profile) {
    profile = String(profile || AppState.user?.profile || '').toLowerCase();
    if (!profile) return;

    var canManageTeam = ['super_admin', 'admin', 'supervisor', 'manager', 'gerente'].includes(profile);
    var canManageCompanyBilling = profile === 'admin';
    var canUseCrm = profile === 'super_admin' || profile === 'admin' || appHasModule('crm');
    var menuRules = [
        { selector: '#nav-btn-team, [data-tab="team"]', allowed: canManageTeam },
        { selector: '[data-tab="request-credits"]', allowed: canManageCompanyBilling },
        { selector: '#nav-btn-choose-plan, [data-tab="choose-plan"]', allowed: canManageCompanyBilling },
        { selector: '#nav-btn-crm, [data-tab="crm"]', allowed: canUseCrm }
    ];

    menuRules.forEach(function(rule) {
        document.querySelectorAll(rule.selector).forEach(function(item) {
            var mustHide = !rule.allowed;
            if (item.classList.contains('hidden') !== mustHide) item.classList.toggle('hidden', mustHide);
            var ariaValue = rule.allowed ? 'false' : 'true';
            if (item.getAttribute('aria-hidden') !== ariaValue) item.setAttribute('aria-hidden', ariaValue);
        });
    });
}

function startProfileMenuVisibilityGuard() {
    if (window._profileMenuVisibilityObserver) {
        window._profileMenuVisibilityObserver.disconnect();
        window._profileMenuVisibilityObserver = null;
    }

    var profile = String(AppState.user?.profile || '').toLowerCase();
    if (!['normal', 'user'].includes(profile)) return;

    var target = document.getElementById('dashboard') || document.body;
    if (!target || typeof MutationObserver === 'undefined') return;

    var timer = null;
    window._profileMenuVisibilityObserver = new MutationObserver(function() {
        if (timer) window.clearTimeout(timer);
        timer = window.setTimeout(function() {
            enforceProfileMenuVisibility(profile);
        }, 0);
    });
    window._profileMenuVisibilityObserver.observe(target, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class', 'style', 'id']
    });
    enforceProfileMenuVisibility(profile);
}

function logout() {
    if (window._profileMenuVisibilityObserver) {
        window._profileMenuVisibilityObserver.disconnect();
        window._profileMenuVisibilityObserver = null;
    }
    fetch(API_BASE + 'auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' }),
        credentials: 'same-origin'
    }).then(function() {
        AppState.user = null;
        AppState.tenant = null;
        AppState.tokenUsage = null;
        AppState.platformCompanyName = null;
        AppState.activeTab = 'dashboard';
        document.title = 'MapsProspector Pro | CRM Integration';
        var tokenBanner = document.getElementById('header-token-warning');
        if (tokenBanner) tokenBanner.classList.add('hidden');
        var userDropdown = document.getElementById('user-dropdown');
        if (userDropdown) userDropdown.classList.add('hidden');
        showLogin();
    }).catch(function() {
        showLogin();
    });
}
const LEGAL_CONSENT_VERSIONS = Object.freeze({
    termsOfUse: '2026-08-17',
    privacyPolicy: '2026-08-17'
});

function ensureCadastroLegalConsents() {
    const legacyCheckbox = document.getElementById('cadastro-terms');
    if (!legacyCheckbox || document.getElementById('cadastro-terms-of-use')) return;

    const legacyLabel = legacyCheckbox.closest('label');
    if (!legacyLabel || !legacyLabel.parentNode) return;

    const consentGroup = document.createElement('fieldset');
    consentGroup.id = 'cadastro-legal-consents';
    consentGroup.className = 'mt-4 space-y-3 text-left';
    consentGroup.innerHTML = `
        <legend class="sr-only">Aceites legais obrigatórios</legend>
        <label class="flex items-start gap-3 text-[11px] text-slate-500 dark:text-slate-300 cursor-pointer">
            <input id="cadastro-terms-of-use" type="checkbox" class="mt-0.5 w-4 h-4 accent-blue-600" required>
            <span>Li e aceito os <a href="termos.php" target="_blank" rel="noopener noreferrer" class="underline font-bold">Termos de Uso</a> (versão ${LEGAL_CONSENT_VERSIONS.termsOfUse}).</span>
        </label>
        <label class="flex items-start gap-3 text-[11px] text-slate-500 dark:text-slate-300 cursor-pointer">
            <input id="cadastro-privacy-policy" type="checkbox" class="mt-0.5 w-4 h-4 accent-blue-600" required>
            <span>Li e aceito a <a href="privacidade.php" target="_blank" rel="noopener noreferrer" class="underline font-bold">Política de Privacidade</a> (versão ${LEGAL_CONSENT_VERSIONS.privacyPolicy}).</span>
        </label>`;
    legacyLabel.parentNode.replaceChild(consentGroup, legacyLabel);
}

function showCadastro() {
    const overlay = document.getElementById('modal-cadastro-overlay');
    const success = document.getElementById('cadastro-success');
    const loginScreen = document.getElementById('login-screen');
    if (loginScreen) loginScreen.style.display = 'none';
    if (overlay) overlay.classList.remove('hidden');
    if (success) { success.classList.add('hidden'); success.textContent = ''; }
    document.body.style.overflow = 'hidden';
    ensureCadastroLegalConsents();
    ensurePublicHomeActions();
}

function hideCadastro() {
    const overlay = document.getElementById('modal-cadastro-overlay');
    if (overlay) overlay.classList.add('hidden');
    document.body.style.overflow = '';
}

async function handleCadastroSubmit(e) {
    if (e && e.preventDefault) e.preventDefault();
    ensureCadastroLegalConsents();
    const company = (document.getElementById('reg-company')?.value || '').trim();
    const companyDocument = (document.getElementById('reg-cnpj')?.value || '').replace(/\D/g, '');
    const email = (document.getElementById('reg-email')?.value || '').trim().toLowerCase();
    const phone = (document.getElementById('reg-phone')?.value || '').trim();
    const name = (document.getElementById('reg-name')?.value || '').trim();
    const regPassword = document.getElementById('reg-password')?.value || '';
    const regPasswordConfirm = document.getElementById('reg-password-confirm')?.value || '';
    const termsOfUseAccepted = document.getElementById('cadastro-terms-of-use')?.checked === true;
    const privacyPolicyAccepted = document.getElementById('cadastro-privacy-policy')?.checked === true;
    const errorEl = document.getElementById('reg-error');
    const btn = document.getElementById('btn-cadastro');

    if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }
    
    if (!company) { if (errorEl) { errorEl.textContent = 'Nome da empresa é obrigatório.'; errorEl.classList.remove('hidden'); } return; }
    if (companyDocument && ![11, 14].includes(companyDocument.length)) { if (errorEl) { errorEl.textContent = 'Informe um CPF ou CNPJ válido, ou deixe o campo em branco.'; errorEl.classList.remove('hidden'); } return; }
    if (!email || !email.includes('@')) { if (errorEl) { errorEl.textContent = 'E-mail inválido.'; errorEl.classList.remove('hidden'); } return; }
    if (regPassword.length < 6) { if (errorEl) { errorEl.textContent = 'A senha deve ter no mínimo 6 caracteres.'; errorEl.classList.remove('hidden'); } return; }
    if (regPassword !== regPasswordConfirm) { if (errorEl) { errorEl.textContent = 'As senhas não coincidem.'; errorEl.classList.remove('hidden'); } return; }
    if (!termsOfUseAccepted || !privacyPolicyAccepted) {
        if (errorEl) { errorEl.textContent = 'É necessário aceitar separadamente os Termos de Uso e a Política de Privacidade para criar a conta.'; errorEl.classList.remove('hidden'); }
        return;
    }
    
    if (btn) btn.disabled = true;
    try {
        const res = await fetch(API_BASE + 'register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                companyName: company, 
                companyDocument: companyDocument,
                adminEmail: email, 
                adminPhone: phone, 
                adminName: name || undefined, 
                adminPassword: regPassword,
                legalConsents: {
                    terms_of_use: { accepted: true, documentVersion: LEGAL_CONSENT_VERSIONS.termsOfUse, documentUrl: 'termos.php' },
                    privacy_policy: { accepted: true, documentVersion: LEGAL_CONSENT_VERSIONS.privacyPolicy, documentUrl: 'privacidade.php' }
                }
            })  
        });
        const data = await res.json();
        if (data.success) {
            // A indicação é atribuída no primeiro login, quando a sessão do novo
            // usuário já existe. O código fica guardado por até 50 dias.
            hideCadastro();
            showLogin();
            const loginEmail = document.getElementById('login-email');
            showLoginSuccess(data.message || 'Empresa cadastrada com sucesso. Entre com seu e-mail e senha.');
            if (loginEmail) loginEmail.value = email;
            document.querySelectorAll('#form-cadastro input').forEach(input => input.value = '');
        } else {
            if (errorEl) { errorEl.textContent = data.error || 'Erro ao cadastrar.'; errorEl.classList.remove('hidden'); }
        }
    } catch (err) {
        if (errorEl) { errorEl.textContent = 'Erro de conexão.'; errorEl.classList.remove('hidden'); }
    } finally {
        if (btn) btn.disabled = false;
    }
}

function showModalPerfil() {
    var overlay = document.getElementById('modal-perfil-overlay');
    var errorEl = document.getElementById('perfil-error');
    var successEl = document.getElementById('perfil-success');
    if (overlay) overlay.classList.remove('hidden');
    if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }
    if (successEl) { successEl.classList.add('hidden'); successEl.textContent = ''; }
    
    var current = document.getElementById('perfil-senha-atual');
    var nova = document.getElementById('perfil-senha-nova');
    var confirm = document.getElementById('perfil-senha-confirm');
    if (current) current.value = '';
    if (nova) nova.value = '';
    if (confirm) confirm.value = '';
    
    var phone = document.getElementById('perfil-phone');
    if (phone && AppState.user) phone.value = AppState.user.phone || '';
    document.body.style.overflow = 'hidden';
}

function hideModalPerfil() {
    var overlay = document.getElementById('modal-perfil-overlay');
    if (overlay) overlay.classList.add('hidden');
    document.body.style.overflow = '';
}

async function handlePerfilSenhaSubmit(e) {
    if (e && e.preventDefault) e.preventDefault();
    
    var currentPassword = document.getElementById('perfil-senha-atual')?.value || '';
    var newPassword = document.getElementById('perfil-senha-nova')?.value || '';
    var newPasswordConfirm = document.getElementById('perfil-senha-confirm')?.value || '';
    var newPhone = document.getElementById('perfil-phone')?.value || '';
    var errorEl = document.getElementById('perfil-error');
    var successEl = document.getElementById('perfil-success');
    var btn = document.getElementById('btn-perfil-salvar');

    if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }
    
    if (newPassword && newPassword.length < 6) { 
        errorEl.textContent = 'A nova senha deve ter no mínimo 6 caracteres.'; 
        errorEl.classList.remove('hidden'); 
        return; 
    }
    if (newPassword !== newPasswordConfirm) { 
        errorEl.textContent = 'As senhas não coincidem.'; 
        errorEl.classList.remove('hidden'); 
        return; 
    }

    if (btn) btn.disabled = true;
    try {
        var res = await fetch(API_BASE + 'auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'update_profile', 
                currentPassword: currentPassword, 
                newPassword: newPassword,
                phone: newPhone 
            })
        });
        var data = await res.json();
        if (data.success) {
            if (successEl) { successEl.textContent = 'Perfil atualizado com sucesso.'; successEl.classList.remove('hidden'); }
            if (AppState.user) AppState.user.phone = newPhone;
            setTimeout(hideModalPerfil, 1500);
        } else {
            errorEl.textContent = data.error || 'Erro ao atualizar perfil.';
            errorEl.classList.remove('hidden');
        }
    } catch (err) {
        errorEl.textContent = 'Erro de conexão com servidor.';
        errorEl.classList.remove('hidden');
    } finally {
        if (btn) btn.disabled = false;
    }
}
function setPasswordResetMessage(element, message, isError) {
    if (!element) return;
    element.textContent = message;
    element.className = 'text-sm ' + (isError ? 'text-red-600' : 'text-emerald-600');
}

function openResetPasswordModal(token) {
    var modal = document.getElementById('reset-password-modal');
    var tokenInput = document.getElementById('reset-password-token');
    var message = document.getElementById('reset-password-message');
    if (!modal || !tokenInput) return;
    tokenInput.value = token;
    if (message) {
        message.textContent = '';
        message.classList.add('hidden');
    }
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
}

function closePasswordResetModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
}

function setupPasswordResetEvents() {
    var forgotLink = document.getElementById('forgot-password-link');
    var forgotModal = document.getElementById('forgot-password-modal');
    var forgotClose = document.getElementById('forgot-password-close');
    var forgotForm = document.getElementById('forgot-password-form');
    if (forgotLink && forgotModal) {
        forgotLink.addEventListener('click', function() {
            forgotModal.classList.remove('hidden');
            forgotModal.setAttribute('aria-hidden', 'false');
            var emailInput = document.getElementById('forgot-password-email');
            var loginEmail = document.getElementById('login-email');
            if (emailInput && loginEmail) emailInput.value = loginEmail.value;
        });
    }
    if (forgotClose) forgotClose.addEventListener('click', function() { closePasswordResetModal('forgot-password-modal'); });
    if (forgotModal) forgotModal.addEventListener('click', function(event) {
        if (event.target === forgotModal) closePasswordResetModal('forgot-password-modal');
    });
    if (forgotForm) forgotForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        var message = document.getElementById('forgot-password-message');
        var email = document.getElementById('forgot-password-email').value.trim();
        try {
            var response = await fetch(API_BASE + 'password-reset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ action: 'request', email: email })
            });
            var result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.error || 'Não foi possível solicitar a redefinição.');
            setPasswordResetMessage(message, result.message, false);
        } catch (error) {
            setPasswordResetMessage(message, error.message || 'Erro de conexão. Tente novamente.', true);
        }
    });

    var resetClose = document.getElementById('reset-password-close');
    var resetModal = document.getElementById('reset-password-modal');
    var resetForm = document.getElementById('reset-password-form');
    if (resetClose) resetClose.addEventListener('click', function() {
        closePasswordResetModal('reset-password-modal');
        var url = new URL(window.location.href);
        url.searchParams.delete('reset');
        window.history.replaceState({}, '', url.pathname + url.search + url.hash);
    });
    if (resetModal) resetModal.addEventListener('click', function(event) {
        if (event.target === resetModal) closePasswordResetModal('reset-password-modal');
    });
    if (resetForm) resetForm.addEventListener('submit', async function(event) {
        event.preventDefault();
        var message = document.getElementById('reset-password-message');
        var password = document.getElementById('reset-password-value').value;
        var passwordConfirm = document.getElementById('reset-password-confirm').value;
        try {
            var response = await fetch(API_BASE + 'password-reset.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    action: 'reset',
                    token: document.getElementById('reset-password-token').value,
                    password: password,
                    passwordConfirm: passwordConfirm
                })
            });
            var result = await response.json();
            if (!response.ok || !result.success) throw new Error(result.error || 'Não foi possível redefinir a senha.');
            setPasswordResetMessage(message, result.message, false);
            window.setTimeout(function() {
                closePasswordResetModal('reset-password-modal');
                var url = new URL(window.location.href);
                url.searchParams.delete('reset');
                window.history.replaceState({}, '', url.pathname + url.search + url.hash);
                showLogin();
            }, 1200);
        } catch (error) {
            setPasswordResetMessage(message, error.message || 'Erro de conexão. Tente novamente.', true);
        }
    });
}

function setupEventListeners() {
    console.log('Setting up event listeners...');
    setupMobileNavigation();
    setupPasswordResetEvents();
    
    const loginForm = document.getElementById('login-form');
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleLogin(e);
        });
    }

    const btnLogin = document.getElementById('btn-login');
    if (btnLogin) {
        btnLogin.addEventListener('click', function(e) {
            e.preventDefault();
            handleLogin(e);
        });
    }

    const linkCadastro = document.getElementById('link-cadastro');
    if (linkCadastro) linkCadastro.onclick = function(e) {
        e.preventDefault();
        openCadastroModal();
    };
    
    const btnCadastroVoltar = document.getElementById('btn-cadastro-voltar');
    if (btnCadastroVoltar) btnCadastroVoltar.addEventListener('click', returnToPublicHome);
    
    const modalCadastroFechar = document.getElementById('modal-cadastro-fechar');
    if (modalCadastroFechar) modalCadastroFechar.addEventListener('click', returnToPublicHome);
    
    const modalCadastroOverlay = document.getElementById('modal-cadastro-overlay');
    if (modalCadastroOverlay) {
        modalCadastroOverlay.addEventListener('click', function(e) {
            if (e.target === modalCadastroOverlay) returnToPublicHome();
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const cadastroOverlay = document.getElementById('modal-cadastro-overlay');
            const perfilOverlay = document.getElementById('modal-perfil-overlay');
            if (cadastroOverlay && !cadastroOverlay.classList.contains('hidden')) returnToPublicHome();
            else if (perfilOverlay && !perfilOverlay.classList.contains('hidden')) hideModalPerfil();
        }
    });

    const formCadastro = document.getElementById('form-cadastro');
    if (formCadastro) formCadastro.addEventListener('submit', handleCadastroSubmit);

    var modalPerfilFechar = document.getElementById('modal-perfil-fechar');
    if (modalPerfilFechar) modalPerfilFechar.addEventListener('click', hideModalPerfil);
    
    var btnPerfilFechar = document.getElementById('btn-perfil-fechar');
    if (btnPerfilFechar) btnPerfilFechar.addEventListener('click', hideModalPerfil);
    
    var modalPerfilOverlay = document.getElementById('modal-perfil-overlay');
    if (modalPerfilOverlay) {
        modalPerfilOverlay.addEventListener('click', function(e) {
            if (e.target === modalPerfilOverlay) hideModalPerfil();
        });
    }
    
    var formPerfilSenha = document.getElementById('form-perfil-senha');
    if (formPerfilSenha) formPerfilSenha.addEventListener('submit', handlePerfilSenhaSubmit);

    var btnThemeToggle = document.getElementById('btn-theme-toggle');
    if (btnThemeToggle) {
        btnThemeToggle.onclick = function(e) {
            e.stopPropagation();
            toggleTheme();
        };
    }
    
    var btnThemeToggleLanding = document.getElementById('btn-theme-toggle-landing');
    if (btnThemeToggleLanding) {
        btnThemeToggleLanding.onclick = function(e) {
            e.stopPropagation();
            toggleTheme();
            updateThemeIconsLanding();
        };
    }

    var headerUserArea = document.getElementById('header-user-area');
    var userDropdown = document.getElementById('user-dropdown');
    var btnLogout = document.getElementById('btn-logout');
    var btnPerfil = document.getElementById('btn-perfil');

    if (headerUserArea && userDropdown) {
        headerUserArea.onclick = function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('hidden');
        };
    }

    if (btnPerfil) {
        btnPerfil.onclick = function(e) {
            e.stopPropagation();
            userDropdown.classList.add('hidden');
            showModalPerfil();
        };
    }

    if (btnLogout) {
        btnLogout.onclick = function(e) {
            e.stopPropagation();
            userDropdown.classList.add('hidden');
            logout();
        };
    }
    
    var btnStopImpersonate = document.getElementById('btn-stop-impersonate');
    if (btnStopImpersonate) {
        btnStopImpersonate.onclick = function() {
            fetch(API_BASE + 'auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ action: 'stop_impersonate' })
            }).then(function(r) { return r.json(); }).then(function(res) {
                if (res.success) {
                    AppState.impersonating = false;
                    AppState.impersonatingTenantName = '';
                    checkAuth();
                    window.location.reload();
                } else {
                    alert(res.error || 'Erro ao sair do acesso.');
                }
            }).catch(function() { alert('Erro de conexão.'); });
        };
    }
    document.addEventListener('click', function(e) {
        if (userDropdown && !userDropdown.contains(e.target) && headerUserArea && !headerUserArea.contains(e.target)) {
            userDropdown.classList.add('hidden');
        }
    });
}

function dashboardMetricEscape(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function dashboardMetricCard(label, value, color, hint) {
    return '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">' +
        '<p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-2">' + dashboardMetricEscape(label) + '</p>' +
        '<p class="text-3xl font-black ' + dashboardMetricEscape(color || 'text-slate-900 dark:text-slate-100') + '">' + dashboardMetricEscape(value) + '</p>' +
        (hint ? '<p class="text-xs text-slate-500 dark:text-slate-400 mt-2">' + dashboardMetricEscape(hint) + '</p>' : '') +
        '</div>';
}

function dashboardMetricTime(value) {
    if (!value) return 'Sem atividade registrada';
    var date = new Date(value);
    return isNaN(date.getTime()) ? 'Sem atividade registrada' : date.toLocaleString('pt-BR');
}

function renderSegmentedDashboardMetrics(container, payload) {
    if (!container || !payload) return;
    var scope = payload.scope || 'personal';
    var html = '<div class="max-w-6xl mx-auto space-y-8 pb-20">';
    html += '<div class="flex justify-between items-start gap-4 flex-wrap"><div><h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">';
    html += scope === 'platform' ? 'Acompanhamento da Plataforma' : (scope === 'team' ? 'Resumo da Equipe' : 'Minha atividade');
    html += '</h3><p class="text-sm text-slate-500 dark:text-slate-400 mt-1">' + (scope === 'company' ? 'Uso consolidado de pesquisas, leads e atividade da equipe.' : 'Dados operacionais consolidados e atualizados conforme o seu perfil de acesso.') + '</p></div>';
    html += '<button type="button" onclick="loadSegmentedDashboardMetrics()" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold px-4 py-2.5 rounded-xl text-xs">Atualizar métricas</button></div>';

    if (scope === 'personal') {
        var a = payload.activity || {};
        html += '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">';
        html += dashboardMetricCard('Pesquisas Maps', a.maps_searches || 0, 'text-blue-600', 'Buscas realizadas');
        html += dashboardMetricCard('Consultas CNPJ', a.cnpj_queries || 0, 'text-amber-600', 'Consultas realizadas');
        html += dashboardMetricCard('Buscas LinkedIn', a.linkedin_searches || 0, 'text-violet-600', 'Buscas realizadas');
        html += dashboardMetricCard('Leads gerados', a.leads_generated || 0, 'text-emerald-600', 'Resultados de Maps');
        html += '</div><div class="p-6 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-600"><p class="text-sm font-semibold text-slate-700 dark:text-slate-300">Última atividade: <span class="font-black">' + dashboardMetricEscape(dashboardMetricTime(a.last_activity)) + '</span></p></div>';
    } else if (scope === 'team' || scope === 'company') {
        var totals = payload.totals || {};
        html += '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">';
        html += dashboardMetricCard('Pesquisas Maps', totals.maps_searches || 0, 'text-blue-600', 'Equipe consolidada');
        html += dashboardMetricCard('Consultas CNPJ', totals.cnpj_queries || 0, 'text-amber-600', 'Equipe consolidada');
        html += dashboardMetricCard('Buscas LinkedIn', totals.linkedin_searches || 0, 'text-violet-600', 'Equipe consolidada');
        html += dashboardMetricCard('Leads gerados', totals.leads_generated || 0, 'text-emerald-600', (totals.active_users || 0) + ' integrante(s) ativo(s)');
        html += '</div><div class="grid grid-cols-1 items-start lg:grid-cols-3 gap-6"><div class="lg:col-span-2 bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm overflow-hidden"><div class="px-6 py-5 border-b border-slate-200 dark:border-slate-600"><h4 class="font-black text-slate-900 dark:text-slate-100">Atividade por integrante</h4><p class="text-xs text-slate-500 dark:text-slate-400">' + (scope === 'company' ? 'Visão consolidada da equipe da empresa.' : 'Dados pessoais minimizados para o papel de supervisão.') + '</p></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 dark:bg-slate-800/50 text-[10px] uppercase text-slate-500"><tr><th class="px-6 py-4">Integrante</th><th class="px-4 py-4 text-right">Maps</th><th class="px-4 py-4 text-right">CNPJ</th><th class="px-4 py-4 text-right">LinkedIn</th><th class="px-6 py-4 text-right">Leads</th></tr></thead><tbody>';
        (payload.users || []).forEach(function(row) {
            html += '<tr class="border-t border-slate-100 dark:border-slate-700"><td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">' + dashboardMetricEscape(row.member || 'Membro') + '</td><td class="px-4 py-4 text-right">' + dashboardMetricEscape(row.maps_searches || 0) + '</td><td class="px-4 py-4 text-right">' + dashboardMetricEscape(row.cnpj_queries || 0) + '</td><td class="px-4 py-4 text-right">' + dashboardMetricEscape(row.linkedin_searches || 0) + '</td><td class="px-6 py-4 text-right font-black">' + dashboardMetricEscape(row.leads_generated || 0) + '</td></tr>';
        });
        html += '</tbody></table></div></div><aside class="p-6 bg-slate-50 dark:bg-slate-800/50 rounded-[2rem] border border-slate-200 dark:border-slate-600"><p class="text-[10px] font-black uppercase tracking-wider text-slate-500">Última atividade</p><p class="mt-2 text-sm font-black text-slate-800 dark:text-slate-200">' + dashboardMetricEscape(dashboardMetricTime(totals.last_activity)) + '</p><p class="mt-4 text-xs text-slate-500 dark:text-slate-400">Use estes dados para acompanhar a operação, orientar a equipe e antecipar necessidades de crédito.</p></aside></div>';
    } else if (scope === 'platform') {
        var p = payload.platform || {};
        html += '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5">';
        html += dashboardMetricCard('Empresas', p.companies_total || 0, 'text-blue-600', (p.companies_active || 0) + ' ativa(s)');
        html += dashboardMetricCard('Usuários cadastrados', p.total_users || 0, 'text-indigo-600', 'Contas de clientes');
        html += dashboardMetricCard('Online agora', p.online_users || 0, 'text-emerald-600', p.online_tracking_available === false ? 'Ative last_activity para medir' : 'Ativos nos últimos ' + (p.online_window_minutes || 15) + ' min');
        html += dashboardMetricCard('Pesquisas Maps', p.maps_searches || 0, 'text-sky-600', 'Plataforma');
        html += dashboardMetricCard('Consultas CNPJ', p.cnpj_queries || 0, 'text-amber-600', 'Plataforma');
        html += dashboardMetricCard('Buscas LinkedIn', p.linkedin_searches || 0, 'text-violet-600', (payload.alerts || []).length + ' alerta(s)');
        html += '</div><div class="grid grid-cols-1 items-start xl:grid-cols-3 gap-6"><div class="xl:col-span-2 bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm overflow-hidden"><div class="px-6 py-5 border-b border-slate-200 dark:border-slate-600"><h4 class="font-black text-slate-900 dark:text-slate-100">Empresas monitoradas</h4></div><div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead class="bg-slate-50 dark:bg-slate-800/50 text-[10px] uppercase text-slate-500"><tr><th class="px-6 py-4">Empresa</th><th class="px-4 py-4 text-right">Usuários</th><th class="px-4 py-4 text-right">Leads</th><th class="px-6 py-4 text-right">Créditos</th></tr></thead><tbody>';
        (payload.companies || []).forEach(function(company) {
            html += '<tr class="border-t border-slate-100 dark:border-slate-700"><td class="px-6 py-4"><span class="font-bold text-slate-800 dark:text-slate-200">' + dashboardMetricEscape(company.name) + '</span><span class="block text-[11px] text-slate-500">' + dashboardMetricEscape(company.plan || company.status || '') + '</span></td><td class="px-4 py-4 text-right">' + dashboardMetricEscape(company.users || 0) + '</td><td class="px-4 py-4 text-right font-bold">' + dashboardMetricEscape(company.leads_generated || 0) + '</td><td class="px-6 py-4 text-right">' + dashboardMetricEscape(company.credits == null ? '—' : company.credits) + '</td></tr>';
        });
        html += '</tbody></table></div></div><aside class="bg-amber-50 dark:bg-amber-950/30 rounded-[2rem] border border-amber-200 dark:border-amber-900 p-6"><h4 class="font-black text-amber-900 dark:text-amber-200">Alertas operacionais</h4><div class="mt-4 space-y-3">';
        if ((payload.alerts || []).length) {
            payload.alerts.forEach(function(alert) { html += '<p class="text-sm text-amber-900 dark:text-amber-100">' + dashboardMetricEscape(alert.message) + '<span class="block text-xs opacity-75 mt-1">' + dashboardMetricEscape(alert.tenant_name) + '</span></p>'; });
        } else {
            html += '<p class="text-sm text-amber-900 dark:text-amber-100">Nenhum alerta crítico no momento.</p>';
        }
        html += '</div></aside></div>';
        html += '<section id="dashboard-maps-pilot-metrics" class="mt-6 rounded-[2rem] border border-sky-200 bg-sky-50 px-6 py-5 text-xs text-sky-900 dark:border-sky-900/70 dark:bg-sky-950/35 dark:text-sky-100"><p class="font-bold">Métricas protegidas da Coleta Atendo disponíveis neste Dashboard.</p></section>';
    }
    container.innerHTML = html + '</div>';
    if (scope === 'platform') {
        var pilotContainer = document.getElementById('dashboard-maps-pilot-metrics');
        if (payload.maps_pilot) {
            AppState.mapsPilotMetrics = payload.maps_pilot;
            renderMapsPilotMetrics(payload.maps_pilot);
        } else if (pilotContainer) {
            loadMapsPilotMetrics();
        }
    }
}

function loadSegmentedDashboardMetrics() {
    var container = document.getElementById('dashboard-segmented-metrics');
    if (!container || AppState.activeTab !== 'dashboard') return;
    container.innerHTML = '<div class="max-w-6xl mx-auto py-16 text-center text-slate-500 dark:text-slate-400"><span class="inline-block w-8 h-8 border-2 border-slate-300 border-t-blue-600 rounded-full animate-spin"></span><p class="mt-3 text-sm font-bold">Carregando métricas…</p></div>';
    fetch(API_BASE + 'dashboard-metrics.php', { method: 'GET', credentials: 'include', cache: 'no-store' })
        .then(function(response) { return response.json(); })
        .then(function(response) {
            if (!response || !response.success) throw new Error((response && response.error) || 'Não foi possível carregar as métricas.');
            renderSegmentedDashboardMetrics(container, response.data || {});
        })
        .catch(function(error) {
            if (container) container.innerHTML = '<div class="max-w-3xl mx-auto p-8 bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900 rounded-3xl text-center"><h3 class="font-black text-amber-900 dark:text-amber-200">Métricas indisponíveis</h3><p class="mt-2 text-sm text-amber-800 dark:text-amber-100">' + dashboardMetricEscape(error.message || 'Tente atualizar novamente.') + '</p></div>';
        });
}

function renderDashboardTab(contentArea) {
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
    var profile = AppState.user ? String(AppState.user.profile || '').toLowerCase() : '';
    if (['super_admin', 'supervisor', 'normal', 'user'].includes(profile)) {
        contentArea.innerHTML = '<div id="dashboard-segmented-metrics" class="w-full"></div>';
        loadSegmentedDashboardMetrics();
        return;
    }

    if (isSuperAdmin) {
        contentArea.innerHTML = `
            <div class="max-w-6xl mx-auto space-y-8 pb-20">
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <div>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Visão Geral da Plataforma</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400">Métricas e estatísticas globais do seu SaaS.</p>
                    </div>
                    <button type="button" onclick="loadSuperAdminDashboardStats()" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 font-bold px-4 py-2.5 rounded-xl text-xs flex items-center gap-2">🔄 Atualizar Métricas</button>
                </div>

                <div id="super-admin-stats-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Empresas Cadastradas</p>
                        <p id="stat-companies" class="text-3xl font-black text-slate-900 dark:text-slate-100">-</p>
                        <p id="stat-companies-sub" class="text-xs text-slate-500 dark:text-slate-400 mt-2">Carregando...</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Total de Leads Gerados</p>
                        <p id="stat-leads" class="text-3xl font-black text-emerald-600">-</p>
                        <p id="stat-searches-sub" class="text-xs text-slate-500 dark:text-slate-400 mt-2">Carregando...</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Chaves de pesquisa ativas</p>
                        <p id="stat-apify" class="text-3xl font-black text-purple-600">-</p>
                        <p id="stat-apify-sub" class="text-xs text-slate-500 dark:text-slate-400 mt-2">Carregando...</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                        <p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Solicitações Pendentes</p>
                        <p id="stat-pending" class="text-3xl font-black text-amber-600">-</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Planos ou créditos</p>
                    </div>
                </div>

                <div class="mt-10">
                    <h3 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-6">Uso de créditos por empresa</h3>
                    <div id="dashboard-tenants-usage" class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm overflow-hidden">
                        <div class="p-8 text-center text-slate-500 dark:text-slate-400">
                            <span class="inline-block w-8 h-8 border-2 border-slate-300 border-t-blue-600 rounded-full animate-spin"></span>
                            <p class="mt-3 text-sm font-bold">Carregando empresas…</p>
                        </div>
                    </div>
                </div>
            </div>
        `;
        loadSuperAdminDashboardStats();
        setTimeout(function() { loadDashboardTenantsUsage(); }, 0);
        return;
    }

    var u = AppState.tokenUsage || { used: 0, limit: 0, limitReached: false };
    var used = u.used || 0;
    var limit = u.limit != null ? u.limit : 0;
    var limitLabel = limit === 0 ? 'Ilimitado' : limit;
    var baseLimit = u.planLimit != null ? u.planLimit : limit;
    var bonusCredits = Number(u.bonus || 0);
    var available = limit === 0 ? 'Ilimitado' : Math.max(0, limit - used);
    var tenantName = (AppState.tenant && AppState.tenant.name) ? AppState.tenant.name : '—';
    
    var isCompanyAdmin = profile === 'admin';
    var adminActivitySection = isCompanyAdmin ? '<section id="dashboard-segmented-metrics" class="w-full"></section><section class="mt-10 pt-10 border-t border-slate-200 dark:border-slate-700"><div class="mb-6"><h4 class="text-xl font-black text-slate-900 dark:text-slate-100">Resumo de créditos</h4><p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Acompanhamento financeiro da empresa no período atual.</p></div>' : '';
    var standardDashboardHeader = !isCompanyAdmin ? '<div class="flex items-start justify-between gap-4 flex-wrap mb-8"><div><h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Estatísticas da conta</h3><p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Resumo de créditos e plano da empresa.</p></div></div>' : '';
    contentArea.innerHTML = '<div class="max-w-6xl mx-auto">' +
        adminActivitySection + standardDashboardHeader +
        '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">' +
        '<div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">' +
        '<div class="flex items-center gap-4 mb-4"><div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center">' +
        '<svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg></div>' +
        '<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Créditos utilizados</p><p class="text-3xl font-black text-slate-900 dark:text-slate-100">' + used + '</p></div></div>' +
        '<p class="text-xs text-slate-500 dark:text-slate-400">Consumo: 1 crédito por lead único capturado no Google Maps e 5 créditos por consulta de CNPJ.</p></div>' +
        '<div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">' +
        '<div class="flex items-center gap-4 mb-4"><div class="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center">' +
        '<svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg></div>' +
        '<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Créditos permitidos (plano)</p><p class="text-3xl font-black text-slate-900 dark:text-slate-100">' + limitLabel + '</p></div></div>' +
        '<p class="text-xs text-slate-500 dark:text-slate-400">Limite do plano: ' + baseLimit + ' créditos' + (bonusCredits > 0 ? ' + ' + bonusCredits + ' de bônus de contratação.' : '.') + '</p></div>' +
        '<div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm sm:col-span-2 lg:col-span-1">' +
        '<div class="flex items-center gap-4 mb-4"><div class="w-14 h-14 bg-slate-100 dark:bg-slate-700 rounded-2xl flex items-center justify-center">' +
        '<svg class="w-7 h-7 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg></div>' +
        '<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Disponível</p><p class="text-3xl font-black text-slate-900 dark:text-slate-100">' + available + '</p></div></div>' +
        '<p class="text-xs text-slate-500 dark:text-slate-400">Créditos restantes para novas buscas e consultas.</p></div>' +
        '</div>' +
        (isCompanyAdmin ? '</section>' : '<div class="mt-8 p-6 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-600"><p class="text-sm font-bold text-slate-700 dark:text-slate-300"><span class="text-slate-500 dark:text-slate-400">Empresa:</span> ' + tenantName + '</p></div>') +
        '</div>';
    if (isCompanyAdmin) loadSegmentedDashboardMetrics();
}

function loadDashboardTenantsUsage() {
    var container = document.getElementById('dashboard-tenants-usage');
    if (!container) return;
    fetch(API_BASE + 'tenants.php', { method: 'GET', credentials: 'include', cache: 'no-store' })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (!res.success || !res.data) {
                container.innerHTML = '<div class="p-8 text-center text-amber-600 font-bold text-sm">Erro ao carregar empresas.</div>';
                return;
            }
            var items = (res.data.items && res.data.items.length) ? res.data.items : (Array.isArray(res.data) ? res.data : []);
            if (items.length === 0) {
                container.innerHTML = '<div class="p-8 text-center text-slate-500 dark:text-slate-400 text-sm font-bold">Nenhuma empresa cadastrada.</div>';
                return;
            }
            var html = '<div class="overflow-x-auto"><table class="w-full text-left"><thead><tr class="border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/50"><th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider">Empresa</th><th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Tokens válidos (limite)</th><th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Tokens utilizados</th></tr></thead><tbody>';
            items.forEach(function(t) {
                var limitLabel = (t.tokensLimit != null && t.tokensLimit === 0) ? 'Ilimitado' : (t.tokensLimit != null ? t.tokensLimit : '—');
                var used = t.tokensUsed != null ? t.tokensUsed : 0;
                html += '<tr class="border-b border-slate-100 dark:border-slate-600 hover:bg-slate-50/50 dark:hover:bg-slate-700/50"><td class="px-6 py-4"><span class="font-bold text-slate-900 dark:text-slate-100">' + (t.name || '—') + '</span>' + (t.plan ? '<span class="block text-[11px] font-bold text-slate-400 uppercase">' + t.plan + '</span>' : '') + '</td><td class="px-6 py-4 text-right font-bold text-slate-700 dark:text-slate-300">' + limitLabel + '</td><td class="px-6 py-4 text-right font-bold text-slate-900 dark:text-slate-100">' + used + '</td></tr>';
            });
            html += '</tbody></table></div>';
            container.innerHTML = html;
        })
        .catch(function() {
            if (container) container.innerHTML = '<div class="p-8 text-center text-amber-600 font-bold text-sm">Erro de conexão ao carregar empresas.</div>';
        });
}
function renderRequestCreditsTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-2xl mx-auto">' +
        '<h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-6">Solicitar créditos</h3>' +
        '<p class="text-sm text-slate-500 dark:text-slate-400 mb-8">Com o Asaas ativo, você será encaminhado para o pagamento. Se ele estiver indisponível, a solicitação seguirá para aprovação manual do administrador.</p>' +
        '<div id="request-credits-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>' +
        '<div id="request-credits-success" class="hidden mb-6 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-700 text-sm font-medium"></div>' +
        '<div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm mb-10">' +
        '<form id="request-credits-form" class="space-y-4">' +
        '<div><div class="flex flex-wrap items-end gap-4">' +
        '<div class="flex-1 min-w-[140px]"><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Quantidade de créditos (tokens)</label>' +
        '<input id="request-credits-amount" type="number" min="100" max="10000" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" placeholder="Ex: 100" required /></div>' +
        '<div id="request-credits-total-wrap" class="flex-1 min-w-[140px] pb-1 hidden"><p class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Valor a pagar</p>' +
        '<p id="request-credits-total-line" class="text-lg font-bold text-blue-600"><span id="request-credits-total-value">—</span></p></div></div>' +
        '<div class="mt-3"><label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-2">Ou arraste até 10.000</label>' +
        '<input id="request-credits-slider" type="range" min="100" max="10000" step="1" value="100" class="w-full h-3 rounded-full appearance-none bg-slate-200 accent-blue-600 cursor-pointer" />' +
        '<p id="request-credits-slider-label" class="text-[10px] text-slate-400 mt-1 text-right">100 créditos</p></div>' +
        '<p class="text-[10px] text-slate-400 mt-1">Cada crédito = 1 busca no período atual.</p>' +
        '<p id="request-credits-price-line" class="text-sm font-medium text-slate-600 dark:text-slate-400 mt-2 hidden">Valor avulso: <span class="font-bold text-slate-800 dark:text-slate-200"></span> por crédito</p></div>' +
        '<button type="submit" id="request-credits-submit" class="w-full py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Enviar solicitação</button>' +
        '</form></div>' +
        '<h4 class="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">Minhas solicitações</h4>' +
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

    form.onsubmit = async function(e) {
        e.preventDefault();
        var num = getAmountNum();
        if (num < 100 || num > 10000) {
            var err = document.getElementById('request-credits-error');
            if (err) {
                err.textContent = 'Informe uma quantidade entre 100 e 10.000.';
                err.classList.remove('hidden');
            }
            return;
        }

        var errEl = document.getElementById('request-credits-error');
        var successEl = document.getElementById('request-credits-success');
        if (errEl) errEl.classList.add('hidden');
        if (successEl) successEl.classList.add('hidden');
        
        var btn = document.getElementById('request-credits-submit');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Enviando solicitação...';
        }

        try {
            const res = await fetch(API_BASE + 'create-payment.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({
                    type: 'credits',
                    amount: 0,
                    credits: num
                })
            });
            const raw = await res.text();
            let data = null;
            try {
                data = raw ? JSON.parse(raw) : null;
            } catch (parseError) {
                throw new Error('Resposta inválida do servidor.');
            }
            if (res.ok && data && data.success) {
                if (successEl) {
                    successEl.textContent = data.message || (data.data && data.data.mode === 'asaas' ? 'Cobrança criada no Asaas.' : 'Solicitação de créditos enviada para análise.');
                    if (data.data && data.data.mode === 'asaas' && data.data.payment_url) {
                        successEl.appendChild(document.createTextNode(' '));
                        var creditPaymentLink = document.createElement('a');
                        creditPaymentLink.href = data.data.payment_url;
                        creditPaymentLink.target = '_blank';
                        creditPaymentLink.rel = 'noopener noreferrer';
                        creditPaymentLink.textContent = 'Abrir pagamento';
                        creditPaymentLink.className = 'font-black underline';
                        successEl.appendChild(creditPaymentLink);
                        window.open(data.data.payment_url, '_blank', 'noopener');
                    }
                    successEl.classList.remove('hidden');
                }
                amountEl.value = '';
                if (sliderEl) sliderEl.value = 100;
                if (sliderLabel) sliderLabel.textContent = '100 créditos';
                if (totalValue) totalValue.textContent = '—';
                if (totalWrap) totalWrap.classList.add('hidden');
                loadRequestCreditsList();
            } else {
                if (errEl) {
                    errEl.textContent = (data && data.error) ? data.error : 'Erro ao enviar a solicitação.';
                    errEl.classList.remove('hidden');
                }
            }
        } catch (err) {
            console.error('Falha ao confirmar solicitação de créditos:', err);
            if (errEl) {
                errEl.textContent = 'Não foi possível confirmar a solicitação agora. Atualize a página antes de tentar novamente.';
                errEl.classList.remove('hidden');
            }
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Enviar solicitação';
            }
        }
    };
}

function loadRequestCreditsList() {
    var form = document.getElementById('request-credits-form');
    var priceLine = document.getElementById('request-credits-price-line');
    var listEl = document.getElementById('request-credits-list');
    
    if (!listEl) return;

    fetch(API_BASE + 'credit-requests.php', { credentials: 'include' })
    .then(function(r) { return r.json(); })
    .then(function(data) {
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
            listEl.innerHTML = '<div class="py-12 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhuma solicitação</p></div>';
            return;
        }

        var items = data.data.items;
        if (items.length === 0) {
            listEl.innerHTML = '<div class="py-12 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhuma solicitação</p></div>';
            return;
        }

        var html = '<div class="space-y-4">';
        items.forEach(function(r) {
            var statusLabel = r.status === 'pending' ? 'Pendente' : r.status === 'approved' ? 'Aprovado' : 'Recusado';
            var statusClass = r.status === 'pending' ? 'text-amber-600' : r.status === 'approved' ? 'text-emerald-600' : 'text-red-600';
            var dateStr = r.createdAt ? new Date(r.createdAt).toLocaleString('pt-BR') : '';
            html += '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 flex items-center justify-between">';
            var valueStr = pricePerCredit > 0 ? ' · <span class="text-blue-600 font-bold">R$ ' + (r.tokensRequested * pricePerCredit).toFixed(2).replace('.', ',') + '</span>' : '';
            html += '<div><p class="font-extrabold text-slate-900 dark:text-slate-100">' + r.tokensRequested + ' créditos' + valueStr + '</p>';
            html += '<p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + dateStr + ' · <span class="' + statusClass + '">' + statusLabel + '</span></p></div>';
            if (r.status === 'pending') html += '<span class="bg-amber-100 text-amber-700 px-3 py-1 rounded-full text-xs font-bold">Aguardando</span>';
            html += '</div>';
        });
        html += '</div>';
        listEl.innerHTML = html;
    })
    .catch(function(err) {
        var listElRetry = document.getElementById('request-credits-list');
        if (listElRetry) {
            listElRetry.innerHTML = '<div class="py-12 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Erro ao carregar</p></div>';
        }
        console.error("Erro na lista de créditos:", err);
    });
}
function renderChoosePlanTab(contentArea) {
    var currentPlanName = (AppState.tenant && AppState.tenant.planName) ? AppState.tenant.planName : '';
    var currentPlanId = (AppState.tenant && AppState.tenant.planId) ? String(AppState.tenant.planId) : '';
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto">' +
        '<h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-2">Meu plano</h3>' +
        '<p class="text-sm text-slate-500 dark:text-slate-400 mb-8">Escolha uma assinatura mensal para sua empresa. Você poderá cancelar a próxima cobrança a qualquer momento, sem estorno do período já pago.</p>' +
        (currentPlanName ? '<div class="mb-8 p-6 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-600"><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Plano atual</p><p class="text-lg font-bold text-slate-900 dark:text-slate-100">' + currentPlanName + '</p></div>' : '') +
        '<div id="subscription-status" class="mb-8 p-5 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-600 text-sm text-slate-600 dark:text-slate-300">Carregando situação da assinatura...</div>' +
        '<div id="choose-plan-pending" class="hidden mb-8 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium"></div>' +
        '<div id="choose-plan-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>' +
        '<div id="choose-plan-spinner" class="py-24 text-center"><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block"></span></div>' +
        '<div id="choose-plan-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10 hidden"></div>' +
        '<div id="crm-addon-plan-box" class="hidden mb-10 rounded-[2rem] border border-amber-200 bg-amber-50 p-6 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"></div>' +
        '<div id="choose-plan-history" class="hidden mt-10"><h4 class="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">Histórico de solicitações</h4><div id="choose-plan-history-list"></div></div></div>';
    loadChoosePlanData(currentPlanId);
    loadCrmAddonPlanBox();
    loadSubscriptionStatus();
}

async function loadCrmAddonPlanBox() {
    var box = document.getElementById('crm-addon-plan-box');
    if (!box) return;
    try {
        var response = await fetch(API_BASE + 'crm-addon.php?action=status', { credentials: 'include', cache: 'no-store' });
        var payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.error || 'Não foi possível carregar as licenças CRM.');
        var data = payload.data || {};
        box.classList.remove('hidden');
        if (data.hasAccess && data.license) {
            var license = data.license;
            box.innerHTML = '<h4 class="text-lg font-black">CRM adicional ativo</h4><p class="mt-1 text-sm">' + Number(license.includedUsers || 3) + ' usuários incluídos. O CRM permanece separado do seu plano principal e não consome créditos.</p>';
            return;
        }
        var offers = Array.isArray(data.offers) ? data.offers : [];
        var html = '<h4 class="text-lg font-black">CRM comercial — módulo adicional</h4><p class="mt-1 text-sm">Contrate o CRM separadamente. Seu plano de Maps, LinkedIn, CNPJ e Exportação não será substituído.</p>';
        if (!offers.length) { box.innerHTML = html + '<p class="mt-3 text-xs font-bold">As ofertas de CRM ainda estão em preparação comercial.</p>'; return; }
        html += '<div class="mt-4 grid gap-4 md:grid-cols-2">';
        offers.forEach(function (offer) {
            html += '<div class="rounded-2xl border border-amber-200 bg-white p-4 dark:border-amber-800 dark:bg-slate-800"><p class="font-black">' + dashboardMetricEscape(offer.name) + '</p><p class="mt-1 text-xs">R$ ' + Number(offer.priceMonthly || 0).toFixed(2).replace('.', ',') + '/mês · ' + Number(offer.includedUsers || 3) + ' usuários incluídos</p><p class="mt-1 text-xs">Usuário adicional: R$ ' + (Number(offer.addonUserPriceCents || 0) / 100).toFixed(2).replace('.', ',') + '/mês</p><button type="button" class="btn-request-crm-addon mt-3 w-full rounded-xl bg-amber-600 px-4 py-2.5 text-xs font-black text-white hover:bg-amber-700" data-plan-id="' + offer.id + '">Solicitar CRM adicional</button></div>';
        });
        box.innerHTML = html + '</div>';
        box.querySelectorAll('.btn-request-crm-addon').forEach(function (button) {
            button.onclick = async function () {
                button.disabled = true; button.textContent = 'Enviando...';
                try {
                    var res = await fetch(API_BASE + 'crm-addon.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify({ planId: parseInt(button.getAttribute('data-plan-id'), 10) }) });
                    var result = await res.json();
                    if (!res.ok || !result.success) throw new Error(result.error || 'Não foi possível solicitar o CRM adicional.');
                    var link = result.data && result.data.payment_url ? ' <a class="underline font-black" target="_blank" rel="noopener noreferrer" href="' + dashboardMetricEscape(result.data.payment_url) + '">Concluir pagamento</a>' : '';
                    box.innerHTML = '<h4 class="text-lg font-black">Solicitação registrada</h4><p class="mt-1 text-sm">' + dashboardMetricEscape(result.message || 'Aguarde a aprovação do administrador.') + link + '</p>';
                } catch (error) { button.disabled = false; button.textContent = 'Solicitar CRM adicional'; alert(error.message); }
            };
        });
    } catch (error) {
        box.classList.add('hidden');
    }
}

async function loadSubscriptionStatus() {
    var container = document.getElementById('subscription-status');
    if (!container) return;
    try {
        var response = await fetch(API_BASE + 'asaas.php?action=subscription_status', { credentials: 'include', cache: 'no-store' });
        var raw = await response.text();
        var payload = raw ? JSON.parse(raw) : null;
        if (!response.ok || !payload || !payload.success) throw new Error((payload && payload.error) || 'Não foi possível consultar a assinatura.');
        var subscription = payload.data && payload.data.subscription;
        if (!subscription) {
            container.innerHTML = '<p class="font-bold text-slate-700 dark:text-slate-200">Nenhuma assinatura mensal ativa.</p><p class="mt-1 text-xs">Selecione um plano pago para gerar a primeira cobrança mensal.</p>';
            return;
        }
        var statusLabels = { pending: 'Aguardando primeiro pagamento', active: 'Ativa', overdue: 'Pagamento pendente', cancelled: 'Cancelada' };
        var accessUntil = subscription.access_until ? formatMapsPilotDate(subscription.access_until) : 'a confirmar após o pagamento';
        var paymentLink = subscription.payment_url ? '<a class="mt-3 inline-flex rounded-xl bg-blue-600 px-4 py-2 text-xs font-black text-white hover:bg-blue-700" target="_blank" rel="noopener noreferrer" href="' + dashboardMetricEscape(subscription.payment_url) + '">Concluir pagamento</a>' : '';
        var cancelButton = subscription.can_cancel ? '<button id="btn-cancel-subscription" type="button" class="mt-3 ml-0 sm:ml-2 inline-flex rounded-xl border border-rose-300 px-4 py-2 text-xs font-black text-rose-700 hover:bg-rose-50 dark:border-rose-800 dark:text-rose-200 dark:hover:bg-rose-950/30">Cancelar meu plano</button>' : '';
        container.innerHTML = '<div class="flex flex-wrap items-start justify-between gap-3"><div><p class="font-black text-slate-900 dark:text-slate-100">' + dashboardMetricEscape(subscription.plan_name || 'Plano mensal') + ' <span class="ml-2 text-xs text-slate-500">' + dashboardMetricEscape(statusLabels[subscription.status] || subscription.status || 'Em análise') + '</span></p><p class="mt-1 text-xs">R$ ' + Number(subscription.amount_brl || 0).toFixed(2).replace('.', ',') + ' por mês · acesso atual preservado até ' + dashboardMetricEscape(accessUntil) + '.</p>' + (subscription.cancelled_at ? '<p class="mt-1 text-xs text-amber-700 dark:text-amber-300">A renovação foi cancelada. Não haverá estorno automático.</p>' : '') + '</div></div>' + paymentLink + cancelButton;
        var cancelEl = document.getElementById('btn-cancel-subscription');
        if (cancelEl) cancelEl.onclick = cancelCurrentSubscription;
    } catch (error) {
        container.innerHTML = '<p class="font-bold text-amber-700 dark:text-amber-300">Não foi possível consultar a assinatura agora.</p><p class="mt-1 text-xs">Atualize a página e tente novamente antes de alterar seu plano.</p>';
    }
}

async function cancelCurrentSubscription() {
    if (!window.confirm('Deseja cancelar a renovação do seu plano? Não haverá estorno automático e o acesso continuará somente até o fim do período já pago.')) return;
    var button = document.getElementById('btn-cancel-subscription');
    if (button) { button.disabled = true; button.textContent = 'Cancelando...'; }
    try {
        var response = await fetch(API_BASE + 'asaas.php?action=cancel_subscription', {
            method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: '{}'
        });
        var raw = await response.text();
        var payload = raw ? JSON.parse(raw) : null;
        if (!response.ok || !payload || !payload.success) throw new Error((payload && payload.error) || 'Não foi possível cancelar a assinatura.');
        showToast(payload.message || 'Renovação cancelada. O acesso seguirá disponível até o fim do período pago.', 'success');
        loadSubscriptionStatus();
    } catch (error) {
        showError(error && error.message ? error.message : 'Não foi possível cancelar a assinatura agora.');
        if (button) { button.disabled = false; button.textContent = 'Cancelar meu plano'; }
    }
}

function loadChoosePlanData(currentPlanId) {
    var spinnerEl = document.getElementById('choose-plan-spinner');
    var gridEl = document.getElementById('choose-plan-grid');
    var errorEl = document.getElementById('choose-plan-error');
    if (!gridEl) return;

    fetch(API_BASE + 'plans-public.php', { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(plansData) {
            if (spinnerEl) spinnerEl.classList.add('hidden');
            var plans = (plansData.success && plansData.data && plansData.data.items) ? plansData.data.items : [];
            
            var html = '';
            plans.forEach(function(p) {
                var isCurrent = currentPlanId === String(p.id);
                var isTrial = p.slug === 'trial';
                var priceNum = parseFloat(p.priceMonthly || 0);
                var priceText = isTrial ? 'Grátis' : 'R$ ' + priceNum.toFixed(2).replace('.', ',');
                var tokenText = (p.tokenLimit || 0).toLocaleString('pt-BR') + ' créditos';
                var modulesText = (p.moduleLabels && p.moduleLabels.length) ? p.moduleLabels.join(' · ') : 'Módulos conforme o plano';
                var includedUsersText = Math.max(1, Number(p.includedUsers || 1)) + ' usuário(s) incluído(s)';
                var bonusText = isTrial ? 'Ao contratar um plano pago, você recebe +50 créditos de bônus.' : 'Bônus de contratação: +50 créditos na confirmação do plano.';

                html += '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border-2 ' + (isCurrent ? 'border-blue-400 bg-blue-50/50 dark:bg-blue-900/30' : 'border-slate-200 dark:border-slate-600') + '">';
                html += '<div class="flex items-center gap-4 mb-4"><div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-lg font-black text-blue-600">' + (p.tokenLimit >= 1000 ? (p.tokenLimit / 1000) + 'K' : p.tokenLimit) + '</div>';
                html += '<div><h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg">' + (p.name || '') + '</h4><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + tokenText + '</p></div></div>';
                html += '<p class="text-2xl font-black mb-4">' + (isTrial ? '<span class="text-emerald-600">' + priceText + '</span>' : priceText) + '<span class="text-sm font-bold text-slate-400">/mês</span></p>';
                html += '<p class="mb-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400"><strong>Módulos:</strong> ' + dashboardMetricEscape(modulesText) + '</p>';
                html += '<p class="mb-3 text-xs font-bold text-slate-500 dark:text-slate-400">' + dashboardMetricEscape(includedUsersText) + '</p>';
                html += '<p class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs font-black text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200">' + dashboardMetricEscape(bonusText) + '</p>';
                
                if (isCurrent) {
                    html += '<p class="text-sm font-bold text-blue-600">Plano atual</p>';
                } else if (!isTrial && priceNum > 0) {
                    html += '<button type="button" class="btn-request-plan w-full py-3 rounded-xl bg-blue-600 text-white font-bold text-sm hover:bg-blue-700 shadow-lg shadow-blue-600/20" data-plan-id="' + p.id + '">Assinar mensalmente</button>';
                } else {
                    html += '<p class="text-sm text-slate-400">Gratuito</p>';
                }
                html += '</div>';
            });
            gridEl.innerHTML = html;
            gridEl.classList.remove('hidden');

            gridEl.querySelectorAll('.btn-request-plan').forEach(function(btn) {
                btn.onclick = async function() {
                    var planId = btn.getAttribute('data-plan-id');
                    btn.disabled = true;
                    btn.textContent = 'Criando assinatura...';

                    try {
                        const res = await fetch(API_BASE + 'asaas.php?action=create_subscription', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({
                                plan_id: parseInt(planId, 10)
                            })
                        });
                        const data = await res.json().catch(function() { return null; });

                        if (data && data.success) {
                            var pendingEl = document.getElementById('choose-plan-pending');
                            if (pendingEl) {
                                var isAutomaticPlan = data.data && data.data.mode === 'asaas';
                                var automaticLink = isAutomaticPlan && data.data.payment_url ? ' <a class="underline font-black" target="_blank" rel="noopener noreferrer" href="' + dashboardMetricEscape(data.data.payment_url) + '">Concluir primeiro pagamento</a>' : '';
                                pendingEl.innerHTML = dashboardMetricEscape(data.message || (isAutomaticPlan ? 'Assinatura mensal criada. Consulte o status para acompanhar a confirmação.' : 'Solicitação enviada para aprovação manual.')) + ' <strong>Após a confirmação, serão liberados +50 créditos de bônus de contratação.</strong>' + automaticLink;
                                pendingEl.className = isAutomaticPlan ? 'mb-8 p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-700 text-sm font-medium' : 'mb-8 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium';
                            }
                            if (data.data && data.data.mode === 'asaas') loadSubscriptionStatus();
                        } else {
                            alert((data && data.error) ? data.error : 'Erro ao criar a assinatura mensal.');
                        }
                    } catch (e) {
                        alert('Erro de conexão ao criar a assinatura mensal.');
                    } finally {
                        btn.disabled = false;
                        btn.textContent = 'Assinar mensalmente';
                    }
                };
            });
        }).catch(function() {
            if (spinnerEl) spinnerEl.classList.add('hidden');
            if (errorEl) { errorEl.textContent = 'Erro ao carregar planos.'; errorEl.classList.remove('hidden'); }
        });
}

function renderCreditsAdminTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto">' +
        '<div class="flex justify-between items-center mb-10">' +
        '<h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Solicitações de créditos</h3>' +
        '<button type="button" id="credits-admin-refresh" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2">Atualizar</button></div>' +
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
            listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhuma solicitação de créditos</p></div>';
            return;
        }
        var items = data.data.items;
        if (items.length === 0) {
            listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhuma solicitação de créditos</p></div>';
            return;
        }
        var html = '<div class="space-y-4">';
        items.forEach(function(r) {
            var statusLabel = r.status === 'pending' ? 'Pendente' : r.status === 'approved' ? 'Aprovado' : 'Recusado';
            var statusClass = r.status === 'pending' ? 'text-amber-600' : r.status === 'approved' ? 'text-emerald-600' : 'text-red-600';
            var dateStr = r.createdAt ? new Date(r.createdAt).toLocaleString('pt-BR') : '';
            var reviewedStr = r.reviewedAt ? ' · ' + new Date(r.reviewedAt).toLocaleString('pt-BR') : '';
            var who = (r.requestedByName || r.requestedByEmail || '—');
            html += '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 flex flex-wrap items-center justify-between gap-4">';
            html += '<div class="flex items-center gap-6">';
            html += '<div class="w-12 h-12 bg-slate-100 dark:bg-slate-700 rounded-xl flex items-center justify-center text-xl font-black text-slate-600 dark:text-slate-400">' + (r.tenantName ? r.tenantName.charAt(0).toUpperCase() : 'E') + '</div>';
            html += '<div><h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg">' + (r.tenantName || 'Empresa') + '</h4>';
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
        listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Erro ao carregar</p></div>';
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
function setActiveTab(tab) {
    // Bloqueia antes de alterar estado, título ou conteúdo. Isso protege
    // menus duplicados, caches de navegação e acionamentos manuais.
    var profile = AppState.user ? String(AppState.user.profile || '').toLowerCase() : '';
    if (['api-busca'].includes(tab) && profile !== 'super_admin') {
        tab = 'dashboard';
    }
    var now = Date.now();
    if (AppState._lastTabRequest === tab && (now - (AppState._lastTabRequestAt || 0)) < 300) {
        return;
    }
    AppState._lastTabRequest = tab;
    AppState._lastTabRequestAt = now;
    AppState.activeTab = tab;
    
    document.querySelectorAll('.tab-btn').forEach(btn => {
        // Preserva as classes de travamento de visibilidade (.hidden, admin-only)
        // aplicadas na inicialização; sem isso, trocar de aba reexibia o menu
        // "Tráfego do site" e demais itens restritos.
        var keep = [];
        btn.classList.forEach(function(cls) {
            if (cls === 'hidden' || cls === 'admin-only') keep.push(cls);
        });
        var keepClasses = keep.join(' ');
        if (btn.dataset.tab === tab) {
            btn.className = 'tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm bg-blue-600 text-white shadow-xl shadow-blue-900/30 ' + keepClasses;
        } else {
            btn.className = 'tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300 ' + keepClasses;
        }
    });
    
    const titles = {
        dashboard: 'Dashboard',
        search: 'Prospecção Inteligente',
        history: 'Arquivo de Buscas',
        'cnpj-lookup': 'Consulta CNPJ (Avançada)', 
        'cnpj-advanced': 'Busca Simples (Segmento)',
        'request-credits': 'Solicitar Créditos',
        'choose-plan': 'Meu plano',
        crm: 'CRM comercial',
        team: 'Minha Equipe', 
        'saas-config': 'Empresa SaaS',
        'latest-companies': 'Recém-Abertas',
        plans: 'Planos',
        companies: 'Empresas',
        credits: 'Créditos',
        'api-busca': 'API de Busca',
        notifications: 'Notificações',
        linkedin: 'Pesquisa LinkedIn',
        settings: 'Integração CRM',
        requests: 'Solicitações',
        'landing-page': 'Landing Page'
    };
    document.getElementById('page-title').textContent = titles[tab] || 'Dashboard';
    
    loadTab(tab);
}

function loadTab(tab) {
    const contentArea = document.getElementById('content-area');
    if (!contentArea) return;
    closeMobileSidebar();
    if (AppState._tabLoadingTarget === tab) return;
    AppState._tabLoadingTarget = tab;
    window.setTimeout(function() {
        if (AppState._tabLoadingTarget === tab) AppState._tabLoadingTarget = null;
    }, 300);
    // Declaração no topo do escopo: usada por TODOS os branches abaixo (tráfego,
    // abas restritas, settings). Evita "isSuperAdmin is not defined" quando o
    // AppState.user ainda não existe ou quando o branch fica fora do if.
    const isSuperAdmin = !!(AppState.user && String(AppState.user.profile || '').toLowerCase() === 'super_admin');
    const currentUserTenantId = Number(AppState.user && (AppState.user.tenant_id || AppState.user.tenantId || (AppState.tenant && AppState.tenant.id)) || 0);
    const canManagePlanCatalog = isSuperAdmin || (AppState.user && String(AppState.user.profile || '').toLowerCase() === 'admin' && currentUserTenantId === 1);
    
    if (AppState.user) {
        const profile = String(AppState.user.profile).toLowerCase();

        // As abas abaixo manipulam dados globais da plataforma. Nunca devem ser
        // carregadas por uma empresa, mesmo se uma aba anterior ficar ativa.
        const superAdminOnlyTabs = ['saas-config', 'companies', 'credits', 'api-busca', 'landing-page'];
        if ((!isSuperAdmin && !canManagePlanCatalog && tab === 'plans') || (!isSuperAdmin && superAdminOnlyTabs.includes(tab))) {
            setActiveTab('dashboard');
            return;
        }

        // O administrador administra o webhook geral da empresa; supervisor e
        // usuários comuns (normal/user) administram somente o próprio webhook.
        const canManageWebhook = ['admin', 'supervisor', 'normal', 'user'].includes(profile);
        if (tab === 'settings' && !isSuperAdmin && !canManageWebhook) {
            setActiveTab('dashboard');
            return;
        }

        // Créditos avulsos e escolha de plano são responsabilidades exclusivas
        // do administrador da empresa, não de membros da equipe.
        const canManageCompanyBilling = profile === 'admin';
        if (['request-credits', 'choose-plan'].includes(tab) && !canManageCompanyBilling) {
            setActiveTab('dashboard');
            return;
        }

        const canManageTeam = ['super_admin', 'admin', 'supervisor', 'manager', 'gerente'].includes(profile);
        if (tab === 'team' && !canManageTeam) {
            setActiveTab('dashboard');
            return;
        }

        const canUseLinkedIn = true;
        const canUseCrm = isSuperAdmin || profile === 'admin' || appHasModule('crm');

        if (tab === 'crm' && !canUseCrm) {
            setActiveTab('dashboard');
            return;
        }

        if (tab === 'linkedin' && !canUseLinkedIn) {
            setActiveTab('dashboard');
            return;
        }

        // A configuração e o monitoramento da API de Busca são exclusivos do super administrador.
        if (tab === 'api-busca' && !isSuperAdmin) {
            setActiveTab('search');
            return;
        }

        // Supervisores podem configurar exclusivamente o webhook pessoal. Gerentes
        // e supervisores não acessam configurações globais, financeiras ou empresariais.
        if (profile === 'supervisor' || profile === 'manager' || profile === 'gerente') {
            const restrictedOperationalTabs = profile === 'supervisor'
                ? ['saas-config', 'plans', 'companies', 'credits', 'api-busca', 'choose-plan']
                : ['settings', 'saas-config', 'plans', 'companies', 'credits', 'api-busca', 'choose-plan'];
            if (restrictedOperationalTabs.includes(tab)) {
                setActiveTab('dashboard');
                return;
            }
        }
    }

    // Carregamento normal das abas...
    if (tab === 'dashboard') {
        renderDashboardTab(contentArea);
    } else if (tab === 'request-credits') {
        renderRequestCreditsTab(contentArea);
    } else if (tab === 'team') {
        renderTeamTab(contentArea);
    } else if (tab === 'crm') {
        renderCrmTab(contentArea);
    } else if (tab === 'search') {
        contentArea.innerHTML = getProspectingHTML();
        setupProspectingEvents();
    } else if (tab === 'history') {
        loadHistory();
    } else if (tab === 'cnpj-lookup') {
        renderCnpjLookupTab(contentArea);
    } else if (tab === 'cnpj-advanced') {
        renderCnpjAdvancedTab(contentArea);
    } else if (tab === 'choose-plan') {
        renderChoosePlanTab(contentArea);
    } else if (tab === 'credits') {
        // Aba administrativa distinta de “Solicitações”. Mostra a gestão de
        // solicitações de créditos sem misturar os pedidos de planos.
        renderCreditsAdminTab(contentArea);
    } else if (tab === 'requests') {
        renderRequestsTab(contentArea);
    } else if (tab === 'saas-config') {
        renderSaasConfigTab(contentArea);
    } else if (tab === 'plans') {
        loadPlansTab(contentArea);
    } else if (tab === 'companies') {
        loadCompaniesTab(contentArea);
    } else if (tab === 'notifications') {
        if (typeof window.renderInboxTab === 'function') {
            window.renderInboxTab(contentArea);
        } else {
            contentArea.innerHTML = '<div class="p-8 text-sm font-semibold text-slate-500">A Central de Notificações não está disponível no momento.</div>';
        }
    } else if (tab === 'help') {
        if (typeof renderHelpTab === 'function') {
            renderHelpTab(contentArea);
        } else {
            contentArea.innerHTML = '<div class="p-8 text-sm font-semibold text-slate-500">A aba de ajuda não está disponível no momento.</div>';
        }
    } else if (tab === 'versions') {
        if (typeof renderVersionsTab === 'function') {
            renderVersionsTab(contentArea);
        } else {
            contentArea.innerHTML = '<div class="p-8 text-sm font-semibold text-slate-500">O histórico de versões não está disponível no momento.</div>';
        }
    } else if (tab === 'linkedin') {
        if (typeof window.renderLinkedInTab === 'function') {
            window.renderLinkedInTab(contentArea);
        } else {
            contentArea.innerHTML = '<div class="p-8 text-sm font-semibold text-red-600">A interface de pesquisa LinkedIn não foi carregada. Verifique a inclusão de assets/js/linkedin-ui.js no index.php.</div>';
        }
    } else if (tab === 'api-busca') {
        contentArea.innerHTML = getApiBuscaHTML();
        setupApiBuscaEvents();
        disableAiResearchControls();
        loadMapsPilotMetrics();
        const usagePanel = document.getElementById('apify-usage-panel');
        if (usagePanel && typeof window.renderApifyUsagePanel === 'function') {
            window.renderApifyUsagePanel(usagePanel).catch(function (error) {
                console.error('Erro ao carregar o monitoramento das contas:', error);
            });
        }
        loadSettingsForm();
    } else if (tab === 'settings') {
        contentArea.innerHTML = getSettingsHTML();
        setupSettingsEvents();
        loadSettingsForm();
    } else if (tab === 'landing-page') {
        if (typeof renderLandingPageTab === 'function') {
            renderLandingPageTab(contentArea);
        } else {
            contentArea.innerHTML = `
                <div class="max-w-4xl mx-auto py-20 text-center">
                    <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-2">Editor de Landing Page</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mb-6">Gerencie as seções, textos e planos exibidos na página inicial.</p>
                    <div class="p-6 bg-slate-50 dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-sm font-bold">
                        O módulo de edição visual da Landing Page está ativo. Certifique-se de que o arquivo JS responsável pelas seções públicas está incluído no seu template HTML principal.
                    </div>
                </div>
            `;
        }
    } else if (tab === 'traffic') {
        if (!isSuperAdmin) {
            setActiveTab('dashboard');
            return;
        }
        renderTrafficTab(contentArea);
    } else if (tab === 'referrals') {
        renderReferralsTab(contentArea);
    }
}

function renderTrafficTab(contentArea) {
    if (!contentArea) return;
    contentArea.innerHTML = `
        <div class="max-w-6xl mx-auto py-10">
            <div class="flex items-center justify-between gap-4 mb-8">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600 mb-2">Super administrador</p>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Tráfego do site</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Origem das visitas, dispositivos e páginas mais acessadas.</p>
                </div>
                <button type="button" id="traffic-refresh" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold px-4 py-3 rounded-2xl text-xs">Atualizar</button>
            </div>
            <div id="traffic-counts" class="grid grid-cols-2 gap-4 mb-8 lg:grid-cols-4"></div>
            <div id="traffic-chart" class="mb-8 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"></div>
            <div class="grid gap-6 lg:grid-cols-3">
                <div id="traffic-referrers" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"></div>
                <div id="traffic-devices" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"></div>
                <div id="traffic-pages" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"></div>
            </div>
        </div>`;
    document.getElementById('traffic-refresh').onclick = loadTrafficData;
    loadTrafficData();
}

function loadTrafficData() {
    const countsEl = document.getElementById('traffic-counts');
    const chartEl = document.getElementById('traffic-chart');
    const refsEl = document.getElementById('traffic-referrers');
    const devEl = document.getElementById('traffic-devices');
    const pagesEl = document.getElementById('traffic-pages');
    const loading = '<div class="flex items-center gap-2 text-xs font-semibold text-slate-400"><svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" class="opacity-25"/><path d="M4 12a8 8 0 018-8" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg> Carregando...</div>';
    if (chartEl) chartEl.innerHTML = loading;
    if (refsEl) refsEl.innerHTML = loading;
    if (devEl) devEl.innerHTML = loading;
    if (pagesEl) pagesEl.innerHTML = loading;

    const request = (url) => fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((res) => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then((payload) => {
            if (!payload.success) throw new Error((payload && payload.error) || 'Indisponível');
            return payload.data || {};
        });

    request('api/site-traffic.php?action=counts').then((data) => {
        const c = data.counts || {};
        const card = (title, value) => `<div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800"><p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">${escapeVersionsHtml(title)}</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-slate-100">${Number.isFinite(c[value]) ? Number(c[value]).toLocaleString('pt-BR') : '—'}</p></div>`;
        if (countsEl) countsEl.innerHTML = card('Visitas totais', 'visits') + card('Visitantes únicos', 'unique_visitors') + card('Visitas (7 dias)', 'visits_7d') + card('Únicos (7 dias)', 'unique_visitors_7d');
    }).catch(() => {
        if (countsEl) countsEl.innerHTML = '<div class="col-span-2 rounded-3xl border border-dashed border-slate-300 bg-slate-50 p-4 text-xs text-slate-500 lg:col-span-4 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-400">Sem dados de tráfego disponíveis. Execute o SQL do módulo de tráfego na base de dados.</div>';
    });

    request('api/site-traffic.php?action=report').then((data) => {
        if (chartEl) {
            const daily = Array.isArray(data.daily) ? data.daily : [];
            const maxVisits = Math.max(1, Number(data.maxVisits || 0), ...daily.map((d) => Number(d.visits || 0)));
            const rows = daily.map((d) => {
                const visits = Math.max(0, Number(d.visits || 0));
                const height = visits > 0 ? Math.max(10, Math.round((visits / maxVisits) * 116)) : 3;
                return `<div class="flex h-full min-w-0 flex-1 items-end justify-center" title="${escapeVersionsHtml(String(d.day || ''))}: ${visits} visita(s)"><div class="w-full max-w-7 rounded-t-md" style="height:${height}px;background-color:${visits > 0 ? '#2563eb' : '#e2e8f0'}"></div></div>`;
            }).join('');
            const labels = daily.map((d) => `<p class="min-w-0 flex-1 text-center text-[9px] font-semibold text-slate-400">${escapeVersionsHtml((d.day || '').slice(5))}</p>`).join('');
            chartEl.innerHTML = `<p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-1">Visitas por dia (14 dias)</p><p class="mb-3 text-[11px] font-semibold text-slate-400">Total no período: ${daily.reduce((sum, d) => sum + Math.max(0, Number(d.visits || 0)), 0).toLocaleString('pt-BR')} visitas</p><div class="flex h-32 items-end gap-1 border-b border-slate-200 pb-1 dark:border-slate-700">${rows || '<p class="text-xs text-slate-400">Sem visitas registradas.</p>'}</div><div class="mt-1 flex gap-1">${labels}</div>`;
        }
        if (refsEl) {
            const items = (data.referrers || []).map((r) => `<div class="flex items-center justify-between gap-2 border-b border-slate-100 py-2 last:border-0 dark:border-slate-700"><p class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200">${escapeVersionsHtml(r.source || '')}</p><p class="shrink-0 text-xs font-black text-slate-900 dark:text-slate-100">${Number(r.visits || 0).toLocaleString('pt-BR')}</p></div>`).join('');
            refsEl.innerHTML = `<p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-3">De onde vieram (30 dias)</p><div>${items || '<p class="text-xs text-slate-400">Nenhuma origem registrada.</p>'}</div>`;
        }
        if (devEl) {
            const total = (data.devices || []).reduce((acc, d) => acc + (d.visits || 0), 0) || 1;
            const items = (data.devices || []).map((d) => {
                const pct = Math.round(((d.visits || 0) / total) * 100);
                return `<div class="mb-3"><div class="flex items-center justify-between mb-1"><p class="text-xs font-semibold text-slate-700 dark:text-slate-200">${escapeVersionsHtml(d.device === 'mobile' ? 'Celular' : d.device === 'tablet' ? 'Tablet' : 'Computador')}</p><p class="text-xs font-black text-slate-900 dark:text-slate-100">${pct}%</p></div><div class="h-2 rounded-full bg-slate-100 overflow-hidden dark:bg-slate-700"><div class="h-full rounded-full bg-blue-500" style="width:${pct}%"></div></div></div>`;
            }).join('');
            devEl.innerHTML = `<p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-3">Dispositivos (30 dias)</p>${items || '<p class="text-xs text-slate-400">Sem dados de dispositivos.</p>'}`;
        }
        if (pagesEl) {
            const items = (data.top_pages || []).map((p) => `<div class="flex items-center justify-between gap-2 border-b border-slate-100 py-2 last:border-0 dark:border-slate-700"><p class="truncate text-xs font-semibold text-slate-700 dark:text-slate-200">${escapeVersionsHtml(p.page || '')}</p><p class="shrink-0 text-xs font-black text-slate-900 dark:text-slate-100">${Number(p.visits || 0).toLocaleString('pt-BR')}</p></div>`).join('');
            pagesEl.innerHTML = `<p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-3">Páginas mais vistas (30 dias)</p><div>${items || '<p class="text-xs text-slate-400">Nenhuma página registrada.</p>'}</div>`;
        }
    }).catch(() => {
        if (chartEl) chartEl.innerHTML = '<p class="text-xs text-slate-400">Não foi possível carregar o relatório de tráfego.</p>';
    });
}

function renderReferralsTab(contentArea) {
    if (!contentArea) return;
    contentArea.innerHTML = `
        <div class="max-w-7xl mx-auto py-10">
            <div class="flex items-center justify-between gap-4 mb-8">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600 mb-2">Programa de indicações</p>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Minhas indicações</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Divulgue seu link, acompanhe os cliques e o valor a receber.</p>
                </div>
                <button type="button" id="referrals-refresh" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold px-4 py-3 rounded-2xl text-xs">Atualizar</button>
            </div>
            <div id="referrals-summary" class="grid grid-cols-2 gap-4 mb-8 lg:grid-cols-4"></div>
            <div id="referrals-admin-dashboard" class="hidden mb-8"></div>
            <div id="referrals-link" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm mb-8 dark:border-slate-700 dark:bg-slate-800"></div>
            <div id="referrals-payment" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm mb-8 dark:border-slate-700 dark:bg-slate-800"></div>
            <div id="referrals-list" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm mb-8 dark:border-slate-700 dark:bg-slate-800"></div>
            <div class="grid gap-6 lg:grid-cols-2">
                <div id="referrals-admin" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"></div>
                <div id="referrals-withdrawals" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"></div>
            </div>
        </div>`;
    document.getElementById('referrals-refresh').onclick = loadReferralsData;
    loadReferralsData();
}

function loadReferralsData() {
    const summaryEl = document.getElementById('referrals-summary');
    const linkEl = document.getElementById('referrals-link');
    const paymentEl = document.getElementById('referrals-payment');
    const referralsListEl = document.getElementById('referrals-list');
    const adminDashboardEl = document.getElementById('referrals-admin-dashboard');
    const adminEl = document.getElementById('referrals-admin');
    const withdrawalsEl = document.getElementById('referrals-withdrawals');
    if (summaryEl) summaryEl.innerHTML = '<div class="col-span-2 lg:col-span-4 text-xs font-semibold text-slate-400">Carregando...</div>';
    if (linkEl) linkEl.innerHTML = '<div class="text-xs font-semibold text-slate-400">Carregando...</div>';
    if (paymentEl) paymentEl.innerHTML = '<div class="text-xs font-semibold text-slate-400">Carregando dados de recebimento...</div>';
    if (referralsListEl) referralsListEl.innerHTML = '<div class="text-xs font-semibold text-slate-400">Carregando acompanhamento...</div>';
    if (adminDashboardEl) adminDashboardEl.innerHTML = '<div class="rounded-3xl border border-slate-200 bg-white p-6 text-xs font-semibold text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-800">Carregando painel consolidado...</div>';
    if (adminEl) adminEl.innerHTML = '<div class="text-xs font-semibold text-slate-400">Carregando...</div>';
    if (withdrawalsEl) withdrawalsEl.innerHTML = '<div class="text-xs font-semibold text-slate-400">Carregando...</div>';

    const fmtBRL = (n) => `R$ ${Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

    fetch('api/referrals.php?action=stats', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((res) => {
            if (!res.ok) throw new Error('HTTP ' + res.status);
            return res.json();
        })
        .then((payload) => {
            if (!payload.success) throw new Error((payload && payload.error) || 'Indisponível');
            const data = payload.data || {};
            const fmt = (n) => Number(n).toLocaleString('pt-BR');
            const card = (title, value, sub) => `<div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-slate-800"><p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">${escapeVersionsHtml(title)}</p><p class="mt-2 text-3xl font-black text-slate-900 dark:text-slate-100">${value}</p>${sub ? `<p class="mt-1 text-[11px] font-semibold text-slate-400">${escapeVersionsHtml(sub)}</p>` : ''}</div>`;
            if (summaryEl) summaryEl.innerHTML = [
                card('Cliques recebidos', fmt(data.clicks || 0), 'Visitas no seu link'),
                card('Indicações', fmt(data.referrals || 0), 'Registros com seu código'),
                card('Convertidas', fmt(data.converted || 0), 'Indicações pagantes'),
                card('A receber', fmtBRL(data.earned || 0), `Mín. de saque: ${fmtBRL(data.minimum_withdrawal || 100)}`)
            ].join('');
            if (linkEl) linkEl.innerHTML = data.terms_accepted ? `
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-2">Seu link de indicação</p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center"><code class="flex-1 break-all rounded-xl bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-700 dark:bg-slate-900 dark:text-slate-200">${escapeVersionsHtml(data.link || '')}</code>
                <button type="button" id="referrals-copy-link" class="shrink-0 rounded-xl bg-blue-600 px-5 py-3 text-xs font-black text-white transition hover:bg-blue-700 active:scale-[0.97]">Copiar link</button></div>
                <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Cada pessoa que se cadastrar usando o seu código gera ${fmtBRL(data.payout_per_conversion || 10)} de crédito para você. Quando o saldo atingir o mínimo de ${fmtBRL(data.minimum_withdrawal || 100)}, você pode solicitar o saque.</p>` : `
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-2">Ative seu link de indicação</p>
                <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800/70 dark:bg-blue-950/30">
                    <p class="text-sm font-black text-slate-800 dark:text-slate-100">Termo de Indicação — versão ${escapeVersionsHtml(data.terms_version || '')}</p>
                    <div class="mt-3 max-h-48 space-y-2 overflow-y-auto pr-2 text-xs leading-relaxed text-slate-600 dark:text-slate-300">
                        <p><strong>1. Divulgação responsável.</strong> Use o link apenas em comunicações legítimas, sem spam, publicidade enganosa, automações abusivas ou autopromoção.</p>
                        <p><strong>2. Atribuição.</strong> A indicação é vinculada ao primeiro acesso válido do navegador e pode ser reconhecida por até 50 dias. Limpeza de dados do navegador, troca de dispositivo ou bloqueadores podem impedir a atribuição.</p>
                        <p><strong>3. Comissão.</strong> O crédito só é devido após pagamento confirmado, conforme o valor vigente no painel. Cancelamentos, reembolsos, chargebacks, fraude ou uso indevido anulam a comissão.</p>
                        <p><strong>4. Saque.</strong> O saque mínimo é de ${fmtBRL(data.minimum_withdrawal || 100)} e fica sujeito à análise operacional. O participante responde pelas obrigações fiscais e pelos dados de pagamento informados.</p>
                        <p><strong>5. Privacidade e revisão.</strong> O painel mostra somente status agregados, sem dados pessoais dos indicados. A plataforma pode suspender links, revisar atribuições e atualizar este termo mediante nova versão.</p>
                    </div>
                    <label class="mt-4 flex cursor-pointer items-start gap-2 text-xs font-semibold text-slate-700 dark:text-slate-200"><input id="referrals-terms-check" type="checkbox" class="mt-0.5" /> <span>Li e aceito o Termo de Indicação e as regras de atribuição, comissão e saque.</span></label>
                    <button type="button" id="referrals-accept-terms" disabled class="mt-4 rounded-xl bg-slate-300 px-5 py-3 text-xs font-black text-white transition disabled:cursor-not-allowed dark:bg-slate-700">Aceitar e liberar meu link</button>
                </div>`;
            const copyBtn = document.getElementById('referrals-copy-link');
            if (copyBtn) copyBtn.onclick = () => {
                navigator.clipboard.writeText(data.link || '').then(() => {
                    if (typeof showToast === 'function') showToast('Link copiado para a área de transferência.', 'success');
                    copyBtn.textContent = 'Copiado!';
                    setTimeout(() => { copyBtn.textContent = 'Copiar link'; }, 2000);
                });
            };
            const termsCheck = document.getElementById('referrals-terms-check');
            const acceptTermsBtn = document.getElementById('referrals-accept-terms');
            if (termsCheck && acceptTermsBtn) {
                termsCheck.onchange = () => {
                    acceptTermsBtn.disabled = !termsCheck.checked;
                    acceptTermsBtn.className = termsCheck.checked
                        ? 'mt-4 rounded-xl bg-blue-600 px-5 py-3 text-xs font-black text-white transition hover:bg-blue-700 active:scale-[0.97]'
                        : 'mt-4 rounded-xl bg-slate-300 px-5 py-3 text-xs font-black text-white transition disabled:cursor-not-allowed dark:bg-slate-700';
                };
                acceptTermsBtn.onclick = () => {
                    if (!termsCheck.checked) return;
                    acceptTermsBtn.disabled = true;
                    acceptTermsBtn.textContent = 'Registrando aceite...';
                    fetch('api/referrals.php?action=accept-terms', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ accepted: true, terms_version: data.terms_version })
                    }).then((r) => r.json()).then((p) => {
                        if (p && p.success) {
                            if (typeof showToast === 'function') showToast('Termo aceito. Seu link de indicação foi liberado.', 'success');
                            loadReferralsData();
                        } else {
                            if (typeof showToast === 'function') showToast((p && p.error) || 'Não foi possível registrar o aceite.', 'error');
                            acceptTermsBtn.disabled = false;
                            acceptTermsBtn.textContent = 'Aceitar e liberar meu link';
                        }
                    }).catch(() => { acceptTermsBtn.disabled = false; acceptTermsBtn.textContent = 'Aceitar e liberar meu link'; });
                };
            }
            if (paymentEl) {
                const profile = data.payment_profile || {};
                const selectedType = String(profile.pix_key_type || '');
                const selectedKey = String(profile.pix_key || '');
                const typeOptions = [
                    ['cpf', 'CPF'],
                    ['cnpj', 'CNPJ'],
                    ['email', 'E-mail'],
                    ['telefone', 'Telefone'],
                    ['aleatoria', 'Chave aleatória'],
                ].map(([value, label]) => `<option value="${value}"${selectedType === value ? ' selected' : ''}>${label}</option>`).join('');
                paymentEl.innerHTML = data.terms_accepted ? `
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Dados para recebimento</p><h4 class="mt-1 text-base font-black text-slate-900 dark:text-slate-100">Receba suas comissões por PIX</h4><p class="mt-1 max-w-2xl text-xs leading-relaxed text-slate-500 dark:text-slate-400">Informe a chave que deverá ser usada quando um saque for aprovado. Esses dados ficam visíveis somente para você e para o super administrador responsável pelo pagamento.</p></div><span class="w-fit rounded-full bg-emerald-100 px-3 py-1.5 text-[10px] font-black text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">PIX preferencial</span></div>
                    <div class="mt-5 grid gap-4 md:grid-cols-3"><label><span class="mb-1 block text-[11px] font-bold text-slate-600 dark:text-slate-300">Forma de recebimento</span><input type="text" value="PIX" readonly class="w-full cursor-not-allowed rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-xs font-semibold text-slate-600 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300" /></label><label><span class="mb-1 block text-[11px] font-bold text-slate-600 dark:text-slate-300">Tipo de chave PIX</span><select id="referrals-pix-key-type" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"><option value="">Selecione</option>${typeOptions}</select></label><label><span class="mb-1 block text-[11px] font-bold text-slate-600 dark:text-slate-300">Chave PIX</span><input id="referrals-pix-key" type="text" maxlength="160" autocomplete="off" value="${escapeVersionsHtml(selectedKey)}" placeholder="Informe a chave" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-semibold text-slate-700 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" /></label></div>
                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-[11px] font-semibold ${profile.configured ? 'text-emerald-600 dark:text-emerald-300' : 'text-amber-600 dark:text-amber-300'}">${profile.configured ? 'Chave PIX cadastrada. Você pode atualizá-la quando necessário.' : 'Cadastre sua chave PIX antes de solicitar o primeiro saque.'}</p><button type="button" id="referrals-save-pix" class="rounded-xl bg-blue-600 px-5 py-3 text-xs font-black text-white transition hover:bg-blue-700 active:scale-[0.97]">Salvar dados PIX</button></div>` : `
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Dados para recebimento</p><p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Aceite o Termo de Indicação para cadastrar a sua chave PIX e liberar seu link.</p>`;
                const savePixBtn = document.getElementById('referrals-save-pix');
                if (savePixBtn) savePixBtn.onclick = () => {
                    const typeEl = document.getElementById('referrals-pix-key-type');
                    const keyEl = document.getElementById('referrals-pix-key');
                    const pixKeyType = String((typeEl && typeEl.value) || '');
                    const pixKey = String((keyEl && keyEl.value) || '').trim();
                    if (!pixKeyType || !pixKey) {
                        if (typeof showToast === 'function') showToast('Selecione o tipo e informe a chave PIX.', 'error');
                        return;
                    }
                    savePixBtn.disabled = true;
                    savePixBtn.textContent = 'Salvando...';
                    fetch('api/referrals.php?action=payment-profile', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ preferred_method: 'pix', pix_key_type: pixKeyType, pix_key: pixKey })
                    }).then((r) => r.json()).then((p) => {
                        if (p && p.success) {
                            if (typeof showToast === 'function') showToast('Dados PIX salvos com sucesso.', 'success');
                            loadReferralsData();
                        } else {
                            if (typeof showToast === 'function') showToast((p && p.error) || 'Não foi possível salvar os dados PIX.', 'error');
                            savePixBtn.disabled = false;
                            savePixBtn.textContent = 'Salvar dados PIX';
                        }
                    }).catch(() => { savePixBtn.disabled = false; savePixBtn.textContent = 'Salvar dados PIX'; });
                };
            }
            if (referralsListEl) {
                const statusLabel = (item) => item.status === 'withdrawn' ? 'Saque realizado' : item.status === 'converted' ? 'Convertida' : item.registered ? 'Cadastro identificado' : item.clicked_at ? 'Clique registrado' : 'Link criado';
                const statusClass = (item) => item.status === 'withdrawn' || item.status === 'converted'
                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                    : item.registered ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'
                    : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300';
                const rows = (data.referrals_list || []).map((item) => `<div class="flex flex-col gap-2 border-b border-slate-100 py-3 last:border-0 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-black text-slate-800 dark:text-slate-100">Indicação ${escapeVersionsHtml(item.code || '')}</p><p class="mt-1 text-[11px] font-semibold text-slate-400">${item.clicked_at ? `Clique: ${escapeVersionsHtml(String(item.clicked_at).slice(0, 16))}` : 'Ainda sem clique registrado'}${item.registered ? ' · Cadastro atribuído' : ''}</p></div><span class="w-fit rounded-full px-2.5 py-1 text-[10px] font-black ${statusClass(item)}">${statusLabel(item)}</span></div>`).join('');
                referralsListEl.innerHTML = `<p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-2">Acompanhamento dos indicados</p><p class="mb-4 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Você acompanha aqui cada clique, cadastro atribuído e conversão. A indicação fica guardada por 50 dias após a pessoa abrir seu link.</p><div>${rows || '<p class="text-xs text-slate-400">Ainda não há acessos no seu link de indicação.</p>'}</div>`;
            }
            const isAdmin = (AppState.user && String(AppState.user.profile || '').toLowerCase() === 'super_admin');
            if (isAdmin) loadReferralsAdminDashboard();
            const hasPixProfile = !!(data.payment_profile && data.payment_profile.configured);
            if (adminEl) adminEl.innerHTML = isAdmin
                ? '<p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-3">Administração (super admin)</p><div id="referrals-admin-content" class="space-y-3"></div><button type="button" id="referrals-admin-load" class="mt-3 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-black text-slate-700 hover:border-blue-300 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">Carregar saques e configurações</button>'
                : '<p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-3">Saque</p>' + (data.can_withdraw && hasPixProfile
                    ? `<p class="text-xs leading-relaxed text-slate-600 dark:text-slate-300 mb-3">Seu saldo de ${fmtBRL(data.earned || 0)} já atingiu o mínimo de saque.</p><button type="button" id="referrals-withdraw" class="rounded-xl bg-emerald-600 px-5 py-3 text-xs font-black text-white transition hover:bg-emerald-700 active:scale-[0.97]">Solicitar saque</button>`
                    : `<p class="text-xs leading-relaxed text-slate-500 dark:text-slate-400 mb-3">${!hasPixProfile ? 'Cadastre sua chave PIX acima para habilitar solicitações de saque.' : `Saldo atual: ${fmtBRL(data.earned || 0)}. O saque mínimo é de ${fmtBRL(data.minimum_withdrawal || 100)}. Continue divulgando seu link para atingir o valor.`}</p><button type="button" id="referrals-withdraw" disabled class="rounded-xl bg-slate-100 px-5 py-3 text-xs font-black text-slate-400 dark:bg-slate-800">Solicitar saque</button>`);
            const withdrawBtn = document.getElementById('referrals-withdraw');
            if (withdrawBtn && data.can_withdraw && hasPixProfile) withdrawBtn.onclick = () => {
                withdrawBtn.disabled = true;
                withdrawBtn.textContent = 'Processando...';
                fetch('api/referrals.php?action=withdraw-request', { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: '{}' })
                    .then((r) => r.json())
                    .then((p) => {
                        if (p && p.success) {
                            if (typeof showToast === 'function') showToast(`Saque de ${fmtBRL((p.data && p.data.amount) || 0)} solicitado com sucesso.`, 'success');
                        } else {
                            if (typeof showToast === 'function') showToast((p && p.error) || 'Não foi possível solicitar o saque.', 'error');
                            withdrawBtn.disabled = false;
                            withdrawBtn.textContent = 'Solicitar saque';
                        }
                    })
                    .catch(() => { withdrawBtn.disabled = false; withdrawBtn.textContent = 'Solicitar saque'; });
            };
            if (withdrawalsEl) {
                const rows = (data.withdrawals || []).map((w) => `<div class="flex items-center justify-between gap-2 border-b border-slate-100 py-2 last:border-0 dark:border-slate-700"><p class="text-xs font-semibold text-slate-600 dark:text-slate-300">${escapeVersionsHtml(String(w.created_at || '').slice(0, 10))}</p><p class="text-xs font-black text-slate-900 dark:text-slate-100">${fmtBRL(w.amount || 0)}</p><span class="rounded-full px-2.5 py-1 text-[10px] font-black ${w.status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : w.status === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' : w.status === 'approved' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'}">${w.status === 'paid' ? 'Pago' : w.status === 'rejected' ? 'Rejeitado' : w.status === 'approved' ? 'Aprovado' : 'Pendente'}</span></div>`).join('');
                withdrawalsEl.innerHTML = `<p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-3">Histórico de saques</p><div>${rows || '<p class="text-xs text-slate-400">Nenhum saque solicitado ainda.</p>'}</div>`;
            }
            const adminLoadBtn = document.getElementById('referrals-admin-load');
            if (adminLoadBtn) adminLoadBtn.onclick = loadReferralsAdmin;
        })
        .catch(() => {
            if (summaryEl) summaryEl.innerHTML = '<div class="col-span-2 lg:col-span-4 text-xs text-slate-400">Não foi possível carregar as suas indicações.</div>';
        });
}

function loadReferralsAdminDashboard() {
    const dashboardEl = document.getElementById('referrals-admin-dashboard');
    if (!dashboardEl) return;
    dashboardEl.classList.remove('hidden');
    dashboardEl.innerHTML = '<div class="rounded-3xl border border-slate-200 bg-white p-6 text-xs font-semibold text-slate-400 shadow-sm dark:border-slate-700 dark:bg-slate-800">Carregando painel consolidado...</div>';
    const fmtNumber = (value) => Number(value || 0).toLocaleString('pt-BR');
    const fmtBRL = (value) => `R$ ${Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    const pixLabel = (item) => {
        const types = { cpf: 'CPF', cnpj: 'CNPJ', email: 'E-mail', telefone: 'Telefone', aleatoria: 'Chave aleatória' };
        return item && item.pix_key ? `PIX · ${types[item.pix_key_type] || 'Chave'} · ${item.pix_key}` : 'PIX não informado';
    };
    const metric = (title, value, detail) => `<div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-700 dark:bg-slate-900/50"><p class="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">${escapeVersionsHtml(title)}</p><p class="mt-1 text-2xl font-black text-slate-900 dark:text-slate-100">${value}</p>${detail ? `<p class="mt-1 text-[10px] font-semibold text-slate-400">${escapeVersionsHtml(detail)}</p>` : ''}</div>`;
    fetch('api/referrals.php?action=admin-dashboard', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((response) => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            return response.json();
        })
        .then((payload) => {
            if (!payload || !payload.success) throw new Error((payload && payload.error) || 'Indisponível');
            const data = payload.data || {};
            const overview = data.overview || {};
            const ranking = Array.isArray(data.top_indicators) ? data.top_indicators : [];
            const participants = Array.isArray(data.participants) ? data.participants : [];
            const rankRows = ranking.map((item, index) => `<div class="flex flex-col gap-3 border-b border-slate-100 py-3 last:border-0 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between"><div class="flex min-w-0 items-center gap-3"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${index === 0 ? 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300' : 'bg-blue-50 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300'} text-xs font-black">${index + 1}</span><div class="min-w-0"><p class="truncate text-xs font-black text-slate-800 dark:text-slate-100">${escapeVersionsHtml(item.name || `Usuário ${item.user_id || ''}`)}</p><p class="truncate text-[11px] font-semibold text-slate-400">${escapeVersionsHtml(item.tenant_name || 'Sem empresa')} · ${escapeVersionsHtml(item.email || 'E-mail não informado')}</p></div></div><div class="grid grid-cols-3 gap-4 text-right text-[10px] font-bold text-slate-500 dark:text-slate-300"><span><b class="block text-xs text-slate-900 dark:text-white">${fmtNumber(item.clicks)}</b>Cliques</span><span><b class="block text-xs text-slate-900 dark:text-white">${fmtNumber(item.registered)}</b>Cadastros</span><span><b class="block text-xs text-slate-900 dark:text-white">${fmtNumber(item.converted)}</b>Conversões</span></div></div>`).join('');
            const participantRows = participants.map((item) => `<tr class="border-b border-slate-100 last:border-0 dark:border-slate-700"><td class="px-3 py-3"><p class="max-w-48 truncate text-xs font-black text-slate-800 dark:text-slate-100">${escapeVersionsHtml(item.name || `Usuário ${item.user_id || ''}`)}</p><p class="max-w-48 truncate text-[10px] font-semibold text-slate-400">${escapeVersionsHtml(item.email || 'E-mail não informado')}</p></td><td class="px-3 py-3 text-xs font-semibold text-slate-600 dark:text-slate-300">${escapeVersionsHtml(item.tenant_name || 'Sem empresa')}</td><td class="px-3 py-3 text-xs font-semibold text-slate-700 dark:text-slate-200"><p class="max-w-56 break-all">${escapeVersionsHtml(pixLabel(item))}</p></td><td class="px-3 py-3 text-center text-xs font-black text-slate-700 dark:text-slate-200">${fmtNumber(item.clicks)}</td><td class="px-3 py-3 text-center text-xs font-black text-slate-700 dark:text-slate-200">${fmtNumber(item.registered)}</td><td class="px-3 py-3 text-center text-xs font-black text-slate-700 dark:text-slate-200">${fmtNumber(item.converted)}</td><td class="px-3 py-3 text-right text-xs font-black text-amber-700 dark:text-amber-300">${fmtBRL(item.commission_pending)}</td><td class="px-3 py-3 text-right text-xs font-black text-emerald-700 dark:text-emerald-300">${fmtBRL(item.commission_paid)}</td></tr>`).join('');
            dashboardEl.innerHTML = `
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div class="mb-5 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Visão da plataforma</p><h4 class="mt-1 text-xl font-black text-slate-900 dark:text-slate-100">Painel completo de indicações</h4><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Dados consolidados do programa e participantes com aceite ativo.</p></div><p class="text-[10px] font-bold text-slate-400">Termo vigente: ${escapeVersionsHtml(data.terms_version || '')}</p></div>
                    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">${[
                        metric('Links ativos', fmtNumber(overview.active_links), `${fmtNumber(overview.participating_users)} participantes`),
                        metric('Cliques em links', fmtNumber(overview.clicks), 'Acessos registrados'),
                        metric('Cadastros atribuídos', fmtNumber(overview.registered), 'Dentro de 50 dias'),
                        metric('Conversões', fmtNumber(overview.converted), 'Pagamentos confirmados'),
                        metric('Comissões pendentes', fmtBRL(overview.commission_pending), `${fmtNumber(overview.commission_pending_count)} lançamento(s)`),
                        metric('Comissões registradas', fmtBRL(overview.commission_paid), `${fmtNumber(overview.commission_paid_count)} lançamento(s)`),
                        metric('Saques em análise', fmtBRL(overview.withdrawals_awaiting), `${fmtNumber(overview.withdrawals_awaiting_count)} solicitação(ões)`),
                        metric('Saques pagos', fmtBRL(overview.withdrawals_paid), `${fmtNumber(overview.withdrawals_paid_count)} pagamento(s)`)
                    ].join('')}</div>
                    <div class="mt-6 grid gap-6 lg:grid-cols-2"><div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><p class="mb-1 text-xs font-black uppercase tracking-[0.16em] text-slate-400">Top 5 indicações</p><p class="mb-3 text-xs text-slate-500 dark:text-slate-400">Ranking por conversões, cadastros e cliques.</p>${rankRows || '<p class="py-4 text-xs text-slate-400">Ainda não há participantes com termo aceito.</p>'}</div><div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><p class="mb-1 text-xs font-black uppercase tracking-[0.16em] text-slate-400">Leitura operacional</p><ul class="space-y-3 text-xs leading-relaxed text-slate-600 dark:text-slate-300"><li><b class="text-slate-900 dark:text-white">Links ativos:</b> somente usuários que aceitaram a versão atual do termo podem divulgar.</li><li><b class="text-slate-900 dark:text-white">Cadastros atribuídos:</b> a memória de indicação é reconhecida por até 50 dias após o clique válido.</li><li><b class="text-slate-900 dark:text-white">Comissões:</b> permanecem pendentes até o fluxo financeiro aplicável; saques exigem a análise do administrador.</li><li><b class="text-slate-900 dark:text-white">Privacidade:</b> esta visão detalhada é restrita exclusivamente ao super administrador.</li></ul></div></div>
                    <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700"><table class="min-w-[1100px] w-full text-left"><thead class="bg-slate-50 dark:bg-slate-900/60"><tr class="text-[10px] font-black uppercase tracking-[0.12em] text-slate-400"><th class="px-3 py-3">Participante</th><th class="px-3 py-3">Empresa</th><th class="px-3 py-3">Recebimento PIX</th><th class="px-3 py-3 text-center">Cliques</th><th class="px-3 py-3 text-center">Cadastros</th><th class="px-3 py-3 text-center">Conversões</th><th class="px-3 py-3 text-right">Pendente</th><th class="px-3 py-3 text-right">Registrado</th></tr></thead><tbody>${participantRows || '<tr><td colspan="8" class="px-3 py-8 text-center text-xs text-slate-400">Ainda não há usuários com link de indicação ativo.</td></tr>'}</tbody></table></div>
                </div>`;
        })
        .catch(() => {
            dashboardEl.innerHTML = '<div class="rounded-3xl border border-amber-200 bg-amber-50 p-6 text-xs font-semibold text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/30 dark:text-amber-200">O painel consolidado de indicações ainda não está disponível. Confirme a execução do arquivo SQL da atualização e tente atualizar a página.</div>';
        });
}

function loadReferralsAdmin() {
    const adminContent = document.getElementById('referrals-admin-content');
    if (!adminContent) return;
    adminContent.innerHTML = '<p class="text-xs font-semibold text-slate-400">Carregando...</p>';
    const fmtBRL = (n) => `R$ ${Number(n).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    Promise.all([
        fetch('api/referrals.php?action=admin-withdrawals', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then((r) => r.json()),
        fetch('api/referrals.php?action=admin-config', { credentials: 'same-origin', headers: { Accept: 'application/json' } }).then((r) => r.json()),
    ]).then(([wPayload, cPayload]) => {
        const wData = (wPayload && wPayload.success && wPayload.data) || { withdrawals: [] };
        const cData = (cPayload && cPayload.success && cPayload.data) || {};
        const pixLabel = (w) => { const types = { cpf: 'CPF', cnpj: 'CNPJ', email: 'E-mail', telefone: 'Telefone', aleatoria: 'Chave aleatória' }; return w.pix_key ? `PIX · ${types[w.pix_key_type] || 'Chave'} · ${w.pix_key}` : 'PIX não informado'; };
        const rows = (wData.withdrawals || []).map((w) => `<div class="flex flex-col gap-2 border-b border-slate-100 py-2.5 last:border-0 dark:border-slate-700 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-xs font-bold text-slate-800 dark:text-slate-100">${escapeVersionsHtml(w.name || w.email || `Usuário ${w.user_id}`)}</p><p class="text-[11px] font-semibold text-slate-400">${escapeVersionsHtml(String(w.created_at || '').slice(0, 10))} · ${fmtBRL(w.amount || 0)}</p><p class="mt-1 break-all text-[11px] font-semibold text-blue-700 dark:text-blue-300">${escapeVersionsHtml(pixLabel(w))}</p></div><div class="flex items-center gap-2"><span class="rounded-full px-2.5 py-1 text-[10px] font-black ${w.status === 'paid' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : w.status === 'rejected' ? 'bg-red-100 text-red-700 dark:bg-red-950/40 dark:text-red-300' : w.status === 'approved' ? 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'}">${w.status === 'paid' ? 'Pago' : w.status === 'rejected' ? 'Rejeitado' : w.status === 'approved' ? 'Aprovado' : 'Pendente'}</span>${w.status === 'pending' ? `<button type="button" data-rw-approve="${w.id}" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-[10px] font-black text-white hover:bg-emerald-700">Aprovar</button><button type="button" data-rw-reject="${w.id}" class="rounded-lg bg-red-600 px-3 py-1.5 text-[10px] font-black text-white hover:bg-red-700">Rejeitar</button>` : (w.status === 'approved' ? `<button type="button" data-rw-paid="${w.id}" class="rounded-lg bg-blue-600 px-3 py-1.5 text-[10px] font-black text-white hover:bg-blue-700">Marcar pago</button>` : '')}</div></div>`).join('');
        adminContent.innerHTML = `
            <div class="mb-4 rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-800/70 dark:bg-blue-950/30">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-700 dark:text-blue-300 mb-2">Configuração do programa</p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <label class="flex-1"><span class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Valor por indicação convertida (R$)</span><input type="number" step="0.01" min="0" id="rw-payout" value="${Number(cData.payout_per_conversion || 10).toFixed(2)}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" /></label>
                    <label class="flex-1"><span class="block text-[11px] font-bold text-slate-600 dark:text-slate-300 mb-1">Saque mínimo (R$)</span><input type="number" step="0.01" min="1" id="rw-minimum" value="${Number(cData.minimum_withdrawal || 100).toFixed(2)}" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" /></label>
                    <button type="button" id="rw-save-config" class="rounded-xl bg-blue-600 px-5 py-2.5 text-xs font-black text-white hover:bg-blue-700 active:scale-[0.97]">Salvar</button>
                </div>
            </div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400 mb-2">Solicitações de saque</p>
            <div>${rows || '<p class="text-xs text-slate-400">Nenhuma solicitação de saque ainda.</p>'}</div>`;
        const saveBtn = document.getElementById('rw-save-config');
        if (saveBtn) saveBtn.onclick = () => {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Salvando...';
            fetch('api/referrals.php?action=admin-config', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ payout_per_conversion: String(document.getElementById('rw-payout').value || '0'), minimum_withdrawal: String(document.getElementById('rw-minimum').value || '100') }),
            }).then((r) => r.json()).then((p) => {
                if (p && p.success) {
                    if (typeof showToast === 'function') showToast('Configurações do programa de indicações salvas.', 'success');
                } else {
                    if (typeof showToast === 'function') showToast((p && p.error) || 'Não foi possível salvar.', 'error');
                }
                saveBtn.disabled = false;
                saveBtn.textContent = 'Salvar';
            }).catch(() => { saveBtn.disabled = false; saveBtn.textContent = 'Salvar'; });
        };
        document.querySelectorAll('[data-rw-approve]').forEach((btn) => { btn.onclick = () => rwUpdate(btn.dataset.rwApprove, 'approved'); });
        document.querySelectorAll('[data-rw-reject]').forEach((btn) => { btn.onclick = () => rwUpdate(btn.dataset.rwReject, 'rejected'); });
        document.querySelectorAll('[data-rw-paid]').forEach((btn) => { btn.onclick = () => rwUpdate(btn.dataset.rwPaid, 'paid'); });
    }).catch(() => { adminContent.innerHTML = '<p class="text-xs text-slate-400">Não foi possível carregar a administração.</p>'; });
    function rwUpdate(id, status) {
        fetch('api/referrals.php?action=admin-withdrawal-update', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ id: Number(id), status }),
        }).then((r) => r.json()).then((p) => {
            if (p && p.success) {
                if (typeof showToast === 'function') showToast('Solicitação atualizada.', 'success');
                loadReferralsAdmin();
                loadReferralsData();
            } else {
                if (typeof showToast === 'function') showToast((p && p.error) || 'Não foi possível atualizar.', 'error');
            }
        });
    }
}

function renderRequestsTab(contentArea) {
    if (!contentArea) return;
    contentArea.innerHTML = `
        <div class="max-w-5xl mx-auto py-10">
            <div class="flex items-center justify-between gap-4 mb-8">
                <div>
                    <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600 mb-2">Aprovação manual</p>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Solicitações</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Analise os pedidos pendentes de créditos e planos das empresas.</p>
                </div>
                <button type="button" id="requests-refresh" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold px-4 py-3 rounded-2xl text-xs">Atualizar</button>
            </div>
            <div id="requests-credits-section" class="mb-8"></div>
            <div id="requests-plans-section" class="mb-8"></div>
        </div>`;
    document.getElementById('requests-refresh').onclick = loadRequestsTab;
    loadRequestsTab();
}

function loadRequestsTab() {
    const creditsSection = document.getElementById('requests-credits-section');
    const plansSection = document.getElementById('requests-plans-section');
    if (!creditsSection || !plansSection) return;

    const loading = '<div class="py-8 text-center text-sm font-bold text-slate-400">Carregando solicitações...</div>';
    creditsSection.innerHTML = loading;
    plansSection.innerHTML = loading;

    Promise.all([
        fetch(API_BASE + 'credit-requests.php', { credentials: 'include' }).then(r => r.json()),
        fetch(API_BASE + 'plan-requests.php', { credentials: 'include' }).then(r => r.json())
    ]).then(([creditsData, plansData]) => {
        const credits = creditsData?.success && Array.isArray(creditsData.data?.items) ? creditsData.data.items : [];
        const plans = plansData?.success && Array.isArray(plansData.data?.items) ? plansData.data.items : [];
        renderRequestsSection(creditsSection, 'Solicitações de créditos', credits, 'credit');
        renderRequestsSection(plansSection, 'Solicitações de planos', plans, 'plan');
    }).catch(error => {
        console.error('Falha ao carregar solicitações:', error);
        const errorHtml = '<div class="rounded-2xl border border-red-200 bg-red-50 dark:bg-red-950/30 dark:border-red-900 p-5 text-sm font-bold text-red-700 dark:text-red-300">Não foi possível carregar as solicitações agora. Atualize a página e tente novamente.</div>';
        creditsSection.innerHTML = errorHtml;
        plansSection.innerHTML = '';
    });
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>'"]/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        "'": '&#39;',
        '"': '&quot;'
    }[char]));
}

function renderRequestsSection(sectionEl, title, items, kind) {
    const pending = items.filter(item => item.status === 'pending');
    if (!pending.length) {
        sectionEl.innerHTML = `<div class="rounded-[1.5rem] border border-dashed border-slate-200 dark:border-slate-700 p-6"><h4 class="font-black text-slate-800 dark:text-slate-100">${title}</h4><p class="text-sm text-slate-400 mt-1">Nenhuma solicitação pendente.</p></div>`;
        return;
    }
    const rows = pending.map(item => {
        const company = escapeHtml(item.tenantName || 'Empresa');
        const requester = escapeHtml(item.requestedByName || item.requestedByEmail || '—');
        const date = item.createdAt ? new Date(item.createdAt).toLocaleString('pt-BR') : '';
        const details = kind === 'credit'
            ? `${Number(item.tokensRequested || item.amount || 0).toLocaleString('pt-BR')} créditos`
            : `${escapeHtml(item.planName || 'Plano')} · ${Number(item.planTokenLimit || 0).toLocaleString('pt-BR')} créditos`;
        const approveLabel = kind === 'credit' ? 'Aprovar créditos' : 'Aprovar plano';
        return `<div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 flex flex-wrap items-center justify-between gap-4"><div><h5 class="font-black text-slate-900 dark:text-slate-100">${company}</h5><p class="text-xs font-bold text-slate-500 mt-1">${requester} · ${details}</p><p class="text-[11px] text-slate-400 mt-1">${date}</p></div><div class="flex gap-2"><button class="request-approve bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl text-xs" data-id="${item.id}" data-kind="${kind}">${approveLabel}</button><button class="request-reject bg-red-50 hover:bg-red-100 text-red-700 font-bold px-4 py-2 rounded-xl text-xs" data-id="${item.id}" data-kind="${kind}">Recusar</button></div></div>`;
    }).join('');
    sectionEl.innerHTML = `<h4 class="font-black text-slate-900 dark:text-slate-100 mb-4">${title} <span class="text-sm text-amber-600">(${pending.length} pendente${pending.length > 1 ? 's' : ''})</span></h4><div class="space-y-3">${rows}</div>`;
    sectionEl.querySelectorAll('.request-approve, .request-reject').forEach(button => {
        button.onclick = () => {
            const status = button.classList.contains('request-approve') ? 'approved' : 'rejected';
            if (button.dataset.kind === 'credit') reviewCreditRequest(button.dataset.id, status);
            else reviewPlanRequest(button.dataset.id, status, document.getElementById('content-area'));
            setTimeout(loadRequestsTab, 600);
        };
    });
}

function renderSaasConfigTab(contentArea) {
    contentArea.innerHTML = `
        <div class="max-w-4xl mx-auto space-y-8 pb-20">
            <div>
                <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Configurações do Sistema SaaS</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Configure as chaves globais da plataforma, gateway de pagamento, WhatsApp e servidores de e-mail SMTP.</p>
            </div>

            <div id="saas-config-error" class="hidden p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>
            <div id="saas-config-success" class="hidden p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-700 text-sm font-medium"></div>

            <form id="saas-config-form" class="space-y-8">
                <!-- PAINEL 1: CONFIGURAÇÕES GERAIS -->
                <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm space-y-6">
                    <h4 class="text-lg font-black text-slate-900 dark:text-slate-100 border-b border-slate-100 dark:border-slate-700 pb-3 flex items-center gap-2">⚙️ Configurações Gerais</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome da Empresa SaaS</label>
                            <input id="saas-config-company" type="text" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" placeholder="Ex: Maps Prospector" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Valor do Crédito Avulso (R$)</label>
                            <input id="saas-config-avulso" type="text" required class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" placeholder="Ex: 2.00" />
                        </div>
                    </div>
                </div>

                <!-- PAINEL 2: PAGAMENTOS (ASAAS — exclusivo do super admin) -->
                <div id="asaas-admin-panel" class="bg-[#0F172A] p-8 rounded-[2rem] border border-slate-800 text-white space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-600/10 rounded-full blur-3xl"></div>
                    <h4 class="text-lg font-black text-white border-b border-slate-800 pb-3 flex items-center gap-2">💳 Pagamentos (Asaas)</h4>
                    <p class="text-xs text-slate-400">Painel exclusivo da plataforma. Configure a chave de API, o ambiente e o webhook. Os planos pagos usam assinatura mensal recorrente; o cancelamento interrompe apenas a próxima cobrança, sem estorno automático.</p>
                    <div id="asaas-status" class="hidden"></div>
                    <div id="asaas-credentials" class="hidden rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-4 space-y-2 text-xs">
                        <p class="text-[10px] font-black text-emerald-300 uppercase">Credenciais registradas no servidor</p>
                        <p class="text-emerald-100">Chave de API: <span id="asaas-apikey-masked" class="font-bold text-white"></span></p>
                        <p class="text-emerald-100">Token do webhook: <span id="asaas-webhook-token-masked" class="font-bold text-white"></span></p>
                    </div>
                    <div id="asaas-message" class="hidden text-sm font-medium"></div>
                    <div class="flex flex-wrap items-center gap-3">
                        <button type="button" id="asaas-toggle" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold rounded-xl transition-all">Configurar</button>
                        <button type="button" id="asaas-buy" class="px-5 py-2.5 bg-white/10 hover:bg-white/20 border border-white/20 text-white text-sm font-bold rounded-xl transition-all">Criar link de teste</button>
                        <button type="button" id="asaas-refresh" class="px-5 py-2.5 bg-white/10 hover:bg-white/20 border border-white/20 text-white text-sm font-bold rounded-xl transition-all">Atualizar</button>
                    </div>
                    <div id="asaas-config-form" class="hidden space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">Ambiente</label>
                                <select id="asaas-environment" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-emerald-500 font-semibold text-white text-sm">
                                    <option value="sandbox">Sandbox (teste)</option>
                                    <option value="production">Produção</option>
                                </select>
                            </div>
                            <label class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900/30 px-4 py-3 cursor-pointer">
                                <input id="asaas-active" type="checkbox" class="h-4 w-4 accent-emerald-500" />
                                <span><span class="block text-xs font-black text-white">Ativar gateway Asaas</span><span class="block mt-0.5 text-[10px] text-slate-400">Permite criar novas cobranças e assinaturas.</span></span>
                            </label>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">Chave de API do Asaas (X-ACCESS-TOKEN)</label>
                            <input id="asaas-apikey" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-emerald-500 font-semibold text-white placeholder:text-slate-600" placeholder="Cole a chave de Integrações do painel Asaas" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">Token de segurança do webhook</label>
                            <input id="asaas-webhook-token" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-emerald-500 font-semibold text-white placeholder:text-slate-600" placeholder="Gerado automaticamente se deixado vazio" />
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" id="asaas-save" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold rounded-xl transition-all">Salvar e testar conexão</button>
                            <button type="button" id="asaas-remove" class="px-5 py-2.5 bg-red-500/20 hover:bg-red-500/30 border border-red-500/30 text-red-200 text-sm font-bold rounded-xl transition-all">Remover</button>
                        </div>
                    </div>
                    <div id="asaas-webhook-box" class="hidden rounded-2xl border border-blue-500/30 bg-blue-500/10 p-4 space-y-2">
                        <p class="text-[10px] font-black text-blue-300 uppercase">URL do webhook (cadastre no painel do Asaas: Integrações → Webhooks)</p>
                        <div class="flex items-center gap-2">
                            <code id="asaas-webhook-url" class="flex-1 break-all text-xs font-semibold text-blue-100"></code>
                            <button type="button" id="asaas-copy-webhook" class="shrink-0 px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-black rounded-lg">Copiar</button>
                        </div>
                        <p class="text-[10px] font-bold text-blue-300">Token: <span id="asaas-webhook-token-display" class="text-blue-100">configure a chave de API primeiro</span></p>
                    </div>
                    <div id="asaas-orders" class="hidden pt-2">
                        <h5 class="text-xs font-black text-slate-400 uppercase mb-2">Resumo de pedidos</h5>
                        <div id="asaas-orders-list" class="space-y-2 text-xs text-slate-300"></div>
                    </div>
                    <div id="asaas-subscriptions" class="hidden pt-2">
                        <h5 class="text-xs font-black text-slate-400 uppercase mb-2">Resumo de assinaturas recorrentes</h5>
                        <div id="asaas-subscriptions-list" class="flex flex-wrap gap-2 text-xs text-slate-300"></div>
                    </div>
                </div>

                <!-- PAINEL 3: INTEGRAÇÃO WHATSAPP (API ATENDO) -->
                <div class="bg-[#0F172A] p-8 rounded-[2rem] border border-slate-800 text-white space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/10 rounded-full blur-3xl"></div>
                    <h4 class="text-lg font-black text-white border-b border-slate-800 pb-3 flex items-center gap-2">💬 Disparador de WhatsApp (API Atendo)</h4>
                    <p class="text-xs text-slate-400">Insira a URL e o Token JWT da sua API do Atendo.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">URL da API do WhatsApp</label>
                            <input id="saas-config-wa-url" type="url" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold text-white placeholder:text-slate-600" placeholder="https://off.atendo.log.br/v1/api/external/..." />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">Token de Autenticação da API</label>
                            <input id="saas-config-wa-token" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold text-white placeholder:text-slate-600" placeholder="Insira o Token JWT" />
                        </div>
                    </div>
                </div>

                <!-- PAINEL 4: SERVIDOR DE E-MAIL (SMTP AUTENTICADO) -->
                <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm space-y-6">
                    <h4 class="text-lg font-black text-slate-900 dark:text-slate-100 border-b border-slate-100 dark:border-slate-700 pb-3 flex items-center gap-2">✉️ Servidor de E-mail (SMTP)</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Configure as credenciais SMTP do seu domínio para disparar e-mails de confirmação e alertas.</p>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Servidor SMTP (Host)</label>
                            <input id="saas-config-smtp-host" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" placeholder="mail.atendo.log.br" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Porta SMTP</label>
                            <input id="saas-config-smtp-port" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" placeholder="587" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Usuário SMTP</label>
                            <input id="saas-config-smtp-user" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" placeholder="exemplo@atendo.log.br" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Senha SMTP</label>
                            <input id="saas-config-smtp-pass" type="password" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" placeholder="Senha do e-mail" />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Criptografia</label>
                            <select id="saas-config-smtp-secure" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold">
                                <option value="tls" selected>TLS (Recomendado)</option>
                                <option value="ssl">SSL</option>
                                <option value="none">Nenhuma</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">E-mail do Remetente</label>
                            <input id="saas-config-smtp-from-email" type="email" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" placeholder="noreply@atendo.log.br" />
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome do Remetente</label>
                            <input id="saas-config-smtp-from-name" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" placeholder="Maps Prospector" />
                        </div>
                    </div>
                </div>

                <button type="submit" id="saas-config-submit" class="w-full py-5 rounded-[1.5rem] bg-blue-600 text-white font-black hover:bg-blue-700 transition-all shadow-xl shadow-blue-100 dark:shadow-blue-900/30">Salvar Todas as Configurações</button>
            </form>
        </div>
    `;
    loadSaasConfigForm();
    setupSaasConfigEvents();
}

function loadSaasConfigForm() {
    fetch(API_BASE + 'platform-config.php', { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success || !data.data) return;
            var d = data.data;
            var companyEl = document.getElementById('saas-config-company');
            var avulsoEl = document.getElementById('saas-config-avulso');
            if (companyEl) companyEl.value = d.saasCompanyName || '';
            if (avulsoEl) avulsoEl.value = (d.creditPriceAvulso != null && d.creditPriceAvulso > 0) ? String(d.creditPriceAvulso) : '0';
            var waUrlEl = document.getElementById('saas-config-wa-url');
            var waTokenEl = document.getElementById('saas-config-wa-token');
            if (waUrlEl) waUrlEl.value = d.whatsappApiUrl || '';
            if (waTokenEl) waTokenEl.value = d.whatsappApiToken || '';

            var smtpHostEl = document.getElementById('saas-config-smtp-host');
            var smtpPortEl = document.getElementById('saas-config-smtp-port');
            var smtpUserEl = document.getElementById('saas-config-smtp-user');
            var smtpPassEl = document.getElementById('saas-config-smtp-pass');
            var smtpSecureEl = document.getElementById('saas-config-smtp-secure');
            var smtpFromEmailEl = document.getElementById('saas-config-smtp-from-email');
            var smtpFromNameEl = document.getElementById('saas-config-smtp-from-name');
            if (smtpHostEl) smtpHostEl.value = d.smtpHost || '';
            if (smtpPortEl) smtpPortEl.value = d.smtpPort || '587';
            if (smtpUserEl) smtpUserEl.value = d.smtpUser || '';
            if (smtpPassEl) smtpPassEl.value = d.smtpPass || '';
            if (smtpSecureEl) smtpSecureEl.value = d.smtpSecure || 'tls';
            if (smtpFromEmailEl) smtpFromEmailEl.value = d.smtpFromEmail || '';
            if (smtpFromNameEl) smtpFromNameEl.value = d.smtpFromName || '';
        })
        .catch(function() {});
}

function setupSaasConfigEvents() {
    var form = document.getElementById('saas-config-form');
    if (!form) return;
    form.onsubmit = function(e) {
        e.preventDefault();
        var companyEl = document.getElementById('saas-config-company');
        var avulsoEl = document.getElementById('saas-config-avulso');
        var avulso = avulsoEl && avulsoEl.value ? parseFloat(String(avulsoEl.value).replace(',', '.')) : 0;
        var waUrlEl = document.getElementById('saas-config-wa-url');
        var waTokenEl = document.getElementById('saas-config-wa-token');

        var smtpHostEl = document.getElementById('saas-config-smtp-host');
        var smtpPortEl = document.getElementById('saas-config-smtp-port');
        var smtpUserEl = document.getElementById('saas-config-smtp-user');
        var smtpPassEl = document.getElementById('saas-config-smtp-pass');
        var smtpSecureEl = document.getElementById('saas-config-smtp-secure');
        var smtpFromEmailEl = document.getElementById('saas-config-smtp-from-email');
        var smtpFromNameEl = document.getElementById('saas-config-smtp-from-name');

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
                creditPriceAvulso: avulso,
                whatsappApiUrl: waUrlEl ? waUrlEl.value.trim() : '',
                whatsappApiToken: waTokenEl ? waTokenEl.value.trim() : '',
                smtpHost: smtpHostEl ? smtpHostEl.value.trim() : '',
                smtpPort: smtpPortEl ? smtpPortEl.value.trim() : '587',
                smtpUser: smtpUserEl ? smtpUserEl.value.trim() : '',
                smtpPass: smtpPassEl ? smtpPassEl.value : '',
                smtpSecure: smtpSecureEl ? smtpSecureEl.value : 'tls',
                smtpFromEmail: smtpFromEmailEl ? smtpFromEmailEl.value.trim() : '',
                smtpFromName: smtpFromNameEl ? smtpFromNameEl.value.trim() : ''
            })
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (data.success) {
                var successEl = document.getElementById('saas-config-success');
                if (successEl) { successEl.textContent = data.message || 'Configurações globais salvas!'; successEl.classList.remove('hidden'); }
                loadSidebarCompanyName();
            } else {
                var err = document.getElementById('saas-config-error');
                if (err) { err.textContent = data.error || 'Erro ao salvar.'; err.classList.remove('hidden'); }
            }
        }).catch(function() {
            var err = document.getElementById('saas-config-error');
            if (err) { err.textContent = 'Erro de conexão.'; err.classList.remove('hidden'); }
        }).finally(function() {
            if (btn) { btn.disabled = false; btn.textContent = 'Salvar Todas as Configurações'; }
        });
    };
    setupPaymentsPanel();
}
function setupPaymentsPanel() {
    // Painel Asaas — exclusivo do super admin. O painel é renderizado apenas
    // para o super administrador; qualquer acesso indevido ao endpoint retorna 403.
    var statusEl = document.getElementById('asaas-status');
    var messageEl = document.getElementById('asaas-message');
    var formEl = document.getElementById('asaas-config-form');
    var ordersEl = document.getElementById('asaas-orders');
    var ordersListEl = document.getElementById('asaas-orders-list');
    var subscriptionsEl = document.getElementById('asaas-subscriptions');
    var subscriptionsListEl = document.getElementById('asaas-subscriptions-list');
    var webhookBoxEl = document.getElementById('asaas-webhook-box');
    var toggleBtn = document.getElementById('asaas-toggle');
    var buyBtn = document.getElementById('asaas-buy');
    var refreshBtn = document.getElementById('asaas-refresh');
    var saveBtn = document.getElementById('asaas-save');
    var removeBtn = document.getElementById('asaas-remove');

    function payMessage(success, text) {
        if (!messageEl) return;
        messageEl.textContent = text;
        messageEl.className = 'text-sm font-medium ' + (success ? 'text-emerald-300' : 'text-red-300');
        messageEl.classList.remove('hidden');
    }

    function loadPaymentStatus() {
        fetch(API_BASE + 'asaas.php?action=getConfig', { credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!statusEl) return;
                var cfg = data.success && data.data ? data.data : {};
                var on = !!cfg.api_key_configured;
                var active = !!cfg.active;
                var connectLabel = cfg.asaas_connected === true ? 'conectado' : cfg.asaas_connected === false ? 'sem conexão' : 'não testado';
                statusEl.textContent = 'Asaas: ' + (on ? 'chave configurada' : 'chave não configurada') + ' · ' + (cfg.environment === 'sandbox' ? 'sandbox' : 'produção') + ' · gateway ' + (active ? 'ativo' : 'desativado') + ' · ' + connectLabel + ' · ' + (cfg.orders_total != null ? cfg.orders_total + ' pedido(s)' : '0 pedidos');
                statusEl.className = 'p-3 rounded-xl text-xs font-bold ' + (on && active ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/10 text-amber-200 border border-amber-500/30');
                statusEl.classList.remove('hidden');
                var environmentEl = document.getElementById('asaas-environment');
                var activeEl = document.getElementById('asaas-active');
                if (environmentEl) environmentEl.value = cfg.environment === 'sandbox' ? 'sandbox' : 'production';
                if (activeEl) activeEl.checked = active;
                var urlEl = document.getElementById('asaas-webhook-url');
                var tokEl = document.getElementById('asaas-webhook-token-display');
                if (urlEl) urlEl.textContent = cfg.webhook_url || '';
                if (tokEl) tokEl.textContent = cfg.webhook_token_configured ? 'já definido (não exibido por segurança)' : 'gerado automaticamente ao salvar a chave';
                if (webhookBoxEl && cfg.webhook_url) webhookBoxEl.classList.remove('hidden');
                var credEl = document.getElementById('asaas-credentials');
                var keyMaskedEl = document.getElementById('asaas-apikey-masked');
                var tokMaskedEl = document.getElementById('asaas-webhook-token-masked');
                if (credEl) {
                    var showCredentials = on || !!cfg.api_key_masked;
                    credEl.classList.toggle('hidden', !showCredentials);
                    if (showCredentials) {
                        if (keyMaskedEl) keyMaskedEl.textContent = cfg.api_key_masked || 'não exibida por segurança';
                        if (tokMaskedEl) tokMaskedEl.textContent = cfg.webhook_token_masked || 'não exibido por segurança';
                    }
                }
                loadPaymentOrders(cfg.orders_by_status || {});
                renderSubscriptionSummary(cfg.subscriptions_by_status || {});
            })
            .catch(function() {});
    }

    function loadPaymentOrders(byStatus) {
        if (!ordersEl || !ordersListEl) return;
        fetch(API_BASE + 'asaas.php?action=orders', { credentials: 'include' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var orders = data.success && data.data && data.data.orders ? data.data.orders : [];
                var summary = '';
                if (byStatus && Object.keys(byStatus).length) {
                    var summaryParts = [];
                    var totals = { pending: 0, paid: 0, failed: 0 };
                    orders.forEach(function(o) { totals[o.status] = (totals[o.status] || 0) + 1; });
                    Object.keys(byStatus).forEach(function(s) {
                        var label = s === 'paid' ? 'Confirmados' : s === 'pending' ? 'Pendentes' : s === 'failed' ? 'Falhados' : s;
                        summaryParts.push(label + ': R$ ' + Number(byStatus[s] || 0).toFixed(2).replace('.', ','));
                    });
                    summary = '<div class="flex flex-wrap gap-2 mb-3">' + summaryParts.map(function(p) { return '<span class="bg-white/5 rounded-lg px-2.5 py-1 text-[10px] font-bold text-slate-300">' + p + '</span>'; }).join('') + '</div>';
                }
                if (!orders.length) {
                    ordersListEl.innerHTML = summary + '<div class="text-slate-500">Nenhum pedido registrado ainda.</div>';
                    ordersEl.classList.remove('hidden');
                    return;
                }
                var statusLabel = { paid: ['Pagamento confirmado', 'bg-emerald-500/20 text-emerald-300'], pending: ['Aguardando pagamento', 'bg-amber-500/20 text-amber-300'], failed: ['Falhou', 'bg-rose-500/20 text-rose-300'], canceled: ['Cancelado', 'bg-slate-500/20 text-slate-300'], unknown: ['Status desconhecido', 'bg-slate-500/20 text-slate-300'] };
                ordersListEl.innerHTML = summary + orders.map(function(o) {
                    var label = statusLabel[o.status] || statusLabel.unknown;
                    var amount = 'R$ ' + (o.amount_brl || 0).toFixed(2).replace('.', ',');
                    var date = o.created_at ? new Date(String(o.created_at).replace(' ', 'T')).toLocaleDateString('pt-BR') : '';
                    return '<div class="flex items-center justify-between gap-3 bg-white/5 rounded-xl px-3 py-2"><div class="min-w-0"><div class="font-semibold truncate">' + (o.description || 'Pedido') + '</div><div class="text-slate-400">' + amount + ' · ' + (o.tenant_name || '') + (date ? ' · ' + date : '') + '</div></div><span class="shrink-0 text-[10px] font-bold px-2 py-1 rounded-lg ' + label[1] + '">' + label[0] + '</span></div>';
                }).join('');
                ordersEl.classList.remove('hidden');
            })
            .catch(function() {});
    }

    function renderSubscriptionSummary(byStatus) {
        if (!subscriptionsEl || !subscriptionsListEl) return;
        var labels = { pending: 'Aguardando pagamento', active: 'Ativas', overdue: 'Inadimplentes', cancelled: 'Canceladas', expired: 'Expiradas' };
        var entries = Object.keys(byStatus || {});
        if (!entries.length) {
            subscriptionsListEl.innerHTML = '<span class="text-slate-500">Nenhuma assinatura registrada ainda.</span>';
        } else {
            subscriptionsListEl.innerHTML = entries.map(function(status) {
                return '<span class="rounded-lg bg-white/5 px-2.5 py-1 text-[10px] font-bold text-slate-300">' + dashboardMetricEscape(labels[status] || status) + ': ' + Number(byStatus[status] || 0) + '</span>';
            }).join('');
        }
        subscriptionsEl.classList.remove('hidden');
    }

    if (toggleBtn && formEl) {
        toggleBtn.onclick = function() {
            var visible = !formEl.classList.contains('hidden');
            formEl.classList.toggle('hidden', visible);
            toggleBtn.textContent = visible ? 'Configurar' : 'Ocultar configuração';
            if (!visible) loadPaymentStatus();
        };
    }

    if (refreshBtn) {
        refreshBtn.onclick = loadPaymentStatus;
    }

    if (saveBtn) {
        saveBtn.onclick = function() {
            var keyEl = document.getElementById('asaas-apikey');
            var tokenEl = document.getElementById('asaas-webhook-token');
            var environmentEl = document.getElementById('asaas-environment');
            var activeEl = document.getElementById('asaas-active');
            var apiKey = keyEl ? keyEl.value.trim() : '';
            var token = tokenEl ? tokenEl.value.trim() : '';
            saveBtn.disabled = true;
            saveBtn.textContent = 'Testando conexão...';
            fetch(API_BASE + 'asaas.php?action=saveConfig', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify({ api_key: apiKey, webhook_token: token, environment: environmentEl ? environmentEl.value : 'production', active: !!(activeEl && activeEl.checked) }) })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    payMessage(!!data.success, data.message || (data.success ? 'Salvo.' : 'Erro ao salvar.'));
                    if (data.success) {
                        formEl.classList.add('hidden');
                        toggleBtn.textContent = 'Configurar';
                        var keyDisplay = document.getElementById('asaas-apikey');
                        if (keyDisplay) keyDisplay.value = '';
                        var tokenDisplay = document.getElementById('asaas-webhook-token');
                        if (tokenDisplay) tokenDisplay.value = '';
                    }
                    loadPaymentStatus();
                })
                .catch(function() { payMessage(false, 'Erro de conexão.'); })
                .finally(function() { saveBtn.disabled = false; saveBtn.textContent = 'Salvar e testar conexão'; });
        };
    }

    if (removeBtn) {
        removeBtn.onclick = function() {
            if (!confirm('Remover a configuração de pagamento Asaas? Os pedidos existentes continuarão visíveis.')) return;
            fetch(API_BASE + 'asaas.php?action=saveConfig', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify({ remove: true }) })
                .then(function(r) { return r.json(); })
                .then(function(data) { payMessage(!!data.success, data.message || (data.success ? 'Removido.' : 'Erro ao remover.')); loadPaymentStatus(); })
                .catch(function() { payMessage(false, 'Erro de conexão.'); });
        };
    }

    var copyWebhookBtn = document.getElementById('asaas-copy-webhook');
    if (copyWebhookBtn) {
        copyWebhookBtn.onclick = function() {
            var urlEl = document.getElementById('asaas-webhook-url');
            var url = urlEl ? urlEl.textContent : '';
            if (!url) return;
            navigator.clipboard.writeText(url).then(function() {
                copyWebhookBtn.textContent = 'Copiado!';
                setTimeout(function() { copyWebhookBtn.textContent = 'Copiar'; }, 2000);
            }).catch(function() {});
        };
    }

    if (buyBtn) {
        buyBtn.onclick = function() {
            var overlay = document.createElement('div');
            overlay.className = 'fixed inset-0 z-[120] bg-black/70 flex items-center justify-center p-4';
            overlay.innerHTML = '<div class="bg-white dark:bg-slate-800 rounded-3xl p-8 w-full max-w-md space-y-5">' +
                '<h4 class="text-lg font-black text-slate-900 dark:text-slate-100">Recarga de créditos extras</h4>' +
                '<p class="text-xs text-slate-500 dark:text-slate-400">Com o Asaas ativo, você recebe um link de pagamento. Se o Asaas estiver indisponível, a solicitação seguirá para aprovação manual do administrador.</p>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Quantidade de créditos</label>' +
                '<input id="buy-qty" type="number" min="100" value="100" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 font-semibold outline-none focus:border-emerald-500" />' +
                '</div>' +
                '<div id="buy-msg" class="hidden text-sm font-medium"></div>' +
                '<div class="flex justify-end gap-3">' +
                '<button type="button" id="buy-cancel" class="px-5 py-2.5 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-bold rounded-xl">Cancelar</button>' +
                '<button type="button" id="buy-go" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-bold rounded-xl">Gerar pagamento</button>' +
                '</div>' +
                '</div>';
            document.body.appendChild(overlay);
            var qtyEl = document.getElementById('buy-qty');
            var msgEl = document.getElementById('buy-msg');
            var close = function() { overlay.remove(); };
            document.getElementById('buy-cancel').onclick = close;
            overlay.onclick = function(e) { if (e.target === overlay) close(); };
            document.getElementById('buy-go').onclick = function() {
                var qty = parseInt(qtyEl.value || '0', 10);
                if (!qty || qty < 100) { qty = 100; qtyEl.value = 100; }
                var amountCents = qty * 10; // R$ 0,10 por crédito
                msgEl.classList.add('hidden');
                fetch(API_BASE + 'asaas.php?action=create', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify({ amount_cents: amountCents, quantity: qty, description: 'Recarga de ' + qty + ' créditos extras' }) })
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (data.success && data.data && data.data.mode === 'asaas' && data.data.payment_url) {
                            msgEl.textContent = 'Cobrança criada no Asaas. Ao concluir, aguarde a confirmação automática. ';
                            var paymentLink = document.createElement('a');
                            paymentLink.href = data.data.payment_url;
                            paymentLink.target = '_blank';
                            paymentLink.rel = 'noopener noreferrer';
                            paymentLink.textContent = 'Abrir pagamento';
                            paymentLink.className = 'font-black underline';
                            msgEl.appendChild(paymentLink);
                            msgEl.className = 'text-sm font-medium text-emerald-600 dark:text-emerald-400';
                            msgEl.classList.remove('hidden');
                            window.open(data.data.payment_url, '_blank', 'noopener');
                        } else if (data.success && data.data && data.data.mode === 'manual') {
                            msgEl.textContent = data.message || 'Solicitação enviada para aprovação manual do administrador.';
                            msgEl.className = 'text-sm font-medium text-amber-600 dark:text-amber-400';
                            msgEl.classList.remove('hidden');
                        } else {
                            msgEl.textContent = data.error || 'Não foi possível iniciar a solicitação. Tente novamente.';
                            msgEl.className = 'text-sm font-medium text-rose-600 dark:text-rose-400';
                            msgEl.classList.remove('hidden');
                        }
                    })
                    .catch(function() { msgEl.textContent = 'Erro de conexão.'; msgEl.className = 'text-sm font-medium text-rose-600 dark:text-rose-400'; msgEl.classList.remove('hidden'); });
            };
        };
    }

    loadPaymentStatus();
}

function renderVersionsTab(contentArea) {
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
    contentArea.innerHTML = '<div class="max-w-3xl mx-auto py-10">' +
        '<div class="flex items-center justify-between flex-wrap gap-4 mb-8">' +
        '<h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Versões e Atualizações</h3>' +
        '<div class="flex items-center gap-2">' +
        '<span id="versions-current-badge" class="bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-xs font-black px-3 py-1.5 rounded-xl">Versão atual: ...</span>' +
        (isSuperAdmin ? '<button type="button" id="versions-edit-btn" class="bg-slate-900 px-4 py-2 text-xs font-black text-white shadow-sm transition hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-200 rounded-xl">Editar versões e atualizações</button>' : '') +
        '</div></div>' +
        '<div id="versions-error" class="hidden text-sm font-semibold text-rose-300 mb-4"></div>' +
        '<div id="versions-spinner" class="flex items-center justify-center py-16"><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block"></span></div>' +
        '<div id="versions-list" class="space-y-6 hidden"></div>' +
        '<div id="versions-editor" class="hidden space-y-6"></div>' +
        '</div>';
    var badge = document.getElementById('versions-current-badge');
    var errorEl = document.getElementById('versions-error');
    var spinner = document.getElementById('versions-spinner');
    var list = document.getElementById('versions-list');
    fetch(API_BASE + 'app-versions.php?action=notes', { credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            spinner.classList.add('hidden');
            if (!data.success || !data.data) {
                errorEl.textContent = 'Não foi possível carregar o histórico de atualizações. Tente novamente.';
                errorEl.classList.remove('hidden');
                return;
            }
            badge.textContent = 'Versão atual: ' + (data.data.current_version || data.data.version || '—');
            var notes = data.data.notes || [];
            if (!notes.length) {
                list.innerHTML = '<div class="text-slate-500 text-sm">Nenhuma atualização registrada ainda.</div>';
                list.classList.remove('hidden');
                return;
            }
            list.innerHTML = notes.map(function(n) {
                var date = n.date ? new Date(String(n.date).replace(' ', 'T')).toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' }) : '';
                var items = Array.isArray(n.items) ? n.items : [];
                return '<div class="bg-white/5 border border-slate-200 dark:border-slate-700 rounded-3xl p-6">' +
                    '<div class="flex items-center justify-between flex-wrap gap-2 mb-1">' +
                    '<div class="text-sm font-black text-slate-900 dark:text-slate-100">' + (n.title || 'Atualização ' + n.version) + '</div>' +
                    '<span class="text-[10px] font-bold px-2 py-1 rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-300">' + (n.version || '') + '</span>' +
                    '</div>' +
                    (date ? '<div class="text-xs text-slate-500 dark:text-slate-400 mb-3">' + date + '</div>' : '') +
                    '<ul class="space-y-1.5">' + items.map(function(it) { return '<li class="flex gap-2 text-sm text-slate-600 dark:text-slate-300"><svg class="w-4 h-4 mt-0.5 shrink-0 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>' + it + '</li>'; }).join('') +
                    '</ul></div>';
            }).join('');
            list.classList.remove('hidden');
            var editBtn = document.getElementById('versions-edit-btn');
            if (editBtn) {
                editBtn.addEventListener('click', function () {
                    openVersionsEditor(notes, data.data.token || '');
                });
            }
        })
        .catch(function() {
            spinner.classList.add('hidden');
            errorEl.textContent = 'Erro de conexão ao carregar as atualizações.';
            errorEl.classList.remove('hidden');
        });
}

function escapeVersionsHtml(value) {
    return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function openVersionsEditor(notes, token) {
    var listEl = document.getElementById('versions-list');
    var editorEl = document.getElementById('versions-editor');
    if (listEl) listEl.classList.add('hidden');
    if (!editorEl) return;
    editorEl.classList.remove('hidden');

    var state = { notes: JSON.parse(JSON.stringify(notes || [])), token: token };

    function itemFieldHtml(noteIndex, itemIndex, item) {
        return '<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-4 space-y-3">' +
            '<div class="flex items-center justify-between gap-2"><span class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Item ' + (itemIndex + 1) + '</span>' +
            '<button type="button" class="versions-remove-item text-xs font-black text-rose-600 hover:text-rose-700 dark:text-rose-400" data-note="' + noteIndex + '" data-item="' + itemIndex + '">Remover</button></div>' +
            '<input type="text" maxlength="300" class="versions-item-title w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" placeholder="Título curto do item" value="' + escapeVersionsHtml(item.title || item || '') + '">' +
            '<textarea maxlength="2000" rows="3" class="versions-item-desc w-full rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm leading-relaxed text-slate-900 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" placeholder="Descrição em linguagem simples (sem nomes de serviços ou configurações internas)">' + escapeVersionsHtml(typeof item === 'object' ? (item.description || '') : '') + '</textarea></div>';
    }

    function render() {
        if (state.notes.length === 0) state.notes = [{ version: 'nova-versao', date: new Date().toISOString().slice(0, 10), title: '', items: [] }];
        var html = '<div class="rounded-[2rem] border border-blue-100 dark:border-blue-900/50 bg-blue-50/50 dark:bg-blue-950/20 p-5">' +
            '<p class="text-xs font-black uppercase tracking-wider text-blue-700 dark:text-blue-300">Editar versões e atualizações</p>' +
            '<p class="mt-1 text-xs leading-relaxed text-blue-900/80 dark:text-blue-100/80">Os changelogs abaixo já estão preenchidos com o que está publicado para os usuários. Edite os textos em linguagem simples — evite nomes de serviços, fornecedores ou configurações internas. Clique em "Salvar alterações" para publicar.</p></div>';
        html += '<div id="versions-notes-list">';
        state.notes.forEach(function(note, noteIndex) {
            var title = note.title || '';
            var date = note.date || new Date().toISOString().slice(0, 10);
            html += '<div class="rounded-[2rem] border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 sm:p-6 space-y-4">' +
                '<div class="flex items-center justify-between gap-2 flex-wrap">' +
                '<div class="flex items-center gap-2"><span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-black bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-300">Versão ' + escapeVersionsHtml(note.version || '') + '</span>' +
                '<button type="button" class="versions-remove-note text-xs font-black text-rose-600 hover:text-rose-700 dark:text-rose-400" data-note="' + noteIndex + '">Remover versão</button></div></div>' +
                '<div class="grid gap-4 sm:grid-cols-2">' +
                '<label class="block"><span class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Título da atualização</span>' +
                '<input type="text" maxlength="200" class="versions-note-title mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" placeholder="Ex.: Novidade que melhora sua rotina" value="' + escapeVersionsHtml(title) + '"></label>' +
                '<label class="block"><span class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Data</span>' +
                '<input type="date" class="versions-note-date mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" value="' + escapeVersionsHtml(date) + '"></label></div>' +
                '<div class="space-y-3" data-note-items="' + noteIndex + '">' + (note.items || []).map(function(item, itemIndex) { return itemFieldHtml(noteIndex, itemIndex, item); }).join('') + '</div>' +
                '<button type="button" class="versions-add-item rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 px-4 py-2.5 text-xs font-black text-slate-700 dark:text-slate-200" data-note="' + noteIndex + '">+ Adicionar item</button></div>';
        });
        html += '</div>';
        html += '<div class="flex flex-wrap gap-3">' +
            '<button type="button" id="versions-save-btn" class="rounded-xl bg-blue-600 hover:bg-blue-700 px-6 py-3 text-xs font-black text-white flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>Salvar alterações</button>' +
            '<button type="button" id="versions-cancel-btn" class="rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 px-6 py-3 text-xs font-black text-slate-700 dark:text-slate-200">Cancelar</button>' +
            '</div>' +
            '<div id="versions-save-msg" class="hidden text-sm font-medium"></div>';
        editorEl.innerHTML = html;
        bindEditorEvents();
    }

    function collectNotes() {
        var editorNoteEls = editorEl.querySelectorAll('[data-note-items]');
        var collected = [];
        state.notes.forEach(function(note, noteIndex) {
            var block = editorEl.querySelectorAll('[data-note-items="' + noteIndex + '"]')[0];
            var dateInput = editorEl.querySelectorAll('.versions-note-date')[noteIndex];
            var titleEl = editorEl.querySelectorAll('.versions-note-title')[noteIndex];
            var itemBlocks = block ? block.querySelectorAll(':scope > div') : [];
            var items = [];
            itemBlocks.forEach(function(blockDiv) {
                var titleInputItem = blockDiv.querySelector('.versions-item-title');
                var descInputItem = blockDiv.querySelector('.versions-item-desc');
                if (!titleInputItem) return;
                var title = String(titleInputItem.value || '').trim();
                var desc = String(descInputItem ? descInputItem.value : '').trim();
                if (title === '' && desc === '') return;
                items.push({ title: title, description: desc });
            });
            var version = String(state.notes[noteIndex].version || '').trim();
            if (version === '') version = 'nova-versao';
            collected.push({
                version: version,
                date: dateInput ? String(dateInput.value || '').trim() : String(state.notes[noteIndex].date || ''),
                title: titleEl ? String(titleEl.value || '').trim() : '',
                items: items
            });
        });
        return collected;
    }

    function bindEditorEvents() {
        editorEl.querySelectorAll('.versions-add-item').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var noteIndex = btn.dataset.note;
                state.notes[noteIndex].items.push({ title: '', description: '' });
                render();
            });
        });
        editorEl.querySelectorAll('.versions-remove-item').forEach(function(btn) {
            btn.addEventListener('click', function() {
                state.notes[btn.dataset.note].items.splice(parseInt(btn.dataset.item, 10), 1);
                render();
            });
        });
        editorEl.querySelectorAll('.versions-remove-note').forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (state.notes.length <= 1) {
                    var msg = document.getElementById('versions-save-msg');
                    if (msg) { msg.textContent = 'É preciso manter ao menos uma versão no changelog.'; msg.className = 'text-sm font-medium text-amber-600 dark:text-amber-400'; msg.classList.remove('hidden'); }
                    return;
                }
                state.notes.splice(parseInt(btn.dataset.note, 10), 1);
                render();
            });
        });
        var saveBtn = document.getElementById('versions-save-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                saveBtn.disabled = true;
                saveBtn.textContent = 'Salvando...';
                var msg = document.getElementById('versions-save-msg');
                fetch(API_BASE + 'app-versions.php?action=save', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify({ token: state.token, notes: collectNotes() })
                }).then(function(r) { return r.json(); })
                .then(function(data) {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Salvar alterações';
                    if (data && data.success) {
                        if (msg) { msg.textContent = 'Versões e atualizações publicadas para os usuários.'; msg.className = 'text-sm font-medium text-emerald-600 dark:text-emerald-400'; msg.classList.remove('hidden'); }
                        setTimeout(function() { closeVersionsEditor(); }, 1200);
                    } else if (msg) {
                        msg.textContent = data && data.error ? data.error : 'Não foi possível salvar as alterações.';
                        msg.className = 'text-sm font-medium text-rose-600 dark:text-rose-400';
                        msg.classList.remove('hidden');
                    }
                })
                .catch(function() {
                    saveBtn.disabled = false;
                    saveBtn.textContent = 'Salvar alterações';
                    if (msg) { msg.textContent = 'Erro de conexão ao salvar.'; msg.className = 'text-sm font-medium text-rose-600 dark:text-rose-400'; msg.classList.remove('hidden'); }
                });
            });
        }
        var cancelBtn = document.getElementById('versions-cancel-btn');
        if (cancelBtn) cancelBtn.addEventListener('click', closeVersionsEditor);
    }

    function closeVersionsEditor() {
        editorEl.classList.add('hidden');
        editorEl.innerHTML = '';
        if (listEl) listEl.classList.remove('hidden');
    }

    render();
}

function canCurrentUserManagePlanCatalog() {
    var profile = String(AppState.user && AppState.user.profile || '').toLowerCase();
    var tenantId = Number(AppState.user && (AppState.user.tenant_id || AppState.user.tenantId || (AppState.tenant && AppState.tenant.id)) || 0);
    return profile === 'super_admin' || (profile === 'admin' && tenantId === 1);
}

function loadPlansTab(contentArea) {
    var canManagePlanCatalog = canCurrentUserManagePlanCatalog();
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto py-10">' +
        '<div id="plans-requests-section" class="mb-10 hidden"></div>' +
        '<div id="crm-addon-requests-section" class="mb-10 hidden"></div>' +
        '<div class="flex justify-between items-center mb-10 flex-wrap gap-4"><div><h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Planos e limite de tokens <span id="plans-total" class="text-slate-500 dark:text-slate-400 font-normal text-lg"></span></h3><p class="mt-2 max-w-2xl text-xs text-slate-500 dark:text-slate-400">Os planos atuais continuam separados dos novos planos modulares sugeridos. Ofertas sugeridas ficam em rascunho e não aparecem para contratação até serem aprovadas.</p></div><div class="flex items-center gap-3"><button type="button" id="plans-refresh-btn" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2">Atualizar lista</button><button type="button" id="plans-create-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-2xl text-xs flex items-center gap-2">Criar plano</button><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block" id="plans-spinner"></span></div></div>' +
        '<div id="plans-list"></div></div>';

    var refreshBtn = document.getElementById('plans-refresh-btn');
    if (refreshBtn) {
        refreshBtn.onclick = function() {
            fetchPlansList(); // 💡 Chama a função de busca sem reiniciar a aba
        };
    }
    var createBtn = document.getElementById('plans-create-btn');
    if (createBtn) {
        createBtn.onclick = function() {
            showPlansCreateModal(contentArea);
        };
    }

    fetchPlansList(); // 💡 Executa a busca uma única vez
}

function fetchPlansList() {
    var listEl = document.getElementById('plans-list');
    var spinnerEl = document.getElementById('plans-spinner');
    var requestsSection = document.getElementById('plans-requests-section');
    var crmAddonRequestsSection = document.getElementById('crm-addon-requests-section');

    Promise.all([
        fetch(API_BASE + 'plan-requests.php', { credentials: 'include' }).then(function(r) { return r.json(); }),
        fetch(API_BASE + 'plans.php', { method: 'GET', credentials: 'include', cache: 'no-store' }).then(function(r) {
            if (spinnerEl) spinnerEl.remove();
            return r.json().catch(function() { return { success: false, error: 'Resposta inválida do servidor.' }; });
        }),
        fetch(API_BASE + 'crm-addon.php?action=requests', { credentials: 'include', cache: 'no-store' }).then(function(r) { return r.json().catch(function() { return { success: false }; }); })
    ]).then(function(results) {
        var requestsData = results[0];
        var data = results[1];
        var crmAddonRequestsData = results[2];
        var planRequests = (requestsData.success && requestsData.data && requestsData.data.items) ? requestsData.data.items : [];
        var pendingRequests = planRequests.filter(function(r) { return r.status === 'pending'; });

        if (crmAddonRequestsSection && crmAddonRequestsData && crmAddonRequestsData.success && Array.isArray(crmAddonRequestsData.data && crmAddonRequestsData.data.items)) {
            var crmRequests = crmAddonRequestsData.data.items.filter(function(r) { return r.status === 'pending'; });
            if (crmRequests.length) {
                crmAddonRequestsSection.classList.remove('hidden');
                var crmHtml = '<h4 class="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">Solicitações de CRM adicional (pendentes)</h4><div class="space-y-4">';
                crmRequests.forEach(function(r) {
                    var crmDate = r.createdAt ? new Date(r.createdAt).toLocaleString('pt-BR') : '';
                    crmHtml += '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-amber-200 dark:border-amber-900 flex flex-wrap items-center justify-between gap-4"><div><h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg">' + dashboardMetricEscape(r.tenantName || 'Empresa') + '</h4><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + dashboardMetricEscape(r.requestedByName || r.requestedByEmail || '') + ' · ' + dashboardMetricEscape(r.planName || 'CRM adicional') + ' · R$ ' + Number(r.planPrice || 0).toFixed(2).replace('.', ',') + '/mês · ' + Number(r.includedUsers || 3) + ' usuários</p><p class="text-xs text-amber-600 font-bold mt-1">' + dashboardMetricEscape(crmDate) + ' · Pendente</p></div><div class="flex items-center gap-2"><button type="button" class="btn-crm-addon-approve bg-emerald-100 text-emerald-700 hover:bg-emerald-200 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + r.id + '">Ativar CRM</button><button type="button" class="btn-crm-addon-reject bg-red-100 text-red-700 hover:bg-red-200 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + r.id + '">Recusar</button></div></div>';
                });
                crmAddonRequestsSection.innerHTML = crmHtml + '</div>';
                crmAddonRequestsSection.querySelectorAll('.btn-crm-addon-approve, .btn-crm-addon-reject').forEach(function(btn) {
                    btn.onclick = function() {
                        var approve = btn.classList.contains('btn-crm-addon-approve');
                        if (!approve && !window.confirm('Recusar esta solicitação de CRM adicional?')) return;
                        btn.disabled = true; btn.textContent = approve ? 'Ativando...' : 'Recusando...';
                        fetch(API_BASE + 'crm-addon.php', { method: 'PUT', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify({ id: parseInt(btn.getAttribute('data-id'), 10), status: approve ? 'approved' : 'rejected' }) }).then(function(r) { return r.json(); }).then(function(result) { if (!result.success) throw new Error(result.error || 'Não foi possível processar a solicitação.'); loadPlansTab(contentArea); }).catch(function(error) { alert(error.message); btn.disabled = false; btn.textContent = approve ? 'Ativar CRM' : 'Recusar'; });
                    };
                });
            } else crmAddonRequestsSection.classList.add('hidden');
        }

        if (requestsSection && pendingRequests.length > 0) {
            requestsSection.classList.remove('hidden');
            var rHtml = '<h4 class="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">Solicitações de plano (pendentes)</h4><div class="space-y-4">';
            pendingRequests.forEach(function(r) {
                var dateStr = r.createdAt ? new Date(r.createdAt).toLocaleString('pt-BR') : '';
                var planInfo = (r.planName || '—') + ' (' + (r.planTokenLimit ? r.planTokenLimit.toLocaleString('pt-BR') : '') + ' tokens · R$ ' + (r.planPrice != null ? Number(r.planPrice).toFixed(2).replace('.', ',') : '0,00') + '/mês)';
                rHtml += '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 flex flex-wrap items-center justify-between gap-4">';
                rHtml += '<div class="flex items-center gap-6"><div class="w-12 h-12 bg-slate-100 dark:bg-slate-700 rounded-xl flex items-center justify-center text-xl font-black text-slate-600 dark:text-slate-400">' + (r.tenantName ? r.tenantName.charAt(0).toUpperCase() : 'E') + '</div>';
                rHtml += '<div><h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg">' + (r.tenantName || 'Empresa') + '</h4><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + (r.requestedByName || r.requestedByEmail || '—') + ' · Plano: ' + planInfo + '</p><p class="text-xs text-amber-600 font-bold mt-1">' + dateStr + ' · Pendente</p></div></div>';
                rHtml += '<div class="flex items-center gap-2"><button type="button" class="btn-plan-request-approve bg-emerald-100 text-emerald-700 hover:bg-emerald-200 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + r.id + '">Confirmar</button><button type="button" class="btn-plan-request-reject bg-red-100 text-red-700 hover:bg-red-200 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + r.id + '">Recusar</button></div></div>';
            });
            rHtml += '</div>';
            requestsSection.innerHTML = rHtml;
        } else {
            if (requestsSection) requestsSection.classList.add('hidden');
        }

        if (!listEl) return;
        if (!data.success) {
            listEl.innerHTML = '<div class="py-8 p-5 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium">' + (data.error || 'Erro ao carregar planos.') + '</div>';
            return;
        }
        var items = (data.data && data.data.items) ? data.data.items : (Array.isArray(data.data) ? data.data : []);
        var total = (data.data && typeof data.data.total === 'number') ? data.data.total : items.length;
        var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
        var canManagePlanCatalog = canCurrentUserManagePlanCatalog();

        if (!Array.isArray(items) || items.length === 0) {
            listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhum plano cadastrado</p></div>';
            var totalEl = document.getElementById('plans-total');
            if (totalEl) totalEl.textContent = '0 plano(s)';
            return;
        }

        items = items.slice().sort(function(a, b) {
            var groupOrder = { current: 0, suggested: 1, crm_addon: 2 };
            var groupA = Object.prototype.hasOwnProperty.call(groupOrder, a.catalogGroup) ? groupOrder[a.catalogGroup] : 0;
            var groupB = Object.prototype.hasOwnProperty.call(groupOrder, b.catalogGroup) ? groupOrder[b.catalogGroup] : 0;
            return groupA - groupB || Number(a.tokenLimit || 0) - Number(b.tokenLimit || 0) || String(a.name || '').localeCompare(String(b.name || ''), 'pt-BR');
        });
        var html = '<div class="space-y-4">';
        var renderedGroup = '';
        items.forEach(function(p) {
            var catalogGroup = ['current', 'suggested', 'crm_addon'].includes(p.catalogGroup) ? p.catalogGroup : 'current';
            if (catalogGroup !== renderedGroup) {
                renderedGroup = catalogGroup;
                html += '<div class="pt-3"><h4 class="text-sm font-black uppercase tracking-wider ' + (catalogGroup !== 'current' ? 'text-amber-700 dark:text-amber-300' : 'text-slate-700 dark:text-slate-200') + '">' + (catalogGroup === 'suggested' ? 'Novos planos modulares sugeridos' : (catalogGroup === 'crm_addon' ? 'CRM adicional — rascunhos' : 'Planos atuais')) + '</h4><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">' + (catalogGroup === 'suggested' ? 'Rascunhos com valores e créditos sugeridos. Não contratáveis até aprovação.' : (catalogGroup === 'crm_addon' ? 'Licenças adicionais de CRM; não substituem o plano de prospecção.' : 'Catálogo atualmente utilizado pelas empresas.')) + '</p></div>';
            }
            var tokenText = catalogGroup === 'crm_addon' ? 'Não consome créditos' : ((p.tokenLimit === 0 || p.tokenLimit === '0') ? 'Ilimitado' : (Number(p.tokenLimit || 0).toLocaleString('pt-BR') + ' créditos'));
            var priceMonthly = parseFloat(p.priceMonthly || 0);
            var priceText = priceMonthly > 0 ? (' · R$ ' + priceMonthly.toFixed(2).replace('.', ',') + '/mês') : ' · Grátis';
            var statusLabel = catalogGroup === 'suggested' ? 'Rascunho / em aprovação' : (catalogGroup === 'crm_addon' && p.status !== 'active' ? 'Rascunho CRM / não contratável' : (p.status === 'active' ? 'Ativo' : 'Inativo / em aprovação'));
            var statusClass = catalogGroup !== 'current' ? 'bg-amber-100 text-amber-800' : (p.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400');
            var moduleMap = { maps: 'Maps', linkedin: 'LinkedIn', cnpj: 'CNPJ', crm: 'CRM', export: 'Exportação' };
            var moduleKeys = Array.isArray(p.modules) ? p.modules : [];
            var modulesText = Array.isArray(p.moduleLabels) && p.moduleLabels.length ? p.moduleLabels.join(' · ') : moduleKeys.map(function (key) { return moduleMap[key] || key; }).join(' · ');
            if (!modulesText) modulesText = 'Nenhum módulo configurado';
            var includedUsers = Math.max(1, Number(p.includedUsers || 1));
            var bonusText = catalogGroup === 'crm_addon' ? 0 : Number(p.contractBonusCredits || (p.priceMonthly > 0 ? 50 : 0));
            var planId = String(p.id);
            var planName = (p.name || '').replace(/"/g, '&quot;');

            html += '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 flex items-center justify-between flex-wrap gap-4">';
            html += '<div class="flex items-center gap-6">';
            html += '<div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-lg font-black text-blue-600">' + ((p.tokenLimit === 0 || p.tokenLimit === '0') ? '∞' : p.tokenLimit) + '</div>';
            html += '<div><h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg">' + dashboardMetricEscape(p.name || '') + '</h4>';
            html += '<p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + dashboardMetricEscape(p.slug || '') + ' · ' + tokenText + priceText + ' · <span class="' + statusClass + ' px-2 py-0.5 rounded-full text-xs font-bold">' + statusLabel + '</span></p>';
            html += '<p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400"><strong>Módulos:</strong> ' + dashboardMetricEscape(modulesText) + ' · <strong>Usuários incluídos:</strong> ' + includedUsers + (bonusText > 0 ? ' · <strong>Bônus:</strong> +' + bonusText + ' créditos' : '') + '</p></div></div>';
            
            html += '<div class="flex items-center gap-2">';
            if (catalogGroup !== 'current' && p.status !== 'active') {
                html += '<span class="text-amber-700 dark:text-amber-300 text-xs font-bold px-3 py-2 rounded-xl bg-amber-50 dark:bg-amber-950/30">Não contratável enquanto rascunho</span>';
            } else if (!canManagePlanCatalog && p.status === 'active' && priceMonthly > 0) {
                html += '<button type="button" class="btn-request-plan bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-2 rounded-xl text-xs" data-plan-id="' + planId + '" data-price="' + priceMonthly + '">Solicitar plano</button>';
            }
            if (canManagePlanCatalog) {
                html += '<button type="button" class="btn-plan-edit bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + planId + '">Editar</button>';
                if (planId !== '1') {
                    html += '<button type="button" class="btn-plan-delete text-red-500 hover:bg-red-50 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + planId + '" data-name="' + planName + '">Excluir</button>';
                }
            }
            html += '</div></div>';
        });
        html += '</div>';
        listEl.innerHTML = html;

            listEl.querySelectorAll('.btn-request-plan').forEach(function(btn) {
                btn.onclick = async function() {
                    var planId = btn.getAttribute('data-plan-id');
                    btn.disabled = true;
                    btn.textContent = 'Processando...';
                    try {
                        const res = await fetch(API_BASE + 'create-payment.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'include',
                            body: JSON.stringify({ type: 'plan', planId: parseInt(planId, 10) })
                        });
                        const data = await res.json().catch(function() { return null; });
                        if (data && data.success && data.data && data.data.mode === 'asaas') {
                            var url = data.data.payment_url || '';
                            btn.textContent = url ? 'Abrir pagamento' : 'Assinatura criada';
                            btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                            btn.classList.add('bg-emerald-600');
                            if (url) {
                                btn.onclick = function() { window.open(url, '_blank', 'noopener'); };
                                window.open(url, '_blank', 'noopener');
                            } else {
                                btn.onclick = null;
                            }
                        } else if (data && data.success && data.data && data.data.mode === 'manual') {
                            btn.textContent = 'Solicitação enviada';
                            btn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                            btn.classList.add('bg-amber-500');
                            btn.onclick = null;
                            alert(data.message || 'Solicitação enviada para aprovação manual do administrador.');
                        } else {
                            alert((data && data.error) || 'Não foi possível iniciar a contratação.');
                        }
                    } catch (e) {
                        alert('Erro de conexão ao iniciar a contratação do plano.');
                    } finally {
                        if (btn.textContent === 'Processando...') {
                            btn.disabled = false;
                            btn.textContent = 'Solicitar plano';
                        } else if (btn.textContent === 'Abrir pagamento' || btn.textContent === 'Solicitação enviada') {
                            btn.disabled = false;
                        }
                    }
                };
            });

        var totalEl = document.getElementById('plans-total');
        if (totalEl) totalEl.textContent = total + ' plano(s)';
    }).catch(function() {
        if (spinnerEl) spinnerEl.remove();
        if (listEl) listEl.innerHTML = '<div class="py-8 p-5 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium">Erro ao carregar planos.</div>';
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
    overlay.innerHTML = '<div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
        '<h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-2">Excluir plano</h4>' +
        '<p class="text-slate-600 dark:text-slate-400 text-sm mb-6">Excluir o plano <strong>"' + safeName + '"</strong>? Nenhuma empresa pode estar vinculada a ele.</p>' +
        '<p id="plans-delete-error" class="text-red-600 text-sm font-medium mb-4 hidden"></p>' +
        '<div class="flex gap-3">' +
        '<button type="button" id="plans-delete-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>' +
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
    overlay.innerHTML = '<div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
        '<h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-6">Criar plano</h4>' +
        '<form id="plans-create-form" class="space-y-4">' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome</label>' +
        '<input type="text" id="plan-name" required placeholder="Ex: Básico, Pro" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" /></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Quantos tokens</label>' +
        '<input type="number" id="plan-tokens" min="0" value="100" placeholder="100" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" />' +
        '<p class="text-[10px] text-slate-400 mt-1">0 = ilimitado. 1 token = 1 página de resultados (até 50 itens por página).</p></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Valor mensal (R$)</label>' +
        '<input type="number" id="plan-price" min="0" step="0.01" value="0" placeholder="0,00" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" /></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Grupo do catálogo</label><select id="plan-catalog-group" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"><option value="suggested" selected>Novo plano modular sugerido (rascunho)</option><option value="current">Plano atual</option><option value="crm_addon">CRM adicional (licença separada)</option></select></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-2">Módulos do plano</label><div class="grid grid-cols-2 gap-2 text-xs font-bold text-slate-600 dark:text-slate-300"><label><input type="checkbox" name="plan-module" value="maps" checked> Maps</label><label><input type="checkbox" name="plan-module" value="linkedin"> LinkedIn</label><label><input type="checkbox" name="plan-module" value="cnpj"> CNPJ</label><label><input type="checkbox" name="plan-module" value="crm"> CRM</label><label><input type="checkbox" name="plan-module" value="export" checked> Exportação</label></div><p class="mt-1 text-[10px] text-slate-400">Use status inativo no editor se o preço ainda estiver em aprovação.</p></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Usuários incluídos</label><input type="number" id="plan-included-users" min="1" value="1" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" /></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Usuário adicional no CRM (R$/mês)</label><input type="number" id="plan-addon-user-price" min="0" step="0.01" value="19.90" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" /></div>' +
        '<p id="plans-create-error" class="text-red-600 text-sm font-medium hidden"></p>' +
        '<div class="flex gap-3 pt-4">' +
        '<button type="button" id="plans-create-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>' +
        '<button type="submit" id="plans-create-submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Criar rascunho</button>' +
        '</div></form></div>';
    overlay.onclick = function(e) { if (e.target === overlay) overlay.remove(); };
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
        var catalogGroup = document.getElementById('plan-catalog-group').value || 'suggested';
        var includedUsers = Math.max(1, parseInt(document.getElementById('plan-included-users').value, 10) || 1);
        var addonUserPriceCents = Math.max(0, Math.round((parseFloat((document.getElementById('plan-addon-user-price').value || '0').toString().replace(',', '.')) || 0) * 100));
        var modules = Array.prototype.map.call(document.querySelectorAll('#plans-create-form input[name="plan-module"]:checked'), function (input) { return input.value; });
        if (!name) { errorEl.textContent = 'Nome é obrigatório.'; errorEl.classList.remove('hidden'); return; }
        var slug = name.toLowerCase().normalize('NFD').replace(/\p{Diacritic}/gu, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'plano';
        errorEl.classList.add('hidden');
        var submitBtn = document.getElementById('plans-create-submit');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Salvando...';
        fetch(API_BASE + 'plans.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ name: name, slug: slug, tokenLimit: tokenLimit, priceMonthly: priceMonthly, period: 'monthly', status: 'inactive', catalogGroup: catalogGroup, includedUsers: includedUsers, addonUserPriceCents: addonUserPriceCents, modules: modules })
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
            errorEl.textContent = 'Erro de conexão.';
            errorEl.classList.remove('hidden');
            submitBtn.disabled = false;
            submitBtn.textContent = 'Criar plano';
        });
    };
}
function showPlansEditModal(contentArea, planId) {
    Promise.all([
        fetch(API_BASE + 'plans.php?id=' + encodeURIComponent(planId), { credentials: 'include' }).then(function(r) { return r.json(); }),
        fetch(API_BASE + 'plans.php', { method: 'GET', credentials: 'include', cache: 'no-store' }).then(function(r) { return r.json(); })
    ])
    .then(function(results) {
        var data = results[0];
        var plansData = results[1];
        if (!data.success || !data.data) {
            alert(data.error || 'Plano não encontrado.');
            return;
        }
        var p = data.data;
        var plans = (plansData.success && plansData.data && plansData.data.items) ? plansData.data.items : [];
        
        var optionsHtml = plans.map(function(item) {
            return '<option value="' + item.id + '"' + (String(item.id) === String(planId) ? ' selected' : '') + '>' + item.name + '</option>';
        }).join('');
        var selectedModules = Array.isArray(p.modules) ? p.modules : [];
        var moduleKeys = ['maps', 'linkedin', 'cnpj', 'crm', 'export'];
        var moduleLabels = { maps: 'Maps', linkedin: 'LinkedIn', cnpj: 'CNPJ', crm: 'CRM', export: 'Exportação' };
        var moduleCheckboxes = moduleKeys.map(function (key) {
            return '<label><input type="checkbox" name="plan-edit-module" value="' + key + '" ' + (selectedModules.indexOf(key) >= 0 ? 'checked' : '') + '> ' + moduleLabels[key] + '</label>';
        }).join('');

        var overlay = document.createElement('div');
        overlay.id = 'plans-edit-overlay';
        overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4';
        overlay.innerHTML = '<div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
            '<h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-6">Editar plano</h4>' +
            '<form id="plans-edit-form" class="space-y-4">' +
            '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome</label>' +
            '<input type="text" id="plan-edit-name" required class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" value="' + (p.name || '').replace(/"/g, '&quot;') + '" /></div>' +
            '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Identificador (slug)</label>' +
            '<input type="text" id="plan-edit-slug" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" value="' + (p.slug || '').replace(/"/g, '&quot;') + '" /></div>' +
            '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Limite de tokens</label>' +
            '<input type="number" id="plan-edit-tokens" min="0" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" value="' + (p.tokenLimit != null ? p.tokenLimit : 100) + '" /></div>' +
            '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Valor mensal (R$)</label>' +
            '<input type="number" id="plan-edit-price" min="0" step="0.01" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" value="' + (p.priceMonthly != null ? p.priceMonthly : 0) + '" /></div>' +
            '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Período</label>' +
            '<select id="plan-edit-period" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">' +
            '<option value="monthly"' + (p.period === 'yearly' ? '' : ' selected') + '>Mensal</option><option value="yearly"' + (p.period === 'yearly' ? ' selected' : '') + '>Anual</option></select></div>' +
            '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Status</label>' +
            '<select id="plan-edit-status" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold">' +
            '<option value="active"' + (p.status === 'inactive' ? '' : ' selected') + '>Ativo</option><option value="inactive"' + (p.status === 'inactive' ? ' selected' : '') + '>Inativo</option></select></div>' +
            '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Grupo do catálogo</label><select id="plan-edit-catalog-group" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium"><option value="current"' + (p.catalogGroup === 'suggested' || p.catalogGroup === 'crm_addon' ? '' : ' selected') + '>Plano atual</option><option value="suggested"' + (p.catalogGroup === 'suggested' ? ' selected' : '') + '>Novo plano modular sugerido</option><option value="crm_addon"' + (p.catalogGroup === 'crm_addon' ? ' selected' : '') + '>CRM adicional (licença separada)</option></select></div>' +
            '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-2">Módulos</label><div class="grid grid-cols-2 gap-2 text-xs font-bold text-slate-600 dark:text-slate-300">' + moduleCheckboxes + '</div></div>' +
            '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Usuários incluídos</label><input type="number" id="plan-edit-included-users" min="1" value="' + (p.includedUsers || 1) + '" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" /></div>' +
            '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Usuário adicional no CRM (R$/mês)</label><input type="number" id="plan-edit-addon-user-price" min="0" step="0.01" value="' + ((Number(p.addonUserPriceCents || 0) / 100).toFixed(2)) + '" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" /></div>' +
            '<p id="plans-edit-error" class="text-red-600 text-sm font-medium hidden"></p>' +
            '<div class="flex gap-3 pt-4">' +
            '<button type="button" id="plans-edit-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>' +
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
            var priceMonthly = parseFloat((document.getElementById('plan-edit-price').value || '0').toString().replace(',', '.')) || 0;
            var period = document.getElementById('plan-edit-period').value || 'monthly';
            var status = document.getElementById('plan-edit-status').value || 'active';
            var catalogGroup = document.getElementById('plan-edit-catalog-group').value || 'current';
            var includedUsers = Math.max(1, parseInt(document.getElementById('plan-edit-included-users').value, 10) || 1);
            var addonUserPriceCents = Math.max(0, Math.round((parseFloat((document.getElementById('plan-edit-addon-user-price').value || '0').toString().replace(',', '.')) || 0) * 100));
            var modules = Array.prototype.map.call(document.querySelectorAll('#plans-edit-form input[name="plan-edit-module"]:checked'), function (input) { return input.value; });
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
                body: JSON.stringify({ id: planId, name: name, slug: slug, tokenLimit: tokenLimit, priceMonthly: priceMonthly, period: period, status: status, catalogGroup: catalogGroup, includedUsers: includedUsers, addonUserPriceCents: addonUserPriceCents, modules: modules })
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
function showAddCompanyModal() {
    fetch(API_BASE + 'plans.php', { method: 'GET', credentials: 'include', cache: 'no-store' })
        .then(function(r) { return r.json().catch(function() { return { success: false, data: { items: [] } }; }); })
        .then(function(data) {
            var plans = (data.success && data.data && data.data.items) ? data.data.items : (Array.isArray(data.data) ? data.data : []);
            var activePlans = plans.filter(function(p) { return p.status === 'active'; });
            var optionsHtml = activePlans.map(function(p) {
                var label = p.name + (p.tokenLimit === 0 ? ' (ilimitado)' : ' (' + p.tokenLimit + ' tokens)');
                return '<option value="' + p.id + '">' + label + '</option>';
            }).join('');
            if (!optionsHtml) optionsHtml = '<option value="1">Básico (100 tokens)</option>';
            
            var overlay = document.createElement('div');
            overlay.id = 'add-company-overlay';
            overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4 overflow-y-auto';
            overlay.innerHTML = '<div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full my-8" onclick="event.stopPropagation()">' +
                '<h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-6">Nova Empresa</h4>' +
                '<form id="add-company-form" class="space-y-4">' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome da Empresa</label>' +
                '<input type="text" id="add-company-name" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold" placeholder="Ex: Minha Empresa Ltda" required>' +
                '</div>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Identificador (slug)</label>' +
                '<input type="text" id="add-company-slug" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold" placeholder="Ex: minha-empresa">' +
                '<p class="text-[10px] text-slate-400 mt-1">Se vazio, gera automático.</p>' +
                '</div>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Plano</label>' +
                '<select id="add-company-plan" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold">' + optionsHtml + '</select>' +
                '</div>' +
                '<hr class="border-slate-200 dark:border-slate-600 my-4">' +
                '<p class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3">Dados do Administrador</p>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome do Administrador</label>' +
                '<input type="text" id="add-company-admin-name" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold" placeholder="Ex: João Silva">' +
                '</div>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">E-mail do Administrador</label>' +
                '<input type="email" id="add-company-admin-email" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold" required>' +
                '</div>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Senha</label>' +
                '<input type="password" id="add-company-admin-password" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold" required minlength="6">' +
                '</div>' +
                '<p id="add-company-error" class="text-red-600 text-sm font-medium hidden"></p>' +
                '<div class="flex gap-3 pt-4">' +
                '<button type="button" id="add-company-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>' +
                '<button type="submit" id="add-company-submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Criar Empresa</button>' +
                '</div></form></div>';
            overlay.onclick = function(ev) { if (ev.target === overlay) overlay.remove(); };
            document.body.appendChild(overlay);
            
            var form = document.getElementById('add-company-form');
            var cancelBtn = document.getElementById('add-company-cancel');
            var errorEl = document.getElementById('add-company-error');
            var submitBtn = document.getElementById('add-company-submit');
            var nameInput = document.getElementById('add-company-name');
            var slugInput = document.getElementById('add-company-slug');
            var adminNameInput = document.getElementById('add-company-admin-name');
            var adminEmailInput = document.getElementById('add-company-admin-email');
            var adminPasswordInput = document.getElementById('add-company-admin-password');

            if (nameInput && slugInput) {
                nameInput.addEventListener('input', function() {
                    var name = nameInput.value.trim();
                    if (!slugInput.value.trim()) {
                        var slug = name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
                        slugInput.value = slug;
                    }
                    if (!adminNameInput.value.trim()) adminNameInput.value = name;
                });
            }
            form.onsubmit = function(e) {
                e.preventDefault();
                var name = nameInput.value.trim();
                var slug = slugInput.value.trim();
                var planId = document.getElementById('add-company-plan').value;
                var adminName = adminNameInput.value.trim();
                var adminEmail = adminEmailInput.value.trim();
                var adminPassword = adminPasswordInput.value;
                if (!name || !adminEmail || !adminPassword || adminPassword.length < 6) {
                    errorEl.textContent = 'Preencha os campos obrigatórios corretamente.';
                    errorEl.classList.remove('hidden');
                    return;
                }
                errorEl.classList.add('hidden');
                submitBtn.disabled = true;
                submitBtn.textContent = 'Criando...';
                fetch(API_BASE + 'tenants.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ name: name, slug: slug, planId: parseInt(planId, 10), status: 'active', adminName: adminName, adminEmail: adminEmail, adminPassword: adminPassword })
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        overlay.remove();
                        showToast('Empresa criada com sucesso!');
                        loadCompaniesTab(document.getElementById('content-area'));
                    } else {
                        errorEl.textContent = res.error || 'Erro ao criar empresa.';
                        errorEl.classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Criar Empresa';
                    }
                });
            };
            cancelBtn.onclick = function() { overlay.remove(); };
        });
}

function loadCompaniesTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto py-10"><div class="flex justify-between items-center mb-10 flex-wrap gap-4"><h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Empresas cadastradas <span id="companies-total" class="text-slate-500 dark:text-slate-400 font-normal text-lg"></span></h3><div class="flex items-center gap-3"><button type="button" id="btn-add-company" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-3 rounded-2xl text-xs flex items-center gap-2"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>Nova Empresa</button><button type="button" id="companies-refresh-btn" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2">Atualizar</button><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block" id="companies-spinner"></span></div></div><div id="companies-list"></div></div>';

    var refreshBtn = document.getElementById('companies-refresh-btn');
    var addBtn = document.getElementById('btn-add-company');
    if (refreshBtn) refreshBtn.onclick = function() { fetchCompaniesList(); };
    if (addBtn) addBtn.onclick = function() { showAddCompanyModal(); };

    fetchCompaniesList();
}

function fetchCompaniesList() {
    var listEl = document.getElementById('companies-list');
    var spinnerEl = document.getElementById('companies-spinner');

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
            listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhuma empresa cadastrada</p></div>';
            return;
        }

        var html = '<div class="space-y-4">';
        items.forEach(function(t) {
            var statusLabel = t.status === 'active' ? 'Ativa' : 'Suspensa';
            var statusClass = t.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700';
            var toggleLabel = t.status === 'active' ? 'Desativar' : 'Ativar';
            var planInfo = (t.plan || '') + (t.planTokenLimit != null ? (t.planTokenLimit === 0 ? ' (ilimitado)' : ' (' + t.planTokenLimit + ' tokens)') : '');
            var tokensUsed = t.tokensUsed != null ? t.tokensUsed : 0;
            var tokensLimit = t.tokensLimit != null ? t.tokensLimit : 0;
            var tokensInfo = tokensLimit > 0 ? ' · Tokens: ' + tokensUsed + '/' + tokensLimit : ' · Tokens: ' + tokensUsed + ' (ilimitado)';
            var createdAt = t.createdAt ? new Date(String(t.createdAt).replace(' ', 'T')).toLocaleString('pt-BR') : 'Não informada';
            var accessUntil = t.planAccessUntil ? String(t.planAccessUntil).slice(0, 10).split('-').reverse().join('/') : 'Sem validade definida';

            html += '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 flex items-center justify-between flex-wrap gap-4">';
            html += '<div class="flex items-center gap-6">';
            // AQUI ESTAVA O ERRO - AGORA CORRIGIDO PARA justify-center:
            html += '<div class="w-12 h-12 bg-slate-100 dark:bg-slate-700 rounded-xl flex items-center justify-center text-xl font-black text-slate-600 dark:text-slate-400">' + (t.name ? t.name.charAt(0).toUpperCase() : '') + '</div>';
            html += '<div><h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg">' + (t.name || '') + '</h4>';
            html += '<p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + (t.slug || '') + (t.email ? ' · ' + (t.email) : '') + ' · Plano: ' + planInfo + tokensInfo + ' · ' + (t.usersCount || 0) + ' usuário(s) · <span class="' + statusClass + ' px-2 py-0.5 rounded-full text-xs font-bold">' + statusLabel + '</span></p>';
            html += '<p class="mt-1 text-[11px] font-semibold text-slate-500 dark:text-slate-400">Cadastro: ' + dashboardMetricEscape(createdAt) + ' · Validade do acesso: ' + dashboardMetricEscape(accessUntil) + '</p></div></div>';
            html += '<div class="flex items-center gap-2">';
            html += '<button type="button" class="btn-access-tenant px-4 py-2 rounded-xl text-xs font-bold bg-emerald-100 text-emerald-700 hover:bg-emerald-200" data-id="' + t.id + '" data-name="' + (t.name || '').replace(/"/g, '&quot;') + '">Acessar</button>';
            html += '<button type="button" class="btn-edit-tenant px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-200" data-id="' + t.id + '" data-name="' + (t.name || '').replace(/"/g, '&quot;') + '" data-email="' + (t.email || '').replace(/"/g, '&quot;') + '">Editar</button>';
            html += '<button type="button" class="btn-toggle-tenant px-4 py-2 rounded-xl text-xs font-bold ' + (t.status === 'active' ? 'bg-amber-100 text-amber-700 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-700 hover:bg-emerald-200') + '" data-id="' + t.id + '" data-name="' + (t.name || '').replace(/"/g, '&quot;') + '" data-status="' + (t.status === 'active' ? 'suspended' : 'active') + '">' + toggleLabel + '</button>';
            html += '<button type="button" class="btn-link-plan px-4 py-2 rounded-xl text-xs font-bold bg-blue-100 text-blue-700 hover:bg-blue-200" data-id="' + t.id + '" data-name="' + (t.name || '').replace(/"/g, '&quot;') + '" data-plan-id="' + (t.planId || '1') + '">Vincular plano</button>';
            if (t.status === 'suspended') {
                html += '<button type="button" class="btn-delete-tenant px-4 py-2 rounded-xl text-xs font-bold bg-red-100 text-red-700 hover:bg-red-200" data-id="' + t.id + '" data-name="' + (t.name || '').replace(/"/g, '&quot;') + '">Excluir</button>';
            }
            html += '</div></div>';
        });
        html += '</div>';
        listEl.innerHTML = html;

        var totalEl = document.getElementById('companies-total');
        if (totalEl) totalEl.textContent = total + ' empresa(s)';

        listEl.querySelectorAll('.btn-access-tenant').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tenantId = btn.dataset.id;
                if (!tenantId) return;
                btn.disabled = true;
                fetch(API_BASE + 'auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'impersonate', tenantId: tenantId })
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        AppState.user = res.data.user;
                        AppState.tenant = res.data.tenant || null;
                        AppState.tokenUsage = res.data.tokenUsage || null;
                        AppState.impersonating = !!(res.data.impersonating);
                        AppState.impersonatingTenantName = res.data.impersonatingTenantName || '';
                        AppState.activeTab = 'dashboard';
                        showDashboard();
                        loadConfig();
                    } else {
                        alert(res.error || 'Erro ao acessar empresa.');
                    }
                })
                .catch(function() { alert('Erro de conexão.'); })
                .finally(function() { btn.disabled = false; });
            });
        });

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
                    if (res.success) { fetchCompaniesList(); }
                    else { alert(res.error || 'Erro ao atualizar.'); }
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
                overlay.innerHTML = '<div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
                    '<h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-2">Vincular empresa ao plano</h4>' +
                    '<p class="text-sm text-slate-500 dark:text-slate-400 mb-4">' + (tenantName ? 'Empresa: ' + tenantName : '') + '</p>' +
                    '<form id="companies-link-plan-form" class="space-y-4">' +
                    '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Plano</label>' +
                    '<select id="companies-link-plan-select" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">' + optionsHtml + '</select>' +
                    '<p id="companies-link-plan-error" class="text-red-600 text-sm font-medium hidden"></p>' +
                    '<div class="flex gap-3 pt-4">' +
                    '<button type="button" id="companies-link-plan-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>' +
                    '<button type="submit" id="companies-link-plan-submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Vincular</button>' +
                    '</div></form></div>';
                overlay.onclick = function(ev) { if (ev.target === overlay) overlay.remove(); };
                document.body.appendChild(overlay);
                var form = document.getElementById('companies-link-plan-form');
                form.onsubmit = function(e) {
                    e.preventDefault();
                    var planId = document.getElementById('companies-link-plan-select').value;
                    var submitBtn = document.getElementById('companies-link-plan-submit');
                    submitBtn.disabled = true;
                    fetch(API_BASE + 'tenants.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ id: tenantId, planId: parseInt(planId, 10) })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) { overlay.remove(); fetchCompaniesList(); }
                        else { document.getElementById('companies-link-plan-error').textContent = res.error || 'Erro.'; document.getElementById('companies-link-plan-error').classList.remove('hidden'); submitBtn.disabled = false; }
                    });
                };
                document.getElementById('companies-link-plan-cancel').onclick = function() { overlay.remove(); };
            });
        });

        listEl.querySelectorAll('.btn-edit-tenant').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var tenantId = btn.dataset.id;
                var tenantName = btn.dataset.name || '';
                var tenantEmail = btn.dataset.email || '';
                var overlay = document.getElementById('companies-edit-tenant-overlay');
                if (overlay) overlay.remove();
                overlay = document.createElement('div');
                overlay.id = 'companies-edit-tenant-overlay';
                overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4';
                overlay.innerHTML = '<div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
                    '<h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-2">Editar empresa</h4>' +
                    '<form id="companies-edit-tenant-form" class="space-y-4">' +
                    '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome</label>' +
                    '<input type="text" id="companies-edit-tenant-name" value="' + tenantName.replace(/"/g, '&quot;') + '" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" placeholder="Nome da empresa"></div>' +
                    '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">E-mail</label>' +
                    '<input type="email" id="companies-edit-tenant-email" value="' + tenantEmail.replace(/"/g, '&quot;') + '" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" placeholder="admin@empresa.com"></div>' +
                    '<p id="companies-edit-tenant-error" class="text-red-600 text-sm font-medium hidden"></p>' +
                    '<div class="flex gap-3 pt-4">' +
                    '<button type="button" id="companies-edit-tenant-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>' +
                    '<button type="submit" id="companies-edit-tenant-submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Salvar</button>' +
                    '</div></form></div>';
                overlay.onclick = function(ev) { if (ev.target === overlay) overlay.remove(); };
                document.body.appendChild(overlay);
                var form = document.getElementById('companies-edit-tenant-form');
                form.onsubmit = function(e) {
                    e.preventDefault();
                    var name = document.getElementById('companies-edit-tenant-name').value.trim();
                    var email = document.getElementById('companies-edit-tenant-email').value.trim().toLowerCase();
                    if (!name) return;
                    fetch(API_BASE + 'tenants.php', {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ id: tenantId, name: name, email: email })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) { overlay.remove(); fetchCompaniesList(); }
                    });
                };
                document.getElementById('companies-edit-tenant-cancel').onclick = function() { overlay.remove(); };
            });
        });

        listEl.querySelectorAll('.btn-delete-tenant').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.dataset.id;
                var name = btn.dataset.name;
                if (!confirm('Excluir a empresa "' + name + '"?')) return;
                fetch(API_BASE + 'tenants.php?id=' + encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(res) { if (res.success) fetchCompaniesList(); });
            });
        });
    }).catch(function(err) {
        console.error(err);
        if (spinnerEl) spinnerEl.remove();
        if (listEl) listEl.innerHTML = '<div class="py-8 p-5 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium">Erro ao carregar empresas.</div>';
    });
}
// GPS
function refreshLocation() {
    AppState.locStatus = 'loading';
    updateGPSUI();
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            async (pos) => {
                AppState.userCoords = { latitude: pos.coords.latitude, longitude: pos.coords.longitude };
                AppState.locStatus = 'success';
                try {
                    const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${AppState.userCoords.latitude}&lon=${AppState.userCoords.longitude}&zoom=10`);
                    if (res.ok) {
                        const data = await res.json();
                        const addr = data.address;
                        const city = addr.city || addr.town || '';
                        const state = addr.state || '';
                        if (city) AppState.userLocationName = city + (state ? ' - ' + state : '');
                    }
                } catch (e) { console.warn(e); }
                updateGPSUI();
            },
            () => { AppState.locStatus = 'error'; updateGPSUI(); },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    }
}

function updateGPSUI() {
    const indicator = document.getElementById('gps-indicator');
    const status = document.getElementById('gps-status');
    if (!indicator || !status) return;
    const locationDiv = document.getElementById('gps-location');
    const locationName = document.getElementById('gps-location-name');
    const btnText = document.getElementById('gps-btn-text');
    const icon = document.getElementById('gps-icon');
    if (AppState.locStatus === 'success') {
        indicator.className = 'w-2 h-2 rounded-full bg-emerald-500 shadow-[0_0_8px_#10b981]';
        status.textContent = 'Localização Ativa';
        if (AppState.userLocationName && locationDiv && locationName) {
            locationDiv.classList.remove('hidden');
            locationName.textContent = AppState.userLocationName;
        }
        if (btnText) btnText.textContent = 'Recarregar GPS';
        if (icon) icon.classList.remove('animate-spin');
    } else if (AppState.locStatus === 'loading') {
        indicator.className = 'w-2 h-2 rounded-full bg-yellow-500 animate-pulse';
        status.textContent = 'Detectando...';
        if (locationDiv) locationDiv.classList.add('hidden');
        if (btnText) btnText.textContent = 'Localizando...';
        if (icon) icon.classList.add('animate-spin');
    } else {
        indicator.className = 'w-2 h-2 rounded-full bg-red-500';
        status.textContent = 'GPS Inativo';
        if (locationDiv) locationDiv.classList.add('hidden');
        if (btnText) btnText.textContent = 'Recarregar GPS';
        if (icon) icon.classList.remove('animate-spin');
    }
}

function getProspectingHTML() {
    const tokenUsage = AppState.tokenUsage || { used: 0, limit: 0 }; 
    const maxTokens = (tokenUsage && tokenUsage.limit > 0) ? Math.max(1, tokenUsage.limit - tokenUsage.used) : 1000;
    const canUseMapsSelfHosted = !!AppState.user;
    const isPilotSuperAdmin = String(((AppState.user || {}).profile || '')).toLowerCase() === 'super_admin';
    
    return `
        <div class="max-w-[1400px] mx-auto">
            <div id="search-token-limit-banner" class="hidden mb-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium flex items-center gap-2">
                <span class="text-lg">⚠️</span>
                <span>Você atingiu o limite de tokens. Solicite mais créditos em <strong>Solicitar Créditos</strong> ou aguarde o próximo período.</span>
            </div>
            
            <div class="hidden">
                <button id="toggle-gps" class="hidden">USAR MEU GPS</button>
                <input type="hidden" id="search-method" value="scraper">
                <input type="number" id="search-max-places" value="200">
            </div>

            <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] shadow-sm border border-slate-200 dark:border-slate-600 mb-10">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="min-w-0 md:col-span-3">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">O que busca?</label>
                        <input id="search-query" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm transition-all placeholder-slate-400 dark:placeholder-slate-500" placeholder="Ex: Petshop, Clínica, Padaria...">
                    </div>
                    <div class="min-w-0 md:col-span-3">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Onde?</label>
                        <input id="search-location" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm transition-all placeholder-slate-400 dark:placeholder-slate-500" placeholder="Cidade ou Região">
                    </div>
                    <div class="min-w-0 md:col-span-2">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Tag CRM</label>
                        <input id="search-tag" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm placeholder-slate-400 dark:placeholder-slate-500" placeholder="Ex: leads_novos">
                    </div>
                    <div class="min-w-0 md:col-span-4 flex flex-wrap items-end gap-2">
                        <button id="btn-search" class="min-w-[102px] flex-1 py-3.5 px-3 bg-slate-900 hover:bg-blue-600 text-white font-black rounded-xl transition-all shadow-lg shadow-slate-900/30 dark:shadow-lg dark:shadow-black/20 disabled:opacity-50 flex items-center justify-center uppercase tracking-wider text-[10px] sm:text-xs whitespace-nowrap">
                            Buscar Tudo
                        </button>
                        ${canUseMapsSelfHosted ? '<button id="btn-maps-pilot-direct" type="button" class="min-w-[122px] flex-1 py-3.5 px-3 bg-sky-700 hover:bg-sky-800 text-white font-black rounded-xl transition-all shadow-lg shadow-sky-900/20 disabled:opacity-50 uppercase tracking-wider text-[10px] whitespace-nowrap" title="Buscar empresas pelo coletor Atendo">Buscar no Maps</button><button id="btn-maps-check-new-leads" type="button" class="min-w-[156px] flex-1 py-3.5 px-3 bg-emerald-600 hover:bg-emerald-700 text-white font-black rounded-xl transition-all shadow-lg shadow-emerald-900/20 disabled:opacity-50 uppercase tracking-wider text-[10px] whitespace-nowrap" title="Comparar os resultados do banco com os perfis já vistos nesta sessão">Verificar novos leads</button>' : ''}
                    </div>
                </div>
            </div>
            ${isPilotSuperAdmin ? '<div id="maps-pilot-live-metrics" class="mb-6 hidden rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-xs text-sky-900 dark:border-sky-900/70 dark:bg-sky-950/35 dark:text-sky-100"></div>' : ''}
            
            <div id="error-info" class="hidden mb-8 p-5 bg-amber-50 border-l-4 border-amber-400 text-amber-900 rounded-r-2xl shadow-sm"></div>
            <div id="results-header" class="hidden mb-6 flex flex-wrap justify-between items-end gap-4 px-2">
                <div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-slate-100 tracking-tight">Resultados da Busca</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                        Exibindo <span id="visible-count" class="font-bold text-slate-900 dark:text-slate-100">0</span> de <span id="total-count" class="font-bold text-slate-900 dark:text-slate-100">0</span> empresas encontradas
                    </p>
                    <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mt-1">
                        Tokens disponíveis na conta: <span id="available-tokens" class="font-bold text-slate-900 dark:text-slate-100">—</span>
                    </p>
                    ${canUseMapsSelfHosted ? '<p id="maps-pilot-summary" class="hidden mt-2 text-xs font-bold text-sky-700 dark:text-sky-300"></p>' : ''}
                </div>
                <div id="results-header-buttons" class="flex items-center gap-3">
                    ${canUseMapsSelfHosted ? '<button id="btn-maps-pilot-refresh" type="button" class="hidden items-center gap-2 px-5 py-3 bg-sky-700 hover:bg-sky-800 text-white rounded-xl transition-all text-xs font-black uppercase shadow-lg shadow-sky-900/20" title="Atualizar a pesquisa e adicionar apenas os novos contatos">Atualizar pesquisa Maps</button>' : ''}
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
            
            <div id="empty-state" class="py-32 text-center">
                <div class="w-28 h-28 bg-blue-500/10 dark:bg-blue-500/5 rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl relative animate-pulse">
                    <div class="absolute inset-0 rounded-full border border-blue-500/30 animate-ping" style="animation-duration: 2s;"></div>
                    <span class="text-5xl animate-bounce" style="animation-duration: 3s;">🗺️</span>
                </div>
                <h4 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-[0.25em] mb-2 animate-pulse">Pronto para Prospectar?</h4>
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Digite uma atividade e cidade acima para iniciar a busca</p>
            </div>
        </div>
    `;
} 

function setupProspectingEvents() {
    const btnSearch = document.getElementById('btn-search');
    if (btnSearch) {
        btnSearch.addEventListener('click', function(e) {
            e.preventDefault();
            performSearch();
        });
    }
    const queryInput = document.getElementById('search-query');
    const locationInput = document.getElementById('search-location');
    const tagInput = document.getElementById('search-tag');
    const savedSearch = AppState.currentSearch || {};
    if (queryInput && savedSearch.query) queryInput.value = savedSearch.query;
    if (locationInput && savedSearch.location) locationInput.value = savedSearch.location;
    if (tagInput && savedSearch.tag) tagInput.value = savedSearch.tag;
    if (queryInput) {
        queryInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }
    if (locationInput) {
        locationInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') performSearch();
        });
    }
    const btnExportExcel = document.getElementById('btn-export-excel');
    if (btnExportExcel) {
        btnExportExcel.addEventListener('click', function(e) {
            e.preventDefault();
            exportCurrentSearchToExcel();
        });
    }
    const btnExportWebhook = document.getElementById('btn-export-webhook');
    if (btnExportWebhook) {
        btnExportWebhook.addEventListener('click', function(e) {
            e.preventDefault();
            exportCurrentSearchToWebhook();
        });
    }
    const btnMapsPilotRefresh = document.getElementById('btn-maps-pilot-refresh');
    if (btnMapsPilotRefresh) {
        btnMapsPilotRefresh.addEventListener('click', function(e) {
            e.preventDefault();
            refreshCurrentSearchWithMapsPilot();
        });
    }
    const btnMapsPilotDirect = document.getElementById('btn-maps-pilot-direct');
    if (btnMapsPilotDirect) {
        btnMapsPilotDirect.addEventListener('click', function(e) {
            e.preventDefault();
            runDirectMapsPilotSearch();
        });
    }
    const btnMapsCheckNewLeads = document.getElementById('btn-maps-check-new-leads');
    if (btnMapsCheckNewLeads) {
        btnMapsCheckNewLeads.addEventListener('click', function(e) {
            e.preventDefault();
            runMapsCheckNewLeads();
        });
    }
}

function escapeCsv(val) {
    if (val == null || val === undefined) return '';
    const s = String(val).trim();
    if (s.includes(',') || s.includes('"') || s.includes('\n') || s.includes('\r')) {
        return '"' + s.replace(/"/g, '""') + '"';
    }
    return s;
}

/**
 * Normaliza telefones retornados por provedores de mapas.
 * Mantém o valor original quando não é possível identificar com segurança
 * um número brasileiro ou internacional.
 */
function formatPhoneDisplay(value) {
    if (value == null) return '';

    if (Array.isArray(value)) {
        const formatted = value
            .flatMap(item => formatPhoneDisplay(item).split(' / '))
            .map(item => item.trim())
            .filter(Boolean);
        return [...new Set(formatted)].join(' / ');
    }

    if (typeof value === 'object') {
        const nested = value.phone || value.phoneNumber || value.number || value.value || '';
        return formatPhoneDisplay(nested);
    }

    const raw = String(value).trim();
    if (!raw) return '';

    const chunks = raw
        .split(/\s*(?:\/|;|\||\n)\s*/)
        .map(item => item.trim())
        .filter(Boolean);

    const formatted = chunks.map(item => {
        const extensionMatch = item.match(/(?:ext\.?|x|ramal)\s*([0-9]+)\s*$/i);
        const extension = extensionMatch ? ` ramal ${extensionMatch[1]}` : '';
        let digits = item.replace(/(?:ext\.?|x|ramal)\s*[0-9]+\s*$/i, '').replace(/\D/g, '');

        // Remove prefixos internacionais de discagem e o zero de tronco.
        digits = digits.replace(/^00+/, '');
        if (digits.length > 11 && digits.startsWith('0')) digits = digits.replace(/^0+/, '');

        // Retorno brasileiro com código do país: 55 + DDD + telefone.
        if (digits.startsWith('55')) {
            const local = digits.slice(2);
            if (local.length === 10 || local.length === 11) {
                const area = local.slice(0, 2);
                const number = local.slice(2);
                const splitAt = number.length === 9 ? 5 : 4;
                return `+55 ${area} ${number.slice(0, splitAt)}-${number.slice(splitAt)}${extension}`;
            }
        }

        // Retorno brasileiro sem código do país: assume DDD + telefone.
        if (digits.length === 10 || digits.length === 11) {
            const area = digits.slice(0, 2);
            const number = digits.slice(2);
            const splitAt = number.length === 9 ? 5 : 4;
            return `+55 ${area} ${number.slice(0, splitAt)}-${number.slice(splitAt)}${extension}`;
        }

        // Para outros países ou formatos incompletos, não inventa agrupamento.
        return item + extension;
    }).filter(Boolean);

    return [...new Set(formatted)].join(' / ');
}

function getLeadPhoneValue(lead) {
    if (!lead) return '';
    return lead.phone || lead.phoneNumber || lead.internationalPhone || lead.telephone || lead.phoneNumbers || '';
}

function exportCurrentSearchToExcel() {
    if (!AppState.searchId) {
        showError('Sessão da pesquisa expirada. Faça uma nova busca.');
        return;
    }
    const leads = (AppState.leads || []);
    if (leads.length === 0) {
        alert('Nenhum lead encontrado para exportar.');
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
            escapeCsv(formatPhoneDisplay(getLeadPhoneValue(lead))),
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

async function exportCurrentSearchToWebhook() {
    const leads = AppState.leads || [];
    if (leads.length === 0) {
        alert('Nenhum lead na pesquisa atual para enviar.');
        return;
    }
    // A URL efetiva é resolvida pelo servidor na ordem: pessoal, empresa e
    // plataforma. Não bloqueie a exportação aqui, pois o usuário pode usar
    // corretamente um dos níveis de fallback sem ter URL pessoal.
    const btn = document.getElementById('btn-export-webhook');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="animate-pulse">Enviando...</span>';
    }
    try {
        const res = await fetch(API_BASE + 'export-bulk.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leadIds: leads.map(l => l.id) })
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
async function performSearch() {
    var limitReached = (AppState.tokenUsage && AppState.tokenUsage.limitReached) || (AppState.tenant && AppState.tenant.status === 'suspended');
    if (limitReached) {
        showError('Você atingiu o limite de tokens do seu plano para este período. Solicite mais créditos em "Solicitar Créditos" ou aguarde o próximo período.');
        return;
    }

    const query = document.getElementById('search-query').value.trim();
    const location = document.getElementById('search-location')?.value.trim() || '';
    const tag = document.getElementById('search-tag').value.trim();
    const useGPS = document.getElementById('toggle-gps') ? document.getElementById('toggle-gps').textContent === 'GPS ATIVO' : false;
    
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
    btn.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span> Buscando...';
    hideError();
    beginSearchLoading('Pesquisando empresas no Maps...', 'Estamos consultando os filtros informados e organizando os leads encontrados para você.');
    AppState.leads = [];
    AppState.visibleCount = 12;
    AppState.mapsPilotSummary = null;
    AppState.mapsPilotNewLeadIds = [];
    AppState.mapsPilotShowOnlyNew = false;
    
    try {
        var maxPlacesEl = document.getElementById('search-max-places');
        var maxPlaces = 200;
        var tokenUsage = AppState.tokenUsage;
        var maxTokens = (tokenUsage && tokenUsage.limit > 0) ? Math.max(1, tokenUsage.limit - tokenUsage.used) : 1000;
        
        if (maxPlacesEl && maxPlacesEl.value) {
            var v = parseInt(maxPlacesEl.value, 10);
            if (!isNaN(v)) maxPlaces = Math.max(1, Math.min(maxTokens, v));
        }
        var methodEl = document.getElementById('search-method');
        var searchMethod = methodEl && methodEl.value ? methodEl.value : 'scraper';
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
                maxCrawledPlacesPerSearch: maxPlaces,
                searchMethod: searchMethod
            })
        });
        
        if (!res.ok) {
            const text = await res.text();
            let errorMsg = `Erro ${res.status}: Erro interno do servidor`;
            try {
                if (text) {
                    const errorData = JSON.parse(text);
                    errorMsg = errorData.error || errorData.message || errorMsg;
                }
            } catch (e) { }
            showError(errorMsg);
            return;
        }
        
        const text = await res.text();
        if (!text || text.trim() === '') {
            showError('Erro: Resposta vazia do servidor.');
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

    // 💡 NOVO: Se outro usuário já pesquisou este mesmo termo antes, exibe o aviso:
    if (data.data.searchedBy) {
        showToast(`⚠️ Este termo já foi pesquisado anteriormente por: ${data.data.searchedBy}`, 'warning');
    } else if (data.data.duplicatesRemoved > 0) {
        showToast(data.data.duplicatesRemoved + ' resultado(s) duplicado(s) removidos.', 'warning');
    }
            
            if (data.data.isNewSearch) {
    var realSearchId = String(data.data.searchId || Date.now());
    var newItem = { 
        id: realSearchId, // ✅ Usa o ID verdadeiro gravado no banco
        query: query, 
        location: locationText, 
        tag: tag, 
        timestamp: new Date().toISOString(), 
        resultsCount: AppState.leads.length, 
        leads: AppState.leads.slice() 
    };
    AppState.history = [newItem].concat(AppState.history.filter(function(h) { return h.id !== realSearchId; })).slice(0, 50);
} else {
                showToast('Pesquisa continuada - novos resultados adicionados.', 'success');
            }
            displayLeads();
        } else {
            showError(data.error || 'Erro ao buscar leads');
        }
    } catch (e) {
        showError('Erro de conexão: ' + e.message);
    } finally {
        endSearchLoading();
        btn.disabled = false;
        btn.textContent = 'Buscar Tudo';
    }
}

function getMapsPilotLeadId(lead) {
    const value = lead && (lead.dbId || lead.db_id || lead.id);
    return String(value == null ? '' : value).replace(/^lead-/, '');
}

function applyMapsPilotNewLeadState(result, leads) {
    const newLeadIds = Array.isArray(result && result.new_lead_ids)
        ? result.new_lead_ids.map(function(id) { return String(id); }).filter(Boolean)
        : [];
    const newLeadIdSet = new Set(newLeadIds);
    AppState.mapsPilotNewLeadIds = newLeadIds;
    AppState.mapsPilotShowOnlyNew = false;
    AppState.mapsViewedLeadIds = AppState.mapsViewedLeadIds || [];
    const viewedSet = new Set(AppState.mapsViewedLeadIds);
    return (Array.isArray(leads) ? leads : []).map(function(lead) {
        const normalizedLead = Object.assign({}, lead);
        const leadId = getMapsPilotLeadId(normalizedLead);
        if (leadId) viewedSet.add(leadId);
        normalizedLead.isPilotNew = newLeadIdSet.has(leadId) || !viewedSet.has(leadId);
        return normalizedLead;
    });
}
function commitMapsViewedLeadIds(leads) {
    const viewedSet = new Set(AppState.mapsViewedLeadIds || []);
    (Array.isArray(leads) ? leads : []).forEach(function(lead) {
        const id = getMapsPilotLeadId(lead);
        if (id) viewedSet.add(id);
    });
    AppState.mapsViewedLeadIds = Array.from(viewedSet);
}

function getDisplayedProspectingLeads() {
    const allLeads = Array.isArray(AppState.leads) ? AppState.leads : [];
    if (!AppState.mapsPilotShowOnlyNew) return allLeads;
    return allLeads.filter(function(lead) { return lead && lead.isPilotNew === true; });
}

function displayLeads() {
    const grid = document.getElementById('leads-grid');
    const emptyState = document.getElementById('empty-state');
    const resultsHeader = document.getElementById('results-header');
    const loadMoreContainer = document.getElementById('load-more-container');

    // A renderização pode ser acionada por um timeout depois que o usuário
    // mudou de aba. Nesse caso, os elementos pertencentes à busca já não
    // existem no DOM e a função deve encerrar sem alterar o painel atual.
    if (!grid || !emptyState || !resultsHeader || !loadMoreContainer) {
        return;
    }

    if (!Array.isArray(AppState.leads)) {
        AppState.leads = [];
    }
    
    const displayedLeads = getDisplayedProspectingLeads();
    if (displayedLeads.length === 0) {
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
        // Verifica se existe URL configurada no config global ou no tenant
        const hasWebhookUrl = (AppState.config && AppState.config.baseUrl && String(AppState.config.baseUrl).trim() !== '') ||
                              (AppState.tenant && AppState.tenant.webhookUrl && String(AppState.tenant.webhookUrl).trim() !== '');
        
        btnExportWebhook.classList.toggle('hidden', !hasWebhookUrl);
    }

    const canUseMapsSelfHosted = !!AppState.user;
    const btnMapsPilotRefresh = document.getElementById('btn-maps-pilot-refresh');
    if (btnMapsPilotRefresh) {
        const canRefresh = canUseMapsSelfHosted && !!AppState.searchId;
        btnMapsPilotRefresh.classList.toggle('hidden', !canRefresh);
        btnMapsPilotRefresh.classList.toggle('inline-flex', canRefresh);
    }
    const mapsPilotSummary = document.getElementById('maps-pilot-summary');
    if (mapsPilotSummary) {
        const summary = AppState.mapsPilotSummary;
        if (summary && String(summary.searchId) === String(AppState.searchId)) {
            mapsPilotSummary.textContent = summary.text;
            const newLeadCount = Array.isArray(summary.newLeadIds) ? summary.newLeadIds.length : 0;
            if (newLeadCount > 0) {
                const filterButton = document.createElement('button');
                filterButton.type = 'button';
                filterButton.className = 'ml-2 inline-flex items-center rounded-lg border border-sky-300 bg-white/70 px-2.5 py-1 text-[10px] font-black uppercase tracking-wide text-sky-800 transition-colors hover:bg-sky-100 dark:border-sky-700 dark:bg-slate-900/50 dark:text-sky-200 dark:hover:bg-sky-900/40';
                filterButton.textContent = AppState.mapsPilotShowOnlyNew
                    ? 'Mostrar todos os leads'
                    : 'Ver ' + newLeadCount + ' novo' + (newLeadCount === 1 ? '' : 's') + ' lead' + (newLeadCount === 1 ? '' : 's');
                filterButton.addEventListener('click', function() {
                    AppState.mapsPilotShowOnlyNew = !AppState.mapsPilotShowOnlyNew;
                    AppState.visibleCount = Math.max(12, getDisplayedProspectingLeads().length);
                    displayLeads();
                });
                mapsPilotSummary.appendChild(filterButton);
                if (AppState.mapsPilotShowOnlyNew) {
                    const modeLabel = document.createElement('span');
                    modeLabel.className = 'ml-2 text-[10px] font-black uppercase tracking-wide text-sky-600 dark:text-sky-300';
                    modeLabel.textContent = 'Mostrando somente os novos leads';
                    mapsPilotSummary.appendChild(modeLabel);
                }
            }
            mapsPilotSummary.classList.remove('hidden');
        } else {
            mapsPilotSummary.textContent = '';
            mapsPilotSummary.classList.add('hidden');
        }
    }

    const visible = displayedLeads.slice(0, AppState.visibleCount);
    const visibleCountEl = document.getElementById('visible-count');
    const totalCountEl = document.getElementById('total-count');
    if (visibleCountEl) visibleCountEl.textContent = visible.length;
    if (totalCountEl) totalCountEl.textContent = displayedLeads.length;
    var u = AppState.tokenUsage;
    var availableEl = document.getElementById('available-tokens');
    if (availableEl) {
        if (u == null) availableEl.textContent = '—';
        else if (u.limit === 0) availableEl.textContent = 'Ilimitado';
        else availableEl.textContent = Math.max(0, u.limit - u.used);
    }
    
    grid.innerHTML = visible.map(lead => getLeadCardHTML(lead)).join('');
    
    document.querySelectorAll('.btn-export').forEach(btn => {
        btn.addEventListener('click', () => { exportLead(btn.dataset.leadId); });
    });
    
    document.querySelectorAll('.btn-export-atendo').forEach(btn => {
        btn.addEventListener('click', () => { exportLeadToAtendo(btn.dataset.leadId); });
    });
    
    if (displayedLeads.length > AppState.visibleCount) {
        loadMoreContainer.classList.remove('hidden');
        loadMoreContainer.innerHTML = `
            <button id="btn-load-more" class="group relative px-8 py-4 bg-white border-2 border-slate-200 dark:border-slate-600 rounded-full shadow-lg hover:shadow-xl hover:border-blue-500 transition-all active:scale-95">
                <div class="flex items-center gap-3">
                    <div class="bg-slate-100 dark:bg-slate-700 rounded-full p-2 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/50 transition-colors">
                        <svg class="w-4 h-4 text-slate-600 dark:text-slate-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" /></svg>
                    </div>
                    <span class="text-xs font-black text-slate-700 dark:text-slate-300 uppercase tracking-widest group-hover:text-blue-700">
                        Carregar Mais Resultados (${displayedLeads.length - AppState.visibleCount} restantes)
                    </span>
                </div>
            </button>
        `;
        const loadMoreButton = document.getElementById('btn-load-more');
        if (loadMoreButton) {
            loadMoreButton.addEventListener('click', () => {
                AppState.visibleCount += 12;
                displayLeads();
            });
        }
    } else {
        loadMoreContainer.classList.add('hidden');
    }
}
async function refreshCurrentSearchWithMapsPilot() {
    if (!AppState.user) {
        showError('Faça login para atualizar a pesquisa Maps.');
        return;
    }
    if (!AppState.searchId) {
        showError('Abra uma pesquisa Maps salva antes de atualizar pelo piloto.');
        return;
    }

    const button = document.getElementById('btn-maps-pilot-refresh');
    const originalLabel = button ? button.textContent : '';
    if (button) {
        button.disabled = true;
        button.textContent = 'Atualizando...';
    }
    hideError();
    beginSearchLoading('Procurando novos leads', 'Estamos procurando leads adicionais para esta pesquisa e removendo os que você já recebeu.');

    try {
        const response = await fetch(API_BASE + 'maps-pilot.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ search_history_id: AppState.searchId, max_results: 0 })
        });
        const raw = await response.text();
        let payload = null;
        try { payload = raw ? JSON.parse(raw) : null; } catch (ignored) {}
        if (!response.ok || !payload || !payload.success) {
            throw new Error((payload && (payload.error || payload.message)) || 'A atualização do piloto não pôde ser concluída.');
        }

        const result = payload.data || {};
        AppState.leads = applyMapsPilotNewLeadState(result, Array.isArray(result.leads) ? result.leads : AppState.leads);
        AppState.visibleCount = Math.max(AppState.visibleCount || 12, AppState.leads.length);
        AppState.searchId = String(result.searchId || AppState.searchId);
        const billingNotice = String(result.billing_notice || '').trim();
        AppState.mapsPilotSummary = {
            searchId: AppState.searchId,
            text: (Number(result.leads_existing) || 0) + ' leads existentes | '
                + (Number(result.leads_new) || 0) + ' novos leads encontrados | '
                + (Number(result.credits_charged) || 0) + ' créditos consumidos nesta atualização'
                + (billingNotice ? ' | ' + billingNotice : ''),
            newLeadIds: AppState.mapsPilotNewLeadIds.slice()
        };

        const historyItem = (AppState.history || []).find(function(item) { return String(item.id) === String(AppState.searchId); });
        if (historyItem) {
            historyItem.leads = AppState.leads.slice();
            historyItem.resultsCount = Number(result.total_count) || AppState.leads.length;
            historyItem.mapsPilotNewLeadIds = AppState.mapsPilotNewLeadIds.slice();
            historyItem.mapsPilotSummary = Object.assign({}, AppState.mapsPilotSummary);
        }
        displayLeads();
        showToast(
            billingNotice || (result.leads_new > 0 ? '+' + result.leads_new + ' novos leads encontrados.' : 'Nenhum novo lead nesta atualização.'),
            result.leads_new > 0 ? 'success' : 'info'
        );
    } catch (error) {
        showError(error && error.message ? error.message : 'Não foi possível atualizar a pesquisa pela Coleta Atendo.');
    } finally {
        endSearchLoading();
        if (button) {
            button.disabled = false;
            button.textContent = originalLabel || 'Atualizar pesquisa Maps';
        }
    }
}

function formatMapsPilotDate(value) {
    if (!value) return 'Ainda não executada';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('pt-BR');
}

function renderMapsPilotMetrics(payload) {
    const container = document.getElementById('dashboard-maps-pilot-metrics') || document.getElementById('maps-pilot-live-metrics') || document.getElementById('api-maps-pilot-metrics');
    if (!container) return;
    const collector = (payload && payload.collector) || {};
    const platform = (payload && payload.platform) || {};
    const available = collector.status === 'online';
    const latest = platform.latest_search || null;
    container.innerHTML = `
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-black uppercase tracking-widest text-[10px]">Monitor operacional da API de Busca · super admin</p>
                <p class="mt-1 font-bold ${available ? 'text-emerald-700 dark:text-emerald-300' : 'text-amber-700 dark:text-amber-300'}">${collector.label || 'Status indisponível'} · ${collector.authentication || 'Validação protegida no servidor'}</p>
            </div>
            <span class="rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-wider ${available ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-200' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-200'}">${available ? 'Pronto para teste' : 'Verificar coletor'}</span>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3 md:grid-cols-4">
            <div><span class="block text-[10px] uppercase tracking-wider opacity-70">Limite efetivo</span><strong>${Number(collector.max_results_per_run) || 50} resultados</strong></div>
            <div><span class="block text-[10px] uppercase tracking-wider opacity-70">Buscas diretas</span><strong>${Number(platform.searches) || 0}</strong></div>
            <div><span class="block text-[10px] uppercase tracking-wider opacity-70">Leads retornados</span><strong>${Number(platform.leads) || 0}</strong></div>
            <div><span class="block text-[10px] uppercase tracking-wider opacity-70">Créditos protegidos</span><strong>${Number(platform.credits_protected) || 0}</strong></div>
        </div>
        ${latest ? `<p class="mt-3 border-t border-sky-200 pt-3 text-[11px] dark:border-sky-900/70">Última busca direta: <strong>${dashboardMetricEscape(latest.query || '—')}</strong> em <strong>${dashboardMetricEscape(latest.location || '—')}</strong> · ${Number(latest.results_count) || 0} resultados · ${formatMapsPilotDate(latest.created_at)}</p>` : '<p class="mt-3 border-t border-sky-200 pt-3 text-[11px] dark:border-sky-900/70">Nenhuma busca direta registrada ainda. As buscas seguem disponíveis normalmente.</p>'}
    `;
    container.classList.remove('hidden');
}

async function loadMapsPilotMetrics() {
    const container = document.getElementById('dashboard-maps-pilot-metrics') || document.getElementById('api-maps-pilot-metrics') || document.getElementById('maps-pilot-live-metrics');
    if (!container) return;
    container.innerHTML = '<p class="font-bold">Carregando métricas protegidas da API de Busca...</p>';
    try {
        const response = await fetch(API_BASE + 'maps-pilot-metrics.php', { credentials: 'include', cache: 'no-store', headers: { 'Accept': 'application/json' } });
        const raw = await response.text();
        let payload = null;
        try { payload = raw ? JSON.parse(raw) : null; } catch (ignored) {}
        if (!response.ok || !payload || !payload.success) throw new Error((payload && payload.error) || 'Métricas temporariamente indisponíveis.');
        AppState.mapsPilotMetrics = payload;
        renderMapsPilotMetrics(payload);
    } catch (error) {
        container.innerHTML = '<div class="flex flex-wrap items-center justify-between gap-3"><div><p class="font-black uppercase tracking-widest text-[10px]">Monitoramento operacional da API de Busca</p><p class="mt-1 font-bold text-amber-700 dark:text-amber-300">Métricas temporariamente indisponíveis</p><p class="mt-1 text-[11px] text-amber-700/90 dark:text-amber-200">A indisponibilidade do monitor não bloqueia as pesquisas Google Maps ou LinkedIn.</p></div><span class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-black uppercase tracking-wider text-amber-800 dark:bg-amber-900/50 dark:text-amber-200">Sem bloquear o sistema</span></div>';
    }
}

// A base técnica de IA permanece preservada para evolução futura, porém o
// produto atual opera exclusivamente com os fluxos de Google Maps e LinkedIn.
const MAPS_PROSPECTOR_AI_RESEARCH_ENABLED = false;
function disableAiResearchControls() {
    if (MAPS_PROSPECTOR_AI_RESEARCH_ENABLED) return;
    document.querySelectorAll('[data-feature="ai-search"], [data-tab="ai-search"], [data-tab="search-ai"], #nav-ai-search, #menu-ai-search, #btn-ai-search, #ai-search-panel').forEach(function(el) {
        el.classList.add('hidden');
        el.setAttribute('aria-hidden', 'true');
    });
}

async function runDirectMapsPilotSearch() {
    if (!AppState.user) {
        showError('Faça login para usar a busca Maps.');
        return;
    }
    const query = String((document.getElementById('search-query') || {}).value || '').trim();
    const location = String((document.getElementById('search-location') || {}).value || '').trim();
    const tag = String((document.getElementById('search-tag') || {}).value || '').trim();
    if (!query || !location) {
        showError('Informe a atividade e a cidade ou região para usar a busca Maps.');
        return;
    }

    const button = document.getElementById('btn-maps-pilot-direct');
    const originalLabel = button ? button.textContent : '';
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin mr-1.5"></span>Coletando...';
    }
    hideError();
    beginSearchLoading('Pesquisando empresas no Maps...', 'Estamos consultando os filtros informados e organizando os leads encontrados para você.');
    try {
        const response = await fetch(API_BASE + 'maps-pilot.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ action: 'direct', query: query, location: location, tag: tag, max_results: 0 })
        });
        const raw = await response.text();
        let payload = null;
        try { payload = raw ? JSON.parse(raw) : null; } catch (ignored) {}
        if (!response.ok || !payload || !payload.success) {
            throw new Error((payload && (payload.error || payload.message)) || 'A busca direta do piloto não pôde ser concluída.');
        }
        const result = payload.data || {};
        AppState.leads = applyMapsPilotNewLeadState(result, Array.isArray(result.leads) ? result.leads : []);
        AppState.visibleCount = Math.max(12, AppState.leads.length);
        AppState.searchId = String(result.searchId || '');
        AppState.currentSearch = { id: AppState.searchId, query: query, location: location, tag: tag };
        AppState.mapsPilotSummary = {
            searchId: AppState.searchId,
            text: (Number(result.leads_existing) || 0) + ' leads existentes | '
                + (Number(result.leads_new) || 0) + ' novos leads encontrados | '
                + (Number(result.credits_charged) || 0) + ' créditos consumidos nesta atualização'
                + (result.reused_cached_results ? ' | resultados já disponíveis reaproveitados com segurança' : '')
                + (result.billing_notice ? ' | ' + String(result.billing_notice) : ''),
            newLeadIds: AppState.mapsPilotNewLeadIds.slice()
        };
        if (!Array.isArray(AppState.history)) AppState.history = [];
        const existing = AppState.history.find(function(item) { return String(item.id) === String(AppState.searchId); });
        if (existing) {
            existing.leads = AppState.leads.slice();
            existing.resultsCount = Number(result.total_count) || AppState.leads.length;
            existing.mapsPilotNewLeadIds = AppState.mapsPilotNewLeadIds.slice();
            existing.mapsPilotSummary = Object.assign({}, AppState.mapsPilotSummary);
        }
        displayLeads();
        showToast(result.leads_new > 0 ? '+' + result.leads_new + ' novos leads adicionados à pesquisa.' : 'A pesquisa já estava atualizada.', result.leads_new > 0 ? 'success' : 'info');
        const pilotMetricsContainer = document.getElementById('maps-pilot-live-metrics');
        if (pilotMetricsContainer) {
            pilotMetricsContainer.innerHTML = '<p class="font-bold">Busca direta concluída.</p><p class="mt-1 text-[11px]">' + (Number(result.leads_new) || 0) + ' novo(s) lead(s) e ' + (Number(result.leads_existing) || 0) + ' lead(s) existente(s) nesta atualização. As métricas consolidadas ficam disponíveis no Dashboard.</p>';
            pilotMetricsContainer.classList.remove('hidden');
        }
    } catch (error) {
        showError(error && error.message ? error.message : 'Não foi possível executar a busca direta pela Coleta Atendo.');
    } finally {
        endSearchLoading();
        if (button) {
            button.disabled = false;
            button.textContent = originalLabel || 'Buscar no Maps';
        }
    }
}

// Compara os leads do banco (última pesquisa) com os já exibidos na sessão
// e reexibe os resultados marcando os novos com a tag "Novo".
async function runMapsCheckNewLeads() {
    if (!AppState.searchId) {
        showError('Abra uma pesquisa Maps salva antes de verificar novos leads.');
        return;
    }
    const button = document.getElementById('btn-maps-check-new-leads');
    const originalLabel = button ? button.textContent : '';
    if (button) {
        button.disabled = true;
        button.innerHTML = '<span class="inline-block w-3.5 h-3.5 border-2 border-white border-t-transparent rounded-full animate-spin mr-1.5"></span>Verificando...';
    }
    hideError();
    beginSearchLoading('Verificando novos leads', 'Estamos atualizando os dados desta pesquisa.');
    try {
        const response = await fetch(API_BASE + 'maps-pilot-new-leads.php?search_history_id=' + encodeURIComponent(AppState.searchId), {
            credentials: 'same-origin',
            cache: 'no-store'
        });
        const raw = await response.text();
        let payload = null;
        try { payload = raw ? JSON.parse(raw) : null; } catch (ignored) {}
        if (!response.ok || !payload || !payload.success) {
            throw new Error((payload && (payload.error || payload.message)) || 'Não foi possível verificar novos leads.');
        }
        const result = payload.data || {};
        const leads = Array.isArray(result.leads) ? result.leads : [];
        const newCount = Number(result.new_count || 0);
        const viewedBefore = (AppState.mapsViewedLeadIds || []).length;
        // Leads ainda não vistos na sessão viram "Novos"; os demais mantêm estado.
        const merged = leads.map(function(lead) {
            const copy = Object.assign({}, lead);
            const leadId = getMapsPilotLeadId(copy);
            copy.isPilotNew = (AppState.mapsViewedLeadIds || []).indexOf(leadId) === -1;
            return copy;
        });
        commitMapsViewedLeadIds(merged);
        AppState.leads = merged;
        AppState.visibleCount = Math.max(AppState.visibleCount || 12, AppState.leads.length);
        AppState.mapsPilotSummary = Object.assign({}, AppState.mapsPilotSummary || {}, {
            text: newCount + ' lead(s) novo(s) nesta pesquisa | ' + (AppState.leads.length - newCount) + ' lead(s) já exibidos antes'
        });
        const historyItem = (AppState.history || []).find(function(item) { return String(item.id) === String(AppState.searchId); });
        if (historyItem) {
            historyItem.leads = AppState.leads.slice();
            historyItem.mapsPilotSummary = AppState.mapsPilotSummary;
        }
        displayLeads();
        showToast(newCount > 0
            ? '+' + newCount + ' lead(s) novo(s) encontrado(s) no banco. Os novos estão marcados com a tag "Novo".'
            : 'A pesquisa já está atualizada. Nenhum lead novo nesta execução.');
    } catch (error) {
        showError(error && error.message ? error.message : 'Não foi possível verificar novos leads.');
    } finally {
        endSearchLoading();
        if (button) {
            button.disabled = false;
            button.textContent = originalLabel || 'Verificar novos leads';
        }
    }
}
// Gera o layout HTML do card de lead (dados completos sempre visíveis)
function getLeadCardHTML(lead) {
    const phoneDisplay = formatPhoneDisplay(getLeadPhoneValue(lead));
    const isPilotNew = lead && lead.isPilotNew === true;
    return `
        <div class="relative mt-4 pt-10 pb-6 px-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-[1.5rem] hover:shadow-2xl hover:shadow-blue-900/5 transition-all duration-300 flex flex-col justify-between group">
            <div class="absolute -top-3 left-6 bg-blue-600 text-white text-[9px] font-black py-1.5 px-3 rounded-lg uppercase tracking-wider shadow-lg shadow-blue-200 z-10">
                ${lead.partners ? 'Dados Ricos' : 'Lead'}
            </div>
            ${isPilotNew ? '<div class="absolute -top-3 right-6 bg-emerald-600 text-white text-[9px] font-black py-1.5 px-3 rounded-lg uppercase tracking-wider shadow-lg shadow-emerald-200 z-10">Novo</div>' : ''}
            <div>
                <h3 class="font-extrabold text-slate-900 dark:text-slate-100 text-sm uppercase leading-snug mb-4 min-h-[2.5rem]">${lead.name || ''}</h3>
                <div class="space-y-3 mb-6">
                    <div class="flex items-center gap-3 bg-emerald-50/50 dark:bg-emerald-950/20 p-2 rounded-lg -mx-2">
                        <span class="text-emerald-500 dark:text-emerald-400 text-xs">📞</span>
                        <span class="text-emerald-700 dark:text-emerald-300 font-bold text-xs">${phoneDisplay || 'Sem telefone'}</span>
                    </div>
                    ${lead.partners ? `
                        <div class="flex items-start gap-3">
                            <span class="text-purple-400 text-xs mt-0.5">👥</span>
                            <div class="flex flex-col">
                                <span class="text-[9px] font-black text-slate-400 uppercase">Sócios/Resp.</span>
                                <p class="text-slate-700 dark:text-slate-300 font-bold text-[10px] leading-tight">${lead.partners}</p>
                            </div>
                        </div>
                    ` : ''}
                    ${lead.cnpj ? `
                        <div class="flex items-center gap-3">
                            <span class="text-amber-400 text-xs">🏢</span>
                            <div class="flex flex-col">
                                <span class="text-[9px] font-black text-slate-400 uppercase">CNPJ</span>
                                <span class="text-slate-700 dark:text-slate-300 font-mono font-bold text-[10px]">${lead.cnpj}</span>
                            </div>
                        </div>
                    ` : ''}
                    ${lead.email ? `
                        <div class="flex items-center gap-3">
                            <span class="text-blue-400 text-xs">✉️</span>
                            <div class="flex flex-col overflow-hidden">
                                <span class="text-[9px] font-black text-slate-400 uppercase">Email</span>
                                <a href="mailto:${lead.email}" class="text-blue-600 dark:text-blue-400 font-bold text-[10px] hover:underline truncate w-full block">${lead.email}</a>
                            </div>
                        </div>
                    ` : ''}
                    ${lead.website ? `
                        <div class="flex items-center gap-3">
                            <span class="text-slate-500 dark:text-slate-400 text-xs">🌐</span>
                            <div class="flex flex-col overflow-hidden">
                                <span class="text-[9px] font-black text-slate-400 uppercase">Site</span>
                                <a href="${lead.website}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 font-bold text-[10px] hover:underline truncate w-full block">${lead.website}</a>
                            </div>
                        </div>
                    ` : ''}
                    <div class="flex items-start gap-3 pt-2 border-t border-slate-100 dark:border-slate-700">
                        <span class="text-rose-400 text-xs mt-0.5">📍</span>
                        <p class="text-slate-500 dark:text-slate-400 font-semibold text-[10px] leading-relaxed line-clamp-2">${lead.address || ''}</p>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-3 pt-4 border-t border-slate-100 dark:border-slate-700 mt-auto">
                <button class="btn-export flex-grow bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-900 dark:text-slate-100 hover:bg-slate-50 dark:hover:bg-slate-600 hover:border-slate-300 font-black text-[10px] py-3 rounded-xl uppercase tracking-wide transition-all shadow-sm active:scale-95 disabled:opacity-50 flex justify-center items-center gap-2" data-lead-id="${lead.id}">
                    Exportar WEBHOOK
                </button>
                ${lead.mapsUri ? `
                    <a href="${lead.mapsUri}" target="_blank" rel="noopener noreferrer" class="w-12 h-[38px] flex-shrink-0 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-blue-500 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-slate-600 hover:border-blue-200 rounded-xl flex items-center justify-center transition-all shadow-sm" title="Ver no Google Maps">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M18.7 3.8C15 .1 9 .1 5.3 3.8c-3.7 3.7-3.7 9.8 0 13.5L12 24l6.7-6.7c3.7-3.7 3.7-9.8 0-13.5zm-6.7 10c-1.9 0-3.5-1.6-3.5-3.5s1.6-3.5 3.5-3.5 3.5 1.6 3.5 3.5-1.6 3.5-3.5 3.5z"/></svg>
                    </a>
                ` : ''}
            </div>
        </div>
    `;
}

async function exportLead(leadId) {
    const lead = AppState.leads.find(l => l.id === leadId);
    if (!lead) {
        alert('Lead não encontrado');
        return;
    }
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
var isHistoryLoading = false; // Impede loops de requisições concorrentes

async function loadHistory() {
    if (isHistoryLoading) return;
    isHistoryLoading = true;

    const contentArea = document.getElementById('content-area');
    contentArea.innerHTML = '<div class="max-w-5xl mx-auto"><div class="text-center py-10"><div class="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto"></div></div></div>';
    
    try {
        const res = await fetch(API_BASE + 'history.php');
        if (res.status === 507 || res.status === 508) {
            contentArea.innerHTML = '<div class="max-w-5xl mx-auto"><div class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm"><h3 class="text-lg font-bold text-slate-800">Histórico temporariamente indisponível</h3><p class="mt-2 text-sm text-slate-500">Não foi possível consultar o histórico neste momento. Tente novamente mais tarde.</p></div></div>';
            return;
        }
        const text = await res.text();
        if (!text || text.trim() === '') throw new Error('Resposta vazia do servidor');
        
        // Verifica se a resposta não é uma página HTML de erro antes de parsear
        if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html')) {
            throw new Error('O servidor retornou uma página de erro HTML em vez de dados JSON.');
        }

        const data = JSON.parse(text);
        if (data.success) {
            AppState.history = data.data || [];
            displayHistory();
        } else {
            throw new Error(data.error || 'Erro desconhecido');
        }
    } catch (e) {
        console.error('History error:', e);
        contentArea.innerHTML = `<div class="max-w-5xl mx-auto"><div class="bg-red-50 p-5 rounded-2xl text-red-700">Erro ao carregar histórico: ${e.message}</div></div>`;
    } finally {
        isHistoryLoading = false;
    }
}

function formatHistoryTimestamp(ts) {
    if (!ts) return '—';
    try {
        var d = new Date(ts);
        if (isNaN(d.getTime())) return '—';
        var day = String(d.getDate()).padStart(2, '0');
        var month = String(d.getMonth() + 1).padStart(2, '0');
        var year = d.getFullYear();
        var h = String(d.getHours()).padStart(2, '0');
        var m = String(d.getMinutes()).padStart(2, '0');
        return day + '/' + month + '/' + year + ' ' + h + ':' + m;
    } catch (e) { return '—'; }
}

function getHistoryItemCount(item) {
    return item.resultsCount ?? item.results_count ?? (item.leads && item.leads.length) ?? 0;
}

function applyHistoryFilters(history, searchText, dateFrom, dateTo) {
    var search = (searchText || '').trim().toLowerCase();
    var from = (dateFrom || '').trim();
    var to = (dateTo || '').trim();
    return history.filter(function(item) {
        if (search) {
            var searchable = [item.query, item.location, item.tag, item.companyName].filter(Boolean).join(' ').toLowerCase();
            (item.leads || []).forEach(function (lead) {
                searchable += ' ' + [lead.name, lead.cnpj, lead.email, lead.phone, lead.address, lead.website].filter(Boolean).join(' ').toLowerCase();
            });
            if (searchable.indexOf(search) === -1) return false;
        }
        var ts = item.timestamp;
        if (ts && (from || to)) {
            try {
                var d = new Date(ts);
                if (isNaN(d.getTime())) return false;
                var dateStr = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
                if (from && dateStr < from) return false;
                if (to && dateStr > to) return false;
            } catch (e) { return false; }
        }
        return true;
    });
}

function renderHistoryList(listEl, filtered) {
    listEl.innerHTML = filtered.map(function(item) {
        var count = getHistoryItemCount(item);
        var dateTime = formatHistoryTimestamp(item.timestamp);
        var tagLabel = (item.tag && item.tag.trim()) ? item.tag.trim() : '—';
        var isCnpjHistory = String(item.tag || '').toUpperCase() === 'CNPJ_AVANCADA';
        var title = item.companyName || item.query || '';
        var queryLabel = item.companyName && isCnpjHistory ? '<span class="ml-2 text-slate-500"> · CNPJ: ' + escapeHtml(String(item.query || '').replace(/\D/g, '').slice(0, 14)) + '</span>' : '';
        return '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 flex items-center justify-between group hover:border-blue-300 transition-all hover:shadow-md">' +
            '<div class="flex items-center gap-6">' +
            '<div class="w-12 h-12 bg-slate-50 dark:bg-slate-800/50 rounded-xl flex items-center justify-center text-xl">🔎</div>' +
            '<div>' +
            '<h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg capitalize">' + escapeHtml(title) + '</h4>' +
            '<p class="text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-wider">📍 ' + escapeHtml(item.location || 'Local Automático') + ' <span class="ml-2">🏷 ' + escapeHtml(tagLabel) + '</span>' + queryLabel + ' <span class="ml-2 text-slate-500">' + escapeHtml(dateTime) + '</span> <span class="ml-2 text-blue-500">(' + count + ' leads)</span></p>' +
            '</div></div>' +
            '<div class="flex items-center gap-2">' +
            '<button class="btn-use-history bg-slate-900 text-white font-bold px-6 py-3 rounded-2xl text-xs hover:bg-blue-600 transition-colors" data-history-id="' + item.id + '">Ver Novamente</button>' +
            '</div>' +
            '</div>';
    }).join('');
    
    listEl.querySelectorAll('.btn-use-history').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var historyId = btn.dataset.historyId;
            var item = AppState.history.find(function(h) { return h.id == historyId; });
            
            if (item) {
                // Se for uma Consulta de CNPJ
                if (item.tag === 'CNPJ_AVANCADA' || (item.query && item.query.includes('Consulta CNPJ:'))) {
                    // Extrai apenas os números do CNPJ salvo na string "Consulta CNPJ: 12345678000190"
                    var cnpjBuscado = item.query.replace(/\D/g, ''); 
                    
                    if (cnpjBuscado && cnpjBuscado.length >= 14) {
                        setActiveTab('cnpj-lookup'); // Abre a aba de Consulta CNPJ
                        setTimeout(function() {
                            var cnpjInput = document.getElementById('cnpj-simple-input');
                            if (cnpjInput) {
                                cnpjInput.value = cnpjBuscado; // Preenche o campo
                                performCnpjSimpleLookup(); // Força a busca automaticamente
                            }
                        }, 200);
                    } else {
                        showToast('O CNPJ desta consulta não pode ser lido corretamente.', 'warning');
                    }
                } 
                // Se for uma busca comum do Google Maps
                else {
                    AppState.searchId = String(item.id);
                    AppState.mapsPilotNewLeadIds = Array.isArray(item.mapsPilotNewLeadIds) ? item.mapsPilotNewLeadIds.map(function(id) { return String(id); }) : [];
                    AppState.mapsPilotShowOnlyNew = false;
                    AppState.leads = (item.leads || []).map(function(l) {
                        const lead = Object.assign({}, l);
                        lead.isPilotNew = AppState.mapsPilotNewLeadIds.indexOf(getMapsPilotLeadId(lead)) !== -1;
                        return lead;
                    });
                    AppState.currentSearch = { query: item.query || '', location: item.location || '', tag: item.tag || '' };
                    AppState.mapsPilotSummary = item.mapsPilotSummary || null;
                    setActiveTab('search'); // Abre a aba de Prospecção
                    setTimeout(function() {
                        if (AppState.activeTab === 'search' && document.getElementById('leads-grid')) {
                            displayLeads();
                        }
                    }, 100);
                }
            }
        });
    });
}
function displayHistory() {
    const contentArea = document.getElementById('content-area');
    if (AppState.history.length === 0) {
        contentArea.innerHTML = `
            <div class="max-w-5xl mx-auto">
                <div class="py-24 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]">
                    <p class="text-slate-400 font-bold uppercase text-[10px]">Nenhum registro encontrado</p>
                </div>
            </div>
        `;
        return;
    }
    contentArea.innerHTML = `
        <div class="max-w-5xl mx-auto">
            <div class="bg-slate-50 dark:bg-slate-800/50 p-5 rounded-2xl border border-slate-200 dark:border-slate-600 mb-6">
                <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-3">Buscar resultados</label>
                <div class="flex flex-wrap gap-3 items-end">
                    <div class="flex-1 min-w-[200px]">
                        <input id="history-search" type="text" class="w-full px-4 py-2.5 rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-900 dark:text-slate-100 font-bold text-sm placeholder-slate-400 focus:border-blue-500 outline-none" placeholder="Termo, local ou tag">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">De</label>
                        <input id="history-date-from" type="date" class="px-4 py-2.5 rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-900 dark:text-slate-100 font-bold text-sm focus:border-blue-500 outline-none">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">Até</label>
                        <input id="history-date-to" type="date" class="px-4 py-2.5 rounded-xl bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-900 dark:text-slate-100 font-bold text-sm focus:border-blue-500 outline-none">
                    </div>
                </div>
            </div>
            <div class="flex justify-between items-center mb-10">
                <div><h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Histórico Recente</h3></div>
                <button id="btn-clear-history" class="text-xs font-extrabold text-red-500 hover:bg-red-50 px-5 py-2 rounded-xl">Limpar Tudo</button>
            </div>
            <div class="space-y-4" id="history-list"></div>
        </div>
    `;
    var listEl = document.getElementById('history-list');
    function refreshFilteredList() {
        var searchVal = (document.getElementById('history-search') && document.getElementById('history-search').value) || '';
        var fromVal = (document.getElementById('history-date-from') && document.getElementById('history-date-from').value) || '';
        var toVal = (document.getElementById('history-date-to') && document.getElementById('history-date-to').value) || '';
        var filtered = applyHistoryFilters(AppState.history, searchVal, fromVal, toVal);
        renderHistoryList(listEl, filtered);
    }
    refreshFilteredList();
    var searchInput = document.getElementById('history-search');
    var dateFromInput = document.getElementById('history-date-from');
    var dateToInput = document.getElementById('history-date-to');
    if (searchInput) searchInput.addEventListener('input', refreshFilteredList);
    if (dateFromInput) dateFromInput.addEventListener('change', refreshFilteredList);
    if (dateToInput) dateToInput.addEventListener('change', refreshFilteredList);
    
    document.getElementById('btn-clear-history').addEventListener('click', async () => {
        if (confirm('Deseja apagar o histórico de buscas?')) {
            try {
                const res = await fetch(API_BASE + 'history.php', { method: 'DELETE' });
                const data = await res.json();
                if (data.success) {
                    AppState.history = [];
                    displayHistory();
                }
            } catch (e) { alert('Erro ao limpar histórico: ' + e.message); }
        }
    });
}

// Carregar pastas do usuário
async function loadFolders() {
    try {
        const res = await fetch(API_BASE + 'folders.php');
        const data = await res.json();
        if (data.success) {
            AppState.folders = data.data || [];
            updateFolderSelect();
        }
    } catch (e) { console.error('Erro ao carregar pastas:', e); }
}

function updateFolderSelect() {
    const folderSelect = document.getElementById('search-folder');
    if (!folderSelect) return;
    const folderOptions = AppState.folders.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
    folderSelect.innerHTML = '<option value="">Sem pasta</option>' + folderOptions;
}

// Criar nova pasta
async function createFolder(name) {
    try {
        const res = await fetch(API_BASE + 'folders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });
        const data = await res.json();
        if (data.success) {
            await loadFolders();
            return data.data;
        }
    } catch (e) { console.error('Erro ao criar pasta:', e); }
    return null;
}
function getApiBuscaHTML() {
    const isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';

    if (!isSuperAdmin) {
        return `
            <div class="max-w-3xl mx-auto py-20 text-center">
                <div class="bg-amber-50 p-6 rounded-2xl border border-amber-200 dark:bg-amber-900/10 dark:border-amber-800">
                    <h3 class="text-amber-800 dark:text-amber-400 font-bold">Acesso Restrito</h3>
                    <p class="text-amber-600 dark:text-amber-500 text-sm">Esta área é exclusiva para o administrador da plataforma.</p>
                </div>
            </div>
        `;
    }

    return `
        <div class="max-w-4xl mx-auto space-y-8 pb-20">
            <div class="bg-white dark:bg-slate-800 p-10 rounded-[2.5rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-8 tracking-tight">Gerenciador de chaves de pesquisa (rotação automática)</h3>
                <div class="space-y-8">
                    
                    <!-- Formulário para Adicionar Nova Chave -->
                    <div class="bg-[#0F172A] p-6 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-purple-600/10 rounded-full blur-3xl"></div>
                        <h4 class="font-black text-lg mb-3 flex items-center gap-2">➕ Adicionar nova chave de pesquisa</h4>
                        <p class="text-xs text-slate-400 mb-4">Cadastre múltiplas chaves. O sistema fará a rotação automática entre elas a cada busca realizada.</p>
                        
                        <form id="form-add-apify-key" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-300 uppercase mb-1 ml-1">Identificação (Apelido)</label>
                                    <input id="apify-key-label" type="text" required class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-purple-500 font-bold text-white text-xs" placeholder="Ex.: Conta de pesquisa 01">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-300 uppercase mb-1 ml-1">Token / chave de pesquisa</label>
                                    <input id="apify-key-token" type="password" required class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-purple-500 font-bold text-white text-xs" placeholder="Cole o token fornecido pelo serviço de pesquisa">
                                </div>
                            </div>
                            <button type="submit" id="btn-save-apify-key" class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white font-black rounded-xl transition shadow-lg text-xs uppercase tracking-wider">Salvar Chave</button>
                        </form>
                    </div>

                    <!-- Tabela de Chaves Cadastradas -->
                    <div class="bg-slate-50 dark:bg-slate-700/30 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-700">
                        <h4 class="font-black text-lg mb-4 text-slate-900 dark:text-white">Chaves Cadastradas no Sistema</h4>
                        <div id="apify-keys-list-container" class="overflow-x-auto">
                            <p class="text-xs text-slate-500 text-center py-4">Carregando chaves...</p>
                        </div>
                    </div>

                    <div id="apify-usage-panel" class="bg-slate-50 dark:bg-slate-700/30 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-700"></div>

                    <div id="api-maps-pilot-metrics" class="rounded-[2rem] border border-sky-200 bg-sky-50 px-6 py-5 text-xs text-sky-900 dark:border-sky-900/70 dark:bg-sky-950/35 dark:text-sky-100">
                        <p class="font-bold">Carregando métricas protegidas da Coleta Atendo…</p>
                    </div>

                    <!-- TOKEN MAPBOX -->
                    <div id="block-mapbox-token" class="bg-[#0F172A] p-6 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-green-600/10 rounded-full blur-3xl"></div>
                        <h4 class="font-black text-lg mb-3 flex items-center gap-2">Token Mapbox (GeoSearch)</h4>
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-2 ml-1">Mapbox Access Token</label>
                            <input id="setting-mapbox-token" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl px-6 py-4 outline-none focus:border-green-500 font-bold text-white placeholder:text-slate-500" placeholder="pk.eyJ1Ijoi...">
                        </div>
                    </div>

                    <!-- URL DE CNPJ -->
                    <div class="bg-[#0F172A] p-6 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-600/10 rounded-full blur-3xl"></div>
                        <h4 class="font-black text-lg mb-3 flex items-center gap-2">🏢 URL de Consulta de CNPJ</h4>
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">URL da API de CNPJ</label>
                            <input id="setting-cnpj-url" type="url" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none font-semibold text-white" placeholder="https://publica.cnpj.ws/cnpj/{cnpj}" />
                            <span class="text-[10px] text-slate-400 mt-1 block">Você pode usar {cnpj} como marcador onde o número será inserido.</span>
                        </div>
                    </div>

                    <button id="btn-save-settings" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-[1.5rem] shadow-xl transition-all active:scale-[0.98]">Salvar Outras Configurações</button>
                </div>
            </div>
        </div>
    `;
}

// Garante visibilidade global
window.getApiBuscaHTML = getApiBuscaHTML;

function setupApiBuscaEvents() {
    loadApifyKeysList();

    const btnSaveKey = document.getElementById('btn-save-apify-key');
    if (btnSaveKey) {
        btnSaveKey.onclick = async function(e) {
            e.preventDefault(); // 💡 Interrompe qualquer comportamento padrão
            e.stopPropagation();

            const labelInput = document.getElementById('apify-key-label');
            const tokenInput = document.getElementById('apify-key-token');
            const btn = document.getElementById('btn-save-apify-key');

            const label = labelInput ? labelInput.value.trim() : '';
            const apiKey = tokenInput ? tokenInput.value.trim() : '';

            if (!apiKey) {
                alert('Por favor, informe a chave de pesquisa.');
                return;
            }

            btn.disabled = true;
            btn.textContent = 'Salvando...';

            try {
                const res = await fetch(API_BASE + 'apify-keys.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    credentials: 'include',
                    body: JSON.stringify({ action: 'create', label: label || 'Chave de pesquisa', api_key: apiKey })
                });
                const data = await res.json();
                
                if (data.success) {
                    if (labelInput) labelInput.value = '';
                    if (tokenInput) tokenInput.value = '';
                    showToast('Chave de pesquisa adicionada com sucesso!');
                    loadApifyKeysList(); // 💡 Atualiza a tabela na mesma hora sem refresh
                } else {
                    alert(data.error || 'Erro ao salvar chave.');
                }
            } catch (err) {
                alert('Erro de conexão: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Salvar Chave';
            }
        };
    }

    var btnSave = document.getElementById('btn-save-settings');
    if (btnSave) {
        btnSave.onclick = function(e) {
            e.preventDefault();
            saveSettings();
        };
    }
}

async function loadApifyKeysList() {
    const container = document.getElementById('apify-keys-list-container');
    if (!container) return;

    try {
        const res = await fetch(API_BASE + 'apify-keys.php', { credentials: 'include' });
        const data = await res.json();

        if (data.success && Array.isArray(data.data)) {
            window.__apifyKeysCache = data.data;
            if (data.data.length === 0) {
                container.innerHTML = `<p class="text-xs text-slate-500 dark:text-slate-400 text-center py-6 font-bold">Nenhuma chave de pesquisa cadastrada. O sistema usará a chave padrão se houver.</p>`;
                return;
            }

            let html = `
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-600 text-[10px] font-black text-slate-400 uppercase">
                            <th class="py-3 px-4">Identificação</th>
                            <th class="py-3 px-4">Chave Mascarada</th>
                            <th class="py-3 px-4 text-center">Buscas Realizadas</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700 text-xs">
            `;

            data.data.forEach(k => {
                const isActive = k.is_active == 1;
                html += `
                    <tr class="hover:bg-slate-100/50 dark:hover:bg-slate-700/30">
                        <td class="py-3 px-4 font-bold text-slate-800 dark:text-slate-200">${escapeHtml(k.label || 'Chave sem identificação')}</td>
                        <td class="py-3 px-4 font-mono text-slate-500">${k.masked_key}</td>
                        <td class="py-3 px-4 text-center font-black text-blue-600 dark:text-blue-400">${k.tokens_used}</td>
                        <td class="py-3 px-4 text-center">
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold ${isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'}">
                                ${isActive ? 'Ativa' : 'Inativa'}
                            </span>
                        </td>
                        <td class="py-3 px-4 text-right">
                            <div class="flex flex-wrap justify-end gap-2">
                            <button type="button" onclick="editApifyKey('${k.id}')" class="px-3 py-1.5 rounded-lg font-bold bg-blue-100 text-blue-800 hover:bg-blue-200">
                                Editar
                            </button>
                            <button type="button" onclick="toggleApifyKey('${k.id}', '${isActive ? 0 : 1}')" class="px-3 py-1.5 rounded-lg font-bold ${isActive ? 'bg-amber-100 text-amber-800 hover:bg-amber-200' : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'}">
                                ${isActive ? 'Desativar' : 'Ativar'}
                            </button>
                            <button type="button" onclick="deleteApifyKey('${k.id}')" class="px-3 py-1.5 rounded-lg font-bold bg-red-100 text-red-700 hover:bg-red-200">
                                Excluir
                            </button>
                            </div>
                        </td>
                    </tr>
                `;
            });

            html += `</tbody></table>`;
            container.innerHTML = html;
        } else {
            container.innerHTML = `<p class="text-xs text-red-500 text-center py-4">Erro ao carregar lista de chaves.</p>`;
        }
    } catch (e) {
        container.innerHTML = `<p class="text-xs text-red-500 text-center py-4">Erro de conexão.</p>`;
    }
}

function closeApifyKeyEditModal() {
    const modal = document.getElementById('apify-key-edit-modal');
    if (modal) modal.remove();
}

function editApifyKey(id) {
    const key = (window.__apifyKeysCache || []).find(item => String(item.id) === String(id));
    if (!key) {
        alert('Não foi possível localizar a chave para edição. Atualize a lista e tente novamente.');
        return;
    }

    closeApifyKeyEditModal();
    const label = escapeHtml(key.label || '');
    document.body.insertAdjacentHTML('beforeend', `
        <div id="apify-key-edit-modal" class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/60 px-4 py-6" role="dialog" aria-modal="true" aria-labelledby="apify-key-edit-title">
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-2xl dark:bg-slate-800">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 id="apify-key-edit-title" class="text-xl font-black text-slate-900 dark:text-white">Editar chave de pesquisa</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Atualize a identificação ou substitua o token desta conta.</p>
                    </div>
                    <button type="button" id="apify-key-edit-close" class="rounded-xl px-3 py-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Fechar">×</button>
                </div>
                <form id="apify-key-edit-form" class="mt-6 space-y-4">
                    <div>
                        <label for="apify-key-edit-label" class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">Identificação</label>
                        <input id="apify-key-edit-label" type="text" maxlength="120" required value="${label}" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label for="apify-key-edit-token" class="mb-1 block text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">Novo token <span class="font-normal normal-case tracking-normal">(opcional)</span></label>
                        <input id="apify-key-edit-token" type="password" autocomplete="new-password" placeholder="Deixe em branco para manter o token atual" class="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-white">
                        <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Por segurança, o token atual nunca é exibido. Informe um novo somente se precisar substituí-lo.</p>
                    </div>
                    <div class="flex flex-wrap justify-end gap-2 pt-2">
                        <button type="button" id="apify-key-edit-cancel" class="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Cancelar</button>
                        <button type="submit" id="apify-key-edit-submit" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700">Salvar alterações</button>
                    </div>
                </form>
            </div>
        </div>
    `);

    const modal = document.getElementById('apify-key-edit-modal');
    const form = document.getElementById('apify-key-edit-form');
    const closeButton = document.getElementById('apify-key-edit-close');
    const cancelButton = document.getElementById('apify-key-edit-cancel');
    const submitButton = document.getElementById('apify-key-edit-submit');
    const labelInput = document.getElementById('apify-key-edit-label');
    const tokenInput = document.getElementById('apify-key-edit-token');
    if (!modal || !form || !labelInput || !tokenInput) return;

    closeButton.onclick = closeApifyKeyEditModal;
    cancelButton.onclick = closeApifyKeyEditModal;
    modal.addEventListener('click', event => {
        if (event.target === modal) closeApifyKeyEditModal();
    });
    labelInput.focus();

    form.onsubmit = async function (event) {
        event.preventDefault();
        const nextLabel = labelInput.value.trim();
        const nextToken = tokenInput.value.trim();
        if (!nextLabel) {
            alert('Informe uma identificação para a chave.');
            labelInput.focus();
            return;
        }
        if (nextToken && !confirm('Você deseja substituir o token desta chave? O novo token será usado nas próximas consultas.')) {
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Salvando...';
        try {
            const res = await fetch(API_BASE + 'apify-keys.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ action: 'update', id: Number(id), label: nextLabel, api_key: nextToken })
            });
            const data = await res.json();
            if (!res.ok || !data.success) throw new Error(data.error || 'Não foi possível atualizar a chave.');
            closeApifyKeyEditModal();
            showToast(data.message || 'Chave atualizada com sucesso!');
            await loadApifyKeysList();
        } catch (error) {
            alert(error.message || 'Erro de conexão.');
        } finally {
            const currentSubmit = document.getElementById('apify-key-edit-submit');
            if (currentSubmit) {
                currentSubmit.disabled = false;
                currentSubmit.textContent = 'Salvar alterações';
            }
        }
    };
}

async function toggleApifyKey(id, newState) {
    try {
        const res = await fetch(API_BASE + 'apify-keys.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ action: 'toggle', id, is_active: newState })
        });
        const data = await res.json();
        if (data.success) {
            loadApifyKeysList();
        } else {
            alert(data.error || 'Erro ao alterar status.');
        }
    } catch (e) {
        alert('Erro de conexão.');
    }
}

async function deleteApifyKey(id) {
    if (!confirm('Tem certeza que deseja excluir esta chave de pesquisa?')) return;

    try {
        const res = await fetch(API_BASE + 'apify-keys.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id })
        });
        const data = await res.json();
        if (data.success) {
            showToast('Chave removida com sucesso!');
            loadApifyKeysList();
        } else {
            alert(data.error || 'Erro ao excluir chave.');
        }
    } catch (e) {
        alert('Erro de conexão.');
    }
}

// Torna global
window.loadApifyKeysList = loadApifyKeysList;
window.toggleApifyKey = toggleApifyKey;
window.deleteApifyKey = deleteApifyKey;
window.editApifyKey = editApifyKey;
window.closeApifyKeyEditModal = closeApifyKeyEditModal;

function loadSettingsForm() {
    var cfg = AppState.config || {};
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';

    if (!isSuperAdmin) {
        var tenantNameEl = document.getElementById('setting-tenant-name');
        if (tenantNameEl) {
            tenantNameEl.value = cfg.tenantName || (AppState.tenant && AppState.tenant.name) || '';
        }
        var companyWebhookEl = document.getElementById('setting-company-webhook-url');
        if (companyWebhookEl) {
            companyWebhookEl.value = cfg.companyWebhookUrl || '';
        }
        var personalWebhookEl = document.getElementById('setting-personal-webhook-url');
        if (personalWebhookEl) {
            personalWebhookEl.value = cfg.personalWebhookUrl || cfg.webhookUrl || cfg.baseUrl || '';
        }
        return;
    }
    
    var map = {
        'setting-tenant': cfg.tenantName,
        'setting-url': cfg.atendoWaBaseUrl || cfg.baseUrl || '',
        'setting-atendo-wa-admin-number': cfg.atendoWaAdminNumber || cfg.whatsappAdminNumber || '551731981705',
        'setting-cnpj-url': cfg.cnpjApiUrl || 'https://publica.cnpj.ws/cnpj/{cnpj}'
    };

    Object.keys(map).forEach(function(id) {
        var el = document.getElementById(id);
        if (el && map[id] !== undefined) {
            el.value = map[id] || '';
        }
    });

    var platformWebhookEl = document.getElementById('setting-platform-webhook-url');
    if (platformWebhookEl) {
        platformWebhookEl.value = cfg.platformWebhookUrl || '';
    }

    // Nunca devolve o token completo para o campo do navegador.
    // Exibe apenas a máscara retornada pelo backend e marca o campo para que
    // essa máscara nunca seja enviada como se fosse um novo token.
    var tokenEl = document.getElementById('setting-token');
    if (tokenEl) {
        var maskedToken = cfg.atendoWaTokenMasked || cfg.tokenMasked || '';
        if (maskedToken) {
            tokenEl.value = maskedToken;
            tokenEl.dataset.tokenMasked = 'true';
            tokenEl.placeholder = 'Token configurado — digite um novo para substituir';
            tokenEl.setAttribute('aria-label', 'Token Bearer configurado, exibido de forma mascarada');
        } else {
            tokenEl.value = '';
            tokenEl.dataset.tokenMasked = 'false';
            tokenEl.placeholder = 'Cole um novo token Bearer da API Atendo';
            tokenEl.setAttribute('aria-label', 'Novo token Bearer da API Atendo');
        }
    }

    // As credenciais do Asaas nunca retornam em texto puro. Quando existirem,
    // a máscara exibida confirma que o valor permaneceu armazenado após salvar
    // ou atualizar a página, sem permitir que a máscara seja reenviada como chave.
    var asaasKeyEl = document.getElementById('saas-config-asaas-apikey');
    var asaasWebhookTokenEl = document.getElementById('saas-config-asaas-webhook-token');
    var asaasKeyMasked = cfg.asaasApiKeyMasked || '';
    var asaasWebhookTokenMasked = cfg.asaasWebhookTokenMasked || '';
    if (asaasKeyEl) {
        asaasKeyEl.value = asaasKeyMasked;
        asaasKeyEl.dataset.asaasMasked = asaasKeyMasked ? 'true' : 'false';
        asaasKeyEl.placeholder = asaasKeyMasked ? 'Chave configurada — digite outra somente para substituir' : 'Cole a chave de Integrações do painel Asaas';
    }
    if (asaasWebhookTokenEl) {
        asaasWebhookTokenEl.value = asaasWebhookTokenMasked;
        asaasWebhookTokenEl.dataset.asaasMasked = asaasWebhookTokenMasked ? 'true' : 'false';
        asaasWebhookTokenEl.placeholder = asaasWebhookTokenMasked ? 'Token configurado — digite outro somente para substituir' : 'Deixe vazio para gerar automaticamente';
    }
    var asaasWebhookUrlEl = document.getElementById('asaas-settings-webhook-url');
    if (asaasWebhookUrlEl) asaasWebhookUrlEl.textContent = cfg.asaasWebhookUrl || 'URL indisponível';
    var asaasActiveEl = document.getElementById('saas-config-asaas-active');
    if (asaasActiveEl) asaasActiveEl.checked = !!cfg.asaasActive;
    AppState._asaasWebhookToken = '';
    var asaasStatusEl = document.getElementById('setting-asaas-status');
    if (asaasStatusEl) {
        var asaasConfigured = !!(cfg.asaasApiKeyConfigured || asaasKeyMasked);
        var asaasActive = !!cfg.asaasActive;
        asaasStatusEl.textContent = asaasConfigured
            ? (asaasActive ? 'Asaas configurado e cobrança automática ATIVA. A chave permanece protegida no servidor.' : 'Asaas configurado, mas cobrança automática DESATIVADA. O sistema seguirá por aprovação manual.')
            : 'Nenhuma chave Asaas configurada. O sistema seguirá automaticamente por aprovação manual.';
        asaasStatusEl.className = 'text-[11px] font-semibold mt-3 ' + (asaasActive ? 'text-emerald-300' : 'text-amber-300');
    }
}


function getSettingsHTML() {
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';

    if (!isSuperAdmin) {
        var profile = AppState.user ? String(AppState.user.profile).toLowerCase() : '';
        var canEditTenantName = profile === 'admin';
        return `
            <div class="max-w-3xl mx-auto space-y-8 pb-20">
                <div class="bg-white dark:bg-slate-800 p-10 rounded-[2.5rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                    <div class="max-w-2xl">
                        <p class="text-xs font-black tracking-[0.18em] text-blue-600 uppercase mb-3">Integração CRM</p>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 tracking-tight">${canEditTenantName ? 'Empresa e integração' : 'Meu webhook pessoal'}</h3>
                        <p class="text-sm leading-6 text-slate-500 dark:text-slate-300 mt-3">${canEditTenantName ? 'O webhook geral é usado pelos membros sem webhook pessoal. Cada pessoa pode configurar uma URL própria, que sempre terá prioridade.' : 'Defina a URL que receberá somente as suas exportações. Se deixá-la vazia, o sistema usa o webhook geral da empresa e, em último caso, o da plataforma.'}</p>
                    </div>
                    ${canEditTenantName ? `
                    <div class="mt-8 bg-slate-50 dark:bg-slate-700/60 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600">
                        <label for="setting-tenant-name" class="block text-[10px] font-black text-slate-500 dark:text-slate-300 uppercase mb-2 ml-1">Nome da empresa</label>
                        <input id="setting-tenant-name" type="text" maxlength="120" class="w-full bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500 ${canEditTenantName ? '' : 'opacity-70 cursor-not-allowed'}" placeholder="Nome da sua empresa" ${canEditTenantName ? '' : 'readonly aria-readonly="true"'}>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3">${canEditTenantName ? 'Esse nome será exibido no painel da sua empresa.' : 'Somente o administrador da empresa pode alterar este nome.'}</p>
                    </div>
                    <div class="mt-8 bg-slate-50 dark:bg-slate-700/60 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600">
                        <label for="setting-company-webhook-url" class="block text-[10px] font-black text-slate-500 dark:text-slate-300 uppercase mb-2 ml-1">Webhook geral da empresa</label>
                        <input id="setting-company-webhook-url" type="url" autocomplete="url" class="w-full bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="https://sua-plataforma.com/webhooks/leads">
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3">Usado como padrão pela empresa quando o usuário que exporta não possuir um webhook pessoal configurado.</p>
                    </div>
                    ` : ''}
                    <div class="mt-8 bg-slate-50 dark:bg-slate-700/60 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600">
                        <label for="setting-personal-webhook-url" class="block text-[10px] font-black text-slate-500 dark:text-slate-300 uppercase mb-2 ml-1">Meu webhook pessoal</label>
                        <input id="setting-personal-webhook-url" type="url" autocomplete="url" class="w-full bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="https://sua-plataforma.com/webhooks/meus-leads">
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-3">Tem prioridade sobre o webhook da empresa e sobre o padrão da plataforma. Deixe vazio para usar automaticamente os níveis de fallback.</p>
                    </div>
                    <div class="mt-6">
                        <button id="btn-save-settings" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-[1.5rem] shadow-xl shadow-blue-100 dark:shadow-blue-900/30 transition-all active:scale-[0.98]">Salvar configurações</button>
                    </div>
                </div>
            </div>
        `;
    }
    
    return `
        <div class="max-w-3xl mx-auto space-y-8 pb-20">
            <div class="bg-white dark:bg-slate-800 p-10 rounded-[2.5rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-8 tracking-tight">Configurações de Conexão</h3>
                <div class="space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 dark:text-slate-300 uppercase mb-2 ml-1">Nome da Instância / Empresa</label>
                            <input id="setting-tenant" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="Nome da empresa SaaS">
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-700/60 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 space-y-5">
                        <div>
                            <h4 class="font-black text-lg text-slate-900 dark:text-slate-100">WhatsApp — API Atendo</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-300 mt-1">Configure a conexão usada para as notificações da plataforma. O token nunca deve ser colocado no frontend ou em mensagens de teste.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-300 uppercase mb-2 ml-1">URL da API Atendo</label>
                            <input id="setting-url" type="url" autocomplete="url" class="w-full bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="https://off.atendo.log.br/v1/api/external/...">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-300 uppercase mb-2 ml-1">Token Bearer da API Atendo</label>
                            <div class="flex gap-3">
                                <input id="setting-token" type="password" autocomplete="new-password" class="min-w-0 flex-1 bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="Cole um novo token Bearer da API Atendo">
                                <button id="btn-toggle-atendo-token" type="button" class="px-5 rounded-2xl border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-200 font-bold hover:bg-white dark:hover:bg-slate-800" aria-label="Mostrar ou ocultar token">Mostrar</button>
                            </div>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2">Deixe vazio para manter o token atual. O valor salvo deve ser exibido apenas de forma mascarada.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-500 dark:text-slate-300 uppercase mb-2 ml-1">Número administrativo para alertas</label>
                            <input id="setting-atendo-wa-admin-number" type="tel" autocomplete="tel" class="w-full bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="551731981705">
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-700/60 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 space-y-3">
                        <div>
                            <h4 class="font-black text-lg text-slate-900 dark:text-slate-100">Webhook padrão da plataforma</h4>
                            <p class="text-xs text-slate-500 dark:text-slate-300 mt-1">Último nível de fallback. Ele é utilizado apenas quando o usuário que exporta e a empresa dele não tiverem um webhook configurado.</p>
                        </div>
                        <div>
                            <label for="setting-platform-webhook-url" class="block text-[10px] font-black text-slate-500 dark:text-slate-300 uppercase mb-2 ml-1">URL padrão do webhook</label>
                            <input id="setting-platform-webhook-url" type="url" autocomplete="url" class="w-full bg-white dark:bg-slate-800 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="https://sua-plataforma.com/webhooks/padrao">
                        </div>
                    </div>

                    ${isSuperAdmin ? `
                    <!-- CAIXA ASAAS -->
                    <div class="bg-[#0F172A] p-6 rounded-[2rem] border border-slate-800 text-white space-y-4">
                        <h4 class="font-black text-lg text-emerald-400 flex items-center gap-2">💳 Gateway de Recebimento (Asaas)</h4>
                        <p class="text-xs text-slate-400">Configure a chave de API e o token do webhook do Asaas. A recarga de créditos dos usuários passa a ser liberada automaticamente após a confirmação do pagamento.</p>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">Chave de API do Asaas (X-ACCESS-TOKEN)</label>
                                <input id="saas-config-asaas-apikey" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-emerald-500 font-bold text-white text-xs" placeholder="Cole a chave de Integrações do painel Asaas" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">Token de segurança do webhook</label>
                                <input id="saas-config-asaas-webhook-token" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-emerald-500 font-bold text-white text-xs" placeholder="Deixe vazio para gerar automaticamente" />
                                <p class="mt-2 text-[10px] leading-relaxed text-slate-400">O token salvo aparece apenas mascarado. Ao gerar um novo token, copie-o imediatamente para a configuração do webhook no Asaas.</p>
                            </div>
                            <div class="rounded-2xl border border-slate-700 bg-slate-900/60 p-4 space-y-3">
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-wider text-slate-300">URL para copiar no Asaas</p>
                                    <div class="mt-2 flex items-center gap-2">
                                        <code id="asaas-settings-webhook-url" class="min-w-0 flex-1 break-all rounded-xl bg-slate-950 px-3 py-2 text-[11px] font-semibold text-blue-200">Carregando…</code>
                                        <button type="button" id="btn-copy-asaas-webhook-url" class="shrink-0 rounded-xl bg-blue-600 px-3 py-2 text-[10px] font-black text-white hover:bg-blue-500">Copiar URL</button>
                                    </div>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <button type="button" id="btn-generate-asaas-webhook-token" class="rounded-xl border border-amber-500/60 px-3 py-2 text-[10px] font-black text-amber-200 hover:bg-amber-500/10">Gerar/rotacionar token</button>
                                    <button type="button" id="btn-copy-asaas-webhook-token" class="rounded-xl border border-blue-500/60 px-3 py-2 text-[10px] font-black text-blue-200 hover:bg-blue-500/10">Copiar token recém-gerado</button>
                                    <button type="button" id="btn-remove-asaas-config" class="rounded-xl border border-rose-500/60 px-3 py-2 text-[10px] font-black text-rose-200 hover:bg-rose-500/10">Remover configuração</button>
                                </div>
                            </div>
                            <label class="flex items-center gap-3 rounded-xl border border-slate-700 bg-slate-900/40 px-3 py-3 text-xs font-bold text-slate-200">
                                <input id="saas-config-asaas-active" type="checkbox" class="h-4 w-4 accent-emerald-500">
                                <span>Ativar cobrança automática quando a chave for validada</span>
                            </label>
                            <p id="setting-asaas-status" class="text-[11px] font-semibold mt-3 text-slate-400">Carregando status da configuração Asaas...</p>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-[10px] leading-relaxed text-slate-400">O teste confirma a comunicação com o Asaas e não cria cobranças.</p>
                                <button id="btn-test-asaas-connection" type="button" class="shrink-0 rounded-xl border border-emerald-500/50 px-4 py-2 text-xs font-black text-emerald-300 transition-colors hover:bg-emerald-500/10 disabled:cursor-not-allowed disabled:opacity-60">Testar conexão Asaas</button>
                            </div>
	                        </div>
                    </div>
                    ` : ''}

                    <div class="mt-6">
                        <button id="btn-save-settings" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-[1.5rem] shadow-xl shadow-blue-100 dark:shadow-blue-900/30 transition-all active:scale-[0.98]">Salvar Alterações</button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function setupSettingsEvents() {
    var btnSave = document.getElementById('btn-save-settings');
	if (btnSave) {
		btnSave.onclick = function() {
			saveSettings();
		};
	}

	var btnTestAsaas = document.getElementById('btn-test-asaas-connection');
	if (btnTestAsaas) {
		btnTestAsaas.onclick = function() {
			testAsaasConnection();
		};
	}

    var toggleTokenBtn = document.getElementById('btn-toggle-atendo-token');
    var tokenInput = document.getElementById('setting-token');
    if (toggleTokenBtn && tokenInput) {
        tokenInput.oninput = function() {
            // Qualquer edição transforma o conteúdo em um novo segredo.
            tokenInput.dataset.tokenMasked = 'false';
        };
        toggleTokenBtn.onclick = function() {
            var isHidden = tokenInput.type === 'password';
            tokenInput.type = isHidden ? 'text' : 'password';
            toggleTokenBtn.textContent = isHidden ? 'Ocultar' : 'Mostrar';
        };
    }

    ['saas-config-asaas-apikey', 'saas-config-asaas-webhook-token'].forEach(function(id) {
        var secretInput = document.getElementById(id);
        if (secretInput) {
            secretInput.oninput = function() {
                secretInput.dataset.asaasMasked = 'false';
            };
        }
    });

    var copyUrlBtn = document.getElementById('btn-copy-asaas-webhook-url');
    if (copyUrlBtn) copyUrlBtn.onclick = function() {
        var url = (document.getElementById('asaas-settings-webhook-url') || {}).textContent || '';
        if (!url || url === 'URL indisponível') return;
        navigator.clipboard.writeText(url).then(function() { copyUrlBtn.textContent = 'URL copiada!'; setTimeout(function() { copyUrlBtn.textContent = 'Copiar URL'; }, 2200); }).catch(function() { showToast('Não foi possível copiar automaticamente a URL.'); });
    };
    var copyTokenBtn = document.getElementById('btn-copy-asaas-webhook-token');
    if (copyTokenBtn) copyTokenBtn.onclick = function() {
        var token = AppState._asaasWebhookToken || '';
        if (!token) { showToast('Gere ou altere o token antes de copiá-lo.'); return; }
        navigator.clipboard.writeText(token).then(function() { copyTokenBtn.textContent = 'Token copiado!'; setTimeout(function() { copyTokenBtn.textContent = 'Copiar token recém-gerado'; }, 2200); }).catch(function() { showToast('Não foi possível copiar automaticamente o token.'); });
    };
    var generateTokenBtn = document.getElementById('btn-generate-asaas-webhook-token');
    if (generateTokenBtn) generateTokenBtn.onclick = async function() {
        if (!window.confirm('Gerar um novo token invalidará o token anterior do webhook. Deseja continuar?')) return;
        generateTokenBtn.disabled = true;
        generateTokenBtn.textContent = 'Gerando...';
        try {
            var response = await fetch(API_BASE + 'settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify({ asaasGenerateWebhookToken: true }) });
            var payload = await response.json();
            if (!response.ok || !payload.success || !payload.data || !payload.data.webhookToken) throw new Error(payload.error || 'Não foi possível gerar o token.');
            AppState._asaasWebhookToken = payload.data.webhookToken;
            var tokenInput = document.getElementById('saas-config-asaas-webhook-token');
            if (tokenInput) { tokenInput.value = payload.data.webhookToken; tokenInput.dataset.asaasMasked = 'false'; tokenInput.type = 'text'; }
            if (payload.data.asaasWebhookUrl) { var urlInput = document.getElementById('asaas-settings-webhook-url'); if (urlInput) urlInput.textContent = payload.data.asaasWebhookUrl; }
            showToast('Token gerado. Copie-o agora e cole no webhook do Asaas.');
        } catch (error) { showToast(error.message || 'Não foi possível gerar o token.'); }
        finally { generateTokenBtn.disabled = false; generateTokenBtn.textContent = 'Gerar/rotacionar token'; }
    };
    var removeAsaasBtn = document.getElementById('btn-remove-asaas-config');
    if (removeAsaasBtn) removeAsaasBtn.onclick = async function() {
        if (!window.confirm('Remover a chave e o token do Asaas? O sistema voltará ao fluxo de aprovação manual.')) return;
        removeAsaasBtn.disabled = true;
        removeAsaasBtn.textContent = 'Removendo...';
        try {
            var response = await fetch(API_BASE + 'settings.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, credentials: 'include', body: JSON.stringify({ asaasRemove: true }) });
            var payload = await response.json();
            if (!response.ok || !payload.success) throw new Error(payload.error || 'Não foi possível remover a configuração.');
            AppState._asaasWebhookToken = '';
            await loadConfig();
            loadSettingsForm();
            showToast('Asaas removido. O fluxo manual está ativo.');
        } catch (error) { showToast(error.message || 'Não foi possível remover a configuração.'); }
        finally { removeAsaasBtn.disabled = false; removeAsaasBtn.textContent = 'Remover configuração'; }
    };
}

async function testAsaasConnection() {
    var btn = document.getElementById('btn-test-asaas-connection');
    var status = document.getElementById('setting-asaas-status');
    if (!btn || !status) return;

    btn.disabled = true;
    btn.textContent = 'Testando...';
    status.textContent = 'Testando a conexão segura com o Asaas...';
    status.className = 'text-[11px] font-semibold mt-3 text-sky-300';

    try {
        var res = await fetch(API_BASE + 'asaas.php?action=testConnection', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: '{}'
        });
        var data = await res.json();
        if (!res.ok || !data.success) {
            throw new Error(data.error || 'O Asaas não confirmou a conexão.');
        }

        status.textContent = 'Conexão com o Asaas confirmada. Nenhuma cobrança foi criada.';
        status.className = 'text-[11px] font-semibold mt-3 text-emerald-300';
        showToast('Conexão com o Asaas confirmada.');
    } catch (error) {
        status.textContent = error && error.message ? error.message : 'Não foi possível testar a conexão com o Asaas.';
        status.className = 'text-[11px] font-semibold mt-3 text-rose-300';
    } finally {
        btn.disabled = false;
        btn.textContent = 'Testar conexão Asaas';
    }
}

async function saveSettings() {
    const btn = document.getElementById('btn-save-settings');
    if (!btn) return;

    btn.disabled = true;
    btn.textContent = 'Salvando...';
    
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';

    if (!isSuperAdmin) {
        var profile = AppState.user ? String(AppState.user.profile).toLowerCase() : '';
        var personalWebhookEl = document.getElementById('setting-personal-webhook-url');
        var personalWebhookUrl = personalWebhookEl ? personalWebhookEl.value.trim() : '';
        var tenantNameEl = document.getElementById('setting-tenant-name');
        var payload = { personalWebhookUrl: personalWebhookUrl };
        if (profile === 'admin') {
            var companyWebhookEl = document.getElementById('setting-company-webhook-url');
            payload.companyWebhookUrl = companyWebhookEl ? companyWebhookEl.value.trim() : '';
        }
        if (tenantNameEl && !tenantNameEl.readOnly) {
            payload.tenantName = tenantNameEl.value.trim();
        }

        try {
            const res = await fetch(API_BASE + 'settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                showToast(profile === 'admin' ? 'Configurações da empresa salvas com sucesso.' : 'Webhook pessoal salvo com sucesso.');
                await loadConfig();
            } else {
                alert('Erro ao salvar: ' + (data.error || 'Erro desconhecido'));
            }
        } catch (e) {
            alert('Erro de conexão: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar configurações';
        }
        return;
    }
    
    var urlEl = document.getElementById('setting-url');
    var tokenEl = document.getElementById('setting-token');
    var tenantEl = document.getElementById('setting-tenant');
    
    var tokenValue = tokenEl ? tokenEl.value.trim() : '';
    var tokenIsMasked = tokenEl && tokenEl.dataset.tokenMasked === 'true';
    var adminNumberEl = document.getElementById('setting-atendo-wa-admin-number');
    var platformWebhookEl = document.getElementById('setting-platform-webhook-url');
    var payload = {
        baseUrl: urlEl ? urlEl.value.trim() : '',
        tenantName: tenantEl ? tenantEl.value.trim() : 'Nome da empresa SaaS',
        atendoWaAdminNumber: adminNumberEl ? adminNumberEl.value.trim() : '',
        platformWebhookUrl: platformWebhookEl ? platformWebhookEl.value.trim() : ''
    };

    // Só envia token quando o administrador digitou um novo segredo.
    // Assim, deixar o campo vazio não apaga o token já armazenado no backend.
    if (tokenValue && !tokenIsMasked) payload.token = tokenValue;

    if (isSuperAdmin) {
        var scraperEl = document.getElementById('setting-scraper-api');
        var openrouterEl = document.getElementById('setting-openrouter-api');
        var mapboxEl = document.getElementById('setting-mapbox-token');
        var cnpjUrlEl = document.getElementById('setting-cnpj-url');

        payload.scraperApiKey = scraperEl ? scraperEl.value.trim() : '';
        payload.openrouterApiKey = openrouterEl ? openrouterEl.value.trim() : '';
        payload.mapboxAccessToken = mapboxEl ? mapboxEl.value.trim() : '';
        payload.cnpjApiUrl = cnpjUrlEl ? cnpjUrlEl.value.trim() : '';
        var asaasKeyEl = document.getElementById('saas-config-asaas-apikey');
        var asaasWebhookTokenEl = document.getElementById('saas-config-asaas-webhook-token');
        var asaasActiveEl = document.getElementById('saas-config-asaas-active');
        if (asaasKeyEl && asaasKeyEl.value.trim() && asaasKeyEl.dataset.asaasMasked !== 'true') {
            payload.asaasApiKey = asaasKeyEl.value.trim();
        }
        if (asaasWebhookTokenEl && asaasWebhookTokenEl.value.trim() && asaasWebhookTokenEl.dataset.asaasMasked !== 'true') {
            payload.asaasWebhookToken = asaasWebhookTokenEl.value.trim();
        }
        if (asaasActiveEl) payload.asaasActive = !!asaasActiveEl.checked;
    }

    try {
        const res = await fetch(API_BASE + 'settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        
        if (data.success) {
            // O backend devolve a configuração Asaas que acabou de reler do
            // banco. Nunca confirme o salvamento se uma chave enviada não
            // estiver marcada como persistida nessa confirmação.
            if (payload.asaasApiKey && !(data.data && data.data.asaasApiKeyConfigured)) {
                throw new Error('A chave do Asaas não foi confirmada após o salvamento. Ela não foi considerada configurada.');
            }
            if (payload.asaasWebhookToken && !(data.data && data.data.asaasWebhookTokenConfigured)) {
                throw new Error('O token de segurança do webhook Asaas não foi confirmado após o salvamento.');
            }

            AppState.config = Object.assign({}, AppState.config || {}, data.data || {}, {
                cnpjApiUrl: payload.cnpjApiUrl || 'https://publica.cnpj.ws/cnpj/{cnpj}'
            });
            await loadConfig();

            if (payload.asaasApiKey && !(AppState.config && AppState.config.asaasApiKeyConfigured)) {
                throw new Error('A chave do Asaas não permaneceu gravada após a releitura das configurações.');
            }

            loadSettingsForm();
            showToast('Configurações de conexão salvas e confirmadas!');
        } else {
            alert('Erro ao salvar: ' + (data.error || 'Erro desconhecido'));
        }
    } catch (e) {
        alert('Erro de conexão: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Salvar Alterações';
    }
}

// Carrega APENAS os municípios da hospedagem. 
// Os CNAEs serão consultados na VPS via Proxy PHP para segurança e performance.
async function loadLocalDatabase() {
    if (!AppState.localData) {
        AppState.localData = { municipios: [], loaded: false };
    }
    if (AppState.localData.loaded) return;
    
    try {
        const resMun = await fetch('data/municipios.json').then(r => r.json());
        
        if (resMun && resMun.resultados) {
            AppState.localData.municipios = resMun.resultados.map(m => ({
                codigo: m.codigo,
                descricao_norm: m.descricao.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim(),
                descricao: m.descricao
            }));
        }
        AppState.localData.loaded = true;
    } catch (e) {
        console.error("Erro ao carregar base de municípios:", e);
        AppState.localData.loaded = true; 
    }
}

// Inicializa a Busca Avançada
function setupCnpjAdvancedEvents() {
    loadLocalDatabase();
}

function showError(message) {
    const errorDiv = document.getElementById('error-info');
    if (errorDiv) {
        errorDiv.classList.remove('hidden');
        errorDiv.innerHTML = `<div class="flex items-center gap-3"><p class="text-xs font-bold">${message}</p></div>`;
    }
}

function hideError() {
    const errorDiv = document.getElementById('error-info');
    if (errorDiv) errorDiv.classList.add('hidden');
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toast-message');
    if (!toast) return;
    toastMsg.textContent = message;
    toast.classList.remove('hidden', 'bg-green-600', 'bg-amber-500', 'bg-red-600');
    toast.classList.add(type === 'warning' ? 'bg-amber-500' : type === 'error' ? 'bg-red-600' : 'bg-green-600');
    setTimeout(() => toast.classList.add('hidden'), 4000);
}

// Listener Global de Cliques e Fechamento de Menus
document.addEventListener('click', function(e) {
    const btnTab = e.target.closest('.tab-btn');
    if (btnTab && btnTab.dataset.tab) {
        e.preventDefault();
        closeMobileSidebar();
        setActiveTab(btnTab.dataset.tab);
    }

    const item = e.target.closest('.cnae-item');
    const menu = document.getElementById('cnae-suggestions');
    if (item && menu) {
        document.getElementById('adv-segment').value = item.dataset.desc;
        document.getElementById('selected-cnae-code').value = item.dataset.code;
        menu.style.display = 'none';
    } else if (menu && !e.target.closest('#adv-segment')) {
        menu.style.display = 'none';
    }
});

// Renderiza a interface da Consulta CNPJ (Simples por Número de CNPJ)
function renderCnpjLookupTab(contentArea) {
    if (!contentArea) return;

    contentArea.innerHTML = `
        <div class="max-w-4xl mx-auto pb-20">
            <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-2">Consulta Avançada de CNPJ</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">Digite um CNPJ para buscar as informações completas da empresa diretamente na base local.</p>
                
                <div class="flex flex-col sm:flex-row gap-4">
                    <div class="flex-1">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1">Número do CNPJ</label>
                        <input id="cnpj-simple-input" type="text" maxlength="18" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm" placeholder="00.000.000/0001-00">
                    </div>
                    <div class="flex items-end">
                        <button id="btn-cnpj-simple-search" type="button" onclick="performCnpjSimpleLookup()" class="w-full sm:w-auto px-8 py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-black rounded-xl transition-all shadow-lg shadow-blue-600/30 uppercase tracking-wider text-xs flex justify-center items-center gap-2">
                            Consultar CNPJ
                        </button>
                    </div>
                </div>
            </div>

            <!-- Resultado da Consulta -->
            <div id="cnpj-simple-result" class="hidden"></div>
        </div>
    `;

    // Adiciona evento de apertar ENTER no campo de busca
    const input = document.getElementById('cnpj-simple-input');
    if (input) {
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') performCnpjSimpleLookup();
        });
    }
}

async function performCnpjSimpleLookup() {
    const input = document.getElementById('cnpj-simple-input');
    const resultDiv = document.getElementById('cnpj-simple-result');
    const btn = document.getElementById('btn-cnpj-simple-search');

    const cnpj = input ? input.value.replace(/[^0-9]/g, '') : '';

    if (!cnpj || cnpj.length !== 14) {
        alert('Por favor, digite um CNPJ válido com 14 dígitos.');
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span> Consultando...';
    }
    beginSearchLoading('Consultando os dados do CNPJ...', 'A consulta pode levar alguns instantes.');

    if (resultDiv) {
        resultDiv.classList.remove('hidden');
        resultDiv.innerHTML = '<div class="py-12 text-center text-slate-500 font-bold">Realizando Consulta de inforações</div>';
    }

    try {
        const res = await fetch(API_BASE + 'cnpj-advanced-search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ cnae: '', municipio: '', uf: '', cnpj: cnpj })
        });

        const data = await res.json();

        if (data.success && data.data && data.data.length > 0) {
            const emp = data.data[0];

            resultDiv.innerHTML = `
                <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm space-y-6">
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 dark:border-slate-700 pb-4">
                        <div>
                            <span class="bg-emerald-100 text-emerald-800 text-[10px] font-extrabold px-3 py-1 rounded-full uppercase">${emp.situation || 'ATIVA'}</span>
                            <h3 class="text-xl font-black text-slate-900 dark:text-slate-100 mt-2">${emp.name}</h3>
                            ${emp.tradeName ? `<p class="text-xs text-slate-500 font-bold">Nome Fantasia: ${emp.tradeName}</p>` : ''}
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-mono font-bold text-slate-400">CNPJ</p>
                            <p class="text-base font-black text-slate-800 dark:text-slate-200">${emp.cnpj}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">📌 <strong>CNAE Principal:</strong> <span class="text-slate-800 dark:text-slate-200 font-bold">${emp.cnae_codigo} - ${emp.cnae_descricao}</span></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">📞 <strong>Telefone:</strong> <span class="text-slate-800 dark:text-slate-200 font-bold">${emp.phone || 'Não informado'}</span></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">✉️ <strong>E-mail:</strong> <span class="text-slate-800 dark:text-slate-200 font-bold">${emp.email || 'Não informado'}</span></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">🗓️ <strong>Abertura:</strong> <span class="text-slate-800 dark:text-slate-200 font-bold">${emp.data_abertura || 'N/A'}</span></p>
                        </div>
                        <div class="space-y-3">
                            <p class="text-xs text-slate-500 dark:text-slate-400">📍 <strong>Endereço:</strong> <span class="text-slate-800 dark:text-slate-200 font-bold">${emp.address}</span></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">💰 <strong>Capital Social:</strong> <span class="text-slate-800 dark:text-slate-200 font-bold">R$ ${parseFloat(emp.capital).toLocaleString('pt-BR')}</span></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">👥 <strong>Sócios:</strong> <span class="text-slate-800 dark:text-slate-200 font-bold">${emp.socios || 'Não informado'}</span></p>
                        </div>
                    </div>
                </div>
            `;
        } else {
            resultDiv.innerHTML = `
                <div class="p-8 text-center bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-400 rounded-2xl border border-amber-200 dark:border-amber-800 font-bold text-sm">
                    CNPJ não encontrado na base pública.
                </div>
            `;
        }
    } catch (e) {
        if (resultDiv) {
            resultDiv.innerHTML = `<div class="p-6 text-center text-red-600 font-bold text-sm">Erro ao consultar CNPJ: ${e.message}</div>`;
        }
    } finally {
        endSearchLoading();
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Consultar CNPJ';
        }
    }
}
// Garante visibilidade no escopo global
window.renderCnpjLookupTab = renderCnpjLookupTab;
window.performCnpjSimpleLookup = performCnpjSimpleLookup;

// Renderiza a interface da Busca Avançada por Segmento / CNAE
function renderCnpjAdvancedTab(contentArea) {
    contentArea.innerHTML = `
        <div class="max-w-6xl mx-auto pb-20">
            <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm mb-8">
                <h3 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-6">Busca por Segmento (CNAE)</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4 relative">
                    <!-- Campo Segmento com Autocomplete -->
                    <div class="md:col-span-5 relative">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1">Segmento / Atividade (CNAE)</label>
                        <input id="adv-segment" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm" placeholder="Ex: Mercado, Padaria, TI...">
                        <input type="hidden" id="selected-cnae-code">
                        
                        <!-- Dropdown de Sugestões CNAE -->
                        <div id="cnae-suggestions" class="absolute left-0 right-0 top-full mt-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-2xl shadow-2xl z-50 max-h-60 overflow-y-auto hidden"></div>
                    </div>

                    <!-- UF / Estado -->
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1">UF</label>
                        <input id="adv-uf" type="text" maxlength="2" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm uppercase" placeholder="SP">
                    </div>

                    <!-- Município -->
                    <div class="md:col-span-3">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1">Cidade / Município</label>
                        <input id="adv-city" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm" placeholder="Ex: São Paulo">
                    </div>

                    <!-- Botão de Busca -->
                    <div class="md:col-span-2 flex items-end">
                        <button id="btn-adv-search" type="button" onclick="performCnpjAdvancedSearch()" class="w-full py-3.5 bg-blue-600 hover:bg-blue-700 text-white font-black rounded-xl transition-all shadow-lg shadow-blue-600/30 uppercase tracking-wider text-xs flex justify-center items-center gap-2">
                            Buscar
                        </button>
                    </div>
                </div>
            </div>

            <!-- Header dos Resultados -->
            <div id="adv-results-header" class="hidden mb-6 flex justify-between items-center px-2">
                <h4 class="text-lg font-black text-slate-900 dark:text-slate-100">Empresas Encontradas (<span id="adv-total-count">0</span>)</h4>
            </div>

            <!-- Grid de Resultados -->
            <div id="adv-results-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"></div>
        </div>
    `;

    setupCnpjAdvancedEvents();
}


// Executa a consulta na API local via Proxy
async function performCnpjAdvancedSearch() {
    const cnae = document.getElementById('selected-cnae-code')?.value || document.getElementById('adv-segment')?.value || '';
    const uf = document.getElementById('adv-uf')?.value.trim() || '';
    const municipio = document.getElementById('adv-city')?.value.trim() || '';
    const btn = document.getElementById('btn-adv-search');
    const grid = document.getElementById('adv-results-grid');
    const header = document.getElementById('adv-results-header');
    const totalCount = document.getElementById('adv-total-count');

    if (!cnae && !municipio) {
        alert('Por favor, informe ao menos um segmento ou cidade para pesquisar.');
        return;
    }

    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span> Buscando...';
    }

    beginSearchLoading('Buscando empresas por segmento e localidade...', 'A pesquisa está sendo processada. Aguarde o retorno.');
    try {
        const res = await fetch(API_BASE + 'cnpj-advanced-search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cnae, uf, municipio })
        });

        const data = await res.json();

        if (data.success && data.data && data.data.length > 0) {
            header.classList.remove('hidden');
            totalCount.textContent = data.data.length;

            grid.innerHTML = data.data.map(empresa => `
                <div class="bg-white dark:bg-slate-800 p-6 rounded-2xl border border-slate-200 dark:border-slate-600 shadow-sm hover:shadow-md transition">
                    <div class="flex items-center justify-between mb-3">
                        <span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2.5 py-1 rounded-full uppercase">${empresa.situation}</span>
                        <span class="text-[10px] font-mono text-slate-400">${empresa.cnpj}</span>
                    </div>
                    <h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-sm mb-2 line-clamp-1" title="${empresa.name}">${empresa.name}</h4>
                    <p class="text-xs text-blue-600 dark:text-blue-400 font-bold mb-4 line-clamp-1">📌 ${empresa.cnae_codigo} - ${empresa.cnae_descricao}</p>
                    
                    <div class="space-y-2 text-xs text-slate-600 dark:text-slate-400 border-t border-slate-100 dark:border-slate-700 pt-3">
                        <p>📞 <strong>Tel:</strong> ${empresa.phone || 'Não informado'}</p>
                        <p>✉️ <strong>Email:</strong> ${empresa.email || 'Não informado'}</p>
                        <p class="line-clamp-2">📍 <strong>Endereço:</strong> ${empresa.address}</p>
                    </div>
                </div>
            `).join('');
        } else {
            header.classList.add('hidden');
            grid.innerHTML = `
                <div class="col-span-full py-16 text-center bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-600">
                    <p class="text-slate-400 font-bold text-sm">Nenhuma empresa encontrada com os filtros informados.</p>
                </div>
            `;
        }
    } catch (e) {
        alert('Erro ao realizar a busca avançada: ' + e.message);
    } finally {
        endSearchLoading();
        if (btn) {
            btn.disabled = false;
            btn.textContent = 'Buscar';
        }
    }
}
// Função para abrir e fechar submenus na sidebar
function toggleSubmenu(elementOrId) {
    var submenu = null;

    if (typeof elementOrId === 'string') {
        submenu = document.getElementById(elementOrId);
    } else if (elementOrId && elementOrId.nodeType) {
        // Se foi passado o 'this' (botão), procura o próximo elemento ou submenu filho
        var parent = elementOrId.parentElement;
        submenu = parent ? parent.querySelector('.submenu, [id*="submenu"], ul, div') : null;
    }

    if (submenu) {
        submenu.classList.toggle('hidden');
    } else if (elementOrId && elementOrId.classList) {
        elementOrId.classList.toggle('hidden');
    }
}

// Garante que a função fique visível globalmente para o HTML
window.toggleSubmenu = toggleSubmenu;

// Listener de Digitação: Consulta a VPS em Tempo Real via Proxy PHP
var cnaeTimeoutDelay = null; 
document.addEventListener('input', function(e) {
    if (e.target.id === 'adv-segment') {
        const input = e.target;
        const menu = document.getElementById('cnae-suggestions');
        const termo = input.value.trim();

        if (termo.length < 2) {
            if (menu) menu.style.display = 'none';
            return;
        }

        clearTimeout(cnaeTimeoutDelay);
        cnaeTimeoutDelay = setTimeout(async () => {
            try {
                // MÁGICA: Em vez de bater na VPS (que causa bloqueio de HTTPS), 
                // bate no seu arquivo PHP interno, que faz o trabalho sujo.
                const res = await fetch(API_BASE + `cnae-search.php?q=${encodeURIComponent(termo)}`);
                const data = await res.json();
                
                const resultados = data.resultados || data.data || (Array.isArray(data) ? data : []);

                if (resultados.length > 0 && menu) {
                    menu.innerHTML = resultados.map(c => {
                        const codigo = c.codigo_cnae || c.codigo || c.id || '';
                        const descricao = c.descricao_cnae || c.descricao || c.name || '';
                        
                        return `
                        <div class="cnae-item" data-code="${codigo}" data-desc="${descricao}" 
                             style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; color: #333; font-size: 13px;">
                            <b style="color: #7c3aed;">${codigo}</b> - ${descricao}
                        </div>
                        `;
                    }).join('');
                    menu.style.display = 'block';
                } else if (menu) {
                    menu.innerHTML = '<div style="padding: 12px; color: #666; font-size: 12px;">Nenhum segmento encontrado.</div>';
                    menu.style.display = 'block';
                }
            } catch (err) {
                console.error("Erro ao consultar a API de CNAE via Proxy PHP:", err);
            }
        }, 300);
    }
});

// ==========================================
// FUNÇÕES QUE FALTAVAM (Aba Equipe e Outras)
// ==========================================

// Renderiza a aba 'Minha Equipe'
function renderTeamTab(contentArea) {
    if (!contentArea) return;
    
    const profile = String(AppState.user?.profile || '').toLowerCase();
    const canManageTeam = profile === 'admin' || profile === 'super_admin' || profile === 'supervisor' || profile === 'manager' || profile === 'gerente';

    contentArea.innerHTML = `
        <div class="max-w-5xl mx-auto space-y-6 pb-20">
            <div class="flex justify-between items-center flex-wrap gap-4">
                <div>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Minha Equipe</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Gerencie os acessos e cargos da sua equipe.</p>
                </div>
                ${canManageTeam ? `
                <button type="button" id="btn-open-team-modal" onclick="showAddTeamMemberModal()" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-3 rounded-2xl text-xs flex items-center gap-2 shadow-lg shadow-blue-600/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                    Novo Funcionário
                </button>
                ` : ''}
            </div>

            <div id="team-quota" class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-100">Carregando limite de usuários...</div>
            <div id="team-list-container" class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-600 p-6 shadow-sm">
                <div class="py-12 text-center">
                    <span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block"></span>
                    <p class="mt-3 text-sm font-bold text-slate-500 dark:text-slate-400">Carregando equipe...</p>
                </div>
            </div>
        </div>
    `;
    
    loadTeamMembers();
}
// Modal para Cadastrar Funcionário
function showAddTeamMemberModal() {
    const existingModal = document.getElementById('modal-add-team-member');
    if (existingModal) existingModal.remove();

    const overlay = document.createElement('div');
    overlay.id = 'modal-add-team-member';
    overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4';
    overlay.innerHTML = `
        <div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">
            <h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-6">Cadastrar Funcionário</h4>
            
            <form id="form-add-team-member" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome Completo</label>
                    <input type="text" id="team-user-name" required placeholder="Ex: Maria Silva" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">
                </div>
                
                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">E-mail</label>
                    <input type="email" id="team-user-email" required placeholder="maria@empresa.com" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">
                </div>

                <!-- 💡 NOVO: Campo de Telefone / WhatsApp -->
                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">WhatsApp / Telefone</label>
                    <input type="tel" id="team-user-phone" required autocomplete="tel" placeholder="Ex: 11999999999" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Senha Inicial</label>
                    <input type="password" id="team-user-password" required minlength="6" placeholder="Mínimo 6 caracteres" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Cargo / Nível de Acesso</label>
                    <select id="team-user-profile" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold">
                        <option value="user">Usuário (Consulta e Histórico próprio)</option>
                        <option value="supervisor">Supervisor (Consulta, Histórico geral e Equipe)</option>
                        <option value="admin">Administrador (Acesso Total)</option>
                    </select>
                </div>
                <label class="flex items-center gap-3 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    <input type="checkbox" id="team-user-can-search" checked class="h-4 w-4 accent-blue-600">
                    Permitir que este usuário realize pesquisas
                </label>

                <p id="team-member-error" class="text-red-600 text-xs font-bold hidden"></p>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="document.getElementById('modal-add-team-member').remove()" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>
                    <button type="submit" id="btn-save-team-member" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Salvar</button>
                </div>
            </form>
        </div>
    `;

    document.body.appendChild(overlay);

    document.getElementById('form-add-team-member').onsubmit = async function(e) {
        e.preventDefault();
        const name = document.getElementById('team-user-name').value.trim();
        const email = document.getElementById('team-user-email').value.trim().toLowerCase();
        const phone = document.getElementById('team-user-phone').value.trim(); // 💡 Captura o telefone
        const password = document.getElementById('team-user-password').value;
        const profile = document.getElementById('team-user-profile').value;
        const canSearch = document.getElementById('team-user-can-search').checked;
        const errEl = document.getElementById('team-member-error');
        const btn = document.getElementById('btn-save-team-member');

        errEl.classList.add('hidden');
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        try {
            const res = await fetch(API_BASE + 'users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ name, email, phone, password, profile, can_search: canSearch }) // 💡 Envia o telefone no JSON
            });

            let data = null;
            try {
                data = await res.json();
            } catch (parseError) {
                data = null;
            }
            if (res.ok && data && data.success) {
                overlay.remove();
                showToast('Funcionário cadastrado com sucesso!');
                loadTeamMembers();
            } else {
                const code = data && data.code ? ' [' + data.code + ']' : '';
                errEl.textContent = (data && data.error ? data.error : 'Não foi possível cadastrar o funcionário. Tente novamente.') + code;
                errEl.classList.remove('hidden');
            }
        } catch (err) {
            errEl.textContent = err && err.message ? err.message : 'Erro de conexão com o servidor.';
            errEl.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Salvar';
        }
    };
}
// Carrega a lista de usuários da equipe
async function loadTeamMembers() {
    const container = document.getElementById('team-list-container');
    if (!container) return;
    
    try {
        const res = await fetch(API_BASE + 'users.php', { credentials: 'include' });
        const data = await res.json();
        let quota = null;
        try {
            const quotaRes = await fetch(API_BASE + 'users.php?action=quota', { credentials: 'include', cache: 'no-store' });
            const quotaData = await quotaRes.json();
            quota = quotaData && quotaData.success ? quotaData.data : null;
        } catch (quotaError) { quota = null; }
        const quotaEl = document.getElementById('team-quota');
        if (quotaEl && quota) {
            const reached = Number(quota.currentUsers || 0) >= Number(quota.includedUsers || 1);
            const available = Math.max(0, Number(quota.includedUsers || 1) - Number(quota.currentUsers || 0));
            quotaEl.className = reached ? 'rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100' : 'rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-100';
            quotaEl.innerHTML = '<strong>Usuários da empresa:</strong> ' + Number(quota.currentUsers || 0) + '/' + Number(quota.includedUsers || 1) + ' incluídos pelo ' + dashboardMetricEscape(quota.source || 'plano') + '. ' + (reached ? 'Limite atingido: remova um usuário ou contrate uma franquia CRM maior para cadastrar outro.' : available + ' vaga(s) disponível(is).');
        }
        
        if (data.success && Array.isArray(data.data)) {
            if (data.data.length === 0) {
                container.innerHTML = `<div class="py-12 text-center text-slate-400 font-bold text-sm">Nenhum funcionário cadastrado.</div>`;
                return;
            }

            const currentProfile = String(AppState.user?.profile || '').toLowerCase();
            const canManageMembers = ['super_admin', 'admin', 'supervisor', 'manager', 'gerente'].includes(currentProfile);
            
            const roleBadges = {
                super_admin: '<span class="bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 px-3 py-1 rounded-full text-xs font-black">Super Admin</span>',
                admin: '<span class="bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 px-3 py-1 rounded-full text-xs font-black">Administrador</span>',
                supervisor: '<span class="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 px-3 py-1 rounded-full text-xs font-black">Supervisor</span>',
                manager: '<span class="bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300 px-3 py-1 rounded-full text-xs font-black">Supervisor</span>',
                user: '<span class="bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 px-3 py-1 rounded-full text-xs font-black">Usuário</span>',
                usuario: '<span class="bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300 px-3 py-1 rounded-full text-xs font-black">Usuário</span>'
            };

            let html = '<div class="space-y-3">';
            data.data.forEach(user => {
                const profileKey = String(user.profile || 'user').toLowerCase();
                const badge = roleBadges[profileKey] || roleBadges.user;
                const escapedUser = encodeURIComponent(JSON.stringify(user));
                const createdAt = user.created_at ? new Date(String(user.created_at).replace(' ', 'T')).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' }) : 'Data não informada';
                const createdBy = user.created_by_name || 'Cadastro anterior à auditoria';
                
                html += `
                    <div class="p-5 rounded-2xl border border-slate-100 dark:border-slate-700/60 bg-slate-50/50 dark:bg-slate-800/50 flex items-center justify-between flex-wrap gap-4">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 rounded-xl bg-blue-600 text-white font-black flex items-center justify-center text-sm uppercase">
                                ${(user.name || 'U').charAt(0)}
                            </div>
                            <div>
                                <h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-sm">${user.name || 'Sem nome'}</h4>
                                <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">${user.email} ${user.phone ? '· ' + user.phone : ''}</p>
                                <p class="mt-1 text-[10px] text-slate-400 dark:text-slate-500 font-semibold">Cadastro: ${createdAt} · Criado por: ${createdBy}</p>
                                <p class="mt-1 text-[10px] font-bold ${user.can_search ? 'text-emerald-600' : 'text-amber-600'}">${user.can_search ? 'Pesquisas permitidas' : 'Pesquisas bloqueadas'}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            ${badge}
                            ${canManageMembers ? `
                            <button type="button" onclick="showEditTeamMemberModal('${escapedUser}')" class="bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-xl text-xs font-bold transition">Editar</button>
                            <button type="button" onclick="deleteTeamMember('${user.id}')" class="bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded-xl text-xs font-bold transition">Excluir</button>
                            ` : ''}
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            container.innerHTML = html;
        } else {
            container.innerHTML = `<div class="py-8 text-center text-amber-600 font-bold text-sm">${data.error || 'Erro ao carregar equipe.'}</div>`;
        }
    } catch (e) {
        container.innerHTML = `<div class="py-8 text-center text-red-500 font-bold text-sm">Erro de conexão ao carregar equipe.</div>`;
    }
}
// Modal para Editar Funcionário
// Modal para Editar Funcionário
function showEditTeamMemberModal(encodedUser) {
    const user = JSON.parse(decodeURIComponent(encodedUser));
    const existingModal = document.getElementById('modal-edit-team-member');
    if (existingModal) existingModal.remove();

    const overlay = document.createElement('div');
    overlay.id = 'modal-edit-team-member';
    overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4';
    overlay.innerHTML = `
        <div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">
            <h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-6">Editar Funcionário</h4>
            
            <form id="form-edit-team-member" class="space-y-4">
                <input type="hidden" id="edit-user-id" value="${user.id}">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome Completo</label>
                    <input type="text" id="edit-user-name" required value="${user.name || ''}" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">
                </div>
                
                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">E-mail</label>
                    <input type="email" id="edit-user-email" required value="${user.email || ''}" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">WhatsApp / Telefone</label>
                    <input type="text" id="edit-user-phone" value="${user.phone || ''}" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nova Senha (deixe em branco para não alterar)</label>
                    <input type="password" id="edit-user-password" minlength="6" placeholder="******" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">
                </div>

                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Cargo / Nível de Acesso</label>
                    <select id="edit-user-profile" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold">
                        <option value="user" ${user.profile === 'user' ? 'selected' : ''}>Usuário (Consulta e Histórico próprio)</option>
                        <option value="supervisor" ${user.profile === 'supervisor' || user.profile === 'manager' ? 'selected' : ''}>Supervisor (Consulta, Histórico geral e Equipe)</option>
                        <option value="admin" ${user.profile === 'admin' ? 'selected' : ''}>Administrador (Acesso Total)</option>
                    </select>
                </div>
                <label class="flex items-center gap-3 text-sm font-semibold text-slate-700 dark:text-slate-300">
                    <input type="checkbox" id="edit-user-can-search" ${user.can_search ? 'checked' : ''} class="h-4 w-4 accent-blue-600">
                    Permitir que este usuário realize pesquisas
                </label>

                <p id="edit-member-error" class="text-red-600 text-xs font-bold hidden"></p>

                <div class="flex gap-3 pt-4">
                    <button type="button" onclick="document.getElementById('modal-edit-team-member').remove()" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>
                    <button type="submit" id="btn-update-team-member" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Atualizar</button>
                </div>
            </form>
        </div>
    `;

    document.body.appendChild(overlay);

    document.getElementById('form-edit-team-member').onsubmit = async function(e) {
        e.preventDefault();
        const id = document.getElementById('edit-user-id').value;
        const name = document.getElementById('edit-user-name').value.trim();
        const email = document.getElementById('edit-user-email').value.trim().toLowerCase();
        const phone = document.getElementById('edit-user-phone').value.trim();
        const password = document.getElementById('edit-user-password').value;
        const profile = document.getElementById('edit-user-profile').value;
        const canSearch = document.getElementById('edit-user-can-search').checked;
        const errEl = document.getElementById('edit-member-error');
        const btn = document.getElementById('btn-update-team-member');

        errEl.classList.add('hidden');
        btn.disabled = true;
        btn.textContent = 'Atualizando...';

        try {
            const res = await fetch(API_BASE + 'users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ action: 'update', id, name, email, phone, password, profile, can_search: canSearch })
            });

            const data = await res.json();
            if (data.success) {
                overlay.remove();
                showToast('Funcionário atualizado com sucesso!');
                loadTeamMembers();
            } else {
                errEl.textContent = data.error || 'Erro ao atualizar funcionário.';
                errEl.classList.remove('hidden');
            }
        } catch (err) {
            errEl.textContent = 'Erro de conexão com o servidor.';
            errEl.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Atualizar';
        }
    };
} 

// Deletar Funcionário
async function deleteTeamMember(userId) {
    if (!confirm('Tem certeza que deseja remover este funcionário da equipe?')) return;

    try {
        const res = await fetch(API_BASE + 'users.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ id: userId })
        });

        const data = await res.json();
        if (data.success) {
            showToast('Funcionário removido com sucesso!');
            loadTeamMembers();
        } else {
            alert(data.error || 'Erro ao remover funcionário.');
        }
    } catch (e) {
        alert('Erro de conexão ao remover funcionário.');
    }
}

// Renderiza a aba 'Recém-Abertas' (Fallback de segurança)
window.deleteTeamMember = deleteTeamMember;

// Renderiza a aba 'Recém-Abertas' (Fallback de segurança)
function renderLatestCompaniesTab(contentArea) {
    if (!contentArea) return;
    contentArea.innerHTML = `
        <div class="max-w-4xl mx-auto py-12 text-center">
            <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-2">Empresas Recém-Abertas</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400">Consulte novas empresas cadastradas no mercado.</p>
        </div>
    `;
}

// Renderiza a aba 'Landing Page' (Fallback de segurança)

async function loadSuperAdminDashboardStats() {
    try {
        const res = await fetch(API_BASE + 'admin-stats.php', { credentials: 'include' });
        const data = await res.json();
        if (data.success && data.data) {
            const d = data.data;
            
            const elCompanies = document.getElementById('stat-companies');
            const elCompaniesSub = document.getElementById('stat-companies-sub');
            const elLeads = document.getElementById('stat-leads');
            const elSearchesSub = document.getElementById('stat-searches-sub');
            const elApify = document.getElementById('stat-apify');
            const elApifySub = document.getElementById('stat-apify-sub');
            const elPending = document.getElementById('stat-pending');

            if (elCompanies) elCompanies.textContent = d.companies.total;
            if (elCompaniesSub) elCompaniesSub.textContent = `${d.companies.active} ativas · ${d.companies.suspended} suspensas`;
            
            if (elLeads) elLeads.textContent = d.leads.toLocaleString('pt-BR');
            if (elSearchesSub) elSearchesSub.textContent = `${d.searches} buscas realizadas`;
            
            if (elApify) elApify.textContent = `${d.apify.activeKeys} / ${d.apify.totalKeys}`;
            if (elApifySub) elApifySub.textContent = `${d.apify.totalUsed} execuções totais`;
            
            if (elPending) elPending.textContent = d.pendingRequests;
        }
    } catch (e) {
        console.warn("Erro ao carregar estatísticas do admin:", e);
    }
}
// Renderização e Gestão da Landing Page
function renderLandingPageTab(contentArea) {
    if (!contentArea) return;

    contentArea.innerHTML = `
        <div class="max-w-4xl mx-auto space-y-8 pb-20">
            <div>
                <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Gerenciamento da Landing Page</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Edite os textos, seções e chamadas da sua página inicial pública.</p>
            </div>

            <div id="landing-admin-error" class="hidden p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>
            <div id="landing-admin-success" class="hidden p-4 bg-emerald-50 border border-emerald-200 rounded-2xl text-emerald-700 text-sm font-medium"></div>

            <div id="landing-admin-sections-container" class="space-y-6">
                <div class="py-12 text-center">
                    <span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block"></span>
                    <p class="mt-3 text-sm font-bold text-slate-500">Carregando seções da página inicial...</p>
                </div>
            </div>
        </div>
    `;

    loadLandingAdminSections();
}

function normalizeLandingAdminSections(payload) {
    const candidates = [
        payload && payload.sections,
        payload && payload.data && payload.data.sections,
        payload && Array.isArray(payload.data) ? payload.data : null,
        payload && payload.items,
        payload && payload.data && payload.data.items
    ];
    const sections = candidates.find(function (value) { return Array.isArray(value); }) || [];
    return sections.map(function (sec) {
        const extraData = sec && (sec.extraData !== undefined ? sec.extraData : sec.extra_data);
        return Object.assign({}, sec, {
            id: sec && sec.id !== undefined ? sec.id : null,
            sectionKey: sec && (sec.sectionKey || sec.section_key) || '',
            sectionTitle: sec && (sec.sectionTitle !== undefined ? sec.sectionTitle : sec.section_title) || '',
            sectionSubtitle: sec && (sec.sectionSubtitle !== undefined ? sec.sectionSubtitle : sec.section_subtitle) || '',
            sectionContent: sec && (sec.sectionContent !== undefined ? sec.sectionContent : sec.section_content) || '',
            sectionImage: sec && (sec.sectionImage !== undefined ? sec.sectionImage : sec.section_image) || '',
            sectionOrder: sec && (sec.sectionOrder !== undefined ? sec.sectionOrder : sec.section_order) || 0,
            isActive: sec && (sec.isActive !== undefined ? sec.isActive : sec.is_active) !== false,
            extraData: extraData && typeof extraData === 'object' ? extraData : {}
        });
    });
}

async function loadLandingAdminSections() {
    const container = document.getElementById('landing-admin-sections-container');
    if (!container) return;

    try {
        const res = await fetch(API_BASE + 'landing-page.php?t=' + new Date().getTime(), {
            credentials: 'include',
            cache: 'no-store'
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const data = await res.json();
        const responseSections = normalizeLandingAdminSections(data);
        const usingFallback = responseSections.length === 0;
        const sections = usingFallback
            ? getDefaultLandingSections().map(function (section) {
                return Object.assign({
                    id: null,
                    isFallback: true,
                    sectionImage: '',
                    sectionOrder: 0,
                    isActive: true,
                    extraData: section.extraData || {}
                }, section);
            })
            : responseSections;

        function escapeLandingAdminHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        let html = '';
        if (usingFallback) {
            html += '<div class="p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium">' +
                'A API administrativa ainda não retornou seções cadastradas. Os campos abaixo exibem o conteúdo padrão da página inicial. Verifique se o arquivo <code>api/landing-page.php</code> está publicado e se o usuário atual é super administrador.' +
                '</div>';
        }

        sections.forEach(function (sec, index) {
                const sectionKey = sec.sectionKey || sec.section_key || ('section-' + index);
                const sectionId = sec.id !== undefined && sec.id !== null && String(sec.id).trim() !== '' ? String(sec.id) : '';
                const fieldKey = (sectionId || sectionKey || String(index)).replace(/[^a-zA-Z0-9_-]/g, '-');
                const sectionTitle = sec.sectionTitle || sec.section_title || '';
                const sectionSubtitle = sec.sectionSubtitle || sec.section_subtitle || '';
                const sectionContent = sec.sectionContent || sec.section_content || '';
                const sectionImage = sec.sectionImage || sec.section_image || '';
                const sectionOrder = Number(sec.sectionOrder !== undefined ? sec.sectionOrder : (sec.section_order || index + 1)) || index + 1;
                const isActive = sec.isActive !== undefined ? Boolean(sec.isActive) : (String(sec.is_active) !== '0');
                const extraData = sec.extraData && typeof sec.extraData === 'object'
                    ? sec.extraData
                    : (sec.extra_data && typeof sec.extra_data === 'object' ? sec.extra_data : {});
                const extraJson = JSON.stringify(extraData, null, 2);
                const submitId = sectionId || sectionKey;
                html += `
                    <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm space-y-4">
                        <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-700 pb-3">
                            <h4 class="font-black text-slate-900 dark:text-slate-100 text-base uppercase">SEÇÃO: ${escapeLandingAdminHtml(sectionKey)}</h4>
                            <span class="text-xs font-bold ${isActive ? 'text-emerald-700 bg-emerald-50' : 'text-slate-600 bg-slate-100'} px-3 py-1 rounded-full">${isActive ? 'Ativa' : 'Oculta'} · ${escapeLandingAdminHtml(sectionTitle || 'Sem Título')}</span>
                        </div>
                        <form onsubmit="return handleLandingSectionSubmit(event, '${escapeLandingAdminHtml(sectionId)}', '${escapeLandingAdminHtml(sectionKey)}')" class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Título da Seção</label>
                                <input type="text" id="landing-title-${fieldKey}" value="${escapeLandingAdminHtml(sectionTitle)}" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Subtítulo</label>
                                <input type="text" id="landing-subtitle-${fieldKey}" value="${escapeLandingAdminHtml(sectionSubtitle)}" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold text-sm">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Conteúdo / Texto HTML</label>
                                <textarea id="landing-content-${fieldKey}" rows="3" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl p-4 outline-none focus:border-blue-500 font-mono text-xs">${escapeLandingAdminHtml(sectionContent)}</textarea>
                            </div>
                            <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_9rem]">
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Imagem da seção</label>
                                    <input type="url" id="landing-image-${fieldKey}" value="${escapeLandingAdminHtml(sectionImage)}" placeholder="https://..." class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 text-sm">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Ordem</label>
                                    <input type="number" min="0" id="landing-order-${fieldKey}" value="${escapeLandingAdminHtml(sectionOrder)}" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 text-sm">
                                </div>
                            </div>
                            <label class="inline-flex items-center gap-3 text-sm font-bold text-slate-700 dark:text-slate-200">
                                <input type="checkbox" id="landing-active-${fieldKey}" ${isActive ? 'checked' : ''} class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                Exibir esta seção na página pública
                            </label>
                            <div>
                                <label class="block text-[10px] font-black text-slate-400 uppercase mb-1">Dados extras (JSON)</label>
                                <textarea id="landing-extra-${fieldKey}" rows="6" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl p-4 outline-none focus:border-blue-500 font-mono text-xs">${escapeLandingAdminHtml(extraJson)}</textarea>
                                <p class="mt-1 text-[11px] text-slate-500 dark:text-slate-400">Use este campo para itens, botões, links, perguntas, etapas e redes sociais da seção.</p>
                            </div>
                            <div class="text-right">
                                <button type="submit" id="btn-save-landing-${fieldKey}" data-section-id="${escapeLandingAdminHtml(sectionId)}" data-section-key="${escapeLandingAdminHtml(sectionKey)}" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-2.5 rounded-xl text-xs transition">Salvar Seção</button>
                            </div>
                        </form>
                    </div>
                `;
        });
        container.innerHTML = html;
    } catch (e) {
        container.innerHTML = `<p class="text-xs text-red-500 font-bold text-center py-6">Erro de conexão ao carregar seções. Atualize a página e tente novamente.</p>`;
    }
}

async function handleLandingSectionSubmit(e, sectionId, sectionKey) {
    if (e && e.preventDefault) e.preventDefault();

    const lookupKey = (sectionId || sectionKey || '').toString().replace(/[^a-zA-Z0-9_-]/g, '-');
    const titleEl = document.getElementById(`landing-title-${lookupKey}`);
    const subtitleEl = document.getElementById(`landing-subtitle-${lookupKey}`);
    const contentEl = document.getElementById(`landing-content-${lookupKey}`);
    const imageEl = document.getElementById(`landing-image-${lookupKey}`);
    const orderEl = document.getElementById(`landing-order-${lookupKey}`);
    const activeEl = document.getElementById(`landing-active-${lookupKey}`);
    const extraEl = document.getElementById(`landing-extra-${lookupKey}`);
    const btn = document.getElementById(`btn-save-landing-${lookupKey}`);

    if (btn) { btn.disabled = true; btn.textContent = 'Salvando...'; }

    try {
        let extraData = {};
        if (extraEl && extraEl.value.trim() !== '') {
            try {
                extraData = JSON.parse(extraEl.value);
            } catch (parseError) {
                throw new Error('O campo Dados extras precisa conter um JSON válido.');
            }
        }

        const res = await fetch(API_BASE + 'landing-page.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                section_key: sectionKey || null,
                section_title: titleEl ? titleEl.value : '',
                section_subtitle: subtitleEl ? subtitleEl.value : '',
                section_content: contentEl ? contentEl.value : '',
                section_image: imageEl ? imageEl.value : '',
                section_order: orderEl ? Number(orderEl.value || 0) : 0,
                is_active: activeEl && activeEl.checked ? 1 : 0,
                extra_data: extraData
            })
        });

        const data = await res.json();
        if (data.success) {
            showToast(data.message || 'Seção atualizada com sucesso!');
            await loadLandingAdminSections();
        } else {
            alert(data.error || 'Erro ao salvar seção.');
        }
    } catch (err) {
        alert(err && err.message ? err.message : 'Erro de conexão ao salvar.');
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'Salvar Seção'; }
    }
}

// Garante visibilidade global
window.renderLandingPageTab = renderLandingPageTab;
window.handleLandingSectionSubmit = handleLandingSectionSubmit;
function openCadastroModal() {
    var landingPage = document.getElementById('landing-page');
    var loginScreen = document.getElementById('login-screen');
    
    // Oculta a landing page e abre o modal de cadastro
    if (landingPage) landingPage.style.display = 'none';
    if (loginScreen) loginScreen.style.display = 'none';
    
    showCadastro(); // Chama a função que exibe o modal de cadastro
}
// Torna global para usar nos botões
window.openCadastroModal = openCadastroModal;
// Torna global
window.renderLandingPageTab = renderLandingPageTab;
// Torna global para uso no HTML e nos botões
window.loadSuperAdminDashboardStats = loadSuperAdminDashboardStats;
window.renderLatestCompaniesTab = renderLatestCompaniesTab;
window.renderLandingPageTab = renderLandingPageTab;

console.log("MapsProspector Pro - Sistema totalmente carregado.");
