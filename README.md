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

## 🧠 Tecnologias

O projeto é **100% PHP + JavaScript** (sem React/Node no frontend).

- **Backend:** PHP (APIs em `/api/`), MySQL/MariaDB
- **Frontend:** HTML servido por PHP (`index.php`) + JavaScript vanilla (`assets/js/app.js`)
- **Estilização:** Tailwind CSS (via CDN)
- **API de Busca:** Integração com serviço de busca (Google Maps); IA opcional via Gemini

---

## 📦 Requisitos

- **PHP 7.4+** (recomendado 8.x) com extensões: pdo, pdo_mysql, curl, json, mbstring
- **MySQL 5.7+** ou **MariaDB 10.2+**
- Servidor web (Apache com mod_rewrite ou Nginx com PHP-FPM)
- Opcional: Docker & Docker Compose para implantação em contêiner
- Chave de API do **Google Cloud** com acesso à **Gemini API** (se usar IA)

---

## 🖥️ Instalação e Execução Local

### 1️⃣ Clone o repositório

```bash
git clone https://github.com/seu-usuario/MapsProspector-Pro.git
cd MapsProspector-Pro
```

### 2️⃣ Configure o banco de dados

Crie o banco `maps` e execute o schema:

```bash
mysql -u usuario -p maps < Database/maps_schema_full.sql
```

Ou importe `Database/maps_schema_full.sql` pelo phpMyAdmin. Veja `Database/README.md` para detalhes.

### 3️⃣ Configure o PHP

Edite `config/config.php` (ou use variáveis de ambiente) e defina:

- Conexão com o banco em `config/database.php`
- Chave da API de Busca (Google Maps)
- Opcional: `GEMINI_API_KEY` para uso de IA

### 4️⃣ Sirva o projeto

**XAMPP / Apache:** Coloque o projeto em `htdocs` e acesse `http://localhost/MapsProspector-Pro/` (ou o caminho configurado).

**Servidor PHP embutido:**

```bash
php -S localhost:8000
```

Acesse `http://localhost:8000/index.php`

### 5️⃣ Primeiro acesso

- **Login:** `admin@atendo.maps` / senha: `admin123`
- Altere a senha do admin após o primeiro acesso em produção.

---

## 🐳 Implantação com Docker

O projeto inclui **Dockerfile** com PHP + Apache (sem Node).

```bash
docker-compose up -d --build
```

A aplicação estará disponível na porta **3005** (ou a definida no `docker-compose.yml`). Configure as variáveis de ambiente (banco de dados, chaves de API) no `docker-compose.yml` ou em um arquivo `.env`.

Para uso em produção com banco externo, defina no ambiente do container as variáveis esperadas por `config/config.php` e `config/database.php`.

---

## 🗂️ Estrutura do Projeto

```
MapsProspector-Pro/
├── api/                  # Endpoints da API REST (PHP)
│   ├── auth.php         # Autenticação (login, logout, check)
│   ├── search.php       # Busca de leads
│   ├── history.php      # Histórico de buscas
│   ├── settings.php     # Configurações (webhook, API de busca)
│   ├── register.php     # Cadastro de empresa
│   ├── export.php       # Exportação para CRM
│   └── ...
├── config/              # Configurações PHP
│   ├── config.php       # Chaves e constantes
│   └── database.php     # Conexão com banco
├── includes/
│   └── functions.php   # Funções auxiliares
├── services/            # Serviços no servidor (PHP)
│   ├── scraperService.php  # API de busca (Google Maps)
│   └── gemini.php       # Integração Google Gemini
├── assets/
│   └── js/
│       └── app.js      # Interface (JavaScript vanilla)
├── Database/            # Scripts SQL (schema e migrações)
├── index.php            # Página principal (HTML + carrega app.js)
├── index.html           # Redireciona para index.php
└── .htaccess            # Regras Apache
```

---

## ⚙️ Configuração do CRM

No menu **Configurações** da aplicação é possível definir:

* **URL do Webhook / API** — Endpoint para onde os leads serão enviados.
* **Token** — Chave de autenticação (header apikey).
* **Modo Estrito** — Remove campos conflitantes (recomendado para Evolution API).
* **Proxy CORS** — Útil quando houver bloqueios de CORS.
* **API de Busca** — Chave da API de Busca (apenas Super Admin; em **API de Busca** no menu).

---

## 📄 Licença

Este projeto é **proprietário** e desenvolvido exclusivamente para a **Atendo Sistemas em parceria com GF Sistemas**.
