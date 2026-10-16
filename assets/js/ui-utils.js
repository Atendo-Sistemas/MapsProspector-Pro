/**
 * ui-utils.js - Gestão de Temas, Notificações e Visibilidade de Telas
 */

// --- GESTÃO DE TEMA ---
function getStoredTheme() {
    try {
        var stored = localStorage.getItem(THEME_STORAGE_KEY);
        if (stored === 'dark' || stored === 'light') return stored;
        return (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
    } catch (e) { return 'light'; }
}

function applyTheme(theme) {
    const root = document.documentElement;
    theme === 'dark' ? root.classList.add('dark') : root.classList.remove('dark');
    try { localStorage.setItem(THEME_STORAGE_KEY, theme); } catch (e) {}
    updateThemeIconsLanding();
}

function toggleTheme() {
    const next = getStoredTheme() === 'dark' ? 'light' : 'dark';
    applyTheme(next);
}

function updateThemeIconsLanding() {
    const sun = document.getElementById('theme-icon-sun') || document.getElementById('theme-icon-sun-landing');
    const moon = document.getElementById('theme-icon-moon') || document.getElementById('theme-icon-moon-landing');
    const isDark = getStoredTheme() === 'dark';
    if (sun && moon) {
        sun.classList.toggle('hidden', !isDark);
        moon.classList.toggle('hidden', isDark);
    }
}

// --- VISIBILIDADE DE TELAS (CORREÇÃO DE REFERENCE ERROR) ---

function showLogin() {
    console.log('Exibindo tela de login...');
    document.getElementById('login-screen')?.classList.remove('hidden');
    document.getElementById('dashboard')?.classList.add('hidden');
    document.getElementById('landing-page')?.classList.add('hidden');
}

function showLandingPage() {
    console.log('Exibindo landing page...');
    document.getElementById('landing-page')?.classList.remove('hidden');
    document.getElementById('login-screen')?.classList.add('hidden');
    document.getElementById('dashboard')?.classList.add('hidden');
}

function showDashboard() {
    console.log('Exibindo dashboard...');
    document.getElementById('dashboard')?.classList.remove('hidden');
    document.getElementById('login-screen')?.classList.add('hidden');
    document.getElementById('landing-page')?.classList.add('hidden');

    if (AppState.user) {
        const userNameEl = document.getElementById('user-name');
        if (userNameEl) userNameEl.textContent = AppState.user.name;
        
        const profile = String(AppState.user.profile).toLowerCase();
        const isAdmin = profile === 'admin' || profile === 'super_admin';
        
        document.querySelectorAll('.admin-only').forEach(el => {
            isAdmin ? el.classList.remove('hidden') : el.classList.add('hidden');
        });
    }

    if (typeof loadSidebarCompanyName === 'function') loadSidebarCompanyName();
    if (typeof setActiveTab === 'function') setActiveTab(AppState.activeTab);
}

// --- NOTIFICAÇÕES ---
function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const msg = document.getElementById('toast-message');
    if (!toast || !msg) return;
    msg.textContent = message;
    toast.classList.remove('hidden');
    setTimeout(() => toast.classList.add('hidden'), 4000);
}

function showError(message) {
    const err = document.getElementById('error-info');
    if (err) {
        err.classList.remove('hidden');
        err.innerHTML = `<p class="text-xs font-bold">⚠️ ${message}</p>`;
    }
}

function hideError() {
    document.getElementById('error-info')?.classList.add('hidden');
}

window.openLoginModal = showLogin;