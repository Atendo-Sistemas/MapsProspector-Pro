# Relatório de Análise Completa do Projeto

## MapsProspector-Pro

**Data de Análise:** 26 de Março de 2026  
**Versão do Projeto:** 1.4.0  
**Analista:** IA Assistente  

---

## 1. Sumário Executivo

O **MapsProspector-Pro** é uma aplicação SaaS B2B desenvolvida em PHP puro com frontend em JavaScript vanilla, projetada para mineração de dados de empresas diretamente do Google Maps. A ferramenta permite exportar leads qualificados para CRMs como Atendo, n8n ou via webhooks.

**Principais Características:**
- **Propósito:** Prospecção de leads B2B via Google Maps
- **Arquitetura:** Multi-tenant (múltiplas empresas/organizações)
- **Backend:** PHP 7.4+ (recomendado 8.x)
- **Frontend:** JavaScript vanilla com Tailwind CSS
- **Banco de Dados:** MySQL/MariaDB com PDO
- **Autenticação:** Baseada em sessões PHP

---

## 2. Stack Tecnológica

### 2.1 Tecnologias Principais

| Categoria | Tecnologia | Versão/Versões |
|-----------|------------|----------------|
| Linguagem Backend | PHP | 7.4+ (recomendado 8.x) |
| Frontend | JavaScript | ES6+ (vanilla) |
| Estilização | Tailwind CSS | Via CDN |
| Banco de Dados | MySQL / MariaDB | 5.7+ / 10.2+ |
| ORM | PDO (native) | - |
| Servidor Web | Apache (XAMPP) ou Nginx | - |
| Container | Docker & Docker Compose | - |

### 2.2 Dependências Externas (CDN)

- **Tailwind CSS:** `https://cdn.tailwindcss.com`
- **Google Fonts:** Família Inter

### 2.3 Serviços Externos

- **Apify:** API para scraping do Google Maps
- **Atendo:** CRM para integração de leads
- **n8n / Evolution API:** Automação de webhooks

---

## 3. Estrutura de Diretórios

```
MapsProspector-Pro/
├── api/                        # Endpoints da API REST
│   ├── auth.php               # Autenticação (login, logout)
│   ├── search.php             # Busca de leads
│   ├── history.php            # Histórico de buscas
│   ├── settings.php           # Configurações do usuário
│   ├── register.php           # Registro de novos tenants
│   ├── export.php             # Exportação individual
│   ├── export-bulk.php        # Exportação em massa
│   ├── unlock.php             # Desbloqueio de detalhes de lead
│   ├── tenants.php            # Gerenciamento de tenants
│   ├── plans.php              # Planos de assinatura
│   ├── plans-public.php       # Listagem pública de planos
│   ├── plan-requests.php      # Solicitações de mudança de plano
│   ├── credit-requests.php    # Solicitações de créditos
│   └── platform-config.php    # Configuração da plataforma
│
├── assets/
│   └── js/
│       └── app.js             # Aplicação JavaScript principal
│
├── config/
│   ├── config.php            # Configurações principais
│   └── database.php          # Conexão com banco de dados
│
├── Database/
│   ├── maps_schema_full.sql  # Schema completo do banco
│   ├── migrations/           # Arquivos de migração
│   ├── seeds/                # Dados iniciais
│   └── README.md             # Documentação do banco
│
├── includes/
│   └── functions.php          # Funções auxiliares
│
├── services/
│   └── [integrações externas]
│
├── scripts/
│   └── [utilitários Git]
│
├── .agent/
│   ├── ARCHITECTURE.md       # Arquitetura de agentes IA
│   ├── agents/               # 20 agentes especializados
│   ├── skills/               # 36 skills de domínio
│   ├── workflows/            # 11 workflows de comandos
│   └── rules/                # Regras globais
│
├── .github/
│   └── [workflows GitHub]
│
├── index.php                 # Ponto de entrada principal
├── index.html                # Redirecionamento
├── check.php                 # Verificação de saúde do sistema
├── docker-compose.yml        # Configuração Docker
├── Dockerfile                # Imagem Docker
├── nginx.conf                # Configuração Nginx
│
├── .env.example              # Exemplo de variáveis de ambiente
├── .env.local               # Variáveis de ambiente locais
│
├── README.md                 # Documentação principal
├── INSTALACAO.md             # Guia de instalação
├── CONTRIBUTING.md           # Diretrizes de contribuição
├── SECURITY_AUDIT.md         # Relatório de segurança
└── changelog.md              # Histórico de alterações
```

---

## 4. Banco de Dados

### 4.1 Arquitetura do Banco

- **Tipo:** MySQL/MariaDB com PDO (padrão Singleton)
- **Conexão:** Implementada em `config/database.php`
- **Charset:** utf8mb4

### 4.2 Tabelas do Banco de Dados

O sistema conta com **14 tabelas** para gestão completa do SaaS:

| Tabela | Descrição |
|--------|-----------|
| `plans` | Planos de assinatura com limites de tokens |
| `tenants` | Empresas/organizações (arquitetura multi-tenant) |
| `users` | Contas de usuários com email/senha |
| `settings` | Configurações por usuário (webhook, CRM) |
| `platform_settings` | Configurações globais da plataforma |
| `search_history` | Histórico de buscas realizadas |
| `leads` | Dados dos leads minerados do Google Maps |
| `lead_unlocks` | Detalhes de leads desbloqueados por usuário |
| `sessions` | Sessões ativas de usuários |
| `tenant_usage` | Uso de tokens por tenant |
| `credit_requests` | Solicitações de compra de créditos |
| `plan_requests` | Solicitações de mudança de plano |

### 4.3 Credenciais Padrão

- **Email:** `admin@atendo.maps`
- **Senha:** `admin123`

> ⚠️ **Nota:** Estas credenciais devem ser alteradas imediatamente após a instalação.

---

## 5. API e Endpoints

### 5.1 Estrutura das Requisições

Todas as APIs retornam respostas JSON com a seguinte estrutura:

```json
{
  "success": true,
  "data": { ... },
  "error": "mensagem de erro (se aplicável)"
}
```

### 5.2 Endpoints Disponíveis

| Endpoint | Método | Descrição |
|----------|--------|-----------|
| `/api/auth.php` | POST | Login, logout, verificação de sessão |
| `/api/search.php` | POST | Busca de leads via Google Maps |
| `/api/history.php` | GET | Recupera histórico de buscas |
| `/api/settings.php` | GET/POST | Configurações do usuário/tenant |
| `/api/register.php` | POST | Registro de novo tenant |
| `/api/export.php` | POST | Exportação de lead único para CRM |
| `/api/export-bulk.php` | POST | Exportação em massa de leads |
| `/api/unlock.php` | POST | Desbloqueio de detalhes de lead |
| `/api/tenants.php` | GET/POST | Gerenciamento de tenants |
| `/api/plans.php` | GET/POST | Gerenciamento de planos |
| `/api/plans-public.php` | GET | Listagem pública de planos |
| `/api/plan-requests.php` | GET/POST | Solicitações de plano |
| `/api/credit-requests.php` | GET/POST | Solicitações de créditos |
| `/api/platform-config.php` | GET | Configuração da plataforma |

### 5.3 Autenticação

- **Método:** Sessões PHP (`$_SESSION['user_id']`)
- **Proteção:** Funções `requireAuth()` e `requireSuperAdmin()`
- **Isolamento:** Dados filtrados por `tenant_id` e `user_id`

---

## 6. Análise de Segurança

### 6.1 Pontos Positivos

- ✅ Uso consistente de **PDO com prepared statements**
- ✅ Senhas hashadas com `password_hash(PASSWORD_DEFAULT)`
- ✅ Verificação de autorização em endpoints sensíveis
- ✅ Isolamento por tenant dos dados
- ✅ Headers de segurança em `.htaccess` (X-Content-Type-Options, X-Frame-Options, X-XSS-Protection)
- ✅ Proteção de arquivos sensíveis via `.htaccess`
- ✅ Criptografia de dados sensíveis de leads
- ✅ Token do webhook armazenado criptografado

### 6.2 Vulnerabilidades Identificadas

#### Críticas/Altas

| Vulnerabilidade | Descrição | Status |
|-----------------|------------|--------|
| Chaves no código | SCRAPER_API_KEY e ENCRYPTION_KEY com fallbacks | ✅ Corrigido |
| Credenciais BD fixas | Host/usuário/senha hardcoded | ✅ Corrigido |
| SSRF via webhook | URL do webhook sem validação | ✅ Corrigido |
| CORS permissivo | `Access-Control-Allow-Origin: *` | ✅ Corrigido |
| Exposição de erros | display_errors ativo em check.php | ✅ Corrigido |

#### Médias

| Vulnerabilidade | Descrição | Status |
|-----------------|------------|--------|
| Cookie sem Secure | session.cookie_secure não configurado | ✅ Corrigido |
| Sessão não regenerada | session_regenerate_id não chamado no login | ✅ Corrigido |
| Ausência de CSRF | Sem token CSRF em APIs | ⚠️ Parcial |
| Rate limiting | Não implementado | ⚠️ Pendente |
| Exposição de erros | Mensagens de erro em produção | ✅ Corrigido |

### 6.3 Recomendações de Segurança

1. **Ambiente de Produção:**
   - Definir `ALLOWED_ORIGIN` para domínios específicos
   - Garantir HTTPS com `session.cookie_secure = 1`
   - Remover ou proteger `check.php`

2. **Variáveis de Ambiente:**
   - Manter todas as chaves e credenciais em `.env`
   - Garantir que `.env` está no `.gitignore`

3. **Proteção Adicional:**
   - Implementar rate limiting em login/registro
   - Considerar token CSRF para ações sensíveis

---

## 7. Configurações

### 7.1 Variáveis de Ambiente

| Variável | Descrição |
|----------|-----------|
| `ENVIRONMENT` | Ambiente (development/production) |
| `BASE_URL` | URL base da aplicação |
| `DB_HOST` | Host do banco de dados |
| `DB_NAME` | Nome do banco de dados |
| `DB_USER` | Usuário do banco de dados |
| `DB_PASS` | Senha do banco de dados |
| `SCRAPER_API_KEY` | Chave da API de scraping |
| `ENCRYPTION_KEY` | Chave para criptografia AES-256 |
| `ALLOW_IFRAME` | Permite iframe (1 ou 0) |
| `ALLOWED_ORIGIN` | Origem permitida para CORS |

### 7.2 Docker

- **Imagem Base:** PHP 8.2-apache
- **Extensões:** PDO, MySQL, curl, json, mbstring
- **Porta Padrão:** 3005
- **Serviço:** `atendo-maps`

---

## 8. Fluxos Principais

### 8.1 Fluxo de Autenticação

1. Usuário acessa `index.php`
2. Formulário de login envia credenciais para `/api/auth.php`
3. API valida credenciais e cria sessão
4. Retorno JSON com status de sucesso/erro
5. Frontend redireciona para dashboard

### 8.2 Fluxo de Busca de Leads

1. Usuário informa termo de busca (ex: "restaurantes em São Paulo")
2. Frontend envia requisição para `/api/search.php`
3. Backend chama API externa (Apify) para scraping do Google Maps
4. Resultados armazenados em `leads` e histórico em `search_history`
5. Retorno JSON com lista de leads

### 8.3 Fluxo de Exportação

1. Usuário seleciona leads para exportar
2. Requisição para `/api/export.php` ou `/api/export-bulk.php`
3. Validação de URL do webhook (SSRF)
4. Criptografia do payload do lead
5. Envio para CRM/Webhook configurado
6. Retorno JSON com status

---

## 9. Pontos de Entrada

| Arquivo | Função |
|---------|--------|
| `index.php` | Principal ponto de entrada -renderiza UI completa |
| `index.html` | Redireciona para index.php |
| `check.php` | Script de verificação de sistema |

---

## 10. Documentação do Projeto

O projeto conta com documentação completa em português:

- **README.md** - Visão geral e instruções
- **INSTALACAO.md** - Guia passo a passo de instalação
- **CONTRIBUTING.md** - Diretrizes para contribuidores
- **SECURITY_AUDIT.md** - Relatório completo de segurança
- **Database/README.md** - Documentação do schema
- **changelog.md** - Histórico de versões

---

## 11. Conclusão

O **MapsProspector-Pro** é um projeto bem estruturado para uma aplicação B2B de prospecção de leads. A arquitetura PHP vanilla com JavaScript oferece simplicidade de manutenção, enquanto a estrutura multi-tenant permite atender múltiplas empresas.

**Pontos Fortes:**
- Código bem organizado com separação clara de responsabilidades
- Segurança bem implementada com correções recentes
- Documentação abrangente
- Suporte a Docker para fácil deployment
- Integrações flexíveis com CRMs e webhooks

**Pontos de Atenção:**
- Credenciais padrão devem ser alteradas
- Rate limiting recomendado para produção
- Manter variáveis de ambiente em vez de valores fixos
- Monitorar uso de API externa (Apify)

---

*Relatório gerado automaticamente em 26 de Março de 2026*
