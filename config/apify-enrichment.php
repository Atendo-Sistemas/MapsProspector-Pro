<?php
/**
 * Configuração privada do enriquecimento LinkedIn.
 *
 * Instruções:
 * 1. Copie este arquivo para config/apify-enrichment.php.
 * 2. Informe apenas o token da conta Apify que é proprietária da Task abaixo.
 * 3. Não publique, envie por e-mail ou inclua o arquivo em ZIPs de suporte.
 *
 * Este arquivo é lido somente pelo PHP no servidor. O token nunca é retornado
 * pelo endpoint e nunca é enviado ao JavaScript do navegador.
 */
return [
    'token' => 'COLE_AQUI_O_TOKEN_PRIVADO_DA_APIFY',
    'task_id' => 'l36y39gHAS12vcQXI',
    'max_requests_per_start_url' => 3,
    'max_depth' => 1,
    'wait_seconds' => 45,
    'credits_per_enrichment' => 1,
];
