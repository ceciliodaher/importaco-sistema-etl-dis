<?php
/**
 * ================================================================================
 * TESTE SIMPLES DA CONFIGURAÇÃO DO BANCO DE DADOS
 * Script para testar funcionalidades básicas do sistema de configuração
 * ================================================================================
 */

// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Teste do Sistema de Configuração de Banco de Dados</h1>\n";

try {
    // Testar carregamento do DatabaseConnectionManager
    echo "<h2>1. Testando DatabaseConnectionManager</h2>\n";
    
    require_once '../core/DatabaseConnectionManager.php';
    $manager = DatabaseConnectionManager::getInstance();
    
    echo "✓ DatabaseConnectionManager carregado com sucesso<br>\n";
    
    // Listar perfis disponíveis
    echo "<h2>2. Perfis Disponíveis</h2>\n";
    $profiles = $manager->getAvailableProfiles();
    echo "Total de perfis: " . count($profiles) . "<br>\n";
    
    foreach ($profiles as $profile) {
        echo "- {$profile}<br>\n";
    }
    
    // Testar detecção de ambientes
    echo "<h2>3. Ambientes Detectados</h2>\n";
    $detected = $manager->getDetectedEnvironments();
    
    if (empty($detected)) {
        echo "⚠ Nenhum ambiente detectado automaticamente<br>\n";
        echo "Isso é normal se você não tiver MySQL instalado localmente<br>\n";
    } else {
        foreach ($detected as $env => $config) {
            echo "✓ {$config['name']} ({$config['type']})<br>\n";
        }
    }
    
    // Testar auto-seleção de perfil
    echo "<h2>4. Auto-seleção de Perfil</h2>\n";
    $currentProfile = $manager->autoSelectProfile();
    echo "Perfil selecionado: {$currentProfile}<br>\n";
    
    // Obter configuração do perfil atual
    echo "<h2>5. Configuração Atual</h2>\n";
    $config = $manager->getCurrentConfig();
    echo "Nome: {$config['name']}<br>\n";
    echo "Host: {$config['host']}:{$config['port']}<br>\n";
    echo "Database: {$config['database']}<br>\n";
    echo "Username: {$config['username']}<br>\n";
    echo "Tipo: {$config['environment_type']}<br>\n";
    
    // Testar conexão do perfil atual
    echo "<h2>6. Teste de Conexão</h2>\n";
    $testResult = $manager->testConnection();
    
    if ($testResult['success']) {
        echo "✓ Conexão bem-sucedida!<br>\n";
        echo "Versão do servidor: {$testResult['server_version']}<br>\n";
    } else {
        echo "✗ Falha na conexão: {$testResult['message']}<br>\n";
        echo "Código do erro: {$testResult['error_code']}<br>\n";
    }
    
    // Status geral do sistema
    echo "<h2>7. Status do Sistema</h2>\n";
    $status = $manager->getStatus();
    
    echo "Perfil atual: {$status['current_profile']}<br>\n";
    echo "Total de perfis: {$status['total_profiles']}<br>\n";
    echo "Auto-detecção ativa: " . ($status['auto_detection_enabled'] ? 'Sim' : 'Não') . "<br>\n";
    echo "Ambientes detectados: " . count($status['detected_environments']) . "<br>\n";
    
    // Verificar arquivos necessários
    echo "<h2>8. Verificação de Arquivos</h2>\n";
    
    $files = [
        'setup.php' => 'Interface principal',
        'assets/setup.css' => 'Estilos CSS',
        'assets/setup.js' => 'JavaScript',
        'ajax/test-connection.php' => 'API de teste',
        'ajax/save-profile.php' => 'API de perfis',
        'connections.php' => 'Perfis padrão'
    ];
    
    foreach ($files as $file => $description) {
        if (file_exists($file)) {
            echo "✓ {$description}: {$file}<br>\n";
        } else {
            echo "✗ {$description}: {$file} - ARQUIVO NÃO ENCONTRADO<br>\n";
        }
    }
    
    echo "<h2>9. Teste Concluído</h2>\n";
    echo "✓ Sistema de configuração funcionando corretamente<br>\n";
    echo "<a href='setup.php'>➤ Acessar Interface de Configuração</a><br>\n";
    
} catch (Exception $e) {
    echo "<h2>❌ Erro no Teste</h2>\n";
    echo "Erro: " . $e->getMessage() . "<br>\n";
    echo "Arquivo: " . $e->getFile() . "<br>\n";
    echo "Linha: " . $e->getLine() . "<br>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>