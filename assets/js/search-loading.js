(function () {
    'use strict';

    var timer = null;
    var startedAt = 0;
    var active = false;

    function formatElapsed(seconds) {
        var total = Math.max(0, Number(seconds) || 0);
        var minutes = Math.floor(total / 60);
        var remainder = total % 60;
        return String(minutes).padStart(2, '0') + ':' + String(remainder).padStart(2, '0');
    }

    function beginSearchLoading(title, message) {
        active = true;
        startedAt = Date.now();
        var overlay = document.getElementById('maps-search-loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'maps-search-loading-overlay';
            overlay.className = 'fixed inset-0 z-[9999] flex items-center justify-center bg-slate-950/70 px-4 backdrop-blur-sm';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');
            overlay.setAttribute('aria-labelledby', 'maps-search-loading-title');
            overlay.innerHTML = '<div class="w-full max-w-md rounded-[2rem] border border-white/20 bg-white p-7 shadow-2xl dark:bg-slate-800"><div class="flex items-start gap-4"><div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-100 text-sky-700 dark:bg-sky-900/50 dark:text-sky-300"><svg class="h-7 w-7 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25"></circle><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path></svg></div><div class="min-w-0"><h2 id="maps-search-loading-title" class="text-lg font-black text-slate-900 dark:text-white"></h2><p id="maps-search-loading-message" class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300"></p></div></div><div class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-bold leading-relaxed text-amber-900 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-100">Não feche nem atualize esta página enquanto a busca estiver em andamento. O retorno pode levar alguns minutos.</div><div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4 text-xs dark:border-slate-700"><span class="font-semibold text-slate-500 dark:text-slate-400">Tempo decorrido</span><strong id="maps-search-loading-elapsed" class="font-black tabular-nums text-sky-700 dark:text-sky-300">00:00</strong></div><p class="mt-3 text-center text-[11px] font-semibold text-slate-400 dark:text-slate-500">Estamos processando sua solicitação com segurança.</p></div>';
            document.body.appendChild(overlay);
        }

        var titleElement = document.getElementById('maps-search-loading-title');
        var messageElement = document.getElementById('maps-search-loading-message');
        if (titleElement) titleElement.textContent = title || 'Pesquisando...';
        if (messageElement) messageElement.textContent = message || 'Aguarde a conclusão da consulta.';

        var updateElapsed = function () {
            var elapsedElement = document.getElementById('maps-search-loading-elapsed');
            if (elapsedElement) elapsedElement.textContent = formatElapsed(Math.floor((Date.now() - startedAt) / 1000));
        };
        updateElapsed();
        if (timer) window.clearInterval(timer);
        timer = window.setInterval(updateElapsed, 1000);
        window.onbeforeunload = function (event) {
            if (!active) return undefined;
            event.preventDefault();
            event.returnValue = '';
            return '';
        };
    }

    function endSearchLoading() {
        active = false;
        if (timer) {
            window.clearInterval(timer);
            timer = null;
        }
        var overlay = document.getElementById('maps-search-loading-overlay');
        if (overlay) overlay.remove();
        window.onbeforeunload = null;
    }

    window.beginSearchLoading = beginSearchLoading;
    window.endSearchLoading = endSearchLoading;
}());
