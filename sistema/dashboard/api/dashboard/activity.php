<?php
/**
 * ================================================================================
 * API DE ATIVIDADES RECENTES DO DASHBOARD
 * Retorna log de atividades do sistema ETL de DI's
 * ================================================================================
 */

require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__) . '/common/response.php';

// Configurar middleware de segurança
apiMiddleware();

try {
    // Conectar ao banco
    $db = getDatabase();
    
    if (!$db->isDatabaseReady()) {
        throw new RuntimeException('Banco de dados não está configurado corretamente');
    }

    // Parâmetros de entrada
    $limit = (int)($_GET['limit'] ?? 50);
    $page = (int)($_GET['page'] ?? 1);
    $type = $_GET['type'] ?? null; // upload, calculation, export, error
    
    // Validar parâmetros
    $validator = new ApiValidator();
    $params = ['limit' => $limit, 'page' => $page];
    
    if (!$validator->ranges($params, [
        'limit' => ['min' => 1, 'max' => 100],
        'page' => ['min' => 1, 'max' => 1000]
    ])) {
        throw new InvalidArgumentException(implode(', ', $validator->getErrors()));
    }

    $offset = ($page - 1) * $limit;

    // Query base para atividades
    $baseQuery = "
        SELECT 
            'di_processing' as activity_type,
            CONCAT('DI ', numero_di, ' processada') as title,
            CONCAT('Status: ', status_processamento, ' | Valor: R$ ', FORMAT(valor_total_reais, 2)) as description,
            created_at as timestamp,
            'success' as status,
            JSON_OBJECT(
                'di_numero', numero_di,
                'valor_total', valor_total_reais,
                'status', status_processamento,
                'adicoes_count', (
                    SELECT COUNT(*) 
                    FROM adicoes 
                    WHERE di_id = declaracoes_importacao.id
                )
            ) as metadata
        FROM declaracoes_importacao
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
            'calculation' as activity_type,
            CONCAT('Cálculo de impostos realizado') as title,
            CONCAT('II: R$ ', FORMAT(valor_ii, 2), ' | IPI: R$ ', FORMAT(valor_ipi, 2)) as description,
            created_at as timestamp,
            'success' as status,
            JSON_OBJECT(
                'adicao_id', adicao_id,
                'valor_ii', valor_ii,
                'valor_ipi', valor_ipi,
                'valor_pis', valor_pis,
                'valor_cofins', valor_cofins
            ) as metadata
        FROM impostos_adicao
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
            'system' as activity_type,
            'Sistema iniciado' as title,
            'Dashboard acessado com sucesso' as description,
            NOW() as timestamp,
            'info' as status,
            JSON_OBJECT('source', 'dashboard_api') as metadata
    ";

    // Filtro por tipo se especificado
    $whereClause = "";
    $params = [];
    
    if ($type && in_array($type, ['upload', 'calculation', 'export', 'error', 'system'])) {
        $typeMapping = [
            'upload' => 'di_processing',
            'calculation' => 'calculation', 
            'export' => 'export',
            'error' => 'error',
            'system' => 'system'
        ];
        $whereClause = " WHERE activity_type = ?";
        $params[] = $typeMapping[$type];
    }

    // Query final com paginação
    $sql = "
        SELECT * FROM ({$baseQuery}) as activities 
        {$whereClause}
        ORDER BY timestamp DESC 
        LIMIT ? OFFSET ?
    ";
    
    $params[] = $limit;
    $params[] = $offset;

    // Executar query
    $stmt = $db->query($sql, $params);
    $activities = $stmt->fetchAll();

    // Query para total de registros
    $countSql = "SELECT COUNT(*) as total FROM ({$baseQuery}) as activities {$whereClause}";
    $countParams = array_slice($params, 0, -2); // Remove limit e offset
    $totalStmt = $db->query($countSql, $countParams);
    $total = $totalStmt->fetch()['total'];

    // Formatar atividades
    $formattedActivities = array_map(function($activity) {
        return [
            'id' => uniqid('act_'),
            'type' => $activity['activity_type'],
            'title' => $activity['title'],
            'description' => $activity['description'],
            'timestamp' => $activity['timestamp'],
            'status' => $activity['status'],
            'metadata' => json_decode($activity['metadata'], true),
            'time_ago' => timeAgo($activity['timestamp'])
        ];
    }, $activities);

    // Adicionar algumas atividades do sistema se lista estiver vazia
    if (empty($formattedActivities)) {
        $formattedActivities = [
            [
                'id' => uniqid('act_'),
                'type' => 'system',
                'title' => 'Sistema Inicializado',
                'description' => 'Dashboard de importação ETL iniciado com sucesso',
                'timestamp' => date('Y-m-d H:i:s'),
                'status' => 'success',
                'metadata' => ['source' => 'system_startup'],
                'time_ago' => 'Agora'
            ],
            [
                'id' => uniqid('act_'),
                'type' => 'info',
                'title' => 'Banco de Dados Conectado',
                'description' => 'Conexão com MySQL ServBay estabelecida na porta 3307',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-5 minutes')),
                'status' => 'success',
                'metadata' => ['host' => 'localhost:3307', 'database' => 'importaco_etl_dis'],
                'time_ago' => '5 minutos atrás'
            ]
        ];
        $total = 2;
    }

    // Preparar response
    $response = apiSuccess([
        'activities' => $formattedActivities,
        'summary' => [
            'total_today' => getTodayActivityCount($db),
            'types' => getActivityTypeStats($db),
            'last_updated' => date('c')
        ]
    ]);

    $response->setPagination($page, $limit, $total);
    $response->addMeta('cache_enabled', false);
    $response->addMeta('data_source', 'database');
    
    $response->send();

} catch (InvalidArgumentException $e) {
    apiError($e->getMessage(), 400)->send();
} catch (RuntimeException $e) {
    apiError($e->getMessage(), 503)->send();
} catch (Exception $e) {
    error_log("Activity API Error: " . $e->getMessage());
    apiError('Erro interno ao buscar atividades', 500)->send();
}

/**
 * Calcular tempo decorrido de forma amigável
 */
function timeAgo($datetime): string 
{
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Agora';
    if ($time < 3600) return floor($time/60) . ' min atrás';
    if ($time < 86400) return floor($time/3600) . ' h atrás';
    if ($time < 2592000) return floor($time/86400) . ' dias atrás';
    
    return date('d/m/Y', strtotime($datetime));
}

/**
 * Contar atividades de hoje
 */
function getTodayActivityCount(Database $db): int 
{
    try {
        $sql = "
            SELECT COUNT(*) as count FROM (
                SELECT id FROM declaracoes_importacao WHERE DATE(created_at) = CURDATE()
                UNION ALL
                SELECT id FROM impostos_adicao WHERE DATE(created_at) = CURDATE()
            ) as today_activities
        ";
        
        $stmt = $db->query($sql);
        return (int)$stmt->fetch()['count'];
    } catch (Exception $e) {
        return 0;
    }
}

/**
 * Estatísticas por tipo de atividade
 */
function getActivityTypeStats(Database $db): array 
{
    try {
        $stats = [
            'di_processing' => 0,
            'calculation' => 0,
            'system' => 1
        ];

        // DIs processadas nos últimos 7 dias
        $sql = "SELECT COUNT(*) as count FROM declaracoes_importacao WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $db->query($sql);
        $stats['di_processing'] = (int)$stmt->fetch()['count'];

        // Cálculos realizados nos últimos 7 dias
        $sql = "SELECT COUNT(*) as count FROM impostos_adicao WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $stmt = $db->query($sql);
        $stats['calculation'] = (int)$stmt->fetch()['count'];

        return $stats;
    } catch (Exception $e) {
        return ['di_processing' => 0, 'calculation' => 0, 'system' => 1];
    }
}