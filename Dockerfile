# Estágio 1: Build da Aplicação
FROM node:20-alpine as builder

WORKDIR /app

# Copia os arquivos de dependência
COPY package.json package-lock.json* ./

# Instala as dependências
RUN npm install

# Copia o código fonte restante
COPY . .

# Executa o build de produção (Gera a pasta /dist)
RUN npm run build

# Estágio 2: Servidor Web (Nginx) para servir o estático
FROM nginx:alpine

# Remove configurações padrão do Nginx para evitar conflitos
RUN rm -rf /etc/nginx/conf.d/*

# Copia a configuração personalizada do Nginx (arquivo nginx.conf do projeto)
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Copia os arquivos estáticos gerados no estágio de build para o diretório do Nginx
COPY --from=builder /app/dist /usr/share/nginx/html

# Expõe a porta 80 (interna do container, que será mapeada para a 3005 no docker-compose)
EXPOSE 80

# Inicia o Nginx em primeiro plano
CMD ["nginx", "-g", "daemon off;"]
