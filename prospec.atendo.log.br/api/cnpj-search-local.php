<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Método não permitido.', 405);
}

$appDb = Database::getInstance()->getConnection();
requireSearchPermission($appDb);
$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['params'])) {
    jsonError('Requisição inválida.', 400);
}

$params = $input['params'];
$uf = isset($params['uf']) ? trim(strtoupper($params['uf'])) : '';
$citySiafi = isset($params['city']) ? trim($params['city']) : ''; // Código SIAFI ex: '6597'
$cnae = isset($params['cnae']) ? trim($params['cnae']) : '';
$q = isset($params['q']) ? trim($params['q']) : '';

if (empty($uf) || empty($citySiafi)) {
    echo json_encode(['success' => false, 'error' => 'Estado (UF) e Cidade são obrigatórios para a busca avançada.']);
    exit;
}

try {
    $host = getenv('CNPJ_LOCAL_DB_HOST');
    $dbname = getenv('CNPJ_LOCAL_DB_NAME');
    $username = getenv('CNPJ_LOCAL_DB_USER');
    $password = getenv('CNPJ_LOCAL_DB_PASSWORD');
    if (!$host || !$dbname || !$username || !$password) {
        jsonError('A pesquisa local de CNPJ não está configurada.', 503);
    }
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query otimizada com sintaxe nativa do MySQL (CONCAT)
    $sql = "SELECT 
                CONCAT(est.cnpj_basico, est.cnpj_ordem, est.cnpj_dv) AS cnpj,
                emp.razao_social,
                est.nome_fantasia,
                est.ddd1,
                est.telefone1,
                est.correio_eletronico,
                est.logradouro,
                est.numero,
                est.bairro,
                est.uf
            FROM estabelecimento est
            INNER JOIN empresa emp ON est.cnpj_basico = emp.cnpj_basico
            WHERE est.situacao_cadastral = '02'
              AND est.uf = :uf
              AND est.municipio = :municipio";

    // Filtros de busca dinâmica por termo ou código de atividade
    if (!empty($cnae)) {
        $sql .= " AND (est.atividade_principal = :cnae OR emp.razao_social LIKE :q OR est.nome_fantasia LIKE :q)";
    } else {
        $sql .= " AND (emp.razao_social LIKE :q OR est.nome_fantasia LIKE :q)";
    }

    $sql .= " LIMIT 50";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':uf', $uf);
    $stmt->bindValue(':municipio', $citySiafi);
    
    if (!empty($cnae)) {
        $stmt->bindValue(':cnae', $cnae);
    }
    
    $stmt->bindValue(':q', '%' . $q . '%');
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formata o retorno para a renderização de cards da interface
    $formattedResults = [];
    foreach ($results as $row) {
        $formattedResults[] = [
            'cnpj' => $row['cnpj'],
            'razao_social' => $row['razao_social'] ? trim($row['razao_social']) : ($row['nome_fantasia'] ? trim($row['nome_fantasia']) : 'Empresa sem Razão'),
            'nome_fantasia' => trim($row['nome_fantasia'] ?? ''),
            'telefone_1' => trim($row['telefone1'] ?? ''),
            'ddd1' => trim($row['ddd1'] ?? ''),
            'correio_eletronico' => trim($row['correio_eletronico'] ?? ''),
            'logradouro' => trim($row['logradouro'] ?? ''),
            'numero' => trim($row['numero'] ?? ''),
            'bairro' => trim($row['bairro'] ?? ''),
            'uf' => trim($row['uf'] ?? ''),
            'municipio' => $citySiafi
        ];
    }

    echo json_encode([
        'success' => true,
        'status' => 'SUCCEEDED',
        'results' => $formattedResults
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Erro de conexão: ' . $e->getMessage()]);
}