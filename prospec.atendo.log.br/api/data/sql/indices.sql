-- ============================================================
-- Dicionário CNAE 2.0 - Índices Otimizados
-- ============================================================

-- MySQL / MariaDB
ALTER TABLE dicionario_cnae ADD INDEX idx_composto_busca (codigo_cnae, tipo, relevancia DESC);
ALTER TABLE dicionario_cnae ADD INDEX idx_secao_divisao (secao, divisao);
ALTER TABLE dicionario_cnae ADD INDEX idx_busca_termo (termo(100), relevancia DESC);

-- PostgreSQL (alternativa)
-- CREATE INDEX idx_composto_busca ON dicionario_cnae (codigo_cnae, tipo, relevancia DESC);
-- CREATE INDEX idx_secao_divisao ON dicionario_cnae (secao, divisao);
-- CREATE INDEX idx_busca_termo ON dicionario_cnae USING gin(to_tsvector('portuguese', termo));

-- Elasticsearch mapping (JSON)
-- {
--   "mappings": {
--     "properties": {
--       "codigo_cnae": { "type": "keyword" },
--       "descricao_cnae": { "type": "text", "analyzer": "brazilian" },
--       "termo": { "type": "text", "analyzer": "brazilian" },
--       "tipo": { "type": "keyword" },
--       "relevancia": { "type": "integer" },
--       "secao": { "type": "keyword" },
--       "divisao": { "type": "keyword" }
--     }
--   }
-- }
