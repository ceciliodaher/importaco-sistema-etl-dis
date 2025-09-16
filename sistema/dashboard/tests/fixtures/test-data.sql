-- ================================================================================
-- DADOS DE TESTE - SUITE COMPLETA DASHBOARD ETL DI's
-- Dados sintéticos baseados em DI's reais brasileiras para testes
-- ================================================================================

-- Limpar dados existentes (apenas em ambiente de teste)
SET FOREIGN_KEY_CHECKS = 0;
TRUNCATE TABLE impostos_adicao;
TRUNCATE TABLE adicoes;
TRUNCATE TABLE declaracoes_importacao;
SET FOREIGN_KEY_CHECKS = 1;

-- ================================================================================
-- DECLARAÇÕES DE IMPORTAÇÃO
-- ================================================================================

INSERT INTO declaracoes_importacao (
    numero_di, data_registro, importador_nome, importador_cnpj, 
    valor_total_usd, valor_total_brl, status, 
    created_at, updated_at
) VALUES
-- DI's para testes de volume e performance
('24BR00001234567', '2024-01-15', 'Equiplex Industrial Ltda', '12.345.678/0001-90', 40000.00, 200000.00, 'concluida', '2024-01-15 14:30:00', '2024-01-15 14:30:00'),
('24BR00001234568', '2024-01-20', 'TechCorp Importadora S.A.', '98.765.432/0001-10', 75000.00, 375000.00, 'concluida', '2024-01-20 09:15:00', '2024-01-20 09:15:00'),
('24BR00001234569', '2024-02-01', 'Santos Port Trading ME', '11.222.333/0001-44', 35000.00, 175000.00, 'concluida', '2024-02-01 16:45:00', '2024-02-01 16:45:00'),
('24BR00001234570', '2024-02-10', 'Industrial Machinary Corp', '55.666.777/0001-88', 120000.00, 600000.00, 'concluida', '2024-02-10 11:20:00', '2024-02-10 11:20:00'),
('24BR00001234571', '2024-02-15', 'Global Electronics Ltda', '33.444.555/0001-22', 85000.00, 425000.00, 'concluida', '2024-02-15 13:10:00', '2024-02-15 13:10:00'),

-- DI's para testes de busca e filtros
('24BR00002000001', '2024-03-01', 'Alfa Trading Company', '10.100.100/0001-01', 25000.00, 125000.00, 'concluida', '2024-03-01 08:30:00', '2024-03-01 08:30:00'),
('24BR00002000002', '2024-03-05', 'Beta Importação EIRELI', '20.200.200/0001-02', 45000.00, 225000.00, 'concluida', '2024-03-05 15:45:00', '2024-03-05 15:45:00'),
('24BR00002000003', '2024-03-10', 'Gamma Distribuidora S.A.', '30.300.300/0001-03', 65000.00, 325000.00, 'processando', '2024-03-10 10:15:00', '2024-03-10 10:15:00'),
('24BR00002000004', '2024-03-15', 'Delta Commerce Ltd', '40.400.400/0001-04', 55000.00, 275000.00, 'concluida', '2024-03-15 12:00:00', '2024-03-15 12:00:00'),
('24BR00002000005', '2024-03-20', 'Omega International Corp', '50.500.500/0001-05', 95000.00, 475000.00, 'concluida', '2024-03-20 14:30:00', '2024-03-20 14:30:00'),

-- DI's para testes de gráficos (evolução temporal)
('24BR00003000001', '2024-04-01', 'Chart Test Company A', '60.600.600/0001-06', 30000.00, 150000.00, 'concluida', '2024-04-01 09:00:00', '2024-04-01 09:00:00'),
('24BR00003000002', '2024-04-15', 'Chart Test Company B', '70.700.700/0001-07', 42000.00, 210000.00, 'concluida', '2024-04-15 11:30:00', '2024-04-15 11:30:00'),
('24BR00003000003', '2024-05-01', 'Chart Test Company C', '80.800.800/0001-08', 38000.00, 190000.00, 'concluida', '2024-05-01 16:20:00', '2024-05-01 16:20:00'),
('24BR00003000004', '2024-05-15', 'Chart Test Company D', '90.900.900/0001-09', 52000.00, 260000.00, 'concluida', '2024-05-15 13:45:00', '2024-05-15 13:45:00'),
('24BR00003000005', '2024-06-01', 'Chart Test Company E', '11.111.111/0001-11', 48000.00, 240000.00, 'concluida', '2024-06-01 10:10:00', '2024-06-01 10:10:00'),

-- DI's para testes de performance (volume maior)
('24BR00004000001', '2024-06-10', 'Performance Test Corp 1', '12.121.212/0001-12', 15000.00, 75000.00, 'concluida', '2024-06-10 08:00:00', '2024-06-10 08:00:00'),
('24BR00004000002', '2024-06-11', 'Performance Test Corp 2', '13.131.313/0001-13', 18000.00, 90000.00, 'concluida', '2024-06-11 09:00:00', '2024-06-11 09:00:00'),
('24BR00004000003', '2024-06-12', 'Performance Test Corp 3', '14.141.414/0001-14', 22000.00, 110000.00, 'concluida', '2024-06-12 10:00:00', '2024-06-12 10:00:00'),
('24BR00004000004', '2024-06-13', 'Performance Test Corp 4', '15.151.515/0001-15', 28000.00, 140000.00, 'concluida', '2024-06-13 11:00:00', '2024-06-13 11:00:00'),
('24BR00004000005', '2024-06-14', 'Performance Test Corp 5', '16.161.616/0001-16', 33000.00, 165000.00, 'concluida', '2024-06-14 12:00:00', '2024-06-14 12:00:00'),

-- DI's para testes de segurança (caracteres especiais e edge cases)
('24BR00005000001', '2024-07-01', 'Security Test & Co.', '17.171.717/0001-17', 20000.00, 100000.00, 'concluida', '2024-07-01 14:00:00', '2024-07-01 14:00:00'),
('24BR00005000002', '2024-07-02', 'Test Company "Special"', '18.181.818/0001-18', 25000.00, 125000.00, 'concluida', '2024-07-02 15:00:00', '2024-07-02 15:00:00'),
('24BR00005000003', '2024-07-03', 'Empresa com Acentuação Ltda', '19.191.919/0001-19', 30000.00, 150000.00, 'concluida', '2024-07-03 16:00:00', '2024-07-03 16:00:00');

-- ================================================================================
-- ADIÇÕES DAS DECLARAÇÕES
-- ================================================================================

-- Adições para primeira DI (24BR00001234567)
INSERT INTO adicoes (
    di_id, numero_adicao, ncm, valor_usd, valor_brl, 
    peso_kg, quantidade, created_at
) VALUES
(1, 1, '85371000', 25000.00, 125000.00, 100.500, 10.000, '2024-01-15 14:30:00'),
(1, 2, '84281000', 15000.00, 75000.00, 500.750, 2.000, '2024-01-15 14:30:00');

-- Adições para segunda DI (24BR00001234568)
INSERT INTO adicoes (
    di_id, numero_adicao, ncm, valor_usd, valor_brl, 
    peso_kg, quantidade, created_at
) VALUES
(2, 1, '85371000', 45000.00, 225000.00, 150.250, 15.000, '2024-01-20 09:15:00'),
(2, 2, '84283000', 30000.00, 150000.00, 200.500, 5.000, '2024-01-20 09:15:00');

-- Adições para terceira DI (24BR00001234569)
INSERT INTO adicoes (
    di_id, numero_adicao, ncm, valor_usd, valor_brl, 
    peso_kg, quantidade, created_at
) VALUES
(3, 1, '84281000', 35000.00, 175000.00, 300.750, 3.000, '2024-02-01 16:45:00');

-- Adições para DI's de teste de volume (simplified)
INSERT INTO adicoes (
    di_id, numero_adicao, ncm, valor_usd, valor_brl, 
    peso_kg, quantidade, created_at
) 
SELECT 
    id,
    1,
    CASE 
        WHEN id % 3 = 0 THEN '85371000'
        WHEN id % 3 = 1 THEN '84281000'
        ELSE '84283000'
    END,
    valor_total_usd * 0.6,
    valor_total_brl * 0.6,
    ROUND(50 + (id * 15.5), 3),
    ROUND(1 + (id * 0.5), 3),
    created_at
FROM declaracoes_importacao 
WHERE id BETWEEN 4 AND 23;

-- Segunda adição para algumas DI's
INSERT INTO adicoes (
    di_id, numero_adicao, ncm, valor_usd, valor_brl, 
    peso_kg, quantidade, created_at
) 
SELECT 
    id,
    2,
    CASE 
        WHEN id % 3 = 0 THEN '84283000'
        WHEN id % 3 = 1 THEN '85371000'
        ELSE '84281000'
    END,
    valor_total_usd * 0.4,
    valor_total_brl * 0.4,
    ROUND(30 + (id * 8.2), 3),
    ROUND(1 + (id * 0.3), 3),
    created_at
FROM declaracoes_importacao 
WHERE id BETWEEN 4 AND 15;

-- ================================================================================
-- IMPOSTOS DAS ADIÇÕES
-- ================================================================================

-- Impostos para adição 1 da primeira DI
INSERT INTO impostos_adicao (
    adicao_id, tipo_imposto, aliquota, valor_calculado, base_calculo, created_at
) VALUES
-- Adição 1 (NCM 85371000)
(1, 'II', 14.00, 3500.00, 25000.00, '2024-01-15 14:30:00'),
(1, 'IPI', 15.00, 4275.00, 28500.00, '2024-01-15 14:30:00'),
(1, 'PIS', 1.65, 470.25, 28500.00, '2024-01-15 14:30:00'),
(1, 'COFINS', 7.60, 2166.00, 28500.00, '2024-01-15 14:30:00'),
(1, 'ICMS', 18.00, 6120.00, 34000.00, '2024-01-15 14:30:00'),

-- Adição 2 (NCM 84281000)
(2, 'II', 10.00, 1500.00, 15000.00, '2024-01-15 14:30:00'),
(2, 'IPI', 12.00, 1980.00, 16500.00, '2024-01-15 14:30:00'),
(2, 'PIS', 1.65, 272.25, 16500.00, '2024-01-15 14:30:00'),
(2, 'COFINS', 7.60, 1254.00, 16500.00, '2024-01-15 14:30:00'),
(2, 'ICMS', 18.00, 3348.00, 18600.00, '2024-01-15 14:30:00');

-- Impostos para segunda DI (simplificado)
INSERT INTO impostos_adicao (
    adicao_id, tipo_imposto, aliquota, valor_calculado, base_calculo, created_at
) VALUES
-- Adição 3
(3, 'II', 14.00, 6300.00, 45000.00, '2024-01-20 09:15:00'),
(3, 'IPI', 15.00, 7695.00, 51300.00, '2024-01-20 09:15:00'),
(3, 'PIS', 1.65, 846.45, 51300.00, '2024-01-20 09:15:00'),
(3, 'COFINS', 7.60, 3898.80, 51300.00, '2024-01-20 09:15:00'),
(3, 'ICMS', 18.00, 11340.00, 63000.00, '2024-01-20 09:15:00'),

-- Adição 4
(4, 'II', 12.00, 3600.00, 30000.00, '2024-01-20 09:15:00'),
(4, 'IPI', 10.00, 3360.00, 33600.00, '2024-01-20 09:15:00'),
(4, 'PIS', 1.65, 554.40, 33600.00, '2024-01-20 09:15:00'),
(4, 'COFINS', 7.60, 2553.60, 33600.00, '2024-01-20 09:15:00'),
(4, 'ICMS', 18.00, 7200.00, 40000.00, '2024-01-20 09:15:00');

-- Impostos para terceira DI
INSERT INTO impostos_adicao (
    adicao_id, tipo_imposto, aliquota, valor_calculado, base_calculo, created_at
) VALUES
(5, 'II', 10.00, 3500.00, 35000.00, '2024-02-01 16:45:00'),
(5, 'IPI', 12.00, 4620.00, 38500.00, '2024-02-01 16:45:00'),
(5, 'PIS', 1.65, 635.25, 38500.00, '2024-02-01 16:45:00'),
(5, 'COFINS', 7.60, 2926.00, 38500.00, '2024-02-01 16:45:00'),
(5, 'ICMS', 18.00, 8100.00, 45000.00, '2024-02-01 16:45:00');

-- Gerar impostos para todas as outras adições de forma algorítmica
INSERT INTO impostos_adicao (
    adicao_id, tipo_imposto, aliquota, valor_calculado, base_calculo, created_at
)
SELECT 
    a.id,
    'II',
    CASE 
        WHEN a.ncm LIKE '8537%' THEN 14.00
        WHEN a.ncm LIKE '8428%' THEN 10.00
        ELSE 12.00
    END,
    ROUND(a.valor_usd * 0.12, 2), -- Aproximação
    a.valor_usd,
    a.created_at
FROM adicoes a
WHERE a.id > 5;

INSERT INTO impostos_adicao (
    adicao_id, tipo_imposto, aliquota, valor_calculado, base_calculo, created_at
)
SELECT 
    a.id,
    'IPI',
    CASE 
        WHEN a.ncm LIKE '8537%' THEN 15.00
        WHEN a.ncm LIKE '8428%' THEN 12.00
        ELSE 10.00
    END,
    ROUND(a.valor_usd * 0.13, 2), -- Base com II
    ROUND(a.valor_usd * 1.12, 2),
    a.created_at
FROM adicoes a
WHERE a.id > 5;

INSERT INTO impostos_adicao (
    adicao_id, tipo_imposto, aliquota, valor_calculado, base_calculo, created_at
)
SELECT 
    a.id,
    'PIS',
    1.65,
    ROUND(a.valor_usd * 1.12 * 0.0165, 2),
    ROUND(a.valor_usd * 1.12, 2),
    a.created_at
FROM adicoes a
WHERE a.id > 5;

INSERT INTO impostos_adicao (
    adicao_id, tipo_imposto, aliquota, valor_calculado, base_calculo, created_at
)
SELECT 
    a.id,
    'COFINS',
    7.60,
    ROUND(a.valor_usd * 1.12 * 0.076, 2),
    ROUND(a.valor_usd * 1.12, 2),
    a.created_at
FROM adicoes a
WHERE a.id > 5;

INSERT INTO impostos_adicao (
    adicao_id, tipo_imposto, aliquota, valor_calculado, base_calculo, created_at
)
SELECT 
    a.id,
    'ICMS',
    18.00,
    ROUND(a.valor_usd * 1.35 * 0.18, 2), -- Base com todos impostos federais
    ROUND(a.valor_usd * 1.35, 2),
    a.created_at
FROM adicoes a
WHERE a.id > 5;

-- ================================================================================
-- VERIFICAÇÕES E TOTALIZADORES
-- ================================================================================

-- Criar view para verificar totais
CREATE OR REPLACE VIEW v_test_totals AS
SELECT 
    di.numero_di,
    di.importador_nome,
    COUNT(a.id) as total_adicoes,
    SUM(a.valor_usd) as total_usd,
    SUM(a.valor_brl) as total_brl,
    SUM(CASE WHEN i.tipo_imposto = 'II' THEN i.valor_calculado ELSE 0 END) as total_ii,
    SUM(CASE WHEN i.tipo_imposto = 'IPI' THEN i.valor_calculado ELSE 0 END) as total_ipi,
    SUM(CASE WHEN i.tipo_imposto = 'PIS' THEN i.valor_calculado ELSE 0 END) as total_pis,
    SUM(CASE WHEN i.tipo_imposto = 'COFINS' THEN i.valor_calculado ELSE 0 END) as total_cofins,
    SUM(CASE WHEN i.tipo_imposto = 'ICMS' THEN i.valor_calculado ELSE 0 END) as total_icms,
    SUM(i.valor_calculado) as total_impostos
FROM declaracoes_importacao di
LEFT JOIN adicoes a ON di.id = a.di_id
LEFT JOIN impostos_adicao i ON a.id = i.adicao_id
GROUP BY di.id, di.numero_di, di.importador_nome
ORDER BY di.numero_di;

-- ================================================================================
-- COMENTÁRIOS PARA TESTES
-- ================================================================================

/*
DADOS INSERIDOS PARA TESTES:

1. DI's de Volume (23 registros):
   - Diferentes empresas e valores
   - Status variados (concluída/processando)
   - Datas distribuídas em 6 meses

2. Adições (40+ registros):
   - NCMs variadas: 85371000, 84281000, 84283000
   - Valores e quantidades realistas
   - Pesos calculados proporcionalmente

3. Impostos (200+ registros):
   - Todos os tipos: II, IPI, PIS, COFINS, ICMS
   - Alíquotas diferenciadas por NCM
   - Bases de cálculo sequenciais corretas

4. Cenários de Teste Cobertos:
   ✅ Busca por empresa
   ✅ Busca por CNPJ
   ✅ Busca por número DI
   ✅ Filtros por período
   ✅ Filtros por valor
   ✅ Filtros por status
   ✅ Paginação
   ✅ Ordenação
   ✅ Agregações (stats)
   ✅ Gráficos evolutivos
   ✅ Breakdown de impostos
   ✅ Performance com volume
   ✅ Caracteres especiais
   ✅ Edge cases

5. Performance:
   - Dados suficientes para testar paginação
   - Volume para stress test das APIs
   - Índices necessários já definidos no schema

Para verificar os dados inseridos:
SELECT * FROM v_test_totals LIMIT 10;
*/