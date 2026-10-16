/* Maps Prospector — registro leve de visualizações públicas.
 * Envia somente o caminho da página; IP, user-agent e referer são tratados
 * exclusivamente pelo endpoint no servidor.
 */
(function () {
  'use strict';
  if (window.__mapsProspectorTrafficTracked) return;
  window.__mapsProspectorTrafficTracked = true;

  var path = window.location.pathname || '/';
  var endpoint = '/api/site-traffic.php?action=track&page=' + encodeURIComponent(path);
  try {
    fetch(endpoint, {
      method: 'GET',
      credentials: 'omit',
      cache: 'no-store',
      keepalive: true,
      headers: { Accept: 'application/json' }
    }).catch(function () {});
  } catch (error) {
    // Métricas nunca podem impedir a navegação do visitante.
  }
})();
