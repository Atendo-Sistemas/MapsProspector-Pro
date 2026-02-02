# Guia de Instalação - MapsProspector Pro (PHP/XAMPP)

Este guia explica como instalar e configurar o MapsProspector Pro para rodar no XAMPP.

## 📋 Pré-requisitos

- **XAMPP** instalado (versão 7.4 ou superior)
- **PHP** 7.4 ou superior
- **MySQL/MariaDB** (incluído no XAMPP)
- **Chave de API do Google Gemini** (obtenha em [Google AI Studio](https://makersuite.google.com/app/apikey))

---

## 🚀 Passo a Passo de Instalação

### 1. Preparar o Banco de Dados

1. Abra o **phpMyAdmin** (acesse `http://localhost/phpmyadmin`)
2. Execute o script SQL fornecido:
   - Abra o arquivo `Database/maps_schema_full.sql` no editor de texto
   - Copie todo o conteúdo
   - No phpMyAdmin, vá em "SQL" e cole o conteúdo
   - Clique em "Executar"
   
   Ou via linha de comando:
   ```bash
   mysql -u root -p maps < Database/maps_schema_full.sql
   ```

3. Verifique se o banco `maps` foi criado com as tabelas:
   - `users`
   - `settings`
   - `search_history`
   - `leads`
   - `sessions`

### 2. Configurar a Conexão com Banco de Dados

Edite o arquivo `config/database.php` e ajuste se necessário:

```php
private $host = 'localhost';
private $dbname = 'maps';
private $username = 'root';  // Padrão XAMPP
private $password = '';      // Padrão XAMPP (vazio)
```

**Nota:** Se você alterou a senha do MySQL no XAMPP, atualize o campo `$password`.

### 3. Configurar a Chave da API Gemini

Edite o arquivo `config/config.php` e substitua:

```php
define('GEMINI_API_KEY', 'SUA_CHAVE_AQUI');
```

Pela sua chave real:

```php
define('GEMINI_API_KEY', 'AIzaSy...sua-chave-aqui');
```

**Alternativa:** Você pode usar variável de ambiente:

1. No Windows, crie/edite o arquivo `.env` na raiz do projeto (ou configure no sistema)
2. Adicione: `GEMINI_API_KEY=sua-chave-aqui`
3. O PHP lerá automaticamente via `getenv('GEMINI_API_KEY')`

### 4. Verificar Permissões

Certifique-se de que o Apache tem permissão para:
- Ler arquivos na pasta do projeto
- Escrever logs (se necessário)

No Windows/XAMPP, geralmente não há problemas de permissão.

### 5. Iniciar Serviços no XAMPP

1. Abra o **XAMPP Control Panel**
2. Inicie os serviços:
   - ✅ **Apache**
   - ✅ **MySQL**

### 6. Acessar a Aplicação

Abra seu navegador e acesse:

```
http://localhost/MapsProspector-Pro/
```

Ou se estiver na raiz do htdocs:

```
http://localhost/MapsProspector-Pro/index.php
```

---

## ⚙️ Configurações Adicionais

### Habilitar mod_rewrite (Apache)

O arquivo `.htaccess` já está configurado. Se houver problemas:

1. Abra `httpd.conf` do Apache (geralmente em `C:\xampp\apache\conf\`)
2. Procure por `LoadModule rewrite_module` e descomente (remova o `#`)
3. Procure por `<Directory "C:/xampp/htdocs">` e altere `AllowOverride None` para `AllowOverride All`
4. Reinicie o Apache

### Configurar PHP (se necessário)

Edite `php.ini` (geralmente em `C:\xampp\php\php.ini`):

```ini
; Habilitar extensões necessárias
extension=curl
extension=mysqli
extension=pdo_mysql

; Aumentar limites se necessário
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
```

---

## 🔐 Primeiro Acesso

1. Ao acessar a aplicação, você verá a tela de login
2. Clique em **"Acessar Plataforma"**
3. O sistema criará automaticamente um usuário padrão (se não existir)
4. Configure sua integração com CRM nas **Configurações**

---

## 📝 Estrutura de Diretórios

```
MapsProspector-Pro/
├── api/              # Endpoints da API
│   ├── auth.php
│   ├── search.php
│   ├── history.php
│   ├── settings.php
│   └── export.php
├── config/           # Configurações
│   ├── config.php
│   └── database.php
├── includes/         # Funções auxiliares
│   └── functions.php
├── services/         # Serviços (Gemini, etc)
│   └── gemini.php
├── assets/           # Arquivos estáticos
│   └── js/
│       └── app.js
├── index.php         # Página principal
├── database.sql      # Script de criação do banco
├── .htaccess         # Configuração Apache
└── INSTALACAO.md     # Este arquivo
```

---

## 🐛 Solução de Problemas

### Erro: "Chave de API não configurada"
- Verifique se a chave está correta em `config/config.php`
- Certifique-se de que não há espaços extras na chave

### Erro: "Erro ao conectar com o banco de dados"
- Verifique se o MySQL está rodando no XAMPP
- Confirme usuário/senha em `config/database.php`
- Verifique se o banco `maps` existe

### Erro 404 ao acessar rotas
- Verifique se `mod_rewrite` está habilitado
- Confirme que `.htaccess` está na raiz do projeto
- Verifique `AllowOverride All` no Apache

### CORS ou erros de conexão com API
- A aplicação já inclui headers CORS
- Se usar proxy externo, configure em Configurações

### Leads não aparecem
- Verifique se a chave do Gemini está correta
- Confira os logs do Apache/PHP para erros
- Teste a chave diretamente na API do Google

---

## 🔄 Atualizações Futuras

Para atualizar o sistema:

1. Faça backup do banco de dados
2. Substitua os arquivos (exceto `config/config.php` se tiver alterações)
3. Execute scripts de migração SQL se houver
4. Limpe cache do navegador

---

## 📞 Suporte

Para problemas ou dúvidas:
- Verifique os logs do Apache em `C:\xampp\apache\logs\error.log`
- Verifique os logs do PHP (se habilitado)
- Consulte a documentação do Google Gemini API

---

## ✅ Checklist de Instalação

- [ ] XAMPP instalado e funcionando
- [ ] Banco de dados `maps` criado
- [ ] Tabelas criadas corretamente
- [ ] Chave da API Gemini configurada
- [ ] Apache e MySQL rodando
- [ ] Aplicação acessível no navegador
- [ ] Login funcionando
- [ ] Configurações salvas no banco

---

**Desenvolvido para Atendo Tecnologia em parceria com GFSISTEMA**  
Versão PHP - 2024
