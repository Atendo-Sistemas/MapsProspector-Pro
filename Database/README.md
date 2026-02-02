# Database – MapsProspector Pro

Scripts SQL para importação do schema em outro ambiente (MySQL/MariaDB).

## Arquivo principal (instalação em outro lugar)

| Arquivo | Descrição |
|--------|-----------|
| `maps_schema_full.sql` | **Schema completo para nova instalação.** Tabelas, FKs e dados iniciais (seed). Basta executar este arquivo no MySQL/MariaDB; nenhuma migração adicional é obrigatória. |

## Uso – quando instalar em outro lugar

1. **Nova instalação**  
   Execute **apenas** `maps_schema_full.sql` no MySQL/MariaDB (phpMyAdmin, linha de comando ou cliente).  
   O script cria o banco `maps` (se não existir), todas as tabelas, chaves estrangeiras e dados iniciais (planos Básico e Período de teste, tenant padrão, usuário admin, `platform_settings`).

2. **Outro nome de banco**  
   Abra `maps_schema_full.sql`, altere o nome em `CREATE DATABASE` e em `USE` para o banco desejado.

3. **Após a importação**  
   Acesse o sistema com `admin@atendo.maps` / senha `admin123` e **altere a senha do admin** em produção.

4. **Reexecução no mesmo banco**  
   O script usa `CREATE TABLE IF NOT EXISTS` e `ON DUPLICATE KEY` nos inserts. As `ALTER TABLE ... ADD CONSTRAINT` não são idempotentes: para rodar de novo no mesmo banco, use um banco vazio ou remova/drop das tabelas antes.

## Tabelas incluídas (em maps_schema_full.sql)

- `plans` – Planos e limites de tokens
- `tenants` – Empresas (multi-tenant)
- `users` – Usuários (com campo `password` para login)
- `settings` – Configurações por usuário (webhook, CRM)
- `search_history` – Histórico de buscas
- `leads` – Leads das buscas
- `sessions` – Sessões
- `tenant_usage` – Uso de tokens por tenant/período
- `credit_requests` – Solicitações de créditos
- `plan_requests` – Solicitações de mudança de plano
- `lead_unlocks` – Leads desbloqueados por usuário
- `platform_settings` – Configurações globais (chave API de busca, preço avulso, nome SaaS)

## Requisitos

- MySQL 5.7+ ou MariaDB 10.2+
- Charset: `utf8mb4`, collation: `utf8mb4_unicode_ci`

## Migrações opcionais (apenas para instalações antigas)

Use estas migrações **somente** se você já tinha o banco criado por uma versão anterior e não está rodando `maps_schema_full.sql` do zero.

| Arquivo | Descrição |
|--------|-----------|
| `database_migration_plan_trial.sql` | Insere o plano **Período de teste** (10 créditos). O schema completo já inclui; use só se o banco foi criado antes. |
| `database_migration_user_password.sql` | Adiciona a coluna `password` na tabela `users`. O schema completo já inclui; use só em instalações antigas. |
| `database_migration_admin_password_fix.sql` | Corrige a senha do usuário admin para `admin123` (hash bcrypt). Use se o admin não conseguir entrar (hash antigo era para outra senha). |

## Dados iniciais

Após a importação existem:

- Planos **Básico** (id 1) e **Período de teste** (id 2, 10 créditos)
- Tenant **Atendo Maps** (id 1)
- Usuário **admin** (`admin@atendo.maps` / senha: `admin123`)
- Registro em `platform_settings` para `scraper_api_key`

Altere a senha do admin após o primeiro acesso.
