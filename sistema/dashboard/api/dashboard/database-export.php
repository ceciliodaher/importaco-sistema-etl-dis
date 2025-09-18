<?php
/**
 * ================================================================================
 * API DE EXPORTAÇÃO JSON DO BANCO DE DADOS
 * Sistema ETL DI's - Exportação completa para validação de dados importados
 * Funcões: Export completo, por período, DI específica com estrutura hierárquica
 * ================================================================================
 */

require_once dirname(__DIR__) . '/common/response.php';
require_once dirname(__DIR__) . '/common/validator.php';
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__) . '/export/json.php';

// Middleware de inicialização
apiMiddleware();

try {
    // Verificar método
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        apiError('Método não permitido. Use POST.', 405)->send();
    }

    // Obter dados JSON do corpo da requisição
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        apiError('JSON inválido no corpo da requisição', 400)->send();
    }

    // Validar parâmetros
    $validator = new ApiValidator();
    $params = $input ?? [];
    
    // Parâmetros de export
    $exportType = $params['export_type'] ?? 'all'; // all, period, di
    $numeroDi = $params['numero_di'] ?? '';
    $startDate = $params['start_date'] ?? '';
    $endDate = $params['end_date'] ?? '';
    $includeMetadata = (bool)($params['include_metadata'] ?? true);
    $prettyPrint = (bool)($params['pretty_print'] ?? true);
    $compression = $params['compression'] ?? 'none'; // none, gzip
    
    // Validar tipo de export
    $allowedTypes = ['all', 'period', 'di'];
    if (!in_array($exportType, $allowedTypes)) {
        apiError('Tipo de export inválido. Tipos permitidos: ' . implode(', ', $allowedTypes), 400)->send();
    }
    
    // Inicializar banco e export service
    $db = getDatabase();
    $exportService = new DatabaseExportService($db);
    
    // Executar exportação baseada no tipo
    $result = executeExport($exportService, $exportType, $params);
    
    // Retornar sucesso com dados ou arquivo
    if (isset($result['file_path'])) {
        // Export para arquivo - retornar informações do arquivo
        apiSuccess()
            ->setData([
                'export_completed' => true,
                'file_info' => $result['file_info'],
                'download_url' => $result['download_url'],
                'summary' => $result['summary']
            ])
            ->addMeta('export_type', $exportType)
            ->addMeta('generated_at', date('Y-m-d H:i:s'))
            ->send();
    } else {
        // Export direto - retornar JSON
        apiSuccess()
            ->setData($result['data'])
            ->addMeta('export_type', $exportType)
            ->addMeta('generated_at', date('Y-m-d H:i:s'))
            ->addMeta('records_count', $result['records_count'])
            ->send();
    }
    
} catch (Exception $e) {
    error_log("API Database Export Error: " . $e->getMessage());
    apiError('Erro interno do servidor: ' . $e->getMessage(), 500)->send();
}

/**
 * Executar exportação baseada no tipo
 */
function executeExport(DatabaseExportService $service, string $exportType, array $params): array 
{
    switch ($exportType) {
        case 'all':
            return $service->exportAll($params);
            
        case 'period':
            $startDate = $params['start_date'] ?? '';
            $endDate = $params['end_date'] ?? '';
            if (empty($startDate) || empty($endDate)) {
                throw new Exception('Datas de início e fim são obrigatórias para export por período');
            }
            return $service->exportByPeriod($startDate, $endDate, $params);
            
        case 'di':
            $numeroDi = $params['numero_di'] ?? '';
            if (empty($numeroDi)) {
                throw new Exception('Número da DI é obrigatório');
            }
            return $service->exportSpecificDI($numeroDi, $params);
            
        default:
            throw new Exception('Tipo de export não implementado: ' . $exportType);
    }
}

/**
 * Serviço de exportação do banco de dados
 */
class DatabaseExportService 
{
    private $db;
    private $maxRecords = 1000; // Limite de segurança
    private $maxFileSize = 100 * 1024 * 1024; // 100MB
    
    public function __construct($database) 
    {
        $this->db = $database;
    }
    
    /**
     * Exportar todas as DIs processadas
     */
    public function exportAll(array $options): array 
    {
        // Verificar se há muitos registros
        $totalCount = $this->countAllDIs();
        
        if ($totalCount > $this->maxRecords) {
            throw new Exception("Muitos registros para export direto ({$totalCount}). Use export por período ou especifique limite.");
        }
        
        if ($totalCount === 0) {
            return [
                'data' => $this->generateEmptyExport(),
                'records_count' => 0
            ];
        }
        
        // Buscar todas as DIs com dados relacionados
        $data = $this->fetchAllDIsWithRelatedData();
        
        // Processar com JsonExporter
        $jsonData = $this->processDataForExport($data, $options, 'all');
        
        return [
            'data' => $jsonData,
            'records_count' => $totalCount
        ];
    }
    
    /**
     * Exportar DIs por período
     */
    public function exportByPeriod(string $startDate, string $endDate, array $options): array 
    {
        // Validar datas
        if (!$this->isValidDate($startDate) || !$this->isValidDate($endDate)) {
            throw new Exception('Formato de data inválido. Use YYYY-MM-DD');
        }
        
        if ($startDate > $endDate) {
            throw new Exception('Data de início deve ser anterior à data de fim');
        }
        
        // Verificar se há registros no período
        $totalCount = $this->countDIsByPeriod($startDate, $endDate);
        
        if ($totalCount === 0) {
            return [
                'data' => $this->generateEmptyExport(),
                'records_count' => 0
            ];
        }
        
        // Buscar DIs do período com dados relacionados
        $data = $this->fetchDIsByPeriodWithRelatedData($startDate, $endDate);
        
        // Processar com JsonExporter
        $jsonData = $this->processDataForExport($data, $options, 'period', [
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        return [
            'data' => $jsonData,
            'records_count' => $totalCount
        ];
    }
    
    /**
     * Exportar DI específica
     */
    public function exportSpecificDI(string $numeroDi, array $options): array 
    {
        // Validar formato da DI
        if (!preg_match('/^[0-9]{10}$/', $numeroDi)) {
            throw new Exception('Formato de número DI inválido');
        }
        
        // Verificar se DI existe
        $diExists = $this->db->fetchOne("SELECT numero_di FROM declaracoes_importacao WHERE numero_di = ?", [$numeroDi]);
        if (!$diExists) {
            return [
                'data' => $this->generateEmptyExport(),
                'records_count' => 0,
                'message' => "DI {$numeroDi} não encontrada"
            ];
        }
        
        // Buscar DI específica com dados relacionados
        $data = $this->fetchSpecificDIWithRelatedData($numeroDi);
        
        // Processar com JsonExporter
        $jsonData = $this->processDataForExport($data, $options, 'di', [
            'numero_di' => $numeroDi
        ]);
        
        return [
            'data' => $jsonData,
            'records_count' => 1
        ];
    }
    
    /**
     * Buscar todas as DIs com dados relacionados
     */
    private function fetchAllDIsWithRelatedData(): array 
    {
        // Query principal das DIs
        $dis = $this->db->fetchAll("
            SELECT 
                di.numero_di,
                di.data_registro,
                di.urf_despacho_codigo,
                di.urf_despacho_nome,
                di.importador_cnpj,
                di.importador_nome,
                di.canal_selecao,
                di.caracteristica_operacao,
                di.total_adicoes,
                di.valor_total_cif_usd,
                di.valor_total_cif_brl,
                di.status_processamento,
                di.created_at,
                di.updated_at
            FROM declaracoes_importacao di
            ORDER BY di.data_registro DESC, di.numero_di ASC
        ");
        
        // Para cada DI, buscar dados relacionados
        foreach ($dis as &$di) {
            $di['adicoes'] = $this->fetchAdicoesByDI($di['numero_di']);
            $di['despesas'] = $this->fetchDespesasByDI($di['numero_di']);
            $di['processamento'] = $this->fetchProcessamentoByDI($di['numero_di']);
        }
        
        return [
            'records' => [
                'dis' => $dis,
                'summary' => $this->generateSummary($dis)
            ]
        ];
    }
    
    /**
     * Buscar DIs por período com dados relacionados
     */
    private function fetchDIsByPeriodWithRelatedData(string $startDate, string $endDate): array 
    {
        // Query principal das DIs no período
        $dis = $this->db->fetchAll("
            SELECT 
                di.numero_di,
                di.data_registro,
                di.urf_despacho_codigo,
                di.urf_despacho_nome,
                di.importador_cnpj,
                di.importador_nome,
                di.canal_selecao,
                di.caracteristica_operacao,
                di.total_adicoes,
                di.valor_total_cif_usd,
                di.valor_total_cif_brl,
                di.status_processamento,
                di.created_at,
                di.updated_at
            FROM declaracoes_importacao di
            WHERE di.data_registro >= ? AND di.data_registro <= ?
            ORDER BY di.data_registro DESC, di.numero_di ASC
        ", [$startDate, $endDate]);
        
        // Para cada DI, buscar dados relacionados
        foreach ($dis as &$di) {
            $di['adicoes'] = $this->fetchAdicoesByDI($di['numero_di']);
            $di['despesas'] = $this->fetchDespesasByDI($di['numero_di']);
            $di['processamento'] = $this->fetchProcessamentoByDI($di['numero_di']);
        }
        
        return [
            'records' => [
                'dis' => $dis,
                'summary' => $this->generateSummary($dis)
            ]
        ];
    }
    
    /**
     * Buscar DI específica com dados relacionados
     */
    private function fetchSpecificDIWithRelatedData(string $numeroDi): array 
    {
        // Query da DI específica
        $di = $this->db->fetchOne("
            SELECT 
                di.numero_di,
                di.data_registro,
                di.urf_despacho_codigo,
                di.urf_despacho_nome,
                di.importador_cnpj,
                di.importador_nome,
                di.canal_selecao,
                di.caracteristica_operacao,
                di.total_adicoes,
                di.valor_total_cif_usd,
                di.valor_total_cif_brl,
                di.status_processamento,
                di.created_at,
                di.updated_at
            FROM declaracoes_importacao di
            WHERE di.numero_di = ?
        ", [$numeroDi]);
        
        if (!$di) {
            return ['records' => ['dis' => []]];
        }
        
        // Buscar dados relacionados
        $di['adicoes'] = $this->fetchAdicoesByDI($numeroDi);
        $di['despesas'] = $this->fetchDespesasByDI($numeroDi);
        $di['processamento'] = $this->fetchProcessamentoByDI($numeroDi);
        
        return [
            'records' => [
                'dis' => [$di],
                'summary' => $this->generateSummary([$di])
            ]
        ];
    }
    
    /**
     * Buscar adições de uma DI
     */
    private function fetchAdicoesByDI(string $numeroDi): array 
    {
        $adicoes = $this->db->fetchAll("
            SELECT 
                a.id,
                a.numero_di,
                a.numero_adicao,
                a.numero_sequencial_item,
                a.ncm,
                a.valor_vmle_moeda,
                a.valor_vmle_reais,
                a.valor_vmcv_moeda,
                a.valor_vmcv_reais,
                a.moeda_codigo,
                a.moeda_nome,
                a.incoterm,
                a.condicao_venda_local,
                a.peso_liquido,
                a.peso_bruto,
                a.taxa_cambio_calculada,
                a.created_at,
                a.updated_at
            FROM adicoes a
            WHERE a.numero_di = ?
            ORDER BY a.numero_adicao ASC
        ", [$numeroDi]);
        
        // Para cada adição, buscar impostos e mercadorias
        foreach ($adicoes as &$adicao) {
            $adicao['impostos'] = $this->fetchImpostosByAdicao($adicao['id']);
            $adicao['mercadorias'] = $this->fetchMercadoriasByAdicao($adicao['id']);
        }
        
        return $adicoes;
    }
    
    /**
     * Buscar impostos de uma adição
     */
    private function fetchImpostosByAdicao(int $adicaoId): array 
    {
        return $this->db->fetchAll("
            SELECT 
                imp.id,
                imp.adicao_id,
                imp.tipo_imposto,
                imp.valor_devido_reais,
                imp.base_calculo,
                imp.aliquota_ad_valorem,
                imp.aliquota_especifica,
                imp.created_at,
                imp.updated_at
            FROM impostos_adicao imp
            WHERE imp.adicao_id = ?
            ORDER BY imp.tipo_imposto ASC
        ", [$adicaoId]);
    }
    
    /**
     * Buscar mercadorias de uma adição
     */
    private function fetchMercadoriasByAdicao(int $adicaoId): array 
    {
        return $this->db->fetchAll("
            SELECT 
                m.id,
                m.adicao_id,
                m.numero_sequencial,
                m.descricao,
                m.quantidade,
                m.unidade_medida,
                m.created_at,
                m.updated_at
            FROM mercadorias m
            WHERE m.adicao_id = ?
            ORDER BY m.numero_sequencial ASC
        ", [$adicaoId]);
    }
    
    /**
     * Buscar despesas de uma DI
     */
    private function fetchDespesasByDI(string $numeroDi): array 
    {
        return $this->db->fetchAll("
            SELECT 
                de.id,
                de.numero_di,
                de.categoria,
                de.grupo_despesa,
                de.valor_original,
                de.valor_final,
                de.moeda_original,
                de.origem_valor,
                de.compoe_base_icms,
                de.fornecedor_nome,
                de.numero_documento,
                de.validado,
                de.observacao_divergencia,
                de.created_at,
                de.updated_at
            FROM despesas_extras de
            WHERE de.numero_di = ?
            ORDER BY de.categoria ASC
        ", [$numeroDi]);
    }
    
    /**
     * Buscar processamento XML de uma DI
     */
    private function fetchProcessamentoByDI(string $numeroDi): array 
    {
        $processamento = $this->db->fetchOne("
            SELECT 
                px.hash_arquivo,
                px.nome_arquivo,
                px.numero_di,
                px.incoterm,
                px.tamanho_arquivo,
                px.status_processamento,
                px.data_processamento,
                px.observacoes,
                px.created_at
            FROM processamento_xmls px
            WHERE px.numero_di = ?
            ORDER BY px.data_processamento DESC
            LIMIT 1
        ", [$numeroDi]);
        
        return $processamento ? [$processamento] : [];
    }
    
    /**
     * Processar dados para export usando JsonExporter
     */
    private function processDataForExport(array $data, array $options, string $exportType, array $additionalInfo = []): array 
    {
        // Configurar template do JsonExporter
        $template = [
            'pretty_print' => $options['pretty_print'] ?? true,
            'include_metadata' => $options['include_metadata'] ?? true,
            'validate_schema' => true,
            'compression' => $options['compression'] ?? 'none'
        ];
        
        // Opções do export
        $exportOptions = [
            'generated_by' => 'Dashboard ETL DI\'s',
            'export_id' => 'export_' . date('YmdHis') . '_' . uniqid(),
            'report_title' => $this->getReportTitle($exportType, $additionalInfo),
            'filters_applied' => $this->getFiltersApplied($exportType, $additionalInfo)
        ];
        
        // Usar JsonExporter para processar dados
        $jsonExporter = new JsonExporter();
        
        // Como não temos arquivo físico, vamos simular o processamento interno
        $processedData = $this->processDataInternally($data, $template, $exportOptions);
        
        return $processedData;
    }
    
    /**
     * Processar dados internamente (similar ao JsonExporter)
     */
    private function processDataInternally(array $data, array $template, array $options): array 
    {
        $result = [
            'metadata' => [
                'format_version' => 'importaco_etl_v1.0',
                'schema_version' => '1.0.0',
                'generated_at' => date('c'),
                'generated_by' => $options['generated_by'],
                'export_id' => $options['export_id'],
                'source_system' => 'Dashboard ETL DI\'s - Sistema Expertzy',
                'total_records_processed' => count($data['records']['dis'] ?? []),
                'filters_applied' => $options['filters_applied'] ?? [],
                'template_used' => 'json_hierarchical_v1'
            ],
            'summary' => $data['records']['summary'] ?? [],
            'dis' => [],
            'export_info' => [
                'generated_by_user' => $options['generated_by'],
                'report_title' => $options['report_title'],
                'company_info' => [
                    'name' => 'Sistema ETL DI\'s',
                    'powered_by' => 'Expertzy Technology',
                    'version' => '1.0.0'
                ]
            ]
        ];
        
        // Processar cada DI
        if (isset($data['records']['dis'])) {
            foreach ($data['records']['dis'] as $di) {
                $result['dis'][] = $this->processDIForExport($di);
            }
        }
        
        // Calcular checksums finais
        $result['metadata']['data_checksum'] = hash('sha256', json_encode($result['dis']));
        $result['metadata']['export_checksum'] = hash('sha256', json_encode($result));
        
        return $result;
    }
    
    /**
     * Processar DI individual para export
     */
    private function processDIForExport(array $di): array 
    {
        $processedDI = [
            'identificacao' => [
                'numero_di' => $di['numero_di'],
                'data_registro' => $di['data_registro'],
                'urf_despacho' => [
                    'codigo' => $di['urf_despacho_codigo'],
                    'nome' => $di['urf_despacho_nome']
                ],
                'canal_selecao' => $di['canal_selecao'],
                'status_processamento' => $di['status_processamento']
            ],
            'importador' => [
                'nome' => $di['importador_nome'],
                'cnpj' => $di['importador_cnpj']
            ],
            'valores_principais' => [
                'cif' => [
                    'valor_original_usd' => (float)($di['valor_total_cif_usd'] ?? 0),
                    'valor_brl' => (float)($di['valor_total_cif_brl'] ?? 0)
                ],
                'total_adicoes' => (int)($di['total_adicoes'] ?? 0)
            ],
            'adicoes' => [],
            'despesas' => [],
            'processamento' => $di['processamento'] ?? [],
            'timestamps' => [
                'created_at' => $di['created_at'],
                'updated_at' => $di['updated_at']
            ]
        ];
        
        // Processar adições
        if (isset($di['adicoes']) && is_array($di['adicoes'])) {
            foreach ($di['adicoes'] as $adicao) {
                $processedDI['adicoes'][] = [
                    'numero_adicao' => $adicao['numero_adicao'],
                    'ncm' => $adicao['ncm'],
                    'valores' => [
                        'vmle_moeda' => (float)($adicao['valor_vmle_moeda'] ?? 0),
                        'vmle_reais' => (float)($adicao['valor_vmle_reais'] ?? 0),
                        'vmcv_moeda' => (float)($adicao['valor_vmcv_moeda'] ?? 0),
                        'vmcv_reais' => (float)($adicao['valor_vmcv_reais'] ?? 0)
                    ],
                    'moeda' => [
                        'codigo' => $adicao['moeda_codigo'],
                        'nome' => $adicao['moeda_nome']
                    ],
                    'incoterm' => $adicao['incoterm'],
                    'pesos' => [
                        'liquido' => (float)($adicao['peso_liquido'] ?? 0),
                        'bruto' => (float)($adicao['peso_bruto'] ?? 0)
                    ],
                    'taxa_cambio_calculada' => (float)($adicao['taxa_cambio_calculada'] ?? 0),
                    'impostos' => $adicao['impostos'] ?? [],
                    'mercadorias' => $adicao['mercadorias'] ?? []
                ];
            }
        }
        
        // Processar despesas
        if (isset($di['despesas']) && is_array($di['despesas'])) {
            $processedDI['despesas'] = $di['despesas'];
        }
        
        return $processedDI;
    }
    
    /**
     * Gerar resumo das DIs
     */
    private function generateSummary(array $dis): array 
    {
        if (empty($dis)) {
            return [
                'total_dis' => 0,
                'valor_total_cif_brl' => 0,
                'valor_total_cif_usd' => 0,
                'periodo_analise' => null
            ];
        }
        
        $totalDis = count($dis);
        $valorTotalBrl = array_sum(array_column($dis, 'valor_total_cif_brl'));
        $valorTotalUsd = array_sum(array_column($dis, 'valor_total_cif_usd'));
        
        $datas = array_column($dis, 'data_registro');
        $dataInicio = min($datas);
        $dataFim = max($datas);
        
        return [
            'total_dis' => $totalDis,
            'valor_total_cif_brl' => $valorTotalBrl,
            'valor_total_cif_usd' => $valorTotalUsd,
            'ticket_medio_cif' => $totalDis > 0 ? ($valorTotalBrl / $totalDis) : 0,
            'periodo_analise' => [
                'data_inicio' => $dataInicio,
                'data_fim' => $dataFim
            ]
        ];
    }
    
    /**
     * Gerar export vazio
     */
    private function generateEmptyExport(): array 
    {
        return [
            'metadata' => [
                'format_version' => 'importaco_etl_v1.0',
                'generated_at' => date('c'),
                'source_system' => 'Dashboard ETL DI\'s - Sistema Expertzy',
                'total_records_processed' => 0
            ],
            'summary' => [
                'total_dis' => 0,
                'valor_total_cif_brl' => 0
            ],
            'dis' => [],
            'message' => 'Nenhum dado encontrado para os critérios especificados'
        ];
    }
    
    /**
     * Contar todas as DIs
     */
    private function countAllDIs(): int 
    {
        return $this->db->fetchOne("SELECT COUNT(*) as count FROM declaracoes_importacao")['count'];
    }
    
    /**
     * Contar DIs por período
     */
    private function countDIsByPeriod(string $startDate, string $endDate): int 
    {
        return $this->db->fetchOne("
            SELECT COUNT(*) as count 
            FROM declaracoes_importacao 
            WHERE data_registro >= ? AND data_registro <= ?
        ", [$startDate, $endDate])['count'];
    }
    
    /**
     * Validar formato de data
     */
    private function isValidDate(string $date): bool 
    {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Obter título do relatório
     */
    private function getReportTitle(string $exportType, array $additionalInfo): string 
    {
        switch ($exportType) {
            case 'all':
                return 'Exportação Completa - Todas as DIs';
            case 'period':
                return "Exportação por Período - {$additionalInfo['start_date']} a {$additionalInfo['end_date']}";
            case 'di':
                return "Exportação DI Específica - {$additionalInfo['numero_di']}";
            default:
                return 'Exportação de Dados';
        }
    }
    
    /**
     * Obter filtros aplicados
     */
    private function getFiltersApplied(string $exportType, array $additionalInfo): array 
    {
        switch ($exportType) {
            case 'period':
                return [
                    'tipo' => 'periodo',
                    'data_inicio' => $additionalInfo['start_date'],
                    'data_fim' => $additionalInfo['end_date']
                ];
            case 'di':
                return [
                    'tipo' => 'di_especifica',
                    'numero_di' => $additionalInfo['numero_di']
                ];
            default:
                return ['tipo' => 'nenhum'];
        }
    }
}