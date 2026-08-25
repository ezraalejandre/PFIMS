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
        'suppliers' => ['label' => 'SUPPLIERS', 'icon' => 'suppliers.png'],
        'reports' => ['label' => 'REPORTS', 'icon' => 'reports.png'],
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
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <link rel="stylesheet" href="{{ asset('css/centralized-predictive-analytics.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
    <script src="{{ asset('js/table-scroll-fade.js') }}" defer></script>
</head>
<body class="dashboard-page">
    <header class="top-header">
        <div class="left">
            <img src="{{ asset('images/logo.jpg') }}" alt="PFIMS logo">
            <div class="brand-text">
                PFIMS
                <small>E.V. Catapang Design-Construction &amp; Supply</small>
            </div>
        </div>
        <div class="right">
            <a href="{{ url($links['notifications']) }}">
                <img src="{{ asset('images/notif.jpg') }}" alt="" aria-hidden="true">
                <span>Notifications</span>
            </a>
            <a href="{{ url($links['profile']) }}">
                <img class="profile-avatar" src="{{ asset('images/user.jpg') }}" alt="" aria-hidden="true">
                <span>{{ auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <aside class="sidebar">
        <nav aria-label="Primary navigation">
            <ul>
                @foreach($navigation as $key => $item)
                    @if(isset($links[$key]))
                        <li class="{{ $key === 'dashboard' ? 'active' : '' }}">
                            <a href="{{ url($links[$key]) }}">
                                <img src="{{ asset('images/'.$item['icon']) }}" alt="" class="nav-link-icon" aria-hidden="true">
                                {{ $item['label'] }}
                            </a>
                        </li>
                    @endif
                @endforeach
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li>
                    <a href="{{ url($links['settings']) }}">
                        <img src="{{ asset('images/settings.jpg') }}" alt="" class="nav-icon" aria-hidden="true">
                        Settings
                    </a>
                </li>
                <li class="logout">
                    <form action="{{ url('/logout') }}" method="POST">
                        @csrf
                        <button type="submit">
                            <img src="{{ asset('images/logout.jpg') }}" alt="" class="nav-icon" aria-hidden="true">
                            Log out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>

    <main class="main-content">
        <section class="dashboard-page-header">
            <div class="dashboard-title-block">
                <p class="eyebrow">BUSINESS OVERVIEW</p>
                <h1>{{ strtoupper($portalTitle) }} DASHBOARD</h1>
                <p class="page-description">Monitor project delivery, financial performance, and operational activity in one place.</p>
            </div>
            <div class="dashboard-heading-actions">
                <div class="dashboard-clock" aria-label="Current Philippine date and time">
                    <span class="clock-zone">PHILIPPINE STANDARD TIME</span>
                    <div class="clock-reading">
                        <time id="dashboardTime" datetime="">--:--:-- --</time>
                        <span id="dashboardDate">Loading date…</span>
                    </div>
                </div>
                <button id="refresh" class="btn-refresh btn-primary" type="button">
                    <span aria-hidden="true">↻</span>
                    Refresh data
                </button>
            </div>
        </section>

        @if(in_array($portal, ['admin', 'accounting'], true))
            <nav class="dashboard-tabs" aria-label="Dashboard sections" role="tablist">
                <button class="dashboard-tab active" id="overviewTab" type="button" role="tab" aria-selected="true" aria-controls="overviewPanel" data-dashboard-tab="overviewPanel">
                    Business overview
                </button>
                <button class="dashboard-tab" id="predictionTab" type="button" role="tab" aria-selected="false" aria-controls="predictionPanel" data-dashboard-tab="predictionPanel">
                    Predictive analytics
                </button>
            </nav>
        @endif

        <section id="overviewPanel" class="dashboard-tab-panel active" role="tabpanel" aria-labelledby="overviewTab">
        <div class="notice" id="notice" role="alert" hidden></div>

        <section class="panel filters" aria-label="Dashboard filters">
            <label>
                Search
                <input id="search" type="search" maxlength="100" placeholder="Project, client, manager, or phase">
            </label>
            <label>
                Project
                <select id="project"><option value="">All projects</option></select>
            </label>
            <label>
                Status
                <select id="status"><option value="">All statuses</option></select>
            </label>
            <label>
                Started from
                <input id="start" type="date">
            </label>
            <label>
                Started to
                <input id="end" type="date">
            </label>
            <button id="clear" class="btn-secondary" type="button">Clear filters</button>
        </section>

        <section class="kpis" id="kpis" aria-label="Dashboard key performance indicators"></section>

        <section class="chart-grid" aria-label="Dashboard charts">
            <article class="panel chart-card">
                <h2>Completion trend</h2>
                <p>Average current completion of projects started in each month.</p>
                <div class="chart" id="completionChart"></div>
            </article>
            <article class="panel chart-card">
                <h2>Project status distribution</h2>
                <p>Matching projects grouped by current status.</p>
                <div class="chart" id="statusChart"></div>
            </article>
            <article class="panel chart-card budget-panel">
                <h2>Budget vs recorded expenses</h2>
                <p>Cumulative allocation and finance-ledger spending.</p>
                <div class="chart" id="budgetChart"></div>
            </article>
        </section>

        <section class="panel content-card project-panel">
            <div class="panel-heading">
                <div>
                    <h2>Matching projects</h2>
                    <p id="projectCount">Loading…</p>
                </div>
            </div>
            <div class="table-wrap table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Manager</th>
                            <th>Phase</th>
                            <th>Status</th>
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
            <div class="pagination-wrapper" id="dashboardPagination">
                <div class="rows-info">
                    Rows per page
                    <select id="pageSize" aria-label="Dashboard rows per page">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span id="dashboardRange">Showing 0–0 of 0 projects</span>
                </div>
                <div class="pagination-links" id="dashboardPaginationLinks" aria-label="Dashboard table pagination"></div>
            </div>
        </section>
        </section>

        @if(in_array($portal, ['admin', 'accounting'], true))
            <section id="predictionPanel" class="dashboard-tab-panel" role="tabpanel" aria-labelledby="predictionTab" hidden>
                @include('ml-dashboard-test', ['fragment' => true])
            </section>
        @endif
    </main>

    <div class="dashboard-modal" id="projectDetailModal" hidden>
        <section class="dashboard-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="detailProjectName" aria-describedby="detailProjectClient">
            <header class="dashboard-modal-header">
                <div>
                    <span class="eyebrow">PROJECT DETAILS</span>
                    <h2 id="detailProjectName">Project</h2>
                    <p id="detailProjectClient">Client</p>
                </div>
                <button class="dashboard-modal-close" id="closeProjectDetailButton" type="button" aria-label="Close project details">×</button>
            </header>
            <div class="dashboard-project-details">
                <div class="dashboard-detail-item"><span>Project manager</span><strong id="detailProjectManager">—</strong></div>
                <div class="dashboard-detail-item"><span>Phase</span><strong id="detailPhase">—</strong></div>
                <div class="dashboard-detail-item"><span>Status</span><strong id="detailStatus">—</strong></div>
                <div class="dashboard-detail-item"><span>Completion</span><strong id="detailCompletion">—</strong></div>
                <div class="dashboard-detail-item"><span>Start date</span><strong id="detailStartDate">—</strong></div>
                <div class="dashboard-detail-item"><span>Estimated end</span><strong id="detailEstimatedEnd">—</strong></div>
                <div class="dashboard-detail-item"><span>Actual end</span><strong id="detailActualEnd">—</strong></div>
                <div class="dashboard-detail-item"><span>Planned duration</span><strong id="detailDuration">—</strong></div>
                <div class="dashboard-detail-item"><span>Assigned workers</span><strong id="detailWorkers">—</strong></div>
                <div class="dashboard-detail-item"><span>Budget</span><strong id="detailBudget">—</strong></div>
                <div class="dashboard-detail-item"><span>Recorded actual</span><strong id="detailActual">—</strong></div>
                <div class="dashboard-detail-item"><span>Budget variance</span><strong id="detailVariance">—</strong></div>
            </div>
            <footer class="dashboard-modal-footer">
                <button class="btn-secondary" id="closeProjectDetailFooter" type="button">Close</button>
                @if(isset($links['projects']))
                    <a class="btn-primary dashboard-view-project" id="viewProjectLink" href="{{ url($links['projects']) }}">View project</a>
                @endif
            </footer>
        </section>
    </div>

    <script>
        (function () {
            const state = { data: null, page: 1, timer: null, loadedOptions: false };
            const controls = {
                search: document.getElementById('search'),
                project_id: document.getElementById('project'),
                status: document.getElementById('status'),
                start_date: document.getElementById('start'),
                end_date: document.getElementById('end')
            };
            const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            })[character]);
            const money = value => new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP'
            }).format(Number(value || 0));

            function updateClock() {
                const now = new Date();
                const time = document.getElementById('dashboardTime');
                const date = document.getElementById('dashboardDate');
                const timeFormatter = new Intl.DateTimeFormat('en-PH', {
                    timeZone: 'Asia/Manila',
                    hour: 'numeric',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: true
                });
                const dateFormatter = new Intl.DateTimeFormat('en-PH', {
                    timeZone: 'Asia/Manila',
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });

                time.textContent = timeFormatter.format(now);
                time.dateTime = now.toISOString();
                date.textContent = dateFormatter.format(now);
            }

            function activeFilters() {
                return Object.fromEntries(Object.entries(controls)
                    .map(([key, element]) => [key, element.value.trim()])
                    .filter(([, value]) => value));
            }

            function showError(message) {
                const notice = document.getElementById('notice');
                notice.textContent = message;
                notice.hidden = false;
                window.setTimeout(() => { notice.hidden = true; }, 5000);
            }

            async function loadDashboard() {
                const refresh = document.getElementById('refresh');
                refresh.disabled = true;

                try {
                    const query = new URLSearchParams(activeFilters());
                    const response = await fetch('/api/dashboard?' + query, {
                        headers: { Accept: 'application/json' }
                    });
                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.errors
                            ? Object.values(data.errors).flat().join(' ')
                            : (data.message || 'Dashboard request failed.'));
                    }

                    state.data = data;
                    if (!state.loadedOptions) {
                        fillOptions(controls.project_id, data.filter_options.projects);
                        fillOptions(controls.status, data.filter_options.statuses.map(value => ({ value, label: value })));
                        state.loadedOptions = true;
                    }
                    state.page = 1;
                    renderDashboard();
                } catch (error) {
                    showError(error.message);
                } finally {
                    refresh.disabled = false;
                }
            }

            function fillOptions(element, items) {
                items.forEach(item => element.insertAdjacentHTML(
                    'beforeend',
                    `<option value="${escapeHtml(item.value)}">${escapeHtml(item.label)}</option>`
                ));
            }

            function renderDashboard() {
                document.getElementById('kpis').innerHTML = state.data.stat_cards.map(card => `
                    <article class="kpi-card">
                        <span>${escapeHtml(card.label)}</span>
                        <strong>${escapeHtml(card.value)}</strong>
                        <small>${escapeHtml(card.subtitle)}</small>
                        ${card.badge ? `<b class="${escapeHtml(card.badge_type)}">${escapeHtml(card.badge)}</b>` : ''}
                    </article>
                `).join('');

                renderChart('completionChart', state.data.completion_trend.months, [
                    { label: 'Completion %', values: state.data.completion_trend.values }
                ]);
                renderChart('budgetChart', state.data.budget_vs_expense.months, [
                    { label: 'Budget', values: state.data.budget_vs_expense.allocated_budget },
                    { label: 'Expenses', values: state.data.budget_vs_expense.expenses }
                ]);
                renderChart('statusChart', state.data.project_status.labels, [
                    { label: 'Projects', values: state.data.project_status.values }
                ]);
                renderTable();
            }

            function renderChart(id, labels, series) {
                const element = document.getElementById(id);
                const allValues = series.flatMap(item => item.values.map(Number));
                const maximum = Math.max(...allValues, 1);

                element.innerHTML = labels.length ? labels.map((label, index) => `
                    <div class="chart-group">
                        <div class="chart-label">${escapeHtml(label)}</div>
                        <div>
                            ${series.map((item, seriesIndex) => {
                                const value = Number(item.values[index] || 0);
                                const width = Math.max((value / maximum) * 100, value ? 2 : 0);
                                return `
                                    <div class="bar-row">
                                        <span>${escapeHtml(item.label)}</span>
                                        <div class="track"><i class="tone-${seriesIndex}" style="width:${width}%"></i></div>
                                        <b>${escapeHtml(new Intl.NumberFormat('en-PH', { maximumFractionDigits: 1 }).format(value))}</b>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `).join('') : '<div class="empty">No matching chart data.</div>';
            }

            function renderTable() {
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
                            <td>${escapeHtml(project.phase || '—')}</td>
                            <td><span class="project-status">${escapeHtml(project.status || '—')}</span></td>
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
                const start = rows.length ? ((state.page - 1) * size) + 1 : 0;
                const end = rows.length ? Math.min(state.page * size, rows.length) : 0;
                document.getElementById('dashboardRange').textContent =
                    `Showing ${start.toLocaleString()}–${end.toLocaleString()} of ${rows.length.toLocaleString()} projects`;
                renderPagination(pages);
            }

            let projectDetailTrigger = null;

            function projectDuration(startDate, endDate) {
                if (!startDate || !endDate) return '—';
                const start = new Date(`${startDate}T00:00:00`);
                const end = new Date(`${endDate}T00:00:00`);
                if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime()) || end < start) return '—';
                const months = Math.max(1, Math.round((end - start) / (1000 * 60 * 60 * 24 * 30.4375)));
                return `${months} month${months === 1 ? '' : 's'}`;
            }

            function openProjectDetail(project, trigger) {
                projectDetailTrigger = trigger || null;
                const budget = Number(project.budget_amount || 0);
                const actual = Number(project.actual_amount || 0);
                const variance = budget - actual;
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
                if (link) link.href = `${link.getAttribute('href').split('?')[0]}?project=${encodeURIComponent(project.project_id)}`;
                const modal = document.getElementById('projectDetailModal');
                modal.hidden = false;
                document.body.classList.add('modal-open');
                document.getElementById('closeProjectDetailButton').focus();
            }

            function closeProjectDetail() {
                const modal = document.getElementById('projectDetailModal');
                if (modal.hidden) return;
                modal.hidden = true;
                document.body.classList.remove('modal-open');
                projectDetailTrigger?.focus();
                projectDetailTrigger = null;
            }

            function paginationItems(current, total) {
                if (total <= 7) return Array.from({ length: total }, (_, index) => index + 1);
                const items = [1];
                const from = Math.max(2, current - 1);
                const to = Math.min(total - 1, current + 1);
                if (from > 2) items.push('ellipsis-start');
                for (let page = from; page <= to; page += 1) items.push(page);
                if (to < total - 1) items.push('ellipsis-end');
                items.push(total);
                return items;
            }

            function renderPagination(totalPages) {
                const links = document.getElementById('dashboardPaginationLinks');
                const pageButton = (label, page, options = {}) => {
                    const classes = [options.active ? 'active' : '', options.disabled ? 'disabled' : ''].filter(Boolean).join(' ');
                    return `<button type="button" data-page="${page}" class="${classes}" ${options.disabled ? 'disabled' : ''}>${label}</button>`;
                };
                let html = pageButton('Previous', state.page - 1, { disabled: state.page <= 1 });
                paginationItems(state.page, totalPages).forEach(item => {
                    html += typeof item === 'number'
                        ? pageButton(item, item, { active: item === state.page })
                        : '<span class="ellipsis" aria-hidden="true">…</span>';
                });
                html += pageButton('Next', state.page + 1, { disabled: state.page >= totalPages });
                links.innerHTML = html;
            }

            Object.entries(controls).forEach(([key, element]) => {
                element.addEventListener(key === 'search' ? 'input' : 'change', () => {
                    window.clearTimeout(state.timer);
                    state.timer = window.setTimeout(loadDashboard, key === 'search' ? 300 : 0);
                });
            });
            document.getElementById('refresh').addEventListener('click', loadDashboard);
            document.getElementById('clear').addEventListener('click', () => {
                Object.values(controls).forEach(element => { element.value = ''; });
                loadDashboard();
            });
            document.getElementById('pageSize').addEventListener('change', () => {
                state.page = 1;
                renderTable();
            });
            document.getElementById('dashboardPaginationLinks').addEventListener('click', event => {
                const button = event.target.closest('[data-page]');
                if (!button || button.disabled) return;
                state.page = Number(button.dataset.page);
                renderTable();
            });
            document.getElementById('projectBody').addEventListener('click', event => {
                const row = event.target.closest('[data-project-id]');
                if (!row) return;
                const project = (state.data?.projects || []).find(item => String(item.project_id) === row.dataset.projectId);
                if (project) openProjectDetail(project, row);
            });
            document.getElementById('projectBody').addEventListener('keydown', event => {
                if (!['Enter', ' '].includes(event.key)) return;
                const row = event.target.closest('[data-project-id]');
                if (!row) return;
                event.preventDefault();
                const project = (state.data?.projects || []).find(item => String(item.project_id) === row.dataset.projectId);
                if (project) openProjectDetail(project, row);
            });
            document.getElementById('closeProjectDetailButton').addEventListener('click', closeProjectDetail);
            document.getElementById('closeProjectDetailFooter').addEventListener('click', closeProjectDetail);
            document.getElementById('projectDetailModal').addEventListener('click', event => {
                if (event.target.id === 'projectDetailModal') closeProjectDetail();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeProjectDetail();
            });

            document.querySelectorAll('[data-dashboard-tab]').forEach(tab => {
                tab.addEventListener('click', () => {
                    const panelId = tab.dataset.dashboardTab;
                    document.querySelectorAll('[data-dashboard-tab]').forEach(item => {
                        const selected = item === tab;
                        item.classList.toggle('active', selected);
                        item.setAttribute('aria-selected', selected ? 'true' : 'false');
                    });
                    document.querySelectorAll('.dashboard-tab-panel').forEach(panel => {
                        const selected = panel.id === panelId;
                        panel.classList.toggle('active', selected);
                        panel.hidden = !selected;
                    });

                    if (panelId === 'predictionPanel') window.dispatchEvent(new Event('resize'));
                });
            });

            updateClock();
            window.setInterval(updateClock, 1000);
            loadDashboard();
        })();
    </script>
</body>
</html>
