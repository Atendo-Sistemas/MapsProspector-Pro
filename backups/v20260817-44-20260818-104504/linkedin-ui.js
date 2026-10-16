/*
 * Maps Prospector — interface da pesquisa LinkedIn.
 * Este arquivo depende de API_BASE, AppState e setActiveTab do app.js.
 * Integração: adicionar linkedin ao mapa de títulos/loadTab e inserir o botão no index.php.
 */

(function () {
    'use strict';

    var LINKEDIN_CONSENT_VERSION = '2026-08-11';

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function apiUrl(path) {
        var base = typeof API_BASE !== 'undefined' ? API_BASE : 'api/';
        return base + path;
    }

    function showLinkedInError(message) {
        var el = document.getElementById('linkedin-error');
        if (!el) return;
        el.textContent = message || 'Não foi possível concluir a operação.';
        el.classList.remove('hidden');
    }

    function clearLinkedInError() {
        var el = document.getElementById('linkedin-error');
        if (el) el.classList.add('hidden');
    }

    function showLinkedInToast(message) {
        if (typeof showToast === 'function') {
            showToast(message);
        }
    }

    function renderLinkedInTab(contentArea) {
        if (!contentArea) return;

        contentArea.innerHTML = `
            <div class="max-w-6xl mx-auto space-y-8 pb-20">
                <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-600">Prospecção B2B</p>
                        <h2 class="text-3xl font-black text-slate-900 dark:text-slate-100 mt-2">Pesquisa LinkedIn</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-2 max-w-2xl">
                            Encontre perfis profissionais por empresa, cargo e localidade.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-2 rounded-full bg-amber-50 text-amber-700 px-4 py-2 text-xs font-bold border border-amber-100">
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        Uso responsável obrigatório
                    </span>
                </div>

                <div id="linkedin-error" class="hidden rounded-2xl border border-red-200 bg-red-50 text-red-700 px-5 py-4 text-sm font-semibold"></div>

                <section class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-700 shadow-sm p-6 md:p-8">
                    <form id="linkedin-search-form" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <label class="block">
                                <span class="block text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Empresa</span>
                                <input id="linkedin-company" maxlength="128" type="text" placeholder="Ex.: Atendo Sistemas (opcional)" class="w-full rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                            </label>
                            <label class="block">
                                <span class="block text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Localidade</span>
                                <input id="linkedin-location" required maxlength="128" type="text" placeholder="Ex.: São Paulo, SP" class="w-full rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                            </label>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-[1fr_160px] gap-5">
                            <label class="block">
                                <span class="block text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Cargos ou funções</span>
                                <input id="linkedin-job-titles" required maxlength="500" type="text" placeholder="Ex.: Diretor, CEO, Gerente Comercial" class="w-full rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                <span class="block text-[11px] text-slate-400 mt-2">Separe vários cargos por vírgula.</span>
                            </label>
                            <label class="block">
                                <span class="block text-xs font-black uppercase tracking-wider text-slate-500 mb-2">Máximo</span>
                                <select id="linkedin-limit" class="w-full rounded-2xl border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 px-4 py-3 text-sm outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="10">10 perfis</option>
                                    <option value="25">25 perfis</option>
                                    <option value="50">50 perfis</option>
                                    <option value="100">100 perfis</option>
                                    <option value="200" selected>200 perfis</option>
                                </select>
                            </label>
                        </div>
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pt-2">
                            <p class="text-xs text-slate-500 dark:text-slate-400">A primeira pesquisa exibirá os termos de uso antes de iniciar.</p>
                            <button id="linkedin-search-submit" type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 font-bold text-sm transition-colors">
                                Pesquisar leads
                            </button>
                        </div>
                    </form>
                </section>

                <section id="linkedin-results" class="hidden space-y-4"></section>

                <section class="bg-white dark:bg-slate-800 rounded-[2rem] border border-slate-200 dark:border-slate-700 shadow-sm p-6 md:p-8">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                        <div>
                            <p class="text-xs font-black uppercase tracking-[0.2em] text-blue-600">Consultas anteriores</p>
                            <h3 class="text-xl font-black text-slate-900 dark:text-slate-100 mt-1">Histórico de pesquisas</h3>
                            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Acompanhe as últimas pesquisas realizadas por sua equipe.</p>
                        </div>
                        <button id="linkedin-history-refresh" type="button" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 dark:border-slate-600 px-4 py-2.5 text-sm font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">Atualizar</button>
                    </div>
                    <div id="linkedin-history" class="space-y-3" aria-live="polite">
                        <div class="text-sm text-slate-500 py-4">Carregando histórico...</div>
                    </div>
                </section>
            </div>
        `;

        setupLinkedInEvents();
        loadLinkedInHistory();
        var refreshHistoryButton = document.getElementById('linkedin-history-refresh');
        if (refreshHistoryButton) {
            refreshHistoryButton.addEventListener('click', loadLinkedInHistory);
        }
    }

    async function getConsent() {
        var response = await fetch(apiUrl('linkedin-consent.php'), { credentials: 'include', cache: 'no-store' });
        var data = await response.json().catch(function () { return { success: false, error: 'Resposta inválida do servidor.' }; });
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Não foi possível verificar os termos de uso.');
        }
        return data.data || {};
    }

    function showConsentModal(onAccepted) {
        var old = document.getElementById('linkedin-consent-modal');
        if (old) old.remove();

        var overlay = document.createElement('div');
        overlay.id = 'linkedin-consent-modal';
        overlay.className = 'fixed inset-0 z-[10000] bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-5';
        overlay.innerHTML = `
            <div class="w-full max-w-xl rounded-[2rem] bg-white dark:bg-slate-800 shadow-2xl p-7 md:p-9">
                <div class="flex items-start gap-4">
                    <div class="w-11 h-11 rounded-2xl bg-amber-100 text-amber-700 flex items-center justify-center font-black">!</div>
                    <div>
                        <h3 class="text-xl font-black text-slate-900 dark:text-slate-100">Antes da primeira pesquisa</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-300 mt-1">Leia e confirme o uso responsável dos resultados.</p>
                    </div>
                </div>
                <div class="mt-6 space-y-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                    <p>Você confirma que utilizará esta funcionalidade para finalidades profissionais legítimas e de acordo com as leis aplicáveis, regras de privacidade e regras das fontes consultadas.</p>
                    <p>Você não deverá usar os resultados para spam, assédio, discriminação, fraude ou coleta além do necessário. Deve revisar as informações antes de qualquer contato.</p>
                    <p>A disponibilidade e a exatidão dos dados dependem da fonte. Este aceite não autoriza nem contorna regras de terceiros.</p>
                </div>
                <label class="flex items-start gap-3 mt-6 text-sm font-semibold text-slate-700 dark:text-slate-200 cursor-pointer">
                    <input id="linkedin-consent-checkbox" type="checkbox" class="mt-1 w-4 h-4 accent-blue-600">
                    <span>Li e aceito as condições de uso responsável da pesquisa LinkedIn.</span>
                </label>
                <div class="flex justify-end gap-3 mt-7">
                    <button type="button" id="linkedin-consent-cancel" class="rounded-2xl px-5 py-3 text-sm font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700">Cancelar</button>
                    <button type="button" id="linkedin-consent-accept" disabled class="rounded-2xl px-5 py-3 text-sm font-bold bg-blue-600 text-white disabled:opacity-40 disabled:cursor-not-allowed">Aceitar e continuar</button>
                </div>
            </div>
        `;
        document.body.appendChild(overlay);

        var checkbox = document.getElementById('linkedin-consent-checkbox');
        var accept = document.getElementById('linkedin-consent-accept');
        checkbox.addEventListener('change', function () { accept.disabled = !checkbox.checked; });
        document.getElementById('linkedin-consent-cancel').onclick = function () { overlay.remove(); };
        accept.onclick = async function () {
            accept.disabled = true;
            accept.textContent = 'Registrando...';
            try {
                var response = await fetch(apiUrl('linkedin-consent.php'), {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ accepted: true, version: LINKEDIN_CONSENT_VERSION })
                });
                var data = await response.json().catch(function () { return { success: false }; });
                if (!response.ok || !data.success) throw new Error(data.error || 'Não foi possível registrar o aceite.');
                overlay.remove();
                onAccepted();
            } catch (error) {
                accept.disabled = false;
                accept.textContent = 'Aceitar e continuar';
                showLinkedInError(error.message);
            }
        };
    }

    async function ensureLinkedInConsent() {
        var data = await getConsent();
        if (data.accepted) return true;
        return new Promise(function (resolve) { showConsentModal(function () { resolve(true); }); });
    }

    function parseJobTitles(value) {
        return value.split(/[,;\n]+/).map(function (item) { return item.trim(); }).filter(Boolean).slice(0, 10);
    }

    function renderLinkedInResults(leads, meta) {
        var section = document.getElementById('linkedin-results');
        if (!section) return;
        section.classList.remove('hidden');
        if (!leads.length) {
            section.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 p-10 text-center text-sm font-semibold text-slate-500">Nenhum perfil foi retornado para os filtros informados.</div>';
            return;
        }
        section.innerHTML = `
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-slate-100">Leads encontrados</h3>
                    <p class="text-xs text-slate-500 mt-1">${leads.length} perfil(is) nesta pesquisa</p>
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                ${leads.map(function (lead) {
                    return `<article class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 shadow-sm">
                        <div class="flex gap-4">
                            ${lead.photo_url ? `<img src="${escapeHtml(lead.photo_url)}" alt="" class="w-12 h-12 rounded-xl object-cover bg-slate-100">` : '<div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-black">in</div>'}
                            <div class="min-w-0 flex-1">
                                <h4 class="font-black text-slate-900 dark:text-slate-100 truncate">${escapeHtml(lead.full_name || 'Perfil sem nome')}</h4>
                                <p class="text-sm font-semibold text-blue-700 truncate">${escapeHtml(lead.job_title || 'Cargo não informado')}</p>
                                <p class="text-xs text-slate-500 mt-1">${escapeHtml(lead.company_name || '')}${lead.location ? ' · ' + escapeHtml(lead.location) : ''}</p>
                            </div>
                        </div>
                        <a href="${escapeHtml(lead.profile_url)}" target="_blank" rel="noopener noreferrer" class="inline-flex mt-4 text-xs font-bold text-blue-700 hover:underline">Abrir perfil no LinkedIn ↗</a>
                    </article>`;
                }).join('')}
            </div>
        `;
    }

    function formatHistoryDate(value) {
        if (!value) return 'Data não informada';
        var normalized = String(value).replace(' ', 'T');
        var date = new Date(normalized);
        if (isNaN(date.getTime())) return String(value);
        try {
            return new Intl.DateTimeFormat('pt-BR', {
                dateStyle: 'short',
                timeStyle: 'short'
            }).format(date);
        } catch (error) {
            return date.toLocaleString('pt-BR');
        }
    }

    function renderLinkedInHistory(items) {
        var container = document.getElementById('linkedin-history');
        if (!container) return;

        if (!items.length) {
            container.innerHTML = '<div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 px-5 py-8 text-center text-sm font-semibold text-slate-500">Nenhuma pesquisa LinkedIn foi realizada por sua equipe ainda.</div>';
            return;
        }

        container.innerHTML = items.map(function (item) {
            var filters = [];
            if (item.company) filters.push(item.company);
            if (Array.isArray(item.jobTitles) && item.jobTitles.length) filters.push(item.jobTitles.join(', '));
            if (item.location) filters.push(item.location);

            return '<article class="rounded-2xl border border-slate-200 dark:border-slate-700 px-4 py-4 md:px-5 bg-slate-50/60 dark:bg-slate-900/20">'
                + '<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">'
                + '  <div class="min-w-0">'
                + '    <h4 class="font-black text-slate-900 dark:text-slate-100 truncate">' + escapeHtml(item.title || 'Pesquisa LinkedIn') + '</h4>'
                + '    <p class="text-sm text-slate-600 dark:text-slate-300 mt-1 break-words">' + escapeHtml(filters.join(' · ') || 'Filtros não informados') + '</p>'
                + '    <p class="text-xs text-slate-500 mt-1">' + escapeHtml(formatHistoryDate(item.createdAt)) + '</p>'
                + '  </div>'
                + '  <div class="flex items-center gap-3 shrink-0">'
                + '    <span class="text-sm font-black text-slate-700 dark:text-slate-200">' + Number(item.leadsFound || 0) + ' perfil(is)</span>'
                + '    <button type="button" data-linkedin-run-id="' + escapeHtml(item.id) + '" class="linkedin-history-view rounded-xl bg-blue-600 hover:bg-blue-700 text-white px-3.5 py-2 text-xs font-bold transition-colors">Ver perfis</button>'
                + '  </div>'
                + '</div>'
                + '</article>';
        }).join('');

        Array.prototype.forEach.call(container.querySelectorAll('.linkedin-history-view'), function (button) {
            button.addEventListener('click', function () {
                loadLinkedInHistoryResults(button.getAttribute('data-linkedin-run-id'), button);
            });
        });
    }

    async function loadLinkedInHistoryResults(runId, button) {
        if (!runId) return;
        clearLinkedInError();
        var originalLabel = button ? button.textContent : '';
        if (button) {
            button.disabled = true;
            button.textContent = 'Carregando...';
        }

        try {
            var response = await fetch(apiUrl('linkedin-history.php?run_id=' + encodeURIComponent(runId)), {
                credentials: 'include',
                cache: 'no-store'
            });
            var data = await response.json().catch(function () {
                return { success: false, error: 'Resposta inválida do servidor.' };
            });
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Não foi possível carregar os perfis desta pesquisa.');
            }

            var resultData = data.data || {};
            var leads = resultData.leads || [];
            renderLinkedInResults(leads, resultData);
            var resultSection = document.getElementById('linkedin-results');
            if (resultSection && typeof resultSection.scrollIntoView === 'function') {
                resultSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
            showLinkedInToast('Perfis da consulta carregados.');
        } catch (error) {
            showLinkedInError(error.message || 'Não foi possível carregar os perfis desta pesquisa.');
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalLabel || 'Ver perfis';
            }
        }
    }

    async function loadLinkedInHistory() {
        var container = document.getElementById('linkedin-history');
        var button = document.getElementById('linkedin-history-refresh');
        if (!container) return;

        if (button) {
            button.disabled = true;
            button.textContent = 'Atualizando...';
        }

        try {
            var response = await fetch(apiUrl('linkedin-history.php?limit=30'), {
                credentials: 'include',
                cache: 'no-store'
            });
            var data = await response.json().catch(function () {
                return { success: false, error: 'Resposta inválida do servidor.' };
            });
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Não foi possível carregar o histórico.');
            }
            renderLinkedInHistory((data.data && data.data.items) || []);
        } catch (error) {
            container.innerHTML = '<div class="rounded-2xl border border-red-200 bg-red-50 text-red-700 px-5 py-4 text-sm font-semibold">Não foi possível carregar o histórico de pesquisas. Tente atualizar novamente.</div>';
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = 'Atualizar';
            }
        }
    }

    function setSearching(isSearching, label) {
        var button = document.getElementById('linkedin-search-submit');
        if (!button) return;
        button.disabled = isSearching;
        button.textContent = isSearching ? (label || 'Pesquisando...') : 'Pesquisar leads';
    }

    function renderLinkedInLoading(limit) {
        var section = document.getElementById('linkedin-results');
        if (!section) return;
        section.classList.remove('hidden');
        section.innerHTML = '<div class="rounded-2xl border border-blue-100 bg-blue-50 px-5 py-6 text-center text-sm font-semibold text-blue-800">A busca foi iniciada. Estamos preparando até ' + escapeHtml(limit || 0) + ' perfis; esta tela atualizará automaticamente quando os resultados estiverem prontos.</div>';
    }

    function wait(milliseconds) {
        return new Promise(function (resolve) { window.setTimeout(resolve, milliseconds); });
    }

    async function waitForLinkedInSearch(runId, pollAfterMs, requestedLimit) {
        var deadline = Date.now() + 180000;
        var delay = Math.max(1200, Math.min(5000, parseInt(pollAfterMs, 10) || 2000));
        renderLinkedInLoading(requestedLimit);

        while (Date.now() < deadline) {
            await wait(delay);
            var response = await fetch(apiUrl('linkedin-search-status.php?run_id=' + encodeURIComponent(runId)), {
                credentials: 'include',
                cache: 'no-store'
            });
            var data = await response.json().catch(function () {
                return { success: false, error: 'Resposta inválida ao atualizar a pesquisa LinkedIn.' };
            });
            if (!response.ok || !data.success) {
                throw new Error(data.error || 'Não foi possível atualizar a pesquisa LinkedIn.');
            }
            var result = data.data || {};
            if (!result.pending) {
                return result;
            }
            delay = Math.max(1200, Math.min(5000, parseInt(result.pollAfterMs, 10) || delay));
        }

        throw new Error('A pesquisa ainda está em processamento. Aguarde alguns instantes e atualize o histórico para consultar os perfis quando ela for concluída.');
    }

    function setupLinkedInEvents() {
        var form = document.getElementById('linkedin-search-form');
        if (!form) return;
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            clearLinkedInError();
            setSearching(true);
            try {
                await ensureLinkedInConsent();
                var company = document.getElementById('linkedin-company').value.trim();
                var location = document.getElementById('linkedin-location').value.trim();
                var jobTitles = parseJobTitles(document.getElementById('linkedin-job-titles').value);
                var limit = parseInt(document.getElementById('linkedin-limit').value, 10) || 10;
                if (!location || !jobTitles.length) throw new Error('Informe a localidade e pelo menos um cargo.');

                var response = await fetch(apiUrl('linkedin-search.php'), {
                    method: 'POST',
                    credentials: 'include',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ company: company, location: location, jobTitles: jobTitles, limit: limit })
                });
                var data = await response.json().catch(function () { return { success: false, error: 'Resposta inválida do servidor.' }; });
                if (!response.ok || !data.success) throw new Error(data.error || 'Erro ao pesquisar leads LinkedIn.');
                var resultData = data.data || {};
                if (resultData.pending) {
                    setSearching(true, 'Buscando perfis...');
                    resultData = await waitForLinkedInSearch(resultData.runId, resultData.pollAfterMs, resultData.limit || limit);
                }
                renderLinkedInResults(resultData.leads || [], resultData);
                loadLinkedInHistory();
                showLinkedInToast('Pesquisa LinkedIn concluída.');
            } catch (error) {
                showLinkedInError(error.message);
            } finally {
                setSearching(false);
            }
        });
    }

    window.renderLinkedInTab = renderLinkedInTab;
    window.setupLinkedInEvents = setupLinkedInEvents;
}());
