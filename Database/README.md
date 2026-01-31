# Database – MapsProspector Pro

Scripts SQL para importação do schema em outro ambiente (MySQL/MariaDB).

## Arquivo principal

| Arquivo | Descrição |
|--------|-----------|
| `maps_schema_full.sql` | Schema completo: tabelas, FKs e dados iniciais (seed). |

## Uso

1. **Importação em sistema novo**  
   Execute `maps_schema_full.sql` no MySQL/MariaDB (phpMyAdmin, linha de comando ou cliente).  
   O script cria o banco `maps` (se não existir), todas as tabelas, chaves estrangeiras e dados iniciais.

2. **Outro nome de banco**  
   Abra o SQL, altere o nome em `CREATE DATABASE` e em `USE` para o banco desejado.

3. **Reexecução**  
   O script usa `CREATE TABLE IF NOT EXISTS` e `ON DUPLICATE KEY` nos inserts. As `ALTER TABLE ... ADD CONSTRAINT` não são idempotentes: para rodar de novo no mesmo banco, use um banco vazio ou remova/drop das tabelas antes.

## Tabelas incluídas

- `tenants` – Empresas (multi-tenant)
- `plans` – Planos e limites de tokens
- `users` – Usuários
- `settings` – Configurações por usuário
- `search_history` – Histórico de buscas
- `leads` – Leads das buscas
- `sessions` – Sessões
- `tenant_usage` – Uso de tokens por tenant/período
- `credit_requests` – Solicitações de créditos
- `lead_unlocks` – Leads desbloqueados por usuário
- `platform_settings` – Configurações globais

## Requisitos

- MySQL 5.7+ ou MariaDB 10.2+
- Charset: `utf8mb4`, collation: `utf8mb4_unicode_ci`

## Dados iniciais

Após a importação existem:

- Plano **Básico** (id 1)
- Tenant **Atendo Maps** (id 1)
- Usuário **admin** (`admin@atendo.maps` / senha: `admin123`)
- Registro em `platform_settings` para `scraper_api_key`

Altere a senha do admin após o primeiro acesso.
