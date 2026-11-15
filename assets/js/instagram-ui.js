(function () {
    'use strict';
    var timer = null;
    var latestLeads = [];
    var latestRunId = null;
    function esc(value) { return String(value == null ? '' : value).replace(/[&<>'"]/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'})[c]; }); }
    function interestLabel(value) { var normalized = String(value || 'low').toLowerCase(); return normalized === 'high' || normalized === 'alto' || normalized === 'alta' ? 'Alto' : (normalized === 'medium' || normalized === 'médio' || normalized === 'medio' || normalized === 'média' || normalized === 'media' ? 'Médio' : 'Baixo'); }
    function apiBase() { return (typeof API_BASE !== 'undefined' ? API_BASE : '/api/'); }
    function el(id) { return document.getElementById(id); }
    function status(message, error) { var node = el('instagram-status'); if (!node) return; node.textContent = message; node.className = 'mt-4 rounded-xl px-4 py-3 text-sm font-semibold ' + (error ? 'bg-red-50 text-red-700 dark:bg-red-950/30 dark:text-red-200' : 'bg-slate-50 text-slate-600 dark:bg-slate-700/50 dark:text-slate-200'); node.classList.remove('hidden'); }
    function csvValue(value) { return '"' + String(value == null ? '' : value).replace(/"/g, '""') + '"'; }
    function renderResults(data) {
        latestLeads = Array.isArray(data.leads) ? data.leads : [];
        latestRunId = Number(data.runId || 0);
        var box = el('instagram-results'); if (!box) return;
        var summary = (data.leadsFound || 0) + ' perfil(is) único(s) · ' + (data.commentsCollected || 0) + ' comentário(s) coletado(s) · ' + (data.creditsCharged || 0) + ' crédito(s)';
        if (!latestLeads.length) { box.innerHTML = '<p class="text-sm text-slate-500">Nenhum perfil público foi encontrado nos comentários analisados.</p>'; box.classList.remove('hidden'); status(summary); return; }
        var rows = latestLeads.map(function (lead, index) {
            var normalizedInterest = String(lead.interest_level || 'low').toLowerCase();
            var badge = (normalizedInterest === 'high' || normalizedInterest === 'alto' || normalizedInterest === 'alta') ? 'bg-emerald-100 text-emerald-700' : ((normalizedInterest === 'medium' || normalizedInterest === 'médio' || normalizedInterest === 'medio' || normalizedInterest === 'média' || normalizedInterest === 'media') ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-600');
            return '<tr class="border-t border-slate-100 dark:border-slate-700"><td class="px-3 py-3"><input class="instagram-row" type="checkbox" checked data-index="' + index + '"></td><td class="px-3 py-3"><a class="font-black text-pink-600 hover:underline" target="_blank" rel="noopener noreferrer" href="' + esc(lead.profile_url) + '">' + esc(lead.username ? '@' + lead.username : 'Perfil') + '</a><span class="block text-[10px] text-slate-400">' + esc(lead.name || '') + '</span></td><td class="px-3 py-3 max-w-sm">' + esc(lead.comment_text || '—') + '</td><td class="px-3 py-3"><span class="rounded-full px-2 py-1 text-[10px] font-black uppercase ' + badge + '">' + esc(interestLabel(lead.interest_level)) + '</span></td><td class="px-3 py-3 text-xs text-slate-500">' + esc(lead.comments_count || 1) + '</td><td class="px-3 py-3"><a class="text-xs text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer" href="' + esc(lead.source_post_url) + '">Publicação</a></td></tr>';
        }).join('');
        box.innerHTML = '<div class="flex flex-wrap items-center justify-between gap-3 mb-3"><p class="text-sm font-black text-slate-900 dark:text-white">' + esc(summary) + '</p><button type="button" id="instagram-export" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-black text-white hover:bg-slate-700">Exportar CSV</button></div><div class="mobile-table-scroll"><table class="w-full text-left text-xs"><thead class="text-[10px] uppercase text-slate-500"><tr><th class="px-3 py-2"><input id="instagram-select-all" type="checkbox" checked></th><th class="px-3 py-2">Perfil</th><th class="px-3 py-2">Comentário</th><th class="px-3 py-2">Interesse</th><th class="px-3 py-2">Comentários</th><th class="px-3 py-2">Origem</th></tr></thead><tbody>' + rows + '</tbody></table></div>';
        box.classList.remove('hidden');
        el('instagram-select-all').onchange = function () { box.querySelectorAll('.instagram-row').forEach(function (item) { item.checked = this.checked; }, this); };
        el('instagram-export').onclick = async function () {
            if (typeof window.currentUserCanExport === 'function' && !window.currentUserCanExport()) {
                if (typeof window.showFreemiumExportModal === 'function') window.showFreemiumExportModal();
                return;
            }
            var selectedIndexes = Array.prototype.slice.call(box.querySelectorAll('.instagram-row:checked')).map(function (item) { return Number(item.dataset.index); });
            if (!selectedIndexes.length) { status('Selecione ao menos um perfil para exportar.', true); return; }
            var button = el('instagram-export');
            button.disabled = true;
            try {
                var response = await fetch(apiBase() + 'instagram-export.php', {
                    method: 'POST', credentials: 'include',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'text/csv, application/json' },
                    body: JSON.stringify({ runId: latestRunId, leadIndexes: selectedIndexes })
                });
                if (!response.ok) {
                    var payload = await response.json().catch(function () { return {}; });
                    if (response.status === 403 && payload.code === 'export_not_allowed') {
                        if (typeof window.showFreemiumExportModal === 'function') window.showFreemiumExportModal();
                        return;
                    }
                    throw new Error(payload.error || 'Não foi possível gerar a exportação.');
                }
                var blob = await response.blob();
                var url = URL.createObjectURL(blob);
                var link = document.createElement('a');
                link.href = url;
                link.download = 'instagram-comentarios.csv';
                link.click();
                window.setTimeout(function () { URL.revokeObjectURL(url); }, 1000);
                status('Arquivo CSV gerado.');
            } catch (error) {
                status(error.message || 'Não foi possível exportar os perfis.', true);
            } finally {
                button.disabled = false;
            }
        };
        status(summary);
    }
    async function poll(runId, button) {
        var response = await fetch(apiBase() + 'instagram-search-status.php?run_id=' + encodeURIComponent(runId), { credentials: 'include', cache: 'no-store' });
        var data = await response.json();
        if (!data.success) throw new Error(data.error || 'Não foi possível atualizar a pesquisa.');
        var result = data.data || {};
        if (result.pending) { status('Coletando comentários públicos…'); return false; }
        if (result.status === 'failed') throw new Error(result.message || 'A pesquisa não pôde ser concluída.');
        renderResults(result); if (button) button.disabled = false; return true;
    }
    function setup() {
        var value = el('instagram-value');
        if (!value || value.dataset.ready === 'true') return;
        value.dataset.ready = 'true';
        var button = el('btn-instagram-search');
        button.onclick = async function () {
            var raw = (value.value || '').trim(); if (!raw) { status('Informe a URL da publicação ou Reel.', true); return; }
            button.disabled = true; latestLeads = []; if (el('instagram-results')) el('instagram-results').classList.add('hidden'); status('Iniciando pesquisa…');
            try {
                var response = await fetch(apiBase() + 'instagram-search.php', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ mode: 'post', value: raw, comments_limit: Number(el('instagram-comments-limit').value || 50), total_comments_limit: Number(el('instagram-total-limit').value || 300), include_replies: !!el('instagram-replies').checked }) });
                var data = await response.json(); if (!data.success) throw new Error(data.error || 'Não foi possível iniciar a pesquisa.');
                if (timer) clearInterval(timer); var done = false; timer = setInterval(async function () { if (done) return; try { done = await poll(data.data.runId, button); if (done) clearInterval(timer); } catch (error) { done = true; clearInterval(timer); button.disabled = false; status(error.message, true); } }, 2500);
            } catch (error) { button.disabled = false; status(error.message, true); }
        };
    }
    window.renderInstagramTab = function (container) {
        container.innerHTML = '<div class="mx-auto max-w-6xl space-y-6"><div class="rounded-[2rem] border border-pink-200 bg-gradient-to-br from-pink-50 via-white to-orange-50 p-6 shadow-sm dark:border-pink-900/60 dark:from-pink-950/30 dark:via-slate-800 dark:to-orange-950/20"><p class="text-[10px] font-black uppercase tracking-[0.2em] text-pink-600">Instagram · leads de intenção</p><h3 class="mt-2 text-2xl font-black text-slate-900 dark:text-white">Comentários públicos</h3><p class="mt-2 max-w-3xl text-sm text-slate-600 dark:text-slate-300">Cole a URL de uma publicação ou Reel para identificar autores públicos dos comentários.</p></div><div class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"><label class="mb-2 block text-[10px] font-black uppercase tracking-wider text-slate-500">URL da publicação ou Reel</label><input id="instagram-value" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-600 dark:bg-slate-700" placeholder="https://www.instagram.com/p/... ou /reel/..." /><p class="mt-2 text-xs text-slate-500">Serão analisados somente os comentários públicos desta URL.</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><div><label class="mb-2 block text-[10px] font-black uppercase tracking-wider text-slate-500">Comentários</label><input id="instagram-comments-limit" type="number" min="1" max="250" value="50" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-600 dark:bg-slate-700" /></div><div><label class="mb-2 block text-[10px] font-black uppercase tracking-wider text-slate-500">Total de comentários</label><input id="instagram-total-limit" type="number" min="1" max="500" value="300" class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm dark:border-slate-600 dark:bg-slate-700" /></div></div><label class="mt-4 flex items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300"><input id="instagram-replies" type="checkbox"> Incluir respostas quando disponíveis</label><div class="mt-5 flex flex-wrap items-center gap-3"><button id="btn-instagram-search" type="button" class="rounded-xl bg-pink-600 px-5 py-3 text-xs font-black uppercase tracking-wider text-white shadow-lg shadow-pink-900/20 hover:bg-pink-700">Iniciar pesquisa</button><span class="text-xs text-slate-500">O custo externo varia conforme a quantidade de itens extraídos.</span></div><div id="instagram-status" class="hidden"></div></div><div id="instagram-results" class="hidden rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800"></div></div>';
        var results = container.querySelector('#instagram-results');
        if (results && window.BannerCampaign) {
            var banner = document.createElement('div');
            results.parentNode.insertBefore(banner, results);
            window.BannerCampaign.render(banner, 'instagram');
        }
        setup();
    };
    window.openInstagramHistoryRun = function (runId) {
        if (typeof setActiveTab === 'function') setActiveTab('instagram');
        window.setTimeout(function () {
            var container = document.getElementById('content-area');
            if (!container) return;
            if (typeof window.renderInstagramTab === 'function') window.renderInstagramTab(container);
            status('Carregando pesquisa salva…');
            fetch(apiBase() + 'instagram-search-status.php?run_id=' + encodeURIComponent(runId), { credentials: 'include', cache: 'no-store' })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data.success) throw new Error(data.error || 'Não foi possível carregar a pesquisa.');
                    renderResults(data.data || {});
                })
                .catch(function (error) { status(error.message, true); });
        }, 150);
    };
}());
