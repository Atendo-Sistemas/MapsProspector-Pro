# MapsProspector Pro - Versão PHP/XAMPP

Versão PHP completa do MapsProspector Pro, migrada do React/TypeScript original para rodar nativamente no XAMPP.

## 🎯 Características

- ✅ **100% PHP** - Sem dependências de Node.js
- ✅ **MySQL/MariaDB** - Banco de dados relacional
- ✅ **API RESTful** - Endpoints organizados
- ✅ **Interface Moderna** - Mantém o design original
- ✅ **JavaScript Vanilla** - Sem frameworks pesados
- ✅ **Pronto para XAMPP** - Configuração otimizada

## 📦 Estrutura do Projeto

```
MapsProspector-Pro/
├── api/                  # Endpoints da API REST
│   ├── auth.php         # Autenticação
│   ├── search.php       # Busca de leads
│   ├── history.php      # Histórico de buscas
│   ├── settings.php     # Configurações
│   └── export.php       # Exportação para CRM
├── config/              # Configurações
│   ├── config.php       # Configurações gerais
│   └── database.php     # Conexão com banco
├── includes/            # Funções auxiliares
│   └── functions.php    # Utilitários
├── services/            # Serviços externos
│   └── gemini.php       # Integração Google Gemini
├── assets/              # Arquivos estáticos
│   └── js/
│       └── app.js       # JavaScript principal
├── index.php            # Página principal
├── database.sql         # Script de criação do banco
├── .htaccess            # Configuração Apache
└── INSTALACAO.md        # Guia de instalação
```

## 🚀 Instalação Rápida

1. **Importe o banco de dados:**
   ```sql
   mysql -u root -p < database.sql
   ```
   Ou via phpMyAdmin: execute o conteúdo de `database.sql`

2. **Configure a chave da API:**
   Edite `config/config.php` e defina:
   ```php
   define('GEMINI_API_KEY', 'sua-chave-aqui');
   ```

3. **Acesse:**
   ```
   http://localhost/MapsProspector-Pro/
   ```

Para instruções detalhadas, consulte [INSTALACAO.md](INSTALACAO.md).

## 🔧 Tecnologias Utilizadas

- **Backend:** PHP 7.4+
- **Banco de Dados:** MySQL/MariaDB
- **Frontend:** HTML5, CSS3 (Tailwind CSS), JavaScript ES6+
- **API Externa:** Google Gemini API
- **Servidor:** Apache (XAMPP)

## 📋 Funcionalidades

### ✅ Implementadas

- [x] Busca de leads via Google Gemini
- [x] Integração com Google Maps
- [x] Histórico de buscas (banco de dados)
- [x] Configurações de CRM (salvas no banco)
- [x] Exportação para CRM/Webhook
- [x] Geolocalização GPS
- [x] Autenticação de usuários
- [x] Interface responsiva

### 🔄 Diferenças da Versão React

| Recurso | React Original | PHP/XAMPP |
|---------|---------------|-----------|
| Armazenamento | localStorage | MySQL |
| Autenticação | Mock | Sessões PHP |
| Histórico | LocalStorage | Banco de dados |
| Configurações | LocalStorage | Banco de dados |
| Build | Vite/Node | Nenhum (PHP puro) |

## 🔐 Segurança

- ✅ Prepared Statements (SQL Injection)
- ✅ Sanitização de inputs
- ✅ Validação de sessões
- ✅ Headers de segurança (CORS, XSS Protection)
- ✅ Proteção de arquivos sensíveis (.htaccess)

## 📝 API Endpoints

### Autenticação
- `POST /api/auth.php` - Login/Logout/Check

### Busca
- `POST /api/search.php` - Buscar leads

### Histórico
- `GET /api/history.php` - Listar histórico
- `DELETE /api/history.php` - Limpar histórico

### Configurações
- `GET /api/settings.php` - Obter configurações
- `POST /api/settings.php` - Salvar configurações

### Exportação
- `POST /api/export.php` - Exportar lead para CRM

## 🗄️ Banco de Dados

### Tabelas

- **users** - Usuários do sistema
- **settings** - Configurações por usuário
- **search_history** - Histórico de buscas
- **leads** - Leads encontrados
- **sessions** - Sessões ativas (opcional)

## 🔄 Migração do React

Este projeto foi completamente migrado do React/TypeScript original:

- ✅ Toda lógica de negócio movida para PHP
- ✅ Interface mantém o mesmo design
- ✅ Funcionalidades preservadas
- ✅ Melhorias de persistência (banco vs localStorage)

## 📞 Suporte

Para problemas:
1. Verifique os logs do Apache: `C:\xampp\apache\logs\error.log`
2. Consulte [INSTALACAO.md](INSTALACAO.md) para troubleshooting
3. Verifique se a chave do Gemini está correta

## 📄 Licença

Proprietário - Atendo Tecnologia

---

**Versão PHP** - Desenvolvido para XAMPP  
**Baseado na versão React original**
