(function () {
    'use strict';

    function api() {
        return (typeof API_BASE !== 'undefined' ? API_BASE : '/api/') + 'banner-campaign.php';
    }

    async function request(url, options) {
        var response = await fetch(url, Object.assign({ credentials: 'same-origin', cache: 'no-store' }, options || {}));
        var payload = await response.json();
        if (!response.ok || !payload.success) throw new Error(payload.error || 'Não foi possível carregar a campanha.');
        return payload.data;
    }

    function render(host, page) {
        if (!host) return;
        request(api() + '?page=' + encodeURIComponent(page)).then(function (campaign) {
            if (!campaign || !host.isConnected) return;
            var aside = document.createElement('aside');
            aside.className = 'my-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-800';
            aside.setAttribute('aria-label', 'Publicidade');
            var label = document.createElement('span');
            label.className = 'block px-4 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-500';
            label.textContent = 'Publicidade';
            var image = document.createElement('img');
            image.src = campaign.image;
            image.alt = campaign.description;
            image.referrerPolicy = 'no-referrer';
            image.className = 'block max-h-48 w-full object-contain';
            image.addEventListener('error', function () { aside.remove(); });
            aside.appendChild(label);
            if (campaign.link) {
                var link = document.createElement('a');
                link.href = campaign.link;
                link.target = '_blank';
                link.rel = 'noopener noreferrer sponsored';
                link.appendChild(image);
                aside.appendChild(link);
            } else {
                aside.appendChild(image);
            }
            host.appendChild(aside);
        }).catch(function (error) { console.error('Banner:', error.message); });
    }

    function admin(host) {
        if (!host) return;
        var section = document.createElement('section');
        section.className = 'max-w-4xl mx-auto my-8 rounded-[2rem] border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-700 dark:bg-slate-800';
        section.innerHTML = '<h3 class="text-xl font-black">Banner de publicidade</h3>' +
            '<p class="mt-2 text-sm text-slate-500">Uma campanha para as páginas selecionadas. Imagens externas são carregadas diretamente pelo navegador; o servidor da imagem pode receber o IP do visitante.</p>' +
            '<form id="banner-campaign-form" class="mt-5 space-y-4">' +
            '<label class="flex items-center gap-2 text-sm font-bold"><input name="enabled" type="checkbox"> Ativar campanha</label>' +
            '<label class="block text-sm font-bold">URL HTTPS da imagem<input name="image" type="url" maxlength="2048" class="mt-1 w-full rounded-xl border p-3 dark:bg-slate-700"></label>' +
            '<label class="block text-sm font-bold">Link HTTPS de destino (opcional)<input name="link" type="url" maxlength="2048" class="mt-1 w-full rounded-xl border p-3 dark:bg-slate-700"></label>' +
            '<label class="block text-sm font-bold">Descrição acessível da imagem<input name="description" maxlength="160" class="mt-1 w-full rounded-xl border p-3 dark:bg-slate-700"></label>' +
            '<div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-bold">Início (opcional)<input name="start" type="date" class="mt-1 w-full rounded-xl border p-3 dark:bg-slate-700"></label>' +
            '<label class="block text-sm font-bold">Fim (opcional)<input name="end" type="date" class="mt-1 w-full rounded-xl border p-3 dark:bg-slate-700"></label></div>' +
            '<fieldset><legend class="text-sm font-bold">Páginas</legend><div class="mt-2 flex flex-wrap gap-4 text-sm">' +
            '<label><input type="checkbox" name="pages" value="maps"> Maps</label><label><input type="checkbox" name="pages" value="linkedin"> LinkedIn</label>' +
            '<label><input type="checkbox" name="pages" value="cnpj"> CNPJ</label><label><input type="checkbox" name="pages" value="history"> Histórico</label></div></fieldset>' +
            '<p class="text-xs text-slate-500">As datas usam o horário do servidor e incluem o dia final.</p><button type="submit" disabled class="rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white disabled:opacity-50">Salvar banner</button>' +
            '<p role="status" class="banner-feedback text-sm"></p></form>';
        host.appendChild(section);
        var form = section.querySelector('form');
        var feedback = section.querySelector('.banner-feedback');
        var button = form.querySelector('button');
        request(api() + '?action=edit').then(function (campaign) {
            form.elements.enabled.checked = campaign.enabled;
            ['image', 'link', 'description', 'start', 'end'].forEach(function (name) { form.elements[name].value = campaign[name]; });
            form.querySelectorAll('input[name="pages"]').forEach(function (input) { input.checked = campaign.pages.includes(input.value); });
            button.disabled = false;
        }).catch(function (error) { feedback.textContent = error.message; });
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            button.disabled = true;
            feedback.textContent = 'Salvando...';
            var payload = { enabled: form.elements.enabled.checked, pages: Array.from(form.querySelectorAll('input[name="pages"]:checked'), function (input) { return input.value; }) };
            ['image', 'link', 'description', 'start', 'end'].forEach(function (name) { payload[name] = form.elements[name].value.trim(); });
            try {
                await request(api(), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                feedback.textContent = 'Banner salvo com sucesso.';
            } catch (error) {
                feedback.textContent = error.message;
            } finally {
                button.disabled = false;
            }
        });
    }

    window.BannerCampaign = { render: render, admin: admin };
}());
