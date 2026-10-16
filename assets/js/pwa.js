/* Maps Prospector PWA — versão 66.0.0
 * Registro, instalação controlada e atualização explícita. Nenhum dado de
 * negócio é lido ou salvo por este arquivo; as regras de cache estão no SW.
 */
(function () {
  'use strict';

  const VERSION = '66.0.2';
  const INSTALL_DISMISS_KEY = `mapsprospector-pwa-install-dismissed-${VERSION}`;
  const INSTALL_DISMISS_DURATION = 14 * 24 * 60 * 60 * 1000;
  let deferredInstallPrompt = null;
  let registration = null;
  let didReloadForUpdate = false;

  function isStandalone() {
    return window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  }

  function isIOS() {
    return /iphone|ipad|ipod/i.test(window.navigator.userAgent) ||
      (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);
  }

  function wasInstallPromptDismissedRecently() {
    const raw = localStorage.getItem(INSTALL_DISMISS_KEY);
    const dismissedAt = Number(raw || 0);
    return dismissedAt > 0 && (Date.now() - dismissedAt) < INSTALL_DISMISS_DURATION;
  }

  function dismissInstallPrompt() {
    localStorage.setItem(INSTALL_DISMISS_KEY, String(Date.now()));
    const banner = document.getElementById('pwa-install-banner');
    if (banner) banner.remove();
  }

  function addBanner(id, html) {
    if (document.getElementById(id)) return document.getElementById(id);
    const banner = document.createElement('section');
    banner.id = id;
    banner.className = 'fixed z-[100] inset-x-3 bottom-3 md:inset-x-auto md:right-5 md:bottom-5 md:w-[26rem] bg-white dark:bg-slate-800 border border-blue-100 dark:border-slate-600 rounded-2xl shadow-2xl p-4 text-slate-800 dark:text-slate-100';
    banner.setAttribute('role', 'status');
    banner.setAttribute('aria-live', 'polite');
    banner.innerHTML = html;
    document.body.appendChild(banner);
    return banner;
  }

  function showInstallBanner() {
    if (isStandalone() || wasInstallPromptDismissedRecently()) return;

    const isAppleDevice = isIOS();
    const banner = addBanner('pwa-install-banner', `
      <div class="flex items-start gap-3">
        <div class="w-10 h-10 shrink-0 rounded-xl bg-blue-600 text-white flex items-center justify-center font-black">M</div>
        <div class="min-w-0 flex-1">
          <p class="font-black text-sm">Instale o Maps Prospector</p>
          <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-300" id="pwa-install-description"></p>
        </div>
        <button type="button" data-pwa-dismiss-install aria-label="Fechar aviso de instalação" class="shrink-0 w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 text-lg leading-none">×</button>
      </div>
      <div class="mt-4 flex gap-2">
        <button type="button" data-pwa-install class="flex-1 px-4 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-black transition-colors">Instalar aplicativo</button>
        <button type="button" data-pwa-dismiss-install class="px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-bold transition-colors">Agora não</button>
      </div>
    `);

    const description = banner.querySelector('#pwa-install-description');
    const installButton = banner.querySelector('[data-pwa-install]');
    if (isAppleDevice) {
      description.textContent = 'No Safari, toque em Compartilhar e escolha “Adicionar à Tela de Início”.';
      installButton.textContent = 'Entendi';
      installButton.addEventListener('click', dismissInstallPrompt);
    } else if (deferredInstallPrompt) {
      description.textContent = 'Abra pelo ícone do celular para uma experiência mais prática e em tela cheia.';
      installButton.addEventListener('click', requestInstall);
    } else {
      banner.remove();
      return;
    }

    banner.querySelectorAll('[data-pwa-dismiss-install]').forEach((button) => {
      button.addEventListener('click', dismissInstallPrompt);
    });
  }

  async function requestInstall() {
    if (!deferredInstallPrompt) return;
    deferredInstallPrompt.prompt();
    try {
      await deferredInstallPrompt.userChoice;
    } finally {
      deferredInstallPrompt = null;
      dismissInstallPrompt();
    }
  }

  function showUpdateBanner(waitingWorker) {
    if (!waitingWorker || document.getElementById('pwa-update-banner')) return;
    const banner = addBanner('pwa-update-banner', `
      <div class="flex items-start gap-3">
        <div class="w-10 h-10 shrink-0 rounded-xl bg-emerald-600 text-white flex items-center justify-center font-black">↻</div>
        <div class="min-w-0 flex-1">
          <p class="font-black text-sm">Nova versão disponível</p>
          <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-300">Atualize quando terminar a ação atual. Seus dados não serão enviados ao cache do aplicativo.</p>
        </div>
      </div>
      <div class="mt-4 flex gap-2">
        <button type="button" data-pwa-update class="flex-1 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black transition-colors">Atualizar agora</button>
        <button type="button" data-pwa-later class="px-3 py-2.5 rounded-xl text-slate-600 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 text-xs font-bold transition-colors">Depois</button>
      </div>
    `);

    banner.querySelector('[data-pwa-update]').addEventListener('click', () => {
      sessionStorage.setItem('mapsprospector-pwa-refreshing', '1');
      waitingWorker.postMessage({ type: 'SKIP_WAITING' });
    });
    banner.querySelector('[data-pwa-later]').addEventListener('click', () => banner.remove());
  }

  function watchForUpdates(currentRegistration) {
    if (currentRegistration.waiting && navigator.serviceWorker.controller) {
      showUpdateBanner(currentRegistration.waiting);
    }

    currentRegistration.addEventListener('updatefound', () => {
      const newWorker = currentRegistration.installing;
      if (!newWorker) return;
      newWorker.addEventListener('statechange', () => {
        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
          showUpdateBanner(currentRegistration.waiting || newWorker);
        }
      });
    });
  }

  function registerServiceWorker() {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) return;
    navigator.serviceWorker.register(`/service-worker.js?v=${VERSION}`, { scope: '/' })
      .then((registered) => {
        registration = registered;
        watchForUpdates(registered);
      })
      .catch((error) => console.warn('PWA não registrado:', error));

    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (didReloadForUpdate || sessionStorage.getItem('mapsprospector-pwa-refreshing') !== '1') return;
      didReloadForUpdate = true;
      sessionStorage.removeItem('mapsprospector-pwa-refreshing');
      window.location.reload();
    });
  }

  window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    deferredInstallPrompt = event;
    window.setTimeout(showInstallBanner, 1200);
  });

  window.addEventListener('appinstalled', () => {
    deferredInstallPrompt = null;
    const banner = document.getElementById('pwa-install-banner');
    if (banner) banner.remove();
  });

  document.addEventListener('DOMContentLoaded', () => {
    registerServiceWorker();
    if (isIOS() && !isStandalone()) window.setTimeout(showInstallBanner, 1200);
  });
})();
