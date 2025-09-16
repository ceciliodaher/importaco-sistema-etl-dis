# Sistema de Monitoramento em Tempo Real - ETL DI's

## 📋 Visão Geral

Sistema inteligente de monitoramento em tempo real para o Hub Central do Sistema ETL de DI's, proporcionando visibilidade completa sobre o status de todos os módulos, componentes e métricas de performance.

## 🎯 Funcionalidades Principais

### ✅ **Status em Tempo Real**
- Monitoramento automático de todos os módulos (Dashboard, Fiscal, Comercial, Contábil, Faturamento)
- Verificação de componentes críticos (Banco de dados, Cache, Calculators, Parsers)
- Atualizações automáticas a cada 30 segundos

### ✅ **API RESTful Completa**
- Endpoint `/shared/api/system-status.php` com múltiplas rotas
- Suporte a cache inteligente com TTL configurável
- Resposta JSON padronizada com metadados

### ✅ **Widget Reutilizável**
- Componente PHP/CSS/JS integrado
- Configurável para diferentes contextos
- Temas personalizáveis (default, compact, minimal)

### ✅ **Métricas de Performance**
- Uso de memória e disco em tempo real
- Tempo de resposta das APIs
- Uptime do sistema
- Gráficos visuais de tendências

### ✅ **Sistema de Alertas**
- Notificações browser nativas
- Notificações toast personalizadas
- Alertas sonoros opcionais
- Log de eventos críticos

## 🏗️ Arquitetura do Sistema

### Estrutura de Arquivos
```
/sistema/shared/
├── config/
│   └── monitoring.php          # Configurações centrais
├── utils/
│   └── module-status.php       # Engine de verificação de status
├── api/
│   └── system-status.php       # API RESTful
├── components/
│   └── status-widget.php       # Widget reutilizável
├── assets/js/
│   └── status-monitor.js       # JavaScript real-time
└── logs/
    └── system.log              # Logs do sistema
```

### Fluxo de Dados
```
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│   Frontend JS   │───▶│   API Endpoint   │───▶│  Module Status  │
│                 │    │                  │    │                 │
│ status-monitor  │◀───│ system-status.php│◀───│ module-status   │
│                 │    │                  │    │                 │
└─────────────────┘    └──────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌──────────────────┐    ┌─────────────────┐
│  Status Widget  │    │      Cache       │    │   System Logs   │
│                 │    │                  │    │                 │
│   UI Updates    │    │   2min TTL       │    │   Rotational    │
└─────────────────┘    └──────────────────┘    └─────────────────┘
```

## 🔧 Configuração e Uso

### 1. **Configurações Básicas**
```php
// shared/config/monitoring.php
$monitoringConfig = [
    'refresh_intervals' => [
        'widget' => 30000,      // 30 segundos
        'dashboard' => 60000,   // 1 minuto
        'background' => 300000  // 5 minutos
    ],
    'modules' => [
        'dashboard' => [
            'critical' => true,
            'health_check_url' => 'dashboard/api/health.php'
        ]
        // ... outros módulos
    ]
];
```

### 2. **Uso do Widget**
```php
// Renderizar widget básico
renderStatusWidget('my-widget');

// Widget com configurações personalizadas
renderStatusWidget('advanced-widget', [
    'show_details' => true,
    'compact_mode' => false,
    'refresh_interval' => 30000,
    'theme' => 'default'
]);
```

### 3. **JavaScript Integration**
```javascript
// Inicializar monitoramento
const monitor = new SystemStatusMonitor({
    refreshInterval: 30000,
    enableNotifications: true,
    debug: false
});

// Subscrever a eventos
monitor.subscribe('statusChange', (data) => {
    console.log('Status changed:', data);
});

// Controle manual
monitor.start();
monitor.stop();
monitor.forceRefresh();
```

### 4. **API Endpoints**

#### **GET** `/shared/api/system-status.php`
Retorna status completo do sistema
```json
{
    "success": true,
    "data": {
        "overall_status": "online",
        "modules": {...},
        "components": {...},
        "performance": {...}
    }
}
```

#### **GET** `/shared/api/system-status.php/modules`
Retorna apenas status dos módulos

#### **GET** `/shared/api/system-status.php/performance`
Retorna métricas de performance

#### **GET** `/shared/api/system-status.php/health`
Health check simples

#### **POST** `/shared/api/system-status.php/refresh`
Força refresh do cache

## 📊 Status e Códigos

### Status Possíveis
- `online` - Funcionando normalmente
- `offline` - Indisponível
- `warning` - Funcionando com problemas
- `error` - Erro crítico
- `developing` - Em desenvolvimento
- `planned` - Planejado para futuro

### Cores Visuais
- 🟢 **Verde** (`online`) - Sistema funcionando
- 🟡 **Amarelo** (`warning`) - Atenção necessária
- 🔴 **Vermelho** (`error/offline`) - Problema crítico
- 🔵 **Azul** (`developing`) - Em desenvolvimento
- ⚪ **Cinza** (`planned`) - Planejado

## ⚡ Performance e Otimização

### Cache Inteligente
- **Sistema**: 2 minutos TTL
- **Performance**: 30 segundos TTL
- **Health Check**: 5 segundos TTL
- **Persistência**: LocalStorage para dados offline

### Polling Otimizado
- **Refresh Normal**: 30 segundos
- **Quick Refresh**: 5 segundos (dados críticos)
- **Background**: 5 minutos (quando inativo)
- **Visibility Change**: Pause quando tab inativa

### Retry Logic
- 3 tentativas com backoff exponencial
- Timeout de 10 segundos por requisição
- Fallback graceful para dados cached
- Indicadores visuais de erro

## 🔔 Sistema de Notificações

### Tipos de Notificação
```javascript
// Browser nativo (se permitido)
new Notification('Sistema ETL', {
    body: 'Status do módulo fiscal alterado',
    icon: '/logo.png'
});

// Toast personalizado
showToastNotification('Erro de conexão', 'error');

// Alertas sonoros
playNotificationSound('warning'); // Frequências diferentes
```

### Configuração de Alertas
```php
'alert_settings' => [
    'enabled' => true,
    'email_notifications' => false,
    'webhook_url' => null,
    'cooldown_period' => 300  // 5 minutos entre alertas
]
```

## 🛠️ Troubleshooting

### Problemas Comuns

#### **1. Widget não aparece**
```bash
# Verificar se arquivos existem
ls -la shared/components/status-widget.php
ls -la shared/utils/module-status.php

# Verificar logs
tail -f shared/logs/system.log
```

#### **2. API retorna erro 500**
```bash
# Verificar permissões
chmod 755 shared/api/system-status.php

# Verificar logs PHP
tail -f /var/log/apache2/error.log
```

#### **3. JavaScript não funciona**
```javascript
// Console do browser
console.log(typeof SystemStatusMonitor); // Deve retornar "function"
console.log(window.statusMonitor); // Deve existir se auto-init ativo
```

#### **4. Cache não funciona**
```bash
# Verificar diretório de cache
ls -la shared/data/cache/
chmod 755 shared/data/cache/

# Limpar cache manualmente
rm -f shared/data/cache/*.json
```

### Debug Mode
```javascript
// Ativar debug
const monitor = new SystemStatusMonitor({
    debug: true  // Logs detalhados no console
});

// Verificar status atual
console.log(monitor.getLastStatus());
console.log(monitor.isRunning());
```

## 📈 Métricas e Monitoramento

### Métricas Coletadas
- **Response Time**: Tempo de resposta das APIs
- **Memory Usage**: Uso de memória PHP
- **Disk Space**: Espaço disponível em disco
- **Database Connections**: Status de conexão MySQL
- **File Permissions**: Verificação de diretórios writable
- **Module Health**: Status individual de cada módulo

### Logs Estruturados
```
[2025-09-16 15:30:00] [INFO] Sistema de monitoramento inicializado
[2025-09-16 15:30:30] [WARNING] Módulo fiscal respondendo lentamente (1.2s)
[2025-09-16 15:31:00] [ERROR] Falha na conexão com banco de dados
[2025-09-16 15:31:30] [INFO] Sistema restaurado - status: online
```

### Rotação de Logs
- **Tamanho máximo**: 10MB por arquivo
- **Histórico**: 5 arquivos (50MB total)
- **Rotação automática**: Quando limite atingido

## 🔒 Segurança

### Proteções Implementadas
- **Validação de Input**: Sanitização de parâmetros
- **Rate Limiting**: Controle de frequência de requests
- **CORS Headers**: Configuração adequada
- **Error Handling**: Não exposição de informações sensíveis
- **Access Control**: Verificação de permissões

### Headers de Segurança
```php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
```

## 🚀 Roadmap Futuro

### Próximas Funcionalidades
- [ ] Dashboard de métricas históricas
- [ ] Alertas por email/webhook
- [ ] Integração com ferramentas de monitoramento
- [ ] Métricas customizadas por módulo
- [ ] API GraphQL para queries complexas
- [ ] Mobile responsiveness melhorada

### Melhorias de Performance
- [ ] WebSocket para updates real-time
- [ ] Service Worker para cache offline
- [ ] Compressão gzip nas APIs
- [ ] CDN para assets estáticos

---

## 📞 Suporte

Para problemas relacionados ao sistema de monitoramento:

1. **Verificar logs**: `tail -f shared/logs/system.log`
2. **Testar API**: `curl /shared/api/system-status.php/health`
3. **Debug JavaScript**: Console do browser
4. **Documentação completa**: `/docs/monitoring/`

**Versão**: 1.0.0  
**Última atualização**: 2025-09-16  
**Compatibilidade**: PHP 8.1+, MySQL 8.0+, Browsers modernos