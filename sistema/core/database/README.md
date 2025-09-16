# 📊 Sistema ETL de DI's - Database

## 🎯 Visão Geral

Sistema de banco de dados MySQL otimizado para processamento de Declarações de Importação brasileiras com:

- **12 tabelas principais** + configurações
- **Validação automática de AFRMM** (DI prevalece sobre cálculo)
- **Despesas portuárias discriminadas** (16 categorias)
- **NCMs catalogados dinamicamente** (sem alíquotas hardcoded)
- **Auditoria completa** com logs de conversão

## 🚀 Instalação Rápida

```bash
# 1. Instalar banco completo
cd sistema/core/database
./setup.sh install

# 2. Verificar instalação
./setup.sh status

# 3. Fazer backup
./setup.sh backup
```

## 📁 Arquivos do Sistema

| Arquivo | Descrição | Status |
|---------|-----------|--------|
| `01_schema.sql` | **13 tabelas** principais + constraints | ✅ |
| `02_functions.sql` | **10 funções** conversão + validação AFRMM | ✅ |
| `03_triggers.sql` | **10 triggers** auditoria + auto-update | ✅ |
| `04_views.sql` | **8 views** analíticas + dashboards | ✅ |
| `05_indexes.sql` | **25+ índices** otimizados | ✅ |
| `06_initial_data.sql` | Moedas + configurações mínimas | ✅ |
| `database.php` | Conexão PHP ServBay MySQL | ✅ |
| `setup.sh` | Instalação automatizada | ✅ |
| `test_validation.sql` | Testes de validação AFRMM | ✅ |

## 🏗️ Estrutura do Banco

### Tabelas Principais

```
declaracoes_importacao (DI principal)
├── adicoes (itens da DI)
│   ├── mercadorias (produtos detalhados)  
│   ├── impostos_adicao (II, IPI, PIS, COFINS)
│   └── acordos_tarifarios (MERCOSUL, etc)
├── icms_detalhado (ICMS por estado)
├── pagamentos_siscomex (taxas)
├── despesas_frete_seguro (internacional)
└── despesas_extras (discriminadas - 16 tipos)
```

### Tabelas de Referência

```
moedas_referencia (15 moedas principais)
ncm_referencia (catalogados dinamicamente)
ncm_aliquotas_historico (alíquotas reais praticadas)
conversao_valores (log de auditoria)
configuracoes_sistema (parâmetros)
```

## ⚡ Funcionalidades Principais

### 1. **Validação AFRMM Inteligente**
```sql
-- DI informa AFRMM = R$ 2.650
-- Sistema calcula 25% frete = R$ 2.500  
-- RESULTADO: DI prevalece (R$ 2.650 usado)
-- Divergência: 6% (dentro do limite)
```

### 2. **Despesas Discriminadas**
```sql
SELECT categoria, valor_final 
FROM v_despesas_discriminadas 
WHERE numero_di = '2300120746';

-- RESULTADO:
-- SISCOMEX:     R$   214,75
-- AFRMM:        R$ 2.650,00  
-- CAPATAZIA:    R$   850,00
-- ARMAZENAGEM:  R$ 1.200,00
-- DESPACHANTE:  R$   800,00
-- TOTAL:        R$ 5.714,75
```

### 3. **NCMs Dinâmicos (Sem Alíquotas Fixas)**
```sql
-- NCMs são cadastrados quando aparecem na primeira DI
-- Alíquotas vêm sempre da DI processada
-- Histórico real em ncm_aliquotas_historico
```

### 4. **Custo Landed Completo**
```sql
SELECT * FROM v_custo_landed_completo 
WHERE numero_di = '2300120746';

-- RESULTADO:
-- CIF:           R$ 178.591,26
-- Impostos:      R$  58.691,29  
-- Despesas:      R$   7.064,75
-- LANDED COST:   R$ 244.347,30
```

## 🔧 Configuração ServBay MySQL

### Credenciais Padrão:
```php
Host:     localhost
Port:     3307
Database: importaco_etl_dis  
User:     root
Password: ServBay.dev
```

### Teste de Conexão:
```bash
mysql -h localhost -P 3307 -u root -p'ServBay.dev' -e "SELECT 1;"
```

## 📊 Views Principais

### 1. **`v_di_resumo`** - Dashboard Executivo
```sql
SELECT numero_di, custo_total_landed, total_impostos 
FROM v_di_resumo 
ORDER BY data_registro DESC;
```

### 2. **`v_despesas_discriminadas`** - Análise Detalhada
```sql  
SELECT grupo_despesa, categoria, SUM(valor_final) as total
FROM v_despesas_discriminadas
GROUP BY grupo_despesa, categoria;
```

### 3. **`v_auditoria_afrmm`** - Validação AFRMM
```sql
SELECT numero_di, status_visual, divergencia_percentual
FROM v_auditoria_afrmm  
WHERE ABS(divergencia_percentual) > 10;
```

### 4. **`v_sistema_status`** - Monitoramento
```sql
SELECT * FROM v_sistema_status;
-- Mostra: moedas ativas, DIs processadas, situação geral
```

## ⚙️ Funções Críticas

### 1. **Conversão Siscomex**
```sql
SELECT fn_convert_siscomex_money('000000017859126');
-- Resultado: 178591.26

SELECT fn_convert_siscomex_rate('01600');  
-- Resultado: 0.1600 (16%)
```

### 2. **Validação AFRMM**
```sql
SELECT fn_validate_afrmm(2650.00, 10000.00);
-- Retorna JSON com validação completa
```

### 3. **Custo Landed**
```sql
SELECT fn_calculate_landed_cost('2300120746');
-- Retorna JSON com breakdown completo
```

## 🧪 Testes de Validação

```bash
# Executar testes completos
mysql -h localhost -P 3307 -u root -p'ServBay.dev' \
      -D importaco_etl_dis < test_validation.sql

# Testa:
# ✅ Conversões Siscomex
# ✅ Validação AFRMM  
# ✅ Triggers automáticos
# ✅ Views analíticas
# ✅ Constraints de segurança
```

## 🔒 Segurança e Auditoria

### Logs Automáticos:
- **Todas conversões** registradas em `conversao_valores`
- **Alterações de impostos** auditadas automaticamente  
- **AFRMM divergente** gera alertas automáticos
- **Taxa câmbio suspeita** bloqueia inserção

### Validações:
```sql
-- CNPJ obrigatório formato 14 dígitos
-- NCM obrigatório formato 8 dígitos  
-- Taxa câmbio dentro de limites razoáveis
-- Valores não-negativos obrigatório
```

## 📈 Performance

### Benchmarks Esperados:
- **Inserção DI**: < 100ms
- **Cálculo landed cost**: < 50ms  
- **Consultas analíticas**: < 2s
- **Views dashboards**: < 1s

### Otimizações:
- **25+ índices** estratégicos
- **Triggers otimizados** 
- **Views materializadas** opcionais
- **Conexão persistente** em produção

## 🛠️ Comandos Úteis

### Administração:
```bash
# Status completo
./setup.sh status

# Backup com timestamp  
./setup.sh backup

# Reset total (CUIDADO!)
./setup.sh reset

# Apenas ajuda
./setup.sh help
```

### Queries Diagnósticas:
```sql
-- Ver crescimento de dados
SELECT * FROM v_crescimento_dados;

-- Monitorar AFRMM problemáticos
SELECT * FROM v_auditoria_afrmm 
WHERE status_visual LIKE '%DIVERGÊNCIA%';

-- Top NCMs importados
SELECT * FROM v_top_ncms LIMIT 10;

-- Performance fiscal mensal
SELECT * FROM v_performance_fiscal 
ORDER BY ano DESC, mes DESC LIMIT 12;
```

## 🚨 Troubleshooting

### Problema: "Connection refused port 3307"
```bash
# Verificar se ServBay está rodando
sudo lsof -i :3307

# Reiniciar ServBay se necessário
```

### Problema: "Table doesn't exist"  
```bash
# Reinstalar banco
./setup.sh reset
./setup.sh install
```

### Problema: "Function doesn't exist"
```bash  
# Executar apenas funções
mysql -h localhost -P 3307 -u root -p'ServBay.dev' \
      -D importaco_etl_dis < 02_functions.sql
```

### Problema: "AFRMM não calculando"
```bash
# Verificar triggers
mysql -h localhost -P 3307 -u root -p'ServBay.dev' \
      -D importaco_etl_dis < 03_triggers.sql

# Testar validação
mysql -h localhost -P 3307 -u root -p'ServBay.dev' \
      -D importaco_etl_dis < test_validation.sql
```

## 📞 Suporte

- **Documentação**: `/sistema/core/database/SCHEMA-SPECIFICATION.md`
- **PRD Completo**: `/PRD-Sistema-ETL-DIs.md`  
- **Configuração**: `/sistema/config/database.php`
- **Logs Sistema**: Tabela `conversao_valores`

---

## ✅ Sistema Pronto!

Após instalação com `./setup.sh install`:

1. **12 tabelas** operacionais
2. **Validação AFRMM** funcionando  
3. **Despesas discriminadas** configuradas
4. **Views analíticas** disponíveis
5. **Testes validados** ✅

**Sistema pronto para receber XMLs de DI e processar automaticamente!**