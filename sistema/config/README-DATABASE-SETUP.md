# Sistema de Configuração de Banco de Dados

## 📋 Visão Geral

Interface web completa para configuração e gerenciamento de conexões com banco de dados MySQL. Permite testar, configurar e alternar entre diferentes perfis de conexão de forma intuitiva e segura.

## 🎯 Funcionalidades Principais

### ✅ Detecção Automática de Ambientes
- **ServBay MySQL (Mac)**: Porta 3307
- **WAMP Server (Windows)**: Porta 3306
- **XAMPP (Cross-Platform)**: Porta 3306
- **Docker Containers**: Detecção automática
- **Servidores de Produção**: Via variáveis de ambiente

### ✅ Gerenciamento de Perfis
- **8 Perfis Pré-configurados**: ServBay, WAMP, XAMPP, Docker, Production, Testing, Custom Local, Cloud
- **Perfis Customizados**: Criação, edição e exclusão
- **Troca Dinâmica**: Alternar perfis sem reiniciar o sistema
- **Persistência**: Configurações salvas automaticamente

### ✅ Teste de Conexões em Tempo Real
- **Teste Individual**: Cada perfil pode ser testado separadamente
- **Teste em Lote**: Testar todos os perfis simultaneamente
- **Feedback Detalhado**: Versão do servidor, tempo de resposta, status do database
- **Validação Completa**: Conectividade, credenciais, existência do database

### ✅ Interface Responsiva
- **Design Expertzy**: Cores padrão #FF002D e #091A30
- **Mobile-First**: Funciona perfeitamente em dispositivos móveis
- **Feedback Visual**: Indicadores de status, animações suaves
- **Acessibilidade**: Suporte a leitores de tela e navegação por teclado

## 🏗️ Arquitetura do Sistema

### Estrutura de Arquivos
```
/sistema/config/
├── setup.php                          # Interface principal
├── connections.php                     # Perfis pré-configurados
├── custom-profiles.php                 # Perfis personalizados (auto-gerado)
├── user-preferences.php               # Preferências do usuário (auto-gerado)
├── example-custom-profiles.php        # Exemplo de estrutura
├── /assets/
│   ├── setup.css                      # Estilos da interface
│   └── setup.js                       # JavaScript funcional
├── /ajax/
│   ├── test-connection.php            # API para testes de conexão
│   └── save-profile.php               # API para salvar/gerenciar perfis
└── README-DATABASE-SETUP.md           # Esta documentação
```

### APIs REST Disponíveis

#### 1. Test Connection API (`/ajax/test-connection.php`)
```json
// Testar perfil específico
POST /ajax/test-connection.php
{
  "action": "test_profile",
  "profile": "servbay"
}

// Testar configuração customizada
POST /ajax/test-connection.php
{
  "action": "test_custom",
  "host": "localhost",
  "port": 3306,
  "database": "test_db",
  "username": "root",
  "password": "password"
}

// Testar todos os perfis
POST /ajax/test-connection.php
{
  "action": "test_all"
}

// Obter status do sistema
POST /ajax/test-connection.php
{
  "action": "get_status"
}

// Atualizar detecção de ambientes
POST /ajax/test-connection.php
{
  "action": "refresh_detection"
}
```

#### 2. Profile Management API (`/ajax/save-profile.php`)
```json
// Salvar perfil customizado
POST /ajax/save-profile.php
{
  "action": "save_custom_profile",
  "name": "Meu Servidor Local",
  "host": "192.168.1.100",
  "port": 3306,
  "database": "importaco_etl_dis",
  "username": "dev_user",
  "password": "dev_password"
}

// Trocar perfil ativo
POST /ajax/save-profile.php
{
  "action": "switch_profile",
  "profile": "servbay"
}

// Deletar perfil customizado
POST /ajax/save-profile.php
{
  "action": "delete_custom_profile",
  "profile": "custom_meu_servidor"
}
```

## 🚀 Como Usar

### 1. Acessar a Interface
```bash
# Navegador
http://localhost:8000/sistema/config/setup.php
```

### 2. Detecção Automática
- A interface detecta automaticamente ambientes MySQL disponíveis
- Clique em "Atualizar Detecção" para re-escanear

### 3. Testar Conexões
- **Teste Individual**: Clique em "Testar" em qualquer perfil
- **Teste em Lote**: Clique em "Testar Todos os Perfis"
- **Resultados**: Verde = sucesso, Vermelho = erro

### 4. Criar Perfil Customizado
1. Preencha o formulário "Configuração Customizada"
2. Clique em "Testar Conexão" para validar
3. Se bem-sucedido, clique em "Salvar Perfil"
4. O perfil aparecerá na lista de perfis disponíveis

### 5. Trocar Perfil Ativo
1. Clique em "Usar Este Perfil" no perfil desejado
2. Confirme a ação se solicitado
3. O sistema testará e ativará o novo perfil

## ⚙️ Configuração de Perfis

### Perfis Pré-configurados

#### ServBay (Mac Development)
```php
'servbay' => [
    'host' => 'localhost',
    'port' => 3307,
    'database' => 'importaco_etl_dis',
    'username' => 'root',
    'password' => 'ServBay.dev'
]
```

#### WAMP (Windows Development)
```php
'wamp' => [
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'importaco_etl_dis',
    'username' => 'root',
    'password' => ''
]
```

#### Production (Environment Variables)
```php
'production' => [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? 3306,
    'database' => $_ENV['DB_DATABASE'] ?? 'importaco_etl_dis',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? ''
]
```

### Variáveis de Ambiente Suportadas

#### Geral
```bash
ETL_ENVIRONMENT=development|testing|production
ETL_DB_PROFILE=servbay|wamp|xampp|docker|production|testing|custom_local|cloud
```

#### Produção
```bash
DB_HOST=mysql.exemplo.com
DB_PORT=3306
DB_DATABASE=importaco_etl_dis
DB_USERNAME=user_prod
DB_PASSWORD=senha_segura
```

#### Testing
```bash
TEST_DB_HOST=localhost
TEST_DB_PORT=3307
TEST_DB_USERNAME=root
TEST_DB_PASSWORD=ServBay.dev
```

#### Custom Local
```bash
CUSTOM_DB_HOST=192.168.1.100
CUSTOM_DB_PORT=3307
CUSTOM_DB_DATABASE=meu_database
CUSTOM_DB_USERNAME=meu_usuario
CUSTOM_DB_PASSWORD=minha_senha
```

## 🔧 Integração com DatabaseConnectionManager

### Auto-detecção de Perfil
```php
// O sistema seleciona automaticamente na ordem:
// 1. Variável ETL_DB_PROFILE
// 2. Ambiente via ETL_ENVIRONMENT
// 3. Detecção automática (ServBay > WAMP > XAMPP > Docker)
// 4. Fallback para ServBay

$manager = DatabaseConnectionManager::getInstance();
$profile = $manager->autoSelectProfile();
```

### Teste Programático
```php
$manager = DatabaseConnectionManager::getInstance();

// Testar perfil específico
$result = $manager->testConnection('servbay');

// Testar todos os perfis
$results = $manager->testAllConnections();

// Obter conexão ativa
$pdo = $manager->getConnection();
```

### Adicionar Perfil Dinamicamente
```php
$manager = DatabaseConnectionManager::getInstance();

$customConfig = [
    'host' => '192.168.1.100',
    'port' => 3306,
    'database' => 'test_db',
    'username' => 'test_user',
    'password' => 'test_pass'
];

$manager->addProfile('my_custom', $customConfig);
```

## 🔐 Segurança

### Validações Implementadas
- **Input Sanitization**: Todos os inputs são validados e sanitizados
- **SQL Injection Prevention**: Uso de PDO com prepared statements
- **Timeout Protection**: Conexões com timeout de 5 segundos
- **Error Handling**: Mensagens de erro amigáveis sem exposição de dados sensíveis

### Proteção de Dados
- **Senhas**: Não são expostas em logs ou interfaces
- **Headers de Segurança**: CORS, XSS Protection, Content-Type
- **Arquivo de Perfis**: Permissões restritas, fora do webroot quando possível

### HTTPS Recomendado
```apache
# .htaccess para forçar HTTPS em produção
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
```

## 📊 Monitoramento e Logs

### Logs de Erro
```php
// Logs automáticos em:
error_log('Erro no teste de conexão: ' . $e->getMessage());
```

### Métricas de Performance
- **Tempo de Resposta**: Medido em milissegundos
- **Status da Conexão**: Online/Offline/Manutenção
- **Versão do Servidor**: MySQL version detection
- **Existência do Database**: Verificação automática

### Status do Sistema
```json
{
  "current_profile": "servbay",
  "detected_environments": {...},
  "available_profiles": [...],
  "total_profiles": 8,
  "auto_detection_enabled": true
}
```

## 🐛 Troubleshooting

### Problemas Comuns

#### Erro 1045 - Access Denied
```
Solução: Verificar credenciais (usuário/senha)
Causa: Credenciais incorretas ou usuário não existe
```

#### Erro 2002 - Can't Connect
```
Solução: Verificar se MySQL está rodando e host/porta estão corretos
Causa: Servidor offline ou configuração de rede incorreta
```

#### Erro 1049 - Unknown Database
```
Solução: Criar o database ou verificar nome correto
Causa: Database não existe no servidor
```

#### Perfis Customizados Não Aparecem
```
Solução: Verificar permissões do arquivo custom-profiles.php
Causa: Arquivo não tem permissão de escrita
```

### Comandos de Debug

#### Verificar Detecção de Ambientes
```php
$manager = DatabaseConnectionManager::getInstance();
$detected = $manager->getDetectedEnvironments();
var_dump($detected);
```

#### Testar Conexão Manualmente
```php
$manager = DatabaseConnectionManager::getInstance();
$result = $manager->testConnection('servbay');
echo json_encode($result, JSON_PRETTY_PRINT);
```

#### Verificar Status Completo
```php
$manager = DatabaseConnectionManager::getInstance();
$status = $manager->getStatus();
print_r($status);
```

## 🔄 Backup e Migração

### Exportar Configurações
```javascript
// Via interface web
fetch('/ajax/save-profile.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ action: 'export_profiles' })
})
```

### Importar Configurações
```javascript
// Via interface web
fetch('/ajax/save-profile.php', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ 
    action: 'import_profiles',
    data: exportedData
  })
})
```

### Backup Manual
```bash
# Copiar arquivos de configuração
cp sistema/config/custom-profiles.php backup/
cp sistema/config/user-preferences.php backup/
```

## 📈 Roadmap e Melhorias Futuras

### Em Desenvolvimento
- [ ] **Editor Visual**: Interface gráfica para editar perfis existentes
- [ ] **Wizard de Setup**: Assistente passo-a-passo para primeira configuração
- [ ] **Monitoramento Contínuo**: Dashboard de saúde das conexões
- [ ] **Backup Automático**: Backup automático de configurações

### Planejado
- [ ] **Múltiplos Databases**: Suporte a múltiplos databases por perfil
- [ ] **SSL Configuration**: Interface para configurar SSL/TLS
- [ ] **Connection Pooling**: Gerenciamento de pool de conexões
- [ ] **Performance Metrics**: Métricas detalhadas de performance

---

## 📞 Suporte

Para suporte técnico ou dúvidas sobre a configuração do banco de dados:

1. **Documentação**: Consulte este README completo
2. **Logs**: Verifique os logs de erro do PHP
3. **Interface**: Use a interface web para diagnósticos
4. **Debug**: Ative o modo debug para informações detalhadas

**Versão**: 1.0.0  
**Última Atualização**: 2025-09-17  
**Compatibilidade**: PHP 8.1+, MySQL 8.0+