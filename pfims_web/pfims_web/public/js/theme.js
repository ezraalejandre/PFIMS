// Set up the theme page actions.
(function () {
    window.pfimsRememberSessionExpiry = function (payload) {
        // Return early if the value does not equal inactivity or the value is missing or false.
        if (payload?.reason !== 'inactivity' || !(Number(payload.inactivity_seconds) > 0)) return;
        // Try this action and handle any errors below.
        try {
            window.sessionStorage.setItem('pfims-inactivity-notice', JSON.stringify({ seconds: Number(payload.inactivity_seconds), at: Date.now() }));
        } catch (error) { /* The server flash remains available when browser storage is blocked. */ }
    };
    const themeScript = document.currentScript;
    // Hide signed-in pages until their first data requests finish.
    const isLandingPath = window.location.pathname === '/';
    const pageGate = window.PFIMS_PAGE_PRELOADER = window.PFIMS_PAGE_PRELOADER || {
        active: !isLandingPath,
        pending: 0
    };
    // Run this step if the is landing path is missing or false.
    if (!isLandingPath) {
        document.documentElement.classList.add('pfims-preload');
        // Run this step if the fetch is set and the pfims page gate tracked is missing or false.
        if (window.fetch && !window.fetch.__pfimsPageGateTracked) {
            const originalFetch = window.fetch;
            // Get the tracked fetch.
            const trackedFetch = function (input, options) {
                const tracked = pageGate.active;
                // Run this step if the tracked is set.
                if (tracked) pageGate.pending += 1;
                // Initial read requests must settle even if an optional endpoint stalls.
                const method = options?.method || (typeof input === 'object' && input.method) || 'GET';
                // Run this step if the tracked is set and the to upper case equals GET.
                if (tracked && method.toUpperCase() === 'GET') {
                    const callerSignal = options?.signal || (typeof input === 'object' && input.signal);
                    const timeoutSignal = AbortSignal.timeout(15000);
                    options = Object.assign({}, options, { signal: callerSignal ? AbortSignal.any([callerSignal, timeoutSignal]) : timeoutSignal });
                }
                // Run the finally action.
                return originalFetch.call(this, input, options).finally(function () {
                    // Return early if the tracked is missing or false.
                    if (!tracked) return;
                    pageGate.pending = Math.max(0, pageGate.pending - 1);
                    window.dispatchEvent(new CustomEvent('pfims:page-fetch-settled'));
                });
            };
            trackedFetch.__pfimsPageGateTracked = true;
            trackedFetch.__pfimsTracked = true;
            window.fetch = trackedFetch;
        }
    }
    // Wait for the page to load before adding the shared UI script.
    // This avoids loading a second copy.
    function loadLegacySystemUiIfNeeded() {
        // Return early if the pfims system ui loaded is set or the query selector check matches.
        if (window.PFIMS_SYSTEM_UI_LOADED || document.querySelector('script[src*="pfims-system-ui.js"]')) return;
        const systemUi = document.createElement('script');
        systemUi.src = new URL('pfims-system-ui.js', themeScript ? themeScript.src : window.location.href).href;
        systemUi.defer = true;
        document.head.appendChild(systemUi);
    }

    // Run this step if the ready state equals loading.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', loadLegacySystemUiIfNeeded, { once: true });
    } else {
        loadLegacySystemUiIfNeeded();
    }
    // Hide saved pages before the browser caches them.
    // Reload them when restored so the server checks the login again.
    const isAuthenticatedPage = Boolean(document.querySelector('form[action$="/logout"]'));

    // Run this step if the is authenticated page is set.
    if (isAuthenticatedPage) {
        // Handle the pagehide event.
        window.addEventListener('pagehide', function () {
            document.documentElement.style.visibility = 'hidden';
        });
    }

    // Handle the pageshow event.
    window.addEventListener('pageshow', function (event) {
        // Run this step if the is authenticated page is set and the saved is set.
        if (isAuthenticatedPage && event.persisted) {
            window.location.reload();
        }
    });

    const storageKey = 'pfims_theme';
    const sidebarStorageKey = 'pfims_sidebar_collapsed';

    // Apply sidebar state.
    function applySidebarState(collapsed) {
        document.documentElement.classList.toggle('sidebar-collapsed', collapsed);
        const toggle = document.querySelector('[data-sidebar-toggle]');
        // Run this step if the toggle is set.
        if (toggle) {
            toggle.setAttribute('aria-expanded', String(!collapsed));
            toggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            toggle.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        }
    }

    // Set up sidebar.
    function initializeSidebar() {
        const sidebar = document.querySelector('.sidebar');
        // Return early if the sidebar is missing or false or the contains check matches.
        if (!sidebar || document.body.classList.contains('landing-page')) return;

        // Process each link.
        sidebar.querySelectorAll('nav a').forEach(function (link) {
            const label = link.textContent.trim();
            // Return early if the label is missing or false.
            if (!label) return;
            link.dataset.navLabel = label;
            link.dataset.navShort = label.charAt(0).toUpperCase();
            // Run this step if the title is missing or false.
            if (!link.title) link.title = label;
        });

        // Run this step if the query selector is missing or false.
        if (!sidebar.querySelector('[data-sidebar-toggle]')) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'sidebar-collapse-toggle';
            button.dataset.sidebarToggle = '';
            button.innerHTML = '<svg aria-hidden="true" viewBox="0 0 24 24" focusable="false"><rect x="3.5" y="4" width="17" height="16" rx="2.5"/><path d="M9 4v16"/><path class="sidebar-toggle-chevron" d="m15 9-3 3 3 3"/></svg>';
            // Handle the click event.
            button.addEventListener('click', function () {
                const collapsed = !document.documentElement.classList.contains('sidebar-collapsed');
                localStorage.setItem(sidebarStorageKey, collapsed ? 'true' : 'false');
                applySidebarState(collapsed);
            });
            sidebar.prepend(button);
        }

        applySidebarState(localStorage.getItem(sidebarStorageKey) === 'true');
    }

    // Set up mobile nav drawer.
    function initializeMobileNavDrawer() {
        const sidebar = document.querySelector('.sidebar');
        const headerLeft = document.querySelector('.top-header .left');
        // Return early if the sidebar is missing or false or the header left is missing or false or the contains check matches.
        if (!sidebar || !headerLeft || document.body.classList.contains('landing-page')) return;

        const mobileQuery = window.matchMedia('(max-width: 1024px)');
        let lastFocusedElement = null;
        let menuButton = headerLeft.querySelector('[data-mobile-nav-toggle]');
        // Run this step if the menu button is missing or false.
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
        // Run this step if the backdrop is missing or false.
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.className = 'mobile-nav-backdrop';
            backdrop.dataset.mobileNavBackdrop = '';
            backdrop.setAttribute('aria-hidden', 'true');
            document.body.appendChild(backdrop);
        }
        backdrop.classList.add('mobile-nav-backdrop');

        // Check the mobile condition.
        function isMobile() { return mobileQuery.matches; }
        // Set drawer accessibility.
        function setDrawerAccessibility(open) {
            // Run this step if the is mobile is missing or false.
            if (!isMobile()) {
                sidebar.inert = false;
                sidebar.removeAttribute('aria-hidden');
                return;
            }
            sidebar.inert = !open;
            sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
        }
        // Set mobile styles.
        function setMobileStyles(enabled) {
            // Run this step if the enabled is missing or false.
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
        // Close drawer.
        function closeDrawer(returnFocus) {
            // Return early if the is mobile is missing or false.
            if (!isMobile()) return;
            document.documentElement.classList.remove('mobile-nav-open');
            menuButton.setAttribute('aria-expanded', 'false');
            menuButton.setAttribute('aria-label', 'Open navigation menu');
            setDrawerAccessibility(false);
            document.body.style.overflow = '';
            // Run this step if the return focus is set.
            if (returnFocus) menuButton.focus();
        }
        // Open drawer.
        function openDrawer() {
            // Return early if the is mobile is missing or false.
            if (!isMobile()) return;
            lastFocusedElement = document.activeElement;
            document.documentElement.classList.add('mobile-nav-open');
            menuButton.setAttribute('aria-expanded', 'true');
            menuButton.setAttribute('aria-label', 'Close navigation menu');
            setDrawerAccessibility(true);
            document.body.style.overflow = 'hidden';
            const firstLink = sidebar.querySelector('nav a, .bottom-nav a, button:not([data-sidebar-toggle])');
            // Run this step if the first link is set.
            if (firstLink) firstLink.focus();
        }

        // Handle the click event.
        menuButton.addEventListener('click', function () {
            menuButton.getAttribute('aria-expanded') === 'true' ? closeDrawer(true) : openDrawer();
        });
        // Handle the click event.
        backdrop.addEventListener('click', function () { closeDrawer(true); });
        // Handle the click event.
        sidebar.addEventListener('click', function (event) {
            const link = event.target.closest('a');
            // Run this step if the value checks match.
            if (link && link.getAttribute('href') !== '#' && !link.classList.contains('nav-parent-toggle') && !link.closest('.nav-parent-toggle')) closeDrawer(false);
        });
        // Handle the keydown event.
        document.addEventListener('keydown', function (event) {
            // Run this step if the key equals Escape and the get attribute equals true.
            if (event.key === 'Escape' && menuButton.getAttribute('aria-expanded') === 'true') {
                closeDrawer(true);
            }
        });
        // Handle viewport change.
        const handleViewportChange = function () {
            setMobileStyles(isMobile());
            // Run this step if the is mobile is missing or false.
            if (!isMobile()) {
                menuButton.setAttribute('aria-expanded', 'false');
                // Run this step if the last focused element is set and the focus equals function.
                if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') lastFocusedElement.focus({ preventScroll: true });
            }
        };
        // Run this step if the add event listener equals function.
        if (typeof mobileQuery.addEventListener === 'function') mobileQuery.addEventListener('change', handleViewportChange);
        else mobileQuery.addListener(handleViewportChange);
        setMobileStyles(isMobile());
    }

    // Apply the selected page theme.
    function applyTheme(theme) {
        const isDark = theme === 'dark';
        document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
        document.documentElement.style.colorScheme = isDark ? 'dark' : 'light';

        // Process each toggle.
        document.querySelectorAll('[data-theme-toggle]').forEach(function (toggle) {
            toggle.classList.toggle('active', isDark);
            toggle.setAttribute('aria-checked', String(isDark));
        });

        updateCharts(isDark);
        window.dispatchEvent(new CustomEvent('pfims:themechange', { detail: { theme: theme } }));
    }

    // Update charts.
    function updateCharts(isDark) {
        // Return early if the chart is missing or false.
        if (!window.Chart) return;

        const textColor = isDark ? '#dbe5f3' : '#475569';
        const gridColor = isDark ? 'rgba(148, 163, 184, 0.20)' : 'rgba(71, 85, 105, 0.14)';
        const tooltipBackground = isDark ? '#020617' : '#ffffff';
        const tooltipText = isDark ? '#f8fafc' : '#172033';

        // Run this step if the defaults is set.
        if (Chart.defaults) {
            Chart.defaults.color = textColor;
            // Run this step if the border color does not equal the undefined.
            if (Chart.defaults.borderColor !== undefined) Chart.defaults.borderColor = gridColor;
        }

        const instances = Chart.instances
            ? (typeof Chart.instances.values === 'function' ? Array.from(Chart.instances.values()) : Object.values(Chart.instances))
            : [];

        // Process each chart.
        instances.forEach(function (chart) {
            // Return early if the chart is missing or false or the config is missing or false or the options is missing or false.
            if (!chart || !chart.config || !chart.config.options) return;
            // Try this action and handle any errors below.
            try {
                const options = chart.config.options;
                const plugins = options.plugins = options.plugins || {};
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

                // Process each scale.
                Object.values(options.scales || {}).forEach(function (scale) {
                    // Return early if the is array check matches.
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
    // Handle the dom content loaded event.
    document.addEventListener('DOMContentLoaded', function () {
        initializeSidebar();
        initializeMobileNavDrawer();
        applyTheme(localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light');
        // Run this action after the delay.
        window.setTimeout(function () {
            updateCharts(document.documentElement.dataset.theme === 'dark');
        }, 0);
    });
})();
