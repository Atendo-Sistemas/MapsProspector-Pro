# Integrações atuais

Esta lista foi conferida no código do painel em 16/10/2026. Uma integração presente no código só funciona se o serviço e suas credenciais estiverem configurados no ambiente.

| Recurso | Caminho ativo no painel | Serviço externo | Observação |
| --- | --- | --- | --- |
| Busca de empresas no Maps | `app/app.js` → `api/maps-pilot.php` → `includes/AtendoMapsCollectorClient.php` | Coletor privado Atendo integrado ao Google Maps | Reutiliza resultados recentes do banco; envia ao coletor apenas a quantidade complementar necessária. |
| Busca de Maps legada | `api/search.php` → `services/scraperService.php` | Apify | Rota mantida para compatibilidade; não é o caminho da busca Maps principal no painel atual. |
| Pesquisa LinkedIn | `api/linkedin-search.php` | Groq Browser Search | Em indisponibilidade, tenta coleta autenticada e contingência Apify. |
| Consulta de CNPJ | `api/cnpj-search.php`, `api/cnpj.php` e `api/cnpj-advanced-search.php` | CNPJ.WS, CNPJ.PW e, conforme a operação, uma VPS privada | A origem depende do tipo de pesquisa e da configuração. |
| Sugestões de CNAE por IA | `api/cnae-ia-matcher.php` | 9router, Groq ou OpenRouter | O roteador e o provedor são selecionados conforme a configuração da plataforma. |
| Dicionário CNAE | `api/cnae-api/app.py` | API Flask própria com SQLite | Serviço interno, não um provedor de IA. |
| Pagamentos | `api/create-payment.php`, `api/asaas.php` | Asaas | As solicitações podem seguir para aprovação manual quando o gateway não está operacional. |
| Exportação de leads | `api/export.php`, `api/export-bulk.php` | Webhook configurado pela empresa | O destino pode ser CRM ou automação externa, por exemplo n8n. |
| Notificações WhatsApp | `services/notificationService.php` | API Atendo configurada na plataforma | O envio depende de credenciais e URL configuradas. |
| Busca de lugares alternativa | `api/search-mapbox.php` | Mapbox | Endpoint encontrado no repositório; não é chamado pelo fluxo principal do painel. |

## Como acompanhar

- Verifique erros PHP nos logs privados do servidor (`logs/php_errors.log`, conforme `config/config.php`) e nos logs do servidor web. Não habilite `display_errors` em produção.
- Consulte o Dashboard e os endpoints administrativos de métricas Maps para volume, cobrança e estado do coletor.
- O endpoint administrativo de uso Apify acompanha apenas a integração Apify; não representa o uso do coletor Maps privado.
- Faça testes de busca e cobrança em ambiente de homologação após alterar credenciais, modelos, URLs ou configuração do coletor.

