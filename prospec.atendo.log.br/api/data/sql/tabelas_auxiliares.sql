-- ============================================================
-- Dicionário CNAE 2.0 - Tabelas Auxiliares
-- ============================================================

-- Tabela de seções CNAE
CREATE TABLE IF NOT EXISTS secoes_cnae (
    codigo VARCHAR(2) PRIMARY KEY,
    descricao VARCHAR(100) NOT NULL,
    total_divisoes INT DEFAULT 0,
    total_subclasses INT DEFAULT 0
);

-- Tabela de divisões CNAE
CREATE TABLE IF NOT EXISTS divisoes_cnae (
    codigo VARCHAR(2) PRIMARY KEY,
    descricao VARCHAR(100) NOT NULL,
    secao VARCHAR(2) NOT NULL,
    total_grupos INT DEFAULT 0,
    total_subclasses INT DEFAULT 0,
    FOREIGN KEY (secao) REFERENCES secoes_cnae(codigo)
);

-- Tabela de sinônimos
CREATE TABLE IF NOT EXISTS sinonimos_cnae (
    id INT AUTO_INCREMENT PRIMARY KEY,
    codigo_cnae VARCHAR(7) NOT NULL,
    sinonimo VARCHAR(255) NOT NULL,
    termo_original VARCHAR(255),
    relevancia INT DEFAULT 50,
    FOREIGN KEY (codigo_cnae) REFERENCES dicionario_cnae(codigo_cnae),
    INDEX idx_sinonimo (sinonimo),
    INDEX idx_cnae_sinonimo (codigo_cnae)
);

-- Tabela de metadados
CREATE TABLE IF NOT EXISTS metadados_cnae (
    chave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(500) NOT NULL,
    data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Inserir metadados
INSERT INTO metadados_cnae (chave, valor) VALUES
('versao_cnae', '2.0 (versão 2.3)'),
('fonte', 'IBGE - Instituto Brasileiro de Geografia e Estatística'),
('total_subclasses', '1301'),
('total_secoes', '21'),
('data_atualizacao', '{data}');
-- Data de geração: 2026-07-29 13:51:41
