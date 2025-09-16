-- ================================================================================
-- SISTEMA ETL DE DI's - VIEWS CONSOLIDADAS
-- Views analíticas com despesas discriminadas e custo landed completo
-- Versão: 1.0.0
-- ================================================================================

USE importaco_etl_dis;

-- ================================================================================
-- VIEW 1: Resumo Executivo por DI
-- Visão consolidada com todos os componentes do custo landed
-- ================================================================================
CREATE OR REPLACE VIEW v_di_resumo AS
SELECT
    di.numero_di,
    di.data_registro,
    di.importador_nome,
    di.importador_cnpj,
    di.urf_despacho_nome,
    di.total_adicoes,
    di.status_processamento,
    
    -- Valores CIF
    di.valor_total_cif_brl as valor_cif_brl,
    di.valor_total_cif_usd as valor_cif_usd,
    
    -- Impostos detalhados
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'II' THEN imp.valor_devido_reais END), 0) as total_ii,
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.valor_devido_reais END), 0) as total_ipi,
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'PIS' THEN imp.valor_devido_reais END), 0) as total_pis,
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'COFINS' THEN imp.valor_devido_reais END), 0) as total_cofins,
    COALESCE(icms.valor_total_icms, 0) as total_icms,
    
    -- Total de impostos
    COALESCE(SUM(imp.valor_devido_reais), 0) + COALESCE(icms.valor_total_icms, 0) as total_impostos,
    
    -- Despesas extras discriminadas
    COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di), 0) as total_despesas_extras,
    
    -- Breakdown das principais despesas
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'AFRMM' LIMIT 1), 0) as afrmm,
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'SISCOMEX' LIMIT 1), 0) as siscomex,
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'DESPACHANTE' LIMIT 1), 0) as despachante,
    
    -- CUSTO TOTAL LANDED
    di.valor_total_cif_brl + 
    COALESCE(SUM(imp.valor_devido_reais), 0) + 
    COALESCE(icms.valor_total_icms, 0) + 
    COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di), 0) as custo_total_landed,
    
    -- Taxa de câmbio média
    AVG(a.taxa_cambio_calculada) as taxa_cambio_media,
    
    -- Timestamps
    di.created_at as data_processamento,
    di.updated_at as ultima_atualizacao
    
FROM declaracoes_importacao di
LEFT JOIN adicoes a ON di.numero_di = a.numero_di
LEFT JOIN impostos_adicao imp ON a.id = imp.adicao_id
LEFT JOIN icms_detalhado icms ON di.numero_di = icms.numero_di
GROUP BY di.numero_di
ORDER BY di.data_registro DESC;

-- ================================================================================
-- VIEW 2: Despesas Totalmente Discriminadas
-- Todas as despesas organizadas por categoria e grupo lógico
-- ================================================================================
CREATE OR REPLACE VIEW v_despesas_discriminadas AS
SELECT 
    de.numero_di,
    de.categoria,
    de.valor_final,
    de.origem_valor,
    de.validado,
    de.observacao_divergencia,
    
    -- Agrupamento lógico das despesas
    CASE
        WHEN de.categoria IN ('SISCOMEX', 'AFRMM', 'CAPATAZIA', 'ARMAZENAGEM', 
                              'THC', 'ISPS', 'SCANNER', 'DESCONSOLIDACAO', 'SDA', 'DEMURRAGE') 
        THEN 'PORTUARIAS'
        WHEN de.categoria IN ('DESPACHANTE', 'LIBERACAO_BL') 
        THEN 'DESPACHO'
        WHEN de.categoria IN ('FRETE_INTERNO', 'SEGURO_INTERNO') 
        THEN 'LOGISTICA_INTERNA'
        WHEN de.categoria = 'BANCARIO' 
        THEN 'FINANCEIRO'
        ELSE 'OUTRAS'
    END as grupo_despesa,
    
    -- Informações complementares
    de.numero_documento,
    de.fornecedor_nome,
    de.data_despesa,
    de.compoe_base_icms,
    de.created_by,
    de.created_at,
    
    -- Dados específicos do AFRMM
    CASE WHEN de.categoria = 'AFRMM' THEN
        JSON_OBJECT(
            'valor_informado', de.valor_informado,
            'valor_calculado', de.valor_calculado,
            'base_calculo', de.base_calculo,
            'divergencia_percentual', de.divergencia_percentual,
            'aliquota', de.aliquota
        )
    ELSE NULL END as detalhes_afrmm
    
FROM despesas_extras de
ORDER BY de.numero_di, grupo_despesa, de.categoria;

-- ================================================================================
-- VIEW 3: Análise Detalhada por Adição
-- Breakdown completo por adição com impostos e mercadorias
-- ================================================================================
CREATE OR REPLACE VIEW v_adicoes_completas AS
SELECT
    a.numero_di,
    a.numero_adicao,
    a.ncm,
    ncm_ref.descricao as ncm_descricao,
    a.valor_vmcv_reais as valor_cif,
    a.taxa_cambio_calculada,
    a.moeda_codigo,
    moeda_ref.codigo_iso as moeda_iso,
    moeda_ref.simbolo as moeda_simbolo,
    
    -- Pesos
    a.peso_liquido,
    a.peso_bruto,
    
    -- Impostos por tipo
    MAX(CASE WHEN imp.tipo_imposto = 'II' THEN imp.valor_devido_reais END) as ii_valor,
    MAX(CASE WHEN imp.tipo_imposto = 'II' THEN imp.aliquota_ad_valorem END) as ii_aliquota,
    MAX(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.valor_devido_reais END) as ipi_valor,
    MAX(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.aliquota_ad_valorem END) as ipi_aliquota,
    MAX(CASE WHEN imp.tipo_imposto = 'PIS' THEN imp.valor_devido_reais END) as pis_valor,
    MAX(CASE WHEN imp.tipo_imposto = 'COFINS' THEN imp.valor_devido_reais END) as cofins_valor,
    
    -- Custo total da adição (CIF + impostos)
    a.valor_vmcv_reais + COALESCE(SUM(imp.valor_devido_reais), 0) as custo_total_adicao,
    
    -- Acordos tarifários
    GROUP_CONCAT(DISTINCT at.tipo_acordo SEPARATOR ', ') as acordos_aplicados,
    MAX(at.percentual_reducao) as maior_reducao_ii,
    
    -- Mercadorias
    COUNT(DISTINCT m.id) as quantidade_mercadorias,
    SUM(m.quantidade) as quantidade_total_mercadorias,
    GROUP_CONCAT(DISTINCT m.unidade_medida SEPARATOR ', ') as unidades_medida,
    
    -- Informações complementares
    a.numero_li,
    a.created_at as data_processamento
    
FROM adicoes a
LEFT JOIN impostos_adicao imp ON a.id = imp.adicao_id
LEFT JOIN acordos_tarifarios at ON a.id = at.adicao_id
LEFT JOIN mercadorias m ON a.id = m.adicao_id
LEFT JOIN ncm_referencia ncm_ref ON a.ncm = ncm_ref.codigo_ncm
LEFT JOIN moedas_referencia moeda_ref ON a.moeda_codigo = moeda_ref.codigo_siscomex
GROUP BY a.id
ORDER BY a.numero_di, a.numero_adicao;

-- ================================================================================
-- VIEW 4: Custo Landed Completo com Breakdown
-- Análise completa do custo landed com todos os componentes
-- ================================================================================
CREATE OR REPLACE VIEW v_custo_landed_completo AS
SELECT
    di.numero_di,
    di.data_registro,
    di.importador_nome,
    di.importador_cnpj,
    
    -- Componente 1: CIF
    di.valor_total_cif_brl as cif_brl,
    di.valor_total_cif_usd as cif_usd,
    
    -- Componente 2: Impostos Federais
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'II' THEN imp.valor_devido_reais END), 0) as ii,
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.valor_devido_reais END), 0) as ipi,
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'PIS' THEN imp.valor_devido_reais END), 0) as pis,
    COALESCE(SUM(CASE WHEN imp.tipo_imposto = 'COFINS' THEN imp.valor_devido_reais END), 0) as cofins,
    COALESCE(icms.valor_total_icms, 0) as icms,
    
    -- Componente 3: Despesas Portuárias Discriminadas
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'SISCOMEX'), 0) as siscomex,
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'AFRMM'), 0) as afrmm,
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'CAPATAZIA'), 0) as capatazia,
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'ARMAZENAGEM'), 0) as armazenagem,
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'THC'), 0) as thc,
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'DEMURRAGE'), 0) as demurrage,
    
    -- Componente 4: Despesas de Despacho
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'DESPACHANTE'), 0) as despachante,
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'LIBERACAO_BL'), 0) as liberacao_bl,
    
    -- Componente 5: Logística Interna
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'FRETE_INTERNO'), 0) as frete_interno,
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'SEGURO_INTERNO'), 0) as seguro_interno,
    
    -- Componente 6: Outras Despesas
    COALESCE((SELECT valor_final FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'BANCARIO'), 0) as bancario,
    COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria = 'OUTROS'), 0) as outras,
    
    -- Totais por Componente
    COALESCE(SUM(imp.valor_devido_reais), 0) + COALESCE(icms.valor_total_icms, 0) as total_impostos,
    COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria IN ('SISCOMEX','AFRMM','CAPATAZIA','ARMAZENAGEM','THC','DEMURRAGE','ISPS','SCANNER')), 0) as total_portuarias,
    COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria IN ('DESPACHANTE','LIBERACAO_BL')), 0) as total_despacho,
    COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di AND categoria IN ('FRETE_INTERNO','SEGURO_INTERNO')), 0) as total_logistica,
    COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di), 0) as total_despesas,
    
    -- CUSTO LANDED FINAL
    di.valor_total_cif_brl + 
    COALESCE(SUM(imp.valor_devido_reais), 0) + 
    COALESCE(icms.valor_total_icms, 0) + 
    COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di), 0) as custo_total_landed,
    
    -- Análise percentual
    ROUND((COALESCE(SUM(imp.valor_devido_reais), 0) + COALESCE(icms.valor_total_icms, 0)) / 
          (di.valor_total_cif_brl + COALESCE(SUM(imp.valor_devido_reais), 0) + COALESCE(icms.valor_total_icms, 0) + COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di), 0)) * 100, 2) as percentual_impostos,
    ROUND(COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di), 0) / 
          (di.valor_total_cif_brl + COALESCE(SUM(imp.valor_devido_reais), 0) + COALESCE(icms.valor_total_icms, 0) + COALESCE((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di), 0)) * 100, 2) as percentual_despesas
    
FROM declaracoes_importacao di
LEFT JOIN adicoes a ON di.numero_di = a.numero_di
LEFT JOIN impostos_adicao imp ON a.id = imp.adicao_id
LEFT JOIN icms_detalhado icms ON di.numero_di = icms.numero_di
GROUP BY di.numero_di
ORDER BY di.data_registro DESC;

-- ================================================================================
-- VIEW 5: Auditoria AFRMM Detalhada
-- Análise específica da validação de AFRMM
-- ================================================================================
CREATE OR REPLACE VIEW v_auditoria_afrmm AS
SELECT 
    de.numero_di,
    di.data_registro,
    di.importador_nome,
    de.valor_informado as afrmm_declarado_di,
    de.valor_calculado as afrmm_calculado_25pct,
    de.valor_final as afrmm_utilizado,
    de.base_calculo as frete_base,
    de.origem_valor,
    de.divergencia_percentual,
    
    -- Status visual da validação
    CASE 
        WHEN de.origem_valor = 'DI' AND ABS(de.divergencia_percentual) > 20 THEN '🔴 DIVERGÊNCIA ALTA'
        WHEN de.origem_valor = 'DI' AND ABS(de.divergencia_percentual) > 10 THEN '🟡 DIVERGÊNCIA MÉDIA'
        WHEN de.origem_valor = 'DI' THEN '🟢 DI VALIDADO'
        WHEN de.origem_valor = 'CALCULADO' THEN '🔵 CALCULADO AUTO'
        ELSE '⚪ MANUAL'
    END as status_visual,
    
    -- Análise da divergência
    CASE
        WHEN de.divergencia_percentual > 20 THEN 'REVISAR URGENTE'
        WHEN de.divergencia_percentual > 10 THEN 'REVISAR'
        WHEN de.divergencia_percentual BETWEEN -10 AND 10 THEN 'NORMAL'
        WHEN de.divergencia_percentual < -10 THEN 'AFRMM BAIXO'
        ELSE 'SEM ANÁLISE'
    END as classificacao_divergencia,
    
    de.validado,
    de.observacao_divergencia,
    de.created_at as data_lancamento,
    de.created_by as usuario_lancamento
    
FROM despesas_extras de
JOIN declaracoes_importacao di ON de.numero_di = di.numero_di
WHERE de.categoria = 'AFRMM'
ORDER BY ABS(de.divergencia_percentual) DESC, de.created_at DESC;

-- ================================================================================
-- VIEW 6: Análise de Performance Fiscal
-- Métricas temporais de importação e tributação
-- ================================================================================
CREATE OR REPLACE VIEW v_performance_fiscal AS
SELECT
    YEAR(di.data_registro) as ano,
    MONTH(di.data_registro) as mes,
    DATE_FORMAT(di.data_registro, '%Y-%m') as ano_mes,
    
    -- Volumes
    COUNT(DISTINCT di.numero_di) as total_dis,
    COUNT(DISTINCT a.id) as total_adicoes,
    
    -- Valores consolidados (em milhões)
    ROUND(SUM(di.valor_total_cif_brl) / 1000000, 2) as cif_total_milhoes,
    ROUND(SUM(CASE WHEN imp.tipo_imposto = 'II' THEN imp.valor_devido_reais ELSE 0 END) / 1000000, 2) as ii_total_milhoes,
    ROUND(SUM(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.valor_devido_reais ELSE 0 END) / 1000000, 2) as ipi_total_milhoes,
    
    -- Análise de acordos tarifários
    COUNT(DISTINCT at.adicao_id) as adicoes_com_acordo,
    ROUND(AVG(at.percentual_reducao), 2) as reducao_media_acordos,
    
    -- Principais moedas (top 3)
    (SELECT GROUP_CONCAT(DISTINCT CONCAT(moeda_ref.codigo_iso, ' (', COUNT(*), ')') ORDER BY COUNT(*) DESC LIMIT 3)
     FROM adicoes a2 
     JOIN moedas_referencia moeda_ref ON a2.moeda_codigo = moeda_ref.codigo_siscomex
     WHERE YEAR(a2.created_at) = YEAR(di.data_registro)
     AND MONTH(a2.created_at) = MONTH(di.data_registro)) as moedas_principais,
    
    -- Taxa de câmbio média USD
    ROUND(AVG(CASE WHEN a.moeda_codigo = '220' THEN a.taxa_cambio_calculada END), 4) as usd_taxa_media,
    
    -- NCMs mais importados (top 5)
    (SELECT GROUP_CONCAT(DISTINCT a3.ncm ORDER BY COUNT(*) DESC LIMIT 5)
     FROM adicoes a3
     WHERE YEAR(a3.created_at) = YEAR(di.data_registro)
     AND MONTH(a3.created_at) = MONTH(di.data_registro)) as ncms_principais,
     
    -- Despesas médias
    ROUND(AVG((SELECT SUM(valor_final) FROM despesas_extras de WHERE de.numero_di = di.numero_di)), 2) as despesas_extras_media
    
FROM declaracoes_importacao di
JOIN adicoes a ON di.numero_di = a.numero_di
LEFT JOIN impostos_adicao imp ON a.id = imp.adicao_id
LEFT JOIN acordos_tarifarios at ON a.id = at.adicao_id
GROUP BY YEAR(di.data_registro), MONTH(di.data_registro)
ORDER BY ano DESC, mes DESC;

-- ================================================================================
-- VIEW 7: Top NCMs por Volume
-- Análise dos NCMs mais importados com estatísticas
-- ================================================================================
CREATE OR REPLACE VIEW v_top_ncms AS
SELECT
    a.ncm,
    ncm_ref.descricao as ncm_descricao,
    COUNT(DISTINCT di.numero_di) as total_dis,
    COUNT(*) as total_adicoes,
    
    -- Valores
    SUM(a.valor_vmcv_reais) as valor_total_cif,
    AVG(a.valor_vmcv_reais) as valor_medio_adicao,
    
    -- Pesos
    SUM(a.peso_liquido) as peso_total_kg,
    AVG(a.peso_liquido) as peso_medio_kg,
    
    -- Impostos médios praticados
    ROUND(AVG(CASE WHEN imp.tipo_imposto = 'II' THEN imp.aliquota_ad_valorem END) * 100, 2) as ii_aliquota_media_pct,
    ROUND(AVG(CASE WHEN imp.tipo_imposto = 'IPI' THEN imp.aliquota_ad_valorem END) * 100, 2) as ipi_aliquota_media_pct,
    
    -- Análise temporal
    MIN(di.data_registro) as primeira_importacao,
    MAX(di.data_registro) as ultima_importacao,
    
    -- Acordos
    COUNT(DISTINCT at.tipo_acordo) as tipos_acordo_aplicados,
    GROUP_CONCAT(DISTINCT at.tipo_acordo) as acordos_utilizados
    
FROM adicoes a
JOIN declaracoes_importacao di ON a.numero_di = di.numero_di
LEFT JOIN ncm_referencia ncm_ref ON a.ncm = ncm_ref.codigo_ncm
LEFT JOIN impostos_adicao imp ON a.id = imp.adicao_id
LEFT JOIN acordos_tarifarios at ON a.id = at.adicao_id
GROUP BY a.ncm
HAVING COUNT(*) >= 2  -- Apenas NCMs com pelo menos 2 ocorrências
ORDER BY valor_total_cif DESC
LIMIT 50;

-- ================================================================================
-- VIEW 8: Dashboard Executivo
-- KPIs principais para dashboard
-- ================================================================================
CREATE OR REPLACE VIEW v_dashboard_executivo AS
SELECT
    -- Período atual (último mês)
    (SELECT COUNT(*) FROM declaracoes_importacao WHERE data_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as dis_ultimo_mes,
    (SELECT ROUND(SUM(valor_total_cif_brl) / 1000000, 2) FROM declaracoes_importacao WHERE data_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as cif_ultimo_mes_milhoes,
    
    -- Comparativo mês anterior
    (SELECT COUNT(*) FROM declaracoes_importacao WHERE data_registro BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as dis_mes_anterior,
    (SELECT ROUND(SUM(valor_total_cif_brl) / 1000000, 2) FROM declaracoes_importacao WHERE data_registro BETWEEN DATE_SUB(CURDATE(), INTERVAL 60 DAY) AND DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as cif_mes_anterior_milhoes,
    
    -- Totais gerais
    (SELECT COUNT(*) FROM declaracoes_importacao) as total_dis_processadas,
    (SELECT ROUND(SUM(valor_total_cif_brl) / 1000000, 2) FROM declaracoes_importacao) as cif_total_milhoes,
    (SELECT COUNT(DISTINCT importador_cnpj) FROM declaracoes_importacao) as total_importadores,
    
    -- Status de processamento
    (SELECT COUNT(*) FROM declaracoes_importacao WHERE status_processamento = 'COMPLETO') as dis_completas,
    (SELECT COUNT(*) FROM declaracoes_importacao WHERE status_processamento = 'PENDENTE') as dis_pendentes,
    (SELECT COUNT(*) FROM declaracoes_importacao WHERE status_processamento = 'ERRO') as dis_erro,
    
    -- Estatísticas AFRMM
    (SELECT COUNT(*) FROM despesas_extras WHERE categoria = 'AFRMM' AND validado = FALSE) as afrmm_nao_validados,
    (SELECT COUNT(*) FROM despesas_extras WHERE categoria = 'AFRMM' AND ABS(divergencia_percentual) > 20) as afrmm_divergencia_alta,
    
    -- Taxa câmbio USD média (últimos 30 dias)
    (SELECT ROUND(AVG(taxa_cambio_calculada), 4) 
     FROM adicoes a JOIN declaracoes_importacao di ON a.numero_di = di.numero_di 
     WHERE a.moeda_codigo = '220' AND di.data_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)) as usd_taxa_media_30d;

-- ================================================================================
-- FIM DAS VIEWS
-- ================================================================================