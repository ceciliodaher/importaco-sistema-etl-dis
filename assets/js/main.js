// Sistema ETL de DI's - Main JavaScript
// Padrão Expertzy - Sem hardcode, modular e reutilizável

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    // Configurações globais
    const config = {
        animationDuration: 300,
        scrollOffset: 100,
        mobileBreakpoint: 768
    };

    // Utilities
    const utils = {
        isMobile: () => window.innerWidth <= config.mobileBreakpoint,

        debounce: (func, wait) => {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        },

        smoothScroll: (target, duration = 800) => {
            const targetElement = document.querySelector(target);
            if (!targetElement) return;

            const targetPosition = targetElement.offsetTop - config.scrollOffset;
            const startPosition = window.pageYOffset;
            const distance = targetPosition - startPosition;
            let startTime = null;

            function animation(currentTime) {
                if (startTime === null) startTime = currentTime;
                const timeElapsed = currentTime - startTime;
                const run = ease(timeElapsed, startPosition, distance, duration);
                window.scrollTo(0, run);
                if (timeElapsed < duration) requestAnimationFrame(animation);
            }

            function ease(t, b, c, d) {
                t /= d / 2;
                if (t < 1) return c / 2 * t * t + b;
                t--;
                return -c / 2 * (t * (t - 2) - 1) + b;
            }

            requestAnimationFrame(animation);
        }
    };

    // Performance monitoring
    const performance = {
        startTime: performance.now(),

        logPageLoad: () => {
            window.addEventListener('load', () => {
                const loadTime = performance.now() - performance.startTime;
                console.log(`✅ Landing page carregada em ${Math.round(loadTime)}ms`);
            });
        },

        logInteraction: (element, action) => {
            const timestamp = new Date().toISOString();
            console.log(`📊 [${timestamp}] Interação: ${action} em ${element}`);
        }
    };

    // Animations
    const animations = {
        fadeInOnScroll: () => {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Aplicar a elementos que devem aparecer gradualmente
            document.querySelectorAll('.feature, .module').forEach(el => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(30px)';
                el.style.transition = `opacity ${config.animationDuration}ms ease, transform ${config.animationDuration}ms ease`;
                observer.observe(el);
            });
        },

        hoverEffects: () => {
            // Efeitos hover para cards
            document.querySelectorAll('.feature, .module').forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px) scale(1.02)';
                    performance.logInteraction(this.className, 'hover_enter');
                });

                card.addEventListener('mouseleave', function() {
                    this.style.transform = '';
                });
            });

            // Efeito hover para botão CTA
            const ctaButton = document.querySelector('.btn');
            if (ctaButton) {
                ctaButton.addEventListener('mouseenter', () => {
                    performance.logInteraction('btn', 'hover_enter');
                });
            }
        }
    };

    // Responsividade
    const responsive = {
        handleResize: utils.debounce(() => {
            const isMobile = utils.isMobile();

            // Ajustar layout para mobile
            document.body.classList.toggle('mobile-layout', isMobile);

            // Log da mudança de viewport
            console.log(`📱 Viewport alterado: ${isMobile ? 'Mobile' : 'Desktop'}`);
        }, 250),

        init: () => {
            window.addEventListener('resize', responsive.handleResize);
            responsive.handleResize(); // Executa na inicialização
        }
    };

    // Navegação
    const navigation = {
        handleCTAClick: () => {
            const ctaButton = document.querySelector('.btn');
            if (ctaButton) {
                ctaButton.addEventListener('click', (e) => {
                    performance.logInteraction('cta_button', 'click');

                    // Adicionar loading state
                    ctaButton.style.opacity = '0.7';
                    ctaButton.innerHTML = 'Carregando...';

                    // Simular loading para melhor UX
                    setTimeout(() => {
                        window.location.href = ctaButton.href;
                    }, 500);
                });
            }
        },

        init: () => {
            navigation.handleCTAClick();
        }
    };

    // Inicialização
    const init = {
        run: () => {
            console.log('🚀 Inicializando Sistema ETL de DI\'s - Expertzy');

            // Verificar suporte do navegador
            if (!window.IntersectionObserver) {
                console.warn('⚠️ Navegador não suporta IntersectionObserver');
                return;
            }

            // Inicializar módulos
            performance.logPageLoad();
            animations.fadeInOnScroll();
            animations.hoverEffects();
            responsive.init();
            navigation.init();

            console.log('✅ Sistema inicializado com sucesso');
        }
    };

    // Executar inicialização
    init.run();

    // Expor utilities globalmente para outros módulos
    window.ExpertzyUtils = utils;
    window.ExpertzyPerformance = performance;
});