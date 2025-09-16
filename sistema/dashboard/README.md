# Dashboard Sistema ETL DI's - Padrão Expertzy

## 🎯 Visão Geral

Interface principal do Sistema ETL para importação e processamento de XMLs de Declarações de Importação brasileiras. Desenvolvido seguindo o padrão visual Expertzy com cores corporativas (#FF002D vermelho, #091A30 azul escuro).

## 🚀 Funcionalidades Implementadas

### ✅ Interface Principal
- **Layout Responsivo**: Design mobile-first com breakpoints otimizados
- **Header Fixo**: Logo Expertzy, navegação principal e status do sistema
- **Sidebar Modular**: 4 módulos (Fiscal, Comercial, Contábil, Faturamento)
- **Cards Dashboard**: Estatísticas principais com feedback visual
- **Sistema Status**: Monitoramento em tempo real dos componentes

### ✅ Sistema de Upload Drag'n'Drop
- **Interface Intuitiva**: Zona de drop com feedback visual
- **Validação Rigorosa**: Apenas XMLs, máximo 10MB, verificação MIME
- **Preview de Arquivos**: Lista com informações detalhadas
- **Progress Bar**: Acompanhamento visual do processamento
- **Seleção Múltipla**: Processamento em lote de vários XMLs

### ✅ Feedback por Cores (Padrão Expertzy)
- 🔴 **Vermelho (#FF002D)**: Erros e falhas de validação
- 🟡 **Amarelo (#FFD700)**: Processamento em andamento
- 🔵 **Azul (#0066CC)**: Informações e status neutro
- 🟢 **Verde (#28A745)**: Sucessos e conclusões
- 🟣 **Roxo (#6F42C1)**: Alertas especiais

### ✅ APIs REST Implementadas
- **Upload API**: `/api/upload/process.php` - Processamento de XMLs
- **Stats API**: `/api/dashboard/stats.php` - Estatísticas em tempo real
- **CORS Habilitado**: Suporte completo para requisições cross-origin
- **Validação Robusta**: Verificação de tipos, tamanhos e formatos

## 📁 Estrutura de Arquivos

```
/sistema/dashboard/
├── index.php                  # Página principal do dashboard
├── .htaccess                  # Configurações Apache (segurança + cache)
├── README.md                  # Esta documentação
├── /assets/
│   ├── /css/
│   │   └── dashboard.css      # Estilos específicos do dashboard
│   └── /js/
│       └── upload.js          # Sistema drag'n'drop e feedback
├── /api/
│   ├── /upload/
│   │   └── process.php        # Endpoint processamento XMLs
│   └── /dashboard/
│       └── stats.php          # Endpoint estatísticas tempo real
├── /components/               # Componentes reutilizáveis (futuro)
├── /templates/                # Templates auxiliares (futuro)
└── /data/                     # Dados e logs
    └── /logs/
        └── dashboard_errors.log
```

## 🎨 Padrão Visual Expertzy

### Cores Oficiais
```css
:root {
    --expertzy-red: #FF002D;        /* Vermelho principal */
    --expertzy-dark: #091A30;       /* Azul escuro */
    --expertzy-white: #FFFFFF;      /* Branco */
    --expertzy-gray: #666666;       /* Cinza médio */
    --expertzy-light-gray: #f8f9fa; /* Cinza claro */
}
```

### Typography
- **Fonte**: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto
- **Pesos**: 300 (light), 400 (normal), 600 (medium), 700 (bold)
- **Estilo**: Clean, moderno, profissional

### Componentes Visuais
- **Cards**: Border-radius 15px, sombras suaves
- **Botões**: Border-radius 25px, hover com elevação
- **Ícones**: SVG inline, 24px padrão
- **Animações**: Transições suaves 0.3s ease

## ⚙️ Configuração e Instalação

### Pré-requisitos
- PHP 8.1+ com extensões: PDO, MySQL, SimpleXML, fileinfo
- MySQL 8.0+ (ServBay para desenvolvimento)
- Apache com mod_rewrite habilitado

### Instalação Rápida
```bash
# 1. Navegar para o diretório do sistema
cd /path/to/importaco-sistema/sistema/dashboard/

# 2. Verificar permissões de diretórios
chmod 755 ../data/uploads/
chmod 755 ../data/processed/
chmod 755 ../data/logs/

# 3. Configurar banco de dados (se necessário)
# Verificar arquivo: ../config/database.php

# 4. Iniciar servidor de desenvolvimento
php -S localhost:8000 -t .

# 5. Acessar dashboard
# http://localhost:8000/
```

### Configuração ServBay (Desenvolvimento Mac)
```bash
# ServBay já configurado automaticamente
# Host: localhost:3307
# User: root
# Password: ServBay.dev
# Database: importaco_etl_dis
```

## 🔧 Uso do Sistema

### Upload de XMLs
1. **Drag'n'Drop**: Arraste arquivos XML diretamente na zona de upload
2. **Seleção Manual**: Clique na zona para abrir browser de arquivos
3. **Validação**: Sistema valida automaticamente formato e tamanho
4. **Preview**: Visualize lista de arquivos antes do processamento
5. **Processar**: Clique "Processar Arquivos" para iniciar

### Validações Automáticas
- ✅ Extensão .xml obrigatória
- ✅ Tamanho máximo 10MB por arquivo
- ✅ MIME type XML válido
- ✅ Estrutura XML bem formada
- ✅ Verificação de duplicatas

### Feedback Visual
- **Mensagens Toast**: Aparecem no canto superior direito
- **Cores por Status**: Sistema de cores padronizado
- **Progress Bar**: Acompanhamento em tempo real
- **Status Cards**: Atualização automática de estatísticas

## 📊 APIs e Endpoints

### Upload API - `/api/upload/process.php`
```javascript
// POST multipart/form-data
FormData: {
    xml_file: File,
    action: 'upload_xml'
}

// Resposta Sucesso
{
    "success": true,
    "message": "Arquivo processado com sucesso",
    "data": {
        "filename": "di_abc123_1234567890.xml",
        "original_name": "minha_di.xml",
        "size": 1024,
        "status": "uploaded"
    }
}

// Resposta Erro
{
    "success": false,
    "error": "Mensagem de erro detalhada"
}
```

### Stats API - `/api/dashboard/stats.php`
```javascript
// GET request
// Resposta
{
    "success": true,
    "data": {
        "stats": {
            "DIs Processadas": 150,
            "Adições": 1247,
            "Impostos Calculados": 890,
            // ...
        },
        "system_status": {
            "database": "online",
            "schema": "ready",
            "upload_dir": "writable"
        },
        "last_updated": "2025-01-15 14:30:00"
    }
}
```

## 🛡️ Segurança Implementada

### Proteções de Upload
- Validação rigorosa de MIME types
- Verificação de conteúdo XML real
- Limite de tamanho por arquivo
- Sanitização de nomes de arquivo
- Quarentena em diretório específico

### Headers de Segurança
```apache
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Content-Security-Policy: default-src 'self'
```

### Controle de Acesso
- Bloqueio de arquivos sensíveis (.sql, .log, .md)
- Proteção de diretórios críticos (/config, /core)
- CORS configurado para localhost apenas

## 📱 Responsividade

### Breakpoints
- **Desktop**: > 1024px - Layout completo com sidebar
- **Tablet**: 768px - 1024px - Sidebar colapsada
- **Mobile**: < 768px - Stack vertical, navegação simplificada
- **Mobile Small**: < 480px - Layout compacto otimizado

### Otimizações Mobile
- Touch-friendly: Botões mínimo 44px
- Swipe gestures: Suporte nativo
- Viewport optimization: Meta tag configurada
- Performance: Lazy loading de componentes

## 🚀 Performance

### Otimizações Implementadas
- **CSS Minificado**: Estilos otimizados para produção
- **Lazy Loading**: Carregamento sob demanda
- **Cache Headers**: Configuração Apache para assets estáticos
- **GZIP Compression**: Compressão automática de texto
- **Image Optimization**: SVG inline para ícones

### Métricas Alvo
- First Contentful Paint: < 2s
- Time to Interactive: < 3s
- Cumulative Layout Shift: < 0.1
- Upload Processing: < 30s por arquivo

## 🐛 Debug e Troubleshooting

### Logs do Sistema
```bash
# Logs de erro PHP
tail -f ../data/logs/dashboard_errors.log

# Logs do Apache (se disponível)
tail -f /var/log/apache2/error.log

# Console do navegador
# F12 -> Console para erros JavaScript
```

### Problemas Comuns

#### Upload não funciona
```bash
# Verificar permissões
ls -la ../data/uploads/
chmod 755 ../data/uploads/

# Verificar configuração PHP
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

#### Banco de dados offline
```php
# Verificar configuração
cat ../config/database.php

# Testar conexão
php -r "require 'config/database.php'; var_dump(getDatabase()->testConnection());"
```

#### Estatísticas não carregam
```javascript
// Verificar no console do navegador
fetch('/api/dashboard/stats.php')
    .then(r => r.json())
    .then(console.log);
```

## 🔄 Atualizações Futuras

### Roadmap v1.1
- [ ] Processamento em background (queues)
- [ ] WebSocket para atualizações em tempo real
- [ ] Export de relatórios em PDF/Excel
- [ ] Sistema de notificações push
- [ ] Dashboard analytics avançado

### Roadmap v1.2
- [ ] Multi-tenancy support
- [ ] Autenticação e autorização
- [ ] Audit logs completos
- [ ] Backup automatizado
- [ ] Disaster recovery

## 📞 Suporte

### Contatos Técnicos
- **Sistema**: Sistema ETL DI's v1.0.0
- **Padrão**: Expertzy IT Solutions
- **Documentação**: /docs/api/ (em desenvolvimento)

### Links Úteis
- [PRD Sistema ETL](../../PRD-Sistema-ETL-DIs.md)
- [Documentação Database](../core/database/SCHEMA-SPECIFICATION.md)
- [Especificações Técnicas](../../docs/)

---

**Última Atualização**: 2025-01-15
**Versão Dashboard**: 1.0.0
**Compatibilidade**: PHP 8.1+, MySQL 8.0+, Apache 2.4+