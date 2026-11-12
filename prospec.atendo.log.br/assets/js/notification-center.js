/*
 * Maps Prospector — Central de Notificações internas v20260817-41
 * Estilo: interface operacional e responsiva; prioriza leitura rápida e não
 * armazena dados no navegador. Todos os dados vêm de API com cache desativado.
 */
(function () {
    'use strict';

    var API_URL = (typeof API_BASE !== 'undefined' ? API_BASE : 'api/') + 'notification-center.php';
    var state = { items: [], filter: 'all', canCompose: false, directory: null, opened: false };

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[character];
        });
    }

    function request(action, payload, method) {
        var options = {
            method: method || 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'Cache-Control': 'no-store' },
            cache: 'no-store'
        };
        if (options.method === 'GET') {
            var query = new URLSearchParams(Object.assign({ action: action }, payload || {}));
            delete options.headers['Content-Type'];
            return fetch(API_URL + '?' + query.toString(), options).then(parseResponse);
        }
        options.body = JSON.stringify(Object.assign({ action: action }, payload || {}));
        return fetch(API_URL, options).then(parseResponse);
    }

    function parseResponse(response) {
        return response.json().catch(function () { return { success: false, error: 'Resposta inválida da Central de Notificações.' }; })
            .then(function (data) {
                if (!response.ok || !data.success) throw new Error(data.error || 'Não foi possível concluir a operação.');
                return data.data || {};
            });
    }

    function showMessage(text, type) {
        if (typeof showToast === 'function') {
            showToast(text, type === 'error' ? 'error' : 'success');
            return;
        }
        if (type === 'error' && typeof showError === 'function') {
            showError(text);
            return;
        }
        window.alert(text);
    }

    function notificationIcon(category) {
        var icons = {
            credit_pending: '⏳', credit_approved: '✓', credit_low: '!', plan: '▣', team: '◉', account: '●', reengagement: '↗', system: '◆', general: '•'
        };
        return icons[category] || icons.general;
    }

    function priorityClass(priority) {
        return priority === 'high'
            ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-950/30'
            : 'border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800';
    }

    function formatDate(value) {
        if (!value) return '';
        var date = new Date(String(value).replace(' ', 'T') + (String(value).includes('Z') ? '' : 'Z'));
        if (isNaN(date.getTime())) return String(value);
        return date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
    }

    function renderItems(container) {
        var list = container.querySelector('#internal-notifications-list');
        if (!list) return;
        if (!state.items.length) {
            list.innerHTML = '<div class="py-16 px-6 text-center rounded-[2rem] border-2 border-dashed border-slate-200 dark:border-slate-700"><div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-300">✓</div><h3 class="text-base font-black text-slate-900 dark:text-slate-100">Nenhuma notificação por aqui</h3><p class="mt-2 text-sm font-medium text-slate-500 dark:text-slate-400">Os comunicados permitidos para a sua conta aparecerão neste espaço.</p></div>';
            return;
        }
        list.innerHTML = state.items.map(function (item) {
            var unread = !item.read_at;
            return '<article class="notification-item rounded-[1.5rem] border p-5 transition-colors ' + priorityClass(item.priority) + (unread ? ' ring-1 ring-blue-100 dark:ring-blue-900/40' : ' opacity-80') + '" data-notification-id="' + Number(item.id) + '">' +
                '<div class="flex items-start gap-4"><div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl ' + (item.priority === 'high' ? 'bg-amber-200 text-amber-900 dark:bg-amber-900 dark:text-amber-100' : 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200') + ' font-black">' + notificationIcon(item.category) + '</div>' +
                '<div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-black text-slate-900 dark:text-slate-100">' + escapeHtml(item.title) + '</h3>' +
                (unread ? '<span class="rounded-full bg-blue-600 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-white">Nova</span>' : '') +
                (item.priority === 'high' ? '<span class="rounded-full bg-amber-600 px-2 py-0.5 text-[9px] font-black uppercase tracking-wide text-white">Importante</span>' : '') +
                '</div><p class="mt-2 whitespace-pre-line break-words text-sm leading-6 text-slate-600 dark:text-slate-300">' + escapeHtml(item.message) + '</p>' +
                '<p class="mt-3 text-[10px] font-bold uppercase tracking-wide text-slate-400 dark:text-slate-500">' + escapeHtml(item.created_by_label || 'Sistema') + ' · ' + formatDate(item.created_at) + '</p></div>' +
                (unread ? '<button type="button" class="btn-mark-notification-read shrink-0 rounded-xl px-3 py-2 text-[10px] font-black text-blue-700 hover:bg-blue-100 dark:text-blue-300 dark:hover:bg-blue-950" data-id="' + Number(item.id) + '">Marcar como lida</button>' : '') +
                '</div></article>';
        }).join('');
        list.querySelectorAll('.btn-mark-notification-read').forEach(function (button) {
            button.addEventListener('click', function () { markRead(Number(button.dataset.id), container); });
        });
    }

    function renderCompose(container) {
        var host = container.querySelector('#notification-compose-host');
        if (!host) return;
        if (!state.canCompose) {
            host.innerHTML = '<div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5 text-sm font-medium text-slate-500 dark:border-slate-700 dark:bg-slate-800/70 dark:text-slate-400">A emissão de comunicados é autorizada somente para administradores, respeitando a empresa de cada conta.</div>';
            return;
        }
        host.innerHTML = '<details id="notification-compose" class="rounded-[1.5rem] border border-blue-100 bg-blue-50/70 p-5 dark:border-blue-900/60 dark:bg-blue-950/20"><summary class="cursor-pointer list-none text-sm font-black text-blue-900 dark:text-blue-100"><span class="mr-2 inline-flex h-6 w-6 items-center justify-center rounded-lg bg-blue-600 text-white">+</span>Enviar comunicado interno</summary><form id="notification-compose-form" class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2"><label class="block text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">Destinatário<select id="notification-recipient-scope" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-800 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></select></label><label id="notification-tenant-wrap" class="block text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">Empresa<select id="notification-tenant" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-800 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></select></label><label id="notification-profile-wrap" class="hidden text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">Perfil<select id="notification-profile" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-800 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"><option value="">Selecione</option><option value="admin">Administrador</option><option value="supervisor">Supervisor</option><option value="manager">Gerente</option><option value="gerente">Gerente</option><option value="normal">Usuário</option><option value="user">Usuário</option></select></label><label id="notification-user-wrap" class="hidden text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">Usuário<select id="notification-user" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-800 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"></select></label><label class="block text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">Tipo<select id="notification-category" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-800 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"><option value="general">Comunicado geral</option><option value="credit_pending">Créditos aguardando</option><option value="credit_approved">Créditos liberados</option><option value="credit_low">Créditos próximos do limite</option><option value="plan">Plano</option><option value="team">Equipe</option><option value="account">Conta</option><option value="system">Sistema</option></select></label><label class="block text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">Prioridade<select id="notification-priority" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-800 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100"><option value="normal">Normal</option><option value="high">Importante</option><option value="low">Baixa</option></select></label><label class="block md:col-span-2 text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">Título<input id="notification-title" maxlength="160" required class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-bold text-slate-800 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="Ex.: Solicitação de créditos recebida"></label><label class="block md:col-span-2 text-xs font-black uppercase tracking-wide text-slate-500 dark:text-slate-300">Mensagem<textarea id="notification-message" maxlength="5000" required rows="4" class="mt-2 w-full resize-y rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm font-medium text-slate-800 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" placeholder="Escreva a orientação que o destinatário precisa receber."></textarea></label><div class="md:col-span-2 flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-between"><p class="text-xs font-medium leading-5 text-slate-500 dark:text-slate-400">O comunicado fica registrado na caixa de entrada autorizada. Dados de login, pesquisa e crédito não são armazenados no navegador.</p><button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-xs font-black text-white shadow-lg shadow-blue-600/20 hover:bg-blue-700">Enviar comunicado</button></div></form></details>';
        var details = host.querySelector('details');
        details.addEventListener('toggle', function () { if (details.open) loadDirectory(container); });
    }

    function profileLabel(profile) {
        return { super_admin: 'Super Admin', admin: 'Administrador', supervisor: 'Supervisor', manager: 'Gerente', gerente: 'Gerente', normal: 'Usuário', user: 'Usuário' }[profile] || profile;
    }

    function loadDirectory(container) {
        if (state.directory) return bindComposeForm(container);
        request('directory', {}, 'GET').then(function (data) {
            state.directory = data;
            bindComposeForm(container);
        }).catch(function (error) { showMessage(error.message, 'error'); });
    }

    function bindComposeForm(container) {
        var form = container.querySelector('#notification-compose-form');
        if (!form || !state.directory) return;
        var scopeEl = form.querySelector('#notification-recipient-scope');
        var tenantEl = form.querySelector('#notification-tenant');
        var userEl = form.querySelector('#notification-user');
        var isSuper = state.directory.tenants && state.directory.tenants.length > 0;
        scopeEl.innerHTML = (isSuper ? '<option value="platform">Toda a plataforma</option><option value="profile">Um perfil em toda a plataforma</option><option value="tenant">Uma empresa</option><option value="tenant_profile">Um perfil dentro de uma empresa</option>' : '<option value="tenant">Toda a minha empresa</option><option value="tenant_profile">Um perfil da minha empresa</option>') + '<option value="user">Um usuário específico</option>';
        tenantEl.innerHTML = isSuper ? '<option value="">Selecione a empresa</option>' + state.directory.tenants.map(function (tenant) { return '<option value="' + Number(tenant.id) + '">' + escapeHtml(tenant.name) + '</option>'; }).join('') : '<option value="">Minha empresa</option>';
        userEl.innerHTML = '<option value="">Selecione o usuário</option>' + state.directory.users.map(function (user) { return '<option value="' + Number(user.id) + '">' + escapeHtml(user.name || user.email) + ' — ' + escapeHtml(profileLabel(user.profile)) + '</option>'; }).join('');
        function updateRecipientFields() {
            var scope = scopeEl.value;
            form.querySelector('#notification-tenant-wrap').classList.toggle('hidden', !isSuper || !['tenant', 'tenant_profile'].includes(scope));
            form.querySelector('#notification-profile-wrap').classList.toggle('hidden', !['profile', 'tenant_profile'].includes(scope));
            form.querySelector('#notification-user-wrap').classList.toggle('hidden', scope !== 'user');
        }
        scopeEl.onchange = updateRecipientFields;
        updateRecipientFields();
        form.onsubmit = function (event) {
            event.preventDefault();
            var button = form.querySelector('button[type="submit"]');
            button.disabled = true;
            button.textContent = 'Enviando…';
            request('create', {
                recipientScope: scopeEl.value,
                targetTenantId: tenantEl.value,
                targetProfile: form.querySelector('#notification-profile').value,
                targetUserId: userEl.value,
                category: form.querySelector('#notification-category').value,
                priority: form.querySelector('#notification-priority').value,
                title: form.querySelector('#notification-title').value.trim(),
                message: form.querySelector('#notification-message').value.trim()
            }).then(function () {
                showMessage('Comunicado enviado com sucesso.');
                form.reset();
                state.directory = null;
                loadNotifications(container);
            }).catch(function (error) { showMessage(error.message, 'error'); }).finally(function () {
                button.disabled = false;
                button.textContent = 'Enviar comunicado';
            });
        };
    }

    function loadNotifications(container) {
        var list = container.querySelector('#internal-notifications-list');
        if (list) list.innerHTML = '<div class="py-12 text-center text-sm font-bold text-slate-400">Carregando notificações…</div>';
        request('list', { filter: state.filter, limit: 50 }, 'GET').then(function (data) {
            state.items = data.items || [];
            state.canCompose = !!data.canCompose;
            renderItems(container);
            renderCompose(container);
            refreshBadge();
        }).catch(function (error) {
            if (list) list.innerHTML = '<div class="rounded-2xl border border-red-200 bg-red-50 p-5 text-sm font-bold text-red-700 dark:border-red-900 dark:bg-red-950/30 dark:text-red-200">' + escapeHtml(error.message) + '</div>';
        });
    }

    function markRead(notificationId, container) {
        request('markRead', { notificationId: notificationId }).then(function () {
            state.items = state.items.map(function (item) { return Number(item.id) === notificationId ? Object.assign({}, item, { read_at: 'now' }) : item; });
            renderItems(container);
            refreshBadge();
        }).catch(function (error) { showMessage(error.message, 'error'); });
    }

    function refreshBadge() {
        if (!window.AppState || !window.AppState.user) return;
        request('count', {}, 'GET').then(function (data) {
            var unread = Number(data.unread || 0);
            ['notifications-inbox-badge', 'notifications-header-badge'].forEach(function (id) {
                var badge = document.getElementById(id);
                if (!badge) return;
                badge.textContent = unread > 99 ? '99+' : String(unread);
                badge.classList.toggle('hidden', unread < 1);
            });
        }).catch(function () { /* A ausência da instalação não deve quebrar o painel. */ });
    }

    function renderInboxTab(contentArea) {
        state.opened = true;
        contentArea.innerHTML = '<section class="mx-auto max-w-5xl space-y-6 pb-8"><div class="flex flex-col gap-4 rounded-[2rem] bg-slate-900 p-6 text-white shadow-xl sm:flex-row sm:items-center sm:justify-between"><div><p class="text-[10px] font-black uppercase tracking-[0.18em] text-blue-300">Central de notificações</p><h2 class="mt-1 text-2xl font-black tracking-tight">Sua caixa de entrada</h2><p class="mt-2 max-w-2xl text-sm font-medium leading-6 text-slate-300">Avisos sobre créditos, plano, equipe, conta e comunicados permitidos para o seu perfil.</p></div><div class="flex gap-2"><button type="button" id="btn-mark-all-notifications-read" class="rounded-xl border border-slate-600 px-4 py-3 text-xs font-black text-white hover:bg-slate-800">Marcar todas como lidas</button><button type="button" id="btn-refresh-notifications" class="rounded-xl bg-blue-500 px-4 py-3 text-xs font-black text-white hover:bg-blue-400">Atualizar</button></div></div><div class="flex flex-wrap items-center gap-2"><button type="button" class="notification-filter rounded-full bg-blue-600 px-4 py-2 text-xs font-black text-white" data-filter="all">Todas</button><button type="button" class="notification-filter rounded-full bg-slate-100 px-4 py-2 text-xs font-black text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700" data-filter="unread">Não lidas</button></div><div id="notification-compose-host"></div><div id="internal-notifications-list" class="space-y-3"></div></section>';
        contentArea.querySelector('#btn-refresh-notifications').addEventListener('click', function () { loadNotifications(contentArea); });
        contentArea.querySelector('#btn-mark-all-notifications-read').addEventListener('click', function () {
            request('markAllRead').then(function () { showMessage('Notificações marcadas como lidas.'); loadNotifications(contentArea); }).catch(function (error) { showMessage(error.message, 'error'); });
        });
        contentArea.querySelectorAll('.notification-filter').forEach(function (button) {
            button.addEventListener('click', function () {
                state.filter = button.dataset.filter;
                contentArea.querySelectorAll('.notification-filter').forEach(function (filterButton) {
                    var selected = filterButton === button;
                    filterButton.className = 'notification-filter rounded-full px-4 py-2 text-xs font-black ' + (selected ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700');
                });
                loadNotifications(contentArea);
            });
        });
        loadNotifications(contentArea);
    }

    function bindGlobalButtons() {
        var button = document.getElementById('btn-notifications-inbox');
        if (button && !button.dataset.bound) {
            button.dataset.bound = 'true';
            button.addEventListener('click', function () {
                if (typeof window.setActiveTab === 'function') window.setActiveTab('notifications');
            });
        }
    }

    window.renderInboxTab = renderInboxTab;
    window.NotificationCenter = { refreshBadge: refreshBadge, renderInboxTab: renderInboxTab };
    document.addEventListener('DOMContentLoaded', bindGlobalButtons);
    if (document.readyState !== 'loading') bindGlobalButtons();
}());
