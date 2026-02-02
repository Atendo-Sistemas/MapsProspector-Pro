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
        $this->host = getenv('DB_HOST') ?: 'localhost';
        $this->dbname = getenv('DB_NAME') ?: 'maps';
        $this->username = getenv('DB_USER') ?: 'root';
        $this->password = (string) (getenv('DB_PASS') ?: '');
        if ($this->host === '' || $this->dbname === '') {
            throw new Exception('DB_HOST e DB_NAME devem estar definidos (variáveis de ambiente ou padrão).');
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
            error_log("Erro de conexão com banco de dados: " . $e->getMessage());
            $dbName = $this->dbname;
            if (strpos($e->getMessage(), "Unknown database") !== false) {
                throw new Exception("Banco de dados '$dbName' não encontrado. Crie o banco no phpMyAdmin e execute o script Database/maps_schema_full.sql.");
            }
            throw new Exception("Erro ao conectar com o banco de dados. Verifique se o MySQL está rodando, se o banco '$dbName' existe e se DB_HOST, DB_USER e DB_PASS no .env estão corretos.");
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
