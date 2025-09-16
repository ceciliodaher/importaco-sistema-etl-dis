// Expertzy Interactions - Interações específicas da marca
// Sistema ETL de DI's - Padrão Expertzy

(function() {
    'use strict';

    // Configurações específicas da marca Expertzy
    const expertzyConfig = {
        brandColors: {
            primary: '#FF002D',
            dark: '#091A30',
            white: '#FFFFFF'
        },
        animations: {
            duration: 300,
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)'
        },
        pillars: [
            { name: 'Energia', color: '#FF002D', icon: '⚡' },
            { name: 'Segurança', color: '#091A30', icon: '🔒' },
            { name: 'Transparência', color: '#FFFFFF', icon: '👁️' }
        ]
    };

    // Interações específicas da marca
    const expertzyInteractions = {
        // Animação do logo
        logoAnimation: () => {
            const logo = document.querySelector('.logo-img');
            if (!logo) return;

            let animationFrame;
            let rotation = 0;

            const animate = () => {
                rotation += 0.5;
                logo.style.transform = `rotate(${rotation}deg)`;

                if (rotation < 360) {
                    animationFrame = requestAnimationFrame(animate);
                } else {
                    logo.style.transform = '';
                }
            };

            logo.addEventListener('click', () => {
                if (animationFrame) cancelAnimationFrame(animationFrame);
                rotation = 0;
                animate();

                // Log da interação
                if (window.ExpertzyPerformance) {
                    window.ExpertzyPerformance.logInteraction('logo', 'animation_click');
                }
            });
        },

        // Efeito de partículas nos módulos
        moduleParticles: () => {
            const modules = document.querySelectorAll('.module');

            modules.forEach((module, index) => {
                module.addEventListener('mouseenter', function() {
                    this.style.background = `
                        radial-gradient(circle at center,
                        rgba(255, 0, 45, 0.1) 0%,
                        transparent 50%),
                        ${expertzyConfig.brandColors.dark}
                    `;
                });

                module.addEventListener('mouseleave', function() {
                    this.style.background = '';
                });
            });
        },

        // Contador animado de benefícios
        animatedCounters: () => {
            const counters = [
                { element: '.hero h1', text: 'Sistema ETL de DI\'s', suffix: '' },
                { element: '.cta h2', text: 'Acesse o Sistema', suffix: '' }
            ];

            const animateText = (element, finalText, suffix = '') => {
                const el = document.querySelector(element);
                if (!el) return;

                const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                let iteration = 0;

                const interval = setInterval(() => {
                    el.textContent = finalText
                        .split('')
                        .map((letter, index) => {
                            if (index < iteration) {
                                return finalText[index];
                            }
                            return chars[Math.floor(Math.random() * chars.length)];
                        })
                        .join('') + suffix;

                    if (iteration >= finalText.length) {
                        clearInterval(interval);
                        el.textContent = finalText + suffix;
                    }

                    iteration += 1 / 3;
                }, 50);
            };

            // Trigger na primeira visualização
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const counter = counters.find(c => entry.target.matches(c.element));
                        if (counter) {
                            setTimeout(() => {
                                animateText(counter.element, counter.text, counter.suffix);
                            }, 500);
                            observer.unobserve(entry.target);
                        }
                    }
                });
            });

            counters.forEach(counter => {
                const element = document.querySelector(counter.element);
                if (element) observer.observe(element);
            });
        },

        // Efeito de typing para tagline
        typingTagline: () => {
            const tagline = document.querySelector('.tagline');
            if (!tagline) return;

            const texts = [
                'Energia • Velocidade • Força',
                'Segurança • Intelecto • Precisão',
                'Respeito • Proteção • Transparência',
                'Energia • Segurança • Transparência'
            ];

            let textIndex = 0;
            let charIndex = 0;
            let isDeleting = false;

            const typeEffect = () => {
                const currentText = texts[textIndex];

                if (isDeleting) {
                    tagline.textContent = currentText.substring(0, charIndex - 1);
                    charIndex--;
                } else {
                    tagline.textContent = currentText.substring(0, charIndex + 1);
                    charIndex++;
                }

                let typeSpeed = isDeleting ? 50 : 100;

                if (!isDeleting && charIndex === currentText.length) {
                    typeSpeed = 2000; // Pausa no final
                    isDeleting = true;
                } else if (isDeleting && charIndex === 0) {
                    isDeleting = false;
                    textIndex = (textIndex + 1) % texts.length;
                    typeSpeed = 500; // Pausa antes do próximo texto
                }

                setTimeout(typeEffect, typeSpeed);
            };

            // Iniciar efeito após 1 segundo
            setTimeout(typeEffect, 1000);
        },

        // Badge de versão animado
        versionBadge: () => {
            const footer = document.querySelector('.footer');
            if (!footer) return;

            const badge = document.createElement('div');
            badge.className = 'version-badge';
            badge.innerHTML = `
                <span class="version-label">v1.0.0</span>
                <span class="version-status">BETA</span>
            `;

            badge.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${expertzyConfig.brandColors.primary};
                color: white;
                padding: 8px 12px;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 600;
                z-index: 1000;
                box-shadow: 0 4px 12px rgba(255, 0, 45, 0.3);
                cursor: pointer;
                transition: all 0.3s ease;
            `;

            badge.addEventListener('mouseenter', () => {
                badge.style.transform = 'scale(1.1)';
                badge.style.boxShadow = '0 6px 20px rgba(255, 0, 45, 0.4)';
            });

            badge.addEventListener('mouseleave', () => {
                badge.style.transform = 'scale(1)';
                badge.style.boxShadow = '0 4px 12px rgba(255, 0, 45, 0.3)';
            });

            document.body.appendChild(badge);
        },

        // Interações dos cards de sistema
        systemCardsInteractions: () => {
            const systemCards = document.querySelectorAll('.system-card');
            
            systemCards.forEach((card, index) => {
                // Efeito de entrada escalonado
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 150);

                // Hover effect aprimorado
                card.addEventListener('mouseenter', function() {
                    const icon = this.querySelector('.system-icon');
                    if (icon) {
                        icon.style.transform = 'scale(1.1) rotate(5deg)';
                        icon.style.transition = 'all 0.3s ease';
                    }

                    // Efeito de brilho para cards featured
                    if (this.classList.contains('featured')) {
                        this.style.boxShadow = '0 20px 60px rgba(255, 0, 45, 0.2)';
                    }
                });

                card.addEventListener('mouseleave', function() {
                    const icon = this.querySelector('.system-icon');
                    if (icon) {
                        icon.style.transform = 'scale(1) rotate(0deg)';
                    }

                    if (this.classList.contains('featured')) {
                        this.style.boxShadow = '';
                    }
                });

                // Click tracking para análise
                card.addEventListener('click', function() {
                    const systemName = this.querySelector('h3')?.textContent || 'Unknown';
                    console.log(`📊 Sistema clicado: ${systemName}`);
                    
                    if (window.ExpertzyPerformance) {
                        window.ExpertzyPerformance.logInteraction('system_card', systemName);
                    }
                });
            });
        },

        // Animação da progress bar de desenvolvimento
        progressBarAnimation: () => {
            const progressBars = document.querySelectorAll('.progress-fill');
            
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const progressBar = entry.target;
                        const targetWidth = progressBar.style.width;
                        
                        // Reset and animate
                        progressBar.style.width = '0%';
                        progressBar.style.transition = 'width 1.5s ease-in-out';
                        
                        setTimeout(() => {
                            progressBar.style.width = targetWidth;
                        }, 300);

                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.5 });

            progressBars.forEach(bar => observer.observe(bar));
        },

        // Timeline do roadmap interativo
        roadmapTimelineInteraction: () => {
            const timelineItems = document.querySelectorAll('.timeline-item');
            
            timelineItems.forEach((item, index) => {
                item.addEventListener('mouseenter', function() {
                    const marker = this.querySelector('.timeline-marker');
                    if (marker) {
                        marker.style.transform = 'scale(1.3)';
                        marker.style.transition = 'all 0.3s ease';
                    }

                    // Tooltip effect
                    const status = this.querySelector('.timeline-status')?.textContent;
                    if (status && !this.querySelector('.timeline-tooltip')) {
                        const tooltip = document.createElement('div');
                        tooltip.className = 'timeline-tooltip';
                        tooltip.textContent = status;
                        tooltip.style.cssText = `
                            position: absolute;
                            top: -40px;
                            left: 50%;
                            transform: translateX(-50%);
                            background: ${expertzyConfig.brandColors.dark};
                            color: white;
                            padding: 4px 8px;
                            border-radius: 4px;
                            font-size: 0.7rem;
                            white-space: nowrap;
                            pointer-events: none;
                            z-index: 100;
                        `;
                        this.style.position = 'relative';
                        this.appendChild(tooltip);
                    }
                });

                item.addEventListener('mouseleave', function() {
                    const marker = this.querySelector('.timeline-marker');
                    if (marker) {
                        marker.style.transform = 'scale(1)';
                    }

                    const tooltip = this.querySelector('.timeline-tooltip');
                    if (tooltip) {
                        tooltip.remove();
                    }
                });
            });
        },

        // Status indicators pulsantes
        statusIndicatorsPulse: () => {
            const indicators = document.querySelectorAll('.indicator-dot.online, .status-dot');
            
            indicators.forEach(indicator => {
                if (indicator.classList.contains('online') || 
                    indicator.parentElement?.classList.contains('available')) {
                    
                    const pulseAnimation = () => {
                        indicator.style.boxShadow = `0 0 0 0 ${indicator.style.backgroundColor || 'var(--expertzy-success)'}`;
                        indicator.style.animation = 'expertzy-pulse 2s infinite';
                    };

                    // Add pulse animation CSS if not exists
                    if (!document.querySelector('#expertzy-pulse-style')) {
                        const style = document.createElement('style');
                        style.id = 'expertzy-pulse-style';
                        style.textContent = `
                            @keyframes expertzy-pulse {
                                0% {
                                    box-shadow: 0 0 0 0 currentColor;
                                    opacity: 1;
                                }
                                70% {
                                    box-shadow: 0 0 0 10px transparent;
                                    opacity: 0.7;
                                }
                                100% {
                                    box-shadow: 0 0 0 0 transparent;
                                    opacity: 1;
                                }
                            }
                        `;
                        document.head.appendChild(style);
                    }

                    pulseAnimation();
                }
            });
        },

        // Footer links hover effects
        footerLinksEffects: () => {
            const footerLinks = document.querySelectorAll('.footer-links a');
            
            footerLinks.forEach(link => {
                link.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateX(5px)';
                    this.style.transition = 'all 0.3s ease';
                });

                link.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateX(0)';
                });
            });
        }
    };

    // Inicialização das interações Expertzy
    const initExpertzyInteractions = () => {
        // Aguardar carregamento completo
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initExpertzyInteractions);
            return;
        }

        console.log('🎨 Inicializando interações Expertzy');

        // Executar todas as interações
        Object.values(expertzyInteractions).forEach(interaction => {
            try {
                interaction();
            } catch (error) {
                console.warn('⚠️ Erro na interação Expertzy:', error);
            }
        });

        console.log('✨ Interações Expertzy carregadas');
    };

    // Inicializar
    initExpertzyInteractions();

    // Expor configurações globalmente
    window.ExpertzyConfig = expertzyConfig;
    window.ExpertzyInteractions = expertzyInteractions;

})();