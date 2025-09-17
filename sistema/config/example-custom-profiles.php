<?php
/**
 * ================================================================================
 * EXEMPLO DE PERFIS CUSTOMIZADOS
 * Este arquivo mostra como os perfis customizados são estruturados
 * ================================================================================
 */

return [
    'custom_meu_servidor_local' => [
        'name' => 'Meu Servidor Local',
        'host' => '192.168.1.100',
        'port' => 3306,
        'database' => 'importaco_etl_dis',
        'username' => 'dev_user',
        'password' => 'dev_password',
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
        'environment_type' => 'custom',
        'description' => 'Servidor MySQL local personalizado configurado pelo usuário',
        'created_at' => '2025-09-17 10:30:00',
        'is_custom' => true
    ],
    
    'custom_servidor_remoto' => [
        'name' => 'Servidor Remoto Empresa',
        'host' => 'db.empresa.com',
        'port' => 3306,
        'database' => 'importaco_etl_dis_prod',
        'username' => 'prod_user',
        'password' => 'prod_secure_password',
        'charset' => 'utf8mb4',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true,
            PDO::ATTR_TIMEOUT => 10,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ],
        'mysql_settings' => [
            "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'",
            "SET SESSION time_zone = '+00:00'",
            "SET SESSION wait_timeout = 28800",
            "SET SESSION interactive_timeout = 28800"
        ],
        'environment_type' => 'custom',
        'description' => 'Servidor MySQL remoto da empresa com SSL e configurações otimizadas',
        'created_at' => '2025-09-17 14:15:00',
        'is_custom' => true
    ]
];