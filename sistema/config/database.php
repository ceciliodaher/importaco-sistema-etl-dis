<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - CONFIGURAÇÃO DO BANCO DE DADOS
 * Sistema flexível de conexões com detecção automática de ambientes
 * Versão: 2.0.0 - Gerenciador de Conexões Inteligente
 * ================================================================================
 */

// Carregar gerenciador de conexões
require_once __DIR__ . '/../core/DatabaseConnectionManager.php';

// Obter instância do gerenciador
$connectionManager = DatabaseConnectionManager::getInstance();

// Auto-selecionar melhor perfil baseado no ambiente
$selectedProfile = $connectionManager->autoSelectProfile();

// Obter configuração do perfil selecionado
$config = $connectionManager->getCurrentConfig();

// BACKWARD COMPATIBILITY: Manter variáveis antigas para compatibilidade
$environment = $_ENV['ETL_ENVIRONMENT'] ?? 'development';
$configs = [
    'development' => $config,
    'testing' => $connectionManager->getProfile('testing'),
    'production' => $connectionManager->getProfile('production')
];

/**
 * Classe para conexão com banco de dados
 * Singleton pattern integrado com DatabaseConnectionManager
 */
class Database 
{
    private static $instance = null;
    private $connection;
    private $config;
    private $connectionManager;

    private function __construct($config = null) 
    {
        $this->connectionManager = DatabaseConnectionManager::getInstance();
        
        // Se config foi passado, usar ele (backward compatibility)
        // Senão, usar o gerenciador de conexões
        if ($config !== null) {
            $this->config = $config;
            $this->connect();
        } else {
            $this->connectViaManager();
        }
    }

    /**
     * Obter instância singleton
     */
    public static function getInstance($config = null) 
    {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Conectar via DatabaseConnectionManager (novo método)
     */
    private function connectViaManager() 
    {
        try {
            $this->connection = $this->connectionManager->getConnection();
            $this->config = $this->connectionManager->getCurrentConfig();
            
        } catch (Exception $e) {
            throw new RuntimeException('Falha na conexão via gerenciador: ' . $e->getMessage());
        }
    }

    /**
     * Conectar ao banco de dados (método legacy)
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
        $info = [
            'version' => $this->connection->getAttribute(PDO::ATTR_SERVER_VERSION),
            'connection_status' => $this->connection->getAttribute(PDO::ATTR_CONNECTION_STATUS),
            'server_info' => $this->connection->getAttribute(PDO::ATTR_SERVER_INFO)
        ];
        
        // Adicionar informações do gerenciador de conexões
        if ($this->connectionManager) {
            $info['connection_manager'] = [
                'current_profile' => $this->connectionManager->getCurrentProfileName(),
                'detected_environments' => count($this->connectionManager->getDetectedEnvironments()),
                'available_profiles' => count($this->connectionManager->getAvailableProfiles())
            ];
        }
        
        return $info;
    }
    
    /**
     * Obter gerenciador de conexões
     */
    public function getConnectionManager() 
    {
        return $this->connectionManager;
    }
    
    /**
     * Trocar perfil de conexão (reconectar)
     */
    public function switchProfile($profileName) 
    {
        if (!$this->connectionManager) {
            throw new RuntimeException('Gerenciador de conexões não disponível');
        }
        
        $this->connectionManager->setProfile($profileName);
        $this->connectViaManager();
        
        return $this;
    }
    
    /**
     * Listar perfis disponíveis
     */
    public function getAvailableProfiles() 
    {
        if (!$this->connectionManager) {
            return [];
        }
        
        return $this->connectionManager->getAvailableProfiles();
    }
    
    /**
     * Obter status do perfil atual
     */
    public function getConnectionStatus() 
    {
        if (!$this->connectionManager) {
            return ['legacy_mode' => true];
        }
        
        return $this->connectionManager->getStatus();
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
 * Agora usa o sistema inteligente de detecção
 */
function getDatabase() 
{
    // Usar o novo sistema sem configuração manual
    return Database::getInstance();
}

/**
 * Função helper para obter gerenciador de conexões
 */
function getConnectionManager() 
{
    return DatabaseConnectionManager::getInstance();
}

/**
 * Função helper para testar todas as conexões
 */
function testAllConnections() 
{
    $manager = getConnectionManager();
    return $manager->testAllConnections();
}

/**
 * Função helper para listar ambientes detectados
 */
function getDetectedEnvironments() 
{
    $manager = getConnectionManager();
    return $manager->getDetectedEnvironments();
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
 * Verificar se ServBay está rodando (desenvolvimento) - LEGACY
 * Função mantida para compatibilidade, mas o gerenciador faz isso automaticamente
 */
function checkServBay() 
{
    $connection = @fsockopen('localhost', 3307, $errno, $errstr, 1);
    if (!$connection) {
        throw new RuntimeException(
            'ServBay MySQL não está rodando na porta 3307. ' .
            'Verifique se o ServBay está iniciado.'
        );
    }
    fclose($connection);
}

// DISPONIBILIZAR INFORMAÇÕES GLOBALMENTE
$GLOBALS['db_config'] = $config;
$GLOBALS['connection_manager'] = $connectionManager;
$GLOBALS['selected_profile'] = $selectedProfile;

/*
================================================================================
SISTEMA DE CONEXÕES INTELIGENTE - GUIA DE USO
================================================================================

O sistema agora detecta automaticamente o ambiente e seleciona a melhor configuração:

1. AUTO-DETECÇÃO:
   - ServBay (Mac): porta 3307 detectada
   - WAMP (Windows): porta 3306 + Windows  
   - XAMPP (Cross-platform): porta 3306 + não-Windows
   - Docker: porta 3306 + ambiente Docker
   - Produção: variáveis DB_* detectadas

2. USO BÁSICO (sem mudanças no código existente):
   $db = getDatabase(); // Detecta automaticamente

3. USO AVANÇADO:
   // Listar ambientes detectados
   $environments = getDetectedEnvironments();
   
   // Testar todas as conexões
   $tests = testAllConnections();
   
   // Trocar perfil manualmente
   $db = getDatabase();
   $db->switchProfile('wamp');
   
   // Obter status atual
   $status = $db->getConnectionStatus();

4. VARIÁVEIS DE AMBIENTE SUPORTADAS:
   - ETL_DB_PROFILE: Forçar perfil específico
   - ETL_ENVIRONMENT: development|testing|production
   - DB_*: Configurações de produção
   - CUSTOM_DB_*: Configurações customizadas

5. PERFIS DISPONÍVEIS:
   - servbay: ServBay MySQL (Mac) - PADRÃO
   - wamp: WAMP Server (Windows)
   - xampp: XAMPP (Cross-platform)
   - docker: Docker MySQL
   - production: Servidor produção
   - testing: Ambiente de testes
   - custom_local: Configuração personalizada
   - cloud: Bancos em nuvem (RDS, Cloud SQL)

================================================================================
BACKWARD COMPATIBILITY
================================================================================

Todo código existente continua funcionando:
- getDatabase() funciona igual
- Database::getInstance() funciona igual
- Todas as funções da classe Database mantidas
- Variáveis $config e $environment ainda disponíveis

NOVO: Funções adicionais disponíveis:
- getConnectionManager()
- testAllConnections()
- getDetectedEnvironments()
- $db->switchProfile()
- $db->getAvailableProfiles()

================================================================================
*/