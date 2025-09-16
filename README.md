# Sistema ETL de DI's - Padrão Expertzy

<div align="center">
  <img src="images/expertzy-it.png" alt="Expertzy - Inteligência Tributária" height="100">

  **Energia • Segurança • Transparência**

  Sistema HTML modular para importação, processamento e análise de Declarações de Importação brasileiras
</div>

## 🎯 Objetivo

Sistema modular completo para ETL (Extract, Transform, Load) de XMLs de DI's com análise fiscal automatizada, precificação inteligente e dashboard dinâmico.

## ⚡ Características Principais

- **ETL Automatizado**: Processamento de XMLs DI em < 30 segundos
- **Cálculos Tributários**: II, IPI, PIS/COFINS, ICMS com benefícios por estado
- **Precificação Segmentada**: B2B vs B2C baseado em landed cost real
- **4 Módulos Independentes**: Fiscal, Comercial, Contábil, Faturamento
- **Dashboard Dinâmico**: Análise em tempo real de custos e margens

## 🏗️ Arquitetura

### Stack Tecnológico
- **Backend**: PHP 8.1+ (MVC modular)
- **Database**: MySQL 8.0+ otimizado
- **Frontend**: HTML5/CSS3/JS ES6+ padrão Expertzy
- **APIs**: RESTful com JWT authentication

### Estrutura Modular
```
├── index.html                 # Landing page
├── CLAUDE.md                  # Configurações do projeto
├── PRD-Sistema-ETL-DIs.md    # Product Requirements Document
├── assets/                    # CSS/JS da landing page
├── sistema/                   # Sistema principal
│   ├── config/               # Configurações
│   ├── core/                 # Componentes centrais
│   ├── modules/              # Módulos especializados
│   └── shared/               # Componentes compartilhados
└── docs/                     # Documentação técnica
```

## 🚀 Instalação Rápida

### Mac (ServBay)
```bash
git clone https://github.com/seu-usuario/importaco-sistema.git
cd importaco-sistema
php -S localhost:8000
```

### Windows (WAMP)
```bash
git clone https://github.com/seu-usuario/importaco-sistema.git
# Copiar para C:\wamp64\www\
# Acessar http://localhost/importaco-sistema/
```

## 📋 Regras de Desenvolvimento

### Princípios Fundamentais
- ❌ **No fallbacks, no hardcoded data**
- ✅ **KISS (Keep It Simple, Stupid)**
- ✅ **DRY (Don't Repeat Yourself)**
- ✅ **Nomenclatura única**: Módulo que cria, nomeia - demais seguem

### Regras de Negócio
1. **Segmentação Cliente**: Precificação diferenciada (B2B/B2C)
2. **Markup Calculation**: Baseado em landed cost + todos impostos
3. **Lógica por Estado**: Benefícios fiscais únicos configuráveis
4. **XML Parsing**: Formato DI brasileiro com adições
5. **Múltiplas Moedas**: Taxa câmbio CALCULADA dos valores DI
6. **Despesas Extras**: Configuração compor/não compor base ICMS
7. **Incentivos Fiscais**: Entrada/Saída/Ambos por estado

## 🔧 Configuração

### Banco de Dados
```sql
CREATE DATABASE importaco_etl_dis;
mysql -u root -p importaco_etl_dis < sistema/core/database/schema.sql
```

### Variáveis de Ambiente
```php
// sistema/config/environments.php
define('DB_HOST', 'localhost');
define('DB_NAME', 'importaco_etl_dis');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
```

## 📊 Módulos do Sistema

### 🏛️ Módulo Fiscal
- Cálculos tributários automatizados (II, IPI, PIS/COFINS, ICMS)
- Aplicação de benefícios fiscais por estado
- Suporte a regimes Real, Presumido, Simples
- **Responsabilidade**: Criar nomenclatura padrão

### 💼 Módulo Comercial
- Precificação segmentada B2B/B2C
- Análise de margens em tempo real
- Histórico e comparativos de preços
- **Responsabilidade**: Seguir nomenclatura fiscal

### 📈 Módulo Contábil
- Custeio completo com rateio de despesas
- Relatórios gerenciais detalhados
- Rastreabilidade de custos por dimensão
- **Responsabilidade**: Seguir nomenclatura fiscal

### 📄 Módulo Faturamento
- Geração automática de croquis NF importação
- Templates padronizados configuráveis
- Gestão de documentos fiscais
- **Responsabilidade**: Seguir nomenclatura fiscal

## 🎯 Performance

### Benchmarks Esperados
- **Processamento XML**: < 30 segundos
- **Consultas Database**: < 5 segundos
- **Cálculos Tributários**: < 2 segundos
- **Geração Relatórios**: < 10 segundos

### Otimizações
- Cache Redis para cálculos frequentes
- Índices MySQL otimizados
- Processamento assíncrono para XMLs grandes

## 🔐 Segurança

- **Autenticação JWT**: Tokens seguros stateless
- **Auditoria Completa**: Log de todas operações
- **Validação Rigorosa**: Sanitização de inputs XML
- **Backup Automático**: Proteção de dados críticos

## 📈 Status do Projeto

- [x] **Documentação**: CLAUDE.md + PRD completo
- [x] **Landing Page**: Design Expertzy oficial
- [x] **Estrutura Base**: Diretórios modulares criados
- [ ] **Core ETL**: XML Parser + Currency Calculator
- [ ] **Módulo Fiscal**: Tax Engine configurável
- [ ] **Módulos Especializados**: Comercial, Contábil, Faturamento
- [ ] **Dashboard**: Interface dinâmica
- [ ] **Deploy Web**: Configuração produção

## 🤝 Contribuição

### Padrão de Commits
```bash
git commit -m "feat: adiciona cálculo incentivo GO"
git commit -m "fix: corrige parse XML adições"
git commit -m "docs: atualiza documentação APIs"
```

### Fluxo de Desenvolvimento
1. Fork do repositório
2. Feature branch: `git checkout -b feature/nova-funcionalidade`
3. Commits seguindo convenção
4. Pull request com descrição detalhada

## 📚 Documentação

- **[CLAUDE.md](CLAUDE.md)**: Configurações e comandos
- **[PRD-Sistema-ETL-DIs.md](PRD-Sistema-ETL-DIs.md)**: Requirements completos
- **[/docs](/docs)**: Documentação técnica detalhada

## 📄 Licença

Sistema proprietário desenvolvido para automação fiscal de importações.

---

<div align="center">
  <strong>Energia • Velocidade • Força | Segurança • Intelecto • Precisão | Respeito • Proteção • Transparência</strong>

  © 2025 Sistema ETL de DI's - Padrão Expertzy
</div>