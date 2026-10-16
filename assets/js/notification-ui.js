/**
 * Central de Notificações do painel administrativo.
 *
 * A aba Notificações permite ao administrador editar os textos das
 * mensagens automáticas por evento (boas-vindas, solicitações, aprovações,
 * recusas e saldo baixo), além de disparar testes de WhatsApp e e-mail
 * para destinatários informados no próprio painel.
 *
 * Instalação:
 * 1. Execute sql/notification-templates.sql no banco de dados.
 * 2. Publique api/notification-templates.php na pasta api/.
 * 3. Publique este arquivo em assets/js/notification-ui.js.
 * 4. No index.php, inclua:
 *      <script src="assets/js/notification-ui.js?v=20260815-1"></script>
 *    antes de assets/js/app.js e adicione o botão da aba no menu lateral:
 *      <button data-tab="notifications" class="super-admin-only tab-btn ...">Notificações</button>
 * 5. Bump o ?v= do app.js no index.php.
 *
 * Nenhum segredo (token, SMTP) trafega ou é exibido no navegador.
 */
(function () {
    'use strict';

    if (window.__notifUiInstalled) {
        return;
    }
    window.__notifUiInstalled = true;

    const NOTIF_API = (typeof window.API_BASE_URL !== 'undefined' ? window.API_BASE_URL : 'api/') + 'notification-templates.php';
    const TEMPLATE_KEYS = [
        'welcome-whatsapp', 'welcome-email-subject', 'welcome-email',
        'credit-request-whatsapp', 'credit-request-email-subject', 'credit-request-email',
        'credits-approved-whatsapp', 'credits-approved-email-subject', 'credits-approved-email',
        'credit-rejected-whatsapp', 'credit-rejected-email-subject', 'credit-rejected-email',
        'plan-request-whatsapp', 'plan-request-email-subject', 'plan-request-email',
        'plan-approved-whatsapp', 'plan-approved-email-subject', 'plan-approved-email',
        'plan-rejected-whatsapp', 'plan-rejected-email-subject', 'plan-rejected-email',
        'low-balance-whatsapp',
        'reengagement-2d-whatsapp', 'reengagement-2d-email-subject', 'reengagement-2d-email',
        'test-whatsapp'
    ];

    // Estado persistente da aba (templates carregados e rascunho do editor).
    window.__notifState = {
        loaded: false,
        templates: {},
        adminEmail: '',
        adminNumber: ''
    };

    /** Feedback visual embutido (sem depender de showToast, que pode não existir). */
    function notifFeedback(el, text, ok) {
        if (!el) {
            if (typeof window.showToast === 'function') {
                window.showToast(text);
            }
            return;
        }
        el.textContent = text;
        el.className = 'text-xs font-bold mt-3 ' + (ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400');
        el.classList.remove('hidden');
        clearTimeout(el.__timer);
        el.__timer = setTimeout(function () {
            el.classList.add('hidden');
        }, 8000);
    }

    function escapeHtml(text) {
        return String(text == null ? '' : text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderNotificationsTab(contentArea) {
        if (!contentArea) return;
        contentArea.innerHTML = `
            <div class="max-w-5xl mx-auto py-10">
                <div class="flex items-center justify-between gap-4 mb-8">
                    <div>
                        <p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600 mb-2">Central de notificações</p>
                        <h3 class="text-2xl font-black text-slate-900 dark:text-slate-100">Notificações</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Edite os textos das mensagens automáticas de WhatsApp e e-mail. Use {empresa}, {plataforma}, {creditos}, {plano}, {restante}, {total}, {percentual}, {dias}, {email}, {usuario} e {acao} como marcadores. O marcador {usuario} informa o nome da pessoa, {dias} informa há quantos dias não há acesso e {acao} descreve o que ela está fazendo.</p>
                    </div>
                    <div class="flex gap-3">
                        <button type="button" id="notif-restore-defaults" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold px-4 py-3 rounded-2xl text-xs">Restaurar padrão</button>
                        <button type="button" id="notif-save" class="bg-blue-600 hover:bg-blue-700 text-white font-black px-6 py-3 rounded-2xl text-xs shadow-xl shadow-blue-100 dark:shadow-blue-900/30">Salvar templates</button>
                    </div>
                </div>

                <div id="notif-loading" class="py-12 text-center text-sm font-bold text-slate-400">Carregando templates...</div>

                <div id="notif-panels" class="space-y-6 hidden"></div>

                <div id="notif-test-section" class="mt-10 hidden">
                    <div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                        <h4 class="text-lg font-black text-slate-900 dark:text-slate-100 border-b border-slate-100 dark:border-slate-700 pb-3 flex items-center gap-2">🧪 Destino dos testes</h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">Configura os números de teste usados pelo botão "Testar" de cada mensagem acima. Use o botão de cada mensagem para enviar exatamente o texto que está salvo nela.</p>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">Telefone de teste (WhatsApp)</label>
                                <input id="notif-test-phone" type="tel" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" placeholder="+55 17 99999-9999" />
                            </div>
                            <div>
                                <label class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">E-mail de teste</label>
                                <input id="notif-test-email" type="email" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" placeholder="teste@empresa.com" />
                            </div>
                        </div>
                        <div id="notif-test-feedback" class="hidden"></div>
                    </div>

                    <div class="mt-6 bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm">
                        <div class="flex items-center justify-between gap-4 mb-4">
                            <h4 class="text-lg font-black text-slate-900 dark:text-slate-100">Histórico de envios</h4>
                            <button type="button" id="notif-history-refresh" class="bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 font-bold px-4 py-3 rounded-2xl text-xs">Atualizar</button>
                        </div>
                        <div id="notif-history-list"></div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('notif-save').onclick = saveTemplates;
        document.getElementById('notif-restore-defaults').onclick = restoreDefaults;
        document.getElementById('notif-history-refresh').onclick = loadHistory;

        // Teste individual de cada mensagem: delegação de clique no painel.
        document.getElementById('notif-panels').addEventListener('click', function (e) {
            var btn = e.target.closest('.notif-test-msg-btn');
            if (btn) { testTemplateMessage(btn); }
        });

        loadTemplates();
    }

    function groupTemplatesByTopic(templates) {
        // Organiza os templates em grupos temáticos para o painel.
        var topics = {
            'Boas-vindas': ['welcome-whatsapp', 'welcome-email-subject', 'welcome-email'],
            'Solicitação de créditos': ['credit-request-whatsapp', 'credit-request-email-subject', 'credit-request-email'],
            'Créditos aprovados': ['credits-approved-whatsapp', 'credits-approved-email-subject', 'credits-approved-email'],
            'Créditos recusados': ['credit-rejected-whatsapp', 'credit-rejected-email-subject', 'credit-rejected-email'],
            'Solicitação de plano': ['plan-request-whatsapp', 'plan-request-email-subject', 'plan-request-email'],
            'Plano aprovado': ['plan-approved-whatsapp', 'plan-approved-email-subject', 'plan-approved-email'],
            'CRM adicional': ['crm-approved-whatsapp'],
            'Plano recusado': ['plan-rejected-whatsapp', 'plan-rejected-email-subject', 'plan-rejected-email'],
            'Saldo baixo (10%)': ['low-balance-whatsapp'],
            'Reengajamento após 2 dias': ['reengagement-2d-whatsapp', 'reengagement-2d-email-subject', 'reengagement-2d-email'],
            'Testes': ['test-whatsapp']
        };
        var meta = {};
        (templates || []).forEach(function (t) { meta[t.key] = t; });

        var html = '';
        Object.keys(topics).forEach(function (topic) {
            html += '<div class="bg-white dark:bg-slate-800 p-8 rounded-[2rem] border border-slate-200 dark:border-slate-600 shadow-sm space-y-6">';
            html += '<h4 class="text-lg font-black text-slate-900 dark:text-slate-100 border-b border-slate-100 dark:border-slate-700 pb-3">' + escapeHtml(topic) + '</h4>';
            topics[topic].forEach(function (key) {
                var t = meta[key];
                if (!t) return;
                var isTextarea = t.value.length > 90 || key.indexOf('-email') === key.length - 6;
                html += '<div>';
                html += '<label for="notif-tpl-' + escapeHtml(key) + '" class="block text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase mb-1">' + escapeHtml(t.label) + ' <span class="normal-case font-semibold ' + (t.channel === 'whatsapp' ? 'text-emerald-600 dark:text-emerald-400' : 'text-blue-600 dark:text-blue-400') + '">(' + (t.channel === 'whatsapp' ? 'WhatsApp' : 'E-mail') + ')</span></label>';
                if (isTextarea) {
                    html += '<textarea id="notif-tpl-' + escapeHtml(key) + '" rows="5" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold text-sm font-mono">' + escapeHtml(t.value) + '</textarea>';
                } else {
                    html += '<input id="notif-tpl-' + escapeHtml(key) + '" type="text" class="w-full bg-slate-50 dark:bg-slate-700 text-slate-900 dark:text-slate-100 border border-slate-200 dark:border-slate-600 rounded-xl px-4 py-3 outline-none focus:border-blue-500 font-semibold" value="' + escapeHtml(t.value) + '" />';
                }
                html += '<div class="flex items-center justify-between gap-2 mt-2">';
                html += '<span id="notif-test-feedback-' + escapeHtml(key) + '" class="text-[11px] font-bold ' + (t.channel === 'whatsapp' ? 'text-emerald-600 dark:text-emerald-400' : 'text-blue-600 dark:text-blue-400') + '"></span>';
                html += '<button type="button" data-tpl-key="' + escapeHtml(key) + '" class="notif-test-msg-btn whitespace-nowrap rounded-full px-4 py-2 text-[11px] font-black shadow-sm transition-all active:scale-[0.97] ' + (t.channel === 'whatsapp' ? 'bg-emerald-600 hover:bg-emerald-700 text-white shadow-emerald-100 dark:shadow-emerald-900/30' : 'bg-blue-600 hover:bg-blue-700 text-white shadow-blue-100 dark:shadow-blue-900/30') + '">' + (t.channel === 'whatsapp' ? 'Testar no WhatsApp' : 'Testar por e-mail') + '</button>';
                html += '</div>';
                html += '</div>';
            });
            html += '</div>';
        });
        return html;
    }

    function loadTemplates() {
        var loading = document.getElementById('notif-loading');
        var panels = document.getElementById('notif-panels');
        var testSection = document.getElementById('notif-test-section');
        if (loading) loading.classList.remove('hidden');
        if (panels) panels.classList.add('hidden');
        if (testSection) testSection.classList.add('hidden');

        fetch(NOTIF_API, { credentials: 'include', cache: 'no-store' })
            .then(function (r) {
                return r.text().then(function (raw) {
                    var data;
                    try {
                        data = JSON.parse(raw);
                    } catch (e) {
                        throw new Error('O endpoint retornou uma resposta não reconhecida (HTTP ' + r.status + ').');
                    }
                    if (!r.ok || !data || !data.success) {
                        throw new Error((data && data.error) || ('Não foi possível carregar os templates (HTTP ' + r.status + ').'));
                    }
                    return data;
                });
            })
            .then(function (data) {
                if (!data.data || !Array.isArray(data.data.templates)) {
                    throw new Error('A resposta da central não contém a lista de templates.');
                }
                var tpl = data.data.templates;
                window.__notifState.loaded = true;
                window.__notifState.templates = {};
                tpl.forEach(function (t) { window.__notifState.templates[t.key] = t; });
                window.__notifState.adminEmail = (data.data.adminEmail || '').toString();
                window.__notifState.adminNumber = (data.data.adminNumber || '').toString();

                if (loading) loading.classList.add('hidden');
                if (panels) {
                    panels.innerHTML = groupTemplatesByTopic(tpl);
                    panels.classList.remove('hidden');
                }
                if (testSection) testSection.classList.remove('hidden');

                // Preenche os campos de teste com o contato administrativo.
                var phoneEl = document.getElementById('notif-test-phone');
                var emailEl = document.getElementById('notif-test-email');
                if (phoneEl && window.__notifState.adminNumber && !phoneEl.value) {
                    phoneEl.value = window.__notifState.adminNumber;
                }
                if (emailEl && window.__notifState.adminEmail && !emailEl.value) {
                    emailEl.value = window.__notifState.adminEmail;
                }
                loadHistory();
            })
            .catch(function (err) {
                console.error('Erro ao carregar templates:', err);
                if (loading) loading.textContent = err && err.message
                    ? err.message
                    : 'Não foi possível carregar os templates. Verifique a inclusão de notification-templates.php na pasta api/.';
            });
    }

    function collectTemplates() {
        var items = [];
        TEMPLATE_KEYS.forEach(function (key) {
            var el = document.getElementById('notif-tpl-' + key);
            if (!el) return;
            items.push({ key: key, value: String(el.value || '') });
        });
        return items;
    }

    function saveTemplates() {
        var btn = document.getElementById('notif-save');
        btn.disabled = true;
        btn.textContent = 'Salvando...';

        fetch(NOTIF_API, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'saveTemplates', templates: collectTemplates() })
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success) {
                    notifFeedback(document.getElementById('notif-save-feedback'), 'Templates salvos com sucesso. Os novos textos serão usados nos próximos envios.', true);
                    if (typeof window.showToast === 'function') window.showToast('Templates salvos com sucesso.');
                } else {
                    notifFeedback(document.getElementById('notif-save-feedback'), data && data.error ? data.error : 'Não foi possível salvar os templates.', false);
                }
            })
            .catch(function () {
                notifFeedback(document.getElementById('notif-save-feedback'), 'Erro de conexão ao salvar os templates.', false);
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = 'Salvar templates';
            });
    }

    /** Restaura os textos-padrão do sistema (são gravados como novos valores salvos). */
    function restoreDefaults() {
        if (!confirm('Restaurar todos os textos para os valores-padrão do sistema? As edições atuais serão perdidas.')) {
            return;
        }
        var defaults = {
            'welcome-whatsapp': 'Olá, *{usuario}*. Você {acao} na empresa *{empresa}*. Use o seu e-mail *{email}* para acessar o painel e iniciar as prospecções.',
            'welcome-email-subject': 'Cadastro confirmado em {plataforma}',
            'welcome-email': '<div style="font-family:Arial,sans-serif;color:#1e293b;max-width:600px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:10px"><h2 style="color:#2563eb">Cadastro confirmado</h2><p>A empresa <strong>{empresa}</strong> foi cadastrada com sucesso em <strong>{plataforma}</strong>.</p><p>Use o e-mail informado no cadastro para acessar seu painel e iniciar as prospecções.</p><p style="margin-top:30px;font-size:12px;color:#64748b">Atenciosamente,<br>Equipe {plataforma} — Atendo Sistemas</p></div>',
            'credit-request-whatsapp': '*{usuario}* {acao} para a empresa *{empresa}*: *{creditos} créditos*.',
            'credit-request-email-subject': 'Solicitação de créditos: {empresa}',
            'credit-request-email': '<p>A empresa <strong>{empresa}</strong> solicitou <strong>{creditos} créditos adicionais</strong>.</p>',
            'credits-approved-whatsapp': 'Olá, *{usuario}*. Você {acao} para a empresa *{empresa}*: *{creditos} créditos*. O saldo já está disponível no painel.',
            'credits-approved-email-subject': 'Créditos liberados em {plataforma}',
            'credits-approved-email': '<p>Foram liberados <strong>{creditos} créditos adicionais</strong> para a empresa <strong>{empresa}</strong>.</p>',
            'credit-rejected-whatsapp': 'Olá, *{usuario}*. Você {acao} para a empresa *{empresa}*: *{creditos} créditos*. Entre em contato pelo painel se precisar de mais informações.',
            'credit-rejected-email-subject': 'Solicitação de créditos em análise — {plataforma}',
            'credit-rejected-email': '<p>A solicitação de <strong>{creditos} créditos adicionais</strong> da empresa <strong>{empresa}</strong> não foi aprovada neste momento. Entre em contato pelo painel se precisar de mais informações.</p>',
            'plan-request-whatsapp': '*{usuario}* {acao} para a empresa *{empresa}*: plano *{plano}*.',
            'plan-request-email-subject': 'Solicitação de plano: {empresa}',
            'plan-request-email': '<p>A empresa <strong>{empresa}</strong> solicitou o plano <strong>{plano}</strong>.</p>',
            'plan-approved-whatsapp': 'Olá, *{usuario}*. Você {acao} para a empresa *{empresa}*: plano *{plano}*. Os novos limites já estão ativos no painel.',
            'plan-approved-email-subject': 'Plano ativado em {plataforma}',
            'plan-approved-email': '<p>O plano da empresa <strong>{empresa}</strong> foi atualizado para <strong>{plano}</strong>. Os novos limites já estão ativos.</p>',
            'crm-approved-whatsapp': 'Olá, *{usuario}*. O CRM adicional *{acao}* foi ativado para a empresa *{empresa}*. A licença já está disponível no painel.',
            'plan-rejected-whatsapp': 'Olá, *{usuario}*. Você {acao} para a empresa *{empresa}*: plano *{plano}*. Entre em contato pelo painel se precisar de mais informações.',
            'plan-rejected-email-subject': 'Solicitação de plano em análise — {plataforma}',
            'plan-rejected-email': '<p>A solicitação de mudança para o plano <strong>{plano}</strong> da empresa <strong>{empresa}</strong> não foi aprovada neste momento. Entre em contato pelo painel se precisar de mais informações.</p>',
            'low-balance-whatsapp': 'Olá, *{usuario}*. Você {acao} em *{empresa}*: restam *{restante} de {total} créditos* ({percentual}%) em *{plataforma}*. Acesse o painel para solicitar créditos e evitar interrupções.',
            'reengagement-2d-whatsapp': 'Olá, *{usuario}*! Sentimos sua falta na {plataforma}. Faz {dias} dias que você não acessa o sistema. Volte ao painel para continuar suas pesquisas de empresas e profissionais. Acesse: https://prospec.atendo.log.br/app/',
            'reengagement-2d-email-subject': 'Sentimos sua falta na {plataforma}',
            'reengagement-2d-email': '<div style="font-family:Arial,sans-serif;color:#1e293b;max-width:600px;margin:0 auto;padding:20px"><h2 style="color:#2563eb">Sentimos sua falta, {usuario}!</h2><p>Faz <strong>{dias} dias</strong> que você não acessa a plataforma <strong>{plataforma}</strong>.</p><p>Volte ao painel para continuar suas pesquisas e aproveitar os recursos disponíveis para a empresa <strong>{empresa}</strong>.</p><p><a href="https://prospec.atendo.log.br/app/" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 18px;border-radius:8px">Acessar o painel</a></p><p style="font-size:12px;color:#64748b">Se você já voltou a acessar o sistema, ignore esta mensagem.</p></div>',
            'test-whatsapp': 'Olá, *{usuario}*. Você {acao} em *{plataforma}*. Se você recebeu este aviso, a integração com o WhatsApp está funcionando.'
        };
        var items = TEMPLATE_KEYS.map(function (key) {
            return { key: key, value: defaults[key] || '' };
        });
        fetch(NOTIF_API, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'saveTemplates', templates: items })
        })
            .then(function (r) { return r.json(); })
            .then(function () { loadTemplates(); })
            .catch(function (err) { console.error('Falha ao restaurar o padrão:', err); });
    }

    /** Teste individual: envia o texto exato de um template para o destino de teste. */
    function testTemplateMessage(btn) {
        var templateKey = (btn && btn.getAttribute('data-tpl-key')) || '';
        var t = (window.__notifState.templates || {})[templateKey];
        if (!t) return;

        var phone = String(document.getElementById('notif-test-phone').value || '').trim();
        var email = String(document.getElementById('notif-test-email').value || '').trim();
        var destination = t.channel === 'whatsapp' ? phone : email;
        var feedback = document.getElementById('notif-test-feedback-' + templateKey);

        if (destination === '') {
            notifFeedback(feedback, t.channel === 'whatsapp'
                ? 'Configure o "Telefone de teste" no topo e tente novamente.'
                : 'Configure o "E-mail de teste" no topo e tente novamente.', false);
            return;
        }

        btn.disabled = true;
        var originalText = btn.textContent;
        btn.textContent = 'Enviando...';

        var body = { action: 'testTemplate', templateKey: templateKey };
        if (t.channel === 'whatsapp') { body.phone = phone; } else { body.email = email; }

        fetch(NOTIF_API, {
            method: 'POST',
            credentials: 'include',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body)
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                notifFeedback(feedback,
                    data && data.success
                        ? (data.data && data.data.message ? data.data.message : 'Mensagem de teste disparada.')
                        : (data && data.error ? data.error : 'Não foi possível enviar o teste.'),
                    !!(data && data.success));
            })
            .catch(function () {
                notifFeedback(feedback, 'Erro de conexão ao testar a mensagem.', false);
            })
            .finally(function () {
                btn.disabled = false;
                btn.textContent = originalText;
                loadHistory();
            });
    }

    function testWhatsApp() {
        var phone = String(document.getElementById('notif-test-phone').value || '').trim();
        var feedback = document.getElementById('notif-test-feedback');
        if (phone === '') {
            notifFeedback(feedback, 'Informe um telefone para o teste de WhatsApp.', false);
            return;
        }
        testTemplateMessage({ getAttribute: function () { return 'test-whatsapp'; } });
    }

    function testEmail() {
        var email = String(document.getElementById('notif-test-email').value || '').trim();
        var feedback = document.getElementById('notif-test-feedback');
        if (email === '') {
            notifFeedback(feedback, 'Informe um e-mail para o teste.', false);
            return;
        }
        testTemplateMessage({ getAttribute: function () { return 'test-email'; } });
    }

    function loadHistory() {
        var list = document.getElementById('notif-history-list');
        if (!list) return;
        list.innerHTML = '<div class="py-6 text-center text-sm font-bold text-slate-400">Carregando histórico...</div>';

        fetch(NOTIF_API + '?action=history&limit=50', { credentials: 'include' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var history = (data && data.success && Array.isArray(data.data && data.data.history)) ? data.data.history : [];
                if (history.length === 0) {
                    list.innerHTML = '<div class="py-6 text-center text-sm font-bold text-slate-400">Nenhum envio registrado ainda. Os envios automáticos e os testes aparecem aqui com o status de entrega.</div>';
                    return;
                }
                var html = '<div class="overflow-x-auto"><table class="w-full text-xs text-left"><thead><tr class="text-[10px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400 border-b border-slate-100 dark:border-slate-700"><th class="py-2 pr-3">Evento</th><th class="py-2 pr-3">Canal</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Tentativas</th><th class="py-2 pr-3">Data</th></tr></thead><tbody>';
                history.forEach(function (row) {
                    var statusClass = row.status === 'sent'
                        ? 'text-emerald-600 dark:text-emerald-400 font-black'
                        : row.status === 'processing' || row.status === 'queued'
                            ? 'text-amber-600 dark:text-amber-400 font-black'
                            : 'text-red-600 dark:text-red-400 font-black';
                    html += '<tr class="border-b border-slate-50 dark:border-slate-700/50 align-top">';
                    html += '<td class="py-2 pr-3 font-mono break-all">' + escapeHtml(row.eventKey.slice(0, 28)) + (row.eventKey.length > 28 ? '…' : '') + '</td>';
                    html += '<td class="py-2 pr-3">' + escapeHtml(row.channel) + '</td>';
                    html += '<td class="py-2 pr-3 ' + statusClass + '">' + escapeHtml(row.status) + (row.lastError ? ' — ' + escapeHtml(row.lastError.slice(0, 60)) : '') + '</td>';
                    html += '<td class="py-2 pr-3">' + (row.attempts || 0) + '</td>';
                    html += '<td class="py-2 pr-3 whitespace-nowrap">' + escapeHtml(row.createdAt ? row.createdAt.slice(0, 16).replace('T', ' ') : '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table></div>';
                list.innerHTML = html;
            })
            .catch(function () {
                list.innerHTML = '<div class="py-6 text-center text-sm font-bold text-slate-400">O histórico não está disponível no momento.</div>';
            });
    }

    // Registra a aba caso o loadTab do app.js a encontre.
    if (typeof window.registerNotificationTab === 'function') {
        window.registerNotificationTab(renderNotificationsTab);
    } else {
        window.renderNotificationsTab = renderNotificationsTab;
    }

    // Autoinjeta o botão da aba no menu lateral se o DOM já estiver pronto.
    function injectMenuButton() {
        var existing = document.querySelector('[data-tab="notifications"]');
        if (existing) return;
        var adminBlock = document.getElementById('nav-block-administracao');
        var firstAdminBtn = adminBlock ? adminBlock.querySelector('.tab-btn') : null;
        var button = document.createElement('button');
        button.type = 'button';
        button.dataset.tab = 'notifications';
        button.className = 'super-admin-only hidden tab-btn w-full flex items-center gap-4 px-5 py-4 rounded-2xl transition-all font-semibold text-sm hover:bg-slate-100 dark:hover:bg-slate-700/50 text-slate-600 dark:text-slate-300';
        button.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>Notificações';
        if (firstAdminBtn && firstAdminBtn.parentNode) {
            firstAdminBtn.parentNode.insertBefore(button, firstAdminBtn);
        } else {
            var sidebarNav = document.querySelector('.sidebar-nav-scroll');
            if (sidebarNav) sidebarNav.appendChild(button);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', injectMenuButton);
    } else {
        injectMenuButton();
    }
})();
