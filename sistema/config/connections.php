<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - PERFIS DE CONEXÃO DO BANCO DE DADOS
 * Configuração de múltiplos ambientes com detecção automática
 * Versão: 1.0.0
 * ================================================================================
 */

return [
    
    /**
     * SERVBAY - Ambiente de desenvolvimento Mac (PADRÃO DO PROJETO)
     * Porta 3307 específica do ServBay
     */
    'servbay' => [
        'name' => 'ServBay MySQL (Mac Development)',
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
        ],
        'mysql_settings' => [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION time_zone = '+00:00'"
        ],
        'environment_type' => 'development',
        'description' => 'ServBay é o ambiente padrão para desenvolvimento Mac'
    ],

    /**
     * WAMP - Windows Apache MySQL PHP
     * Ambiente padrão Windows
     */
    'wamp' => [
        'name' => 'WAMP Server (Windows Development)',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'importaco_etl_dis',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ],
        'mysql_settings' => [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION time_zone = '+00:00'"
        ],
        'environment_type' => 'development',
        'description' => 'WAMP Server para desenvolvimento Windows'
    ],

    /**
     * XAMPP - Cross-platform (Windows/Mac/Linux)
     * Alternativa universal
     */
    'xampp' => [
        'name' => 'XAMPP Server (Cross-Platform)',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'importaco_etl_dis',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ],
        'mysql_settings' => [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION time_zone = '+00:00'"
        ],
        'environment_type' => 'development',
        'description' => 'XAMPP para desenvolvimento multiplataforma'
    ],

    /**
     * DOCKER - Containerized MySQL
     * Para ambientes Docker locais
     */
    'docker' => [
        'name' => 'Docker MySQL Container',
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'importaco_etl_dis',
        'username' => 'root',
        'password' => 'docker123',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => false, // Não usar conexões persistentes no Docker
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ],
        'mysql_settings' => [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION time_zone = '+00:00'"
        ],
        'environment_type' => 'development',
        'description' => 'MySQL rodando em container Docker'
    ],

    /**
     * PRODUCTION - Servidor Web de Produção
     * Configurado via variáveis de ambiente
     */
    'production' => [
        'name' => 'Production Web Server',
        'host' => $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['DB_PORT'] ?? $_SERVER['DB_PORT'] ?? 3306),
        'database' => $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'importaco_etl_dis',
        'username' => $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? 'root',
        'password' => $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true, // Conexões persistentes em produção
            PDO::ATTR_TIMEOUT => 30,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ],
        'mysql_settings' => [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION time_zone = '+00:00'",
            "SET SESSION wait_timeout = 28800",
            "SET SESSION interactive_timeout = 28800"
        ],
        'environment_type' => 'production',
        'description' => 'Servidor de produção configurado via variáveis de ambiente'
    ],

    /**
     * TESTING - Ambiente de Testes
     * Base de dados separada para testes
     */
    'testing' => [
        'name' => 'Testing Environment',
        'host' => $_ENV['TEST_DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['TEST_DB_PORT'] ?? 3307), // ServBay por padrão
        'database' => 'importaco_etl_dis_test',
        'username' => $_ENV['TEST_DB_USERNAME'] ?? 'root',
        'password' => $_ENV['TEST_DB_PASSWORD'] ?? 'ServBay.dev',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ],
        'mysql_settings' => [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION time_zone = '+00:00'"
        ],
        'environment_type' => 'testing',
        'description' => 'Ambiente isolado para execução de testes'
    ],

    /**
     * CUSTOM_LOCAL - Perfil customizável
     * Para configurações específicas locais
     */
    'custom_local' => [
        'name' => 'Custom Local Configuration',
        'host' => $_ENV['CUSTOM_DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['CUSTOM_DB_PORT'] ?? 3306),
        'database' => $_ENV['CUSTOM_DB_DATABASE'] ?? 'importaco_etl_dis',
        'username' => $_ENV['CUSTOM_DB_USERNAME'] ?? 'root',
        'password' => $_ENV['CUSTOM_DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ],
        'mysql_settings' => [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION time_zone = '+00:00'"
        ],
        'environment_type' => 'development',
        'description' => 'Configuração customizável via variáveis de ambiente CUSTOM_*'
    ],

    /**
     * CLOUD - Configuração para provedores cloud
     * AWS RDS, Google Cloud SQL, etc.
     */
    'cloud' => [
        'name' => 'Cloud Database (RDS/Cloud SQL)',
        'host' => $_ENV['CLOUD_DB_HOST'] ?? 'localhost',
        'port' => (int)($_ENV['CLOUD_DB_PORT'] ?? 3306),
        'database' => $_ENV['CLOUD_DB_DATABASE'] ?? 'importaco_etl_dis',
        'username' => $_ENV['CLOUD_DB_USERNAME'] ?? 'root',
        'password' => $_ENV['CLOUD_DB_PASSWORD'] ?? '',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false, // Para SSL em cloud
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ],
        'mysql_settings' => [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION time_zone = '+00:00'",
            "SET SESSION wait_timeout = 28800",
            "SET SESSION interactive_timeout = 28800"
        ],
        'environment_type' => 'production',
        'description' => 'Configuração para bancos de dados em nuvem (AWS RDS, Google Cloud SQL, etc.)'
    ]
];

/*
================================================================================
VARIÁVEIS DE AMBIENTE SUPORTADAS
================================================================================

GERAIS:
- ETL_ENVIRONMENT: development|testing|production
- ETL_DB_PROFILE: servbay|wamp|xampp|docker|production|testing|custom_local|cloud

PRODUÇÃO:
- DB_HOST: Host do banco de dados
- DB_PORT: Porta do banco de dados  
- DB_DATABASE: Nome do banco de dados
- DB_USERNAME: Usuário do banco de dados
- DB_PASSWORD: Senha do banco de dados

TESTING:
- TEST_DB_HOST: Host do banco de testes
- TEST_DB_PORT: Porta do banco de testes
- TEST_DB_USERNAME: Usuário do banco de testes
- TEST_DB_PASSWORD: Senha do banco de testes

CUSTOM LOCAL:
- CUSTOM_DB_HOST: Host customizado
- CUSTOM_DB_PORT: Porta customizada
- CUSTOM_DB_DATABASE: Database customizado
- CUSTOM_DB_USERNAME: Usuário customizado
- CUSTOM_DB_PASSWORD: Senha customizada

CLOUD:
- CLOUD_DB_HOST: Host do banco em nuvem
- CLOUD_DB_PORT: Porta do banco em nuvem
- CLOUD_DB_DATABASE: Database em nuvem
- CLOUD_DB_USERNAME: Usuário do banco em nuvem
- CLOUD_DB_PASSWORD: Senha do banco em nuvem

================================================================================
EXEMPLOS DE USO
================================================================================

1. Forçar perfil específico:
   export ETL_DB_PROFILE=servbay

2. Ambiente de produção:
   export ETL_ENVIRONMENT=production
   export DB_HOST=mysql.exemplo.com
   export DB_USERNAME=user_prod
   export DB_PASSWORD=senha_segura

3. Testes locais:
   export ETL_ENVIRONMENT=testing

4. Configuração customizada:
   export ETL_DB_PROFILE=custom_local
   export CUSTOM_DB_HOST=192.168.1.100
   export CUSTOM_DB_PORT=3307

================================================================================
*/