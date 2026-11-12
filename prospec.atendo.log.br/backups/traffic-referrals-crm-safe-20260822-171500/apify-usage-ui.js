/**
 * Monitoramento administrativo de contas de pesquisa.
 * O navegador chama somente o endpoint interno; nenhum token externo é exposto.
 */
(function () {
    'use strict';

    var STATUS = {
        ok: { label: 'Dentro do limite', tone: 'emerald' },
        warning: { label: 'Atenção', tone: 'amber' },
        critical: { label: 'Limite próximo', tone: 'rose' },
        limit_reached: { label: 'Limite atingido', tone: 'rose' },
        error: { label: 'Erro na consulta', tone: 'slate' },
        unknown: { label: 'Sem informação', tone: 'slate' },
        disabled: { label: 'Desativada', tone: 'slate' }
    };

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function money(value) {
        if (value === null || value === undefined || value === '') return '—';
        return 'US$ ' + Number(value).toFixed(2);
    }

    function percent(value) {
        if (value === null || value === undefined || value === '') return '—';
        return Number(value).toFixed(1) + '%';
    }

    function quantity(value) {
        if (value === null || value === undefined || value === '') return '—';
        var numeric = Number(value);
        if (!Number.isFinite(numeric)) return '—';
        return numeric.toLocaleString('pt-BR', { maximumFractionDigits: 4 });
    }

    function serviceLabel(value) {
        var labels = {
            PAID_ACTORS_PER_EVENT: 'Execuções de atores',
            DATASET_READS: 'Leituras de dataset',
            DATA_TRANSFER_EXTERNAL_GBYTES: 'Transferência externa',
            DATASET_TIMED_STORAGE_GBYTE_HOURS: 'Armazenamento de dataset',
            KEY_VALUE_STORE_LISTS: 'Listas de armazenamento',
            KEY_VALUE_STORE_READS: 'Leituras de armazenamento',
            KEY_VALUE_STORE_TIMED_STORAGE_GBYTE_HOURS: 'Armazenamento de chave/valor'
        };
        return labels[value] || String(value || '').replace(/_/g, ' ').toLowerCase();
    }

    function dateLabel(value) {
        if (!value) return '—';
        var parts = String(value).split('-');
        return parts.length === 3 ? parts[2] + '/' + parts[1] : String(value);
    }

    function metricLabel(value) {
        var labels = {
            monthlyActorComputeUnits: 'Unidades de computação',
            monthlyExternalDataTransferGbytes: 'Transferência externa (GB)',
            monthlyProxySerps: 'Consultas de proxy',
            monthlyUnblockerUnits: 'Unidades de desbloqueio',
            monthlyResidentialProxyGbytes: 'Proxy residencial (GB)',
            actorMemoryGbytes: 'Memória de atores (GB)',
            actorCount: 'Atores',
            actorTaskCount: 'Tarefas de atores',
            scheduleCount: 'Agendamentos',
            concurrentActorJobs: 'Execuções simultâneas',
            teamAccountSeatCount: 'Assentos da equipe',
            dataRetentionDays: 'Retenção de dados'
        };
        return labels[value] || value;
    }

    function metricUnit(value) {
        if (/Gbytes$/i.test(value)) return ' GB';
        if (value === 'dataRetentionDays') return ' dias';
        return '';
    }

    function serviceRows(account) {
        var services = Array.isArray(account.monthlyServices) ? account.monthlyServices : [];
        if (!services.length) return '<p class="text-xs text-slate-500">Nenhum serviço faturado no ciclo atual.</p>';
        return '<div class="space-y-2">' + services.slice(0, 6).map(function (service) {
            return '<div class="flex items-center justify-between gap-3 text-xs"><span class="truncate text-slate-600" title="' + escapeHtml(service.key) + '">' + escapeHtml(serviceLabel(service.key)) + '</span><span class="shrink-0 font-semibold text-slate-800">' + money(service.amountUsd) + '</span></div>';
        }).join('') + '</div>';
    }

    function dailyRows(account) {
        var days = Array.isArray(account.dailyUsages) ? account.dailyUsages : [];
        if (!days.length) return '<p class="text-xs text-slate-500">Ainda não há detalhamento diário.</p>';
        return '<div class="grid grid-cols-2 gap-2 sm:grid-cols-3">' + days.slice(-6).map(function (day) {
            return '<div class="rounded-xl bg-slate-50 px-3 py-2"><p class="text-[11px] text-slate-500">' + escapeHtml(dateLabel(day.date)) + '</p><p class="mt-1 text-sm font-bold text-slate-800">' + money(day.totalUsageCreditsUsd) + '</p></div>';
        }).join('') + '</div>';
    }

    function technicalLimitRows(account) {
        var metrics = account.limitMetrics || {};
        var rows = Object.keys(metrics).filter(function (key) { return key !== 'monthlyUsageUsd'; }).map(function (key) {
            var metric = metrics[key] || {};
            return '<div class="flex items-center justify-between gap-3 border-b border-slate-100 py-2 last:border-0"><span class="text-xs text-slate-600">' + escapeHtml(metricLabel(key)) + '</span><span class="text-right text-xs font-semibold text-slate-800">' + quantity(metric.current) + ' / ' + quantity(metric.max) + escapeHtml(metricUnit(key)) + '</span></div>';
        });
        return rows.length ? rows.join('') : '<p class="text-xs text-slate-500">Limites técnicos não informados.</p>';
    }

    function statusBadge(status) {
        var item = STATUS[status] || STATUS.unknown;
        var colors = {
            emerald: 'bg-emerald-100 text-emerald-700',
            amber: 'bg-amber-100 text-amber-700',
            rose: 'bg-rose-100 text-rose-700',
            slate: 'bg-slate-100 text-slate-600'
        };
        return '<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ' + colors[item.tone] + '">' + escapeHtml(item.label) + '</span>';
    }

    function providerBadge(status) {
        var labels = {
            active: ['Ativa e respondendo', 'bg-emerald-100 text-emerald-700'],
            limit_reached: ['Limite atingido', 'bg-rose-100 text-rose-700'],
            error: ['Falha na consulta', 'bg-rose-100 text-rose-700'],
            pending: ['Verificando', 'bg-slate-100 text-slate-600'],
            disabled: ['Desativada', 'bg-slate-100 text-slate-600']
        };
        var item = labels[status] || labels.pending;
        return '<span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ' + item[1] + '">' + escapeHtml(item[0]) + '</span>';
    }

    function roleBadge(role) {
        if (role === 'disabled') {
            return '<span class="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">Desativada</span>';
        }
        if (role === 'reserve') {
            return '<span class="inline-flex rounded-full bg-sky-100 px-2.5 py-1 text-xs font-semibold text-sky-700">Reserva de emergência</span>';
        }
        return '<span class="inline-flex rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">Produção</span>';
    }

    function accountCard(account) {
        var usage = Number(account.usagePercent);
        var hasUsage = Number.isFinite(usage);
        var width = hasUsage ? Math.max(0, Math.min(100, usage)) : 0;
        var progressTone = account.role === 'disabled'
            ? 'bg-slate-300'
            : account.status === 'ok' ? 'bg-emerald-500' : account.status === 'warning' ? 'bg-amber-500' : 'bg-rose-500';
        var error = account.error ? '<p class="mt-3 text-xs text-rose-600">' + escapeHtml(account.error) + '</p>' : '';
        var role = account.role === 'reserve' || account.role === 'disabled' ? account.role : 'production';
        var roleSelector = '<label class="mt-5 flex flex-wrap items-center gap-2 text-xs font-semibold text-slate-600">' +
            '<span>Função operacional</span>' +
            '<select data-apify-role-id="' + escapeHtml(account.id) + '" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 outline-none transition focus:border-indigo-500 focus:ring-2 focus:ring-indigo-100" aria-label="Função da conta ' + escapeHtml(account.id) + '">' +
                '<option value="production"' + (role === 'production' ? ' selected' : '') + '>Produção</option>' +
                '<option value="reserve"' + (role === 'reserve' ? ' selected' : '') + '>Reserva</option>' +
                '<option value="disabled"' + (role === 'disabled' ? ' selected' : '') + '>Desativada</option>' +
            '</select>' +
            '<span data-apify-role-status="' + escapeHtml(account.id) + '" class="text-[11px] text-slate-400"></span>' +
        '</label>';

        return '<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">' +
            '<div class="flex flex-wrap items-start justify-between gap-3">' +
                '<div><p class="text-sm font-semibold text-slate-900">' + escapeHtml(account.name || ('Conta de pesquisa #' + account.id)) + '</p>' +
                '<p class="mt-1 text-xs text-slate-500">Ciclo: ' + escapeHtml(account.usageCycle || 'não informado') + '</p></div>' +
                '<div class="flex flex-wrap justify-end gap-2">' + roleBadge(account.role) + providerBadge(account.providerStatus) + statusBadge(account.status) + '</div>' +
            '</div>' +
            '<div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">' +
                '<div><p class="text-xs text-slate-500">Uso mensal</p><p class="mt-1 font-bold text-slate-900">' + money(account.monthlyUsageUsd) + '</p></div>' +
                '<div><p class="text-xs text-slate-500">Limite</p><p class="mt-1 font-bold text-slate-900">' + money(account.monthlyLimitUsd) + '</p></div>' +
                '<div><p class="text-xs text-slate-500">Saldo</p><p class="mt-1 font-bold text-emerald-700">' + money(account.monthlyBalanceUsd) + '</p></div>' +
                '<div><p class="text-xs text-slate-500">Rotação</p><p class="mt-1 font-bold text-slate-900">' + escapeHtml(account.tokensUsedByRotation) + ' usos</p></div>' +
            '</div>' +
            '<div class="mt-4"><div class="mb-1 flex justify-between text-xs text-slate-500"><span>Percentual consumido</span><span>' + percent(account.usagePercent) + '</span></div>' +
            '<div class="h-2 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full ' + progressTone + '" style="width:' + width + '%"></div></div></div>' +
            '<div class="mt-5 grid gap-4 md:grid-cols-2">' +
                '<div class="rounded-xl bg-slate-50 p-4"><div class="mb-3 flex items-center justify-between"><h4 class="text-xs font-bold uppercase tracking-wide text-slate-500">Serviços do ciclo</h4><span class="text-[11px] text-slate-400">USD</span></div>' + serviceRows(account) + '</div>' +
                '<div class="rounded-xl bg-slate-50 p-4"><h4 class="mb-3 text-xs font-bold uppercase tracking-wide text-slate-500">Consumo diário</h4>' + dailyRows(account) + '</div>' +
            '</div>' +
            '<details class="mt-4 rounded-xl border border-slate-200 bg-white"><summary class="cursor-pointer px-4 py-3 text-xs font-bold uppercase tracking-wide text-slate-600">Limites operacionais</summary><div class="px-4 pb-3">' + technicalLimitRows(account) + '</div></details>' +
            roleSelector +
            error +
            '<p class="mt-4 text-[11px] text-slate-400">Atualizado em ' + escapeHtml(account.checkedAt || '—') + '</p>' +
        '</article>';
    }

    function renderLoading(container) {
        container.innerHTML = '<div class="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500">Consultando uso e saldo das contas de pesquisa…</div>';
    }

    function loadApifyUsage(container, refresh) {
        renderLoading(container);
        return fetch('api/apify-usage.php', { credentials: 'include', cache: 'no-store' })
            .then(function (response) {
                return response.text().then(function (text) {
                    var body = null;
                    try { body = text ? JSON.parse(text) : null; } catch (parseError) { body = null; }
                    if (!response.ok || !body || !body.success) {
                        var detail = body && body.error ? body.error : ('O servidor retornou HTTP ' + response.status + '.');
                        throw new Error(detail);
                    }
                    return body.data;
                });
            })
            .then(function (data) {
                var accounts = Array.isArray(data.accounts) ? data.accounts : [];
                var summary = data.summary || {};
                if (!accounts.length) {
                    container.innerHTML = summaryCards(summary) + '<div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-sm text-slate-600">Nenhuma conta ativa foi encontrada.</div>';
                    return data;
                }
                container.innerHTML = summaryCards(summary) + '<div class="grid gap-4 lg:grid-cols-2">' + accounts.map(accountCard).join('') + '</div>';
                bindRoleSelectors(container, refresh);
                return data;
            })
            .catch(function (error) {
                container.innerHTML = '<div class="rounded-2xl border border-rose-200 bg-rose-50 p-6 text-sm text-rose-700">' + escapeHtml(error.message) + '</div>';
                throw error;
            });
    }

    function summaryCards(summary) {
        var items = [
            ['Contas configuradas', summary.configuredTotal || summary.configuredActive || 0, 'text-slate-900'],
            ['Ativas e respondendo', summary.operational || 0, 'text-emerald-700'],
            ['Próximas do limite', summary.attention || 0, 'text-amber-700'],
            ['Desativadas / limite / erro', (summary.disabled || 0) + (summary.limitReached || 0) + (summary.errors || 0), 'text-rose-700'],
            ['Uso mensal agregado', money(summary.monthlyUsageUsd), 'text-slate-900'],
            ['Limite mensal agregado', money(summary.monthlyLimitUsd), 'text-indigo-700'],
            ['Saldo mensal agregado', money(summary.monthlyBalanceUsd), 'text-emerald-700']
        ];
        return '<div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-6">' + items.map(function (item) {
            return '<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"><p class="text-xs font-medium text-slate-500">' + escapeHtml(item[0]) + '</p><p class="mt-2 text-2xl font-bold ' + item[2] + '">' + escapeHtml(item[1]) + '</p></div>';
        }).join('') + '</div>';
    }

    function bindRoleSelectors(container, refresh) {
        Array.prototype.forEach.call(container.querySelectorAll('[data-apify-role-id]'), function (select) {
            select.addEventListener('change', function () {
                var id = Number(select.getAttribute('data-apify-role-id'));
                var role = select.value;
                var status = container.querySelector('[data-apify-role-status="' + id + '"]');
                var previous = select.getAttribute('data-previous-role') || role;
                select.disabled = true;
                if (status) status.textContent = 'Salvando…';

                fetch('api/apify-role.php', {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, role: role })
                }).then(function (response) {
                    return response.json().then(function (body) {
                        if (!response.ok || !body.success) throw new Error(body.error || 'Não foi possível salvar a função.');
                        return body;
                    });
                }).then(function () {
                    select.setAttribute('data-previous-role', role);
                    if (status) status.textContent = 'Salvo';
                    return refresh();
                }).catch(function (error) {
                    select.value = previous;
                    if (status) status.textContent = error.message;
                }).finally(function () {
                    select.disabled = false;
                });
            });
            select.setAttribute('data-previous-role', select.value);
        });
    }

    window.renderApifyUsagePanel = function (container) {
        if (!container) return Promise.reject(new Error('Área do monitoramento não encontrada.'));
        container.innerHTML = '<div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div><h2 class="text-xl font-bold text-slate-900">Uso das contas de pesquisa</h2><p class="mt-1 text-sm text-slate-500">Acompanhe uso mensal, limite, saldo e a função operacional de cada conta.</p></div><button type="button" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-700" data-apify-refresh>Atualizar consulta</button></div><div data-apify-usage-list></div>';
        var list = container.querySelector('[data-apify-usage-list]');
        var button = container.querySelector('[data-apify-refresh]');
        var refresh = function () { return loadApifyUsage(list, refresh).catch(function () { return null; }); };
        button.addEventListener('click', refresh);
        return refresh();
    };
}());
