/**
 * search-maps.js - Busca de Leads (Google Maps) e Exportação
 */

async function performSearch() {
    var limitReached = (AppState.tokenUsage && AppState.tokenUsage.limitReached) || (AppState.tenant && AppState.tenant.status === 'suspended');
    if (limitReached) {
        showError('Você atingiu o limite de tokens do seu plano para este período.');
        return;
    }

    const query = document.getElementById('search-query').value.trim();
    const location = document.getElementById('search-location')?.value.trim() || '';
    const tag = document.getElementById('search-tag').value.trim();
    
    if (!query) {
        showError('Digite o ramo de atividade (Ex: Petshop).');
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
                location: location,
                tag: tag,
                maxCrawledPlacesPerSearch: 20,
                searchMethod: 'scraper'
            })
        });
        
        const data = await res.json();
        
        if (data.success) {
            AppState.leads = data.data.leads;
            AppState.searchId = data.data.searchId || null;
            if (data.data.tokenUsage) AppState.tokenUsage = data.data.tokenUsage;
            AppState.currentSearch = { query, location, tag };
            
            displayLeads();
        } else {
            showError(data.error || 'Erro ao buscar leads');
        }
    } catch (e) {
        showError('Erro de conexão: ' + e.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = 'Buscar Tudo';
    }
}

function displayLeads() {
    const grid = document.getElementById('leads-grid');
    const emptyState = document.getElementById('empty-state');
    const resultsHeader = document.getElementById('results-header');
    const loadMoreContainer = document.getElementById('load-more-container');
    
    if (!grid) return;

    if (AppState.leads.length === 0) {
        grid.innerHTML = '';
        emptyState?.classList.remove('hidden');
        resultsHeader?.classList.add('hidden');
        loadMoreContainer?.classList.add('hidden');
        return;
    }
    
    emptyState?.classList.add('hidden');
    resultsHeader?.classList.remove('hidden');

    const visible = AppState.leads.slice(0, AppState.visibleCount);
    document.getElementById('visible-count').textContent = visible.length;
    document.getElementById('total-count').textContent = AppState.leads.length;
    
    grid.innerHTML = visible.map(lead => getLeadCardHTML(lead)).join('');
    
    // Adiciona eventos aos botões dos cards recém-criados
    grid.querySelectorAll('.btn-unlock').forEach(btn => {
        btn.onclick = () => doUnlockLeads([btn.dataset.leadId]);
    });

    grid.querySelectorAll('.btn-export').forEach(btn => {
        btn.onclick = () => exportLead(btn.dataset.leadId);
    });

    // Botão Carregar Mais
    if (AppState.leads.length > AppState.visibleCount) {
        loadMoreContainer.classList.remove('hidden');
        loadMoreContainer.innerHTML = `
            <button id="btn-load-more" class="px-8 py-4 bg-white border-2 border-slate-200 rounded-full shadow-lg font-black text-xs uppercase tracking-widest hover:border-blue-500 transition-all">
                Carregar Mais (${AppState.leads.length - AppState.visibleCount} restantes)
            </button>
        `;
        document.getElementById('btn-load-more').onclick = () => {
            AppState.visibleCount += 12;
            displayLeads();
        };
    } else {
        loadMoreContainer.classList.add('hidden');
    }
}

/**
 * search-maps.js - Cards Ricos e Exportação Completa
 */

function getLeadCardHTML(lead) {
    if (lead.locked) {
        return `
        <div class="relative mt-4 pt-10 pb-6 px-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-[1.5rem] shadow-sm flex flex-col justify-between">
            <div class="absolute -top-3 left-6 bg-amber-500 text-white text-[9px] font-black py-1.5 px-3 rounded-lg uppercase">Bloqueado</div>
            <h3 class="font-extrabold text-slate-900 dark:text-slate-100 text-sm uppercase mb-4 min-h-[2.5rem]">${lead.name}</h3>
            <div class="bg-slate-50 dark:bg-slate-700/50 p-3 rounded-lg mb-6 text-center">
                <span class="text-slate-400 text-xs">🔒 Dados protegidos.</span>
            </div>
            <button class="btn-unlock w-full py-3 bg-amber-500 text-white rounded-xl font-black text-[10px] uppercase" data-lead-id="${lead.id}">Desbloquear Lead</button>
        </div>`;
    }

    return `
        <div class="relative mt-4 pt-10 pb-6 px-6 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded-[1.5rem] shadow-sm flex flex-col justify-between">
            <div class="absolute -top-3 left-6 bg-blue-600 text-white text-[9px] font-black py-1.5 px-3 rounded-lg uppercase">${lead.partners ? 'Dados Ricos' : 'Lead'}</div>
            <div>
                <h3 class="font-extrabold text-slate-900 dark:text-slate-100 text-sm uppercase mb-4 min-h-[2.5rem]">${lead.name}</h3>
                <div class="space-y-3 mb-6">
                    <div class="flex items-center gap-3 bg-emerald-50/50 dark:bg-emerald-950/20 p-2 rounded-lg">
                        <span class="text-emerald-500 text-xs">📞</span>
                        <span class="text-emerald-700 dark:text-emerald-300 font-bold text-xs">${lead.phone || 'Sem telefone'}</span>
                    </div>
                    ${lead.cnpj ? `<div class="text-[10px] font-bold text-slate-500 uppercase">🏢 CNPJ: <span class="text-slate-900 dark:text-white font-mono">${lead.cnpj}</span></div>` : ''}
                    ${lead.partners ? `<div class="text-[10px] font-bold text-slate-500 uppercase">👥 Sócios: <span class="text-slate-700 dark:text-slate-300">${lead.partners}</span></div>` : ''}
                    <div class="text-[10px] font-semibold text-slate-400 leading-tight">📍 ${lead.address || ''}</div>
                </div>
            </div>
            <div class="flex gap-2">
                <button class="btn-export flex-grow bg-slate-900 text-white font-black text-[10px] py-3 rounded-xl uppercase" data-lead-id="${lead.id}">Exportar Webhook</button>
                ${lead.mapsUri ? `<a href="${lead.mapsUri}" target="_blank" class="w-10 h-10 flex items-center justify-center border rounded-xl">📍</a>` : ''}
            </div>
        </div>`;
}

// ... as outras funções performSearch, doUnlockLeads continuam as mesmas que enviamos antes.

async function doUnlockLeads(leadIds) {
    if (!AppState.searchId) return;
    try {
        const res = await fetch(API_BASE + 'unlock.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ searchId: AppState.searchId, leadIds })
        });
        const data = await res.json();
        if (data.success) {
            const unlocked = data.data.unlocked || {};
            AppState.leads.forEach(l => {
                if (unlocked[l.id]) {
                    Object.assign(l, unlocked[l.id]);
                    l.locked = false;
                }
            });
            displayLeads();
        }
    } catch (e) { console.error(e); }
}

async function exportLead(leadId) {
    const lead = AppState.leads.find(l => l.id === leadId);
    if (!lead) return;
    showToast(`Exportando ${lead.name}...`);
    try {
        const res = await fetch(API_BASE + 'export.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leadId: leadId.replace('lead-', ''), leadName: lead.name })
        });
        const data = await res.json();
        if (data.success) showToast('Exportado com sucesso!');
        else alert('Erro: ' + data.error);
    } catch (e) { alert('Erro de conexão'); }
}