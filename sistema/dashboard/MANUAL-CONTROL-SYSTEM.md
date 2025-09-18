# Sistema de Controle Manual - Dashboard ETL DI's

## 📋 Visão Geral

O **Sistema de Controle Manual** é a evolução do dashboard ETL DI's que implementa controle total sobre o workflow de carregamento de dados e visualizações. Substitui carregamentos automáticos por um sistema inteligente e controlado pelo usuário.

## 🎯 Funcionalidades Principais

### 1. **Controle de Estado Inteligente**
- **DashboardState**: Gerencia estado global do sistema
- **Validação Condicional**: Verifica se operações podem ser executadas
- **Persistência**: Estado mantido entre sessões

### 2. **Validação Inteligente**
```javascript
// Exemplos de validações automáticas
state.canLoadCharts()     // Verifica se há dados suficientes
state.canLoadStats()      // Valida disponibilidade de estatísticas
state.getNextRecommendedAction()  // Sugere próximo passo
```

### 3. **Sistema de Feedback Visual**
- **Toasts Inteligentes**: Mensagens contextuais com tipos (success, error, warning, info)
- **Loading States**: Indicadores visuais para operações em andamento
- **Progress Bars**: Barras de progresso para operações longas
- **Persistência Visual**: Toasts persistentes para ações críticas

### 4. **Auto-refresh Opcional**
```javascript
// Configurável pelo usuário
autoRefresh.start(30000)    // 30 segundos
autoRefresh.updateInterval(60000)  // Alterar para 60 segundos
autoRefresh.toggle()        // Ligar/desligar
```

### 5. **Integração com APIs**
- **Retry Automático**: Tentativas automáticas em caso de falha
- **Fallback Graceful**: Degradação elegante quando APIs falham
- **Cache Inteligente**: Evita requisições desnecessárias

## 🏗️ Arquitetura do Sistema

### Arquivos Principais

```
/assets/js/
├── manual-control-system.js      # Sistema principal (5 classes)
├── dashboard-integration.js      # Integração com componentes existentes
├── manual-control.js            # Painel de controle (legacy - integrado)
└── charts.js                    # Sistema de gráficos (modificado)
```

### Classes Principais

#### 1. **DashboardState**
```javascript
class DashboardState {
    database: {
        status, connected, schema_ready, 
        dis_count, sufficient, last_check
    }
    charts: { loaded, available_types, failed_types, last_load }
    stats: { loaded, data, last_load }
    autoRefresh: { enabled, interval, timer }
    operations: { active, history }
}
```

#### 2. **AutoRefreshManager**
```javascript
class AutoRefreshManager {
    start(interval)         // Iniciar refresh automático
    stop()                  // Parar refresh
    toggle()                // Alternar estado
    updateInterval(ms)      // Atualizar intervalo
    executeRefresh()        // Executar refresh manual
}
```

#### 3. **FeedbackSystem**
```javascript
class FeedbackSystem {
    showToast(message, type, options)    // Exibir toast
    showLoading(elementId, message)      // Loading overlay
    showProgress(title, message, %)      // Progress bar global
    updateProgress(%, message)           // Atualizar progresso
}
```

#### 4. **APIIntegration**
```javascript
class APIIntegration {
    checkDatabaseStatus()    // Verificar status BD
    executePreCheck()        // Executar pré-validação
    clearCache()            // Limpar cache
    loadChartsData(type)    // Carregar dados gráficos
    loadStats()             // Carregar estatísticas
}
```

#### 5. **ManualControlSystem**
```javascript
class ManualControlSystem {
    // Conecta todos os componentes
    handleVerifyDatabase()   // Verificar banco
    handleLoadCharts()       // Carregar gráficos
    handleLoadStats()        // Carregar estatísticas
    handleRefreshAll()       // Atualizar tudo
    handleClearCache()       // Limpar cache
}
```

## 🔧 Como Usar

### Inicialização Automática
O sistema é inicializado automaticamente quando o DOM está pronto:

```javascript
// Auto-inicializado
window.manualControlSystem  // Sistema principal
window.dashboardIntegration // Integração com componentes existentes
```

### Controles Manuais

#### Verificar Status do Banco
```javascript
window.manualControlSystem.handleVerifyDatabase()
// ou usar o botão "Verificar Status"
```

#### Carregar Gráficos
```javascript
window.loadChartsManually()
// ou usar o botão "Carregar Gráficos"
```

#### Carregar Estatísticas
```javascript
window.loadStatsManually()
// ou usar o botão "Carregar Estatísticas"
```

#### Atualizar Tudo
```javascript
window.refreshAllManually()
// ou usar o botão "Atualizar Tudo"
```

### Auto-refresh Configurável
```javascript
const autoRefresh = window.manualControlSystem.getAutoRefresh()

// Iniciar com intervalo de 30 segundos
autoRefresh.start(30000)

// Alterar intervalo para 60 segundos
autoRefresh.updateInterval(60000)

// Parar auto-refresh
autoRefresh.stop()
```

## 🎮 Interface do Usuário

### Painel de Controle
- **Status do Sistema**: Indicadores visuais do estado atual
- **Próximo Passo**: Recomendação inteligente da próxima ação
- **Controles de Dados**: Botões para verificar, importar, limpar
- **Controles de Visualização**: Botões para carregar gráficos e stats
- **Configurações**: Toggle de auto-refresh e configurações avançadas

### Estados Visuais

#### Indicadores de Status
```html
<!-- Banco de dados online -->
<div class="status-indicator success">Online</div>

<!-- Dados insuficientes -->
<div class="status-indicator warning">0 DIs</div>

<!-- Sistema aguardando -->
<div class="status-indicator-main inactive">Aguardando Dados</div>
```

#### Feedback Visual
- **Success**: Operações completadas com sucesso
- **Error**: Falhas que requerem atenção do usuário
- **Warning**: Situações que precisam de cuidado
- **Info**: Informações gerais do sistema

## 🔍 Validações Inteligentes

### Antes de Carregar Gráficos
```javascript
// Verificações automáticas:
✅ Banco conectado
✅ Schema configurado
✅ Pelo menos 1 DI importada
✅ Dados suficientes para análise
```

### Antes de Carregar Estatísticas
```javascript
// Verificações automáticas:
✅ Banco conectado
✅ Schema configurado
✅ Dados disponíveis
```

### Próximo Passo Recomendado
O sistema sempre orienta o usuário sobre a próxima ação:

1. **Banco Offline** → "Verificar Conexão do Banco"
2. **Schema Pendente** → "Configurar Schema do Banco"  
3. **Sem Dados** → "Importar XMLs de DI"
4. **Dados Disponíveis** → "Carregar Gráficos"
5. **Sistema Completo** → "Sistema Operacional"

## 🚀 Experiência do Usuário

### Fluxo Típico de Uso

1. **Dashboard Abre**
   ```
   Status: "Banco: Não verificado"
   Ação: Botão "Verificar Status" disponível
   ```

2. **Usuário Clica "Verificar Status"**
   ```
   Loading: "Verificando banco de dados..."
   Resultado: "Status do banco verificado ✅"
   ```

3. **Se Banco Vazio**
   ```
   Recomendação: "Importar XML DI"
   Status: "Aguardando Dados"
   ```

4. **Se Banco com Dados**
   ```
   Recomendação: "Carregar Gráficos"
   Botões: "Carregar Gráficos" e "Carregar Stats" habilitados
   ```

5. **Carregamento de Gráficos**
   ```
   Progress: "Carregando Gráficos... 75%"
   Resultado: "Gráficos carregados com sucesso ✅"
   ```

### Feedback em Tempo Real
- **Toasts Contextuais**: Mensagens aparecem no canto superior direito
- **Loading Overlays**: Indicadores sobre elementos sendo carregados
- **Progress Bars**: Para operações longas (refresh completo)
- **Status Updates**: Indicadores visuais atualizados em tempo real

## 🛠️ Atalhos de Teclado

- **Ctrl+Shift+V**: Verificar banco de dados
- **Ctrl+Shift+R**: Refresh completo do sistema  
- **Ctrl+Shift+C**: Carregar gráficos
- **Ctrl+Shift+S**: Carregar estatísticas
- **Ctrl+Shift+I**: Importar XML

## 🔧 Configurações Avançadas

### localStorage Settings
```javascript
{
    "autoRefresh": boolean,
    "refreshInterval": number,
    "debugMode": boolean,
    "notifications": boolean,
    "cacheDuration": number
}
```

### Cookies Persistentes
- `etl_auto_refresh`: Estado do auto-refresh (30 dias)
- `etl_refresh_interval`: Intervalo configurado (30 dias)

## 🐛 Troubleshooting

### Problemas Comuns

#### Gráficos Não Carregam
```javascript
// Verificar:
1. window.manualControlSystem.getState().canLoadCharts()
2. Status do banco via "Verificar Status"
3. Console do navegador para erros de API
```

#### Auto-refresh Não Funciona
```javascript
// Verificar:
1. window.manualControlSystem.getAutoRefresh().getStatus()
2. Configurações salvas no localStorage
3. Se há dados suficientes para refresh
```

#### Botões Desabilitados
```javascript
// Verificar estado:
window.manualControlSystem.getState().getNextRecommendedAction()
```

## 📊 Monitoramento

### Logs do Sistema
```javascript
// Debug mode ativado:
console.log('✅ Sistema inicializado')
console.log('🔄 Carregamento manual iniciado')
console.log('📊 Dados atualizados')
console.log('⚠️ Validação falhou')
console.log('❌ Erro na operação')
```

### Event Listeners
```javascript
// Escutar eventos do sistema:
document.addEventListener('chartsDataUpdated', handler)
state.on('database-changed', handler)
state.on('operation-completed', handler)
```

## 🔄 Migração do Sistema Anterior

### Compatibilidade
O novo sistema mantém compatibilidade com funções existentes:

```javascript
// Funções legacy ainda funcionam:
window.loadChartsManually()      // Novo sistema
window.refreshAllCharts()        // Redirecionado para novo
window.updateSystemStats()       // Redirecionado para novo
```

### Benefícios da Migração
- ✅ **Controle Total**: Usuário decide quando carregar dados
- ✅ **Performance**: Sem carregamentos desnecessários  
- ✅ **Feedback**: Sempre informa o que está acontecendo
- ✅ **Robustez**: Retry automático e tratamento de erros
- ✅ **Intuitividade**: Interface orienta próximos passos

---

**Status**: ✅ **SISTEMA IMPLEMENTADO E OPERACIONAL**

**Arquivos Criados**:
- `manual-control-system.js` (Sistema principal)
- `dashboard-integration.js` (Integração com componentes)
- Painel de controle integrado ao `index.php`

**Resultado**: Dashboard ETL DI's com controle manual completo, validações inteligentes e experiência de usuário fluida.