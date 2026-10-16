/**
 * Maps Prospector — Central de Ajuda segmentada por perfil.
 * O arquivo é carregado após app.js e expõe renderHelpTab globalmente.
 *
 * Para acrescentar uma imagem, preencha `src` no objeto HELP_IMAGES abaixo
 * com a URL pública da captura desejada. Enquanto estiver vazio, a interface
 * exibe um espaço de imagem identificado, preservando o layout do tutorial.
 */
(function () {
    'use strict';

    var HELP_IMAGES = {
        'admin-dashboard': { src: '', alt: 'Visão do dashboard administrativo' },
        'admin-management': { src: '', alt: 'Exemplo da gestão de equipe e créditos' },
        'supervisor-dashboard': { src: '', alt: 'Visão do dashboard de supervisor' },
        'supervisor-team': { src: '', alt: 'Exemplo do acompanhamento da equipe' },
        'user-dashboard': { src: '', alt: 'Visão do dashboard individual' },
        'user-search': { src: '', alt: 'Exemplo de uma tela de prospecção' },
        'research-modules': { src: '', alt: 'Exemplo dos módulos de pesquisa' }
    };

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function getHelpProfile() {
        // AppState é declarado no app.js como variável global lexical. Em
        // navegadores modernos ele pode não existir como window.AppState,
        // portanto a referência direta mantém a segmentação de perfil ativa.
        var user = (typeof AppState !== 'undefined' && AppState && AppState.user) ? AppState.user : null;
        var profile = String(user && user.profile ? user.profile : '').toLowerCase();
        if (profile === 'super_admin') return 'super_admin';
        if (profile === 'admin') return 'admin';
        if (['supervisor', 'manager', 'gerente'].includes(profile)) return 'supervisor';
        return 'user';
    }

    function imageSlot(slotId, title, description) {
        var image = HELP_IMAGES[slotId] || { src: '', alt: title };
        if (image.src) {
            return '<figure class="overflow-hidden rounded-2xl border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/70">'
                + '<img src="' + escapeHtml(image.src) + '" alt="' + escapeHtml(image.alt || title) + '" class="w-full h-auto" loading="lazy">'
                + '<figcaption class="px-4 py-3 text-xs font-semibold text-slate-500 dark:text-slate-400">' + escapeHtml(title) + '</figcaption>'
                + '</figure>';
        }

        return '<figure class="help-image-slot rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-600 bg-slate-50/80 dark:bg-slate-800/60 p-5" data-help-image-slot="' + escapeHtml(slotId) + '">'
            + '<div class="flex flex-col sm:flex-row gap-4 sm:items-center">'
            + '<div class="w-11 h-11 shrink-0 rounded-xl bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-300 flex items-center justify-center">'
            + '<svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-7h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>'
            + '</div>'
            + '<div><p class="text-sm font-black text-slate-800 dark:text-slate-100">Imagem do tutorial: ' + escapeHtml(title) + '</p>'
            + '<p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">' + escapeHtml(description) + ' Para inserir a captura, preencha a URL de <code>HELP_IMAGES.' + slotId + '.src</code> neste arquivo.</p></div>'
            + '</div></figure>';
    }

    function callout(title, text, tone) {
        var styles = {
            blue: 'border-blue-200 bg-blue-50 text-blue-900 dark:border-blue-800/70 dark:bg-blue-950/30 dark:text-blue-100',
            amber: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-800/70 dark:bg-amber-950/30 dark:text-amber-100',
            emerald: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-800/70 dark:bg-emerald-950/30 dark:text-emerald-100'
        };
        return '<aside class="rounded-2xl border p-4 ' + (styles[tone] || styles.blue) + '">'
            + '<p class="text-xs font-black uppercase tracking-wider">' + escapeHtml(title) + '</p>'
            + '<p class="mt-1 text-sm leading-relaxed">' + escapeHtml(text) + '</p></aside>';
    }

    function step(number, title, text) {
        return '<li class="flex gap-3"><span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-black text-white">' + number + '</span>'
            + '<div><p class="text-sm font-black text-slate-800 dark:text-slate-100">' + escapeHtml(title) + '</p><p class="mt-0.5 text-sm leading-relaxed text-slate-600 dark:text-slate-400">' + escapeHtml(text) + '</p></div></li>';
    }

    function moduleCards() {
        return '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">'
            + '<article class="rounded-2xl border border-emerald-100 dark:border-emerald-900/50 bg-emerald-50/50 dark:bg-emerald-950/20 p-4"><p class="text-xs font-black uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Google Maps</p><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Encontre empresas por segmento e cidade. Use filtros para priorizar contatos com telefone, site e boa avaliação.</p></article>'
            + '<article class="rounded-2xl border border-violet-100 dark:border-violet-900/50 bg-violet-50/50 dark:bg-violet-950/20 p-4"><p class="text-xs font-black uppercase tracking-wider text-violet-700 dark:text-violet-300">Consulta CNPJ</p><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Consulte dados cadastrais com CNPJ completo e valide a situação da empresa antes de prospectar.</p></article>'
            + '<article class="rounded-2xl border border-blue-100 dark:border-blue-900/50 bg-blue-50/50 dark:bg-blue-950/20 p-4"><p class="text-xs font-black uppercase tracking-wider text-blue-700 dark:text-blue-300">LinkedIn</p><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Pesquise perfis por cargo, localidade e empresa de referência. O aceite de uso responsável é solicitado na primeira consulta.</p></article>'
            + '</div>';
    }

    var GUIDES = {
        super_admin: {
            label: 'Administrador da plataforma',
            eyebrow: 'Guia do administrador da plataforma',
            intro: 'Você acompanha empresas, créditos e configurações globais da operação. Este guia apresenta a rotina administrativa sem misturar funções de usuários de empresas.',
            overview: function () {
                return '<div class="space-y-6">'
                    + callout('Seu escopo', 'Você visualiza a operação da plataforma, empresas cadastradas, solicitações, planos e as configurações globais. Use cada módulo apenas para finalidades administrativas legítimas.', 'blue')
                    + '<ol class="space-y-4">'
                    + step('1', 'Comece pelo Dashboard', 'Acompanhe o total de empresas, usuários, pesquisas e créditos em uma visão consolidada.')
                    + step('2', 'Verifique Empresas e Solicitações', 'Analise cadastros, solicitações de créditos e demandas de plano antes de realizar alterações.')
                    + step('3', 'Configure integrações globais com cuidado', 'O webhook padrão da plataforma é usado somente quando a empresa e o usuário não possuem uma configuração própria.')
                    + '</ol>'
                    + imageSlot('admin-dashboard', 'Dashboard da plataforma', 'Inclua uma captura com os indicadores principais e o menu de Administração.')
                    + '</div>';
            },
            research: function () { return researchGuide(); },
            routine: function () {
                return '<div class="space-y-6">'
                    + '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">'
                    + '<article class="rounded-2xl border border-slate-200 dark:border-slate-700 p-5"><h4 class="font-black text-slate-900 dark:text-slate-100">Empresas, planos e créditos</h4><p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Use as abas administrativas para acompanhar empresas, analisar solicitações e aplicar decisões comerciais registradas.</p></article>'
                    + '<article class="rounded-2xl border border-slate-200 dark:border-slate-700 p-5"><h4 class="font-black text-slate-900 dark:text-slate-100">Configurações globais</h4><p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Mantenha o webhook padrão apenas como alternativa de fallback. Não use credenciais sensíveis em campos que sejam expostos no navegador.</p></article>'
                    + '</div>'
                    + callout('Controle de acesso', 'Não compartilhe a conta de super administrador. Ações administrativas devem ser realizadas somente por pessoas autorizadas.', 'amber')
                    + '</div>';
            }
        },
        admin: {
            label: 'Administrador da empresa',
            eyebrow: 'Guia do administrador',
            intro: 'Você administra a operação da sua empresa: acompanha a equipe, solicita créditos, consulta o plano e define a integração geral do CRM.',
            overview: function () {
                return '<div class="space-y-6">'
                    + '<ol class="space-y-4">'
                    + step('1', 'Confira a atividade da empresa', 'No Dashboard, acompanhe a atividade da equipe e os indicadores consolidados da sua empresa.')
                    + step('2', 'Organize sua equipe', 'Em Minha Equipe, cadastre ou ajuste integrantes de acordo com as responsabilidades de cada pessoa.')
                    + step('3', 'Defina o webhook da empresa', 'Use Configurações para salvar o destino geral do CRM. Um webhook pessoal do integrante, quando configurado, tem prioridade.')
                    + step('4', 'Acompanhe créditos e plano', 'Use Solicitar Créditos e Meu Plano para acompanhar consumo, pedidos e condições comerciais da empresa.')
                    + '</ol>'
                    + imageSlot('admin-dashboard', 'Dashboard da empresa', 'Inclua uma captura com a visão da atividade da equipe e o resumo de créditos.')
                    + '</div>';
            },
            research: function () { return researchGuide(); },
            routine: function () {
                return '<div class="space-y-6">'
                    + '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">'
                    + '<article class="rounded-2xl border border-slate-200 dark:border-slate-700 p-5"><h4 class="font-black text-slate-900 dark:text-slate-100">Minha Equipe</h4><p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Mantenha apenas membros ativos, atribua perfis compatíveis com a função e revise o uso pelos indicadores disponíveis.</p></article>'
                    + '<article class="rounded-2xl border border-slate-200 dark:border-slate-700 p-5"><h4 class="font-black text-slate-900 dark:text-slate-100">Integração CRM</h4><p class="mt-2 text-sm text-slate-600 dark:text-slate-400">O webhook da empresa é a alternativa padrão. Teste o recebimento no seu CRM após qualquer alteração de URL.</p></article>'
                    + '</div>'
                    + imageSlot('admin-management', 'Equipe, créditos e integração', 'Inclua uma captura que indique onde gerenciar pessoas, créditos e webhook da empresa.')
                    + callout('Boa prática', 'Evite exportar dados sem finalidade definida. Revise filtros, destino do webhook e permissões da equipe antes de enviar leads ao CRM.', 'emerald')
                    + '</div>';
            }
        },
        supervisor: {
            label: 'Supervisor',
            eyebrow: 'Guia do supervisor',
            intro: 'Você acompanha o trabalho da equipe e também pode realizar pesquisas. A gestão comercial, créditos e plano permanecem com o administrador da empresa.',
            overview: function () {
                return '<div class="space-y-6">'
                    + '<ol class="space-y-4">'
                    + step('1', 'Acompanhe a equipe no Dashboard', 'Use os indicadores para entender o volume de pesquisas e a atividade do time sob sua supervisão.')
                    + step('2', 'Use Minha Equipe quando necessário', 'Consulte os integrantes autorizados e acompanhe a rotina, sem alterar dados financeiros da empresa.')
                    + step('3', 'Configure seu destino pessoal', 'Em Configurações, você pode salvar somente o seu webhook pessoal para receber as próprias exportações.')
                    + '</ol>'
                    + imageSlot('supervisor-dashboard', 'Dashboard do supervisor', 'Inclua uma captura com os indicadores de equipe e a área de atividade.')
                    + '</div>';
            },
            research: function () { return researchGuide(); },
            routine: function () {
                return '<div class="space-y-6">'
                    + '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">'
                    + '<article class="rounded-2xl border border-slate-200 dark:border-slate-700 p-5"><h4 class="font-black text-slate-900 dark:text-slate-100">Acompanhar sem expor dados</h4><p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Use os dados agregados da equipe para orientar a operação. Evite compartilhar resultados de pesquisas fora da finalidade de trabalho.</p></article>'
                    + '<article class="rounded-2xl border border-slate-200 dark:border-slate-700 p-5"><h4 class="font-black text-slate-900 dark:text-slate-100">Quando pedir apoio</h4><p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Para créditos, plano, acesso comercial da empresa ou webhook geral, procure o administrador responsável.</p></article>'
                    + '</div>'
                    + imageSlot('supervisor-team', 'Acompanhamento da equipe', 'Inclua uma captura da visão de atividade de integrantes.')
                    + '</div>';
            }
        },
        user: {
            label: 'Usuário',
            eyebrow: 'Guia do usuário',
            intro: 'Você utiliza os módulos de pesquisa, acompanha sua própria atividade, consulta o histórico e pode configurar o destino pessoal das suas exportações.',
            overview: function () {
                return '<div class="space-y-6">'
                    + '<ol class="space-y-4">'
                    + step('1', 'Comece pelo Dashboard', 'Veja seu resumo de atividade e retome suas tarefas a partir do menu lateral.')
                    + step('2', 'Escolha o módulo de pesquisa', 'Google Maps localiza empresas; CNPJ consulta dados empresariais; LinkedIn encontra perfis profissionais por filtros.')
                    + step('3', 'Consulte o Histórico', 'Use o histórico para recuperar pesquisas anteriores e evitar repetir uma busca sem necessidade.')
                    + step('4', 'Configure seu webhook pessoal', 'Em Configurações, informe o seu destino somente se você tiver autorização para enviar os seus leads a um CRM ou automação.')
                    + '</ol>'
                    + imageSlot('user-dashboard', 'Dashboard individual', 'Inclua uma captura mostrando o dashboard pessoal e as opções de pesquisa.')
                    + '</div>';
            },
            research: function () { return researchGuide(); },
            routine: function () {
                return '<div class="space-y-6">'
                    + '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">'
                    + '<article class="rounded-2xl border border-slate-200 dark:border-slate-700 p-5"><h4 class="font-black text-slate-900 dark:text-slate-100">Antes de pesquisar</h4><p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Use termos específicos, escolha uma localidade apropriada e verifique se a pesquisa atende à finalidade comercial legítima.</p></article>'
                    + '<article class="rounded-2xl border border-slate-200 dark:border-slate-700 p-5"><h4 class="font-black text-slate-900 dark:text-slate-100">Antes de exportar</h4><p class="mt-2 text-sm text-slate-600 dark:text-slate-400">Revise os leads selecionados, confirme o destino e não exporte informações para canais pessoais ou não autorizados.</p></article>'
                    + '</div>'
                    + imageSlot('user-search', 'Pesquisa e resultado de leads', 'Inclua uma captura dos filtros e de uma lista de resultados selecionáveis.')
                    + callout('Precisa de acesso adicional?', 'Solicitações de créditos, plano e gestão de equipe devem ser encaminhadas ao administrador da sua empresa.', 'amber')
                    + '</div>';
            }
        }
    };

    function researchGuide() {
        return '<div class="space-y-6">'
            + '<p class="text-sm leading-relaxed text-slate-600 dark:text-slate-400">Os três módulos de pesquisa estão disponíveis para contas autenticadas. Use cada consulta de forma responsável, com filtros necessários e finalidade de prospecção legítima.</p>'
            + moduleCards()
            + imageSlot('research-modules', 'Módulos de pesquisa', 'Inclua uma captura do menu de pesquisas e dos campos de filtro principais.')
            + '<div class="rounded-2xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-5"><h4 class="font-black text-slate-900 dark:text-slate-100">Roteiro recomendado</h4><ol class="mt-4 space-y-3">'
            + step('1', 'Defina o público', 'Escolha segmento, cidade, cargo ou CNPJ de acordo com o tipo de lead necessário.')
            + step('2', 'Aplique filtros', 'Prefira critérios suficientes para reduzir resultados irrelevantes antes de consumir créditos ou exportar.')
            + step('3', 'Revise e use o histórico', 'Analise os resultados e consulte buscas anteriores antes de iniciar uma nova pesquisa semelhante.')
            + step('4', 'Exporte apenas o necessário', 'Envie somente os leads selecionados e confirme o webhook ou destino de integração antes de concluir.')
            + '</ol></div>'
            + callout('Uso responsável e privacidade', 'O aceite específico de uso responsável é exigido na primeira busca LinkedIn. Independentemente do módulo, trate dados de forma proporcional, segura e compatível com a finalidade declarada.', 'emerald')
            + '</div>';
    }

    function renderHelpTab(contentArea) {
        if (!contentArea) return;
        var guide = GUIDES[getHelpProfile()] || GUIDES.user;
        var profileLabel = guide.label;

        contentArea.innerHTML = '<div class="max-w-6xl mx-auto space-y-7 pb-20">'
            + '<section class="rounded-[2rem] border border-blue-100 dark:border-blue-900/50 bg-gradient-to-br from-blue-50 to-white dark:from-blue-950/30 dark:to-slate-900 p-6 sm:p-8">'
            + '<div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">'
            + '<div><p class="text-xs font-black uppercase tracking-[0.18em] text-blue-600 dark:text-blue-300">Central de Ajuda</p>'
            + '<h2 class="mt-2 text-3xl font-black tracking-tight text-slate-900 dark:text-slate-100">' + escapeHtml(guide.eyebrow) + '</h2>'
            + '<p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-300">' + escapeHtml(guide.intro) + '</p></div>'
            + '<div class="inline-flex items-center gap-2 rounded-full border border-blue-200 dark:border-blue-800 bg-white/90 dark:bg-slate-900/80 px-4 py-2 text-xs font-black text-blue-700 dark:text-blue-300"><span class="h-2 w-2 rounded-full bg-blue-500"></span> Perfil: ' + escapeHtml(profileLabel) + '</div>'
            + '</div></section>'
            + '<nav class="flex flex-wrap gap-2" aria-label="Seções da central de ajuda">'
            + '<button type="button" data-help-section="overview" class="help-nav-btn rounded-xl border px-4 py-2.5 text-sm font-black">Primeiros passos</button>'
            + '<button type="button" data-help-section="research" class="help-nav-btn rounded-xl border px-4 py-2.5 text-sm font-black">Pesquisas e exportação</button>'
            + '<button type="button" data-help-section="routine" class="help-nav-btn rounded-xl border px-4 py-2.5 text-sm font-black">Rotina do perfil</button>'
            + '</nav>'
            + '<section id="help-content" class="rounded-[2rem] border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 p-6 sm:p-8 shadow-sm"></section>'
            + '</div>';

        var content = contentArea.querySelector('#help-content');
        var buttons = contentArea.querySelectorAll('.help-nav-btn');
        function switchSection(section) {
            buttons.forEach(function (button) {
                var active = button.dataset.helpSection === section;
                button.className = active
                    ? 'help-nav-btn rounded-xl border border-blue-600 bg-blue-600 px-4 py-2.5 text-sm font-black text-white shadow-sm'
                    : 'help-nav-btn rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-black text-slate-600 hover:border-blue-200 hover:text-blue-700 dark:border-slate-600 dark:bg-slate-700/50 dark:text-slate-200 dark:hover:border-blue-700 dark:hover:text-blue-300';
            });
            content.innerHTML = guide[section] ? guide[section]() : guide.overview();
        }
        buttons.forEach(function (button) {
            button.addEventListener('click', function () { switchSection(button.dataset.helpSection); });
        });
        switchSection('overview');
    }

    window.MapsProspectorHelpImages = HELP_IMAGES;
    window.renderHelpTab = renderHelpTab;
})();
