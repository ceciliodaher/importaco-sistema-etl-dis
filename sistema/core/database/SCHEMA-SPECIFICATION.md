# ESPECIFICAÇÃO DO SCHEMA MYSQL - SISTEMA ETL DE DI's

<div align="center">
  <strong>Padrão Expertzy: Energia • Segurança • Transparência</strong>

  Especificação técnica baseada em análise real de XMLs DI brasileiras
</div>

---

## 📋 RESUMO EXECUTIVO

Este documento especifica o schema MySQL otimizado para o Sistema ETL de DI's, baseado na análise detalhada de 3 XMLs reais de Declaração de Importação:

- **DI 2300120746**: 16 adições, múltiplas mercadorias, origem China
- **DI 2518173187**: 1 adição, produto químico, origem Índia
- **DI 2520345968**: 1 adição, luvas médicas, origem Argentina/Uruguai

### Características do Schema
- **12 tabelas principais** organizadas hierarquicamente
- **Conversões automáticas** para formatos Siscomex
- **Relacionamentos 1:N** bem definidos
- **Índices otimizados** para performance
- **Constraints robustas** para integridade

---

## 🏗️ ARQUITETURA GERAL

### Hierarquia de Dados
```
Declaração de Importação (DI)
├── Adições (1:N)
│   ├── Mercadorias (1:N)
│   ├── Impostos (1:N)
│   └── Acordos Tarifários (0:N)
├── ICMS Detalhado (0:1)
├── Pagamentos Siscomex (1:N)
├── Despesas Frete/Seguro (0:N)
└── Despesas Extras (0:N)
```

### Tabelas de Referência
- Moedas Siscomex
- NCM Classificação
- Conversões e Auditoria

---

## 📊 ESPECIFICAÇÃO DETALHADA DAS TABELAS

### 1. **declaracoes_importacao** (Tabela Principal)

**Propósito:** Armazenar dados principais de cada DI processada
**Origem:** Elemento raiz `<declaracaoImportacao>` dos XMLs

| Campo | Tipo | Origem XML | Conversão | Exemplo |
|-------|------|------------|-----------|---------|
| `numero_di` | VARCHAR(10) PRIMARY KEY | `numeroDI` | Direto | '2300120746' |
| `data_registro` | DATE NOT NULL | `dataRegistro` | YYYYMMDD→DATE | '20230102'→'2023-01-02' |
| `urf_despacho_codigo` | VARCHAR(7) | `urfDespacho/codigo` | Direto | '717600' |
| `urf_despacho_nome` | VARCHAR(100) | `urfDespacho/nome` | Direto | 'PORTO DE SANTOS' |
| `importador_cnpj` | CHAR(14) NOT NULL | `importador/numeroInscricao` | Direto | '12345678000190' |
| `importador_nome` | VARCHAR(255) NOT NULL | `importador/nome` | Direto | 'EMPRESA LTDA' |
| `canal_selecao` | CHAR(1) | `canalSelecaoParametrizada` | Direto | 'V' |
| `caracteristica_operacao` | VARCHAR(10) | `caracteristicaOperacao` | Direto | 'IMPORTACAO' |
| `total_adicoes` | TINYINT UNSIGNED | COUNT(adicao) | Calculado | 16 |
| `valor_total_cif_usd` | DECIMAL(15,2) | SUM(localEmbarque/totalDolares) | /100 | 33112.20 |
| `valor_total_cif_brl` | DECIMAL(15,2) | SUM(localEmbarque/totalReais) | /100 | 178591.26 |
| `status_processamento` | ENUM('PENDENTE','PROCESSANDO','COMPLETO','ERRO') | Sistema | - | 'COMPLETO' |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |
| `updated_at` | TIMESTAMP | Sistema | NOW() ON UPDATE | '2025-09-15 20:30:00' |

**Relacionamentos:**
- 1:N com `adicoes`
- 1:1 com `icms_detalhado` (opcional)
- 1:N com `pagamentos_siscomex`
- 1:N com `despesas_frete_seguro`
- 1:N com `despesas_extras`

**Índices:**
```sql
PRIMARY KEY (numero_di)
INDEX idx_data_registro (data_registro)
INDEX idx_importador_cnpj (importador_cnpj)
INDEX idx_status_data (status_processamento, data_registro)
INDEX idx_valor_total (valor_total_cif_brl)
```

---

### 2. **adicoes** (Itens da DI)

**Propósito:** Cada item/linha da DI (1 DI pode ter N adições)
**Origem:** Array `<adicao>` dentro de cada DI

| Campo | Tipo | Origem XML | Conversão | Exemplo |
|-------|------|------------|-----------|---------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | - | Gerado | 1 |
| `numero_di` | VARCHAR(10) NOT NULL | FK para DI | Direto | '2300120746' |
| `numero_adicao` | VARCHAR(3) NOT NULL | `numeroAdicao` | Direto | '001' |
| `numero_sequencial_item` | VARCHAR(10) | `numeroSequencialItem` | Direto | '0000000001' |
| `codigo_fabricante` | VARCHAR(50) | `fabricante/codigo` | Direto | 'FAB123' |
| `ncm` | CHAR(8) NOT NULL | `dadosMercadoria/codigoNcm` | Direto | '73181500' |
| `valor_vmle_moeda` | DECIMAL(15,2) NOT NULL | `localEmbarque/totalDolares` | /100 | 33112.20 |
| `valor_vmle_reais` | DECIMAL(15,2) NOT NULL | `localEmbarque/totalReais` | /100 | 178591.26 |
| `valor_vmcv_moeda` | DECIMAL(15,2) NOT NULL | `condicaoVenda/valorMoeda` | /100 | 33112.20 |
| `valor_vmcv_reais` | DECIMAL(15,2) NOT NULL | `condicaoVenda/valorReais` | /100 | 178591.26 |
| `taxa_cambio_calculada` | DECIMAL(10,6) GENERATED ALWAYS AS (valor_vmcv_reais/valor_vmcv_moeda) STORED | Computed | vmcv_reais/vmcv_moeda | 5.392800 |
| `moeda_codigo` | CHAR(3) NOT NULL | `condicaoVenda/moedaCodigo` | Lookup | '220'→'USD' |
| `moeda_nome` | VARCHAR(50) | `condicaoVenda/moedaNome` | Direto | 'DOLAR DOS EUA' |
| `peso_liquido` | DECIMAL(12,3) | `dadosCarga/pesoLiquido` | /1000 | 213.480 |
| `peso_bruto` | DECIMAL(12,3) | `dadosCarga/pesoBruto` | /1000 | 250.000 |
| `numero_li` | VARCHAR(20) | `numeroLi` | Direto | 'LI123456789' |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |

**Relacionamentos:**
- N:1 com `declaracoes_importacao`
- 1:N com `mercadorias`
- 1:N com `impostos_adicao`
- 1:N com `acordos_tarifarios`

**Índices:**
```sql
PRIMARY KEY (id)
UNIQUE KEY uk_di_adicao (numero_di, numero_adicao)
INDEX idx_ncm (ncm)
INDEX idx_valor_vmcv (valor_vmcv_reais)
INDEX idx_moeda (moeda_codigo)
INDEX idx_taxa_cambio (taxa_cambio_calculada)
FOREIGN KEY fk_adicao_di (numero_di) REFERENCES declaracoes_importacao(numero_di)
```

---

### 3. **mercadorias** (Produtos por Adição)

**Propósito:** Produtos específicos dentro de cada adição (1 adição pode ter N mercadorias)
**Origem:** Array `<mercadoria>` dentro de cada `<adicao>`

| Campo | Tipo | Origem XML | Conversão | Exemplo |
|-------|------|------------|-----------|---------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | - | Gerado | 1 |
| `adicao_id` | BIGINT UNSIGNED NOT NULL | FK para adicoes | Direto | 1 |
| `numero_sequencial` | VARCHAR(3) | `numeroSequencial` | Direto | '001' |
| `descricao` | TEXT NOT NULL | `descricao` | Direto | 'PARAFUSOS DE ACO INOX M6X20MM' |
| `quantidade` | DECIMAL(12,5) NOT NULL | `quantidade` | /100000 | 213.48000 |
| `unidade_medida` | VARCHAR(20) | `unidadeMedida` | Direto | 'QUILOGRAMA' |
| `valor_unitario_moeda` | DECIMAL(15,8) GENERATED ALWAYS AS (quantidade > 0 ? (SELECT valor_vmcv_moeda FROM adicoes WHERE id = adicao_id) / quantidade : 0) STORED | Calculado | vmcv/qtd | 155.12345678 |
| `especificacao_mercadoria` | TEXT | `especificacaoMercadoria` | Direto | 'Aço inoxidável AISI 316' |
| `condicao_mercadoria` | VARCHAR(50) | `condicaoMercadoria` | Direto | 'NOVA' |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |

**Relacionamentos:**
- N:1 com `adicoes`

**Índices:**
```sql
PRIMARY KEY (id)
INDEX idx_adicao_id (adicao_id)
FULLTEXT INDEX ft_descricao (descricao)
FULLTEXT INDEX ft_especificacao (especificacao_mercadoria)
FOREIGN KEY fk_mercadoria_adicao (adicao_id) REFERENCES adicoes(id) ON DELETE CASCADE
```

---

### 4. **impostos_adicao** (Impostos por Adição)

**Propósito:** Impostos calculados/informados para cada adição
**Origem:** Vários elementos dentro de `<adicao>`

| Campo | Tipo | Origem XML | Conversão | Exemplo |
|-------|------|------------|-----------|---------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | - | Gerado | 1 |
| `adicao_id` | BIGINT UNSIGNED NOT NULL | FK para adicoes | Direto | 1 |
| `tipo_imposto` | ENUM('II','IPI','PIS','COFINS','ICMS') NOT NULL | Manual | - | 'II' |
| `base_calculo` | DECIMAL(15,2) | `{imposto}BaseCalculo` | /100 | 178591.26 |
| `aliquota_ad_valorem` | DECIMAL(7,4) | `{imposto}AliquotaAdValorem` | /10000 | 16.0000 |
| `valor_devido` | DECIMAL(15,2) | `{imposto}ValorDevido` | /100 | 28574.60 |
| `valor_recolher` | DECIMAL(15,2) | `{imposto}ValorRecolher` | /100 | 28574.60 |
| `valor_devido_reais` | DECIMAL(15,2) | `{imposto}ValorDevidoReais` | /100 | 154102.40 |
| `situacao_tributaria` | VARCHAR(10) | `{imposto}Situacao` | Direto | 'DEVIDA' |
| `aliquota_especifica` | DECIMAL(15,8) | `{imposto}AliquotaEspecifica` | /100000000 | 0.00000000 |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |

**Casos Especiais:**
- **II Ausente:** Quando aliquota = 0 ou acordo tarifário
- **ICMS Vazio:** Campo `<icms/>` presente mas vazio = exoneração
- **ICMS Ausente:** Campo não existe = não se aplica

**Relacionamentos:**
- N:1 com `adicoes`

**Índices:**
```sql
PRIMARY KEY (id)
UNIQUE KEY uk_adicao_imposto (adicao_id, tipo_imposto)
INDEX idx_tipo_imposto (tipo_imposto)
INDEX idx_valor_devido (valor_devido_reais)
INDEX idx_situacao (situacao_tributaria)
FOREIGN KEY fk_imposto_adicao (adicao_id) REFERENCES adicoes(id) ON DELETE CASCADE
```

---

### 5. **acordos_tarifarios** (Acordos Internacionais)

**Propósito:** Acordos internacionais que reduzem/eliminam II
**Origem:** Elementos `{imposto}AcordoTarifario*` quando presentes

| Campo | Tipo | Origem XML | Conversão | Exemplo |
|-------|------|------------|-----------|---------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | - | Gerado | 1 |
| `adicao_id` | BIGINT UNSIGNED NOT NULL | FK para adicoes | Direto | 1 |
| `tipo_acordo` | VARCHAR(20) NOT NULL | Nome do elemento XML | Parse | 'MERCOSUL' |
| `codigo_acordo` | VARCHAR(10) | `codigo` | Direto | 'ACE035' |
| `aliquota_acordo` | DECIMAL(7,4) | `aliquota` | /10000 | 0.0000 |
| `percentual_reducao` | DECIMAL(5,2) GENERATED ALWAYS AS ((SELECT aliquota_ad_valorem FROM impostos_adicao WHERE adicao_id = acordos_tarifarios.adicao_id AND tipo_imposto = 'II') - aliquota_acordo) / (SELECT aliquota_ad_valorem FROM impostos_adicao WHERE adicao_id = acordos_tarifarios.adicao_id AND tipo_imposto = 'II') * 100) STORED | Calculado | % redução | 100.00 |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |

**Relacionamentos:**
- N:1 com `adicoes`

**Índices:**
```sql
PRIMARY KEY (id)
INDEX idx_adicao_id (adicao_id)
INDEX idx_tipo_acordo (tipo_acordo)
INDEX idx_percentual_reducao (percentual_reducao)
FOREIGN KEY fk_acordo_adicao (adicao_id) REFERENCES adicoes(id) ON DELETE CASCADE
```

---

### 6. **icms_detalhado** (ICMS Específico)

**Propósito:** Detalhamento específico do ICMS quando presente
**Origem:** Elemento `<icms>` da DI (quando não vazio)

| Campo | Tipo | Origem XML | Conversão | Exemplo |
|-------|------|------------|-----------|---------|
| `numero_di` | VARCHAR(10) PRIMARY KEY | FK para DI | Direto | '2518173187' |
| `uf_icms` | CHAR(2) | `uf` | Direto | 'SC' |
| `valor_total_icms` | DECIMAL(15,2) | `valorTotalIcms` | /100 | 0.00 |
| `codigo_receita` | VARCHAR(10) | `codigoReceita` | Direto | '10101' |
| `situacao` | ENUM('NAO_APLICA','EXONERADO','DEVIDO') GENERATED ALWAYS AS (
    CASE
        WHEN valor_total_icms IS NULL THEN 'NAO_APLICA'
        WHEN valor_total_icms = 0 THEN 'EXONERADO'
        ELSE 'DEVIDO'
    END
) STORED | Inferido | - | 'EXONERADO' |
| `observacoes` | TEXT | `observacoes` | Direto | 'Exoneração conforme decreto' |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |

**Estados Possíveis:**
- **Campo ausente:** ICMS não se aplica
- **Campo vazio `<icms/>`:** ICMS exonerado
- **Campo com dados:** ICMS devido

**Relacionamentos:**
- 1:1 com `declaracoes_importacao`

**Índices:**
```sql
PRIMARY KEY (numero_di)
INDEX idx_uf_icms (uf_icms)
INDEX idx_situacao (situacao)
INDEX idx_valor_total (valor_total_icms)
FOREIGN KEY fk_icms_di (numero_di) REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE
```

---

### 7. **pagamentos_siscomex** (Taxas Siscomex)

**Propósito:** Taxas e pagamentos do Siscomex
**Origem:** Array `<pagamento>` da DI

| Campo | Tipo | Origem XML | Conversão | Exemplo |
|-------|------|------------|-----------|---------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | - | Gerado | 1 |
| `numero_di` | VARCHAR(10) NOT NULL | FK para DI | Direto | '2300120746' |
| `codigo_receita` | VARCHAR(10) NOT NULL | `codigoReceita` | Direto | '7811' |
| `nome_receita` | VARCHAR(100) | Lookup/Manual | Por código | 'Taxa Siscomex' |
| `valor_multa` | DECIMAL(15,2) | `valorMulta` | /100 | 0.00 |
| `valor_juros` | DECIMAL(15,2) | `valorJuros` | /100 | 0.00 |
| `valor_receita` | DECIMAL(15,2) NOT NULL | `valorReceita` | /100 | 214.75 |
| `data_vencimento` | DATE | `dataVencimento` | YYYYMMDD→DATE | '2023-01-15' |
| `data_pagamento` | DATE | `dataPagamento` | YYYYMMDD→DATE | '2023-01-10' |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |

**Relacionamentos:**
- N:1 com `declaracoes_importacao`

**Índices:**
```sql
PRIMARY KEY (id)
INDEX idx_numero_di (numero_di)
INDEX idx_codigo_receita (codigo_receita)
INDEX idx_valor_receita (valor_receita)
INDEX idx_data_pagamento (data_pagamento)
FOREIGN KEY fk_pagamento_di (numero_di) REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE
```

---

### 8. **despesas_frete_seguro** (Frete e Seguro Internacional)

**Propósito:** Despesas de frete e seguro internacional
**Origem:** Elementos `frete*` e `seguro*` da DI

| Campo | Tipo | Origem XML | Conversão | Exemplo |
|-------|------|------------|-----------|---------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | - | Gerado | 1 |
| `numero_di` | VARCHAR(10) NOT NULL | FK para DI | Direto | '2518173187' |
| `tipo_despesa` | ENUM('FRETE','SEGURO') NOT NULL | Manual | - | 'FRETE' |
| `valor_moeda_negociada` | DECIMAL(15,2) | `{tipo}TotalMoedaNegociada` | /100 | 2000.00 |
| `moeda_negociada_codigo` | CHAR(3) | `{tipo}MoedaNegociadaCodigo` | Lookup | '860' |
| `moeda_negociada_nome` | VARCHAR(50) | `{tipo}MoedaNegociadaNome` | Direto | 'RUPIA INDIANA' |
| `valor_dolares` | DECIMAL(15,2) | `{tipo}TotalDolares` | /100 | 240.00 |
| `valor_reais` | DECIMAL(15,2) | `{tipo}TotalReais` | /100 | 1294.32 |
| `prepago` | BOOLEAN | `{tipo}Prepago` | S/N→BOOL | TRUE |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |

**Relacionamentos:**
- N:1 com `declaracoes_importacao`

**Índices:**
```sql
PRIMARY KEY (id)
INDEX idx_numero_di (numero_di)
INDEX idx_tipo_despesa (tipo_despesa)
INDEX idx_moeda_negociada (moeda_negociada_codigo)
INDEX idx_valor_reais (valor_reais)
FOREIGN KEY fk_despesa_di (numero_di) REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE
```

---

### 9. **despesas_extras** (Despesas Não Incluídas na DI)

**Propósito:** Despesas não incluídas na DI (informadas separadamente)
**Origem:** Sistema (não XML) - input manual/outros sistemas

| Campo | Tipo | Origem | Conversão | Exemplo |
|-------|------|--------|-----------|---------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | Sistema | Gerado | 1 |
| `numero_di` | VARCHAR(10) NOT NULL | FK para DI | Manual | '2300120746' |
| `categoria` | ENUM('PORTUARIO','BANCARIO','DESPACHANTE','ARMAZENAGEM','OUTROS') NOT NULL | Manual | - | 'PORTUARIO' |
| `descricao` | VARCHAR(255) NOT NULL | Manual | - | 'Taxa de Armazenagem Porto' |
| `valor` | DECIMAL(15,2) NOT NULL | Manual | - | 500.00 |
| `moeda` | CHAR(3) DEFAULT 'BRL' | Manual | - | 'BRL' |
| `compoe_base_icms` | BOOLEAN DEFAULT FALSE | Configurável | - | TRUE |
| `criterio_rateio` | ENUM('VALOR','PESO','QUANTIDADE','MANUAL') DEFAULT 'VALOR' | Configurável | - | 'VALOR' |
| `dados_rateio` | JSON | Manual | - | '{"percentuais": [50, 30, 20]}' |
| `created_by` | VARCHAR(100) | Sistema | - | 'user@empresa.com' |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |

**Relacionamentos:**
- N:1 com `declaracoes_importacao`

**Índices:**
```sql
PRIMARY KEY (id)
INDEX idx_numero_di (numero_di)
INDEX idx_categoria (categoria)
INDEX idx_compoe_icms (compoe_base_icms)
INDEX idx_criterio_rateio (criterio_rateio)
INDEX idx_created_at (created_at)
FOREIGN KEY fk_despesa_extra_di (numero_di) REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE
```

---

### 10. **moedas_referencia** (Tabela de Moedas)

**Propósito:** Mapeamento de códigos de moeda do Siscomex
**Origem:** Tabela de referência (não XML)

| Campo | Tipo | Origem | Conversão | Exemplo |
|-------|------|--------|-----------|---------|
| `codigo_siscomex` | CHAR(3) PRIMARY KEY | Manual | - | '220' |
| `codigo_iso` | CHAR(3) UNIQUE NOT NULL | Manual | - | 'USD' |
| `nome_moeda` | VARCHAR(50) NOT NULL | Manual | - | 'DOLAR DOS EUA' |
| `simbolo` | VARCHAR(5) | Manual | - | 'US$' |
| `decimal_places` | TINYINT DEFAULT 2 | Manual | - | 2 |
| `ativo` | BOOLEAN DEFAULT TRUE | Manual | - | TRUE |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |
| `updated_at` | TIMESTAMP | Sistema | NOW() ON UPDATE | '2025-09-15 20:30:00' |

**Dados Iniciais:**
```sql
INSERT INTO moedas_referencia VALUES
('220', 'USD', 'DOLAR DOS EUA', 'US$', 2, TRUE, NOW(), NOW()),
('860', 'INR', 'RUPIA INDIANA', '₹', 2, TRUE, NOW(), NOW()),
('032', 'ARS', 'PESO ARGENTINO', '$', 2, TRUE, NOW(), NOW()),
('156', 'CNY', 'YUAN RENMINBI', '¥', 2, TRUE, NOW(), NOW()),
('978', 'EUR', 'EURO', '€', 2, TRUE, NOW(), NOW());
```

**Relacionamentos:**
- Referenciada por outras tabelas via `moeda_codigo`

**Índices:**
```sql
PRIMARY KEY (codigo_siscomex)
UNIQUE KEY uk_codigo_iso (codigo_iso)
INDEX idx_ativo (ativo)
```

---

### 11. **ncm_referencia** (Classificação NCM)

**Propósito:** Classificação fiscal NCM com alíquotas padrão
**Origem:** Tabela de referência + atualizações RFB

| Campo | Tipo | Origem | Conversão | Exemplo |
|-------|------|--------|-----------|---------|
| `codigo_ncm` | CHAR(8) PRIMARY KEY | Manual | - | '73181500' |
| `descricao` | TEXT NOT NULL | Manual | - | 'Parafusos rosqueados' |
| `unidade_estatistica` | VARCHAR(10) | Manual | - | 'KG' |
| `aliquota_ii_padrao` | DECIMAL(7,4) DEFAULT 0.0000 | Manual | - | 16.0000 |
| `aliquota_ipi_padrao` | DECIMAL(7,4) DEFAULT 0.0000 | Manual | - | 6.5000 |
| `ex_tarifario` | BOOLEAN DEFAULT FALSE | Manual | - | FALSE |
| `observacoes` | TEXT | Manual | - | 'Sujeito a Ex tarifário' |
| `data_inicio_vigencia` | DATE | Manual | - | '2023-01-01' |
| `data_fim_vigencia` | DATE | Manual | - | NULL |
| `ativo` | BOOLEAN DEFAULT TRUE | Manual | - | TRUE |
| `created_at` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |
| `updated_at` | TIMESTAMP | Sistema | NOW() ON UPDATE | '2025-09-15 20:30:00' |

**Relacionamentos:**
- Referenciada por `adicoes` via `ncm`

**Índices:**
```sql
PRIMARY KEY (codigo_ncm)
FULLTEXT INDEX ft_descricao (descricao)
INDEX idx_aliquota_ii (aliquota_ii_padrao)
INDEX idx_vigencia (data_inicio_vigencia, data_fim_vigencia)
INDEX idx_ativo (ativo)
```

---

### 12. **conversao_valores** (Log de Conversões)

**Propósito:** Log de conversões de valores realizadas
**Origem:** Sistema (auditoria das conversões)

| Campo | Tipo | Origem | Conversão | Exemplo |
|-------|------|--------|-----------|---------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY | Sistema | Gerado | 1 |
| `numero_di` | VARCHAR(10) NOT NULL | FK para DI | Direto | '2300120746' |
| `tabela_origem` | VARCHAR(50) NOT NULL | Sistema | - | 'adicoes' |
| `campo_origem` | VARCHAR(50) NOT NULL | Sistema | - | 'valor_vmcv_reais' |
| `valor_original` | VARCHAR(50) | XML | - | '000000017859126' |
| `valor_convertido` | DECIMAL(15,2) | Sistema | - | 178591.26 |
| `tipo_conversao` | VARCHAR(20) NOT NULL | Sistema | - | 'DIVISAO_100' |
| `funcao_utilizada` | VARCHAR(100) | Sistema | - | 'fn_convert_siscomex_money' |
| `timestamp_conversao` | TIMESTAMP | Sistema | NOW() | '2025-09-15 20:30:00' |

**Relacionamentos:**
- N:1 com `declaracoes_importacao`

**Índices:**
```sql
PRIMARY KEY (id)
INDEX idx_numero_di (numero_di)
INDEX idx_tabela_campo (tabela_origem, campo_origem)
INDEX idx_tipo_conversao (tipo_conversao)
INDEX idx_timestamp (timestamp_conversao)
FOREIGN KEY fk_conversao_di (numero_di) REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE
```

---

## 🔧 FUNÇÕES SQL DE CONVERSÃO

### 1. Converter Valores Monetários Siscomex
```sql
DELIMITER $$
CREATE FUNCTION fn_convert_siscomex_money(valor_string VARCHAR(15))
RETURNS DECIMAL(15,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE valor_numeric DECIMAL(15,2);

    -- Remove zeros à esquerda e divide por 100
    SET valor_numeric = CAST(TRIM(LEADING '0' FROM valor_string) AS DECIMAL(15,0)) / 100;

    RETURN IFNULL(valor_numeric, 0.00);
END$$
DELIMITER ;

-- Uso: SELECT fn_convert_siscomex_money('000000017859126') → 178591.26
```

### 2. Converter Alíquotas
```sql
DELIMITER $$
CREATE FUNCTION fn_convert_siscomex_rate(aliquota_string VARCHAR(10))
RETURNS DECIMAL(7,4)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE aliquota_numeric DECIMAL(7,4);

    -- Remove zeros à esquerda e divide por 10000
    SET aliquota_numeric = CAST(TRIM(LEADING '0' FROM aliquota_string) AS DECIMAL(7,0)) / 10000;

    RETURN IFNULL(aliquota_numeric, 0.0000);
END$$
DELIMITER ;

-- Uso: SELECT fn_convert_siscomex_rate('01600') → 16.0000
```

### 3. Calcular Taxa de Câmbio
```sql
DELIMITER $$
CREATE FUNCTION fn_calculate_exchange_rate(valor_brl DECIMAL(15,2), valor_usd DECIMAL(15,2))
RETURNS DECIMAL(10,6)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE taxa DECIMAL(10,6);

    IF valor_usd > 0 THEN
        SET taxa = valor_brl / valor_usd;
    ELSE
        SET taxa = NULL;
    END IF;

    RETURN taxa;
END$$
DELIMITER ;

-- Uso: SELECT fn_calculate_exchange_rate(178591.26, 33112.20) → 5.392800
```

### 4. Converter Data Siscomex
```sql
DELIMITER $$
CREATE FUNCTION fn_convert_siscomex_date(data_string VARCHAR(8))
RETURNS DATE
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE data_convertida DATE;

    IF LENGTH(data_string) = 8 AND data_string REGEXP '^[0-9]{8}$' THEN
        SET data_convertida = STR_TO_DATE(data_string, '%Y%m%d');
    ELSE
        SET data_convertida = NULL;
    END IF;

    RETURN data_convertida;
END$$
DELIMITER ;

-- Uso: SELECT fn_convert_siscomex_date('20230102') → '2023-01-02'
```

---

## 📊 VIEWS CONSOLIDADAS

### 1. Resumo por DI
```sql
CREATE VIEW v_di_resumo AS
SELECT
    di.numero_di,
    di.data_registro,
    di.importador_nome,
    di.importador_cnpj,
    di.total_adicoes,
    di.valor_total_cif_brl,

    -- Totais de impostos
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'II' THEN imp.valor_devido_reais ELSE 0 END), 0) as total_ii,
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.valor_devido_reais ELSE 0 END), 0) as total_ipi,
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'PIS' THEN imp.valor_devido_reais ELSE 0 END), 0) as total_pis,
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'COFINS' THEN imp.valor_devido_reais ELSE 0 END), 0) as total_cofins,
    COALESCE(icms.valor_total_icms, 0) as total_icms,

    -- Totais consolidados
    di.valor_total_cif_brl +
    COALESCE(SUM(imp.valor_devido_reais), 0) +
    COALESCE(icms.valor_total_icms, 0) as custo_total_landed,

    -- Taxa de câmbio média
    AVG(a.taxa_cambio_calculada) as taxa_cambio_media,

    -- Status
    di.status_processamento,
    di.created_at as data_processamento

FROM declaracoes_importacao di
LEFT JOIN adicoes a ON di.numero_di = a.numero_di
LEFT JOIN impostos_adicao imp ON a.id = imp.adicao_id
LEFT JOIN icms_detalhado icms ON di.numero_di = icms.numero_di
GROUP BY di.numero_di;
```

### 2. Análise Detalhada por Adição
```sql
CREATE VIEW v_adicoes_completas AS
SELECT
    a.numero_di,
    a.numero_adicao,
    a.ncm,
    ncm_ref.descricao as ncm_descricao,
    a.valor_vmcv_reais as valor_cif,
    a.taxa_cambio_calculada,
    a.moeda_codigo,
    moeda_ref.codigo_iso as moeda_iso,

    -- Impostos por tipo
    MAX(CASE WHEN imp.tipo_imposto = 'II' THEN imp.valor_devido_reais END) as ii_valor,
    MAX(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.valor_devido_reais END) as ipi_valor,
    MAX(CASE WHEN imp.tipo_imposto = 'PIS' THEN imp.valor_devido_reais END) as pis_valor,
    MAX(CASE WHEN imp.tipo_imposto = 'COFINS' THEN imp.valor_devido_reais END) as cofins_valor,

    -- Custo total por adição
    a.valor_vmcv_reais + COALESCE(SUM(imp.valor_devido_reais), 0) as custo_total_adicao,

    -- Acordos tarifários
    GROUP_CONCAT(DISTINCT at.tipo_acordo) as acordos_aplicados,
    MAX(at.percentual_reducao) as maior_reducao_ii,

    -- Mercadorias
    COUNT(DISTINCT m.id) as quantidade_mercadorias,
    SUM(m.quantidade) as quantidade_total,
    GROUP_CONCAT(DISTINCT m.unidade_medida) as unidades_medida

FROM adicoes a
LEFT JOIN impostos_adicao imp ON a.id = imp.adicao_id
LEFT JOIN acordos_tarifarios at ON a.id = at.adicao_id
LEFT JOIN mercadorias m ON a.id = m.adicao_id
LEFT JOIN ncm_referencia ncm_ref ON a.ncm = ncm_ref.codigo_ncm
LEFT JOIN moedas_referencia moeda_ref ON a.moeda_codigo = moeda_ref.codigo_siscomex
GROUP BY a.id;
```

### 3. Análise de Performance Fiscal
```sql
CREATE VIEW v_performance_fiscal AS
SELECT
    YEAR(di.data_registro) as ano,
    MONTH(di.data_registro) as mes,
    COUNT(DISTINCT di.numero_di) as total_dis,
    COUNT(DISTINCT a.id) as total_adicoes,

    -- Valores consolidados
    SUM(di.valor_total_cif_brl) as total_cif,
    SUM(CASE WHEN imp.tipo_imposto = 'II' THEN imp.valor_devido_reais ELSE 0 END) as total_ii_recolhido,
    SUM(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.valor_devido_reais ELSE 0 END) as total_ipi_recolhido,

    -- Análise de acordos
    COUNT(DISTINCT at.adicao_id) as adicoes_com_acordo,
    AVG(at.percentual_reducao) as reducao_media_acordos,

    -- Moedas mais utilizadas
    (SELECT GROUP_CONCAT(DISTINCT moeda_codigo ORDER BY COUNT(*) DESC LIMIT 3)
     FROM adicoes a2 WHERE YEAR(a2.created_at) = YEAR(di.data_registro)
     AND MONTH(a2.created_at) = MONTH(di.data_registro)) as moedas_principais,

    -- Taxa de câmbio média do período
    AVG(a.taxa_cambio_calculada) as taxa_cambio_media_periodo

FROM declaracoes_importacao di
JOIN adicoes a ON di.numero_di = a.numero_di
LEFT JOIN impostos_adicao imp ON a.id = imp.adicao_id
LEFT JOIN acordos_tarifarios at ON a.id = at.adicao_id
GROUP BY YEAR(di.data_registro), MONTH(di.data_registro)
ORDER BY ano DESC, mes DESC;
```

---

## 🔒 CONSTRAINTS E VALIDAÇÕES

### Constraints de Integridade Referencial
```sql
-- Garantir que DI existe antes de adições
ALTER TABLE adicoes
ADD CONSTRAINT fk_adicoes_di
FOREIGN KEY (numero_di) REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE;

-- Garantir que adição existe antes de mercadorias
ALTER TABLE mercadorias
ADD CONSTRAINT fk_mercadorias_adicao
FOREIGN KEY (adicao_id) REFERENCES adicoes(id) ON DELETE CASCADE;

-- Garantir que adição existe antes de impostos
ALTER TABLE impostos_adicao
ADD CONSTRAINT fk_impostos_adicao
FOREIGN KEY (adicao_id) REFERENCES adicoes(id) ON DELETE CASCADE;
```

### Constraints de Validação de Dados
```sql
-- NCM deve ter 8 dígitos numéricos
ALTER TABLE adicoes
ADD CONSTRAINT chk_ncm_format
CHECK (ncm REGEXP '^[0-9]{8}$');

-- Número DI deve ter 10 dígitos numéricos
ALTER TABLE declaracoes_importacao
ADD CONSTRAINT chk_numero_di_format
CHECK (numero_di REGEXP '^[0-9]{10}$');

-- CNPJ deve ter 14 dígitos numéricos
ALTER TABLE declaracoes_importacao
ADD CONSTRAINT chk_cnpj_format
CHECK (importador_cnpj REGEXP '^[0-9]{14}$');

-- Valores não podem ser negativos
ALTER TABLE impostos_adicao
ADD CONSTRAINT chk_valores_positivos
CHECK (valor_devido >= 0 AND valor_recolher >= 0 AND base_calculo >= 0);

-- Alíquotas devem estar entre 0 e 100%
ALTER TABLE impostos_adicao
ADD CONSTRAINT chk_aliquota_valida
CHECK (aliquota_ad_valorem >= 0 AND aliquota_ad_valorem <= 1);

-- Quantidades devem ser positivas
ALTER TABLE mercadorias
ADD CONSTRAINT chk_quantidade_positiva
CHECK (quantidade > 0);
```

### Constraints de Consistência de Negócio
```sql
-- Se ICMS existe, deve ter UF definida
ALTER TABLE icms_detalhado
ADD CONSTRAINT chk_icms_uf_obrigatoria
CHECK (uf_icms IS NOT NULL AND LENGTH(uf_icms) = 2);

-- Taxa de câmbio deve ser positiva quando calculada
ALTER TABLE adicoes
ADD CONSTRAINT chk_taxa_cambio_positiva
CHECK (taxa_cambio_calculada IS NULL OR taxa_cambio_calculada > 0);

-- Código de moeda deve existir na tabela de referência
ALTER TABLE adicoes
ADD CONSTRAINT fk_adicoes_moeda
FOREIGN KEY (moeda_codigo) REFERENCES moedas_referencia(codigo_siscomex);
```

---

## 🔧 TRIGGERS DE AUDITORIA

### Trigger: Log de Alterações em Impostos
```sql
DELIMITER $$
CREATE TRIGGER tr_impostos_audit
AFTER UPDATE ON impostos_adicao
FOR EACH ROW
BEGIN
    INSERT INTO conversao_valores (
        numero_di,
        tabela_origem,
        campo_origem,
        valor_original,
        valor_convertido,
        tipo_conversao,
        funcao_utilizada
    )
    SELECT
        a.numero_di,
        'impostos_adicao',
        CONCAT('imposto_', NEW.tipo_imposto, '_valor_devido'),
        CAST(OLD.valor_devido AS CHAR),
        NEW.valor_devido,
        'RECALCULO_IMPOSTO',
        'TRIGGER_UPDATE'
    FROM adicoes a
    WHERE a.id = NEW.adicao_id;
END$$
DELIMITER ;
```

### Trigger: Validação de Conversões
```sql
DELIMITER $$
CREATE TRIGGER tr_conversao_log
BEFORE INSERT ON conversao_valores
FOR EACH ROW
BEGIN
    -- Validar se a conversão é consistente
    IF NEW.tipo_conversao = 'DIVISAO_100' AND
       CAST(NEW.valor_original AS DECIMAL(15,0)) / 100 != NEW.valor_convertido THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Conversão inconsistente detectada';
    END IF;
END$$
DELIMITER ;
```

### Trigger: Atualização Automática de Totais
```sql
DELIMITER $$
CREATE TRIGGER tr_atualizar_total_di
AFTER INSERT ON adicoes
FOR EACH ROW
BEGIN
    UPDATE declaracoes_importacao
    SET
        total_adicoes = (
            SELECT COUNT(*) FROM adicoes
            WHERE numero_di = NEW.numero_di
        ),
        valor_total_cif_brl = (
            SELECT SUM(valor_vmcv_reais) FROM adicoes
            WHERE numero_di = NEW.numero_di
        ),
        updated_at = NOW()
    WHERE numero_di = NEW.numero_di;
END$$
DELIMITER ;
```

---

## 📈 ÍNDICES DE PERFORMANCE

### Índices Compostos para Consultas Frequentes
```sql
-- Consultas por importador e período
CREATE INDEX idx_di_importador_periodo
ON declaracoes_importacao (importador_cnpj, data_registro, status_processamento);

-- Análise de impostos por NCM e período
CREATE INDEX idx_impostos_ncm_periodo
ON impostos_adicao (tipo_imposto, created_at, valor_devido_reais)
INCLUDE (adicao_id);

-- Pesquisa de mercadorias por descrição e NCM
CREATE INDEX idx_mercadorias_busca
ON mercadorias (adicao_id, quantidade)
INCLUDE (descricao);

-- Análise de acordos tarifários
CREATE INDEX idx_acordos_tipo_reducao
ON acordos_tarifarios (tipo_acordo, percentual_reducao, created_at);

-- Consultas de despesas por categoria e valor
CREATE INDEX idx_despesas_categoria_valor
ON despesas_extras (numero_di, categoria, compoe_base_icms, valor);
```

### Índices para Relatórios Analíticos
```sql
-- Ranking de importadores por valor
CREATE INDEX idx_ranking_importadores
ON declaracoes_importacao (valor_total_cif_brl DESC, importador_cnpj);

-- Análise temporal de importações
CREATE INDEX idx_analise_temporal
ON declaracoes_importacao (data_registro, total_adicoes, valor_total_cif_brl);

-- Top NCMs por volume de importação
CREATE INDEX idx_top_ncms
ON adicoes (ncm, valor_vmcv_reais DESC, created_at);
```

---

## 🚀 OTIMIZAÇÕES DE PERFORMANCE

### 1. Particionamento de Tabelas
```sql
-- Particionar tabela de conversões por ano
ALTER TABLE conversao_valores
PARTITION BY RANGE (YEAR(timestamp_conversao)) (
    PARTITION p2023 VALUES LESS THAN (2024),
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

### 2. Configurações de Memória
```sql
-- Configurações recomendadas para performance
SET GLOBAL innodb_buffer_pool_size = 1073741824; -- 1GB
SET GLOBAL query_cache_size = 268435456; -- 256MB
SET GLOBAL tmp_table_size = 134217728; -- 128MB
SET GLOBAL max_heap_table_size = 134217728; -- 128MB
```

### 3. Estratégias de Cache
```sql
-- Query cache para consultas de referência
SELECT SQL_CACHE * FROM moedas_referencia WHERE ativo = TRUE;
SELECT SQL_CACHE * FROM ncm_referencia WHERE ativo = TRUE;
```

---

## 📋 CHECKLIST DE IMPLEMENTAÇÃO

### Fase 1: Estrutura Base
- [ ] Criar banco de dados `importaco_etl_dis`
- [ ] Executar script de criação das 12 tabelas principais
- [ ] Criar funções de conversão
- [ ] Inserir dados de referência (moedas, NCMs básicos)
- [ ] Testar constraints e relacionamentos

### Fase 2: Dados de Teste
- [ ] Implementar parser XML para popular tabelas
- [ ] Processar DI 2300120746 (16 adições)
- [ ] Processar DI 2518173187 (1 adição, ICMS)
- [ ] Processar DI 2520345968 (1 adição, múltiplas moedas)
- [ ] Validar conversões automáticas

### Fase 3: Otimização
- [ ] Criar views consolidadas
- [ ] Implementar triggers de auditoria
- [ ] Configurar índices de performance
- [ ] Testar consultas analíticas
- [ ] Documentar performance benchmarks

### Fase 4: Validação
- [ ] Comparar resultados com sistema Python existente
- [ ] Validar cálculos de impostos
- [ ] Verificar conversões de moeda
- [ ] Testar casos edge identificados
- [ ] Documentar diferenças e ajustes

---

## 📚 REFERÊNCIAS E DOCUMENTAÇÃO

### Documentos Base
- XMLs DI analisados: `orientacoes/2300120746.xml`, `2518173187.xml`, `2520345968.xml`
- Sistema Python: `orientacoes/importador-xml-di-nf-entrada-perplexity-aprimorado-venda.py`
- Documentação Siscomex: Portal oficial da Receita Federal

### Próximos Passos de Desenvolvimento
1. **XML Parser PHP**: Implementar classes para processar XMLs DI
2. **Currency Calculator**: Sistema de conversão automática de moedas
3. **Tax Engine**: Motor de cálculo configurável por estado
4. **APIs REST**: Endpoints para consulta e processamento
5. **Dashboard**: Interface web para visualização de dados

---

<div align="center">

**Especificação técnica completa pronta para implementação**

*Energia • Velocidade • Força | Segurança • Intelecto • Precisão | Respeito • Proteção • Transparência*

© 2025 Sistema ETL de DI's - Padrão Expertzy

</div>