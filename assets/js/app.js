/**
 * MapsProspector Pro - JavaScript Principal (interface única PHP/XAMPP)
 * Histórico de pesquisas: gravado e listado via banco de dados (api/search.php e api/history.php).
 */

const API_BASE = 'api/';

// Estado da aplicação (histórico vem do banco via history.php)
const AppState = {
    user: null,
    config: null,
    activeTab: 'search',
    userCoords: null,
    userLocationName: '',
    locStatus: 'idle', // idle | loading | success | error
    leads: [],
    history: [],      // preenchido por loadHistory() -> api/history.php (banco)
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
async function handleLogin() {
    const btn = document.getElementById('btn-login');
    btn.disabled = true;
    btn.innerHTML = '<span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin inline-block"></span> Carregando...';
    
    try {
        const res = await fetch(API_BASE + 'auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'login',
                email: 'admin@atendo.maps'
            })
        });
        
        const data = await res.json();
        if (data.success) {
            AppState.user = data.data.user;
            showDashboard();
            loadConfig();
        } else {
            alert('Erro ao fazer login: ' + data.error);
        }
    } catch (e) {
        alert('Erro de conexão: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Acessar Plataforma <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>';
    }
}

// Mostra/Oculta telas
function showLogin() {
    document.getElementById('login-screen').classList.remove('hidden');
    document.getElementById('dashboard').classList.add('hidden');
}

function showDashboard() {
    document.getElementById('login-screen').classList.add('hidden');
    document.getElementById('dashboard').classList.remove('hidden');
    if (AppState.user) {
        document.getElementById('user-name').textContent = AppState.user.name;
    }
    loadTab(AppState.activeTab);
}

// Event Listeners
function setupEventListeners() {
    document.getElementById('btn-login').addEventListener('click', handleLogin);
    
    // Tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            setActiveTab(tab);
        });
    });
    
    // GPS
    document.getElementById('btn-refresh-gps').addEventListener('click', refreshLocation);
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
        search: 'Prospecção Inteligente',
        history: 'Arquivo de Buscas',
        settings: 'Integração CRM'
    };
    document.getElementById('page-title').textContent = titles[tab] || 'Dashboard';
    
    loadTab(tab);
}

// Carrega conteúdo da tab
function loadTab(tab) {
    const contentArea = document.getElementById('content-area');
    
    if (tab === 'search') {
        contentArea.innerHTML = getProspectingHTML();
        setupProspectingEvents();
    } else if (tab === 'history') {
        loadHistory();
    } else if (tab === 'settings') {
        contentArea.innerHTML = getSettingsHTML();
        setupSettingsEvents();
        loadSettingsForm();
    }
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
            <div class="bg-white p-6 rounded-[2rem] shadow-sm border border-slate-200 mb-10">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-4">
                        <label class="block text-[10px] font-black text-slate-400 uppercase mb-1 ml-1 tracking-widest">O que busca?</label>
                        <input id="search-query" type="text" class="w-full px-5 py-3 rounded-xl bg-slate-50 border border-slate-200 focus:border-blue-500 outline-none font-bold text-sm transition-all" placeholder="Ex: Petshop, Clínica, Padaria...">
                    </div>
                    <div class="md:col-span-4">
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
                </div>
                <div id="results-header-buttons" class="flex items-center gap-3">
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
    const leads = AppState.leads || [];
    if (leads.length === 0) {
        alert('Nenhum dado na pesquisa atual para exportar.');
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

// Realiza busca (api/search.php grava pesquisa e leads no banco; listagem vem de api/history.php)
async function performSearch() {
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
        const res = await fetch(API_BASE + 'search.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                query,
                location: useGPS ? null : location,
                tag,
                useGPS,
                coords: useGPS ? AppState.userCoords : null,
                locationName: useGPS ? AppState.userLocationName : null
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
    
    const visible = AppState.leads.slice(0, AppState.visibleCount);
    document.getElementById('visible-count').textContent = visible.length;
    document.getElementById('total-count').textContent = AppState.leads.length;
    
    grid.innerHTML = visible.map(lead => getLeadCardHTML(lead)).join('');
    
    // Botões de exportar
    document.querySelectorAll('.btn-export').forEach(btn => {
        btn.addEventListener('click', () => {
            const leadId = btn.dataset.leadId;
            exportLead(leadId);
        });
    });
    
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

// HTML do card de lead
function getLeadCardHTML(lead) {
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
                AppState.leads = item.leads;
                AppState.currentSearch = {
                    query: item.query || '',
                    location: item.location || '',
                    tag: item.tag || ''
                };
                setActiveTab('search');
                // Recarrega a tab de busca
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
                        <h4 class="font-black text-xl mb-4 flex items-center gap-2">API Thordata (ScraperAPI)</h4>
                        <div id="api-status" class="p-5 bg-emerald-500/20 border border-emerald-500/50 rounded-2xl text-emerald-400 text-center font-bold">✓ Conectado ao Google Maps via Thordata</div>
                        <p class="text-[10px] text-slate-400 mt-4 text-center italic">API configurada no servidor</p>
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
                    
                    <div class="mt-6 bg-[#0F172A] p-6 rounded-[2rem] border border-slate-800 text-white relative overflow-hidden">
                        <div class="absolute top-0 right-0 w-32 h-32 bg-purple-600/10 rounded-full blur-3xl"></div>
                        <h4 class="font-black text-lg mb-3 flex items-center gap-2">🔑 ScraperAPI Thordata</h4>
                        <p class="text-xs text-slate-400 mb-4">Chave de API para busca direta no Google Maps</p>
                        <div>
                            <label class="block text-[10px] font-black text-slate-300 uppercase mb-2 ml-1">Chave da API Thordata</label>
                            <input id="setting-scraper-api" type="password" class="w-full bg-slate-900/50 border border-slate-700 rounded-2xl px-6 py-4 outline-none focus:border-purple-500 font-bold text-white placeholder:text-slate-500" placeholder="Insira a chave da API Thordata">
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
            // Carrega a chave da API Thordata se o elemento existir
            const scraperApiInput = document.getElementById('setting-scraper-api');
            if (scraperApiInput) {
                scraperApiInput.value = AppState.config.scraperApiKey || '';
            }
            
            updateSwitch('toggle-simplified', AppState.config.simplifiedPayload);
            updateSwitch('toggle-proxy', AppState.config.useProxy);
            updateSwitch('toggle-wrap', AppState.config.wrapInBody);
        }
    } catch (e) {
        console.error('Erro ao carregar configurações:', e);
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
    
    try {
        const res = await fetch(API_BASE + 'settings.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                baseUrl: document.getElementById('setting-url').value,
                token: document.getElementById('setting-token').value,
                tenantName: document.getElementById('setting-tenant').value,
                scraperApiKey: document.getElementById('setting-scraper-api') ? document.getElementById('setting-scraper-api').value : '',
                simplifiedPayload: AppState.config.simplifiedPayload || false,
                useProxy: AppState.config.useProxy || false,
                wrapInBody: AppState.config.wrapInBody || false
            })
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
