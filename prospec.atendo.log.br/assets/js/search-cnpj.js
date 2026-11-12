/**
 * search-cnpj.js - Inteligência de Dados CNPJ (Simples, Avançada e Recém-Abertas)
 */

// --- CARREGAMENTO DE BASES LOCAIS (Municípios e CNAEs) ---
async function loadLocalDatabase() {
    if (AppState.localData.loaded) return;
    try {
        const [resMun, resCnae] = await Promise.all([
            fetch('data/municipios.json').then(r => r.json()),
            fetch('data/cnaes.json').then(r => r.json())
        ]);
        
        if (resMun && resMun.resultados) {
            AppState.localData.municipios = resMun.resultados.map(m => ({
                codigo: m.codigo,
                descricao_norm: m.descricao.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim(),
                descricao: m.descricao
            }));
        }

        if (resCnae && resCnae.resultados) {
            AppState.localData.cnaes = resCnae.resultados.map(c => ({
                codigo: c.codigo,
                descricao_norm: c.descricao.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim(),
                descricao: c.descricao
            }));
        }
        
        AppState.localData.loaded = true;
    } catch (e) {
        console.error("Erro ao carregar bases locais:", e);
        AppState.localData.loaded = true; 
    }
}

// --- CONSULTA CNPJ SIMPLES ---
function setupCnpjEvents() {
    const input = document.getElementById('cnpj-search-input');
    const form = document.getElementById('cnpj-search-form');
    if (!input || !form) return;

    input.addEventListener('input', (e) => {
        let v = e.target.value.replace(/\D/g, '');
        if (v.length > 14) v = v.slice(0, 14);
        if (v.length > 12) v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, "$1.$2.$3/$4-$5");
        else if (v.length > 8) v = v.replace(/^(\d{2})(\d{3})(\d{3})(\d{1,4})/, "$1.$2.$3/$4");
        else if (v.length > 5) v = v.replace(/^(\d{2})(\d{3})(\d{1,3})/, "$1.$2.$3");
        else if (v.length > 2) v = v.replace(/^(\d{2})(\d{1,3})/, "$1.$2");
        e.target.value = v;
    });

    form.onsubmit = async function(e) {
        e.preventDefault();
        const rawCnpj = input.value.replace(/\D/g, '');
        if (rawCnpj.length !== 14) return alert('CNPJ Inválido');

        const btn = document.getElementById('btn-cnpj-search');
        btn.disabled = true;
        btn.innerHTML = 'Consultando...';

        try {
            const res = await fetch(API_BASE + 'cnpj-search.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: 'lookup', params: { cnpj: rawCnpj } })
            });
            const resJson = await res.json();
            if (!resJson.success) throw new Error(resJson.error);

            const container = document.getElementById('cnpj-results-container');
            container.classList.remove('hidden');
            if (resJson.emptyReason) {
                container.textContent = '';
                const emptyState = document.createElement('div');
                emptyState.className = 'mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-sm font-semibold text-amber-900';
                emptyState.textContent = resJson.emptyMessage || 'Nenhuma empresa foi encontrada. Confira o CNPJ e tente novamente.';
                container.appendChild(emptyState);
            } else {
                renderCnpjResults(resJson.data, container);
            }
            checkAuth();
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Consultar Empresa';
        }
    };
}

function renderCnpjResults(data, container) {
    const nomeExibicao = data.nome_empresarial || data.razao_social || "Empresa sem nome";
    const cnpjFull = data.cnpj || "N/A";
    const fone = data.ddd1 && data.telefone_1 ? `(${data.ddd1}) ${data.telefone_1}` : 'Não informado';
    
    container.innerHTML = `
        <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 shadow-sm mt-6">
            <h4 class="text-xl font-black text-slate-900 dark:text-white uppercase">${nomeExibicao}</h4>
            <p class="text-sm text-slate-500 font-mono mt-2">${cnpjFull}</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                <div class="p-4 bg-slate-50 dark:bg-slate-700 rounded-2xl">
                    <p class="text-[10px] font-black uppercase text-slate-400">Telefone</p>
                    <p class="font-bold text-slate-700 dark:text-slate-200">${fone}</p>
                </div>
                <div class="p-4 bg-slate-50 dark:bg-slate-700 rounded-2xl">
                    <p class="text-[10px] font-black uppercase text-slate-400">E-mail</p>
                    <p class="font-bold text-slate-700 dark:text-slate-200">${data.correio_eletronico || 'Não informado'}</p>
                </div>
            </div>
            <button onclick="window.exportarLeadIndividual('${cnpjFull}', '${nomeExibicao.replace(/'/g, "")}')" class="w-full mt-6 py-4 bg-blue-600 text-white rounded-2xl font-black uppercase text-xs">Exportar para CRM</button>
        </div>
    `;
}

// --- BUSCA AVANÇADA (SEGMENTO) ---
async function setupCnpjAdvancedEvents() {
    const form = document.getElementById('cnpj-advanced-form');
    if (!form) return;
    
    form.onsubmit = async function(e) {
        e.preventDefault();
        const segment = document.getElementById('adv-segment').value.trim();
        const uf = document.getElementById('adv-uf').value; 
        const cityName = document.getElementById('adv-city').value.trim();
        
        const btn = document.getElementById('btn-adv-search');
        const container = document.getElementById('adv-results-container');

        btn.disabled = true;
        btn.innerHTML = 'Buscando...';

        try {
            await loadLocalDatabase();
            let searchParams = {};

            if (segment) {
                const buscaSeg = segment.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
                const cnaeEncontrado = AppState.localData.cnaes.find(c => c.descricao_norm.includes(buscaSeg));
                if (cnaeEncontrado) searchParams.cnae = cnaeEncontrado.codigo;
                else searchParams.razao_social = segment;
            }

            if (cityName) {
                const buscaCid = cityName.normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().trim();
                const munEncontrado = AppState.localData.municipios.find(m => m.descricao_norm === buscaCid);
                if (munEncontrado) searchParams.municipio = munEncontrado.codigo;
            }

            if (uf && !searchParams.municipio) searchParams.uf = uf;

            const res = await fetch(API_BASE + 'cnpj-search.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ type: 'difusa', params: searchParams })
            });

            const resJson = await res.json();
            if (!resJson.success) throw new Error(resJson.error);

            renderAdvancedResults(resJson.data.resultados, container);
            checkAuth();
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            btn.disabled = false;
            btn.innerHTML = 'Iniciar Busca Avançada';
        }
    };
}

function renderAdvancedResults(results, container) {
    if (!results || results.length === 0) {
        container.innerHTML = '<p class="col-span-full text-center py-10">Nenhum resultado.</p>';
        return;
    }
    container.innerHTML = results.map(emp => `
        <div class="bg-white dark:bg-slate-800 p-6 rounded-[2rem] border border-slate-200 shadow-sm flex flex-col justify-between">
            <div>
                <h4 class="font-black text-slate-900 dark:text-white text-xs uppercase mb-2 line-clamp-2">${emp.razao_social}</h4>
                <p class="text-[10px] font-mono text-slate-500">${emp.cnpj}</p>
                <p class="text-[10px] text-slate-400 mt-2 uppercase">📍 ${emp.uf}</p>
            </div>
            <button onclick="window.consultarCnpjRapido('${emp.cnpj}')" class="mt-4 w-full py-2 bg-slate-100 dark:bg-slate-700 text-[10px] font-black uppercase rounded-xl">Detalhes</button>
        </div>
    `).join('');
}

// --- AUXILIARES GLOBAIS ---
window.consultarCnpjRapido = function(cnpj) {
    const cleanCnpj = cnpj.replace(/\D/g, '');
    setActiveTab('cnpj-lookup');
    setTimeout(() => {
        const input = document.getElementById('cnpj-search-input');
        const form = document.getElementById('cnpj-search-form');
        if (input && form) {
            input.value = cleanCnpj;
            form.dispatchEvent(new Event('submit'));
        }
    }, 200);
};

window.exportarLeadIndividual = async function(cnpj, nome) {
    showToast(`Exportando ${nome}...`);
    try {
        const res = await fetch(API_BASE + 'export.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leadId: 'cnpj-' + cnpj, leadName: nome })
        });
        const data = await res.json();
        if (data.success) showToast('Exportado!');
        else alert('Erro: ' + data.error);
    } catch (e) { alert('Erro de conexão'); }
};