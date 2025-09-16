/**
 * ================================================================================
 * UPLOAD MANAGER ADVANCED - SISTEMA ETL DI's COM FUNCIONALIDADES AVANÇADAS
 * Features: Queue, Retry, Chunked Upload, WebSocket, XML Validation
 * Cores de Feedback: Vermelho (erro), Amarelo (processando), Verde (sucesso), Azul (info)
 * ================================================================================
 */

class UploadManager {
    constructor() {
        this.uploadZone = document.getElementById('uploadZone');
        this.fileInput = document.getElementById('fileInput');
        this.uploadProgress = document.getElementById('uploadProgress');
        this.progressFill = document.getElementById('progressFill');
        this.progressText = document.getElementById('progressText');
        this.progressPercent = document.getElementById('progressPercent');
        this.fileList = document.getElementById('fileList');
        this.filesContainer = document.getElementById('filesContainer');
        this.processFilesBtn = document.getElementById('processFiles');
        this.clearFilesBtn = document.getElementById('clearFiles');
        this.feedbackContainer = document.getElementById('feedbackContainer');
        
        // Advanced properties
        this.selectedFiles = [];
        this.uploadQueue = [];
        this.maxFileSize = 10 * 1024 * 1024; // 10MB
        this.chunkSize = 5 * 1024 * 1024; // 5MB chunks
        this.allowedTypes = ['.xml'];
        this.isProcessing = false;
        this.maxRetries = 3;
        this.retryDelay = 1000; // 1 second
        this.duplicateFiles = new Map();
        this.xmlValidator = null;
        
        // WebSocket connection
        this.wsConnection = null;
        this.wsReconnectAttempts = 0;
        this.maxWsReconnectAttempts = 5;
        
        this.init();
    }

    async init() {
        this.setupEventListeners();
        await this.initializeXmlValidator();
        this.initializeWebSocket();
        this.showFeedback('Sistema de upload avançado inicializado', 'info');
    }

    async initializeXmlValidator() {
        try {
            // Import XML validator if available
            if (window.XMLValidator) {
                this.xmlValidator = new window.XMLValidator();
            }
        } catch (error) {
            console.warn('XML Validator não disponível:', error.message);
        }
    }

    initializeWebSocket() {
        try {
            const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
            const wsUrl = `${protocol}//${location.host}/ws/upload-status`;
            
            this.wsConnection = new WebSocket(wsUrl);
            
            this.wsConnection.onopen = () => {
                this.wsReconnectAttempts = 0;
                this.showFeedback('Conexão em tempo real estabelecida', 'success', 2000);
            };
            
            this.wsConnection.onmessage = (event) => {
                this.handleWebSocketMessage(JSON.parse(event.data));
            };
            
            this.wsConnection.onclose = () => {
                this.handleWebSocketReconnect();
            };
            
            this.wsConnection.onerror = (error) => {
                console.warn('WebSocket error:', error);
            };
        } catch (error) {
            console.warn('WebSocket não disponível:', error.message);
        }
    }

    handleWebSocketMessage(data) {
        switch (data.type) {
            case 'upload_progress':
                this.updateFileProgress(data.fileId, data.progress);
                break;
            case 'upload_complete':
                this.handleUploadComplete(data.fileId, data.result);
                break;
            case 'system_status':
                this.updateSystemStatus(data.status);
                break;
            case 'duplicate_detected':
                this.handleDuplicateFile(data.filename, data.existingData);
                break;
        }
    }

    handleWebSocketReconnect() {
        if (this.wsReconnectAttempts < this.maxWsReconnectAttempts) {
            setTimeout(() => {
                this.wsReconnectAttempts++;
                this.initializeWebSocket();
            }, 2000 * this.wsReconnectAttempts);
        }
    }

    setupEventListeners() {
        // Drag and drop events
        this.uploadZone.addEventListener('dragover', (e) => this.handleDragOver(e));
        this.uploadZone.addEventListener('dragleave', (e) => this.handleDragLeave(e));
        this.uploadZone.addEventListener('drop', (e) => this.handleDrop(e));
        
        // Click para selecionar arquivos
        this.uploadZone.addEventListener('click', () => this.fileInput.click());
        this.fileInput.addEventListener('change', (e) => this.handleFileSelect(e));
        
        // Botões de ação
        this.processFilesBtn.addEventListener('click', () => this.processFiles());
        this.clearFilesBtn.addEventListener('click', () => this.clearFiles());
        
        // Prevenir comportamento padrão do navegador
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            this.uploadZone.addEventListener(eventName, this.preventDefaults);
            document.body.addEventListener(eventName, this.preventDefaults);
        });
    }

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    handleDragOver(e) {
        this.preventDefaults(e);
        this.uploadZone.classList.add('dragover');
    }

    handleDragLeave(e) {
        this.preventDefaults(e);
        this.uploadZone.classList.remove('dragover');
    }

    handleDrop(e) {
        this.preventDefaults(e);
        this.uploadZone.classList.remove('dragover');
        
        const files = Array.from(e.dataTransfer.files);
        this.addFiles(files);
    }

    handleFileSelect(e) {
        const files = Array.from(e.target.files);
        this.addFiles(files);
    }

    async addFiles(files) {
        const validFiles = [];
        const errors = [];
        const duplicates = [];

        // Process files with loading feedback
        this.showFeedback('Validando arquivos...', 'info', 1000);
        
        for (const file of files) {
            try {
                const validation = await this.validateFile(file);
                
                if (validation.valid) {
                    // Check for duplicates with enhanced detection
                    const duplicate = await this.checkDuplicate(file);
                    
                    if (duplicate.isDuplicate) {
                        duplicates.push({
                            file,
                            existing: duplicate.existingFile,
                            reason: duplicate.reason
                        });
                    } else {
                        // Generate unique file ID
                        file.uploadId = this.generateFileId(file);
                        file.status = 'pending';
                        file.retryCount = 0;
                        file.chunks = this.calculateChunks(file);
                        
                        validFiles.push(file);
                    }
                } else {
                    errors.push(`${file.name}: ${validation.error}`);
                }
            } catch (error) {
                errors.push(`${file.name}: Erro na validação - ${error.message}`);
            }
        }

        // Handle duplicates with user choice
        if (duplicates.length > 0) {
            const allowDuplicates = await this.handleDuplicateFiles(duplicates);
            if (allowDuplicates) {
                duplicates.forEach(dup => {
                    dup.file.uploadId = this.generateFileId(dup.file);
                    dup.file.status = 'pending';
                    dup.file.retryCount = 0;
                    dup.file.chunks = this.calculateChunks(dup.file);
                    dup.file.isDuplicate = true;
                    validFiles.push(dup.file);
                });
            }
        }

        if (validFiles.length > 0) {
            this.selectedFiles.push(...validFiles);
            this.renderFileList();
            this.showFeedback(
                `${validFiles.length} arquivo(s) validado(s) e adicionado(s)`, 
                'success'
            );
        }

        if (errors.length > 0) {
            errors.forEach(error => {
                this.showFeedback(error, 'error');
            });
        }

        // Clear input
        this.fileInput.value = '';
    }

    async checkDuplicate(file) {
        // Check in current selection
        const inSelection = this.selectedFiles.find(f => 
            f.name === file.name && f.size === file.size
        );
        
        if (inSelection) {
            return {
                isDuplicate: true,
                existingFile: inSelection,
                reason: 'Arquivo já selecionado nesta sessão'
            };
        }
        
        // Check in server (via API call)
        try {
            const response = await fetch('api/upload/check-duplicate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    filename: file.name,
                    size: file.size,
                    lastModified: file.lastModified
                })
            });
            
            if (response.ok) {
                const result = await response.json();
                if (result.isDuplicate) {
                    return {
                        isDuplicate: true,
                        existingFile: result.existingFile,
                        reason: 'DI já processada no sistema'
                    };
                }
            }
        } catch (error) {
            console.warn('Erro ao verificar duplicatas no servidor:', error);
        }
        
        return { isDuplicate: false };
    }

    async handleDuplicateFiles(duplicates) {
        return new Promise(resolve => {
            const modal = this.createDuplicateModal(duplicates, resolve);
            document.body.appendChild(modal);
        });
    }

    createDuplicateModal(duplicates, resolve) {
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.innerHTML = `
            <div class="modal-content duplicate-modal">
                <h3>Arquivos Duplicados Detectados</h3>
                <p>Os seguintes arquivos podem ser duplicatas:</p>
                <div class="duplicate-list">
                    ${duplicates.map(dup => `
                        <div class="duplicate-item">
                            <strong>${dup.file.name}</strong>
                            <small>${dup.reason}</small>
                        </div>
                    `).join('')}
                </div>
                <div class="modal-actions">
                    <button class="btn btn-secondary" onclick="this.parentElement.parentElement.parentElement.resolve(false)">Cancelar</button>
                    <button class="btn btn-primary" onclick="this.parentElement.parentElement.parentElement.resolve(true)">Processar Mesmo Assim</button>
                </div>
            </div>
        `;
        
        modal.resolve = (result) => {
            document.body.removeChild(modal);
            resolve(result);
        };
        
        return modal;
    }

    generateFileId(file) {
        return `file_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
    }

    calculateChunks(file) {
        if (file.size <= this.chunkSize) {
            return 1;
        }
        return Math.ceil(file.size / this.chunkSize);
    }

    async validateFile(file) {
        // Verificar extensão
        const extension = '.' + file.name.split('.').pop().toLowerCase();
        if (!this.allowedTypes.includes(extension)) {
            return {
                valid: false,
                error: 'Apenas arquivos XML são permitidos'
            };
        }

        // Verificar tamanho
        if (file.size > this.maxFileSize) {
            return {
                valid: false,
                error: `Arquivo muito grande (máximo ${this.formatFileSize(this.maxFileSize)})`
            };
        }

        // Verificar se é um arquivo válido
        if (file.size === 0) {
            return {
                valid: false,
                error: 'Arquivo está vazio'
            };
        }

        // Validação XML estrutural
        try {
            const xmlContent = await this.readFileContent(file, 10000); // Primeiros 10KB
            const xmlValidation = this.validateXMLStructure(xmlContent);
            
            if (!xmlValidation.valid) {
                return xmlValidation;
            }
            
            // Validação específica para DI brasileira
            if (this.xmlValidator) {
                const diValidation = await this.xmlValidator.validateDI(xmlContent);
                if (!diValidation.valid) {
                    return diValidation;
                }
            }
        } catch (error) {
            return {
                valid: false,
                error: `Erro ao validar XML: ${error.message}`
            };
        }

        return { valid: true };
    }

    readFileContent(file, maxBytes = null) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = (e) => resolve(e.target.result);
            reader.onerror = (e) => reject(new Error('Erro ao ler arquivo'));
            
            if (maxBytes && file.size > maxBytes) {
                const blob = file.slice(0, maxBytes);
                reader.readAsText(blob);
            } else {
                reader.readAsText(file);
            }
        });
    }

    validateXMLStructure(xmlContent) {
        try {
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(xmlContent, 'text/xml');
            
            // Verificar se há erros de parsing
            const parserError = xmlDoc.querySelector('parsererror');
            if (parserError) {
                return {
                    valid: false,
                    error: 'XML mal formado: ' + parserError.textContent
                };
            }
            
            // Verificar se é uma DI brasileira (estrutura básica)
            const isDI = xmlContent.includes('<declaracaoImportacao>') ||
                        xmlContent.includes('<DI>') ||
                        xmlContent.includes('numero_di') ||
                        xmlContent.includes('declaracao_importacao');
            
            if (!isDI) {
                return {
                    valid: false,
                    error: 'XML não parece ser uma Declaração de Importação brasileira'
                };
            }
            
            return { valid: true };
        } catch (error) {
            return {
                valid: false,
                error: 'Erro ao validar estrutura XML: ' + error.message
            };
        }
    }

    renderFileList() {
        if (this.selectedFiles.length === 0) {
            this.fileList.style.display = 'none';
            return;
        }

        this.fileList.style.display = 'block';
        this.filesContainer.innerHTML = '';

        this.selectedFiles.forEach((file, index) => {
            const fileElement = this.createFileElement(file, index);
            this.filesContainer.appendChild(fileElement);
        });

        // Atualizar botão processar
        this.processFilesBtn.textContent = `Processar ${this.selectedFiles.length} Arquivo(s)`;
        this.processFilesBtn.disabled = this.isProcessing;
    }

    createFileElement(file, index) {
        const fileDiv = document.createElement('div');
        fileDiv.className = 'file-item';
        fileDiv.setAttribute('data-file-id', file.uploadId);
        
        // Enhanced file info with validation status
        const validationIcon = file.isDuplicate ? 
            '<span class="duplicate-badge" title="Arquivo duplicado">DUP</span>' : '';
        
        const chunkedInfo = file.chunks > 1 ? 
            `<span class="chunked-info" title="Será enviado em ${file.chunks} partes">📦 ${file.chunks}</span>` : '';
        
        fileDiv.innerHTML = `
            <div class="file-info">
                <div class="file-icon">XML</div>
                <div class="file-details">
                    <h4>${file.name} ${validationIcon}</h4>
                    <span>
                        ${this.formatFileSize(file.size)} • 
                        ${this.formatDate(file.lastModified)}
                        ${chunkedInfo}
                    </span>
                </div>
            </div>
            <div class="file-progress">
                <div class="progress-ring">
                    <div class="progress-circle" data-progress="0"></div>
                    <span class="progress-text">0%</span>
                </div>
            </div>
            <div class="file-status pending">Aguardando</div>
            <button class="btn-remove" onclick="uploadManager.removeFile(${index})" title="Remover arquivo">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
                    <path d="M6 6L18 18M6 18L18 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </button>
        `;

        return fileDiv;
    }

    getFileElement(fileId) {
        return this.filesContainer.querySelector(`[data-file-id="${fileId}"]`);
    }

    updateFileProgress(fileId, progress) {
        const fileElement = this.getFileElement(fileId);
        if (!fileElement) return;
        
        const progressCircle = fileElement.querySelector('.progress-circle');
        const progressText = fileElement.querySelector('.progress-text');
        
        if (progressCircle && progressText) {
            progressCircle.setAttribute('data-progress', Math.round(progress));
            progressText.textContent = `${Math.round(progress)}%`;
            
            // Update circle stroke
            const circumference = 2 * Math.PI * 12; // radius = 12
            const strokeDasharray = circumference;
            const strokeDashoffset = circumference - (progress / 100) * circumference;
            
            progressCircle.style.strokeDasharray = strokeDasharray;
            progressCircle.style.strokeDashoffset = strokeDashoffset;
        }
    }

    handleUploadComplete(fileId, result) {
        const fileElement = this.getFileElement(fileId);
        if (!fileElement) return;
        
        const statusElement = fileElement.querySelector('.file-status');
        
        if (result.success) {
            statusElement.textContent = 'Concluído';
            statusElement.className = 'file-status success';
            this.updateFileProgress(fileId, 100);
        } else {
            statusElement.textContent = 'Erro';
            statusElement.className = 'file-status error';
        }
    }

    handleDuplicateFile(filename, existingData) {
        this.showFeedback(
            `DI duplicada detectada: ${filename}. Dados existentes: DI ${existingData.numero_di}`, 
            'warning',
            8000
        );
    }

    updateSystemStatus(status) {
        // Update system status indicator if available
        const statusIndicator = document.querySelector('#system-status');
        if (statusIndicator) {
            statusIndicator.className = `system-status ${status}`;
            statusIndicator.textContent = status.toUpperCase();
        }
    }

    removeFile(index) {
        this.selectedFiles.splice(index, 1);
        this.renderFileList();
        this.showFeedback('Arquivo removido da lista', 'info');
    }

    clearFiles() {
        this.selectedFiles = [];
        this.renderFileList();
        this.hideProgress();
        this.showFeedback('Lista de arquivos limpa', 'info');
    }

    async processFiles() {
        if (this.selectedFiles.length === 0) {
            this.showFeedback('Nenhum arquivo selecionado para processar', 'warning');
            return;
        }

        if (this.isProcessing) {
            this.showFeedback('Processamento já em andamento', 'warning');
            return;
        }

        this.isProcessing = true;
        this.processFilesBtn.disabled = true;
        this.showProgress();

        try {
            // Initialize upload queue
            this.uploadQueue = [...this.selectedFiles];
            const totalFiles = this.uploadQueue.length;
            let processedFiles = 0;
            let successCount = 0;
            let errorCount = 0;

            // Process queue with concurrent uploads (max 3 simultaneous)
            const maxConcurrent = 3;
            const processingPromises = [];

            while (this.uploadQueue.length > 0 || processingPromises.length > 0) {
                // Start new uploads if queue has files and we're under limit
                while (this.uploadQueue.length > 0 && processingPromises.length < maxConcurrent) {
                    const file = this.uploadQueue.shift();
                    const uploadPromise = this.processFileWithRetry(file, processedFiles, totalFiles)
                        .then(result => {
                            processedFiles++;
                            if (result.success) {
                                successCount++;
                            } else {
                                errorCount++;
                            }
                            return result;
                        })
                        .catch(error => {
                            processedFiles++;
                            errorCount++;
                            return { success: false, error: error.message };
                        });
                    
                    processingPromises.push(uploadPromise);
                }

                // Wait for at least one upload to complete
                if (processingPromises.length > 0) {
                    const completedIndex = await Promise.race(
                        processingPromises.map((promise, index) => 
                            promise.then(result => ({ index, result }))
                        )
                    );
                    
                    processingPromises.splice(completedIndex.index, 1);
                    
                    // Update overall progress
                    const overallProgress = (processedFiles / totalFiles) * 100;
                    this.updateProgress(
                        overallProgress, 
                        `Processados: ${processedFiles}/${totalFiles} | Sucessos: ${successCount} | Erros: ${errorCount}`
                    );
                }
            }

            // Final feedback
            if (errorCount === 0) {
                this.showFeedback(
                    `Todos os ${successCount} arquivos processados com sucesso!`, 
                    'success'
                );
            } else if (successCount > 0) {
                this.showFeedback(
                    `Processamento concluído: ${successCount} sucessos, ${errorCount} erros`, 
                    'warning'
                );
            } else {
                this.showFeedback(
                    `Falha no processamento: ${errorCount} erros`, 
                    'error'
                );
            }
            
        } catch (error) {
            this.showFeedback(`Erro crítico no processamento: ${error.message}`, 'error');
        } finally {
            this.isProcessing = false;
            this.processFilesBtn.disabled = false;
            this.hideProgress();
            
            // Refresh dashboard stats after processing
            setTimeout(() => {
                this.refreshStats();
            }, 2000);
        }
    }

    async processFileWithRetry(file, fileIndex, totalFiles) {
        const fileElement = this.getFileElement(file.uploadId);
        const statusElement = fileElement?.querySelector('.file-status');
        
        let lastError = null;
        
        for (let attempt = 0; attempt <= this.maxRetries; attempt++) {
            try {
                // Update status
                if (statusElement) {
                    const retryText = attempt > 0 ? ` (Tentativa ${attempt + 1})` : '';
                    statusElement.textContent = `Processando${retryText}`;
                    statusElement.className = 'file-status processing';
                }
                
                // Add individual file progress if supported
                this.updateFileProgress(file.uploadId, 0);
                
                const result = await this.uploadFileAdvanced(file, fileIndex, totalFiles);
                
                if (result.success) {
                    if (statusElement) {
                        statusElement.textContent = 'Concluído';
                        statusElement.className = 'file-status success';
                    }
                    this.updateFileProgress(file.uploadId, 100);
                    this.showFeedback(`${file.name} processado com sucesso`, 'success', 3000);
                    return result;
                } else {
                    throw new Error(result.error || 'Erro desconhecido');
                }
            } catch (error) {
                lastError = error;
                file.retryCount = attempt + 1;
                
                if (attempt < this.maxRetries) {
                    // Show retry feedback
                    this.showFeedback(
                        `Erro em ${file.name}, tentando novamente... (${attempt + 1}/${this.maxRetries})`, 
                        'warning',
                        2000
                    );
                    
                    // Wait before retry with exponential backoff
                    await this.sleep(this.retryDelay * Math.pow(2, attempt));
                } else {
                    // Final failure
                    if (statusElement) {
                        statusElement.textContent = `Erro (${this.maxRetries + 1} tentativas)`;
                        statusElement.className = 'file-status error';
                    }
                    this.showFeedback(
                        `Falha definitiva em ${file.name}: ${error.message}`, 
                        'error'
                    );
                }
            }
        }
        
        return { 
            success: false, 
            error: lastError?.message || 'Falha após todas as tentativas',
            retryCount: file.retryCount
        };
    }

    async uploadFileAdvanced(file, fileIndex, totalFiles) {
        // Check if file needs chunked upload
        if (file.size > this.chunkSize) {
            return await this.uploadFileChunked(file, fileIndex, totalFiles);
        } else {
            return await this.uploadFileSingle(file, fileIndex, totalFiles);
        }
    }

    async uploadFileSingle(file, fileIndex, totalFiles) {
        return new Promise((resolve, reject) => {
            const formData = new FormData();
            formData.append('xml_file', file);
            formData.append('action', 'upload_xml');
            formData.append('file_id', file.uploadId);
            formData.append('is_duplicate', file.isDuplicate || false);

            const xhr = new XMLHttpRequest();
            
            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const percent = (e.loaded / e.total) * 100;
                    this.updateFileProgress(file.uploadId, percent);
                }
            };

            xhr.onload = () => {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        reject(new Error('Resposta inválida do servidor'));
                    }
                } else {
                    reject(new Error(`Erro HTTP ${xhr.status}: ${xhr.statusText}`));
                }
            };

            xhr.onerror = () => {
                reject(new Error('Erro de conexão com o servidor'));
            };

            xhr.ontimeout = () => {
                reject(new Error('Timeout na requisição (tempo limite excedido)'));
            };

            xhr.timeout = 120000; // 2 minutes
            xhr.open('POST', 'api/upload/process.php');
            xhr.send(formData);
        });
    }

    async uploadFileChunked(file, fileIndex, totalFiles) {
        const totalChunks = Math.ceil(file.size / this.chunkSize);
        const uploadId = file.uploadId;
        
        this.showFeedback(
            `Iniciando upload em ${totalChunks} partes: ${file.name}`, 
            'info', 
            3000
        );

        try {
            // Initialize chunked upload session
            const initResponse = await fetch('api/upload/init-chunked.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    filename: file.name,
                    filesize: file.size,
                    total_chunks: totalChunks,
                    upload_id: uploadId
                })
            });

            if (!initResponse.ok) {
                throw new Error('Falha ao inicializar upload em partes');
            }

            const { session_id } = await initResponse.json();

            // Upload chunks sequentially
            for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                const start = chunkIndex * this.chunkSize;
                const end = Math.min(start + this.chunkSize, file.size);
                const chunk = file.slice(start, end);

                const chunkFormData = new FormData();
                chunkFormData.append('chunk', chunk);
                chunkFormData.append('session_id', session_id);
                chunkFormData.append('chunk_index', chunkIndex);
                chunkFormData.append('total_chunks', totalChunks);

                const chunkResponse = await fetch('api/upload/chunk.php', {
                    method: 'POST',
                    body: chunkFormData
                });

                if (!chunkResponse.ok) {
                    throw new Error(`Falha no upload da parte ${chunkIndex + 1}/${totalChunks}`);
                }

                // Update progress
                const chunkProgress = ((chunkIndex + 1) / totalChunks) * 100;
                this.updateFileProgress(uploadId, chunkProgress);
            }

            // Finalize chunked upload
            const finalizeResponse = await fetch('api/upload/finalize-chunked.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    session_id,
                    filename: file.name,
                    upload_id: uploadId
                })
            });

            if (!finalizeResponse.ok) {
                throw new Error('Falha ao finalizar upload em partes');
            }

            return await finalizeResponse.json();
            
        } catch (error) {
            // Clean up failed chunked upload
            try {
                await fetch('api/upload/cleanup-chunked.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ upload_id: uploadId })
                });
            } catch (cleanupError) {
                console.warn('Erro na limpeza do upload falhado:', cleanupError);
            }
            
            throw error;
        }
    }

    // Legacy method for compatibility
    async uploadFile(file) {
        return this.uploadFileSingle(file, 0, 1);
    }

    showProgress() {
        this.uploadProgress.style.display = 'block';
        this.updateProgress(0, 'Iniciando processamento...');
    }

    updateProgress(percent, text) {
        this.progressFill.style.width = `${percent}%`;
        this.progressPercent.textContent = `${Math.round(percent)}%`;
        this.progressText.textContent = text;
    }

    hideProgress() {
        setTimeout(() => {
            this.uploadProgress.style.display = 'none';
        }, 1000);
    }

    showFeedback(message, type = 'info', duration = 5000) {
        const feedbackDiv = document.createElement('div');
        feedbackDiv.className = `feedback-message ${type}`;
        
        // Ícone baseado no tipo
        const icons = {
            success: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M9 12L11 14L15 10M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
            </svg>`,
            error: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M12 8V12M12 16H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
            </svg>`,
            warning: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M12 9V13M12 17H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
            </svg>`,
            info: `<svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <path d="M13 16H12V12H11M12 8H12.01M21 12C21 16.9706 16.9706 21 12 21C7.02944 21 3 16.9706 3 12C3 7.02944 7.02944 3 12 3C16.9706 3 21 7.02944 21 12Z" stroke="currentColor" stroke-width="2"/>
            </svg>`
        };

        feedbackDiv.innerHTML = `
            ${icons[type] || icons.info}
            <span>${message}</span>
        `;

        // Adicionar à tela
        this.feedbackContainer.appendChild(feedbackDiv);

        // Remover após timeout
        setTimeout(() => {
            feedbackDiv.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (feedbackDiv.parentNode) {
                    feedbackDiv.parentNode.removeChild(feedbackDiv);
                }
            }, 300);
        }, duration);

        // Permitir remoção por clique
        feedbackDiv.addEventListener('click', () => {
            feedbackDiv.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => {
                if (feedbackDiv.parentNode) {
                    feedbackDiv.parentNode.removeChild(feedbackDiv);
                }
            }, 300);
        });
    }

    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    formatDate(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleDateString('pt-BR') + ' ' + date.toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    sleep(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    // Método público para recarregar estatísticas
    async refreshStats() {
        try {
            const response = await fetch('api/dashboard/stats.php');
            const data = await response.json();
            
            if (data.success) {
                // Atualizar cards de estatísticas
                this.updateStatsCards(data.stats);
                this.showFeedback('Estatísticas atualizadas', 'success', 2000);
            }
        } catch (error) {
            this.showFeedback('Erro ao atualizar estatísticas', 'error');
        }
    }

    updateStatsCards(stats) {
        // Implementar atualização dos cards de estatísticas
        // Por enquanto, apenas log para debug
        console.log('Atualizando estatísticas:', stats);
    }
}

// Adicionar animação de saída para feedback messages
const style = document.createElement('style');
style.textContent = `
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .btn-remove {
        background: none;
        border: none;
        color: #999;
        cursor: pointer;
        padding: 0.5rem;
        border-radius: 50%;
        transition: all 0.2s ease;
    }
    
    .btn-remove:hover {
        background: #ff002d;
        color: white;
        transform: scale(1.1);
    }
`;
document.head.appendChild(style);

// Inicializar o gerenciador de upload quando o DOM estiver pronto
let uploadManager;

document.addEventListener('DOMContentLoaded', function() {
    uploadManager = new UploadManager();
    
    // Configurar refresh automático das estatísticas
    setInterval(() => {
        if (!uploadManager.isProcessing) {
            uploadManager.refreshStats();
        }
    }, 30000); // 30 segundos
});

// Exportar para uso global
window.UploadManager = UploadManager;