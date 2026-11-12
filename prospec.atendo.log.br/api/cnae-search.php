<?php
header('Content-Type: application/json; charset=utf-8');

$termo = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($termo) < 2) {
    echo json_encode(['resultados' => []]);
    exit;
}

// Caminho para a base do CNAE baixada do GitHub
$cnaeFile = __DIR__ . '/data/cnae-dict/cnae.json';

if (!file_exists($cnaeFile)) {
    // Fallback básico caso o arquivo ainda não esteja no local correto
    echo json_encode(['resultados' => []]);
    exit;
}

$cnaeList = json_decode(file_get_contents($cnaeFile), true);
$resultados = [];
$termoLower = mb_strtolower($termo, 'UTF-8');
$termoNumerico = preg_replace('/[^0-9]/', '', $termo);

foreach ($cnaeList as $item) {
    $codigo = isset($item['codigo']) ? $item['codigo'] : (isset($item['id']) ? $item['id'] : '');
    $descricao = isset($item['descricao']) ? $item['descricao'] : (isset($item['text']) ? $item['text'] : '');

    $codigoLimpo = preg_replace('/[^0-9]/', '', $codigo);
    $descLower = mb_strtolower($descricao, 'UTF-8');

    // Busca por código numérico ou por texto na descrição
    if (($termoNumerico !== '' && strpos($codigoLimpo, $termoNumerico) !== false) || 
        strpos($descLower, $termoLower) !== false) {
        
        $resultados[] = [
            'codigo_cnae' => $codigo,
            'descricao_cnae' => $descricao
        ];

        if (count($resultados) >= 20) break; // Limita a 20 sugestões
    }
}

echo json_encode(['resultados' => $resultados], JSON_UNESCAPED_UNICODE);