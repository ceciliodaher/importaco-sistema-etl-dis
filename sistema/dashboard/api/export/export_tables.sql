-- ================================================================================
-- TABELAS PARA SISTEMA DE EXPORTAÇÃO ENTERPRISE
-- Sistema ETL DI's - Suporte a processamento assíncrono e auditoria
-- ================================================================================

-- Tabela para jobs de exportação
CREATE TABLE IF NOT EXISTS export_jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    export_id VARCHAR(64) NOT NULL UNIQUE,
    status ENUM('queued', 'processing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'queued',
    progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
    status_message TEXT,
    
    -- Parâmetros da exportação
    export_type VARCHAR(50) NOT NULL,
    export_format VARCHAR(10) NOT NULL,
    template_name VARCHAR(50) DEFAULT 'default',
    parameters JSON,
    
    -- Metadados do resultado
    metadata JSON,
    file_path VARCHAR(512),
    file_size BIGINT UNSIGNED,
    download_url VARCHAR(512),
    
    -- Controle de downloads
    download_count INT UNSIGNED DEFAULT 0,
    last_downloaded_at TIMESTAMP NULL,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    -- Índices
    INDEX idx_status (status),
    INDEX idx_export_type (export_type),
    INDEX idx_created_at (created_at),
    INDEX idx_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para logs de download
CREATE TABLE IF NOT EXISTS download_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    export_id VARCHAR(64) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size BIGINT UNSIGNED,
    
    -- Informações do cliente
    client_ip VARCHAR(45) NOT NULL,
    user_agent TEXT,
    referer VARCHAR(512),
    
    -- Timestamp
    downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_export_id (export_id),
    INDEX idx_client_ip (client_ip),
    INDEX idx_downloaded_at (downloaded_at),
    INDEX idx_client_downloaded (client_ip, downloaded_at),
    
    -- Chave estrangeira
    FOREIGN KEY (export_id) REFERENCES export_jobs(export_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para templates de exportação
CREATE TABLE IF NOT EXISTS export_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    format ENUM('json', 'pdf', 'xlsx') NOT NULL,
    template_id VARCHAR(50) NOT NULL,
    description TEXT,
    config JSON NOT NULL,
    
    -- Controle de versão
    version VARCHAR(20) DEFAULT '1.0.0',
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    
    -- Metadados
    created_by VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    UNIQUE KEY unique_template (format, template_id),
    INDEX idx_format (format),
    INDEX idx_active (is_active),
    INDEX idx_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabela para estatísticas de uso
CREATE TABLE IF NOT EXISTS export_usage_stats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    date_period DATE NOT NULL,
    export_type VARCHAR(50) NOT NULL,
    export_format VARCHAR(10) NOT NULL,
    
    -- Contadores
    total_exports INT UNSIGNED DEFAULT 0,
    successful_exports INT UNSIGNED DEFAULT 0,
    failed_exports INT UNSIGNED DEFAULT 0,
    total_downloads INT UNSIGNED DEFAULT 0,
    
    -- Métricas de performance
    avg_processing_time_seconds DECIMAL(10,2),
    avg_file_size_mb DECIMAL(10,2),
    max_file_size_mb DECIMAL(10,2),
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Índices
    UNIQUE KEY unique_period_type_format (date_period, export_type, export_format),
    INDEX idx_date_period (date_period),
    INDEX idx_export_type (export_type),
    INDEX idx_export_format (export_format)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================================
-- VIEWS PARA RELATÓRIOS E DASHBOARDS
-- ================================================================================

-- View para estatísticas gerais de exportação
CREATE OR REPLACE VIEW v_export_statistics AS
SELECT 
    DATE(created_at) as export_date,
    export_type,
    export_format,
    status,
    COUNT(*) as total_jobs,
    
    -- Métricas de tempo
    AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_processing_seconds,
    MIN(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as min_processing_seconds,
    MAX(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as max_processing_seconds,
    
    -- Métricas de arquivo
    AVG(file_size / 1048576) as avg_file_size_mb,
    SUM(file_size / 1048576) as total_file_size_mb,
    SUM(download_count) as total_downloads,
    
    -- Taxa de sucesso
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*) * 100 as success_rate
    
FROM export_jobs
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at), export_type, export_format, status
ORDER BY export_date DESC, export_type, export_format;

-- View para jobs ativos
CREATE OR REPLACE VIEW v_active_export_jobs AS
SELECT 
    export_id,
    export_type,
    export_format,
    status,
    progress,
    status_message,
    created_at,
    updated_at,
    TIMESTAMPDIFF(SECOND, created_at, NOW()) as elapsed_seconds,
    
    -- Estimativa de tempo restante baseada no progresso
    CASE 
        WHEN progress > 0 AND progress < 100 THEN
            ROUND((TIMESTAMPDIFF(SECOND, created_at, NOW()) / progress) * (100 - progress))
        ELSE NULL
    END as estimated_remaining_seconds
    
FROM export_jobs
WHERE status IN ('queued', 'processing')
ORDER BY created_at ASC;

-- View para histórico de downloads
CREATE OR REPLACE VIEW v_download_history AS
SELECT 
    ej.export_id,
    ej.export_type,
    ej.export_format,
    ej.file_path,
    ej.file_size,
    dl.file_name,
    dl.client_ip,
    dl.downloaded_at,
    
    -- Informações agregadas
    COUNT(*) OVER (PARTITION BY ej.export_id) as total_downloads_for_export,
    COUNT(*) OVER (PARTITION BY dl.client_ip, DATE(dl.downloaded_at)) as daily_downloads_by_ip
    
FROM export_jobs ej
JOIN download_logs dl ON ej.export_id = dl.export_id
ORDER BY dl.downloaded_at DESC;

-- ================================================================================
-- STORED PROCEDURES PARA MANUTENÇÃO
-- ================================================================================

DELIMITER $$

-- Procedure para limpeza automática de jobs antigos
CREATE PROCEDURE CleanupOldExportJobs(IN retention_days INT DEFAULT 7)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;
    
    START TRANSACTION;
    
    -- Remover logs de download de jobs que serão removidos
    DELETE dl FROM download_logs dl
    JOIN export_jobs ej ON dl.export_id = ej.export_id
    WHERE ej.created_at < DATE_SUB(NOW(), INTERVAL retention_days DAY)
    AND ej.status IN ('completed', 'failed', 'cancelled');
    
    -- Remover jobs antigos
    DELETE FROM export_jobs
    WHERE created_at < DATE_SUB(NOW(), INTERVAL retention_days DAY)
    AND status IN ('completed', 'failed', 'cancelled');
    
    COMMIT;
    
    SELECT ROW_COUNT() as jobs_cleaned;
END$$

-- Procedure para atualizar estatísticas de uso
CREATE PROCEDURE UpdateExportUsageStats(IN target_date DATE DEFAULT NULL)
BEGIN
    DECLARE target_period DATE DEFAULT COALESCE(target_date, CURDATE());
    
    INSERT INTO export_usage_stats (
        date_period, export_type, export_format,
        total_exports, successful_exports, failed_exports, total_downloads,
        avg_processing_time_seconds, avg_file_size_mb, max_file_size_mb
    )
    SELECT 
        target_period,
        export_type,
        export_format,
        COUNT(*) as total_exports,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful_exports,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_exports,
        SUM(COALESCE(download_count, 0)) as total_downloads,
        AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_processing_time_seconds,
        AVG(file_size / 1048576) as avg_file_size_mb,
        MAX(file_size / 1048576) as max_file_size_mb
        
    FROM export_jobs
    WHERE DATE(created_at) = target_period
    GROUP BY export_type, export_format
    
    ON DUPLICATE KEY UPDATE
        total_exports = VALUES(total_exports),
        successful_exports = VALUES(successful_exports),
        failed_exports = VALUES(failed_exports),
        total_downloads = VALUES(total_downloads),
        avg_processing_time_seconds = VALUES(avg_processing_time_seconds),
        avg_file_size_mb = VALUES(avg_file_size_mb),
        max_file_size_mb = VALUES(max_file_size_mb),
        updated_at = CURRENT_TIMESTAMP;
        
    SELECT ROW_COUNT() as stats_updated;
END$$

DELIMITER ;

-- ================================================================================
-- EVENTOS PARA MANUTENÇÃO AUTOMÁTICA
-- ================================================================================

-- Evento para limpeza diária de jobs antigos
CREATE EVENT IF NOT EXISTS evt_cleanup_export_jobs
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE()) + INTERVAL 2 HOUR
DO
    CALL CleanupOldExportJobs(7);

-- Evento para atualização diária de estatísticas
CREATE EVENT IF NOT EXISTS evt_update_export_stats
ON SCHEDULE EVERY 1 DAY
STARTS TIMESTAMP(CURDATE()) + INTERVAL 1 HOUR
DO
    CALL UpdateExportUsageStats(CURDATE() - INTERVAL 1 DAY);

-- ================================================================================
-- DADOS INICIAIS - TEMPLATES PADRÃO
-- ================================================================================

INSERT IGNORE INTO export_templates (name, format, template_id, description, config, is_default) VALUES
('Padrão JSON', 'json', 'default', 'Template padrão para exportação JSON estruturada', 
 '{"pretty_print": true, "include_metadata": true, "compression": "none"}', TRUE),

('Resumo Executivo PDF', 'pdf', 'executive_summary', 'Template condensado para resumos executivos', 
 '{"orientation": "portrait", "sections": {"detailed_analysis": {"enabled": false}}}', FALSE),

('Relatório Completo PDF', 'pdf', 'default', 'Template completo para relatórios executivos PDF', 
 '{"orientation": "portrait", "sections": {"cover": {"enabled": true}}}', TRUE),

('Planilha Padrão Excel', 'xlsx', 'default', 'Template padrão para planilhas Excel completas', 
 '{"sheets": {"dashboard": {"enabled": true}}, "conditional_formatting": true}', TRUE),

('Análise Financeira Excel', 'xlsx', 'financial_analysis', 'Template especializado para análises financeiras', 
 '{"sheets": {"financial_summary": {"enabled": true}}, "charts": {"enabled": true}}', FALSE);

-- ================================================================================
-- ÍNDICES ADICIONAIS PARA PERFORMANCE
-- ================================================================================

-- Índices compostos para queries comuns
CREATE INDEX idx_export_jobs_type_status_date ON export_jobs (export_type, status, created_at);
CREATE INDEX idx_export_jobs_format_completed ON export_jobs (export_format, completed_at);
CREATE INDEX idx_download_logs_date_ip ON download_logs (downloaded_at, client_ip);

-- Índices para estatísticas
CREATE INDEX idx_export_jobs_stats ON export_jobs (created_at, export_type, export_format, status);

-- ================================================================================
-- TRIGGERS PARA AUDITORIA E CONTROLE
-- ================================================================================

DELIMITER $$

-- Trigger para atualizar timestamp de completed_at
CREATE TRIGGER tr_export_jobs_completed
    BEFORE UPDATE ON export_jobs
    FOR EACH ROW
BEGIN
    IF OLD.status != 'completed' AND NEW.status = 'completed' THEN
        SET NEW.completed_at = CURRENT_TIMESTAMP;
    END IF;
END$$

-- Trigger para validar progresso
CREATE TRIGGER tr_export_jobs_progress_validation
    BEFORE UPDATE ON export_jobs
    FOR EACH ROW
BEGIN
    IF NEW.progress < 0 OR NEW.progress > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Progress must be between 0 and 100';
    END IF;
END$$

DELIMITER ;

-- ================================================================================
-- GRANTS E PERMISSÕES (para usuário da aplicação)
-- ================================================================================

-- Criar usuário específico para exportações (se necessário)
-- CREATE USER 'export_user'@'localhost' IDENTIFIED BY 'secure_password';

-- Conceder permissões específicas
-- GRANT SELECT, INSERT, UPDATE, DELETE ON importaco_etl_dis.export_jobs TO 'export_user'@'localhost';
-- GRANT SELECT, INSERT, DELETE ON importaco_etl_dis.download_logs TO 'export_user'@'localhost';
-- GRANT SELECT, INSERT, UPDATE ON importaco_etl_dis.export_usage_stats TO 'export_user'@'localhost';
-- GRANT SELECT ON importaco_etl_dis.export_templates TO 'export_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE importaco_etl_dis.CleanupOldExportJobs TO 'export_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE importaco_etl_dis.UpdateExportUsageStats TO 'export_user'@'localhost';

-- ================================================================================
-- VERIFICAÇÕES FINAIS
-- ================================================================================

-- Verificar se as tabelas foram criadas
SELECT 
    TABLE_NAME,
    TABLE_ROWS,
    DATA_LENGTH,
    INDEX_LENGTH,
    CREATE_TIME
FROM INFORMATION_SCHEMA.TABLES 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME LIKE 'export_%'
ORDER BY TABLE_NAME;

-- Verificar se os índices foram criados
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX,
    INDEX_TYPE
FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME LIKE 'export_%'
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Verificar eventos
SELECT 
    EVENT_NAME,
    EVENT_DEFINITION,
    STATUS,
    STARTS,
    INTERVAL_VALUE,
    INTERVAL_FIELD
FROM INFORMATION_SCHEMA.EVENTS 
WHERE EVENT_SCHEMA = DATABASE() 
AND EVENT_NAME LIKE 'evt_%export%';

-- ================================================================================
-- QUERIES DE EXEMPLO PARA MONITORAMENTO
-- ================================================================================

-- Monitorar jobs ativos
-- SELECT * FROM v_active_export_jobs;

-- Estatísticas de hoje
-- SELECT * FROM v_export_statistics WHERE export_date = CURDATE();

-- Top downloads dos últimos 7 dias
-- SELECT export_type, export_format, SUM(total_downloads) as downloads
-- FROM v_export_statistics 
-- WHERE export_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
-- GROUP BY export_type, export_format
-- ORDER BY downloads DESC;

-- Jobs que falharam nas últimas 24 horas
-- SELECT export_id, export_type, status_message, created_at
-- FROM export_jobs 
-- WHERE status = 'failed' 
-- AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR);

-- ================================================================================
-- FIM DO SCRIPT
-- ================================================================================