-- ================================================================================
-- SISTEMA ETL DE DI's - FUNÇÕES SQL
-- Funções de Conversão Siscomex e Validação AFRMM
-- Versão: 1.0.0
-- ================================================================================

USE importaco_etl_dis;

-- ================================================================================
-- FUNÇÃO 1: Converter Valores Monetários Siscomex
-- Converte valores no formato Siscomex (15 dígitos) para decimal
-- Exemplo: '000000017859126' → 178591.26
-- ================================================================================
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS fn_convert_siscomex_money(valor_string VARCHAR(15))
RETURNS DECIMAL(15,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE valor_numeric DECIMAL(15,2);
    
    IF valor_string IS NULL OR valor_string = '' THEN
        RETURN 0.00;
    END IF;
    
    -- Remove zeros à esquerda e divide por 100
    SET valor_numeric = CAST(TRIM(LEADING '0' FROM valor_string) AS DECIMAL(15,0)) / 100;
    
    RETURN IFNULL(valor_numeric, 0.00);
END$$
DELIMITER ;

-- ================================================================================
-- FUNÇÃO 2: Converter Alíquotas Siscomex
-- Converte alíquotas no formato Siscomex para decimal
-- Exemplo: '01600' → 16.0000 (16%)
-- ================================================================================
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS fn_convert_siscomex_rate(aliquota_string VARCHAR(10))
RETURNS DECIMAL(7,4)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE aliquota_numeric DECIMAL(7,4);
    
    IF aliquota_string IS NULL OR aliquota_string = '' THEN
        RETURN 0.0000;
    END IF;
    
    -- Remove zeros à esquerda e divide por 10000
    SET aliquota_numeric = CAST(TRIM(LEADING '0' FROM aliquota_string) AS DECIMAL(7,0)) / 10000;
    
    RETURN IFNULL(aliquota_numeric, 0.0000);
END$$
DELIMITER ;

-- ================================================================================
-- FUNÇÃO 3: Calcular Taxa de Câmbio
-- Calcula taxa de câmbio baseada nos valores da DI
-- ================================================================================
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS fn_calculate_exchange_rate(
    valor_brl DECIMAL(15,2), 
    valor_foreign DECIMAL(15,2)
)
RETURNS DECIMAL(10,6)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE taxa DECIMAL(10,6);
    
    IF valor_foreign IS NULL OR valor_foreign <= 0 THEN
        RETURN NULL;
    END IF;
    
    SET taxa = valor_brl / valor_foreign;
    
    RETURN ROUND(taxa, 6);
END$$
DELIMITER ;

-- ================================================================================
-- FUNÇÃO 4: Converter Data Siscomex
-- Converte data no formato YYYYMMDD para DATE
-- Exemplo: '20230102' → '2023-01-02'
-- ================================================================================
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS fn_convert_siscomex_date(data_string VARCHAR(8))
RETURNS DATE
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE data_convertida DATE;
    
    IF data_string IS NULL OR data_string = '' THEN
        RETURN NULL;
    END IF;
    
    IF LENGTH(data_string) = 8 AND data_string REGEXP '^[0-9]{8}$' THEN
        SET data_convertida = STR_TO_DATE(data_string, '%Y%m%d');
    ELSE
        SET data_convertida = NULL;
    END IF;
    
    RETURN data_convertida;
END$$
DELIMITER ;

-- ================================================================================
-- FUNÇÃO 5: Validar AFRMM com Prevalência DI
-- Valida AFRMM informado vs calculado, DI sempre prevalece
-- Retorna JSON com valores e status de validação
-- ================================================================================
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS fn_validate_afrmm(
    valor_di DECIMAL(15,2),
    valor_frete DECIMAL(15,2)
)
RETURNS JSON
DETERMINISTIC
BEGIN
    DECLARE valor_calc DECIMAL(15,2);
    DECLARE divergencia DECIMAL(5,2);
    DECLARE status_validacao VARCHAR(20);
    
    -- Calcular 25% do frete
    SET valor_calc = IFNULL(valor_frete * 0.25, 0);
    
    IF valor_di IS NOT NULL AND valor_di > 0 THEN
        -- VALOR DA DI SEMPRE PREVALECE
        IF valor_calc > 0 THEN
            SET divergencia = ((valor_di - valor_calc) / valor_calc) * 100;
        ELSE
            SET divergencia = 0;
        END IF;
        
        -- Determinar status baseado na divergência
        SET status_validacao = CASE
            WHEN ABS(divergencia) > 20 THEN 'DIVERGENCIA_ALTA'
            WHEN ABS(divergencia) > 10 THEN 'DIVERGENCIA_MEDIA'
            ELSE 'OK'
        END;
        
        RETURN JSON_OBJECT(
            'valor_final', valor_di,
            'valor_calculado', valor_calc,
            'origem', 'DI',
            'divergencia', ROUND(divergencia, 2),
            'status', status_validacao
        );
    ELSE
        -- Usar cálculo automático como fallback
        RETURN JSON_OBJECT(
            'valor_final', valor_calc,
            'valor_calculado', valor_calc,
            'origem', 'CALCULADO',
            'divergencia', 0,
            'status', 'CALCULADO_AUTO'
        );
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- FUNÇÃO 6: Calcular Custo Landed Completo
-- Calcula custo total incluindo CIF, impostos e todas despesas
-- Retorna JSON com breakdown completo
-- ================================================================================
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS fn_calculate_landed_cost(p_numero_di VARCHAR(10))
RETURNS JSON
READS SQL DATA
BEGIN
    DECLARE resultado JSON;
    DECLARE v_cif DECIMAL(15,2);
    DECLARE v_ii DECIMAL(15,2);
    DECLARE v_ipi DECIMAL(15,2);
    DECLARE v_pis DECIMAL(15,2);
    DECLARE v_cofins DECIMAL(15,2);
    DECLARE v_icms DECIMAL(15,2);
    DECLARE v_despesas DECIMAL(15,2);
    DECLARE v_total DECIMAL(15,2);
    
    -- Buscar valor CIF
    SELECT valor_total_cif_brl INTO v_cif
    FROM declaracoes_importacao
    WHERE numero_di = p_numero_di;
    
    -- Buscar impostos
    SELECT 
        IFNULL(SUM(CASE WHEN tipo_imposto = 'II' THEN valor_devido_reais ELSE 0 END), 0),
        IFNULL(SUM(CASE WHEN tipo_imposto = 'IPI' THEN valor_devido_reais ELSE 0 END), 0),
        IFNULL(SUM(CASE WHEN tipo_imposto = 'PIS' THEN valor_devido_reais ELSE 0 END), 0),
        IFNULL(SUM(CASE WHEN tipo_imposto = 'COFINS' THEN valor_devido_reais ELSE 0 END), 0)
    INTO v_ii, v_ipi, v_pis, v_cofins
    FROM impostos_adicao ia
    JOIN adicoes a ON ia.adicao_id = a.id
    WHERE a.numero_di = p_numero_di;
    
    -- Buscar ICMS
    SELECT IFNULL(valor_total_icms, 0) INTO v_icms
    FROM icms_detalhado
    WHERE numero_di = p_numero_di;
    
    -- Buscar total de despesas extras
    SELECT IFNULL(SUM(valor_final), 0) INTO v_despesas
    FROM despesas_extras
    WHERE numero_di = p_numero_di;
    
    -- Calcular total
    SET v_total = v_cif + v_ii + v_ipi + v_pis + v_cofins + v_icms + v_despesas;
    
    -- Montar JSON de resultado
    SET resultado = JSON_OBJECT(
        'numero_di', p_numero_di,
        'valor_cif', v_cif,
        'impostos', JSON_OBJECT(
            'ii', v_ii,
            'ipi', v_ipi,
            'pis', v_pis,
            'cofins', v_cofins,
            'icms', v_icms,
            'total_impostos', v_ii + v_ipi + v_pis + v_cofins + v_icms
        ),
        'despesas_extras', v_despesas,
        'custo_total_landed', v_total
    );
    
    RETURN resultado;
END$$
DELIMITER ;

-- ================================================================================
-- FUNÇÃO 7: Calcular Despesas Discriminadas
-- Retorna JSON com todas despesas organizadas por categoria
-- ================================================================================
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS fn_get_despesas_discriminadas(p_numero_di VARCHAR(10))
RETURNS JSON
READS SQL DATA
BEGIN
    DECLARE resultado JSON;
    
    SELECT JSON_OBJECT(
        'numero_di', p_numero_di,
        'despesas_portuarias', JSON_OBJECT(
            'siscomex', IFNULL((SELECT valor_final FROM despesas_extras WHERE numero_di = p_numero_di AND categoria = 'SISCOMEX' LIMIT 1), 0),
            'afrmm', IFNULL((SELECT valor_final FROM despesas_extras WHERE numero_di = p_numero_di AND categoria = 'AFRMM' LIMIT 1), 0),
            'capatazia', IFNULL((SELECT valor_final FROM despesas_extras WHERE numero_di = p_numero_di AND categoria = 'CAPATAZIA' LIMIT 1), 0),
            'armazenagem', IFNULL((SELECT valor_final FROM despesas_extras WHERE numero_di = p_numero_di AND categoria = 'ARMAZENAGEM' LIMIT 1), 0),
            'thc', IFNULL((SELECT valor_final FROM despesas_extras WHERE numero_di = p_numero_di AND categoria = 'THC' LIMIT 1), 0),
            'isps', IFNULL((SELECT valor_final FROM despesas_extras WHERE numero_di = p_numero_di AND categoria = 'ISPS' LIMIT 1), 0)
        ),
        'despesas_despacho', JSON_OBJECT(
            'despachante', IFNULL((SELECT valor_final FROM despesas_extras WHERE numero_di = p_numero_di AND categoria = 'DESPACHANTE' LIMIT 1), 0),
            'liberacao_bl', IFNULL((SELECT valor_final FROM despesas_extras WHERE numero_di = p_numero_di AND categoria = 'LIBERACAO_BL' LIMIT 1), 0)
        ),
        'despesas_logistica', JSON_OBJECT(
            'frete_interno', IFNULL((SELECT valor_final FROM despesas_extras WHERE numero_di = p_numero_di AND categoria = 'FRETE_INTERNO' LIMIT 1), 0),
            'seguro_interno', IFNULL((SELECT valor_final FROM despesas_extras WHERE numero_di = p_numero_di AND categoria = 'SEGURO_INTERNO' LIMIT 1), 0)
        ),
        'total_despesas', IFNULL((SELECT SUM(valor_final) FROM despesas_extras WHERE numero_di = p_numero_di), 0)
    ) INTO resultado;
    
    RETURN resultado;
END$$
DELIMITER ;

-- ================================================================================
-- FUNÇÃO 8: Validar NCM e Registrar Uso
-- Registra NCM na tabela de referência se não existir
-- ================================================================================
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS fn_register_ncm(
    p_codigo_ncm CHAR(8),
    p_descricao TEXT
)
RETURNS BOOLEAN
MODIFIES SQL DATA
BEGIN
    DECLARE ncm_exists BOOLEAN DEFAULT FALSE;
    
    -- Verificar se NCM já existe
    SELECT COUNT(*) > 0 INTO ncm_exists
    FROM ncm_referencia
    WHERE codigo_ncm = p_codigo_ncm;
    
    IF NOT ncm_exists THEN
        -- Inserir novo NCM
        INSERT INTO ncm_referencia (
            codigo_ncm, 
            descricao, 
            primeira_ocorrencia, 
            ultima_ocorrencia, 
            total_importacoes
        ) VALUES (
            p_codigo_ncm, 
            p_descricao, 
            CURDATE(), 
            CURDATE(), 
            1
        );
    ELSE
        -- Atualizar estatísticas de uso
        UPDATE ncm_referencia
        SET ultima_ocorrencia = CURDATE(),
            total_importacoes = total_importacoes + 1,
            descricao = IF(descricao IS NULL OR descricao = '', p_descricao, descricao)
        WHERE codigo_ncm = p_codigo_ncm;
    END IF;
    
    RETURN TRUE;
END$$
DELIMITER ;

-- ================================================================================
-- FUNÇÃO 9: Converter Peso Siscomex
-- Converte peso no formato Siscomex (gramas) para quilogramas
-- Exemplo: '213480' → 213.480 kg
-- ================================================================================
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS fn_convert_siscomex_weight(peso_string VARCHAR(12))
RETURNS DECIMAL(12,3)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE peso_numeric DECIMAL(12,3);
    
    IF peso_string IS NULL OR peso_string = '' THEN
        RETURN 0.000;
    END IF;
    
    -- Remove zeros à esquerda e divide por 1000 (gramas para kg)
    SET peso_numeric = CAST(TRIM(LEADING '0' FROM peso_string) AS DECIMAL(12,0)) / 1000;
    
    RETURN IFNULL(peso_numeric, 0.000);
END$$
DELIMITER ;

-- ================================================================================
-- FUNÇÃO 10: Calcular Rateio de Despesas
-- Rateia despesas extras entre adições conforme critério
-- ================================================================================
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS fn_calculate_rateio(
    p_numero_di VARCHAR(10),
    p_valor_despesa DECIMAL(15,2),
    p_criterio ENUM('VALOR','PESO','QUANTIDADE')
)
RETURNS JSON
READS SQL DATA
BEGIN
    DECLARE resultado JSON;
    DECLARE total_base DECIMAL(15,2);
    
    -- Calcular base total conforme critério
    CASE p_criterio
        WHEN 'VALOR' THEN
            SELECT SUM(valor_vmcv_reais) INTO total_base
            FROM adicoes
            WHERE numero_di = p_numero_di;
            
        WHEN 'PESO' THEN
            SELECT SUM(peso_liquido) INTO total_base
            FROM adicoes
            WHERE numero_di = p_numero_di;
            
        WHEN 'QUANTIDADE' THEN
            SELECT COUNT(*) INTO total_base
            FROM adicoes
            WHERE numero_di = p_numero_di;
    END CASE;
    
    -- Calcular rateio por adição
    SELECT JSON_ARRAYAGG(
        JSON_OBJECT(
            'numero_adicao', numero_adicao,
            'percentual', ROUND(
                CASE p_criterio
                    WHEN 'VALOR' THEN (valor_vmcv_reais / total_base) * 100
                    WHEN 'PESO' THEN (peso_liquido / total_base) * 100
                    WHEN 'QUANTIDADE' THEN (1 / total_base) * 100
                END, 2
            ),
            'valor_rateado', ROUND(
                p_valor_despesa * CASE p_criterio
                    WHEN 'VALOR' THEN (valor_vmcv_reais / total_base)
                    WHEN 'PESO' THEN (peso_liquido / total_base)
                    WHEN 'QUANTIDADE' THEN (1 / total_base)
                END, 2
            )
        )
    ) INTO resultado
    FROM adicoes
    WHERE numero_di = p_numero_di;
    
    RETURN resultado;
END$$
DELIMITER ;

-- ================================================================================
-- FIM DAS FUNÇÕES SQL
-- ================================================================================