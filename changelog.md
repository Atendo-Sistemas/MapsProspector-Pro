# Changelog

Todos as **mudanças** deste projeto serão documentados neste arquivo.

---

## [1.4.11] - 2026-11-12

- Exibido o histórico de solicitações manuais de planos aprovadas e recusadas na área de solicitações.
- As listas de créditos e planos são carregadas separadamente; erros de leitura não são exibidos como ausência de pedidos.

---

## [1.4.10] - 2026-11-12

- O botão de assinatura mensal informa falhas de configuração do Asaas sem criar uma solicitação manual automaticamente.
- Solicitações manuais de planos voltam a aparecer no histórico da empresa; avisos e links de pagamento são exibidos em local visível.
- Uma solicitação manual pendente precisa ser analisada antes de uma contratação automática.
- A mensagem de seleção de plano só orienta atualizar CPF/CNPJ quando o documento não está cadastrado com 11 ou 14 dígitos; a confirmação possui texto editável separado.

---

## [1.4.9] - 2026-11-12

- Concessão direta de créditos pelo super administrador, sem cobrança, com registro e proteção contra repetição no período vigente.
- Corrigida a busca de nome e e-mail para cobranças Asaas; falhas de confirmação exigem conferência antes de nova cobrança.
- Corrigida a atualização e exibição de erros na análise de solicitações e protegida a aprovação simultânea.

---

## [1.4.8] - 2026-11-12

- Integradas as atualizações recentes de pesquisa com os banners e notificações, preservando a pesquisa Instagram.
- Adicionada a opção de exibir banners na pesquisa Instagram.

---

## [1.4.7] - 2026-11-12

- Campanha de banner configurável pelo super administrador em Empresa SaaS, com imagem HTTPS, destino, páginas e período.
- Publicidade identificada entre os filtros e resultados de Maps, LinkedIn, CNPJ e histórico.
- Imagens hospedadas externamente são carregadas diretamente pelo navegador; o servidor da imagem pode receber o IP do visitante.

---

## [1.4.6] - 2026-11-12

### Orientação de contratação de planos

- O administrador da empresa pode atualizar o CPF/CNPJ cadastral diretamente no perfil.
- A seleção de um plano envia ao WhatsApp cadastrado uma mensagem editável na Central de Notificações, orientando a atualização dos dados.
- A Central já permite comunicados internos para toda a plataforma; o novo aviso de contratação não bloqueia o fluxo de compra.

---

# 📢 Changelog – MapsProspector Pro v2.0 (PHP/XAMPP)

## 🚀 Novidades Principais
- Migração completa do **React/TypeScript** para **PHP nativo**, rodando diretamente no **XAMPP**  
- Estrutura reorganizada com **API RESTful** e endpoints dedicados (`auth`, `search`, `history`, `settings`, `export`)  
- Persistência de dados agora em **MySQL/MariaDB**, substituindo o uso de `localStorage`  
- **Autenticação real via sessões PHP**, garantindo maior segurança  
- **Histórico de buscas, configurações e leads** armazenados no banco de dados  
- **Exportação de leads para CRM/Webhook** integrada  
- **Integração com Google Gemini API** para busca avançada de leads  
- Interface moderna e responsiva preservada, sem dependência de frameworks pesados  

## 🔐 Melhorias de Segurança
- Uso de **Prepared Statements** contra SQL Injection  
- **Sanitização de inputs** e validação de sessões  
- Configuração de **headers de segurança** (CORS, XSS Protection)  
- Proteção de arquivos sensíveis via `.htaccess`  

## 📦 Tecnologias
- **Backend:** PHP 7.4+  
- **Banco:** MySQL/MariaDB  
- **Frontend:** HTML5, CSS3 (Tailwind), JavaScript ES6+  
- **Servidor:** Apache (XAMPP)  

---

## [1.4.5] - 2026-10-16 — **Versão Atual**

### Qualidade e privacidade nas pesquisas

- Adicionada telemetria agregada de qualidade para Maps, LinkedIn e CNPJ, visível somente ao super administrador.
- Resultados Maps são ordenados por aderência e completude, e a deduplicação usa identificadores fortes e combinações conservadoras.
- As interfaces orientam sobre resultados parciais, contatos ausentes e a correspondência com os filtros selecionados.
- A data de origem de resultados LinkedIn compartilhados não é enviada ao cliente; métricas não exibem consultas, empresas ou datas individuais.
- Adicionada migração para habilitar as métricas em bancos existentes.

---

## [1.4.4] - 2026-10-16

### Recuperação de acesso e permissões

- Adicionada recuperação de senha por e-mail com token temporário e de uso único.
- Administradores e supervisores podem habilitar ou bloquear pesquisas por integrante; os endpoints de Maps, CNPJ e LinkedIn aplicam essa permissão.
- O painel SaaS agora exibe a data de cadastro e a validade do acesso de cada empresa.
- O cadastro armazena o CPF/CNPJ opcional e as pesquisas Maps exibem um modal de carregamento.
- Adicionada migração para bancos existentes.

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
