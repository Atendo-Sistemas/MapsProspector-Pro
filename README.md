
<div align="center">
  <img 
    width="1200" 
    height="475" 
    alt="Atendo Maps Banner" 
    src="https://blogger.googleusercontent.com/img/b/R29vZ2xl/AVvXsEifb65AmhXlG6f-JunD1EDO6b49xAsdoRvDaM58q_HTZg1k1VFEK0coVCYBQYcDn73zwaT0VE-0bEFNaZOLLT2Ofe-vYThcu9U8s2fa5m8i3RnPgRc_G6QSmUY7RkVAhzqlxqex5x5juN_urWJpslFVNX3bYuY-jn_3JbiMUBTvm_U_E0eZ-TBN-qcU90w/s1600/Atendo%20Sistemas.png"
  />
</div>

# Atendo Maps — Ferramenta de Prospecção Inteligente

O **Atendo Maps** é uma aplicação web de prospecção B2B que utiliza a inteligência artificial do **Google Gemini** para minerar dados de empresas diretamente do **Google Maps**.  

A ferramenta permite exportar **leads qualificados** para o **CRM da Atendo** ou para outras plataformas via **Webhooks** (como **n8n**), facilitando e acelerando o processo comercial.

---

## 🚀 Principais Funcionalidades

- **Prospecção Inteligente**  
  Busca de empresas por ramo de atividade e localização utilizando a API do Google Gemini (modelos *Flash 2.0* e *2.5*).

- **Integração com Google Maps**  
  Validação de existência e extração de dados reais:
  - Nome
  - Endereço
  - Telefone
  - Site
  - Link do Google Maps

- **Exportação para CRM**  
  Envio direto de leads para o **Atendo CRM** ou via **Webhook** (n8n / Evolution API).

- **Geolocalização**  
  Opção de busca baseada na localização atual do usuário via GPS.

- **Histórico de Buscas**  
  Histórico de pesquisas armazenado no banco de dados (MySQL), listado via API para acesso rápido posterior.

- **Configuração Flexível**  
  Personalização de endpoints de API, tokens e modelos de IA.

---

## 🧠 Tecnologias e Interface

O projeto utiliza **uma única interface**: PHP/XAMPP (index.php + assets/js/app.js). O histórico de pesquisas é gravado e listado no banco de dados (api/search.php e api/history.php).

- **Frontend:** HTML, Tailwind CSS, JavaScript (assets/js/app.js)
- **Backend:** PHP (APIs em /api/), MySQL (tabelas search_history e leads)
- **Estilização:** Tailwind CSS
- **Scraper:** API Thordata (Google Maps); IA opcional via Gemini

---

## 📦 Requisitos

Para rodar o projeto localmente ou em produção, você precisará de:

- Node.js **18 ou superior**
- Docker & Docker Compose (para implantação em contêiner)
- Chave de API do **Google Cloud** com acesso à **Gemini API**

---

## 🖥️ Instalação e Execução Local

### 1️⃣ Clone o repositório

```bash
git clone https://github.com/seu-usuario/atendo-maps.git
cd atendo-maps
````

### 2️⃣ Instale as dependências

```bash
npm install
```

### 3️⃣ Configure a chave da API

Crie ou edite o arquivo `.env` e adicione:

```env
VITE_GEMINI_API_KEY=SUA_CHAVE_AQUI
```

### 4️⃣ Inicie o servidor de desenvolvimento

```bash
npm run dev
```

### 5️⃣ Acesse a aplicação

```text
http://localhost:5173
```

---

## 🐳 Implantação com Docker (Recomendado para VPS)

O projeto já inclui configuração com **Docker Compose**, facilitando o deploy em servidores Linux.

### 1️⃣ Construa e inicie os contêineres

```bash
docker-compose up -d --build
```

### 2️⃣ Acesse a aplicação

A aplicação estará disponível na porta definida no `docker-compose.yml`
(padrão: **80** ou **3000**, conforme configuração).

---

## 🗂️ Estrutura do Projeto

```text
src/
├── components/        # Componentes React (Login, Prospecção)
├── services/          # Lógica de negócios e integrações externas
│   ├── gemini.ts
│   ├── crm.ts
│   └── auth.ts
├── types/             # Definições de tipos TypeScript
├── config/            # Configuração do build e variáveis de ambiente
nginx/
├── default.conf       # Configuração do Nginx para Docker
```

---

## ⚙️ Configuração do CRM

No menu **Configurações** da aplicação, é possível definir:

* **URL do Webhook / API**
  Endpoint para onde os leads serão enviados.

* **Token**
  Chave de autenticação (Bearer ou ApiKey).

* **Modo Estrito**
  Remove campos conflitantes (recomendado para Evolution API).

* **Proxy CORS**
  Útil para execução local quando houver bloqueios de CORS.

---

## 🧪 Executar Localmente (Resumo)

### Pré-requisitos

* Node.js

### Passos rápidos

```bash
npm install
```

Defina no `.env`:

```env
VITE_GEMINI_API_KEY=SUA_CHAVE_GEMINI
```

Execute:

```bash
npm run dev
```

---

## 📄 Licença

Este projeto é **proprietário** e desenvolvido exclusivamente para a **Atendo Sistemas em parceria com GF Sistemas**.

```


Só dizer 🚀
```
