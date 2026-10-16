<?php
/**
 * Configuração de Conexão com Banco de Dados
 * MapsProspector Pro — compatível com XAMPP (local) e cPanel (produção).
 *
 * Ordem de resolução das credenciais:
 * 1) Variáveis de ambiente (DB_HOST etc.) — útil para testes e .env
 * 2) Arquivo privado fora do webroot (fallback de produção)
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
        // Credenciais de produção ficam fora do webroot, em arquivo privado 0600.
        $privateConfig = [];
        $privatePath = '/home/naciona4/atendo-secrets/database.php';
        if (is_readable($privatePath)) {
            $loaded = require $privatePath;
            if (is_array($loaded)) {
                $privateConfig = $loaded;
            }
        }
        // Variáveis de ambiente têm prioridade; o arquivo privado é fallback server-side.
        $this->host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? ($privateConfig['host'] ?? ''));
        $this->dbname = getenv('DB_NAME') ?: ($_ENV['DB_NAME'] ?? ($privateConfig['dbname'] ?? ''));
        $this->username = getenv('DB_USER') ?: ($_ENV['DB_USER'] ?? ($privateConfig['username'] ?? ''));
        $this->password = (string) (getenv('DB_PASS') ?: ($_ENV['DB_PASS'] ?? ($privateConfig['password'] ?? '')));

        if ($this->host === '' || $this->dbname === '') {
            throw new RuntimeException('As configurações de conexão com o banco de dados estão incompletas.');
        }
        if ($this->username === 'root' || $this->password === '') {
            // Protege contra as credenciais XAMPP (root/vazio) vazarem para produção.
            throw new RuntimeException('As credenciais de conexão com o banco de dados não são válidas.');
        }

        try {
            $dsn = "mysql:host={$this->host};dbname={$this->dbname};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            $this->connection->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
        } catch (PDOException $e) {
            $errorMsg = $e->getMessage();
            error_log('Database connection failed: ' . $errorMsg);
            throw new RuntimeException('Não foi possível conectar ao banco de dados. Verifique as configurações e os logs.');
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
