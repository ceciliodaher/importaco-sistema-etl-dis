-- ================================================================================
-- SISTEMA ETL DE DI's - SCHEMA PRINCIPAL
-- Padrão Expertzy: Energia • Segurança • Transparência
-- Versão: 1.0.0
-- ================================================================================

-- Criar database
CREATE DATABASE IF NOT EXISTS importaco_etl_dis
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE importaco_etl_dis;

-- ================================================================================
-- TABELA 1: declaracoes_importacao (Tabela Principal)
-- ================================================================================
CREATE TABLE IF NOT EXISTS declaracoes_importacao (
    numero_di VARCHAR(10) NOT NULL,
    data_registro DATE NOT NULL,
    urf_despacho_codigo VARCHAR(7),
    urf_despacho_nome VARCHAR(100),
    importador_cnpj CHAR(14) NOT NULL,
    importador_nome VARCHAR(255) NOT NULL,
    canal_selecao CHAR(1),
    caracteristica_operacao VARCHAR(10),
    total_adicoes TINYINT UNSIGNED DEFAULT 0,
    valor_total_cif_usd DECIMAL(15,2),
    valor_total_cif_brl DECIMAL(15,2),
    status_processamento ENUM('PENDENTE','PROCESSANDO','COMPLETO','ERRO') DEFAULT 'PENDENTE',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (numero_di),
    INDEX idx_data_registro (data_registro),
    INDEX idx_importador_cnpj (importador_cnpj),
    INDEX idx_status_data (status_processamento, data_registro),
    INDEX idx_valor_total (valor_total_cif_brl),
    
    CONSTRAINT chk_numero_di_format CHECK (numero_di REGEXP '^[0-9]{10}$'),
    CONSTRAINT chk_cnpj_format CHECK (importador_cnpj REGEXP '^[0-9]{14}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 2: adicoes (Itens da DI)
-- ================================================================================
CREATE TABLE IF NOT EXISTS adicoes (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    numero_di VARCHAR(10) NOT NULL,
    numero_adicao VARCHAR(3) NOT NULL,
    numero_sequencial_item VARCHAR(10),
    codigo_fabricante VARCHAR(50),
    ncm CHAR(8) NOT NULL,
    valor_vmle_moeda DECIMAL(15,2) NOT NULL,
    valor_vmle_reais DECIMAL(15,2) NOT NULL,
    valor_vmcv_moeda DECIMAL(15,2) NOT NULL,
    valor_vmcv_reais DECIMAL(15,2) NOT NULL,
    taxa_cambio_calculada DECIMAL(10,6) NULL COMMENT 'Calculado via trigger: valor_vmcv_reais / valor_vmcv_moeda',
    moeda_codigo CHAR(3) NOT NULL,
    moeda_nome VARCHAR(50),
    incoterm VARCHAR(10) COMMENT 'INCOTERM da adição (CPT, FCA, CIF, FOB, etc)',
    condicao_venda_local VARCHAR(100) COMMENT 'Local da condição de venda',
    peso_liquido DECIMAL(12,3),
    peso_bruto DECIMAL(12,3),
    numero_li VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uk_di_adicao (numero_di, numero_adicao),
    INDEX idx_ncm (ncm),
    INDEX idx_valor_vmcv (valor_vmcv_reais),
    INDEX idx_moeda (moeda_codigo),
    INDEX idx_taxa_cambio (taxa_cambio_calculada),
    INDEX idx_incoterm (incoterm),
    
    CONSTRAINT fk_adicao_di FOREIGN KEY (numero_di) 
        REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE,
    CONSTRAINT chk_ncm_format CHECK (ncm REGEXP '^[0-9]{8}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 3: mercadorias (Produtos por Adição)
-- ================================================================================
CREATE TABLE IF NOT EXISTS mercadorias (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    adicao_id BIGINT UNSIGNED NOT NULL,
    numero_sequencial VARCHAR(3),
    descricao TEXT NOT NULL,
    quantidade DECIMAL(12,5) NOT NULL,
    unidade_medida VARCHAR(20),
    valor_unitario_moeda DECIMAL(15,8) NULL COMMENT 'Calculado via trigger: valor_vmcv_moeda / quantidade',
    especificacao_mercadoria TEXT,
    condicao_mercadoria VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    INDEX idx_adicao_id (adicao_id),
    FULLTEXT INDEX ft_descricao (descricao),
    FULLTEXT INDEX ft_especificacao (especificacao_mercadoria),
    
    CONSTRAINT fk_mercadoria_adicao FOREIGN KEY (adicao_id) 
        REFERENCES adicoes(id) ON DELETE CASCADE,
    CONSTRAINT chk_quantidade_positiva CHECK (quantidade > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 4: impostos_adicao (Impostos por Adição)
-- ================================================================================
CREATE TABLE IF NOT EXISTS impostos_adicao (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    adicao_id BIGINT UNSIGNED NOT NULL,
    tipo_imposto ENUM('II','IPI','PIS','COFINS','ICMS') NOT NULL,
    base_calculo DECIMAL(15,2),
    aliquota_ad_valorem DECIMAL(7,4),
    valor_devido DECIMAL(15,2),
    valor_recolher DECIMAL(15,2),
    valor_devido_reais DECIMAL(15,2),
    valor_calculado DECIMAL(15,2) COMMENT 'Valor calculado pelo sistema (para comparação)',
    divergencia_valor DECIMAL(15,2) COMMENT 'Divergência (DI - Calculado)',
    divergencia_pct DECIMAL(8,4) COMMENT '% Divergência',
    situacao_tributaria VARCHAR(10),
    aliquota_especifica DECIMAL(15,8),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    UNIQUE KEY uk_adicao_imposto (adicao_id, tipo_imposto),
    INDEX idx_tipo_imposto (tipo_imposto),
    INDEX idx_valor_devido (valor_devido_reais),
    INDEX idx_situacao (situacao_tributaria),
    
    CONSTRAINT fk_imposto_adicao FOREIGN KEY (adicao_id) 
        REFERENCES adicoes(id) ON DELETE CASCADE,
    CONSTRAINT chk_valores_positivos CHECK (
        (valor_devido IS NULL OR valor_devido >= 0) AND 
        (valor_recolher IS NULL OR valor_recolher >= 0) AND 
        (base_calculo IS NULL OR base_calculo >= 0)
    ),
    CONSTRAINT chk_aliquota_valida CHECK (
        aliquota_ad_valorem IS NULL OR (aliquota_ad_valorem >= 0 AND aliquota_ad_valorem <= 1)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 5: acordos_tarifarios (Acordos Internacionais)
-- ================================================================================
CREATE TABLE IF NOT EXISTS acordos_tarifarios (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    adicao_id BIGINT UNSIGNED NOT NULL,
    tipo_acordo VARCHAR(20) NOT NULL,
    codigo_acordo VARCHAR(10),
    aliquota_acordo DECIMAL(7,4),
    percentual_reducao DECIMAL(5,2) NULL COMMENT 'Calculado via trigger: reducao aplicada',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    INDEX idx_adicao_id (adicao_id),
    INDEX idx_tipo_acordo (tipo_acordo),
    INDEX idx_percentual_reducao (percentual_reducao),
    
    CONSTRAINT fk_acordo_adicao FOREIGN KEY (adicao_id) 
        REFERENCES adicoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 6: icms_detalhado (ICMS Específico)
-- ================================================================================
CREATE TABLE IF NOT EXISTS icms_detalhado (
    numero_di VARCHAR(10) NOT NULL,
    uf_icms CHAR(2),
    valor_total_icms DECIMAL(15,2),
    codigo_receita VARCHAR(10),
    situacao ENUM('NAO_APLICA','EXONERADO','DEVIDO') NULL COMMENT 'Calculado via trigger baseado em valor_total_icms',
    observacoes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (numero_di),
    INDEX idx_uf_icms (uf_icms),
    INDEX idx_situacao (situacao),
    INDEX idx_valor_total (valor_total_icms),
    
    CONSTRAINT fk_icms_di FOREIGN KEY (numero_di) 
        REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE,
    CONSTRAINT chk_icms_uf_obrigatoria CHECK (uf_icms IS NULL OR LENGTH(uf_icms) = 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 7: pagamentos_siscomex (Taxas Siscomex)
-- ================================================================================
CREATE TABLE IF NOT EXISTS pagamentos_siscomex (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    numero_di VARCHAR(10) NOT NULL,
    codigo_receita VARCHAR(10) NOT NULL,
    nome_receita VARCHAR(100),
    valor_multa DECIMAL(15,2) DEFAULT 0,
    valor_juros DECIMAL(15,2) DEFAULT 0,
    valor_receita DECIMAL(15,2) NOT NULL,
    data_vencimento DATE,
    data_pagamento DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    INDEX idx_numero_di (numero_di),
    INDEX idx_codigo_receita (codigo_receita),
    INDEX idx_valor_receita (valor_receita),
    INDEX idx_data_pagamento (data_pagamento),
    
    CONSTRAINT fk_pagamento_di FOREIGN KEY (numero_di) 
        REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 8: despesas_frete_seguro (Frete e Seguro Internacional)
-- ================================================================================
CREATE TABLE IF NOT EXISTS despesas_frete_seguro (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    numero_di VARCHAR(10) NOT NULL,
    tipo_despesa ENUM('FRETE','SEGURO') NOT NULL,
    valor_moeda_negociada DECIMAL(15,2),
    moeda_negociada_codigo CHAR(3),
    moeda_negociada_nome VARCHAR(50),
    valor_dolares DECIMAL(15,2),
    valor_reais DECIMAL(15,2),
    prepago BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    INDEX idx_numero_di (numero_di),
    INDEX idx_tipo_despesa (tipo_despesa),
    INDEX idx_moeda_negociada (moeda_negociada_codigo),
    INDEX idx_valor_reais (valor_reais),
    
    CONSTRAINT fk_despesa_di FOREIGN KEY (numero_di) 
        REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 9: despesas_extras (Despesas Discriminadas com Validação AFRMM)
-- ================================================================================
CREATE TABLE IF NOT EXISTS despesas_extras (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    numero_di VARCHAR(10) NOT NULL,
    
    categoria ENUM(
        'SISCOMEX',          -- Taxa Siscomex
        'AFRMM',             -- Adicional Frete Marinha Mercante
        'CAPATAZIA',         -- Movimentação portuária
        'ARMAZENAGEM',       -- Armazenagem alfandegada
        'DEMURRAGE',         -- Sobreestadia contêiner
        'DESPACHANTE',       -- Honorários despachante
        'FRETE_INTERNO',     -- Frete rodoviário/nacional
        'SEGURO_INTERNO',    -- Seguro nacional
        'BANCARIO',          -- Taxas bancárias/câmbio
        'LIBERACAO_BL',      -- Taxa liberação conhecimento
        'SCANNER',           -- Inspeção não invasiva
        'ISPS',              -- International Ship and Port Security
        'THC',               -- Terminal Handling Charge
        'DESCONSOLIDACAO',   -- Taxa desconsolidação
        'SDA',               -- Segregação e Entrega
        'OUTROS'             -- Outras despesas
    ) NOT NULL,
    
    -- Valores com validação
    valor_informado DECIMAL(15,2),
    valor_calculado DECIMAL(15,2),
    valor_final DECIMAL(15,2) NOT NULL,
    
    -- Controle de origem
    origem_valor ENUM('DI', 'CALCULADO', 'MANUAL', 'DOCUMENTO') DEFAULT 'MANUAL',
    divergencia_percentual DECIMAL(5,2),
    
    -- Documentação
    numero_documento VARCHAR(50),
    data_despesa DATE,
    fornecedor_cnpj CHAR(14),
    fornecedor_nome VARCHAR(255),
    
    -- Cálculo AFRMM
    base_calculo DECIMAL(15,2),
    aliquota DECIMAL(5,2),
    
    -- Configurações
    moeda CHAR(3) DEFAULT 'BRL',
    compoe_base_icms BOOLEAN DEFAULT FALSE,
    criterio_rateio ENUM('VALOR','PESO','QUANTIDADE','MANUAL') DEFAULT 'VALOR',
    dados_rateio JSON,
    
    -- Validação e auditoria
    validado BOOLEAN DEFAULT FALSE,
    observacao_divergencia TEXT,
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    INDEX idx_numero_di (numero_di),
    INDEX idx_categoria (categoria),
    INDEX idx_categoria_valor (categoria, valor_final),
    INDEX idx_di_categoria (numero_di, categoria),
    INDEX idx_compoe_icms (compoe_base_icms),
    INDEX idx_criterio_rateio (criterio_rateio),
    INDEX idx_created_at (created_at),
    INDEX idx_origem_valor (origem_valor),
    INDEX idx_validado (validado),
    
    CONSTRAINT fk_despesa_extra_di FOREIGN KEY (numero_di) 
        REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE,
    CONSTRAINT chk_fornecedor_cnpj CHECK (fornecedor_cnpj IS NULL OR fornecedor_cnpj REGEXP '^[0-9]{14}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 10: moedas_referencia (Tabela de Moedas)
-- ================================================================================
CREATE TABLE IF NOT EXISTS moedas_referencia (
    codigo_siscomex CHAR(3) NOT NULL,
    codigo_iso CHAR(3) NOT NULL,
    nome_moeda VARCHAR(50) NOT NULL,
    simbolo VARCHAR(5),
    decimal_places TINYINT DEFAULT 2,
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (codigo_siscomex),
    UNIQUE KEY uk_codigo_iso (codigo_iso),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 11: ncm_referencia (Classificação NCM - SEM ALÍQUOTAS PADRÃO)
-- Populada dinamicamente conforme DIs são processadas
-- Alíquotas sempre vêm da DI (fonte única de verdade)
-- ================================================================================
CREATE TABLE IF NOT EXISTS ncm_referencia (
    codigo_ncm CHAR(8) NOT NULL,
    descricao TEXT,
    unidade_estatistica VARCHAR(10),
    ex_tarifario BOOLEAN DEFAULT FALSE,
    observacoes TEXT,
    -- Controle de uso
    primeira_ocorrencia DATE,
    ultima_ocorrencia DATE,
    total_importacoes INT DEFAULT 0,
    -- Status
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (codigo_ncm),
    FULLTEXT INDEX ft_descricao (descricao),
    INDEX idx_ultima_ocorrencia (ultima_ocorrencia),
    INDEX idx_total_importacoes (total_importacoes),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA 12: conversao_valores (Log de Conversões)
-- ================================================================================
CREATE TABLE IF NOT EXISTS conversao_valores (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    numero_di VARCHAR(10) NOT NULL,
    tabela_origem VARCHAR(50) NOT NULL,
    campo_origem VARCHAR(50) NOT NULL,
    valor_original VARCHAR(50),
    valor_convertido DECIMAL(15,2),
    tipo_conversao VARCHAR(20) NOT NULL,
    funcao_utilizada VARCHAR(100),
    timestamp_conversao TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    INDEX idx_numero_di (numero_di),
    INDEX idx_tabela_campo (tabela_origem, campo_origem),
    INDEX idx_tipo_conversao (tipo_conversao),
    INDEX idx_timestamp (timestamp_conversao),
    
    CONSTRAINT fk_conversao_di FOREIGN KEY (numero_di) 
        REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA ADICIONAL: ncm_aliquotas_historico (Histórico de Alíquotas Praticadas)
-- Mantém histórico real baseado em DIs processadas
-- ================================================================================
CREATE TABLE IF NOT EXISTS ncm_aliquotas_historico (
    id BIGINT UNSIGNED AUTO_INCREMENT,
    codigo_ncm CHAR(8) NOT NULL,
    numero_di VARCHAR(10) NOT NULL,
    aliquota_ii_praticada DECIMAL(7,4),
    aliquota_ipi_praticada DECIMAL(7,4),
    aliquota_pis_praticada DECIMAL(7,4),
    aliquota_cofins_praticada DECIMAL(7,4),
    data_importacao DATE,
    acordo_aplicado VARCHAR(20),
    uf_destino CHAR(2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    PRIMARY KEY (id),
    INDEX idx_ncm_data (codigo_ncm, data_importacao),
    INDEX idx_di (numero_di),
    INDEX idx_acordo (acordo_aplicado),
    
    CONSTRAINT fk_historico_ncm FOREIGN KEY (codigo_ncm) 
        REFERENCES ncm_referencia(codigo_ncm),
    CONSTRAINT fk_historico_di FOREIGN KEY (numero_di) 
        REFERENCES declaracoes_importacao(numero_di) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- TABELA ADICIONAL: processamento_xmls (Controle de Upload e Processamento)
-- ================================================================================
CREATE TABLE IF NOT EXISTS processamento_xmls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hash_arquivo VARCHAR(32) UNIQUE NOT NULL COMMENT 'MD5 do arquivo XML',
    nome_arquivo VARCHAR(255) NOT NULL COMMENT 'Nome original do arquivo',
    numero_di VARCHAR(20) COMMENT 'Número da DI extraído do XML',
    incoterm VARCHAR(10) COMMENT 'INCOTERM principal da DI',
    status_processamento ENUM('PENDENTE', 'PROCESSANDO', 'COMPLETO', 'ERRO') DEFAULT 'PENDENTE',
    erro_detalhes TEXT COMMENT 'Detalhes do erro caso status seja ERRO',
    tamanho_arquivo INT UNSIGNED COMMENT 'Tamanho do arquivo em bytes',
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_processamento TIMESTAMP NULL,
    
    INDEX idx_numero_di (numero_di),
    INDEX idx_incoterm (incoterm),
    INDEX idx_status (status_processamento),
    INDEX idx_data_upload (data_upload),
    INDEX idx_hash (hash_arquivo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Controle de processamento de XMLs de DI brasileiras';

-- ================================================================================
-- NOTA: Foreign Key para moedas deve ser criada APÓS população da tabela
-- moedas_referencia via script 08_popular_referencias.sql
-- Comando: ALTER TABLE adicoes ADD CONSTRAINT fk_adicoes_moeda FOREIGN KEY (moeda_codigo) 
--          REFERENCES moedas_referencia(codigo_siscomex);
-- ================================================================================

-- ================================================================================
-- FIM DO SCHEMA PRINCIPAL
-- ================================================================================