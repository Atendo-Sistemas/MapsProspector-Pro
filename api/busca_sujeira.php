<?php
// busca_sujeira.php
header('Content-Type: text/html; charset=utf-8');

$diretorio = __DIR__;
$termo_busca = 'stilos de formatação';

echo "<h3>Buscando o texto perdido...</h3>";

function buscarTexto($pasta, $termo) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($pasta));
    $encontrou = false;
    
    foreach ($iterator as $arquivo) {
        if ($fileException = $arquivo->isFile() && $arquivo->getExtension() === 'php') {
            $caminho = $arquivo->getPathname();
            $conteudo = file_get_contents($caminho);
            
            if (strpos($conteudo, $termo) !== false) {
                $encontrou = true;
                echo "❌ <strong>Encontrado no arquivo:</strong> <span style='color: red;'>$caminho</span><br>";
                
                // Mostra a linha exata
                $linhas = file($caminho);
                foreach ($linhas as $numero => $linha) {
                    if (strpos($linha, $termo) !== false) {
                        echo "Linha " . ($numero + 1) . ": <code>" . htmlspecialchars(trim($linha)) . "</code><br><br>";
                    }
                }
            }
        }
    }
    
    if (!$encontrou) {
        echo "✅ Nenhuma sujeira encontrada nesta pasta.";
    }
}

buscarTexto($diretorio, $termo_busca);