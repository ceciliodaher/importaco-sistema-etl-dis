<?php
/**
 * ================================================================================
 * PROCESSADOR BACKGROUND PARA EXPORTAÇÕES ASSÍNCRONAS
 * Sistema ETL DI's - Processamento via Cron/Queue Jobs
 * Features: Job scheduling, progress tracking, cleanup automático
 * ================================================================================
 */

require_once '../common/response.php';
require_once '../common/cache.php';
require_once '../../../config/database.php';
require_once 'manager.php';

/**
 * Classe para processamento de exportações em background
 */
class BackgroundExportProcessor 
{
    private $db;
    private $maxConcurrentJobs = 3;
    private $maxExecutionTime = 300; // 5 minutos
    private $cleanupRetentionDays = 7;
    private $logFile;
    
    public function __construct() 
    {
        $this->db = getDatabase()->getConnection();
        $this->logFile = __DIR__ . '/../../logs/export_processor.log';
        
        // Configurar limits
        set_time_limit($this->maxExecutionTime);
        ini_set('memory_limit', '512M');
    }
    
    /**
     * Processar jobs em fila
     */
    public function processQueue(): void 
    {
        try {
            $this->log('Iniciando processamento da fila de exportações');
            
            // Buscar jobs pendentes
            $pendingJobs = $this->getPendingJobs();
            
            if (empty($pendingJobs)) {
                $this->log('Nenhum job pendente encontrado');
                return;
            }
            
            $this->log(sprintf('Encontrados %d jobs pendentes', count($pendingJobs)));
            
            // Verificar jobs ativos
            $activeJobs = $this->getActiveJobs();
            $availableSlots = max(0, $this->maxConcurrentJobs - count($activeJobs));
            
            if ($availableSlots === 0) {
                $this->log('Máximo de jobs concorrentes atingido, aguardando');
                return;
            }
            
            // Processar jobs até atingir limite
            $jobsToProcess = array_slice($pendingJobs, 0, $availableSlots);
            
            foreach ($jobsToProcess as $job) {
                $this->processJob($job);
            }
            
            // Cleanup de jobs antigos
            $this->cleanupOldJobs();
            
            $this->log('Processamento da fila concluído');
            
        } catch (Exception $e) {
            $this->log('Erro no processamento da fila: ' . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Processar job individual
     */
    private function processJob(array $job): void 
    {
        $exportId = $job['export_id'];
        
        try {
            $this->log("Iniciando processamento do job: {$exportId}");
            
            // Marcar job como ativo
            $this->updateJobStatus($exportId, 'processing', 0, 'Iniciando processamento');
            
            // Decodificar parâmetros
            $parameters = json_decode($job['parameters'], true);
            if (!$parameters) {
                throw new Exception('Parâmetros inválidos no job');
            }
            
            // Instanciar coletores e geradores
            $dataCollector = new ExportDataCollector($this->db);
            $progressCallback = function($progress, $message) use ($exportId) {
                $this->updateJobProgress($exportId, $progress, $message);
            };
            
            // Coletar dados com callback de progresso
            $progressCallback(10, 'Coletando dados...');
            $data = $this->collectDataWithProgress($dataCollector, $parameters, $progressCallback);
            
            $progressCallback(50, 'Gerando arquivo...');
            
            // Gerar arquivo
            $filePath = $this->generateExportFile($data, $parameters, $exportId);
            
            $progressCallback(90, 'Finalizando...');
            
            // Calcular tamanho do arquivo
            $fileSize = filesize($filePath);
            
            // Gerar URL de download
            $downloadUrl = $this->generateDownloadUrl($filePath, $exportId);
            
            // Marcar como concluído
            $this->updateJobStatus($exportId, 'completed', 100, 'Exportação concluída', [
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'download_url' => $downloadUrl
            ]);
            
            // Notificar via WebSocket se disponível
            $this->notifyCompletion($exportId, $downloadUrl, $fileSize);
            
            $this->log("Job {$exportId} processado com sucesso");
            
        } catch (Exception $e) {
            $this->log("Erro no processamento do job {$exportId}: " . $e->getMessage(), 'ERROR');
            $this->updateJobStatus($exportId, 'failed', 0, 'Erro: ' . $e->getMessage());
            $this->notifyError($exportId, $e->getMessage());
        }
    }
    
    /**
     * Coletar dados com atualizações de progresso
     */
    private function collectDataWithProgress($dataCollector, array $parameters, callable $progressCallback): array 
    {
        $progressCallback(15, 'Coletando DIs...');
        
        switch ($parameters['type']) {
            case 'dashboard_complete':
                $data = $dataCollector->getDashboardComplete($parameters['filters']);
                break;
            case 'dis_detailed':
                $data = $dataCollector->getDIsDetailed($parameters['filters']);
                break;
            case 'financial_analysis':
                $data = $dataCollector->getFinancialAnalysis($parameters['filters']);
                break;
            case 'customs_report':
                $data = $dataCollector->getCustomsReport($parameters['filters']);
                break;
            default:
                throw new Exception("Tipo de exportação não suportado: {$parameters['type']}");
        }
        
        $progressCallback(40, 'Dados coletados com sucesso');
        
        return $data;
    }
    
    /**
     * Gerar arquivo de exportação
     */
    private function generateExportFile(array $data, array $parameters, string $exportId): string 
    {
        // Obter gerador
        $generator = $this->getFileGenerator($parameters['format']);
        
        // Obter template
        $template = $this->getTemplate($parameters['format'], $parameters['template'] ?? 'default');
        
        // Gerar nome do arquivo
        $fileName = $this->generateFileName($parameters, $exportId);
        $filePath = $this->getExportPath($fileName);
        
        // Opções adicionais
        $options = [
            'export_id' => $exportId,
            'generated_by' => 'Background Processor',
            'company_logo' => '/sistema/dashboard/assets/images/logo-expertzy.png',
            'report_title' => $this->getReportTitle($parameters['type']),
            'filters_applied' => $parameters['filters'] ?? []
        ];
        
        // Gerar arquivo
        $generator->generate($data, $template, $filePath, $options);
        
        return $filePath;
    }
    
    /**
     * Obter jobs pendentes
     */
    private function getPendingJobs(): array 
    {
        $query = "
            SELECT export_id, parameters, created_at
            FROM export_jobs 
            WHERE status = 'queued' 
            ORDER BY created_at ASC
            LIMIT 10
        ";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Obter jobs ativos
     */
    private function getActiveJobs(): array 
    {
        $query = "
            SELECT export_id, status, updated_at
            FROM export_jobs 
            WHERE status = 'processing'
            AND updated_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ";
        
        $stmt = $this->db->query($query);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Atualizar status do job
     */
    private function updateJobStatus(string $exportId, string $status, int $progress, string $message, array $metadata = []): void 
    {
        $query = "
            UPDATE export_jobs 
            SET status = ?, progress = ?, status_message = ?, metadata = ?, updated_at = NOW()
            WHERE export_id = ?
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            $status,
            $progress,
            $message,
            json_encode($metadata),
            $exportId
        ]);
    }
    
    /**
     * Atualizar progresso do job
     */
    private function updateJobProgress(string $exportId, int $progress, string $message): void 
    {
        $this->updateJobStatus($exportId, 'processing', $progress, $message);
        
        // Broadcast via WebSocket se disponível
        $this->broadcastProgress($exportId, $progress, 'processing', $message);
    }
    
    /**
     * Limpeza de jobs antigos
     */
    private function cleanupOldJobs(): void 
    {
        try {
            // Remover jobs muito antigos do banco
            $query = "
                DELETE FROM export_jobs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
                AND status IN ('completed', 'failed', 'cancelled')
            ";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$this->cleanupRetentionDays]);
            
            $deletedRows = $stmt->rowCount();
            if ($deletedRows > 0) {
                $this->log("Removidos {$deletedRows} jobs antigos do banco de dados");
            }
            
            // Cleanup de arquivos antigos
            $this->cleanupOldFiles();
            
        } catch (Exception $e) {
            $this->log('Erro no cleanup: ' . $e->getMessage(), 'ERROR');
        }
    }
    
    /**
     * Cleanup de arquivos antigos
     */
    private function cleanupOldFiles(): void 
    {
        $exportDir = __DIR__ . '/../../exports/';
        if (!is_dir($exportDir)) return;
        
        $cutoffTime = time() - ($this->cleanupRetentionDays * 24 * 60 * 60);
        $filesRemoved = 0;
        
        $files = glob($exportDir . '*');
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoffTime) {
                if (unlink($file)) {
                    $filesRemoved++;
                }
            }
        }
        
        if ($filesRemoved > 0) {
            $this->log("Removidos {$filesRemoved} arquivos antigos");
        }
    }
    
    /**
     * Notificações
     */
    private function notifyCompletion(string $exportId, string $downloadUrl, int $fileSize): void 
    {
        $this->broadcastMessage([
            'type' => 'export_complete',
            'export_id' => $exportId,
            'download_url' => $downloadUrl,
            'file_size' => $fileSize,
            'timestamp' => time()
        ]);
    }
    
    private function notifyError(string $exportId, string $error): void 
    {
        $this->broadcastMessage([
            'type' => 'export_error',
            'export_id' => $exportId,
            'error' => $error,
            'timestamp' => time()
        ]);
    }
    
    private function broadcastProgress(string $exportId, int $progress, string $status, string $message): void 
    {
        $this->broadcastMessage([
            'type' => 'export_progress',
            'export_id' => $exportId,
            'progress' => $progress,
            'status' => $status,
            'message' => $message,
            'timestamp' => time()
        ]);
    }
    
    /**
     * Broadcast via WebSocket ou arquivo para polling
     */
    private function broadcastMessage(array $message): void 
    {
        // Tentar WebSocket primeiro
        if ($this->sendWebSocketMessage($message)) {
            return;
        }
        
        // Fallback: salvar em arquivo para polling
        $this->saveProgressFile($message['export_id'], $message);
    }
    
    /**
     * Enviar mensagem via WebSocket (implementação específica)
     */
    private function sendWebSocketMessage(array $message): bool 
    {
        // Implementação específica do WebSocket seria aqui
        // Por exemplo, usando ReactPHP, Ratchet, ou outro servidor WebSocket
        
        try {
            // Placeholder para implementação WebSocket
            // return $this->webSocketClient->send(json_encode($message));
            
            return false; // WebSocket não disponível no momento
        } catch (Exception $e) {
            $this->log('Erro ao enviar WebSocket: ' . $e->getMessage(), 'WARNING');
            return false;
        }
    }
    
    /**
     * Salvar arquivo de progresso para polling
     */
    private function saveProgressFile(string $exportId, array $data): void 
    {
        $progressFile = $this->getExportPath("progress_{$exportId}.json");
        file_put_contents($progressFile, json_encode($data));
    }
    
    /**
     * Métodos auxiliares
     */
    private function getFileGenerator(string $format): object 
    {
        switch ($format) {
            case 'json':
                require_once 'json.php';
                return new JsonExporter();
            case 'pdf':
                require_once 'pdf.php';
                return new PdfExporter();
            case 'xlsx':
                require_once 'xlsx.php';
                return new XlsxExporter();
            default:
                throw new Exception("Formato não suportado: {$format}");
        }
    }
    
    private function getTemplate(string $format, string $templateName): array 
    {
        $templatePath = __DIR__ . "/../../templates/{$format}/{$templateName}.json";
        
        if (!file_exists($templatePath)) {
            $templatePath = __DIR__ . "/../../templates/{$format}/default.json";
        }
        
        if (!file_exists($templatePath)) {
            return [];
        }
        
        return json_decode(file_get_contents($templatePath), true) ?: [];
    }
    
    private function generateFileName(array $parameters, string $exportId): string 
    {
        $typeNames = [
            'dashboard_complete' => 'Dashboard_Completo',
            'dis_detailed' => 'DIs_Detalhadas',
            'financial_analysis' => 'Analise_Financeira',
            'customs_report' => 'Relatorio_Aduaneiro'
        ];
        
        $typeName = $typeNames[$parameters['type']] ?? 'Export';
        $timestamp = date('Y-m-d_H-i-s');
        $shortId = substr($exportId, -8);
        
        return "{$typeName}_{$timestamp}_{$shortId}.{$parameters['format']}";
    }
    
    private function getExportPath(string $fileName): string 
    {
        $exportDir = __DIR__ . '/../../exports/';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        return $exportDir . $fileName;
    }
    
    private function generateDownloadUrl(string $filePath, string $exportId): string 
    {
        $fileName = basename($filePath);
        $token = $this->generateDownloadToken($fileName, $exportId);
        
        return "/api/export/download/{$fileName}?token={$token}";
    }
    
    private function generateDownloadToken(string $fileName, string $exportId): string 
    {
        $data = [
            'file' => $fileName,
            'export_id' => $exportId,
            'expires' => time() + (7 * 24 * 60 * 60), // 7 dias
            'hash' => hash('sha256', $fileName . $exportId . 'secret_key')
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
    
    private function log(string $message, string $level = 'INFO'): void 
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        // Log em arquivo
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Log no error_log também
        error_log("Export Processor [{$level}]: {$message}");
    }
}

/**
 * Script CLI para execução via cron
 */
if (php_sapi_name() === 'cli') {
    try {
        $processor = new BackgroundExportProcessor();
        $processor->processQueue();
        echo "Processamento da fila concluído com sucesso\n";
    } catch (Exception $e) {
        echo "Erro no processamento: " . $e->getMessage() . "\n";
        exit(1);
    }
}

/**
 * API endpoint para verificar status
 */
if (isset($_SERVER['REQUEST_METHOD'])) {
    apiMiddleware();
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
        try {
            $processor = new BackgroundExportProcessor();
            
            switch ($_GET['action']) {
                case 'status':
                    $db = getDatabase()->getConnection();
                    
                    $query = "
                        SELECT 
                            COUNT(*) as total_jobs,
                            SUM(CASE WHEN status = 'queued' THEN 1 ELSE 0 END) as queued,
                            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing,
                            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                        FROM export_jobs 
                        WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ";
                    
                    $stmt = $db->query($query);
                    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    apiSuccess()->setData([
                        'queue_status' => 'active',
                        'stats' => $stats,
                        'processor_running' => true,
                        'last_check' => date('c')
                    ])->send();
                    break;
                    
                case 'process':
                    // Trigger manual do processamento (apenas para admin)
                    $processor->processQueue();
                    apiSuccess()->setData(['message' => 'Processamento executado'])->send();
                    break;
                    
                default:
                    apiError('Ação não reconhecida', 400)->send();
            }
            
        } catch (Exception $e) {
            error_log("Background Processor API Error: " . $e->getMessage());
            apiError('Erro interno do servidor', 500)->send();
        }
    } else {
        apiError('Método não permitido', 405)->send();
    }
}