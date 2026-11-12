/* Compatibilidade para instalações antigas do Maps Prospector.
 * Se uma instalação antiga ainda iniciar na home pública, encaminha-a para
 * o shell privado com a marca de abertura do PWA.
 */
(function () {
  'use strict';
  var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true;
  var path = window.location.pathname || '/';
  if (standalone && !/^\/app(?:\/|$)/.test(path)) {
    window.location.replace('/app/?source=pwa');
  }
})();
