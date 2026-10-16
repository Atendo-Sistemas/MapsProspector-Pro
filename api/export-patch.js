/* ==========================================================================
 * Patch v=20260815-116 — Correção das exportações Excel e Webhook
 * --------------------------------------------------------------------------
 * 1. Exportar para Excel: não depende mais do AppState.searchId. Se houver
 *    leads na tela, exporta tudo o que está visível; a função original
 *    bloqueava a exportação quando a "sessão da pesquisa" tinha expirado,
 *    mesmo com os leads carregados no painel.
 * 2. Exportação via Webhook: o app.js de produção chama api/export-bulk.php
 *    mas usa res.json() direto, que quebra com "Unexpected end of JSON
 *    input" quando o servidor responde HTML (webhook não configurado, erro
 *    de sessão, etc.). Este patch:
 *    a) intercepta o clique no botão da barra superior (#btn-export-webhook)
 *       em modo captura, evitando que o handler original (res.json direto)
 *       rode;
 *    b) intercepta os cliques nos botões dos cards (.btn-export) — que no
 *       app original chamavam api/export.php (inexistente) — também em
 *       captura;
 *    c) redefini as funções globais como camada extra de segurança.
 * Este arquivo é carregado DEPOIS do app.js e apenas redefini funções
 * globais e instala delegação, sem alterar o restante do painel.
 * ========================================================================== */
(function () {
    'use strict';
    function escapeCsv(val) {
        if (val == null || val === undefined) return '';
        var s = String(val).trim();
        if (s.indexOf(',') !== -1 || s.indexOf('"') !== -1 || s.indexOf('\n') !== -1 || s.indexOf('\r') !== -1) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }
    function formatPhoneDisplay(numbers) {
        if (!numbers) return '';
        var parts = String(numbers).split(/ ?\/ ?/);
        var out = [];
        for (var i = 0; i < parts.length; i++) {
            var p = parts[i].replace(/\D/g, '');
            if (p.length === 11) {
                p = '(' + p.slice(2, 4) + ') ' + p.slice(4, 9) + '-' + p.slice(9);
            } else if (p.length === 10) {
                p = '(' + p.slice(2, 4) + ') ' + p.slice(4, 8) + '-' + p.slice(8);
            }
            out.push(p);
        }
        return out.filter(Boolean).join(' / ');
    }
    function getLeadPhoneValue(lead) {
        if (!lead) return '';
        return lead.phone || lead.phoneNumber || lead.internationalPhone || lead.telephone || lead.phoneNumbers || '';
    }
    function notify(msg) {
        if (typeof showToast === 'function') {
            showToast(msg);
        } else if (typeof showError === 'function') {
            showError(msg);
        } else {
            window.alert(msg);
        }
    }

    /* ==========================================================================
     * 1. Exportação para Excel (redefinição global — app.js chama esta função)
     * ========================================================================== */
    window.exportCurrentSearchToExcel = function () {
        var leads = (window.AppState && Array.isArray(window.AppState.leads)) ? window.AppState.leads : [];
        if (leads.length === 0) {
            alert('Nenhum lead encontrado para exportar.');
            return;
        }
        var ctx = (window.AppState && window.AppState.currentSearch) || {};
        var queryEl = document.getElementById('search-query');
        var locationEl = document.getElementById('search-location');
        var tagEl = document.getElementById('search-tag');
        var query = (ctx.query !== undefined && ctx.query !== '') ? ctx.query : (queryEl ? queryEl.value.trim() : '');
        var location = (ctx.location !== undefined && ctx.location !== '') ? ctx.location : ((locationEl && locationEl.value !== undefined) ? String(locationEl.value || '').trim() : '');
        var tag = (ctx.tag !== undefined && ctx.tag !== '') ? ctx.tag : (tagEl ? tagEl.value.trim() : '');
        var dateStr = new Date().toLocaleString('pt-BR');
        var rows = [];
        rows.push(['Pesquisa', 'Local', 'Tag', 'Data da Pesquisa', 'Nome', 'Telefone', 'Email', 'Endereço', 'CNPJ', 'Sócios', 'Site', 'Maps']);
        for (var i = 0; i < leads.length; i++) {
            var lead = leads[i];
            rows.push([
                escapeCsv(query),
                escapeCsv(location),
                escapeCsv(tag),
                escapeCsv(dateStr),
                escapeCsv(lead.name),
                escapeCsv(formatPhoneDisplay(getLeadPhoneValue(lead))),
                escapeCsv(lead.email || ''),
                escapeCsv(lead.address || ''),
                escapeCsv(lead.cnpj || ''),
                escapeCsv(lead.partners || ''),
                escapeCsv(lead.website || ''),
                escapeCsv(lead.mapsUri || '')
            ]);
        }
        var csvContent = rows.map(function (row) { return row.join(';'); }).join('\r\n');
        var BOM = '\uFEFF';
        var blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8' });
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = 'pesquisa_atual_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(url);
        notify((leads.length === 1 ? '1 lead exportado' : leads.length + ' leads exportados') + ' com sucesso.');
    };

    /* ==========================================================================
     * 2. Exportação via Webhook (núcleo compartilhado)
     * ========================================================================== */
    function doWebhookExport(leadIds, single) {
        var leads = (window.AppState && Array.isArray(window.AppState.leads)) ? window.AppState.leads : [];
        if (leads.length === 0 && leadIds.length === 0) {
            notify('Nenhum lead encontrado para exportar.');
            return Promise.resolve(false);
        }
        var ids = leadIds.length > 0 ? leadIds : leads.map(function (l) { return String(l.id).replace('lead-', ''); });
        var btn = document.getElementById('btn-export-webhook');
        if (btn && !single) {
            btn.disabled = true;
            btn.innerHTML = '<span class="animate-pulse">Enviando...</span>';
        }
        return fetch(API_BASE + 'export-bulk.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ leadIds: ids }),
            credentials: 'include'
        }).then(function (res) {
            return res.text().then(function (text) {
                if (btn && !single) {
                    btn.disabled = false;
                    btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg> Exportar para Webhook';
                }
                var data = null;
                try { data = JSON.parse(text); } catch (e) { data = null; }
                if (res.ok && data && data.success) {
                    var msg = single
                        ? (data.message || '1 lead enviado para o Webhook.')
                        : (data.message || (ids.length === 1 ? '1 lead enviado para o Webhook.' : ids.length + ' leads enviados para o Webhook.'));
                    notify(msg);
                    return true;
                }
                var msg = single ? 'Falha ao enviar o lead.' : 'Falha ao enviar ao Webhook.';
                if (res.status === 404) {
                    msg = 'Endpoint de exportação indisponível no servidor (export-bulk.php). Avise o administrador.';
                } else if (res.status === 401) {
                    msg = 'Sessão expirada. Faça login novamente.';
                } else if (data && data.message) {
                    msg = data.message;
                } else if (data && data.error) {
                    msg = data.error;
                }
                alert('❌ ' + msg);
                return false;
            });
        }).catch(function (e) {
            if (btn && !single) {
                btn.disabled = false;
                btn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg> Exportar para Webhook';
            }
            alert('❌ Erro de conexão: ' + e.message);
            return false;
        });
    }

    /* exportLead (botão dentro do card, lead único) */
    window.exportLead = function (leadId) {
        var leads = (window.AppState && Array.isArray(window.AppState.leads)) ? window.AppState.leads : [];
        var found = null;
        for (var i = 0; i < leads.length; i++) {
            if (String(leads[i].id) === String(leadId) || leads[i].id === String(leadId).replace('lead-', '')) {
                found = leads[i];
                break;
            }
        }
        if (!found) {
            alert('Lead não encontrado.');
            return;
        }
        doWebhookExport([String(leadId).replace('lead-', '')], true);
    };

    /* exportCurrentSearchToWebhook (barra superior, todos os leads visíveis) */
    window.exportCurrentSearchToWebhook = async function () {
        var leads = (window.AppState && Array.isArray(window.AppState.leads)) ? window.AppState.leads : [];
        if (leads.length === 0) {
            notify('Nenhum lead encontrado para exportar.');
            return;
        }
        await doWebhookExport([], false);
    };

    /* ==========================================================================
     * 3. Delegação global em CAPTURA (roda antes de qualquer handler do app.js)
     * ========================================================================== */
    document.addEventListener('click', function (e) {
        var barBtn = e.target.closest('#btn-export-webhook');
        if (barBtn) {
            e.stopPropagation();
            e.preventDefault();
            if (typeof window.exportCurrentSearchToWebhook === 'function') {
                window.exportCurrentSearchToWebhook();
            }
            return;
        }
        var cardBtn = e.target.closest('.btn-export');
        if (cardBtn) {
            e.stopPropagation();
            e.preventDefault();
            if (cardBtn.disabled) return;
            var id = cardBtn.dataset.leadId || cardBtn.getAttribute('data-lead-id');
            if (typeof window.exportLead === 'function') {
                window.exportLead(id);
            }
            return;
        }
    }, true);
})();
