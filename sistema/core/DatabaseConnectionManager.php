<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - GERENCIADOR DE CONEXÕES DO BANCO DE DADOS
 * Detecção automática e configuração flexível de ambientes
 * Versão: 1.0.0
 * ================================================================================
 */

class DatabaseConnectionManager 
{
    private static $instance = null;
    private $connectionProfiles = [];
    private $currentProfile = null;
    private $detectedEnvironments = [];

    private function __construct() 
    {
        $this->loadConnectionProfiles();
        $this->detectEnvironments();
    }

    /**
     * Obter instância singleton
     */
    public static function getInstance() 
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Carregar perfis de conexão do arquivo de configuração
     */
    private function loadConnectionProfiles() 
    {
        $configFile = __DIR__ . '/../config/connections.php';
        
        if (!file_exists($configFile)) {
            throw new RuntimeException('Arquivo de configuração de conexões não encontrado: ' . $configFile);
        }
        
        $this->connectionProfiles = require $configFile;
        
        if (!is_array($this->connectionProfiles)) {
            throw new RuntimeException('Arquivo de configuração de conexões deve retornar um array');
        }
        
        // Carregar perfis customizados se existirem
        $this->loadCustomProfiles();
    }
    
    /**
     * Carregar perfis customizados
     */
    private function loadCustomProfiles() 
    {
        $customProfilesFile = __DIR__ . '/../config/custom-profiles.php';
        
        if (file_exists($customProfilesFile)) {
            $customProfiles = require $customProfilesFile;
            
            if (is_array($customProfiles)) {
                $this->connectionProfiles = array_merge($this->connectionProfiles, $customProfiles);
            }
        }
        
        // Carregar preferências do usuário para perfil ativo
        $this->loadUserPreferences();
    }
    
    /**
     * Carregar preferências do usuário
     */
    private function loadUserPreferences() 
    {
        $preferencesFile = __DIR__ . '/../config/user-preferences.php';
        
        if (file_exists($preferencesFile)) {
            $preferences = require $preferencesFile;
            
            if (is_array($preferences) && isset($preferences['active_profile'])) {
                $activeProfile = $preferences['active_profile'];
                
                // Verificar se o perfil ainda existe
                if (isset($this->connectionProfiles[$activeProfile])) {
                    $this->currentProfile = $activeProfile;
                }
            }
        }
    }

    /**
     * Detectar ambientes disponíveis automaticamente
     */
    private function detectEnvironments() 
    {
        $this->detectedEnvironments = [];

        // Detectar ServBay (Mac)
        if ($this->isPortOpen('localhost', 3307)) {
            $this->detectedEnvironments['servbay'] = [
                'name' => 'ServBay MySQL',
                'type' => 'development',
                'detected' => true,
                'profile' => 'servbay'
            ];
        }

        // Detectar WAMP (Windows)
        if (PHP_OS_FAMILY === 'Windows' && $this->isPortOpen('localhost', 3306)) {
            $this->detectedEnvironments['wamp'] = [
                'name' => 'WAMP Server',
                'type' => 'development', 
                'detected' => true,
                'profile' => 'wamp'
            ];
        }

        // Detectar XAMPP (Cross-platform)
        if ($this->isPortOpen('localhost', 3306) && !isset($this->detectedEnvironments['wamp'])) {
            $this->detectedEnvironments['xampp'] = [
                'name' => 'XAMPP Server',
                'type' => 'development',
                'detected' => true,
                'profile' => 'xampp'
            ];
        }

        // Detectar Docker
        if ($this->isPortOpen('localhost', 3306) && $this->isDockerEnvironment()) {
            $this->detectedEnvironments['docker'] = [
                'name' => 'Docker MySQL',
                'type' => 'development',
                'detected' => true,
                'profile' => 'docker'
            ];
        }

        // Detectar ambiente de produção via variáveis
        if (isset($_ENV['DB_HOST']) || isset($_SERVER['DB_HOST'])) {
            $this->detectedEnvironments['production'] = [
                'name' => 'Servidor Produção',
                'type' => 'production',
                'detected' => true,
                'profile' => 'production'
            ];
        }
    }

    /**
     * Verificar se uma porta está aberta
     */
    private function isPortOpen($host, $port, $timeout = 1) 
    {
        $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }

    /**
     * Detectar se está em ambiente Docker
     */
    private function isDockerEnvironment() 
    {
        return file_exists('/.dockerenv') || 
               file_exists('/proc/1/cgroup') && 
               strpos(file_get_contents('/proc/1/cgroup'), 'docker') !== false;
    }

    /**
     * Obter ambientes detectados
     */
    public function getDetectedEnvironments() 
    {
        return $this->detectedEnvironments;
    }

    /**
     * Obter perfis de conexão disponíveis
     */
    public function getAvailableProfiles() 
    {
        return array_keys($this->connectionProfiles);
    }

    /**
     * Obter configuração de um perfil específico
     */
    public function getProfile($profileName) 
    {
        if (!isset($this->connectionProfiles[$profileName])) {
            throw new InvalidArgumentException("Perfil de conexão '{$profileName}' não encontrado");
        }
        
        return $this->connectionProfiles[$profileName];
    }

    /**
     * Selecionar perfil de conexão automaticamente
     */
    public function autoSelectProfile() 
    {
        // Prioridade: Variável de ambiente > Detecção automática > ServBay padrão
        
        // 1. Verificar variável de ambiente
        $envProfile = $_ENV['ETL_DB_PROFILE'] ?? $_SERVER['ETL_DB_PROFILE'] ?? null;
        if ($envProfile && isset($this->connectionProfiles[$envProfile])) {
            $this->currentProfile = $envProfile;
            return $this->currentProfile;
        }

        // 2. Verificar ambiente específico via ETL_ENVIRONMENT
        $environment = $_ENV['ETL_ENVIRONMENT'] ?? $_SERVER['ETL_ENVIRONMENT'] ?? 'development';
        if ($environment === 'production' && isset($this->detectedEnvironments['production'])) {
            $this->currentProfile = 'production';
            return $this->currentProfile;
        }

        // 3. Auto-detecção por prioridade (ServBay > WAMP > XAMPP > Docker)
        $priorityOrder = ['servbay', 'wamp', 'xampp', 'docker'];
        
        foreach ($priorityOrder as $env) {
            if (isset($this->detectedEnvironments[$env])) {
                $this->currentProfile = $this->detectedEnvironments[$env]['profile'];
                return $this->currentProfile;
            }
        }

        // 4. Fallback para ServBay (padrão do projeto)
        $this->currentProfile = 'servbay';
        return $this->currentProfile;
    }

    /**
     * Definir perfil manualmente
     */
    public function setProfile($profileName) 
    {
        if (!isset($this->connectionProfiles[$profileName])) {
            throw new InvalidArgumentException("Perfil '{$profileName}' não existe");
        }
        
        $this->currentProfile = $profileName;
        return $this;
    }

    /**
     * Obter configuração do perfil atual
     */
    public function getCurrentConfig() 
    {
        if ($this->currentProfile === null) {
            $this->autoSelectProfile();
        }
        
        return $this->getProfile($this->currentProfile);
    }

    /**
     * Obter nome do perfil atual
     */
    public function getCurrentProfileName() 
    {
        return $this->currentProfile;
    }

    /**
     * Testar conexão com um perfil específico
     */
    public function testConnection($profileName = null) 
    {
        $profile = $profileName ? $this->getProfile($profileName) : $this->getCurrentConfig();
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                $profile['host'],
                $profile['port'],
                $profile['charset'] ?? 'utf8mb4'
            );

            $pdo = new PDO($dsn, $profile['username'], $profile['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3
            ]);

            // Testar query simples
            $stmt = $pdo->query('SELECT 1 as test');
            $result = $stmt->fetch();
            
            $pdo = null; // Fechar conexão
            
            return [
                'success' => true,
                'profile' => $profileName ?? $this->currentProfile,
                'message' => 'Conexão estabelecida com sucesso',
                'server_version' => $pdo ? $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) : 'N/A'
            ];

        } catch (PDOException $e) {
            return [
                'success' => false,
                'profile' => $profileName ?? $this->currentProfile,
                'message' => 'Falha na conexão: ' . $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }

    /**
     * Testar todos os perfis disponíveis
     */
    public function testAllConnections() 
    {
        $results = [];
        
        foreach ($this->getAvailableProfiles() as $profile) {
            $results[$profile] = $this->testConnection($profile);
        }
        
        return $results;
    }

    /**
     * Obter conexão PDO com o perfil atual
     */
    public function getConnection() 
    {
        $config = $this->getCurrentConfig();
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'],
                $config['database'],
                $config['charset'] ?? 'utf8mb4'
            );

            $pdo = new PDO($dsn, $config['username'], $config['password'], $config['options'] ?? []);
            
            // Configurações específicas do MySQL
            if (isset($config['mysql_settings'])) {
                foreach ($config['mysql_settings'] as $setting) {
                    $pdo->exec($setting);
                }
            }
            
            return $pdo;

        } catch (PDOException $e) {
            throw new RuntimeException(
                "Falha na conexão com perfil '{$this->currentProfile}': " . $e->getMessage()
            );
        }
    }

    /**
     * Obter informações de status do gerenciador
     */
    public function getStatus() 
    {
        return [
            'current_profile' => $this->currentProfile,
            'detected_environments' => $this->detectedEnvironments,
            'available_profiles' => $this->getAvailableProfiles(),
            'total_profiles' => count($this->connectionProfiles),
            'auto_detection_enabled' => true
        ];
    }

    /**
     * Criar nova conexão com configuração customizada
     */
    public function createCustomConnection($config) 
    {
        $requiredKeys = ['host', 'port', 'database', 'username', 'password'];
        
        foreach ($requiredKeys as $key) {
            if (!isset($config[$key])) {
                throw new InvalidArgumentException("Configuração customizada deve conter: " . implode(', ', $requiredKeys));
            }
        }
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $config['host'],
                $config['port'], 
                $config['database'],
                $config['charset'] ?? 'utf8mb4'
            );

            return new PDO($dsn, $config['username'], $config['password'], $config['options'] ?? []);

        } catch (PDOException $e) {
            throw new RuntimeException('Falha na conexão customizada: ' . $e->getMessage());
        }
    }

    /**
     * Registrar novo perfil dinamicamente
     */
    public function addProfile($name, $config) 
    {
        $this->connectionProfiles[$name] = $config;
        return $this;
    }

    /**
     * Reset da detecção de ambientes
     */
    public function refreshEnvironmentDetection() 
    {
        $this->detectEnvironments();
        return $this;
    }
}