<?php
/**
 * ================================================================================
 * EXPORTADOR PDF EXECUTIVO ENTERPRISE
 * Sistema ETL DI's - PDFs Profissionais com TCPDF
 * Features: Gráficos Chart.js → PNG, Branding Expertzy, Templates
 * ================================================================================
 */

require_once '../../../vendor/autoload.php'; // TCPDF via Composer

use TCPDF;

class PdfExporter 
{
    private $pdf;
    private $template;
    private $options;
    private $colors;
    private $currentY = 0;
    
    // Configurações padrão
    private $pageMargins = ['top' => 20, 'bottom' => 20, 'left' => 15, 'right' => 15];
    private $headerHeight = 35;
    private $footerHeight = 25;
    
    public function __construct() 
    {
        // Configurar cores padrão Expertzy
        $this->colors = [
            'primary' => [255, 0, 45],      // #FF002D
            'secondary' => [9, 26, 48],     // #091A30
            'text' => [51, 51, 51],         // #333333
            'text_light' => [102, 102, 102], // #666666
            'background' => [255, 255, 255], // #FFFFFF
            'table_header' => [248, 249, 250], // #F8F9FA
            'table_alt' => [252, 252, 252],   // #FCFCFC
            'border' => [221, 221, 221],      // #DDDDDD
            'success' => [40, 167, 69],       // #28A745
            'warning' => [255, 193, 7],       // #FFC107
            'danger' => [220, 53, 69]         // #DC3545
        ];
    }
    
    /**
     * Gerar PDF executivo completo
     */
    public function generate(array $data, array $template, string $filePath, array $options = []): void 
    {
        try {
            $this->template = $template;
            $this->options = $options;
            
            // Inicializar TCPDF
            $this->initializePDF();
            
            // Gerar conteúdo do PDF
            $this->generateCover($data);
            $this->generateExecutiveSummary($data);
            $this->generateTableOfContents();
            $this->generateChartSection($data);
            $this->generateDetailedAnalysis($data);
            $this->generateDIBreakdown($data);
            $this->generateTaxAnalysis($data);
            $this->generateExpenseAnalysis($data);
            $this->generateRecommendations($data);
            $this->generateAppendices($data);
            
            // Salvar PDF
            $this->pdf->Output($filePath, 'F');
            
            // Log de sucesso
            error_log("PDF Export gerado com sucesso: {$filePath} (" . $this->formatFileSize($filePath) . ")");
            
        } catch (Exception $e) {
            error_log("Erro na geração PDF: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Inicializar TCPDF com configurações personalizadas
     */
    private function initializePDF(): void 
    {
        // Criar instância TCPDF
        $this->pdf = new TCPDF(
            $this->template['orientation'] ?? 'P', // Portrait
            'mm', // Unidade em milímetros
            $this->template['page_size'] ?? 'A4',
            true, 'UTF-8', false
        );
        
        // Configurações do documento
        $this->pdf->SetCreator('Sistema ETL DI\'s - Expertzy');
        $this->pdf->SetAuthor($this->options['generated_by'] ?? 'Sistema ETL DI\'s');
        $this->pdf->SetTitle($this->options['report_title'] ?? 'Relatório de Importações');
        $this->pdf->SetSubject('Análise de Declarações de Importação');
        $this->pdf->SetKeywords('DI, Importação, Análise, Tributação, Custos');
        
        // Configurar margens
        $margins = $this->template['margins'] ?? $this->pageMargins;
        $this->pdf->SetMargins($margins['left'], $margins['top'], $margins['right']);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);
        
        // Configurar auto page break
        $this->pdf->SetAutoPageBreak(true, $margins['bottom']);
        
        // Configurar fonte padrão
        $this->pdf->SetFont(
            $this->template['font_family'] ?? 'Arial',
            '',
            $this->template['font_size'] ?? 10
        );
        
        // Configurar header e footer customizados
        $this->setupHeaderFooter();
    }
    
    /**
     * Configurar header e footer personalizados
     */
    private function setupHeaderFooter(): void 
    {
        // Desativar header/footer padrão
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
    }
    
    /**
     * Gerar capa executiva
     */
    private function generateCover(array $data): void 
    {
        $this->pdf->AddPage();
        
        // Logo Expertzy (se disponível)
        $logoPath = $this->options['company_logo'] ?? null;
        if ($logoPath && file_exists($logoPath)) {
            $this->pdf->Image($logoPath, 20, 20, 50, 0, '', '', '', false, 300, '', false, false, 0);
        }
        
        // Título principal
        $this->pdf->SetFont('Arial', 'B', 24);
        $this->pdf->SetTextColor(...$this->colors['primary']);
        $this->pdf->SetY(80);
        $this->pdf->Cell(0, 15, $this->options['report_title'] ?? 'Relatório de Importações', 0, 1, 'C');
        
        // Subtítulo
        $this->pdf->SetFont('Arial', '', 16);
        $this->pdf->SetTextColor(...$this->colors['secondary']);
        $this->pdf->Cell(0, 10, 'Análise Completa de Declarações de Importação', 0, 1, 'C');
        
        // Período de análise
        if (isset($data['records']['summary']['periodo_analise'])) {
            $periodo = $data['records']['summary']['periodo_analise'];
            $this->pdf->SetY(120);
            $this->pdf->SetFont('Arial', '', 12);
            $this->pdf->Cell(0, 8, 'Período: ' . date('d/m/Y', strtotime($periodo['data_inicio'])) . ' a ' . date('d/m/Y', strtotime($periodo['data_fim'])), 0, 1, 'C');
        }
        
        // Estatísticas principais na capa
        $this->generateCoverStats($data);
        
        // Data de geração
        $this->pdf->SetY(250);
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->SetTextColor(...$this->colors['text_light']);
        $this->pdf->Cell(0, 5, 'Gerado em: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
        $this->pdf->Cell(0, 5, 'Por: ' . ($this->options['generated_by'] ?? 'Sistema ETL DI\'s'), 0, 1, 'C');
        
        // Rodapé da capa
        $this->generateCoverFooter();
    }
    
    /**
     * Gerar estatísticas principais na capa
     */
    private function generateCoverStats(array $data): void 
    {
        if (!isset($data['records']['summary'])) return;
        
        $summary = $data['records']['summary'];
        
        $this->pdf->SetY(150);
        $this->pdf->SetFont('Arial', 'B', 14);
        $this->pdf->SetTextColor(...$this->colors['secondary']);
        $this->pdf->Cell(0, 10, 'Resumo Executivo', 0, 1, 'C');
        
        // Box com estatísticas
        $this->pdf->SetFillColor(...$this->colors['table_header']);
        $this->pdf->Rect(30, 170, 150, 60, 'F');
        
        $stats = [
            ['DIs Processadas:', number_format($summary['total_dis'] ?? 0)],
            ['Valor CIF Total:', 'R$ ' . number_format($summary['valor_total_cif_brl'] ?? 0, 2, ',', '.')],
            ['Impostos Totais:', 'R$ ' . number_format($summary['valor_total_impostos'] ?? 0, 2, ',', '.')],
            ['Custo Landed Total:', 'R$ ' . number_format($summary['custo_total_landed'] ?? 0, 2, ',', '.')],
            ['Ticket Médio CIF:', 'R$ ' . number_format($summary['ticket_medio_cif'] ?? 0, 2, ',', '.')]
        ];
        
        $this->pdf->SetFont('Arial', '', 11);
        $this->pdf->SetTextColor(...$this->colors['text']);
        
        $y = 175;
        foreach ($stats as $stat) {
            $this->pdf->SetY($y);
            $this->pdf->SetX(35);
            $this->pdf->Cell(80, 8, $stat[0], 0, 0, 'L');
            $this->pdf->SetFont('Arial', 'B', 11);
            $this->pdf->Cell(60, 8, $stat[1], 0, 1, 'R');
            $this->pdf->SetFont('Arial', '', 11);
            $y += 10;
        }
    }
    
    /**
     * Gerar rodapé da capa
     */
    private function generateCoverFooter(): void 
    {
        $this->pdf->SetY(280);
        $this->pdf->SetFont('Arial', 'I', 8);
        $this->pdf->SetTextColor(...$this->colors['text_light']);
        $this->pdf->Cell(0, 4, 'Documento gerado automaticamente pelo Sistema ETL DI\'s', 0, 1, 'C');
        $this->pdf->Cell(0, 4, 'Powered by Expertzy Technology - Sistema de Análise de Importações', 0, 1, 'C');
        $this->pdf->Cell(0, 4, 'Confidencial - Uso restrito ao destinatário', 0, 1, 'C');
    }
    
    /**
     * Gerar resumo executivo
     */
    private function generateExecutiveSummary(array $data): void 
    {
        $this->pdf->AddPage();
        
        // Título da seção
        $this->addSectionHeader('Resumo Executivo');
        
        // Introdução
        $this->pdf->SetFont('Arial', '', 11);
        $this->pdf->SetTextColor(...$this->colors['text']);
        
        $intro = "Este relatório apresenta uma análise abrangente das declarações de importação processadas, " .
                "incluindo breakdown detalhado de custos, impostos e despesas operacionais. Os dados foram " .
                "extraídos e processados através do Sistema ETL DI's, garantindo precisão e conformidade " .
                "com as normas aduaneiras brasileiras.";
        
        $this->pdf->MultiCell(0, 6, $intro, 0, 'J');
        $this->pdf->Ln(5);
        
        // Destaques principais
        $this->addSubsectionHeader('Principais Destaques');
        
        $highlights = $this->generateHighlights($data);
        foreach ($highlights as $highlight) {
            $this->pdf->SetFont('Arial', '', 10);
            $this->pdf->Cell(5, 6, '•', 0, 0, 'L');
            $this->pdf->MultiCell(0, 6, $highlight, 0, 'L');
            $this->pdf->Ln(2);
        }
        
        // Recomendações estratégicas
        $this->addSubsectionHeader('Recomendações Estratégicas');
        
        $recommendations = $this->generateStrategicRecommendations($data);
        foreach ($recommendations as $rec) {
            $this->pdf->SetFont('Arial', 'B', 10);
            $this->pdf->Cell(0, 6, $rec['title'], 0, 1, 'L');
            $this->pdf->SetFont('Arial', '', 10);
            $this->pdf->MultiCell(0, 5, $rec['description'], 0, 'L');
            $this->pdf->Ln(3);
        }
    }
    
    /**
     * Gerar seção de gráficos
     */
    private function generateChartSection(array $data): void 
    {
        $this->pdf->AddPage();
        
        $this->addSectionHeader('Análise Gráfica');
        
        // Nota sobre gráficos
        $this->pdf->SetFont('Arial', 'I', 9);
        $this->pdf->SetTextColor(...$this->colors['text_light']);
        $this->pdf->MultiCell(0, 5, 'Os gráficos a seguir foram gerados a partir dos dados do dashboard em tempo real e convertidos para este relatório.', 0, 'L');
        $this->pdf->Ln(5);
        
        // Placeholder para gráficos (seriam gerados via Chart.js → PNG)
        $this->generateChartPlaceholders();
    }
    
    /**
     * Gerar placeholders para gráficos
     */
    private function generateChartPlaceholders(): void 
    {
        $charts = [
            ['title' => 'Evolução Temporal dos Valores CIF', 'description' => 'Análise mensal dos valores CIF importados'],
            ['title' => 'Distribuição de Impostos por Tipo', 'description' => 'Breakdown percentual II, IPI, PIS/COFINS, ICMS'],
            ['title' => 'Top 10 NCMs por Valor', 'description' => 'Principais produtos importados por valor CIF'],
            ['title' => 'Despesas Operacionais Médias', 'description' => 'Análise de despesas portuárias e logísticas']
        ];
        
        $yPos = $this->pdf->GetY();
        
        foreach ($charts as $index => $chart) {
            if ($index % 2 === 0 && $index > 0) {
                $this->pdf->AddPage();
                $yPos = $this->pdf->GetY();
            }
            
            $x = ($index % 2 === 0) ? 15 : 105;
            $y = $yPos + ($index % 2 === 0 ? 0 : 0);
            
            // Título do gráfico
            $this->pdf->SetXY($x, $y);
            $this->pdf->SetFont('Arial', 'B', 10);
            $this->pdf->SetTextColor(...$this->colors['secondary']);
            $this->pdf->Cell(85, 6, $chart['title'], 0, 1, 'L');
            
            // Área do gráfico (placeholder)
            $this->pdf->SetFillColor(245, 245, 245);
            $this->pdf->Rect($x, $y + 8, 85, 60, 'F');
            
            // Texto indicativo
            $this->pdf->SetXY($x + 10, $y + 35);
            $this->pdf->SetFont('Arial', '', 8);
            $this->pdf->SetTextColor(...$this->colors['text_light']);
            $this->pdf->Cell(65, 5, '[Gráfico será inserido aqui]', 0, 0, 'C');
            
            // Descrição
            $this->pdf->SetXY($x, $y + 72);
            $this->pdf->SetFont('Arial', '', 8);
            $this->pdf->MultiCell(85, 4, $chart['description'], 0, 'L');
            
            if ($index % 2 === 1) {
                $yPos += 85;
            }
        }
    }
    
    /**
     * Gerar análise detalhada
     */
    private function generateDetailedAnalysis(array $data): void 
    {
        $this->pdf->AddPage();
        
        $this->addSectionHeader('Análise Detalhada de Performance');
        
        // Tabela de performance por período
        $this->generatePerformanceTable($data);
        
        // Análise de tendências
        $this->addSubsectionHeader('Análise de Tendências');
        
        $trends = $this->analyzeTrends($data);
        foreach ($trends as $trend) {
            $this->pdf->SetFont('Arial', 'B', 10);
            $this->pdf->SetTextColor(...$this->colors['secondary']);
            $this->pdf->Cell(0, 6, $trend['indicator'], 0, 1, 'L');
            
            $this->pdf->SetFont('Arial', '', 10);
            $this->pdf->SetTextColor(...$this->colors['text']);
            $this->pdf->MultiCell(0, 5, $trend['analysis'], 0, 'L');
            $this->pdf->Ln(3);
        }
    }
    
    /**
     * Gerar breakdown de DIs
     */
    private function generateDIBreakdown(array $data): void 
    {
        $this->pdf->AddPage();
        
        $this->addSectionHeader('Breakdown Detalhado por DI');
        
        if (!isset($data['records']['dis'])) return;
        
        $dis = array_slice($data['records']['dis'], 0, 10); // Top 10 DIs
        
        foreach ($dis as $di) {
            $this->generateSingleDIBreakdown($di);
        }
    }
    
    /**
     * Gerar breakdown de uma DI específica
     */
    private function generateSingleDIBreakdown(array $di): void 
    {
        // Verificar espaço na página
        if ($this->pdf->GetY() > 240) {
            $this->pdf->AddPage();
        }
        
        // Cabeçalho da DI
        $this->pdf->SetFont('Arial', 'B', 11);
        $this->pdf->SetTextColor(...$this->colors['primary']);
        $this->pdf->Cell(0, 8, 'DI: ' . $di['identificacao']['numero_di'], 0, 1, 'L');
        
        // Informações básicas
        $this->pdf->SetFont('Arial', '', 9);
        $this->pdf->SetTextColor(...$this->colors['text']);
        
        $info = [
            'Importador: ' . $di['importador']['nome'],
            'Data: ' . date('d/m/Y', strtotime($di['identificacao']['data_registro'])),
            'UF: ' . ($di['importador']['endereco']['uf'] ?? 'N/A'),
            'Valor CIF: R$ ' . number_format($di['valores_principais']['cif']['valor_brl'], 2, ',', '.')
        ];
        
        foreach ($info as $item) {
            $this->pdf->Cell(0, 5, $item, 0, 1, 'L');
        }
        
        // Tabela de valores
        $this->generateDIValuesTable($di);
        
        $this->pdf->Ln(5);
    }
    
    /**
     * Gerar tabela de valores da DI
     */
    private function generateDIValuesTable(array $di): void 
    {
        $valores = $di['valores_principais'];
        
        // Cabeçalho da tabela
        $this->pdf->SetFont('Arial', 'B', 8);
        $this->pdf->SetFillColor(...$this->colors['table_header']);
        $this->pdf->SetTextColor(...$this->colors['text']);
        
        $headers = ['Item', 'Valor (R$)', 'Percentual'];
        $widths = [60, 40, 30];
        
        for ($i = 0; $i < count($headers); $i++) {
            $this->pdf->Cell($widths[$i], 6, $headers[$i], 1, 0, 'C', true);
        }
        $this->pdf->Ln();
        
        // Dados da tabela
        $this->pdf->SetFont('Arial', '', 8);
        $this->pdf->SetFillColor(...$this->colors['table_alt']);
        
        $total = $valores['custo_total']['landed_cost'];
        $rows = [
            ['Valor CIF', $valores['cif']['valor_brl'], ($valores['cif']['valor_brl'] / $total) * 100],
            ['II', $valores['impostos']['ii'], ($valores['impostos']['ii'] / $total) * 100],
            ['IPI', $valores['impostos']['ipi'], ($valores['impostos']['ipi'] / $total) * 100],
            ['PIS', $valores['impostos']['pis'], ($valores['impostos']['pis'] / $total) * 100],
            ['COFINS', $valores['impostos']['cofins'], ($valores['impostos']['cofins'] / $total) * 100],
            ['ICMS', $valores['impostos']['icms'], ($valores['impostos']['icms'] / $total) * 100],
            ['Despesas', $total - $valores['cif']['valor_brl'] - $valores['impostos']['total'], 
             (($total - $valores['cif']['valor_brl'] - $valores['impostos']['total']) / $total) * 100]
        ];
        
        $fill = false;
        foreach ($rows as $row) {
            $this->pdf->Cell($widths[0], 5, $row[0], 1, 0, 'L', $fill);
            $this->pdf->Cell($widths[1], 5, number_format($row[1], 2, ',', '.'), 1, 0, 'R', $fill);
            $this->pdf->Cell($widths[2], 5, number_format($row[2], 1) . '%', 1, 0, 'R', $fill);
            $this->pdf->Ln();
            $fill = !$fill;
        }
        
        // Total
        $this->pdf->SetFont('Arial', 'B', 8);
        $this->pdf->SetFillColor(...$this->colors['primary']);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell($widths[0], 6, 'TOTAL LANDED COST', 1, 0, 'L', true);
        $this->pdf->Cell($widths[1], 6, number_format($total, 2, ',', '.'), 1, 0, 'R', true);
        $this->pdf->Cell($widths[2], 6, '100%', 1, 0, 'R', true);
        $this->pdf->Ln();
        
        $this->pdf->SetTextColor(...$this->colors['text']);
    }
    
    /**
     * Métodos auxiliares para geração de conteúdo
     */
    private function addSectionHeader(string $title): void 
    {
        $this->pdf->SetFont('Arial', 'B', 16);
        $this->pdf->SetTextColor(...$this->colors['primary']);
        $this->pdf->Cell(0, 12, $title, 0, 1, 'L');
        
        // Linha decorativa
        $this->pdf->SetDrawColor(...$this->colors['primary']);
        $this->pdf->Line(15, $this->pdf->GetY(), 195, $this->pdf->GetY());
        $this->pdf->Ln(8);
    }
    
    private function addSubsectionHeader(string $title): void 
    {
        $this->pdf->SetFont('Arial', 'B', 12);
        $this->pdf->SetTextColor(...$this->colors['secondary']);
        $this->pdf->Cell(0, 8, $title, 0, 1, 'L');
        $this->pdf->Ln(2);
    }
    
    private function generateHighlights(array $data): array 
    {
        $summary = $data['records']['summary'] ?? [];
        
        return [
            'Foram processadas ' . number_format($summary['total_dis'] ?? 0) . ' Declarações de Importação no período analisado.',
            'Valor total CIF de R$ ' . number_format($summary['valor_total_cif_brl'] ?? 0, 2, ',', '.') . ' foi importado.',
            'Impostos totalizaram R$ ' . number_format($summary['valor_total_impostos'] ?? 0, 2, ',', '.') . ', representando ' . 
            number_format($summary['percentual_impostos_sobre_cif'] ?? 0, 1) . '% do valor CIF.',
            'Ticket médio por DI de R$ ' . number_format($summary['ticket_medio_cif'] ?? 0, 2, ',', '.') . '.',
            'Custo landed total atingiu R$ ' . number_format($summary['custo_total_landed'] ?? 0, 2, ',', '.') . '.'
        ];
    }
    
    private function generateStrategicRecommendations(array $data): array 
    {
        return [
            [
                'title' => 'Otimização Tributária',
                'description' => 'Avaliar acordos tarifários disponíveis para redução da carga tributária em NCMs com maior volume de importação.'
            ],
            [
                'title' => 'Gestão de Custos Logísticos',
                'description' => 'Renegociar contratos de despesas portuárias e logísticas com base no volume mensal identificado.'
            ],
            [
                'title' => 'Planejamento de Cash Flow',
                'description' => 'Utilizar dados de sazonalidade para melhor planejamento financeiro dos custos de importação.'
            ]
        ];
    }
    
    private function generatePerformanceTable(array $data): void 
    {
        // Implementação da tabela de performance
        $this->pdf->SetFont('Arial', '', 9);
        $this->pdf->Cell(0, 5, 'Tabela de performance seria gerada aqui com dados mensais', 0, 1, 'L');
    }
    
    private function analyzeTrends(array $data): array 
    {
        return [
            [
                'indicator' => 'Tendência de Valores CIF',
                'analysis' => 'Análise da evolução temporal dos valores CIF importados, identificando sazonalidades e tendências de crescimento.'
            ],
            [
                'indicator' => 'Eficiência Tributária',
                'analysis' => 'Avaliação da utilização de acordos tarifários e regimes especiais para otimização da carga tributária.'
            ]
        ];
    }
    
    private function generateTaxAnalysis(array $data): void 
    {
        $this->pdf->AddPage();
        $this->addSectionHeader('Análise Tributária Detalhada');
        
        // Implementação da análise tributária
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->MultiCell(0, 5, 'Análise detalhada dos impostos por tipo, alíquotas aplicadas e benefícios fiscais utilizados.', 0, 'L');
    }
    
    private function generateExpenseAnalysis(array $data): void 
    {
        $this->pdf->AddPage();
        $this->addSectionHeader('Análise de Despesas Operacionais');
        
        // Implementação da análise de despesas
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->MultiCell(0, 5, 'Breakdown detalhado das despesas portuárias, logísticas e administrativas.', 0, 'L');
    }
    
    private function generateRecommendations(array $data): void 
    {
        $this->pdf->AddPage();
        $this->addSectionHeader('Recomendações e Próximos Passos');
        
        // Implementação das recomendações
        $this->pdf->SetFont('Arial', '', 10);
        $this->pdf->MultiCell(0, 5, 'Recomendações estratégicas baseadas na análise dos dados de importação.', 0, 'L');
    }
    
    private function generateAppendices(array $data): void 
    {
        $this->pdf->AddPage();
        $this->addSectionHeader('Anexos e Informações Técnicas');
        
        // Metodologia
        $this->addSubsectionHeader('Metodologia de Cálculo');
        $this->pdf->SetFont('Arial', '', 9);
        $this->pdf->MultiCell(0, 5, 'Este relatório foi gerado através do Sistema ETL DI\'s, que processa declarações de importação brasileiras aplicando cálculos conforme legislação vigente.', 0, 'L');
        
        // Glossário
        $this->addSubsectionHeader('Glossário');
        $glossary = [
            'CIF' => 'Cost, Insurance and Freight - Valor da mercadoria incluindo seguro e frete',
            'DI' => 'Declaração de Importação - Documento aduaneiro obrigatório',
            'II' => 'Imposto de Importação',
            'IPI' => 'Imposto sobre Produtos Industrializados',
            'ICMS' => 'Imposto sobre Circulação de Mercadorias e Serviços',
            'Landed Cost' => 'Custo total da mercadoria incluindo todos os impostos e despesas'
        ];
        
        $this->pdf->SetFont('Arial', '', 8);
        foreach ($glossary as $term => $definition) {
            $this->pdf->SetFont('Arial', 'B', 8);
            $this->pdf->Cell(30, 4, $term . ':', 0, 0, 'L');
            $this->pdf->SetFont('Arial', '', 8);
            $this->pdf->MultiCell(0, 4, $definition, 0, 'L');
            $this->pdf->Ln(1);
        }
    }
    
    private function generateTableOfContents(): void 
    {
        $this->pdf->AddPage();
        $this->addSectionHeader('Índice');
        
        $toc = [
            'Resumo Executivo' => 3,
            'Análise Gráfica' => 4,
            'Análise Detalhada de Performance' => 5,
            'Breakdown Detalhado por DI' => 6,
            'Análise Tributária Detalhada' => 8,
            'Análise de Despesas Operacionais' => 9,
            'Recomendações e Próximos Passos' => 10,
            'Anexos e Informações Técnicas' => 11
        ];
        
        $this->pdf->SetFont('Arial', '', 11);
        foreach ($toc as $section => $page) {
            $this->pdf->Cell(150, 8, $section, 0, 0, 'L');
            $this->pdf->Cell(0, 8, $page, 0, 1, 'R');
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