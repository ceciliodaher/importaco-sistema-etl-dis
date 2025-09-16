<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - CONFIGURAÇÃO DO BANCO DE DADOS
 * Configuração para ServBay MySQL (localhost:3307)
 * Versão: 1.0.0
 * ================================================================================
 */

// Detectar ambiente automaticamente
$environment = $_ENV['ETL_ENVIRONMENT'] ?? 'development';

// Configurações por ambiente
$configs = [
    'development' => [
        'host' => 'localhost',
        'port' => 3307,
        'database' => 'importaco_etl_dis',
        'username' => 'root',
        'password' => 'ServBay.dev',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]
    ],
    'testing' => [
        'host' => 'localhost',
        'port' => 3307,
        'database' => 'importaco_etl_dis_test',
        'username' => 'root',
        'password' => 'ServBay.dev',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    ],
    'production' => [
        'host' => $_ENV['DB_HOST'] ?? 'localhost',
        'port' => $_ENV['DB_PORT'] ?? 3306,
        'database' => $_ENV['DB_DATABASE'] ?? 'importaco_etl_dis',
        'username' => $_ENV['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
        ]
    ]
];

// Configuração ativa
$config = $configs[$environment];

/**
 * Classe para conexão com banco de dados
 * Singleton pattern para garantir uma única conexão
 */
class Database 
{
    private static $instance = null;
    private $connection;
    private $config;

    private function __construct($config) 
    {
        $this->config = $config;
        $this->connect();
    }

    /**
     * Obter instância singleton
     */
    public static function getInstance($config = null) 
    {
        if (self::$instance === null) {
            if ($config === null) {
                throw new InvalidArgumentException('Configuração é obrigatória na primeira chamada');
            }
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Conectar ao banco de dados
     */
    private function connect() 
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );

            $this->connection = new PDO($dsn, 
                $this->config['username'], 
                $this->config['password'], 
                $this->config['options']
            );

            // Configurações específicas MySQL para performance
            $this->connection->exec("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            $this->connection->exec("SET SESSION time_zone = '+00:00'");
            
        } catch (PDOException $e) {
            throw new RuntimeException('Falha na conexão com banco de dados: ' . $e->getMessage());
        }
    }

    /**
     * Obter conexão PDO
     */
    public function getConnection() 
    {
        return $this->connection;
    }

    /**
     * Testar conexão
     */
    public function testConnection() 
    {
        try {
            $stmt = $this->connection->query('SELECT 1 as test');
            return $stmt->fetch()['test'] === 1;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obter informações do servidor
     */
    public function getServerInfo() 
    {
        return [
            'version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'connection_status' => $this->connection->getAttribute(PDO::ATTR_CONNECTION_STATUS),
            'server_info' => $this->connection->getAttribute(PDO::ATTR_SERVER_INFO)
        ];
    }

    /**
     * Executar transação de forma segura
     */
    public function transaction(callable $callback) 
    {
        $this->connection->beginTransaction();
        
        try {
            $result = $callback($this->connection);
            $this->connection->commit();
            return $result;
        } catch (Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Executar query com preparação automática
     */
    public function query($sql, $params = []) 
    {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            throw new RuntimeException("Erro na query: {$sql}. Erro: " . $e->getMessage());
        }
    }

    /**
     * Inserir registro e retornar ID
     */
    public function insert($sql, $params = []) 
    {
        $stmt = $this->query($sql, $params);
        return $this->connection->lastInsertId();
    }

    /**
     * Buscar um registro
     */
    public function fetchOne($sql, $params = []) 
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Buscar todos os registros
     */
    public function fetchAll($sql, $params = []) 
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Verificar se banco de dados existe e está configurado
     */
    public function isDatabaseReady() 
    {
        try {
            // Verificar se tabelas principais existem
            $tables = [
                'declaracoes_importacao',
                'adicoes', 
                'impostos_adicao',
                'despesas_extras',
                'moedas_referencia'
            ];

            foreach ($tables as $table) {
                $stmt = $this->connection->prepare("SHOW TABLES LIKE ?");
                $stmt->execute([$table]);
                if (!$stmt->fetch()) {
                    return false;
                }
            }

            // Verificar se funções existem
            $stmt = $this->connection->prepare("
                SELECT ROUTINE_NAME 
                FROM INFORMATION_SCHEMA.ROUTINES 
                WHERE ROUTINE_SCHEMA = ? AND ROUTINE_NAME = ?
            ");
            $stmt->execute([$this->config['database'], 'fn_validate_afrmm']);
            
            return $stmt->fetch() !== false;

        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obter estatísticas do banco
     */
    public function getStatistics() 
    {
        $stats = [];
        
        $tables = [
            'declaracoes_importacao' => 'DIs Processadas',
            'adicoes' => 'Adições',
            'impostos_adicao' => 'Impostos Calculados',
            'despesas_extras' => 'Despesas Extras',
            'ncm_referencia' => 'NCMs Catalogados',
            'moedas_referencia' => 'Moedas Configuradas'
        ];

        foreach ($tables as $table => $label) {
            try {
                $stmt = $this->connection->query("SELECT COUNT(*) as count FROM {$table}");
                $stats[$label] = $stmt->fetch()['count'];
            } catch (PDOException $e) {
                $stats[$label] = 'Erro';
            }
        }

        return $stats;
    }

    /**
     * Limpar cache de query
     */
    public function clearQueryCache() 
    {
        try {
            $this->connection->exec('RESET QUERY CACHE');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
}

/**
 * Função helper para obter conexão rapidamente
 */
function getDatabase() 
{
    global $config;
    return Database::getInstance($config);
}

/**
 * Função helper para executar SQL de setup
 */
function executeSqlFile($filename) 
{
    $db = getDatabase();
    
    if (!file_exists($filename)) {
        throw new InvalidArgumentException("Arquivo SQL não encontrado: {$filename}");
    }

    $sql = file_get_contents($filename);
    $statements = explode(';', $sql);

    $success = 0;
    $errors = [];

    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;

        try {
            $db->getConnection()->exec($statement);
            $success++;
        } catch (PDOException $e) {
            $errors[] = "Statement: {$statement}\nError: {$e->getMessage()}";
        }
    }

    return [
        'success' => $success,
        'errors' => $errors,
        'total' => count($statements)
    ];
}

// Disponibilizar configuração globalmente
$GLOBALS['db_config'] = $config;

/**
 * Verificar se ServBay está rodando (desenvolvimento)
 */
function checkServBay() 
{
    if ($GLOBALS['db_config']['port'] === 3307) {
        $connection = @fsockopen('localhost', 3307, $errno, $errstr, 1);
        if (!$connection) {
            throw new RuntimeException(
                'ServBay MySQL não está rodando na porta 3307. ' .
                'Verifique se o ServBay está iniciado.'
            );
        }
        fclose($connection);
    }
}

// Verificar ServBay se estiver em desenvolvimento
if ($environment === 'development') {
    checkServBay();
}