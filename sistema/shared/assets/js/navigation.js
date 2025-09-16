/**
 * ================================================================================
 * SISTEMA ETL DE DI's - NAVEGAÇÃO DINÂMICA
 * JavaScript para controle de navegação, dropdowns e responsividade
 * Padrão Visual: Expertzy (#FF002D, #091A30)
 * ================================================================================
 */

class SystemNavigation {
    constructor() {
        this.currentUrl = window.location.pathname;
        this.mobileMenuOpen = false;
        this.activeDropdown = null;
        this.init();
    }

    /**
     * Inicializa a navegação
     */
    init() {
        this.bindEvents();
        this.detectActivePage();
        this.initMobileMenu();
        this.initDropdowns();
        this.initSmoothScroll();
        this.updateBreadcrumbs();
        
        // Debug info
        console.log('Sistema de Navegação Expertzy inicializado');
        console.log('URL atual:', this.currentUrl);
    }

    /**
     * Vincula eventos aos elementos
     */
    bindEvents() {
        // Mobile menu toggle
        const mobileToggle = document.getElementById('mobileMenuToggle');
        const mobileClose = document.getElementById('mobileNavClose');
        const mobileOverlay = document.getElementById('mobileNavOverlay');

        if (mobileToggle) {
            mobileToggle.addEventListener('click', () => this.toggleMobileMenu());
        }

        if (mobileClose) {
            mobileClose.addEventListener('click', () => this.closeMobileMenu());
        }

        if (mobileOverlay) {
            mobileOverlay.addEventListener('click', (e) => {
                if (e.target === mobileOverlay) {
                    this.closeMobileMenu();
                }
            });
        }

        // Dropdown toggles
        document.querySelectorAll('[data-dropdown]').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                const dropdownId = toggle.getAttribute('data-dropdown');
                this.toggleDropdown(dropdownId);
            });
        });

        // Mobile dropdown toggles
        document.querySelectorAll('[data-mobile-dropdown]').forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                const dropdownId = toggle.getAttribute('data-mobile-dropdown');
                this.toggleMobileDropdown(dropdownId);
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.nav-item.has-dropdown')) {
                this.closeAllDropdowns();
            }
        });

        // Keyboard navigation
        document.addEventListener('keydown', (e) => this.handleKeyboardNavigation(e));

        // Resize handler
        window.addEventListener('resize', () => this.handleResize());

        // Page visibility change
        document.addEventListener('visibilitychange', () => this.handleVisibilityChange());
    }

    /**
     * Detecta e marca a página ativa
     */
    detectActivePage() {
        const navItems = document.querySelectorAll('.nav-item');
        const currentPath = this.currentUrl;

        navItems.forEach(item => {
            const link = item.querySelector('.nav-link');
            if (!link) return;

            const href = link.getAttribute('href');
            const isActive = this.isUrlActive(href, currentPath);

            if (isActive) {
                item.classList.add('active');
                this.setActiveState(item);
            } else {
                item.classList.remove('active');
            }
        });

        // Verificar itens de dropdown
        document.querySelectorAll('.dropdown-item').forEach(item => {
            const href = item.getAttribute('href');
            const isActive = this.isUrlActive(href, currentPath);

            if (isActive) {
                const parentNavItem = item.closest('.nav-item');
                if (parentNavItem) {
                    parentNavItem.classList.add('active');
                    this.setActiveState(parentNavItem);
                }
            }
        });
    }

    /**
     * Verifica se uma URL está ativa
     */
    isUrlActive(href, currentPath) {
        if (!href || href === '#') return false;
        
        // Normalizar URLs
        const normalizedHref = href.replace(/\/$/, '') || '/';
        const normalizedPath = currentPath.replace(/\/$/, '') || '/';

        // Verificação exata
        if (normalizedHref === normalizedPath) return true;

        // Verificação de início de caminho para subpáginas
        if (normalizedPath.startsWith(normalizedHref + '/')) return true;

        return false;
    }

    /**
     * Define estado ativo para um item
     */
    setActiveState(item) {
        item.classList.add('active');
        
        // Adicionar indicador visual se necessário
        const indicator = item.querySelector('.nav-indicator');
        if (indicator) {
            indicator.style.opacity = '1';
        }
    }

    /**
     * Inicializa menu mobile
     */
    initMobileMenu() {
        const mobileMenu = document.getElementById('mobileNavOverlay');
        if (!mobileMenu) return;

        // Prevenir scroll do body quando menu está aberto
        const preventScroll = (e) => {
            if (this.mobileMenuOpen) {
                e.preventDefault();
            }
        };

        document.addEventListener('touchmove', preventScroll, { passive: false });
        document.addEventListener('wheel', preventScroll, { passive: false });
    }

    /**
     * Toggle do menu mobile
     */
    toggleMobileMenu() {
        const overlay = document.getElementById('mobileNavOverlay');
        const toggle = document.getElementById('mobileMenuToggle');
        
        if (!overlay || !toggle) return;

        this.mobileMenuOpen = !this.mobileMenuOpen;

        if (this.mobileMenuOpen) {
            overlay.classList.add('show');
            toggle.classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Focus no primeiro item do menu
            const firstItem = overlay.querySelector('.mobile-nav-link, .mobile-nav-toggle');
            if (firstItem) {
                setTimeout(() => firstItem.focus(), 300);
            }
        } else {
            overlay.classList.remove('show');
            toggle.classList.remove('active');
            document.body.style.overflow = '';
        }
    }

    /**
     * Fecha menu mobile
     */
    closeMobileMenu() {
        if (!this.mobileMenuOpen) return;
        
        const overlay = document.getElementById('mobileNavOverlay');
        const toggle = document.getElementById('mobileMenuToggle');
        
        if (overlay) overlay.classList.remove('show');
        if (toggle) toggle.classList.remove('active');
        
        this.mobileMenuOpen = false;
        document.body.style.overflow = '';
    }

    /**
     * Inicializa dropdowns
     */
    initDropdowns() {
        // Timeout para fechar dropdowns automaticamente
        let dropdownTimeout;

        document.querySelectorAll('.nav-item.has-dropdown').forEach(item => {
            const dropdown = item.querySelector('.dropdown-menu');
            if (!dropdown) return;

            item.addEventListener('mouseenter', () => {
                clearTimeout(dropdownTimeout);
                this.closeAllDropdowns();
                this.showDropdown(dropdown);
            });

            item.addEventListener('mouseleave', () => {
                dropdownTimeout = setTimeout(() => {
                    this.hideDropdown(dropdown);
                }, 300);
            });
        });
    }

    /**
     * Toggle dropdown
     */
    toggleDropdown(dropdownId) {
        const dropdown = document.getElementById(`dropdown-${dropdownId}`);
        if (!dropdown) return;

        if (this.activeDropdown === dropdown) {
            this.hideDropdown(dropdown);
        } else {
            this.closeAllDropdowns();
            this.showDropdown(dropdown);
        }
    }

    /**
     * Toggle dropdown mobile
     */
    toggleMobileDropdown(dropdownId) {
        const dropdown = document.getElementById(`mobile-dropdown-${dropdownId}`);
        const toggle = document.querySelector(`[data-mobile-dropdown="${dropdownId}"]`);
        
        if (!dropdown || !toggle) return;

        const isOpen = dropdown.classList.contains('show');
        
        // Fechar todos os outros dropdowns mobile
        document.querySelectorAll('.mobile-dropdown.show').forEach(dd => {
            if (dd !== dropdown) {
                dd.classList.remove('show');
                const otherToggle = document.querySelector(`[data-mobile-dropdown]`);
                if (otherToggle) otherToggle.classList.remove('active');
            }
        });

        if (isOpen) {
            dropdown.classList.remove('show');
            toggle.classList.remove('active');
        } else {
            dropdown.classList.add('show');
            toggle.classList.add('active');
        }
    }

    /**
     * Mostra dropdown
     */
    showDropdown(dropdown) {
        dropdown.style.opacity = '1';
        dropdown.style.visibility = 'visible';
        dropdown.style.transform = 'translateY(0)';
        this.activeDropdown = dropdown;
    }

    /**
     * Esconde dropdown
     */
    hideDropdown(dropdown) {
        dropdown.style.opacity = '0';
        dropdown.style.visibility = 'hidden';
        dropdown.style.transform = 'translateY(-10px)';
        if (this.activeDropdown === dropdown) {
            this.activeDropdown = null;
        }
    }

    /**
     * Fecha todos os dropdowns
     */
    closeAllDropdowns() {
        document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
            this.hideDropdown(dropdown);
        });
        this.activeDropdown = null;
    }

    /**
     * Inicializa smooth scroll
     */
    initSmoothScroll() {
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', (e) => {
                const targetId = anchor.getAttribute('href').substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    e.preventDefault();
                    targetElement.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    }

    /**
     * Atualiza breadcrumbs dinamicamente
     */
    updateBreadcrumbs() {
        const breadcrumbNav = document.querySelector('.breadcrumb-nav');
        if (!breadcrumbNav) return;

        // Aqui poderia ser implementada lógica para atualizar breadcrumbs
        // baseada na navegação atual ou mudanças de estado
    }

    /**
     * Manipula navegação por teclado
     */
    handleKeyboardNavigation(e) {
        // ESC fecha dropdowns e menu mobile
        if (e.key === 'Escape') {
            this.closeAllDropdowns();
            this.closeMobileMenu();
        }

        // Tab navigation em dropdowns
        if (e.key === 'Tab' && this.activeDropdown) {
            const focusableElements = this.activeDropdown.querySelectorAll('a, button');
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (e.shiftKey && document.activeElement === firstElement) {
                e.preventDefault();
                lastElement.focus();
            } else if (!e.shiftKey && document.activeElement === lastElement) {
                e.preventDefault();
                firstElement.focus();
            }
        }

        // Arrow keys em navegação
        if (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(e.key)) {
            this.handleArrowNavigation(e);
        }
    }

    /**
     * Manipula navegação com setas
     */
    handleArrowNavigation(e) {
        const navItems = document.querySelectorAll('.nav-link');
        const currentIndex = Array.from(navItems).findIndex(item => 
            item === document.activeElement
        );

        if (currentIndex === -1) return;

        let nextIndex;
        if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            nextIndex = currentIndex > 0 ? currentIndex - 1 : navItems.length - 1;
        } else {
            nextIndex = currentIndex < navItems.length - 1 ? currentIndex + 1 : 0;
        }

        e.preventDefault();
        navItems[nextIndex].focus();
    }

    /**
     * Manipula redimensionamento da janela
     */
    handleResize() {
        // Fechar menu mobile em telas grandes
        if (window.innerWidth >= 992 && this.mobileMenuOpen) {
            this.closeMobileMenu();
        }

        // Fechar dropdowns em telas pequenas
        if (window.innerWidth < 992) {
            this.closeAllDropdowns();
        }
    }

    /**
     * Manipula mudança de visibilidade da página
     */
    handleVisibilityChange() {
        if (document.hidden) {
            // Página ficou oculta - pode pausar animações, etc.
            this.closeAllDropdowns();
        } else {
            // Página ficou visível - pode reativar funcionalidades
            this.detectActivePage();
        }
    }

    /**
     * Atualiza status do sistema
     */
    updateSystemStatus(status) {
        const indicators = document.querySelectorAll('.status-icon');
        const texts = document.querySelectorAll('.status-text');
        
        indicators.forEach(indicator => {
            indicator.className = `status-icon status-${status}`;
        });
        
        texts.forEach(text => {
            const statusLabels = {
                'online': 'Online',
                'offline': 'Offline',
                'maintenance': 'Manutenção'
            };
            text.textContent = statusLabels[status] || 'Desconhecido';
        });
    }

    /**
     * Adiciona notificação de navegação
     */
    showNavigationNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `nav-notification nav-notification-${type}`;
        notification.textContent = message;
        
        document.body.appendChild(notification);
        
        // Animar entrada
        setTimeout(() => notification.classList.add('show'), 100);
        
        // Remover após 3 segundos
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }

    /**
     * Destroy - remove event listeners
     */
    destroy() {
        // Remover event listeners para evitar vazamentos de memória
        document.removeEventListener('click', this.handleDocumentClick);
        document.removeEventListener('keydown', this.handleKeyboardNavigation);
        window.removeEventListener('resize', this.handleResize);
        document.removeEventListener('visibilitychange', this.handleVisibilityChange);
        
        console.log('Sistema de Navegação destruído');
    }
}

/**
 * Função para inicializar navegação
 */
function initSystemNavigation() {
    if (window.systemNavigation) {
        window.systemNavigation.destroy();
    }
    
    window.systemNavigation = new SystemNavigation();
}

/**
 * Utilitários de navegação
 */
const NavigationUtils = {
    /**
     * Redireciona para uma URL com feedback visual
     */
    navigateTo(url, showLoader = true) {
        if (showLoader) {
            this.showNavigationLoader();
        }
        
        window.location.href = url;
    },

    /**
     * Mostra loader de navegação
     */
    showNavigationLoader() {
        const loader = document.createElement('div');
        loader.className = 'navigation-loader';
        loader.innerHTML = `
            <div class="loader-spinner"></div>
            <span>Carregando...</span>
        `;
        
        document.body.appendChild(loader);
        setTimeout(() => loader.classList.add('show'), 10);
    },

    /**
     * Esconde loader de navegação
     */
    hideNavigationLoader() {
        const loader = document.querySelector('.navigation-loader');
        if (loader) {
            loader.classList.remove('show');
            setTimeout(() => loader.remove(), 300);
        }
    },

    /**
     * Atualiza breadcrumbs programaticamente
     */
    updateBreadcrumbs(breadcrumbs) {
        const breadcrumbContainer = document.querySelector('.breadcrumb');
        if (!breadcrumbContainer) return;

        breadcrumbContainer.innerHTML = '';
        
        breadcrumbs.forEach((crumb, index) => {
            const li = document.createElement('li');
            li.className = `breadcrumb-item ${crumb.active ? 'active' : ''}`;
            
            if (crumb.active) {
                li.innerHTML = `<span>${crumb.label}</span>`;
            } else if (crumb.url) {
                li.innerHTML = `<a href="${crumb.url}">${crumb.label}</a>`;
            } else {
                li.innerHTML = `<span>${crumb.label}</span>`;
            }
            
            breadcrumbContainer.appendChild(li);
            
            // Adicionar separador
            if (index < breadcrumbs.length - 1) {
                const separator = document.createElement('span');
                separator.className = 'breadcrumb-separator';
                separator.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none"><path d="M9 18L15 12L9 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                li.appendChild(separator);
            }
        });
    },

    /**
     * Marca item de menu como ativo
     */
    setActiveMenuItem(menuId) {
        // Remove active de todos os itens
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        
        // Adiciona active ao item específico
        const targetItem = document.querySelector(`[data-menu-id="${menuId}"]`);
        if (targetItem) {
            const navItem = targetItem.closest('.nav-item');
            if (navItem) {
                navItem.classList.add('active');
            }
        }
    }
};

// Auto-inicialização quando DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSystemNavigation);
} else {
    initSystemNavigation();
}

// Exportar para uso global
window.NavigationUtils = NavigationUtils;

// CSS dinâmico para loader e notificações
const navigationStyles = `
.navigation-loader {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(9, 26, 48, 0.9);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    color: white;
}

.navigation-loader.show {
    opacity: 1;
    visibility: visible;
}

.loader-spinner {
    width: 40px;
    height: 40px;
    border: 3px solid rgba(255, 255, 255, 0.3);
    border-top: 3px solid #FF002D;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-bottom: 16px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.nav-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 12px 20px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    z-index: 10000;
    transform: translateX(100%);
    transition: transform 0.3s ease;
}

.nav-notification.show {
    transform: translateX(0);
}

.nav-notification-info {
    background: #007bff;
}

.nav-notification-success {
    background: #28a745;
}

.nav-notification-warning {
    background: #ffc107;
    color: #212529;
}

.nav-notification-error {
    background: #dc3545;
}
`;

// Adicionar estilos ao head
const styleSheet = document.createElement('style');
styleSheet.textContent = navigationStyles;
document.head.appendChild(styleSheet);