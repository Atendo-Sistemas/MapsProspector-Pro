# Dicionário CNAE 2.0

O **Dicionário CNAE 2.0** é um projeto open-source que expande a tabela oficial de Classificação Nacional de Atividades Econômicas (CNAE) do Brasil. Ele adiciona termos associados, sinônimos e dados estruturados para facilitar buscas, normalização e classificação de empresas e atividades.

## 📊 Estatísticas do Projeto

| Métrica | Valor |
|---------|-------|
| **Termos no Dicionário** | 10,483 |
| **CNAEs Únicos** | 1,441 |
| **Seções Cobertas** | 21 |
| **Divisões Cobertas** | 87 |
| **Grupos Cobertos** | 85 |
| **Classes Cobertas** | 152 |
| **Termos por CNAE (média)** | 7.3 |
| **Relevância Média** | 86.4 |

## 📁 Estrutura do Projeto

O projeto é organizado em diferentes formatos para facilitar a integração com diversos sistemas:

- **CSV:** Tabelas otimizadas para importação em banco de dados (MySQL, PostgreSQL, etc.).
- **JSON:** Arquivos hierárquicos divididos por Seção, Divisão, Grupo e Classe.
- **SQL:** Scripts de criação de tabelas, índices e inserção de dados.
- **Documentação:** Explicação da metodologia e critérios de classificação.
- **Testes:** Scripts de validação de dados e integridade.

## 🚀 Como Usar

### 1. Importação para Banco de Dados
Utilize os arquivos na pasta `/sql` para criar as tabelas e importar os dados:
```sql
SOURCE sql/dicionario_cnae.sql;
SOURCE sql/tabelas_auxiliares.sql;
```

### 2. Leitura via Python/JSON
O arquivo principal está em `/json/completo/dicionario_cnae.json`. Você também pode usar os arquivos divididos por categoria.
```python
import json

with open('json/completo/dicionario_cnae.json', 'r', encoding='utf-8') as f:
    data = json.load(f)
    print(f"Total de termos: 10483")
```

### 3. Busca e Classificação
O campo `tipo` indica a natureza do termo:
- `descricao-oficial`: A descrição exata do IBGE (relevância 100)
- `palavra-chave`: Termos extraídos da descrição (relevância 85)
- `sinônimo`: Termos relacionados e variações (relevância 70)

## 🤝 Contribuição
Este projeto visa facilitar a classificação de atividades econômicas no Brasil. Sinta-se livre para contribuir com mais sinônimos ou melhorias na estrutura.

## 📄 Licença
Licenciado sob MIT License. Consulte o arquivo `LICENSE` para mais detalhes.
