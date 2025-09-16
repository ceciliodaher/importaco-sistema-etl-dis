# Guia de Contribuição - Sistema ETL de DI's Expertzy

<div align="center">
  <img src="images/expertzy-it.png" alt="Expertzy - Inteligência Tributária" height="100">

  **Energia • Segurança • Transparência**

  Guia completo para desenvolvimento colaborativo do Sistema ETL de DI's
</div>

---

## 📋 Índice

- [Visão Geral do Projeto](#visão-geral-do-projeto)
- [Configuração do Ambiente](#configuração-do-ambiente)
- [Padrões de Desenvolvimento](#padrões-de-desenvolvimento)
- [Fluxo de Trabalho Git](#fluxo-de-trabalho-git)
- [Estrutura do Código](#estrutura-do-código)
- [Convenções de Nomenclatura](#convenções-de-nomenclatura)
- [Testes e Qualidade](#testes-e-qualidade)
- [Documentação](#documentação)
- [Revisão de Código](#revisão-de-código)
- [Deploy e Releases](#deploy-e-releases)

---

## 🎯 Visão Geral do Projeto

### Objetivo
Sistema modular para ETL (Extract, Transform, Load) de XMLs de Declarações de Importação brasileiras, com análise fiscal automatizada, precificação inteligente e dashboard dinâmico.

### Princípios Fundamentais ⚠️ **OBRIGATÓRIOS**
- ❌ **No fallbacks, no hardcoded data**
- ✅ **KISS (Keep It Simple, Stupid)**
- ✅ **DRY (Don't Repeat Yourself)** - Nunca duplicar códigos/arquivos/funções
- ✅ **Single Responsibility** - Uma função, um propósito
- ✅ **Nomenclatura Única** - Módulo que cria, nomeia; demais seguem

### Stack Tecnológico
- **Backend**: PHP 8.1+ (MVC modular)
- **Database**: MySQL 8.0+ otimizado
- **Frontend**: HTML5/CSS3/JavaScript ES6+ padrão Expertzy
- **APIs**: RESTful com JWT authentication
- **Versionamento**: Git com GitHub

---

## 🛠️ Configuração do Ambiente

### Pré-requisitos
```bash
# Verificar versões mínimas
php --version    # 8.1+
mysql --version  # 8.0+
git --version    # 2.30+
```

### Setup Inicial

#### 1. Clone do Repositório
```bash
git clone https://github.com/ceciliodaher/importaco-sistema-etl-dis.git
cd importaco-sistema-etl-dis
```

#### 2. Configuração Mac (ServBay)
```bash
# Instalar dependências
brew install php mysql

# Iniciar serviços
brew services start mysql

# Servidor de desenvolvimento
php -S localhost:8000 -t sistema/
```

#### 3. Configuração Windows (WAMP)
```bash
# 1. Instalar WAMP Server
# 2. Copiar projeto para C:\wamp64\www\importaco-sistema\
# 3. Acessar http://localhost/importaco-sistema/
```

#### 4. Banco de Dados
```sql
-- Criar banco
CREATE DATABASE importaco_etl_dis CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Executar schema (quando disponível)
mysql -u root -p importaco_etl_dis < sistema/core/database/schema.sql
```

#### 5. Configuração de Ambiente
```php
// sistema/config/environments.php
<?php
if (PHP_OS === 'Darwin') {
    // Configuração Mac
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
} elseif (PHP_OS_FAMILY === 'Windows') {
    // Configuração Windows
    define('DB_HOST', 'localhost');
    define('DB_PORT', 3306);
}
```

### Verificação da Instalação
```bash
# Testar ambiente
php sistema/tests/environment_check.php

# Acessar landing page
open http://localhost:8000  # Mac
start http://localhost/importaco-sistema/  # Windows
```

---

## 📝 Padrões de Desenvolvimento

### Regras de Negócio Específicas

#### 1. Segmentação de Cliente
```php
// CORRETO: Enum para tipos de cliente
enum ClientType: string {
    case B2B = 'business';
    case B2C = 'consumer';
}

// INCORRETO: Hardcode
if ($client === 'revenda') { ... }  // ❌
```

#### 2. Cálculo de Markup
```php
// CORRETO: Baseado em landed cost
$markup = $this->calculateMarkup($landedCost, $clientSegment);

// INCORRETO: Valores fixos
$markup = $cost * 1.30;  // ❌
```

#### 3. Lógica por Estado
```php
// CORRETO: Configurável
$benefits = $this->stateConfig->getBenefits($state, $ncm);

// INCORRETO: Hardcode por estado
if ($state === 'GO') { $icms = 0.02; }  // ❌
```

#### 4. Parsing XML
```php
// CORRETO: Parser flexível
$di = $this->xmlParser->parseDI($xmlContent);

// INCORRETO: XPath fixo
$value = $xml->xpath('//declaracao/adicao[1]/valor')[0];  // ❌
```

#### 5. Múltiplas Moedas
```php
// CORRETO: Taxa calculada dos valores DI
$exchangeRate = $this->calculateExchangeRate($vmle, $vmcv, $currency);

// INCORRETO: Taxa externa
$rate = $this->getExternalRate('USD');  // ❌
```

### Padrões de Código PHP

#### Estrutura de Classes
```php
<?php
namespace Expertzy\Core\Calculators;

use Expertzy\Core\Interfaces\CalculatorInterface;
use Expertzy\Core\Exceptions\CalculationException;

/**
 * Calculadora de câmbio dinâmica baseada em valores DI
 *
 * @package Expertzy\Core\Calculators
 * @author Sistema Expertzy
 * @since 1.0.0
 */
class CurrencyCalculator implements CalculatorInterface
{
    private NomenclatureRegistry $nomenclature;

    public function __construct(NomenclatureRegistry $nomenclature)
    {
        $this->nomenclature = $nomenclature;
    }

    /**
     * Calcula taxa de câmbio a partir dos valores VMLE/VMCV da DI
     *
     * @param float $vmle Valor da mercadoria no local de embarque
     * @param float $vmcv Valor da mercadoria na condição de venda
     * @param string $currency Código ISO da moeda
     * @return float Taxa de câmbio calculada
     * @throws CalculationException
     */
    public function calculateExchangeRate(float $vmle, float $vmcv, string $currency): float
    {
        if ($vmle <= 0 || $vmcv <= 0) {
            throw new CalculationException('Valores VMLE/VMCV devem ser positivos');
        }

        // Implementação específica
        return $this->performCalculation($vmle, $vmcv, $currency);
    }
}
```

#### Convenções de Nomenclatura
```php
// Classes: PascalCase
class TaxCalculator { }
class DiXmlParser { }

// Métodos: camelCase
public function calculateImportTax() { }
public function parseXmlDi() { }

// Variáveis: camelCase
$exchangeRate = 5.50;
$importTaxValue = 1000.00;

// Constantes: UPPER_SNAKE_CASE
const MAX_PROCESSING_TIME = 30;
const DEFAULT_CURRENCY = 'BRL';

// Arquivos: kebab-case
di-xml-parser.php
tax-calculator.php
```

### Padrões Frontend

#### HTML Estrutural
```html
<!-- CORRETO: Semântico e acessível -->
<section class="etl-dashboard">
    <header class="dashboard-header">
        <h1>Dashboard ETL</h1>
    </header>
    <main class="dashboard-content">
        <article class="module-fiscal">
            <h2>Módulo Fiscal</h2>
        </article>
    </main>
</section>
```

#### CSS Modular
```css
/* CORRETO: Nomenclatura BEM */
.module-fiscal { }
.module-fiscal__header { }
.module-fiscal__content { }
.module-fiscal--active { }

/* Variáveis CSS Expertzy */
:root {
    --expertzy-red: #FF002D;
    --expertzy-dark: #091A30;
    --expertzy-white: #FFFFFF;
}
```

#### JavaScript ES6+
```javascript
// CORRETO: Módulos ES6
class ExpertzyModule {
    constructor(config) {
        this.config = config;
        this.nomenclature = new NomenclatureRegistry();
    }

    async processData(data) {
        try {
            const result = await this.validateAndProcess(data);
            return this.formatResponse(result);
        } catch (error) {
            this.logError('PROCESSING_ERROR', error);
            throw error;
        }
    }
}

// Export/Import
export { ExpertzyModule };
import { ExpertzyModule } from './expertzy-module.js';
```

---

## 🔄 Fluxo de Trabalho Git

### Estrutura de Branches

```
master (main)           # Produção estável
├── develop            # Integração de features
├── feature/tax-engine # Feature específica
├── feature/xml-parser # Feature específica
├── hotfix/urgent-bug  # Correções urgentes
└── release/v1.0.0     # Preparação release
```

### Processo de Desenvolvimento

#### 1. Criar Feature Branch
```bash
# Sempre partir da develop
git checkout develop
git pull origin develop

# Criar feature branch
git checkout -b feature/currency-calculator
```

#### 2. Desenvolvimento
```bash
# Commits frequentes e pequenos
git add .
git commit -m "feat: adiciona base currency calculator"

git add src/calculators/
git commit -m "feat: implementa cálculo taxa câmbio DI"

git add tests/
git commit -m "test: adiciona testes currency calculator"
```

#### 3. Pull Request
```bash
# Push da feature
git push origin feature/currency-calculator

# Criar PR via GitHub
# Target: develop
# Reviewers: obrigatório 1+ aprovação
```

#### 4. Merge e Cleanup
```bash
# Após aprovação, merge via GitHub
# Deletar branch local
git checkout develop
git pull origin develop
git branch -d feature/currency-calculator
```

### Convenções de Commit

#### Formato Obrigatório
```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

#### Tipos Permitidos
- **feat**: Nova funcionalidade
- **fix**: Correção de bug
- **docs**: Documentação
- **style**: Formatação, sem mudança lógica
- **refactor**: Refatoração de código
- **test**: Adicionar/modificar testes
- **chore**: Tarefas de build, configuração

#### Exemplos
```bash
# Features
git commit -m "feat(tax): adiciona cálculo ICMS por estado"
git commit -m "feat(parser): implementa parse XML DI brasileiras"

# Fixes
git commit -m "fix(currency): corrige cálculo taxa múltiplas moedas"
git commit -m "fix(api): resolve erro 500 em POST /calculate"

# Docs
git commit -m "docs: atualiza README com setup Windows"
git commit -m "docs(api): adiciona documentação endpoints"

# Tests
git commit -m "test(parser): adiciona casos teste XML inválido"
```

---

## 🏗️ Estrutura do Código

### Organização de Diretórios

```
/importaco-sistema/
├── README.md                    # Documentação principal
├── CONTRIBUTING.md              # Este arquivo
├── CLAUDE.md                    # Configurações projeto
├── index.html                   # Landing page
├── assets/                      # Assets landing page
│   ├── css/
│   │   ├── main.css            # Estilos principais
│   │   └── expertzy-theme.css  # Tema da marca
│   └── js/
│       ├── main.js             # JavaScript principal
│       └── expertzy-interactions.js # Interações marca
├── sistema/                     # Sistema principal
│   ├── config/
│   │   ├── database.php        # Configuração BD
│   │   ├── environments.php    # Ambientes (Mac/Windows/Web)
│   │   └── nomenclature.php    # Registry central nomenclatura
│   ├── core/                   # Componentes centrais
│   │   ├── parsers/
│   │   │   └── DiXmlParser.php # Parser único DI brasileiras
│   │   ├── calculators/
│   │   │   ├── CurrencyCalculator.php # Câmbio calculado
│   │   │   ├── TaxCalculator.php      # Engine fiscal
│   │   │   └── MarkupCalculator.php   # Precificação
│   │   ├── engines/
│   │   │   ├── IncentivesEngine.php   # Benefícios fiscais
│   │   │   └── CostEngine.php         # Custeio completo
│   │   └── database/
│   │       └── schema.sql             # Schema MySQL único
│   ├── modules/                # Módulos especializados
│   │   ├── fiscal/             # Módulo Fiscal (cria nomenclatura)
│   │   │   ├── controllers/
│   │   │   ├── views/
│   │   │   ├── models/
│   │   │   └── config/
│   │   ├── commercial/         # Módulo Comercial
│   │   ├── accounting/         # Módulo Contábil
│   │   └── billing/            # Módulo Faturamento
│   ├── shared/                 # Componentes compartilhados
│   │   ├── components/         # Componentes reutilizáveis
│   │   ├── utils/              # Utilitários
│   │   └── assets/             # CSS/JS padrão sistema
│   └── data/                   # Dados do sistema
│       ├── uploads/            # XMLs carregados
│       ├── processed/          # Dados processados
│       └── exports/            # Relatórios gerados
└── docs/                       # Documentação técnica
    ├── api/                    # Documentação APIs
    ├── database/               # Documentação BD
    └── modules/                # Documentação módulos
```

### Regras de Organização

#### 1. Separação de Responsabilidades
```php
// CORRETO: Cada classe uma responsabilidade
class DiXmlParser { }      // Apenas parse XML
class TaxCalculator { }    // Apenas cálculos fiscais
class DatabaseLogger { }   // Apenas logs BD

// INCORRETO: Classe "deus"
class DiProcessor {        // ❌ Faz tudo
    public function parseXml() { }
    public function calculateTax() { }
    public function saveDatabase() { }
    public function generateReport() { }
}
```

#### 2. Nomenclatura Hierárquica
```php
// CORRETO: Módulo Fiscal define nomenclatura
namespace Expertzy\Modules\Fiscal;
class NomenclatureCreator {
    public function defineNcmCode(string $code): string { }
    public function defineCfopCode(string $code): string { }
}

// CORRETO: Outros módulos consultam
namespace Expertzy\Modules\Commercial;
class PricingEngine {
    public function __construct(NomenclatureRegistry $registry) {
        $this->nomenclature = $registry; // Usa definições do Fiscal
    }
}
```

#### 3. APIs RESTful Padronizadas
```php
// Estrutura padrão endpoints
GET    /api/v1/etl/status/{id}      # Status processamento
POST   /api/v1/etl/upload           # Upload XML DI
POST   /api/v1/fiscal/calculate     # Cálculos tributários
GET    /api/v1/commercial/pricing   # Consulta preços
POST   /api/v1/accounting/costs     # Rateio custos
GET    /api/v1/billing/croqui/{id}  # Gerar croqui
```

---

## 🔧 Convenções de Nomenclatura

### Sistema de Nomenclatura Único ⚠️ **CRÍTICO**

#### Regra Fundamental
> **"Módulo que cria, nomeia - demais seguem"**

#### Hierarquia de Nomenclatura
```
Módulo Fiscal (CREATOR) 👑
├── Define: NCM, CFOP, CST, Regimes Tributários
├── Valida: Códigos fiscais, alíquotas
└── Exporta: NomenclatureRegistry

Módulo Comercial (CONSUMER)
├── Importa: NomenclatureRegistry do Fiscal
├── Usa: Códigos definidos pelo Fiscal
└── NÃO cria: Nomenclaturas próprias

Módulo Contábil (CONSUMER)
├── Importa: NomenclatureRegistry do Fiscal
├── Usa: Códigos definidos pelo Fiscal
└── NÃO cria: Nomenclaturas próprias

Módulo Faturamento (CONSUMER)
├── Importa: NomenclatureRegistry do Fiscal
├── Usa: Códigos definidos pelo Fiscal
└── NÃO cria: Nomenclaturas próprias
```

#### Implementação Prática
```php
// FISCAL MODULE - CREATOR
namespace Expertzy\Modules\Fiscal;

class NomenclatureCreator {
    public function createNcmDefinition(string $code, array $data): NcmDefinition {
        // Fiscal module CRIA a definição
        return new NcmDefinition($code, $data);
    }

    public function registerToGlobalRegistry(NcmDefinition $definition): void {
        NomenclatureRegistry::getInstance()->register('ncm', $definition);
    }
}

// COMMERCIAL MODULE - CONSUMER
namespace Expertzy\Modules\Commercial;

class PricingEngine {
    public function getPricingByNcm(string $ncmCode): Price {
        // Commercial module USA definição do Fiscal
        $ncmDef = NomenclatureRegistry::getInstance()->get('ncm', $ncmCode);
        return $this->calculatePrice($ncmDef);
    }
}
```

### Convenções por Tipo

#### 1. Classes e Interfaces
```php
// Classes: PascalCase + sufixo descritivo
DiXmlParser           # Parser de DI
TaxCalculatorEngine   # Engine de cálculo
IncentivesManager     # Gerenciador de incentivos

// Interfaces: PascalCase + Interface
CalculatorInterface   # Interface para calculadoras
ParserInterface       # Interface para parsers
EngineInterface       # Interface para engines

// Abstracts: PascalCase + Abstract
AbstractCalculator    # Calculadora base
AbstractParser        # Parser base
```

#### 2. Métodos e Variáveis
```php
// Métodos: camelCase + verbo + objeto
calculateImportTax()     # Calcula imposto importação
parseXmlDiContent()      # Parse conteúdo XML DI
generateCostReport()     # Gera relatório custos

// Variáveis: camelCase + substantivo
$exchangeRate           # Taxa de câmbio
$importTaxValue         # Valor imposto importação
$diProcessingResult     # Resultado processamento DI

// Booleanos: is/has/can + adjetivo
$isValidXml            # XML é válido
$hasIncentives         # Tem incentivos
$canProcessDi          # Pode processar DI
```

#### 3. Constantes e Configurações
```php
// Constantes: UPPER_SNAKE_CASE
const MAX_XML_SIZE = 50 * 1024 * 1024;  # 50MB
const DEFAULT_CURRENCY = 'BRL';
const TAX_CALCULATION_TIMEOUT = 30;     # segundos

// Configurações: snake_case
$config['database_host'] = 'localhost';
$config['upload_max_size'] = '50M';
$config['cache_ttl'] = 3600;
```

#### 4. Arquivos e Diretórios
```php
// PHP: kebab-case.php
di-xml-parser.php
tax-calculator-engine.php
currency-calculator.php

// CSS: kebab-case.css
expertzy-theme.css
module-fiscal.css
dashboard-components.css

// JS: kebab-case.js
main-application.js
expertzy-interactions.js
module-commercial.js

// Diretórios: kebab-case
/modules/fiscal/
/shared/components/
/core/calculators/
```

---

## ✅ Testes e Qualidade

### Estratégia de Testes

#### 1. Pirâmide de Testes
```
                 /\
                /  \  E2E (5%)
               /____\
              /      \
             / Integration (15%)
            /__________\
           /            \
          /  Unit Tests   \  (80%)
         /________________\
```

#### 2. Estrutura de Testes
```
/tests/
├── unit/                       # Testes unitários
│   ├── core/
│   │   ├── parsers/
│   │   │   └── DiXmlParserTest.php
│   │   ├── calculators/
│   │   │   ├── CurrencyCalculatorTest.php
│   │   │   └── TaxCalculatorTest.php
│   │   └── engines/
│   │       └── IncentivesEngineTest.php
│   └── modules/
│       ├── fiscal/
│       ├── commercial/
│       ├── accounting/
│       └── billing/
├── integration/                # Testes integração
│   ├── api/
│   ├── database/
│   └── modules/
├── e2e/                       # Testes end-to-end
│   ├── complete-di-processing/
│   └── user-workflows/
└── fixtures/                  # Dados de teste
    ├── xml-samples/
    ├── expected-outputs/
    └── mock-data/
```

#### 3. Padrões de Teste Unitário
```php
<?php
namespace Tests\Unit\Core\Calculators;

use PHPUnit\Framework\TestCase;
use Expertzy\Core\Calculators\CurrencyCalculator;
use Expertzy\Core\Exceptions\CalculationException;

class CurrencyCalculatorTest extends TestCase
{
    private CurrencyCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new CurrencyCalculator();
    }

    /**
     * @test
     * @dataProvider validCurrencyDataProvider
     */
    public function deve_calcular_taxa_cambio_corretamente(
        float $vmle,
        float $vmcv,
        string $currency,
        float $expectedRate
    ): void {
        // Arrange (Setup já feito)

        // Act
        $actualRate = $this->calculator->calculateExchangeRate($vmle, $vmcv, $currency);

        // Assert
        $this->assertEquals($expectedRate, $actualRate, '', 0.001);
    }

    /**
     * @test
     */
    public function deve_lancar_excecao_para_valores_invalidos(): void
    {
        // Arrange & Assert
        $this->expectException(CalculationException::class);
        $this->expectExceptionMessage('Valores VMLE/VMCV devem ser positivos');

        // Act
        $this->calculator->calculateExchangeRate(-100, 200, 'USD');
    }

    public function validCurrencyDataProvider(): array
    {
        return [
            'USD básico' => [1000.00, 5500.00, 'USD', 5.50],
            'EUR básico' => [1000.00, 6000.00, 'EUR', 6.00],
            'CNY básico' => [1000.00, 800.00, 'CNY', 0.80],
        ];
    }
}
```

#### 4. Cobertura de Testes
```bash
# Executar testes com cobertura
./vendor/bin/phpunit --coverage-html coverage/

# Métricas obrigatórias
# - Cobertura geral: > 90%
# - Core components: 100%
# - Calculators: 100%
# - Parsers: 100%
```

### Qualidade de Código

#### 1. Análise Estática
```bash
# PHPStan (análise estática)
./vendor/bin/phpstan analyse src/ --level=8

# PHP_CodeSniffer (padrões código)
./vendor/bin/phpcs --standard=PSR12 src/

# PHP-CS-Fixer (formatação automática)
./vendor/bin/php-cs-fixer fix src/
```

#### 2. Métricas de Qualidade
```php
// Complexidade ciclomática: < 10
// Linhas por método: < 50
// Parâmetros por método: < 7
// Nível de herança: < 5

class GoodExample {
    public function calculateSimpleTax(float $value, float $rate): float
    {
        if ($value <= 0 || $rate < 0) {
            throw new InvalidArgumentException('Valores inválidos');
        }

        return $value * $rate;
    }
}
```

#### 3. Performance Benchmarks
```php
/**
 * @test
 * @group performance
 */
public function deve_processar_xml_em_menos_de_30_segundos(): void
{
    // Arrange
    $startTime = microtime(true);
    $xmlContent = file_get_contents('fixtures/large-di.xml');

    // Act
    $result = $this->parser->parseXml($xmlContent);

    // Assert
    $processingTime = microtime(true) - $startTime;
    $this->assertLessThan(30, $processingTime, 'Processamento deve ser < 30s');
    $this->assertNotNull($result);
}
```

---

## 📚 Documentação

### Documentação de Código

#### 1. PHPDoc Obrigatório
```php
/**
 * Calcula impostos de importação baseado no regime tributário
 *
 * Este método aplica as regras fiscais específicas para cada regime
 * (Real, Presumido, Simples) e considera benefícios por estado.
 *
 * @param float $value Valor base para cálculo (em BRL)
 * @param string $regime Regime tributário: 'real', 'presumido', 'simples'
 * @param string $state Código UF (ex: 'GO', 'SC', 'ES')
 * @param string $ncm Código NCM do produto
 *
 * @return TaxCalculationResult Resultado com todos os impostos calculados
 *
 * @throws CalculationException Quando parâmetros são inválidos
 * @throws UnsupportedRegimeException Quando regime não é suportado
 *
 * @example
 * ```php
 * $result = $calculator->calculateImportTax(
 *     value: 10000.00,
 *     regime: 'real',
 *     state: 'GO',
 *     ncm: '84091000'
 * );
 * ```
 *
 * @see TaxCalculationResult Para estrutura do resultado
 * @see https://expertzy.com.br/docs/tax-calculation Para documentação completa
 *
 * @since 1.0.0
 * @author Sistema Expertzy
 */
public function calculateImportTax(
    float $value,
    string $regime,
    string $state,
    string $ncm
): TaxCalculationResult {
    // Implementação
}
```

#### 2. README por Módulo
```markdown
# Módulo Fiscal - Documentação

## Responsabilidades
- Cálculos tributários (II, IPI, PIS/COFINS, ICMS)
- Criação de nomenclatura fiscal padrão
- Aplicação de benefícios fiscais por estado

## APIs Principais
- `POST /api/v1/fiscal/calculate` - Calcular impostos
- `GET /api/v1/fiscal/regimes` - Listar regimes
- `POST /api/v1/fiscal/incentives` - Aplicar incentivos

## Configuração
```php
$config = [
    'regimes' => ['real', 'presumido', 'simples'],
    'states' => ['GO', 'SC', 'ES', 'MG'],
    'cache_ttl' => 3600
];
```

## Exemplos de Uso
[Exemplos práticos aqui]
```

#### 3. Documentação de APIs
```yaml
# OpenAPI 3.0 spec
openapi: 3.0.0
info:
  title: Sistema ETL DI's API
  version: 1.0.0
  description: APIs para processamento de DI's

paths:
  /api/v1/fiscal/calculate:
    post:
      summary: Calcular impostos de importação
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              properties:
                value:
                  type: number
                  format: float
                  example: 10000.00
                regime:
                  type: string
                  enum: [real, presumido, simples]
                  example: "real"
```

### Wiki e Conhecimento

#### 1. Estrutura Wiki
```
/docs/wiki/
├── onboarding/
│   ├── setup-ambiente.md
│   ├── primeira-contribuicao.md
│   └── arquitetura-overview.md
├── tutoriais/
│   ├── como-adicionar-modulo.md
│   ├── como-criar-calculadora.md
│   └── como-testar-xml-parser.md
├── troubleshooting/
│   ├── problemas-comuns.md
│   ├── debug-guia.md
│   └── performance-issues.md
└── rfcs/
    ├── rfc-001-nomenclature-system.md
    ├── rfc-002-currency-calculation.md
    └── rfc-003-module-communication.md
```

#### 2. Changelog Estruturado
```markdown
# Changelog

## [1.1.0] - 2025-10-15

### Added
- Suporte a múltiplas moedas no XML parser
- Cálculo automático de taxa de câmbio
- Dashboard de análise em tempo real

### Changed
- Refatoração do sistema de nomenclatura
- Otimização de performance em 40%
- Atualização documentação APIs

### Fixed
- Correção bug cálculo ICMS Santa Catarina
- Resolução erro timeout em XMLs grandes
- Fix validação campos obrigatórios DI

### Security
- Implementação rate limiting APIs
- Validação rigorosa inputs XML
- Auditoria completa de operações
```

---

## 🔍 Revisão de Código

### Processo de Code Review

#### 1. Checklist Obrigatório
```markdown
## ✅ Checklist Review

### Funcionalidade
- [ ] Feature funciona conforme especificado
- [ ] Casos edge tratados adequadamente
- [ ] Validação de inputs implementada
- [ ] Error handling apropriado

### Código
- [ ] Segue padrões de nomenclatura
- [ ] Não duplica código existente
- [ ] Complexidade ciclomática < 10
- [ ] Métodos com < 50 linhas

### Testes
- [ ] Testes unitários adicionados
- [ ] Cobertura > 90%
- [ ] Casos edge testados
- [ ] Performance testada

### Documentação
- [ ] PHPDoc completo
- [ ] README atualizado se necessário
- [ ] Changelog atualizado
- [ ] APIs documentadas

### Segurança
- [ ] Inputs sanitizados
- [ ] SQL injection prevenido
- [ ] Dados sensíveis protegidos
- [ ] Logs sem informações sensíveis
```

#### 2. Critérios de Aprovação
```markdown
## 🎯 Critérios Aprovação

### Obrigatórios (Blocking)
- ✅ Todos os testes passando
- ✅ Cobertura > 90%
- ✅ Análise estática sem erros
- ✅ 1+ aprovação de reviewer senior

### Recomendados (Non-blocking)
- 📝 Documentação completa
- 🚀 Performance otimizada
- 🔒 Segurança validada
- 📊 Métricas de qualidade OK
```

#### 3. Templates de Review
```markdown
## 🔍 Review Template

### Resumo
Breve descrição das alterações e impacto.

### Pontos Positivos ✅
- Implementação clara e objetiva
- Boa cobertura de testes
- Documentação adequada

### Pontos de Melhoria 🔧
- [ ] Refatorar método X para reduzir complexidade
- [ ] Adicionar validação Y
- [ ] Melhorar error message Z

### Perguntas ❓
- Por que escolheu implementação X ao invés de Y?
- Considerou o impacto na performance?

### Decisão
- [ ] Aprovado ✅
- [ ] Aprovado com ressalvas ⚠️
- [ ] Precisa alterações ❌
```

---

## 🚀 Deploy e Releases

### Estratégia de Deploy

#### 1. Ambientes
```
Development  → Develop branch
Staging      → Release branch
Production   → Master branch
```

#### 2. Pipeline CI/CD
```yaml
# .github/workflows/ci.yml
name: CI/CD Pipeline

on:
  push:
    branches: [ develop, master ]
  pull_request:
    branches: [ develop ]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: ./vendor/bin/phpunit
      - name: Static analysis
        run: ./vendor/bin/phpstan analyse
```

#### 3. Versionamento Semântico
```
MAJOR.MINOR.PATCH

1.0.0 → 1.0.1 (patch: bug fixes)
1.0.1 → 1.1.0 (minor: new features)
1.1.0 → 2.0.0 (major: breaking changes)
```

### Processo de Release

#### 1. Preparação Release
```bash
# 1. Criar release branch
git checkout develop
git pull origin develop
git checkout -b release/v1.1.0

# 2. Atualizar versões
echo "1.1.0" > VERSION
# Atualizar composer.json, package.json, etc.

# 3. Executar testes completos
./scripts/run-all-tests.sh

# 4. Gerar changelog
./scripts/generate-changelog.sh

# 5. Commit release
git add .
git commit -m "chore: prepare release v1.1.0"
```

#### 2. Deploy Production
```bash
# 1. Merge para master
git checkout master
git merge release/v1.1.0

# 2. Tag da release
git tag -a v1.1.0 -m "Release v1.1.0"

# 3. Push
git push origin master
git push origin v1.1.0

# 4. Deploy automático via CI/CD
```

#### 3. Rollback de Emergência
```bash
# Em caso de problemas críticos
git checkout master
git revert HEAD
git push origin master

# Ou rollback para versão anterior
git reset --hard v1.0.0
git push origin master --force-with-lease
```

---

## 🆘 Suporte e Troubleshooting

### Canais de Comunicação

#### 1. GitHub Issues
```markdown
## 🐛 Bug Report Template

### Descrição
Descrição clara e concisa do bug.

### Reprodução
Passos para reproduzir:
1. Acesse '...'
2. Clique em '...'
3. Erro aparece

### Comportamento Esperado
O que deveria acontecer.

### Screenshots
Se aplicável, adicione screenshots.

### Ambiente
- OS: [ex. Windows 10, macOS 12]
- Browser: [ex. Chrome 96, Safari 15]
- Versão: [ex. v1.0.0]

### Logs
```
[Cole logs relevantes aqui]
```

### Contexto Adicional
Qualquer informação adicional sobre o problema.
```

#### 2. Debugging Guide
```markdown
## 🔧 Debug Guide

### PHP Errors
```bash
# Ativar debug mode
export APP_DEBUG=true

# Verificar logs
tail -f sistema/data/logs/error.log

# Debug específico
php -d display_errors=1 script.php
```

### Database Issues
```sql
-- Verificar conexão
SELECT 1;

-- Verificar tabelas
SHOW TABLES;

-- Verificar índices
SHOW INDEX FROM dis;
```

### Performance Issues
```bash
# Profile PHP
php -d xdebug.mode=profile script.php

# Monitor MySQL
SHOW PROCESSLIST;

# Verificar memoria
php -d memory_limit=-1 script.php
```
```

### Contatos de Emergência

#### 1. Escalação
```
Nível 1: GitHub Issues
Nível 2: Email direto
Nível 3: Telefone emergência
```

#### 2. Horários de Suporte
```
Business Hours: 08:00 - 18:00 (GMT-3)
Emergency: 24/7 para bugs críticos
Response Time: < 4h business hours
```

---

## 📞 Contatos e Recursos

### Links Importantes
- **Repositório**: https://github.com/ceciliodaher/importaco-sistema-etl-dis
- **Documentação**: https://github.com/ceciliodaher/importaco-sistema-etl-dis/wiki
- **Issues**: https://github.com/ceciliodaher/importaco-sistema-etl-dis/issues
- **Releases**: https://github.com/ceciliodaher/importaco-sistema-etl-dis/releases

### Ferramentas Recomendadas
- **IDE**: VSCode, PhpStorm
- **Git GUI**: GitKraken, Sourcetree
- **Database**: phpMyAdmin, MySQL Workbench
- **API Testing**: Postman, Insomnia

---

<div align="center">

### 🎯 **Lembre-se**: KISS + DRY + Nomenclatura Única

**Energia • Velocidade • Força | Segurança • Intelecto • Precisão | Respeito • Proteção • Transparência**

© 2025 Sistema ETL de DI's - Padrão Expertzy

</div>