(function () {
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
            button.innerHTML = '<span aria-hidden="true">‹</span>';
            button.addEventListener('click', function () {
                const collapsed = !document.documentElement.classList.contains('sidebar-collapsed');
                localStorage.setItem(sidebarStorageKey, collapsed ? 'true' : 'false');
                applySidebarState(collapsed);
            });
            sidebar.prepend(button);
        }

        applySidebarState(localStorage.getItem(sidebarStorageKey) === 'true');
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
        applyTheme(localStorage.getItem(storageKey) === 'dark' ? 'dark' : 'light');
        window.setTimeout(function () {
            updateCharts(document.documentElement.dataset.theme === 'dark');
        }, 0);
    });
})();
