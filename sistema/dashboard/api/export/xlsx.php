<?php
/**
 * ================================================================================
 * EXPORTADOR XLSX AVANÇADO ENTERPRISE
 * Sistema ETL DI's - Excel com PhpSpreadsheet
 * Features: Múltiplas abas, gráficos nativos, formatação condicional, fórmulas
 * ================================================================================
 */

require_once '../../../vendor/autoload.php'; // PhpSpreadsheet via Composer

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class XlsxExporter 
{
    private $spreadsheet;
    private $template;
    private $options;
    private $colors;
    private $styles;
    
    // Configurações padrão Expertzy
    private $brandColors = [
        'primary' => 'FF002D',
        'secondary' => '091A30',
        'success' => '28A745',
        'warning' => 'FFC107',
        'danger' => 'DC3545',
        'info' => '17A2B8',
        'light' => 'F8F9FA',
        'dark' => '343A40'
    ];
    
    public function __construct() 
    {
        $this->initializeStyles();
    }
    
    /**
     * Gerar planilha Excel avançada
     */
    public function generate(array $data, array $template, string $filePath, array $options = []): void 
    {
        try {
            $this->template = $template;
            $this->options = $options;
            
            // Criar nova planilha
            $this->spreadsheet = new Spreadsheet();
            $this->setupDocumentProperties();
            
            // Remover worksheet padrão
            $this->spreadsheet->removeSheetByIndex(0);
            
            // Gerar abas
            $this->generateDashboardSheet($data);
            $this->generateDIsSheet($data);
            $this->generateAdicoesSheet($data);
            $this->generateImpostosSheet($data);
            $this->generateDespesasSheet($data);
            $this->generateAnalysisSheet($data);
            $this->generateChartsSheet($data);
            $this->generateSummarySheet($data);
            
            // Definir aba ativa como Dashboard
            $this->spreadsheet->setActiveSheetIndex(0);
            
            // Salvar arquivo
            $writer = new Xlsx($this->spreadsheet);
            $writer->setIncludeCharts(true);
            $writer->save($filePath);
            
            // Log de sucesso
            error_log("XLSX Export gerado com sucesso: {$filePath} (" . $this->formatFileSize($filePath) . ")");
            
        } catch (Exception $e) {
            error_log("Erro na geração XLSX: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Configurar propriedades do documento
     */
    private function setupDocumentProperties(): void 
    {
        $properties = $this->spreadsheet->getProperties();
        $properties->setCreator('Sistema ETL DI\'s - Expertzy')
                  ->setLastModifiedBy($this->options['generated_by'] ?? 'Sistema')
                  ->setTitle($this->options['report_title'] ?? 'Relatório de Importações')
                  ->setSubject('Análise de Declarações de Importação')
                  ->setDescription('Relatório detalhado gerado pelo Sistema ETL DI\'s')
                  ->setKeywords('DI,Importação,Análise,Tributação,Custos')
                  ->setCategory('Relatórios Empresariais')
                  ->setCompany('Expertzy Technology');
    }
    
    /**
     * Gerar aba Dashboard (Resumo com gráficos)
     */
    private function generateDashboardSheet(array $data): void 
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Dashboard');
        
        // Cabeçalho principal
        $this->createHeaderSection($sheet, $data);
        
        // Seção de KPIs
        $this->createKPISection($sheet, $data);
        
        // Área reservada para gráficos
        $this->createChartsPlaceholders($sheet);
        
        // Resumo de tendências
        $this->createTrendsSection($sheet, $data);
        
        // Aplicar proteção seletiva
        $this->protectWorksheet($sheet, ['B2:G2', 'B15:M25']); // Proteger exceto áreas editáveis
    }
    
    /**
     * Criar seção de cabeçalho
     */
    private function createHeaderSection($sheet, array $data): void 
    {
        // Logo area (placeholder)
        $sheet->mergeCells('A1:B3');
        $sheet->setCellValue('A1', 'LOGO');
        $sheet->getStyle('A1')->applyFromArray($this->styles['logo']);
        
        // Título principal
        $sheet->mergeCells('C1:H1');
        $sheet->setCellValue('C1', $this->options['report_title'] ?? 'Dashboard de Importações');
        $sheet->getStyle('C1')->applyFromArray($this->styles['title_main']);
        
        // Subtítulo
        $sheet->mergeCells('C2:H2');
        $sheet->setCellValue('C2', 'Análise Completa de Declarações de Importação');
        $sheet->getStyle('C2')->applyFromArray($this->styles['title_sub']);
        
        // Data de geração
        $sheet->mergeCells('C3:H3');
        $sheet->setCellValue('C3', 'Gerado em: ' . date('d/m/Y H:i:s'));
        $sheet->getStyle('C3')->applyFromArray($this->styles['date']);
        
        // Período de análise
        if (isset($data['records']['summary']['periodo_analise'])) {
            $periodo = $data['records']['summary']['periodo_analise'];
            $sheet->mergeCells('I1:M1');
            $sheet->setCellValue('I1', 'Período de Análise');
            $sheet->getStyle('I1')->applyFromArray($this->styles['period_header']);
            
            $sheet->mergeCells('I2:M2');
            $sheet->setCellValue('I2', 
                date('d/m/Y', strtotime($periodo['data_inicio'])) . ' a ' . 
                date('d/m/Y', strtotime($periodo['data_fim']))
            );
            $sheet->getStyle('I2')->applyFromArray($this->styles['period_value']);
        }
    }
    
    /**
     * Criar seção de KPIs
     */
    private function createKPISection($sheet, array $data): void 
    {
        if (!isset($data['records']['summary'])) return;
        
        $summary = $data['records']['summary'];
        
        // Cabeçalho da seção
        $sheet->mergeCells('A5:M5');
        $sheet->setCellValue('A5', 'INDICADORES PRINCIPAIS');
        $sheet->getStyle('A5')->applyFromArray($this->styles['section_header']);
        
        // KPIs
        $kpis = [
            ['label' => 'DIs Processadas', 'value' => $summary['total_dis'] ?? 0, 'format' => '#,##0'],
            ['label' => 'Valor CIF Total', 'value' => $summary['valor_total_cif_brl'] ?? 0, 'format' => '_-R$ * #,##0.00_-'],
            ['label' => 'Impostos Totais', 'value' => $summary['valor_total_impostos'] ?? 0, 'format' => '_-R$ * #,##0.00_-'],
            ['label' => 'Custo Landed Total', 'value' => $summary['custo_total_landed'] ?? 0, 'format' => '_-R$ * #,##0.00_-'],
            ['label' => 'Ticket Médio CIF', 'value' => $summary['ticket_medio_cif'] ?? 0, 'format' => '_-R$ * #,##0.00_-'],
            ['label' => '% Impostos/CIF', 'value' => $summary['percentual_impostos_sobre_cif'] ?? 0, 'format' => '0.00%']
        ];
        
        $col = 'A';
        $row = 7;
        
        foreach ($kpis as $kpi) {
            // Label
            $sheet->setCellValue($col . $row, $kpi['label']);
            $sheet->getStyle($col . $row)->applyFromArray($this->styles['kpi_label']);
            
            // Valor
            $sheet->setCellValue($col . ($row + 1), $kpi['value'] / 100); // Para percentuais
            $sheet->getStyle($col . ($row + 1))->getNumberFormat()->setFormatCode($kpi['format']);
            $sheet->getStyle($col . ($row + 1))->applyFromArray($this->styles['kpi_value']);
            
            // Aplicar formatação condicional para valores monetários
            if (strpos($kpi['format'], 'R$') !== false) {
                $this->applyConditionalFormatting($sheet, $col . ($row + 1), 'currency');
            }
            
            $col = chr(ord($col) + 2); // Pular uma coluna
        }
    }
    
    /**
     * Gerar aba de DIs
     */
    private function generateDIsSheet(array $data): void 
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('DIs Detalhadas');
        
        if (!isset($data['records']['dis'])) {
            $sheet->setCellValue('A1', 'Nenhuma DI encontrada');
            return;
        }
        
        // Cabeçalhos
        $headers = [
            'Número DI', 'Data Registro', 'Importador', 'CNPJ', 'UF', 'URF Despacho',
            'Valor CIF (R$)', 'Valor CIF (USD)', 'II (R$)', 'IPI (R$)', 'PIS (R$)', 
            'COFINS (R$)', 'ICMS (R$)', 'Total Impostos (R$)', 'Custo Landed (R$)',
            'Taxa Câmbio', '% Impostos', '% Despesas', 'Status'
        ];
        
        $this->createTableHeaders($sheet, $headers, 1);
        
        // Dados
        $row = 2;
        foreach ($data['records']['dis'] as $di) {
            $sheet->setCellValue('A' . $row, $di['identificacao']['numero_di']);
            $sheet->setCellValue('B' . $row, date('d/m/Y', strtotime($di['identificacao']['data_registro'])));
            $sheet->setCellValue('C' . $row, $di['importador']['nome']);
            $sheet->setCellValue('D' . $row, $di['importador']['cnpj']);
            $sheet->setCellValue('E' . $row, $di['importador']['endereco']['uf'] ?? '');
            $sheet->setCellValue('F' . $row, $di['identificacao']['urf_despacho']['nome'] ?? '');
            
            // Valores financeiros
            $sheet->setCellValue('G' . $row, $di['valores_principais']['cif']['valor_brl']);
            $sheet->setCellValue('H' . $row, $di['valores_principais']['cif']['valor_original']);
            $sheet->setCellValue('I' . $row, $di['valores_principais']['impostos']['ii']);
            $sheet->setCellValue('J' . $row, $di['valores_principais']['impostos']['ipi']);
            $sheet->setCellValue('K' . $row, $di['valores_principais']['impostos']['pis']);
            $sheet->setCellValue('L' . $row, $di['valores_principais']['impostos']['cofins']);
            $sheet->setCellValue('M' . $row, $di['valores_principais']['impostos']['icms']);
            $sheet->setCellValue('N' . $row, $di['valores_principais']['impostos']['total']);
            $sheet->setCellValue('O' . $row, $di['valores_principais']['custo_total']['landed_cost']);
            
            // Outros dados
            $sheet->setCellValue('P' . $row, $di['valores_principais']['cif']['taxa_cambio']);
            $sheet->setCellValue('Q' . $row, $di['valores_principais']['custo_total']['percentual_impostos'] / 100);
            $sheet->setCellValue('R' . $row, $di['valores_principais']['custo_total']['percentual_despesas'] / 100);
            $sheet->setCellValue('S' . $row, $di['identificacao']['status_processamento'] ?? 'Processado');
            
            $row++;
        }
        
        // Aplicar formatação
        $this->formatDataTable($sheet, 1, $row - 1, count($headers));
        
        // Auto-ajustar colunas
        $this->autoSizeColumns($sheet, $headers);
        
        // Aplicar filtros
        $sheet->setAutoFilter('A1:S' . ($row - 1));
        
        // Formatação condicional para valores
        $this->applyConditionalFormatting($sheet, 'G2:O' . ($row - 1), 'currency');
        $this->applyConditionalFormatting($sheet, 'Q2:R' . ($row - 1), 'percentage');
    }
    
    /**
     * Gerar aba de Adições
     */
    private function generateAdicoesSheet(array $data): void 
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Adições');
        
        // Cabeçalhos
        $headers = [
            'Número DI', 'Adição', 'NCM', 'Descrição NCM', 'Quantidade', 'Unidade',
            'Peso Líquido', 'Peso Bruto', 'Valor CIF (R$)', 'Moeda Original', 
            'País Origem', 'País Procedência', 'VMLE (R$)', 'Base ICMS (R$)',
            'Acordos Aplicados'
        ];
        
        $this->createTableHeaders($sheet, $headers, 1);
        
        // Dados
        $row = 2;
        if (isset($data['records']['dis'])) {
            foreach ($data['records']['dis'] as $di) {
                if (isset($di['adicoes'])) {
                    foreach ($di['adicoes'] as $adicao) {
                        $sheet->setCellValue('A' . $row, $di['identificacao']['numero_di']);
                        $sheet->setCellValue('B' . $row, $adicao['numero_adicao']);
                        $sheet->setCellValue('C' . $row, $adicao['produto']['ncm']);
                        $sheet->setCellValue('D' . $row, $adicao['produto']['descricao'] ?? '');
                        $sheet->setCellValue('E' . $row, $adicao['produto']['quantidade']);
                        $sheet->setCellValue('F' . $row, $adicao['produto']['unidade_medida'] ?? '');
                        $sheet->setCellValue('G' . $row, $adicao['produto']['peso_liquido']);
                        $sheet->setCellValue('H' . $row, $adicao['produto']['peso_bruto']);
                        $sheet->setCellValue('I' . $row, $adicao['valores']['cif']['valor_brl']);
                        $sheet->setCellValue('J' . $row, $adicao['valores']['cif']['moeda']);
                        $sheet->setCellValue('K' . $row, $adicao['origem']['pais_origem'] ?? '');
                        $sheet->setCellValue('L' . $row, $adicao['origem']['pais_procedencia'] ?? '');
                        $sheet->setCellValue('M' . $row, $adicao['valores']['vmle']);
                        $sheet->setCellValue('N' . $row, $adicao['valores']['base_icms']);
                        
                        // Acordos (concatenar)
                        $acordos = [];
                        if (isset($adicao['acordos_tarifarios'])) {
                            foreach ($adicao['acordos_tarifarios'] as $acordo) {
                                $acordos[] = $acordo['codigo'];
                            }
                        }
                        $sheet->setCellValue('O' . $row, implode(', ', $acordos));
                        
                        $row++;
                    }
                }
            }
        }
        
        // Aplicar formatação
        $this->formatDataTable($sheet, 1, $row - 1, count($headers));
        $this->autoSizeColumns($sheet, $headers);
        $sheet->setAutoFilter('A1:O' . ($row - 1));
        
        // Formatação condicional
        $this->applyConditionalFormatting($sheet, 'E2:H' . ($row - 1), 'numbers');
        $this->applyConditionalFormatting($sheet, 'I2:N' . ($row - 1), 'currency');
    }
    
    /**
     * Gerar aba de Impostos
     */
    private function generateImpostosSheet(array $data): void 
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Impostos');
        
        // Cabeçalhos
        $headers = [
            'Número DI', 'Adição', 'NCM', 'Tipo Imposto', 'Alíquota Ad Valorem (%)',
            'Alíquota Específica', 'Base Cálculo (R$)', 'Valor Devido (R$)', 
            'Redução/Benefício (%)', 'Valor Reduzido (R$)', 'Acordo Aplicado',
            'Regime Especial', 'Fundamentação Legal'
        ];
        
        $this->createTableHeaders($sheet, $headers, 1);
        
        // Dados
        $row = 2;
        if (isset($data['records']['dis'])) {
            foreach ($data['records']['dis'] as $di) {
                if (isset($di['adicoes'])) {
                    foreach ($di['adicoes'] as $adicao) {
                        if (isset($adicao['impostos'])) {
                            foreach ($adicao['impostos'] as $imposto) {
                                $sheet->setCellValue('A' . $row, $di['identificacao']['numero_di']);
                                $sheet->setCellValue('B' . $row, $adicao['numero_adicao']);
                                $sheet->setCellValue('C' . $row, $adicao['produto']['ncm']);
                                $sheet->setCellValue('D' . $row, $imposto['tipo']);
                                $sheet->setCellValue('E' . $row, $imposto['aliquotas']['ad_valorem']);
                                $sheet->setCellValue('F' . $row, $imposto['aliquotas']['especifica']);
                                $sheet->setCellValue('G' . $row, $imposto['calculo']['base_calculo']);
                                $sheet->setCellValue('H' . $row, $imposto['calculo']['valor_devido']);
                                $sheet->setCellValue('I' . $row, $imposto['aliquotas']['reducao_percentual']);
                                $sheet->setCellValue('J' . $row, $imposto['calculo']['valor_reduzido']);
                                $sheet->setCellValue('K' . $row, $imposto['beneficios']['acordo_aplicado'] ?? '');
                                $sheet->setCellValue('L' . $row, $imposto['beneficios']['regime_especial'] ?? '');
                                $sheet->setCellValue('M' . $row, $imposto['beneficios']['fundamentacao_legal'] ?? '');
                                
                                $row++;
                            }
                        }
                    }
                }
            }
        }
        
        // Aplicar formatação
        $this->formatDataTable($sheet, 1, $row - 1, count($headers));
        $this->autoSizeColumns($sheet, $headers);
        $sheet->setAutoFilter('A1:M' . ($row - 1));
        
        // Formatação condicional
        $this->applyConditionalFormatting($sheet, 'E2:F' . ($row - 1), 'percentage');
        $this->applyConditionalFormatting($sheet, 'G2:H' . ($row - 1), 'currency');
        $this->applyConditionalFormatting($sheet, 'J2:J' . ($row - 1), 'currency');
        
        // Formatação especial para tipos de imposto
        $this->applyTaxTypeFormatting($sheet, 'D2:D' . ($row - 1));
    }
    
    /**
     * Gerar aba de Despesas
     */
    private function generateDespesasSheet(array $data): void 
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Despesas');
        
        // Cabeçalhos
        $headers = [
            'Número DI', 'Data Registro', 'Importador', 'Categoria', 'Grupo',
            'Valor (R$)', 'Origem', 'Compõe Base ICMS', 'Fornecedor',
            'Documento', 'Validado', 'Observações'
        ];
        
        $this->createTableHeaders($sheet, $headers, 1);
        
        // Dados
        $row = 2;
        if (isset($data['records']['dis'])) {
            foreach ($data['records']['dis'] as $di) {
                if (isset($di['despesas'])) {
                    foreach (['portuarias', 'logisticas', 'administrativas', 'extras'] as $categoria) {
                        if (isset($di['despesas'][$categoria])) {
                            foreach ($di['despesas'][$categoria] as $despesa) {
                                $sheet->setCellValue('A' . $row, $di['identificacao']['numero_di']);
                                $sheet->setCellValue('B' . $row, date('d/m/Y', strtotime($di['identificacao']['data_registro'])));
                                $sheet->setCellValue('C' . $row, $di['importador']['nome']);
                                $sheet->setCellValue('D' . $row, $despesa['item']);
                                $sheet->setCellValue('E' . $row, $despesa['grupo'] ?? $categoria);
                                $sheet->setCellValue('F' . $row, $despesa['valor']);
                                $sheet->setCellValue('G' . $row, $despesa['origem']);
                                $sheet->setCellValue('H' . $row, $despesa['compoe_base_icms'] ? 'Sim' : 'Não');
                                $sheet->setCellValue('I' . $row, $despesa['fornecedor'] ?? '');
                                $sheet->setCellValue('J' . $row, $despesa['documento'] ?? '');
                                $sheet->setCellValue('K' . $row, $despesa['validado'] ? 'Sim' : 'Não');
                                $sheet->setCellValue('L' . $row, $despesa['observacoes'] ?? '');
                                
                                $row++;
                            }
                        }
                    }
                }
            }
        }
        
        // Aplicar formatação
        $this->formatDataTable($sheet, 1, $row - 1, count($headers));
        $this->autoSizeColumns($sheet, $headers);
        $sheet->setAutoFilter('A1:L' . ($row - 1));
        
        // Formatação condicional
        $this->applyConditionalFormatting($sheet, 'F2:F' . ($row - 1), 'currency');
        $this->applyExpenseCategoryFormatting($sheet, 'E2:E' . ($row - 1));
    }
    
    /**
     * Gerar aba de Análises (com fórmulas)
     */
    private function generateAnalysisSheet(array $data): void 
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Análises');
        
        // Seção 1: Análise por UF
        $this->createUFAnalysis($sheet, $data);
        
        // Seção 2: Análise por NCM
        $this->createNCMAnalysis($sheet, $data);
        
        // Seção 3: Análise Temporal
        $this->createTemporalAnalysis($sheet, $data);
        
        // Seção 4: Benchmarks
        $this->createBenchmarks($sheet, $data);
    }
    
    /**
     * Gerar aba de Gráficos
     */
    private function generateChartsSheet(array $data): void 
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Gráficos');
        
        // Dados para gráficos
        $this->createChartsData($sheet, $data);
        
        // Gráfico 1: Evolução CIF por mês
        $this->createLineChart($sheet, 'Evolução Valor CIF', 'A20:M30');
        
        // Gráfico 2: Distribuição de Impostos
        $this->createPieChart($sheet, 'Distribuição de Impostos', 'O20:T25');
        
        // Gráfico 3: Top NCMs
        $this->createColumnChart($sheet, 'Top 10 NCMs por Valor', 'A35:B45');
    }
    
    /**
     * Gerar aba de Resumo (com totalizações)
     */
    private function generateSummarySheet(array $data): void 
    {
        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle('Resumo');
        
        // Reposicionar como primeira aba
        $this->spreadsheet->setActiveSheetIndex(0);
        
        // Título
        $sheet->mergeCells('A1:F1');
        $sheet->setCellValue('A1', 'RESUMO EXECUTIVO - IMPORTAÇÕES');
        $sheet->getStyle('A1')->applyFromArray($this->styles['title_main']);
        
        // Resumo consolidado
        $this->createConsolidatedSummary($sheet, $data);
        
        // Links para outras abas
        $this->createSheetLinks($sheet);
    }
    
    /**
     * Métodos auxiliares para formatação
     */
    private function initializeStyles(): void 
    {
        $this->styles = [
            'title_main' => [
                'font' => [
                    'bold' => true,
                    'size' => 16,
                    'color' => ['rgb' => $this->brandColors['primary']]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->brandColors['light']]
                ]
            ],
            'title_sub' => [
                'font' => [
                    'bold' => false,
                    'size' => 12,
                    'color' => ['rgb' => $this->brandColors['secondary']]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ],
            'section_header' => [
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->brandColors['primary']]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ]
            ],
            'table_header' => [
                'font' => [
                    'bold' => true,
                    'size' => 10,
                    'color' => ['rgb' => 'FFFFFF']
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $this->brandColors['secondary']]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'FFFFFF']
                    ]
                ]
            ],
            'kpi_label' => [
                'font' => [
                    'bold' => true,
                    'size' => 9,
                    'color' => ['rgb' => $this->brandColors['secondary']]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ],
            'kpi_value' => [
                'font' => [
                    'bold' => true,
                    'size' => 14,
                    'color' => ['rgb' => $this->brandColors['primary']]
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER
                ]
            ]
        ];
    }
    
    private function createTableHeaders($sheet, array $headers, int $row): void 
    {
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . $row, $header);
            $sheet->getStyle($col . $row)->applyFromArray($this->styles['table_header']);
            $col++;
        }
    }
    
    private function formatDataTable($sheet, int $startRow, int $endRow, int $numCols): void 
    {
        $range = 'A' . $startRow . ':' . chr(64 + $numCols) . $endRow;
        
        // Bordas
        $sheet->getStyle($range)->getBorders()->getAllBorders()
              ->setBorderStyle(Border::BORDER_THIN)
              ->setColor(new Color('CCCCCC'));
        
        // Zebra striping
        for ($row = $startRow + 1; $row <= $endRow; $row += 2) {
            $rowRange = 'A' . $row . ':' . chr(64 + $numCols) . $row;
            $sheet->getStyle($rowRange)->getFill()
                  ->setFillType(Fill::FILL_SOLID)
                  ->setStartColor(new Color($this->brandColors['light']));
        }
    }
    
    private function autoSizeColumns($sheet, array $headers): void 
    {
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }
    }
    
    private function applyConditionalFormatting($sheet, string $range, string $type): void 
    {
        $conditional = new Conditional();
        $conditional->setConditionType(Conditional::CONDITION_CELLIS);
        
        switch ($type) {
            case 'currency':
                $conditional->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
                $conditional->addCondition('1000000'); // > 1M
                $conditional->getStyle()->getFont()->getColor()->setRGB($this->brandColors['success']);
                break;
                
            case 'percentage':
                $conditional->setOperatorType(Conditional::OPERATOR_GREATERTHAN);
                $conditional->addCondition('0.3'); // > 30%
                $conditional->getStyle()->getFont()->getColor()->setRGB($this->brandColors['warning']);
                break;
        }
        
        $sheet->getStyle($range)->setConditionalStyles([$conditional]);
    }
    
    private function applyTaxTypeFormatting($sheet, string $range): void 
    {
        // Cores diferentes para cada tipo de imposto
        $taxColors = [
            'II' => $this->brandColors['primary'],
            'IPI' => $this->brandColors['secondary'],
            'PIS' => $this->brandColors['info'],
            'COFINS' => $this->brandColors['warning'],
            'ICMS' => $this->brandColors['success']
        ];
        
        foreach ($taxColors as $tax => $color) {
            $conditional = new Conditional();
            $conditional->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
            $conditional->setText($tax);
            $conditional->getStyle()->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->setStartColor(new Color($color . '20')); // 20% opacity
            
            $sheet->getStyle($range)->setConditionalStyles([$conditional]);
        }
    }
    
    private function applyExpenseCategoryFormatting($sheet, string $range): void 
    {
        $categoryColors = [
            'portuarias' => $this->brandColors['primary'],
            'logisticas' => $this->brandColors['info'],
            'administrativas' => $this->brandColors['warning'],
            'extras' => $this->brandColors['secondary']
        ];
        
        foreach ($categoryColors as $category => $color) {
            $conditional = new Conditional();
            $conditional->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
            $conditional->setText($category);
            $conditional->getStyle()->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->setStartColor(new Color($color . '30'));
            
            $sheet->getStyle($range)->setConditionalStyles([$conditional]);
        }
    }
    
    private function protectWorksheet($sheet, array $unlockedRanges = []): void 
    {
        $sheet->getProtection()->setSheet(true);
        
        foreach ($unlockedRanges as $range) {
            $sheet->getStyle($range)->getProtection()->setLocked(false);
        }
    }
    
    // Métodos placeholder para implementações específicas
    private function createChartsPlaceholders($sheet): void 
    {
        $sheet->mergeCells('A12:F18');
        $sheet->setCellValue('A12', '[Área reservada para gráficos]');
        $sheet->getStyle('A12')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    }
    
    private function createTrendsSection($sheet, array $data): void 
    {
        // Implementar seção de tendências
    }
    
    private function createUFAnalysis($sheet, array $data): void 
    {
        // Implementar análise por UF
    }
    
    private function createNCMAnalysis($sheet, array $data): void 
    {
        // Implementar análise por NCM
    }
    
    private function createTemporalAnalysis($sheet, array $data): void 
    {
        // Implementar análise temporal
    }
    
    private function createBenchmarks($sheet, array $data): void 
    {
        // Implementar benchmarks
    }
    
    private function createChartsData($sheet, array $data): void 
    {
        // Implementar dados para gráficos
    }
    
    private function createLineChart($sheet, string $title, string $range): void 
    {
        // Implementar gráfico de linha
    }
    
    private function createPieChart($sheet, string $title, string $range): void 
    {
        // Implementar gráfico de pizza
    }
    
    private function createColumnChart($sheet, string $title, string $range): void 
    {
        // Implementar gráfico de colunas
    }
    
    private function createConsolidatedSummary($sheet, array $data): void 
    {
        // Implementar resumo consolidado
    }
    
    private function createSheetLinks($sheet): void 
    {
        // Implementar links entre abas
        $links = [
            'DIs Detalhadas' => 'A10',
            'Adições' => 'B10',
            'Impostos' => 'C10',
            'Despesas' => 'D10',
            'Análises' => 'E10',
            'Gráficos' => 'F10'
        ];
        
        foreach ($links as $sheetName => $cell) {
            $sheet->setCellValue($cell, $sheetName);
            $sheet->getCell($cell)->getHyperlink()->setUrl("sheet://'$sheetName'!A1");
            $sheet->getStyle($cell)->getFont()->setUnderline(true)->getColor()->setRGB('0000FF');
        }
    }
    
    private function formatFileSize(string $filePath): string 
    {
        $bytes = filesize($filePath);
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}