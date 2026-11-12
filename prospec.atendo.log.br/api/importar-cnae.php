<?php
/**
 * importar-cnae.php — Script para importar dados CNAE no MySQL/MariaDB
 * 
 * Este script cria as tabelas e importa os dados do JSON no banco de dados
 * principal do seu sistema, para que a busca seja ainda mais rápida.
 * 
 * COMO USAR:
 * 1. Configure as credenciais do banco abaixo
 * 2. Execute: php importar-cnae.php
 * 3. O script criará a tabela e importará os dados
 */

// ============================================================
// CONFIGURAÇÃO DO BANCO DE DADOS
// ============================================================
// Use as mesmas credenciais do seu sistema principal
define('DB_HOST', 'localhost');
define('DB_NAME', 'nome_do_banco');
define('DB_USER', 'usuario_do_banco');
define('DB_PASS', 'senha_do_banco');
define('DB_CHARSET', 'utf8mb4');

// Caminho do arquivo JSON (ajuste conforme necessário)
define('JSON_PATH', __DIR__ . '/data/dicionario_cnae.json');

// ============================================================

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    
    echo "Conectado ao banco de dados.\n";
    
    // Criar tabela
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS dicionario_cnae (
            id INT AUTO_INCREMENT PRIMARY KEY,
            codigo_cnae VARCHAR(7) NOT NULL,
            descricao_cnae VARCHAR(300) NOT NULL,
            secao VARCHAR(2) NOT NULL,
            divisao VARCHAR(2) NOT NULL,
            grupo VARCHAR(3) NOT NULL,
            classe VARCHAR(4) NOT NULL,
            termo VARCHAR(300) NOT NULL,
            tipo VARCHAR(50) NOT NULL DEFAULT 'descricao-oficial',
            relevancia INT DEFAULT 50,
            INDEX idx_codigo (codigo_cnae),
            INDEX idx_termo (termo(100)),
            INDEX idx_secao (secao),
            INDEX idx_divisao (divisao),
            INDEX idx_tipo (tipo),
            INDEX idx_relevancia (relevancia DESC),
            INDEX idx_composto (codigo_cnae, tipo, relevancia),
            FULLTEXT INDEX idx_ft_busca (termo, descricao_cnae)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "Tabela criada/verificada.\n";
    
    // Verificar se já tem dados
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dicionario_cnae");
    $count = $stmt->fetch()['total'];
    
    if ($count > 0) {
        echo "Banco já possui {$count} registros. Pulando importação.\n";
        echo "Para forçar reimportação, execute: TRUNCATE TABLE dicionario_cnae;\n";
        exit;
    }
    
    // Carregar JSON
    if (!file_exists(JSON_PATH)) {
        echo "ERRO: Arquivo JSON não encontrado em " . JSON_PATH . "\n";
        exit(1);
    }
    
    $json = file_get_contents(JSON_PATH);
    $data = json_decode($json, true);
    
    if (!$data || !isset($data['termos'])) {
        echo "ERRO: JSON inválido ou sem dados.\n";
        exit(1);
    }
    
    $termos = $data['termos'];
    echo "Importando " . count($termos) . " registros...\n";
    
    $sql = "INSERT INTO dicionario_cnae 
            (codigo_cnae, descricao_cnae, secao, divisao, grupo, classe, termo, tipo, relevancia)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    
    $batchSize = 1000;
    $pdo->beginTransaction();
    
    for ($i = 0; $i < count($termos); $i += $batchSize) {
        $batch = array_slice($termos, $i, $batchSize);
        
        foreach ($batch as $t) {
            $stmt->execute([
                $t['codigo_cnae'] ?? '',
                $t['descricao_cnae'] ?? '',
                $t['secao'] ?? '',
                $t['divisao'] ?? '',
                $t['grupo'] ?? '',
                $t['classe'] ?? '',
                $t['termo'] ?? '',
                $t['tipo'] ?? 'descricao-oficial',
                $t['relevancia'] ?? 50
            ]);
        }
        
        $pdo->commit();
        $pdo->beginTransaction();
        
        echo "  " . min($i + $batchSize, count($termos)) . "/" . count($termos) . "\n";
    }
    
    $pdo->commit();
    
    // Verificar total
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dicionario_cnae");
    $total = $stmt->fetch()['total'];
    
    echo "\n✅ Importação concluída! Total: {$total} registros.\n";
    
} catch (PDOException $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}
