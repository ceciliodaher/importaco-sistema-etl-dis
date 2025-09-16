<?php
/**
 * ================================================================================
 * BOOTSTRAP PARA TESTES - SUITE COMPLETA ETL DI's
 * Inicialização de ambiente de testes com database, cache e mocks
 * ================================================================================
 */

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('America/Sao_Paulo');

// Definir constantes de teste
define('TESTING_MODE', true);
define('TEST_ROOT', __DIR__);
define('APP_ROOT', dirname(__DIR__));

// Autoloader básico para classes de teste
spl_autoload_register(function ($class) {
    // Namespace mapping
    $namespaces = [
        'Tests\\' => TEST_ROOT . '/',
        'App\\' => APP_ROOT . '/',
    ];
    
    foreach ($namespaces as $namespace => $path) {
        if (strpos($class, $namespace) === 0) {
            $relativePath = substr($class, strlen($namespace));
            $file = $path . str_replace('\\', '/', $relativePath) . '.php';
            
            if (file_exists($file)) {
                require_once $file;
                return;
            }
        }
    }
});

// Carregar dependências do sistema
require_once APP_ROOT . '/api/common/response.php';
require_once APP_ROOT . '/api/common/cache.php';
require_once APP_ROOT . '/api/common/security.php';

/**
 * Classe base para configuração de testes
 */
class TestBootstrap
{
    private static $dbConnection = null;
    private static $testDatabase = 'importaco_etl_dis_test';
    
    /**
     * Inicializar ambiente de teste
     */
    public static function initialize(): void
    {
        self::setupTestDatabase();
        self::setupTestCache();
        self::setupTestFiles();
        self::loadTestHelpers();
    }
    
    /**
     * Configurar database de teste
     */
    private static function setupTestDatabase(): void
    {
        $host = $_ENV['DB_HOST'] ?? 'localhost:3307';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? 'ServBay.dev';
        
        try {
            // Conectar sem especificar database
            $pdo = new PDO("mysql:host={$host}", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
            ]);
            
            // Criar database de teste se não existir
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . self::$testDatabase . "`");
            $pdo->exec("USE `" . self::$testDatabase . "`");
            
            self::$dbConnection = $pdo;
            
            // Carregar schema de teste
            self::loadTestSchema();
            
            echo "✅ Database de teste configurado: " . self::$testDatabase . "\n";
            
        } catch (PDOException $e) {
            echo "❌ Erro ao configurar database de teste: " . $e->getMessage() . "\n";
            exit(1);
        }
    }
    
    /**
     * Carregar schema de teste
     */
    private static function loadTestSchema(): void
    {
        $schemaFile = APP_ROOT . '/../core/database/schema.sql';
        
        if (!file_exists($schemaFile)) {
            // Schema simplificado para testes
            $testSchema = self::getTestSchema();
            self::$dbConnection->exec($testSchema);
            echo "✅ Schema de teste simplificado carregado\n";
            return;
        }
        
        $schema = file_get_contents($schemaFile);
        
        // Executar cada statement separadamente
        $statements = array_filter(
            array_map('trim', explode(';', $schema)),
            function($stmt) { return !empty($stmt); }
        );
        
        foreach ($statements as $statement) {
            try {
                self::$dbConnection->exec($statement);
            } catch (PDOException $e) {
                // Ignorar erros de "table already exists"
                if (strpos($e->getMessage(), 'already exists') === false) {
                    echo "⚠️  Warning ao carregar schema: " . $e->getMessage() . "\n";
                }
            }
        }
        
        echo "✅ Schema principal carregado para testes\n";
    }
    
    /**
     * Schema básico para testes
     */
    private static function getTestSchema(): string
    {
        return "
            CREATE TABLE IF NOT EXISTS declaracoes_importacao (
                id INT AUTO_INCREMENT PRIMARY KEY,
                numero_di VARCHAR(50) UNIQUE NOT NULL,
                data_registro DATE NOT NULL,
                importador_nome VARCHAR(255) NOT NULL,
                importador_cnpj VARCHAR(20),
                valor_total_usd DECIMAL(15,2) DEFAULT 0,
                valor_total_brl DECIMAL(15,2) DEFAULT 0,
                status ENUM('processando', 'concluida', 'erro') DEFAULT 'processando',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_numero_di (numero_di),
                INDEX idx_data_registro (data_registro),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            
            CREATE TABLE IF NOT EXISTS adicoes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                di_id INT NOT NULL,
                numero_adicao INT NOT NULL,
                ncm VARCHAR(10),
                valor_usd DECIMAL(15,2) DEFAULT 0,
                valor_brl DECIMAL(15,2) DEFAULT 0,
                peso_kg DECIMAL(10,3) DEFAULT 0,
                quantidade DECIMAL(10,3) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (di_id) REFERENCES declaracoes_importacao(id) ON DELETE CASCADE,
                INDEX idx_di_id (di_id),
                INDEX idx_ncm (ncm)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            
            CREATE TABLE IF NOT EXISTS impostos_adicao (
                id INT AUTO_INCREMENT PRIMARY KEY,
                adicao_id INT NOT NULL,
                tipo_imposto ENUM('II', 'IPI', 'PIS', 'COFINS', 'ICMS') NOT NULL,
                aliquota DECIMAL(5,2) DEFAULT 0,
                valor_calculado DECIMAL(15,2) DEFAULT 0,
                base_calculo DECIMAL(15,2) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (adicao_id) REFERENCES adicoes(id) ON DELETE CASCADE,
                INDEX idx_adicao_id (adicao_id),
                INDEX idx_tipo_imposto (tipo_imposto)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            
            -- View simplificada para dashboard
            CREATE OR REPLACE VIEW v_dashboard_executivo AS
            SELECT 
                COUNT(DISTINCT di.id) as total_dis,
                COUNT(DISTINCT a.id) as total_adicoes,
                COALESCE(SUM(di.valor_total_usd), 0) as valor_total_usd,
                COALESCE(SUM(di.valor_total_brl), 0) as valor_total_brl,
                COALESCE(SUM(imp.valor_calculado), 0) as total_impostos,
                AVG(di.valor_total_usd) as ticket_medio_usd
            FROM declaracoes_importacao di
            LEFT JOIN adicoes a ON di.id = a.di_id
            LEFT JOIN impostos_adicao imp ON a.id = imp.adicao_id
            WHERE di.status = 'concluida';
        ";
    }
    
    /**
     * Configurar cache de teste
     */
    private static function setupTestCache(): void
    {
        // Usar cache array para testes (não persistente)
        if (class_exists('Cache')) {
            Cache::setDriver('array');
            echo "✅ Cache de teste configurado (array driver)\n";
        }
    }
    
    /**
     * Configurar arquivos de teste
     */
    private static function setupTestFiles(): void
    {
        $testDirs = [
            TEST_ROOT . '/fixtures',
            TEST_ROOT . '/reports',
            TEST_ROOT . '/reports/coverage',
            TEST_ROOT . '/temp',
            APP_ROOT . '/exports/test'
        ];
        
        foreach ($testDirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        echo "✅ Diretórios de teste criados\n";
    }
    
    /**
     * Carregar helpers de teste
     */
    private static function loadTestHelpers(): void
    {
        $helpers = [
            TEST_ROOT . '/helpers/TestHelper.php',
            TEST_ROOT . '/helpers/DatabaseHelper.php',
            TEST_ROOT . '/helpers/ApiHelper.php',
            TEST_ROOT . '/helpers/MockHelper.php'
        ];
        
        foreach ($helpers as $helper) {
            if (file_exists($helper)) {
                require_once $helper;
            }
        }
    }
    
    /**
     * Obter conexão de teste
     */
    public static function getDbConnection(): ?PDO
    {
        return self::$dbConnection;
    }
    
    /**
     * Limpar dados de teste
     */
    public static function cleanTestData(): void
    {
        if (self::$dbConnection) {
            $tables = ['impostos_adicao', 'adicoes', 'declaracoes_importacao'];
            
            self::$dbConnection->exec('SET FOREIGN_KEY_CHECKS = 0');
            foreach ($tables as $table) {
                self::$dbConnection->exec("TRUNCATE TABLE {$table}");
            }
            self::$dbConnection->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        
        // Limpar cache
        if (function_exists('apcu_clear_cache')) {
            apcu_clear_cache();
        }
        
        // Limpar arquivos temporários
        $tempDir = TEST_ROOT . '/temp';
        if (is_dir($tempDir)) {
            array_map('unlink', glob("{$tempDir}/*"));
        }
    }
    
    /**
     * Inserir dados de teste
     */
    public static function seedTestData(): void
    {
        if (!self::$dbConnection) {
            return;
        }
        
        // Dados básicos para testes
        $testData = [
            [
                'numero_di' => '24BR00000001234',
                'data_registro' => '2024-01-15',
                'importador_nome' => 'Equiplex Industrial Ltda',
                'importador_cnpj' => '12.345.678/0001-90',
                'valor_total_usd' => 50000.00,
                'valor_total_brl' => 250000.00,
                'status' => 'concluida'
            ],
            [
                'numero_di' => '24BR00000005678',
                'data_registro' => '2024-02-20',
                'importador_nome' => 'TechCorp Importadora S.A.',
                'importador_cnpj' => '98.765.432/0001-10',
                'valor_total_usd' => 75000.00,
                'valor_total_brl' => 375000.00,
                'status' => 'concluida'
            ]
        ];
        
        foreach ($testData as $di) {
            $stmt = self::$dbConnection->prepare("
                INSERT INTO declaracoes_importacao 
                (numero_di, data_registro, importador_nome, importador_cnpj, valor_total_usd, valor_total_brl, status)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $di['numero_di'], $di['data_registro'], $di['importador_nome'],
                $di['importador_cnpj'], $di['valor_total_usd'], $di['valor_total_brl'], $di['status']
            ]);
        }
        
        echo "✅ Dados de teste inseridos\n";
    }
}

// Inicializar ambiente de teste
TestBootstrap::initialize();

// Registrar função de limpeza ao final dos testes
register_shutdown_function(function() {
    if (getenv('KEEP_TEST_DATA') !== 'true') {
        TestBootstrap::cleanTestData();
    }
});

echo "🧪 Bootstrap de testes carregado com sucesso!\n";