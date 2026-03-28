<?php
/**
 * Configuração de Conexão com Banco de Dados
 * MapsProspector Pro - XAMPP
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private $host;
    private $dbname;
    private $username;
    private $password;
    private $charset = 'utf8mb4';
    
    private function __construct() {
        // Tenta primeiro via getenv, depois via $_ENV (para hospedagens que bloqueiam getenv)
        $this->host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? 'localhost');
        $this->dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? 'maps');
        $this->username = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? 'root');
        $this->password = (string) (getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ''));
        
        // Debug: mostrar configurações (remover em produção)
        $debug = [
            'host' => $this->host,
            'dbname' => $this->dbname,
            'username' => $this->username,
            'password' => empty($this->password) ? '(vazio)' : '(definido)'
        ];
        
        if ($this->host === '' || $this->dbname === '') {
            throw new Exception('DB_HOST e DB_NAME devem estar definidos. Debug: ' . json_encode($debug));
        }
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            error_log("Erro de conexão: " . $errorMsg . " | Config: " . json_encode($debug));
            $dbName = $this->dbname;
            if (strpos($errorMsg, "Unknown database") !== false) {
                throw new Exception("Banco de dados '$dbName' não encontrado. Crie o banco no phpMyAdmin e execute o script Database/maps_schema_full.sql.");
            }
            if (strpos($errorMsg, "Access denied") !== false || strpos($errorMsg, "connect") !== false) {
                throw new Exception("Erro de conexão. Verifique as credenciais. Debug: " . json_encode($debug) . " | Erro: " . $errorMsg);
            }
            throw new Exception("Erro ao conectar com o banco de dados: " . $errorMsg);
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Previne clonagem
    private function __clone() {}
    
    // Previne unserialize
    public function __wakeup() {
        throw new Exception("Não é possível unserialize singleton");
    }
}
