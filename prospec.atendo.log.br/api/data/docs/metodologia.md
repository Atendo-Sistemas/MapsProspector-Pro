# Metodologia do Dicionário CNAE 2.0

## Objetivo
Este projeto visa criar o dicionário mais completo de linguagem comercial aplicada à classificação CNAE 2.0, servindo como base para mecanismos de busca inteligentes, IA generativa, classificação automática de empresas, ERPs, CRMs, marketplaces e portais de classificados.

## Metodologia de Geração

### 1. Coleta de Dados
Os dados foram extraídos da tabela oficial CNAE 2.0 publicada pelo IBGE (Instituto Brasileiro de Geografia e Estatística), contemplando todas as 1.301 subclasses.

### 2. Geração de Termos
Cada subclasse CNAE recebe automaticamente termos associados baseados em:
- Nome oficial da subclasse (tipo: nome_oficial)
- Palavras-chave extraídas da descrição (tipo: atividade/produto/servico)
- Variantes linguísticas (tipo: sinonimo/variante)
- Termos populares associados (tipo: termo_popular)

### 3. Classificação
Cada termo é classificado conforme seu tipo (nome_oficial, sinonimo, termo_popular, regionalismo, abreviacao, etc.) e sua origem (descrição oficial, linguagem popular, classificação semântica, etc.).

### 4. Normalização
Todo termo possui múltiplas versões normalizadas para busca: original, minúscula, sem acento, sem caracteres especiais, sem hífen, sem espaços duplicados e versão full-text.

## Estrutura Hierárquica
- Seção (1 letra: A-U) → 21 seções
- Divisão (2 dígitos: 01-99) → 87 divisões
- Grupo (3 dígitos) → 284 grupos
- Classe (4 dígitos) → 672 classes
- Subclasse (7 dígitos) → 1.301 subclasses

## Compatibilidade
O projeto é compatível com MySQL, MariaDB, PostgreSQL, SQLite, MongoDB, Elasticsearch, OpenSearch, Meilisearch e Typesense.
