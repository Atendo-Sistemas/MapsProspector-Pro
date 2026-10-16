# Correção do piloto Maps self-hosted — v20260819-50

## Motivo

Uma pesquisa criada pelo usuário de plataforma `super_admin` pode não ter uma empresa (`tenant`) associada. A versão anterior recusava a atualização incremental nesse contexto com HTTP 422, pois não havia uma empresa para receber o débito de créditos.

## Regra aplicada

| Contexto da pesquisa | Atualização pelo piloto | Créditos |
|---|---|---|
| Pesquisa vinculada a uma empresa | Permitida; deduplica e adiciona somente leads inéditos. | Debita somente os leads inéditos. |
| Pesquisa própria do `super_admin` sem empresa vinculada | Permitida exclusivamente como teste de plataforma. | Não debita créditos, pois não existe empresa de cobrança. |
| Qualquer outro perfil | Bloqueada pelo servidor. | Não aplicável. |

O retorno do endpoint informa explicitamente quando a execução ocorreu em modo de teste de plataforma. A interface exibe esse aviso junto ao total de leads existentes, novos e créditos consumidos.

## Arquivos alterados

- `api/maps-pilot.php`
- `assets/js/app.js`
- `index.php`
- `assets/js/pwa.js`
- `service-worker.js`

## Garantias preservadas

- A Apify continua sendo o fluxo padrão e não foi alterada.
- O endpoint continua restrito ao perfil `super_admin` no servidor.
- A deduplicação permanece limitada ao mesmo `search_history_id`.
- A atualização de uma pesquisa de empresa continua cobrando somente os leads realmente inéditos.
- Nenhum segredo HMAC, token da Apify ou arquivo `settings.php` integra esta correção.
