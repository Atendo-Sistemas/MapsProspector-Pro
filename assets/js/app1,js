/**
 * MapsProspector Pro - JavaScript Principal (interface única PHP/XAMPP)
 * Histórico de pesquisas: gravado e listado via banco de dados (api/search.php e api/history.php).
 */

var API_BASE = (typeof window.API_BASE_URL !== 'undefined' ? window.API_BASE_URL : 'api/');

// Estado da aplicação (histórico vem do banco via history.php)
var AppState = window.AppState || {
    user: null,
    tenant: null,
    tokenUsage: null,  // { used, limit, limitReached } para aviso de limite de tokens
    impersonating: false,
    impersonatingTenantName: '',
    platformCompanyName: null,  // Nome da empresa SaaS (Configuração SaaS) para título da aba
    config: null,
    activeTab: 'dashboard',
    userCoords: null,
    userLocationName: '',
    locStatus: 'idle', // idle | loading | success | error
    leads: [],
    searchId: null,  // ID da pesquisa atual (para desbloqueio; dados vêm bloqueados)
    history: [],     // preenchido por loadHistory() -> api/history.php (banco)
    folders: [],    // pastas para organizar pesquisas
    visibleCount: 12,
    currentSearch: { query: '', location: '', tag: '', folderId: null, folderName: '' }  // contexto da pesquisa atual (para exportar)
};

// Tema (claro/escuro) — persistido em localStorage
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

function applyTheme(theme) {
    var root = document.documentElement;
    if (theme === 'dark') {
        root.classList.add('dark');
    } else {
        root.classList.remove('dark');
    }
    try { localStorage.setItem(THEME_STORAGE_KEY, theme); } catch (e) {}
    
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

// Inicialização
function initApp() {
    console.log('DOM loaded, initializing...');
    applyTheme(getStoredTheme());
    // Mostrar landing page imediatamente enquanto verifica auth
    console.log('Calling showLandingPage...');
    showLandingPage();
    console.log('Calling checkAuth...');
    checkAuth();
    console.log('Setting up event listeners...');
    setupEventListeners();
    console.log('Initialization complete');
}

// Execute on both DOMContentLoaded and load to ensure everything is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initApp);
} else {
    initApp();
}

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
                showLandingPage();
            }
        } else {
            showLandingPage();
        }
    } catch (e) {
        console.error('Erro ao verificar auth:', e);
        showLandingPage();
    }
}

// Login
async function handleLogin(e) {
    console.log('handleLogin called');
    if (e && e.preventDefault) {
        e.preventDefault();
        console.log('preventDefault called');
    }
    const emailInput = document.getElementById('login-email');
    const passwordInput = document.getElementById('login-password');
    const errorEl = document.getElementById('login-error');
    const btn = document.getElementById('btn-login');
    
    console.log('Elements found:', { emailInput: !!emailInput, passwordInput: !!passwordInput, btn: !!btn });
    
    const email = (emailInput && emailInput.value ? emailInput.value.trim().toLowerCase() : '') || '';
    const password = (passwordInput && passwordInput.value ? passwordInput.value : '') || '';
    
    console.log('Email:', email, 'Password length:', password.length);
    
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
    btn.innerHTML = '<span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin inline-block"></span> Entrando...';
    try {
        console.log('Sending login request...');
        const res = await fetch(API_BASE + 'auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', email: email, password: password })
        });
        console.log('Response status:', res.status);
        const text = await res.text();
        console.log('Auth response:', text.substring(0, 200));
        
        if (!text || text.trim() === '') {
            throw new Error('Resposta vazia do servidor');
        }
        
        const data = JSON.parse(text);
        console.log('Parsed data, success:', data.success);
        
        if (data.success) {
            console.log('Login successful!');
            
            AppState.user = data.data.user;
            AppState.tenant = data.data.tenant || null;
            AppState.tokenUsage = data.data.tokenUsage || null;
            AppState.impersonating = !!(data.data && data.data.impersonating);
            AppState.impersonatingTenantName = (data.data && data.data.impersonatingTenantName) || '';
            
            // Reload page to show dashboard - session is already set
            console.log('Reloading page...');
            window.location.reload();
        } else {
            console.log('Login failed:', data.error);
            alert('Login failed: ' + (data.error || 'unknown error'));
            if (errorEl) {
                errorEl.textContent = data.error || 'Falha no login. Tente novamente.';
                errorEl.classList.remove('hidden');
            } else {
                alert('Erro ao fazer login: ' + (data.error || 'Tente novamente.'));
            }
        }
    } catch (err) {
        console.error('Login error:', err);
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

// Mostra/Oculta telas
function showLogin() {
    var loginScreen = document.getElementById('login-screen');
    var dashboard = document.getElementById('dashboard');
    var landingPage = document.getElementById('landing-page');
    var themeToggle = document.getElementById('btn-theme-toggle');
    if (landingPage) landingPage.style.display = 'none';
    if (loginScreen) loginScreen.style.display = 'flex';
    if (dashboard) dashboard.style.display = 'none';
    if (themeToggle) themeToggle.classList.add('hidden');
}

function showLandingPage() {
    console.log('showLandingPage called');
    var loginScreen = document.getElementById('login-screen');
    var dashboard = document.getElementById('dashboard');
    var landingPage = document.getElementById('landing-page');
    var themeToggle = document.getElementById('btn-theme-toggle');
    
    if (loginScreen) loginScreen.style.display = 'none';
    if (dashboard) dashboard.style.display = 'none';
    if (landingPage) {
        landingPage.style.display = 'block';
        console.log('Landing page shown');
    }
    
    // Show theme toggle on landing page
    if (themeToggle) {
        themeToggle.classList.remove('hidden');
    }
    
    // Update theme icons for landing page
    updateThemeIconsLanding();
    
    console.log('Loading landing page content and plans...');
    loadLandingPageContent();
    loadLandingPagePlans();
}

function loadLandingPageContent() {
    console.log('loadLandingPageContent called');
    console.log('API_BASE:', API_BASE);
    console.log('Fetching API...');
    fetch(API_BASE + 'landing-page-public.php')
        .then(function(r) { 
            console.log('API response status:', r.status);
            return r.json(); 
        })
        .then(function(data) {
            console.log('API data received:', data);
            if (data.success && data.sections) {
                console.log('Rendering sections, count:', data.sections.length);
                renderLandingSections(data.sections);
            } else {
                console.log('No sections to render or data.success is false');
            }
        })
        .catch(function(err) {
            console.error('Error loading landing content:', err);
        });
}

function loadLandingPagePlans() {
    fetch(API_BASE + 'plans-public.php')
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.data && data.data.items) {
                renderLandingPlans(data.data.items);
            }
        })
        .catch(function(err) {
            console.error('Error loading plans:', err);
        });
}

function renderLandingSections(sections) {
    console.log('renderLandingSections called with', sections.length, 'sections');
    
    var heroTitle = document.getElementById('lp-hero-title');
    var heroSubtitle = document.getElementById('lp-hero-subtitle');
    var heroContent = document.getElementById('lp-hero-content');
    var heroCta = document.getElementById('lp-hero-cta');
    var featuresTitle = document.getElementById('lp-features-title');
    var featuresSubtitle = document.getElementById('lp-features-subtitle');
    var featuresGrid = document.getElementById('lp-features-grid');
    var howTitle = document.getElementById('lp-how-title');
    var howSubtitle = document.getElementById('lp-how-subtitle');
    var howContent = document.getElementById('lp-how-content');
    var benefitsTitle = document.getElementById('lp-benefits-title');
    var benefitsSubtitle = document.getElementById('lp-benefits-subtitle');
    var benefitsGrid = document.getElementById('lp-benefits-grid');
    var testimonialsTitle = document.getElementById('lp-testimonials-title');
    var testimonialsSubtitle = document.getElementById('lp-testimonials-subtitle');
    var testimonialsGrid = document.getElementById('lp-testimonials-grid');
    var faqTitle = document.getElementById('lp-faq-title');
    var faqSubtitle = document.getElementById('lp-faq-subtitle');
    var faqList = document.getElementById('lp-faq-list');
    var ctaTitle = document.getElementById('lp-cta-title');
    var ctaSubtitle = document.getElementById('lp-cta-subtitle');
    var ctaContent = document.getElementById('lp-cta-content');
    var ctaButton = document.getElementById('lp-cta-button');
    var ctaSecondary = document.getElementById('lp-cta-secondary');
    var footerContent = document.getElementById('lp-footer-content');
    
    console.log('Elements found:', {
        heroTitle: !!heroTitle,
        heroSubtitle: !!heroSubtitle,
        featuresGrid: !!featuresGrid,
        landingPage: !!document.getElementById('landing-page')
    });
    
    var icons = {
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
    
    sections.forEach(function(section) {
        switch(section.sectionKey) {
            case 'hero':
                if (heroTitle) heroTitle.textContent = section.sectionTitle || '';
                if (heroSubtitle) heroSubtitle.textContent = section.sectionSubtitle || '';
                if (heroContent) heroContent.innerHTML = section.sectionContent || '';
                if (heroCta && section.extraData && section.extraData.cta_text) {
                    heroCta.textContent = section.extraData.cta_text;
                }
                break;
            case 'features':
                if (featuresTitle) featuresTitle.textContent = section.sectionTitle || '';
                if (featuresSubtitle) featuresSubtitle.textContent = section.sectionSubtitle || '';
                if (featuresGrid && section.extraData && section.extraData.items) {
                    featuresGrid.innerHTML = section.extraData.items.map(function(item) {
                        return '<div class="bg-slate-50 dark:bg-slate-800 rounded-2xl p-6 hover:shadow-lg transition">' +
                            '<div class="w-14 h-14 bg-blue-100 dark:bg-blue-900/30 rounded-2xl flex items-center justify-center text-blue-600 dark:text-blue-400 mb-4">' + (icons[item.icon] || icons.search) + '</div>' +
                            '<h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">' + item.title + '</h3>' +
                            '<p class="text-slate-600 dark:text-slate-400 text-sm">' + item.description + '</p></div>';
                    }).join('');
                }
                break;
            case 'how_it_works':
                if (howTitle) howTitle.textContent = section.sectionTitle || '';
                if (howSubtitle) howSubtitle.textContent = section.sectionSubtitle || '';
                if (howContent) howContent.innerHTML = section.sectionContent || '';
                break;
            case 'benefits':
                if (benefitsTitle) benefitsTitle.textContent = section.sectionTitle || '';
                if (benefitsSubtitle) benefitsSubtitle.textContent = section.sectionSubtitle || '';
                if (benefitsGrid && section.extraData && section.extraData.items) {
                    benefitsGrid.innerHTML = section.extraData.items.map(function(item) {
                        return '<div class="bg-white dark:bg-slate-800 rounded-2xl p-6 shadow-sm">' +
                            '<div class="w-12 h-12 bg-emerald-100 dark:bg-emerald-900/30 rounded-xl flex items-center justify-center text-emerald-600 dark:text-emerald-400 mb-4">' + (icons[item.icon] || icons['check-circle']) + '</div>' +
                            '<h3 class="text-lg font-bold text-slate-900 dark:text-white mb-2">' + item.title + '</h3>' +
                            '<p class="text-slate-600 dark:text-slate-400 text-sm">' + item.description + '</p></div>';
                    }).join('');
                }
                break;
            case 'testimonials':
                if (testimonialsTitle) testimonialsTitle.textContent = section.sectionTitle || '';
                if (testimonialsSubtitle) testimonialsSubtitle.textContent = section.sectionSubtitle || '';
                if (testimonialsGrid && section.extraData && section.extraData.items) {
                    testimonialsGrid.innerHTML = section.extraData.items.map(function(item) {
                        return '<div class="bg-slate-50 dark:bg-slate-800 rounded-2xl p-6">' +
                            '<p class="text-slate-700 dark:text-slate-300 mb-4">"' + item.text + '"</p>' +
                            '<div class="flex items-center gap-3">' +
                            '<div class="w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">' + item.name.charAt(0) + '</div>' +
                            '<div><p class="font-bold text-slate-900 dark:text-white text-sm">' + item.name + '</p>' +
                            '<p class="text-slate-500 dark:text-slate-400 text-xs">' + item.company + '</p></div></div></div>';
                    }).join('');
                }
                break;
            case 'faq':
                if (faqTitle) faqTitle.textContent = section.sectionTitle || '';
                if (faqSubtitle) faqSubtitle.textContent = section.sectionSubtitle || '';
                if (faqList && section.extraData && section.extraData.items) {
                    faqList.innerHTML = section.extraData.items.map(function(item) {
                        return '<div class="bg-white dark:bg-slate-800 rounded-xl p-6 shadow-sm">' +
                            '<h3 class="font-bold text-slate-900 dark:text-white mb-2">' + item.question + '</h3>' +
                            '<p class="text-slate-600 dark:text-slate-400 text-sm">' + item.answer + '</p></div>';
                    }).join('');
                }
                break;
            case 'cta':
                if (ctaTitle) ctaTitle.textContent = section.sectionTitle || '';
                if (ctaSubtitle) ctaSubtitle.textContent = section.sectionSubtitle || '';
                if (ctaContent) ctaContent.innerHTML = section.sectionContent || '';
                if (ctaButton && section.extraData && section.extraData.cta_text) {
                    ctaButton.textContent = section.extraData.cta_text;
                }
                if (ctaSecondary && section.extraData && section.extraData.secondary_cta_text) {
                    ctaSecondary.textContent = section.extraData.secondary_cta_text;
                    ctaSecondary.classList.remove('hidden');
                }
                break;
            case 'footer':
                if (footerContent) footerContent.innerHTML = section.sectionContent || '';
                break;
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
        
        return '<div class="bg-slate-50 dark:bg-slate-800 rounded-2xl p-6 ' + (isFree ? 'ring-2 ring-blue-500' : '') + '">' +
            '<h3 class="text-xl font-black text-slate-900 dark:text-white mb-2">' + plan.name + '</h3>' +
            '<div class="mb-4"><span class="text-3xl font-black text-slate-900 dark:text-white">' + price + '</span>' +
            (!isFree ? '<span class="text-slate-500 dark:text-slate-400">/mês</span>' : '') + '</div>' +
            '<p class="text-slate-600 dark:text-slate-400 text-sm mb-6">' + (plan.tokenLimit || 0) + ' tokens por mês</p>' +
            '<button type="button" onclick="openLoginModal()" class="block w-full py-3 text-center ' + (isFree ? 'bg-blue-600 hover:bg-blue-700' : 'bg-slate-900 hover:bg-slate-800') + ' text-white font-bold rounded-xl transition">Assinar</button></div>';
    }).join('');
}

function openLoginModal() {
    var landingPage = document.getElementById('landing-page');
    var loginScreen = document.getElementById('login-screen');
    if (landingPage) landingPage.style.display = 'none';
    if (loginScreen) loginScreen.style.display = 'flex';
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

function updateDocumentTitle() {
    if (!AppState.user) return;
    var isSuperAdmin = String(AppState.user.profile).toLowerCase() === 'super_admin';
    var companyName = isSuperAdmin
        ? (AppState.platformCompanyName && String(AppState.platformCompanyName).trim() ? AppState.platformCompanyName.trim() : 'MapsProspector Pro')
        : (AppState.tenant && AppState.tenant.name ? String(AppState.tenant.name).trim() : 'Empresa');
    var instanceName = (AppState.config && AppState.config.tenantName && String(AppState.config.tenantName).trim()) ? String(AppState.config.tenantName).trim() : 'Nome da empresa SaaS';
    document.title = companyName + ' | ' + instanceName;
}

function loadSidebarCompanyName() {
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
    fetch(API_BASE + 'platform-config.php', { credentials: 'include' }).then(function(r) { return r.json(); }).then(function(data) {
        var d = data.data || data;
        var name = (d && d.saasCompanyName && String(d.saasCompanyName).trim()) ? String(d.saasCompanyName).trim() : 'Nome da empresa SaaS';
        AppState.platformCompanyName = name;
        var el = document.getElementById('sidebar-company-name');
        if (el) el.textContent = name;
        var instanceName = (AppState.config && AppState.config.tenantName && String(AppState.config.tenantName).trim()) ? String(AppState.config.tenantName).trim() : (AppState.tenant && AppState.tenant.name) ? AppState.tenant.name : (isSuperAdmin ? 'Nome da empresa SaaS' : 'Empresa');
        var instanceEl = document.getElementById('sidebar-instance-name');
        if (instanceEl) instanceEl.textContent = instanceName;
        var subtitleEl = document.getElementById('header-dashboard-subtitle');
        if (subtitleEl) {
            if (isSuperAdmin) {
                subtitleEl.textContent = 'Dashboard ' + name.toUpperCase();
            } else {
                subtitleEl.textContent = 'Dashboard ' + instanceName.toUpperCase();
            }
        }
        updateDocumentTitle();
    }).catch(function() {});
}

function showDashboard() {
    console.log('=== showDashboard() START ===');
    
    var loginScreen = document.getElementById('login-screen');
    var dashboard = document.getElementById('dashboard');
    var landingPage = document.getElementById('landing-page');
    var themeToggle = document.getElementById('btn-theme-toggle');
    
    console.log('login-screen:', loginScreen);
    console.log('dashboard:', dashboard);
    
    // Force show dashboard, hide login and landing page
    if (landingPage) {
        landingPage.style.display = 'none';
    }
    if (loginScreen) {
        loginScreen.setAttribute('data-hidden', 'true');
        loginScreen.style.setProperty('display', 'none', 'important');
        loginScreen.style.display = 'none';
        console.log('login-screen hidden');
    }
    if (dashboard) {
        dashboard.removeAttribute('data-hidden');
        dashboard.style.setProperty('display', 'flex', 'important');
        dashboard.style.display = 'flex';
        console.log('dashboard shown');
    }
    
    // Show theme toggle on dashboard
    if (themeToggle) {
        themeToggle.classList.remove('hidden');
    }
    
    // Also toggle classes
    if (loginScreen) loginScreen.classList.add('hidden');
    if (dashboard) dashboard.classList.remove('hidden');
    
    console.log('=== showDashboard() END ===');
    
    loadSidebarCompanyName();
    renderHeaderTokenWarning();
    updateSearchApiUI();
    if (AppState.user) {
        var userNameEl = document.getElementById('user-name');
        if (userNameEl) userNameEl.textContent = AppState.user.name;
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
        }// Ocultar o status de conexão da API do Google Maps para usuários normais (White-Label)
        var apiStatusWidget = document.getElementById('sidebar-api-status-widget');
        if (apiStatusWidget) {
            var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
            if (isSuperAdmin) {
                apiStatusWidget.classList.remove('hidden');
            } else {
                apiStatusWidget.classList.add('hidden');
            }
        }
        // =====================================================================
        // CONTROLE DE ACESSO POR CARGO (ADMIN VS COLABORADOR COMUM)
        // =====================================================================
        if (AppState.user) {
            const profile = String(AppState.user.profile).toLowerCase();
            const isAdmin = profile === 'admin' || profile === 'super_admin';
            
            // Lista de botões administrativos que os colaboradores comuns não podem ver
            const adminButtons = [
                document.getElementById('nav-btn-choose-plan'),
                document.getElementById('nav-btn-team'),
                document.querySelector('[data-tab="cnpj-lookup"]'), // <-- Garanta que esta linha existe aqui
                document.querySelector('[data-tab="request-credits"]'),
                document.querySelector('[data-tab="settings"]')
            ];
            
            adminButtons.forEach(btn => {
                if (btn) {
                    if (isAdmin) {
                        btn.classList.remove('hidden');
                    } else {
                        btn.classList.add('hidden');
                    }
                }
            });
        }
        // =====================================================================
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
        AppState.platformCompanyName = null;
        document.title = 'MapsProspector Pro | CRM Integration';
        var tokenBanner = document.getElementById('header-token-warning');
        if (tokenBanner) tokenBanner.classList.add('hidden');
        document.getElementById('user-dropdown').classList.add('hidden');
        showLogin();
    }).catch(function() {
        AppState.user = null;
        AppState.tenant = null;
        AppState.tokenUsage = null;
        AppState.platformCompanyName = null;
        document.title = 'MapsProspector Pro | CRM Integration';
        showLogin();
    });
}

// Cadastro de empresa (toggle + submit)
function showCadastro() {
    const overlay = document.getElementById('modal-cadastro-overlay');
    const success = document.getElementById('cadastro-success');
    if (overlay) overlay.classList.remove('hidden');
    if (success) { success.classList.add('hidden'); success.textContent = ''; }
    document.body.style.overflow = 'hidden';
}
function hideCadastro() {
    const overlay = document.getElementById('modal-cadastro-overlay');
    if (overlay) overlay.classList.add('hidden');
    document.body.style.overflow = '';
}
async function handleCadastroSubmit(e) {
    if (e && e.preventDefault) e.preventDefault();
    const company = (document.getElementById('reg-company') && document.getElementById('reg-company').value || '').trim();
    const email = (document.getElementById('reg-email') && document.getElementById('reg-email').value || '').trim().toLowerCase();
    const phone = (document.getElementById('reg-phone') && document.getElementById('reg-phone').value || '').trim();
    const name = (document.getElementById('reg-name') && document.getElementById('reg-name').value || '').trim();
    const regPassword = (document.getElementById('reg-password') && document.getElementById('reg-password').value) || '';
    const regPasswordConfirm = (document.getElementById('reg-password-confirm') && document.getElementById('reg-password-confirm').value) || '';
    const errorEl = document.getElementById('reg-error');
    const btn = document.getElementById('btn-cadastro');
    if (errorEl) { errorEl.classList.add('hidden'); errorEl.textContent = ''; }
    if (!company) { if (errorEl) { errorEl.textContent = 'Nome da empresa é obrigatório.'; errorEl.classList.remove('hidden'); } return; }
    if (!email || !email.includes('@')) { if (errorEl) { errorEl.textContent = 'E-mail do administrador é obrigatório e deve ser válido.'; errorEl.classList.remove('hidden'); } return; }
    if (regPassword.length < 6) { if (errorEl) { errorEl.textContent = 'A senha deve ter no mínimo 6 caracteres.'; errorEl.classList.remove('hidden'); } return; }
    if (regPassword !== regPasswordConfirm) { if (errorEl) { errorEl.textContent = 'As senhas não coincidem.'; errorEl.classList.remove('hidden'); } return; }
    if (btn) btn.disabled = true;
    try {
        const res = await fetch(API_BASE + 'register.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                companyName: company, 
                adminEmail: email, 
                adminPhone: phone, 
                adminName: name || undefined, 
                adminPassword: regPassword 
            })  
        });
        const data = await res.json();
        if (data.success) {
            hideCadastro();
            const successEl = document.getElementById('cadastro-success');
            const loginEmail = document.getElementById('login-email');
            if (successEl) { successEl.textContent = data.message || 'Empresa cadastrada. Faça login com seu e-mail e senha.'; successEl.classList.remove('hidden'); }
            if (loginEmail) loginEmail.value = email;
            const regCnpj = document.getElementById('reg-cnpj');
            if (regCnpj) regCnpj.value = '';
            if (document.getElementById('reg-company')) document.getElementById('reg-company').value = '';
            if (document.getElementById('reg-email')) document.getElementById('reg-email').value = '';
            if (document.getElementById('reg-name')) document.getElementById('reg-name').value = '';
            if (document.getElementById('reg-phone')) document.getElementById('reg-phone').value = '';
            if (document.getElementById('reg-password')) document.getElementById('reg-password').value = '';
            if (document.getElementById('reg-password-confirm')) document.getElementById('reg-password-confirm').value = '';
        } else {
            if (errorEl) { errorEl.textContent = data.error || 'Erro ao cadastrar.'; errorEl.classList.remove('hidden'); }
        }
    } catch (err) {
        if (errorEl) { errorEl.textContent = 'Erro de conexão. Verifique o servidor.'; errorEl.classList.remove('hidden'); }
    } finally {
        if (btn) btn.disabled = false;
    }
}

// Modal Perfil (alterar senha)
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
    if (phone) phone.value = AppState.user.phone || ''; // Carrega o telefone atual
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
    
    // Validação de senha apenas se o usuário digitar algo nos campos
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
            AppState.user.phone = newPhone; // Atualiza o estado global
            setTimeout(hideModalPerfil, 1500);
        } else {
            errorEl.textContent = data.error || 'Erro ao atualizar perfil.';
            errorEl.classList.remove('hidden');
        }
    } catch (err) {
        errorEl.textContent = 'Erro de conexão.';
        errorEl.classList.remove('hidden');
    } finally {
        if (btn) btn.disabled = false;
    }
}
// Event Listeners
function setupEventListeners() {
    console.log('Setting up event listeners');
    const loginForm = document.getElementById('login-form');
    console.log('Login form element:', loginForm);
    if (loginForm) {
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            handleLogin(e);
        });
        console.log('Login form submit listener added');
    }
    const btn = document.getElementById('btn-login');
    if (btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            handleLogin(e);
        });
    }
    const linkCadastro = document.getElementById('link-cadastro');
    if (linkCadastro) linkCadastro.addEventListener('click', showCadastro);
    const btnCadastroVoltar = document.getElementById('btn-cadastro-voltar');
    if (btnCadastroVoltar) btnCadastroVoltar.addEventListener('click', hideCadastro);
    const modalCadastroFechar = document.getElementById('modal-cadastro-fechar');
    if (modalCadastroFechar) modalCadastroFechar.addEventListener('click', hideCadastro);
    const modalCadastroOverlay = document.getElementById('modal-cadastro-overlay');
    if (modalCadastroOverlay) {
        modalCadastroOverlay.addEventListener('click', function(e) {
            if (e.target === modalCadastroOverlay) hideCadastro();
        });
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const overlay = document.getElementById('modal-cadastro-overlay');
            const perfilOverlay = document.getElementById('modal-perfil-overlay');
            if (overlay && !overlay.classList.contains('hidden')) hideCadastro();
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

    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            setActiveTab(tab);
        });
    });
    
    // Botão de alternar tema (claro/escuro) - Dashboard
    var btnThemeToggle = document.getElementById('btn-theme-toggle');
    if (btnThemeToggle) {
        btnThemeToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleTheme();
        });
    }
    
    // Botão de alternar tema (claro/escuro) - Landing Page
    var btnThemeToggleLanding = document.getElementById('btn-theme-toggle-landing');
    if (btnThemeToggleLanding) {
        btnThemeToggleLanding.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleTheme();
            updateThemeIconsLanding();
        });
    }

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
    var btnPerfil = document.getElementById('btn-perfil');
    if (btnPerfil) {
        btnPerfil.addEventListener('click', function(e) {
            e.stopPropagation();
            if (userDropdown) userDropdown.classList.add('hidden');
            showModalPerfil();
        });
    }
    if (btnLogout) {
        btnLogout.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.add('hidden');
            logout();
        });
    }
    var btnStopImpersonate = document.getElementById('btn-stop-impersonate');
    if (btnStopImpersonate) {
        btnStopImpersonate.addEventListener('click', function() {
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
                } else {
                    alert(res.error || 'Erro ao sair do acesso.');
                }
            }).catch(function() { alert('Erro de conexão.'); });
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
        '<h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-8">Estatísticas da conta</h3>' +
        '<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">' +
        '<div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">' +
        '<div class="flex items-center gap-4 mb-4"><div class="w-14 h-14 bg-blue-100 rounded-2xl flex items-center justify-center">' +
        '<svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg></div>' +
        '<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Tokens utilizados</p><p class="text-3xl font-black text-slate-900 dark:text-slate-100">' + used + '</p></div></div>' +
        '<p class="text-xs text-slate-500 dark:text-slate-400">Tokens usados no período: 1 token = 1 página de resultados (até 20 itens por página)</p></div>' +
        '<div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">' +
        '<div class="flex items-center gap-4 mb-4"><div class="w-14 h-14 bg-emerald-100 rounded-2xl flex items-center justify-center">' +
        '<svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg></div>' +
        '<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Tokens permitidos (plano)</p><p class="text-3xl font-black text-slate-900 dark:text-slate-100">' + limitLabel + '</p></div></div>' +
        '<p class="text-xs text-slate-500 dark:text-slate-400">Limite do plano vinculado à sua empresa neste período</p></div>' +
        '<div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm sm:col-span-2 lg:col-span-1">' +
        '<div class="flex items-center gap-4 mb-4"><div class="w-14 h-14 bg-slate-100 dark:bg-slate-700 rounded-2xl flex items-center justify-center">' +
        '<svg class="w-7 h-7 text-slate-600 dark:text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" /></svg></div>' +
        '<div><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Disponível</p><p class="text-3xl font-black text-slate-900 dark:text-slate-100">' + available + '</p></div></div>' +
        '<p class="text-xs text-slate-500 dark:text-slate-400">Tokens restantes para novas buscas neste período</p></div>' +
        '</div>' +
        '<div class="mt-8 p-6 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-600">' +
        '<p class="text-sm font-bold text-slate-700 dark:text-slate-300"><span class="text-slate-500 dark:text-slate-400">Empresa:</span> ' + tenantName + '</p>' +
        (!hasTenant ? '<p class="text-xs text-slate-500 dark:text-slate-400 mt-2">Conta plataforma (Super Admin) — não há limite de tokens por empresa.</p>' : '') +
        '</div>' +
        (!hasTenant ? '<div class="mt-10"><h3 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-6">Uso de tokens por empresa</h3><div id="dashboard-tenants-usage" class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm overflow-hidden"><div class="p-8 text-center text-slate-500 dark:text-slate-400"><span class="inline-block w-8 h-8 border-2 border-slate-300 border-t-blue-600 rounded-full animate-spin"></span><p class="mt-3 text-sm font-bold">Carregando empresas…</p></div></div></div>' : '') +
        '</div>';
    if (!hasTenant) {
        setTimeout(function() { loadDashboardTenantsUsage(); }, 0);
    }
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

// Solicitar Créditos (Normal): usuário solicita X créditos
function renderRequestCreditsTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-2xl mx-auto">' +
        '<h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-6">Solicitar créditos</h3>' +
        '<p class="text-sm text-slate-500 dark:text-slate-400 mb-8">Solicite créditos (tokens) adicionais para sua empresa. O administrador da plataforma analisará e poderá aprovar ou recusar.</p>' +
        '<div id="request-credits-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>' +
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
        var amountEl = document.getElementById('request-credits-amount');
        var totalWrap = document.getElementById('request-credits-total-wrap');
        var totalValue = document.getElementById('request-credits-total-value');
        if (pricePerCredit > 0 && totalWrap && totalValue) {
            totalWrap.classList.remove('hidden');
            var num = parseInt(amountEl ? amountEl.value : '', 10);
            totalValue.textContent = (!isNaN(num) && num >= 100) ? 'R$ ' + (num * pricePerCredit).toFixed(2).replace('.', ',') : '—';
        }
    }).catch(function() {
        listEl.innerHTML = '<div class="py-12 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Erro ao carregar</p></div>';
    });
}

// Meu plano: usuário escolhe plano e solicita; fica pendente até Super Admin liberar
function renderChoosePlanTab(contentArea) {
    var currentPlanName = (AppState.tenant && AppState.tenant.planName) ? AppState.tenant.planName : '';
    var currentPlanId = (AppState.tenant && AppState.tenant.planId) ? String(AppState.tenant.planId) : '';
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto">' +
        '<h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-2">Meu plano</h3>' +
        '<p class="text-sm text-slate-500 dark:text-slate-400 mb-8">Escolha um plano para sua empresa. Após solicitar, o administrador confirmará e seu plano será atualizado.</p>' +
        (currentPlanName ? '<div class="mb-8 p-6 bg-slate-50 dark:bg-slate-800/50 rounded-2xl border border-slate-200 dark:border-slate-600"><p class="text-[10px] font-black text-slate-400 uppercase tracking-wider mb-1">Plano atual</p><p class="text-lg font-bold text-slate-900 dark:text-slate-100">' + currentPlanName + '</p></div>' : '') +
        '<div id="choose-plan-pending" class="hidden mb-8 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium"></div>' +
        '<div id="choose-plan-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>' +
        '<div id="choose-plan-spinner" class="py-24 text-center"><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block"></span></div>' +
        '<div id="choose-plan-grid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-10 hidden"></div>' +
        '<div id="choose-plan-history" class="hidden mt-10"><h4 class="text-lg font-black text-slate-900 dark:text-slate-100 mb-4">Histórico de solicitações</h4><div id="choose-plan-history-list"></div></div></div>';
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
            var isTrial = p.slug === 'trial';
            var priceText = isTrial ? 'Grátis' : ((p.priceMonthly != null && parseFloat(p.priceMonthly) > 0) ? ('R$ ' + parseFloat(p.priceMonthly).toFixed(2).replace('.', ',')) : '—');
            var tokenText = isTrial ? ((p.tokenLimit || 0).toLocaleString('pt-BR') + ' créditos grátis') : ((p.tokenLimit || 0).toLocaleString('pt-BR') + ' tokens · ' + (p.period === 'yearly' ? 'ano' : 'mês'));
            html += '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border-2 ' + (isCurrent ? 'border-blue-400 bg-blue-50/50 dark:bg-blue-900/30' : 'border-slate-200 dark:border-slate-600') + '">';
            html += '<div class="flex items-center gap-4 mb-4"><div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center text-lg font-black text-blue-600">' + (p.tokenLimit >= 1000 ? (p.tokenLimit / 1000) + 'K' : p.tokenLimit) + '</div>';
            html += '<div><h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg">' + (p.name || '') + '</h4><p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + tokenText + '</p></div></div>';
            html += '<p class="text-2xl font-black mb-4">' + (isTrial ? '<span class="text-emerald-600">' + priceText + '</span>' : priceText) + '<span class="text-sm font-bold text-slate-400">/mês</span></p>';
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
                hHtml += '<div class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-600 flex items-center justify-between"><span class="font-bold text-slate-800 dark:text-slate-200">' + (r.planName || 'Plano') + '</span><span class="text-xs font-bold ' + statusClass + '">' + statusLabel + (dateStr ? ' · ' + dateStr : '') + '</span></div>';
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

// Tabs
function setActiveTab(tab) {
    AppState.activeTab = tab;
    
    // Atualiza botões
    document.querySelectorAll('.tab-btn').forEach(btn => {
        if (btn.dataset.tab === tab) {
            btn.className = 'tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm bg-blue-600 text-white shadow-xl shadow-blue-900/30';
        } else {
            btn.className = 'tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 hover:text-slate-900 dark:text-slate-300 dark:hover:text-slate-300';
        }
    });
    
    // Atualiza título
    const titles = {
        dashboard: 'Dashboard',
        search: 'Prospecção Inteligente',
        history: 'Arquivo de Buscas',
        'cnpj-lookup': 'Consulta CNPJ (Simples)', 
        'cnpj-advanced': 'Busca Avançada (Segmento)',
        'request-credits': 'Solicitar Créditos',
        'choose-plan': 'Meu plano',
        team: 'Minha Equipe', 
        'saas-config': 'Empresa SaaS',
        'latest-companies': 'Recém-Abertas',
        plans: 'Planos',
        companies: 'Empresas',
        credits: 'Créditos',
        'api-busca': 'API de Busca',
        settings: 'Integração CRM',
        'landing-page': 'Landing Page'
    };
    document.getElementById('page-title').textContent = titles[tab] || 'Dashboard';
    
    loadTab(tab);
}

// Carrega conteúdo da tab
function loadTab(tab) {
    const contentArea = document.getElementById('content-area');
    // SEGURANÇA: Impede que colaboradores (user) acessem abas administrativas
    if (AppState.user) {
        const profile = String(AppState.user.profile).toLowerCase();
        if (profile === 'user') {
            const restrictedTabs = [
                'request-credits', 'choose-plan', 'team', 'settings', 
                'saas-config', 'plans', 'companies', 'credits', 
                'api-busca', 'landing-page', 'cnpj-lookup',
                'cnpj-advanced'
            ];
            if (restrictedTabs.includes(tab)) {
                setActiveTab('search'); // Redireciona o colaborador direto para Prospecção
                return;
            }
        }
    }
    if (tab === 'dashboard') {
        renderDashboardTab(contentArea);
    } else if (tab === 'request-credits') {
        renderRequestCreditsTab(contentArea);
    } else if (tab === 'latest-companies') {
        renderLatestCompaniesTab(contentArea);
    } else if (tab === 'team') {
        renderTeamTab(contentArea);
    } else if (tab === 'credits') {
        var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
        if (isSuperAdmin) renderCreditsAdminTab(contentArea);
        else contentArea.innerHTML = '<div class="max-w-4xl mx-auto py-24 text-center text-slate-500 dark:text-slate-400 font-bold">Acesso restrito ao administrador da plataforma.</div>';
    } else if (tab === 'search') {
        contentArea.innerHTML = getProspectingHTML();
        setupProspectingEvents();
    } else if (tab === 'history') {
        loadHistory();
    } else if (tab === 'cnpj-lookup') {
        renderCnpjLookupTab(contentArea);
    } else if (tab === 'cnpj-advanced') {
        renderCnpjAdvancedTab(contentArea);
        setupCnpjAdvancedEvents();
    } else if (tab === 'choose-plan') {
        var isSuperAdminChoose = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
        if (isSuperAdminChoose) {
            contentArea.innerHTML = '<div class="max-w-4xl mx-auto py-24 text-center"><p class="text-slate-600 dark:text-slate-400 font-bold mb-2">Conta Super Admin — ilimitada</p><p class="text-sm text-slate-500 dark:text-slate-400">Não é possível alterar plano; sua conta não possui limite de tokens. Cuidado, pois API de Scrapy será contabilizada.</p></div>';
        } else {
            renderChoosePlanTab(contentArea);
        }
    } else if (tab === 'saas-config') {
        renderSaasConfigTab(contentArea);
    } else if (tab === 'plans') {
        loadPlansTab(contentArea);
    } else if (tab === 'companies') {
        loadCompaniesTab(contentArea);
    } else if (tab === 'api-busca') {
        contentArea.innerHTML = getApiBuscaHTML();
        var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
        var blockScraper = document.getElementById('block-scraper-api-key-admin');
        if (blockScraper && !isSuperAdmin) blockScraper.style.display = 'none';
        setupApiBuscaEvents();
        loadSettingsForm();
    } else if (tab === 'settings') {
        contentArea.innerHTML = getSettingsHTML();
        setupSettingsEvents();
        loadSettingsForm();
    } else if (tab === 'landing-page') {
        renderLandingPageTab(contentArea);
    }
}

// Empresa SaaS (super_admin): nome, preço avulso, whatsapp API, Chave CNPJ e SMTP de e-mail
function renderSaasConfigTab(contentArea) {
    contentArea.innerHTML = `
        <div class="max-w-4xl mx-auto space-y-8 pb-20">
            <div>
                <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Configurações do Sistema SaaS</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Configure as chaves globais da plataforma, gateway de WhatsApp, chave paga de CNPJ e servidores de e-mail SMTP.</p>
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

                <!-- PAINEL 2: INTEGRAÇÃO WHATSAPP (API ATENDO) -->
                <div class="bg-[#0F172A] p-8 rounded-[2rem] border border-slate-800 text-white space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/10 rounded-full blur-3xl"></div>
                    <h4 class="text-lg font-black text-white border-b border-slate-800 pb-3 flex items-center gap-2">💬 Disparador de WhatsApp (API Atendo)</h4>
                    <p class="text-xs text-slate-400">Insira a URL e o Token JWT da sua API do Atendo. O sistema usará essa conexão para notificar os clientes e sua equipe automaticamente.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">URL da API do WhatsApp</label>
                            <input id="saas-config-wa-url" type="url" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold text-white placeholder:text-slate-600" placeholder="https://off.atendo.log.br/v1/api/external/..." />
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">Token de Autenticação da API</label>
                            <input id="saas-config-wa-token" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold text-white placeholder:text-slate-600" placeholder="Insira o Token JWT da API Atendo" />
                        </div>
                    </div>
                </div>

                <!-- PAINEL 3: CHAVE DE CNPJ PREMIUM (CNPJ.WS) -->
                <div class="bg-[#0F172A] p-8 rounded-[2rem] border border-slate-800 text-white space-y-6 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-emerald-600/10 rounded-full blur-3xl"></div>
                    <h4 class="text-lg font-black text-white border-b border-slate-800 pb-3 flex items-center gap-2">🏢 Consulta de CNPJ Premium (Opcional)</h4>
                    <p class="text-xs text-slate-400">Insira a sua chave de API paga do CNPJ.ws. O sistema detectará a chave automaticamente, removendo os limites de consultas por IP e realizando as buscas diretamente pelo servidor.</p>
                    <div>
                        <label class="block text-[10px] font-black text-slate-300 uppercase mb-1">Chave de API do CNPJ.ws</label>
                        <input id="saas-config-cnpj-key" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-xl px-4 py-3 outline-none focus:border-emerald-500 font-semibold text-white placeholder:text-slate-600" placeholder="Deixe em branco para usar o método de consulta gratuita local" />
                    </div>
                </div>

                <!-- PAINEL 4: SERVIDOR DE E-MAIL (SMTP AUTENTICADO) -->
                <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm space-y-6">
                    <h4 class="text-lg font-black text-slate-900 dark:text-slate-100 border-b border-slate-100 dark:border-slate-700 pb-3 flex items-center gap-2">✉️ Servidor de E-mail (SMTP)</h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Configure as credenciais SMTP do seu domínio para disparar e-mails de confirmação e alertas. Deixe em branco para usar o e-mail padrão do cPanel.</p>
                    
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
            
            // Campos gerais
            var companyEl = document.getElementById('saas-config-company');
            var avulsoEl = document.getElementById('saas-config-avulso');
            if (companyEl) companyEl.value = d.saasCompanyName || '';
            if (avulsoEl) avulsoEl.value = (d.creditPriceAvulso != null && d.creditPriceAvulso > 0) ? String(d.creditPriceAvulso) : '0';
            
            // Campos WhatsApp
            var waUrlEl = document.getElementById('saas-config-wa-url');
            var waTokenEl = document.getElementById('saas-config-wa-token');
            if (waUrlEl) waUrlEl.value = d.whatsappApiUrl || '';
            if (waTokenEl) waTokenEl.value = d.whatsappApiToken || '';

            // Campo CNPJ Premium
            var cnpjKeyEl = document.getElementById('saas-config-cnpj-key');
            if (cnpjKeyEl) cnpjKeyEl.value = d.cnpjApiKey || '';

            // Campos SMTP
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
        
        if (avulso < 0) {
            var err = document.getElementById('saas-config-error');
            if (err) { err.textContent = 'O valor avulso não pode ser negativo.'; err.classList.remove('hidden'); }
            return;
        }

        var waUrlEl = document.getElementById('saas-config-wa-url');
        var waTokenEl = document.getElementById('saas-config-wa-token');
        var cnpjKeyEl = document.getElementById('saas-config-cnpj-key');
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
        if (btn) { btn.disabled = true; btn.textContent = 'Salvando Configurações...'; }
        
        fetch(API_BASE + 'platform-config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({
                saasCompanyName: companyEl ? companyEl.value.trim() : '',
                creditPriceAvulso: avulso,
                whatsappApiUrl: waUrlEl ? waUrlEl.value.trim() : '',
                whatsappApiToken: waTokenEl ? waTokenEl.value.trim() : '',
                cnpjApiKey: cnpjKeyEl ? cnpjKeyEl.value.trim() : '',
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
                if (successEl) { successEl.textContent = data.message || 'Configurações globais salvas com sucesso!'; successEl.classList.remove('hidden'); }
                loadSidebarCompanyName();
            } else {
                var err = document.getElementById('saas-config-error');
                if (err) { err.textContent = data.error || 'Erro ao salvar.'; err.classList.remove('hidden'); }
            }
        }).catch(function() {
            var err = document.getElementById('saas-config-error');
            if (err) { err.textContent = 'Erro de conexão com o servidor.'; err.classList.remove('hidden'); }
        }).finally(function() {
            if (btn) { btn.disabled = false; btn.textContent = 'Salvar Todas as Configurações'; }
        });
    };
}

// Planos (super_admin): solicitações de plano pendentes (Confirmar/Recusar) + listar planos, criar plano
function loadPlansTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto py-10">' +
        '<div id="plans-requests-section" class="mb-10 hidden"></div>' +
        '<div class="flex justify-between items-center mb-10 flex-wrap gap-4"><h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Planos e limite de tokens <span id="plans-total" class="text-slate-500 dark:text-slate-400 font-normal text-lg"></span></h3><div class="flex items-center gap-3"><button type="button" id="plans-refresh-btn" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2">Atualizar lista</button><button type="button" id="plans-create-btn" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-2xl text-xs flex items-center gap-2">Criar plano</button><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block" id="plans-spinner"></span></div></div>' +
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
            listEl.innerHTML = '<div class="py-24 text-center border-2 border-dashed border-slate-200 dark:border-slate-600 rounded-[2rem]"><p class="text-slate-400 font-bold uppercase text-[10px]">Nenhum plano cadastrado</p></div>';
            var totalEl = document.getElementById('plans-total');
            if (totalEl) totalEl.textContent = '0 plano(s)';
            return;
        }
        var html = '<div class="space-y-4">';
        items.forEach(function(p) {
            var tokenText = (p.tokenLimit === 0 || p.tokenLimit === '0') ? 'Ilimitado' : (p.tokenLimit + ' tokens/' + (p.period === 'yearly' ? 'ano' : 'mês'));
            var priceText = (p.priceMonthly != null && parseFloat(p.priceMonthly) > 0) ? (' · R$ ' + parseFloat(p.priceMonthly).toFixed(2).replace('.', ',') + '/mês') : '';
            var statusLabel = p.status === 'active' ? 'Ativo' : 'Inativo';
            var statusClass = p.status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400';
            var planId = String(p.id);
            var planName = (p.name || '').replace(/"/g, '&quot;');
            html += '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 flex items-center justify-between flex-wrap gap-4">';
            html += '<div class="flex items-center gap-6">';
            html += '<div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-lg font-black text-blue-600">' + ((p.tokenLimit === 0 || p.tokenLimit === '0') ? '∞' : p.tokenLimit) + '</div>';
            html += '<div><h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg">' + (p.name || '') + '</h4>';
            html += '<p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + (p.slug || '') + ' · ' + tokenText + priceText + ' · ' + (p.tenantsCount || 0) + ' empresa(s) · <span class="' + statusClass + ' px-2 py-0.5 rounded-full text-xs font-bold">' + statusLabel + '</span></p></div></div>';
            html += '<div class="flex items-center gap-2">';
            html += '<button type="button" class="btn-plan-edit bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 font-bold px-4 py-2 rounded-xl text-xs" data-id="' + planId + '" data-name="' + planName + '">Editar</button>';
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
        '<p class="text-[10px] text-slate-400 mt-1">0 = ilimitado. 1 token = 1 página de resultados (até 20 itens por página).</p></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Valor mensal (R$)</label>' +
        '<input type="number" id="plan-price" min="0" step="0.01" value="0" placeholder="0,00" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" /></div>' +
        '<p id="plans-create-error" class="text-red-600 text-sm font-medium hidden"></p>' +
        '<div class="flex gap-3 pt-4">' +
        '<button type="button" id="plans-create-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>' +
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
            overlay.innerHTML = '<div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
                '<h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-6">Editar plano</h4>' +
                '<form id="plans-edit-form" class="space-y-4">' +
                '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome</label>' +
                '<input type="text" id="plan-edit-name" required placeholder="Ex: Básico, Pro" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" value="' + (p.name || '').replace(/"/g, '&quot;') + '" /></div>' +
                '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Identificador (slug)</label>' +
                '<input type="text" id="plan-edit-slug" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" value="' + (p.slug || '').replace(/"/g, '&quot;') + '" /></div>' +
                '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Limite de tokens</label>' +
                '<input type="number" id="plan-edit-tokens" min="0" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" value="' + (p.tokenLimit != null ? p.tokenLimit : 100) + '" />' +
                '<p class="text-[10px] text-slate-400 mt-1">0 = ilimitado.</p></div>' +
                '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Valor mensal (R$)</label>' +
                '<input type="number" id="plan-edit-price" min="0" step="0.01" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" value="' + (p.priceMonthly != null ? p.priceMonthly : 0) + '" /></div>' +
                '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Período</label>' +
                '<select id="plan-edit-period" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500">' +
                '<option value="monthly"' + (p.period === 'yearly' ? '' : ' selected') + '>Mensal</option><option value="yearly"' + (p.period === 'yearly' ? ' selected' : '') + '>Anual</option></select></div>' +
                '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Status</label>' +
                '<select id="plan-edit-status" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold">' +
                '<option value="active"' + (p.status === 'inactive' ? '' : ' selected') + '>Ativo</option><option value="inactive"' + (p.status === 'inactive' ? ' selected' : '') + '>Inativo</option></select></div>' +
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
                                document.getElementById('toast-message').textContent = 'Plano updated with success.';
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

// Modal para adicionar nova empresa
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
                '<input type="text" id="add-company-name" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="Ex: Minha Empresa Ltda" required>' +
                '</div>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Identificador (slug)</label>' +
                '<input type="text" id="add-company-slug" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="Ex: minha-empresa (opcional - gera automático)">' +
                '<p class="text-[10px] text-slate-400 mt-1">Se vazio, será gerado automaticamente a partir do nome.</p>' +
                '</div>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Plano</label>' +
                '<select id="add-company-plan" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold">' + optionsHtml + '</select>' +
                '</div>' +
                '<hr class="border-slate-200 dark:border-slate-600 my-4">' +
                '<p class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-3">Dados do Administrador</p>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome do Administrador</label>' +
                '<input type="text" id="add-company-admin-name" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="Ex: João Silva">' +
                '</div>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">E-mail do Administrador</label>' +
                '<input type="email" id="add-company-admin-email" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="Ex: joao@empresa.com" required>' +
                '</div>' +
                '<div>' +
                '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Senha</label>' +
                '<input type="password" id="add-company-admin-password" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="Mínimo 6 caracteres" required minlength="6">' +
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
                    if (!adminNameInput.value.trim()) {
                        adminNameInput.value = name;
                    }
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
                if (!name) {
                    errorEl.textContent = 'Nome da empresa é obrigatório.';
                    errorEl.classList.remove('hidden');
                    return;
                }
                if (!adminEmail) {
                    errorEl.textContent = 'E-mail do administrador é obrigatório.';
                    errorEl.classList.remove('hidden');
                    return;
                }
                if (!adminPassword || adminPassword.length < 6) {
                    errorEl.textContent = 'Senha deve ter pelo menos 6 caracteres.';
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
                    body: JSON.stringify({ 
                        name: name, 
                        slug: slug, 
                        planId: parseInt(planId, 10), 
                        status: 'active',
                        adminName: adminName,
                        adminEmail: adminEmail,
                        adminPassword: adminPassword
                    })
                })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success) {
                            overlay.remove();
                            showToast('Empresa "' + name + '" criada com sucesso!');
                            loadCompaniesTab(document.getElementById('content-area'));
                        } else {
                            errorEl.textContent = res.error || 'Erro ao criar empresa.';
                            errorEl.classList.remove('hidden');
                        }
                    })
                    .catch(function() {
                        errorEl.textContent = 'Erro de conexão.';
                        errorEl.classList.remove('hidden');
                    })
                    .finally(function() {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Criar Empresa';
                    });
            };
            cancelBtn.onclick = function() { overlay.remove(); };
        })
        .catch(function() { alert('Erro ao carregar planos.'); });
}

// Empresas (super_admin): listar e ativar/desativar
function loadCompaniesTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-4xl mx-auto py-10"><div class="flex justify-between items-center mb-10 flex-wrap gap-4"><h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Empresas cadastradas <span id="companies-total" class="text-slate-500 dark:text-slate-400 font-normal text-lg"></span></h3><div class="flex items-center gap-3"><button type="button" id="btn-add-company" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-5 py-3 rounded-2xl text-xs flex items-center gap-2 shadow-lg shadow-blue-100 dark:shadow-blue-900/30"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>Nova Empresa</button><button type="button" id="companies-refresh-btn" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2">Atualizar lista</button><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block" id="companies-spinner"></span></div></div><div id="companies-list"></div></div>';
    var listEl = document.getElementById('companies-list');
    var spinnerEl = document.getElementById('companies-spinner');
    var refreshBtn = document.getElementById('companies-refresh-btn');
    var addBtn = document.getElementById('btn-add-company');
    if (refreshBtn) {
        refreshBtn.onclick = function() {
            loadCompaniesTab(document.getElementById('content-area'));
        };
    }
    if (addBtn) {
        addBtn.onclick = function() {
            showAddCompanyModal();
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
                html += '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 flex items-center justify-between flex-wrap gap-4">';
                html += '<div class="flex items-center gap-6">';
                html += '<div class="w-12 h-12 bg-slate-100 dark:bg-slate-700 rounded-xl flex items-center justify-center text-xl font-black text-slate-600 dark:text-slate-400">' + (t.name ? t.name.charAt(0).toUpperCase() : '') + '</div>';
                html += '<div><h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg">' + (t.name || '') + '</h4>';
                html += '<p class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">' + (t.slug || '') + (t.email ? ' · ' + (t.email) : '') + ' · Plano: ' + planInfo + tokensInfo + ' · ' + (t.usersCount || 0) + ' usuário(s) · <span class="' + statusClass + ' px-2 py-0.5 rounded-full text-xs font-bold">' + statusLabel + '</span></p></div></div>';
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
                    overlay.innerHTML = '<div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">' +
                        '<h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-2">Vincular empresa ao plano</h4>' +
                        '<p class="text-sm text-slate-500 dark:text-slate-400 mb-4">' + (tenantName ? 'Empresa: ' + tenantName : '') + '</p>' +
                        '<form id="companies-link-plan-form" class="space-y-4">' +
                        '<label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Plano</label>' +
                        '<select id="companies-link-plan-select" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500">' + optionsHtml + '</select>' +
                        '<p id="companies-link-plan-error" class="text-red-600 text-sm font-medium hidden"></p>' +
                        '<div class="flex gap-3 pt-4">' +
                        '<button type="button" id="companies-link-plan-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>' +
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

            listEl.querySelectorAll('.btn-access-tenant').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var tenantId = btn.dataset.id;
                    var name = btn.dataset.name;
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
                        '<p class="text-sm text-slate-500 dark:text-slate-400 mb-4">Altere o nome e o e-mail do administrador da empresa.</p>' +
                        '<form id="companies-edit-tenant-form" class="space-y-4">' +
                        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome</label>' +
                        '<input type="text" id="companies-edit-tenant-name" value="' + tenantName.replace(/"/g, '&quot;') + '" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" placeholder="Nome da empresa"></div>' +
                        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">E-mail</label>' +
                        '<input type="email" id="companies-edit-tenant-email" value="' + tenantEmail.replace(/"/g, '&quot;') + '" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium placeholder-slate-400 dark:placeholder-slate-500" placeholder="admin@empresa.com"></div>' +
                        '<p id="companies-edit-tenant-error" class="text-red-600 text-sm font-medium hidden"></p>' +
                        '<div class="flex gap-3 pt-4">' +
                        '<button type="button" id="companies-edit-tenant-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>' +
                        '<button type="submit" id="companies-edit-tenant-submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Salvar</button>' +
                        '</div></form></div>';
                    overlay.onclick = function(ev) { if (ev.target === overlay) overlay.remove(); };
                    document.body.appendChild(overlay);
                    var form = document.getElementById('companies-edit-tenant-form');
                    var cancelBtn = document.getElementById('companies-edit-tenant-cancel');
                    var errorEl = document.getElementById('companies-edit-tenant-error');
                    var submitBtn = document.getElementById('companies-edit-tenant-submit');
                    form.onsubmit = function(e) {
                        e.preventDefault();
                        var name = document.getElementById('companies-edit-tenant-name').value.trim();
                        var email = document.getElementById('companies-edit-tenant-email').value.trim().toLowerCase();
                        if (!name) { errorEl.textContent = 'Nome é obrigatório.'; errorEl.classList.remove('hidden'); return; }
                        errorEl.classList.add('hidden');
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Salvando...';
                        var body = { id: tenantId, name: name };
                        if (email) body.email = email;
                        fetch(API_BASE + 'tenants.php', {
                            method: 'PUT',
                            headers: { 'Content-Type': 'application/json' },
                            credentials: 'same-origin',
                            body: JSON.stringify(body)
                        })
                            .then(function(r) { return r.json(); })
                            .then(function(res) {
                                if (res.success) {
                                    overlay.remove();
                                    loadCompaniesTab(document.getElementById('content-area'));
                                } else {
                                    errorEl.textContent = res.error || 'Erro ao salvar.';
                                    errorEl.classList.remove('hidden');
                                }
                            })
                            .catch(function() {
                                errorEl.textContent = 'Erro de conexão.';
                                errorEl.classList.remove('hidden');
                            })
                            .finally(function() {
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Salvar';
                            });
                    };
                    cancelBtn.onclick = function() { overlay.remove(); };
                });
            });

            listEl.querySelectorAll('.btn-delete-tenant').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var id = btn.dataset.id;
                    var name = btn.dataset.name;
                    if (!confirm('Excluir a empresa "' + name + '"? Os usuários serão vinculados à empresa padrão.')) return;
                    btn.disabled = true;
                    fetch(API_BASE + 'tenants.php?id=' + encodeURIComponent(id), { method: 'DELETE', credentials: 'same-origin' })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            if (res.success) {
                                setActiveTab('companies');
                            } else {
                                alert(res.error || 'Erro ao excluir.');
                            }
                        })
                        .catch(function() { alert('Erro de conexão.'); })
                        .finally(function() { btn.disabled = false; });
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

// Prospecção HTML
function getProspectingHTML() {
    const scraperConfigured = AppState.config && (
        (AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin' && AppState.config.scraperApiKey && String(AppState.config.scraperApiKey).trim() !== '') ||
        AppState.config.scraperApiKeyConfigured
    );
    const openrouterConfigured = AppState.config && (
        (AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin' && AppState.config.openrouterApiKey && String(AppState.config.openrouterApiKey).trim() !== '') ||
        AppState.config.openrouterApiKeyConfigured
    );
    
    const tokenUsage = AppState.tokenUsage;
    const maxTokens = (tokenUsage && tokenUsage.limit > 0) ? Math.max(1, tokenUsage.limit - tokenUsage.used) : 1000;
    const availableTokens = (tokenUsage && tokenUsage.limit > 0) ? Math.max(0, tokenUsage.limit - tokenUsage.used) : 'Ilimitado';
    const defaultLimit = (tokenUsage && tokenUsage.limit > 0) ? Math.min(20, maxTokens) : 20;
    
    return `
        <div class="max-w-[1400px] mx-auto">
            <div id="search-token-limit-banner" class="hidden mb-6 p-4 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium flex items-center gap-2">
                <span class="text-lg">⚠️</span>
                <span>Você atingiu o limite de tokens do seu plano para este período. Cada página de resultados (até 20 itens) consome 1 token. Solicite mais créditos em <strong>Solicitar Créditos</strong> no menu ou aguarde o próximo período.</span>
            </div>
            
            <!-- Elementos ocultos em segundo plano para manter a integridade do JavaScript -->
            <div class="hidden">
                <button id="toggle-gps" class="hidden">USAR MEU GPS</button>
                <input type="hidden" id="search-method" value="scraper">
                <input type="number" id="search-max-places" value="50"> <!-- Oculto e fixado em 50 para economia e segurança -->
            </div>

            <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] shadow-sm border border-slate-200 dark:border-slate-600 mb-10">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-4">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">O que busca?</label>
                        <input id="search-query" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm transition-all placeholder-slate-400 dark:placeholder-slate-500" placeholder="Ex: Petshop, Clínica, Padaria...">
                    </div>
                    <div class="md:col-span-4">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Onde?</label>
                        <input id="search-location" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm transition-all placeholder-slate-400 dark:placeholder-slate-500" placeholder="Cidade ou Região">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Tag CRM</label>
                        <input id="search-tag" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm placeholder-slate-400 dark:placeholder-slate-500" placeholder="Ex: leads_novos">
                    </div>
                    <div class="md:col-span-2 flex items-end">
                        <button id="btn-search" class="w-full py-3.5 bg-slate-900 hover:bg-blue-600 text-white font-black rounded-xl transition-all shadow-lg shadow-slate-900/30 dark:shadow-lg dark:shadow-black/20 disabled:opacity-50 flex items-center justify-center uppercase tracking-wider text-xs">
                            Buscar Tudo
                        </button>
                    </div>
                </div>
            </div>
            
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
            
            <!-- Tela de espera animada (Efeito Radar e Flutuação) -->
            <div id="empty-state" class="py-32 text-center">
                <!-- Círculo do Radar com Brilho e Pulso -->
                <div class="w-28 h-28 bg-blue-500/10 dark:bg-blue-500/5 rounded-full flex items-center justify-center mx-auto mb-6 shadow-xl relative animate-pulse">
                    <!-- Anel do Sonar se expandindo (Efeito Ping) -->
                    <div class="absolute inset-0 rounded-full border border-blue-500/30 animate-ping" style="animation-duration: 2s;"></div>
                    <!-- Ícone do Mapa Flutuando Suavemente (Efeito Bounce Lento) -->
                    <span class="text-5xl animate-bounce" style="animation-duration: 3s;">🗺️</span>
                </div>
                <!-- Texto Principal Pulsante -->
                <h4 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-[0.25em] mb-2 animate-pulse">Pronto para Prospectar?</h4>
                <!-- Texto de Instrução -->
                <p class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Digite uma atividade e cidade acima para iniciar a busca</p>
            </div>
        </div>
    `;
}
// Setup eventos de prospecção
function setupProspectingEvents() {
    const btnSearch = document.getElementById('btn-search');
    if (btnSearch) {
        btnSearch.addEventListener('click', function(e) {
            e.preventDefault();
            performSearch();
        });
    }
    
    // Permitir buscar ao pressionar Enter nos campos de entrada
    const queryInput = document.getElementById('search-query');
    const locationInput = document.getElementById('search-location');
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

    // Vincular clique do botão de Exportar tudo para Excel
    const btnExportExcel = document.getElementById('btn-export-excel');
    if (btnExportExcel) {
        btnExportExcel.addEventListener('click', function(e) {
            e.preventDefault();
            exportCurrentSearchToExcel();
        });
    }

    // Vincular clique do botão de Exportar tudo para Webhook
    const btnExportWebhook = document.getElementById('btn-export-webhook');
    if (btnExportWebhook) {
        btnExportWebhook.addEventListener('click', function(e) {
            e.preventDefault();
            exportCurrentSearchToWebhook();
        });
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
            
            if (data.data.duplicatesRemoved > 0) {
                showToast(data.data.duplicatesRemoved + ' resultado(s) duplicado(s) foram removidos desta pesquisa.', 'warning');
            }
            
            if (data.data.isNewSearch) {
                var newItem = { id: String(Date.now()), query: query, location: locationText, tag: tag, timestamp: new Date().toISOString(), resultsCount: AppState.leads.length, leads: AppState.leads.slice() };
                AppState.history = [newItem].concat(AppState.history.filter(function(h) { return h.query !== query || h.location !== locationText; })).slice(0, 50);
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
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab; 
            setActiveTab(tab);
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
            <button id="btn-load-more" class="group relative px-8 py-4 bg-white border-2 border-slate-200 dark:border-slate-600 rounded-full shadow-lg hover:shadow-xl hover:border-blue-500 transition-all active:scale-95">
                <div class="flex items-center gap-3">
                    <div class="bg-slate-100 dark:bg-slate-700 rounded-full p-2 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/50 transition-colors">
                        <svg class="w-4 h-4 text-slate-600 dark:text-slate-400 group-hover:text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" /></svg>
                    </div>
                    <span class="text-xs font-black text-slate-700 dark:text-slate-300 uppercase tracking-widest group-hover:text-blue-700">
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
        <div class="relative mt-4 pt-10 pb-6 px-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-[1.5rem] hover:shadow-2xl hover:shadow-blue-900/5 transition-all duration-300 flex flex-col justify-between group">
            <div class="absolute -top-3 left-6 bg-amber-500 text-white text-[9px] font-black py-1.5 px-3 rounded-lg uppercase tracking-wider shadow-lg z-10">Bloqueado</div>
            <div>
                <h3 class="font-extrabold text-slate-900 dark:text-slate-100 text-sm uppercase leading-snug mb-4 min-h-[2.5rem]">${lead.name || ''}</h3>
                <div class="space-y-4 mb-6">
                    ${lead.website ? `
                    <div class="flex items-center gap-3 bg-slate-50 dark:bg-slate-700/50 p-2 rounded-lg -mx-2 border border-slate-200 dark:border-slate-600">
                        <span class="text-slate-500 dark:text-slate-400 text-xs">🌐</span>
                        <a href="${lead.website}" target="_blank" rel="noopener noreferrer" class="text-blue-600 dark:text-blue-400 font-bold text-xs hover:underline truncate">Site</a>
                    </div>
                    ` : ''}
                    <div class="flex items-center gap-3 bg-slate-50 dark:bg-slate-700/50 p-3 rounded-lg -mx-2 border border-slate-200 dark:border-slate-600">
                        <span class="text-slate-400 text-xs">🔒</span>
                        <span class="text-slate-500 dark:text-slate-400 font-bold text-xs">Telefone, email e endereço bloqueados. Desbloqueie para visualizar.</span>
                    </div>
                    <button type="button" class="btn-unlock w-full py-3 bg-amber-500 hover:bg-amber-600 text-white rounded-xl font-black text-[10px] uppercase tracking-wide transition-all disabled:opacity-50 flex justify-center items-center gap-2" data-lead-id="${lead.id}" title="Desbloquear">Desbloquear para ver dados</button>
                </div>
            </div>
        </div>
        `;
    }
    return `
        <div class="relative mt-4 pt-10 pb-6 px-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-[1.5rem] hover:shadow-2xl hover:shadow-blue-900/5 transition-all duration-300 flex flex-col justify-between group">
            <div class="absolute -top-3 left-6 bg-blue-600 text-white text-[9px] font-black py-1.5 px-3 rounded-lg uppercase tracking-wider shadow-lg shadow-blue-200 z-10">
                ${lead.partners ? 'Dados Ricos' : 'Lead'}
            </div>
            <div>
                <h3 class="font-extrabold text-slate-900 dark:text-slate-100 text-sm uppercase leading-snug mb-4 min-h-[2.5rem]">${lead.name || ''}</h3>
                <div class="space-y-3 mb-6">
                    <div class="flex items-center gap-3 bg-emerald-50/50 dark:bg-emerald-950/20 p-2 rounded-lg -mx-2">
                        <span class="text-emerald-500 dark:text-emerald-400 text-xs">📞</span>
                        <span class="text-emerald-700 dark:text-emerald-300 font-bold text-xs">${lead.phone || 'Sem telefone'}</span>
                    </div>${lead.partners ? `
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
async function loadHistory() {
    const contentArea = document.getElementById('content-area');
    contentArea.innerHTML = '<div class="max-w-5xl mx-auto"><div class="text-center py-10"><div class="w-8 h-8 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto"></div></div></div>';
    try {
        const res = await fetch(API_BASE + 'history.php');
        const text = await res.text();
        if (!text || text.trim() === '') {
            throw new Error('Resposta vazia do servidor');
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
            var q = (item.query || '').toLowerCase();
            var loc = (item.location || '').toLowerCase();
            var tag = (item.tag || '').toLowerCase();
            if (q.indexOf(search) === -1 && loc.indexOf(search) === -1 && tag.indexOf(search) === -1) return false;
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
        return '<div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600 flex items-center justify-between group hover:border-blue-300 transition-all hover:shadow-md">' +
            '<div class="flex items-center gap-6">' +
            '<div class="w-12 h-12 bg-slate-50 dark:bg-slate-800/50 rounded-xl flex items-center justify-center text-xl">🔎</div>' +
            '<div>' +
            '<h4 class="font-extrabold text-slate-900 dark:text-slate-100 text-lg capitalize">' + (item.query || '') + '</h4>' +
            '<p class="text-[11px] font-bold text-slate-400 mt-1 uppercase tracking-wider">📍 ' + (item.location || 'Local Automático') + ' <span class="ml-2">🏷 ' + tagLabel + '</span> <span class="ml-2 text-slate-500">' + dateTime + '</span> <span class="ml-2 text-blue-500">(' + count + ' leads)</span></p>' +
            '</div></div>' +
            '<div class="flex items-center gap-2">' +
            '<button class="btn-continue-search bg-blue-600 text-white font-bold px-4 py-3 rounded-2xl text-xs hover:bg-blue-700 transition-colors" data-history-id="' + item.id + '" data-query="' + encodeURIComponent(item.query || '') + '" data-location="' + encodeURIComponent(item.location || '') + '" data-tag="' + encodeURIComponent(item.tag || '') + '">🔄 Continuar Pesquisa</button>' +
            '<button class="btn-use-history bg-slate-900 text-white font-bold px-6 py-3 rounded-2xl text-xs hover:bg-blue-600 transition-colors" data-history-id="' + item.id + '">Ver Novamente</button>' +
            '</div>' +
            '</div>';
    }).join('');
    
    listEl.querySelectorAll('.btn-use-history').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var historyId = btn.dataset.historyId;
            var item = AppState.history.find(function(h) { return h.id == historyId; });
            if (item) {
                AppState.searchId = String(item.id);
                AppState.leads = (item.leads || []).map(function(l) {
                    return { 
                        id: l.id, 
                        name: l.name || '', 
                        locked: l.locked, 
                        dbId: l.dbId,
                        phone: l.phone || '',
                        email: l.email || '',
                        address: l.address || '',
                        website: l.website || '',
                        mapsUri: l.mapsUri || '',
                        cnpj: l.cnpj || '',
                        partners: l.partners || '',
                        tag: l.tag || '',
                        latitude: l.latitude,
                        longitude: l.longitude
                    };
                });
                AppState.currentSearch = { query: item.query || '', location: item.location || '', tag: item.tag || '' };
                setActiveTab('search');
                setTimeout(function() { displayLeads(); }, 100);
            }
        });
    });
    listEl.querySelectorAll('.btn-continue-search').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var historyId = btn.dataset.historyId;
            var query = decodeURIComponent(btn.dataset.query || '');
            var location = decodeURIComponent(btn.dataset.location || '');
            var tag = decodeURIComponent(btn.dataset.tag || '');
            var item = AppState.history.find(function(h) { return h.id == historyId; });
            AppState.currentSearch = { query: query, location: location, tag: tag };
            setActiveTab('search');
            setTimeout(function() {
                var queryInput = document.getElementById('search-query');
                var locationInput = document.getElementById('search-location');
                var tagInput = document.getElementById('search-tag');
                if (queryInput) queryInput.value = query;
                if (locationInput) locationInput.value = location;
                if (tagInput) tagInput.value = tag;
                if (item && item.leads) {
                    AppState.searchId = String(item.id);
                    AppState.leads = item.leads.map(function(l) {
                        return { 
                            id: l.id, 
                            name: l.name || '', 
                            locked: l.locked, 
                            dbId: l.dbId,
                            phone: l.phone || '',
                            email: l.email || '',
                            address: l.address || '',
                            website: l.website || '',
                            mapsUri: l.mapsUri || '',
                            cnpj: l.cnpj || '',
                            partners: l.partners || '',
                            tag: l.tag || '',
                            latitude: l.latitude,
                            longitude: l.longitude
                        };
                    });
                    displayLeads();
                }
            }, 200);
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
    if (searchInput) searchInput.addEventListener('change', refreshFilteredList);
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
            } catch (e) {
                alert('Erro ao limpar histórico: ' + e.message);
            }
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
    } catch (e) {
        console.error('Erro ao carregar pastas:', e);
    }
}

function updateFolderSelect() {
    const folderSelect = document.getElementById('search-folder');
    if (!folderSelect) return;
    const folderOptions = AppState.folders.map(f => `<option value="${f.id}">${f.name}</option>`).join('');
    folderSelect.innerHTML = '<option value="">Sem pasta</option>' + folderOptions;
    if (AppState.currentSearch && AppState.currentSearch.folderId) {
        folderSelect.value = AppState.currentSearch.folderId;
    }
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
    } catch (e) {
        console.error('Erro ao criar pasta:', e);
    }
    return null;
}

// API de Busca (apenas status + chave para super_admin)
function getApiBuscaHTML() {
    return `
        <div class="max-w-3xl mx-auto space-y-8 pb-20">
            <div class="bg-white dark:bg-slate-800 p-10 rounded-[2.5rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-8 tracking-tight">API de Busca (Google Maps)</h3>
                <div class="space-y-8">
                    <div class="bg-[#0F172A] p-8 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/10 rounded-full blur-3xl"></div>
                        <h4 id="api-section-title" class="font-black text-xl mb-4 flex items-center gap-2">Status da API de Busca</h4>
                        <div id="api-status" class="p-5 bg-amber-500/20 border border-amber-500/50 rounded-2xl text-amber-400 text-center font-bold">Nenhuma API de busca configurada</div>
                        <p id="api-status-subtitle" class="text-[10px] text-slate-400 mt-4 text-center italic">Configure a chave abaixo para buscar leads no Google Maps.</p>
                    </div>
                    <div id="block-scraper-api-key-admin" class="bg-[#0F172A] p-6 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-purple-600/10 rounded-full blur-3xl"></div>
                        <h4 class="font-black text-lg mb-3 flex items-center gap-2">Chave da API de Busca (Scraper)</h4>
                        <p class="text-xs text-slate-400 mb-4">Chave de API para busca direta no Google Maps via Apify. Apenas o Super Admin pode alterar; todas as empresas utilizam esta chave.</p>
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-2 ml-1">Chave da API de Busca (Apify)</label>
                            <input id="setting-scraper-api" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl px-6 py-4 outline-none focus:border-purple-500 font-bold text-white placeholder:text-slate-500 dark:text-slate-400" placeholder="Insira a chave da API de busca">
                        </div>
                    </div>
                    <div id="block-openrouter-api-key-admin" class="bg-[#0F172A] p-6 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-cyan-600/10 rounded-full blur-3xl"></div>
                        <h4 class="font-black text-lg mb-3 flex items-center gap-2">API de IA (OpenRouter)</h4>
                        <p class="text-xs text-slate-400 mb-4">Chave de API do OpenRouter.ai para busca alternativa por Inteligência Artificial. O super admin escolhe o modelo.</p>
                        <div class="space-y-4">
                            <div>
                                <label class="block text-[10px] font-black text-slate-300 uppercase mb-2 ml-1">Chave da API OpenRouter</label>
                                <input id="setting-openrouter-api" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl px-6 py-4 outline-none focus:border-cyan-500 font-bold text-white placeholder:text-slate-500 dark:text-slate-400" placeholder="sk-or-v1-...">
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-300 uppercase mb-2 ml-1">Modelo de IA (OpenRouter)</label>
                                <input id="setting-ia-model" type="text" class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl px-6 py-4 outline-none focus:border-cyan-500 font-bold text-white placeholder:text-slate-500 dark:text-slate-400" placeholder="google/gemini-2.0-flash-001">
                                <p class="text-[10px] text-slate-500 mt-1 ml-1">Ex: google/gemini-2.0-flash-001, openai/gpt-4o, anthropic/claude-3.5-sonnet</p>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-300 uppercase mb-2 ml-1">Modelo de IA Fallback (opcional)</label>
                                <input id="setting-ia-model-fallback" type="text" class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl px-6 py-4 outline-none focus:border-cyan-500 font-bold text-white placeholder:text-slate-500 dark:text-slate-400" placeholder="qwen/qwen3-coder:free">
                                <p class="text-[10px] text-slate-500 mt-1 ml-1">Usado automaticamente se o modelo principal falhar</p>
                            </div>
                        </div>
                    </div>
                    <button id="btn-save-settings" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-[1.5rem] shadow-xl shadow-blue-100 dark:shadow-blue-900/30 transition-all active:scale-[0.98]">Salvar Alterações</button>
                </div>
            </div>
        </div>
    `;
}

function setupApiBuscaEvents() {
    document.getElementById('btn-save-settings').addEventListener('click', saveSettings);
    var scraperApiInput = document.getElementById('setting-scraper-api');
    if (scraperApiInput) scraperApiInput.addEventListener('input', function() { updateApiStatusDisplay(scraperApiInput.value); });
}

// Configurações (Integração CRM: webhook, token, opções — sem bloco API de Busca)
function getSettingsHTML() {
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
    var apikeyFieldHTML = isSuperAdmin ? `
        <div>
            <label class="block text-[10px] font-black text-slate-400 dark:text-slate-300 uppercase mb-2 ml-1">Authentication Header: apikey</label>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-1 ml-1">Nome do header fixo: <code class="bg-slate-100 dark:bg-slate-700 px-1 rounded">apikey</code>. Valor (preenchido abaixo) é salvo criptografado no banco.</p>
            <input id="setting-token" type="password" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="Valor do header apikey (deixe em branco para manter o atual)">
        </div>
    ` : '';
    return `
        <div class="max-w-3xl mx-auto space-y-8 pb-20">
            <div class="bg-white dark:bg-slate-800 p-10 rounded-[2.5rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100 mb-8 tracking-tight">Configurações de Conexão</h3>
                <div class="space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 dark:text-slate-300 uppercase mb-2 ml-1">Nome da Instância</label>
                            <input id="setting-tenant" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="Nome da empresa SaaS">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[10px] font-black text-slate-400 dark:text-slate-300 uppercase mb-2 ml-1">URL do Webhook</label>
                        <input id="setting-url" type="url" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-2xl px-6 py-4 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" placeholder="https://seu-webhook.com/...">
                    </div>
                    ${apikeyFieldHTML}
                    <div class="mt-6">
                        <button id="btn-save-settings" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black py-5 rounded-[1.5rem] shadow-xl shadow-blue-100 dark:shadow-blue-900/30 transition-all active:scale-[0.98]">Salvar Alterações</button>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function setupSettingsEvents() {
    document.getElementById('btn-save-settings').addEventListener('click', saveSettings);
    const scraperApiInput = document.getElementById('setting-scraper-api');
    if (scraperApiInput) {
        scraperApiInput.addEventListener('input', () => updateApiStatusDisplay(scraperApiInput.value));
    }
}

async function loadSettingsForm() {
    try {
        const res = await fetch(API_BASE + 'settings.php');
        const data = await res.json();
        if (data.success) {
            AppState.config = data.data;
            var urlEl = document.getElementById('setting-url');
            if (urlEl) urlEl.value = AppState.config.baseUrl || '';
            var tokenEl = document.getElementById('setting-token');
            if (tokenEl) tokenEl.value = AppState.config.token || '';
            var tenantEl = document.getElementById('setting-tenant');
            if (tenantEl) tenantEl.value = AppState.config.tenantName || 'Nome da empresa SaaS';
            var scraperApiInput = document.getElementById('setting-scraper-api');
            if (scraperApiInput) scraperApiInput.value = AppState.config.scraperApiKey || '';
            var openrouterApiInput = document.getElementById('setting-openrouter-api');
            if (openrouterApiInput) openrouterApiInput.value = AppState.config.openrouterApiKey || '';
            var iaModelInput = document.getElementById('setting-ia-model');
            if (iaModelInput) iaModelInput.value = AppState.config.iaModel || 'google/gemini-2.0-flash-001';
            var iaModelFallbackInput = document.getElementById('setting-ia-model-fallback');
            if (iaModelFallbackInput) iaModelFallbackInput.value = AppState.config.iaModelFallback || '';
            updateApiStatusDisplay();
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
    if (titleEl) titleEl.textContent = 'Status das APIs de Busca';
    var configured = isSuperAdmin
        ? (overrideKey !== undefined ? String(overrideKey || '').trim() : (AppState.config && AppState.config.scraperApiKey) ? String(AppState.config.scraperApiKey).trim() : '')
        : (AppState.config && AppState.config.scraperApiKeyConfigured);
    var geminiConfigured = isSuperAdmin
        ? (AppState.config && AppState.config.openrouterApiKey) ? String(AppState.config.openrouterApiKey).trim() : ''
        : (AppState.config && AppState.config.openrouterApiKeyConfigured);
    if (configured || geminiConfigured) {
        var statusText = '';
        if (configured && geminiConfigured) {
            statusText = '✓ Scraper (Apify) + IA (OpenRouter) conectado';
        } else if (configured) {
            statusText = '✓ Scraper (Apify) conectado';
        } else {
            statusText = '✓ IA (OpenRouter) conectado';
        }
        statusEl.textContent = statusText;
        statusEl.className = 'p-5 bg-emerald-500/20 border border-emerald-500/50 rounded-2xl text-emerald-400 text-center font-bold';
        var usageHtml = '';
        if (AppState.config && AppState.config.apifyUsage && isSuperAdmin) {
            var au = AppState.config.apifyUsage;
            var used = parseFloat(au.usedUsd);
            var limit = parseFloat(au.limitUsd);
            var pct = Math.min(100, Math.max(0, (used / limit) * 100)).toFixed(1);
            usageHtml = `
                <div class="mt-6 p-5 bg-slate-900 border border-slate-800 rounded-2xl text-white">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider">Consumo da API Apify</span>
                        <span class="text-xs font-bold text-blue-400">Plano: ${au.planName} (${au.username})</span>
                    </div>
                    <div class="flex justify-between items-end mb-1">
                        <span class="text-xs text-slate-400">Uso de limites da plataforma: ${pct}%</span>
                        <span class="text-xs font-bold text-slate-100">$${used.toFixed(2)} / $${limit.toFixed(2)} USD</span>
                    </div>
                    <div class="w-full bg-slate-850 rounded-full h-2">
                        <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" style="width: ${pct}%"></div>
                    </div>
                </div>
            `;
        }
        subtitleEl.innerHTML = 'Método padrão: Scraper. Na busca, você pode selecionar "IA (OpenRouter)".' + usageHtml;
    } else {
        statusEl.textContent = 'Nenhuma API configurada';
        statusEl.className = 'p-5 bg-amber-500/20 border border-amber-500/50 rounded-2xl text-amber-400 text-center font-bold';
        subtitleEl.textContent = isSuperAdmin ? 'Configure as chaves abaixo para ativar os métodos de busca.' : 'O administrador da plataforma deve configurar as chaves em API de Busca.';
    }
}

async function saveSettings() {
    const btn = document.getElementById('btn-save-settings');
    btn.disabled = true;
    btn.textContent = 'Salvando...';
    var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
    var urlEl = document.getElementById('setting-url');
    var tokenEl = document.getElementById('setting-token');
    var tenantEl = document.getElementById('setting-tenant');
    var payload = {
        baseUrl: urlEl ? urlEl.value : (AppState.config.baseUrl || ''),
        token: tokenEl ? tokenEl.value : (AppState.config.token || ''),
        tenantName: tenantEl ? tenantEl.value : (AppState.config.tenantName || 'Nome da empresa SaaS')
    };
    if (isSuperAdmin) {
        var scraperEl = document.getElementById('setting-scraper-api');
        var openrouterEl = document.getElementById('setting-openrouter-api');
        var iaModelEl = document.getElementById('setting-ia-model');
        var iaModelFallbackEl = document.getElementById('setting-ia-model-fallback');
        payload.scraperApiKey = scraperEl ? scraperEl.value : (AppState.config.scraperApiKey || '');
        payload.openrouterApiKey = openrouterEl ? openrouterEl.value : (AppState.config.openrouterApiKey || '');
        payload.iaModel = iaModelEl ? iaModelEl.value : (AppState.config.iaModel || 'google/gemini-2.0-flash-001');
        payload.iaModelFallback = iaModelFallbackEl ? iaModelFallbackEl.value : (AppState.config.iaModelFallback || '');
    }
    try {
        const res = await fetch(API_BASE + 'settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            if (AppState.config) AppState.config.tenantName = payload.tenantName;
            var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
            if (!isSuperAdmin) {
                var subtitleEl = document.getElementById('header-dashboard-subtitle');
                if (subtitleEl) {
                    var instanceName = (payload.tenantName && String(payload.tenantName).trim()) ? String(payload.tenantName).trim() : (AppState.tenant && AppState.tenant.name) ? AppState.tenant.name : 'Empresa';
                    subtitleEl.textContent = 'Dashboard ' + instanceName.toUpperCase();
                }
            }
            updateDocumentTitle();
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
        const res = await fetch(API_BASE + 'settings.php', { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            AppState.config = data.data;
            updateSearchApiUI();
            var isSuperAdmin = AppState.user && String(AppState.user.profile).toLowerCase() === 'super_admin';
            var instanceName = (AppState.config.tenantName && String(AppState.config.tenantName).trim()) ? String(AppState.config.tenantName).trim() : (AppState.tenant && AppState.tenant.name) ? AppState.tenant.name : 'Nome da empresa SaaS';
            var instanceEl = document.getElementById('sidebar-instance-name');
            if (instanceEl) instanceEl.textContent = instanceName;
            if (!isSuperAdmin) {
                var subtitleEl = document.getElementById('header-dashboard-subtitle');
                if (subtitleEl) subtitleEl.textContent = 'Dashboard ' + instanceName.toUpperCase();
            }
            updateDocumentTitle();
        }
    } catch (e) {
        console.error('Erro ao carregar config:', e);
        updateSearchApiUI();
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

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const toastMsg = document.getElementById('toast-message');
    toastMsg.textContent = message;
    toast.classList.remove('hidden');
    if (type === 'warning') {
        toast.classList.remove('bg-green-600');
        toast.classList.add('bg-amber-500');
    } else if (type === 'error') {
        toast.classList.remove('bg-green-600');
        toast.classList.add('bg-red-600');
    } else {
        toast.classList.remove('bg-amber-500', 'bg-red-600');
        toast.classList.add('bg-green-600');
    }
    setTimeout(() => {
        toast.classList.add('hidden');
    }, 4000);
}

// Landing Page Editor Tab (SuperAdmin)
function renderLandingPageTab(contentArea) {
    contentArea.innerHTML = '<div class="max-w-5xl mx-auto">' +
        '<div class="flex justify-between items-center mb-8 flex-wrap gap-4">' +
        '<div><h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Editor da Landing Page</h3>' +
        '<p class="text-sm text-slate-500 dark:text-slate-400">Edite o conteúdo que aparece na página pública de apresentação.</p></div>' +
        '<div class="flex items-center gap-3">' +
        '<button type="button" id="landing-refresh-btn" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 font-bold px-4 py-3 rounded-2xl text-xs flex items-center gap-2">Atualizar</button>' +
        '<a href="landing.php" target="_blank" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-2xl text-xs flex items-center gap-2">Ver Landing Page <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg></a>' +
        '</div></div>' +
        '<div id="landing-content"></div></div>';
    loadLandingPageSections();
    document.getElementById('landing-refresh-btn').addEventListener('click', loadLandingPageSections);
}

function loadLandingPageSections() {
    const contentEl = document.getElementById('landing-content');
    contentEl.innerHTML = '<div class="text-center py-12"><span class="w-8 h-8 border-2 border-blue-600 border-t-transparent rounded-full animate-spin inline-block"></span></div>';
    fetch(API_BASE + 'landing-page.php', { method: 'GET', credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                contentEl.innerHTML = '<div class="py-8 p-5 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium">Erro ao carregar seções. Execute a migração do banco.</div>';
                return;
            }
            var sections = data.data || [];
            if (sections.length === 0) {
                contentEl.innerHTML = '<div class="py-8 p-5 bg-amber-50 border border-amber-200 rounded-2xl text-amber-800 text-sm font-medium">Nenhuma seção encontrada. Execute a migração do banco.</div>';
                return;
            }
            var html = '';
            sections.forEach(function(section, index) {
                var title = section.sectionTitle || section.sectionKey;
                html += '<div class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm mb-6 overflow-hidden">' +
                    '<div class="px-6 py-4 bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-600 flex justify-between items-center">' +
                    '<div><h4 class="font-black text-slate-900 dark:text-slate-100">' + title + '</h4>' +
                    '<p class="text-xs text-slate-500 dark:text-slate-400">Key: ' + section.sectionKey + '</p></div>' +
                    '<div class="flex items-center gap-2">' +
                    '<label class="flex items-center gap-2 text-xs font-bold text-slate-500 dark:text-slate-400">' +
                    '<input type="checkbox" class="w-4 h-4 rounded border-slate-300 dark:border-slate-600" ' + (section.isActive ? 'checked' : '') + ' data-section="' + section.sectionKey + '" data-field="isActive" onchange="toggleLandingSection(this)"> Ativo' +
                    '</label>' +
                    '<button type="button" onclick="editLandingSection(\'' + section.sectionKey + '\')" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold">Editar</button>' +
                    '</div></div>' +
                    '<div class="p-6">' +
                    (section.sectionTitle ? '<p class="text-sm font-bold text-slate-700 dark:text-slate-300 mb-1">Título: ' + section.sectionTitle + '</p>' : '') +
                    (section.sectionSubtitle ? '<p class="text-sm text-slate-600 dark:text-slate-400 mb-2">' + section.sectionSubtitle + '</p>' : '') +
                    (section.sectionContent ? '<div class="text-sm text-slate-600 dark:text-slate-400 bg-slate-50 dark:bg-slate-700/50 p-3 rounded-xl">' + section.sectionContent.substring(0, 200) + '...</div>' : '') +
                    '</div></div>';
            });
            contentEl.innerHTML = html;
        })
        .catch(function(err) {
            contentEl.innerHTML = '<div class="py-8 p-5 bg-red-50 border border-red-200 rounded-2xl text-red-800 text-sm font-medium">Erro: ' + err.message + '</div>';
        });
}

function editLandingSection(sectionKey) {
    fetch(API_BASE + 'landing-page.php?section=' + encodeURIComponent(sectionKey), { method: 'GET', credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                showToast(data.error || 'Erro ao carregar seção', 'error');
                return;
            }
            var section = data.data;
            showLandingSectionEditor(section);
        });
}

function showLandingSectionEditor(section) {
    var overlay = document.createElement('div');
    overlay.className = 'fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[200] flex items-center justify-center p-4';
    overlay.id = 'landing-edit-overlay';
    var extraData = section.extraData ? JSON.stringify(section.extraData, null, 2) : '';
    overlay.innerHTML = '<div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl border border-slate-200 dark:border-slate-600 w-full max-w-2xl max-h-[90vh] overflow-y-auto">' +
        '<div class="p-8">' +
        '<div class="flex items-center justify-between mb-6">' +
        '<h3 class="text-xl font-black text-slate-900 dark:text-slate-100">Editar: ' + (section.sectionTitle || section.sectionKey) + '</h3>' +
        '<button type="button" onclick="document.getElementById(\'landing-edit-overlay\').remove()" class="p-2 rounded-xl text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Fechar">' +
        '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg></button></div>' +
        '<form id="landing-edit-form" class="space-y-4">' +
        '<input type="hidden" name="section_key" value="' + section.sectionKey + '">' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Título</label>' +
        '<input type="text" name="section_title" value="' + (section.sectionTitle || '').replace(/"/g, '&quot;') + '" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" /></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Subtítulo</label>' +
        '<input type="text" name="section_subtitle" value="' + (section.sectionSubtitle || '').replace(/"/g, '&quot;') + '" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" /></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Conteúdo (HTML)</label>' +
        '<textarea name="section_content" rows="5" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium">' + (section.sectionContent || '') + '</textarea></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">URL da Imagem</label>' +
        '<input type="url" name="section_image" value="' + (section.sectionImage || '').replace(/"/g, '&quot;') + '" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" placeholder="https://..." /></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Ordem</label>' +
        '<input type="number" name="section_order" value="' + section.sectionOrder + '" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium" /></div>' +
        '<div><label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Dados Extras (JSON)</label>' +
        '<textarea name="extra_data" rows="4" class="w-full bg-white dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-medium font-mono text-xs">' + extraData + '</textarea></div>' +
        '<p id="landing-edit-error" class="text-red-600 text-sm font-medium hidden"></p>' +
        '<div class="flex gap-3 pt-2">' +
        '<button type="button" onclick="document.getElementById(\'landing-edit-overlay\').remove()" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>' +
        '<button type="submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Salvar</button>' +
        '</div></form></div></div>';
    document.body.appendChild(overlay);
    document.getElementById('landing-edit-form').addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        var data = {
            section_key: formData.get('section_key'),
            section_title: formData.get('section_title'),
            section_subtitle: formData.get('section_subtitle'),
            section_content: formData.get('section_content'),
            section_image: formData.get('section_image'),
            section_order: parseInt(formData.get('section_order')) || 0,
            extra_data: null
        };
        try {
            if (formData.get('extra_data').trim()) {
                data.extra_data = JSON.parse(formData.get('extra_data'));
            }
        } catch(err) {
            document.getElementById('landing-edit-error').textContent = 'JSON inválido no campo Dados Extras';
            document.getElementById('landing-edit-error').classList.remove('hidden');
            return;
        }
        document.getElementById('landing-edit-error').classList.add('hidden');
        fetch(API_BASE + 'landing-page.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify(data)
        })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                document.getElementById('landing-edit-overlay').remove();
                showToast('Seção salva com sucesso!');
                loadLandingPageSections();
            } else {
                document.getElementById('landing-edit-error').textContent = res.error || 'Erro ao salvar';
                document.getElementById('landing-edit-error').classList.remove('hidden');
            }
        });
    });
}

function toggleLandingSection(checkbox) {
    var sectionKey = checkbox.dataset.section;
    var field = checkbox.dataset.field;
    var value = checkbox.checked ? 1 : 0;
    fetch(API_BASE + 'landing-page.php?section=' + encodeURIComponent(sectionKey), { method: 'GET', credentials: 'include' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                showToast(data.error || 'Erro ao carregar seção', 'error');
                checkbox.checked = !checkbox.checked;
                return;
            }
            var section = data.data;
            section.is_active = value;
            fetch(API_BASE + 'landing-page.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify(section)
            })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (res.success) {
                    showToast('Status atualizado!');
                } else {
                    showToast(res.error || 'Erro ao salvar', 'error');
                    checkbox.checked = !checkbox.checked;
                }
            });
        });
}

// =============================================================================
// FUNÇÕES DE GERENCIAMENTO DE EQUIPE (MINHA EQUIPE)
// =============================================================================

function renderTeamTab(contentArea) {
    contentArea.innerHTML = `
        <div class="max-w-4xl mx-auto py-10">
            <div class="flex justify-between items-center mb-10 flex-wrap gap-4">
                <div>
                    <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Minha Equipe</h3>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Adicione novos colaboradores para prospectarem na ferramenta utilizando o saldo de tokens da empresa.</p>
                </div>
                <button type="button" id="btn-add-team-member" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-2xl text-xs flex items-center gap-2 shadow-lg shadow-blue-100 dark:shadow-blue-900/30">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" /></svg>
                    Adicionar Colaborador
                </button>
            </div>
            <div id="team-error" class="hidden mb-6 p-4 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm font-medium"></div>
            <div id="team-list" class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm overflow-hidden">
                <div class="p-8 text-center text-slate-500 dark:text-slate-400">
                    <span class="inline-block w-8 h-8 border-2 border-slate-300 border-t-blue-600 rounded-full animate-spin"></span>
                    <p class="mt-3 text-sm font-bold">Carregando equipe…</p>
                </div>
            </div>
        </div>
    `;
    document.getElementById('btn-add-team-member').onclick = showAddTeamMemberModal;
    loadTeamList();
}

async function loadTeamList() {
    const listEl = document.getElementById('team-list');
    if (!listEl) return;
    try {
        const res = await fetch(API_BASE + 'users.php', { credentials: 'same-origin' });
        const data = await res.json();
        if (!data.success) {
            listEl.innerHTML = `<div class="p-8 text-center text-red-600 font-bold">${data.error || 'Erro ao carregar equipe.'}</div>`;
            return;
        }
        const users = data.data || [];
        if (users.length === 0) {
            listEl.innerHTML = `<div class="p-8 text-center text-slate-400 font-bold">Nenhum colaborador cadastrado além de você.</div>`;
            return;
        }
        let html = `
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-800/50">
                            <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider">Colaborador</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider">E-mail</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider">Perfil</th>
                            <th class="px-6 py-4 text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
        `;
        users.forEach(u => {
            const isMe = u.id == AppState.user.id;
            const profileLabel = u.profile === 'admin' ? 'Administrador' : 'Colaborador';
            const profileClass = u.profile === 'admin' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400';
            html += `
                <tr class="border-b border-slate-100 dark:border-slate-650 hover:bg-slate-50/50 dark:hover:bg-slate-700/50">
                    <td class="px-6 py-4">
                        <span class="font-bold text-slate-900 dark:text-slate-100">${u.name}</span>
                        ${isMe ? '<span class="ml-2 text-[9px] font-black bg-blue-600 text-white px-2 py-0.5 rounded-full uppercase">Você</span>' : ''}
                    </td>
                    <td class="px-6 py-4 text-slate-600 dark:text-slate-400 font-semibold text-sm">${u.email}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-1 rounded-full text-xs font-bold ${profileClass}">${profileLabel}</span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        ${!isMe ? `<button type="button" class="btn-delete-team-member text-red-500 hover:bg-red-50 font-bold px-4 py-2 rounded-xl text-xs transition" data-id="${u.id}" data-name="${u.name}">Remover</button>` : '—'}
                    </td>
                </tr>
            `;
        });
        html += `
                    </tbody>
                </table>
            </div>
        `;
        listEl.innerHTML = html;
        listEl.querySelectorAll('.btn-delete-team-member').forEach(btn => {
            btn.onclick = () => {
                const id = btn.dataset.id;
                const name = btn.dataset.name;
                removeTeamMember(id, name);
            };
        });
    } catch (e) {
        listEl.innerHTML = `<div class="p-8 text-center text-red-600 font-bold">Erro de conexão ao carregar equipe.</div>`;
    }
}

function showAddTeamMemberModal() {
    const overlay = document.createElement('div');
    overlay.id = 'team-add-overlay';
    overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-[100] p-4';
    overlay.innerHTML = `
        <div class="bg-white dark:bg-slate-800 rounded-[2rem] shadow-2xl p-8 max-w-md w-full" onclick="event.stopPropagation()">
            <h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mb-6">Novo Colaborador</h4>
            <form id="team-add-form" class="space-y-4">
                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Nome Completo</label>
                    <input type="text" id="add-member-name" required placeholder="Ex: Lucas Mendes" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" />
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">E-mail de Acesso</label>
                    <input type="email" id="add-member-email" required placeholder="Ex: lucas@suaempresa.com" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" />
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">WhatsApp (Celular com DDD)</label>
                    <input type="text" id="add-member-phone" required placeholder="DDD + Número (Ex: 17991504628)" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" />
                </div>
                <div>
                    <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Senha de Acesso</label>
                    <input type="password" id="add-member-password" required placeholder="Mínimo 6 caracteres" minlength="6" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-bold placeholder-slate-400 dark:placeholder-slate-500" />
                </div>
                <p id="team-add-error" class="text-red-600 text-sm font-medium hidden"></p>
                <div class="flex gap-3 pt-4">
                    <button type="button" id="team-add-cancel" class="flex-1 py-3 rounded-xl border border-slate-200 dark:border-slate-600 font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-700">Cancelar</button>
                    <button type="submit" id="team-add-submit" class="flex-1 py-3 rounded-xl bg-blue-600 text-white font-bold hover:bg-blue-700">Adicionar</button>
                </div>
            </form>
        </div>
    `;
    overlay.onclick = () => overlay.remove();
    document.body.appendChild(overlay);
    document.getElementById('team-add-cancel').onclick = () => overlay.remove();
    const form = document.getElementById('team-add-form');
    const errorEl = document.getElementById('team-add-error');
    const submitBtn = document.getElementById('team-add-submit');
    form.onsubmit = async (e) => {
        e.preventDefault();
        const name = document.getElementById('add-member-name').value.trim();
        const email = document.getElementById('add-member-email').value.trim();
        const phone = document.getElementById('add-member-phone').value.trim();
        const password = document.getElementById('add-member-password').value;
        errorEl.classList.add('hidden');
        submitBtn.disabled = true;
        submitBtn.textContent = 'Adicionando...';
        try {
            const res = await fetch(API_BASE + 'users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ name, email, phone, password })
            });
            const data = await res.json();
            if (data.success) {
                overlay.remove();
                showToast('Colaborador adicionado com sucesso!');
                loadTeamList();
            } else {
                errorEl.textContent = data.error || 'Erro ao adicionar.';
                errorEl.classList.remove('hidden');
            }
        } catch (err) {
            errorEl.textContent = 'Erro de conexão.';
            errorEl.classList.remove('hidden');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Adicionar';
        }
    };
}

function removeTeamMember(id, name) {
    if (!confirm(`Deseja realmente remover o colaborador "${name}" da equipe? Ele perderá acesso ao painel.`)) return;
    fetch(API_BASE + 'users.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'same-origin',
        body: JSON.stringify({ id })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast('Colaborador removido!');
            loadTeamList();
        } else {
            alert(data.error || 'Erro ao remover colaborador.');
        }
    })
    .catch(() => alert('Erro de conexão.'));
}

function setupCnpjEvents() {
    const input = document.getElementById('cnpj-search-input');
    const form = document.getElementById('cnpj-search-form');
    const errorEl = document.getElementById('cnpj-error');
    const resultsContainer = document.getElementById('cnpj-results-container');
    const btn = document.getElementById('btn-cnpj-search');
    if (!input || !form) return;
    input.addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length > 14) value = value.slice(0, 14);
        let masked = '';
        if (value.length > 0) masked += value.slice(0, 2);
        if (value.length > 2) masked += '.' + value.slice(2, 5);
        if (value.length > 5) masked += '.' + value.slice(5, 8);
        if (value.length > 8) masked += '/' + value.slice(8, 12);
        if (value.length > 12) masked += '-' + value.slice(12, 14);
        e.target.value = masked;
    });
    form.onsubmit = async function(e) {
        e.preventDefault();
        const rawCnpj = input.value.replace(/\D/g, '');
        if (rawCnpj.length !== 14) {
            errorEl.textContent = 'Por favor, digite um CNPJ completo com 14 dígitos.';
            errorEl.classList.remove('hidden');
            return;
        }
        errorEl.classList.add('hidden');
        resultsContainer.classList.add('hidden');
        btn.disabled = true;
        btn.innerHTML = '<span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin inline-block mr-2"></span> Buscando...';
        const hasPaidCnpjKey = AppState.config && AppState.config.cnpjApiKeyConfigured;
        try {
            if (hasPaidCnpjKey) {
                const res = await fetch(API_BASE + 'cnpj.php?cnpj=' + encodeURIComponent(rawCnpj));
                const data = await res.json();
                if (data.success) {
                    renderCnpjResults(data.data, resultsContainer);
                    resultsContainer.classList.remove('hidden');
                    if (data.data.tokenUsage) {
                        AppState.tokenUsage = data.data.tokenUsage;
                        renderHeaderTokenWarning();
                    }
                } else {
                    errorEl.textContent = data.error || 'Erro ao consultar o CNPJ.';
                    errorEl.classList.remove('hidden');
                }
            } else {
                const res = await fetch('https://publica.cnpj.ws/cnpj/' + encodeURIComponent(rawCnpj));
                if (res.status === 404) {
                    errorEl.textContent = 'CNPJ não encontrado na base de dados da Receita Federal.';
                    errorEl.classList.remove('hidden');
                    return;
                }
                if (res.status === 429) {
                    errorEl.textContent = 'Limite de consultas por IP atingido na API gratuita. Tente novamente em alguns segundos.';
                    errorEl.classList.remove('hidden');
                    return;
                }
                if (res.ok) {
                    const data = await res.json();
                    const deductRes = await fetch(API_BASE + 'cnpj.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ cnpj: rawCnpj, companyData: data })
                    });
                    const deductData = await deductRes.json();
                    if (deductData.success) {
                        renderCnpjResults(data, resultsContainer);
                        resultsContainer.classList.remove('hidden');
                        if (deductData.data && deductData.data.tokenUsage) {
                            AppState.tokenUsage = deductData.data.tokenUsage;
                            renderHeaderTokenWarning();
                        }
                    } else {
                        errorEl.textContent = deductData.error || 'Erro ao processar o débito de tokens da consulta.';
                        errorEl.classList.remove('hidden');
                    }
                } else {
                    errorEl.textContent = 'Erro ao consultar CNPJ na base pública.';
                    errorEl.classList.remove('hidden');
                }
            }
        } catch (err) {
            errorEl.textContent = 'Erro de conexão ou saldo insuficiente de tokens.';
            errorEl.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Consultar Empresa';
        }
    };
}

// =============================================================================
// FUNÇÕES DE CONSULTA E ENRIQUECIMENTO DE CNPJ (API CNPJ.WS)
// =============================================================================

function renderCnpjLookupTab(contentArea) {
    contentArea.innerHTML = `
        <div class="max-w-4xl mx-auto py-10 space-y-8">
            <div>
                <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Consulta de CNPJ</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Enriqueça sua base de dados consultando os dados cadastrais, quadro societário, CNAEs e endereço oficial de qualquer empresa diretamente na base da Receita Federal.</p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] shadow-sm border border-slate-200 dark:border-slate-600">
                <form id="cnpj-search-form" class="flex flex-col sm:flex-row gap-4 items-end">
                    <div class="flex-grow">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Digite o CNPJ</label>
                        <input id="cnpj-search-input" type="text" required class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm placeholder-slate-400 dark:placeholder-slate-500" placeholder="00.000.000/0001-00">
                    </div>
                    <button type="submit" id="btn-cnpj-search" class="w-full sm:w-auto px-8 py-3.5 bg-slate-900 hover:bg-blue-600 text-white font-black rounded-xl transition-all shadow-lg text-xs uppercase tracking-wider">
                        Consultar Empresa
                    </button>
                </form>
            </div>
            <div id="cnpj-error" class="hidden p-5 bg-amber-50 border-l-4 border-amber-400 text-amber-900 rounded-r-2xl shadow-sm"></div>
            <div id="cnpj-results-container" class="hidden"></div>
        </div>
    `;
    setupCnpjEvents();
}

function renderCnpjResults(data, container) {
    const est = data.estabelecimento || {};
    const razao = data.razao_social || 'Não informado';
    const fantasia = est.nome_fantasia || 'Não informado';
    const statusClass = est.situacao_cadastral === 'Ativa' ? 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20' : 'bg-red-500/10 text-red-500 border-red-500/20';
    const capital = data.capital_social ? parseFloat(data.capital_social).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' }) : 'Não informado';
    const ddd = est.ddd1 || '';
    const tel = est.telefone1 || '';
    const phone = ddd && tel ? `(${ddd}) ${tel}` : 'Não informado';
    const email = est.email || 'Não informado';
    const logradouro = est.logradouro || '';
    const numero = est.numero || '';
    const complemento = est.complemento ? `, ${est.complemento}` : '';
    const bairro = est.bairro ? ` - ${est.bairro}` : '';
    const cidade = est.cidade ? est.cidade.nome : '';
    const uf = est.estado ? est.estado.sigla : '';
    const cep = est.cep ? ` CEP ${est.cep}` : '';
    const enderecoCompleto = `${logradouro}, ${numero}${complemento}${bairro}, ${cidade} - ${uf}${cep}`;
    const sociosList = data.socios && data.socios.length > 0 
        ? data.socios.map(s => `
            <div class="p-4 bg-slate-50 dark:bg-slate-700/50 border border-slate-100 dark:border-slate-700 rounded-2xl flex items-center gap-3">
                <span class="text-xl">👤</span>
                <div>
                    <p class="text-xs font-black text-slate-900 dark:text-slate-100 uppercase leading-snug">${s.nome}</p>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-wider">${s.qualificacao_socio ? s.qualificacao_socio.descricao : 'Sócio'}</p>
                </div>
            </div>
        `).join('')
        : '<p class="text-xs text-slate-400 font-bold col-span-full">Nenhum sócio ou administrador listado.</p>';
    const cnaePrincipal = est.atividade_principal ? `${est.atividade_principal.id} - ${est.atividade_principal.descricao}` : 'Não informado';
    const cnaesSecundarios = est.atividades_secundarias && est.atividades_secundarias.length > 0
        ? est.atividades_secundarias.map(c => `<li class="text-[10px] leading-relaxed text-slate-600 dark:text-slate-400 font-semibold">• ${c.id} - ${c.descricao}</li>`).join('')
        : '<li class="text-[10px] text-slate-400 font-bold list-none">• Nenhum CNAE secundário listado.</li>';
    container.innerHTML = `
        <div class="space-y-8 animate-slide-in">
            <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <span class="px-3 py-1 text-[10px] font-black border rounded-full uppercase tracking-wider ${statusClass}">${est.situacao_cadastral || 'Inativo'}</span>
                    <h4 class="text-xl font-black text-slate-900 dark:text-slate-100 mt-3 tracking-tight uppercase leading-snug">${razao}</h4>
                    <p class="text-xs font-bold text-slate-400 mt-1 uppercase">Nome Fantasia: <span class="text-slate-600 dark:text-slate-300">${fantasia}</span></p>
                </div>
                <button type="button" id="btn-export-cnpj-webhook" class="w-full md:w-auto flex items-center justify-center gap-2 px-6 py-4 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-black uppercase shadow-lg shadow-blue-100 dark:shadow-blue-900/30 transition-all">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                    Exportar enriquecido para Webhook
                </button>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm space-y-6">
                    <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-700 pb-2">🏢 Dados Cadastrais</h5>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="text-[9px] font-black text-slate-400 uppercase">CNPJ</span>
                                <p class="text-slate-700 dark:text-slate-300 font-mono font-bold text-xs">${est.cnpj || 'Não informado'}</p>
                            </div>
                            <div>
                                <span class="text-[9px] font-black text-slate-400 uppercase">Abertura</span>
                                <p class="text-slate-700 dark:text-slate-300 font-bold text-xs">${est.data_situacao_cadastral || 'Não informado'}</p>
                            </div>
                            <div>
                                <span class="text-[9px] font-black text-slate-400 uppercase">Capital Social</span>
                                <p class="text-slate-700 dark:text-slate-300 font-bold text-xs text-blue-600">${capital}</p>
                            </div>
                            <div>
                                <span class="text-[9px] font-black text-slate-400 uppercase">Porte</span>
                                <p class="text-slate-700 dark:text-slate-300 font-bold text-xs uppercase">${data.porte ? data.porte.descricao : 'Não informado'}</p>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-700 space-y-3">
                            <div class="flex items-center gap-3 bg-emerald-50/50 dark:bg-emerald-950/20 p-3 rounded-xl">
                                <span class="text-emerald-500 text-xs">📞</span>
                                <span class="text-emerald-700 dark:text-emerald-400 font-bold text-xs">${phone}</span>
                            </div>
                            <div class="flex items-center gap-3 bg-blue-50/50 dark:bg-blue-950/20 p-3 rounded-xl">
                                <span class="text-blue-500 text-xs">✉️</span>
                                <span class="text-blue-700 dark:text-blue-400 font-bold text-xs truncate">${email}</span>
                            </div>
                        </div>
                        <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                            <span class="text-[9px] font-black text-slate-400 uppercase">Endereço Fiscal Oficial</span>
                            <p class="text-slate-500 dark:text-slate-400 font-semibold text-[11px] leading-relaxed mt-1">${enderecoCompleto}</p>
                        </div>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm space-y-6">
                    <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-700 pb-2">👥 Sócios e Administradores (QSA)</h5>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-[250px] overflow-y-auto pr-1">
                        ${sociosList}
                    </div>
                </div>
            </div>
            <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm space-y-6">
                <h5 class="text-xs font-black text-slate-400 uppercase tracking-widest border-b border-slate-100 dark:border-slate-700 pb-2">📊 Atividades Econômicas (CNAE)</h5>
                <div class="space-y-4">
                    <div>
                        <span class="text-[9px] font-black text-slate-400 uppercase">CNAE Principal</span>
                        <p class="text-slate-700 dark:text-slate-300 font-bold text-xs mt-1">• ${cnaePrincipal}</p>
                    </div>
                    <div class="pt-4 border-t border-slate-100 dark:border-slate-700">
                        <span class="text-[9px] font-black text-slate-400 uppercase mb-2 block">CNAES Secundários</span>
                        <ul class="space-y-2 max-h-[150px] overflow-y-auto pr-1">
                            ${cnaesSecundarios}
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    `;
    document.getElementById('btn-export-cnpj-webhook').onclick = async function() {
        if (!AppState.config || !AppState.config.baseUrl || String(AppState.config.baseUrl).trim() === '') {
            alert('Configure a URL do Webhook em Configurações para usar esta função.');
            return;
        }
        const btnExport = document.getElementById('btn-export-cnpj-webhook');
        btnExport.disabled = true;
        btnExport.innerHTML = '<span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin inline-block mr-2"></span> Enviando...';
        try {
            const res = await fetch(API_BASE + 'export.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    leadId: 'cnpj-' + est.cnpj,
                    leadName: razao,
                    leadAddress: enderecoCompleto,
                    leadPhone: phone,
                    leadEmail: email,
                    leadWebsite: fantasia,
                    customData: {
                        cnpj: est.cnpj,
                        capitalSocial: capital,
                        situacaoCadastral: est.situacao_cadastral,
                        cnaePrincipal: cnaePrincipal,
                        socios: data.socios ? data.socios.map(s => s.nome).join(', ') : ''
                    }
                })
            });
            const resData = await res.json();
            if (resData.success) {
                showToast(resData.message || 'Dados enriquecidos enviados ao CRM!');
            } else {
                alert('❌ Erro no Envio: ' + resData.error);
            }
        } catch (e) {
            alert('❌ Erro de conexão: ' + e.message);
        } finally {
            btnExport.disabled = false;
            btnExport.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg> Exportar enriquecido para Webhook';
        }
    };
}

function renderLatestCompaniesTab(contentArea) {
    contentArea.innerHTML = `
        <div class="max-w-4xl mx-auto py-10 space-y-8">
            <div>
                <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Empresas Recém-Abertas</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Consulte as últimas 20 empresas abertas em uma cidade. (Custo: 5 tokens)</p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 dark:border-slate-600">
                <div class="flex gap-4">
                    <input id="latest-city-input" type="text" class="flex-grow px-5 py-3 rounded-xl bg-slate-50 border border-slate-200" placeholder="Nome da cidade. Ex: Jales, SP">
                    <button id="btn-latest-search" class="px-8 py-3 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700">Buscar</button>
                </div>
            </div>
            <div id="latest-results" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
        </div>
    `;
    document.getElementById('btn-latest-search').onclick = async () => {
        const city = document.getElementById('latest-city-input').value;
        const container = document.getElementById('latest-results');
        container.innerHTML = '<p class="text-center">Buscando...</p>';
        const res = await fetch(API_BASE + 'latest-companies.php?city=' + encodeURIComponent(city));
        const data = await res.json();
        if (data.success) {
            container.innerHTML = data.data.map(emp => `
                <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200">
                    <h4 class="font-bold text-sm text-blue-600">${emp.razao_social}</h4>
                    <p class="text-[10px] text-slate-500 uppercase font-black">${emp.estabelecimento.cnpj_formatado}</p>
                    <p class="text-xs mt-2">${emp.estabelecimento.logradouro}, ${emp.estabelecimento.numero}</p>
                </div>
            `).join('');
        } else {
            container.innerHTML = '<p class="text-red-500">Erro: ' + data.error + '</p>';
        }
    };
}

// =============================================================================
// FUNÇÕES DE BUSCA AVANÇADA CNPJ (API CNPJPW)
// =============================================================================

function renderCnpjAdvancedTab(contentArea) {
    contentArea.innerHTML = `
        <div class="max-w-4xl mx-auto py-10 space-y-8">
            <div>
                <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Busca Avançada por Segmento</h3>
                <p class="text-sm text-slate-500 dark:text-slate-400">Localize empresas por atividade (CNAE), nome ou localização. Ideal para prospecção em massa por setores específicos.</p>
            </div>
            <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] shadow-sm border border-slate-200 dark:border-slate-600">
                <form id="cnpj-advanced-form" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Segmento ou Palavra-Chave</label>
                            <input id="adv-segment" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm" placeholder="Ex: Pet Shop, Restaurante...">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Estado (UF)</label>
                            <select id="adv-uf" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm">
                                <option value="">Todos os Estados</option>
                                <option value="AC">Acre</option><option value="AL">Alagoas</option><option value="AP">Amapá</option><option value="AM">Amazonas</option><option value="BA">Bahia</option><option value="CE">Ceará</option><option value="DF">Distrito Federal</option><option value="ES">Espírito Santo</option><option value="GO">Goiás</option><option value="MA">Maranhão</option><option value="MT">Mato Grosso</option><option value="MS">Mato Grosso do Sul</option><option value="MG">Minas Gerais</option><option value="PA">Pará</option><option value="PB">Paraíba</option><option value="PR">Paraná</option><option value="PE">Pernambuco</option><option value="PI">Piauí</option><option value="RJ">Rio de Janeiro</option><option value="RN">Rio Grande do Norte</option><option value="RS">Rio Grande do Sul</option><option value="RO">Rondônia</option><option value="RR">Roraima</option><option value="SC">Santa Catarina</option><option value="SP">São Paulo</option><option value="SE">Sergipe</option><option value="TO">Tocantins</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Cidade (Nome exato)</label>
                            <input id="adv-city" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm" placeholder="Ex: Jales ou Sao Paulo">
                        </div>
                        <div>
                            <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">Código CNAE (Opcional)</label>
                            <input id="adv-cnae" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 focus:border-blue-500 outline-none font-bold text-sm" placeholder="Ex: 4789004">
                        </div>
                    </div>
                    <button type="submit" id="btn-adv-search" class="w-full py-4 bg-slate-900 hover:bg-blue-600 text-white font-black rounded-xl transition-all shadow-lg text-xs uppercase tracking-widest">
                        Iniciar Busca Avançada
                    </button>
                </form>
            </div>
            <div id="adv-error" class="hidden p-5 bg-amber-50 border-l-4 border-amber-400 text-amber-900 rounded-r-2xl shadow-sm"></div>
            <div id="adv-results-container" class="grid grid-cols-1 md:grid-cols-2 gap-4"></div>
        </div>
    `;
}

async function setupCnpjAdvancedEvents() {
    const form = document.getElementById('cnpj-advanced-form');
    if (!form) return;
    
    form.onsubmit = async function(e) {
        e.preventDefault();
        
        const segment = document.getElementById('adv-segment').value.trim();
        const uf = document.getElementById('adv-uf').value; // Pode ser "" (vazio)
        const cityName = document.getElementById('adv-city').value.trim();
        const manualCnae = document.getElementById('adv-cnae').value.trim();
        
        const btn = document.getElementById('btn-adv-search');
        const errorEl = document.getElementById('adv-error');
        const container = document.getElementById('adv-results-container');

        btn.disabled = true;
        btn.innerHTML = 'Processando...';
        errorEl.classList.add('hidden');
        container.innerHTML = '';

        try {
            let params = new URLSearchParams();

            // 1. CNAE
            if (manualCnae) {
                params.append('cnae', manualCnae.replace(/\D/g, ''));
            } else if (segment) {
                const cnaeRes = await fetch('https://api.cnpj.pw/cnaes/');
                const cnaeData = await cnaeRes.json();
                const match = cnaeData.resultados.find(c => c.descricao.toLowerCase().includes(segment.toLowerCase()));
                if (match) params.append('cnae', match.codigo);
                else params.append('razao_social', segment);
            }

            // 2. Cidade com segurança extra
            if (cityName) {
                const munRes = await fetch('https://api.cnpj.pw/municipios/');
                const munData = await munRes.json();
                const normalize = (str) => (str || "").toString().normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
                
                const municipio = munData.resultados.find(m => 
                    normalize(m.descricao) === normalize(cityName) && 
                    (!uf || (m.uf && m.uf.toUpperCase() === uf.toUpperCase()))
                );
                
                if (municipio) {
                    params.append('municipio', municipio.codigo);
                }
            }

            // 3. UF com segurança
            if (uf && uf !== "") params.append('uf', uf.toUpperCase());

            const searchRes = await fetch(`https://api.cnpj.pw/busca_difusa/?${params.toString()}`);
            const searchData = await searchRes.json();

            if (searchData.resultados && searchData.resultados.length > 0) {
                renderAdvancedResults(searchData.resultados, container);
            } else {
                errorEl.textContent = "Nenhuma empresa encontrada com esses filtros.";
                errorEl.classList.remove('hidden');
            }
        } catch (err) {
            console.error("Erro na busca:", err);
            errorEl.textContent = "Erro ao processar busca. Verifique os dados.";
            errorEl.classList.remove('hidden');
        } finally {
            btn.disabled = false;
            btn.textContent = 'Iniciar Busca Avançada';
        }
        
        // 4. EXECUÇÃO DA BUSCA DIFUSA
            const urlCompleta = `https://api.cnpj.pw/busca_difusa/?${params.toString()}`;
            console.log("URL de busca sendo enviada:", urlCompleta);
            
            const searchRes = await fetch(urlCompleta);
            const searchData = await searchRes.json();
            
            console.log("Resposta recebida da API:", searchData);

            if (searchData.resultados && searchData.resultados.length > 0) {
                renderAdvancedResults(searchData.resultados, container);
                showToast(`${searchData.resultados.length} empresas encontradas!`);
            } else {
                errorEl.textContent = "Nenhuma empresa encontrada com esses filtros. Tente remover o filtro de cidade.";
                errorEl.classList.remove('hidden');
            }
            
function renderAdvancedResults(results, container) {
    if (!results || results.length === 0) {
        container.innerHTML = '<p class="col-span-full text-center py-10 text-slate-500">Nenhum resultado encontrado.</p>';
        return;
    }
    container.innerHTML = results.map(emp => `
        <div class="bg-white dark:bg-slate-800 p-5 rounded-2xl border border-slate-200 dark:border-slate-600 shadow-sm flex flex-col justify-between">
            <div>
                <h4 class="font-black text-xs text-blue-600 dark:text-blue-400 uppercase truncate" title="${emp.nome_fantasia || emp.nome_empresarial}">
                    ${emp.nome_fantasia || emp.nome_empresarial}
                </h4>
                <p class="text-[10px] font-mono font-bold text-slate-500 mt-1">${emp.cnpj}</p>
                <p class="text-[10px] text-slate-400 mt-2 uppercase font-bold">📍 ${emp.municipio} - ${emp.uf}</p>
            </div>
            <button onclick="copyToCnpjLookup('${emp.cnpj}')" class="mt-4 w-full py-2 bg-slate-100 dark:bg-slate-700 text-[10px] font-black uppercase rounded-lg hover:bg-blue-600 hover:text-white transition-all">
                Ver Detalhes Completos
            </button>
        </div>
    `).join('');
}

window.copyToCnpjLookup = function(cnpj) {
    const cleanCnpj = cnpj.replace(/\D/g, '');
    setActiveTab('cnpj-lookup');
    setTimeout(() => {
        const input = document.getElementById('cnpj-search-input');
        if (input) {
            input.value = cleanCnpj;
            const btn = document.getElementById('btn-cnpj-search');
            if (btn) btn.click();
        }
    }, 500);
};

window.toggleSubmenu = function(id) {
    const submenu = document.getElementById(id);
    const arrow = document.getElementById('arrow-' + id.split('-')[1]);
    if (submenu) {
        if (submenu.classList.contains('hidden')) {
            submenu.classList.remove('hidden');
            if (arrow) arrow.style.transform = 'rotate(180deg)';
        } else {
            submenu.classList.add('hidden');
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        }
    }
};

// Re-garantir que os botões do sub-menu funcionem de forma geral
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.tab-btn');
    if (btn && btn.dataset.tab) {
        setActiveTab(btn.dataset.tab);
};
// --- CORREÇÃO DE SEGURANÇA E FECHAMENTO DE BLOCOS ---

window.openLoginModal = function() {
    var landingPage = document.getElementById('landing-page');
    var loginScreen = document.getElementById('login-screen');
    if (landingPage) landingPage.style.display = 'none';
    if (loginScreen) loginScreen.style.display = 'flex';
};

window.toggleSubmenu = function(id) {
    const submenu = document.getElementById(id);
    const arrow = document.getElementById('arrow-' + id.split('-')[1]);
    if (submenu) {
        submenu.classList.toggle('hidden');
        if (arrow) arrow.style.transform = submenu.classList.contains('hidden') ? 'rotate(0deg)' : 'rotate(180deg)';
    }
};

window.copyToCnpjLookup = function(cnpj) {
    const cleanCnpj = cnpj.replace(/\D/g, '');
    setActiveTab('cnpj-lookup');
    setTimeout(() => {
        const input = document.getElementById('cnpj-search-input');
        if (input) {
            input.value = cleanCnpj;
            const btn = document.getElementById('btn-cnpj-search');
            if (btn) btn.click();
        }
    }, 500);
};

document.addEventListener('click', function(e) {
    const btn = e.target.closest('.tab-btn');
    if (btn && btn.dataset.tab) {
        setActiveTab(btn.dataset.tab);
    }
});

console.log("Arquivo carregado e corrigido.");
// ... (seu código atual)

// Garanta que todas as funções tenham o fechamento correto:
function setupCnpjEvents() {
    // ... conteúdo da função
} // Fecha setupCnpjEvents

function renderCnpjLookupTab(contentArea) {
    // ... conteúdo
} // Fecha renderCnpjLookupTab

function renderCnpjResults(data, container) {
    // ... conteúdo
} // Fecha renderCnpjResults

function renderLatestCompaniesTab(contentArea) {
    // ... conteúdo
} // Fecha renderLatestCompaniesTab

// ADICIONE ISSO NO FINAL PARA CORRIGIR O ERRO DE SINTAXE E REFERENCE:
window.openLoginModal = openLoginModal;
