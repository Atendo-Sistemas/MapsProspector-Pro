-- ============================================================
-- Dicionário CNAE 2.0 - Schema Principal
-- Compatível com: MySQL 8.0+, PostgreSQL 12+, MariaDB 10.5+
-- Versão: 1.0.0
-- Data: {data}
-- ============================================================

-- Tabela principal do dicionário
CREATE TABLE IF NOT EXISTS dicionario_cnae (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_cnae VARCHAR(7) NOT NULL,
    descricao_cnae VARCHAR(255) NOT NULL,
    secao VARCHAR(2) NOT NULL,
    divisao VARCHAR(2) NOT NULL,
    grupo VARCHAR(3) NOT NULL,
    classe VARCHAR(4) NOT NULL,
    termo VARCHAR(255) NOT NULL,
    tipo VARCHAR(50) NOT NULL DEFAULT 'descricao-oficial',
    relevancia INT DEFAULT 50,
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_codigo_cnae (codigo_cnae),
    INDEX idx_termo (termo),
    INDEX idx_secao (secao),
    INDEX idx_divisao (divisao),
    INDEX idx_tipo (tipo),
    INDEX idx_relevancia (relevancia DESC),
    FULLTEXT INDEX idx_fulltext (termo, descricao_cnae)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Inserção de dados
-- ============================================================

-- Total de registros: 10483

INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111301', '', 'A', '01', '', '', 'Cultivo de arroz', 'nome_oficial', 100, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111301', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111301', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111301', '', 'A', '01', '', '', 'Arroz', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111301', '', 'A', '01', '', '', 'Arroz', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111301', '', 'A', '01', '', '', 'Cultivo de arroz', 'palavra_chave', 90, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111302', '', 'A', '01', '', '', 'Cultivo de milho', 'nome_oficial', 100, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111302', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111302', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111302', '', 'A', '01', '', '', 'Milho', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111302', '', 'A', '01', '', '', 'Milho', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111302', '', 'A', '01', '', '', 'Cultivo de milho', 'palavra_chave', 90, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111303', '', 'A', '01', '', '', 'Cultivo de trigo', 'nome_oficial', 100, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111303', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111303', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111303', '', 'A', '01', '', '', 'Trigo', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111303', '', 'A', '01', '', '', 'Trigo', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111303', '', 'A', '01', '', '', 'Cultivo de trigo', 'palavra_chave', 90, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111399', '', 'A', '01', '', '', 'Cultivo de outros cereais não especificados anteriormente', 'nome_oficial', 100, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111399', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111399', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111399', '', 'A', '01', '', '', 'Outros', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111399', '', 'A', '01', '', '', 'Outros', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111399', '', 'A', '01', '', '', 'Cereais', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111399', '', 'A', '01', '', '', 'Cereais', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111399', '', 'A', '01', '', '', 'Especificados', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111399', '', 'A', '01', '', '', 'Especificados', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0111399', '', 'A', '01', '', '', 'Cultivo de outros cereais não especificados anteriormente', 'palavra_chave', 90, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112101', '', 'A', '01', '', '', 'Cultivo de algodão herbáceo', 'nome_oficial', 100, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112101', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112101', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112101', '', 'A', '01', '', '', 'Algodão', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112101', '', 'A', '01', '', '', 'Algodao', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112101', '', 'A', '01', '', '', 'Herbáceo', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112101', '', 'A', '01', '', '', 'Herbaceo', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112101', '', 'A', '01', '', '', 'Cultivo de algodão herbáceo', 'palavra_chave', 90, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112102', '', 'A', '01', '', '', 'Cultivo de juta', 'nome_oficial', 100, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112102', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112102', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112102', '', 'A', '01', '', '', 'Juta', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112102', '', 'A', '01', '', '', 'Juta', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112102', '', 'A', '01', '', '', 'Cultivo de juta', 'palavra_chave', 90, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112199', '', 'A', '01', '', '', 'Cultivo de outras fibras de lavoura temporária não especificadas anteriormente', 'nome_oficial', 100, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112199', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112199', '', 'A', '01', '', '', 'Cultivo', 'produto', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112199', '', 'A', '01', '', '', 'Outras', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112199', '', 'A', '01', '', '', 'Outras', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112199', '', 'A', '01', '', '', 'Fibras', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112199', '', 'A', '01', '', '', 'Fibras', 'atividade', 85, '');
INSERT INTO dicionario_cnae (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia, data_criacao) VALUES ('0112199', '', 'A', '01', '', '', 'Lavoura', 'atividade', 85, '');

-- Total: 10483 registros (exemplo com 50 inseridos acima)
-- Para importar todos os dados, use o arquivo CSV ou o arquivo JSON
