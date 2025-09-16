/**
 * ================================================================================
 * WEBSOCKET MANAGER - CONEXÃO EM TEMPO REAL PARA SISTEMA ETL DI's
 * Features: Reconexão Automática, EventSource Fallback, Heartbeat, Queue de Mensagens
 * Cores de Feedback: Vermelho (erro), Amarelo (processando), Verde (sucesso), Azul (info)
 * ================================================================================
 */

class WebSocketManager {
    constructor(options = {}) {
        // Connection settings
        this.options = {
            url: options.url || this.generateWebSocketURL(),
            protocols: options.protocols || [],
            reconnectInterval: options.reconnectInterval || 3000,
            maxReconnectAttempts: options.maxReconnectAttempts || 10,
            heartbeatInterval: options.heartbeatInterval || 30000,
            messageQueueSize: options.messageQueueSize || 100,
            enableEventSourceFallback: options.enableEventSourceFallback !== false,
            debug: options.debug || false,
            ...options
        };
        
        // Connection state
        this.ws = null;
        this.eventSource = null;
        this.isConnected = false;
        this.reconnectAttempts = 0;
        this.reconnectTimer = null;
        this.heartbeatTimer = null;
        this.lastHeartbeat = null;
        this.connectionType = null; // 'websocket' or 'eventsource'
        
        // Message handling
        this.messageQueue = [];
        this.pendingMessages = new Map();
        this.messageId = 0;
        this.eventHandlers = new Map();
        this.onceHandlers = new Map();
        
        // Statistics
        this.stats = {
            messagesReceived: 0,
            messagesSent: 0,
            reconnections: 0,
            connectionTime: null,
            lastActivity: null,
            averageLatency: 0,
            latencyMeasurements: []
        };
        
        this.init();
    }

    init() {
        this.setupGlobalErrorHandlers();
        this.connectWithFallback();
        
        // Setup cleanup on page unload
        window.addEventListener('beforeunload', () => {
            this.cleanup();
        });
        
        // Handle visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.handlePageVisible();
            } else {
                this.handlePageHidden();
            }
        });
        
        // Handle online/offline status
        window.addEventListener('online', () => {
            this.handleOnline();
        });
        
        window.addEventListener('offline', () => {
            this.handleOffline();
        });
    }

    async connectWithFallback() {
        try {
            await this.connectWebSocket();
        } catch (error) {
            this.log('WebSocket connection failed, trying EventSource fallback', error);
            
            if (this.options.enableEventSourceFallback) {
                this.connectEventSource();
            } else {
                throw error;
            }
        }
    }

    async connectWebSocket() {
        return new Promise((resolve, reject) => {
            try {
                this.log('Connecting to WebSocket:', this.options.url);
                
                this.ws = new WebSocket(this.options.url, this.options.protocols);
                this.connectionType = 'websocket';
                
                // Connection opened
                this.ws.onopen = (event) => {
                    this.handleConnectionOpen('websocket');
                    resolve(event);
                };
                
                // Message received
                this.ws.onmessage = (event) => {
                    this.handleMessage(event.data);
                };
                
                // Connection closed
                this.ws.onclose = (event) => {
                    this.handleConnectionClose(event);
                };
                
                // Connection error
                this.ws.onerror = (error) => {
                    this.log('WebSocket error:', error);
                    this.emit('error', { type: 'websocket', error, connectionType: 'websocket' });
                    reject(error);
                };
                
                // Connection timeout
                setTimeout(() => {
                    if (this.ws.readyState === WebSocket.CONNECTING) {
                        this.ws.close();
                        reject(new Error('WebSocket connection timeout'));
                    }
                }, 10000);
                
            } catch (error) {
                this.log('WebSocket creation failed:', error);
                reject(error);
            }
        });
    }

    connectEventSource() {
        try {
            const eventSourceUrl = this.generateEventSourceURL();
            this.log('Connecting to EventSource:', eventSourceUrl);
            
            this.eventSource = new EventSource(eventSourceUrl);
            this.connectionType = 'eventsource';
            
            this.eventSource.onopen = (event) => {
                this.handleConnectionOpen('eventsource');
            };
            
            this.eventSource.onmessage = (event) => {
                this.handleMessage(event.data);
            };
            
            this.eventSource.onerror = (error) => {
                this.log('EventSource error:', error);
                this.emit('error', { type: 'eventsource', error, connectionType: 'eventsource' });
                this.handleConnectionClose({ code: 1000, reason: 'EventSource error' });
            };
            
            // Custom events for specific message types
            this.eventSource.addEventListener('upload_progress', (event) => {
                this.handleMessage(event.data, 'upload_progress');
            });
            
            this.eventSource.addEventListener('system_status', (event) => {
                this.handleMessage(event.data, 'system_status');
            });
            
            this.eventSource.addEventListener('notification', (event) => {
                this.handleMessage(event.data, 'notification');
            });
            
        } catch (error) {
            this.log('EventSource creation failed:', error);
            this.emit('error', { type: 'eventsource', error });
            this.scheduleReconnect();
        }
    }

    handleConnectionOpen(type) {
        this.isConnected = true;
        this.reconnectAttempts = 0;
        this.stats.connectionTime = Date.now();
        this.stats.lastActivity = Date.now();
        
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }
        
        this.log(`${type} connection established`);
        this.emit('connected', { connectionType: type, timestamp: Date.now() });
        
        // Start heartbeat for WebSocket
        if (type === 'websocket') {
            this.startHeartbeat();
        }
        
        // Send queued messages
        this.processMessageQueue();
        
        // Show success notification
        this.showConnectionNotification('Conectado em tempo real', 'success');
    }

    handleConnectionClose(event) {
        this.isConnected = false;
        this.stopHeartbeat();
        
        this.log('Connection closed:', {
            code: event.code,
            reason: event.reason,
            wasClean: event.wasClean
        });
        
        this.emit('disconnected', {
            code: event.code,
            reason: event.reason,
            wasClean: event.wasClean,
            timestamp: Date.now()
        });
        
        // Show disconnection notification
        this.showConnectionNotification('Conexão perdida', 'warning');
        
        // Schedule reconnection if not a clean close
        if (!event.wasClean || event.code !== 1000) {
            this.scheduleReconnect();
        }
    }

    handleMessage(data, eventType = null) {
        this.stats.messagesReceived++;
        this.stats.lastActivity = Date.now();
        
        try {
            const message = typeof data === 'string' ? JSON.parse(data) : data;
            
            // Add metadata
            message._timestamp = Date.now();
            message._eventType = eventType;
            message._connectionType = this.connectionType;
            
            this.log('Message received:', message);
            
            // Handle system messages
            if (this.handleSystemMessage(message)) {
                return;
            }
            
            // Emit to handlers
            this.emit('message', message);
            
            // Emit specific event type if available
            if (message.type) {
                this.emit(message.type, message);
            }
            
        } catch (error) {
            this.log('Error parsing message:', error, data);
            this.emit('error', { type: 'parse', error, data });
        }
    }

    handleSystemMessage(message) {
        switch (message.type) {
            case 'heartbeat':
                this.handleHeartbeatResponse(message);
                return true;
                
            case 'pong':
                this.handlePongMessage(message);
                return true;
                
            case 'error':
                this.emit('server_error', message);
                return true;
                
            case 'reconnect':
                this.handleReconnectRequest(message);
                return true;
                
            default:
                return false;
        }
    }

    handleHeartbeatResponse(message) {
        if (message.timestamp) {
            const latency = Date.now() - message.timestamp;
            this.updateLatencyStats(latency);
        }
        this.lastHeartbeat = Date.now();
    }

    handlePongMessage(message) {
        const messageId = message.messageId;
        if (this.pendingMessages.has(messageId)) {
            const sentTime = this.pendingMessages.get(messageId);
            const latency = Date.now() - sentTime;
            this.updateLatencyStats(latency);
            this.pendingMessages.delete(messageId);
        }
    }

    handleReconnectRequest(message) {
        this.log('Server requested reconnection:', message.reason);
        this.disconnect();
        setTimeout(() => {
            this.connect();
        }, message.delay || 1000);
    }

    updateLatencyStats(latency) {
        this.stats.latencyMeasurements.push(latency);
        
        // Keep only last 20 measurements
        if (this.stats.latencyMeasurements.length > 20) {
            this.stats.latencyMeasurements.shift();
        }
        
        // Calculate average
        const sum = this.stats.latencyMeasurements.reduce((a, b) => a + b, 0);
        this.stats.averageLatency = Math.round(sum / this.stats.latencyMeasurements.length);
        
        this.emit('latency_update', {
            current: latency,
            average: this.stats.averageLatency,
            measurements: this.stats.latencyMeasurements.length
        });
    }

    send(data) {
        if (!this.isConnected) {
            this.queueMessage(data);
            return false;
        }
        
        try {
            const message = typeof data === 'object' ? JSON.stringify(data) : data;
            
            if (this.connectionType === 'websocket' && this.ws) {
                this.ws.send(message);
                this.stats.messagesSent++;
                this.log('Message sent:', data);
                return true;
            } else {
                // EventSource is read-only, queue for later or use HTTP POST
                this.sendViaHTTP(data);
                return true;
            }
        } catch (error) {
            this.log('Error sending message:', error);
            this.emit('error', { type: 'send', error, data });
            this.queueMessage(data);
            return false;
        }
    }

    async sendViaHTTP(data) {
        try {
            const response = await fetch('/api/websocket/message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ...data,
                    clientId: this.getClientId(),
                    timestamp: Date.now()
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            this.stats.messagesSent++;
            this.log('Message sent via HTTP:', data);
            
        } catch (error) {
            this.log('Error sending via HTTP:', error);
            this.emit('error', { type: 'http_send', error, data });
            this.queueMessage(data);
        }
    }

    queueMessage(data) {
        if (this.messageQueue.length >= this.options.messageQueueSize) {
            // Remove oldest message
            this.messageQueue.shift();
        }
        
        this.messageQueue.push({
            data,
            timestamp: Date.now(),
            retries: 0
        });
        
        this.log('Message queued:', data);
    }

    processMessageQueue() {
        if (!this.isConnected || this.messageQueue.length === 0) {
            return;
        }
        
        const messagesToSend = [...this.messageQueue];
        this.messageQueue = [];
        
        for (const queuedMessage of messagesToSend) {
            if (!this.send(queuedMessage.data)) {
                // If sending fails, requeue with retry count
                queuedMessage.retries++;
                if (queuedMessage.retries < 3) {
                    this.messageQueue.push(queuedMessage);
                }
            }
        }
        
        this.log(`Processed ${messagesToSend.length} queued messages`);
    }

    startHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
        }
        
        this.heartbeatTimer = setInterval(() => {
            if (this.isConnected && this.connectionType === 'websocket') {
                const heartbeatData = {
                    type: 'heartbeat',
                    timestamp: Date.now(),
                    clientId: this.getClientId()
                };
                
                this.send(heartbeatData);
                
                // Check if we're receiving heartbeat responses
                if (this.lastHeartbeat && (Date.now() - this.lastHeartbeat) > this.options.heartbeatInterval * 2) {
                    this.log('Heartbeat timeout, reconnecting...');
                    this.reconnect();
                }
            }
        }, this.options.heartbeatInterval);
    }

    stopHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }
    }

    scheduleReconnect() {
        if (this.reconnectAttempts >= this.options.maxReconnectAttempts) {
            this.log('Max reconnection attempts reached');
            this.emit('max_reconnect_attempts', {
                attempts: this.reconnectAttempts,
                maxAttempts: this.options.maxReconnectAttempts
            });
            this.showConnectionNotification('Não foi possível reconectar', 'error');
            return;
        }
        
        const delay = Math.min(
            this.options.reconnectInterval * Math.pow(2, this.reconnectAttempts),
            30000
        );
        
        this.log(`Scheduling reconnect in ${delay}ms (attempt ${this.reconnectAttempts + 1})`);
        
        this.reconnectTimer = setTimeout(() => {
            this.reconnect();
        }, delay);
    }

    reconnect() {
        this.disconnect();
        this.reconnectAttempts++;
        this.stats.reconnections++;
        
        this.log(`Reconnecting... (attempt ${this.reconnectAttempts})`);
        this.emit('reconnecting', { attempt: this.reconnectAttempts });
        
        this.connectWithFallback().catch(error => {
            this.log('Reconnection failed:', error);
            this.scheduleReconnect();
        });
    }

    disconnect() {
        this.isConnected = false;
        this.stopHeartbeat();
        
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
            this.reconnectTimer = null;
        }
        
        if (this.ws) {
            this.ws.close(1000, 'Client disconnect');
            this.ws = null;
        }
        
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        
        this.connectionType = null;
        this.log('Disconnected');
    }

    // Event handling methods
    on(event, handler) {
        if (!this.eventHandlers.has(event)) {
            this.eventHandlers.set(event, []);
        }
        this.eventHandlers.get(event).push(handler);
    }

    once(event, handler) {
        if (!this.onceHandlers.has(event)) {
            this.onceHandlers.set(event, []);
        }
        this.onceHandlers.get(event).push(handler);
    }

    off(event, handler = null) {
        if (handler) {
            const handlers = this.eventHandlers.get(event);
            if (handlers) {
                const index = handlers.indexOf(handler);
                if (index > -1) {
                    handlers.splice(index, 1);
                }
            }
        } else {
            this.eventHandlers.delete(event);
            this.onceHandlers.delete(event);
        }
    }

    emit(event, data = null) {
        // Regular handlers
        const handlers = this.eventHandlers.get(event);
        if (handlers) {
            handlers.forEach(handler => {
                try {
                    handler(data);
                } catch (error) {
                    this.log('Error in event handler:', error);
                }
            });
        }
        
        // Once handlers
        const onceHandlers = this.onceHandlers.get(event);
        if (onceHandlers) {
            onceHandlers.forEach(handler => {
                try {
                    handler(data);
                } catch (error) {
                    this.log('Error in once event handler:', error);
                }
            });
            this.onceHandlers.delete(event);
        }
    }

    // Utility methods
    generateWebSocketURL() {
        const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
        return `${protocol}//${location.host}/ws/dashboard`;
    }

    generateEventSourceURL() {
        return `/api/websocket/events?clientId=${this.getClientId()}`;
    }

    getClientId() {
        if (!this.clientId) {
            this.clientId = 'client_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
        }
        return this.clientId;
    }

    getConnectionInfo() {
        return {
            isConnected: this.isConnected,
            connectionType: this.connectionType,
            reconnectAttempts: this.reconnectAttempts,
            lastHeartbeat: this.lastHeartbeat,
            clientId: this.getClientId(),
            stats: { ...this.stats }
        };
    }

    // Page visibility handlers
    handlePageVisible() {
        this.log('Page became visible');
        if (!this.isConnected) {
            this.reconnect();
        } else {
            // Send a ping to verify connection
            this.ping();
        }
    }

    handlePageHidden() {
        this.log('Page became hidden');
        // Optionally pause heartbeat to save resources
        // this.stopHeartbeat();
    }

    handleOnline() {
        this.log('Network came online');
        if (!this.isConnected) {
            this.reconnect();
        }
    }

    handleOffline() {
        this.log('Network went offline');
        this.showConnectionNotification('Sem conexão com a internet', 'warning');
    }

    ping() {
        if (this.isConnected && this.connectionType === 'websocket') {
            const pingId = ++this.messageId;
            const pingData = {
                type: 'ping',
                messageId: pingId,
                timestamp: Date.now(),
                clientId: this.getClientId()
            };
            
            this.pendingMessages.set(pingId, Date.now());
            this.send(pingData);
        }
    }

    // Error handling
    setupGlobalErrorHandlers() {
        window.addEventListener('error', (event) => {
            if (event.message && event.message.includes('WebSocket')) {
                this.log('Global WebSocket error:', event.error);
                this.emit('error', { type: 'global', error: event.error });
            }
        });
        
        window.addEventListener('unhandledrejection', (event) => {
            if (event.reason && event.reason.toString().includes('WebSocket')) {
                this.log('Unhandled WebSocket promise rejection:', event.reason);
                this.emit('error', { type: 'unhandled_rejection', error: event.reason });
            }
        });
    }

    // Notification system
    showConnectionNotification(message, type) {
        if (window.dashboardManager && window.dashboardManager.showNotification) {
            window.dashboardManager.showNotification(message, type, 3000);
        } else if (window.uploadManager && window.uploadManager.showFeedback) {
            window.uploadManager.showFeedback(message, type, 3000);
        } else {
            console.log(`[${type.toUpperCase()}] ${message}`);
        }
    }

    // Logging
    log(...args) {
        if (this.options.debug) {
            console.log('[WebSocket]', ...args);
        }
    }

    // Cleanup
    cleanup() {
        this.disconnect();
        this.eventHandlers.clear();
        this.onceHandlers.clear();
        this.messageQueue = [];
        this.pendingMessages.clear();
    }

    // High-level API methods
    subscribeToUploadProgress(callback) {
        this.on('upload_progress', callback);
    }

    subscribeToSystemStatus(callback) {
        this.on('system_status', callback);
    }

    subscribeToNotifications(callback) {
        this.on('notification', callback);
    }

    requestSystemStats() {
        this.send({
            type: 'request_system_stats',
            timestamp: Date.now(),
            clientId: this.getClientId()
        });
    }

    requestUploadStatus(uploadId) {
        this.send({
            type: 'request_upload_status',
            uploadId: uploadId,
            timestamp: Date.now(),
            clientId: this.getClientId()
        });
    }

    // Advanced features
    enableAutoReconnect(enabled = true) {
        this.options.autoReconnect = enabled;
    }

    setHeartbeatInterval(interval) {
        this.options.heartbeatInterval = interval;
        if (this.isConnected) {
            this.startHeartbeat();
        }
    }

    getLatencyStats() {
        return {
            current: this.stats.latencyMeasurements[this.stats.latencyMeasurements.length - 1] || 0,
            average: this.stats.averageLatency,
            min: Math.min(...this.stats.latencyMeasurements),
            max: Math.max(...this.stats.latencyMeasurements),
            measurements: this.stats.latencyMeasurements.length
        };
    }

    getStats() {
        return {
            ...this.stats,
            queuedMessages: this.messageQueue.length,
            pendingMessages: this.pendingMessages.size,
            eventHandlers: this.eventHandlers.size,
            connectionInfo: this.getConnectionInfo()
        };
    }
}

// Initialize WebSocket manager when DOM is ready
let webSocketManager;

document.addEventListener('DOMContentLoaded', function() {
    // Check if WebSocket is supported
    if (typeof WebSocket !== 'undefined' || typeof EventSource !== 'undefined') {
        webSocketManager = new WebSocketManager({
            debug: true // Enable debug logging in development
        });
        
        // Setup integration with other managers
        if (window.uploadManager) {
            integrateWithUploadManager();
        }
        
        if (window.dashboardManager) {
            integrateWithDashboardManager();
        }
        
        // Make available globally
        window.webSocketManager = webSocketManager;
    } else {
        console.warn('Neither WebSocket nor EventSource is supported in this browser');
    }
});

// Integration functions
function integrateWithUploadManager() {
    if (!webSocketManager || !window.uploadManager) return;
    
    // Subscribe to upload progress updates
    webSocketManager.subscribeToUploadProgress((data) => {
        if (window.uploadManager.updateFileProgress) {
            window.uploadManager.updateFileProgress(data.fileId, data.progress);
        }
    });
    
    // Subscribe to upload completion
    webSocketManager.on('upload_complete', (data) => {
        if (window.uploadManager.handleUploadComplete) {
            window.uploadManager.handleUploadComplete(data.fileId, data.result);
        }
    });
    
    // Subscribe to duplicate detection
    webSocketManager.on('duplicate_detected', (data) => {
        if (window.uploadManager.handleDuplicateFile) {
            window.uploadManager.handleDuplicateFile(data.filename, data.existingData);
        }
    });
}

function integrateWithDashboardManager() {
    if (!webSocketManager || !window.dashboardManager) return;
    
    // Subscribe to system status updates
    webSocketManager.subscribeToSystemStatus((data) => {
        if (window.dashboardManager.updateSystemStatus) {
            window.dashboardManager.updateSystemStatus(data.status);
        }
    });
    
    // Subscribe to notifications
    webSocketManager.subscribeToNotifications((data) => {
        if (window.dashboardManager.showNotification) {
            window.dashboardManager.showNotification(data.message, data.type, data.duration);
        }
    });
    
    // Auto-refresh dashboard on data updates
    webSocketManager.on('data_update', () => {
        if (window.dashboardManager.refreshDashboard) {
            window.dashboardManager.refreshDashboard(false); // Silent refresh
        }
    });
}

// Export for module use
window.WebSocketManager = WebSocketManager;