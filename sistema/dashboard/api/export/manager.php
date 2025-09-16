<?php
/**
 * ================================================================================
 * GERENCIADOR CENTRAL DE EXPORTAÇÃO ENTERPRISE
 * Sistema ETL DI's - Exportação Profissional Multi-formato
 * Suporte: JSON, PDF, XLSX com processamento assíncrono
 * ================================================================================
 */

require_once '../common/response.php';
require_once '../common/cache.php';
require_once '../../../config/database.php';

// Configurações de exportação
define('EXPORT_TIMEOUT', 300); // 5 minutos
define('MAX_RECORDS_SYNC', 5000); // Limite para processamento síncrono
define('EXPORT_CLEANUP_HOURS', 24); // Limpeza de arquivos temporários

/**
 * Classe principal para gerenciamento de exportações
 */
class ExportManager 
{
    private $db;
    private $exportId;
    private $progress = 0;
    private $status = 'queued';
    private $errors = [];
    
    public function __construct() 
    {
        $this->db = getDatabase()->getConnection();
        $this->exportId = uniqid('export_', true);
        $this->createExportRecord();
    }
    
    /**
     * Processar requisição de exportação
     */
    public function processExportRequest(): array 
    {
        try {
            // Obter dados da requisição
            $input = $this->getRequestData();
            $this->validateInput($input);
            
            // Determinar se é processamento síncrono ou assíncrono
            $estimatedRecords = $this->estimateRecordCount($input);
            $isAsync = $estimatedRecords > MAX_RECORDS_SYNC;
            
            if ($isAsync) {
                return $this->queueAsyncExport($input);
            } else {
                return $this->processSyncExport($input);
            }
            
        } catch (Exception $e) {
            $this->logError($e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Exportação síncrona (resposta imediata)
     */
    private function processSyncExport(array $input): array 
    {
        $this->updateStatus('processing');
        
        try {
            $data = $this->gatherExportData($input);
            $this->updateProgress(50);
            
            $filePath = $this->generateExportFile($data, $input);
            $this->updateProgress(100);
            $this->updateStatus('completed');
            
            return [
                'export_id' => $this->exportId,
                'status' => 'completed',
                'download_url' => $this->generateDownloadUrl($filePath),
                'file_size' => filesize($filePath),
                'record_count' => count($data['records']),
                'generated_at' => date('c'),
                'expires_at' => date('c', time() + (EXPORT_CLEANUP_HOURS * 3600))
            ];
            
        } catch (Exception $e) {
            $this->updateStatus('failed', $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Exportação assíncrona (via background job)
     */
    private function queueAsyncExport(array $input): array 
    {
        $this->updateStatus('queued');
        
        // Armazenar parâmetros para processamento em background
        $this->storeExportParameters($input);
        
        // Agendar job em background (pode ser via cron, queue system, etc)
        $this->scheduleBackgroundJob();
        
        return [
            'export_id' => $this->exportId,
            'status' => 'queued',
            'estimated_completion' => date('c', time() + 120), // 2 minutos estimado
            'progress_url' => "/api/export/progress/{$this->exportId}",
            'websocket_channel' => "export_{$this->exportId}"
        ];
    }
    
    /**
     * Coletar dados para exportação
     */
    private function gatherExportData(array $input): array 
    {
        $dataCollector = new ExportDataCollector($this->db);
        
        switch ($input['type']) {
            case 'dashboard_complete':
                return $dataCollector->getDashboardComplete($input['filters']);
            case 'dis_detailed':
                return $dataCollector->getDIsDetailed($input['filters']);
            case 'financial_analysis':
                return $dataCollector->getFinancialAnalysis($input['filters']);
            case 'customs_report':
                return $dataCollector->getCustomsReport($input['filters']);
            default:
                throw new Exception("Tipo de exportação não suportado: {$input['type']}");
        }
    }
    
    /**
     * Gerar arquivo de exportação
     */
    private function generateExportFile(array $data, array $input): string 
    {
        $generator = $this->getFileGenerator($input['format']);
        $template = $this->getTemplate($input['format'], $input['template'] ?? 'default');
        
        $fileName = $this->generateFileName($input);
        $filePath = $this->getExportPath($fileName);
        
        $generator->generate($data, $template, $filePath, [
            'export_id' => $this->exportId,
            'generated_by' => $_SESSION['user_name'] ?? 'Sistema',
            'company_logo' => '/sistema/dashboard/assets/images/logo-expertzy.png',
            'report_title' => $this->getReportTitle($input['type']),
            'filters_applied' => $input['filters']
        ]);
        
        return $filePath;
    }
    
    /**
     * Obter gerador específico por formato
     */
    private function getFileGenerator(string $format): object 
    {
        switch ($format) {
            case 'json':
                require_once __DIR__ . '/json.php';
                return new JsonExporter();
            case 'pdf':
                require_once __DIR__ . '/pdf.php';
                return new PdfExporter();
            case 'xlsx':
                require_once __DIR__ . '/xlsx.php';
                return new XlsxExporter();
            default:
                throw new Exception("Formato não suportado: {$format}");
        }
    }
    
    /**
     * Validação de entrada
     */
    private function validateInput(array $input): void 
    {
        $required = ['format', 'type'];
        foreach ($required as $field) {
            if (!isset($input[$field])) {
                throw new Exception("Campo obrigatório ausente: {$field}");
            }
        }
        
        $allowedFormats = ['json', 'pdf', 'xlsx'];
        if (!in_array($input['format'], $allowedFormats)) {
            throw new Exception("Formato inválido. Permitidos: " . implode(', ', $allowedFormats));
        }
        
        $allowedTypes = ['dashboard_complete', 'dis_detailed', 'financial_analysis', 'customs_report'];
        if (!in_array($input['type'], $allowedTypes)) {
            throw new Exception("Tipo inválido. Permitidos: " . implode(', ', $allowedTypes));
        }
    }
    
    /**
     * Estimativa de registros para determinação síncrono/assíncrono
     */
    private function estimateRecordCount(array $input): int 
    {
        $filters = $input['filters'] ?? [];
        $whereClause = $this->buildWhereClause($filters);
        
        $query = "SELECT COUNT(*) as total FROM v_di_resumo " . $whereClause;
        $stmt = $this->db->prepare($query);
        $stmt->execute($this->buildParameters($filters));
        
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * Métodos de controle de status e progresso
     */
    private function createExportRecord(): void 
    {
        $query = "
            INSERT INTO export_jobs (
                export_id, status, progress, created_at, updated_at
            ) VALUES (?, 'queued', 0, NOW(), NOW())
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$this->exportId]);
    }
    
    private function updateStatus(string $status, string $error = null): void 
    {
        $this->status = $status;
        
        $query = "
            UPDATE export_jobs 
            SET status = ?, error_message = ?, updated_at = NOW() 
            WHERE export_id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$status, $error, $this->exportId]);
        
        // Broadcast via WebSocket se disponível
        $this->broadcastProgress();
    }
    
    private function updateProgress(int $progress): void 
    {
        $this->progress = $progress;
        
        $query = "
            UPDATE export_jobs 
            SET progress = ?, updated_at = NOW() 
            WHERE export_id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$progress, $this->exportId]);
        
        $this->broadcastProgress();
    }
    
    /**
     * Broadcast de progresso via WebSocket
     */
    private function broadcastProgress(): void 
    {
        // Implementação WebSocket seria integrada aqui
        $progressData = [
            'export_id' => $this->exportId,
            'status' => $this->status,
            'progress' => $this->progress,
            'timestamp' => time()
        ];
        
        // Salvar para polling se WebSocket não disponível
        file_put_contents(
            $this->getProgressFile(),
            json_encode($progressData)
        );
    }
    
    /**
     * Utilitários
     */
    private function getRequestData(): array 
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            throw new Exception('Método não permitido. Use POST.');
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON inválido no corpo da requisição');
        }
        
        return $input ?? [];
    }
    
    private function generateFileName(array $input): string 
    {
        $typeNames = [
            'dashboard_complete' => 'Dashboard_Completo',
            'dis_detailed' => 'DIs_Detalhadas',
            'financial_analysis' => 'Analise_Financeira',
            'customs_report' => 'Relatorio_Aduaneiro'
        ];
        
        $typeName = $typeNames[$input['type']] ?? 'Export';
        $timestamp = date('Y-m-d_H-i-s');
        
        return "{$typeName}_{$timestamp}.{$input['format']}";
    }
    
    private function getExportPath(string $fileName): string 
    {
        $exportDir = __DIR__ . '/../../exports/';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        return $exportDir . $fileName;
    }
    
    private function getProgressFile(): string 
    {
        return $this->getExportPath("progress_{$this->exportId}.json");
    }
    
    private function generateDownloadUrl(string $filePath): string 
    {
        $fileName = basename($filePath);
        return "/api/export/download/{$fileName}?token=" . $this->generateDownloadToken($fileName);
    }
    
    private function generateDownloadToken(string $fileName): string 
    {
        $data = [
            'file' => $fileName,
            'export_id' => $this->exportId,
            'expires' => time() + (EXPORT_CLEANUP_HOURS * 3600)
        ];
        
        return base64_encode(json_encode($data));
    }
    
    private function getReportTitle(string $type): string 
    {
        $titles = [
            'dashboard_complete' => 'Dashboard Completo - Análise de Importações',
            'dis_detailed' => 'Relatório Detalhado de Declarações de Importação',
            'financial_analysis' => 'Análise Financeira e Tributária',
            'customs_report' => 'Relatório Aduaneiro Especializado'
        ];
        
        return $titles[$type] ?? 'Relatório de Exportação';
    }
    
    private function buildWhereClause(array $filters): string 
    {
        $conditions = [];
        
        if (!empty($filters['date_start'])) {
            $conditions[] = "data_registro >= :date_start";
        }
        
        if (!empty($filters['date_end'])) {
            $conditions[] = "data_registro <= :date_end";
        }
        
        if (!empty($filters['uf'])) {
            $ufs = array_map(function($uf) {
                return "'" . addslashes($uf) . "'";
            }, (array)$filters['uf']);
            $conditions[] = "importador_uf IN (" . implode(',', $ufs) . ")";
        }
        
        return !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    }
    
    private function buildParameters(array $filters): array 
    {
        $params = [];
        
        if (!empty($filters['date_start'])) {
            $params['date_start'] = $filters['date_start'];
        }
        
        if (!empty($filters['date_end'])) {
            $params['date_end'] = $filters['date_end'];
        }
        
        return $params;
    }
    
    private function logError(string $message): void 
    {
        error_log("Export Manager Error [{$this->exportId}]: {$message}");
        $this->errors[] = $message;
    }
    
    private function storeExportParameters(array $input): void 
    {
        $query = "
            UPDATE export_jobs 
            SET parameters = ? 
            WHERE export_id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([json_encode($input), $this->exportId]);
    }
    
    private function scheduleBackgroundJob(): void 
    {
        // Implementação específica dependendo do sistema de filas usado
        // Por exemplo: Redis Queue, RabbitMQ, ou simplesmente cron job
        
        // Para simplicidade, criar arquivo de job para processamento via cron
        $jobFile = $this->getExportPath("job_{$this->exportId}.json");
        file_put_contents($jobFile, json_encode([
            'export_id' => $this->exportId,
            'scheduled_at' => time(),
            'priority' => 'normal'
        ]));
    }
    
    private function getTemplate(string $format, string $templateName): array 
    {
        $templatePath = __DIR__ . "/../../templates/{$format}/{$templateName}.json";
        
        if (!file_exists($templatePath)) {
            $templatePath = __DIR__ . "/../../templates/{$format}/default.json";
        }
        
        if (!file_exists($templatePath)) {
            return $this->getDefaultTemplate($format);
        }
        
        return json_decode(file_get_contents($templatePath), true);
    }
    
    private function getDefaultTemplate(string $format): array 
    {
        // Templates padrão embutidos
        $defaults = [
            'pdf' => [
                'page_size' => 'A4',
                'orientation' => 'portrait',
                'margins' => ['top' => 20, 'bottom' => 20, 'left' => 15, 'right' => 15],
                'font_family' => 'Arial',
                'font_size' => 10,
                'header_height' => 30,
                'footer_height' => 20,
                'colors' => [
                    'primary' => '#FF002D',
                    'secondary' => '#091A30',
                    'text' => '#333333',
                    'background' => '#FFFFFF'
                ]
            ],
            'xlsx' => [
                'default_font' => 'Arial',
                'default_size' => 10,
                'freeze_panes' => true,
                'auto_filter' => true,
                'conditional_formatting' => true,
                'colors' => [
                    'header' => '#FF002D',
                    'alt_row' => '#F8F9FA'
                ]
            ],
            'json' => [
                'pretty_print' => true,
                'include_metadata' => true,
                'compression' => 'gzip'
            ]
        ];
        
        return $defaults[$format] ?? [];
    }
}

/**
 * Coletor de dados para exportação
 */
class ExportDataCollector 
{
    private $db;
    
    public function __construct($db) 
    {
        $this->db = $db;
    }
    
    /**
     * Dashboard completo com hierarquia DI → Adições → Impostos → Despesas
     */
    public function getDashboardComplete(array $filters): array 
    {
        $whereClause = $this->buildWhereClause($filters);
        $params = $this->buildParameters($filters);
        
        // 1. DIs principais
        $disQuery = "
            SELECT * FROM v_di_resumo 
            {$whereClause}
            ORDER BY data_registro DESC
        ";
        
        $stmt = $this->db->prepare($disQuery);
        $stmt->execute($params);
        $dis = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $result = [
            'metadata' => [
                'format' => 'importaco_etl_v1.0',
                'generated_at' => date('c'),
                'source' => 'Dashboard ETL DIs',
                'filters' => $filters,
                'total_dis' => count($dis),
                'checksum' => null // Será calculado após construção completa
            ],
            'summary' => $this->generateSummaryStats($dis),
            'dis' => []
        ];
        
        // 2. Para cada DI, buscar adições, impostos e despesas
        foreach ($dis as $di) {
            $diData = $di;
            
            // Adicoes da DI
            $adicoesQuery = "
                SELECT * FROM v_adicoes_completas 
                WHERE numero_di = ? 
                ORDER BY numero_adicao
            ";
            $stmt = $this->db->prepare($adicoesQuery);
            $stmt->execute([$di['numero_di']]);
            $adicoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Para cada adição, buscar impostos
            foreach ($adicoes as &$adicao) {
                $impostosQuery = "
                    SELECT * FROM impostos_adicao 
                    WHERE adicao_id = (
                        SELECT id FROM adicoes 
                        WHERE numero_di = ? AND numero_adicao = ?
                    )
                ";
                $stmt = $this->db->prepare($impostosQuery);
                $stmt->execute([$di['numero_di'], $adicao['numero_adicao']]);
                $adicao['impostos'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Despesas da DI
            $despesasQuery = "
                SELECT * FROM v_despesas_discriminadas 
                WHERE numero_di = ? 
                ORDER BY categoria, grupo_despesa
            ";
            $stmt = $this->db->prepare($despesasQuery);
            $stmt->execute([$di['numero_di']]);
            $despesas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $diData['adicoes'] = $adicoes;
            $diData['despesas'] = $despesas;
            
            $result['dis'][] = $diData;
        }
        
        // Calcular checksum
        $result['metadata']['checksum'] = hash('sha256', json_encode($result['dis']));
        
        return ['records' => $result];
    }
    
    /**
     * Gerar estatísticas resumo
     */
    private function generateSummaryStats(array $dis): array 
    {
        $totalCif = array_sum(array_column($dis, 'valor_cif_brl'));
        $totalImpostos = array_sum(array_column($dis, 'total_impostos'));
        $totalLanded = array_sum(array_column($dis, 'custo_total_landed'));
        
        return [
            'total_dis' => count($dis),
            'valor_total_cif_brl' => $totalCif,
            'valor_total_impostos' => $totalImpostos,
            'custo_total_landed' => $totalLanded,
            'percentual_impostos_sobre_cif' => $totalCif > 0 ? ($totalImpostos / $totalCif) * 100 : 0,
            'ticket_medio_cif' => count($dis) > 0 ? $totalCif / count($dis) : 0,
            'periodo_analise' => [
                'data_inicio' => min(array_column($dis, 'data_registro')),
                'data_fim' => max(array_column($dis, 'data_registro'))
            ]
        ];
    }
    
    // Outros métodos de coleta de dados específicos...
    public function getDIsDetailed(array $filters): array { /* implementação */ return []; }
    public function getFinancialAnalysis(array $filters): array { /* implementação */ return []; }
    public function getCustomsReport(array $filters): array { /* implementação */ return []; }
    
    private function buildWhereClause(array $filters): string 
    {
        $conditions = [];
        
        if (!empty($filters['date_start'])) {
            $conditions[] = "data_registro >= :date_start";
        }
        
        if (!empty($filters['date_end'])) {
            $conditions[] = "data_registro <= :date_end";
        }
        
        if (!empty($filters['uf'])) {
            $ufs = array_map(function($uf) {
                return "'" . addslashes($uf) . "'";
            }, (array)$filters['uf']);
            $conditions[] = "importador_uf IN (" . implode(',', $ufs) . ")";
        }
        
        return !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
    }
    
    private function buildParameters(array $filters): array 
    {
        $params = [];
        
        if (!empty($filters['date_start'])) {
            $params['date_start'] = $filters['date_start'];
        }
        
        if (!empty($filters['date_end'])) {
            $params['date_end'] = $filters['date_end'];
        }
        
        return $params;
    }
}

// Endpoint de processamento
try {
    apiMiddleware();
    
    $manager = new ExportManager();
    $result = $manager->processExportRequest();
    
    apiSuccess()
        ->setData($result)
        ->addMeta('processing_time', microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'])
        ->send();
        
} catch (Exception $e) {
    error_log("Export Manager Error: " . $e->getMessage());
    apiError($e->getMessage(), 500)->send();
}