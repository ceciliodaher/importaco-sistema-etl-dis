-- ================================================================================
-- SISTEMA ETL DE DI's - TRIGGERS ADICIONAIS PARA CAMPOS CALCULADOS
-- Triggers para campos que eram "Calculado via trigger" mas não tinham triggers
-- Versão: 1.0.0
-- ================================================================================

USE importaco_etl_dis;

-- ================================================================================
-- TRIGGER 11: Calcular Valor Unitário da Mercadoria
-- Calcula valor unitário em moeda estrangeira para mercadorias
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_calculate_valor_unitario_mercadoria
BEFORE INSERT ON mercadorias
FOR EACH ROW
BEGIN
    DECLARE v_valor_vmcv_moeda DECIMAL(15,2);
    
    -- Buscar valor VMCV em moeda estrangeira da adição
    SELECT valor_vmcv_moeda INTO v_valor_vmcv_moeda
    FROM adicoes
    WHERE id = NEW.adicao_id;
    
    -- Calcular valor unitário (valor total / quantidade)
    IF NEW.quantidade > 0 AND v_valor_vmcv_moeda > 0 THEN
        SET NEW.valor_unitario_moeda = v_valor_vmcv_moeda / NEW.quantidade;
    ELSE
        SET NEW.valor_unitario_moeda = NULL;
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 12: Atualizar Valor Unitário da Mercadoria (Update)
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_update_valor_unitario_mercadoria
BEFORE UPDATE ON mercadorias
FOR EACH ROW
BEGIN
    DECLARE v_valor_vmcv_moeda DECIMAL(15,2);
    
    -- Se quantidade mudou, recalcular valor unitário
    IF NEW.quantidade != OLD.quantidade THEN
        -- Buscar valor VMCV em moeda estrangeira da adição
        SELECT valor_vmcv_moeda INTO v_valor_vmcv_moeda
        FROM adicoes
        WHERE id = NEW.adicao_id;
        
        -- Recalcular valor unitário
        IF NEW.quantidade > 0 AND v_valor_vmcv_moeda > 0 THEN
            SET NEW.valor_unitario_moeda = v_valor_vmcv_moeda / NEW.quantidade;
        ELSE
            SET NEW.valor_unitario_moeda = NULL;
        END IF;
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 13: Calcular Percentual de Redução dos Acordos
-- Calcula percentual de redução baseado em alíquota normal vs acordo
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_calculate_percentual_reducao
BEFORE INSERT ON acordos_tarifarios
FOR EACH ROW
BEGIN
    DECLARE v_aliquota_normal DECIMAL(7,4);
    DECLARE v_ncm CHAR(8);
    
    -- Buscar NCM da adição
    SELECT a.ncm INTO v_ncm
    FROM adicoes a
    WHERE a.id = NEW.adicao_id;
    
    -- Buscar alíquota normal II praticada para este NCM (mais recente)
    SELECT aliquota_ii_praticada INTO v_aliquota_normal
    FROM ncm_aliquotas_historico
    WHERE codigo_ncm = v_ncm 
      AND aliquota_ii_praticada IS NOT NULL
      AND acordo_aplicado IS NULL
    ORDER BY data_importacao DESC
    LIMIT 1;
    
    -- Calcular percentual de redução se temos as informações
    IF v_aliquota_normal IS NOT NULL AND NEW.aliquota_acordo IS NOT NULL AND v_aliquota_normal > 0 THEN
        SET NEW.percentual_reducao = ((v_aliquota_normal - NEW.aliquota_acordo) / v_aliquota_normal) * 100;
    ELSE
        SET NEW.percentual_reducao = NULL;
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- FIM DOS TRIGGERS ADICIONAIS
-- ================================================================================