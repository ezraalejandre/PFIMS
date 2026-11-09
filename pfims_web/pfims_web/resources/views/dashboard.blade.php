{{-- Prepare the values used by this page section. --}}
@php
    $portal = $portal ?? 'admin';
    $maps = [
        'admin' => [
            'dashboard' => '/dashboard',
            'projects' => '/projects',
            'finance' => '/finance',
            'inventory' => '/inventory',
            'suppliers' => '/suppliers',
            'reports' => '/reports',
            'settings' => '/settings',
            'notifications' => '/notifications',
            'profile' => '/profile',
        ],
        'accounting' => [
            'dashboard' => '/adashboard',
            'finance' => '/afinance',
            'reports' => '/areports',
            'settings' => '/asettings',
            'notifications' => '/anotifications',
            'profile' => '/aprofile',
        ],
        'operations' => [
            'dashboard' => '/odashboard',
            'projects' => '/oprojects',
            'inventory' => '/oinventory',
            'suppliers' => '/osuppliers',
            'reports' => '/oreports',
            'settings' => '/osettings',
            'notifications' => '/onotifications',
            'profile' => '/oprofile',
        ],
    ];
    $navigation = [
        'dashboard' => ['label' => 'DASHBOARD', 'icon' => 'dashboard.png'],
        'projects' => ['label' => 'PROJECTS', 'icon' => 'projects.png'],
        'finance' => ['label' => 'FINANCE', 'icon' => 'finance.png'],
        'inventory' => ['label' => 'INVENTORY', 'icon' => 'inventory.png'],
        'reports' => ['label' => 'REPORTS', 'icon' => 'folder.svg'],
    ];
    $portalTitles = [
        'admin' => 'Admin',
        'accounting' => 'Accounting',
        'operations' => 'Operations',
    ];
    $links = $maps[$portal];
    $portalTitle = $portalTitles[$portal];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $portalTitle }} Dashboard - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/centralized-dashboard.css') }}">
    <link rel="stylesheet" href="{{ asset('css/'.$portal.'.css') }}">
    <link rel="stylesheet" href="{{ asset('css/centralized-predictive-analytics.css') }}?v={{ filemtime(public_path('css/centralized-predictive-analytics.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/table-scroll-fade.js') }}" defer></script>
    {{-- Set up the actions used by this page section. --}}
    <script data-login-epoch="{{ session('navigation_epoch', 'initial') }}" src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}" defer></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/modal-policy.js') }}?v={{ filemtime(public_path('js/modal-policy.js')) }}"></script>
    {{-- Set the styles used by this page section. --}}
    <style id="pfims-session-privacy">html { visibility:hidden; }</style>
    {{-- Set up the actions used by this page section. --}}
    <script data-navigation-granted="{{ session('_page_navigation_granted', false) ? '1' : '0' }}" data-session-epoch="{{ session('navigation_epoch', '') }}" src="{{ asset('js/protected-navigation.js') }}?v={{ filemtime(public_path('js/protected-navigation.js')) }}"></script>
</head>
<body class="dashboard-page" data-portal="{{ $portal }}" data-pfims-wait-for-ready="true">
    {{-- Show the page header. --}}
    <header class="top-header">
        <div class="left">
            <img src="{{ asset('images/logo.jpg') }}" alt="PFIMS logo">
            <div class="brand-text">
                PFIMS
                <small>E.V. Catapang Design-Construction &amp; Supply</small>
            </div>
        </div>
        <div class="right">
            <span class="header-clock" aria-label="Current Philippine date and time">Loading date and time…</span>
            <a href="{{ url($links['notifications']) }}">
                <img src="{{ asset('images/notif.jpg') }}" alt="" aria-hidden="true">
                <span class="sr-only">Open alerts</span>
            </a>
            <a href="{{ url($links['profile']) }}">
                <img class="profile-avatar" src="{{ asset('images/user.jpg') }}" alt="" aria-hidden="true">
                <span>{{ auth()->user()->name === 'Administrator' ? 'Admin' : auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    {{-- Show the side panel. --}}
    <aside class="sidebar">
        {{-- Show the page navigation. --}}
        <nav aria-label="Primary navigation">
            <ul>
                {{-- Show each item. --}}
                @foreach($navigation as $key => $item)
                    {{-- Show this section when its display check passes. --}}
                    @if(isset($links[$key]))
                        <li class="{{ $key === 'dashboard' ? 'active' : '' }} {{ in_array($key, ['projects','finance','inventory'], true) ? 'nav-parent' : '' }}">
                            <a href="{{ in_array($key, ['projects','finance','inventory'], true) ? '#' : url($links[$key]) }}" class="{{ in_array($key, ['projects','finance','inventory'], true) ? 'nav-parent-toggle' : '' }}" aria-expanded="false">
                                <img src="{{ asset('images/'.$item['icon']) }}" alt="" class="nav-link-icon" aria-hidden="true">
                                {{ $item['label'] }} @if(in_array($key, ['projects','finance','inventory'], true))<span class="nav-chevron" aria-hidden="true">▾</span>@endif
                            </a>
                            {{-- Show this section when its display check passes. --}}
                            @if($key === 'projects')
                                <div class="nav-dropdown">
                                    <a href="{{ url($links[$key]) }}">Project Records</a>
                                    {{-- Show this section when its display check passes. --}}
                                    @if($portal === 'admin')
                                        <a href="{{ url('/ml-dashboard-test') }}?section=predictive">Project Cost Prediction</a>
                                    @endif
                                </div>
                            @elseif($key === 'finance')
                                <div class="nav-dropdown">
                                    <a href="{{ url($links[$key]) }}">Expenses</a>
                                    <a href="{{ url($links[$key]) }}?section=budgets">Budgets &amp; Contracts</a>
                                    <a href="{{ url($links[$key]) }}?section=ar-ap">AR / AP</a>
                                    <a href="{{ url($links[$key]) }}?section=cash-position">Cash Asset</a>
                                    <a href="{{ url($links[$key]) }}?section=equipment">Equipment</a>
                                    <a href="{{ url($links[$key]) }}?section=bonds">Bonds</a>
                                    <a href="{{ url('/ml-dashboard-test') }}?section=budget-comparison">Budget-Spending Comparison</a>
                                </div>
                            @elseif($key === 'inventory')
                                <div class="nav-dropdown"><a href="{{ url($links[$key]) }}">Items</a><a href="{{ url($portal === 'operations' ? '/osuppliers' : '/suppliers') }}">Suppliers</a><a href="{{ url($links[$key]) }}?section=transactions">Transactions</a><a href="{{ url('/ml-dashboard-test') }}?section=material-projection">Material Projection</a></div>
                            @endif
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li>
                    <a href="{{ url($links['settings']) }}">
                        <img src="{{ asset('images/settings.png') }}" alt="" class="nav-icon" aria-hidden="true">
                        Settings
                    </a>
                </li>
                <li class="logout">
                    {{-- Collect the details entered in this form. --}}
                    <form action="{{ url('/logout') }}" method="POST">
                        @csrf
                        <button type="submit">
                            <img src="{{ asset('images/logout.png') }}" alt="" class="nav-icon" aria-hidden="true">
                            Log out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>

    {{-- Show the main page content. --}}
    <main class="main-content">
        {{-- Show the page section. --}}
        <section class="dashboard-page-header">
            <div class="dashboard-title-block">
                <h1>{{ strtoupper($portalTitle) }} DASHBOARD</h1>
            </div>
            <div class="dashboard-heading-actions">
            </div>
        </section>

        {{-- Show the overview panel. --}}
        <section id="overviewPanel" class="dashboard-tab-panel active" role="tabpanel" aria-labelledby="overviewTab">
        {{-- Show the notice section. --}}
        <div class="notice" id="notice" role="alert" hidden></div>

        {{-- Show the page section. --}}
        <section class="panel filters" aria-label="Dashboard filters"@if($portal === 'accounting') data-filter-description="Filters update the budget and expenses graph and matching records."@endif>
            <label>
                Search
                <input id="search" type="search" maxlength="100" placeholder="Project, client, manager, or phase">
            </label>
            <label>
                Project status
                <select id="status"><option value="">All statuses</option></select>
            </label>
            <label>
                Stock status
                <select id="stockStatus"><option value="">All stock states</option></select>
            </label>
            <div class="dashboard-period-filter" role="group" aria-label="Dashboard reporting period">
                <button type="button" class="active" data-dashboard-period="all" aria-pressed="true">All</button>
                <button type="button" data-dashboard-period="monthly" aria-pressed="false">Monthly</button>
                <button type="button" data-dashboard-period="yearly" aria-pressed="false">Yearly</button>
            </div>
            <button id="clear" class="btn-secondary" type="button">Clear filters</button>
        </section>

        {{-- Show this section when its display check passes. --}}
        @if($portal !== 'accounting')
        <h2 class="dashboard-section-title">Projects Performance</h2>
        {{-- Show the kpis. --}}
        <section class="kpis" id="kpis" aria-label="Dashboard key performance indicators"></section>
        @endif

        {{-- Show the page section. --}}
        <section class="chart-grid{{ $portal !== 'admin' ? ' single-chart' : '' }}" aria-label="Dashboard charts">
            {{-- Show this section when its display check passes. --}}
            @if($portal !== 'accounting')
            <article class="panel chart-card">
                <h2>Completion trend</h2>
                <p>Average current completion of projects started in each month.</p>
                {{-- Show the completion chart section. --}}
                <div class="chart"><canvas id="completionChart"></canvas></div>
            </article>
            @endif
            {{-- Show this section when its display check passes. --}}
            @if($portal !== 'operations')
            <article class="panel chart-card budget-panel">
                <h2>Budget vs recorded expenses</h2>
                <p>Cumulative allocation and finance-ledger spending.</p>
                {{-- Show the budget chart section. --}}
                <div class="chart"><canvas id="budgetChart"></canvas></div>
            </article>
            @endif
        </section>
        {{-- Show the page section. --}}
        <section class="panel content-card project-panel">
            <div class="panel-heading">
                <div>
                    <h2>Matching projects</h2>
                    <p id="projectCount">Loading…</p>
                </div>
            </div>
            <div class="table-wrap table-wrapper">
                {{-- Show the records in a table. --}}
                <table>
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Manager</th>
                            <th>Phase</th>
                            <th>Project Status</th>
                            <th>Started</th>
                            <th>Estimated End</th>
                            <th>Workers</th>
                            <th>Completion</th>
                            <th>Budget</th>
                            <th>Actual</th>
                        </tr>
                    </thead>
                    <tbody id="projectBody">
                        <tr><td colspan="11">Loading dashboard…</td></tr>
                    </tbody>
                </table>
            </div>
            {{-- Show the dashboard pagination section. --}}
            <div class="pagination-wrapper" id="dashboardPagination">
                <div class="rows-info">
                    Rows per page
                    <select id="pageSize" aria-label="Dashboard rows per page">
                        <option value="5" selected>5</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span id="dashboardRange">Total: 0 projects</span>
                </div>
                {{-- Show the dashboard pagination links section. --}}
                <div class="pagination-links" id="dashboardPaginationLinks" aria-label="Dashboard table pagination"></div>
            </div>
        </section>
        </section>

    </main>

    {{-- Show the project detail modal section. --}}
    <div class="dashboard-modal" id="projectDetailModal" hidden>
        {{-- Show the page section. --}}
        <section class="dashboard-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="detailProjectName" aria-describedby="detailProjectClient">
            {{-- Show the page header. --}}
            <header class="dashboard-modal-header">
                <div>
                    <span class="eyebrow">PROJECT DETAILS</span>
                    <h2 id="detailProjectName">Project</h2>
                    <p id="detailProjectClient">Client</p>
                </div>
                <button class="dashboard-modal-close" id="closeProjectDetailButton" type="button" aria-label="Close project details">×</button>
            </header>
            <div class="dashboard-project-details">
                {{-- Show the detail project manager section. --}}
                <div class="dashboard-detail-item"><span>Project manager</span><strong id="detailProjectManager">—</strong></div>
                {{-- Show the detail phase section. --}}
                <div class="dashboard-detail-item"><span>Phase</span><strong id="detailPhase">—</strong></div>
                {{-- Show the detail status section. --}}
                <div class="dashboard-detail-item"><span>Project Status</span><strong id="detailStatus">—</strong></div>
                {{-- Show the detail completion section. --}}
                <div class="dashboard-detail-item"><span>Completion</span><strong id="detailCompletion">—</strong></div>
                {{-- Show the detail start date section. --}}
                <div class="dashboard-detail-item"><span>Start date</span><strong id="detailStartDate">—</strong></div>
                {{-- Show the detail estimated end section. --}}
                <div class="dashboard-detail-item"><span>Estimated end</span><strong id="detailEstimatedEnd">—</strong></div>
                {{-- Show the detail actual end section. --}}
                <div class="dashboard-detail-item"><span>Actual end</span><strong id="detailActualEnd">—</strong></div>
                {{-- Show the detail duration section. --}}
                <div class="dashboard-detail-item"><span>Planned duration</span><strong id="detailDuration">—</strong></div>
                {{-- Show the detail workers section. --}}
                <div class="dashboard-detail-item"><span>Assigned workers</span><strong id="detailWorkers">—</strong></div>
                {{-- Show the detail budget section. --}}
                <div class="dashboard-detail-item"><span>Budget</span><strong id="detailBudget">—</strong></div>
                {{-- Show the detail actual section. --}}
                <div class="dashboard-detail-item"><span>Recorded actual</span><strong id="detailActual">—</strong></div>
                {{-- Show the detail variance section. --}}
                <div class="dashboard-detail-item"><span>Budget difference</span><strong id="detailVariance">—</strong></div>
            </div>
            {{-- Show the page footer. --}}
            <footer class="dashboard-modal-footer">
                <button class="btn-secondary" id="closeProjectDetailFooter" type="button">Close</button>
                {{-- Show this section when its display check passes. --}}
                @if(isset($links['projects']))
                    <a class="btn-refresh dashboard-view-project" id="viewProjectLink" href="{{ url($links['projects']) }}">View project</a>
                @endif
            </footer>
        </section>
    </div>

    {{-- Set up the actions used by this page section. --}}
    <script>
        // Set up the dashboard page actions.
        (function () {
            const state = { data: null, page: 1, timer: null, loadedOptions: false, period: 'all' };
            const controls = {
                search: document.getElementById('search'),
                status: document.getElementById('status'),
                stock_status: document.getElementById('stockStatus')
            };
            const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            })[character]);
            const money = value => new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(Number(value || 0));

            // Get the active filters.
            function activeFilters() {
                const filters = Object.fromEntries(Object.entries(controls)
                    .map(([key, element]) => [key, element.value.trim()])
                    .filter(([, value]) => value));
                // Run this step if the period does not equal all.
                if (state.period !== 'all') {
                    const parts = Object.fromEntries(new Intl.DateTimeFormat('en', {
                        timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit'
                    }).formatToParts(new Date()).filter(part => part.type !== 'literal').map(part => [part.type, part.value]));
                    const year = Number(parts.year);
                    const month = Number(parts.month);
                    filters.start_date = state.period === 'yearly'
                        ? `${year}-01-01`
                        : `${year}-${String(month).padStart(2, '0')}-01`;
                    filters.end_date = state.period === 'yearly'
                        ? `${year}-12-31`
                        : `${year}-${String(month).padStart(2, '0')}-${String(new Date(Date.UTC(year, month, 0)).getUTCDate()).padStart(2, '0')}`;
                }
                return filters;
            }

            // Show error.
            function showError(message) {
                const notice = document.getElementById('notice');
                notice.textContent = message;
                notice.hidden = false;
                // Run this action after the delay.
                window.setTimeout(() => { notice.hidden = true; }, 5000);
            }

            // Load dashboard.
            async function loadDashboard() {
                // Try this action and handle any errors below.
                try {
                    const query = new URLSearchParams(activeFilters());
                    const response = await fetch('/api/dashboard?' + query, {
                        headers: { Accept: 'application/json' }
                    });
                    const data = await response.json();

                    // Run this step if the response ok is missing or false.
                    if (!response.ok) {
                        throw new Error(data.errors
                            ? Object.values(data.errors).flat().join(' ')
                            : (data.message || 'Dashboard request failed.'));
                    }

                    state.data = data;
                    // Run this step if the loaded options is missing or false.
                    if (!state.loadedOptions) {
                        fillOptions(controls.status, data.filter_options.statuses.map(value => ({ value, label: value })));
                        fillOptions(controls.stock_status, data.filter_options.stock_statuses.map(value => ({ value, label: value })));
                        state.loadedOptions = true;
                    }
                    state.page = 1;
                    renderDashboard();
                } catch (error) {
                    showError(error.message);
                } finally {
                    document.documentElement.dataset.pfimsPageReady = 'true';
                    document.dispatchEvent(new CustomEvent('pfims:page-ready'));
                }
            }

            // Get the fill options.
            function fillOptions(element, items) {
                items.forEach(item => element.insertAdjacentHTML(
                    'beforeend',
                    `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`
                ));
            }

            const dashboardActionLinks = @json(['projects' => isset($links['projects']) ? url($links['projects']) : null, 'inventory' => isset($links['inventory']) ? url($links['inventory']) : null]);

            // Show dashboard.
            function renderDashboard() {
                const kpis = document.getElementById('kpis');
                // Run this step if the kpis is set.
                if (kpis) kpis.innerHTML = state.data.stat_cards.map(card => `
                    <article class="kpi-card" data-priority="${escapeHtml(card.badge_type || 'neutral')}">
                        <span>${escapeHtml(card.label)}</span>
                        <strong>${escapeHtml(card.value)}</strong>
                        <small>${escapeHtml(card.subtitle)}</small>
                        ${card.badge ? `<b class="${escapeHtml(card.badge_type)}">${escapeHtml(card.badge)}</b>` : ''}
                        ${card.action ? `<p class="kpi-decision">${escapeHtml(card.action)}</p>` : ''}
                        ${dashboardActionLinks[card.action_module] ? `<a class="kpi-action-link" href="${escapeHtml(dashboardActionLinks[card.action_module])}">${escapeHtml(card.action_label)} →</a>` : ''}
                    </article>
                `).join('');

                // Run this step if the get element by id check matches.
                if (document.getElementById('completionChart')) {
                    renderChart('completionChart', 'line', state.data.completion_trend.months, [
                        { label: 'Completion %', values: state.data.completion_trend.values }
                    ]);
                }
                // Run this step if the get element by id check matches.
                if (document.getElementById('budgetChart')) {
                    renderChart('budgetChart', 'bar', state.data.budget_vs_expense.months, [
                        { label: 'Budget', values: state.data.budget_vs_expense.allocated_budget },
                        { label: 'Expenses', values: state.data.budget_vs_expense.expenses }
                    ]);
                }
                renderTable();
            }

            const dashboardCharts = {};
            // Show chart.
            function renderChart(id, type, labels, series) {
                const canvas = document.getElementById(id);
                // Return early if the canvas is missing or false.
                if (!canvas) return;
                // Run this step if the dashboard charts id is set.
                if (dashboardCharts[id]) dashboardCharts[id].destroy();
                const colors = ['#f08a1a', '#2563eb', '#16a34a', '#9333ea'];
                dashboardCharts[id] = new Chart(canvas, {
                    type,
                    data: { labels, datasets: series.map((item, index) => ({
                        label: item.label, data: item.values.map(Number), borderColor: colors[index],
                        backgroundColor: type === 'doughnut' ? colors : (type === 'line' ? colors[index] + '28' : colors[index]),
                        fill: type === 'line', tension: .35, pointRadius: 4, pointHoverRadius: 7, borderWidth: 2
                    })) },
                    options: { responsive: true, maintainAspectRatio: false, interaction: { mode: type === 'doughnut' ? 'nearest' : 'index', intersect: type === 'doughnut' },
                        plugins: { legend: { position: 'bottom' }, tooltip: { enabled: true } }, scales: type === 'doughnut' ? {} : { y: { beginAtZero: true } } }
                });
            }

            // Show table.
            function renderTable() {
                // Shared pagination normalization can dispatch a change event
                // before the initial dashboard request has completed.
                if (!state.data) return;
                const size = Number(document.getElementById('pageSize').value);
                const rows = state.data.projects || [];
                const pages = Math.max(Math.ceil(rows.length / size), 1);
                state.page = Math.min(state.page, pages);
                const visibleRows = rows.slice((state.page - 1) * size, state.page * size);

                document.getElementById('projectBody').innerHTML = visibleRows.length
                    ? visibleRows.map(project => `
                        <tr class="dashboard-project-row" data-project-id="${escapeHtml(project.project_id)}" tabindex="0" role="button" aria-label="View details for ${escapeHtml(project.name)}">
                            <td><strong>${escapeHtml(project.name)}</strong></td>
                            <td>${escapeHtml(project.client_name || '—')}</td>
                            <td>${escapeHtml(project.project_manager || '—')}</td>
                            <td>${escapeHtml(project.status === 'Completed' ? '—' : (project.phase || '—'))}</td>
                            <td><span class="project-status" data-status="${escapeHtml(project.status || '')}">${escapeHtml(project.status || '—')}</span></td>
                            <td>${escapeHtml(project.start_date || '—')}</td>
                            <td>${escapeHtml(project.estimated_end_date || '—')}</td>
                            <td>${escapeHtml(project.worker_count || 0)}</td>
                            <td>
                                <div class="progress"><i style="width:${Math.min(Number(project.completion_percentage || 0), 100)}%"></i></div>
                                ${Number(project.completion_percentage || 0).toFixed(1)}%
                            </td>
                            <td>${escapeHtml(money(project.budget_amount))}</td>
                            <td>${escapeHtml(money(project.actual_amount))}</td>
                        </tr>
                    `).join('')
                    : '<tr><td colspan="11">No projects match these filters.</td></tr>';

                const total = Number(state.data.project_total ?? rows.length);
                document.getElementById('projectCount').textContent =
                    `${total.toLocaleString()} matching project${total === 1 ? '' : 's'}` +
                    (total > rows.length ? `; first ${rows.length.toLocaleString()} shown` : '');
                document.getElementById('dashboardRange').textContent =
                    `Total: ${rows.length.toLocaleString()} project${rows.length === 1 ? '' : 's'}`;
                renderPagination(pages);
            }

            let projectDetailTrigger = null;

            // Get the project duration.
            function projectDuration(startDate, endDate) {
                // Return early if the start date is missing or false or the end date is missing or false.
                if (!startDate || !endDate) return '—';
                const start = new Date(`${startDate}T00:00:00`);
                const end = new Date(`${endDate}T00:00:00`);
                // Return early if the is na n check matches or the is na n check matches or the end is below the start.
                if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return '—';
                const months = Math.max(1, Math.round((end - start) / (1000 * 60 * 60 * 24 * 30.4375)));
                return `${months} month${months === 1 ? '' : 's'}`;
            }

            // Open project detail.
            function openProjectDetail(project, trigger) {
                projectDetailTrigger = trigger || null;
                const budget = Number(project.budget_amount || 0);
                const actual = Number(project.actual_amount || 0);
                const variance = budget - actual;
                // Set text.
                const setText = (id, value) => { document.getElementById(id).textContent = value ?? '—'; };
                setText('detailProjectName', project.name || 'Untitled project');
                setText('detailProjectClient', project.client_name || 'No client recorded');
                setText('detailProjectManager', project.project_manager || '—');
                setText('detailPhase', project.phase || '—');
                setText('detailStatus', project.status || '—');
                setText('detailCompletion', `${Number(project.completion_percentage || 0).toFixed(1)}%`);
                setText('detailStartDate', project.start_date || '—');
                setText('detailEstimatedEnd', project.estimated_end_date || '—');
                setText('detailActualEnd', project.actual_end_date || '—');
                setText('detailDuration', projectDuration(project.start_date, project.estimated_end_date));
                setText('detailWorkers', Number(project.worker_count || 0).toLocaleString());
                setText('detailBudget', money(budget));
                setText('detailActual', money(actual));
                setText('detailVariance', money(variance));
                const link = document.getElementById('viewProjectLink');
                // Run this step if the link is set.
                if (link) link.href = `${link.getAttribute('href').split('?')[0]}?project=${encodeURIComponent(project.project_id)}`;
                const modal = document.getElementById('projectDetailModal');
                modal.hidden = false;
                document.body.classList.add('modal-open');
                document.getElementById('closeProjectDetailButton').focus();
            }

            // Close project detail.
            function closeProjectDetail() {
                const modal = document.getElementById('projectDetailModal');
                // Return early if the hidden is set.
                if (modal.hidden) return;
                modal.hidden = true;
                document.body.classList.remove('modal-open');
                projectDetailTrigger?.focus();
                projectDetailTrigger = null;
            }

            // Get the pagination items.
            function paginationItems(current, total) {
                // Return early if the total is at most 7.
                if (total <= 7) return Array.from({ length: total }, (_, index) => index + 1);
                const items = [1];
                const from = Math.max(2, current - 1);
                const to = Math.min(total - 1, current + 1);
                // Run this step if the from is above 2.
                if (from > 2) items.push('ellipsis-start');
                // Repeat for each step in this range.
                for (let page = from; page <= to; page += 1) items.push(page);
                // Run this step if the to is below the value.
                if (to < total - 1) items.push('ellipsis-end');
                items.push(total);
                return items;
            }

            // Show pagination.
            function renderPagination(totalPages) {
                const links = document.getElementById('dashboardPaginationLinks');
                // Get the page button.
                const pageButton = (label, page, options = {}) => {
                    const classes = [options.active ? 'active' : '', options.disabled ? 'disabled' : ''].filter(Boolean).join(' ');
                    return `<button type="button" data-page="${page}" class="${classes}" ${options.disabled ? 'disabled' : ''}>${label}</button>`;
                };
                let html = pageButton('Previous', state.page - 1, { disabled: state.page <= 1 });
                // Process each item.
                paginationItems(state.page, totalPages).forEach(item => {
                    html += typeof item === 'number'
                        ? pageButton(item, item, { active: item === state.page })
                        : '<span class="ellipsis" aria-hidden="true">…</span>';
                });
                html += pageButton('Next', state.page + 1, { disabled: state.page >= totalPages });
                links.innerHTML = html;
            }

            // Process each value.
            Object.entries(controls).forEach(([key, element]) => {
                // Run the add event listener action.
                element.addEventListener(key === 'search' ? 'input' : 'change', () => {
                    window.clearTimeout(state.timer);
                    state.timer = window.setTimeout(loadDashboard, key === 'search' ? 300 : 0);
                });
            });
            // Handle the click event.
            document.getElementById('clear').addEventListener('click', () => {
                // Process each element.
                Object.values(controls).forEach(element => { element.value = ''; });
                state.period = 'all';
                // Process each button.
                document.querySelectorAll('[data-dashboard-period]').forEach(button => {
                    const selected = button.dataset.dashboardPeriod === state.period;
                    button.classList.toggle('active', selected);
                    button.setAttribute('aria-pressed', String(selected));
                });
                loadDashboard();
            });
            // Process each button.
            document.querySelectorAll('[data-dashboard-period]').forEach(button => {
                // Handle the click event.
                button.addEventListener('click', () => {
                    state.period = button.dataset.dashboardPeriod;
                    // Process each option.
                    document.querySelectorAll('[data-dashboard-period]').forEach(option => {
                        const selected = option === button;
                        option.classList.toggle('active', selected);
                        option.setAttribute('aria-pressed', String(selected));
                    });
                    loadDashboard();
                });
            });
            // Handle the change event.
            document.getElementById('pageSize').addEventListener('change', () => {
                state.page = 1;
                renderTable();
            });
            // Handle the click event.
            document.getElementById('dashboardPaginationLinks').addEventListener('click', event => {
                const button = event.target.closest('[data-page]');
                // Return early if the button is missing or false or the button disabled is set.
                if (!button || button.disabled) return;
                state.page = Number(button.dataset.page);
                renderTable();
            });
            // Handle the click event.
            document.getElementById('projectBody').addEventListener('click', event => {
                const row = event.target.closest('[data-project-id]');
                // Return early if the row is missing or false.
                if (!row) return;
                const project = (state.data?.projects || []).find(item => String(item.project_id) === row.dataset.projectId);
                // Run this step if the project is set.
                if (project) openProjectDetail(project, row);
            });
            // Handle the keydown event.
            document.getElementById('projectBody').addEventListener('keydown', event => {
                // Return early if the includes is missing or false.
                if (!['Enter', ' '].includes(event.key)) return;
                const row = event.target.closest('[data-project-id]');
                // Return early if the row is missing or false.
                if (!row) return;
                event.preventDefault();
                const project = (state.data?.projects || []).find(item => String(item.project_id) === row.dataset.projectId);
                // Run this step if the project is set.
                if (project) openProjectDetail(project, row);
            });
            document.getElementById('closeProjectDetailButton').addEventListener('click', closeProjectDetail);
            document.getElementById('closeProjectDetailFooter').addEventListener('click', closeProjectDetail);
            // Handle the click event.
            document.getElementById('projectDetailModal').addEventListener('click', event => {
                // Run this step if the target id equals projectDetailModal.
                if (event.target.id === 'projectDetailModal') closeProjectDetail();
            });
            // Handle the keydown event.
            document.addEventListener('keydown', event => {
                // Run this step if the key equals Escape.
                if (event.key === 'Escape') closeProjectDetail();
            });
            document.addEventListener('pfims:autorefresh', loadDashboard);
            loadDashboard();
        })();
    </script>
    @include('partials.validation-alert')
</body>
</html>
