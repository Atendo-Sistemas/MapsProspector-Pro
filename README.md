# Atendo Maps - Ferramenta de Prospecção Inteligente

O Atendo Maps é uma aplicação web de prospecção B2B que utiliza a inteligência artificial do Google Gemini para minerar dados de empresas diretamente do Google Maps. A ferramenta permite exportar leads qualificados para o CRM da Atendo ou outras plataformas via Webhooks (como n8n), facilitando o processo de vendas.

## Principais Funcionalidades

*   **Prospecção Inteligente:** Busca de empresas por ramo de atividade e localização utilizando a API do Google Gemini (modelos Flash 2.0 e 2.5).
*   **Integração com Google Maps:** Validação de existência e extração de dados reais (Nome, Endereço, Telefone, Site, Link do Maps).
*   **Exportação para CRM:** Envio direto de leads para o Atendo CRM ou via Webhook (n8n/Evolution API).
*   **Geolocalização:** Opção de busca baseada na localização atual do usuário via GPS.
*   **Histórico de Buscas:** Registro local das pesquisas realizadas para fácil acesso posterior.
*   **Configuração Flexível:** Personalização de endpoints de API, tokens e modelos de IA.

## Tecnologias Utilizadas

Este projeto foi desenvolvido utilizando uma stack moderna e performática:

*   **Frontend:** React 19 (Hooks, Functional Components)
*   **Linguagem:** TypeScript
*   **Build Tool:** Vite
*   **Estilização:** Tailwind CSS
*   **IA:** Google GenAI SDK (`@google/genai`)
*   **Infraestrutura:** Docker & Nginx

## Requisitos

Para rodar o projeto localmente ou em produção, você precisará de:

*   Node.js (versão 18 ou superior)
*   Docker & Docker Compose (para implantação em contêiner)
*   Uma Chave de API do Google Cloud com acesso ao Gemini API.

## Instalação e Execução Local

1.  **Clone o repositório:**
    ```bash
    git clone https://github.com/seu-usuario/atendo-maps.git
    cd atendo-maps
    ```

2.  **Instale as dependências:**
    ```bash
    npm install
    ```

3.  **Configure a Chave de API:**
    Abra o arquivo `vite.config.ts` e insira sua chave na propriedade `process.env.API_KEY`:
    ```typescript
    define: {
      'process.env.API_KEY': JSON.stringify("SUA_CHAVE_AQUI")
    },
    ```

4.  **Inicie o servidor de desenvolvimento:**
    ```bash
    npm run dev
    ```
    Acesse a aplicação em `http://localhost:3000`.

## Implantação com Docker (Recomendado para VPS)

O projeto já inclui configuração para Docker Compose, facilitando o deploy em servidores Linux.

1.  **Construa e inicie o contêiner:**
    ```bash
    docker-compose up -d --build
    ```

2.  **Acesse a aplicação:**
    A aplicação estará disponível na porta `3005` do servidor (configurável no `docker-compose.yml`).

## Estrutura do Projeto

*   `src/components`: Componentes React da interface (Login, Prospecção).
*   `src/services`: Lógica de negócios e integrações externas (Gemini AI, API CRM, Autenticação).
*   `src/types.ts`: Definições de tipos TypeScript.
*   `vite.config.ts`: Configuração do build e injeção de variáveis de ambiente.
*   `nginx.conf`: Configuração do servidor web para o ambiente Docker.

## Configuração do CRM

No menu "Configurações" dentro da aplicação, você pode definir:

*   **URL do Webhook/API:** O endpoint para onde os dados dos leads serão enviados.
*   **Token:** Chave de autenticação (Bearer ou ApiKey).
*   **Modo Estrito:** Remove campos conflitantes (recomendado para Evolution API).
*   **Proxy CORS:** Útil se estiver rodando localmente e enfrentando bloqueios de CORS.

## Licença

Este projeto é proprietário e desenvolvido para a Atendo Sistemas. 
