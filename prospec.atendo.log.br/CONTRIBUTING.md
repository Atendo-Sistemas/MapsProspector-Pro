# Guia de Contribuição - MapsProspector Pro

## Configuração do Git para UTF-8

Para evitar problemas de encoding nos commits (caracteres estranhos como Ã§, Ã£), configure o Git corretamente:

### Configuração Global (recomendado)

```bash
git config --global core.quotepath false
git config --global i18n.commitEncoding utf-8
git config --global i18n.logOutputEncoding utf-8
```

### Configuração Local (apenas este projeto)

```bash
git config --local core.quotepath false
git config --local i18n.commitEncoding utf-8
git config --local i18n.logOutputEncoding utf-8
```

## Padrão de Commits

Use o padrão **Conventional Commits** para mensagens de commit:

```
<tipo>(<escopo>): <descrição>

[corpo opcional]

[rodapé opcional]
```

### Tipos de Commit

- `feat`: Nova funcionalidade
- `fix`: Correção de bug
- `docs`: Mudanças na documentação
- `style`: Formatação, ponto e vírgula faltando, etc (não afeta código)
- `refactor`: Refatoração de código
- `perf`: Melhoria de performance
- `test`: Adicionando ou corrigindo testes
- `chore`: Mudanças em build, dependências, etc

### Exemplos

```bash
# Funcionalidade
git commit -m "feat(api): adiciona endpoint de busca de leads"

# Correção
git commit -m "fix(auth): corrige encoding UTF-8 em mensagens de erro"

# Documentação
git commit -m "docs: atualiza guia de instalação"

# Múltiplas linhas
git commit -m "feat: migração completa para PHP/XAMPP

- Implementação de API RESTful completa
- Banco de dados MySQL
- Sistema de autenticação com sessões PHP
- Documentação atualizada"
```

## Método Recomendado para Commits com UTF-8

Para garantir que commits com acentos funcionem corretamente, use um dos métodos abaixo:

### Método 1: Usar arquivo de mensagem (RECOMENDADO)

```bash
# 1. Crie um arquivo com sua mensagem (UTF-8)
echo "feat: adiciona nova funcionalidade" > .commit-msg

# 2. Faça o commit usando o arquivo
git commit -F .commit-msg

# 3. Remova o arquivo temporário
rm .commit-msg
```

### Método 2: Script PowerShell (Windows)

```powershell
# Use o script fornecido
.\scripts\git-commit-utf8.ps1 "feat: sua mensagem aqui"
```

### Método 3: Configurar terminal antes do commit

```powershell
# PowerShell
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
chcp 65001
git commit -m "sua mensagem"
```

## Verificação de Encoding

Antes de fazer commit, verifique se o encoding está correto:

```bash
# Ver configurações atuais
git config --list | grep -i encoding

# Ver último commit
git log -1 --pretty=format:"%B"
```

## Solução de Problemas

Se ainda aparecerem caracteres estranhos (Ã§, Ã£, etc):

1. **Use sempre arquivo de mensagem:**
   ```bash
   # Método mais confiável
   git commit -F .commit-msg
   ```

2. **Verifique o encoding do arquivo:**
   - Use UTF-8 sem BOM
   - No VS Code: "Change File Encoding" → "Save with Encoding" → "UTF-8"

3. **Corrija commit anterior:**
   ```bash
   # Crie arquivo .commit-msg com mensagem correta
   git commit --amend -F .commit-msg
   ```

## Changelog

Sempre atualize o `services/changelog.md` ao fazer mudanças significativas:

1. Adicione uma nova entrada no topo
2. Use o formato de versão semântica: `[MAJOR.MINOR.PATCH]`
3. Documente mudanças em categorias: ✨ Adicionado, 🚀 Melhorias, 🐛 Correções, etc.

---

**Importante:** Sempre teste localmente antes de fazer push!
