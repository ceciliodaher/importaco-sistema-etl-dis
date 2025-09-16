<?php
/**
 * ================================================================================
 * SISTEMA ETL DE DI's - HUB CENTRAL
 * Landing page principal como hub central para todos os sistemas e módulos ETL
 * Padrão Visual: Expertzy (#FF002D, #091A30)
 * Versão: 1.0.0
 * ================================================================================
 */

// Configurações de erro para desenvolvimento
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configurar timezone
date_default_timezone_set('America/Sao_Paulo');

// Carregar configurações do banco
require_once 'config/database.php';

// Carregar sistema de monitoramento
require_once 'shared/utils/module-status.php';
require_once 'shared/components/status-widget.php';

/**
 * Função para verificar status do sistema (legacy - mantida para compatibilidade)
 */
function getSystemStatus() {
    try {
        $db = getDatabase();
        $isReady = $db->isDatabaseReady();
        $connection = $db->testConnection();
        
        return [
            'database' => $connection ? 'online' : 'offline',
            'schema' => $isReady ? 'ready' : 'pending',
            'upload_dir' => is_writable('data/uploads/') ? 'writable' : 'readonly',
            'processed_dir' => is_writable('data/processed/') ? 'writable' : 'readonly',
            'exports_dir' => is_writable('data/exports/') ? 'writable' : 'readonly'
        ];
    } catch (Exception $e) {
        return [
            'database' => 'offline',
            'schema' => 'error',
            'upload_dir' => 'error',
            'processed_dir' => 'error',
            'exports_dir' => 'error'
        ];
    }
}

/**
 * Obter status avançado usando o novo sistema de monitoramento
 */
function getAdvancedSystemStatus() {
    try {
        $moduleStatus = getModuleStatus();
        return $moduleStatus->getSystemStatus();
    } catch (Exception $e) {
        // Fallback para função legacy
        return getSystemStatus();
    }
}

/**
 * Função para obter estatísticas rápidas do sistema (cache 5 min)
 */
function getQuickStats() {
    $cacheFile = 'data/cache/quick_stats.json';
    $cacheTime = 300; // 5 minutos
    
    // Verificar cache
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    
    try {
        $db = getDatabase();
        $stats = $db->getStatistics();
        
        // Criar diretório cache se não existir
        $cacheDir = dirname($cacheFile);
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        // Salvar cache
        file_put_contents($cacheFile, json_encode($stats));
        return $stats;
        
    } catch (Exception $e) {
        return [
            'DIs Processadas' => 0,
            'Adições' => 0,
            'Impostos Calculados' => 0,
            'Despesas Extras' => 0,
            'NCMs Catalogados' => 0,
            'Moedas Configuradas' => 0
        ];
    }
}

/**
 * Função para verificar status dos módulos
 */
function getModulesStatus() {
    $modules = [
        'dashboard' => [
            'name' => 'Dashboard ETL',
            'description' => 'Interface principal para processamento de DIs',
            'status' => 'available',
            'progress' => 100,
            'path' => 'dashboard/',
            'icon' => 'dashboard',
            'badge' => 'Disponível'
        ],
        'fiscal' => [
            'name' => 'Módulo Fiscal',
            'description' => 'Cálculos tributários e nomenclatura fiscal',
            'status' => 'development',
            'progress' => 75,
            'path' => 'modules/fiscal/',
            'icon' => 'calculator',
            'badge' => '75% Dev'
        ],
        'commercial' => [
            'name' => 'Módulo Comercial',
            'description' => 'Precificação e análise de margens',
            'status' => 'planned',
            'progress' => 0,
            'path' => '#',
            'icon' => 'trending-up',
            'badge' => 'Em Breve'
        ],
        'accounting' => [
            'name' => 'Módulo Contábil',
            'description' => 'Custeio e rateio de despesas',
            'status' => 'planned',
            'progress' => 0,
            'path' => '#',
            'icon' => 'file-text',
            'badge' => 'Em Breve'
        ],
        'billing' => [
            'name' => 'Módulo Faturamento',
            'description' => 'Geração de documentos fiscais',
            'status' => 'planned',
            'progress' => 0,
            'path' => '#',
            'icon' => 'receipt',
            'badge' => 'Em Breve'
        ]
    ];
    
    // Verificar se diretórios existem
    foreach ($modules as $key => &$module) {
        if ($module['status'] === 'available' || $module['status'] === 'development') {
            $fullPath = __DIR__ . '/' . $module['path'];
            $module['exists'] = is_dir($fullPath) && file_exists($fullPath . 'index.php');
        } else {
            $module['exists'] = false;
        }
    }
    
    return $modules;
}

// Obter dados do sistema usando o novo sistema de monitoramento
try {
    $advancedStatus = getAdvancedSystemStatus();
    $systemStatus = getSystemStatus(); // Manter compatibilidade
    $quickStats = getQuickStats();
    $modules = getModulesStatus();
    $overallStatus = $advancedStatus['overall_status'] ?? ($systemStatus['database'] === 'online' && $systemStatus['schema'] === 'ready' ? 'online' : 'offline');
    $performanceMetrics = $advancedStatus['performance'] ?? [];
} catch (Exception $e) {
    // Fallback para sistema legacy
    $systemStatus = getSystemStatus();
    $quickStats = getQuickStats();
    $modules = getModulesStatus();
    $overallStatus = $systemStatus['database'] === 'online' && $systemStatus['schema'] === 'ready' ? 'online' : 'offline';
    $performanceMetrics = [];
    $advancedStatus = null;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema ETL DI's | Hub Central Expertzy</title>
    
    <!-- Meta tags SEO -->
    <meta name="description" content="Hub central do Sistema ETL para processamento de Declarações de Importação (DI) brasileiras - Padrão Expertzy">
    <meta name="keywords" content="ETL, DI, Importação, Tributação, Sistema Fiscal, Hub Central">
    <meta name="author" content="Expertzy IT Solutions">
    
    <!-- Estilos Expertzy - IMPORTANTE: Order matters! -->
    <link rel="stylesheet" href="../assets/css/expertzy-theme.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../shared/assets/css/system-navigation.css">
    
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../images/logo-expertzy.png">
    
    <!-- Feather Icons para ícones -->
    <script src="https://unpkg.com/feather-icons"></script>
    
    <!-- Sistema de Monitoramento em Tempo Real -->
    <script src="shared/assets/js/status-monitor.js"></script>
    
    <!-- Custom styles para o hub -->
    <style>
        /* ========== HUB CENTRAL STYLES ========== */
        .hub-container {
            min-height: 100vh;
            background: linear-gradient(135deg, var(--expertzy-light-gray) 0%, #e9ecef 100%);
            padding: 0;
        }
        
        /* Header do Hub */
        .hub-header {
            background: linear-gradient(135deg, var(--expertzy-blue) 0%, #1a2b3a 100%);
            color: white;
            padding: var(--expertzy-spacing-lg) 0;
            box-shadow: var(--expertzy-shadow-lg);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--expertzy-spacing-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: var(--expertzy-spacing-base);
        }
        
        .logo-img {
            height: 40px;
            width: auto;
        }
        
        .system-info h1 {
            font-size: var(--expertzy-font-size-2xl);
            font-weight: var(--expertzy-font-weight-bold);
            margin: 0;
            color: white;
        }
        
        .version {
            font-size: var(--expertzy-font-size-sm);
            color: var(--expertzy-gray-light);
            font-weight: var(--expertzy-font-weight-medium);
        }
        
        /* Navegação principal */
        .main-navigation {
            display: flex;
            gap: var(--expertzy-spacing-lg);
        }
        
        .nav-item {
            color: white;
            text-decoration: none;
            font-weight: var(--expertzy-font-weight-medium);
            padding: var(--expertzy-spacing-sm) var(--expertzy-spacing-base);
            border-radius: var(--expertzy-border-radius-lg);
            transition: all var(--expertzy-transition-fast);
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateY(-2px);
        }
        
        .nav-item.active {
            background: var(--expertzy-red);
            color: white;
        }
        
        /* Status indicator */
        .status-indicator {
            display: flex;
            align-items: center;
            gap: var(--expertzy-spacing-sm);
            font-size: var(--expertzy-font-size-sm);
        }
        
        .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        .status-dot.online { background: var(--expertzy-success); }
        .status-dot.offline { background: var(--expertzy-danger); }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        /* Breadcrumb */
        .breadcrumb {
            background: white;
            padding: var(--expertzy-spacing-base) 0;
            border-bottom: 1px solid var(--expertzy-border-gray);
        }
        
        .breadcrumb-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--expertzy-spacing-lg);
            font-size: var(--expertzy-font-size-sm);
            color: var(--expertzy-gray);
        }
        
        .breadcrumb a {
            color: var(--expertzy-red);
            text-decoration: none;
        }
        
        /* Main content */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: var(--expertzy-spacing-2xl) var(--expertzy-spacing-lg);
        }
        
        /* Welcome section */
        .welcome-section {
            text-align: center;
            margin-bottom: var(--expertzy-spacing-3xl);
        }
        
        .welcome-title {
            font-size: var(--expertzy-font-size-4xl);
            font-weight: var(--expertzy-font-weight-extrabold);
            color: var(--expertzy-dark);
            margin-bottom: var(--expertzy-spacing-base);
        }
        
        .welcome-subtitle {
            font-size: var(--expertzy-font-size-lg);
            color: var(--expertzy-gray);
            max-width: 600px;
            margin: 0 auto var(--expertzy-spacing-xl);
        }
        
        /* Quick stats */
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: var(--expertzy-spacing-lg);
            margin-bottom: var(--expertzy-spacing-3xl);
        }
        
        .stat-card {
            background: white;
            padding: var(--expertzy-spacing-lg);
            border-radius: var(--expertzy-border-radius-xl);
            box-shadow: var(--expertzy-shadow-sm);
            text-align: center;
            transition: all var(--expertzy-transition-normal);
            border: 1px solid var(--expertzy-border-gray);
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--expertzy-shadow-lg);
        }
        
        .stat-number {
            font-size: var(--expertzy-font-size-3xl);
            font-weight: var(--expertzy-font-weight-bold);
            color: var(--expertzy-red);
            display: block;
        }
        
        .stat-label {
            font-size: var(--expertzy-font-size-sm);
            color: var(--expertzy-gray);
            font-weight: var(--expertzy-font-weight-medium);
        }
        
        /* Modules grid */
        .modules-section h2 {
            font-size: var(--expertzy-font-size-2xl);
            font-weight: var(--expertzy-font-weight-bold);
            color: var(--expertzy-dark);
            margin-bottom: var(--expertzy-spacing-xl);
            text-align: center;
        }
        
        .modules-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: var(--expertzy-spacing-xl);
        }
        
        /* Module cards */
        .module-card {
            background: white;
            border-radius: var(--expertzy-border-radius-xl);
            box-shadow: var(--expertzy-shadow-sm);
            overflow: hidden;
            transition: all var(--expertzy-transition-normal);
            border: 1px solid var(--expertzy-border-gray);
            position: relative;
        }
        
        .module-card:hover {
            transform: translateY(-6px);
            box-shadow: var(--expertzy-shadow-xl);
        }
        
        .module-card.disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .module-card.disabled:hover {
            transform: none;
            box-shadow: var(--expertzy-shadow-sm);
        }
        
        .module-header {
            padding: var(--expertzy-spacing-lg);
            display: flex;
            align-items: center;
            gap: var(--expertzy-spacing-base);
            position: relative;
        }
        
        .module-icon {
            width: 48px;
            height: 48px;
            background: var(--expertzy-gradient-primary);
            border-radius: var(--expertzy-border-radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }
        
        .module-info h3 {
            font-size: var(--expertzy-font-size-xl);
            font-weight: var(--expertzy-font-weight-bold);
            color: var(--expertzy-dark);
            margin: 0 0 var(--expertzy-spacing-xs);
        }
        
        .module-description {
            font-size: var(--expertzy-font-size-sm);
            color: var(--expertzy-gray);
            margin: 0;
        }
        
        .module-badge {
            position: absolute;
            top: var(--expertzy-spacing-base);
            right: var(--expertzy-spacing-base);
            padding: var(--expertzy-spacing-xs) var(--expertzy-spacing-sm);
            border-radius: var(--expertzy-border-radius-full);
            font-size: var(--expertzy-font-size-xs);
            font-weight: var(--expertzy-font-weight-bold);
            text-transform: uppercase;
        }
        
        .module-badge.available {
            background: var(--expertzy-success);
            color: white;
        }
        
        .module-badge.development {
            background: var(--expertzy-warning);
            color: var(--expertzy-dark);
        }
        
        .module-badge.planned {
            background: var(--expertzy-gray-light);
            color: var(--expertzy-gray);
        }
        
        .module-body {
            padding: 0 var(--expertzy-spacing-lg) var(--expertzy-spacing-lg);
        }
        
        .progress-bar {
            width: 100%;
            height: 6px;
            background: var(--expertzy-light-gray);
            border-radius: var(--expertzy-border-radius-full);
            overflow: hidden;
            margin-bottom: var(--expertzy-spacing-base);
        }
        
        .progress-fill {
            height: 100%;
            background: var(--expertzy-gradient-primary);
            border-radius: var(--expertzy-border-radius-full);
            transition: width var(--expertzy-transition-slow);
        }
        
        .module-actions {
            display: flex;
            gap: var(--expertzy-spacing-sm);
        }
        
        /* Footer */
        .hub-footer {
            background: var(--expertzy-dark);
            color: white;
            text-align: center;
            padding: var(--expertzy-spacing-2xl) 0;
            margin-top: var(--expertzy-spacing-4xl);
        }
        
        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 var(--expertzy-spacing-lg);
        }
        
        .footer-links {
            display: flex;
            justify-content: center;
            gap: var(--expertzy-spacing-xl);
            margin-bottom: var(--expertzy-spacing-base);
        }
        
        .footer-links a {
            color: var(--expertzy-gray-light);
            text-decoration: none;
            font-size: var(--expertzy-font-size-sm);
            transition: color var(--expertzy-transition-fast);
        }
        
        .footer-links a:hover {
            color: var(--expertzy-red);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: var(--expertzy-spacing-base);
                text-align: center;
            }
            
            .main-navigation {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .welcome-title {
                font-size: var(--expertzy-font-size-3xl);
            }
            
            .quick-stats {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            }
            
            .modules-grid {
                grid-template-columns: 1fr;
            }
            
            .footer-links {
                flex-direction: column;
                gap: var(--expertzy-spacing-base);
            }
        }
        
        /* Real-time monitoring styles */
        .real-time-toggle {
            background: rgba(255, 255, 255, 0.1);
            border: none;
            border-radius: 6px;
            padding: 6px;
            color: white;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-left: 8px;
        }
        
        .real-time-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.05);
        }
        
        .real-time-toggle.active {
            background: var(--expertzy-success);
        }
        
        /* Monitoring section */
        .monitoring-section .row {
            display: flex;
            gap: var(--expertzy-spacing-xl);
        }
        
        .monitoring-section .col-12 {
            flex: 1;
        }
        
        /* Performance dashboard */
        .performance-dashboard {
            height: 100%;
        }
        
        .performance-dashboard .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--expertzy-spacing-lg);
            border-bottom: 1px solid var(--expertzy-border-gray);
        }
        
        .performance-dashboard h3 {
            margin: 0;
            font-size: var(--expertzy-font-size-lg);
            font-weight: var(--expertzy-font-weight-bold);
            color: var(--expertzy-dark);
        }
        
        .dashboard-controls {
            display: flex;
            gap: var(--expertzy-spacing-sm);
        }
        
        .performance-metrics {
            display: flex;
            flex-direction: column;
            gap: var(--expertzy-spacing-lg);
            margin-bottom: var(--expertzy-spacing-xl);
        }
        
        .metric-item {
            padding: var(--expertzy-spacing-base);
            border: 1px solid var(--expertzy-border-gray);
            border-radius: var(--expertzy-border-radius-lg);
            background: var(--expertzy-light-gray);
        }
        
        .metric-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--expertzy-spacing-sm);
        }
        
        .metric-label {
            font-size: var(--expertzy-font-size-sm);
            font-weight: var(--expertzy-font-weight-medium);
            color: var(--expertzy-gray);
        }
        
        .metric-value {
            font-size: var(--expertzy-font-size-lg);
            font-weight: var(--expertzy-font-weight-bold);
            color: var(--expertzy-dark);
        }
        
        .metric-bar {
            width: 100%;
            height: 8px;
            background: var(--expertzy-border-gray);
            border-radius: var(--expertzy-border-radius-full);
            overflow: hidden;
        }
        
        .metric-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--expertzy-success) 0%, var(--expertzy-success) 70%, var(--expertzy-warning) 85%, var(--expertzy-danger) 100%);
            transition: width 0.3s ease;
        }
        
        .metric-chart {
            height: 60px;
            background: rgba(255, 0, 45, 0.05);
            border-radius: var(--expertzy-border-radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--expertzy-gray);
            font-size: var(--expertzy-font-size-sm);
        }
        
        /* Connection status */
        .connection-status {
            display: flex;
            flex-direction: column;
            gap: var(--expertzy-spacing-base);
        }
        
        .connection-status .status-item {
            display: flex;
            align-items: center;
            gap: var(--expertzy-spacing-base);
            padding: var(--expertzy-spacing-sm) var(--expertzy-spacing-base);
            border-radius: var(--expertzy-border-radius-lg);
            background: white;
            border: 1px solid var(--expertzy-border-gray);
        }
        
        .connection-status .status-item i {
            width: 16px;
            height: 16px;
            color: var(--expertzy-gray);
        }
        
        .connection-status .status-item span {
            flex: 1;
            font-size: var(--expertzy-font-size-sm);
            font-weight: var(--expertzy-font-weight-medium);
            color: var(--expertzy-dark);
        }
        
        .status-badge {
            padding: var(--expertzy-spacing-xs) var(--expertzy-spacing-sm);
            border-radius: var(--expertzy-border-radius-full);
            font-size: var(--expertzy-font-size-xs);
            font-weight: var(--expertzy-font-weight-bold);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-badge.status-online {
            background: var(--expertzy-success);
            color: white;
        }
        
        .status-badge.status-offline {
            background: var(--expertzy-danger);
            color: white;
        }
        
        .status-badge.status-warning {
            background: var(--expertzy-warning);
            color: var(--expertzy-dark);
        }
        
        /* Grid system */
        .row {
            display: flex;
            flex-wrap: wrap;
            gap: var(--expertzy-spacing-lg);
        }
        
        .col-12 {
            flex: 1;
            min-width: 0;
        }
        
        @media (max-width: 992px) {
            .col-lg-6 {
                flex: 1 1 100%;
            }
            
            .monitoring-section .row {
                flex-direction: column;
            }
        }
        
        /* Loading animation */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid var(--expertzy-light-gray);
            border-radius: 50%;
            border-top-color: var(--expertzy-red);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>

<body class="hub-container animate-fade-in">
    <!-- Header -->
    <header class="hub-header">
        <div class="header-content">
            <div class="logo-section">
                <a href="../index.html">
                    <img src="../images/logo-expertzy.png" alt="Expertzy" class="logo-img">
                </a>
                <div class="system-info">
                    <h1>Sistema ETL DI's</h1>
                    <span class="version">Hub Central v1.0.0</span>
                </div>
            </div>
            
            <nav class="main-navigation">
                <a href="#sistemas" class="nav-item active">Dashboard</a>
                <a href="#modulos" class="nav-item">Módulos</a>
                <a href="#relatorios" class="nav-item">Relatórios</a>
                <a href="#config" class="nav-item">Configurações</a>
            </nav>
            
            <div class="status-indicator" data-status-indicator="system">
                <span class="status-dot <?php echo $overallStatus; ?>"></span>
                <span class="status-text">Sistema <?php echo ucfirst($overallStatus); ?></span>
                <button class="real-time-toggle" onclick="toggleRealtimeMonitoring()" title="Alternar Monitoramento em Tempo Real">
                    <i data-feather="wifi" id="realtime-icon"></i>
                </button>
            </div>
        </div>
    </header>

    <!-- Breadcrumb -->
    <nav class="breadcrumb">
        <div class="breadcrumb-content">
            <a href="../index.html">Home</a> > <span>Sistema</span>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Welcome Section -->
        <section class="welcome-section" id="sistemas">
            <h1 class="welcome-title animate-fade-in text-center">Hub Central do Sistema ETL</h1>
            <p class="welcome-subtitle animate-fade-in text-center">
                Processamento inteligente de Declarações de Importação (DI) brasileiras com análise fiscal completa, 
                cálculos tributários automatizados e geração de relatórios gerenciais.
            </p>
        </section>

        <!-- Real-time Monitoring Widget -->
        <section class="monitoring-section" id="monitoring" style="margin-bottom: var(--expertzy-spacing-3xl);">
            <div class="row">
                <div class="col-12 col-lg-6">
                    <?php
                    // Renderizar widget de status com configurações específicas para o hub
                    renderStatusWidget('hub-status-widget', [
                        'show_details' => true,
                        'show_performance' => true,
                        'show_modules' => true,
                        'show_components' => true,
                        'compact_mode' => false,
                        'refresh_interval' => 30000,
                        'theme' => 'default'
                    ]);
                    ?>
                </div>
                <div class="col-12 col-lg-6">
                    <!-- Performance Dashboard -->
                    <div class="performance-dashboard card">
                        <div class="card-header">
                            <h3>Performance em Tempo Real</h3>
                            <div class="dashboard-controls">
                                <button class="btn btn-sm btn-outline-primary" onclick="refreshPerformance()">
                                    <i data-feather="refresh-cw"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Métricas de Performance -->
                            <div class="performance-metrics">
                                <div class="metric-item" data-metric="response">
                                    <div class="metric-header">
                                        <span class="metric-label">Tempo de Resposta</span>
                                        <span class="metric-value"><?php echo isset($performanceMetrics['response_time']) ? $performanceMetrics['response_time'] : 0; ?>ms</span>
                                    </div>
                                    <div class="metric-chart" id="response-chart"></div>
                                </div>
                                
                                <div class="metric-item" data-metric="memory">
                                    <div class="metric-header">
                                        <span class="metric-label">Uso de Memória</span>
                                        <span class="metric-value"><?php echo isset($performanceMetrics['memory_usage']['percentage']) ? $performanceMetrics['memory_usage']['percentage'] : 0; ?>%</span>
                                    </div>
                                    <div class="metric-bar">
                                        <div class="metric-fill" style="width: <?php echo isset($performanceMetrics['memory_usage']['percentage']) ? $performanceMetrics['memory_usage']['percentage'] : 0; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div class="metric-item" data-metric="disk">
                                    <div class="metric-header">
                                        <span class="metric-label">Espaço em Disco</span>
                                        <span class="metric-value"><?php echo isset($performanceMetrics['disk_space']['percentage']) ? $performanceMetrics['disk_space']['percentage'] : 0; ?>%</span>
                                    </div>
                                    <div class="metric-bar">
                                        <div class="metric-fill" style="width: <?php echo isset($performanceMetrics['disk_space']['percentage']) ? $performanceMetrics['disk_space']['percentage'] : 0; %>%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Status de Conexão -->
                            <div class="connection-status">
                                <div class="status-item">
                                    <i data-feather="database"></i>
                                    <span>Database</span>
                                    <div class="status-badge status-<?php echo $systemStatus['database']; ?>" data-status-indicator="database">
                                        <?php echo ucfirst($systemStatus['database']); ?>
                                    </div>
                                </div>
                                
                                <div class="status-item">
                                    <i data-feather="upload-cloud"></i>
                                    <span>Uploads</span>
                                    <div class="status-badge status-<?php echo $systemStatus['upload_dir'] === 'writable' ? 'online' : 'offline'; ?>">
                                        <?php echo $systemStatus['upload_dir'] === 'writable' ? 'Online' : 'Offline'; ?>
                                    </div>
                                </div>
                                
                                <div class="status-item">
                                    <i data-feather="wifi"></i>
                                    <span>Monitoramento</span>
                                    <div class="status-badge status-online" id="monitoring-status">
                                        Ativo
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Stats -->
        <section class="quick-stats" id="estatisticas">
            <?php foreach ($quickStats as $label => $value): ?>
            <div class="stat-card card animate-scale-in">
                <span class="stat-number"><?php echo number_format($value); ?></span>
                <span class="stat-label"><?php echo htmlspecialchars($label); ?></span>
            </div>
            <?php endforeach; ?>
        </section>

        <!-- Modules Section -->
        <section class="modules-section" id="modulos">
            <h2>Sistemas e Módulos Disponíveis</h2>
            
            <div class="modules-grid">
                <?php foreach ($modules as $moduleKey => $module): ?>
                <div class="module-card card <?php echo $module['status'] === 'planned' ? 'disabled' : ''; ?> animate-slide-in-right">
                    <div class="module-header">
                        <div class="module-icon">
                            <i data-feather="<?php echo htmlspecialchars($module['icon']); ?>"></i>
                        </div>
                        <div class="module-info">
                            <h3><?php echo htmlspecialchars($module['name']); ?></h3>
                            <p class="module-description"><?php echo htmlspecialchars($module['description']); ?></p>
                        </div>
                        <span class="module-badge <?php echo $module['status']; ?>">
                            <?php echo htmlspecialchars($module['badge']); ?>
                        </span>
                    </div>
                    
                    <div class="module-body">
                        <?php if ($module['progress'] > 0): ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $module['progress']; ?>%"></div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="module-actions">
                            <?php if ($module['status'] === 'available' && $module['exists']): ?>
                                <a href="<?php echo htmlspecialchars($module['path']); ?>" class="btn btn-primary">
                                    <i data-feather="arrow-right" style="width: 16px; height: 16px; margin-right: 8px;"></i>
                                    Acessar Sistema
                                </a>
                            <?php elseif ($module['status'] === 'development'): ?>
                                <button class="btn btn-outline-primary" disabled>
                                    <i data-feather="settings" style="width: 16px; height: 16px; margin-right: 8px;"></i>
                                    Em Desenvolvimento
                                </button>
                            <?php else: ?>
                                <button class="btn btn-outline-primary" disabled>
                                    <i data-feather="clock" style="width: 16px; height: 16px; margin-right: 8px;"></i>
                                    Em Breve
                                </button>
                            <?php endif; ?>
                            
                            <?php if (in_array($module['status'], ['available', 'development'])): ?>
                            <a href="#docs-<?php echo $moduleKey; ?>" class="btn btn-outline-primary btn-sm">
                                <i data-feather="book" style="width: 14px; height: 14px; margin-right: 6px;"></i>
                                Docs
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- System Status Details -->
        <section class="system-status" style="margin-top: var(--expertzy-spacing-3xl);">
            <div class="card">
                <div class="card-header">
                    <h3>Status Detalhado do Sistema</h3>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--expertzy-spacing-base);">
                        <?php foreach ($systemStatus as $component => $status): ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: var(--expertzy-spacing-sm); border-radius: var(--expertzy-border-radius-md); background: <?php echo $status === 'online' || $status === 'ready' || $status === 'writable' ? 'var(--expertzy-success)' : 'var(--expertzy-danger)'; ?>; color: white;">
                            <span style="font-weight: var(--expertzy-font-weight-medium);"><?php echo ucfirst(str_replace('_', ' ', $component)); ?></span>
                            <span style="font-size: var(--expertzy-font-size-sm);"><?php echo ucfirst($status); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="hub-footer">
        <div class="footer-content">
            <div class="footer-links">
                <a href="../docs/">Documentação</a>
                <a href="../docs/api/">API</a>
                <a href="../docs/database/">Schema Database</a>
                <a href="https://github.com/expertzy/etl-dis">GitHub</a>
                <a href="mailto:suporte@expertzy.com">Suporte</a>
            </div>
            <p style="color: var(--expertzy-gray-light); font-size: var(--expertzy-font-size-sm); margin: 0;">
                © <?php echo date('Y'); ?> Expertzy IT Solutions. Sistema ETL DI's v1.0.0 - 
                Desenvolvido com padrão de excelência para processamento fiscal brasileiro.
            </p>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Inicializar ícones Feather
        feather.replace();

        // Smooth scroll para navegação
        document.querySelectorAll('.nav-item[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Animação de contadores
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-number');
            
            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/,/g, ''));
                const increment = target / 30;
                let current = 0;
                
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    counter.textContent = Math.floor(current).toLocaleString('pt-BR');
                }, 50);
            });
        }

        // Verificação de status em tempo real (a cada 30 segundos)
        function checkSystemStatus() {
            const statusDot = document.querySelector('.status-dot');
            const statusText = statusDot.nextElementSibling;
            
            // Simular verificação (em produção, fazer fetch para API)
            // fetch('api/system/status')
            //     .then(response => response.json())
            //     .then(data => {
            //         statusDot.className = `status-dot ${data.status}`;
            //         statusText.textContent = `Sistema ${data.status.charAt(0).toUpperCase() + data.status.slice(1)}`;
            //     });
        }

        // Inicializar animações quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(animateCounters, 500);
            
            // Verificar status a cada 30 segundos
            setInterval(checkSystemStatus, 30000);
        });

        // Navigation active state
        function updateNavigation() {
            const sections = document.querySelectorAll('section[id]');
            const navItems = document.querySelectorAll('.nav-item');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        navItems.forEach(item => item.classList.remove('active'));
                        const targetNav = document.querySelector(`.nav-item[href="#${entry.target.id}"]`);
                        if (targetNav) {
                            targetNav.classList.add('active');
                        }
                    }
                });
            }, { threshold: 0.5 });

            sections.forEach(section => observer.observe(section));
        }

        // Initialize navigation observer
        document.addEventListener('DOMContentLoaded', updateNavigation);

        // Module card hover effects
        document.querySelectorAll('.module-card:not(.disabled)').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-6px) scale(1.02)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
        
        // ========== FUNÇÕES DE MONITORAMENTO EM TEMPO REAL ==========
        
        let realtimeMonitoringEnabled = true;
        let statusMonitor = null;
        
        /**
         * Inicializar monitoramento em tempo real
         */
        function initializeRealtimeMonitoring() {
            if (typeof SystemStatusMonitor !== 'undefined') {
                statusMonitor = new SystemStatusMonitor({
                    refreshInterval: 30000,
                    quickRefreshInterval: 5000,
                    enableNotifications: true,
                    debug: false
                });
                
                // Subscrever a eventos
                statusMonitor.subscribe('status', handleStatusUpdate);
                statusMonitor.subscribe('statusChange', handleStatusChange);
                statusMonitor.subscribe('error', handleMonitoringError);
                statusMonitor.subscribe('performance', handlePerformanceUpdate);
                
                console.log('Sistema de monitoramento em tempo real inicializado');
            } else {
                console.warn('SystemStatusMonitor não encontrado. Monitoramento em tempo real desabilitado.');
            }
        }
        
        /**
         * Alternar monitoramento em tempo real
         */
        function toggleRealtimeMonitoring() {
            const button = document.querySelector('.real-time-toggle');
            const icon = document.getElementById('realtime-icon');
            const statusElement = document.getElementById('monitoring-status');
            
            if (realtimeMonitoringEnabled) {
                // Desabilitar
                if (statusMonitor) {
                    statusMonitor.stop();
                }
                realtimeMonitoringEnabled = false;
                button.classList.remove('active');
                icon.setAttribute('data-feather', 'wifi-off');
                statusElement.textContent = 'Pausado';
                statusElement.className = 'status-badge status-warning';
                
                showNotification('Monitoramento em tempo real pausado', 'warning');
            } else {
                // Habilitar
                if (statusMonitor) {
                    statusMonitor.start();
                } else {
                    initializeRealtimeMonitoring();
                }
                realtimeMonitoringEnabled = true;
                button.classList.add('active');
                icon.setAttribute('data-feather', 'wifi');
                statusElement.textContent = 'Ativo';
                statusElement.className = 'status-badge status-online';
                
                showNotification('Monitoramento em tempo real ativado', 'success');
            }
            
            // Atualizar ícones
            feather.replace();
        }
        
        /**
         * Atualizar status do sistema
         */
        function handleStatusUpdate(data) {
            // Atualizar timestamp
            const timestampElements = document.querySelectorAll('[data-timestamp]');
            timestampElements.forEach(element => {
                element.textContent = new Date(data.timestamp * 1000).toLocaleTimeString();
            });
            
            // Atualizar indicadores automáticos já é feito pelo status-monitor.js
        }
        
        /**
         * Lidar com mudanças de status
         */
        function handleStatusChange(data) {
            console.log('Mudança de status detectada:', data);
            
            // Animate status changes
            const indicators = document.querySelectorAll('[data-status-indicator]');
            indicators.forEach(indicator => {
                indicator.style.transform = 'scale(1.1)';
                setTimeout(() => {
                    indicator.style.transform = 'scale(1)';
                }, 300);
            });
        }
        
        /**
         * Lidar com erros de monitoramento
         */
        function handleMonitoringError(data) {
            console.error('Erro no monitoramento:', data);
            
            const statusElement = document.getElementById('monitoring-status');
            if (statusElement) {
                statusElement.textContent = 'Erro';
                statusElement.className = 'status-badge status-offline';
            }
        }
        
        /**
         * Atualizar métricas de performance
         */
        function handlePerformanceUpdate(data) {
            // Atualizar métricas de response time
            const responseMetric = document.querySelector('[data-metric="response"] .metric-value');
            if (responseMetric && data.api_response_time) {
                responseMetric.textContent = data.api_response_time + 'ms';
            }
            
            // Atualizar outros dados de performance conforme necessário
        }
        
        /**
         * Refresh manual de performance
         */
        function refreshPerformance() {
            if (statusMonitor) {
                statusMonitor.forceRefresh();
                
                // Animação visual no botão
                const button = event.target.closest('button');
                const icon = button.querySelector('i');
                icon.style.animation = 'spin 1s linear';
                
                setTimeout(() => {
                    icon.style.animation = '';
                }, 1000);
            }
        }
        
        /**
         * Mostrar notificação simples
         */
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `hub-notification hub-notification-${type}`;
            notification.textContent = message;
            
            // Estilos inline
            notification.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                background: ${getNotificationColor(type)};
                color: white;
                padding: 12px 16px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                font-size: 14px;
                max-width: 300px;
                animation: slideInRight 0.3s ease;
            `;
            
            document.body.appendChild(notification);
            
            // Remover automaticamente
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 3000);
        }
        
        /**
         * Obter cor da notificação
         */
        function getNotificationColor(type) {
            const colors = {
                success: '#10b981',
                warning: '#f59e0b',
                error: '#ef4444',
                info: '#3b82f6'
            };
            return colors[type] || '#3b82f6';
        }

        console.log('Hub Central ETL DI\'s inicializado com sucesso!');
        console.log('Status do sistema:', <?php echo json_encode($systemStatus); ?>);
        console.log('Estatísticas rápidas:', <?php echo json_encode($quickStats); ?>);
        
        // Inicializar sistema de monitoramento em tempo real
        initializeRealtimeMonitoring();
    </script>
</body>
</html>