<?php
/**
 * ================================================================================
 * EXEMPLOS DE USO DO SISTEMA DE NAVEGAÇÃO UNIFICADO
 * Demonstra como implementar o sistema de navegação em diferentes páginas
 * Padrão Visual: Expertzy (#FF002D, #091A30)
 * ================================================================================
 */

// Carregar sistema de layout
require_once '../utils/layout-helpers.php';

/**
 * EXEMPLO 1: Página simples com layout completo
 */
function exemploLayoutCompleto() {
    $config = [
        'page_title' => 'Exemplo de Página',
        'page_description' => 'Demonstração do sistema de navegação unificado',
        'layout_type' => 'default',
        'show_breadcrumbs' => true,
        'additional_css' => ['custom-styles.css'],
        'body_class' => 'exemplo-pagina'
    ];
    
    renderSystemLayout($config, function() {
        ?>
        <div class="container">
            <h1>Exemplo de Página com Layout Completo</h1>
            <p>Esta página utiliza o sistema de navegação unificado com header, footer e breadcrumbs.</p>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Conteúdo Principal</h5>
                            <p class="card-text">Aqui vai o conteúdo principal da página.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Sidebar</h5>
                            <p class="card-text">Conteúdo da sidebar.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    });
}

/**
 * EXEMPLO 2: Dashboard personalizado
 */
function exemploDashboard() {
    $config = [
        'page_title' => 'Dashboard Customizado',
        'page_description' => 'Exemplo de dashboard usando o sistema unificado',
        'layout_type' => 'dashboard',
        'additional_js' => ['charts.js', 'dashboard-custom.js']
    ];
    
    renderDashboardLayout($config, function() {
        ?>
        <div class="dashboard-grid">
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="stat-card">
                        <div class="stat-icon bg-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3 data-stat="users">1,234</h3>
                            <p>Usuários Ativos</p>
                        </div>
                    </div>
                </div>
                <!-- Mais cards de estatísticas -->
            </div>
            
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>Gráfico de Exemplo</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="exemploChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    });
}

/**
 * EXEMPLO 3: Página simples sem footer
 */
function exemploSimples() {
    $config = [
        'page_title' => 'Página Simples',
        'layout_type' => 'simple',
        'show_breadcrumbs' => false
    ];
    
    renderSimpleLayout($config, function() {
        ?>
        <div class="container text-center py-5">
            <h1>Página Simples</h1>
            <p class="lead">Layout minimalista para páginas específicas.</p>
            <button class="btn btn-primary">Ação Principal</button>
        </div>
        <?php
    });
}

/**
 * EXEMPLO 4: Layout fullscreen
 */
function exemploFullscreen() {
    $config = [
        'page_title' => 'Aplicação Fullscreen',
        'layout_type' => 'fullscreen'
    ];
    
    renderFullscreenLayout($config, function() {
        ?>
        <div class="fullscreen-app">
            <div class="app-toolbar">
                <h2>Aplicação em Tela Cheia</h2>
                <div class="toolbar-actions">
                    <button class="btn btn-sm btn-outline-primary">Configurações</button>
                    <button class="btn btn-sm btn-primary">Salvar</button>
                </div>
            </div>
            
            <div class="app-content">
                <div class="app-sidebar">
                    <nav class="sidebar-nav">
                        <a href="#" class="nav-link active">Item 1</a>
                        <a href="#" class="nav-link">Item 2</a>
                        <a href="#" class="nav-link">Item 3</a>
                    </nav>
                </div>
                
                <div class="app-main">
                    <div class="content-area">
                        <h3>Área de Conteúdo Principal</h3>
                        <p>Conteúdo da aplicação fullscreen.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php
    });
}

/**
 * EXEMPLO 5: Usar apenas header e footer sem layout manager
 */
function exemploComponentesSeparados() {
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Exemplo Componentes Separados</title>
        <link rel="stylesheet" href="/assets/css/expertzy-theme.css">
        <link rel="stylesheet" href="/sistema/shared/assets/css/system-navigation.css">
    </head>
    <body>
        
        <?php
        // Incluir apenas o header
        includeHeader([
            'show_breadcrumbs' => true,
            'show_status' => true,
            'current_url' => '/exemplo-separado',
            'custom_title' => 'Exemplo Separado'
        ]);
        ?>
        
        <main class="main-content">
            <div class="container py-4">
                <h1>Exemplo com Componentes Separados</h1>
                <p>Esta página usa header e footer separadamente, sem o LayoutManager.</p>
                
                <div class="alert alert-info">
                    <strong>Dica:</strong> Este método é útil quando você precisa de mais controle
                    sobre o HTML da página ou quando está integrando com sistemas existentes.
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h3>Vantagens</h3>
                        <ul>
                            <li>Controle total sobre o HTML</li>
                            <li>Facilita integração</li>
                            <li>Flexibilidade máxima</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h3>Desvantagens</h3>
                        <ul>
                            <li>Mais código para manter</li>
                            <li>Possíveis inconsistências</li>
                            <li>SEO manual</li>
                        </ul>
                    </div>
                </div>
            </div>
        </main>
        
        <?php
        // Incluir apenas o footer
        includeFooter([
            'show_modules' => true,
            'show_system_info' => false
        ]);
        ?>
        
        <script src="/sistema/shared/assets/js/navigation.js"></script>
    </body>
    </html>
    <?php
}

/**
 * EXEMPLO 6: Página de módulo específico
 */
function exemploModulo() {
    $config = [
        'page_title' => 'Módulo Fiscal',
        'page_description' => 'Gestão de cálculos tributários e nomenclatura fiscal',
        'layout_type' => 'default',
        'additional_css' => ['modules/fiscal.css'],
        'additional_js' => ['modules/fiscal.js'],
        'meta_tags' => [
            'keywords' => 'Fiscal, Tributação, ICMS, IPI, NCM',
            'robots' => 'noindex, nofollow'
        ]
    ];
    
    renderSystemLayout($config, function() {
        ?>
        <div class="module-container">
            <div class="module-header">
                <div class="container">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="module-title">
                                <span class="module-icon">📊</span>
                                Módulo Fiscal
                            </h1>
                            <p class="module-description">
                                Gestão completa de cálculos tributários e nomenclatura fiscal
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end">
                            <div class="module-status">
                                <span class="status-indicator online"></span>
                                <span>Módulo Ativo</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="module-content">
                <div class="container">
                    <div class="row">
                        <div class="col-lg-3">
                            <div class="module-sidebar">
                                <nav class="module-nav">
                                    <h6>Funcionalidades</h6>
                                    <a href="#impostos" class="nav-link active">Cálculo de Impostos</a>
                                    <a href="#ncm" class="nav-link">Gestão NCM</a>
                                    <a href="#cfop" class="nav-link">CFOP</a>
                                    <a href="#incentivos" class="nav-link">Incentivos Fiscais</a>
                                </nav>
                            </div>
                        </div>
                        
                        <div class="col-lg-9">
                            <div class="module-main">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Cálculo de Impostos</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Interface para cálculos tributários das DIs processadas.</p>
                                        
                                        <div class="tax-calculator">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>Valor CIF (USD)</label>
                                                        <input type="number" class="form-control" placeholder="0.00">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="form-group">
                                                        <label>NCM</label>
                                                        <input type="text" class="form-control" placeholder="0000.00.00">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <button class="btn btn-primary">Calcular Impostos</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    });
}

// Determinar qual exemplo executar baseado no parâmetro GET
$exemplo = $_GET['exemplo'] ?? 'completo';

switch ($exemplo) {
    case 'dashboard':
        exemploDashboard();
        break;
    case 'simples':
        exemploSimples();
        break;
    case 'fullscreen':
        exemploFullscreen();
        break;
    case 'separados':
        exemploComponentesSeparados();
        break;
    case 'modulo':
        exemploModulo();
        break;
    default:
        exemploLayoutCompleto();
        break;
}
?>