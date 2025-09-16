-- ================================================================================
-- SISTEMA ETL DE DI's - ÍNDICES E OTIMIZAÇÕES
-- Índices especializados para performance das consultas
-- Versão: 1.0.0
-- ================================================================================

USE importaco_etl_dis;

-- ================================================================================
-- ÍNDICES COMPOSTOS PARA CONSULTAS FREQUENTES
-- ================================================================================

-- Consultas por importador e período
CREATE INDEX IF NOT EXISTS idx_di_importador_periodo
ON declaracoes_importacao (importador_cnpj, data_registro, status_processamento);

-- Análise de impostos por NCM e período
CREATE INDEX IF NOT EXISTS idx_impostos_ncm_tipo
ON impostos_adicao (tipo_imposto, valor_devido_reais DESC)
USING BTREE;

-- Busca de adições por NCM e valor
CREATE INDEX IF NOT EXISTS idx_adicoes_ncm_valor
ON adicoes (ncm, valor_vmcv_reais DESC, data_registro);

-- Pesquisa de mercadorias por quantidade e valor
CREATE INDEX IF NOT EXISTS idx_mercadorias_quantidade_valor
ON mercadorias (adicao_id, quantidade DESC)
USING BTREE;

-- Análise de acordos tarifários por tipo e redução
CREATE INDEX IF NOT EXISTS idx_acordos_tipo_reducao
ON acordos_tarifarios (tipo_acordo, percentual_reducao DESC, created_at);

-- Consultas de despesas por categoria, valor e validação
CREATE INDEX IF NOT EXISTS idx_despesas_categoria_valor_validacao
ON despesas_extras (categoria, valor_final DESC, validado, numero_di);

-- ================================================================================
-- ÍNDICES PARA RELATÓRIOS ANALÍTICOS
-- ================================================================================

-- Ranking de importadores por valor total
CREATE INDEX IF NOT EXISTS idx_ranking_importadores
ON declaracoes_importacao (valor_total_cif_brl DESC, importador_cnpj, data_registro);

-- Análise temporal de importações
CREATE INDEX IF NOT EXISTS idx_analise_temporal
ON declaracoes_importacao (data_registro, total_adicoes, valor_total_cif_brl DESC);

-- Top NCMs por volume e valor
CREATE INDEX IF NOT EXISTS idx_top_ncms_volume
ON adicoes (ncm, valor_vmcv_reais DESC, peso_liquido DESC);

-- Análise de moedas por período
CREATE INDEX IF NOT EXISTS idx_moedas_periodo
ON adicoes (moeda_codigo, created_at, taxa_cambio_calculada);

-- Consultas de ICMS por UF e valor
CREATE INDEX IF NOT EXISTS idx_icms_uf_valor
ON icms_detalhado (uf_icms, valor_total_icms DESC, situacao);

-- ================================================================================
-- ÍNDICES PARA VIEWS PRINCIPAIS
-- ================================================================================

-- Suporte para v_di_resumo
CREATE INDEX IF NOT EXISTS idx_view_di_resumo
ON adicoes (numero_di, taxa_cambio_calculada);

-- Suporte para v_custo_landed_completo  
CREATE INDEX IF NOT EXISTS idx_view_landed_cost
ON impostos_adicao (adicao_id, tipo_imposto, valor_devido_reais);

-- Suporte para v_despesas_discriminadas
CREATE INDEX IF NOT EXISTS idx_view_despesas_discriminadas
ON despesas_extras (numero_di, categoria, valor_final, grupo_despesa(categoria));

-- Suporte para v_performance_fiscal
CREATE INDEX IF NOT EXISTS idx_view_performance_fiscal
ON declaracoes_importacao (data_registro, valor_total_cif_brl);

-- ================================================================================
-- ÍNDICES PARA AUDITORIA E LOG
-- ================================================================================

-- Log de conversões por DI e timestamp
CREATE INDEX IF NOT EXISTS idx_conversao_di_timestamp
ON conversao_valores (numero_di, timestamp_conversao DESC, tipo_conversao);

-- Histórico de alíquotas por NCM e data
CREATE INDEX IF NOT EXISTS idx_historico_ncm_data
ON ncm_aliquotas_historico (codigo_ncm, data_importacao DESC, acordo_aplicado);

-- Pagamentos Siscomex por código e data
CREATE INDEX IF NOT EXISTS idx_pagamentos_codigo_data
ON pagamentos_siscomex (codigo_receita, data_pagamento, valor_receita DESC);

-- ================================================================================
-- ÍNDICES PARA PESQUISA DE TEXTO COMPLETO
-- ================================================================================

-- Busca em descrições de mercadorias (já existe como FULLTEXT)
-- Melhorar índice existente para performance
DROP INDEX IF EXISTS ft_descricao ON mercadorias;
CREATE FULLTEXT INDEX ft_mercadoria_descricao_otimizado 
ON mercadorias (descricao, especificacao_mercadoria)
WITH PARSER ngram;

-- Busca em descrições de NCM
DROP INDEX IF EXISTS ft_descricao ON ncm_referencia;
CREATE FULLTEXT INDEX ft_ncm_descricao_otimizado
ON ncm_referencia (descricao)
WITH PARSER ngram;

-- Busca em observações de despesas
CREATE FULLTEXT INDEX IF NOT EXISTS ft_despesas_observacoes
ON despesas_extras (observacao_divergencia);

-- ================================================================================
-- ÍNDICES ESPECIALIZADOS POR FUNÇÃO DE NEGÓCIO
-- ================================================================================

-- 1. VALIDAÇÃO AFRMM
CREATE INDEX IF NOT EXISTS idx_afrmm_validacao
ON despesas_extras (categoria, origem_valor, divergencia_percentual, validado)
WHERE categoria = 'AFRMM';

-- 2. CONTROLE DE STATUS DE PROCESSAMENTO
CREATE INDEX IF NOT EXISTS idx_status_processamento_controle
ON declaracoes_importacao (status_processamento, updated_at, numero_di);

-- 3. ANÁLISE DE TAXA DE CÂMBIO
CREATE INDEX IF NOT EXISTS idx_taxa_cambio_analise
ON adicoes (moeda_codigo, taxa_cambio_calculada, created_at);

-- 4. CONTROLE DE ACORDOS TARIFÁRIOS
CREATE INDEX IF NOT EXISTS idx_acordos_controle
ON acordos_tarifarios (adicao_id, tipo_acordo, percentual_reducao DESC);

-- 5. RASTREABILIDADE DE FORNECEDORES
CREATE INDEX IF NOT EXISTS idx_fornecedores_rastreabilidade
ON despesas_extras (fornecedor_cnpj, categoria, valor_final DESC)
WHERE fornecedor_cnpj IS NOT NULL;

-- ================================================================================
-- ÍNDICES PARA DASHBOARD EXECUTIVO
-- ================================================================================

-- KPIs de volume por período
CREATE INDEX IF NOT EXISTS idx_dashboard_volume_periodo
ON declaracoes_importacao (data_registro, status_processamento, valor_total_cif_brl);

-- Estatísticas de AFRMM para dashboard
CREATE INDEX IF NOT EXISTS idx_dashboard_afrmm_stats
ON despesas_extras (categoria, validado, divergencia_percentual, created_at)
WHERE categoria = 'AFRMM';

-- Top importadores para dashboard
CREATE INDEX IF NOT EXISTS idx_dashboard_top_importadores
ON declaracoes_importacao (importador_cnpj, valor_total_cif_brl DESC, data_registro DESC);

-- ================================================================================
-- ÍNDICES PARA OTIMIZAÇÃO DE JOINS
-- ================================================================================

-- Otimizar join entre adicoes e impostos_adicao
CREATE INDEX IF NOT EXISTS idx_join_adicoes_impostos
ON impostos_adicao (adicao_id, tipo_imposto);

-- Otimizar join entre adicoes e mercadorias
CREATE INDEX IF NOT EXISTS idx_join_adicoes_mercadorias
ON mercadorias (adicao_id, quantidade);

-- Otimizar join entre DI e despesas_extras
CREATE INDEX IF NOT EXISTS idx_join_di_despesas
ON despesas_extras (numero_di, categoria, valor_final);

-- Otimizar join entre adicoes e moedas_referencia
CREATE INDEX IF NOT EXISTS idx_join_adicoes_moedas
ON adicoes (moeda_codigo, numero_di);

-- ================================================================================
-- ÍNDICES DE PARTICIONAMENTO (Para grandes volumes)
-- ================================================================================

-- Preparar para particionamento por ano da data_registro
-- (Será implementado quando volume justificar)
CREATE INDEX IF NOT EXISTS idx_particao_ano_mes
ON declaracoes_importacao (YEAR(data_registro), MONTH(data_registro), numero_di);

-- Preparar para particionamento de conversao_valores
CREATE INDEX IF NOT EXISTS idx_particao_conversao_ano
ON conversao_valores (YEAR(timestamp_conversao), numero_di);

-- ================================================================================
-- ÍNDICES FUNCIONAIS ESPECIAIS
-- ================================================================================

-- Índice para calcular percentual de impostos
CREATE INDEX IF NOT EXISTS idx_percentual_impostos_funcional
ON impostos_adicao ((valor_devido_reais / (SELECT valor_vmcv_reais FROM adicoes WHERE id = adicao_id) * 100));

-- Índice para agrupar despesas por grupo lógico
CREATE INDEX IF NOT EXISTS idx_grupo_despesas_funcional
ON despesas_extras (
  (CASE
    WHEN categoria IN ('SISCOMEX', 'AFRMM', 'CAPATAZIA', 'ARMAZENAGEM', 'THC', 'ISPS') 
    THEN 'PORTUARIAS'
    WHEN categoria IN ('DESPACHANTE', 'LIBERACAO_BL') 
    THEN 'DESPACHO'
    WHEN categoria IN ('FRETE_INTERNO', 'SEGURO_INTERNO') 
    THEN 'LOGISTICA'
    ELSE 'OUTRAS'
  END),
  valor_final DESC
);

-- ================================================================================
-- ESTATÍSTICAS E HISTOGRAMAS
-- ================================================================================

-- Forçar atualização de estatísticas para otimizador
ANALYZE TABLE declaracoes_importacao;
ANALYZE TABLE adicoes;
ANALYZE TABLE impostos_adicao;
ANALYZE TABLE despesas_extras;
ANALYZE TABLE mercadorias;

-- ================================================================================
-- CONFIGURAÇÕES DE OTIMIZAÇÃO
-- ================================================================================

-- Configurações específicas para tabelas grandes
ALTER TABLE declaracoes_importacao 
  ENGINE=InnoDB 
  ROW_FORMAT=COMPRESSED 
  KEY_BLOCK_SIZE=8;

ALTER TABLE adicoes 
  ENGINE=InnoDB 
  ROW_FORMAT=COMPRESSED 
  KEY_BLOCK_SIZE=8;

ALTER TABLE impostos_adicao 
  ENGINE=InnoDB 
  ROW_FORMAT=COMPRESSED 
  KEY_BLOCK_SIZE=8;

-- Otimizar tabela de despesas_extras para consultas frequentes
ALTER TABLE despesas_extras 
  ENGINE=InnoDB 
  ROW_FORMAT=DYNAMIC;

-- ================================================================================
-- MONITORAMENTO DE PERFORMANCE
-- ================================================================================

-- View para monitorar uso de índices
CREATE OR REPLACE VIEW v_monitor_indices AS
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    CARDINALITY,
    CASE 
        WHEN CARDINALITY = 0 THEN '🔴 BAIXA'
        WHEN CARDINALITY < 100 THEN '🟡 MÉDIA'
        ELSE '🟢 ALTA'
    END as selectividade
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = 'importaco_etl_dis'
  AND TABLE_NAME IN (
    'declaracoes_importacao', 'adicoes', 'impostos_adicao', 
    'despesas_extras', 'mercadorias'
  )
ORDER BY TABLE_NAME, CARDINALITY DESC;

-- View para identificar queries lentas que podem precisar de índices
CREATE OR REPLACE VIEW v_monitor_queries_performance AS
SELECT
    'Verificar EXPLAIN de queries que demoram >1s' as recomendacao,
    'USE: SHOW PROCESSLIST; para queries ativas' as comando_util,
    'USE: SHOW PROFILES; após SET profiling = 1;' as profiling_comando;

-- ================================================================================
-- FIM DOS ÍNDICES E OTIMIZAÇÕES
-- ================================================================================