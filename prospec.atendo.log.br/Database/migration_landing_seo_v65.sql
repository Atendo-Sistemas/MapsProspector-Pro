-- Atualização editorial da landing pública para a expansão SEO v65.
-- Não remove registros; apenas revisa as seções públicas existentes.
-- Execute no banco da aplicação após criar um backup, em uma única transação.
SET NAMES utf8mb4;
START TRANSACTION;

-- Backup lógico somente do conteúdo editável da landing, antes da revisão.
CREATE TABLE IF NOT EXISTS landing_page_content_backup_20260821 LIKE landing_page_content;
DELETE FROM landing_page_content_backup_20260821;
INSERT INTO landing_page_content_backup_20260821 SELECT * FROM landing_page_content;

UPDATE landing_page_content
SET section_title = 'Prospecção B2B com Google Maps, LinkedIn e dados de CNPJ',
    section_subtitle = 'Planeje buscas por segmento e localidade, acompanhe leads e organize o trabalho comercial em um único painel.',
    section_content = '<p>O MapsProspector reúne recursos de prospecção para pesquisar empresas, consultar dados cadastrais quando a fonte configurada os disponibilizar e organizar a rotina da equipe. A disponibilidade e o retorno de cada pesquisa dependem da fonte conectada, dos filtros usados e das regras aplicáveis.</p>',
    extra_data = '{"cta_link":"#register","cta_text":"Criar acesso gratuito","video_url":null}'
WHERE section_key = 'hero';

UPDATE landing_page_content
SET section_title = 'Recursos para organizar a prospecção',
    section_subtitle = 'Conheça as funções disponíveis e escolha a forma de pesquisa adequada ao seu processo comercial.',
    section_content = NULL,
    extra_data = '{"items":[{"icon":"search","title":"Prospecção no Google Maps","description":"Pesquise empresas por segmento e localidade e trabalhe os resultados retornados pela fonte configurada."},{"icon":"users","title":"Pesquisa no LinkedIn","description":"Consulte perfis por cargo e localidade quando a sessão autenticada estiver disponível e autorizada."},{"icon":"shield","title":"Consulta de CNPJ","description":"Visualize atributos cadastrais e contatos que a fonte de dados configurada retornar para a empresa pesquisada."},{"icon":"chart","title":"Organização de leads","description":"Acompanhe resultados, histórico e dados de trabalho em um painel compartilhado conforme as permissões da equipe."},{"icon":"clock","title":"Planos e créditos","description":"Acompanhe os recursos do plano e os créditos aplicáveis às buscas contratadas."}]}'
WHERE section_key = 'features';

UPDATE landing_page_content
SET section_title = 'Como começar a prospectar',
    section_subtitle = 'Um fluxo simples para transformar critérios comerciais em uma lista de trabalho.',
    section_content = '<ol class="space-y-4"><li class="flex items-start gap-4"><span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">1</span><div><strong>Defina seu recorte</strong><p class="text-slate-600">Escolha segmento, localidade e demais critérios coerentes com o público que deseja trabalhar.</p></div></li><li class="flex items-start gap-4"><span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">2</span><div><strong>Inicie a pesquisa disponível</strong><p class="text-slate-600">Use Google Maps, LinkedIn autenticado ou consulta de CNPJ conforme a finalidade e a disponibilidade de cada fonte.</p></div></li><li class="flex items-start gap-4"><span class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">3</span><div><strong>Revise e organize os resultados</strong><p class="text-slate-600">Avalie os dados retornados, registre o andamento e distribua o trabalho para a equipe quando necessário.</p></div></li></ol>'
WHERE section_key = 'how_it_works';

UPDATE landing_page_content
SET section_title = 'Um painel para o processo comercial',
    section_subtitle = 'Funções para apoiar a pesquisa, a organização e o acompanhamento de leads.',
    section_content = NULL,
    extra_data = '{"items":[{"icon":"clock","title":"Critérios de pesquisa","description":"Organize pesquisas por segmento, localização e outros filtros disponíveis em cada fonte."},{"icon":"check-circle","title":"Consulta orientada por fonte","description":"Identifique de qual recurso veio cada retorno e revise os dados antes de utilizá-los comercialmente."},{"icon":"trending-up","title":"Histórico de trabalho","description":"Mantenha o acompanhamento de pesquisas e leads dentro do ambiente da empresa."},{"icon":"users","title":"Equipe com permissões","description":"Estruture os acessos conforme o papel de cada integrante e a política da organização."}]}'
WHERE section_key = 'benefits';

UPDATE landing_page_content
SET is_active = 0
WHERE section_key = 'testimonials';

UPDATE landing_page_content
SET section_title = 'Perguntas frequentes',
    section_subtitle = 'Informações para entender os recursos e os limites de uso da plataforma.',
    section_content = NULL,
    extra_data = '{"items":[{"question":"Quais fontes de pesquisa estão disponíveis?","answer":"A plataforma disponibiliza recursos para Google Maps, LinkedIn autenticado e consulta de CNPJ. O retorno depende da configuração, disponibilidade da fonte e dos filtros aplicados."},{"question":"Posso cancelar uma assinatura mensal?","answer":"O cancelamento pode ser solicitado pelo painel. Quando houver período já pago, o acesso permanece até o seu encerramento, sem estorno automático."},{"question":"Os dados de CNPJ sempre incluem contatos e quadro societário?","answer":"A tela apresenta os campos que a fonte de dados configurada retornar. Alguns atributos podem não estar disponíveis para todos os CNPJs consultados."},{"question":"Como funcionam os créditos de busca?","answer":"Os créditos e limites são definidos pelo plano e pelas regras da função utilizada. Consulte os detalhes do plano antes de iniciar pesquisas em volume."}]}'
WHERE section_key = 'faq';

UPDATE landing_page_content
SET section_title = 'Conheça os recursos do MapsProspector',
    section_subtitle = 'Explore as soluções, consulte os planos disponíveis e crie seu acesso quando estiver pronto.',
    section_content = '<p>Veja como cada recurso funciona e escolha os fluxos compatíveis com a rotina comercial da sua empresa.</p>',
    extra_data = '{"cta_text":"Criar acesso gratuito","cta_link":"index.php?register=1","secondary_cta_text":"Ver soluções","secondary_cta_link":"solucoes.php"}'
WHERE section_key = 'cta';

UPDATE landing_page_content
SET section_content = '<p>&copy; 2026 MapsProspector. Todos os direitos reservados.</p>',
    extra_data = '{"company_name":"MapsProspector","social_links":{"email":"contato@atendo.log.br","whatsapp":"551731981705"}}'
WHERE section_key = 'footer';

COMMIT;
