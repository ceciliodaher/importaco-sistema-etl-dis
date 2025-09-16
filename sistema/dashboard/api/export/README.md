# Sistema de Exportação Enterprise - Dashboard ETL DI's

## 📋 Visão Geral

Sistema completo de exportação profissional para o Dashboard ETL de DI's, oferecendo múltiplos formatos enterprise-grade com processamento assíncrono, templates customizáveis e branding Expertzy.

## 🎯 Características Principais

### ✅ Formatos de Exportação
- **JSON Estruturado**: Hierarquia completa DI → Adições → Impostos → Despesas
- **PDF Executivo**: Relatórios profissionais com TCPDF e gráficos embeddados
- **Excel Avançado**: Múltiplas abas com PhpSpreadsheet, gráficos nativos e formatação condicional

### ✅ Sistema de Templates
- Templates configuráveis para PDF e Excel
- Branding personalizado Expertzy (#FF002D, #091A30)
- Versioning e biblioteca de templates

### ✅ Processamento Assíncrono
- Jobs em background para exportações grandes
- Progress tracking em tempo real via WebSocket
- Rate limiting e controle de concorrência
- Cleanup automático de arquivos temporários

### ✅ Segurança Enterprise
- Download seguro com tokens temporários
- Audit trail completo
- Rate limiting por IP
- Validação rigorosa de arquivos

## 🏗️ Arquitetura do Sistema

```
/api/export/
├── manager.php              # Gerenciador central de exportações
├── json.php                 # Exportador JSON estruturado
├── pdf.php                  # Gerador PDF executivo (TCPDF)
├── xlsx.php                 # Planilhas avançadas (PhpSpreadsheet)
├── background_processor.php # Processamento assíncrono
├── download.php             # Sistema de download seguro
├── export_tables.sql        # Schema das tabelas
└── README.md               # Esta documentação

/templates/
├── pdf/
│   ├── default.json         # Template PDF padrão
│   └── executive_summary.json # Template resumo executivo
└── xlsx/
    ├── default.json         # Template Excel padrão
    └── financial_analysis.json # Template análise financeira

/assets/js/
└── export.js              # Frontend com progress tracking

/exports/                   # Diretório de arquivos gerados
```

## 📊 Formatos de Saída

### 1. JSON Export
```json
{
  "metadata": {
    "format_version": "importaco_etl_v1.0",
    "generated_at": "2025-09-16T10:30:00Z",
    "source": "Dashboard ETL DI's",
    "checksum": "sha256..."
  },
  "summary": {
    "totais": { /* estatísticas */ },
    "medias": { /* médias */ },
    "benchmarks": { /* benchmarks */ }
  },
  "dis": [
    {
      "identificacao": { /* dados da DI */ },
      "importador": { /* dados do importador */ },
      "valores_principais": { /* CIF, impostos, landed cost */ },
      "adicoes": [
        {
          "produto": { /* NCM, descrição, pesos */ },
          "valores": { /* CIF, VMLE, bases */ },
          "impostos": [ /* impostos calculados */ ],
          "acordos_tarifarios": [ /* acordos aplicados */ ]
        }
      ],
      "despesas": {
        "portuarias": [ /* despesas portuárias */ ],
        "logisticas": [ /* despesas logísticas */ ],
        "administrativas": [ /* despesas administrativas */ ]
      }
    }
  ]
}
```

### 2. PDF Export
- **Capa executiva** com logo Expertzy
- **Resumo executivo** com KPIs principais
- **Gráficos embedded** em alta resolução
- **Breakdown detalhado** por DI com tabelas formatadas
- **Análise tributária** especializada
- **Recomendações estratégicas**
- **Anexos técnicos** com glossário

### 3. Excel Export
- **Aba Dashboard**: KPIs e gráficos placeholder
- **Aba DIs**: Dados principais com formatação condicional
- **Aba Adições**: Detalhes dos produtos importados
- **Aba Impostos**: Cálculos tributários com cores por tipo
- **Aba Despesas**: Breakdown de custos operacionais
- **Aba Análises**: Fórmulas e benchmarks automáticos
- **Aba Gráficos**: Charts nativos do Excel
- **Aba Resumo**: Consolidação com links entre abas

## 🚀 Instalação e Configuração

### Pré-requisitos
```bash
# Composer para dependências
composer require tcpdf/tcpdf
composer require phpoffice/phpspreadsheet

# Extensões PHP necessárias
php -m | grep -E "(gd|zip|xml|json)"
```

### 1. Configurar Database
```sql
-- Executar schema das tabelas
mysql -u root -p importaco_etl_dis < export_tables.sql
```

### 2. Configurar Diretórios
```bash
# Criar diretórios com permissões
mkdir -p sistema/dashboard/exports
mkdir -p sistema/dashboard/logs
chmod 755 sistema/dashboard/exports
chmod 755 sistema/dashboard/logs
```

### 3. Configurar Cron Job
```bash
# Adicionar ao crontab para processamento background
* * * * * cd /path/to/sistema/dashboard/api/export && php background_processor.php

# Cleanup diário
0 2 * * * cd /path/to/sistema/dashboard/api/export && php -r "
require_once 'background_processor.php';
\$processor = new BackgroundExportProcessor();
\$processor->processQueue();
"
```

### 4. Configurar WebSocket (Opcional)
```bash
# Para notificações em tempo real
# Implementar servidor WebSocket usando ReactPHP ou similar
```

## 📱 Como Usar

### Frontend JavaScript
```javascript
// Iniciar exportação programaticamente
ExportAPI.startExport('dashboard_complete', 'pdf', {
    filters: {
        date_start: '2025-01-01',
        date_end: '2025-12-31',
        uf: ['SC', 'SP']
    },
    template: 'executive_summary'
});

// Monitorar exports ativos
const activeExports = ExportAPI.getActiveExports();

// Histórico de exports
const history = ExportAPI.getExportHistory();
```

### API REST
```bash
# Iniciar exportação
curl -X POST /api/export/manager \
  -H "Content-Type: application/json" \
  -d '{
    "type": "dashboard_complete",
    "format": "xlsx",
    "template": "financial_analysis",
    "filters": {
      "date_start": "2025-01-01",
      "date_end": "2025-12-31"
    }
  }'

# Verificar progresso
curl /api/export/progress/{export_id}

# Download do arquivo
curl -O "/api/export/download/{filename}?token={download_token}"
```

## ⚙️ Templates Configuráveis

### Template PDF
```json
{
  "name": "Executive Summary Report",
  "orientation": "portrait",
  "colors": {
    "primary": "#FF002D",
    "secondary": "#091A30"
  },
  "sections": {
    "cover": {"enabled": true},
    "executive_summary": {"enabled": true},
    "charts": {"enabled": true, "high_resolution": true},
    "appendices": {"enabled": false}
  }
}
```

### Template Excel
```json
{
  "name": "Financial Analysis Workbook",
  "sheets": {
    "financial_summary": {"enabled": true},
    "charts": {"enabled": true, "native_charts": true}
  },
  "conditional_formatting": {
    "currency_thresholds": {"high": 1000000}
  }
}
```

## 📈 Performance e Otimização

### Benchmarks Esperados
- **Processamento Síncrono**: < 30 segundos (até 5.000 registros)
- **JSON Export**: < 15 segundos
- **PDF Generation**: < 45 segundos
- **Excel Export**: < 60 segundos
- **Download Speed**: Limitado apenas pela banda

### Otimizações Implementadas
- Cache de dados preparados
- Processamento em chunks para arquivos grandes
- Cleanup automático com retention policies
- Rate limiting inteligente
- Compressão GZIP opcional

## 🔒 Segurança

### Controles de Acesso
- Tokens temporários para downloads
- Validação rigorosa de nomes de arquivo
- Rate limiting por IP (50 downloads/hora)
- Audit trail completo
- Sandbox de diretórios

### Monitoramento
```sql
-- Jobs ativos
SELECT * FROM v_active_export_jobs;

-- Estatísticas de uso
SELECT * FROM v_export_statistics WHERE export_date >= CURDATE() - INTERVAL 7 DAY;

-- Downloads suspeitos
SELECT client_ip, COUNT(*) as downloads
FROM download_logs 
WHERE downloaded_at >= NOW() - INTERVAL 1 HOUR
GROUP BY client_ip
HAVING downloads > 10;
```

## 🛠️ Troubleshooting

### Problemas Comuns

1. **Export fica "stuck" em processing**
   ```bash
   # Verificar jobs órfãos
   SELECT * FROM export_jobs WHERE status = 'processing' AND updated_at < NOW() - INTERVAL 10 MINUTE;
   
   # Reset manual
   UPDATE export_jobs SET status = 'failed', status_message = 'Timeout' WHERE export_id = 'XXX';
   ```

2. **Arquivos não são gerados**
   ```bash
   # Verificar permissões
   ls -la sistema/dashboard/exports/
   
   # Verificar logs
   tail -f sistema/dashboard/logs/export_processor.log
   ```

3. **WebSocket não conecta**
   ```javascript
   // Verificar fallback para polling
   console.log('WebSocket status:', exportManager.websocket?.readyState);
   ```

## 📚 Logs e Auditoria

### Tipos de Log
- **Export Processor**: `logs/export_processor.log`
- **Download Access**: Tabela `download_logs`
- **Error Logs**: PHP error_log padrão
- **Performance**: Tabela `export_usage_stats`

### Queries Úteis
```sql
-- Performance por tipo
SELECT export_type, AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_seconds
FROM export_jobs 
WHERE status = 'completed'
GROUP BY export_type;

-- Downloads por arquivo
SELECT file_name, COUNT(*) as download_count
FROM download_logs 
WHERE downloaded_at >= CURDATE()
GROUP BY file_name
ORDER BY download_count DESC;
```

## 🔄 Manutenção

### Rotinas Automáticas
- **Cleanup diário**: Jobs > 7 dias removidos automaticamente
- **Estatísticas**: Atualizadas diariamente às 01:00
- **Logs**: Rotacionados semanalmente

### Monitoramento de Health
```bash
# Verificar status do sistema
curl /api/export/background_processor.php?action=status

# Trigger manual de processamento
curl -X POST /api/export/background_processor.php?action=process
```

## 🆕 Novas Features Planejadas

- [ ] Integração com cloud storage (AWS S3, Google Cloud)
- [ ] Templates visuais via interface web
- [ ] Notificações por email quando export completo
- [ ] API para integração com sistemas externos
- [ ] Dashboard de monitoramento em tempo real
- [ ] Suporte a outros formatos (CSV, ODS)
- [ ] Assinatura digital de PDFs
- [ ] Watermarks personalizáveis

## 🤝 Contribuição

Para contribuir com melhorias:

1. Teste as funcionalidades existentes
2. Documente bugs encontrados
3. Sugira otimizações de performance
4. Proponha novos templates
5. Melhore a documentação

## 📞 Suporte

Para questões técnicas:
- **Sistema**: Dashboard ETL DI's v1.0
- **Autor**: Sistema Expertzy
- **Docs**: Este README.md
- **Logs**: `/logs/export_processor.log`

---

**Última atualização**: 16/09/2025  
**Versão**: 1.0.0  
**Status**: ✅ Sistema completo e operacional