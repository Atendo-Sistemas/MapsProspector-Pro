<?php
/**
 * Script de Verificação - MapsProspector Pro
 * Use este arquivo para verificar se tudo está configurado corretamente
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

echo "<h1>Verificação do Sistema - MapsProspector Pro</h1>";
echo "<style>body{font-family:Arial;padding:20px;} .ok{color:green;} .erro{color:red;} .info{color:blue;}</style>";

// 1. Verificar PHP
echo "<h2>1. Verificação do PHP</h2>";
echo "<p>Versão do PHP: <strong>" . phpversion() . "</strong></p>";
if (version_compare(phpversion(), '7.4.0', '>=')) {
    echo "<p class='ok'>✓ Versão do PHP compatível (7.4+)</p>";
} else {
    echo "<p class='erro'>✗ Versão do PHP muito antiga. Necessário PHP 7.4 ou superior.</p>";
}

// 2. Verificar extensões
echo "<h2>2. Extensões PHP</h2>";
$extensoes = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring'];
foreach ($extensoes as $ext) {
    if (extension_loaded($ext)) {
        echo "<p class='ok'>✓ Extensão $ext carregada</p>";
    } else {
        echo "<p class='erro'>✗ Extensão $ext NÃO encontrada</p>";
    }
}

// 3. Verificar banco de dados (.env deve ser carregado antes para DB_*)
echo "<h2>3. Conexão com Banco de Dados</h2>";
try {
    require_once __DIR__ . '/config/config.php';
    require_once __DIR__ . '/config/database.php';
    $db = Database::getInstance()->getConnection();
    echo "<p class='ok'>✓ Conexão com banco de dados estabelecida</p>";
    
    // Verifica se as tabelas existem
    $tabelas = ['users', 'settings', 'search_history', 'leads', 'sessions'];
    foreach ($tabelas as $tabela) {
        $stmt = $db->query("SHOW TABLES LIKE '$tabela'");
        if ($stmt->rowCount() > 0) {
            echo "<p class='ok'>✓ Tabela '$tabela' existe</p>";
        } else {
            echo "<p class='erro'>✗ Tabela '$tabela' NÃO existe</p>";
        }
    }
} catch (Exception $e) {
    echo "<p class='erro'>✗ Erro ao conectar: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='info'>💡 Solução: Execute o script database.sql no phpMyAdmin</p>";
}

// 4. Verificar chave da API de Busca (config já carregado no passo 3)
echo "<h2>4. Configuração da API de Busca</h2>";
if (defined('SCRAPER_API_KEY') && !empty(trim((string)SCRAPER_API_KEY))) {
    echo "<p class='ok'>✓ Chave da API de Busca (Scraper) configurada</p>";
} else {
    echo "<p class='erro'>✗ Chave da API de Busca não configurada (SCRAPER_API_KEY no .env ou Configurações)</p>";
}

// 5. Verificar permissões
echo "<h2>5. Permissões de Arquivos</h2>";
$dirs = ['logs', 'api', 'config'];
foreach ($dirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "<p class='ok'>✓ Diretório '$dir' é gravável</p>";
        } else {
            echo "<p class='info'>⚠ Diretório '$dir' não é gravável (pode causar problemas com logs)</p>";
        }
    } else {
        echo "<p class='info'>⚠ Diretório '$dir' não existe</p>";
    }
}

// 6. Verificar arquivos principais
echo "<h2>6. Arquivos Principais</h2>";
$arquivos = [
    'index.php',
    'config/config.php',
    'config/database.php',
    'includes/functions.php',
    'api/auth.php',
    'api/search.php',
    'api/settings.php',
    'api/history.php',
    'api/export.php',
    'services/scraperService.php',
    'assets/js/app.js'
];

foreach ($arquivos as $arquivo) {
    $path = __DIR__ . '/' . $arquivo;
    if (file_exists($path)) {
        echo "<p class='ok'>✓ Arquivo '$arquivo' existe</p>";
    } else {
        echo "<p class='erro'>✗ Arquivo '$arquivo' NÃO encontrado</p>";
    }
}

echo "<hr>";
echo "<h2>Resumo</h2>";
echo "<p>Se todos os itens acima estão marcados com ✓, o sistema deve estar funcionando.</p>";
echo "<p>Se houver erros, corrija-os antes de usar a aplicação.</p>";
echo "<p><strong>Próximo passo:</strong> Acesse <a href='index.php'>index.php</a> para usar a aplicação.</p>";
