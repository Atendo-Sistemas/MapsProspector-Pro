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

    function renderLinkedInSessionStatus(data) {
        var container = document.getElementById('linkedin-session-status');
        if (!container) return;
        var active = !!(data && data.active);
        var canManage = !!(data && data.canManage);
        var stateLabel = active ? 'Sessão temporária ativa' : 'Sessão temporária indisponível';
        var tone = active
            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
            : 'border-amber-200 bg-amber-50 text-amber-800';
        var detail = data && data.notice
            ? escapeHtml(data.notice)
            : 'Não foi possível consultar a sessão temporária agora. A pesquisa poderá usar a contingência automática.';
        var button = canManage
            ? '<button id="linkedin-session-help" type="button" class="shrink-0 rounded-xl border border-current/25 bg-white/70 px-3 py-2 text-xs font-black transition-colors hover:bg-white">Orientações para ativar</button>'
            : '';
        container.innerHTML = '<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 rounded-2xl border px-4 py-3 ' + tone + '">'
            + '<div class="flex items-start gap-3"><span class="mt-1 w-2.5 h-2.5 shrink-0 rounded-full ' + (active ? 'bg-emerald-500' : 'bg-amber-500') + '"></span>'
            + '<div><p class="text-xs font-black uppercase tracking-wider">' + stateLabel + '</p><p class="mt-1 text-xs font-medium leading-relaxed">' + detail + '</p></div></div>'
            + button + '</div>';
        var helpButton = document.getElementById('linkedin-session-help');
        if (helpButton) helpButton.addEventListener('click', showLinkedInSessionHelp);
    }

    function showLinkedInSessionHelp() {
        var old = document.getElementById('linkedin-session-help-modal');
        if (old) old.remove();
        var overlay = document.createElement('div');
        overlay.id = 'linkedin-session-help-modal';
        overlay.className = 'fixed inset-0 z-[10000] bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-5';
        overlay.innerHTML = '<div class="w-full max-w-lg rounded-[2rem] bg-white dark:bg-slate-800 shadow-2xl p-7 md:p-9">'
            + '<div class="flex items-start justify-between gap-4"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600">Sessão temporária</p><h3 class="mt-2 text-xl font-black text-slate-900 dark:text-slate-100">Ativar pesquisa autenticada</h3></div>'
            + '<button type="button" data-close-linkedin-session class="rounded-xl p-2 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Fechar">×</button></div>'
            + '<div class="mt-5 space-y-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300"><p>O acesso é feito manualmente no navegador protegido da VPS por um administrador autorizado. Nenhuma senha é salva no painel ou no banco de dados.</p>'
            + '<ol class="list-decimal space-y-2 pl-5"><li>Acesse o navegador da VPS em uma sessão administrativa autorizada.</li><li>Entre no LinkedIn normalmente e confirme que a página inicial foi carregada.</li><li>Retorne ao painel: a sessão fica disponível apenas temporariamente e expira automaticamente.</li></ol>'
            + '<p class="rounded-xl bg-slate-50 dark:bg-slate-700/60 px-4 py-3 text-xs font-semibold">Enquanto não houver sessão válida, o sistema pode acionar automaticamente a fonte de contingência sem exibir credenciais ou detalhes internos.</p></div>'
            + '<div class="mt-6 flex justify-end"><button type="button" data-close-linkedin-session class="rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-black text-white hover:bg-blue-700">Entendi</button></div></div>';
        overlay.querySelectorAll('[data-close-linkedin-session]').forEach(function (button) {
            button.addEventListener('click', function () { overlay.remove(); });
        });
        overlay.addEventListener('click', function (event) { if (event.target === overlay) overlay.remove(); });
        document.body.appendChild(overlay);
    }

    async function loadLinkedInSessionStatus() {
        try {
            var response = await fetch(apiUrl('linkedin-session.php'), { credentials: 'include', cache: 'no-store' });
            var data = await response.json().catch(function () { return { success: false }; });
            renderLinkedInSessionStatus(response.ok && data.success ? (data.data || {}) : {});
        } catch (error) {
            renderLinkedInSessionStatus({});
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

                    <div id="linkedin-session-status" aria-live="polite">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold text-slate-500">Verificando a sessão temporária LinkedIn...</div>
                    </div>

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
        loadLinkedInSessionStatus();
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

    function normalizeLinkedInLocation(value) {
        var location = String(value || '').replace(/\s+/g, ' ').trim();
        var statePattern = 'AC|AL|AP|AM|BA|CE|DF|ES|GO|MA|MT|MS|MG|PA|PB|PR|PE|PI|RJ|RN|RS|RO|RR|SC|SP|SE|TO';
        if (!location) return { value: '', error: 'Informe a cidade ou região da pesquisa LinkedIn.' };
        if (new RegExp('^(' + statePattern + ')\\.?$', 'i').test(location)) {
            return { value: '', error: 'Informe a cidade ou região junto à UF. Exemplo: Campinas e Região, SP.' };
        }
        var match = location.match(new RegExp('^(.*?)(?:,|\\s)+(' + statePattern + ')\\.?$', 'i'));
        if (!match) return { value: location, changed: false };
        var place = String(match[1] || '').replace(/[,\s]+$/, '').trim();
        var state = String(match[2] || '').toUpperCase();
        if (!place) return { value: '', error: 'Informe a cidade ou região junto à UF. Exemplo: Campinas e Região, SP.' };
        if (location.indexOf(',') !== -1) return { value: place + ', ' + state, changed: location !== (place + ', ' + state) };
        var capitals = ['BRASILIA', 'SÃO PAULO', 'SAO PAULO', 'RIO DE JANEIRO', 'BELO HORIZONTE', 'CURITIBA', 'PORTO ALEGRE', 'SALVADOR', 'RECIFE', 'FORTALEZA', 'MANAUS', 'GOIÂNIA', 'GOIANIA', 'FLORIANÓPOLIS', 'FLORIANOPOLIS', 'VITÓRIA', 'VITORIA', 'BELÉM', 'BELEM', 'NATAL', 'JOÃO PESSOA', 'JOAO PESSOA', 'MACEIÓ', 'MACEIO', 'ARACAJU', 'TERESINA', 'SÃO LUÍS', 'SAO LUIS', 'CUIABÁ', 'CUIABA', 'CAMPO GRANDE', 'PALMAS', 'PORTO VELHO', 'BOA VISTA', 'MACAPÁ', 'MACAPA', 'RIO BRANCO'];
        var normalizedPlace = /\be\s+regi[aã]o$/i.test(place) || capitals.indexOf(place.toLocaleUpperCase('pt-BR')) !== -1
            ? place
            : place + ' e Região';
        return { value: normalizedPlace + ', ' + state, changed: true };
    }

    function normalizeCompanyWebsite(value) {
        var candidate = String(value == null ? '' : value).trim();
        if (!candidate || /^mailto:/i.test(candidate)) return '';
        if (/^\/\//.test(candidate)) candidate = 'https:' + candidate;
        if (!/^https?:\/\//i.test(candidate)) candidate = 'https://' + candidate;
        try {
            var parsed = new URL(candidate);
            var hostname = String(parsed.hostname || '').toLowerCase().replace(/^www\./, '');
            var blocked = /(^|\.)(linkedin\.com|facebook\.com|instagram\.com|twitter\.com|x\.com|youtube\.com)$/i;
            if (!hostname || hostname.indexOf('.') === -1 || blocked.test(hostname)) return '';
            return 'https://' + hostname;
        } catch (error) {
            return '';
        }
    }

    function extractCompanyWebsiteFromLead(lead) {
        if (!lead || typeof lead !== 'object') return '';
        var company = lead.company && typeof lead.company === 'object' ? lead.company : {};
        var candidates = [
            lead.website, lead.site, lead.company_website, lead.companyWebsite, lead.companyWebsiteUrl,
            lead.company_website_url, lead.company_site, lead.company_url, lead.companyUrl, lead.company_domain, lead.companyDomain,
            company.website, company.site, company.website_url, company.websiteUrl, company.company_website, company.domain, company.url
        ];
        for (var index = 0; index < candidates.length; index += 1) {
            var normalized = normalizeCompanyWebsite(candidates[index]);
            if (normalized) return normalized;
        }
        var emails = [];
        ['email', 'work_email', 'business_email', 'company_email'].forEach(function (field) {
            if (lead[field]) emails = emails.concat(Array.isArray(lead[field]) ? lead[field] : [lead[field]]);
        });
        var freeEmailDomains = /^(gmail\.com|googlemail\.com|outlook\.com|hotmail\.com|live\.com|yahoo\.com(?:\.br)?|icloud\.com|protonmail\.com|uol\.com\.br|bol\.com\.br)$/i;
        for (var emailIndex = 0; emailIndex < emails.length; emailIndex += 1) {
            var emailMatch = String(emails[emailIndex] || '').trim().match(/^[^\s@]+@([^\s@]+\.[^\s@]+)$/);
            if (emailMatch && !freeEmailDomains.test(emailMatch[1])) {
                var fromEmail = normalizeCompanyWebsite(emailMatch[1]);
                if (fromEmail) return fromEmail;
            }
        }
        return '';
    }

    var currentLinkedInLeads = [];

    function closeLeadEnrichmentModal() {
        var modal = document.getElementById('linkedin-lead-enrichment-modal');
        if (modal) modal.remove();
    }

    function renderLeadEnrichmentResult(container, enrichment) {
        if (!container || !enrichment) return;
        var emails = Array.isArray(enrichment.emails) ? enrichment.emails : [];
        var phones = Array.isArray(enrichment.phones) ? enrichment.phones : [];
        var socialLinks = Array.isArray(enrichment.socialLinks) ? enrichment.socialLinks : [];
        var sourceText = enrichment.source === 'cache'
            ? 'Resultado reutilizado do cache interno com cobrança registrada para esta solicitação.'
            : 'Dados públicos encontrados no site da empresa e salvos neste histórico.';
        var allEmpty = !emails.length && !phones.length && !socialLinks.length;
        container.innerHTML = '<div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 dark:bg-emerald-950/20 dark:border-emerald-900 px-5 py-4 text-sm">'
            + '<p class="font-black text-emerald-800 dark:text-emerald-200">Consulta concluída</p>'
            + '<p class="mt-1 text-xs leading-5 text-emerald-700 dark:text-emerald-300">' + escapeHtml(sourceText) + '</p>'
            + (enrichment.creditsCharged > 0 ? '<p class="mt-2 text-xs font-bold text-emerald-800 dark:text-emerald-200">Créditos consumidos: ' + Number(enrichment.creditsCharged) + '</p>' : '')
            + '</div>'
            + (allEmpty ? '<div class="rounded-2xl border border-dashed border-slate-300 dark:border-slate-600 px-5 py-7 text-center text-sm font-semibold text-slate-500">Nenhum contato corporativo elegível foi encontrado nas páginas consultadas.</div>' : '')
            + (emails.length ? '<section><p class="text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">E-mails corporativos</p><div class="space-y-2">' + emails.map(function (email) { return '<a class="block rounded-xl bg-slate-50 dark:bg-slate-900/40 px-3 py-2 text-sm font-bold text-blue-700 dark:text-blue-300 break-all hover:underline" href="mailto:' + escapeHtml(email) + '">' + escapeHtml(email) + '</a>'; }).join('') + '</div></section>' : '')
            + (phones.length ? '<section><p class="text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Telefones publicados</p><div class="space-y-2">' + phones.map(function (phone) { return '<a class="block rounded-xl bg-slate-50 dark:bg-slate-900/40 px-3 py-2 text-sm font-bold text-slate-800 dark:text-slate-100 hover:underline" href="tel:' + encodeURIComponent(phone.replace(/[^0-9+]/g, '')) + '">' + escapeHtml(phone) + '</a>'; }).join('') + '</div></section>' : '')
            + (socialLinks.length ? '<section><p class="text-[11px] font-black uppercase tracking-wider text-slate-500 mb-2">Canais sociais corporativos</p><div class="space-y-2">' + socialLinks.map(function (item) { var label = String(item.type || 'perfil').charAt(0).toUpperCase() + String(item.type || 'perfil').slice(1); return '<a class="block rounded-xl bg-slate-50 dark:bg-slate-900/40 px-3 py-2 text-sm font-bold text-blue-700 dark:text-blue-300 break-all hover:underline" target="_blank" rel="noopener noreferrer" href="' + escapeHtml(item.url || '') + '">' + escapeHtml(label) + ' ↗</a>'; }).join('') + '</div></section>' : '');
    }

    function setLeadEnrichmentLoading(container, message) {
        if (!container) return;
        container.innerHTML = '<div class="rounded-2xl border border-blue-100 bg-blue-50 dark:bg-blue-950/20 dark:border-blue-900 px-5 py-5 text-sm font-semibold text-blue-800 dark:text-blue-200 flex items-center gap-3"><span class="w-4 h-4 shrink-0 rounded-full border-2 border-current border-t-transparent animate-spin"></span><span>' + escapeHtml(message || 'Consultando dados corporativos publicados...') + '</span></div>';
    }

    function showLeadEnrichmentError(container, message) {
        if (!container) return;
        container.innerHTML = '<div class="rounded-2xl border border-red-200 bg-red-50 dark:bg-red-950/20 dark:border-red-900 px-5 py-4 text-sm font-semibold text-red-700 dark:text-red-300">' + escapeHtml(message || 'Não foi possível concluir o enriquecimento.') + '</div>';
    }

    async function pollLeadEnrichment(id, resultContainer, submitButton) {
        var deadline = Date.now() + 180000;
        while (Date.now() < deadline) {
            await new Promise(function (resolve) { window.setTimeout(resolve, 2500); });
            var response = await fetch(apiUrl('lead-enrich.php?id=' + encodeURIComponent(id)), { credentials: 'include', cache: 'no-store' });
            var data = await response.json().catch(function () { return { success: false, error: 'Resposta inválida ao atualizar o enriquecimento.' }; });
            if (!response.ok || !data.success) throw new Error(data.error || 'Não foi possível atualizar o enriquecimento.');
            var enrichment = data.data && data.data.enrichment;
            if (enrichment && !enrichment.pending) {
                renderLeadEnrichmentResult(resultContainer, enrichment);
                if (submitButton) submitButton.classList.add('hidden');
                return;
            }
        }
        throw new Error('A consulta segue em processamento. Reabra este lead em alguns instantes para consultar o resultado salvo.');
    }

    async function loadSavedLeadEnrichment(lead, resultContainer, submitButton) {
        if (!lead || !lead.profile_url) return;
        try {
            var response = await fetch(apiUrl('lead-enrich.php?lead_profile_url=' + encodeURIComponent(lead.profile_url)), { credentials: 'include', cache: 'no-store' });
            var data = await response.json().catch(function () { return { success: false }; });
            if (!response.ok || !data.success || !data.data || !data.data.found) return;
            var enrichment = data.data.enrichment;
            if (enrichment && enrichment.pending) {
                setLeadEnrichmentLoading(resultContainer, 'Há uma consulta em andamento para este domínio. Atualizando o resultado...');
                if (submitButton) submitButton.classList.add('hidden');
                await pollLeadEnrichment(enrichment.id, resultContainer, submitButton);
                return;
            }
            if (enrichment && enrichment.status === 'done') {
                renderLeadEnrichmentResult(resultContainer, enrichment);
                if (submitButton) submitButton.classList.add('hidden');
            }
        } catch (error) {
            // Não interrompe a abertura do modal quando o histórico não estiver disponível.
        }
    }

    function openLeadEnrichmentModal(lead) {
        if (!lead) return;
        closeLeadEnrichmentModal();
        var defaultWebsite = extractCompanyWebsiteFromLead(lead);
        var websiteWasDetected = Boolean(defaultWebsite);
        var modal = document.createElement('div');
        modal.id = 'linkedin-lead-enrichment-modal';
        modal.className = 'fixed inset-0 z-[10000] bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4';
        modal.innerHTML = '<div class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-[2rem] bg-white dark:bg-slate-800 shadow-2xl border border-slate-200 dark:border-slate-700 p-6 md:p-8">'
            + '<div class="flex items-start justify-between gap-5">'
            + ' <div><p class="text-[11px] font-black uppercase tracking-[0.2em] text-blue-600">Enriquecimento corporativo</p><h3 class="mt-2 text-xl font-black text-slate-900 dark:text-slate-100">' + escapeHtml(lead.full_name || 'Lead LinkedIn') + '</h3><p class="mt-1 text-sm text-slate-500 dark:text-slate-400">' + escapeHtml(lead.company_name || 'Empresa não informada') + '</p></div>'
            + ' <button type="button" data-enrich-close class="rounded-xl px-3 py-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700" aria-label="Fechar">✕</button>'
            + '</div>'
            + '<div class="mt-6 space-y-5">'
            + ' <div class="rounded-2xl border border-amber-200 bg-amber-50/70 dark:bg-amber-950/20 dark:border-amber-900 px-5 py-4 text-sm text-amber-900 dark:text-amber-200"><p class="font-black">Uso profissional e dados públicos</p><p class="mt-1 leading-5 text-xs">A consulta busca contatos publicados no site institucional. O sistema mantém apenas e-mails corporativos de função, telefones comerciais e perfis sociais corporativos; não registra e-mails pessoais ou dados privados.</p></div>'
            + (websiteWasDetected
                ? ' <div class="rounded-2xl border border-emerald-200 bg-emerald-50/70 dark:bg-emerald-950/20 dark:border-emerald-900 px-4 py-3 text-sm"><p class="text-[10px] font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Site identificado automaticamente</p><a href="' + escapeHtml(defaultWebsite) + '" target="_blank" rel="noopener noreferrer" class="mt-1 block break-all font-bold text-emerald-800 underline decoration-emerald-400 underline-offset-2 dark:text-emerald-200">' + escapeHtml(defaultWebsite) + '</a></div>'
                : ' <div class="rounded-2xl border border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-700/40 px-4 py-3 text-sm text-slate-600 dark:text-slate-300"><p class="font-bold">Site corporativo não identificado automaticamente</p><p class="mt-1 text-xs leading-5">Para preservar a confiabilidade da consulta, o enriquecimento não aceita preenchimento manual de domínio.</p></div>')
            + (websiteWasDetected ? ' <p class="text-xs leading-5 text-slate-500 dark:text-slate-400">Esta consulta pode consumir <strong>1 crédito</strong>.</p>' : '')
            + ' <div id="linkedin-enrich-result" class="space-y-4"></div>'
            + ' <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3 pt-1"><button type="button" data-enrich-close class="rounded-2xl px-5 py-3 text-sm font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700">Cancelar</button><button id="linkedin-enrich-submit" type="button"' + (websiteWasDetected ? '' : ' disabled aria-disabled="true"') + ' class="rounded-2xl bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 text-sm font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-50">' + (websiteWasDetected ? 'Enriquecer contato' : 'Site não identificado') + '</button></div>'
            + '</div></div>';
        document.body.appendChild(modal);
        Array.prototype.forEach.call(modal.querySelectorAll('[data-enrich-close]'), function (button) { button.addEventListener('click', closeLeadEnrichmentModal); });
        modal.addEventListener('click', function (event) { if (event.target === modal) closeLeadEnrichmentModal(); });

        var resultContainer = modal.querySelector('#linkedin-enrich-result');
        var submitButton = modal.querySelector('#linkedin-enrich-submit');
        loadSavedLeadEnrichment(lead, resultContainer, submitButton);
        submitButton.addEventListener('click', async function () {
            var website = defaultWebsite;
            if (!website) {
                showLeadEnrichmentError(resultContainer, 'O enriquecimento requer um site corporativo identificado automaticamente nos dados públicos do lead.');
                return;
            }
            submitButton.disabled = true;
            submitButton.textContent = 'Consultando...';
            setLeadEnrichmentLoading(resultContainer);
            try {
                var response = await fetch(apiUrl('lead-enrich.php'), {
                    method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ lead_profile_url: lead.profile_url, lead_name: lead.full_name || '', company_name: lead.company_name || '', website: website })
                });
                var data = await response.json().catch(function () { return { success: false, error: 'Resposta inválida do servidor.' }; });
                if (!response.ok || !data.success) throw new Error(data.error || 'Não foi possível concluir o enriquecimento.');
                var enrichment = data.data && data.data.enrichment;
                if (enrichment && enrichment.pending) {
                    setLeadEnrichmentLoading(resultContainer, 'Consulta iniciada. Estamos verificando os contatos corporativos publicados...');
                    await pollLeadEnrichment(enrichment.id, resultContainer, submitButton);
                } else {
                    renderLeadEnrichmentResult(resultContainer, enrichment || {});
                    submitButton.classList.add('hidden');
                }
            } catch (error) {
                showLeadEnrichmentError(resultContainer, error.message);
                submitButton.disabled = false;
                submitButton.textContent = 'Tentar novamente';
            }
        });
    }

    function renderLinkedInResults(leads, meta) {
        var section = document.getElementById('linkedin-results');
        if (!section) return;
        section.classList.remove('hidden');
        if (!leads.length) {
            var emptyMessage = meta && meta.emptyMessage
                ? meta.emptyMessage
                : 'Nenhum perfil foi retornado para os filtros informados.';
            section.innerHTML = '<div class="rounded-2xl border border-amber-200 bg-amber-50/70 dark:bg-amber-950/20 dark:border-amber-900 p-8 text-center text-sm font-semibold text-amber-900 dark:text-amber-200">' + escapeHtml(emptyMessage) + '</div>';
            return;
        }
        currentLinkedInLeads = Array.isArray(leads) ? leads : [];
        if (!window.__linkedinViewedProfileUrls) window.__linkedinViewedProfileUrls = {};
        var viewedUrls = window.__linkedinViewedProfileUrls;
        var isFirstRenderOfLeads = Object.keys(viewedUrls).length === 0;
        var newCount = 0;
        currentLinkedInLeads.forEach(function (lead) {
            var url = String(lead.profile_url || '');
            if (url && !viewedUrls[url]) {
                viewedUrls[url] = true;
                if (!isFirstRenderOfLeads) newCount++;
                lead.__isNew = true;
            } else if (url && isFirstRenderOfLeads) {
                lead.__isNew = false;
            } else if (url) {
                lead.__isNew = false;
            }
        });
        var newLeadsButton = '<button type="button" id="linkedin-check-new-leads" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-bold transition-colors">Verificar novos leads</button>';
        var resultsTitle = newCount > 0
            ? 'Leads encontrados <span class="text-xs font-black uppercase tracking-wider text-emerald-600">+ ' + newCount + ' novo(s)</span>'
            : 'Leads encontrados';
        section.innerHTML = `
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 class="text-xl font-black text-slate-900 dark:text-slate-100">${resultsTitle}</h3>
                    <p class="text-xs text-slate-500 mt-1">${leads.length} perfil(is) nesta pesquisa</p>
                </div>
                <div class="flex items-center gap-3">
                    <button type="button" id="linkedin-filter-only-new" class="hidden inline-flex items-center gap-2 rounded-xl border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/30 px-3.5 py-2 text-xs font-bold text-emerald-700 dark:text-emerald-300 hover:bg-emerald-100 dark:hover:bg-emerald-950/60 transition-colors">Mostrar só os novos</button>
                    ${newLeadsButton}
                </div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                ${leads.map(function (lead, index) {
                    var isNewBadge = lead.__isNew ? '<div class="absolute -top-3 right-5 bg-emerald-600 text-white text-[9px] font-black py-1.5 px-3 rounded-lg uppercase tracking-wider shadow-lg shadow-emerald-200 z-10">Novo</div>' : '';
                    var showOnlyNew = !!document.getElementById('linkedin-filter-only-new') && document.getElementById('linkedin-filter-only-new').getAttribute('data-active') === '1';
                    if (showOnlyNew && !lead.__isNew) return '';
                    return `<article class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5 shadow-sm relative">
                        ${isNewBadge}
                        <div class="flex gap-4">
                            ${lead.photo_url ? `<img src="${escapeHtml(lead.photo_url)}" alt="" class="w-12 h-12 rounded-xl object-cover bg-slate-100">` : '<div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center font-black">in</div>'}
                            <div class="min-w-0 flex-1">
                                <h4 class="font-black text-slate-900 dark:text-slate-100 truncate">${escapeHtml(lead.full_name || 'Perfil sem nome')}</h4>
                                <p class="text-sm font-semibold text-blue-700 truncate">${escapeHtml(lead.job_title || 'Cargo não informado')}</p>
                                <p class="text-xs text-slate-500 mt-1">${escapeHtml(lead.company_name || '')}${lead.location ? ' · ' + escapeHtml(lead.location) : ''}</p>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-3">
                            <a href="${escapeHtml(lead.profile_url)}" target="_blank" rel="noopener noreferrer" class="inline-flex text-xs font-bold text-blue-700 hover:underline">Abrir perfil no LinkedIn ↗</a>
                            <button type="button" data-linkedin-enrich-index="${index}" class="linkedin-enrich-contact inline-flex items-center rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950/30 px-3 py-2 text-xs font-black text-blue-700 dark:text-blue-300 hover:bg-blue-100 dark:hover:bg-blue-950/60 transition-colors">Enriquecer contato</button>
                        </div>
                    </article>`;
                }).join('')}
            </div>
        `;
        Array.prototype.forEach.call(section.querySelectorAll('.linkedin-enrich-contact'), function (button) {
            button.addEventListener('click', function () {
                var index = parseInt(button.getAttribute('data-linkedin-enrich-index'), 10);
                if (!isNaN(index) && currentLinkedInLeads[index]) openLeadEnrichmentModal(currentLinkedInLeads[index]);
            });
        });
        var checkNewButton = document.getElementById('linkedin-check-new-leads');
        if (checkNewButton) {
            checkNewButton.addEventListener('click', async function () {
                checkNewButton.disabled = true;
                var originalLabel = checkNewButton.textContent;
                checkNewButton.textContent = 'Verificando...';
                try {
                    var checkResponse = await fetch(apiUrl('linkedin-new-leads.php'), { credentials: 'include', cache: 'no-store' });
                    var checkData = await checkResponse.json().catch(function () { return { success: false }; });
                    if (!checkResponse.ok || !checkData.success) {
                        throw new Error(checkData.error || 'Não foi possível verificar novos leads.');
                    }
                    var checkResult = checkData.data || {};
                    if (Array.isArray(checkResult.leads) && checkResult.leads.length) {
                        renderLinkedInResults(checkResult.leads, checkResult.meta || {});
                        showLinkedInToast((checkResult.new_count || 0) + ' lead(s) novo(s) nesta pesquisa. Os perfis marcados como "Novo" ainda não tinham sido exibidos nesta sessão.');
                    } else {
                        renderLinkedInResults(currentLinkedInLeads, {});
                        showLinkedInToast('A pesquisa já está atualizada. Nenhum lead novo encontrado nesta execução.');
                    }
                } catch (error) {
                    showLinkedInError(error.message);
                } finally {
                    checkNewButton.disabled = false;
                    checkNewButton.textContent = originalLabel;
                }
            });
        }
        var filterOnlyNew = document.getElementById('linkedin-filter-only-new');
        if (filterOnlyNew && newCount > 0) {
            filterOnlyNew.classList.remove('hidden');
            filterOnlyNew.addEventListener('click', function () {
                var isActive = filterOnlyNew.getAttribute('data-active') === '1';
                filterOnlyNew.setAttribute('data-active', isActive ? '0' : '1');
                filterOnlyNew.textContent = isActive ? 'Mostrar só os novos' : 'Mostrar todos';
                renderLinkedInResults(currentLinkedInLeads, {});
            });
        }
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
        if (isSearching) {
            button.innerHTML = '<span class="inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>' + escapeHtml(label || 'Pesquisando...');
        } else {
            button.textContent = 'Pesquisar leads';
        }
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
                var locationInput = document.getElementById('linkedin-location');
                var locationResolution = normalizeLinkedInLocation(locationInput ? locationInput.value : '');
                if (locationResolution.error) throw new Error(locationResolution.error);
                var location = locationResolution.value;
                if (locationInput && locationResolution.changed) locationInput.value = location;
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
                if (!response.ok || !data.success) {
                    var message = data && data.error ? String(data.error) : '';
                    if (/esgotado|crédito/i.test(message)) {
                        throw new Error('Seu saldo de créditos está esgotado. Solicite novos créditos para continuar a pesquisa LinkedIn.');
                    }
                    throw new Error(data.error || 'Não foi possível concluir a pesquisa agora. Verifique os filtros informados e tente novamente em alguns instantes.');
                }
                var resultData = data.data || {};
                if (resultData.pending) {
                    setSearching(true, 'Buscando perfis...');
                    resultData = await waitForLinkedInSearch(resultData.runId, resultData.pollAfterMs, resultData.limit || limit);
                }
                resultData.requestedLimit = resultData.requestedLimit || limit;
                renderLinkedInResults(resultData.leads || [], resultData);
                loadLinkedInHistory();
                var notice = resultData.sourceNotice ? resultData.sourceNotice + ' ' : '';
                showLinkedInToast((resultData.leads || []).length
                    ? notice + 'Pesquisa concluída. Você pode exportar os perfis ou enriquecer contatos.'
                    : 'A pesquisa não retornou perfis. Revise a localidade e os cargos informados.');
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
