<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Reports - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Oreports.css') }}">
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
            <a href="{{ url('/onotifications') }}" onclick="hideBadge(event)" style="position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span>Notifications</span>
                <span class="notif-badge" id="notifBadge">6</span>
            </a>
            <a href="{{ url('/oprofile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>User</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
        <nav>
            <ul>
                <li><a href="{{ url('/odashboard') }}">DASHBOARD</a></li>
                <li><a href="{{ url('/oprojects') }}">PROJECTS</a></li>
                <li><a href="{{ url('/oinventory') }}">INVENTORY</a></li>
                <li><a href="{{ url('/osuppliers') }}">SUPPLIERS</a></li>
                <li class="active"><a href="{{ url('/oreports') }}">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li>
                    <a href="{{ url('/osettings') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                        <img src="{{ asset('images/settings.jpg') }}" alt="Settings" class="nav-icon">
                        Settings
                    </a>
                </li>
                <li class="logout">
                    <a href="{{ url('/olandig') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
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
            <h1>OPERATIONAL REPORTS</h1>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <input type="text" class="search-input" placeholder="Search Report...">
            <input type="date" class="date-input" value="2026-06-01">
            <span style="color: #888; font-size: 0.9rem;">to</span>
            <input type="date" class="date-input" value="2026-06-30">
            <select>
                <option>All Reports</option>
                <option>Project Reports</option>
                <option>Inventory Reports</option>
                <option>Workforce Reports</option>
            </select>
            <button class="btn-filter" onclick="applyFilters()">Apply Filters</button>
        </div>

        <!-- Tab Row -->
        <div class="tab-row">
            <div class="report-tabs">
                <span class="tab active" onclick="switchTab(this, 'projects')">Projects</span>
                <span class="tab" onclick="switchTab(this, 'inventory')">Inventory</span>
                <span class="tab" onclick="switchTab(this, 'workforce')">Workforce</span>
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
            <div class="section-label">Operational KPIs</div>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Active Projects</div>
                    <div class="kpi-value">24</div>
                    <div class="kpi-sub">6 completed this month</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Equipment Units</div>
                    <div class="kpi-value">156</div>
                    <div class="kpi-sub">12 under maintenance</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Workforce</div>
                    <div class="kpi-value">342</div>
                    <div class="kpi-sub">28 new hires this month</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total Reports</div>
                    <div class="kpi-value">256</div>
                    <div class="kpi-sub">Operational reports generated</div>
                </div>
            </div>
        </div>

        <!-- ─── CHARTS SECTION ─── -->
        <div class="charts-section">
            <div class="section-label">Operational Charts</div>
            <div class="charts-grid">
                <div class="chart-card">Project Completion Chart</div>
                <div class="chart-card">Inventory Usage Chart</div>
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
                        <td>Project Completion Report - Q2 2026</td>
                        <td>Project</td>
                        <td>2026-06-30</td>
                        <td>Admin User</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-002</strong></td>
                        <td>Inventory Stock Report</td>
                        <td>Inventory</td>
                        <td>2026-06-28</td>
                        <td>Operations</td>
                        <td><span class="status-badge in-progress"><span class="dot"></span> In Progress</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-003</strong></td>
                        <td>Equipment Utilization Report</td>
                        <td>Inventory</td>
                        <td>2026-06-25</td>
                        <td>Operations</td>
                        <td><span class="status-badge pending"><span class="dot"></span> Pending</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-004</strong></td>
                        <td>Labor Cost Analysis</td>
                        <td>Project</td>
                        <td>2026-06-23</td>
                        <td>Admin User</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-005</strong></td>
                        <td>Material Consumption Report</td>
                        <td>Inventory</td>
                        <td>2026-06-22</td>
                        <td>Operations</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-006</strong></td>
                        <td>Project Timeline Variance</td>
                        <td>Project</td>
                        <td>2026-06-21</td>
                        <td>Admin User</td>
                        <td><span class="status-badge pending"><span class="dot"></span> Pending</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-007</strong></td>
                        <td>Workforce Productivity Report</td>
                        <td>Workforce</td>
                        <td>2026-06-20</td>
                        <td>Operations</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-008</strong></td>
                        <td>Equipment Maintenance Schedule</td>
                        <td>Inventory</td>
                        <td>2026-06-19</td>
                        <td>Operations</td>
                        <td><span class="status-badge in-progress"><span class="dot"></span> In Progress</span></td>
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
                    'projects': 'Project Reports',
                    'inventory': 'Inventory Reports',
                    'workforce': 'Workforce Reports'
                };
                dropdown.value = options[type] || 'All Reports';
            }

            var kpiValues = {
                'projects': {
                    total: '24',
                    completed: '18',
                    pending: '6',
                    value: '128'
                },
                'inventory': {
                    total: '156',
                    completed: '144',
                    pending: '12',
                    value: '64'
                },
                'workforce': {
                    total: '342',
                    completed: '314',
                    pending: '28',
                    value: '64'
                }
            };

            var data = kpiValues[type] || kpiValues['projects'];
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