<?php
declare(strict_types=1);

function mapsProspectorCleanSeoPages(): array
{
    return [
        'prospeccao-b2b' => [
            'section' => 'Prospecção B2B',
            'title' => 'Prospecção B2B: encontre empresas e novas oportunidades',
            'description' => 'Organize sua prospecção B2B por segmento, localidade e fonte de pesquisa. Encontre empresas e transforme resultados em listas de trabalho.',
            'intro' => 'Uma rotina de prospecção B2B começa com um recorte claro: quais empresas têm perfil para comprar, em qual região elas estão e quais dados ajudam sua equipe a iniciar uma conversa relevante.',
            'sections' => [
                ['heading' => 'O que é prospecção B2B?', 'paragraphs' => ['Prospecção B2B é o processo de identificar empresas com potencial de compra e organizar informações para uma abordagem comercial responsável. Em vez de trabalhar com uma lista genérica, você define critérios de segmento, localização e perfil antes de iniciar o contato.', 'O MapsProspector reúne pesquisa de empresas, consulta de dados cadastrais, histórico e organização de leads em um mesmo ambiente. A disponibilidade de resultados depende da fonte conectada e dos filtros usados.']],
                ['heading' => 'Como estruturar uma busca B2B', 'paragraphs' => ['Comece descrevendo o segmento e delimitando a localidade. Depois, revise os resultados, confira os campos retornados e priorize as empresas que realmente se encaixam no público da sua oferta.', 'Uma lista bem segmentada ajuda a equipe a trabalhar com contexto, reduzir duplicidades e registrar o próximo passo comercial sem perder o histórico da pesquisa.']],
                ['heading' => 'Da pesquisa à próxima ação', 'paragraphs' => ['O resultado de uma busca não é uma garantia de conversão. Ele é um ponto de partida para qualificar a empresa, confirmar a pertinência dos dados e escolher um canal de contato compatível com sua operação.', 'Quando houver dados suficientes, use o CRM adicional para distribuir leads, definir etapas, responsáveis, próximo contato e atividades do time.']],
            ],
            'faq' => [
                'Quais critérios posso usar na prospecção B2B?' => 'Você pode combinar segmento, localidade e, conforme a fonte, cargo, empresa, cidade e outros filtros disponíveis no painel.',
                'O MapsProspector garante que todos os leads vão comprar?' => 'Não. A plataforma apoia a descoberta e a organização de oportunidades; a qualificação e o resultado comercial dependem da oferta, do público e da abordagem da empresa.',
            ],
            'related' => ['prospeccao-google-maps', 'geracao-de-leads', 'recursos'],
        ],
        'prospeccao-google-maps' => [
            'section' => 'Google Maps',
            'title' => 'Prospecção pelo Google Maps: encontre empresas por região',
            'description' => 'Pesquise empresas pelo Google Maps usando segmento e localidade. Crie listas de leads locais para sua prospecção comercial.',
            'intro' => 'Encontre empresas de um segmento específico em cidades, bairros e regiões de atuação. A pesquisa pelo Google Maps ajuda a transformar uma área comercial em um recorte de empresas para análise.',
            'sections' => [
                ['heading' => 'Encontre empresas pelo segmento e pela região', 'paragraphs' => ['Uma busca mais específica tende a ser mais útil do que uma consulta ampla. Informe o tipo de negócio que deseja encontrar e combine o segmento com uma localidade que faça sentido para sua estratégia.', 'Os resultados podem apresentar nome, endereço, telefone, site, categoria e outros campos disponibilizados pela fonte configurada. Revise as informações antes de iniciar uma abordagem.']],
                ['heading' => 'Como usar a pesquisa no Maps', 'paragraphs' => ['Defina o segmento, informe a cidade ou área e inicie a pesquisa disponível para sua conta. Depois, confira a lista, elimine resultados fora do perfil e acompanhe o que já foi visto no histórico.', 'A plataforma também permite verificar novos leads quando uma pesquisa é repetida, ajudando a concentrar a atenção em resultados ainda não apresentados na conta.']],
                ['heading' => 'Listas locais para equipes comerciais', 'paragraphs' => ['Empresas que vendem regionalmente podem organizar listas por cidade, território, categoria ou prioridade. Com o CRM adicional, os leads podem ser distribuídos por responsável e acompanhados por etapa.', 'Os créditos, limites e campos retornados variam conforme o plano, os filtros e a fonte disponível no momento da consulta.']],
            ],
            'faq' => [
                'A pesquisa no Google Maps encontra qualquer empresa?' => 'A cobertura depende da fonte, da localidade, do termo usado e dos resultados disponibilizados no momento da consulta. Não há garantia de cobertura total.',
                'A busca no Maps consome créditos?' => 'A operação segue as regras de créditos exibidas para o plano e a função utilizada. Consulte o saldo no painel antes de pesquisar em volume.',
            ],
            'related' => ['lista-de-empresas', 'geracao-de-leads', 'prospeccao-b2b'],
        ],
        'geracao-de-leads' => [
            'section' => 'Geração de leads',
            'title' => 'Geração de Leads B2B: transforme buscas em listas de trabalho',
            'description' => 'Gere listas de leads B2B a partir de pesquisas por segmento, localidade, cargo e empresa, com histórico e organização comercial.',
            'intro' => 'Gerar leads é mais do que acumular nomes. É criar uma lista com contexto suficiente para decidir quem abordar, por que aquela empresa é relevante e qual deve ser o próximo passo.',
            'sections' => [
                ['heading' => 'O que torna uma lista de leads útil', 'paragraphs' => ['Uma lista de trabalho deve ter um critério de origem, uma finalidade comercial e dados que permitam uma revisão. O MapsProspector centraliza resultados de Maps, LinkedIn autenticado e CNPJ conforme a disponibilidade de cada recurso.', 'A qualidade da lista melhora quando a equipe define previamente o público, evita filtros genéricos e registra as decisões tomadas durante a qualificação.']],
                ['heading' => 'Pesquise, revise e organize', 'paragraphs' => ['Depois da pesquisa, revise nomes, localização, contatos e demais campos retornados. Use o histórico para lembrar quais critérios já foram usados e para verificar resultados novos antes de repetir uma consulta.', 'A exportação pode ser usada quando a operação já possui outra ferramenta. Para quem não possui um CRM, o módulo adicional permite acompanhar etapas, responsáveis, atividades e próximo contato dentro da plataforma.']],
                ['heading' => 'Dados dependem da fonte', 'paragraphs' => ['Nem todo resultado terá os mesmos campos. A disponibilidade de telefone, site, cargo, e-mail, razão social ou quadro societário depende da fonte, da consulta e da atualização dos dados.', 'Antes de usar uma informação, confira sua pertinência e respeite os termos aplicáveis, as regras internas e as boas práticas de comunicação comercial.']],
            ],
            'faq' => [
                'Posso gerar leads de diferentes fontes?',
                'Sim. O ambiente reúne recursos de Google Maps, LinkedIn autenticado e consulta de CNPJ, respeitando a disponibilidade e as regras de cada fonte.',
                'A geração de leads substitui a qualificação comercial?',
                'Não. Ela acelera a descoberta e a organização. A equipe ainda deve validar aderência, atualidade e prioridade antes do contato.',
            ],
            'related' => ['prospeccao-b2b', 'prospeccao-google-maps', 'recursos'],
        ],
        'lista-de-empresas' => [
            'section' => 'Listas de empresas',
            'title' => 'Lista de empresas por segmento e localização',
            'description' => 'Crie listas de empresas para prospecção comercial usando segmento, cidade e região, com organização para sua equipe.',
            'intro' => 'Uma lista de empresas bem construída permite concentrar a prospecção em contas que compartilham um contexto comercial. O primeiro passo é definir a combinação entre mercado, território e objetivo da campanha.',
            'sections' => [
                ['heading' => 'Como criar uma lista segmentada', 'paragraphs' => ['Escolha o segmento, delimite a área de atuação e defina quais informações serão necessárias para a próxima etapa. Uma lista de empresas pode apoiar vendas locais, parcerias, fornecedores, franquias ou campanhas por nicho.', 'Depois de coletar os resultados, revise duplicidades, descarte registros fora do perfil e separe as empresas por prioridade. O histórico ajuda a documentar os critérios utilizados.']],
                ['heading' => 'Listas por cidade, bairro ou território', 'paragraphs' => ['A segmentação geográfica é útil para equipes que trabalham com visitas, atendimento regional ou campanhas por praça. Comece por uma área menor para entender a qualidade dos resultados e ajuste o recorte conforme a necessidade.', 'Não confunda quantidade com qualidade. Uma lista menor e coerente pode ser mais útil para uma equipe do que milhares de empresas sem relação clara com a oferta.']],
                ['heading' => 'Organização dentro da plataforma', 'paragraphs' => ['Os resultados podem ser revisados no histórico e exportados para outras ferramentas. Com o CRM adicional, a empresa também pode acompanhar etapas, responsáveis, notas, atividades e datas de próximo contato sem consumir créditos de pesquisa.', 'O retorno dos dados varia conforme a fonte e não representa confirmação formal de todas as informações empresariais.']],
            ],
            'faq' => [
                'Posso criar uma lista de empresas para uma cidade específica?',
                'Sim. A busca no Google Maps permite combinar segmento e localidade conforme os filtros e a fonte disponíveis para sua conta.',
                'A lista fica pronta para contato automático?',
                'A plataforma organiza dados para apoiar a prospecção. A abordagem deve seguir sua política comercial, os termos aplicáveis e as regras de comunicação pertinentes.',
            ],
            'related' => ['prospeccao-google-maps', 'geracao-de-leads', 'pesquisa-cnpj'],
        ],
        'pesquisa-cnpj' => [
            'section' => 'Consulta de CNPJ',
            'title' => 'Pesquisa de CNPJ e dados cadastrais de empresas',
            'description' => 'Consulte dados cadastrais por CNPJ e complemente a qualificação de empresas com informações retornadas pela fonte configurada.',
            'intro' => 'A pesquisa de CNPJ ajuda a conferir o contexto cadastral de uma empresa antes de avançar na qualificação comercial. Use o documento como uma etapa de validação, não como substituto da análise do negócio.',
            'sections' => [
                ['heading' => 'O que a consulta de CNPJ pode complementar', 'paragraphs' => ['Conforme a fonte disponível, a consulta pode retornar razão social, situação cadastral, datas, endereço, contatos e quadro societário. Os campos não são iguais para todos os CNPJs e podem mudar de acordo com a atualização da fonte.', 'A razão social pode ajudar a relacionar um resultado de prospecção ao registro empresarial correspondente, enquanto endereço e contatos apoiam a conferência do contexto.']],
                ['heading' => 'Como usar os dados com responsabilidade', 'paragraphs' => ['Confirme se o CNPJ corresponde à empresa que você pretende qualificar e preserve somente os dados necessários para sua rotina. Antes de entrar em contato, verifique a pertinência da informação e mantenha um processo interno de atualização.', 'Informações cadastrais podem ficar desatualizadas ou incompletas. Para decisões formais, consulte a fonte oficial ou o procedimento adequado à sua operação.']],
                ['heading' => 'CNPJ e prospecção comercial', 'paragraphs' => ['A consulta pode enriquecer um lead encontrado no Maps ou em outra fonte, mas não garante que a empresa tenha interesse na oferta. Combine o dado cadastral com segmento, localização, perfil e momento comercial.', 'O consumo de créditos e os limites da função são exibidos conforme o plano e o tipo de consulta realizado.']],
            ],
            'faq' => [
                'A pesquisa de CNPJ sempre retorna o quadro de sócios?',
                'Não. O quadro societário e outros campos dependem da disponibilidade e do escopo da fonte configurada para a consulta.',
                'Posso usar a pesquisa de CNPJ para validar um lead?',
                'Sim, como apoio à qualificação. Confirme a correspondência e a atualidade dos dados antes de tomar decisões ou iniciar uma comunicação.',
            ],
            'related' => ['lista-de-empresas', 'geracao-de-leads', 'recursos'],
        ],
        'recursos' => [
            'section' => 'Recursos',
            'title' => 'Recursos do MapsProspector para sua rotina comercial',
            'description' => 'Conheça os recursos do MapsProspector: Google Maps, LinkedIn, pesquisa de CNPJ, histórico, exportação, equipe e CRM adicional.',
            'intro' => 'O MapsProspector reúne recursos para descobrir empresas, consultar informações disponíveis e organizar o trabalho comercial. Cada módulo atende a uma etapa diferente do processo e pode ser usado conforme o plano e as permissões da conta.',
            'sections' => [
                ['heading' => 'Pesquisa no Google Maps', 'paragraphs' => ['Encontre empresas por segmento e localidade. É uma opção para prospecção regional, criação de listas locais e identificação de negócios dentro de uma área comercial.', 'Os resultados retornados, campos disponíveis e limites dependem da fonte conectada e dos filtros utilizados.']],
                ['heading' => 'Pesquisa no LinkedIn autenticado', 'paragraphs' => ['Organize pesquisas profissionais por cargo, empresa e localidade quando a sessão autorizada estiver disponível. O recurso é indicado para identificar perfis relacionados aos decisores e influenciadores de uma conta.', 'A disponibilidade dos perfis e campos depende da sessão e das informações retornadas no momento da consulta.']],
                ['heading' => 'CNPJ, histórico e exportação', 'paragraphs' => ['A consulta de CNPJ complementa a qualificação de empresas com dados cadastrais disponíveis. O histórico permite revisar pesquisas anteriores e acompanhar o que já foi exibido na conta.', 'A exportação é independente do CRM e pode atender empresas que já usam outra ferramenta para o trabalho comercial.']],
                ['heading' => 'CRM comercial adicional', 'paragraphs' => ['O CRM adicional funciona como uma licença separada do plano de prospecção. Ele não substitui o plano principal e não consome créditos de pesquisa.', 'O módulo permite organizar etapas, responsáveis, próximo contato, notas, atividades e movimentação em lote, conforme a licença contratada e a franquia de usuários incluída.']],
            ],
            'faq' => [
                'Preciso contratar todos os recursos?',
                'Não. A disponibilidade depende do plano e das licenças adicionais. O CRM é tratado separadamente para não substituir o plano de prospecção.',
                'O CRM consome créditos?',
                'Não. O CRM adicional é uma licença de organização comercial e não consome a carteira de créditos das pesquisas.',
            ],
            'related' => ['prospeccao-b2b', 'geracao-de-leads', 'precos'],
        ],
        'precos' => [
            'section' => 'Planos e preços',
            'title' => 'Planos e preços do MapsProspector',
            'description' => 'Compare os planos atuais de prospecção, créditos mensais e o CRM adicional. Veja qual configuração combina com sua rotina comercial.',
            'intro' => 'Os planos atuais combinam acesso aos recursos de prospecção com uma carteira mensal de créditos. O CRM é uma licença adicional independente, sem substituir o plano principal e sem consumir créditos.',
            'plans' => [
                ['name' => 'Plano gratuito', 'price' => 'Grátis', 'details' => '100 créditos · todos os módulos liberados no teste · 3 usuários incluídos'],
                ['name' => 'Plano Start', 'price' => 'R$ 49,90/mês', 'details' => '1.500 créditos · Maps, LinkedIn, CNPJ e Exportação · +50 créditos de bônus na contratação · 1 usuário'],
                ['name' => 'Plano Pro', 'price' => 'R$ 97,90/mês', 'details' => '5.000 créditos · módulos completos de prospecção · +50 créditos de bônus na contratação · 1 usuário'],
                ['name' => 'Plano Advanced', 'price' => 'R$ 197,90/mês', 'details' => '15.000 créditos · módulos completos de prospecção · +50 créditos de bônus na contratação · 1 usuário'],
                ['name' => 'Plano Ultimate', 'price' => 'R$ 397,90/mês', 'details' => '40.000 créditos · módulos completos de prospecção · +50 créditos de bônus na contratação · 1 usuário'],
                ['name' => 'CRM Essencial', 'price' => 'R$ 39,90/mês', 'details' => 'Licença adicional · CRM sem consumo de créditos · 3 usuários incluídos · usuário excedente informativo de R$ 19,90/mês'],
                ['name' => 'CRM Equipe', 'price' => 'R$ 79,90/mês', 'details' => 'Licença adicional · CRM sem consumo de créditos · 10 usuários incluídos · usuário excedente informativo de R$ 14,90/mês'],
            ],
            'sections' => [
                ['heading' => 'Como escolher um plano', 'paragraphs' => ['Escolha um plano completo quando sua equipe precisar combinar Maps, LinkedIn, CNPJ e Exportação ou quando o volume de pesquisa exigir mais créditos. O preço por crédito diminui nos planos de maior volume.', 'O teste libera todos os módulos e disponibiliza 100 créditos para conhecer o fluxo. Os planos pagos informam claramente o bônus comercial de 50 créditos na contratação.']],
                ['heading' => 'CRM como módulo adicional', 'paragraphs' => ['O CRM Essencial e o CRM Equipe podem ser contratados separadamente, mantendo o plano de prospecção intacto. A franquia de usuários da licença CRM orienta a criação de novos acessos quando o add-on estiver ativo.', 'O valor de usuário excedente é apresentado como referência comercial nesta fase. A cobrança automática de assentos depende de uma futura ativação específica do faturamento.']],
                ['heading' => 'Créditos e limites', 'paragraphs' => ['O consumo depende da operação: Maps, LinkedIn e consultas de CNPJ podem usar quantidades diferentes de créditos. Consulte o saldo, os limites e as regras exibidas no painel antes de realizar pesquisas em volume.', 'Os preços e condições devem ser conferidos no painel no momento da contratação.']],
            ],
            'faq' => [
                'O CRM substitui meu plano de prospecção?',
                'Não. O CRM é uma licença adicional independente e mantém o plano principal, seus créditos e seus módulos de prospecção.',
                'O bônus de 50 créditos vale para o plano gratuito?',
                'Não. O bônus comercial de 50 créditos é associado à contratação de planos pagos, conforme as regras exibidas no painel.',
            ],
            'related' => ['recursos', 'prospeccao-b2b', 'geracao-de-leads'],
        ],
        'prospeccao-linkedin' => [
            'section' => 'LinkedIn',
            'title' => 'Prospecção no LinkedIn: encontre perfis para sua estratégia B2B',
            'description' => 'Organize pesquisas de perfis no LinkedIn por cargo, empresa e localidade, com informações disponíveis para qualificação B2B.',
            'intro' => 'A prospecção no LinkedIn ajuda a aproximar a pesquisa de empresas das pessoas que participam de decisões, influenciam projetos ou representam uma área de negócio.',
            'sections' => [
                ['heading' => 'Pesquise perfis com contexto comercial', 'paragraphs' => ['Defina os cargos, empresas e localidades relacionados à sua oferta. Um filtro específico ajuda a reduzir ruído e a concentrar a análise em perfis que fazem sentido para a conta pesquisada.', 'Os resultados e campos dependem da sessão autorizada, da fonte e das informações disponíveis no momento da pesquisa.']],
                ['heading' => 'Combine empresa, cargo e localidade', 'paragraphs' => ['Use a empresa para delimitar a conta, o cargo para definir o tipo de contato e a localidade para respeitar o território da operação. Depois, revise os resultados antes de adicionar um perfil à sua rotina comercial.', 'A pesquisa é um apoio à qualificação e não confirma, por si só, cargo atual, intenção de compra ou autorização para contato.']],
                ['heading' => 'Organize os próximos passos', 'paragraphs' => ['Quando a equipe identificar perfis relevantes, pode exportar os dados disponíveis ou organizá-los no CRM adicional, conforme as permissões e licenças da conta.', 'Observe os limites de créditos, a disponibilidade da sessão e as regras aplicáveis ao uso de dados profissionais.']],
            ],
            'faq' => [
                'Quais filtros posso usar no LinkedIn?' => 'A pesquisa pode usar cargo, empresa, localidade e outros filtros que estejam disponíveis para a sessão e para a função habilitada.',
                'A plataforma garante que o perfil ainda ocupa o cargo informado?' => 'Não. Os dados podem mudar e devem ser revisados antes de qualquer decisão ou comunicação comercial.',
            ],
            'related' => ['prospeccao-b2b', 'geracao-de-leads', 'crm-comercial'],
        ],
        'crm-comercial' => [
            'section' => 'CRM comercial',
            'title' => 'CRM comercial para organizar leads e acompanhar oportunidades',
            'description' => 'Organize leads em etapas, defina responsáveis, registre atividades e acompanhe o próximo contato com um CRM comercial adicional.',
            'intro' => 'O CRM comercial transforma resultados de pesquisa em um processo acompanhável. Ele ajuda a equipe a saber o que já foi feito, quem é responsável e qual é o próximo passo de cada lead.',
            'sections' => [
                ['heading' => 'Pipeline por etapas', 'paragraphs' => ['Organize leads em etapas como Novo, Qualificado, Contato, Proposta, Negociação, Ganho ou Perdido. O pipeline cria uma visão compartilhada do trabalho em andamento.', 'A etapa deve refletir um critério claro da operação, evitando que o CRM seja apenas uma lista sem próximo passo.']],
                ['heading' => 'Responsáveis, atividades e próximo contato', 'paragraphs' => ['Atribua um responsável, registre notas e atividades e defina uma data para o próximo contato. Essas informações ajudam a evitar esquecimentos e facilitam a distribuição de trabalho.', 'A movimentação em lote pode apoiar mudanças operacionais quando vários leads precisam avançar pela mesma regra.']],
                ['heading' => 'Licença adicional sem substituir a prospecção', 'paragraphs' => ['O CRM comercial é uma licença adicional. Ele não substitui o plano de prospecção e não consome a carteira de créditos.', 'Na interface de adição ao pipeline, a busca de leads considera somente resultados registrados pelo LinkedIn e pelo Google Maps. A consulta de CNPJ permanece separada e não participa dessa busca.', 'A franquia de usuários depende da licença CRM. Usuários já existentes são preservados; novos cadastros respeitam o limite efetivo da empresa.']],
            ],
            'faq' => [
                'O CRM consome créditos de pesquisa?' => 'Não. A licença CRM é independente e não consome créditos usados nas pesquisas.',
                'O CRM substitui meu plano de prospecção?' => 'Não. A licença adicional mantém o plano principal e seus módulos.',
                'Posso movimentar vários leads de uma vez?' => 'Sim, quando a função estiver disponível para a conta, é possível selecionar leads e aplicar uma etapa em lote respeitando os limites da operação.',
            ],
            'related' => ['geracao-de-leads', 'recursos', 'precos'],
        ],
        'creditos' => [
            'section' => 'Créditos de pesquisa',
            'title' => 'Como funcionam os créditos de pesquisa',
            'description' => 'Entenda a carteira mensal de créditos do MapsProspector, os diferentes tipos de consulta e como planejar seu volume de pesquisa.',
            'intro' => 'Os créditos organizam o consumo das pesquisas dentro do plano. Como cada operação pode ter um custo diferente, é importante conferir a regra exibida no painel antes de pesquisar em volume.',
            'sections' => [
                ['heading' => 'Uma carteira para diferentes operações', 'paragraphs' => ['A mesma carteira técnica pode ser usada por operações como Maps, LinkedIn e CNPJ, conforme as regras do plano e da função. O custo não deve ser presumido apenas pelo número de resultados.', 'O CRM adicional não consome créditos, pois é uma licença de organização comercial.']],
                ['heading' => 'Planeje o volume antes da busca', 'paragraphs' => ['Defina o objetivo da pesquisa, comece por filtros específicos e acompanhe o saldo no painel. Pesquisas mais amplas podem consumir a carteira com mais rapidez e trazer mais resultados para revisão.', 'Use o histórico para evitar repetir consultas sem necessidade e para entender quais critérios já foram testados.']],
                ['heading' => 'Teste e contratação', 'paragraphs' => ['O teste disponibiliza 100 créditos e libera os módulos para avaliação. Nos planos pagos, o bônus comercial de 50 créditos é informado no momento da contratação conforme as regras vigentes.', 'Os créditos não são uma garantia de quantidade ou qualidade de leads. O retorno depende da fonte, filtros, disponibilidade e dados encontrados.']],
            ],
            'faq' => [
                'Todos os módulos consomem a mesma quantidade de créditos?' => 'Não necessariamente. O consumo depende da operação, da função utilizada e das regras exibidas para o plano.',
                'O CRM reduz meus créditos?' => 'Não. O CRM adicional é uma licença sem consumo de créditos de pesquisa.',
                'Quantos créditos existem no teste?' => 'O teste atual disponibiliza 100 créditos e libera os módulos para avaliação.',
            ],
            'related' => ['precos', 'recursos', 'geracao-de-leads'],
        ],
        'historico-prospeccao' => [
            'section' => 'Histórico de prospecção',
            'title' => 'Histórico de prospecção: acompanhe suas pesquisas e leads',
            'description' => 'Revise o histórico de pesquisas, consultas e leads encontrados para entender o que já foi feito e planejar o próximo passo.',
            'intro' => 'O histórico de prospecção dá contexto ao trabalho comercial. Ele ajuda a lembrar quais filtros foram usados, quais resultados apareceram e onde a equipe deve continuar.',
            'sections' => [
                ['heading' => 'Por que manter um histórico', 'paragraphs' => ['Sem histórico, a equipe pode repetir consultas, perder critérios importantes ou não saber por que uma empresa entrou na lista. Registrar a origem da pesquisa ajuda a interpretar cada lead com mais contexto.', 'O histórico também permite revisar resultados de Maps, LinkedIn e CNPJ conforme as permissões e recursos disponíveis na conta.']],
                ['heading' => 'Encontre informações por qualquer parte do texto', 'paragraphs' => ['A busca histórica pode localizar trechos de consulta, localização, tags, razão social, CNPJ, telefone, e-mail, endereço e site. Isso facilita a recuperação de um resultado mesmo quando você não lembra o texto completo.', 'Ao revisar um item, confira a data, a fonte e os dados retornados antes de reaproveitar a informação em uma ação comercial.']],
                ['heading' => 'Histórico como apoio à qualificação', 'paragraphs' => ['O histórico não substitui o CRM, mas ajuda a entender de onde o lead veio. Quando o CRM adicional estiver disponível, a equipe pode transformar resultados selecionados em etapas, atividades e próximos contatos.', 'A qualidade da análise depende da atualização e da disponibilidade dos dados na fonte consultada.']],
            ],
            'faq' => [
                'O histórico armazena todas as informações para sempre?' => 'A disponibilidade e a retenção dependem das regras da plataforma, da conta e dos registros existentes. Consulte o painel e os termos aplicáveis.',
                'Posso pesquisar parte do nome ou do CNPJ no histórico?' => 'Sim. A busca foi estruturada para encontrar trechos em consultas, localidade, razão social e campos relevantes dos leads.',
            ],
            'related' => ['geracao-de-leads', 'crm-comercial', 'pesquisa-cnpj'],
        ],
        'equipe-prospeccao' => [
            'section' => 'Equipe de prospecção',
            'title' => 'Equipe de prospecção: distribua pesquisas e oportunidades',
            'description' => 'Organize usuários e responsabilidades na prospecção, com limites de acesso, CRM adicional e acompanhamento do trabalho comercial.',
            'intro' => 'Uma equipe comercial trabalha melhor quando cada pessoa sabe quais pesquisas pode realizar, quais leads acompanha e qual é o próximo passo esperado.',
            'sections' => [
                ['heading' => 'Usuários incluídos e limites', 'paragraphs' => ['Os planos e licenças podem incluir uma quantidade definida de usuários. Usuários já existentes são preservados, enquanto novos cadastros respeitam a franquia aplicável à empresa.', 'Quando houver necessidade de mais acessos, a empresa deve revisar o plano ou a licença adicional antes de cadastrar novos usuários.']],
                ['heading' => 'Distribuição de leads', 'paragraphs' => ['Com o CRM adicional, leads podem receber responsável, etapa, nota, atividade e data de próximo contato. Isso transforma a lista pesquisada em uma rotina que pode ser acompanhada pelo time.', 'A distribuição deve seguir critérios internos de território, segmento, prioridade e capacidade de atendimento.']],
                ['heading' => 'Segurança e responsabilidade', 'paragraphs' => ['Cada usuário deve utilizar seu próprio acesso e manter suas credenciais protegidas. O administrador deve conceder somente as permissões necessárias para a função de cada pessoa.', 'Dados encontrados em fontes públicas ou autorizadas devem ser usados de modo compatível com os termos aplicáveis e com as políticas da empresa.']],
            ],
            'faq' => [
                'O limite de usuários apaga usuários existentes quando é atingido?' => 'Não. O limite bloqueia novos cadastros acima da franquia efetiva; os usuários existentes são preservados.',
                'O CRM aumenta automaticamente a quantidade de usuários do plano de prospecção?' => 'Não. A licença CRM é separada e controla sua própria franquia, sem substituir o plano principal.',
            ],
            'related' => ['crm-comercial', 'recursos', 'precos'],
        ],
    ];
}
