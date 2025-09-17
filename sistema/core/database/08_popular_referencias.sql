-- ================================================================================
-- POPULAÇÃO DE TABELAS DE REFERÊNCIA
-- Dados oficiais de ICMS/FCP 2025 e configurações SISCOMEX
-- Versão: 1.0.0
-- ================================================================================

USE importaco_etl_dis;

-- ================================================================================
-- 1. MOEDAS DE REFERÊNCIA (RFB/SISCOMEX)
-- ================================================================================
INSERT INTO moedas_referencia (codigo_siscomex, codigo_iso, nome_moeda, simbolo, decimal_places, ativo) VALUES
('220', 'USD', 'Dólar dos Estados Unidos', '$', 2, TRUE),
('978', 'EUR', 'Euro', '€', 2, TRUE),
('826', 'GBP', 'Libra Esterlina', '£', 2, TRUE),
('392', 'JPY', 'Iene Japonês', '¥', 0, TRUE),
('124', 'CAD', 'Dólar Canadense', 'C$', 2, TRUE),
('036', 'AUD', 'Dólar Australiano', 'A$', 2, TRUE),
('756', 'CHF', 'Franco Suíço', 'CHF', 2, TRUE),
('156', 'CNY', 'Yuan Chinês', '¥', 2, TRUE),
('410', 'KRW', 'Won Sul-Coreano', '₩', 0, TRUE),
('484', 'MXN', 'Peso Mexicano', '$', 2, TRUE),
('032', 'ARS', 'Peso Argentino', '$', 2, TRUE),
('152', 'CLP', 'Peso Chileno', '$', 0, TRUE),
('604', 'PEN', 'Sol Peruano', 'S/', 2, TRUE),
('858', 'UYU', 'Peso Uruguaio', '$U', 2, TRUE),
('986', 'BRL', 'Real Brasileiro', 'R$', 2, TRUE),
('840', 'USD', 'Dólar dos Estados Unidos', 'US$', 2, TRUE),
('344', 'HKD', 'Dólar de Hong Kong', 'HK$', 2, TRUE),
('702', 'SGD', 'Dólar de Singapura', 'S$', 2, TRUE)
ON DUPLICATE KEY UPDATE 
    codigo_iso = VALUES(codigo_iso),
    nome_moeda = VALUES(nome_moeda),
    simbolo = VALUES(simbolo),
    decimal_places = VALUES(decimal_places),
    ativo = VALUES(ativo),
    updated_at = CURRENT_TIMESTAMP;

-- ================================================================================
-- 2. TABELA DE CONFIGURAÇÕES DO SISTEMA
-- ================================================================================
CREATE TABLE IF NOT EXISTS configuracoes_sistema (
    chave VARCHAR(100) NOT NULL,
    valor TEXT NOT NULL,
    categoria VARCHAR(50) NOT NULL,
    descricao TEXT,
    tipo_valor ENUM('STRING','NUMBER','BOOLEAN','JSON') DEFAULT 'STRING',
    ativo BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    PRIMARY KEY (chave),
    INDEX idx_categoria (categoria),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- 3. ALÍQUOTAS ICMS POR ESTADO (2025)
-- Fonte: Alíquotas de ICMS e FCP por UF para Cálculo do DIFAL.md
-- ================================================================================
INSERT INTO configuracoes_sistema (chave, valor, categoria, descricao, tipo_valor) VALUES
-- ICMS Internal Rates
('icms_ac', '19.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Acre', 'NUMBER'),
('icms_al', '20.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Alagoas', 'NUMBER'),
('icms_ap', '18.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Amapá', 'NUMBER'),
('icms_am', '20.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Amazonas', 'NUMBER'),
('icms_ba', '20.50', 'ICMS_INTERNO', 'Alíquota ICMS interna - Bahia', 'NUMBER'),
('icms_ce', '20.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Ceará', 'NUMBER'),
('icms_df', '20.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Distrito Federal', 'NUMBER'),
('icms_es', '17.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Espírito Santo', 'NUMBER'),
('icms_go', '19.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Goiás', 'NUMBER'),
('icms_ma', '23.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Maranhão (a partir de 23/02/2025)', 'NUMBER'),
('icms_mt', '17.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Mato Grosso', 'NUMBER'),
('icms_ms', '17.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Mato Grosso do Sul', 'NUMBER'),
('icms_mg', '18.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Minas Gerais', 'NUMBER'),
('icms_pa', '19.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Pará', 'NUMBER'),
('icms_pb', '20.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Paraíba', 'NUMBER'),
('icms_pr', '19.50', 'ICMS_INTERNO', 'Alíquota ICMS interna - Paraná', 'NUMBER'),
('icms_pe', '20.50', 'ICMS_INTERNO', 'Alíquota ICMS interna - Pernambuco', 'NUMBER'),
('icms_pi', '22.50', 'ICMS_INTERNO', 'Alíquota ICMS interna - Piauí (a partir de 01/04/2025)', 'NUMBER'),
('icms_rj', '22.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Rio de Janeiro', 'NUMBER'),
('icms_rn', '20.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Rio Grande do Norte (a partir de 20/03/2025)', 'NUMBER'),
('icms_rs', '17.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Rio Grande do Sul', 'NUMBER'),
('icms_ro', '19.50', 'ICMS_INTERNO', 'Alíquota ICMS interna - Rondônia', 'NUMBER'),
('icms_rr', '20.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Roraima', 'NUMBER'),
('icms_sc', '17.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Santa Catarina', 'NUMBER'),
('icms_sp', '18.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - São Paulo', 'NUMBER'),
('icms_se', '20.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Sergipe', 'NUMBER'),
('icms_to', '20.00', 'ICMS_INTERNO', 'Alíquota ICMS interna - Tocantins', 'NUMBER'),

-- FCP Rates (Floor Values - Always use minimum/0%)
('fcp_ac', '0.00', 'FCP', 'FCP - Acre (Não possui)', 'NUMBER'),
('fcp_al', '1.00', 'FCP', 'FCP - Alagoas (1.00% a 2.00% = usar piso 1.00%)', 'NUMBER'),
('fcp_ap', '0.00', 'FCP', 'FCP - Amapá (Não possui)', 'NUMBER'),
('fcp_am', '1.50', 'FCP', 'FCP - Amazonas (1.50% a 2.00% = usar piso 1.50%)', 'NUMBER'),
('fcp_ba', '2.00', 'FCP', 'FCP - Bahia', 'NUMBER'),
('fcp_ce', '2.00', 'FCP', 'FCP - Ceará', 'NUMBER'),
('fcp_df', '2.00', 'FCP', 'FCP - Distrito Federal', 'NUMBER'),
('fcp_es', '2.00', 'FCP', 'FCP - Espírito Santo', 'NUMBER'),
('fcp_go', '0.00', 'FCP', 'FCP - Goiás (Até 2.00% = usar piso 0.00%)', 'NUMBER'),
('fcp_ma', '2.00', 'FCP', 'FCP - Maranhão', 'NUMBER'),
('fcp_mt', '0.00', 'FCP', 'FCP - Mato Grosso (Até 2.00% = usar piso 0.00%)', 'NUMBER'),
('fcp_ms', '0.00', 'FCP', 'FCP - Mato Grosso do Sul (Até 2.00% = usar piso 0.00%)', 'NUMBER'),
('fcp_mg', '2.00', 'FCP', 'FCP - Minas Gerais', 'NUMBER'),
('fcp_pa', '0.00', 'FCP', 'FCP - Pará (Não possui)', 'NUMBER'),
('fcp_pb', '2.00', 'FCP', 'FCP - Paraíba', 'NUMBER'),
('fcp_pr', '2.00', 'FCP', 'FCP - Paraná', 'NUMBER'),
('fcp_pe', '2.00', 'FCP', 'FCP - Pernambuco', 'NUMBER'),
('fcp_pi', '2.00', 'FCP', 'FCP - Piauí', 'NUMBER'),
('fcp_rj', '2.00', 'FCP', 'FCP - Rio de Janeiro (Até 4.00% = usar base 2.00%)', 'NUMBER'),
('fcp_rn', '2.00', 'FCP', 'FCP - Rio Grande do Norte', 'NUMBER'),
('fcp_rs', '2.00', 'FCP', 'FCP - Rio Grande do Sul', 'NUMBER'),
('fcp_ro', '2.00', 'FCP', 'FCP - Rondônia', 'NUMBER'),
('fcp_rr', '0.00', 'FCP', 'FCP - Roraima (Até 2.00% = usar piso 0.00%)', 'NUMBER'),
('fcp_sc', '0.00', 'FCP', 'FCP - Santa Catarina (Não possui)', 'NUMBER'),
('fcp_sp', '2.00', 'FCP', 'FCP - São Paulo', 'NUMBER'),
('fcp_se', '1.00', 'FCP', 'FCP - Sergipe (1.00% a 2.00% = usar piso 1.00%)', 'NUMBER'),
('fcp_to', '2.00', 'FCP', 'FCP - Tocantins', 'NUMBER'),

-- ================================================================================
-- 4. CONFIGURAÇÕES SISCOMEX (Conversões e Formatos)
-- ================================================================================
-- Conversions for SISCOMEX values
('siscomex_vmle_divisor', '100', 'SISCOMEX', 'Divisor para valores VMLE (valor por 100)', 'NUMBER'),
('siscomex_vmcv_divisor', '100', 'SISCOMEX', 'Divisor para valores VMCV (valor por 100)', 'NUMBER'),
('siscomex_impostos_divisor', '100', 'SISCOMEX', 'Divisor para impostos (alíquotas por 100)', 'NUMBER'),
('siscomex_pesos_divisor', '1000', 'SISCOMEX', 'Divisor para pesos (gramas para quilos)', 'NUMBER'),
('siscomex_quantidades_divisor', '100000', 'SISCOMEX', 'Divisor para quantidades (micro para unidade)', 'NUMBER'),

-- AFRMM Configuration
('afrmm_aliquota_padrao', '25.00', 'AFRMM', 'Alíquota AFRMM sobre valor do frete marítimo (%)', 'NUMBER'),
('afrmm_base_calculo', 'FRETE_MARITIMO', 'AFRMM', 'Base de cálculo: FRETE_MARITIMO, VALOR_CIF', 'STRING'),
('afrmm_validacao_di', 'true', 'AFRMM', 'Usar valor da DI quando disponível (prevalece sobre cálculo)', 'BOOLEAN'),

-- System Parameters
('sistema_versao', '1.0.0', 'SISTEMA', 'Versão do sistema ETL', 'STRING'),
('processamento_max_xml_size', '50', 'SISTEMA', 'Tamanho máximo de XML em MB', 'NUMBER'),
('processamento_timeout', '300', 'SISTEMA', 'Timeout de processamento em segundos', 'NUMBER'),
('logs_retention_days', '90', 'SISTEMA', 'Dias de retenção de logs', 'NUMBER'),

-- ================================================================================
-- 5. INCENTIVOS FISCAIS POR ESTADO
-- ================================================================================
-- Goiás
('incentivo_go_comexproduzir', '{"tipo": "ENTRADA", "descricao": "COMEXPRODUZIR - Programa de Desenvolvimento Industrial de Goiás", "beneficio": "DIFERIMENTO_ICMS", "vigencia": "PERMANENTE"}', 'INCENTIVOS_FISCAIS', 'Incentivo COMEXPRODUZIR - Goiás', 'JSON'),

-- Espírito Santo
('incentivo_es_invest', '{"tipo": "ENTRADA", "descricao": "INVEST-ES - Programa de Incentivo ao Investimento no Espírito Santo", "beneficio": "REDUCAO_ICMS", "vigencia": "PERMANENTE"}', 'INCENTIVOS_FISCAIS', 'Incentivo INVEST-ES - Espírito Santo', 'JSON'),

-- Minas Gerais
('incentivo_mg_pro_mg', '{"tipo": "ENTRADA", "descricao": "PRO-MG - Programa de Incentivo ao Desenvolvimento de Minas Gerais", "beneficio": "DIFERIMENTO_ICMS", "vigencia": "PERMANENTE"}', 'INCENTIVOS_FISCAIS', 'Incentivo PRO-MG - Minas Gerais', 'JSON'),

-- Ceará
('incentivo_ce_proade', '{"tipo": "ENTRADA", "descricao": "PROADE - Programa de Apoio ao Desenvolvimento Empresarial", "beneficio": "DIFERIMENTO_ICMS", "vigencia": "PERMANENTE"}', 'INCENTIVOS_FISCAIS', 'Incentivo PROADE - Ceará', 'JSON'),

-- Bahia  
('incentivo_ba_desenvolve', '{"tipo": "ENTRADA", "descricao": "DESENVOLVE - Programa de Desenvolvimento Industrial da Bahia", "beneficio": "DIFERIMENTO_ICMS", "vigencia": "PERMANENTE"}', 'INCENTIVOS_FISCAIS', 'Incentivo DESENVOLVE - Bahia', 'JSON'),

-- Rio Grande do Sul
('incentivo_rs_fundopem', '{"tipo": "ENTRADA", "descricao": "FUNDOPEM/RS - Fundo Operação Empresa do Estado", "beneficio": "FINANCIAMENTO_ICMS", "vigencia": "PERMANENTE"}', 'INCENTIVOS_FISCAIS', 'Incentivo FUNDOPEM/RS - Rio Grande do Sul', 'JSON'),

-- Paraná
('incentivo_pr_bom_emprego', '{"tipo": "ENTRADA", "descricao": "Programa Bom Emprego - Incentivo ao emprego e renda", "beneficio": "DIFERIMENTO_ICMS", "vigencia": "PERMANENTE"}', 'INCENTIVOS_FISCAIS', 'Incentivo Bom Emprego - Paraná', 'JSON'),

-- ================================================================================
-- 6. CONFIGURAÇÕES DE VALIDAÇÃO
-- ================================================================================
('validacao_cnpj_obrigatorio', 'true', 'VALIDACAO', 'CNPJ obrigatório em importações', 'BOOLEAN'),
('validacao_ncm_obrigatorio', 'true', 'VALIDACAO', 'NCM obrigatório em adições', 'BOOLEAN'),
('validacao_moeda_obrigatoria', 'true', 'VALIDACAO', 'Código moeda obrigatório', 'BOOLEAN'),
('validacao_valor_minimo', '0.01', 'VALIDACAO', 'Valor mínimo para importações (R$)', 'NUMBER'),
('validacao_data_limite_anos', '10', 'VALIDACAO', 'Limite de anos para DIs antigas', 'NUMBER'),

-- ================================================================================
-- 7. FORMATOS E PADRÕES
-- ================================================================================
('formato_numero_di', '\\d{10}', 'FORMATO', 'Regex para número de DI', 'STRING'),
('formato_cnpj', '\\d{14}', 'FORMATO', 'Regex para CNPJ', 'STRING'),
('formato_ncm', '\\d{8}', 'FORMATO', 'Regex para código NCM', 'STRING'),
('formato_moeda_siscomex', '\\d{3}', 'FORMATO', 'Regex para código moeda SISCOMEX', 'STRING'),

-- Default Processing Settings
('processamento_lote_size', '50', 'PROCESSAMENTO', 'Tamanho do lote para processamento', 'NUMBER'),
('processamento_retry_attempts', '3', 'PROCESSAMENTO', 'Tentativas de reprocessamento', 'NUMBER'),
('processamento_parallel_jobs', '4', 'PROCESSAMENTO', 'Jobs paralelos de processamento', 'NUMBER')

ON DUPLICATE KEY UPDATE 
    valor = VALUES(valor),
    descricao = VALUES(descricao),
    tipo_valor = VALUES(tipo_valor),
    ativo = VALUES(ativo),
    updated_at = CURRENT_TIMESTAMP;

-- ================================================================================
-- 8. POPULATE NCM SAMPLES (Empty - Will be populated from DI processing)
-- ================================================================================
-- NCM table will be populated dynamically as DIs are processed
-- This maintains the "single source of truth" principle

-- ================================================================================
-- 9. CREATE INDEXES FOR PERFORMANCE
-- ================================================================================
-- Additional indexes for configuration table (using ALTER TABLE syntax)
ALTER TABLE configuracoes_sistema ADD INDEX idx_config_categoria_ativo (categoria, ativo);
ALTER TABLE configuracoes_sistema ADD INDEX idx_config_tipo_valor (tipo_valor);

-- ================================================================================
-- VERIFICATION QUERIES
-- ================================================================================

-- Verify moedas population
SELECT 'MOEDAS CARREGADAS' as tabela, COUNT(*) as total FROM moedas_referencia;

-- Verify configurations
SELECT 'CONFIGURAÇÕES POR CATEGORIA' as info, categoria, COUNT(*) as total 
FROM configuracoes_sistema 
WHERE ativo = TRUE 
GROUP BY categoria;

-- Show ICMS rates
SELECT CONCAT('ICMS - ', UPPER(SUBSTRING(chave, 6))) as estado, 
       CONCAT(valor, '%') as aliquota 
FROM configuracoes_sistema 
WHERE categoria = 'ICMS_INTERNO' 
ORDER BY chave;

-- Show FCP rates  
SELECT CONCAT('FCP - ', UPPER(SUBSTRING(chave, 5))) as estado, 
       CONCAT(valor, '%') as aliquota 
FROM configuracoes_sistema 
WHERE categoria = 'FCP' 
ORDER BY chave;

-- ================================================================================
-- END OF REFERENCE DATA POPULATION
-- ================================================================================