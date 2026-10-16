/**
 * auth.js - Autenticação e Controle de Sessão
 */

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
                showDashboard(); // Definida no app.js mais tarde
                if (typeof loadConfig === 'function') loadConfig();
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

async function handleLogin(e) {
    if (e && e.preventDefault) e.preventDefault();
    
    const emailInput = document.getElementById('login-email');
    const passwordInput = document.getElementById('login-password');
    const errorEl = document.getElementById('login-error');
    const btn = document.getElementById('btn-login');
    
    const email = (emailInput?.value || '').trim().toLowerCase();
    const password = passwordInput?.value || '';
    
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
    btn.disabled = true;

    try {
        const res = await fetch(API_BASE + 'auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'login', email, password })
        });
        
        const data = await res.json();
        
        if (data.success) {
            AppState.user = data.data.user;
            AppState.tenant = data.data.tenant || null;
            AppState.tokenUsage = data.data.tokenUsage || null;
            AppState.impersonating = !!(data.data?.impersonating);
            AppState.impersonatingTenantName = data.data?.impersonatingTenantName || '';
            
            window.location.reload();
        } else {
            if (errorEl) {
                errorEl.textContent = data.error || 'Falha no login.';
                errorEl.classList.remove('hidden');
            }
        }
    } catch (err) {
        if (errorEl) {
            errorEl.textContent = 'Erro de conexão com o servidor.';
            errorEl.classList.remove('hidden');
        }
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Entrar <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>';
    }
}

function logout() {
    fetch(API_BASE + 'auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' }),
        credentials: 'same-origin'
    }).then(() => {
        AppState.user = null;
        AppState.tenant = null;
        AppState.tokenUsage = null;
        window.location.reload();
    });
}

function showLogin() {
    console.log('Exibindo tela de login...');
    const loginScreen = document.getElementById('login-screen');
    const dashboard = document.getElementById('dashboard');
    const landingPage = document.getElementById('landing-page');

    if (loginScreen) loginScreen.classList.remove('hidden');
    if (dashboard) dashboard.classList.add('hidden');
    if (landingPage) landingPage.classList.add('hidden');
}

function showLandingPage() {
    console.log('Exibindo landing page...');
    const loginScreen = document.getElementById('login-screen');
    const dashboard = document.getElementById('dashboard');
    const landingPage = document.getElementById('landing-page');

    if (landingPage) landingPage.classList.remove('hidden');
    if (loginScreen) loginScreen.classList.add('hidden');
    if (dashboard) dashboard.classList.add('hidden');
    
    if (typeof updateThemeIconsLanding === 'function') updateThemeIconsLanding();
}