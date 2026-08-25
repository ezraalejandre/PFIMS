@php
    $portal = $portal ?? 'admin';
    $portalLinks = [
        'admin' => [
            'dashboard' => '/dashboard',
            'projects' => '/projects',
            'finance' => '/finance',
            'inventory' => '/inventory',
            'suppliers' => '/suppliers',
            'reports' => '/reports',
            'notifications' => '/notifications',
            'profile' => '/profile',
            'settings' => '/settings',
        ],
        'accounting' => [
            'dashboard' => '/adashboard',
            'finance' => '/afinance',
            'reports' => '/areports',
            'notifications' => '/anotifications',
            'profile' => '/aprofile',
            'settings' => '/asettings',
        ],
        'operations' => [
            'dashboard' => '/odashboard',
            'projects' => '/oprojects',
            'inventory' => '/oinventory',
            'suppliers' => '/osuppliers',
            'reports' => '/oreports',
            'notifications' => '/onotifications',
            'profile' => '/oprofile',
            'settings' => '/osettings',
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
    $links = $portalLinks[$portal];
    $portalTitle = $portalTitles[$portal];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $portalTitle }} Reports - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/centralized-reports.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
    <script src="{{ asset('js/table-scroll-fade.js') }}" defer></script>
</head>
<body class="reports-page">
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
                @foreach ($navigation as $key => $item)
                    @if (isset($links[$key]))
                        <li class="{{ $key === 'reports' ? 'active' : '' }}">
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
        <section class="page-heading">
            <div>
                <p class="eyebrow">CENTRALIZED ANALYTICS</p>
                <h1>REPORTS</h1>
                <p>Review live operational data, apply focused filters, and generate traceable exports.</p>
            </div>
            <button type="button" class="btn btn-primary" id="openExport">Configure export</button>
        </section>

        <div class="notice" id="notice" role="alert" hidden></div>
        <section class="report-tabs" id="reportTabs" aria-label="Report types"></section>

        <section class="panel content-card filter-panel">
            <div class="panel-heading">
                <div>
                    <h2 id="datasetTitle">Report</h2>
                    <p>Filters update the KPIs, live records, and graph together.</p>
                </div>
                <button type="button" class="btn btn-secondary" id="clearFilters">Clear filters</button>
            </div>
            <div class="filters-grid">
                <label class="filter-control" data-filter="search">Search<input id="filterSearch" type="search" maxlength="100" placeholder="Search this report"></label>
                <label class="filter-control" data-filter="project_id">Project<select id="filterProject"><option value="">All projects</option></select></label>
                <label class="filter-control" data-filter="status">Status<select id="filterStatus"><option value="">All statuses</option></select></label>
                <label class="filter-control" data-filter="classification">Classification<select id="filterClassification"><option value="">All classifications</option></select></label>
                <label class="filter-control" data-filter="category_id">Category<select id="filterCategory"><option value="">All categories</option></select></label>
                <label class="filter-control" data-filter="supplier_id">Supplier<select id="filterSupplier"><option value="">All suppliers</option></select></label>
                <label class="filter-control" data-filter="stock_status">Stock status<select id="filterStockStatus"><option value="">All stock states</option></select></label>
                <label class="filter-control" data-filter="start_date">From date<input id="filterStart" type="date"></label>
                <label class="filter-control" data-filter="end_date">To date<input id="filterEnd" type="date"></label>
            </div>
        </section>

        <section class="kpi-grid" id="kpiGrid" aria-label="Report KPIs"></section>

        <section class="panel content-card live-data-panel">
            <div class="panel-heading">
                <div>
                    <h2>Live report records</h2>
                    <p id="rowSummary">Loading records…</p>
                </div>
            </div>
            <div class="table-wrap table-wrapper">
                <table>
                    <thead id="dataHead"></thead>
                    <tbody id="dataBody"><tr><td>Loading…</td></tr></tbody>
                </table>
            </div>
            <div class="pagination-wrapper" id="dataPagination">
                <div class="rows-info">
                    Rows per page
                    <select id="dataPageSize" aria-label="Live report rows per page">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span id="dataRange">Loading…</span>
                </div>
                <div class="pagination-links" id="dataPaginationLinks" aria-label="Live report pagination"></div>
            </div>
        </section>

        <section class="panel content-card chart-panel">
            <div class="panel-heading">
                <div>
                    <h2 id="chartTitle">Report chart</h2>
                    <p>Calculated from all records matching the active filters.</p>
                </div>
            </div>
            <div id="chart" class="chart" role="img" aria-label="Report chart"></div>
        </section>

        <section class="panel content-card history-panel">
            <div class="panel-heading">
                <div>
                    <h2>Export history</h2>
                    <p>Only system-generated exports are recorded here; source records and uploaded files are not listed.</p>
                </div>
            </div>
            <div class="history-filters">
                <input id="historySearch" type="search" maxlength="100" placeholder="Search report ID, title, filename, or user">
                <input id="historyStart" type="date" aria-label="History from date">
                <input id="historyEnd" type="date" aria-label="History to date">
                <button type="button" class="btn btn-secondary" id="refreshHistory">Refresh history</button>
            </div>
            <div class="table-wrap table-wrapper">
                <table>
                    <thead>
                        <tr><th>Report ID</th><th>Title / Type</th><th>Filters</th><th>Contents</th><th>Rows</th><th>Format</th><th>Generated by</th><th>Generated at</th><th>Action</th></tr>
                    </thead>
                    <tbody id="historyBody"><tr><td colspan="9">Loading export history…</td></tr></tbody>
                </table>
            </div>
            <div class="pagination-wrapper" id="historyPagination">
                <div class="rows-info">
                    Rows per page
                    <select id="historyPageSize" aria-label="Export history rows per page">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span id="historyRange">Loading…</span>
                </div>
                <div class="pagination-links" id="historyPaginationLinks" aria-label="Export history pagination"></div>
            </div>
        </section>
    </main>

    <dialog id="exportDialog" class="export-dialog">
        <form id="exportForm">
            <div class="dialog-heading">
                <div><p class="eyebrow">SYSTEM-GENERATED REPORT</p><h2>Configure export</h2></div>
                <button type="button" class="icon-btn" id="closeExport" aria-label="Close">×</button>
            </div>
            <label>Report title<input id="exportTitle" name="title" required minlength="3" maxlength="120"></label>
            <label>Format<select id="exportFormat" name="format" required><option value="csv">CSV</option></select></label>
            <div class="selection-group"><strong>Include sections</strong><div id="sectionChoices" class="choice-grid"></div></div>
            <div class="selection-group"><strong>Choose detailed fields</strong><div id="columnChoices" class="choice-grid columns"></div></div>
            <div class="active-filter-summary"><strong>Filters included in this export</strong><p id="exportFilterSummary">No filters applied.</p></div>
            <div class="dialog-actions">
                <button type="button" class="btn btn-secondary" id="cancelExport">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitExport">Generate and download</button>
            </div>
        </form>
    </dialog>

    <script>
        (() => {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const state = {
                catalog: null,
                dataset: null,
                definition: null,
                payload: null,
                dataTimer: null,
                historyTimer: null,
                dataPage: 1,
                dataPerPage: 25,
                historyPage: 1,
                historyPerPage: 25
            };
            const filterInputs = {
                search: document.getElementById('filterSearch'),
                project_id: document.getElementById('filterProject'),
                status: document.getElementById('filterStatus'),
                classification: document.getElementById('filterClassification'),
                category_id: document.getElementById('filterCategory'),
                supplier_id: document.getElementById('filterSupplier'),
                stock_status: document.getElementById('filterStockStatus'),
                start_date: document.getElementById('filterStart'),
                end_date: document.getElementById('filterEnd')
            };
            const moneyColumns = new Set(['budget_amount', 'actual_amount', 'variance', 'amount', 'remaining_amount']);

            const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            })[character]);
            const queryString = object => new URLSearchParams(Object.entries(object)
                .filter(([, value]) => value !== '' && value != null)).toString();
            const selectedFilters = () => Object.fromEntries(Object.entries(filterInputs)
                .map(([key, input]) => [key, input.value.trim()])
                .filter(([, value]) => value !== ''));

            function showNotice(message, type = 'error') {
                const notice = document.getElementById('notice');
                notice.textContent = message;
                notice.className = 'notice ' + type;
                notice.hidden = false;
                window.setTimeout(() => { notice.hidden = true; }, 6000);
            }

            async function apiJson(url, options = {}) {
                const { headers = {}, ...requestOptions } = options;
                const response = await fetch(url, {
                    ...requestOptions,
                    headers: { Accept: 'application/json', ...headers }
                });
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const validation = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                    throw new Error(validation || data.message || data.error || 'Request failed.');
                }

                return data;
            }

            function fillSelect(element, options) {
                options.forEach(option => {
                    const value = typeof option === 'object' ? option.value : option;
                    const label = typeof option === 'object' ? option.label : option;
                    element.insertAdjacentHTML('beforeend', `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`);
                });
            }

            async function initialize() {
                try {
                    state.catalog = await apiJson('/api/reports/catalog');
                    fillSelect(filterInputs.project_id, state.catalog.options.projects);
                    fillSelect(filterInputs.status, state.catalog.options.statuses);
                    fillSelect(filterInputs.classification, state.catalog.options.classifications);
                    fillSelect(filterInputs.category_id, state.catalog.options.categories);
                    fillSelect(filterInputs.supplier_id, state.catalog.options.suppliers);
                    fillSelect(filterInputs.stock_status, state.catalog.options.stock_statuses);
                    renderTabs();
                    renderSectionChoices();

                    if (!state.catalog.datasets.length) {
                        throw new Error('No reports are available for this role.');
                    }

                    await selectDataset(state.catalog.datasets[0].key);
                } catch (error) {
                    showNotice(error.message);
                }
            }

            function renderTabs() {
                const tabs = document.getElementById('reportTabs');
                tabs.innerHTML = state.catalog.datasets.map(item => `
                    <button type="button" class="tab" data-dataset="${escapeHtml(item.key)}">${escapeHtml(item.title)}</button>
                `).join('');
                tabs.addEventListener('click', event => {
                    const button = event.target.closest('[data-dataset]');
                    if (button) selectDataset(button.dataset.dataset);
                });
            }

            async function selectDataset(key) {
                state.dataset = key;
                state.definition = state.catalog.datasets.find(item => item.key === key);
                state.dataPage = 1;
                state.historyPage = 1;
                document.querySelectorAll('[data-dataset]').forEach(button => {
                    button.classList.toggle('active', button.dataset.dataset === key);
                });
                document.getElementById('datasetTitle').textContent = state.definition.title;
                document.querySelectorAll('[data-filter]').forEach(control => {
                    control.hidden = !state.definition.filters.includes(control.dataset.filter);
                });
                Object.entries(filterInputs).forEach(([name, input]) => {
                    if (!state.definition.filters.includes(name)) input.value = '';
                });
                renderColumnChoices();
                await Promise.all([loadDataset(), loadHistory()]);
            }

            async function loadDataset() {
                document.getElementById('dataBody').innerHTML = '<tr><td>Loading filtered records…</td></tr>';

                try {
                    const query = {
                        ...selectedFilters(),
                        page: state.dataPage,
                        per_page: state.dataPerPage
                    };
                    state.payload = await apiJson(`/api/reports/data/${state.dataset}?${queryString(query)}`);
                    state.dataPage = state.payload.pagination.current_page;
                    renderKpis();
                    renderDataTable();
                    renderChart();
                    renderPagination('data', state.payload.pagination);
                    updateExportSummary();
                } catch (error) {
                    showNotice(error.message);
                    document.getElementById('dataBody').innerHTML = '<tr><td>Unable to load this report.</td></tr>';
                }
            }

            function renderKpis() {
                document.getElementById('kpiGrid').innerHTML = state.payload.kpis.map(kpi => `
                    <article class="kpi-card">
                        <span>${escapeHtml(kpi.label)}</span>
                        <strong>${escapeHtml(kpi.value)}</strong>
                    </article>
                `).join('');
            }

            function displayValue(column, value) {
                if (value === null || value === '') return '—';
                if (moneyColumns.has(column)) {
                    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value));
                }
                if (column === 'completion_percentage' || column === 'utilization_percentage') {
                    return Number(value).toFixed(1) + '%';
                }
                if (typeof value === 'number') {
                    return new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(value);
                }
                return value;
            }

            function renderDataTable() {
                const columns = Object.entries(state.payload.columns);
                document.getElementById('dataHead').innerHTML = '<tr>' + columns
                    .map(([, label]) => `<th>${escapeHtml(label)}</th>`).join('') + '</tr>';
                document.getElementById('dataBody').innerHTML = state.payload.rows.length
                    ? state.payload.rows.map(row => '<tr>' + columns
                        .map(([key]) => `<td>${escapeHtml(displayValue(key, row[key]))}</td>`).join('') + '</tr>').join('')
                    : `<tr><td colspan="${columns.length}">No records match the selected filters.</td></tr>`;

                const pagination = state.payload.pagination;
                document.getElementById('rowSummary').textContent = pagination.total
                    ? `Showing ${pagination.from.toLocaleString()}–${pagination.to.toLocaleString()} of ${pagination.total.toLocaleString()} matching records.`
                    : 'No records match the selected filters.';
            }

            function renderChart() {
                const chart = state.payload.chart;
                document.getElementById('chartTitle').textContent = chart.title;
                const values = chart.series.flatMap(series => series.values.map(Number));
                const maximum = Math.max(...values, 1);

                if (!chart.labels.length) {
                    document.getElementById('chart').innerHTML = '<p class="empty-state">No chart data for these filters.</p>';
                    return;
                }

                document.getElementById('chart').innerHTML = chart.labels.map((label, index) => `
                    <div class="chart-group">
                        <div class="chart-label">${escapeHtml(label)}</div>
                        <div class="chart-series">
                            ${chart.series.map((series, seriesIndex) => {
                                const value = Number(series.values[index] || 0);
                                const width = Math.max((value / maximum) * 100, value > 0 ? 2 : 0);
                                return `
                                    <div class="bar-row">
                                        <span>${escapeHtml(series.label)}</span>
                                        <div class="bar-track"><div class="bar tone-${seriesIndex % 3}" style="width:${width}%"></div></div>
                                        <b>${escapeHtml(new Intl.NumberFormat('en-PH', { maximumFractionDigits: 2 }).format(value))}</b>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    </div>
                `).join('');
            }

            function paginationItems(current, last) {
                if (last <= 7) return Array.from({ length: last }, (_, index) => index + 1);
                const pages = new Set([1, last, current - 1, current, current + 1]);
                const sorted = [...pages].filter(page => page >= 1 && page <= last).sort((a, b) => a - b);
                const items = [];

                sorted.forEach((page, index) => {
                    if (index && page - sorted[index - 1] > 1) items.push('ellipsis-' + page);
                    items.push(page);
                });

                return items;
            }

            function renderPagination(kind, pagination) {
                const range = document.getElementById(kind + 'Range');
                const links = document.getElementById(kind + 'PaginationLinks');
                const total = Number(pagination.total || 0);
                const current = Number(pagination.current_page || 1);
                const last = Number(pagination.last_page || 1);

                range.textContent = total
                    ? `Showing ${Number(pagination.from).toLocaleString()}–${Number(pagination.to).toLocaleString()} of ${total.toLocaleString()}`
                    : 'No records';
                links.innerHTML = `
                    <button type="button" data-page="${current - 1}" ${current <= 1 ? 'disabled' : ''}>Previous</button>
                    ${paginationItems(current, last).map(item => typeof item === 'string'
                        ? '<span class="ellipsis" aria-hidden="true">…</span>'
                        : `<button type="button" data-page="${item}" class="${item === current ? 'active' : ''}" ${item === current ? 'aria-current="page"' : ''}>${item}</button>`
                    ).join('')}
                    <button type="button" data-page="${current + 1}" ${current >= last ? 'disabled' : ''}>Next</button>
                `;
            }

            function renderSectionChoices() {
                document.getElementById('sectionChoices').innerHTML = state.catalog.options.sections.map(section => `
                    <label><input type="checkbox" name="sections" value="${escapeHtml(section.value)}" checked> ${escapeHtml(section.label)}</label>
                `).join('');
            }

            function renderColumnChoices() {
                document.getElementById('columnChoices').innerHTML = Object.entries(state.definition.columns).map(([key, label]) => `
                    <label><input type="checkbox" name="columns" value="${escapeHtml(key)}" checked> ${escapeHtml(label)}</label>
                `).join('');
                document.getElementById('exportTitle').value =
                    `${state.definition.title} - ${new Date().toLocaleDateString('en-PH')}`;
            }

            function updateExportSummary() {
                const entries = Object.entries(selectedFilters());
                document.getElementById('exportFilterSummary').textContent = entries.length
                    ? entries.map(([key, value]) => `${key.replaceAll('_', ' ')}: ${value}`).join(' · ')
                    : 'No filters applied; all accessible records will be exported.';
            }

            async function loadHistory() {
                if (!state.dataset) return;
                const filters = {
                    dataset: state.dataset,
                    search: document.getElementById('historySearch').value.trim(),
                    start_date: document.getElementById('historyStart').value,
                    end_date: document.getElementById('historyEnd').value,
                    page: state.historyPage,
                    per_page: state.historyPerPage
                };

                try {
                    const response = await apiJson(`/api/reports?${queryString(filters)}`);
                    const reports = response.data || [];
                    state.historyPage = response.current_page;
                    document.getElementById('historyBody').innerHTML = reports.length
                        ? reports.map(report => {
                            const filterText = Object.entries(report.filters_applied || {})
                                .map(([key, value]) => `${key.replaceAll('_', ' ')}=${value}`).join(', ') || 'All records';
                            const sections = (report.export_options?.sections || []).join(', ') || 'data';
                            const contents = `${(report.selected_columns || []).length} fields · ${sections}`;
                            return `
                                <tr>
                                    <td>${escapeHtml(report.report_id)}</td>
                                    <td><strong>${escapeHtml(report.title)}</strong><small>${escapeHtml(report.dataset_key)}</small></td>
                                    <td>${escapeHtml(filterText)}</td>
                                    <td>${escapeHtml(contents)}</td>
                                    <td>${Number(report.row_count || 0).toLocaleString()}</td>
                                    <td>${escapeHtml(String(report.export_format || '').toUpperCase())}</td>
                                    <td>${escapeHtml(report.uploaded_by)}</td>
                                    <td>${escapeHtml(new Date(report.generated_at).toLocaleString('en-PH'))}</td>
                                    <td><a class="table-link" href="/api/reports/download/${encodeURIComponent(report.report_id)}">Download</a></td>
                                </tr>
                            `;
                        }).join('')
                        : '<tr><td colspan="9">No exports have been generated for this report yet.</td></tr>';
                    renderPagination('history', response);
                } catch (error) {
                    showNotice(error.message);
                }
            }

            Object.entries(filterInputs).forEach(([name, input]) => {
                input.addEventListener(name === 'search' ? 'input' : 'change', () => {
                    state.dataPage = 1;
                    window.clearTimeout(state.dataTimer);
                    state.dataTimer = window.setTimeout(loadDataset, name === 'search' ? 300 : 0);
                });
            });

            document.getElementById('clearFilters').addEventListener('click', () => {
                Object.values(filterInputs).forEach(input => { input.value = ''; });
                state.dataPage = 1;
                loadDataset();
            });
            document.getElementById('dataPageSize').addEventListener('change', event => {
                state.dataPerPage = Number(event.target.value);
                state.dataPage = 1;
                loadDataset();
            });
            document.getElementById('dataPaginationLinks').addEventListener('click', event => {
                const button = event.target.closest('[data-page]');
                if (!button || button.disabled) return;
                state.dataPage = Number(button.dataset.page);
                loadDataset();
            });

            function refreshHistoryFromStart() {
                state.historyPage = 1;
                loadHistory();
            }

            document.getElementById('refreshHistory').addEventListener('click', refreshHistoryFromStart);
            document.getElementById('historySearch').addEventListener('input', () => {
                state.historyPage = 1;
                window.clearTimeout(state.historyTimer);
                state.historyTimer = window.setTimeout(loadHistory, 300);
            });
            document.getElementById('historyStart').addEventListener('change', refreshHistoryFromStart);
            document.getElementById('historyEnd').addEventListener('change', refreshHistoryFromStart);
            document.getElementById('historyPageSize').addEventListener('change', event => {
                state.historyPerPage = Number(event.target.value);
                refreshHistoryFromStart();
            });
            document.getElementById('historyPaginationLinks').addEventListener('click', event => {
                const button = event.target.closest('[data-page]');
                if (!button || button.disabled) return;
                state.historyPage = Number(button.dataset.page);
                loadHistory();
            });

            const dialog = document.getElementById('exportDialog');
            document.getElementById('openExport').addEventListener('click', () => {
                updateExportSummary();
                dialog.showModal();
            });
            ['closeExport', 'cancelExport'].forEach(id => {
                document.getElementById(id).addEventListener('click', () => dialog.close());
            });
            document.getElementById('exportForm').addEventListener('submit', async event => {
                event.preventDefault();
                const columns = [...document.querySelectorAll('input[name="columns"]:checked')].map(input => input.value);
                const sections = [...document.querySelectorAll('input[name="sections"]:checked')].map(input => input.value);

                if (!columns.length || !sections.length) {
                    showNotice('Choose at least one field and one report section.');
                    return;
                }

                const button = document.getElementById('submitExport');
                button.disabled = true;
                button.textContent = 'Generating…';

                try {
                    const response = await fetch('/api/reports/export', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'text/csv,application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            dataset: state.dataset,
                            title: document.getElementById('exportTitle').value.trim(),
                            format: document.getElementById('exportFormat').value,
                            columns,
                            sections,
                            filters: selectedFilters()
                        })
                    });

                    if (!response.ok) {
                        const data = await response.json().catch(() => ({}));
                        throw new Error(data.errors
                            ? Object.values(data.errors).flat().join(' ')
                            : (data.message || 'Unable to generate report.'));
                    }

                    const blob = await response.blob();
                    const disposition = response.headers.get('Content-Disposition') || '';
                    const match = disposition.match(/filename="?([^";]+)"?/i);
                    const anchor = document.createElement('a');
                    anchor.href = URL.createObjectURL(blob);
                    anchor.download = match ? match[1] : 'pfims-report.csv';
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                    URL.revokeObjectURL(anchor.href);
                    dialog.close();
                    showNotice('Report generated, downloaded, and added to export history.', 'success');
                    state.historyPage = 1;
                    await loadHistory();
                } catch (error) {
                    showNotice(error.message);
                } finally {
                    button.disabled = false;
                    button.textContent = 'Generate and download';
                }
            });

            initialize();
        })();
    </script>
</body>
</html>
