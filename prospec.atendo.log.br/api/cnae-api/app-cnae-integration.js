/**
 * ============================================================
 * INTEGRAÇÃO DICIONÁRIO CNAE — MapsProspector Pro
 * ============================================================
 * 
 * Este arquivo deve ser ADICIONADO ao seu app.js existente.
 * Ele melhora a busca de CNAEs usando o Dicionário CNAE completo
 * com sinônimos, relevância e classificação.
 * 
 * INSTALAÇÃO:
 * 1. Adicione este código no final do seu app.js
 * 2. Ou inclua como script separado: <script src="cnae-integration.js"></script>
 * 3. Coloque os arquivos PHP em sua pasta /api/
 */

// ============================================================
// OVERRIDE DA BUSCA CNAE EXISTENTE
// ============================================================

// Nome da seção CNAE (para exibir no dropdown)
var CNAE_SECAO_NOMES = {
    'A': 'Agricultura, Pecuária e Serviços Relacionados',
    'B': 'Indústrias Extrativas',
    'C': 'Indústrias de Transformação',
    'D': 'Eletricidade, Gás e Água',
    'E': 'Construção',
    'F': 'Comércio; Reparação de Veículos Automotores e Motocicletas',
    'G': 'Transporte, Armazenagem e Correio',
    'H': 'Alojamento e Alimentação',
    'I': 'Informação e Comunicação',
    'J': 'Atividades Financeiras, de Seguro e Serviços Relacionados',
    'K': 'Atividades Imobiliárias',
    'L': 'Atividades Profissionais, Científicas e Técnicas',
    'M': 'Atividades Administrativas e Serviços Complementares',
    'N': 'Administração Pública, Defesa e Seguridade Social',
    'O': 'Educação',
    'P': 'Saúde Humana e Serviços Sociais',
    'Q': 'Artes, Cultura, Esporte e Recreação',
    'R': 'Outras Atividades de Serviços',
    'S': 'Serviços Domésticos',
    'T': 'Organismos Internacionais e Outras Instituições Extraterritoriais'
};

var CNAE_TIPO_LABELS = {
    'descricao-oficial': 'Oficial',
    'palavra-chave': 'Termo',
    'sinônimo': 'Sinônimo'
};

var CNAE_TIPO_BADGE_CLASS = {
    'descricao-oficial': 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
    'palavra-chave': 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300',
    'sinônimo': 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
};

// Substituir a função de busca CNAE existente
// Override: remove o listener original e cria um novo
document.removeEventListener('input', function(){}); // Limpa listeners antigos

// Nova função de busca CNAE
function setupCnaeSearch() {
    var cnaeTimeout = null;
    
    document.addEventListener('input', function(e) {
        if (e.target.id !== 'adv-segment') return;
        
        var input = e.target;
        var menu = document.getElementById('cnae-suggestions');
        var termo = input.value.trim();
        
        if (termo.length < 2) {
            if (menu) menu.style.display = 'none';
            return;
        }
        
        clearTimeout(cnaeTimeout);
        cnaeTimeout = setTimeout(async function() {
            try {
                var res = await fetch(API_BASE + 'cnae-search.php?q=' + encodeURIComponent(termo) + '&limit=30');
                var data = await res.json();
                
                var resultados = data.resultados || [];
                
                if (resultados.length > 0 && menu) {
                    var html = '';
                    
                    resultados.forEach(function(cnae, index) {
                        var codigo = cnae.codigo_cnae || '';
                        var descricao = cnae.descricao_cnae || '';
                        var secao = cnae.secao || '';
                        var secaoNome = CNAE_SECAO_NOMES[secao] || '';
                        
                        // Pegar o termo mais relevante (maior relevancia)
                        var melhorTermo = (cnae.termos && cnae.termos.length > 0) 
                            ? cnae.termos.reduce(function(a, b) { return (a.relevancia || 0) >= (b.relevancia || 0) ? a : b; })
                            : { termo: descricao, tipo: 'descricao-oficial', relevancia: 100 };
                        
                        var tipoLabel = CNAE_TIPO_LABELS[melhorTermo.tipo] || melhorTermo.tipo;
                        var tipoClass = CNAE_TIPO_BADGE_CLASS[melhorTermo.tipo] || 'bg-slate-100 text-slate-700';
                        
                        // Destacar o termo buscado na descrição
                        var descDestacada = descricao;
                        if (termo.length >= 3) {
                            var regex = new RegExp('(' + termo.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'gi');
                            descDestacada = descricao.replace(regex, '<mark class="bg-yellow-200 dark:bg-yellow-800/50 rounded px-0.5">$1</mark>');
                        }
                        
                        html += '<div class="cnae-item group flex items-start gap-3 px-4 py-3 hover:bg-blue-50 dark:hover:bg-blue-900/20 cursor-pointer border-b border-slate-100 dark:border-slate-700/50 transition-colors" ' +
                            'data-code="' + codigo + '" data-desc="' + descricao.replace(/"/g, '&quot;') + '">' +
                            '<div class="flex-shrink-0 w-16 pt-0.5">' +
                                '<span class="text-xs font-black text-purple-600 dark:text-purple-400">' + codigo + '</span>' +
                            '</div>' +
                            '<div class="flex-1 min-w-0">' +
                                '<p class="text-sm text-slate-800 dark:text-slate-200 leading-tight">' + descDestacada + '</p>' +
                                '<div class="flex items-center gap-2 mt-1">' +
                                    '<span class="text-[10px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded ' + tipoClass + '">' + tipoLabel + '</span>' +
                                    (secaoNome ? '<span class="text-[10px] text-slate-400 dark:text-slate-500">Seção ' + secao + '</span>' : '') +
                                '</div>' +
                            '</div>' +
                        '</div>';
                    });
                    
                    // Adicionar rodapé com contagem
                    html += '<div class="px-4 py-2 bg-slate-50 dark:bg-slate-800/50 text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">' +
                        (data.total_resultados || resultados.length) + ' resultados encontrados' +
                        (data.source ? ' · <span class="text-emerald-500">Dicionário CNAE</span>' : '') +
                    '</div>';
                    
                    menu.innerHTML = html;
                    menu.style.display = 'block';
                } else if (menu) {
                    menu.innerHTML = '<div class="px-4 py-6 text-center text-slate-400 text-sm">Nenhum segmento encontrado para "<b>' + termo + '</b>"</div>';
                    menu.style.display = 'block';
                }
            } catch (err) {
                console.error("Erro na busca CNAE:", err);
                if (menu) {
                    menu.innerHTML = '<div class="px-4 py-4 text-center text-red-500 text-sm">Erro de conexão</div>';
                    menu.style.display = 'block';
                }
            }
        }, 300);
    });
}

// Inicializar quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupCnaeSearch);
} else {
    setupCnaeSearch();
}

// ============================================================
// FUNÇÕES EXTRAS — Seleção de Seção CNAE
// ============================================================

/**
 * Filtrar CNAEs por seção (usado em busca avançada)
 */
async function buscarCnaePorSecao(secao, limit) {
    try {
        var res = await fetch(API_BASE + 'cnae-search.php?q=' + secao + '&secao=' + secao + '&limit=' + (limit || 50));
        var data = await res.json();
        return data.resultados || [];
    } catch (e) {
        console.error('Erro ao buscar por seção:', e);
        return [];
    }
}

/**
 * Obter detalhes completos de um CNAE
 */
async function obterDetalhesCnae(codigo) {
    try {
        var res = await fetch(API_BASE + 'cnae-detail.php?codigo=' + codigo);
        var data = await res.json();
        return data.data || null;
    } catch (e) {
        console.error('Erro ao obter detalhes CNAE:', e);
        return null;
    }
}

/**
 * Listar todas as seções com contagem
 */
async function listarSecoesCnae() {
    try {
        var res = await fetch(API_BASE + 'cnae-secoes.php');
        var data = await res.json();
        return data.secoes || [];
    } catch (e) {
        console.error('Erro ao listar seções:', e);
        return [];
    }
}

console.log('[Dicionário CNAE] Integração carregada com sucesso.');
