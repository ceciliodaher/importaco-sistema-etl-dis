-- ================================================================================
-- SISTEMA ETL DE DI's - TRIGGERS DE AUDITORIA E VALIDAÇÃO
-- Triggers inteligentes para AFRMM, NCM e controle automático
-- Versão: 1.0.0
-- ================================================================================

USE importaco_etl_dis;

-- ================================================================================
-- TRIGGER 1: Validação AFRMM Antes de Inserir Despesas
-- Aplica validação automática do AFRMM conforme frete internacional
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_validate_afrmm_before_insert
BEFORE INSERT ON despesas_extras
FOR EACH ROW
BEGIN
    IF NEW.categoria = 'AFRMM' THEN
        DECLARE frete_valor DECIMAL(15,2) DEFAULT 0;
        DECLARE validacao JSON;
        
        -- Buscar valor do frete internacional da mesma DI
        SELECT IFNULL(valor_reais, 0) INTO frete_valor
        FROM despesas_frete_seguro
        WHERE numero_di = NEW.numero_di AND tipo_despesa = 'FRETE'
        ORDER BY id DESC
        LIMIT 1;
        
        -- Se não encontrou frete na tabela despesas_frete_seguro, buscar como despesa extra
        IF frete_valor = 0 THEN
            SELECT IFNULL(SUM(valor_final), 0) INTO frete_valor
            FROM despesas_extras
            WHERE numero_di = NEW.numero_di AND categoria = 'FRETE_INTERNO';
        END IF;
        
        -- Validar AFRMM usando função especializada
        SET validacao = fn_validate_afrmm(NEW.valor_informado, frete_valor);
        
        -- Aplicar valores validados
        SET NEW.valor_calculado = JSON_UNQUOTE(JSON_EXTRACT(validacao, '$.valor_calculado'));
        SET NEW.valor_final = JSON_UNQUOTE(JSON_EXTRACT(validacao, '$.valor_final'));
        SET NEW.origem_valor = JSON_UNQUOTE(JSON_EXTRACT(validacao, '$.origem'));
        SET NEW.divergencia_percentual = JSON_UNQUOTE(JSON_EXTRACT(validacao, '$.divergencia'));
        SET NEW.base_calculo = frete_valor;
        SET NEW.aliquota = 25.00;
        
        -- Determinar se precisa validação manual
        IF JSON_UNQUOTE(JSON_EXTRACT(validacao, '$.status')) = 'DIVERGENCIA_ALTA' THEN
            SET NEW.validado = FALSE;
            SET NEW.observacao_divergencia = CONCAT(
                'ATENÇÃO: AFRMM informado na DI (R$ ', 
                FORMAT(NEW.valor_informado, 2),
                ') difere em ', 
                ROUND(NEW.divergencia_percentual, 2),
                '% do calculado (R$ ', 
                FORMAT(NEW.valor_calculado, 2),
                '). Frete base: R$ ',
                FORMAT(frete_valor, 2)
            );
        ELSEIF JSON_UNQUOTE(JSON_EXTRACT(validacao, '$.status')) = 'DIVERGENCIA_MEDIA' THEN
            SET NEW.validado = FALSE;
            SET NEW.observacao_divergencia = CONCAT(
                'REVISAR: Divergência de ', 
                ROUND(NEW.divergencia_percentual, 2),
                '% entre AFRMM informado e calculado'
            );
        ELSE
            SET NEW.validado = TRUE;
            SET NEW.observacao_divergencia = NULL;
        END IF;
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 2: Registrar NCM Automaticamente
-- Registra NCM na tabela de referência quando nova adição é inserida
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_register_ncm_after_insert
AFTER INSERT ON adicoes
FOR EACH ROW
BEGIN
    DECLARE ncm_exists INT DEFAULT 0;
    
    -- Verificar se NCM já existe
    SELECT COUNT(*) INTO ncm_exists
    FROM ncm_referencia
    WHERE codigo_ncm = NEW.ncm;
    
    IF ncm_exists = 0 THEN
        -- Inserir novo NCM
        INSERT INTO ncm_referencia (
            codigo_ncm,
            descricao,
            primeira_ocorrencia,
            ultima_ocorrencia,
            total_importacoes,
            ativo
        ) VALUES (
            NEW.ncm,
            CONCAT('NCM ', NEW.ncm, ' - Descrição a definir'),
            CURDATE(),
            CURDATE(),
            1,
            TRUE
        );
    ELSE
        -- Atualizar estatísticas de uso
        UPDATE ncm_referencia
        SET ultima_ocorrencia = CURDATE(),
            total_importacoes = total_importacoes + 1,
            updated_at = CURRENT_TIMESTAMP
        WHERE codigo_ncm = NEW.ncm;
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 3: Registrar Histórico de Alíquotas
-- Registra alíquotas praticadas para análise histórica
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_register_aliquotas_historico
AFTER INSERT ON impostos_adicao
FOR EACH ROW
BEGIN
    DECLARE v_numero_di VARCHAR(10);
    DECLARE v_ncm CHAR(8);
    DECLARE v_data_registro DATE;
    DECLARE v_acordo VARCHAR(20) DEFAULT NULL;
    
    -- Buscar dados da adição
    SELECT a.numero_di, a.ncm INTO v_numero_di, v_ncm
    FROM adicoes a
    WHERE a.id = NEW.adicao_id;
    
    -- Buscar data da DI
    SELECT data_registro INTO v_data_registro
    FROM declaracoes_importacao
    WHERE numero_di = v_numero_di;
    
    -- Verificar se há acordo tarifário
    SELECT tipo_acordo INTO v_acordo
    FROM acordos_tarifarios
    WHERE adicao_id = NEW.adicao_id
    LIMIT 1;
    
    -- Registrar no histórico
    INSERT INTO ncm_aliquotas_historico (
        codigo_ncm,
        numero_di,
        aliquota_ii_praticada,
        aliquota_ipi_praticada,
        aliquota_pis_praticada,
        aliquota_cofins_praticada,
        data_importacao,
        acordo_aplicado,
        uf_destino
    ) VALUES (
        v_ncm,
        v_numero_di,
        CASE WHEN NEW.tipo_imposto = 'II' THEN NEW.aliquota_ad_valorem ELSE NULL END,
        CASE WHEN NEW.tipo_imposto = 'IPI' THEN NEW.aliquota_ad_valorem ELSE NULL END,
        CASE WHEN NEW.tipo_imposto = 'PIS' THEN NEW.aliquota_ad_valorem ELSE NULL END,
        CASE WHEN NEW.tipo_imposto = 'COFINS' THEN NEW.aliquota_ad_valorem ELSE NULL END,
        v_data_registro,
        v_acordo,
        (SELECT SUBSTRING(importador_cnpj, 1, 2) FROM declaracoes_importacao WHERE numero_di = v_numero_di)
    ) ON DUPLICATE KEY UPDATE
        aliquota_ii_praticada = CASE WHEN NEW.tipo_imposto = 'II' THEN NEW.aliquota_ad_valorem ELSE aliquota_ii_praticada END,
        aliquota_ipi_praticada = CASE WHEN NEW.tipo_imposto = 'IPI' THEN NEW.aliquota_ad_valorem ELSE aliquota_ipi_praticada END,
        aliquota_pis_praticada = CASE WHEN NEW.tipo_imposto = 'PIS' THEN NEW.aliquota_ad_valorem ELSE aliquota_pis_praticada END,
        aliquota_cofins_praticada = CASE WHEN NEW.tipo_imposto = 'COFINS' THEN NEW.aliquota_ad_valorem ELSE aliquota_cofins_praticada END;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 4: Atualizar Totais da DI
-- Atualiza totais da DI quando adições são inseridas/atualizadas
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_update_di_totals_after_insert
AFTER INSERT ON adicoes
FOR EACH ROW
BEGIN
    UPDATE declaracoes_importacao
    SET 
        total_adicoes = (
            SELECT COUNT(*) 
            FROM adicoes 
            WHERE numero_di = NEW.numero_di
        ),
        valor_total_cif_brl = (
            SELECT IFNULL(SUM(valor_vmcv_reais), 0)
            FROM adicoes 
            WHERE numero_di = NEW.numero_di
        ),
        valor_total_cif_usd = (
            SELECT IFNULL(SUM(valor_vmcv_moeda), 0)
            FROM adicoes 
            WHERE numero_di = NEW.numero_di
        ),
        updated_at = CURRENT_TIMESTAMP
    WHERE numero_di = NEW.numero_di;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 5: Atualizar Totais da DI (Update)
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_update_di_totals_after_update
AFTER UPDATE ON adicoes
FOR EACH ROW
BEGIN
    UPDATE declaracoes_importacao
    SET 
        valor_total_cif_brl = (
            SELECT IFNULL(SUM(valor_vmcv_reais), 0)
            FROM adicoes 
            WHERE numero_di = NEW.numero_di
        ),
        valor_total_cif_usd = (
            SELECT IFNULL(SUM(valor_vmcv_moeda), 0)
            FROM adicoes 
            WHERE numero_di = NEW.numero_di
        ),
        updated_at = CURRENT_TIMESTAMP
    WHERE numero_di = NEW.numero_di;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 6: Log de Conversões
-- Registra conversões de valores para auditoria
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_log_conversoes
BEFORE INSERT ON conversao_valores
FOR EACH ROW
BEGIN
    -- Validar consistência da conversão
    IF NEW.tipo_conversao = 'DIVISAO_100' THEN
        IF CAST(NEW.valor_original AS DECIMAL(15,0)) / 100 != NEW.valor_convertido THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Conversão DIVISAO_100 inconsistente detectada';
        END IF;
    ELSEIF NEW.tipo_conversao = 'DIVISAO_10000' THEN
        IF CAST(NEW.valor_original AS DECIMAL(7,0)) / 10000 != NEW.valor_convertido THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Conversão DIVISAO_10000 inconsistente detectada';
        END IF;
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 7: Validar Despesas Extras
-- Validações gerais para despesas extras
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_validate_despesas_extras
BEFORE INSERT ON despesas_extras
FOR EACH ROW
BEGIN
    -- Garantir que valor_final não seja nulo
    IF NEW.valor_final IS NULL THEN
        SET NEW.valor_final = IFNULL(NEW.valor_informado, IFNULL(NEW.valor_calculado, 0));
    END IF;
    
    -- Definir origem se não especificada
    IF NEW.origem_valor IS NULL THEN
        IF NEW.valor_informado IS NOT NULL THEN
            SET NEW.origem_valor = 'MANUAL';
        ELSE
            SET NEW.origem_valor = 'CALCULADO';
        END IF;
    END IF;
    
    -- Para categorias específicas, definir se compõe base ICMS
    IF NEW.categoria IN ('CAPATAZIA', 'ARMAZENAGEM', 'THC') THEN
        SET NEW.compoe_base_icms = TRUE;
    END IF;
    
    -- Validar CNPJ do fornecedor se informado
    IF NEW.fornecedor_cnpj IS NOT NULL AND NEW.fornecedor_cnpj NOT REGEXP '^[0-9]{14}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'CNPJ do fornecedor inválido';
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 8: Auditoria de Alterações em Impostos
-- Registra alterações nos valores de impostos
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_audit_impostos_update
AFTER UPDATE ON impostos_adicao
FOR EACH ROW
BEGIN
    DECLARE v_numero_di VARCHAR(10);
    
    -- Buscar número da DI
    SELECT a.numero_di INTO v_numero_di
    FROM adicoes a
    WHERE a.id = NEW.adicao_id;
    
    -- Registrar alteração se valor mudou
    IF OLD.valor_devido_reais != NEW.valor_devido_reais THEN
        INSERT INTO conversao_valores (
            numero_di,
            tabela_origem,
            campo_origem,
            valor_original,
            valor_convertido,
            tipo_conversao,
            funcao_utilizada
        ) VALUES (
            v_numero_di,
            'impostos_adicao',
            CONCAT(NEW.tipo_imposto, '_valor_devido_reais'),
            CAST(OLD.valor_devido_reais AS CHAR),
            NEW.valor_devido_reais,
            'UPDATE_MANUAL',
            'TRIGGER_AUDIT'
        );
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 9: Controle de Status de Processamento
-- Atualiza status da DI conforme processamento
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_update_status_processamento
AFTER INSERT ON impostos_adicao
FOR EACH ROW
BEGIN
    DECLARE v_numero_di VARCHAR(10);
    DECLARE v_total_adicoes INT;
    DECLARE v_adicoes_com_impostos INT;
    
    -- Buscar dados da DI
    SELECT a.numero_di INTO v_numero_di
    FROM adicoes a
    WHERE a.id = NEW.adicao_id;
    
    -- Contar adições total e processadas
    SELECT 
        COUNT(DISTINCT a.id),
        COUNT(DISTINCT CASE WHEN ia.adicao_id IS NOT NULL THEN a.id END)
    INTO v_total_adicoes, v_adicoes_com_impostos
    FROM adicoes a
    LEFT JOIN impostos_adicao ia ON a.id = ia.adicao_id
    WHERE a.numero_di = v_numero_di;
    
    -- Atualizar status se todas adições foram processadas
    IF v_total_adicoes = v_adicoes_com_impostos THEN
        UPDATE declaracoes_importacao
        SET status_processamento = 'COMPLETO',
            updated_at = CURRENT_TIMESTAMP
        WHERE numero_di = v_numero_di;
    ELSEIF v_adicoes_com_impostos > 0 THEN
        UPDATE declaracoes_importacao
        SET status_processamento = 'PROCESSANDO',
            updated_at = CURRENT_TIMESTAMP
        WHERE numero_di = v_numero_di;
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- TRIGGER 10: Validar Taxa de Câmbio
-- Valida taxa de câmbio calculada está dentro de limites razoáveis
-- ================================================================================
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS tr_validate_taxa_cambio
BEFORE INSERT ON adicoes
FOR EACH ROW
BEGIN
    -- Validar se taxa de câmbio está dentro de limites razoáveis
    IF NEW.taxa_cambio_calculada IS NOT NULL THEN
        -- Para USD, taxa entre 3.00 e 8.00 (limites históricos amplos)
        IF NEW.moeda_codigo = '220' AND (NEW.taxa_cambio_calculada < 3.0 OR NEW.taxa_cambio_calculada > 8.0) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = CONCAT('Taxa de câmbio USD suspeita: ', NEW.taxa_cambio_calculada);
        END IF;
        
        -- Para EUR, taxa entre 3.50 e 9.00
        IF NEW.moeda_codigo = '978' AND (NEW.taxa_cambio_calculada < 3.5 OR NEW.taxa_cambio_calculada > 9.0) THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = CONCAT('Taxa de câmbio EUR suspeita: ', NEW.taxa_cambio_calculada);
        END IF;
    END IF;
END$$
DELIMITER ;

-- ================================================================================
-- FIM DOS TRIGGERS
-- ================================================================================