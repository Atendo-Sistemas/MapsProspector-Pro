/**
 * admin.js - Gestão SaaS, Planos, Empresas, Créditos e Equipe
 */

// --- CONFIGURAÇÕES GLOBAIS DA PLATAFORMA (SUPER ADMIN) ---
async function loadConfig() {
    try {
        const res = await fetch(API_BASE + 'settings.php', { credentials: 'include' });
        const data = await res.json();
        if (data.success) {
            AppState.config = data.data;
            updateSearchApiUI();
            updateDocumentTitle();
        }
    } catch (e) { console.error('Erro ao carregar config:', e); }
}

function updateSearchApiUI() {
    const indicator = document.getElementById('search-api-indicator');
    const status = document.getElementById('search-api-status');
    if (!indicator || !status) return;
    
    let configured = false;
    if (AppState.config) {
        const isSuperAdmin = String(AppState.user?.profile).toLowerCase() === 'super_admin';
        configured = isSuperAdmin 
            ? !!AppState.config.scraperApiKey 
            : !!AppState.config.scraperApiKeyConfigured;
    }

    if (configured) {
        indicator.className = 'w-2 h-2 rounded-full bg-emerald-500';
        status.textContent = 'Conectado ao Google Maps';
    } else {
        indicator.className = 'w-2 h-2 rounded-full bg-red-500';
        status.textContent = 'API não configurada';
    }
}

// --- GESTÃO DE EQUIPE ---
function renderTeamTab(contentArea) {
    contentArea.innerHTML = `
        <div class="max-w-4xl mx-auto py-10">
            <div class="flex justify-between items-center mb-10">
                <h3 class="text-2xl font-black text-slate-900 dark:text-white">Minha Equipe</h3>
                <button onclick="showAddTeamMemberModal()" class="bg-blue-600 text-white font-bold px-6 py-3 rounded-2xl text-xs">Adicionar Colaborador</button>
            </div>
            <div id="team-list" class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 overflow-hidden shadow-sm">
                <p class="p-8 text-center text-slate-500">Carregando equipe...</p>
            </div>
        </div>`;
    loadTeamList();
}

async function loadTeamList() {
    const listEl = document.getElementById('team-list');
    if (!listEl) return;
    try {
        const res = await fetch(API_BASE + 'users.php');
        const data = await res.json();
        if (data.success) {
            let html = `<table class="w-full text-left"><thead class="bg-slate-50 dark:bg-slate-700/50"><tr>
                <th class="px-6 py-4 text-[10px] uppercase font-black text-slate-400">Nome</th>
                <th class="px-6 py-4 text-[10px] uppercase font-black text-slate-400">E-mail</th>
                <th class="px-6 py-4 text-right text-[10px] uppercase font-black text-slate-400">Ações</th>
            </tr></thead><tbody>`;
            data.data.forEach(u => {
                html += `<tr class="border-t border-slate-100 dark:border-slate-700">
                    <td class="px-6 py-4 font-bold text-slate-900 dark:text-white">${u.name}</td>
                    <td class="px-6 py-4 text-slate-500">${u.email}</td>
                    <td class="px-6 py-4 text-right">
                        <button onclick="removeTeamMember('${u.id}', '${u.name}')" class="text-red-500 font-bold text-xs">Remover</button>
                    </td>
                </tr>`;
            });
            html += `</tbody></table>`;
            listEl.innerHTML = html;
        }
    } catch (e) { listEl.innerHTML = '<p class="p-8 text-center text-red-500">Erro ao carregar.</p>'; }
}

async function removeTeamMember(id, name) {
    if (!confirm(`Remover ${name}?`)) return;
    await fetch(API_BASE + 'users.php', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    loadTeamList();
}

// --- GESTÃO DE PLANOS (SUPER ADMIN) ---
function loadPlansTab(contentArea) {
    contentArea.innerHTML = `<div class="max-w-4xl mx-auto py-10">
        <div class="flex justify-between items-center mb-10">
            <h3 class="text-2xl font-black text-slate-900 dark:text-white">Planos do Sistema</h3>
            <button onclick="showPlansCreateModal()" class="bg-blue-600 text-white font-bold px-6 py-3 rounded-2xl text-xs">Criar Plano</button>
        </div>
        <div id="plans-list" class="space-y-4"></div>
    </div>`;
    renderPlansList();
}

async function renderPlansList() {
    const listEl = document.getElementById('plans-list');
    const res = await fetch(API_BASE + 'plans.php');
    const data = await res.json();
    if(data.success) {
        listEl.innerHTML = data.data.items.map(p => `
            <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 flex justify-between items-center">
                <div>
                    <h4 class="font-black text-slate-900 dark:text-white">${p.name}</h4>
                    <p class="text-xs text-slate-400 uppercase font-bold">${p.tokenLimit} tokens / mês</p>
                </div>
                <div class="flex gap-2">
                    <button class="px-4 py-2 bg-slate-100 dark:bg-slate-700 rounded-xl text-xs font-bold">Editar</button>
                </div>
            </div>
        `).join('');
    }
}

// --- SOLICITAÇÃO DE CRÉDITOS ---
function renderRequestCreditsTab(contentArea) {
    contentArea.innerHTML = `
        <div class="max-w-2xl mx-auto py-10">
            <h3 class="text-2xl font-black text-slate-900 dark:text-white mb-6">Solicitar Créditos</h3>
            <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 shadow-sm">
                <form id="request-credits-form" class="space-y-4">
                    <label class="block text-[10px] font-black text-slate-400 uppercase">Quantidade de Tokens</label>
                    <input id="credits-amount" type="number" min="100" class="w-full p-4 rounded-xl bg-slate-50 border border-slate-200 font-bold" value="100">
                    <button type="submit" class="w-full py-4 bg-blue-600 text-white font-black rounded-xl uppercase text-xs">Enviar Solicitação</button>
                </form>
            </div>
        </div>`;
    
    document.getElementById('request-credits-form').onsubmit = async (e) => {
        e.preventDefault();
        const tokensRequested = document.getElementById('credits-amount').value;
        const res = await fetch(API_BASE + 'credit-requests.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tokensRequested })
        });
        const data = await res.json();
        if(data.success) {
            showToast('Solicitação enviada!');
            setActiveTab('dashboard');
        }
    };
}