# Sistema ETL de DI's - Padrão Expertzy

## 📋 Visão Geral do Projeto

Sistema HTML modular E56 baseado em MySQL para importação, processamento e exportação (ETL) de XMLs de DI's brasileiras. Sistema modular com 4 módulos independentes: Fiscal, Faturamento, Contábil e Comercial.

## 🎯 Objetivos Principais

- **ETL Completo**: Importação, processamento e exportação de XMLs DI
- **Análise Fiscal**: Cálculo de custos sob regimes Real, Presumido, Simples + Reforma Tributária
- **Precificação Inteligente**: Segmentação B2B/B2C com markup sobre landed cost
- **Dashboard Dinâmico**: Análise em tempo real de custos, câmbio e evolução
- **Modularidade**: 4 módulos independentes com comunicação via APIs REST

## 🔧 Regras de Desenvolvimento

### Princípios Fundamentais
- ❌ **No fallbacks, no hardcoded data**
- ✅ **KISS (Keep It Simple, Stupid)**
- ✅ **DRY (Don't Repeat Yourself)** - Nunca duplicar códigos/arquivos/funções
- ✅ **Nomenclatura única**: Módulo que cria, nomeia - demais seguem
- ✅ **Single Source of Truth**: Uma função, um propósito, um lugar

### Regras de Negócio Específicas
1. **Segmentação Cliente**: Precificação diferenciada (consumidor final vs revenda)
2. **Markup Calculation**: Baseado em custo total landed + todos impostos
3. **Lógica por Estado**: Benefícios fiscais únicos configuráveis por UF
4. **XML Parsing**: Formato DI brasileiro com adições (sem hardcode)
5. **Múltiplas Moedas**: Taxa câmbio CALCULADA dos valores DI (não extraída)
6. **Despesas Extras**: Fora da DI, configurável compor/não compor base ICMS
7. **Incentivos Fiscais**: Entrada/Saída/Ambos com controle granular

## 🏗️ Arquitetura do Sistema

### Stack Tecnológico
- **Backend**: PHP 8.1+ (desenvolvimento Mac/Windows, deploy web)
- **Database**: MySQL 8.0+ com schema configurável
- **Frontend**: HTML5/CSS3/JavaScript ES6+ padrão Expertzy
- **APIs**: RESTful com middleware de autenticação
- **Reports**: PhpSpreadsheet + TCPDF

### Estrutura de Diretórios
```
/importaco-sistema/
├── index.html                    # Landing page simples
├── CLAUDE.md                     # Este arquivo
├── PRD-Sistema-ETL-DIs.md       # Product Requirements Document
├── /sistema/                     # Sistema principal
│   ├── /config/
│   │   ├── database.php         # Configurações BD
│   │   ├── environments.php     # Mac/Windows/Web configs
│   │   └── nomenclature.php     # Registry central nomenclatura
│   ├── /core/
│   │   ├── /parsers/
│   │   │   └── DiXmlParser.php  # Parser único DI brasileiras
│   │   ├── /calculators/
│   │   │   ├── CurrencyCalculator.php  # Câmbio calculado
│   │   │   ├── TaxCalculator.php       # Engine fiscal
│   │   │   └── MarkupCalculator.php    # Precificação
│   │   ├── /engines/
│   │   │   ├── IncentivesEngine.php    # Benefícios fiscais
│   │   │   └── CostEngine.php          # Custeio completo
│   │   └── /database/
│   │       ├── schema.sql              # Schema MySQL único
│   │       └── SCHEMA-SPECIFICATION.md # Documentação completa das tabelas
│   ├── /modules/
│   │   ├── /fiscal/                    # Módulo Fiscal (cria nomenclatura)
│   │   ├── /commercial/                # Módulo Comercial
│   │   ├── /accounting/                # Módulo Contábil
│   │   └── /billing/                   # Módulo Faturamento
│   ├── /shared/
│   │   ├── /components/               # Componentes únicos
│   │   ├── /utils/                    # Utilitários compartilhados
│   │   └── /assets/                   # CSS/JS padrão Expertzy
│   └── /data/
│       ├── /uploads/                  # XMLs DI carregados
│       ├── /processed/                # Dados processados
│       └── /exports/                  # Relatórios gerados
└── /docs/                            # Documentação técnica
```

## 🔄 Comandos de Desenvolvimento

### Ambiente Local (Desenvolvimento)
```bash
# Mac (ServBay)
brew install php mysql
brew services start mysql
php -S localhost:8000 -t sistema/

# Windows (WAMP)
# Instalar WAMP Server
# Acessar http://localhost/importaco-sistema/sistema/
```

### Comandos MySQL
```bash
# Instalar banco completo (automático)
cd sistema/core/database
./setup.sh install

# Verificar status
./setup.sh status

# Fazer backup
./setup.sh backup

# Reset completo (cuidado!)
./setup.sh reset
```

### Testes e Validação
```bash
# Validar XML DI
php sistema/core/parsers/DiXmlParser.php --validate exemplo.xml

# Testar cálculos
php sistema/core/calculators/TaxCalculator.php --test

# Executar testes unitários
php sistema/tests/run_tests.php
```

## 📊 Módulos do Sistema

### 1. Módulo Fiscal (Nome Creator)
- **Responsabilidade**: Cálculos tributários, nomenclatura fiscal
- **Funções**: II, IPI, PIS/COFINS, ICMS, benefícios por estado
- **Nomenclatura**: Define padrões (NCM, CFOP, CST, etc.)

### 2. Módulo Comercial
- **Responsabilidade**: Precificação e análise de margens
- **Funções**: Segmentação B2B/B2C, markup sobre landed cost
- **Nomenclatura**: Segue padrões do Módulo Fiscal

### 3. Módulo Contábil
- **Responsabilidade**: Custeio e rateio de despesas
- **Funções**: Rateio proporcional, despesas extras, base ICMS
- **Nomenclatura**: Segue padrões do Módulo Fiscal

### 4. Módulo Faturamento
- **Responsabilidade**: Geração de documentos fiscais
- **Funções**: Croqui NF importação, templates, exportação
- **Nomenclatura**: Segue padrões do Módulo Fiscal

## 🔐 Configurações de Segurança

### Autenticação
- Sistema de tokens JWT
- Controle de acesso por módulo
- Logs de auditoria completos

### Validações
- Sanitização de inputs XML
- Validação de tipos de dados
- Prevenção SQL injection

## 📈 Performance e Otimização

### Benchmarks Esperados
- **Processamento XML**: < 30 segundos
- **Consultas Database**: < 5 segundos
- **Geração Relatórios**: < 10 segundos
- **Cálculos Tributários**: < 2 segundos

### Otimizações
- Cache em Redis para cálculos frequentes
- Índices MySQL otimizados
- Processamento assíncrono para XMLs grandes

## 🌐 Deploy para Produção Web

### Configurações Web
```php
// Configurações para produção web
define('ENVIRONMENT', 'production');
define('DB_HOST', $_ENV['DB_HOST']);
define('DB_NAME', $_ENV['DB_NAME']);
define('DB_USER', $_ENV['DB_USER']);
define('DB_PASS', $_ENV['DB_PASS']);
```

### Checklist Deploy
- [ ] Variáveis de ambiente configuradas
- [ ] SSL/HTTPS ativo
- [ ] Backups automáticos configurados
- [ ] Monitoramento de logs ativo
- [ ] Rate limiting implementado

## 🐛 Troubleshooting

### Problemas Comuns
1. **XML não processa**: Verificar formato DI brasileiro
2. **Cálculos incorretos**: Validar configurações por estado
3. **Performance lenta**: Verificar índices MySQL
4. **Erro de nomenclatura**: Verificar registry central

### Logs e Debug
```bash
# Verificar logs sistema
tail -f sistema/data/logs/sistema.log

# Debug XML parser
php sistema/core/parsers/DiXmlParser.php --debug exemplo.xml
```

## 🔄 Versionamento

### Convenções
- **v1.0.0**: Sistema base com 4 módulos
- **v1.1.0**: Novas funcionalidades
- **v1.0.1**: Correções e melhorias

### Git Workflow
```bash
# Feature branch
git checkout -b feature/novo-calculo-fiscal
git commit -m "feat: adiciona cálculo incentivo GO"
git push origin feature/novo-calculo-fiscal
```

## ✅ Database Schema - IMPLEMENTADO

### Estrutura Completa (13 tabelas operacionais)
Sistema completo baseado em análise de DIs reais brasileiras:

1. **declaracoes_importacao** - Dados principais da DI
2. **adicoes** - Itens individuais da importação  
3. **mercadorias** - Detalhes dos produtos
4. **impostos_adicao** - Cálculos tributários por adição
5. **acordos_tarifarios** - Benefícios e incentivos fiscais
6. **icms_detalhado** - Detalhamento específico ICMS
7. **pagamentos_siscomex** - Controle de pagamentos
8. **despesas_frete_seguro** - Custos de transporte
9. **despesas_extras** - 16 categorias discriminadas
10. **moedas_referencia** - 15 moedas principais
11. **ncm_referencia** - Catalogação dinâmica (sem alíquotas fixas)
12. **ncm_aliquotas_historico** - Histórico real das alíquotas
13. **conversao_valores** - Auditoria de conversões Siscomex
14. **configuracoes_sistema** - Parâmetros operacionais

### ✅ Funcionalidades Implementadas
- **Validação AFRMM**: DI prevalece sobre cálculo (25% frete)
- **16 Despesas Discriminadas**: SISCOMEX, AFRMM, Capatazia, etc.
- **NCM Dinâmico**: Sem alíquotas hardcoded - populado das DIs
- **10 Funções MySQL**: Conversão Siscomex + validações
- **10 Triggers**: Auditoria automática + atualizações
- **8 Views**: Dashboard executivo + análises
- **25+ Índices**: Performance otimizada
- **Testes Automatizados**: Suite completa de validação

### ⚡ Scripts Prontos para Uso
```bash
# Instalação completa em 1 comando
cd sistema/core/database && ./setup.sh install

# Testes automáticos
mysql -u root -p'ServBay.dev' -D importaco_etl_dis < test_validation.sql
```

### 📊 Status: BANCO OPERACIONAL ✅
- Host: localhost:3307 (ServBay MySQL)
- Database: importaco_etl_dis
- Credenciais: root / ServBay.dev
- **Sistema pronto para receber XMLs de DI**

## 📚 Documentação Adicional

- **PRD-Sistema-ETL-DIs.md**: Requirements completos
- **sistema/core/database/SCHEMA-SPECIFICATION.md**: Schema completo com 12 tabelas
- **/docs/api/**: Documentação APIs REST
- **/docs/database/**: Schema e relacionamentos
- **/docs/modules/**: Documentação por módulo

---

**Última atualização**: 2025-09-16
**Versão Sistema**: 1.0.0
**Ambiente**: Desenvolvimento
**Status Database**: ✅ OPERACIONAL (13 tabelas + 10 funções + triggers + views)