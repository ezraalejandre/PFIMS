(function () {
    const themeScript = document.currentScript;
    // Authenticated pages must begin behind the shared loading surface.  The
    // theme script runs in the document head, before the body and deferred
    // module scripts exist, so this prevents a partially-rendered page from
    // flashing while the page-specific data request is still in flight.
    const isLandingPath = window.location.pathname === '/';
    const pageGate = window.PFIMS_PAGE_PRELOADER = window.PFIMS_PAGE_PRELOADER || {
        active: !isLandingPath,
        pending: 0
    };
    if (!isLandingPath) {
        document.documentElement.classList.add('pfims-preload');
        if (window.fetch && !window.fetch.__pfimsPageGateTracked) {
            const originalFetch = window.fetch;
            const trackedFetch = function () {
                if (pageGate.active) pageGate.pending += 1;
                return originalFetch.apply(this, arguments).finally(function () {
                    if (!pageGate.active) return;
                    pageGate.pending = Math.max(0, pageGate.pending - 1);
                    window.dispatchEvent(new CustomEvent('pfims:page-fetch-settled'));
                });
            };
            trackedFetch.__pfimsPageGateTracked = true;
            trackedFetch.__pfimsTracked = true;
            window.fetch = trackedFetch;
        }
    }
    // Authenticated pages include the versioned shared UI script explicitly at
    // the end of the document. Wait until parsing is complete before deciding
    // whether a legacy page (currently the landing page) still needs the
    // fallback; otherwise the head script races the explicit copy and loads an
    // unversioned duplicate first.
    function loadLegacySystemUiIfNeeded() {
        if (window.PFIMS_SYSTEM_UI_LOADED || document.querySelector('script[src*="pfims-system-ui.js"]')) return;
        const systemUi = document.createElement('script');
        systemUi.src = new URL('pfims-system-ui.js', themeScript ? themeScript.src : window.location.href).href;
        systemUi.defer = true;
        document.head.appendChild(systemUi);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadLegacySystemUiIfNeeded, { once: true });
    } else {
        loadLegacySystemUiIfNeeded();
    }
    // Authenticated pages can be kept as visual snapshots in a browser's
    // back/forward cache. Hide the page before it enters that cache. If the
    // snapshot is restored, keep it hidden while a real request verifies the
    // session; logged-out users will then be redirected by Laravel.
    const isAuthenticatedPage = Boolean(document.querySelector('form[action$="/logout"]'));

    if (isAuthenticatedPage) {
        window.addEventListener('pagehide', function () {
            document.documentElement.style.visibility = 'hidden';
        });
    }

    window.addEventListener('pageshow', function (event) {
        if (isAuthenticatedPage && event.persisted) {
            window.location.reload();
        }
    });

    const storageKey = 'pfims_theme';
    const sidebarStorageKey = 'pfims_sidebar_collapsed';

    function applySidebarState(collapsed) {
        document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
        const toggle = document.querySelector('[data-sidebar-toggle]');
        if (toggle) {
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }
    }

    function initializeSidebar() {
        const sidebar = document.querySelector('.sidebar');
        if (!sidebar || document.body.classList.contains('landing-page')) return;

        sidebar.querySelectorAll('nav a').forEach(function (link) {
            const label = link.textContent.trim();
            if (!label) return;
            link.dataset.navLabel = label;
            link.dataset.navShort = label.charAt(0).toUpperCase();
            if (!link.title) link.title = label;
        });

        if (!sidebar.querySelector('[data-sidebar-toggle]')) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sidebar-collapse-toggle';
            button.dataset.sidebarToggle = '';
            button.innerHTML = '<svg aria-hidden="true" viewBox="0 0 24 24" focusable="false"><rect x="3.5" y="4" width="17" height="16" rx="2.5"/><path d="M9 4v16"/><path class="sidebar-toggle-chevron" d="m15 9-3 3 3 3"/></svg>';
            button.addEventListener('click', function () {
                const collapsed = !document.documentElement.classList.contains('sidebar-collapsed');
                localStorage.setItem(sidebarStorageKey, collapsed ? 'true' : 'false');
                applySidebarState(collapsed);
            });
            sidebar.prepend(button);
        }

        applySidebarState(localStorage.getItem(sidebarStorageKey) === 'true');
    }

    function initializeMobileNavDrawer() {
        const sidebar = document.querySelector('.sidebar');
        const headerLeft = document.querySelector('.top-header .left');
        if (!sidebar || !headerLeft || document.body.classList.contains('landing-page')) return;

        const mobileQuery = window.matchMedia('(max-width: 1024px)');
        let lastFocusedElement = null;
        let menuButton = headerLeft.querySelector('[data-mobile-nav-toggle]');
        if (!menuButton) {
            menuButton = document.createElement('button');
            menuButton.type = 'button';
            menuButton.className = 'mobile-nav-toggle';
            menuButton.dataset.mobileNavToggle = '';
            menuButton.setAttribute('aria-label', 'Open navigation menu');
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.setAttribute('aria-controls', 'pfimsMobileNavDrawer');
            menuButton.innerHTML = '<span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span>';
            headerLeft.prepend(menuButton);
        }
        menuButton.classList.add('mobile-nav-toggle');
        sidebar.id = sidebar.id || 'pfimsMobileNavDrawer';

        let backdrop = document.querySelector('[data-mobile-nav-backdrop]');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'mobile-nav-backdrop';
            backdrop.dataset.mobileNavBackdrop = '';
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.appendChild(backdrop);
        }
        backdrop.classList.add('mobile-nav-backdrop');

        function isMobile() { return mobileQuery.matches; }
        function setDrawerAccessibility(open) {
            if (!isMobile()) {
                sidebar.inert = false;
                sidebar.removeAttribute('aria-hidden');
                return;
            }
            sidebar.inert = !open;
            sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
        function setMobileStyles(enabled) {
            if (!enabled) {
                document.documentElement.classList.remove('mobile-nav-open');
                document.body.style.overflow = '';
                menuButton.setAttribute('aria-expanded', 'false');
                menuButton.setAttribute('aria-label', 'Open navigation menu');
                setDrawerAccessibility(false);
                return;
            }
            document.documentElement.classList.remove('mobile-nav-open');
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.setAttribute('aria-label', 'Open navigation menu');
            setDrawerAccessibility(false);
        }
        function closeDrawer(returnFocus) {
            if (!isMobile()) return;
            document.documentElement.classList.remove('mobile-nav-open');
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.setAttribute('aria-label', 'Open navigation menu');
            setDrawerAccessibility(false);
            document.body.style.overflow = '';
            if (returnFocus) menuButton.focus();
        }
        function openDrawer() {
            if (!isMobile()) return;
            lastFocusedElement = document.activeElement;
            document.documentElement.classList.add('mobile-nav-open');
            menuButton.setAttribute('aria-expanded', 'true');
            menuButton.setAttribute('aria-label', 'Close navigation menu');
            setDrawerAccessibility(true);
            document.body.style.overflow = 'hidden';
            const firstLink = sidebar.querySelector('nav a, .bottom-nav a, button:not([data-sidebar-toggle])');
            if (firstLink) firstLink.focus();
        }

        menuButton.addEventListener('click', function () {
            menuButton.getAttribute('aria-expanded') === 'true' ? closeDrawer(true) : openDrawer();
        });
        backdrop.addEventListener('click', function () { closeDrawer(true); });
        sidebar.addEventListener('click', function (event) {
            const link = event.target.closest('a');
            if (link && link.getAttribute('href') !== '#' && !link.classList.contains('nav-parent-toggle') && !link.closest('.nav-parent-toggle')) closeDrawer(false);
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
                closeDrawer(true);
            }
        });
        const handleViewportChange = function () {
            setMobileStyles(isMobile());
            if (!isMobile()) {
                menuButton.setAttribute('aria-expanded', 'false');
                if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') lastFocusedElement.focus({ preventScroll: true });
            }
        };
        if (typeof mobileQuery.addEventListener === 'function') mobileQuery.addEventListener('change', handleViewportChange);
        else mobileQuery.addListener(handleViewportChange);
        setMobileStyles(isMobile());
    }

    function applyTheme(theme) {
        const isDark = theme === 'dark';
        document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
        document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

        document.querySelectorAll('[data-theme-toggle]').forEach(function (toggle) {
            toggle.classList.toggle('active', isDark);
            toggle.setAttribute('aria-checked', String(isDark));
        });

        updateCharts(isDark);
        window.dispatchEvent(new CustomEvent('pfims:themechange', { detail: { theme: theme } }));
    }

    function updateCharts(isDark) {
        if (!window.Chart) return;

        const textColor = isDark ? '#dbe5f3' : '#475569';
        const gridColor = isDark ? 'rgba(148, 163, 184, 0.20)' : 'rgba(71, 85, 105, 0.14)';
        const tooltipBackground = isDark ? '#020617' : '#ffffff';
        const tooltipText = isDark ? '#f8fafc' : '#172033';

        if (Chart.defaults) {
            Chart.defaults.color = textColor;
            if (Chart.defaults.borderColor !== undefined) Chart.defaults.borderColor = gridColor;
        }

        const instances = Chart.instances
            ? (typeof Chart.instances.values === 'function' ? Array.from(Chart.instances.values()) : Object.values(Chart.instances))
            : [];

        instances.forEach(function (chart) {
            if (!chart || !chart.options) return;
            try {
                const plugins = chart.options.plugins = chart.options.plugins || {};
                plugins.legend = plugins.legend || {};
                plugins.legend.labels = plugins.legend.labels || {};
                plugins.legend.labels.color = textColor;
                plugins.title = plugins.title || {};
                plugins.title.color = textColor;
                plugins.tooltip = plugins.tooltip || {};
                plugins.tooltip.backgroundColor = tooltipBackground;
                plugins.tooltip.titleColor = tooltipText;
                plugins.tooltip.bodyColor = tooltipText;
                plugins.tooltip.borderColor = gridColor;
                plugins.tooltip.borderWidth = 1;

                Object.values(chart.options.scales || {}).forEach(function (scale) {
                    if (Array.isArray(scale)) return;
                    scale.ticks = scale.ticks || {};
                    scale.grid = scale.grid || {};
                    scale.title = scale.title || {};
                    scale.ticks.color = textColor;
                    scale.grid.color = gridColor;
                    scale.grid.borderColor = gridColor;
                    scale.title.color = textColor;
                });
                chart.update('none');
            } catch (error) {
                console.warn('Unable to refresh a chart for the selected theme.', error);
            }
        });
    }

    window.toggleDarkMode = function () {
        const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        localStorage.setItem(storageKey, nextTheme);
        applyTheme(nextTheme);
    };

    applyTheme(localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light');
    applySidebarState(localStorage.getItem(sidebarStorageKey) === 'true');
    document.addEventListener('DOMContentLoaded', function () {
        initializeSidebar();
        initializeMobileNavDrawer();
        applyTheme(localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light');
        window.setTimeout(function () {
            updateCharts(document.documentElement.dataset.theme === 'dark');
        }, 0);
    });
})();
