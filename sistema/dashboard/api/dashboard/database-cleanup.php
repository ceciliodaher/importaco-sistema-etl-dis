<?php
/**
 * ================================================================================
 * API DE LIMPEZA DO BANCO DE DADOS - SEGURA COM AUDITORIA
 * Sistema ETL DI's - Limpeza controlada com logs e validações
 * Funcões: Limpeza por período, DI específica, dados de teste, limpeza total
 * ================================================================================
 */

require_once dirname(__DIR__) . '/common/response.php';
require_once dirname(__DIR__) . '/common/validator.php';
require_once dirname(__DIR__, 3) . '/config/database.php';

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
    
    // Parâmetros obrigatórios
    $operation = $params['operation'] ?? '';
    $confirmation = $params['confirmation'] ?? '';
    
    // Validar operação
    $allowedOperations = ['cleanup_test', 'cleanup_period', 'cleanup_di', 'cleanup_all'];
    if (!in_array($operation, $allowedOperations)) {
        apiError('Operação inválida. Operações permitidas: ' . implode(', ', $allowedOperations), 400)->send();
    }
    
    // Inicializar banco e cleanup service
    $db = getDatabase();
    $cleanupService = new DatabaseCleanupService($db);
    
    // Executar operação baseada no tipo
    $result = executeCleanupOperation($cleanupService, $operation, $params, $confirmation);
    
    // Retornar sucesso
    apiSuccess()
        ->setData($result)
        ->addMeta('operation', $operation)
        ->addMeta('timestamp', date('Y-m-d H:i:s'))
        ->send();
    
} catch (Exception $e) {
    error_log("API Database Cleanup Error: " . $e->getMessage());
    apiError('Erro interno do servidor: ' . $e->getMessage(), 500)->send();
}

/**
 * Executar operação de limpeza baseada no tipo
 */
function executeCleanupOperation(DatabaseCleanupService $service, string $operation, array $params, string $confirmation): array 
{
    switch ($operation) {
        case 'cleanup_test':
            return $service->cleanupTestData($confirmation);
            
        case 'cleanup_period':
            $days = (int)($params['days'] ?? 0);
            if ($days < 1) {
                throw new Exception('Número de dias deve ser maior que 0');
            }
            return $service->cleanupByPeriod($days, $confirmation);
            
        case 'cleanup_di':
            $numeroDi = $params['numero_di'] ?? '';
            if (empty($numeroDi)) {
                throw new Exception('Número da DI é obrigatório');
            }
            return $service->cleanupSpecificDI($numeroDi, $confirmation);
            
        case 'cleanup_all':
            $doubleConfirmation = $params['double_confirmation'] ?? '';
            return $service->cleanupAll($confirmation, $doubleConfirmation);
            
        default:
            throw new Exception('Operação não implementada: ' . $operation);
    }
}

/**
 * Serviço de limpeza do banco de dados
 */
class DatabaseCleanupService 
{
    private $db;
    private $dryRun = false;
    private $auditLog = [];
    
    public function __construct($database) 
    {
        $this->db = $database;
    }
    
    /**
     * Limpar apenas dados de teste (prefixo TEST%)
     */
    public function cleanupTestData(string $confirmation): array 
    {
        if ($confirmation !== 'CONFIRM_CLEANUP_TEST') {
            throw new Exception('Confirmação inválida para limpeza de dados de teste');
        }
        
        $this->auditLog[] = "Iniciando limpeza de dados de teste - " . date('Y-m-d H:i:s');
        
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Contar registros antes da limpeza
            $counts = $this->countTestRecords();
            
            // Deletar em ordem de dependência (FK constraints)
            $deletedCounts = [];
            
            // 1. Impostos de adições de teste
            $deleted = $this->executeDelete("
                DELETE imp FROM impostos_adicao imp
                JOIN adicoes a ON imp.adicao_id = a.id
                WHERE a.numero_di LIKE 'TEST%'
            ");
            $deletedCounts['impostos_adicao'] = $deleted;
            
            // 2. Mercadorias de adições de teste
            $deleted = $this->executeDelete("
                DELETE m FROM mercadorias m
                JOIN adicoes a ON m.adicao_id = a.id
                WHERE a.numero_di LIKE 'TEST%'
            ");
            $deletedCounts['mercadorias'] = $deleted;
            
            // 3. Despesas de DIs de teste
            $deleted = $this->executeDelete("
                DELETE FROM despesas_extras 
                WHERE numero_di LIKE 'TEST%'
            ");
            $deletedCounts['despesas_extras'] = $deleted;
            
            // 4. Adições de teste
            $deleted = $this->executeDelete("
                DELETE FROM adicoes 
                WHERE numero_di LIKE 'TEST%'
            ");
            $deletedCounts['adicoes'] = $deleted;
            
            // 5. DIs de teste
            $deleted = $this->executeDelete("
                DELETE FROM declaracoes_importacao 
                WHERE numero_di LIKE 'TEST%'
            ");
            $deletedCounts['declaracoes_importacao'] = $deleted;
            
            // 6. Processamento XMLs de teste
            $deleted = $this->executeDelete("
                DELETE FROM processamento_xmls 
                WHERE numero_di LIKE 'TEST%'
            ");
            $deletedCounts['processamento_xmls'] = $deleted;
            
            $this->db->getConnection()->commit();
            
            $this->auditLog[] = "Limpeza de dados de teste concluída com sucesso";
            $this->writeAuditLog();
            
            return [
                'success' => true,
                'operation' => 'cleanup_test',
                'records_before' => $counts,
                'records_deleted' => $deletedCounts,
                'total_deleted' => array_sum($deletedCounts),
                'audit_log' => $this->auditLog
            ];
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollback();
            $this->auditLog[] = "ERRO na limpeza de dados de teste: " . $e->getMessage();
            $this->writeAuditLog();
            throw new Exception('Falha na limpeza de dados de teste: ' . $e->getMessage());
        }
    }
    
    /**
     * Limpar dados por período (mais antigos que X dias)
     */
    public function cleanupByPeriod(int $days, string $confirmation): array 
    {
        if ($confirmation !== 'CONFIRM_CLEANUP_PERIOD') {
            throw new Exception('Confirmação inválida para limpeza por período');
        }
        
        if ($days < 7) {
            throw new Exception('Não é permitido limpar dados com menos de 7 dias para segurança');
        }
        
        $cutoffDate = date('Y-m-d', strtotime("-{$days} days"));
        $this->auditLog[] = "Iniciando limpeza de dados anteriores a {$cutoffDate} - " . date('Y-m-d H:i:s');
        
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Contar registros antes da limpeza
            $counts = $this->countRecordsByPeriod($cutoffDate);
            
            if ($counts['declaracoes_importacao'] === 0) {
                $this->db->getConnection()->rollback();
                return [
                    'success' => true,
                    'operation' => 'cleanup_period',
                    'message' => 'Nenhum registro encontrado para o período especificado',
                    'cutoff_date' => $cutoffDate,
                    'records_deleted' => []
                ];
            }
            
            // Deletar em ordem de dependência
            $deletedCounts = [];
            
            // 1. Impostos de adições antigas
            $deleted = $this->executeDelete("
                DELETE imp FROM impostos_adicao imp
                JOIN adicoes a ON imp.adicao_id = a.id
                JOIN declaracoes_importacao di ON a.numero_di = di.numero_di
                WHERE di.data_registro < ?
            ", [$cutoffDate]);
            $deletedCounts['impostos_adicao'] = $deleted;
            
            // 2. Mercadorias de adições antigas
            $deleted = $this->executeDelete("
                DELETE m FROM mercadorias m
                JOIN adicoes a ON m.adicao_id = a.id
                JOIN declaracoes_importacao di ON a.numero_di = di.numero_di
                WHERE di.data_registro < ?
            ", [$cutoffDate]);
            $deletedCounts['mercadorias'] = $deleted;
            
            // 3. Despesas de DIs antigas
            $deleted = $this->executeDelete("
                DELETE de FROM despesas_extras de
                JOIN declaracoes_importacao di ON de.numero_di = di.numero_di
                WHERE di.data_registro < ?
            ", [$cutoffDate]);
            $deletedCounts['despesas_extras'] = $deleted;
            
            // 4. Adições antigas
            $deleted = $this->executeDelete("
                DELETE a FROM adicoes a
                JOIN declaracoes_importacao di ON a.numero_di = di.numero_di
                WHERE di.data_registro < ?
            ", [$cutoffDate]);
            $deletedCounts['adicoes'] = $deleted;
            
            // 5. DIs antigas
            $deleted = $this->executeDelete("
                DELETE FROM declaracoes_importacao 
                WHERE data_registro < ?
            ", [$cutoffDate]);
            $deletedCounts['declaracoes_importacao'] = $deleted;
            
            $this->db->getConnection()->commit();
            
            $this->auditLog[] = "Limpeza por período concluída com sucesso";
            $this->writeAuditLog();
            
            return [
                'success' => true,
                'operation' => 'cleanup_period',
                'cutoff_date' => $cutoffDate,
                'days' => $days,
                'records_before' => $counts,
                'records_deleted' => $deletedCounts,
                'total_deleted' => array_sum($deletedCounts),
                'audit_log' => $this->auditLog
            ];
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollback();
            $this->auditLog[] = "ERRO na limpeza por período: " . $e->getMessage();
            $this->writeAuditLog();
            throw new Exception('Falha na limpeza por período: ' . $e->getMessage());
        }
    }
    
    /**
     * Limpar DI específica
     */
    public function cleanupSpecificDI(string $numeroDi, string $confirmation): array 
    {
        if ($confirmation !== 'CONFIRM_CLEANUP_DI') {
            throw new Exception('Confirmação inválida para limpeza de DI específica');
        }
        
        // Validar formato da DI
        if (!preg_match('/^[0-9]{10}$/', $numeroDi)) {
            throw new Exception('Formato de número DI inválido');
        }
        
        $this->auditLog[] = "Iniciando limpeza da DI {$numeroDi} - " . date('Y-m-d H:i:s');
        
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Verificar se DI existe
            $diExists = $this->db->fetchOne("SELECT numero_di FROM declaracoes_importacao WHERE numero_di = ?", [$numeroDi]);
            if (!$diExists) {
                $this->db->getConnection()->rollback();
                return [
                    'success' => false,
                    'operation' => 'cleanup_di',
                    'message' => "DI {$numeroDi} não encontrada",
                    'numero_di' => $numeroDi
                ];
            }
            
            // Contar registros relacionados
            $counts = $this->countRecordsByDI($numeroDi);
            
            // Deletar em ordem de dependência
            $deletedCounts = [];
            
            // 1. Impostos da DI
            $deleted = $this->executeDelete("
                DELETE imp FROM impostos_adicao imp
                JOIN adicoes a ON imp.adicao_id = a.id
                WHERE a.numero_di = ?
            ", [$numeroDi]);
            $deletedCounts['impostos_adicao'] = $deleted;
            
            // 2. Mercadorias da DI
            $deleted = $this->executeDelete("
                DELETE m FROM mercadorias m
                JOIN adicoes a ON m.adicao_id = a.id
                WHERE a.numero_di = ?
            ", [$numeroDi]);
            $deletedCounts['mercadorias'] = $deleted;
            
            // 3. Despesas da DI
            $deleted = $this->executeDelete("
                DELETE FROM despesas_extras WHERE numero_di = ?
            ", [$numeroDi]);
            $deletedCounts['despesas_extras'] = $deleted;
            
            // 4. Adições da DI
            $deleted = $this->executeDelete("
                DELETE FROM adicoes WHERE numero_di = ?
            ", [$numeroDi]);
            $deletedCounts['adicoes'] = $deleted;
            
            // 5. DI principal
            $deleted = $this->executeDelete("
                DELETE FROM declaracoes_importacao WHERE numero_di = ?
            ", [$numeroDi]);
            $deletedCounts['declaracoes_importacao'] = $deleted;
            
            // 6. Processamento XML
            $deleted = $this->executeDelete("
                DELETE FROM processamento_xmls WHERE numero_di = ?
            ", [$numeroDi]);
            $deletedCounts['processamento_xmls'] = $deleted;
            
            $this->db->getConnection()->commit();
            
            $this->auditLog[] = "Limpeza da DI {$numeroDi} concluída com sucesso";
            $this->writeAuditLog();
            
            return [
                'success' => true,
                'operation' => 'cleanup_di',
                'numero_di' => $numeroDi,
                'records_before' => $counts,
                'records_deleted' => $deletedCounts,
                'total_deleted' => array_sum($deletedCounts),
                'audit_log' => $this->auditLog
            ];
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollback();
            $this->auditLog[] = "ERRO na limpeza da DI {$numeroDi}: " . $e->getMessage();
            $this->writeAuditLog();
            throw new Exception("Falha na limpeza da DI {$numeroDi}: " . $e->getMessage());
        }
    }
    
    /**
     * Limpeza total (PERIGOSO - requer dupla confirmação)
     */
    public function cleanupAll(string $confirmation, string $doubleConfirmation): array 
    {
        if ($confirmation !== 'CONFIRM_CLEANUP_ALL') {
            throw new Exception('Confirmação inválida para limpeza total');
        }
        
        if ($doubleConfirmation !== 'I_UNDERSTAND_THIS_DELETES_ALL_DATA') {
            throw new Exception('Dupla confirmação inválida. Esta operação deletará TODOS os dados!');
        }
        
        $this->auditLog[] = "INICIANDO LIMPEZA TOTAL - TODOS OS DADOS SERÃO DELETADOS - " . date('Y-m-d H:i:s');
        $this->auditLog[] = "ATENÇÃO: OPERAÇÃO IRREVERSÍVEL!";
        
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Contar todos os registros antes da limpeza
            $counts = $this->countAllRecords();
            
            // Deletar tudo em ordem de dependência
            $deletedCounts = [];
            
            // 1. Impostos de todas as adições
            $deleted = $this->executeDelete("DELETE FROM impostos_adicao");
            $deletedCounts['impostos_adicao'] = $deleted;
            
            // 2. Mercadorias de todas as adições
            $deleted = $this->executeDelete("DELETE FROM mercadorias");
            $deletedCounts['mercadorias'] = $deleted;
            
            // 3. Todas as despesas
            $deleted = $this->executeDelete("DELETE FROM despesas_extras");
            $deletedCounts['despesas_extras'] = $deleted;
            
            // 4. Todas as adições
            $deleted = $this->executeDelete("DELETE FROM adicoes");
            $deletedCounts['adicoes'] = $deleted;
            
            // 5. Todas as DIs
            $deleted = $this->executeDelete("DELETE FROM declaracoes_importacao");
            $deletedCounts['declaracoes_importacao'] = $deleted;
            
            // 6. Todos os processamentos XML
            $deleted = $this->executeDelete("DELETE FROM processamento_xmls");
            $deletedCounts['processamento_xmls'] = $deleted;
            
            // 7. Reset AUTO_INCREMENT
            $this->executeDelete("ALTER TABLE adicoes AUTO_INCREMENT = 1");
            $this->executeDelete("ALTER TABLE mercadorias AUTO_INCREMENT = 1");
            $this->executeDelete("ALTER TABLE impostos_adicao AUTO_INCREMENT = 1");
            $this->executeDelete("ALTER TABLE despesas_extras AUTO_INCREMENT = 1");
            
            $this->db->getConnection()->commit();
            
            $this->auditLog[] = "LIMPEZA TOTAL CONCLUÍDA - TODOS OS DADOS FORAM DELETADOS";
            $this->writeAuditLog();
            
            return [
                'success' => true,
                'operation' => 'cleanup_all',
                'warning' => 'TODOS OS DADOS FORAM DELETADOS',
                'records_before' => $counts,
                'records_deleted' => $deletedCounts,
                'total_deleted' => array_sum($deletedCounts),
                'audit_log' => $this->auditLog
            ];
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollback();
            $this->auditLog[] = "ERRO CRÍTICO na limpeza total: " . $e->getMessage();
            $this->writeAuditLog();
            throw new Exception('ERRO CRÍTICO na limpeza total: ' . $e->getMessage());
        }
    }
    
    /**
     * Executar DELETE e retornar número de registros afetados
     */
    private function executeDelete(string $sql, array $params = []): int 
    {
        $stmt = $this->db->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }
    
    /**
     * Contar registros de teste
     */
    private function countTestRecords(): array 
    {
        return [
            'declaracoes_importacao' => $this->db->fetchOne("SELECT COUNT(*) as count FROM declaracoes_importacao WHERE numero_di LIKE 'TEST%'")['count'],
            'adicoes' => $this->db->fetchOne("SELECT COUNT(*) as count FROM adicoes WHERE numero_di LIKE 'TEST%'")['count'],
            'impostos_adicao' => $this->db->fetchOne("SELECT COUNT(*) as count FROM impostos_adicao imp JOIN adicoes a ON imp.adicao_id = a.id WHERE a.numero_di LIKE 'TEST%'")['count'],
            'mercadorias' => $this->db->fetchOne("SELECT COUNT(*) as count FROM mercadorias m JOIN adicoes a ON m.adicao_id = a.id WHERE a.numero_di LIKE 'TEST%'")['count'],
            'despesas_extras' => $this->db->fetchOne("SELECT COUNT(*) as count FROM despesas_extras WHERE numero_di LIKE 'TEST%'")['count']
        ];
    }
    
    /**
     * Contar registros por período
     */
    private function countRecordsByPeriod(string $cutoffDate): array 
    {
        return [
            'declaracoes_importacao' => $this->db->fetchOne("SELECT COUNT(*) as count FROM declaracoes_importacao WHERE data_registro < ?", [$cutoffDate])['count'],
            'adicoes' => $this->db->fetchOne("SELECT COUNT(*) as count FROM adicoes a JOIN declaracoes_importacao di ON a.numero_di = di.numero_di WHERE di.data_registro < ?", [$cutoffDate])['count']
        ];
    }
    
    /**
     * Contar registros por DI específica
     */
    private function countRecordsByDI(string $numeroDi): array 
    {
        return [
            'declaracoes_importacao' => 1,
            'adicoes' => $this->db->fetchOne("SELECT COUNT(*) as count FROM adicoes WHERE numero_di = ?", [$numeroDi])['count'],
            'impostos_adicao' => $this->db->fetchOne("SELECT COUNT(*) as count FROM impostos_adicao imp JOIN adicoes a ON imp.adicao_id = a.id WHERE a.numero_di = ?", [$numeroDi])['count'],
            'mercadorias' => $this->db->fetchOne("SELECT COUNT(*) as count FROM mercadorias m JOIN adicoes a ON m.adicao_id = a.id WHERE a.numero_di = ?", [$numeroDi])['count'],
            'despesas_extras' => $this->db->fetchOne("SELECT COUNT(*) as count FROM despesas_extras WHERE numero_di = ?", [$numeroDi])['count']
        ];
    }
    
    /**
     * Contar todos os registros
     */
    private function countAllRecords(): array 
    {
        return [
            'declaracoes_importacao' => $this->db->fetchOne("SELECT COUNT(*) as count FROM declaracoes_importacao")['count'],
            'adicoes' => $this->db->fetchOne("SELECT COUNT(*) as count FROM adicoes")['count'],
            'impostos_adicao' => $this->db->fetchOne("SELECT COUNT(*) as count FROM impostos_adicao")['count'],
            'mercadorias' => $this->db->fetchOne("SELECT COUNT(*) as count FROM mercadorias")['count'],
            'despesas_extras' => $this->db->fetchOne("SELECT COUNT(*) as count FROM despesas_extras")['count'],
            'processamento_xmls' => $this->db->fetchOne("SELECT COUNT(*) as count FROM processamento_xmls")['count']
        ];
    }
    
    /**
     * Escrever log de auditoria
     */
    private function writeAuditLog(): void 
    {
        $logContent = "=== DATABASE CLEANUP AUDIT LOG ===\n";
        $logContent .= implode("\n", $this->auditLog) . "\n";
        $logContent .= "=== END AUDIT LOG ===\n\n";
        
        $logFile = __DIR__ . '/../../../data/logs/database_cleanup_' . date('Y-m-d') . '.log';
        
        // Criar diretório se não existir
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
    }
}