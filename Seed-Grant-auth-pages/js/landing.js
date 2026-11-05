(() => {
    const root = document.documentElement;
    root.classList.add('js-enabled');
    const themeToggle = document.querySelector('.theme-toggle');
    const storageKey = 'seed-grant-theme';
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

    function applyTheme(theme, persist = false) {
        const nextTheme = theme === 'dark' ? 'dark' : 'light';
        root.dataset.theme = nextTheme;

        if (themeToggle) {
            themeToggle.setAttribute('aria-pressed', String(nextTheme === 'dark'));
            themeToggle.setAttribute('aria-label', nextTheme === 'dark' ? 'Switch to light mode' : 'Switch to dark mode');
        }

        if (persist) {
            try {
                window.localStorage.setItem(storageKey, nextTheme);
            } catch (error) {
                // Keep the current page usable when storage is unavailable.
            }
        }
    }

    applyTheme(root.dataset.theme || 'light');

    themeToggle?.addEventListener('click', () => {
        applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark', true);
    });

    const logo = document.querySelector('[data-challenge-logo]');
    const logoArt = logo?.querySelector('[data-logo-art]');

    async function showStaticLogo() {
        if (!logoArt || logoArt.dataset.static === 'true') {
            return;
        }

        try {
            const response = await fetch('assets/itech_seed_grant_challenge_static_logo.svg', {
                credentials: 'same-origin',
                cache: 'force-cache',
            });

            if (!response.ok) {
                return;
            }

            const svgText = await response.text();
            const parser = new DOMParser();
            const svgDocument = parser.parseFromString(svgText, 'image/svg+xml');
            const svg = svgDocument.documentElement;

            if (svg.localName !== 'svg' || svgDocument.querySelector('parsererror')) {
                return;
            }

            const importedSvg = document.importNode(svg, true);
            importedSvg.setAttribute('aria-label', 'iTech Seed Grant Challenge — seed it. build it. launch it.');
            importedSvg.setAttribute('role', 'img');
            logoArt.replaceChildren(importedSvg);
            logoArt.dataset.static = 'true';
        } catch (error) {
            // The animated SVG has already reached its complete, static final frame.
        }
    }

    if (logoArt) {
        if (reducedMotion.matches) {
            showStaticLogo();
        } else {
            let fallbackTimer;
            const onAnimationEnd = (event) => {
                if (event.animationName === 'lift' && event.target.classList?.contains('lift')) {
                    window.clearTimeout(fallbackTimer);
                    logoArt.removeEventListener('animationend', onAnimationEnd, true);
                    showStaticLogo();
                }
            };

            logoArt.addEventListener('animationend', onAnimationEnd, true);

            // Fallback if a browser omits animation events for an embedded SVG animation.
            fallbackTimer = window.setTimeout(showStaticLogo, 8500);
        }
    }

    const revealElements = document.querySelectorAll('[data-reveal]');
    if (reducedMotion.matches || !('IntersectionObserver' in window)) {
        revealElements.forEach((element) => element.classList.add('is-visible'));
    } else {
        const observer = new IntersectionObserver((entries, currentObserver) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    currentObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -24px 0px' });

        revealElements.forEach((element) => observer.observe(element));
    }
})();
