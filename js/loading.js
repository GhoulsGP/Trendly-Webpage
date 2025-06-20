// Verificar si ya existe antes de declarar
if (typeof window.TrendlyLoader === 'undefined') {
    
    class TrendlyLoader {
        constructor() {
            this.isLoading = false;
            this.init();
        }

        init() {
            this.createLoadingOverlay();
            this.interceptNavigation();
        }

        createLoadingOverlay() {
            const existing = document.getElementById('trendly-loader');
            if (existing) {
                existing.remove();
            }

            const overlay = document.createElement('div');
            overlay.id = 'trendly-loader';
            overlay.className = 'loading-overlay fade-out';
            overlay.style.display = 'none';
            
            // Forzar estilos inline para evitar conflictos
            overlay.style.cssText = `
                position: fixed !important;
                top: 0 !important;
                left: 0 !important;
                width: 100% !important;
                height: 100% !important;
                background: rgba(18, 18, 18, 0.8) !important;
                backdrop-filter: blur(10px) !important;
                z-index: 99999 !important;
                display: none !important;
                align-items: center !important;
                justify-content: center !important;
                opacity: 0 !important;
                visibility: hidden !important;
                transition: all 0.3s ease !important;
                pointer-events: none !important;
            `;
            
            overlay.innerHTML = '<div class="loading-spinner"></div>';
            
            document.body.appendChild(overlay);
        }

        show() {
            if (this.isLoading) return;
            
            this.isLoading = true;
            
            const overlay = document.getElementById('trendly-loader');
            if (!overlay) {
                this.createLoadingOverlay();
            }
            
            const loadingOverlay = document.getElementById('trendly-loader');
            
            // Forzar visibilidad con estilos inline
            loadingOverlay.style.display = 'flex';
            loadingOverlay.style.opacity = '1';
            loadingOverlay.style.visibility = 'visible';
            loadingOverlay.style.pointerEvents = 'all';
            loadingOverlay.classList.remove('fade-out');
            
            document.body.style.overflow = 'hidden';
            
            // Auto-hide después de 500ms
            setTimeout(() => {
                this.hide();
            }, 500);
        }

        hide() {
            const overlay = document.getElementById('trendly-loader');
            if (overlay) {
                overlay.classList.add('fade-out');
                overlay.style.opacity = '0';
                overlay.style.visibility = 'hidden';
                overlay.style.pointerEvents = 'none';
                
                setTimeout(() => {
                    overlay.style.display = 'none';
                    this.isLoading = false;
                    document.body.style.overflow = '';
                }, 300);
            } else {
                this.isLoading = false;
                document.body.style.overflow = '';
            }
        }

        interceptNavigation() {
            document.addEventListener('click', (e) => {
                const link = e.target.closest('a');
                if (!link) return;
                
                if (this.isNavigationLink(link) && !this.hasExplicitNoLoading(link)) {
                    e.preventDefault();
                    this.show();
                    
                    setTimeout(() => {
                        window.location.href = link.href;
                    }, 200);
                }
            });
        }

        hasExplicitNoLoading(link) {
            return (
                link.classList.contains('no-loading') ||
                link.hasAttribute('data-bs-toggle') ||
                link.getAttribute('href') === '#' ||
                link.href === '#' ||
                link.href.startsWith('#') ||
                link.href.startsWith('mailto:') ||
                link.href.startsWith('tel:') ||
                link.target === '_blank'
            );
        }

        isNavigationLink(link) {
            const navigationPages = [
                'inicio.php',
                'perfil.php', 
                'messages.php',
                'login.php',
                'register.php',
                'logout.php',
                'explorar.php',
                'perfil_usuario.php'
            ];
            
            const href = link.getAttribute('href') || link.href;
            
            const isNavigationPage = navigationPages.some(page => {
                return href === page || href.endsWith('/' + page) || href.includes('/' + page);
            });
            
            const isSameOrigin = !link.hostname || link.hostname === window.location.hostname;
            
            return isNavigationPage && isSameOrigin;
        }
    }

    // Inicializar solo si no existe
    let trendlyLoader;

    document.addEventListener('DOMContentLoaded', function() {
        if (!window.TrendlyLoader) {
            trendlyLoader = new TrendlyLoader();
            window.TrendlyLoader = trendlyLoader;
        }
    });

    // Funciones globales
    if (typeof window.showLoading === 'undefined') {
        window.showLoading = function() {
            if (window.TrendlyLoader) {
                window.TrendlyLoader.show();
            }
        };
    }

    if (typeof window.hideLoading === 'undefined') {
        window.hideLoading = function() {
            if (window.TrendlyLoader) {
                window.TrendlyLoader.hide();
            }
        };
    }

} else {
    // TrendlyLoader ya existe, no redeclarar
}