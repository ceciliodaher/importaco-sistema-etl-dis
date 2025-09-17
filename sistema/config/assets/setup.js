/**
 * ================================================================================
 * SISTEMA ETL DE DI's - SETUP DE CONFIGURAÇÃO DO BANCO DE DADOS
 * JavaScript para interface de configuração de conexões
 * ================================================================================
 */

// Estado global da aplicação
let setupState = {
    isLoading: false,
    testResults: {},
    currentProfile: null,
    detectedEnvironments: [],
    availableProfiles: []
};

/**
 * Inicializar funcionalidades do setup
 */
function initConnectionSetup() {
    console.log('Inicializando setup de conexão...');
    
    // Configurar estado inicial
    setupState.currentProfile = window.setupConfig?.currentProfile || null;
    setupState.detectedEnvironments = window.setupConfig?.detectedEnvironments || [];
    setupState.availableProfiles = window.setupConfig?.profiles || [];
    
    // Configurar event listeners
    setupEventListeners();
    
    // Verificar status inicial
    checkInitialStatus();
}

/**
 * Configurar event listeners
 */
function setupEventListeners() {
    // Botões de teste de conexão
    document.querySelectorAll('.test-connection').forEach(button => {
        button.addEventListener('click', handleTestConnection);
    });
    
    // Botões de usar perfil
    document.querySelectorAll('.use-profile').forEach(button => {
        button.addEventListener('click', handleUseProfile);
    });
    
    // Botão de testar todos os perfis
    const testAllButton = document.getElementById('testAllConnections');
    if (testAllButton) {
        testAllButton.addEventListener('click', handleTestAllConnections);
    }
    
    // Botão de atualizar detecção
    const refreshButton = document.getElementById('refreshDetection');
    if (refreshButton) {
        refreshButton.addEventListener('click', handleRefreshDetection);
    }
    
    // Formulário de configuração customizada
    const customForm = document.getElementById('customConfigForm');
    if (customForm) {
        customForm.addEventListener('submit', handleSaveCustomProfile);
    }
    
    // Botão de testar conexão customizada
    const testCustomButton = document.getElementById('testCustomConnection');
    if (testCustomButton) {
        testCustomButton.addEventListener('click', handleTestCustomConnection);
    }
    
    // Botões de editar perfil
    document.querySelectorAll('.edit-profile').forEach(button => {
        button.addEventListener('click', handleEditProfile);
    });
    
    // Modal de confirmação
    setupModalListeners();
    
    // Auto-save do formulário customizado
    setupAutoSave();
}

/**
 * Configurar listeners dos modais
 */
function setupModalListeners() {
    const confirmModal = document.getElementById('confirmModal');
    const closeButton = document.getElementById('closeConfirmModal');
    const cancelButton = document.getElementById('cancelAction');
    
    if (closeButton) {
        closeButton.addEventListener('click', hideConfirmModal);
    }
    
    if (cancelButton) {
        cancelButton.addEventListener('click', hideConfirmModal);
    }
    
    // Fechar modal clicando no overlay
    if (confirmModal) {
        confirmModal.addEventListener('click', (e) => {
            if (e.target === confirmModal) {
                hideConfirmModal();
            }
        });
    }
}

/**
 * Configurar auto-save para formulário customizado
 */
function setupAutoSave() {
    const formFields = document.querySelectorAll('#customConfigForm input');
    
    formFields.forEach(field => {
        field.addEventListener('input', () => {
            // Salvar no localStorage
            const formData = getCustomFormData();
            localStorage.setItem('etl_custom_config', JSON.stringify(formData));
            
            // Limpar resultado de teste anterior quando alterar dados
            hideCustomTestResult();
            
            // Desabilitar botão de salvar até testar novamente
            const saveButton = document.getElementById('saveCustomProfile');
            if (saveButton) {
                saveButton.disabled = true;
            }
        });
    });
    
    // Restaurar dados salvos
    restoreCustomFormData();
}

/**
 * Verificar status inicial
 */
function checkInitialStatus() {
    if (setupState.currentProfile) {
        updateConnectionStatus('testing', 'Verificando conexão atual...');
        testConnection(setupState.currentProfile, true);
    } else {
        updateConnectionStatus('offline', 'Nenhum perfil ativo');
    }
}

/**
 * Testar conexão com um perfil
 */
async function testConnection(profileName, isCurrentCheck = false) {
    const resultElement = document.getElementById(`testResult-${profileName}`);
    
    try {
        if (!isCurrentCheck) {
            showLoading('Testando conexão...');
        }
        
        const response = await fetch('/sistema/config/ajax/test-connection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'test_profile',
                profile: profileName
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const testData = result.data;
            setupState.testResults[profileName] = testData;
            
            if (resultElement) {
                showTestResult(resultElement, testData);
            }
            
            if (isCurrentCheck) {
                updateConnectionStatus(
                    testData.success ? 'online' : 'offline',
                    testData.success ? 'Conectado' : testData.message
                );
            }
            
            if (!isCurrentCheck) {
                showMessage('success', `Teste concluído para ${profileName}`);
            }
        } else {
            throw new Error(result.message || 'Erro no teste de conexão');
        }
        
    } catch (error) {
        console.error('Erro ao testar conexão:', error);
        
        if (resultElement) {
            showTestResult(resultElement, {
                success: false,
                message: error.message,
                profile: profileName
            });
        }
        
        if (isCurrentCheck) {
            updateConnectionStatus('offline', 'Erro na conexão');
        }
        
        if (!isCurrentCheck) {
            showMessage('error', `Erro ao testar ${profileName}: ${error.message}`);
        }
    } finally {
        if (!isCurrentCheck) {
            hideLoading();
        }
    }
}

/**
 * Handler para teste de conexão
 */
function handleTestConnection(event) {
    const button = event.target.closest('button');
    const profileName = button.dataset.profile;
    
    if (profileName) {
        testConnection(profileName);
    }
}

/**
 * Handler para usar perfil
 */
async function handleUseProfile(event) {
    const button = event.target.closest('button');
    const profileName = button.dataset.profile;
    
    if (!profileName) return;
    
    // Confirmar se há um perfil ativo diferente
    if (setupState.currentProfile && setupState.currentProfile !== profileName) {
        const confirmed = await showConfirmModal(
            `Deseja trocar do perfil "${setupState.currentProfile}" para "${profileName}"?`
        );
        
        if (!confirmed) return;
    }
    
    try {
        showLoading('Alterando perfil...');
        
        const response = await fetch('/sistema/config/ajax/save-profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'switch_profile',
                profile: profileName,
                previous_profile: setupState.currentProfile
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            setupState.currentProfile = profileName;
            updateCurrentProfileDisplay(profileName);
            updateProfileCards();
            updateConnectionStatus('online', 'Perfil alterado com sucesso');
            
            showMessage('success', `Perfil alterado para: ${profileName}`);
        } else {
            throw new Error(result.message || 'Erro ao alterar perfil');
        }
        
    } catch (error) {
        console.error('Erro ao alterar perfil:', error);
        showMessage('error', `Erro ao alterar perfil: ${error.message}`);
    } finally {
        hideLoading();
    }
}

/**
 * Handler para testar todos os perfis
 */
async function handleTestAllConnections() {
    try {
        showLoading('Testando todas as conexões...');
        
        const response = await fetch('/sistema/config/ajax/test-connection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'test_all'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const testResults = result.data;
            setupState.testResults = { ...setupState.testResults, ...testResults };
            
            // Atualizar interface com resultados
            Object.keys(testResults).forEach(profileName => {
                const resultElement = document.getElementById(`testResult-${profileName}`);
                if (resultElement) {
                    showTestResult(resultElement, testResults[profileName]);
                }
            });
            
            showMessage('success', 'Teste de todas as conexões concluído');
        } else {
            throw new Error(result.message || 'Erro ao testar conexões');
        }
        
    } catch (error) {
        console.error('Erro ao testar todas as conexões:', error);
        showMessage('error', `Erro ao testar conexões: ${error.message}`);
    } finally {
        hideLoading();
    }
}

/**
 * Handler para atualizar detecção
 */
async function handleRefreshDetection() {
    try {
        showLoading('Atualizando detecção de ambientes...');
        
        const response = await fetch('/sistema/config/ajax/test-connection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'refresh_detection'
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            setupState.detectedEnvironments = result.data;
            updateEnvironmentsGrid();
            
            showMessage('success', 'Detecção de ambientes atualizada');
        } else {
            throw new Error(result.message || 'Erro ao atualizar detecção');
        }
        
    } catch (error) {
        console.error('Erro ao atualizar detecção:', error);
        showMessage('error', `Erro ao atualizar detecção: ${error.message}`);
    } finally {
        hideLoading();
    }
}

/**
 * Handler para testar conexão customizada
 */
async function handleTestCustomConnection() {
    const formData = getCustomFormData();
    
    if (!validateCustomForm(formData)) {
        showMessage('error', 'Preencha todos os campos obrigatórios');
        return;
    }
    
    try {
        showLoading('Testando conexão customizada...');
        
        const response = await fetch('/sistema/config/ajax/test-connection.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'test_custom',
                ...formData
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const testData = result.data;
            showCustomTestResult(testData);
            
            // Habilitar botão de salvar se teste foi bem-sucedido
            const saveButton = document.getElementById('saveCustomProfile');
            if (saveButton && testData.success) {
                saveButton.disabled = false;
            }
            
            showMessage('success', 'Teste de conexão customizada concluído');
        } else {
            throw new Error(result.message || 'Erro no teste de conexão');
        }
        
    } catch (error) {
        console.error('Erro ao testar conexão customizada:', error);
        showCustomTestResult({
            success: false,
            message: error.message
        });
        showMessage('error', `Erro ao testar conexão: ${error.message}`);
    } finally {
        hideLoading();
    }
}

/**
 * Handler para salvar perfil customizado
 */
async function handleSaveCustomProfile(event) {
    event.preventDefault();
    
    const formData = getCustomFormData();
    
    if (!validateCustomForm(formData)) {
        showMessage('error', 'Preencha todos os campos obrigatórios');
        return;
    }
    
    try {
        showLoading('Salvando perfil customizado...');
        
        const response = await fetch('/sistema/config/ajax/save-profile.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'save_custom_profile',
                ...formData
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            const profileData = result.data;
            
            // Limpar formulário
            clearCustomForm();
            hideCustomTestResult();
            
            // Limpar localStorage
            localStorage.removeItem('etl_custom_config');
            
            showMessage('success', `Perfil "${profileData.config.name}" salvo com sucesso`);
            
            // Recarregar página para mostrar novo perfil
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            throw new Error(result.message || 'Erro ao salvar perfil');
        }
        
    } catch (error) {
        console.error('Erro ao salvar perfil:', error);
        showMessage('error', `Erro ao salvar perfil: ${error.message}`);
    } finally {
        hideLoading();
    }
}

/**
 * Handler para editar perfil
 */
function handleEditProfile(event) {
    const button = event.target.closest('button');
    const profileName = button.dataset.profile;
    
    // TODO: Implementar modal de edição
    showMessage('info', `Edição de perfil em desenvolvimento: ${profileName}`);
}

/**
 * Obter dados do formulário customizado
 */
function getCustomFormData() {
    return {
        name: document.getElementById('customName')?.value.trim() || '',
        host: document.getElementById('customHost')?.value.trim() || '',
        port: parseInt(document.getElementById('customPort')?.value) || 3306,
        database: document.getElementById('customDatabase')?.value.trim() || '',
        username: document.getElementById('customUsername')?.value.trim() || '',
        password: document.getElementById('customPassword')?.value || ''
    };
}

/**
 * Validar formulário customizado
 */
function validateCustomForm(data) {
    const required = ['name', 'host', 'database', 'username'];
    
    for (const field of required) {
        if (!data[field]) {
            return false;
        }
    }
    
    if (data.port < 1 || data.port > 65535) {
        return false;
    }
    
    return true;
}

/**
 * Limpar formulário customizado
 */
function clearCustomForm() {
    document.getElementById('customName').value = '';
    document.getElementById('customHost').value = '';
    document.getElementById('customPort').value = '';
    document.getElementById('customDatabase').value = '';
    document.getElementById('customUsername').value = '';
    document.getElementById('customPassword').value = '';
    
    const saveButton = document.getElementById('saveCustomProfile');
    if (saveButton) {
        saveButton.disabled = true;
    }
}

/**
 * Restaurar dados do formulário do localStorage
 */
function restoreCustomFormData() {
    const savedData = localStorage.getItem('etl_custom_config');
    
    if (savedData) {
        try {
            const data = JSON.parse(savedData);
            
            document.getElementById('customName').value = data.name || '';
            document.getElementById('customHost').value = data.host || '';
            document.getElementById('customPort').value = data.port || '';
            document.getElementById('customDatabase').value = data.database || '';
            document.getElementById('customUsername').value = data.username || '';
            document.getElementById('customPassword').value = data.password || '';
        } catch (error) {
            console.warn('Erro ao restaurar dados do formulário:', error);
        }
    }
}

/**
 * Mostrar resultado do teste
 */
function showTestResult(element, testData) {
    element.style.display = 'block';
    element.className = `profile-test-result ${testData.success ? 'success' : 'error'}`;
    
    const icon = testData.success ? '✓' : '✗';
    const serverInfo = testData.server_version ? ` (${testData.server_version})` : '';
    
    element.innerHTML = `
        <strong>${icon} ${testData.message}</strong>
        ${serverInfo}
        ${testData.response_time ? `<br><small>Tempo de resposta: ${testData.response_time}</small>` : ''}
    `;
}

/**
 * Mostrar resultado do teste customizado
 */
function showCustomTestResult(testData) {
    const element = document.getElementById('customTestResult');
    if (!element) return;
    
    element.style.display = 'block';
    element.className = `custom-test-result ${testData.success ? 'success' : 'error'}`;
    
    const icon = testData.success ? '✓' : '✗';
    const serverInfo = testData.server_version ? ` (${testData.server_version})` : '';
    const dbStatus = testData.database_exists !== undefined ? 
        `<br><small>Database "${testData.config?.database}": ${testData.database_exists ? 'Existe' : 'Não encontrado'}</small>` : '';
    
    element.innerHTML = `
        <strong>${icon} ${testData.message}</strong>
        ${serverInfo}
        ${testData.response_time ? `<br><small>Tempo de resposta: ${testData.response_time}</small>` : ''}
        ${dbStatus}
    `;
}

/**
 * Ocultar resultado do teste customizado
 */
function hideCustomTestResult() {
    const element = document.getElementById('customTestResult');
    if (element) {
        element.style.display = 'none';
    }
}

/**
 * Atualizar status da conexão no header
 */
function updateConnectionStatus(status, text) {
    const statusIndicator = document.getElementById('connectionStatus');
    const statusText = document.getElementById('connectionStatusText');
    
    if (statusIndicator) {
        statusIndicator.className = `status-indicator ${status}`;
    }
    
    if (statusText) {
        statusText.textContent = text;
    }
}

/**
 * Atualizar exibição do perfil atual
 */
function updateCurrentProfileDisplay(profileName) {
    const element = document.getElementById('currentProfileName');
    if (element) {
        element.textContent = profileName;
    }
}

/**
 * Atualizar cards dos perfis
 */
function updateProfileCards() {
    document.querySelectorAll('.profile-card').forEach(card => {
        const profileName = card.dataset.profile;
        
        if (profileName === setupState.currentProfile) {
            card.classList.add('active');
            
            // Atualizar botões
            const useButton = card.querySelector('.use-profile');
            if (useButton) {
                useButton.style.display = 'none';
            }
        } else {
            card.classList.remove('active');
            
            // Mostrar botão de usar
            const useButton = card.querySelector('.use-profile');
            if (useButton) {
                useButton.style.display = 'inline-flex';
            }
        }
    });
}

/**
 * Atualizar grid de ambientes detectados
 */
function updateEnvironmentsGrid() {
    const grid = document.getElementById('environmentsGrid');
    if (!grid) return;
    
    if (Object.keys(setupState.detectedEnvironments).length === 0) {
        grid.innerHTML = `
            <div class="no-environments">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none">
                    <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                </svg>
                <p>Nenhum ambiente foi detectado automaticamente</p>
                <span class="note">Isso é normal se você não tiver MySQL instalado localmente</span>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    Object.entries(setupState.detectedEnvironments).forEach(([env, config]) => {
        html += `
            <div class="environment-card" data-environment="${env}">
                <div class="env-header">
                    <div class="env-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                            <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
                        </svg>
                    </div>
                    <div class="env-info">
                        <h3>${config.name}</h3>
                        <span class="env-type">${config.type}</span>
                    </div>
                    <div class="env-status detected">Detectado</div>
                </div>
                <div class="env-actions">
                    <button class="btn btn-primary btn-sm test-connection" data-profile="${config.profile}">
                        Testar Conexão
                    </button>
                    <button class="btn btn-secondary btn-sm use-profile" data-profile="${config.profile}">
                        Usar Este Perfil
                    </button>
                </div>
            </div>
        `;
    });
    
    grid.innerHTML = html;
    
    // Reconfigurar event listeners
    grid.querySelectorAll('.test-connection').forEach(button => {
        button.addEventListener('click', handleTestConnection);
    });
    
    grid.querySelectorAll('.use-profile').forEach(button => {
        button.addEventListener('click', handleUseProfile);
    });
}

/**
 * Mostrar modal de confirmação
 */
function showConfirmModal(message) {
    return new Promise((resolve) => {
        const modal = document.getElementById('confirmModal');
        const messageElement = document.getElementById('confirmMessage');
        const confirmButton = document.getElementById('confirmAction');
        
        if (!modal || !messageElement || !confirmButton) {
            resolve(false);
            return;
        }
        
        messageElement.textContent = message;
        modal.style.display = 'flex';
        
        // Configurar handlers
        const handleConfirm = () => {
            cleanup();
            resolve(true);
        };
        
        const handleCancel = () => {
            cleanup();
            resolve(false);
        };
        
        const cleanup = () => {
            modal.style.display = 'none';
            confirmButton.removeEventListener('click', handleConfirm);
            document.getElementById('cancelAction').removeEventListener('click', handleCancel);
        };
        
        confirmButton.addEventListener('click', handleConfirm);
        document.getElementById('cancelAction').addEventListener('click', handleCancel);
    });
}

/**
 * Ocultar modal de confirmação
 */
function hideConfirmModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Mostrar loading overlay
 */
function showLoading(message = 'Carregando...') {
    const overlay = document.getElementById('loadingOverlay');
    const text = overlay?.querySelector('.loading-text');
    
    if (overlay) {
        overlay.style.display = 'flex';
        setupState.isLoading = true;
    }
    
    if (text) {
        text.textContent = message;
    }
}

/**
 * Ocultar loading overlay
 */
function hideLoading() {
    const overlay = document.getElementById('loadingOverlay');
    
    if (overlay) {
        overlay.style.display = 'none';
        setupState.isLoading = false;
    }
}

/**
 * Mostrar mensagem de feedback
 */
function showMessage(type, message, duration = 5000) {
    const container = document.getElementById('feedbackContainer');
    if (!container) return;
    
    const messageElement = document.createElement('div');
    messageElement.className = `feedback-message ${type}`;
    messageElement.innerHTML = `
        <strong>${getMessageIcon(type)}</strong>
        <span>${message}</span>
    `;
    
    container.appendChild(messageElement);
    
    // Auto-remover após duration
    setTimeout(() => {
        if (messageElement.parentNode) {
            messageElement.remove();
        }
    }, duration);
}

/**
 * Obter ícone para tipo de mensagem
 */
function getMessageIcon(type) {
    const icons = {
        success: '✓',
        error: '✗',
        warning: '⚠',
        info: 'ℹ'
    };
    
    return icons[type] || 'ℹ';
}

// Expor funções globalmente
window.connectionSetup = {
    init: initConnectionSetup,
    testConnection: testConnection,
    showMessage: showMessage,
    showLoading: showLoading,
    hideLoading: hideLoading
};

// Auto-inicializar se DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initConnectionSetup);
} else {
    initConnectionSetup();
}