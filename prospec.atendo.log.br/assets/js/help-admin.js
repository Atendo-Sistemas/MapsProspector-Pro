/**
 * Maps Prospector — Editor administrativo da Central de Ajuda v20260818-46.
 * A interface só é invocada pelo botão exibido ao super admin dentro da Ajuda.
 */
(function () {
    'use strict';

    var SECTIONS = [
        { key: 'meta', label: 'Cabeçalho do guia', description: 'Título e introdução apresentados no topo da Central de Ajuda.' },
        { key: 'overview', label: 'Primeiros passos', description: 'Orientações iniciais do perfil.' },
        { key: 'research', label: 'Pesquisas e exportação', description: 'Uso dos módulos de pesquisa e exportação.' },
        { key: 'routine', label: 'Rotina do perfil', description: 'Tarefas, permissões e boas práticas do perfil.' }
    ];
    var PROFILES = [
        { key: 'super_admin', label: 'Administrador da plataforma' },
        { key: 'admin', label: 'Administrador da empresa' },
        { key: 'supervisor', label: 'Supervisor' },
        { key: 'user', label: 'Usuário' }
    ];

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function notify(message, type) {
        if (typeof showToast !== 'undefined') {
            showToast(message, type || 'info');
            return;
        }
        window.alert(message);
    }

    function request(url, options) {
        return fetch(url, Object.assign({ credentials: 'same-origin', headers: { 'Accept': 'application/json' } }, options || {}))
            .then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (payload) {
                    if (!response.ok || !payload.success) throw new Error(payload.error || 'Não foi possível concluir a operação.');
                    return payload;
                });
            });
    }

    function selectorOptions(items, selected) {
        return items.map(function (item) {
            return '<option value="' + escapeHtml(item.key) + '"' + (item.key === selected ? ' selected' : '') + '>' + escapeHtml(item.label) + '</option>';
        }).join('');
    }

    function sectionLabel(key) {
        var section = SECTIONS.find(function (item) { return item.key === key; });
        return section ? section.label : key;
    }

    function emptyItem(profile, section) {
        return { profile: profile, section: section, heading: '', body: '', imageUrl: '', imageAlt: '', replaceDefault: false, isPublished: false, isUnsaved: true };
    }

    function openHelpCenterManager(contentArea, returnToHelp) {
        if (!contentArea) return;
        var state = { profile: 'super_admin', section: 'meta', items: {}, defaults: {}, sharedResearchGuide: {}, csrfToken: '', loading: false };

        function currentItem() {
            var existing = state.items[state.section];
            if (existing && !existing.isUnsaved) return existing;
            var item = existing || emptyItem(state.profile, state.section);
            if (item.isUnsaved) {
                var profileDefault = state.defaults || {};
                if (state.section === 'meta') {
                    if (!item.heading && profileDefault.meta && String(profileDefault.meta.heading || '').trim()) item.heading = profileDefault.meta.heading;
                    if (!item.body && profileDefault.intro && String(profileDefault.intro || '').trim()) item.body = profileDefault.intro;
                } else if (state.section === 'research') {
                    var researchDefault = profileDefault.sections && profileDefault.sections.research;
                    if (!researchDefault && state.sharedResearchGuide) researchDefault = state.sharedResearchGuide;
                    if (researchDefault) {
                        if (!item.heading && researchDefault.title) item.heading = researchDefault.title;
                        if (!item.body && researchDefault.body) item.body = researchDefault.body;
                    }
                } else {
                    var sectionDefault = profileDefault.sections && profileDefault.sections[state.section];
                    if (sectionDefault) {
                        if (!item.heading && sectionDefault.title) item.heading = sectionDefault.title;
                        if (!item.body && sectionDefault.body) item.body = sectionDefault.body;
                    }
                }
            }
            return item;
        }

        function render() {
            var item = currentItem();
            var profileButtons = PROFILES.map(function (profile) {
                var active = state.profile === profile.key;
                return '<button type="button" data-help-profile="' + profile.key + '" class="rounded-xl border px-3 py-2 text-xs font-black transition ' + (active ? 'border-blue-600 bg-blue-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200') + '">' + escapeHtml(profile.label) + '</button>';
            }).join('');
            var sectionButtons = SECTIONS.map(function (section) {
                var active = state.section === section.key;
                return '<button type="button" data-help-section-edit="' + section.key + '" class="w-full rounded-xl border px-4 py-3 text-left transition ' + (active ? 'border-blue-600 bg-blue-50 text-blue-800 dark:border-blue-500 dark:bg-blue-950/30 dark:text-blue-200' : 'border-slate-200 bg-white text-slate-600 hover:border-blue-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200') + '"><span class="block text-sm font-black">' + escapeHtml(section.label) + '</span><span class="mt-1 block text-xs font-medium opacity-75">' + escapeHtml(section.description) + '</span></button>';
            }).join('');
            var imagePreview = item.imageUrl
                ? '<figure class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700"><img src="' + escapeHtml(item.imageUrl) + '" alt="' + escapeHtml(item.imageAlt || 'Prévia da imagem') + '" class="w-full max-h-64 object-contain bg-slate-50 dark:bg-slate-900"><figcaption class="px-3 py-2 text-xs text-slate-500">Imagem que será publicada nesta seção.</figcaption></figure>'
                : '<div class="rounded-xl border-2 border-dashed border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-900/50 p-4 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Nenhuma imagem personalizada. O guia padrão continuará mostrando o espaço reservado existente, quando houver.</div>';
            var metaTip = state.section === 'meta'
                ? '<p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">Para o cabeçalho, o título e a introdução substituem os textos padrão. Os demais campos permitem complementar ou substituir cada seção.</p>'
                : '';
            var unpublishedBanner = item.isUnsaved
                ? '<div class="mt-4 flex items-center gap-3 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 dark:border-slate-600 dark:bg-slate-900/60 dark:text-slate-300"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="h-4 w-4 shrink-0 text-slate-400"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z"/></svg><p><strong>Ainda não há conteúdo salvo nesta seção.</strong> Preencha o título e o texto abaixo e clique em "Salvar alteração" para publicar. Enquanto não houver conteúdo salvo, os visitantes verão o guia padrão.</p></div>'
                : '';

            contentArea.innerHTML = '<div class="max-w-7xl mx-auto space-y-6 pb-20">'
                + '<section class="rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800">'
                + '<div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"><div><p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600 dark:text-blue-300">Administrador da plataforma</p><h2 class="mt-2 text-2xl font-black tracking-tight text-slate-900 dark:text-slate-100">Gerenciar Central de Ajuda</h2><p class="mt-2 max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">Edite textos e imagens por perfil. O conteúdo padrão permanece disponível quando uma personalização é removida ou não publicada.</p></div><button id="help-editor-back" type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-black text-slate-700 hover:border-blue-300 hover:text-blue-700 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-100">Voltar para Ajuda</button></div>'
                + '<div class="mt-5 flex flex-wrap gap-2">' + profileButtons + '</div></section>'
                + '<div class="grid gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]"><aside class="space-y-2">' + sectionButtons + '</aside>'
                + '<section class="rounded-[2rem] border border-slate-200 bg-white p-5 sm:p-7 shadow-sm dark:border-slate-700 dark:bg-slate-800">'
                + '<div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between"><div><p class="text-xs font-black uppercase tracking-wider text-blue-600 dark:text-blue-300">' + escapeHtml(PROFILES.find(function (profile) { return profile.key === state.profile; }).label) + '</p><h3 class="mt-1 text-xl font-black text-slate-900 dark:text-slate-100">' + escapeHtml(sectionLabel(state.section)) + '</h3></div><span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-black ' + (item.isUnsaved ? 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' : (item.isPublished ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300')) + '">' + (item.isUnsaved ? 'Sem conteúdo salvo' : (item.isPublished ? 'Publicado' : 'Rascunho')) + '</span></div>'
                + unpublishedBanner
                + (item.isUnsaved && state.defaults ? '<div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 dark:border-blue-800/70 dark:bg-blue-950/30"><p class="text-xs font-black uppercase tracking-wider text-blue-700 dark:text-blue-300">Informações existentes do guia</p><p class="mt-1 text-xs leading-relaxed text-blue-900/80 dark:text-blue-100/80">Os campos abaixo já estão preenchidos com o conteúdo atual do guia deste perfil. Edite como quiser e clique em "Salvar alteração" — o que for salvo passa a ser o conteúdo exibido para os visitantes deste perfil.</p></div>' : '')
                + metaTip
                + '<form id="help-editor-form" class="mt-6 space-y-5">'
                + '<label class="block"><span class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Título</span><input id="help-editor-heading" maxlength="200" value="' + escapeHtml(item.heading) + '" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-900 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" placeholder="Título do tópico ou do cabeçalho"></label>'
                + '<label class="block"><span class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Texto</span><textarea id="help-editor-body" maxlength="12000" rows="9" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm leading-relaxed text-slate-900 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" placeholder="Escreva orientações claras para este perfil. Quebras de linha serão preservadas.">' + escapeHtml(item.body) + '</textarea></label>'
                + '<div class="rounded-2xl border border-slate-200 p-4 dark:border-slate-700"><div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"><div><p class="text-sm font-black text-slate-900 dark:text-slate-100">Imagem da seção</p><p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">PNG, JPEG ou WEBP de até 5 MB. Não envie imagens com dados pessoais, credenciais, tokens ou informações sensíveis.</p></div><label class="inline-flex cursor-pointer items-center justify-center rounded-xl bg-blue-600 px-4 py-2.5 text-xs font-black text-white hover:bg-blue-700"><input id="help-editor-image-file" type="file" accept="image/png,image/jpeg,image/webp" class="hidden">Enviar imagem</label></div><div id="help-editor-image-preview" class="mt-4">' + imagePreview + '</div><label class="mt-4 block"><span class="text-xs font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">Texto alternativo da imagem</span><input id="help-editor-image-alt" maxlength="255" value="' + escapeHtml(item.imageAlt) + '" class="mt-2 w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-blue-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100" placeholder="Descreva a imagem para acessibilidade"></label>'
                + (item.imageUrl ? '<button id="help-editor-remove-image" type="button" class="mt-3 text-xs font-black text-rose-600 hover:text-rose-700">Remover imagem desta seção</button>' : '') + '</div>'
                + (state.section === 'meta' ? '' : '<label class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-200"><input id="help-editor-replace" type="checkbox" class="mt-0.5 h-4 w-4 accent-blue-600"' + (item.replaceDefault ? ' checked' : '') + '><span><strong>Substituir o guia padrão desta seção.</strong><br><span class="text-xs leading-relaxed text-slate-500 dark:text-slate-400">Desmarcado: o conteúdo abaixo será exibido depois da orientação padrão. Marcado: será exibido somente este conteúdo personalizado.</span></span></label>')
                + '<label class="flex items-center gap-3 text-sm font-semibold text-slate-700 dark:text-slate-200"><input id="help-editor-published" type="checkbox" class="h-4 w-4 accent-blue-600"' + (item.isPublished ? ' checked' : '') + '> Publicar para o perfil selecionado</label>'
                + '<div class="flex flex-col-reverse gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-slate-700"><button id="help-editor-reset" type="button" class="text-sm font-black text-rose-600 hover:text-rose-700">Restaurar guia padrão</button><button type="submit" class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-black text-white shadow-sm transition hover:bg-blue-700">Salvar alteração</button></div>'
                + '</form></section></div></div>';

            bindEvents();
        }

        function reloadProfile() {
            state.loading = true;
            request('api/help-center.php?action=manage&profile=' + encodeURIComponent(state.profile))
                .then(function (payload) {
                    state.items = (payload.data && payload.data.items) || {};
                    state.defaults = (payload.data && payload.data.defaults) || {};
                    state.sharedResearchGuide = (payload.data && payload.data.sharedResearchGuide) || {};
                    state.csrfToken = (payload.data && payload.data.csrfToken) || '';
                    state.loading = false;
                    render();
                })
                .catch(function (error) {
                    state.loading = false;
                    contentArea.innerHTML = '<div class="max-w-3xl mx-auto rounded-2xl border border-rose-200 bg-rose-50 p-6 text-rose-800 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-100"><p class="font-black">Não foi possível abrir o editor.</p><p class="mt-2 text-sm">' + escapeHtml(error.message) + '</p><button id="help-editor-back-error" type="button" class="mt-4 rounded-xl bg-white px-4 py-2 text-sm font-black text-rose-700">Voltar</button></div>';
                    var back = contentArea.querySelector('#help-editor-back-error');
                    if (back) back.addEventListener('click', function () { returnToHelp(contentArea); });
                });
        }

        function bindEvents() {
            var back = contentArea.querySelector('#help-editor-back');
            if (back) back.addEventListener('click', function () { returnToHelp(contentArea); });
            contentArea.querySelectorAll('[data-help-profile]').forEach(function (button) {
                button.addEventListener('click', function () {
                    state.profile = button.dataset.helpProfile;
                    state.section = 'meta';
                    reloadProfile();
                });
            });
            contentArea.querySelectorAll('[data-help-section-edit]').forEach(function (button) {
                button.addEventListener('click', function () {
                    state.section = button.dataset.helpSectionEdit;
                    render();
                });
            });
            var imageFile = contentArea.querySelector('#help-editor-image-file');
            if (imageFile) imageFile.addEventListener('change', function () {
                var file = imageFile.files && imageFile.files[0];
                if (!file) return;
                if (file.size > 5 * 1024 * 1024) { notify('A imagem deve ter no máximo 5 MB.', 'error'); imageFile.value = ''; return; }
                var formData = new FormData();
                formData.append('action', 'uploadImage');
                formData.append('csrfToken', state.csrfToken);
                formData.append('image', file);
                request('api/help-center.php', { method: 'POST', body: formData, headers: { 'Accept': 'application/json' } })
                    .then(function (payload) {
                        var item = currentItem();
                        item.imageUrl = payload.data.imageUrl;
                        state.items[state.section] = item;
                        render();
                        notify('Imagem enviada. Salve a seção para publicá-la.', 'success');
                    })
                    .catch(function (error) { notify(error.message, 'error'); });
            });
            var removeImage = contentArea.querySelector('#help-editor-remove-image');
            if (removeImage) removeImage.addEventListener('click', function () {
                var item = currentItem();
                item.imageUrl = '';
                item.imageAlt = '';
                state.items[state.section] = item;
                render();
            });
            var form = contentArea.querySelector('#help-editor-form');
            if (form) form.addEventListener('submit', function (event) {
                event.preventDefault();
                var existing = currentItem();
                var payload = {
                    action: 'save',
                    csrfToken: state.csrfToken,
                    profile: state.profile,
                    section: state.section,
                    heading: contentArea.querySelector('#help-editor-heading').value.trim(),
                    body: contentArea.querySelector('#help-editor-body').value.trim(),
                    imageUrl: existing.imageUrl || '',
                    imageAlt: contentArea.querySelector('#help-editor-image-alt').value.trim(),
                    replaceDefault: !!(contentArea.querySelector('#help-editor-replace') && contentArea.querySelector('#help-editor-replace').checked),
                    isPublished: !!contentArea.querySelector('#help-editor-published').checked
                };
                request('api/help-center.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload) })
                    .then(function (response) {
                        state.items[state.section] = response.data.item;
                        render();
                        notify('Conteúdo da ajuda salvo.', 'success');
                    })
                    .catch(function (error) { notify(error.message, 'error'); });
            });
            var reset = contentArea.querySelector('#help-editor-reset');
            if (reset) reset.addEventListener('click', function () {
                if (!window.confirm('Remover esta personalização e voltar ao guia padrão?')) return;
                request('api/help-center.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ action: 'delete', csrfToken: state.csrfToken, profile: state.profile, section: state.section }) })
                    .then(function (response) {
                        delete state.items[state.section];
                        render();
                        notify(response.message || 'Guia padrão restaurado.', 'success');
                    })
                    .catch(function (error) { notify(error.message, 'error'); });
            });
        }

        reloadProfile();
    }

    window.openHelpCenterManager = openHelpCenterManager;
})();
