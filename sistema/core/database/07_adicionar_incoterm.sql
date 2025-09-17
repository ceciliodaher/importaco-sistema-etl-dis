-- ================================================================================
-- ADICIONAR INCOTERM AO SCHEMA EXISTENTE
-- Sistema ETL de DI's - Campos críticos baseados em análise XML real
-- Baseado em: condicaoVendaIncoterm (CPT, FCA, CIF, FOB, etc)
-- ================================================================================

USE importaco_etl_dis;

-- ================================================================================
-- ADICIONAR CAMPOS INCOTERM NA TABELA ADICOES
-- ================================================================================

-- Verificar se a coluna já existe antes de adicionar
SET @col_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'importaco_etl_dis' 
    AND TABLE_NAME = 'adicoes' 
    AND COLUMN_NAME = 'incoterm'
);

-- Adicionar coluna incoterm se não existir
SET @sql = IF(
    @col_exists = 0,
    'ALTER TABLE adicoes ADD COLUMN incoterm VARCHAR(10) COMMENT "INCOTERM da adição (CPT, FCA, CIF, FOB, etc)" AFTER moeda_nome',
    'SELECT "Coluna incoterm já existe" as status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar se a coluna condicao_venda_local já existe
SET @col_exists2 = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = 'importaco_etl_dis' 
    AND TABLE_NAME = 'adicoes' 
    AND COLUMN_NAME = 'condicao_venda_local'
);

-- Adicionar coluna condicao_venda_local se não existir
SET @sql2 = IF(
    @col_exists2 = 0,
    'ALTER TABLE adicoes ADD COLUMN condicao_venda_local VARCHAR(100) COMMENT "Local da condição de venda" AFTER incoterm',
    'SELECT "Coluna condicao_venda_local já existe" as status'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Adicionar índice para INCOTERM (se não existir)
SET @index_exists = (
    SELECT COUNT(*) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE TABLE_SCHEMA = 'importaco_etl_dis' 
    AND TABLE_NAME = 'adicoes' 
    AND INDEX_NAME = 'idx_incoterm'
);

SET @sql3 = IF(
    @index_exists = 0,
    'ALTER TABLE adicoes ADD INDEX idx_incoterm (incoterm)',
    'SELECT "Índice idx_incoterm já existe" as status'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- ================================================================================
-- CRIAR TABELA PROCESSAMENTO_XMLS
-- ================================================================================

CREATE TABLE IF NOT EXISTS processamento_xmls (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hash_arquivo VARCHAR(32) UNIQUE NOT NULL COMMENT 'MD5 do arquivo XML',
    nome_arquivo VARCHAR(255) NOT NULL COMMENT 'Nome original do arquivo',
    numero_di VARCHAR(20) COMMENT 'Número da DI extraído do XML',
    incoterm VARCHAR(10) COMMENT 'INCOTERM principal da DI',
    status_processamento ENUM('PENDENTE', 'PROCESSANDO', 'COMPLETO', 'ERRO') DEFAULT 'PENDENTE',
    erro_detalhes TEXT COMMENT 'Detalhes do erro caso status seja ERRO',
    campos_extraidos JSON COMMENT 'Metadados dos campos extraídos',
    tamanho_arquivo INT UNSIGNED COMMENT 'Tamanho do arquivo em bytes',
    data_upload TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    data_processamento TIMESTAMP NULL,
    usuario_upload VARCHAR(100) DEFAULT 'sistema',
    
    -- Índices para performance
    INDEX idx_numero_di (numero_di),
    INDEX idx_incoterm (incoterm),
    INDEX idx_status (status_processamento),
    INDEX idx_data_upload (data_upload),
    INDEX idx_hash (hash_arquivo),
    
    -- Constraint para validar formato DI brasileiro
    CONSTRAINT chk_numero_di_br CHECK (numero_di IS NULL OR numero_di REGEXP '^[0-9]{10}$')
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Controle de processamento de XMLs de DI brasileiras';

-- ================================================================================
-- ADICIONAR INCOTERM COMO CONSTRAINT DE VALIDAÇÃO
-- ================================================================================

-- Adicionar constraint para validar INCOTERMs conhecidos
ALTER TABLE adicoes ADD CONSTRAINT chk_incoterm_valido 
CHECK (incoterm IS NULL OR incoterm IN (
    'EXW', 'FCA', 'CPT', 'CIP', 'DAP', 'DPU', 'DDP',  -- Grupo E e F e D
    'FAS', 'FOB', 'CFR', 'CIF'                        -- Grupo C
));

-- ================================================================================
-- ADICIONAR COMENTÁRIOS PARA DOCUMENTAÇÃO
-- ================================================================================

ALTER TABLE adicoes MODIFY COLUMN incoterm VARCHAR(10) 
COMMENT 'INCOTERM: Termo internacional de comércio (EXW, FOB, CIF, etc)';

ALTER TABLE adicoes MODIFY COLUMN condicao_venda_local VARCHAR(100) 
COMMENT 'Local da condição de venda conforme INCOTERM';

-- ================================================================================
-- VERIFICAR RESULTADOS
-- ================================================================================

-- Mostrar estrutura atualizada da tabela adicoes
DESCRIBE adicoes;

-- Mostrar estrutura da nova tabela
DESCRIBE processamento_xmls;

-- Verificar índices criados
SHOW INDEX FROM adicoes WHERE Key_name IN ('idx_incoterm');
SHOW INDEX FROM processamento_xmls;

SELECT 'INCOTERM adicionado com sucesso ao schema existente!' as resultado;