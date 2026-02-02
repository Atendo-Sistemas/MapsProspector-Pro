# Relatório de Análise de Segurança — MapsProspector Pro

**Data:** 02/02/2025  
**Escopo:** Projeto completo (PHP, API, config, serviços, banco de dados)

---

## 1. Resumo Executivo

Foi realizada análise de segurança em todo o projeto, cobrindo autenticação, autorização, injeção (SQL/XSS), gestão de segredos, CORS, sessão, SSRF, exposição de informações e boas práticas. O sistema usa **PDO com prepared statements** na maior parte dos fluxos e **password_hash/password_verify** para senhas, o que é positivo. Foram identificados **riscos altos e médios** que devem ser tratados antes de uso em produção.

---

## 2. Vulnerabilidades Críticas / Altas

### 2.1 Chaves e segredos no código (config/config.php)

- **SCRAPER_API_KEY** possui valor padrão (fallback) no código.
- **ENCRYPTION_KEY** usa fallback derivado de string fixa no código.
- **Risco:** Em repositório ou deploy, quem tiver acesso ao código conhece chaves e chave de criptografia. Vazamento permite uso indevido das APIs e descriptografia de dados (ex.: token do webhook em `settings`).

**Recomendação:**  
- Remover qualquer valor padrão de chave/segredo.  
- Usar apenas variáveis de ambiente (getenv / .env) e falhar explicitamente se não estiverem definidas em produção.

---

### 2.2 Credenciais do banco fixas (config/database.php)

- Host, dbname, username e password estão hardcoded (root/senha vazia).
- **Risco:** Não adequado para produção; senha em código ou em repositório.

**Recomendação:**  
- Ler credenciais de variáveis de ambiente (ex.: `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`) e não definir fallbacks sensíveis.

---

### 2.3 SSRF via URL do Webhook (api/export.php e api/export-bulk.php)

- A URL do CRM/Webhook (`base_url`) é definida pelo usuário em Configurações e usada diretamente em `curl_init($targetUrl)`.
- Não há validação de esquema, host ou lista de destinos permitidos.
- **Risco:** Usuário mal-intencionado (ou conta comprometida) pode apontar para:
  - Metadados de cloud (ex.: `http://169.254.169.254/`).
  - Serviços internos (banco, admin, etc.).
  - Protocolos como `file://` (dependendo do PHP/cURL).

**Recomendação:**  
- Validar que a URL usa apenas `https://` (e, se necessário, `http://` em ambientes controlados).  
- Restringir host a uma whitelist (domínios permitidos) ou bloqueio de IPs privados e de metadados.  
- Não permitir `file://` e outros esquemas perigosos.

---

### 2.4 CORS permissivo (config/config.php)

- `Access-Control-Allow-Origin: *` permite que qualquer origem faça requisições à API.
- **Risco:** Em aplicação com sessão/cookies, facilita ataques a partir de outros domínios e uso indevido da API por terceiros.

**Recomendação:**  
- Em produção, definir origens específicas (ex.: domínio do front-end).  
- Evitar `*` quando houver credenciais (cookies/sessão).

---

### 2.5 Exposição de erros e chave (check.php)

- `ini_set('display_errors', 1)` ativa exibição de erros na página.
- Exibe preview da chave da API (evitar expor qualquer trecho de SCRAPER_API_KEY).
- **Risco:** Vazamento de stack traces, caminhos de arquivo e parte da chave; útil para atacantes.

**Recomendação:**  
- Desativar `display_errors` em qualquer ambiente acessível externamente.  
- Não exibir nenhum trecho de chaves ou segredos; apenas indicar “configurada” ou “não configurada”.

---

## 3. Vulnerabilidades Médias

### 3.1 Cookie de sessão sem Secure em produção (config/config.php)

- `session.cookie_secure = 0` está comentado como “mudar para 1 em produção com HTTPS”.
- **Risco:** Em HTTPS, cookie de sessão pode ser enviado em canal não criptografado se houver downgrade ou misconfiguration.

**Recomendação:**  
- Em produção com HTTPS, definir `session.cookie_secure = 1` (e garantir que o ambiente está realmente sob HTTPS).

---

### 3.2 Falta de regeneração de ID de sessão no login (api/auth.php)

- Após login bem-sucedido não é chamado `session_regenerate_id(true)`.
- **Risco:** Aumenta risco de fixação de sessão: atacante pode forçar uso de um ID conhecido.

**Recomendação:**  
- Chamar `session_regenerate_id(true)` logo após validar credenciais e antes de preencher `$_SESSION`.

---

### 3.3 Ausência de proteção CSRF nas APIs

- APIs que alteram estado (POST/PUT/DELETE) confiam apenas em sessão/cookie; não há token CSRF.
- **Risco:** Se a aplicação for usada com cookies (mesmo domínio), um site malicioso no mesmo browser pode induzir requisições alterando estado.

**Recomendação:**  
- Para ações sensíveis (login, alterar senha, configurações, etc.), considerar token CSRF em header ou corpo, validado no servidor.  
- Ou garantir que a API é usada apenas com origem restrita (CORS) e sem cookies (ex.: apenas Authorization header).

---

### 3.4 Ausência de rate limiting

- Endpoints como login (api/auth.php) e registro (api/register.php) não possuem limite de tentativas por IP ou por conta.
- **Risco:** Brute-force em senhas e abuso de registro/automation.

**Recomendação:**  
- Implementar rate limiting (por IP e, quando aplicável, por identificador de conta) em login, registro e endpoints sensíveis.  
- Considerar bloqueio temporário ou CAPTCHA após N falhas.

---

### 3.5 Mensagem de erro interna em resposta JSON (api/search.php)

- No `register_shutdown_function`, em caso de erro fatal, a resposta inclui `'Erro interno do servidor: ' . $error['message']`.
- **Risco:** Em produção, `$error['message']` pode expor caminhos de arquivo ou detalhes de configuração.

**Recomendação:**  
- Em produção, retornar mensagem genérica (“Erro interno”) e registrar o detalhe apenas em log.

---

## 4. Pontos Positivos

- **SQL:** Uso consistente de PDO com prepared statements e parâmetros; não foi encontrada concatenação de entrada do usuário em SQL.
- **Senhas:** Uso de `password_hash(PASSWORD_DEFAULT)` e `password_verify`.
- **Autorização:** Endpoints sensíveis usam `requireAuth()` ou `requireSuperAdmin()`; verificação de `tenant_id` e `user_id` em histórico, export e unlock.
- **Isolamento por tenant:** Buscas, histórico e leads filtrados por `user_id`/`search_history` do usuário; desbloqueio e export respeitam propriedade dos dados.
- **Impersonation:** Restrita a `super_admin`; validação de `tenant_id` e existência do tenant.
- **Headers de segurança:** .htaccess define X-Content-Type-Options, X-Frame-Options, X-XSS-Protection.
- **Proteção de arquivos:** .htaccess nega acesso direto a `.sql`, `.env`, `.log`, `.md`.
- **Listagem de diretórios:** `Options -Indexes` ativo.
- **Dados sensíveis do lead:** Dados de contato podem ser criptografados (encryptLeadPayload/decryptLeadPayload) e lógica de desbloqueio por lead.
- **Token do webhook:** Armazenado criptografado (encryptApikey/decryptApikey).

---

## 5. Outras Recomendações

### 5.1 Variáveis de ambiente e .gitignore

- `.gitignore` contém `*.local` (protege `.env.local`), mas não lista explicitamente `.env`.
- **Recomendação:** Incluir `.env` e `.env.*` (exceto talvez `.env.example`) no `.gitignore` para evitar commit acidental de segredos.

### 5.2 check.php em produção

- check.php expõe estado do ambiente (PHP, extensões, BD, chave da API).
- **Recomendação:** Desativar acesso a check.php em produção (remover, restringir por IP ou por autenticação).

### 5.3 Schema e usuário padrão (Database/maps_schema_full.sql)

- Comentário indica usuário inicial `admin@atendo.maps` com senha `admin123`.
- **Recomendação:** Documentar obrigatoriedade de troca imediata na primeira instalação; considerar script pós-instalação que force alteração de senha.

### 5.4 Sanitização (includes/functions.php)

- `sanitizeInput` usa `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`; adequado para saída HTML.
- Entradas usadas em JSON (APIs) não precisam dessa transformação para “sanitização” de SQL, pois o SQL está parametrizado; manter sanitização onde a saída for HTML.

---

## 6. Checklist de Ações Prioritárias

| Prioridade | Ação |
|------------|------|
| Alta | Remover fallbacks de SCRAPER_API_KEY e ENCRYPTION_KEY; usar apenas env. |
| Alta | Mover credenciais do banco para variáveis de ambiente. |
| Alta | Validar/restringir URL do webhook (esquema, host, sem IPs internos) em export e export-bulk. |
| Alta | Restringir CORS a origens específicas em produção. |
| Alta | Desativar display_errors e não exibir trechos de chaves em check.php (ou bloquear check.php em produção). |
| Média | Definir session.cookie_secure = 1 em produção com HTTPS. |
| Média | Chamar session_regenerate_id(true) após login. |
| Média | Tratar resposta de erro fatal em search.php sem expor $error['message'] ao cliente. |
| Média | Implementar rate limiting em login e registro. |
| Baixa | Considerar token CSRF para ações que alteram estado. |
| Baixa | Incluir `.env` no .gitignore. |

---

---

## 7. Correções já implementadas (02/02/2025)

- **Segredos em produção:** Em `config/config.php`, em ambiente `production` as constantes SCRAPER_API_KEY e ENCRYPTION_KEY passam a exigir variáveis de ambiente (sem fallback).
- **Banco de dados:** `config/database.php` passa a ler host, dbname, usuário e senha de `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`.
- **CORS:** Em produção, se `ALLOWED_ORIGIN` estiver definida, é usada como origem permitida em vez de `*`.
- **SSRF:** Criada `validateWebhookUrl()` em `includes/functions.php`; URL do webhook/CRM é validada ao salvar em `api/settings.php` e antes de cada requisição em `api/export.php` e `api/export-bulk.php` (apenas http/https, bloqueio de IPs privados e de metadados).
- **Regeneração de sessão:** `session_regenerate_id(true)` chamado em `api/auth.php` após login bem-sucedido.
- **Erro fatal em search:** Em `api/search.php`, a mensagem de erro enviada ao cliente em erro fatal não expõe detalhes em produção (apenas em development com display_errors).
- **check.php:** `display_errors` desativado; não é mais exibido preview da chave da API.
- **.gitignore:** Inclusão de `.env` e `.env.*` (exceto `.env.example`) para evitar commit de segredos.

---

*Este relatório deve ser tratado como confidencial e usado apenas para reforço da segurança do projeto.*
