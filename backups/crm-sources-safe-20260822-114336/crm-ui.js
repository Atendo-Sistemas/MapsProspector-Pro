(function () {
    'use strict';

    var CRM_STAGE_LABELS = {
        novo: 'Novo',
        qualificado: 'Qualificado',
        contato: 'Contato',
        proposta: 'Proposta',
        negociacao: 'Negociação',
        ganho: 'Ganho',
        perdido: 'Perdido'
    };
    var CRM_ACTIVITY_LABELS = { nota: 'Nota', ligacao: 'Ligação', email: 'E-mail', reuniao: 'Reunião', tarefa: 'Tarefa' };
    var CRM_SELECTED_IDS = {};

    function crmEscape(value) {
        if (typeof dashboardMetricEscape === 'function') return dashboardMetricEscape(value == null ? '' : String(value));
        return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[char];
        });
    }
    function crmMoney(cents) { return 'R$ ' + (Number(cents || 0) / 100).toFixed(2).replace('.', ','); }
    function crmDate(value) {
        if (!value) return 'Sem próximo contato';
        var date = new Date(String(value).replace(' ', 'T'));
        return isNaN(date.getTime()) ? String(value) : date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    }
    function crmLeadDisplayName(lead) {
        if (!lead) return 'Lead sem nome';
        return String(lead.leadName || lead.companyName || lead.jobTitle || 'Lead sem nome').trim() || 'Lead sem nome';
    }
    function crmFetch(url, options) {
        return fetch(API_BASE + url, Object.assign({ credentials: 'include', headers: { 'Accept': 'application/json' } }, options || {}))
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (payload) {
                    if (!response.ok || !payload.success) throw new Error(payload.error || 'Não foi possível concluir a operação do CRM.');
                    return payload;
                });
            });
    }
    function crmStageOptions(selected) {
        return Object.keys(CRM_STAGE_LABELS).map(function (key) {
            return '<option value="' + key + '" ' + (key === selected ? 'selected' : '') + '>' + CRM_STAGE_LABELS[key] + '</option>';
        }).join('');
    }
    function crmAssigneeOptions(users, selected) {
        var html = '<option value="0">Sem responsável</option>';
        (users || []).forEach(function (user) {
            html += '<option value="' + Number(user.id) + '" ' + (Number(user.id) === Number(selected || 0) ? 'selected' : '') + '>' + crmEscape(user.name || user.email) + '</option>';
        });
        return html;
    }

    function renderCrmTab(contentArea) {
        contentArea.innerHTML = '<div class="max-w-7xl mx-auto space-y-6 pb-20">' +
            '<div class="flex flex-wrap items-start justify-between gap-4">' +
                '<div><h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">CRM comercial</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Acompanhe os leads pesquisados, distribua responsáveis e registre o próximo contato.</p></div>' +
                '<button type="button" id="crm-refresh" class="rounded-xl bg-slate-100 px-4 py-2.5 text-xs font-black text-slate-700 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600">Atualizar pipeline</button>' +
            '</div>' +
            '<div id="crm-error" class="hidden rounded-2xl border border-red-200 bg-red-50 p-4 text-sm font-bold text-red-700"></div>' +
            '<div id="crm-license" class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-100">Carregando licença CRM…</div>' +
            '<div id="crm-addon-box" class="hidden rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"></div>' +
            '<form id="crm-add-form" class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-600 dark:bg-slate-800">' +
                '<div class="mb-4"><h4 class="font-black text-slate-900 dark:text-slate-100">Adicionar lead ao pipeline</h4><p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pesquise por nome, empresa, e-mail, telefone, cidade ou perfil e selecione o resultado. A busca não consome créditos.</p></div>' +
                '<div class="grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_10rem]">' +
                    '<input id="crm-lead-search" type="search" minlength="2" autocomplete="off" placeholder="Buscar lead por nome, empresa, e-mail ou telefone" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">' +
                    '<select id="crm-lead-source" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100"><option value="all">Todas as origens</option><option value="maps">Maps/CNPJ</option><option value="linkedin">LinkedIn</option></select>' +
                '</div>' +
                '<div id="crm-search-status" class="mt-2 hidden text-xs font-semibold text-slate-500 dark:text-slate-400"></div>' +
                '<div id="crm-search-results" class="mt-3 hidden max-h-72 space-y-2 overflow-y-auto rounded-2xl border border-slate-200 bg-slate-50 p-2 dark:border-slate-600 dark:bg-slate-900"></div>' +
                '<div id="crm-selected-lead" class="mt-3 hidden rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-bold text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200"></div>' +
                '<input id="crm-add-lead-id" type="hidden"><input id="crm-add-linkedin-id" type="hidden">' +
                '<div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-[minmax(0,1fr)_auto]">' +
                    '<select id="crm-add-stage" class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">' + crmStageOptions('novo') + '</select>' +
                    '<button type="submit" id="crm-add-submit" disabled class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-black text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50">Adicionar lead selecionado</button>' +
                '</div>' +
                '<p id="crm-add-message" class="mt-3 hidden text-xs font-bold"></p>' +
            '</form>' +
            '<div id="crm-bulk-toolbar" class="hidden flex flex-wrap items-center gap-3 rounded-2xl border border-blue-200 bg-blue-50 p-4 dark:border-blue-900 dark:bg-blue-950/30">' +
                '<label class="flex items-center gap-2 text-xs font-black text-blue-900 dark:text-blue-100"><input id="crm-select-all" type="checkbox" class="h-4 w-4 accent-blue-600"> Selecionar todos</label>' +
                '<span id="crm-selected-count" class="text-xs font-bold text-blue-800 dark:text-blue-200">0 selecionados</span>' +
                '<select id="crm-bulk-stage" class="rounded-xl border border-blue-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 dark:border-blue-800 dark:bg-slate-800 dark:text-slate-100">' + crmStageOptions('qualificado') + '</select>' +
                '<button type="button" id="crm-bulk-move" class="rounded-xl bg-blue-600 px-4 py-2 text-xs font-black text-white hover:bg-blue-700">Mover selecionados</button>' +
                '<button type="button" id="crm-bulk-clear" class="rounded-xl border border-blue-200 bg-white px-4 py-2 text-xs font-black text-blue-700 hover:bg-blue-100 dark:border-blue-800 dark:bg-slate-800 dark:text-blue-200">Limpar seleção</button>' +
            '</div>' +
            '<div id="crm-board" class="grid grid-cols-1 gap-4 overflow-x-auto lg:grid-cols-4 xl:grid-cols-7"></div>' +
        '</div>';

        document.getElementById('crm-refresh').addEventListener('click', loadCrmBoard);
        document.getElementById('crm-select-all').addEventListener('change', function () {
            var checked = !!this.checked;
            document.querySelectorAll('#crm-board .crm-select-lead').forEach(function (box) {
                box.checked = checked;
                var id = Number(box.getAttribute('data-crm-id'));
                if (checked) CRM_SELECTED_IDS[id] = true; else delete CRM_SELECTED_IDS[id];
            });
            crmUpdateSelectionToolbar();
        });
        document.getElementById('crm-bulk-clear').addEventListener('click', function () {
            CRM_SELECTED_IDS = {};
            document.querySelectorAll('#crm-board .crm-select-lead').forEach(function (box) { box.checked = false; });
            var all = document.getElementById('crm-select-all'); if (all) all.checked = false;
            crmUpdateSelectionToolbar();
        });
        document.getElementById('crm-bulk-move').addEventListener('click', crmMoveSelected);
        var selectedLead = null;
        var searchTimer = null;
        var searchSequence = 0;
        var searchAbortController = null;
        var searchInput = document.getElementById('crm-lead-search');
        var sourceInput = document.getElementById('crm-lead-source');
        var resultsEl = document.getElementById('crm-search-results');
        var searchStatus = document.getElementById('crm-search-status');
        var selectedEl = document.getElementById('crm-selected-lead');
        var submitButton = document.getElementById('crm-add-submit');

        function resetSelectedLead() {
            selectedLead = null;
            document.getElementById('crm-add-lead-id').value = '';
            document.getElementById('crm-add-linkedin-id').value = '';
            if (selectedEl) { selectedEl.textContent = ''; selectedEl.classList.add('hidden'); }
            if (submitButton) submitButton.disabled = true;
        }
        function selectLead(lead) {
            selectedLead = lead;
            document.getElementById('crm-add-lead-id').value = lead.leadId || '';
            document.getElementById('crm-add-linkedin-id').value = lead.linkedinLeadId || '';
            if (selectedEl) {
                var detail = [lead.source === 'linkedin' ? 'LinkedIn' : 'Maps/CNPJ', lead.companyName || lead.jobTitle || '', lead.email || lead.phone || lead.location || ''].filter(Boolean).join(' · ');
                selectedEl.textContent = 'Selecionado: ' + crmLeadDisplayName(lead) + (detail ? ' — ' + detail : '');
                selectedEl.classList.remove('hidden');
            }
            if (submitButton) submitButton.disabled = false;
            if (resultsEl) resultsEl.classList.add('hidden');
        }
        function invalidateSearchRequest() {
            searchSequence += 1;
            if (searchAbortController) {
                searchAbortController.abort();
                searchAbortController = null;
            }
        }
        function renderSearchResults(items) {
            if (!resultsEl) return;
            if (!items.length) {
                resultsEl.innerHTML = '<div class="px-3 py-4 text-center text-xs font-semibold text-slate-500">Nenhum lead encontrado para essa busca.</div>';
                resultsEl.classList.remove('hidden');
                return;
            }
            resultsEl.innerHTML = items.map(function (lead, index) {
                var origin = lead.source === 'linkedin' ? 'LinkedIn' : 'Maps/CNPJ';
                var detail = [lead.companyName || lead.jobTitle || '', lead.email || lead.phone || '', lead.location || ''].filter(Boolean).join(' · ');
                var state = lead.inPipeline ? ' · Já está no pipeline' : '';
                return '<button type="button" data-lead-index="' + index + '" class="crm-search-result block w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-left hover:border-blue-400 hover:bg-blue-50 dark:border-slate-700 dark:bg-slate-800 dark:hover:border-blue-500 dark:hover:bg-slate-900"><span class="block text-sm font-black text-slate-900 dark:text-slate-100">' + crmEscape(crmLeadDisplayName(lead)) + '</span><span class="mt-1 block text-xs text-slate-500 dark:text-slate-400">' + crmEscape(origin + (detail ? ' · ' + detail : '') + state) + '</span></button>';
            }).join('');
            resultsEl.querySelectorAll('.crm-search-result').forEach(function (button) {
                button.addEventListener('click', function () { selectLead(items[Number(button.getAttribute('data-lead-index'))]); });
            });
            resultsEl.classList.remove('hidden');
        }
        function searchLeads() {
            var query = String(searchInput.value || '').trim();
            var requestId = searchSequence;
            if (query.length < 2) {
                invalidateSearchRequest();
                if (resultsEl) resultsEl.classList.add('hidden');
                if (searchStatus) searchStatus.classList.add('hidden');
                return;
            }
            if (resultsEl) {
                resultsEl.innerHTML = '';
                resultsEl.classList.add('hidden');
            }
            if (searchAbortController) {
                searchAbortController.abort();
            }
            var controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
            searchAbortController = controller;
            if (searchStatus) { searchStatus.textContent = 'Buscando leads…'; searchStatus.className = 'mt-2 text-xs font-semibold text-blue-700 dark:text-blue-300'; searchStatus.classList.remove('hidden'); }
            crmFetch('crm.php?action=search-leads&q=' + encodeURIComponent(query) + '&source=' + encodeURIComponent(sourceInput.value || 'all') + '&limit=25', controller ? { signal: controller.signal, cache: 'no-store' } : { cache: 'no-store' })
                .then(function (response) {
                    var currentQuery = String(searchInput.value || '').trim();
                    if (requestId !== searchSequence || currentQuery !== query) return;
                    if (searchAbortController === controller) searchAbortController = null;
                    var items = response.data && Array.isArray(response.data.items) ? response.data.items : [];
                    if (searchStatus) { searchStatus.textContent = items.length + ' resultado(s) encontrado(s).'; searchStatus.className = 'mt-2 text-xs font-semibold text-slate-500 dark:text-slate-400'; searchStatus.classList.remove('hidden'); }
                    renderSearchResults(items);
                })
                .catch(function (error) {
                    if (error && error.name === 'AbortError') return;
                    if (requestId !== searchSequence || String(searchInput.value || '').trim() !== query) return;
                    if (searchAbortController === controller) searchAbortController = null;
                    if (searchStatus) { searchStatus.textContent = error.message || 'Não foi possível buscar os leads.'; searchStatus.className = 'mt-2 text-xs font-semibold text-red-700 dark:text-red-300'; searchStatus.classList.remove('hidden'); }
                    if (resultsEl) resultsEl.classList.add('hidden');
                });
        }
        searchInput.addEventListener('input', function () {
            invalidateSearchRequest();
            resetSelectedLead();
            if (resultsEl) { resultsEl.innerHTML = ''; resultsEl.classList.add('hidden'); }
            if (searchStatus) searchStatus.classList.add('hidden');
            clearTimeout(searchTimer);
            searchTimer = setTimeout(searchLeads, 300);
        });
        sourceInput.addEventListener('change', function () { invalidateSearchRequest(); resetSelectedLead(); if (String(searchInput.value || '').trim().length >= 2) searchLeads(); });
        document.getElementById('crm-add-form').addEventListener('submit', function (event) {
            event.preventDefault();
            var message = document.getElementById('crm-add-message');
            if (!selectedLead) {
                message.className = 'mt-3 text-xs font-bold text-amber-700';
                message.textContent = 'Pesquise e selecione um lead antes de adicionar ao CRM.';
                message.classList.remove('hidden');
                return;
            }
            message.className = 'mt-3 text-xs font-bold text-blue-700';
            message.textContent = 'Adicionando…';
            message.classList.remove('hidden');
            if (submitButton) submitButton.disabled = true;
            crmFetch('crm.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'add', lead_id: selectedLead.leadId || null, linkedin_lead_id: selectedLead.linkedinLeadId || null, stage: document.getElementById('crm-add-stage').value }) })
                .then(function () {
                    message.className = 'mt-3 text-xs font-bold text-emerald-700';
                    message.textContent = 'Lead adicionado ao pipeline.';
                    searchInput.value = '';
                    if (resultsEl) resultsEl.classList.add('hidden');
                    if (searchStatus) searchStatus.classList.add('hidden');
                    resetSelectedLead();
                    loadCrmBoard();
                })
                .catch(function (error) {
                    message.className = 'mt-3 text-xs font-bold text-red-700';
                    message.textContent = error.message;
                    if (submitButton) submitButton.disabled = false;
                });
        });
        loadCrmBoard();
    }

    function showCrmError(error) {
        var element = document.getElementById('crm-error');
        if (!element) return;
        element.textContent = error && error.message ? error.message : 'Não foi possível carregar o CRM.';
        element.classList.remove('hidden');
    }

    function crmUpdateSelectionToolbar() {
        var toolbar = document.getElementById('crm-bulk-toolbar');
        var countEl = document.getElementById('crm-selected-count');
        var count = Object.keys(CRM_SELECTED_IDS).length;
        if (toolbar) toolbar.classList.toggle('hidden', count === 0);
        if (countEl) countEl.textContent = count + ' selecionado(s)';
    }

    function crmMoveSelected() {
        var ids = Object.keys(CRM_SELECTED_IDS).map(Number).filter(function (id) { return id > 0; });
        var stageEl = document.getElementById('crm-bulk-stage');
        var stage = stageEl ? stageEl.value : '';
        if (!ids.length || !stage) return;
        if (!window.confirm('Mover ' + ids.length + ' lead(s) para "' + (CRM_STAGE_LABELS[stage] || stage) + '"?')) return;
        var button = document.getElementById('crm-bulk-move');
        if (button) { button.disabled = true; button.textContent = 'Movendo…'; }
        crmFetch('crm.php', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'bulk-stage', ids: ids, stage: stage }) })
            .then(function (response) {
                if (typeof showToast === 'function') showToast((response.data && response.data.updated ? response.data.updated : ids.length) + ' lead(s) movimentado(s).', 'success');
                CRM_SELECTED_IDS = {};
                loadCrmBoard();
            })
            .catch(showCrmError)
            .finally(function () { if (button) { button.disabled = false; button.textContent = 'Mover selecionados'; } });
    }

    function loadCrmBoard() {
        var board = document.getElementById('crm-board');
        if (!board) return;
        CRM_SELECTED_IDS = {};
        crmUpdateSelectionToolbar();
        var error = document.getElementById('crm-error');
        if (error) error.classList.add('hidden');
        board.innerHTML = '<div class="col-span-full rounded-2xl bg-white p-8 text-center text-sm font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-300">Carregando licença CRM…</div>';
        crmFetch('crm-addon.php?action=status').then(function (response) {
            var addon = response.data || {};
            renderCrmAddonOffers(addon);
            if (!addon.hasAccess) {
                var licenseEl = document.getElementById('crm-license');
                if (licenseEl) licenseEl.innerHTML = '<strong>CRM adicional não contratado.</strong> O plano de prospecção continua ativo normalmente. Contrate uma licença CRM para liberar o pipeline; isso não altera seu plano principal.';
                board.innerHTML = '<div class="col-span-full rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-10 text-center text-sm font-bold text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">O pipeline ficará disponível após a ativação do CRM adicional.</div>';
                var addForm = document.getElementById('crm-add-form'); if (addForm) addForm.classList.add('hidden');
                var bulk = document.getElementById('crm-bulk-toolbar'); if (bulk) bulk.classList.add('hidden');
                return null;
            }
            return Promise.all([crmFetch('crm.php?action=meta'), crmFetch('crm.php?action=list&limit=200')]);
        }).then(function (responses) {
            if (!responses) return;
            var meta = responses[0].data || {};
            var data = responses[1].data || {};
            var license = data.license || meta.license || {};
            renderCrmLicense(license);
            renderCrmBoard(data.items || [], meta.users || []);
            var error = document.getElementById('crm-error');
            if (error) error.classList.add('hidden');
        }).catch(showCrmError);
    }

    function renderCrmAddonOffers(addon) {
        var box = document.getElementById('crm-addon-box');
        if (!box) return;
        var offers = Array.isArray(addon.offers) ? addon.offers : [];
        if (addon.hasAccess || !offers.length) {
            box.classList.add('hidden');
            return;
        }
        box.classList.remove('hidden');
        var html = '<strong class="block text-base">CRM comercial como módulo adicional</strong><p class="mt-1 text-xs leading-relaxed">Escolha a franquia de usuários. O CRM não consome créditos e não substitui Maps, LinkedIn, CNPJ ou Exportação.</p><div class="mt-4 grid gap-3 md:grid-cols-2">';
        offers.forEach(function (offer) {
            var extra = Number(offer.addonUserPriceCents || 0);
            html += '<div class="rounded-xl border border-amber-200 bg-white p-4 dark:border-amber-800 dark:bg-slate-800"><p class="font-black">' + crmEscape(offer.name) + '</p><p class="mt-1 text-xs">R$ ' + Number(offer.priceMonthly || 0).toFixed(2).replace('.', ',') + '/mês · ' + Number(offer.includedUsers || 3) + ' usuários incluídos</p><p class="mt-1 text-xs">Usuário adicional: ' + crmMoney(extra) + '/mês</p><button type="button" class="crm-addon-request mt-3 rounded-lg bg-amber-600 px-3 py-2 text-xs font-black text-white hover:bg-amber-700" data-plan-id="' + crmEscape(offer.id) + '">Contratar CRM adicional</button></div>';
        });
        box.innerHTML = html + '</div>';
        box.querySelectorAll('.crm-addon-request').forEach(function (button) {
            button.addEventListener('click', function () {
                button.disabled = true;
                button.textContent = 'Enviando…';
                crmFetch('crm-addon.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ planId: Number(button.getAttribute('data-plan-id')) }) })
                    .then(function (response) {
                        var result = response.data || {};
                        var link = result.payment_url ? ' <a class="underline font-black" target="_blank" rel="noopener noreferrer" href="' + crmEscape(result.payment_url) + '">Concluir pagamento</a>' : '';
                        box.innerHTML = '<strong>Solicitação registrada.</strong> ' + crmEscape(response.message || 'Aguarde a confirmação do administrador.') + link;
                    })
                    .catch(function (error) { button.disabled = false; button.textContent = 'Contratar CRM adicional'; alert(error.message || 'Não foi possível solicitar o CRM.'); });
            });
        });
    }

    function renderCrmLicense(license) {
        var element = document.getElementById('crm-license');
        if (!element) return;
        var included = Number(license.includedUsers || 3);
        var current = Number(license.currentUsers || 0);
        var excess = Number(license.excessUsers || 0);
        var suffix = excess > 0 ? ' Há ' + excess + ' usuário(s) excedente(s), estimados em ' + crmMoney(license.estimatedExcessMonthlyCents || 0) + '/mês.' : ' A franquia atual cobre todos os usuários ativos.';
        var source = license.sourceType === 'legacy' ? 'pelo plano legado' : 'por licença adicional';
        element.innerHTML = '<strong>CRM ativo.</strong> ' + included + ' usuário(s) incluído(s); ' + current + ' usuário(s) ativo(s), liberado ' + source + '.' + suffix + ' <span class="block mt-1 text-xs opacity-80">' + crmEscape(license.billingNote || 'Excedentes serão calculados para a próxima cobrança.') + '</span>';
    }

    function renderCrmBoard(items, users) {
        var board = document.getElementById('crm-board');
        if (!board) return;
        var stages = Object.keys(CRM_STAGE_LABELS);
        board.innerHTML = stages.map(function (stage) {
            var stageItems = items.filter(function (item) { return item.stage === stage; });
            var cards = stageItems.map(function (item) {
                return '<article class="min-w-[16rem] rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-600 dark:bg-slate-800" data-crm-id="' + Number(item.id) + '">' +
                        '<div class="flex items-start justify-between gap-2"><label class="flex items-start gap-2"><input type="checkbox" class="crm-select-lead mt-1 h-4 w-4 accent-blue-600" data-crm-id="' + Number(item.id) + '" ' + (CRM_SELECTED_IDS[Number(item.id)] ? 'checked' : '') + '><span><h5 class="font-black text-slate-900 dark:text-slate-100">' + crmEscape(crmLeadDisplayName(item)) + '</h5><p class="mt-1 text-xs font-bold text-slate-500 dark:text-slate-400">' + crmEscape(item.companyName || item.source || 'Lead') + '</p></span></label><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-black uppercase text-slate-500 dark:bg-slate-700 dark:text-slate-300">' + crmEscape(item.source) + '</span></div>' +
                    '<div class="mt-3 space-y-2 text-xs text-slate-600 dark:text-slate-300">' +
                        (item.email ? '<p class="truncate">' + crmEscape(item.email) + '</p>' : '') +
                        (item.phone ? '<p>' + crmEscape(item.phone) + '</p>' : '') +
                        '<p>Próximo contato: ' + crmEscape(crmDate(item.nextContactAt)) + '</p>' +
                    '</div>' +
                    '<select class="crm-stage mt-3 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-xs font-bold dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">' + crmStageOptions(item.stage) + '</select>' +
                    '<select class="crm-assignee mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-xs dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">' + crmAssigneeOptions(users, item.assigneeUserId) + '</select>' +
                    '<input type="datetime-local" class="crm-next-contact mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-xs dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" value="' + (item.nextContactAt ? String(item.nextContactAt).replace(' ', 'T').slice(0, 16) : '') + '">' +
                    '<textarea class="crm-notes mt-2 w-full rounded-lg border border-slate-200 bg-slate-50 px-2 py-2 text-xs dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100" rows="2" placeholder="Anotações">' + crmEscape(item.notes) + '</textarea>' +
                    '<div class="mt-3 flex flex-wrap gap-2"><button type="button" class="crm-activity rounded-lg bg-blue-50 px-3 py-2 text-[11px] font-black text-blue-700 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-200">Registrar atividade</button><button type="button" class="crm-remove rounded-lg bg-rose-50 px-3 py-2 text-[11px] font-black text-rose-700 hover:bg-rose-100 dark:bg-rose-950/30 dark:text-rose-200">Remover</button></div>' +
                '</article>';
            }).join('');
            return '<section class="min-w-[16rem] rounded-[1.5rem] bg-slate-100/80 p-3 dark:bg-slate-900/60"><div class="mb-3 flex items-center justify-between"><h4 class="text-xs font-black uppercase tracking-wider text-slate-600 dark:text-slate-300">' + CRM_STAGE_LABELS[stage] + '</h4><span class="rounded-full bg-white px-2 py-1 text-[10px] font-black text-slate-500 dark:bg-slate-700 dark:text-slate-300">' + stageItems.length + '</span></div><div class="space-y-3">' + (cards || '<p class="rounded-xl border border-dashed border-slate-300 p-4 text-center text-xs text-slate-400 dark:border-slate-700">Nenhum lead</p>') + '</div></section>';
        }).join('');
        board.querySelectorAll('[data-crm-id]').forEach(function (card) {
            var id = Number(card.getAttribute('data-crm-id'));
            var selectBox = card.querySelector('.crm-select-lead');
            if (selectBox) selectBox.addEventListener('change', function () { if (this.checked) CRM_SELECTED_IDS[id] = true; else delete CRM_SELECTED_IDS[id]; crmUpdateSelectionToolbar(); });
            card.querySelector('.crm-stage').addEventListener('change', function () { updateCrmLead(id, { stage: this.value }); });
            card.querySelector('.crm-assignee').addEventListener('change', function () { updateCrmLead(id, { assignee_user_id: Number(this.value) || 0 }); });
            card.querySelector('.crm-next-contact').addEventListener('change', function () { updateCrmLead(id, { next_contact_at: this.value || null }); });
            card.querySelector('.crm-notes').addEventListener('change', function () { updateCrmLead(id, { notes: this.value }); });
            card.querySelector('.crm-activity').addEventListener('click', function () { addCrmActivity(id); });
            card.querySelector('.crm-remove').addEventListener('click', function () { removeCrmLead(id); });
        });
    }

    function updateCrmLead(id, changes) {
        crmFetch('crm.php', { method: 'PATCH', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(Object.assign({ id: id }, changes)) })
            .then(function () { if (typeof showToast === 'function') showToast('Pipeline atualizado.', 'success'); })
            .catch(showCrmError);
    }
    function addCrmActivity(id) {
        var type = window.prompt('Tipo: nota, ligacao, email, reuniao ou tarefa', 'nota');
        if (!type) return;
        type = type.toLowerCase().trim();
        var body = window.prompt('Descreva a atividade:', '');
        if (!body) return;
        crmFetch('crm.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ action: 'activity', crm_lead_id: id, activity_type: type, body: body }) })
            .then(function () { if (typeof showToast === 'function') showToast('Atividade registrada.', 'success'); })
            .catch(showCrmError);
    }
    function removeCrmLead(id) {
        if (!window.confirm('Remover este lead do pipeline? O lead original não será excluído.')) return;
        crmFetch('crm.php?id=' + encodeURIComponent(id), { method: 'DELETE', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ id: id }) })
            .then(function () { if (typeof showToast === 'function') showToast('Lead removido do pipeline.', 'success'); loadCrmBoard(); })
            .catch(showCrmError);
    }

    window.renderCrmTab = renderCrmTab;
})();
