<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
</head>
<body>

    @include('partials.header')

    <!-- ─── MAIN CONTENT ─── -->
    <main class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1>REPORTS</h1>
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
                <option>Financial Reports</option>
                <option>Inventory Reports</option>
            </select>
            <button class="btn-filter" onclick="alert('Filters applied!')">Apply Filters</button>
        </div>

        <!-- Report Navigation Tabs -->
        <div class="report-tabs">
            <span class="tab active" onclick="switchTab(this, 'projects')">Projects</span>
            <span class="tab" onclick="switchTab(this, 'finance')">Finance</span>
            <span class="tab" onclick="switchTab(this, 'inventory')">Inventory</span>
        </div>

        <!-- ─── KPI SECTION ─── -->
        <div class="kpi-section">
            <div class="section-label">KPIs</div>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Total Reports</div>
                    <div class="kpi-value">1,284</div>
                    <div class="kpi-sub">+12% vs last month</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Completed</div>
                    <div class="kpi-value">892</div>
                    <div class="kpi-sub">69% of total</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Pending</div>
                    <div class="kpi-value">392</div>
                    <div class="kpi-sub">31% of total</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Total Value</div>
                    <div class="kpi-value">₱2.4M</div>
                    <div class="kpi-sub">Across all reports</div>
                </div>
            </div>
        </div>

        <!-- ─── CHARTS SECTION ─── -->
        <div class="charts-section">
            <div class="section-label">Charts</div>
            <div class="charts-grid">
                <div class="chart-card">Chart placeholder</div>
                <div class="chart-card">Chart placeholder</div>
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
                        <td>Financial Summary - June 2026</td>
                        <td>Finance</td>
                        <td>2026-06-29</td>
                        <td>Finance Team</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-003</strong></td>
                        <td>Inventory Stock Report</td>
                        <td>Inventory</td>
                        <td>2026-06-28</td>
                        <td>Operations</td>
                        <td><span class="status-badge in-progress"><span class="dot"></span> In Progress</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-004</strong></td>
                        <td>Supplier Performance Review</td>
                        <td>Supplier</td>
                        <td>2026-06-27</td>
                        <td>Procurement</td>
                        <td><span class="status-badge pending"><span class="dot"></span> Pending</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-005</strong></td>
                        <td>Project Budget vs Actual</td>
                        <td>Project</td>
                        <td>2026-06-26</td>
                        <td>Admin User</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-006</strong></td>
                        <td>Equipment Utilization Report</td>
                        <td>Inventory</td>
                        <td>2026-06-25</td>
                        <td>Operations</td>
                        <td><span class="status-badge pending"><span class="dot"></span> Pending</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-007</strong></td>
                        <td>Monthly Expense Summary</td>
                        <td>Finance</td>
                        <td>2026-06-24</td>
                        <td>Finance Team</td>
                        <td><span class="status-badge in-progress"><span class="dot"></span> In Progress</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-008</strong></td>
                        <td>Labor Cost Analysis</td>
                        <td>Project</td>
                        <td>2026-06-23</td>
                        <td>Admin User</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-009</strong></td>
                        <td>Material Consumption Report</td>
                        <td>Inventory</td>
                        <td>2026-06-22</td>
                        <td>Operations</td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr>
                        <td><strong>#RPT-010</strong></td>
                        <td>Project Timeline Variance</td>
                        <td>Project</td>
                        <td>2026-06-21</td>
                        <td>Admin User</td>
                        <td><span class="status-badge pending"><span class="dot"></span> Pending</span></td>
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
        // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
            // The link will still navigate to /notifications.
        }

        // ─── TAB SWITCHING ───
        function switchTab(el, type) {
            var tabs = document.querySelectorAll('.report-tabs .tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            el.classList.add('active');

            // Update filter dropdown to match selected tab
            var dropdown = document.querySelector('.filters-bar select');
            if (dropdown) {
                var options = {
                    'projects': 'Project Reports',
                    'finance': 'Financial Reports',
                    'inventory': 'Inventory Reports'
                };
                dropdown.value = options[type] || 'All Reports';
            }

            // Update KPI values based on selected tab
            var kpiValues = {
                'projects': {
                    total: '284',
                    completed: '192',
                    pending: '92',
                    value: '₱1.2M'
                },
                'finance': {
                    total: '456',
                    completed: '324',
                    pending: '132',
                    value: '₱850K'
                },
                'inventory': {
                    total: '544',
                    completed: '376',
                    pending: '168',
                    value: '₱350K'
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

            console.log('Switched to: ' + type);
        }
    </script>

</body>
</html>