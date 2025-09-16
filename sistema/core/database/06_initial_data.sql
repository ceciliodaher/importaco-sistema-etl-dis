-- ================================================================================
-- SISTEMA ETL DE DI's - DADOS INICIAIS MÍNIMOS
-- Apenas dados essenciais para funcionamento - DIs serão importadas dinamicamente
-- Versão: 1.0.0
-- ================================================================================

USE importaco_etl_dis;

-- ================================================================================
-- MOEDAS SISCOMEX - ESSENCIAIS PARA VALIDAÇÃO
-- Baseadas na tabela oficial do Siscomex
-- ================================================================================

INSERT INTO moedas_referencia (
    codigo_siscomex, 
    codigo_iso, 
    nome_moeda, 
    simbolo, 
    decimal_places, 
    ativo
) VALUES
-- Moedas principais mais utilizadas
('220', 'USD', 'DOLAR DOS EUA', 'US$', 2, TRUE),
('978', 'EUR', 'EURO', '€', 2, TRUE),
('032', 'ARS', 'PESO ARGENTINO', '$', 2, TRUE),
('156', 'CNY', 'YUAN RENMINBI', '¥', 2, TRUE),
('392', 'JPY', 'IENE', '¥', 0, TRUE),
('826', 'GBP', 'LIBRA ESTERLINA', '£', 2, TRUE),
('860', 'INR', 'RUPIA INDIANA', '₹', 2, TRUE),
('858', 'UYU', 'PESO URUGUAIO', '$', 2, TRUE),
('124', 'CAD', 'DOLAR CANADENSE', 'C$', 2, TRUE),
('036', 'AUD', 'DOLAR AUSTRALIANO', 'A$', 2, TRUE),
('756', 'CHF', 'FRANCO SUICO', 'CHF', 2, TRUE),
('410', 'KRW', 'WON SUL-COREANO', '₩', 0, TRUE),
('484', 'MXN', 'PESO MEXICANO', '$', 2, TRUE),
('152', 'CLP', 'PESO CHILENO', '$', 2, TRUE),
('604', 'PEN', 'SOL PERUANO', 'S/', 2, TRUE)

ON DUPLICATE KEY UPDATE
    nome_moeda = VALUES(nome_moeda),
    simbolo = VALUES(simbolo),
    ativo = VALUES(ativo),
    updated_at = CURRENT_TIMESTAMP;

-- ================================================================================
-- CONFIGURAÇÕES DO SISTEMA - ESSENCIAIS PARA FUNCIONAMENTO
-- ================================================================================

-- Criar tabela de configurações
CREATE TABLE IF NOT EXISTS configuracoes_sistema (
    chave VARCHAR(100) PRIMARY KEY,
    valor TEXT,
    descricao TEXT,
    tipo ENUM('STRING', 'NUMBER', 'BOOLEAN', 'JSON') DEFAULT 'STRING',
    categoria VARCHAR(50) DEFAULT 'GERAL',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Configurações essenciais
INSERT INTO configuracoes_sistema (chave, valor, descricao, tipo, categoria) VALUES
-- Configurações AFRMM
('afrmm_aliquota_padrao', '25.00', 'Alíquota padrão do AFRMM (%)', 'NUMBER', 'AFRMM'),
('afrmm_divergencia_alerta', '10.00', 'Percentual de divergência para alerta (%)', 'NUMBER', 'AFRMM'),
('afrmm_divergencia_critica', '20.00', 'Percentual de divergência crítica (%)', 'NUMBER', 'AFRMM'),

-- Configurações de câmbio (para validação)
('usd_taxa_minima', '3.00', 'Taxa mínima aceitável para USD', 'NUMBER', 'CAMBIO'),
('usd_taxa_maxima', '8.00', 'Taxa máxima aceitável para USD', 'NUMBER', 'CAMBIO'),
('eur_taxa_minima', '3.50', 'Taxa mínima aceitável para EUR', 'NUMBER', 'CAMBIO'),
('eur_taxa_maxima', '9.00', 'Taxa máxima aceitável para EUR', 'NUMBER', 'CAMBIO'),

-- Configurações Siscomex
('siscomex_taxa_padrao', '214.75', 'Taxa Siscomex padrão (R$)', 'NUMBER', 'SISCOMEX'),

-- Configurações de processamento
('batch_size_default', '1000', 'Tamanho padrão do lote para processamento', 'NUMBER', 'PROCESSAMENTO'),
('timeout_processamento', '300', 'Timeout para processamento de DI (segundos)', 'NUMBER', 'PROCESSAMENTO'),

-- Configurações de validação
('validacao_cnpj_obrigatoria', 'true', 'Validação de CNPJ obrigatória', 'BOOLEAN', 'VALIDACAO'),
('validacao_ncm_obrigatoria', 'true', 'Validação de NCM obrigatória', 'BOOLEAN', 'VALIDACAO'),

-- Configurações de auditoria
('log_conversoes_ativo', 'true', 'Log de conversões ativo', 'BOOLEAN', 'AUDITORIA'),
('retencao_logs_dias', '365', 'Dias de retenção dos logs', 'NUMBER', 'AUDITORIA')

ON DUPLICATE KEY UPDATE
    valor = VALUES(valor),
    descricao = VALUES(descricao),
    updated_at = CURRENT_TIMESTAMP;

-- ================================================================================
-- LOG DE INICIALIZAÇÃO
-- ================================================================================

INSERT INTO conversao_valores (
    numero_di,
    tabela_origem,
    campo_origem,
    valor_original,
    valor_convertido,
    tipo_conversao,
    funcao_utilizada
) VALUES (
    '0000000000',
    'sistema',
    'inicializacao_sistema',
    CAST(UNIX_TIMESTAMP() AS CHAR),
    1.00,
    'INIT_SYSTEM_EMPTY',
    '06_initial_data_minimal'
);

-- ================================================================================
-- VIEWS DE MONITORAMENTO PARA SISTEMA VAZIO
-- ================================================================================

-- View para verificar se o sistema está pronto para receber DIs
CREATE OR REPLACE VIEW v_sistema_status AS
SELECT
    'Sistema Inicializado' as status,
    (SELECT COUNT(*) FROM moedas_referencia WHERE ativo = TRUE) as moedas_ativas,
    (SELECT COUNT(*) FROM configuracoes_sistema) as configuracoes_carregadas,
    (SELECT COUNT(*) FROM declaracoes_importacao) as dis_processadas,
    (SELECT COUNT(*) FROM ncm_referencia WHERE ativo = TRUE) as ncms_catalogados,
    CASE 
        WHEN (SELECT COUNT(*) FROM moedas_referencia WHERE ativo = TRUE) > 10 AND
             (SELECT COUNT(*) FROM configuracoes_sistema) > 10 THEN '✅ PRONTO'
        ELSE '⚠️ CONFIGURANDO'
    END as situacao;

-- View para monitorar crescimento dos dados
CREATE OR REPLACE VIEW v_crescimento_dados AS
SELECT
    DATE(created_at) as data,
    COUNT(*) as novos_registros,
    'DIs Importadas' as tipo
FROM declaracoes_importacao
GROUP BY DATE(created_at)

UNION ALL

SELECT
    DATE(created_at) as data,
    COUNT(*) as novos_registros,
    'NCMs Catalogados' as tipo
FROM ncm_referencia
GROUP BY DATE(created_at)

ORDER BY data DESC, tipo;

-- ================================================================================
-- ATUALIZAR ESTATÍSTICAS DAS TABELAS VAZIAS
-- ================================================================================

ANALYZE TABLE moedas_referencia;
ANALYZE TABLE configuracoes_sistema;
ANALYZE TABLE declaracoes_importacao;
ANALYZE TABLE adicoes;
ANALYZE TABLE ncm_referencia;

-- ================================================================================
-- MENSAGEM DE INICIALIZAÇÃO CONCLUÍDA
-- ================================================================================

SELECT 
    '🚀 SISTEMA INICIALIZADO COM SUCESSO' as status,
    'Tabelas vazias prontas para receber DIs' as observacao,
    CONCAT(
        (SELECT COUNT(*) FROM moedas_referencia WHERE ativo = TRUE), 
        ' moedas configuradas'
    ) as moedas_status,
    CONCAT(
        (SELECT COUNT(*) FROM configuracoes_sistema), 
        ' configurações carregadas'
    ) as config_status;

-- ================================================================================
-- FIM DA INICIALIZAÇÃO MÍNIMA
-- ================================================================================