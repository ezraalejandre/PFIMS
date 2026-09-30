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
        'reports' => ['label' => 'REPORTS', 'icon' => 'folder.svg'],
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
    <link rel="stylesheet" href="{{ asset('css/centralized-reports.css') }}?v={{ filemtime(public_path('css/centralized-reports.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/'.$portal.'.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    <script src="{{ asset('js/table-scroll-fade.js') }}" defer></script>
</head>
<body class="reports-page" data-portal="{{ $portal }}" data-reports-api-base="{{ request()->getBaseUrl() }}/api/reports" data-report-view-icon="{{ asset('images/view.jpg') }}" data-report-download-icon="{{ asset('images/download.jpg') }}" data-report-header-image="{{ asset('images/report-header.jpeg') }}">
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
                <span>{{ auth()->user()->name === 'Administrator' ? 'Admin' : auth()->user()->name }}</span>
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
                <h1>REPORTS</h1>
                <p>Review filtered operational records and generate consistent system reports.</p>
            </div>
            <div class="report-header-actions">
                <button type="button" class="btn btn-primary" id="openExport">Configure export</button>
            </div>
        </section>

        <div class="notice" id="notice" role="alert" hidden></div>
        <section class="report-tabs" id="reportTabs" aria-label="Report types"></section>

        <section class="panel content-card filter-panel">
            <div class="panel-heading">
                <div>
                    <h2 id="datasetTitle">Filters update the summary table.</h2>
                    <p id="reportScopeNote"></p>
                </div>
            </div>
            <div class="filters-grid" id="reportFiltersGrid">
                <label class="filter-control" data-filter="search">Search<input id="filterSearch" type="search" maxlength="100" placeholder="Search this report"></label>
                @php($reportToday = now()->timezone('Asia/Manila'))
                <div class="filter-control report-date-picker" data-filter="report_month"><span>Month</span>
                    <input id="filterReportMonth" type="hidden" value="{{ $reportToday->month }}" data-default="{{ $reportToday->month }}">
                    <button type="button" class="report-date-trigger" data-date-part="report_month" aria-expanded="false" aria-label="Choose report month">{{ $reportToday->format('F') }}</button>
                    <div class="report-date-popover" hidden></div>
                </div>
                <div class="filter-control report-date-picker" data-filter="report_day"><span>Day</span>
                    <input id="filterReportDay" type="hidden" value="{{ $reportToday->day }}" data-default="{{ $reportToday->day }}">
                    <button type="button" class="report-date-trigger" data-date-part="report_day" aria-expanded="false" aria-label="Choose report day">{{ $reportToday->day }}</button>
                    <div class="report-date-popover" hidden></div>
                </div>
                <div class="filter-control report-date-picker" data-filter="report_year"><span>Year</span>
                    <input id="filterReportYear" type="hidden" value="{{ $reportToday->year }}" data-default="{{ $reportToday->year }}">
                    <button type="button" class="report-date-trigger" data-date-part="report_year" aria-expanded="false" aria-label="Choose report year">{{ $reportToday->year }}</button>
                    <div class="report-date-popover" hidden></div>
                </div>
                <label class="filter-control" data-filter="project_status">Project Status<select id="filterProjectStatus" data-default="Ongoing">
                    <option value="Ongoing">Ongoing</option><option value="Completed">Completed</option>
                </select></label>
                <label class="filter-control" data-filter="expense_type">Expense Type<select id="filterExpenseType" data-default="Overall">
                    <option value="Overall">Overall</option><option value="Direct">Direct</option><option value="Admin">Admin</option>
                </select></label>
                <label class="filter-control" data-filter="project_id">Project<select id="filterProject"><option value="">All projects</option></select></label>
                <label class="filter-control" data-filter="status">Status<select id="filterStatus"><option value="">All statuses</option></select></label>
                <label class="filter-control" data-filter="classification">Classification<select id="filterClassification"><option value="">All classifications</option></select></label>
                <label class="filter-control" data-filter="category_id">Category<select id="filterCategory"><option value="">All categories</option></select></label>
                <label class="filter-control" data-filter="supplier_id">Supplier<select id="filterSupplier"><option value="">All suppliers</option></select></label>
                <label class="filter-control" data-filter="stock_status">Stock status<select id="filterStockStatus"><option value="">All stock states</option></select></label>
                <label class="filter-control" data-filter="start_date">From date<input id="filterStart" type="date"></label>
                <label class="filter-control" data-filter="end_date">To date<input id="filterEnd" type="date"></label>
                <button type="button" class="btn btn-secondary" id="clearFilters">Clear filters</button>
            </div>
        </section>

        <section class="kpi-grid" id="kpiGrid" aria-label="Report KPIs"></section>

        <section class="panel content-card live-data-panel">
            <div class="panel-heading">
                <div>
                    <h2 id="recordsTitle">Expenses Summary</h2>
                    <p id="rowSummary">Loading records…</p>
                </div>
            </div>
            <div class="table-wrap table-wrapper">
                <table>
                    <thead id="dataHead"></thead>
                    <tbody id="dataBody"><tr><td>Loading…</td></tr></tbody>
                    <tfoot id="dataFoot"></tfoot>
                </table>
            </div>
            <div class="pagination-wrapper" id="dataPagination">
                <div class="rows-info">
                    Rows per page
                    <select id="dataPageSize" aria-label="Live report rows per page">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span id="dataRange">Loading…</span>
                </div>
                <div class="pagination-links" id="dataPaginationLinks" aria-label="Live report pagination"></div>
            </div>
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
            </div>
            <div class="table-wrap table-wrapper">
                <table data-pfims-standard-actions="off">
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
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
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
            <label>Format<select id="exportFormat" name="format" required><option value="xlsx">Excel (.xlsx)</option><option value="csv">CSV (.csv)</option><option value="pdf">PDF (.pdf)</option></select></label>
            <div class="selection-group"><strong>Choose detailed fields</strong><div id="columnChoices" class="choice-grid columns"></div></div>
            <div class="active-filter-summary"><strong>Filters included in this export</strong><p id="exportFilterSummary">No filters applied.</p></div>
            <div class="preview-toolbar">
                <h3>Preview Export</h3>
                <button type="button" class="btn btn-secondary" id="editExportDesign" data-pfims-no-expand aria-expanded="false" aria-controls="exportDesignControls">Edit Export</button>
            </div>
            <div class="export-design-controls" id="exportDesignControls" hidden>
                <label>Header color<select id="exportHeaderColor"><option value="navy">Navy</option><option value="orange">Orange</option><option value="green">Green</option></select></label>
                <label>Table spacing<select id="exportTableSpacing"><option value="standard">Standard</option><option value="compact">Compact</option></select></label>
            </div>
            <div class="report-preview" id="reportPreview" aria-live="polite">Preparing preview…</div>
            <div class="dialog-actions">
                <button type="button" class="btn btn-secondary" id="cancelExport">Cancel</button>
                <button type="submit" class="btn btn-primary" id="submitExport">Generate and download</button>
            </div>
        </form>
    </dialog>

    <dialog id="historyDetailDialog" class="export-dialog history-detail-dialog" aria-labelledby="historyDetailTitle">
        <form method="dialog" class="history-detail-content">
            <div class="dialog-heading">
                <div><p class="eyebrow">EXPORT HISTORY</p><h2 id="historyDetailTitle">Export details</h2></div>
                <button type="button" class="icon-btn" id="closeHistoryDetail" aria-label="Close">×</button>
            </div>
            <div class="history-detail-grid">
                <div><span>Report ID</span><strong id="historyDetailId">—</strong></div>
                <div><span>Title</span><strong id="historyDetailReportTitle">—</strong></div>
                <div><span>Dataset</span><strong id="historyDetailDataset">—</strong></div>
                <div><span>Format</span><strong id="historyDetailFormat">—</strong></div>
                <div><span>Rows</span><strong id="historyDetailRows">—</strong></div>
                <div><span>Generated by</span><strong id="historyDetailUser">—</strong></div>
                <div><span>Generated at</span><strong id="historyDetailGenerated">—</strong></div>
                <div class="history-detail-wide"><span>Filters</span><strong id="historyDetailFilters">—</strong></div>
                <div class="history-detail-wide"><span>Included sections</span><strong id="historyDetailSections">—</strong></div>
                <div class="history-detail-wide"><span>Selected fields</span><strong id="historyDetailColumns">—</strong></div>
            </div>
        </form>
    </dialog>

    <script type="application/json" id="reportCatalogBootstrap">{!! json_encode($reportCatalog, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

    <script>
        (() => {
            const csrf = document.querySelector('meta[name="csrf-token"]').content;
            const reportsApiBase = document.body.dataset.reportsApiBase;
            const state = {
                catalog: null,
                dataset: null,
                definition: null,
                payload: null,
                dataTimer: null,
                historyTimer: null,
                dataPage: 1,
                dataPerPage: 5,
                historyPage: 1,
                historyPerPage: 5,
                historyRecords: {}
            };
            const filterInputs = {
                search: document.getElementById('filterSearch'),
                report_month: document.getElementById('filterReportMonth'),
                report_day: document.getElementById('filterReportDay'),
                report_year: document.getElementById('filterReportYear'),
                project_status: document.getElementById('filterProjectStatus'),
                expense_type: document.getElementById('filterExpenseType'),
                project_id: document.getElementById('filterProject'),
                status: document.getElementById('filterStatus'),
                classification: document.getElementById('filterClassification'),
                category_id: document.getElementById('filterCategory'),
                supplier_id: document.getElementById('filterSupplier'),
                stock_status: document.getElementById('filterStockStatus'),
                start_date: document.getElementById('filterStart'),
                end_date: document.getElementById('filterEnd')
            };
            const filterControls = Object.fromEntries(Object.entries(filterInputs)
                .map(([name, input]) => [name, input.closest('[data-filter]')]));
            let datasetRequest = 0;
            let historyRequest = 0;
            const moneyColumns = new Set(['budget_amount', 'actual_amount', 'variance', 'amount', 'remaining_amount', 'unit_price',
                'construction_supply', 'salaries_wages', 'permits_taxes_licenses', 'transportation_expenses',
                'utilities', 'delivery_expense', 'others', 'administrative_expenses', 'total',
                'original_contract_price', 'additional_works_contract', 'total_contract_price',
                'original_payment_received', 'additional_works_payment', 'total_payment', 'project_expense',
                'accounts_receivable', 'profit_loss_payment_basis', 'profit_loss_contract_basis']);

            const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'
            })[character]);
            const queryString = object => new URLSearchParams(Object.entries(object)
                .filter(([, value]) => value !== '' && value != null)).toString();
            const selectedFilters = () => Object.fromEntries(Object.entries(filterInputs)
                .filter(([key]) => state.definition?.filters.includes(key))
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
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...headers }
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
                    state.catalog = JSON.parse(document.getElementById('reportCatalogBootstrap').textContent);
                    fillSelect(filterInputs.project_id, state.catalog.options.projects);
                    fillSelect(filterInputs.status, state.catalog.options.statuses);
                    fillSelect(filterInputs.classification, state.catalog.options.classifications);
                    fillSelect(filterInputs.category_id, state.catalog.options.categories);
                    fillSelect(filterInputs.supplier_id, state.catalog.options.suppliers);
                    fillSelect(filterInputs.stock_status, state.catalog.options.stock_statuses);
                    renderTabs();

                    if (!state.catalog.datasets.length) {
                        document.getElementById('openExport').hidden = true;
                        document.querySelectorAll('main .filter-panel, main .live-data-panel, main .history-panel').forEach(panel => { panel.hidden = true; });
                        const notice = document.getElementById('notice');
                        notice.textContent = 'No reports are currently available for this role.';
                        notice.className = 'notice info';
                        notice.hidden = false;
                        return;
                    }

                    const requested = new URLSearchParams(window.location.search).get('section');
                    await selectDataset(state.catalog.datasets.some(item => item.key === requested)
                        ? requested : state.catalog.datasets[0].key);
                } catch (error) {
                    showNotice(error.message);
                }
            }

            function renderTabs() {
                const tabs = document.getElementById('reportTabs');
                tabs.hidden = state.catalog.datasets.length === 0;
                tabs.innerHTML = state.catalog.datasets.map(item => `
                    <button type="button" class="tab" data-dataset="${escapeHtml(item.key)}">${escapeHtml(item.title)}</button>
                `).join('');
                tabs.addEventListener('click', event => {
                    const button = event.target.closest('[data-dataset]');
                    if (button) selectDataset(button.dataset.dataset);
                });
            }

            async function selectDataset(key) {
                window.clearTimeout(state.dataTimer);
                datasetRequest++;
                state.dataset = key;
                document.body.dataset.reportDataset = key;
                state.definition = state.catalog.datasets.find(item => item.key === key);
                state.dataPage = 1;
                state.historyPage = 1;
                document.querySelectorAll('[data-dataset]').forEach(button => {
                    button.classList.toggle('active', button.dataset.dataset === key);
                });
                document.getElementById('datasetTitle').textContent = key === 'inventory'
                    ? 'Filters update both the KPIs and summary table.'
                    : 'Filters update the summary table.';
                document.getElementById('recordsTitle').textContent = key === 'expense_summary'
                    ? 'SUMMARY OF EXPENSES (PROJECT SITE-OVERALL EXPENSES)'
                    : (key === 'inventory' ? 'Inventory Items Summary' : state.definition.title);
                document.getElementById('reportScopeNote').textContent = key === 'expense_summary'
                    ? 'Project-linked expenses only. Office expenses without a project link are excluded.' : '';
                document.getElementById('reportScopeNote').hidden = key === 'inventory';
                document.getElementById('kpiGrid').hidden = key !== 'inventory';
                const filterGrid = document.getElementById('reportFiltersGrid');
                const clearFilters = document.getElementById('clearFilters');
                Object.values(filterControls).forEach(control => {
                    if (state.definition.filters.includes(control.dataset.filter)) {
                        filterGrid.insertBefore(control, clearFilters.parentNode === filterGrid ? clearFilters : null);
                    } else {
                        control.remove();
                    }
                });
                Object.entries(filterInputs).forEach(([name, input]) => {
                    if (!state.definition.filters.includes(name)) input.value = '';
                    else if (key === 'expense_summary' && input.dataset.default) input.value = input.dataset.default;
                });
                updateDatePickerTriggers();
                closeDatePickers();
                renderColumnChoices();
                document.getElementById('dataHead').innerHTML = '';
                document.getElementById('dataFoot').innerHTML = '';
                document.getElementById('rowSummary').textContent = 'Loading records…';
                await Promise.all([loadDataset(), loadHistory()]);
            }

            async function loadDataset() {
                const key = state.dataset;
                const request = ++datasetRequest;
                document.getElementById('dataBody').innerHTML = '<tr><td>Loading filtered records…</td></tr>';

                try {
                    const query = {
                        ...selectedFilters(),
                        page: state.dataPage,
                        per_page: state.dataPerPage
                    };
                    const payload = await apiJson(`${reportsApiBase}/data/${key}?${queryString(query)}`);
                    if (request !== datasetRequest || key !== state.dataset) return;
                    state.payload = payload;
                    state.dataPage = state.payload.pagination.current_page;
                    renderKpis();
                    renderDataTable();
                    renderPagination('data', state.payload.pagination);
                    updateExportSummary();
                } catch (error) {
                    if (request !== datasetRequest || key !== state.dataset) return;
                    showNotice(error.message);
                    document.getElementById('dataBody').innerHTML = '<tr><td>Unable to load this report.</td></tr>';
                }
            }

            function renderKpis() {
                document.getElementById('kpiGrid').innerHTML = state.dataset === 'inventory' ? state.payload.kpis.map(kpi => `
                    <article class="kpi-card">
                        <span>${escapeHtml(kpi.label)}</span>
                        <strong>${escapeHtml(kpi.value)}</strong>
                    </article>
                `).join('') : '';
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

            const isTotalColumn = key => key === 'total' || key.startsWith('total_');
            const summaryTitle = () => `SUMMARY OF EXPENSES (PROJECT SITE-${filterInputs.expense_type.value.toUpperCase()} EXPENSES)`;

            function renderDataTable() {
                const columns = Object.entries(state.payload.columns);
                if (state.dataset === 'expense_summary') document.getElementById('recordsTitle').textContent = summaryTitle();
                document.getElementById('dataHead').innerHTML = '<tr>' + columns
                    .map(([key, label]) => `<th${isTotalColumn(key) ? ' class="report-total-column"' : ''}>${escapeHtml(label)}</th>`).join('')
                    + '</tr>';
                document.getElementById('dataBody').innerHTML = state.payload.rows.length
                    ? state.payload.rows.map(row => (state.dataset === 'contracts' ? '<tr class="report-contract-row">' : '<tr>') + columns
                        .map(([key]) => {
                            const contractProfit = state.dataset === 'contracts' && key.startsWith('profit_loss_');
                            const classes = [isTotalColumn(key) ? 'report-total-column' : '',
                                state.dataset === 'contracts' && key === 'project_name' ? 'report-contract-project' : '',
                                contractProfit ? (Number(row[key]) < 0 ? 'amount-negative' : 'amount-positive') : '',
                                state.dataset === 'expense_summary' && key === 'project_name' ? 'report-project-name' : ''].filter(Boolean).join(' ');
                            const value = state.dataset === 'contracts' && key === 'actual_end_date' && !row[key]
                                ? 'In Progress' : displayValue(key, row[key]);
                            return `<td${classes ? ` class="${classes}"` : ''}>${escapeHtml(value)}</td>`;
                        }).join('')
                        + '</tr>').join('')
                    : `<tr><td colspan="${columns.length}">No records match the selected filters.</td></tr>`;
                document.getElementById('dataFoot').innerHTML = state.dataset === 'expense_summary'
                    ? [state.payload.totals, state.payload.previous_totals, state.payload.month_totals].filter(Boolean)
                        .map((totalRow, index) => `<tr class="report-total-row report-total-row-${index === 0 ? 'current' : (index === 1 ? 'previous' : 'month')}">` + columns.map(([key]) => `<td${isTotalColumn(key) ? ' class="report-total-column"' : ''}>${escapeHtml(displayValue(key, totalRow[key]))}</td>`).join('') + '</tr>').join('')
                    : '';

                const pagination = state.payload.pagination;
                document.getElementById('rowSummary').textContent = state.dataset === 'expense_summary'
                    ? (filterInputs.project_status.value === 'Completed' ? 'COMPLETED PROJECTS' : 'ONGOING PROJECTS')
                    : pagination.total
                    ? `Showing ${pagination.from.toLocaleString()}–${pagination.to.toLocaleString()} of ${pagination.total.toLocaleString()} matching records.`
                    : 'No records match the selected filters.';
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

            function renderColumnChoices() {
                document.getElementById('columnChoices').innerHTML = Object.entries(state.definition.columns).map(([key, label]) => `
                    <label><input type="checkbox" name="columns" value="${escapeHtml(key)}" checked> ${escapeHtml(label)}</label>
                `).join('');
                document.getElementById('exportTitle').value =
                    state.dataset === 'expense_summary'
                        ? 'SUMMARY OF EXPENSES (PROJECT SITE-OVERALL EXPENSES)'
                        : `${state.definition.title} - ${new Date().toLocaleDateString('en-PH')}`;
            }

            function updateExportSummary() {
                const entries = Object.entries(selectedFilters());
                document.getElementById('exportFilterSummary').textContent = entries.length
                    ? entries.map(([key, value]) => `${key.replaceAll('_', ' ')}: ${value}`).join(' · ')
                    : 'No filters applied; all accessible records will be exported.';
            }

            async function loadHistory() {
                if (!state.dataset) return;
                const key = state.dataset;
                const request = ++historyRequest;
                const filters = {
                    dataset: key,
                    search: document.getElementById('historySearch').value.trim(),
                    start_date: document.getElementById('historyStart').value,
                    end_date: document.getElementById('historyEnd').value,
                    page: state.historyPage,
                    per_page: state.historyPerPage
                };

                try {
                    const response = await apiJson(`${reportsApiBase}?${queryString(filters)}`);
                    if (request !== historyRequest || key !== state.dataset) return;
                    const reports = response.data || [];
                    state.historyRecords = {};
                    state.historyPage = response.current_page;
                    document.getElementById('historyBody').innerHTML = reports.length
                        ? reports.map(report => {
                            state.historyRecords[String(report.report_id)] = report;
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
                                    <td class="history-actions"><button type="button" class="pfims-row-action history-view-button" data-report-id="${escapeHtml(report.report_id)}" title="View report details" aria-label="View report details"><img src="${escapeHtml(document.body.dataset.reportViewIcon)}" alt=""></button><a class="pfims-row-action history-download-button" href="${reportsApiBase}/download/${encodeURIComponent(report.report_id)}" download title="Download report" aria-label="Download report"><img src="${escapeHtml(document.body.dataset.reportDownloadIcon)}" alt=""></a></td>
                                </tr>
                            `;
                        }).join('')
                        : '<tr><td colspan="9">No exports have been generated yet.</td></tr>';
                    renderPagination('history', response);
                } catch (error) {
                    if (request !== historyRequest || key !== state.dataset) return;
                    showNotice(error.message);
                }
            }

            const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'];
            let yearPickerStart = 1900;
            const datePickerNames = ['report_month', 'report_day', 'report_year'];

            function updateDatePickerTriggers() {
                datePickerNames.forEach(name => {
                    const input = filterInputs[name];
                    const trigger = input.closest('.report-date-picker').querySelector('.report-date-trigger');
                    trigger.textContent = name === 'report_month'
                        ? monthNames[Number(input.value) - 1] : input.value;
                });
            }

            function closeDatePickers() {
                datePickerNames.forEach(name => {
                    const picker = filterInputs[name].closest('.report-date-picker');
                    picker.querySelector('.report-date-popover').hidden = true;
                    picker.querySelector('.report-date-trigger').setAttribute('aria-expanded', 'false');
                });
            }

            function daysInSelectedMonth() {
                return new Date(Number(filterInputs.report_year.value), Number(filterInputs.report_month.value), 0).getDate();
            }

            function limitSelectedDay() {
                const dayInput = filterInputs.report_day;
                dayInput.value = String(Math.min(Number(dayInput.value), daysInSelectedMonth()));
            }

            function renderDatePicker(name) {
                const input = filterInputs[name];
                const popover = input.closest('.report-date-picker').querySelector('.report-date-popover');
                let values;
                if (name === 'report_month') values = monthNames.map((label, index) => ({ value: index + 1, label }));
                else if (name === 'report_day') values = Array.from({ length: daysInSelectedMonth() }, (_, index) => ({ value: index + 1, label: String(index + 1) }));
                else values = Array.from({ length: 12 }, (_, index) => ({ value: yearPickerStart + index, label: String(yearPickerStart + index) }))
                    .filter(item => item.value <= 2100);
                const navigation = name === 'report_year' ? `<div class="report-year-navigation">
                    <button type="button" data-year-shift="-12" aria-label="Previous years" ${yearPickerStart <= 1900 ? 'disabled' : ''}>‹</button>
                    <span>${yearPickerStart}–${Math.min(yearPickerStart + 11, 2100)}</span>
                    <button type="button" data-year-shift="12" aria-label="Next years" ${yearPickerStart >= 2089 ? 'disabled' : ''}>›</button>
                </div>` : '';
                popover.innerHTML = navigation + `<div class="report-date-options ${name}">` + values.map(item =>
                    `<button type="button" data-picker-value="${item.value}" aria-pressed="${String(item.value) === input.value}">${escapeHtml(item.label)}</button>`
                ).join('') + '</div>';
                popover.querySelectorAll('[data-year-shift]').forEach(button => {
                    button.addEventListener('click', event => {
                        event.preventDefault();
                        event.stopPropagation();
                        yearPickerStart = Math.max(1900, Math.min(2089, yearPickerStart + Number(button.dataset.yearShift)));
                        renderDatePicker(name);
                    });
                });
                popover.querySelectorAll('[data-picker-value]').forEach(button => {
                    button.addEventListener('click', () => {
                        input.value = button.dataset.pickerValue;
                        if (name === 'report_month' || name === 'report_year') limitSelectedDay();
                        updateDatePickerTriggers();
                        closeDatePickers();
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });
            }

            datePickerNames.forEach(name => {
                const picker = filterInputs[name].closest('.report-date-picker');
                const trigger = picker.querySelector('.report-date-trigger');
                const popover = picker.querySelector('.report-date-popover');
                trigger.addEventListener('click', () => {
                    const wasOpen = !popover.hidden;
                    closeDatePickers();
                    if (wasOpen) return;
                    if (name === 'report_year') {
                        yearPickerStart = Math.min(2089, 1900 + Math.floor((Number(filterInputs[name].value) - 1900) / 12) * 12);
                    }
                    renderDatePicker(name);
                    popover.hidden = false;
                    trigger.setAttribute('aria-expanded', 'true');
                    popover.style.left = '0';
                    popover.style.right = 'auto';
                    if (popover.getBoundingClientRect().right > window.innerWidth - 8) {
                        popover.style.left = 'auto';
                        popover.style.right = '0';
                    }
                });
            });
            document.addEventListener('click', event => {
                if (!event.target.closest('.report-date-picker')) closeDatePickers();
            });
            document.addEventListener('keydown', event => {
                if (event.key === 'Escape') closeDatePickers();
            });

            Object.entries(filterInputs).forEach(([name, input]) => {
                input.addEventListener(name === 'search' ? 'input' : 'change', () => {
                    state.dataPage = 1;
                    window.clearTimeout(state.dataTimer);
                    state.dataTimer = window.setTimeout(loadDataset, name === 'search' ? 300 : 0);
                });
            });

            document.getElementById('clearFilters').addEventListener('click', () => {
                Object.values(filterInputs).forEach(input => {
                    input.value = state.dataset === 'expense_summary' && input.dataset.default ? input.dataset.default : '';
                });
                updateDatePickerTriggers();
                closeDatePickers();
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

            function setHistoryDetailValue(id, value) {
                const element = document.getElementById(id);
                if (element) element.textContent = value || '—';
            }

            function openHistoryDetails(report) {
                if (!report) return;
                const filters = Object.entries(report.filters_applied || {})
                    .map(([key, value]) => `${key.replaceAll('_', ' ')}=${value}`).join(', ') || 'All records';
                const options = report.export_options || {};
                setHistoryDetailValue('historyDetailId', report.report_id);
                setHistoryDetailValue('historyDetailReportTitle', report.title);
                setHistoryDetailValue('historyDetailDataset', report.dataset_key);
                setHistoryDetailValue('historyDetailFormat', String(report.export_format || '').toUpperCase());
                setHistoryDetailValue('historyDetailRows', Number(report.row_count || 0).toLocaleString());
                setHistoryDetailValue('historyDetailUser', report.uploaded_by);
                setHistoryDetailValue('historyDetailGenerated', report.generated_at ? new Date(report.generated_at).toLocaleString('en-PH') : '—');
                setHistoryDetailValue('historyDetailFilters', filters);
                setHistoryDetailValue('historyDetailSections', (options.sections || []).join(', ') || 'data');
                setHistoryDetailValue('historyDetailColumns', (report.selected_columns || []).join(', ') || '—');
                document.getElementById('historyDetailDialog').showModal();
            }

            document.getElementById('historyBody').addEventListener('click', event => {
                const button = event.target.closest('.history-view-button');
                if (!button) return;
                openHistoryDetails(state.historyRecords[String(button.dataset.reportId)]);
            });

            const dialog = document.getElementById('exportDialog');
            const closeDialog = () => { if (dialog.open) dialog.close(); };
            const exportDesign = () => ({
                header_color: document.getElementById('exportHeaderColor').value,
                table_spacing: document.getElementById('exportTableSpacing').value
            });
            document.getElementById('editExportDesign').addEventListener('click', event => {
                const controls = document.getElementById('exportDesignControls');
                controls.hidden = !controls.hidden;
                event.currentTarget.setAttribute('aria-expanded', String(!controls.hidden));
            });
            document.getElementById('openExport').addEventListener('click', () => {
                updateExportSummary();
                dialog.showModal();
                loadExportPreview();
            });
            let previewRequest = 0;
            async function loadExportPreview() {
                const current = ++previewRequest;
                const host = document.getElementById('reportPreview');
                const button = document.getElementById('submitExport');
                const columns = [...document.querySelectorAll('input[name="columns"]:checked')].map(input => input.value);
                button.disabled = true;
                if (!columns.length) {
                    host.textContent = 'Select at least one field to preview the report.';
                    return;
                }
                host.textContent = 'Preparing preview…';
                try {
                    const data = await apiJson(`${reportsApiBase}/preview`, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf},
                        body: JSON.stringify({
                            dataset: state.dataset,
                            title: document.getElementById('exportTitle').value.trim(),
                            columns,
                            design: exportDesign(),
                            filters: selectedFilters()
                        })
                    });
                    if (current !== previewRequest) return;
                    const format = document.getElementById('exportFormat').value;
                    const keys = Object.keys(data.columns);
                    const balanceRows = [data.totals, data.previous_totals, data.month_totals].filter(Boolean);
                    const line = row => keys.map(key => String(row[key] ?? '').replaceAll('"', '""')).map(value => /[,"\n]/.test(value) ? `"${value}"` : value).join(',');
                    if (format === 'csv') {
                        host.innerHTML = `<p class="preview-note">CSV stores values only; it cannot contain an image or visual styling.</p><pre>${escapeHtml([
                            data.title, 'As of ' + data.as_of, data.scope, '',
                            line(Object.fromEntries(Object.entries(data.columns).map(([key, label]) => [key, label.toUpperCase()]))), ...data.rows.map(line),
                            ...balanceRows.map(line)
                        ].join('\n'))}</pre>`;
                    } else {
                        const header = Object.entries(data.columns).map(([key, label]) => `<th${isTotalColumn(key) ? ' class="report-total-column"' : ''}>${escapeHtml(label.toUpperCase())}</th>`).join('');
                        const fileValue = (key, value) => typeof value === 'number' || (value !== '' && value !== null && !isNaN(Number(value)) && !['project_name', 'item_name', 'category_name', 'supplier_name', 'unit_name', 'stock_status'].includes(key))
                            ? Number(value).toLocaleString('en-US', {minimumFractionDigits: key === 'item_id' ? 0 : 2, maximumFractionDigits: key === 'item_id' ? 0 : 2}) : (value ?? '');
                        const cells = (row, isBalance = false) => keys.map(key => `<td${!isBalance && state.dataset === 'expense_summary' && key === 'project_name' ? ' class="report-project-name"' : (isTotalColumn(key) ? ' class="report-total-column"' : '')}>${escapeHtml(fileValue(key, row[key]))}</td>`).join('');
                        host.innerHTML = `<div class="preview-paper ${exportDesign().table_spacing === 'compact' ? 'preview-compact' : ''}" data-header-color="${exportDesign().header_color}"><img src="${escapeHtml(document.body.dataset.reportHeaderImage)}" alt="E.V. Catapang Design & Construction header"><h3>${escapeHtml(data.title)}</h3><p>As of ${escapeHtml(data.as_of)}</p><strong>${escapeHtml(data.scope)}</strong><div class="table-wrap"><table data-pfims-standard-actions="off"><thead><tr>${header}</tr></thead><tbody>${data.rows.map(row => `<tr>${cells(row)}</tr>`).join('')}${balanceRows.map((row, index) => `<tr class="report-total-row report-total-row-${index === 0 ? 'current' : (index === 1 ? 'previous' : 'month')}">${cells(row, true)}</tr>`).join('')}</tbody></table></div>${data.row_count > data.rows.length ? `<p class="preview-note">Showing the first ${data.rows.length} of ${data.row_count} rows. Export includes all matching rows.</p>` : ''}</div>`;
                    }
                    button.disabled = false;
                } catch (error) {
                    if (current === previewRequest) host.textContent = 'Preview unavailable: ' + error.message;
                }
            }
            document.getElementById('exportFormat').addEventListener('change', loadExportPreview);
            document.getElementById('exportTitle').addEventListener('input', () => {
                window.clearTimeout(state.previewTimer);
                state.previewTimer = window.setTimeout(loadExportPreview, 300);
            });
            document.getElementById('columnChoices').addEventListener('change', loadExportPreview);
            document.getElementById('exportDesignControls').addEventListener('change', loadExportPreview);
            ['closeExport', 'cancelExport'].forEach(id => {
                document.getElementById(id).addEventListener('click', closeDialog);
            });
            dialog.addEventListener('click', event => {
                if (event.target === dialog) closeDialog();
            });

            const historyDetailDialog = document.getElementById('historyDetailDialog');
            const closeHistoryDetails = () => { if (historyDetailDialog.open) historyDetailDialog.close(); };
            document.getElementById('closeHistoryDetail').addEventListener('click', closeHistoryDetails);
            historyDetailDialog.addEventListener('click', event => {
                if (event.target === historyDetailDialog) closeHistoryDetails();
            });
            document.getElementById('exportForm').addEventListener('submit', async event => {
                event.preventDefault();
                const columns = [...document.querySelectorAll('input[name="columns"]:checked')].map(input => input.value);

                if (!columns.length) {
                    showNotice('Choose at least one report field.');
                    return;
                }

                const button = document.getElementById('submitExport');
                button.disabled = true;
                button.textContent = 'Generating…';

                try {
                    const response = await fetch(`${reportsApiBase}/export`, {
                        credentials: 'same-origin',
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/octet-stream,application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            dataset: state.dataset,
                            title: document.getElementById('exportTitle').value.trim(),
                            format: document.getElementById('exportFormat').value,
                            columns,
                            design: exportDesign(),
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
                    anchor.download = match ? match[1] : 'pfims-report.' + document.getElementById('exportFormat').value;
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

            document.addEventListener('pfims:autorefresh', () => {
                loadDataset();
                loadHistory();
            });
            initialize();
        })();
    </script>
    <script src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}"></script>
</body>
</html>
