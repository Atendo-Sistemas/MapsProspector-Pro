# Instagram — comentários por publicação e por local

## Escopo implementado

A versão atual adiciona uma aba autenticada **Instagram · Comentários** ao MapsProspector. O módulo usa o Actor `apify~instagram-scraper` e não expõe tokens ao navegador.

Há dois modos:

- **URL de publicação/Reel:** uma execução do Actor com `resultsType: comments`.
- **Buscar por local:** o backend normaliza o texto do local, executa uma busca `resultsType: posts`/`searchType: place`, extrai URLs de publicações e inicia execuções de comentários com limites globais.

## Chaves Apify

O serviço reaproveita a tabela `apify_keys`. A chave ativa menos usada recentemente é selecionada e seu `last_used_at` é atualizado. As quatro chaves podem ser cadastradas pelo painel administrativo existente. Nenhum token é enviado ao frontend, incluído na resposta JSON ou escrito no log.

A rotação organiza disponibilidade entre as chaves cadastradas. Ela não altera o preço PPE do Apify e não deve ser usada para ultrapassar limites comerciais do provedor.

## Cobrança e deduplicação

Os comentários são normalizados por `ownerId` e, quando ele não existe, por username. O mesmo autor em vários comentários da publicação vira um único perfil na entrega. A cobrança interna usa somente perfis novos para o tenant, através de `incrementTenantUsage`; Super Admin e tenant de validação permanecem isentos segundo a regra existente.

O pacote registra separadamente `comments_collected`, `apify_items_count`, `leads_found`, `duplicate_count` e `credits_charged`. Isso permite diferenciar custo externo PPE de créditos internos.

## Limites padrão

A interface inicia com:

- até 5 publicações no modo local;
- até 50 comentários por publicação;
- até 300 comentários no total;
- até 250 comentários numa pesquisa direta;
- respostas desativadas por padrão.

Os limites são novamente validados no backend, portanto não dependem da interface.

## Instalação

Aplicar em homologação:

```sql
SOURCE Database/database_migration_instagram_comments.sql;
```

Depois, cadastrar as chaves na tela existente de gerenciamento de chaves Apify. Não inserir tokens no código ou no `.env` público.

## Arquivos adicionados

- `services/instagramApifyService.php`
- `api/instagram-search.php`
- `api/instagram-search-status.php`
- `assets/js/instagram-ui.js`
- `Database/database_migration_instagram_comments.sql`

Arquivos ajustados:

- `app/index.php`
- `app/app.js`

## Validação local

Foram validados:

- `php -l` dos três arquivos PHP novos;
- `node --check` do frontend Instagram;
- `node --check` do `app/app.js` atual;
- normalização de `Ribeirão Preto - SP` para `Ribeirão Preto - São Paulo, Brazil`;
- normalização de URL de publicação;
- deduplicação de dois comentários do mesmo autor;
- classificação local de intenção em `high`, `medium` e `low`.

A implementação ainda não foi publicada no servidor compartilhado e não foi executada contra as quatro chaves reais. Antes da publicação, aplicar a migration em homologação e testar uma URL de publicação real com limite pequeno.
