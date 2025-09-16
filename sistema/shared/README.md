# Sistema de Navegação Unificado - ETL DI's

## 📋 Visão Geral

Sistema de navegação modular e reutilizável para o Sistema ETL de DI's, seguindo o padrão visual Expertzy. Oferece header, footer, breadcrumbs e navegação responsiva unificados em toda a aplicação.

## 🏗️ Estrutura do Sistema

```
/sistema/shared/
├── components/
│   ├── header.php          # Componente header reutilizável
│   └── footer.php          # Componente footer reutilizável
├── assets/
│   ├── css/
│   │   └── system-navigation.css  # Estilos da navegação
│   └── js/
│       └── navigation.js          # JavaScript da navegação
├── config/
│   └── routes.php          # Configuração de rotas e breadcrumbs
├── utils/
│   └── layout-helpers.php  # Utilitários de layout
├── examples/
│   └── navigation-usage.php # Exemplos de uso
└── README.md              # Esta documentação
```

## 🚀 Como Usar

### 1. Layout Completo (Recomendado)

```php
<?php
require_once 'sistema/shared/utils/layout-helpers.php';

$config = [
    'page_title' => 'Minha Página',
    'page_description' => 'Descrição da página',
    'layout_type' => 'default', // default, dashboard, simple, fullscreen
    'show_breadcrumbs' => true,
    'additional_css' => ['custom.css'],
    'additional_js' => ['custom.js']
];

renderSystemLayout($config, function() {
    // Seu conteúdo aqui
    echo '<h1>Conteúdo da Página</h1>';
});
?>
```

### 2. Dashboard Layout

```php
<?php
$config = [
    'page_title' => 'Dashboard',
    'layout_type' => 'dashboard'
];

renderDashboardLayout($config, function() {
    // Conteúdo do dashboard
    include 'dashboard-content.php';
});
?>
```

### 3. Layout Simples

```php
<?php
$config = [
    'page_title' => 'Página Simples',
    'layout_type' => 'simple',
    'show_breadcrumbs' => false
];

renderSimpleLayout($config, function() {
    // Conteúdo simples
    echo '<div class="container">Conteúdo</div>';
});
?>
```

### 4. Componentes Separados

```php
<?php
require_once 'sistema/shared/components/header.php';
require_once 'sistema/shared/components/footer.php';
?>
<!DOCTYPE html>
<html>
<head>...</head>
<body>
    <?php includeHeader(['show_breadcrumbs' => true]); ?>
    
    <main>
        <!-- Seu conteúdo -->
    </main>
    
    <?php includeFooter(); ?>
</body>
</html>
```

## ⚙️ Configurações Disponíveis

### Layout Manager

| Opção | Tipo | Padrão | Descrição |
|-------|------|--------|-----------|
| `page_title` | string | 'Sistema ETL DI\'s' | Título da página |
| `page_description` | string | - | Descrição para SEO |
| `layout_type` | string | 'default' | Tipo: default, dashboard, simple, fullscreen |
| `show_header` | bool | true | Exibir header |
| `show_footer` | bool | true | Exibir footer |
| `show_breadcrumbs` | bool | true | Exibir breadcrumbs |
| `show_status` | bool | true | Exibir status do sistema |
| `body_class` | string | '' | Classes CSS para o body |
| `additional_css` | array | [] | CSS adicional |
| `additional_js` | array | [] | JavaScript adicional |
| `meta_tags` | array | [] | Meta tags customizadas |

### Header

| Opção | Tipo | Padrão | Descrição |
|-------|------|--------|-----------|
| `show_breadcrumbs` | bool | true | Exibir breadcrumbs |
| `show_status` | bool | true | Exibir indicador de status |
| `show_user_menu` | bool | false | Exibir menu do usuário |
| `current_url` | string | $_SERVER['REQUEST_URI'] | URL atual |
| `system_status` | string | 'online' | Status: online, offline, maintenance |
| `custom_title` | string | null | Título customizado |
| `logo_url` | string | '/index.html' | URL do logo |

### Footer

| Opção | Tipo | Padrão | Descrição |
|-------|------|--------|-----------|
| `show_modules` | bool | true | Exibir links dos módulos |
| `show_system_info` | bool | true | Exibir informações do sistema |
| `show_support_links` | bool | true | Exibir links de suporte |
| `show_version` | bool | true | Exibir versão |
| `compact` | bool | false | Modo compacto |
| `additional_links` | array | [] | Links adicionais |

## 🎨 Tipos de Layout

### Default Layout
Layout padrão com header, footer e breadcrumbs. Ideal para páginas de conteúdo geral.

### Dashboard Layout
Layout otimizado para dashboards com estilos e scripts específicos para gráficos e estatísticas.

### Simple Layout
Layout minimalista sem footer completo, ideal para páginas de processo ou forms.

### Fullscreen Layout
Layout sem header/footer para aplicações que precisam de tela cheia.

## 🔧 Navegação Dinâmica

O sistema JavaScript oferece:

- **Detecção automática de página ativa**
- **Dropdowns responsivos**
- **Menu mobile**
- **Navegação por teclado**
- **Breadcrumbs dinâmicas**

### Funções JavaScript Disponíveis

```javascript
// Navegação programática
NavigationUtils.navigateTo('/nova-pagina', true);

// Atualizar breadcrumbs
NavigationUtils.updateBreadcrumbs([
    {label: 'Início', url: '/'},
    {label: 'Página Atual', active: true}
]);

// Marcar item ativo
NavigationUtils.setActiveMenuItem('dashboard');

// Atualizar status do sistema
systemNavigation.updateSystemStatus('maintenance');
```

## 📱 Responsividade

O sistema é mobile-first e inclui:

- **Breakpoints**: 576px, 768px, 992px, 1200px, 1400px
- **Menu mobile**: Overlay deslizante
- **Touch interactions**: Otimizado para touch
- **Reduced motion**: Suporte para prefers-reduced-motion

## 🎯 Breadcrumbs

### Configuração Automática

As breadcrumbs são geradas automaticamente baseadas na URL atual usando o mapeamento em `routes.php`.

### Breadcrumbs Customizadas

```php
$customBreadcrumbs = [
    ['label' => 'Início', 'url' => '/'],
    ['label' => 'Módulos', 'url' => '/modules'],
    ['label' => 'Fiscal', 'url' => '', 'active' => true]
];

renderSystemLayout([
    'breadcrumbs' => $customBreadcrumbs
], function() {
    // Conteúdo
});
```

## 🔍 SEO e Acessibilidade

### SEO
- Meta tags automáticas
- Open Graph tags
- JSON-LD structured data
- Canonical URLs

### Acessibilidade
- Skip navigation links
- ARIA labels
- Navegação por teclado
- Focus management
- Screen reader support

## 🎨 Customização Visual

### CSS Variables Disponíveis

O sistema usa as variáveis do `expertzy-theme.css`:

```css
:root {
    --expertzy-red: #FF002D;
    --expertzy-blue: #091A30;
    /* ... outras variáveis */
}
```

### Classes CSS Personalizadas

```css
/* Customizar navegação */
.system-header {
    /* Seus estilos */
}

/* Customizar dropdown */
.dropdown-menu {
    /* Seus estilos */
}

/* Layout específico */
.dashboard-layout .main-content {
    /* Estilos do dashboard */
}
```

## 🧪 Exemplos

Acesse `/sistema/shared/examples/navigation-usage.php` para ver exemplos funcionais:

- `?exemplo=completo` - Layout completo
- `?exemplo=dashboard` - Dashboard
- `?exemplo=simples` - Layout simples
- `?exemplo=fullscreen` - Fullscreen
- `?exemplo=separados` - Componentes separados
- `?exemplo=modulo` - Página de módulo

## 🐛 Troubleshooting

### Problemas Comuns

1. **CSS não carrega**: Verifique os caminhos dos arquivos CSS
2. **JavaScript não funciona**: Confirme se `navigation.js` está carregado
3. **Breadcrumbs incorretas**: Verifique mapeamento em `routes.php`
4. **Layout quebrado**: Confirme dependências do Bootstrap/Expertzy

### Debug

```javascript
// Habilitar debug da navegação
window.systemNavigation.debug = true;

// Verificar configuração atual
console.log(window.systemLayout);
```

## 📚 API Reference

### LayoutManager

```php
$layout = new LayoutManager($config);
$layout->renderLayoutStart();
// ... conteúdo ...
$layout->renderLayoutEnd();
```

### Funções Helper

```php
// Layouts pré-configurados
renderSystemLayout($config, $callback);
renderDashboardLayout($config, $callback);
renderSimpleLayout($config, $callback);
renderFullscreenLayout($config, $callback);

// Componentes individuais
includeHeader($config);
includeFooter($config);

// Utilitários
generateMetaTags($pageData);
generateDynamicBreadcrumbs($currentPage);
isFeatureEnabled($feature);
```

## 🔄 Migração

### Para migrar páginas existentes:

1. **Inclua o layout helper**:
   ```php
   require_once 'sistema/shared/utils/layout-helpers.php';
   ```

2. **Remova HTML boilerplate**:
   - `<!DOCTYPE html>`, `<head>`, header/footer manuais

3. **Use renderSystemLayout**:
   ```php
   renderSystemLayout($config, function() {
       // Seu conteúdo existente
   });
   ```

4. **Atualize caminhos CSS/JS**:
   - Use `additional_css` e `additional_js`

## 📊 Performance

- **CSS**: ~25KB minificado
- **JavaScript**: ~15KB minificado
- **Tempo de carregamento**: <100ms
- **Mobile-first**: Otimizado para mobile

## 🔒 Segurança

- Sanitização automática de inputs
- Escape HTML automático
- CSP headers recomendados
- XSS protection

## 📝 Changelog

### v1.0.0 (2025-01-16)
- Lançamento inicial
- Sistema completo de navegação
- Suporte a 4 tipos de layout
- Responsividade completa
- Acessibilidade implementada

---

**Desenvolvido por**: Expertzy IT Solutions  
**Versão**: 1.0.0  
**Última atualização**: Janeiro 2025