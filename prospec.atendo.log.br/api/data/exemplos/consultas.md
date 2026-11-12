# Exemplos de Consultas SQL - Dicionário CNAE 2.0

## 1. Buscar termo por nome
```sql
SELECT * FROM dicionario_cnae 
WHERE termo LIKE '%café%' 
ORDER BY relevancia DESC 
LIMIT 20;
```

## 2. Buscar por código CNAE
```sql
SELECT d.*, c.descricao 
FROM dicionario_cnae d
INNER JOIN cnae_cadastro c ON d.codigo_cnae = c.codigo
WHERE d.codigo_cnae = '1093700';
```

## 3. Buscar por seção
```sql
SELECT DISTINCT codigo_cnae, descricao 
FROM dicionario_cnae 
WHERE secao = 'J'
ORDER BY relevancia DESC;
```

## 4. Buscar por tipo de termo
```sql
SELECT * FROM dicionario_cnae 
WHERE tipo = 'nome_oficial' 
ORDER BY termo;
```

## 5. Busca full-text
```sql
SELECT * FROM dicionario_cnae 
WHERE MATCH(termo, descricao) AGAINST('comércio atacado' IN NATURAL LANGUAGE MODE)
ORDER BY relevancia DESC;
```

## 6. Contagem por seção
```sql
SELECT secao, COUNT(DISTINCT codigo_cnae) as cnaes, COUNT(*) as termos
FROM dicionario_cnae
GROUP BY secao
ORDER BY termos DESC;
```

## 7. Top 10 CNAEs com mais termos
```sql
SELECT codigo_cnae, descricao, COUNT(*) as total_termos
FROM dicionario_cnae
GROUP BY codigo_cnae
ORDER BY total_termos DESC
LIMIT 10;
```
