# Sistema JavaScript Avançado - Dashboard ETL DI's

## 🚀 Visão Geral

Sistema JavaScript moderno e reativo para o dashboard de importação de DI's brasileiras, com funcionalidades avançadas de tempo real, validação XML especializada e interface interativa.

## 📋 Arquivos Implementados

### 1. `upload.js` (Aprimorado)
**Sistema de Upload Avançado com Queue e Retry**

#### Funcionalidades Principais:
- **Queue de Upload**: Processamento sequencial com máximo de 3 uploads simultâneos
- **Retry Automático**: Até 3 tentativas com backoff exponencial
- **Chunked Upload**: Arquivos grandes (>5MB) enviados em partes de 5MB
- **Validação XML**: Verificação estrutural antes do upload
- **WebSocket Integration**: Status em tempo real via WebSocket
- **Detecção de Duplicatas**: Verificação local e no servidor
- **Progress Ring**: Indicadores visuais de progresso individual

#### Métodos Principais:
```javascript
// Upload com queue avançado
await uploadManager.processFiles();

// Validação de arquivo
const validation = await uploadManager.validateFile(file);

// Upload chunked para arquivos grandes
const result = await uploadManager.uploadFileChunked(file);
```

### 2. `dashboard.js` (Novo)
**Funcionalidades Gerais do Dashboard com Interface Reativa**

#### Funcionalidades Principais:
- **Auto-refresh**: Atualização automática a cada 30 segundos
- **Keyboard Shortcuts**: Atalhos para ações rápidas (R=refresh, F=search, etc)
- **Drag & Drop**: Reorganização de cards por arrastar
- **Context Menu**: Menu contextual nos cards
- **Search & Filters**: Busca inteligente com autocomplete
- **Modal System**: Sistema de modais para detalhes
- **Settings Panel**: Painel de configurações personalizável
- **Local Storage**: Persistência de preferências do usuário

#### Métodos Principais:
```javascript
// Refresh manual do dashboard
await dashboardManager.refreshDashboard();

// Busca com debounce
dashboardManager.executeSearch("termo de busca");

// Alternar auto-refresh
dashboardManager.toggleAutoRefresh();
```

#### Keyboard Shortcuts:
- `R` - Refresh dashboard
- `F` - Focar na busca
- `S` - Abrir configurações
- `1-4` - Focar nos cards
- `Escape` - Limpar filtros

### 3. `websocket.js` (Novo)
**Conexão em Tempo Real com Fallback**

#### Funcionalidades Principais:
- **WebSocket Connection**: Conexão primária via WebSocket
- **EventSource Fallback**: Fallback para Server-Sent Events
- **Auto-reconnection**: Reconexão automática com backoff exponencial
- **Heartbeat System**: Sistema de heartbeat para detectar conexões perdidas
- **Message Queue**: Queue de mensagens para garantir entrega
- **Latency Monitoring**: Monitoramento de latência da conexão
- **Page Visibility**: Pausa/retoma baseado na visibilidade da página
- **Network Status**: Detecção de online/offline

#### Métodos Principais:
```javascript
// Conectar ao WebSocket
webSocketManager.connectWithFallback();

// Enviar mensagem
webSocketManager.send({ type: 'request_stats' });

// Subscrever a eventos
webSocketManager.on('upload_progress', (data) => {
    console.log('Progress:', data);
});

// Ping para testar conexão
webSocketManager.ping();
```

#### Eventos Disponíveis:
- `connected` - Conexão estabelecida
- `disconnected` - Conexão perdida
- `message` - Mensagem recebida
- `upload_progress` - Progresso de upload
- `system_status` - Status do sistema
- `notification` - Notificações

### 4. `xml-validator.js` (Novo)
**Validação Específica para DI's Brasileiras**

#### Funcionalidades Principais:
- **Validação Estrutural**: Verifica estrutura XML básica
- **Campos Obrigatórios**: Valida campos essenciais da DI
- **Códigos Brasileiros**: Validação de NCM, CFOP, CST, CNPJ/CPF
- **Multi-Currency**: Detecção de múltiplas moedas na DI
- **Preview Generation**: Gera preview dos dados principais
- **Cache System**: Cache de validações para performance
- **Statistics**: Estatísticas detalhadas do XML
- **Tax Structure**: Validação da estrutura tributária

#### Métodos Principais:
```javascript
// Validação completa da DI
const result = await xmlValidator.validateDI(xmlContent);

// Validação rápida (apenas estrutura)
const quick = await xmlValidator.quickValidate(xmlContent);

// Validar arquivo
const fileResult = await xmlValidator.validateFile(file);

// Resumo da validação
const summary = xmlValidator.getValidationSummary(result);
```

#### Estrutura do Resultado:
```javascript
{
    valid: true,
    errors: [],
    warnings: [],
    info: [],
    preview: {
        declaracao: { numero_di: "12345678901", ... },
        adicoes: [{ ncm: "12345678", ... }],
        resumo: { total_adicoes: 5, ... }
    },
    currencies: ["USD", "BRL"],
    statistics: { validation_score: 95, ... }
}
```

## 🎨 Estilos CSS

### `advanced-features.css` (Novo)
**Estilos para todas as funcionalidades avançadas**

#### Componentes Estilizados:
- **Progress Rings**: Anéis de progresso animados
- **Drag & Drop**: Indicadores visuais de arrastar
- **Notifications**: Sistema de notificações moderno
- **Context Menu**: Menu contextual estilizado
- **Modals**: Sistema de modais responsivo
- **Settings Panel**: Painel de configurações
- **Health Indicators**: Indicadores de saúde do sistema
- **Responsive Design**: Layout responsivo completo

## 🔧 Integração e Uso

### Inicialização Automática
Todos os managers são inicializados automaticamente no `DOMContentLoaded`:

```javascript
document.addEventListener('DOMContentLoaded', function() {
    // Inicialização automática de todos os sistemas
    uploadManager = new UploadManager();
    dashboardManager = new DashboardManager();
    webSocketManager = new WebSocketManager();
    xmlValidator = new XMLValidator();
});
```

### Integração entre Componentes
Os sistemas são integrados automaticamente:

```javascript
// WebSocket integra com Upload Manager
webSocketManager.subscribeToUploadProgress((data) => {
    uploadManager.updateFileProgress(data.fileId, data.progress);
});

// Dashboard integra com WebSocket
webSocketManager.on('system_status', (data) => {
    dashboardManager.updateSystemStatus(data.status);
});
```

## 📊 Monitoramento e Debug

### Debug Mode
Ative o modo debug para logs detalhados:

```javascript
// WebSocket com debug
const wsManager = new WebSocketManager({ debug: true });

// Logs aparecem no console com prefixo [WebSocket]
```

### Estatísticas
Todos os managers fornecem estatísticas:

```javascript
// Estatísticas do WebSocket
console.log(webSocketManager.getStats());

// Estatísticas de cache do XML Validator
console.log(xmlValidator.getCacheStats());

// Info de conexão
console.log(webSocketManager.getConnectionInfo());
```

## 🛠️ APIs Backend Necessárias

### Upload APIs
- `POST /api/upload/process.php` - Upload single
- `POST /api/upload/init-chunked.php` - Iniciar upload chunked
- `POST /api/upload/chunk.php` - Enviar chunk
- `POST /api/upload/finalize-chunked.php` - Finalizar upload chunked
- `POST /api/upload/check-duplicate.php` - Verificar duplicatas

### Dashboard APIs  
- `GET /api/dashboard/stats.php` - Estatísticas gerais
- `GET /api/dashboard/charts.php` - Dados dos gráficos
- `GET /api/dashboard/activity.php` - Atividade recente
- `GET /api/dashboard/system-status.php` - Status do sistema
- `POST /api/dashboard/search.php` - Busca
- `GET /api/dashboard/card-data.php` - Dados específicos do card

### WebSocket Endpoints
- `WS /ws/dashboard` - Conexão WebSocket principal
- `GET /api/websocket/events` - EventSource fallback
- `POST /api/websocket/message` - Envio via HTTP (fallback)

## 🔒 Segurança

### Validações Implementadas
- **XSS Prevention**: Sanitização de dados XML
- **Input Validation**: Validação de todos os inputs
- **File Size Limits**: Limite de 10MB por arquivo
- **File Type Restriction**: Apenas arquivos XML
- **Rate Limiting**: Controle de frequência de uploads

### Error Handling
- **Try-catch**: Todos os métodos assíncronos protegidos
- **Graceful Degradation**: Fallbacks quando funcionalidades não estão disponíveis
- **User Feedback**: Erros sempre comunicados ao usuário

## 🚀 Performance

### Otimizações Implementadas
- **Lazy Loading**: Componentes carregados sob demanda
- **Debouncing**: Busca com delay para evitar requests excessivos
- **Caching**: Cache de validações XML e dados do dashboard
- **Concurrent Uploads**: Máximo de 3 uploads simultâneos
- **Chunked Upload**: Arquivos grandes divididos em chunks
- **Memory Management**: Limpeza automática de caches antigos

### Benchmarks Esperados
- **Validação XML**: < 100ms para DIs típicas
- **Upload Single**: < 30s para arquivos até 10MB
- **Upload Chunked**: < 2min para arquivos grandes
- **WebSocket Latency**: < 50ms em condições normais
- **Dashboard Refresh**: < 5s para atualização completa

## 🎯 Recursos Avançados

### Accessibility (A11y)
- **Keyboard Navigation**: Navegação completa por teclado
- **Focus Indicators**: Indicadores visuais de foco
- **Screen Reader**: Texto alternativo para elementos visuais
- **Reduced Motion**: Suporte a usuários com preferência por menos animações

### Progressive Enhancement
- **Feature Detection**: Verifica disponibilidade de funcionalidades
- **Graceful Fallbacks**: Funciona mesmo sem WebSocket/JavaScript avançado
- **Mobile First**: Design responsivo começando pelo mobile

### Extensibilidade
- **Plugin System**: Estrutura permite adição de plugins
- **Event System**: Sistema de eventos para integração
- **Modular Design**: Componentes independentes e reutilizáveis

## 📝 Próximos Passos

### Melhorias Futuras
1. **Service Worker**: Cache offline e background sync
2. **IndexedDB**: Storage local para grandes volumes de dados
3. **Web Workers**: Processamento de XML em background
4. **Push Notifications**: Notificações do sistema
5. **Dark Mode**: Tema escuro completo
6. **i18n**: Internacionalização para outros idiomas

### Testes
1. **Unit Tests**: Testes unitários para cada componente
2. **Integration Tests**: Testes de integração entre componentes
3. **E2E Tests**: Testes end-to-end com Cypress
4. **Performance Tests**: Testes de performance e carga

---

**Versão**: 1.0.0  
**Última atualização**: 2025-09-16  
**Compatibilidade**: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+