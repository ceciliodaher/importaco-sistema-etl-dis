/**
 * ================================================================================
 * TESTE DO SISTEMA DE CONTROLE MANUAL
 * Validação e demonstração das funcionalidades
 * ================================================================================
 */

/**
 * Suite de testes para o sistema de controle manual
 */
class ManualControlTest {
    constructor() {
        this.tests = [];
        this.results = [];
        this.manualControl = null;
        
        this.init();
    }
    
    async init() {
        console.log('🧪 Iniciando testes do Sistema de Controle Manual...');
        
        // Aguardar sistema estar pronto
        await this.waitForSystem();
        
        // Executar testes
        await this.runTests();
        
        // Exibir resultados
        this.displayResults();
    }
    
    async waitForSystem() {
        let attempts = 0;
        while (!window.manualControlSystem && attempts < 100) {
            await new Promise(resolve => setTimeout(resolve, 100));
            attempts++;
        }
        
        if (window.manualControlSystem) {
            this.manualControl = window.manualControlSystem;
            console.log('✅ Sistema encontrado para teste');
        } else {
            throw new Error('❌ Sistema de controle manual não encontrado');
        }
    }
    
    async runTests() {
        // Teste 1: Verificar inicialização
        await this.test('Inicialização do Sistema', async () => {
            const state = this.manualControl.getState();
            const feedback = this.manualControl.getFeedback();
            const autoRefresh = this.manualControl.getAutoRefresh();
            
            return state && feedback && autoRefresh;
        });
        
        // Teste 2: Estado do dashboard
        await this.test('Estado do Dashboard', async () => {
            const state = this.manualControl.getState();
            
            return state.database !== undefined &&
                   state.charts !== undefined &&
                   state.stats !== undefined &&
                   state.autoRefresh !== undefined;
        });
        
        // Teste 3: Validações inteligentes
        await this.test('Validações Inteligentes', async () => {
            const state = this.manualControl.getState();
            
            const hasCanLoadCharts = typeof state.canLoadCharts === 'function';
            const hasCanLoadStats = typeof state.canLoadStats === 'function';
            const hasNextAction = typeof state.getNextRecommendedAction === 'function';
            
            return hasCanLoadCharts && hasCanLoadStats && hasNextAction;
        });
        
        // Teste 4: Sistema de feedback
        await this.test('Sistema de Feedback', async () => {
            const feedback = this.manualControl.getFeedback();
            
            // Testar toast
            const toastId = feedback.showToast('Teste de toast', 'info', { duration: 1000 });
            
            // Verificar se toast foi criado
            const toastElement = document.getElementById(toastId);
            const success = toastElement !== null;
            
            // Limpar toast
            if (toastElement) {
                setTimeout(() => feedback.removeToast(toastId), 100);
            }
            
            return success;
        });
        
        // Teste 5: Auto-refresh manager
        await this.test('Auto-refresh Manager', async () => {
            const autoRefresh = this.manualControl.getAutoRefresh();
            
            // Testar status inicial
            const initialStatus = autoRefresh.getStatus();
            
            // Testar start/stop (sem executar refresh real)
            autoRefresh.start(10000);
            const runningStatus = autoRefresh.getStatus();
            
            autoRefresh.stop();
            const stoppedStatus = autoRefresh.getStatus();
            
            return initialStatus && 
                   runningStatus.isRunning && 
                   !stoppedStatus.isRunning;
        });
        
        // Teste 6: Integração com APIs
        await this.test('Integração com APIs', async () => {
            try {
                // Testar apenas se API está acessível (sem executar)
                const api = this.manualControl.api;
                
                return api && 
                       typeof api.checkDatabaseStatus === 'function' &&
                       typeof api.loadChartsData === 'function' &&
                       typeof api.loadStats === 'function';
            } catch (error) {
                return false;
            }
        });
        
        // Teste 7: Event system
        await this.test('Sistema de Eventos', async () => {
            const state = this.manualControl.getState();
            let eventFired = false;
            
            // Configurar listener de teste
            state.on('test-event', () => {
                eventFired = true;
            });
            
            // Disparar evento
            state.emit('test-event');
            
            return eventFired;
        });
        
        // Teste 8: Persistência de estado
        await this.test('Persistência de Estado', async () => {
            try {
                // Verificar se localStorage está funcional
                const testKey = 'etl_test_storage';
                const testValue = { test: true, timestamp: Date.now() };
                
                localStorage.setItem(testKey, JSON.stringify(testValue));
                const stored = JSON.parse(localStorage.getItem(testKey));
                localStorage.removeItem(testKey);
                
                return stored && stored.test === true;
            } catch (error) {
                return false;
            }
        });
        
        // Teste 9: Botões de controle
        await this.test('Botões de Controle', async () => {
            const buttons = [
                'btnVerifyDatabase',
                'btnLoadCharts', 
                'btnLoadStats',
                'btnRefreshAll',
                'btnClearCache'
            ];
            
            let allButtonsFound = true;
            
            buttons.forEach(buttonId => {
                const button = document.getElementById(buttonId);
                if (!button) {
                    allButtonsFound = false;
                    console.warn(`❌ Botão ${buttonId} não encontrado`);
                }
            });
            
            return allButtonsFound;
        });
        
        // Teste 10: Integração com dashboard
        await this.test('Integração com Dashboard', async () => {
            return window.dashboardIntegration && 
                   window.dashboardIntegration.isReady &&
                   typeof window.dashboardIntegration.isReady === 'function';
        });
    }
    
    async test(name, testFunction) {
        console.log(`🧪 Executando: ${name}`);
        
        try {
            const result = await testFunction();
            
            this.results.push({
                name,
                passed: result,
                error: null
            });
            
            console.log(`${result ? '✅' : '❌'} ${name}: ${result ? 'PASSOU' : 'FALHOU'}`);
            
        } catch (error) {
            this.results.push({
                name,
                passed: false,
                error: error.message
            });
            
            console.log(`❌ ${name}: ERRO - ${error.message}`);
        }
    }
    
    displayResults() {
        const passed = this.results.filter(r => r.passed).length;
        const total = this.results.length;
        const percentage = Math.round((passed / total) * 100);
        
        console.log('\n🧪 RESULTADOS DOS TESTES:');
        console.log(`📊 ${passed}/${total} testes passaram (${percentage}%)`);
        
        // Exibir falhas
        const failures = this.results.filter(r => !r.passed);
        if (failures.length > 0) {
            console.log('\n❌ FALHAS:');
            failures.forEach(failure => {
                console.log(`   • ${failure.name}${failure.error ? `: ${failure.error}` : ''}`);
            });
        }
        
        // Exibir resumo visual
        this.showVisualResults(passed, total, percentage);
        
        // Feedback para usuário
        if (window.manualControlSystem) {
            const feedback = window.manualControlSystem.getFeedback();
            if (feedback) {
                feedback.showToast(
                    `Testes concluídos: ${passed}/${total} (${percentage}%)`,
                    percentage >= 80 ? 'success' : percentage >= 60 ? 'warning' : 'error',
                    { 
                        subtitle: percentage < 100 ? 'Verifique console para detalhes' : 'Todos os testes passaram!',
                        duration: 8000
                    }
                );
            }
        }
    }
    
    showVisualResults(passed, total, percentage) {
        // Criar elemento visual de resultados
        const resultsDiv = document.createElement('div');
        resultsDiv.id = 'test-results';
        resultsDiv.style.cssText = `
            position: fixed;
            top: 20px;
            left: 20px;
            background: white;
            border: 2px solid ${percentage >= 80 ? '#10B981' : percentage >= 60 ? '#F59E0B' : '#EF4444'};
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            z-index: 10000;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 300px;
        `;
        
        resultsDiv.innerHTML = `
            <div style="display: flex; align-items: center; margin-bottom: 12px;">
                <div style="font-size: 24px; margin-right: 8px;">
                    ${percentage >= 80 ? '✅' : percentage >= 60 ? '⚠️' : '❌'}
                </div>
                <div>
                    <div style="font-weight: 600; color: #111827;">Testes do Sistema</div>
                    <div style="font-size: 14px; color: #6B7280;">${passed}/${total} testes passaram</div>
                </div>
            </div>
            
            <div style="background: #F3F4F6; border-radius: 8px; height: 8px; overflow: hidden; margin-bottom: 12px;">
                <div style="
                    background: ${percentage >= 80 ? '#10B981' : percentage >= 60 ? '#F59E0B' : '#EF4444'};
                    height: 100%;
                    width: ${percentage}%;
                    transition: width 0.3s ease;
                "></div>
            </div>
            
            <div style="font-size: 12px; color: #6B7280;">
                Verifique o console para detalhes completos
            </div>
            
            <button onclick="this.parentNode.remove()" style="
                position: absolute;
                top: 8px;
                right: 8px;
                background: none;
                border: none;
                color: #6B7280;
                cursor: pointer;
                font-size: 18px;
                width: 24px;
                height: 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                border-radius: 4px;
            " onmouseover="this.style.background='#F3F4F6'" onmouseout="this.style.background='none'">
                ×
            </button>
        `;
        
        document.body.appendChild(resultsDiv);
        
        // Auto-remover após 15 segundos
        setTimeout(() => {
            if (resultsDiv.parentNode) {
                resultsDiv.remove();
            }
        }, 15000);
    }
    
    // Método público para executar testes específicos
    async runSpecificTest(testName) {
        const testMap = {
            'feedback': () => this.test('Sistema de Feedback Específico', async () => {
                const feedback = this.manualControl.getFeedback();
                
                // Testar diferentes tipos de toast
                feedback.showToast('Teste Success', 'success', { duration: 2000 });
                feedback.showToast('Teste Warning', 'warning', { duration: 2000 });
                feedback.showToast('Teste Error', 'error', { duration: 2000 });
                feedback.showToast('Teste Info', 'info', { duration: 2000 });
                
                return true;
            }),
            
            'loading': () => this.test('Estados de Loading', async () => {
                const feedback = this.manualControl.getFeedback();
                
                // Testar loading overlay
                feedback.showLoading('manualControlPanel', 'Teste de loading...');
                
                setTimeout(() => {
                    feedback.hideLoading('manualControlPanel');
                }, 3000);
                
                return true;
            }),
            
            'progress': () => this.test('Progress Bar', async () => {
                const feedback = this.manualControl.getFeedback();
                
                // Testar progress bar
                feedback.showProgress('Teste de Progresso', 'Simulando operação...', 0);
                
                let progress = 0;
                const interval = setInterval(() => {
                    progress += 20;
                    feedback.updateProgress(progress, `Progresso: ${progress}%`);
                    
                    if (progress >= 100) {
                        clearInterval(interval);
                        setTimeout(() => {
                            feedback.hideProgress();
                        }, 1000);
                    }
                }, 500);
                
                return true;
            })
        };
        
        if (testMap[testName]) {
            await testMap[testName]();
        } else {
            console.warn(`Teste '${testName}' não encontrado`);
        }
    }
}

// Função para executar testes manuais
function runManualControlTests() {
    new ManualControlTest();
}

// Função para testes específicos
function testFeedback() {
    if (window.manualControlTestInstance) {
        window.manualControlTestInstance.runSpecificTest('feedback');
    } else {
        console.warn('Instância de teste não encontrada. Execute runManualControlTests() primeiro.');
    }
}

function testLoading() {
    if (window.manualControlTestInstance) {
        window.manualControlTestInstance.runSpecificTest('loading');
    } else {
        console.warn('Instância de teste não encontrada. Execute runManualControlTests() primeiro.');
    }
}

function testProgress() {
    if (window.manualControlTestInstance) {
        window.manualControlTestInstance.runSpecificTest('progress');
    } else {
        console.warn('Instância de teste não encontrada. Execute runManualControlTests() primeiro.');
    }
}

// Auto-executar testes se solicitado via URL
if (window.location.search.includes('test=manual-control')) {
    document.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            window.manualControlTestInstance = new ManualControlTest();
        }, 2000); // Aguardar sistemas carregarem
    });
}

// Expor funções globalmente
window.runManualControlTests = runManualControlTests;
window.testFeedback = testFeedback;
window.testLoading = testLoading;
window.testProgress = testProgress;

console.log('🧪 Sistema de testes carregado. Use:');
console.log('   • runManualControlTests() - Executar todos os testes');
console.log('   • testFeedback() - Testar sistema de feedback');
console.log('   • testLoading() - Testar estados de loading');
console.log('   • testProgress() - Testar progress bars');
console.log('   • URL: ?test=manual-control - Auto-executar na inicialização');