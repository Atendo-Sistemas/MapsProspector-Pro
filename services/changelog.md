# Changelog

Todos as **mudanças** deste projeto serão documentados neste arquivo.

---

## [2.0.0] - 2026-01-26 — **Versão Atual**

### Migração Completa para PHP/XAMPP

Migração total do projeto de React/TypeScript para PHP puro, otimizado para rodar no XAMPP com banco de dados MySQL.

### ✨ Adicionado

* **Backend PHP completo:** API RESTful com endpoints para autenticação, busca, histórico, configurações e exportação.
* **Banco de dados MySQL:** Estrutura completa com tabelas para usuários, configurações, histórico de buscas e leads.
* **Sistema de autenticação:** Sessões PHP com controle de usuários.
* **Persistência em banco:** Histórico e configurações agora são salvos permanentemente no MySQL.
* **Script de verificação:** Arquivo `check.php` para diagnosticar problemas de configuração.
* **Documentação completa:** Guias de instalação (`INSTALACAO.md`) e documentação PHP (`README-PHP.md`).

### 🚀 Melhorias

* **Performance:** Eliminação de dependências Node.js, execução direta no servidor.
* **Segurança:** Prepared statements, sanitização de inputs, validação de sessões.
* **Tratamento de erros:** Output buffer para evitar HTML em respostas JSON, logs estruturados.
* **Compatibilidade:** Suporte a PHP 7.4+ com fallbacks para funções antigas.

### 🔄 Mudanças Arquiteturais

* **Frontend:** Migrado de React para JavaScript vanilla mantendo o mesmo design.
* **Armazenamento:** Substituição de localStorage por banco de dados MySQL.
* **Build:** Removida necessidade de Vite/Node, execução direta via Apache.
* **API:** Endpoints RESTful em PHP substituindo chamadas diretas ao Gemini.

### 🐛 Correções

* Correção de compatibilidade com PHP antigo (substituição de `str_starts_with`).
* Prevenção de erros PHP sendo exibidos antes de respostas JSON.
* Melhor tratamento de erros de conexão com banco de dados.

### 📝 Arquivos Criados

* `api/` - Endpoints da API (auth, search, history, settings, export)
* `config/` - Configurações (config.php, database.php)
* `includes/` - Funções auxiliares (functions.php)
* `services/` - Serviços externos (gemini.php)
* `assets/js/` - JavaScript principal (app.js)
* `index.php` - Página principal
* `database.sql` - Script de criação do banco
* `check.php` - Script de verificação
* `INSTALACAO.md` - Guia de instalação
* `README-PHP.md` - Documentação da versão PHP

---

## [1.3.0] - 2026-03-26

### Persistência de Dados e Otimização de Cache

Esta atualização foca na experiência do usuário, garantindo que os dados pesquisados não sejam perdidos ao recarregar a página ou navegar pelo histórico.

### ✨ Adicionado

* **Persistência completa de resultados:** além do termo de busca, a lista completa de empresas (leads) agora é armazenada no navegador.
* **Restauração inteligente:** ao clicar em **“Ver novamente”** no histórico, os resultados carregam instantaneamente sem consumir novos créditos da API.

### 🚀 Melhorias

* **Recuperação de sessão:** ao abrir a aplicação, a última pesquisa realizada é carregada automaticamente na tela inicial.
* **Gestão de storage:** o histórico mantém apenas os **últimos 20 registros completos**, otimizando o uso do LocalStorage.

### 🐛 Correções

* Correção na tipagem do `SearchHistoryItem` para suportar o array opcional de `leads`.

---

## [1.2.0] - 2026-03-07

### Integração CRM Avançada e Configurações de API

Melhorias significativas na estabilidade da conexão com CRMs externos e ajustes no motor de busca da IA.

### ✨ Adicionado

* **Painel de configurações:** nova aba lateral para gestão de chaves de API e URLs.
* **Modo Estrito (Strict Mode):** filtro automático de payloads JSON, removendo campos como `ticketId` e `contactId` que causam erro 400 em CRMs.
* **Suporte a Proxy (CORS):** opção nativa para contornar bloqueios de CORS via `corsproxy.io`.
* **Wrap in Body:** opção para encapsular o JSON dentro de `{ body: ... }`, necessária para alguns fluxos do n8n.
* **Seletor de modelo IA:** alternância entre `gemini-2.0-flash` (estável) e `gemini-2.5-flash` (experimental Maps).

### 🚀 Melhorias

* **Prompt Gemini V2 (Massive Scraper):** busca entre 50 a 100 resultados com validação automática de números de telefone.

---

## [1.1.0] - 2026-02-05

### Geolocalização e Interface Rica

Implementação de recursos visuais e detecção automática de localização para facilitar a prospecção em campo.

### ✨ Adicionado

* **Integração GPS:** detecção automática de coordenadas via `navigator.geolocation`.
* **Reverse Geocoding:** conversão de coordenadas em nomes de cidades utilizando OpenStreetMap (Nominatim).
* **Deep Linking:** botão para abrir o endereço da empresa diretamente no aplicativo do Google Maps.

### 🎨 Interface

* Layout responsivo com **Sidebar escura** e **Grid de cards**.
* Paginação local com botão **“Carregar mais”** para grandes volumes de leads.
* **Badges visuais** para indicar leads com dados ricos (CNPJ, sócios, etc.).

---

## [1.0.0] - 2026-01-01

### Lançamento Inicial

Versão base do sistema de prospecção.

### 🧱 Core

* Integração com **Google Gemini API** (`@google/genai`).
* Busca por **ramo de atividade** e **localização manual**.
* Exportação unitária de leads via **Webhook genérico**.
* Stack inicial: **React + Vite + TailwindCSS**.