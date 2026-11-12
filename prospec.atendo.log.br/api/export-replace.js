/* ============================================================
   EXPORTAÇÃO WEBHOOK / EXCEL — VERSÃO 2 (reescrita do zero)
   Substitui definitivamente as funções do app.js após o load.
   v=20260816-201
   ============================================================ */
(function () {
    'use strict';

    // API base compatível com o app.js (fallback se a variável não existir)
    var API = (typeof API_BASE !== 'undefined' && API_BASE)
        ? String(API_BASE)
        : '/api/';
    if (API.indexOf('/api/') !== API.length - 5) {
        API = API.replace(/\/?$/, '/') + 'api/';
    }

    // Estado compartilhado entre as duas exportações
    // (usa self como fallback para robustez em eval/iframe)
    var g = (typeof window !== 'undefined' && window) || (typeof self !== 'undefined' && self) || {};

    // Extrai o ID numérico de um lead (suporta 1436, "1436" ou "lead-1436")
    function numericLeadId(id) {
        var s = String(id || '').trim();
        var m = s.match(/(\d+)$/);
        return m ? parseInt(m[1], 10) : 0;
    }

    // ---------- Util: leitura segura de resposta ----------
    function safeFetch(url, opts) {
        return fetch(url, opts).then(async function (res) {
            var text = await res.text();
            var data = null;
            try { data = JSON.parse(text); } catch (e) { data = null; }
            return { ok: res.ok && data && data.success, status: res.status, data: data, raw: text };
        });
    }

    // ---------- Feedback na tela (toast limpo) ----------
    function showToastMsg(msg, isError) {
        // Tenta o toast do sistema; se não existir, usa alert seguro
        if (g && typeof g.showToast === 'function') {
            try { g.showToast(msg); return; } catch (e) { /* fallthrough */ }
        }
        alert((isError ? '❌ ' : '✅ ') + msg);
    }

    // ---------- Exportar para Excel (leads visíveis) ----------
    // OBS: lê AppState no momento do clique (g.AppState) e não no carregamento
    // do script, porque no fluxo do Histórico os leads só entram depois
    // ("Ver Novamente" → AppState.leads preenchido em tempo real).
    g.exportCurrentSearchToExcel = function () {
        var st = g.AppState || {};
        var leads = (st.leads || []).slice(0, st.visibleCount || 200);
        if (leads.length === 0) {
            showToastMsg('Nenhum lead visível para exportar.', true);
            return;
        }
        var esc = function (v) {
            if (v == null || v === undefined) return '';
            var s = String(v).trim();
            if (/[,"\n\r]/.test(s)) { s = '"' + s.replace(/"/g, '""') + '"'; }
            return s;
        };
        var header = 'Nome;Telefone;E-mail;Endereço;Segmento;Cidade;Estado;Data de Captura\n';
        var rows = leads.map(function (l) {
            return [
                esc(l.nome || l.name || ''),
                esc(l.telefone || l.phone || ''),
                esc(l.email || ''),
                esc(l.endereco || l.address || ''),
                esc(l.segmento || ''),
                esc(l.cidade || ''),
                esc(l.estado || ''),
                esc(l.data_captura || l.dataCaptura || '')
            ].join(';');
        });
        var blob = new Blob(['\ufeff' + header + rows.join('\n')], { type: 'text/csv;charset=utf-8;' });
        var link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'leads_' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        showToastMsg(leads.length + ' lead(s) exportado(s) para CSV/Excel.');
    };

    // ---------- Exportar para Webhook (lote) ----------
    g.exportCurrentSearchToWebhook = async function () {
        var st = g.AppState || {};
        var leads = st.leads || [];
        if (leads.length === 0) {
            showToastMsg('Nenhum lead na pesquisa atual para enviar.', true);
            return;
        }
        var btn = document.getElementById('btn-export-webhook');
        var originalHTML = btn ? btn.innerHTML : null;
        try {
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="animate-pulse">Enviando...</span>';
            }
            var result = await safeFetch(API + 'export-bulk.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ leadIds: leads.map(numericLeadId).filter(function (n) { return n > 0; }) }),
                credentials: 'include'
            });
            if (result.data && result.data.success) {
                showToastMsg(result.data.message || 'Enviado para o Webhook.');
            } else if (result.data && result.data.error) {
                showToastMsg(result.data.error, true);
            } else {
                showToastMsg('O servidor não retornou uma resposta válida (HTTP ' + result.status + ').', true);
            }
        } catch (e) {
            showToastMsg('Falha de conexão: ' + e.message, true);
        } finally {
            if (btn && originalHTML) {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }
    };

    // ---------- Lead único (botões dos cards) ----------
    g.exportLead = async function (leadId) {
        var numeric = null;
        var m = String(leadId || '').match(/lead-(\d+)$/);
        if (m) { numeric = parseInt(m[1], 10); }
        else if (/^\d+$/.test(String(leadId || ''))) { numeric = parseInt(leadId, 10); }
        if (!numeric) {
            showToastMsg('ID do lead inválido.', true);
            return;
        }
        try {
            var result = await safeFetch(API + 'export-bulk.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ leadIds: [numeric] }),
                credentials: 'include'
            });
            if (result.data && result.data.success) {
                showToastMsg(result.data.message || '1 lead enviado para o Webhook.');
            } else if (result.data && result.data.error) {
                showToastMsg(result.data.error, true);
            } else {
                showToastMsg('O servidor não retornou uma resposta válida (HTTP ' + result.status + ').', true);
            }
        } catch (e) {
            showToastMsg('Falha de conexão: ' + e.message, true);
        }
    };

    // ---------- Interceptação global dos cliques (captura) ----------
    // Bloqueia os handlers antigos do app.js (export.php inexistente / res.json inseguro)
    document.addEventListener('click', function (e) {
        var t = e.target;
        // Sobe até 4 níveis procurando um botão de exportação
        for (var i = 0; i < 4 && t && t !== document; i++) {
            if (t.nodeType === 1) {
                // Botão da barra superior
                if (t.id === 'btn-export-webhook' || t.closest('#btn-export-webhook')) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                    g.exportCurrentSearchToWebhook();
                    return;
                }
                if (t.id === 'btn-export-excel' || t.closest('#btn-export-excel')) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                    g.exportCurrentSearchToExcel();
                    return;
                }
                // Botões dos cards
                var leadBtn = t.closest('.btn-export[data-lead-id], [class*="btn-export"][data-lead-id]');
                if (leadBtn) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                    e.stopPropagation();
                    g.exportLead(leadBtn.getAttribute('data-lead-id'));
                    return;
                }
            }
            t = t.parentElement || (t.parentNode && t.parentNode.nodeType === 1 ? t.parentNode : null);
        }
    }, true); // true = fase de captura: roda ANTES de qualquer handler

})();
