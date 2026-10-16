# Exemplos de API - Dicionário CNAE 2.0

## 1. Buscar CNAE por termo
```json
GET /api/cnae/search?q=restaurante&limit=10

Response:
{
  "total": 5,
  "resultados": [
    {
      "termo": "Restaurantes e similares",
      "codigo_cnae": "5611201",
      "descricao": "Restaurantes e similares",
      "relevancia": 100,
      "secao": "I",
      "divisao": "56"
    }
  ]
}
```

## 2. Buscar por código CNAE
```json
GET /api/cnae/{codigo}

Response:
{
  "codigo": "5611201",
  "descricao": "Restaurantes e similares",
  "secao": "I",
  "divisao": "56",
  "grupo": "561",
  "classe": "5611",
  "termos": ["restaurantes", "restaurante", "comida", "alimentação"]
}
```

## 3. Listar por seção
```json
GET /api/cnae/secao/J

Response:
{
  "secao": "J",
  "nome": "Informação e comunicação",
  "total_cnaes": 35,
  "cnaes": [...]
}
```

## 4. Sugestão de CNAE (autocomplete)
```json
GET /api/cnae/suggest?q=comercio&limit=5

Response:
{
  "sugestoes": [
    {"termo": "Comércio varejista", "codigo": "4711302", "score": 95},
    {"termo": "Comércio atacadista", "codigo": "4687702", "score": 90}
  ]
}
```
