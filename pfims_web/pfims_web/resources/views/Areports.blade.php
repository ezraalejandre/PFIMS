<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Reports - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Areports.css') }}">
    <style>
        .error-notification { z-index: 9999 !important; }
        .success-notification { z-index: 9999 !important; }
        .icon-group {
            display: flex;
            gap: 4px;
            background: #fff;
            padding: 4px 6px;
            border-radius: 8px;
            border: 1px solid #ddd;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .btn-action-icon {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px 8px;
            transition: 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }
        .btn-action-icon:hover {
            background: rgba(0,0,0,0.06);
            transform: scale(1.05);
        }
        .btn-action-icon img {
            width: 22px;
            height: 22px;
            object-fit: contain;
        }
    </style>
</head>
<body>

    <!-- ─── ERROR NOTIFICATION (POP-UP) ─── -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon">⚠</span>
            <span id="errorMessage">An error occurred. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- ─── SUCCESS NOTIFICATION (POP-UP) ─── -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span id="successMessage">Action completed successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- ─── FULL-WIDTH HEADER (Fixed) ─── -->
    <header class="top-header">
        <div class="left">
            <img src="{{ asset('images/logo.jpg') }}" alt="Logo">
            <div class="brand-text">
                PFIMS
                <small>E.V. Catapang Design-Construction & Supply</small>
            </div>
        </div>
        <div class="right">
            <a href="{{ url('/anotifications') }}" onclick="hideBadge(event)" style="position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span>Notifications</span>
                <span class="notif-badge" id="notifBadge">6</span>
            </a>
            <a href="{{ url('/aprofile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>User</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
        <nav>
            <ul>
                <li><a href="{{ url('/adashboard') }}">DASHBOARD</a></li>
                <li><a href="{{ url('/afinance') }}">FINANCE</a></li>
                <li class="active"><a href="{{ url('/areports') }}">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li>
                    <a href="{{ url('/asettings') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                        <img src="{{ asset('images/settings.jpg') }}" alt="Settings" class="nav-icon">
                        Settings
                    </a>
                </li>
                <li class="logout">
                    <a href="{{ url('/alanding') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                        <img src="{{ asset('images/logout.jpg') }}" alt="Log Out" class="nav-icon">
                        Log out
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- ─── MAIN CONTENT ─── -->
    <main class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1>FINANCIAL REPORTS</h1>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <input type="text" class="search-input" placeholder="Search Report...">
            <input type="date" class="date-input" value="2026-06-01">
            <span style="color: #888; font-size: 0.9rem;">to</span>
            <input type="date" class="date-input" value="2026-06-30">
            <select>
                <option>All Reports</option>
                <option>Financial Reports</option>
                <option>Expense Reports</option>
                <option>Budget Reports</option>
            </select>
            <button class="btn-filter" onclick="applyFilters()">Apply Filters</button>
        </div>

        <!-- Tab Row -->
        <div class="tab-row">
            <div class="report-tabs">
                <span class="tab active" onclick="switchTab(this, 'finance')">Finance</span>
                <span class="tab" onclick="switchTab(this, 'budget')">Budget</span>
                <span class="tab" onclick="switchTab(this, 'expenses')">Expenses</span>
            </div>
            <div class="tab-actions">
                <div class="icon-group">
                    <button class="btn-action-icon" onclick="refreshReports()" title="Refresh">
                        <img src="{{ asset('images/refresh.jpg') }}" alt="Refresh">
                    </button>
                    <button class="btn-action-icon" onclick="exportReports()" title="Export">
                        <img src="{{ asset('images/export.jpg') }}" alt="Export">
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── KPI SECTION ─── -->
        <div class="kpi-section">
            <div class="section-label">Financial KPIs</div>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Total Budget</div>
                    <div class="kpi-value">₱67,000,000</div>
                    <div class="kpi-sub">All projects combined</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total Expenses</div>
                    <div class="kpi-value">₱54,200,000</div>
                    <div class="kpi-sub">80.9% of budget</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Net Variance</div>
                    <div class="kpi-value" style="color: #d32f2f;">-₱440</div>
                    <div class="kpi-sub">vs. planned budget</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total Reports</div>
                    <div class="kpi-value">128</div>
                    <div class="kpi-sub">Financial reports generated</div>
                </div>
            </div>
        </div>

        <!-- ─── CHARTS SECTION ─── -->
        <div class="charts-section">
            <div class="section-label">Financial Charts</div>
            <div class="charts-grid">
                <div class="chart-card">Budget Allocation Chart</div>
                <div class="chart-card">Expense by Category Chart</div>
            </div>
        </div>

        <!-- ─── TABLE ─── -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Title</th>
                        <th>Type</th>
                        <th>Date Generated</th>
                        <th>Created By</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>#RPT-001</strong></td>
                        <td>Financial Summary - June 2026</td>
                        <td>Finance</td>
                        <td>2026-06-30</td>
                        <td>Admin User</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-002</strong></td>
                        <td>Project Budget vs Actual</td>
                        <td>Budget</td>
                        <td>2026-06-29</td>
                        <td>Admin User</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-003</strong></td>
                        <td>Monthly Expense Summary</td>
                        <td>Expense</td>
                        <td>2026-06-28</td>
                        <td>Finance Team</td>
                        <td><span class="status-badge in-progress"><span class="dot"></span> In Progress</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-004</strong></td>
                        <td>Labor Cost Analysis</td>
                        <td>Expense</td>
                        <td>2026-06-27</td>
                        <td>Admin User</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-005</strong></td>
                        <td>Q2 Budget Performance</td>
                        <td>Budget</td>
                        <td>2026-06-26</td>
                        <td>Finance Team</td>
                        <td><span class="status-badge pending"><span class="dot"></span> Pending</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-006</strong></td>
                        <td>Material Cost Summary</td>
                        <td>Expense</td>
                        <td>2026-06-25</td>
                        <td>Operations</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-007</strong></td>
                        <td>Annual Budget Projection</td>
                        <td>Budget</td>
                        <td>2026-06-24</td>
                        <td>Admin User</td>
                        <td><span class="status-badge in-progress"><span class="dot"></span> In Progress</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-008</strong></td>
                        <td>Expense by Category Report</td>
                        <td>Expense</td>
                        <td>2026-06-23</td>
                        <td>Finance Team</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="rows-info">
                Rows Displayed:
                <select>
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                </select>
            </div>
            <div class="pagination-links">
                <a href="#">«</a>
                <a href="#" class="active">1</a>
                <a href="#">2</a>
                <a href="#">3</a>
                <span class="dots">...</span>
                <a href="#">12</a>
                <a href="#">13</a>
                <a href="#">»</a>
            </div>
        </div>

    </main>

    <script>
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        }

        function showError(message) {
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            if (msgSpan) {
                msgSpan.textContent = message || 'An error occurred. Please try again.';
            }
            notif.style.display = 'block';
            if (window.errorTimeout) clearTimeout(window.errorTimeout);
            window.errorTimeout = setTimeout(function() {
                closeError();
            }, 5000);
        }

        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
            if (window.errorTimeout) {
                clearTimeout(window.errorTimeout);
                window.errorTimeout = null;
            }
        }

        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            if (msgSpan) {
                msgSpan.textContent = message || 'Action completed successfully!';
            }
            notif.style.display = 'block';
            if (window.successTimeout) clearTimeout(window.successTimeout);
            window.successTimeout = setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
            if (window.successTimeout) {
                clearTimeout(window.successTimeout);
                window.successTimeout = null;
            }
        }

        function applyFilters() {
            showSuccess('Filters applied successfully!');
        }

        function refreshReports() {
            showSuccess('Reports refreshed successfully!');
        }

        function exportReports() {
            showSuccess('Export functionality coming soon!');
        }

        function switchTab(el, type) {
            var tabs = document.querySelectorAll('.report-tabs .tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            el.classList.add('active');

            var dropdown = document.querySelector('.filters-bar select');
            if (dropdown) {
                var options = {
                    'finance': 'Financial Reports',
                    'budget': 'Budget Reports',
                    'expenses': 'Expense Reports'
                };
                dropdown.value = options[type] || 'All Reports';
            }

            var kpiValues = {
                'finance': {
                    total: '₱67,000,000',
                    completed: '₱54,200,000',
                    pending: '-₱440',
                    value: '128'
                },
                'budget': {
                    total: '₱67,000,000',
                    completed: '₱62,500,000',
                    pending: '₱4,500,000',
                    value: '42'
                },
                'expenses': {
                    total: '₱54,200,000',
                    completed: '₱48,300,000',
                    pending: '₱5,900,000',
                    value: '64'
                }
            };

            var data = kpiValues[type] || kpiValues['finance'];
            var kpiValuesEl = document.querySelectorAll('.kpi-card .kpi-value');
            var kpiSubs = document.querySelectorAll('.kpi-card .kpi-sub');

            if (kpiValuesEl.length >= 4) {
                kpiValuesEl[0].textContent = data.total;
                kpiValuesEl[1].textContent = data.completed;
                kpiValuesEl[2].textContent = data.pending;
                kpiValuesEl[3].textContent = data.value;
            }

            showSuccess('Switched to ' + type + ' reports.');
            console.log('Switched to: ' + type);
        }

        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });
    </script>

</body>
</html>