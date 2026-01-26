<?php
/**
 * Configuração de Conexão com Banco de Dados
 * MapsProspector Pro - XAMPP
 */

class Database {
    private static $instance = null;
    private $connection;
    
    private $host = 'localhost';
    private $dbname = 'maps';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    
    private function __construct() {
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
            // Verifica se o erro é porque o banco não existe
            if (strpos($e->getMessage(), "Unknown database") !== false) {
                throw new Exception("Banco de dados 'maps' não encontrado. Execute o script database.sql no phpMyAdmin.");
            }
            throw new Exception("Erro ao conectar com o banco de dados. Verifique se o MySQL está rodando e se o banco 'maps' existe.");
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
