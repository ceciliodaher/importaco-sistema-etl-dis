#!/bin/bash

# ================================================================================
# SISTEMA ETL DE DI's - SCRIPT DE INSTALAÇÃO AUTOMATIZADA
# Script para instalar completamente o banco de dados MySQL
# Versão: 1.0.0
# ================================================================================

set -e  # Parar em caso de erro

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configurações
DB_HOST="localhost"
DB_PORT="3307"
DB_NAME="importaco_etl_dis"
DB_USER="root"
DB_PASS="ServBay.dev"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

# ================================================================================
# FUNÇÕES AUXILIARES
# ================================================================================

print_header() {
    echo -e "${BLUE}"
    echo "================================================================================"
    echo "  SISTEMA ETL DE DI's - INSTALAÇÃO DO BANCO DE DADOS"
    echo "  Padrão Expertzy: Energia • Segurança • Transparência"
    echo "================================================================================"
    echo -e "${NC}"
}

print_step() {
    echo -e "${YELLOW}[STEP]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[OK]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

# Verificar se comando existe
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Verificar se MySQL está rodando
check_mysql_connection() {
    print_step "Verificando conexão MySQL..."
    
    if ! command_exists mysql; then
        print_error "MySQL client não encontrado. Instale o MySQL client primeiro."
        exit 1
    fi

    # Testar conexão
    if mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "SELECT 1;" >/dev/null 2>&1; then
        print_success "Conexão MySQL OK (${DB_HOST}:${DB_PORT})"
        return 0
    else
        print_error "Não foi possível conectar ao MySQL"
        print_info "Host: ${DB_HOST}, Port: ${DB_PORT}, User: ${DB_USER}"
        print_info "Verifique se o ServBay está rodando e as credenciais estão corretas"
        exit 1
    fi
}

# Executar arquivo SQL
execute_sql_file() {
    local file="$1"
    local description="$2"
    
    if [ ! -f "$file" ]; then
        print_error "Arquivo não encontrado: $file"
        return 1
    fi

    print_step "Executando: $description"
    
    if mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" < "$file"; then
        print_success "$description - Concluído"
        return 0
    else
        print_error "Falha ao executar: $description"
        return 1
    fi
}

# Verificar se arquivo existe
check_file() {
    local file="$1"
    if [ ! -f "$file" ]; then
        print_error "Arquivo obrigatório não encontrado: $file"
        exit 1
    fi
}

# ================================================================================
# FUNÇÃO PRINCIPAL DE INSTALAÇÃO
# ================================================================================

install_database() {
    print_header
    
    # Verificar arquivos necessários
    print_step "Verificando arquivos necessários..."
    check_file "$SCRIPT_DIR/01_schema.sql"
    check_file "$SCRIPT_DIR/02_functions.sql"
    check_file "$SCRIPT_DIR/03_triggers.sql"
    check_file "$SCRIPT_DIR/04_views.sql"
    check_file "$SCRIPT_DIR/05_indexes.sql"
    check_file "$SCRIPT_DIR/06_initial_data.sql"
    print_success "Todos os arquivos SQL encontrados"

    # Verificar conexão
    check_mysql_connection

    # Executar scripts na ordem correta
    print_step "Iniciando instalação do banco de dados..."
    
    execute_sql_file "$SCRIPT_DIR/01_schema.sql" "1. Schema principal (tabelas e constraints)"
    execute_sql_file "$SCRIPT_DIR/02_functions.sql" "2. Funções de conversão e validação"
    execute_sql_file "$SCRIPT_DIR/03_triggers.sql" "3. Triggers de auditoria"
    execute_sql_file "$SCRIPT_DIR/04_views.sql" "4. Views consolidadas"
    execute_sql_file "$SCRIPT_DIR/05_indexes.sql" "5. Índices e otimizações"
    execute_sql_file "$SCRIPT_DIR/06_initial_data.sql" "6. Dados iniciais mínimos"

    print_success "Instalação concluída com sucesso!"
    
    # Verificar instalação
    verify_installation
}

# ================================================================================
# VERIFICAR INSTALAÇÃO
# ================================================================================

verify_installation() {
    print_step "Verificando instalação..."

    # Verificar tabelas
    local table_count=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -sN -e "
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = '$DB_NAME'
    " 2>/dev/null || echo "0")

    if [ "$table_count" -ge 12 ]; then
        print_success "Tabelas criadas: $table_count"
    else
        print_error "Número insuficiente de tabelas: $table_count (esperado: 13+)"
    fi

    # Verificar funções
    local function_count=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -sN -e "
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.ROUTINES 
        WHERE ROUTINE_SCHEMA = '$DB_NAME' AND ROUTINE_TYPE = 'FUNCTION'
    " 2>/dev/null || echo "0")

    if [ "$function_count" -ge 8 ]; then
        print_success "Funções criadas: $function_count"
    else
        print_error "Número insuficiente de funções: $function_count (esperado: 8+)"
    fi

    # Verificar triggers
    local trigger_count=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -sN -e "
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.TRIGGERS 
        WHERE TRIGGER_SCHEMA = '$DB_NAME'
    " 2>/dev/null || echo "0")

    if [ "$trigger_count" -ge 8 ]; then
        print_success "Triggers criados: $trigger_count"
    else
        print_error "Número insuficiente de triggers: $trigger_count (esperado: 8+)"
    fi

    # Verificar views
    local view_count=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -sN -e "
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.VIEWS 
        WHERE TABLE_SCHEMA = '$DB_NAME'
    " 2>/dev/null || echo "0")

    if [ "$view_count" -ge 6 ]; then
        print_success "Views criadas: $view_count"
    else
        print_error "Número insuficiente de views: $view_count (esperado: 6+)"
    fi

    # Teste funcional básico
    print_step "Executando teste funcional..."
    local test_result=$(mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -sN -e "
        SELECT fn_convert_siscomex_money('000000001000000')
    " 2>/dev/null || echo "ERROR")

    if [ "$test_result" = "10000.00" ]; then
        print_success "Teste de função conversão: OK"
    else
        print_error "Teste de função conversão falhou: $test_result"
    fi

    # Status final
    print_step "Status final do sistema..."
    mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -e "
        SELECT * FROM v_sistema_status;
    " 2>/dev/null || print_error "Não foi possível consultar status"

    echo
    print_success "✅ SISTEMA PRONTO PARA USO!"
    print_info "Banco: $DB_NAME"
    print_info "Host: $DB_HOST:$DB_PORT"
    print_info "Para testar: cd ../../../config && php -r \"require 'database.php'; var_dump(getDatabase()->testConnection());\""
}

# ================================================================================
# FUNÇÕES DE MANUTENÇÃO
# ================================================================================

reset_database() {
    print_step "⚠️  RESETANDO BANCO DE DADOS..."
    read -p "Tem certeza? Esta ação é irreversível! (yes/no): " confirm
    
    if [ "$confirm" = "yes" ]; then
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -e "
            DROP DATABASE IF EXISTS $DB_NAME;
            CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        "
        print_success "Banco resetado. Execute install para reinstalar."
    else
        print_info "Operação cancelada."
    fi
}

backup_database() {
    print_step "Fazendo backup do banco de dados..."
    
    local backup_file="backup_${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql"
    
    if command_exists mysqldump; then
        mysqldump -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" \
            --single-transaction --routines --triggers \
            "$DB_NAME" > "$backup_file"
        
        print_success "Backup criado: $backup_file"
    else
        print_error "mysqldump não encontrado"
    fi
}

show_status() {
    print_step "Status do sistema..."
    
    if check_mysql_connection >/dev/null 2>&1; then
        mysql -h"$DB_HOST" -P"$DB_PORT" -u"$DB_USER" -p"$DB_PASS" -D"$DB_NAME" -e "
            SELECT 'SISTEMA ETL DI' as Sistema, 'OPERACIONAL' as Status;
            SELECT * FROM v_sistema_status;
        " 2>/dev/null || print_error "Banco não inicializado"
    fi
}

# ================================================================================
# MENU PRINCIPAL
# ================================================================================

show_help() {
    echo "Uso: $0 [COMANDO]"
    echo
    echo "Comandos disponíveis:"
    echo "  install     - Instalar banco de dados completo"
    echo "  reset       - Resetar banco (CUIDADO!)"
    echo "  backup      - Fazer backup do banco"
    echo "  status      - Mostrar status do sistema"
    echo "  help        - Mostrar esta ajuda"
    echo
    echo "Sem comando = install"
}

# ================================================================================
# EXECUÇÃO PRINCIPAL
# ================================================================================

case "${1:-install}" in
    "install")
        install_database
        ;;
    "reset")
        reset_database
        ;;
    "backup")
        backup_database
        ;;
    "status")
        show_status
        ;;
    "help"|"-h"|"--help")
        show_help
        ;;
    *)
        print_error "Comando inválido: $1"
        show_help
        exit 1
        ;;
esac