# Guia de Instalação - MapsProspector Pro (PHP/XAMPP)

Este guia explica como instalar e configurar o MapsProspector Pro para rodar no XAMPP (desenvolvimento) ou em servidor (produção).

## 📋 Pré-requisitos

- **XAMPP** instalado (versão 7.4 ou superior) — ou servidor PHP + MySQL em produção
- **PHP** 7.4 ou superior (extensões: `pdo`, `pdo_mysql`, `curl`, `json`, `mbstring`, `openssl`)
- **MySQL/MariaDB** (incluído no XAMPP)
- **Chave de API de Busca (Google Maps)** — Apify ou similar (obtenha conforme documentação do serviço)

---

## 🌍 Ambiente: Desenvolvimento vs Produção

- **Desenvolvimento:** `config/config.php` usa `ENVIRONMENT = 'development'`. Chaves e banco podem usar valores padrão ou arquivo `.env`.
- **Produção:** Altere para `ENVIRONMENT = 'production'` no `.env`. **Obrigatório no arranque:** apenas o **banco de dados** (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`). A **chave da API de Busca** é cadastrada pelo **super admin** no painel (Configurações). A **ENCRYPTION_KEY** é gerada automaticamente na pasta `storage/` se não for definida no `.env`. Opcionais: `ALLOWED_ORIGIN`, `ALLOW_IFRAME`.

Em produção, configure também:
- `session.cookie_secure = 1` em `config/config.php` (quando estiver em HTTPS)
- Não exponha o arquivo `check.php` publicamente (remova, restrinja por IP ou proteja por senha)

---

## 🚀 Passo a Passo de Instalação

### 1. Preparar o Banco de Dados

1. Abra o **phpMyAdmin** (acesse `http://localhost/phpmyadmin`) ou use o cliente MySQL.
2. Execute o script SQL principal:
   - Abra o arquivo `Database/maps_schema_full.sql` no editor de texto
   - Copie todo o conteúdo
   - No phpMyAdmin, vá em "SQL", cole o conteúdo e clique em "Executar"

   Ou via linha de comando:
   ```bash
   mysql -u root -p maps < Database/maps_schema_full.sql
   ```

3. O schema já inclui todas as migrações. Verifique se o banco `maps` foi criado com as tabelas principais:
   - `users`, `tenants`, `plans`
   - `settings`, `platform_settings`
   - `search_history`, `leads`, `lead_unlocks`
   - `sessions`, `tenant_usage`, `credit_requests`, `plan_requests`

### 2. Configurar Variáveis de Ambiente (recomendado)

O sistema lê credenciais e chaves de **variáveis de ambiente**. A forma mais prática é usar um arquivo **`.env`** na raiz do projeto (o arquivo `.env` não deve ser versionado; está no `.gitignore`).

**Opção A — Arquivo `.env` (desenvolvimento/local):**

Crie na raiz do projeto o arquivo `.env` (ou `.env.local`) com o conteúdo abaixo e ajuste os valores:

```env
# Produção (Hostinger etc.): defina ENVIRONMENT e BASE_URL
ENVIRONMENT=production
BASE_URL=https://seudominio.com/

# Banco de dados (Hostinger: use os dados do painel MySQL)
DB_HOST=localhost
DB_NAME=u123456_maps
DB_USER=u123456_user
DB_PASS=sua_senha_mysql

# Chave da API de Busca (Google Maps / Apify)
SCRAPER_API_KEY=sua-chave-aqui

# Chave para criptografia do token do webhook (produção: use valor forte, ex. openssl rand -base64 32)
ENCRYPTION_KEY=base64-ou-hex-32-bytes
```

**Importante:** O sistema **carrega o arquivo `.env` automaticamente** na raiz do projeto (Hostinger, hospedagem compartilhada, XAMPP etc.). Basta criar o `.env` com as chaves abaixo — não é necessário configurar variáveis no painel do servidor nem no Apache.

Se preferir **não** usar `.env`: em XAMPP/Apache pode usar `SetEnv` no `.htaccess`; em servidor, variáveis no painel ou em `php.ini`. Variáveis já definidas pelo servidor têm prioridade sobre o `.env`.

Em **desenvolvimento**, sem `.env` nem variáveis de ambiente, o código usa valores padrão para o banco (`localhost`, `maps`, `root`, senha vazia) e para as chaves (pode ficar vazio até configurar na tela Configurações).

**Opção B — Produção (Hostinger e outros):**

Coloque o `.env` na **raiz do projeto** (mesmo nível que `index.php`). **Obrigatório:** apenas `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`. A chave da API de Busca é cadastrada pelo super admin no painel; a ENCRYPTION_KEY é gerada automaticamente em `storage/encryption_key` se não for definida.

### 3. Configurar a Conexão com Banco de Dados (alternativa sem .env)

Se não usar variáveis de ambiente, o sistema usa estes padrões (apenas em desenvolvimento):

- Host: `localhost`
- Banco: `maps`
- Usuário: `root`
- Senha: (vazia)

Para alterar em ambiente que não suporte `.env`, edite `config/database.php` e ajuste os valores dentro do `__construct()` (onde está `getenv('DB_HOST') ?: 'localhost'`, etc.). **Em produção, prefira sempre variáveis de ambiente.**

### 4. Configurar Chave da API de Busca

- **Obrigatório apenas o banco de dados** no arranque. Após acessar a aplicação, o **super admin** deve entrar em **Configurações** e cadastrar a **Chave da API de Busca** (Scraper / Google Maps). Opcionalmente, pode definir `SCRAPER_API_KEY` no `.env`.
- **ENCRYPTION_KEY:** se não for definida no `.env`, o sistema gera uma chave aleatória e salva em `storage/encryption_key` (a pasta `storage/` deve ser gravável pelo PHP). Se não conseguir carregar ou gerar uma chave, o sistema falha de forma segura; não usa mais uma chave padrão conhecida. Faça backup protegido dessa chave: perdê-la impede a leitura dos tokens criptografados já armazenados.

### 5. Verificar Permissões e Diretórios

- O servidor web precisa ler todos os arquivos do projeto.
- Os diretórios `logs/` (se existir) e `storage/` devem ser graváveis pelo PHP (erros e chave de criptografia gerada automaticamente).
- Certifique-se de que `config/`, `api/` e `includes/` não são acessíveis diretamente para download (apenas execução pelo servidor).

No Windows/XAMPP, normalmente não é necessário alterar permissões.

### 6. Iniciar Serviços (XAMPP)

1. Abra o **XAMPP Control Panel**
2. Inicie:
   - ✅ **Apache**
   - ✅ **MySQL**

### 7. Verificar a Instalação (check.php)

Acesse no navegador:

```
http://localhost/MapsProspector-Pro/check.php
```

O script verifica: versão do PHP, extensões, conexão com o banco, existência das tabelas, configuração da chave da API e permissões. Corrija qualquer item marcado como erro antes de usar a aplicação.

**Segurança:** Em produção, remova ou restrinja o acesso a `check.php` (não deixe público).

### 8. Acessar a Aplicação

Abra no navegador:

```
http://localhost/MapsProspector-Pro/
```

Ou:

```
http://localhost/MapsProspector-Pro/index.php
```

---

## 🔐 Primeiro Acesso

1. O schema inicial cria um usuário **super_admin** (consulte o comentário no `Database/maps_schema_full.sql` para usuário e senha padrão).
2. **Altere a senha padrão imediatamente** após o primeiro login (menu do usuário → Perfil → Alterar senha).
3. Em **Configurações**, o super admin pode definir a chave da API de Busca (se não estiver em variável de ambiente) e o nome da empresa SaaS.
4. Novas empresas podem se cadastrar pela tela de login (Cadastrar minha empresa); o primeiro usuário da empresa será admin do tenant.

---

## ⚙️ Configurações Adicionais

### Habilitar mod_rewrite (Apache)

O `.htaccess` já está configurado. Se houver 404 em rotas:

1. Abra `httpd.conf` do Apache (ex.: `C:\xampp\apache\conf\`)
2. Descomente: `LoadModule rewrite_module modules/mod_rewrite.so`
3. Em `<Directory "C:/xampp/htdocs">`, use `AllowOverride All`
4. Reinicie o Apache

### PHP (php.ini)

Exemplo de extensões e limites (ajuste o caminho do `php.ini` conforme seu XAMPP):

```ini
extension=curl
extension=pdo_mysql
extension=mbstring
extension=openssl

upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
```

### Carregar .env no PHP (opcional)

Se quiser usar um arquivo `.env` sem configurar variáveis no Apache/sistema, você pode usar uma biblioteca que defina `getenv()` a partir do `.env` (ex.: `vlucas/phpdotenv`), carregando-a em um arquivo incluído antes de `config/config.php`. O projeto atualmente não inclui essa biblioteca; as variáveis devem estar no ambiente ou nos padrões de desenvolvimento.

---

## 📝 Estrutura de Diretórios

```
MapsProspector-Pro/
├── api/                  # Endpoints da API
│   ├── auth.php
│   ├── search.php
│   ├── history.php
│   ├── settings.php
│   ├── export.php
│   ├── export-bulk.php
│   ├── unlock.php
│   ├── register.php
│   ├── platform-config.php
│   ├── plans.php
│   ├── plans-public.php
│   ├── tenants.php
│   ├── credit-requests.php
│   └── plan-requests.php
├── config/
│   ├── config.php        # Geral e ambiente
│   └── database.php      # Conexão BD (lê DB_* do ambiente)
├── includes/
│   └── functions.php
├── services/
│   └── scraperService.php
├── storage/              # Chave de criptografia (gerada automaticamente se não houver ENCRYPTION_KEY no .env)
├── assets/js/
│   └── app.js
├── Database/             # Schema e migrações
│   ├── maps_schema_full.sql
│   └── database_migration_*.sql
├── index.php
├── check.php             # Verificação (não expor em produção)
├── .htaccess
├── .env                  # Variáveis de ambiente (não versionar)
├── .env.example          # Exemplo opcional para .env
└── INSTALACAO.md         # Este arquivo
```

---

## 🐛 Solução de Problemas

### Erro: "Chave de API não configurada" ou "Chave de API de Busca inválida"
- Verifique `SCRAPER_API_KEY` (variável de ambiente ou Configurações como super admin). Não deixe espaços extras.

### Erro: "Erro ao conectar com o banco de dados" / "DB_HOST e DB_NAME devem estar definidos"
- Confirme que o MySQL está rodando.
- Se usar variáveis de ambiente, verifique `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
- Se não usar env, verifique os padrões em `config/database.php` (apenas desenvolvimento).

### Erro 404 ao acessar rotas
- Habilite `mod_rewrite` e `AllowOverride All` no Apache.
- Confirme que o `.htaccess` está na raiz do projeto.

### CORS ou erros de conexão com API
- Em produção, defina `ALLOWED_ORIGIN` com a origem do seu front-end (ex.: `https://seudominio.com`).

### Não abre dentro de iframe
- Por padrão o sistema permite ser exibido em iframe (qualquer origem). Se estiver bloqueando, verifique se não há outro servidor (ex.: nginx) enviando `X-Frame-Options: SAMEORIGIN`. No Apache, o header é controlado por `config/config.php` conforme a variável de ambiente `ALLOW_IFRAME` (`1`/`true` = permitir; `0` = só mesma origem).

### Hostinger (ou outro) não reconhece o .env
- O sistema carrega o `.env` automaticamente na **raiz do projeto** (pasta onde está o `index.php`). Confirme que o arquivo se chama exatamente `.env` (com ponto no início), está na raiz e tem permissão de leitura. Não use `.env.local` nem subpasta — apenas `.env` na raiz. Variáveis no painel da Hostinger têm prioridade sobre o `.env`.

### URL do Webhook/CRM rejeitada
- O sistema valida a URL (apenas http/https; bloqueio de IPs privados e de metadados). Use uma URL pública HTTPS válida nas Configurações.

### Leads não aparecem
- Verifique a chave da API de Busca (Scraper) nas Configurações ou em `SCRAPER_API_KEY`.
- Consulte os logs do PHP (ex.: `logs/php_errors.log`).

---

## 🔄 Atualizações

Para atualizar o sistema:

1. Faça backup do banco de dados.
2. Substitua os arquivos do projeto (preserve `.env` e alterações em `config/` se fizer sentido).
3. Execute scripts de migração em `Database/` se houver novos `database_migration_*.sql`.
4. Em produção, confirme que todas as variáveis de ambiente continuam definidas.
5. Limpe o cache do navegador.

---

## ✅ Checklist de Instalação

- [ ] XAMPP (ou PHP + MySQL) instalado e funcionando
- [ ] Banco `maps` criado com `Database/maps_schema_full.sql`
- [ ] Variáveis de ambiente definidas (ou padrões em desenvolvimento)
- [ ] Chave da API de Busca configurada (env ou Configurações)
- [ ] Apache e MySQL rodando
- [ ] `check.php` executado sem erros críticos
- [ ] Aplicação acessível no navegador
- [ ] Login com usuário inicial e **senha padrão alterada**
- [ ] Configurações (webhook/CRM) testadas se for usar exportação

---

**MapsProspector Pro** — Versão PHP · 2025
