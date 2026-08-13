<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Accounting Dashboard - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Adashboard.css') }}">
    <style>
        .error-notification { z-index: 9999 !important; }
        .success-notification { z-index: 9999 !important; }
    </style>
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="dashboard-page">

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
                <span>{{ auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
        <nav>
            <ul>
                <li class="active"><a href="{{ url('/adashboard') }}">DASHBOARD</a></li>
                <li><a href="{{ url('/afinance') }}">FINANCE</a></li>
                <li><a href="{{ url('/areports') }}">REPORTS</a></li>
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
                    <form method="POST" action="{{ url('/logout') }}" style="width: 100%; margin: 0; padding: 0;">
                        @csrf
                        <button type="submit" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%; background: none; border: none; cursor: pointer; padding: 0; font: inherit; color: inherit;">
                            <img src="{{ asset('images/logout.jpg') }}" alt="Log Out" class="nav-icon">
                            Log out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>

    <!-- ─── MAIN CONTENT ─── -->
    <main class="main-content">

        <!-- Page Title -->
        <div class="page-header">
            <h1>DASHBOARD <small>financial overview</small></h1>
        </div>

        <!-- Stats Cards (2 cards only) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Budget</div>
                <div class="stat-value" id="totalBudgetDisplay">₱0.00</div>
                <div class="stat-sub">All projects combined</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Net Variance</div>
                <div class="stat-value" id="netVarianceDisplay" style="color: #2e7d32;">₱0.00</div>
                <div class="stat-sub">vs. planned budget</div>
            </div>
        </div>

        <!-- ─── BUDGET ALLOCATION VS SPENDING (LINE CHART) ─── -->
        <div class="charts-row">
            <div class="chart-box">
                <h3>BUDGET ALLOCATION VS SPENDING</h3>
                <div class="line-chart">
                    <svg viewBox="0 0 500 180" preserveAspectRatio="xMidYMid meet">
                        <line x1="40" y1="20" x2="480" y2="20" class="grid-line" />
                        <line x1="40" y1="60" x2="480" y2="60" class="grid-line" />
                        <line x1="40" y1="100" x2="480" y2="100" class="grid-line" />
                        <line x1="40" y1="140" x2="480" y2="140" class="grid-line" />
                        <text x="30" y="20" class="y-label">500</text>
                        <text x="30" y="60" class="y-label">400</text>
                        <text x="30" y="100" class="y-label">300</text>
                        <text x="30" y="140" class="y-label">200</text>
                        <text x="30" y="170" class="y-label">100</text>
                        <text x="30" y="175" class="y-label">0</text>
                        <polygon class="area-path" points="40,40 128,33 216,30 304,26 392,23 480,20 480,170 40,170" />
                        <polyline class="line-path" points="40,40 128,33 216,30 304,26 392,23 480,20" />
                        <circle cx="40" cy="40" r="5" class="dot" />
                        <text x="40" y="30" class="dot-label">430</text>
                        <text x="40" y="175" class="x-label">Jan</text>
                        <circle cx="128" cy="33" r="5" class="dot" />
                        <text x="128" y="23" class="dot-label">450</text>
                        <text x="128" y="175" class="x-label">Feb</text>
                        <circle cx="216" cy="30" r="5" class="dot" />
                        <text x="216" y="20" class="dot-label">460</text>
                        <text x="216" y="175" class="x-label">Mar</text>
                        <circle cx="304" cy="26" r="5" class="dot" />
                        <text x="304" y="16" class="dot-label">470</text>
                        <text x="304" y="175" class="x-label">Apr</text>
                        <circle cx="392" cy="23" r="5" class="dot" />
                        <text x="392" y="13" class="dot-label">480</text>
                        <text x="392" y="175" class="x-label">May</text>
                        <circle cx="480" cy="20" r="5" class="dot" />
                        <text x="480" y="10" class="dot-label">490</text>
                        <text x="480" y="175" class="x-label">Jun</text>
                    </svg>
                </div>
            </div>
        </div>

        <!-- ─── PROJECT BUDGET OVERVIEW TABLE ─── -->
        <div class="budget-section">

            <!-- Search Filter -->
            <div class="filter-row">
                <input type="text" 
                       id="budgetSearch" 
                       class="project-filter" 
                       placeholder="Search project name..." 
                       oninput="filterBudgetTable()">
                <button class="btn-clear-search" onclick="clearBudgetSearch()">✕ Clear</button>
            </div>

            <!-- Budget Table -->
            <div class="budget-table-wrapper">
                <table id="budgetTable">
                    <thead>
                        <tr>
                            <th>Project Name</th>
                            <th>Budget Amount</th>
                            <th>Total Expenses</th>
                            <th>Variance</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="budgetTableBody">
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 20px; color: #888;">Loading budget data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-wrapper">
                <div class="rows-info">
                    <span id="budgetRowsInfo">Showing 0 of 0 projects</span>
                    <select id="budgetRowsPerPage" onchange="changeBudgetPageSize()">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="pagination-links" id="budgetPaginationLinks">
                    <!-- Generated by JavaScript -->
                </div>
            </div>
        </div>

    </main>

    <script>
        // ─── CSRF TOKEN ───
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // ─── STATE VARIABLES ───────────────────────────────────────────
        var budgetData = [];
        var budgetFilteredData = [];
        var budgetPageSize = 25;
        var budgetCurrentPage = 1;

        // ─── NOTIFICATION FUNCTIONS ──────────────────────────────────
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // ─── ERROR NOTIFICATION ───
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

        // ─── SUCCESS NOTIFICATION ───
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

        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });

        // ─── UTILITY FUNCTIONS ───────────────────────────────────────
        function formatCurrency(value) {
            var amount = parseFloat(value) || 0;
            return '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // ─── FETCH BUDGET DATA ──────────────────────────────────────
        function fetchBudgetData() {
            fetch('/api/budgets')
                .then(function(response) {
                    if (!response.ok) throw new Error('Failed to load budget data.');
                    return response.json();
                })
                .then(function(data) {
                    budgetData = data || [];
                    budgetFilteredData = budgetData;
                    renderBudgetPage(1);
                    updateDashboardStats();
                })
                .catch(function(error) {
                    showError(error.message);
                    var tbody = document.getElementById('budgetTableBody');
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #d32f2f;">Error loading budget data</td></tr>';
                });
        }

        // ─── RENDER BUDGET TABLE ─────────────────────────────────────
        function renderBudgetPage(page) {
            budgetCurrentPage = page;
            var start = (page - 1) * budgetPageSize;
            var end = Math.min(start + budgetPageSize, budgetFilteredData.length);
            var pageData = budgetFilteredData.slice(start, end);
            
            var tbody = document.getElementById('budgetTableBody');
            tbody.innerHTML = '';
            
            if (!pageData.length) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: #888;">No budget data found.</td></tr>';
                renderBudgetPagination();
                updateBudgetRowsInfo(0);
                return;
            }
            
            pageData.forEach(function(budget) {
                var row = document.createElement('tr');
                
                var budgetAmount = parseFloat(budget.budget_amount) || 0;
                var actualAmount = parseFloat(budget.actual_amount) || 0;
                var variance = budgetAmount - actualAmount;
                var variancePercentage = budgetAmount > 0 ? (variance / budgetAmount) * 100 : 0;
                
                var statusClass = variance < 0 ? 'over-budget' : (variancePercentage < 10 ? 'at-risk' : 'on-track');
                var statusText = variance < 0 ? 'Over Budget' : (variancePercentage < 10 ? 'At Risk' : 'On Track');
                var statusColor = variance < 0 ? '#c62828' : (variancePercentage < 10 ? '#e65100' : '#2e7d32');
                
                row.innerHTML = 
                    '<td><strong>' + (budget.project_name || 'Unnamed Project') + '</strong></td>' +
                    '<td>' + formatCurrency(budgetAmount) + '</td>' +
                    '<td>' + formatCurrency(actualAmount) + '</td>' +
                    '<td style="color: ' + statusColor + '; font-weight: 600;">' +
                        (variance < 0 ? '▼' : '▲') + ' ' + formatCurrency(Math.abs(variance)) +
                        '<br><small style="color: #888; font-weight: 400; font-size: 0.7rem;">' + 
                        variancePercentage.toFixed(1) + '%</small>' +
                    '</td>' +
                    '<td><span class="status-badge ' + statusClass + '">' + statusText + '</span></td>';
                tbody.appendChild(row);
            });
            
            renderBudgetPagination();
            updateBudgetRowsInfo(budgetFilteredData.length);
        }

        function renderBudgetPagination() {
            var container = document.getElementById('budgetPaginationLinks');
            if (!container) return;
            
            var total = budgetFilteredData.length;
            var totalPages = Math.ceil(total / budgetPageSize);
            var current = budgetCurrentPage;
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            var html = '';
            
            html += '<a href="#" onclick="renderBudgetPage(' + (current - 1) + '); return false;" class="' + (current <= 1 ? 'disabled' : '') + '">«</a>';
            
            if (totalPages <= 7) {
                for (var i = 1; i <= totalPages; i++) {
                    html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            } else {
                for (var i = 1; i <= 3; i++) {
                    html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
                
                if (current > 4) {
                    html += '<span class="dots">...</span>';
                }
                
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                for (var i = startPage; i <= endPage; i++) {
                    html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
                
                if (current < totalPages - 3) {
                    html += '<span class="dots">...</span>';
                }
                
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    if (i > 3) {
                        html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                    }
                }
            }
            
            html += '<a href="#" onclick="renderBudgetPage(' + (current + 1) + '); return false;" class="' + (current >= totalPages ? 'disabled' : '') + '">»</a>';
            
            container.innerHTML = html;
        }

        function changeBudgetPageSize() {
            var select = document.getElementById('budgetRowsPerPage');
            budgetPageSize = parseInt(select.value) || 25;
            budgetCurrentPage = 1;
            renderBudgetPage(1);
        }

        function updateBudgetRowsInfo(totalCount) {
            var rowsInfo = document.getElementById('budgetRowsInfo');
            if (!rowsInfo) return;
            
            var currentPage = budgetCurrentPage || 1;
            var pageSize = budgetPageSize || 25;
            var start = (currentPage - 1) * pageSize + 1;
            var end = Math.min(start + pageSize - 1, totalCount);
            
            if (totalCount === 0) {
                rowsInfo.textContent = 'Showing 0 of 0 projects';
            } else {
                rowsInfo.textContent = 'Showing ' + start + '-' + end + ' of ' + totalCount + ' projects';
            }
        }

        // ─── UPDATE DASHBOARD STATS ──────────────────────────────────
        function updateDashboardStats() {
            var totalBudget = budgetData.reduce(function(sum, item) {
                return sum + (parseFloat(item.budget_amount) || 0);
            }, 0);
            
            var totalExpenses = budgetData.reduce(function(sum, item) {
                return sum + (parseFloat(item.actual_amount) || 0);
            }, 0);
            
            var variance = totalBudget - totalExpenses;
            
            document.getElementById('totalBudgetDisplay').textContent = formatCurrency(totalBudget);
            
            var varianceDisplay = document.getElementById('netVarianceDisplay');
            varianceDisplay.textContent = formatCurrency(variance);
            varianceDisplay.style.color = variance < 0 ? '#d32f2f' : '#2e7d32';
        }

        // ─── FILTER FUNCTIONS ────────────────────────────────────────
        function filterBudgetTable() {
            var searchTerm = document.getElementById('budgetSearch').value.toLowerCase().trim();
            
            if (!searchTerm) {
                budgetFilteredData = budgetData;
            } else {
                budgetFilteredData = budgetData.filter(function(item) {
                    var projectName = (item.project_name || '').toLowerCase();
                    var clientName = (item.client_name || '').toLowerCase();
                    var manager = (item.project_manager || '').toLowerCase();
                    return projectName.includes(searchTerm) || 
                           clientName.includes(searchTerm) || 
                           manager.includes(searchTerm);
                });
            }
            
            renderBudgetPage(1);
        }

        function clearBudgetSearch() {
            document.getElementById('budgetSearch').value = '';
            filterBudgetTable();
        }

        // ─── ENTER KEY SUPPORT ───────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            var searchInput = document.getElementById('budgetSearch');
            if (searchInput) {
                searchInput.addEventListener('keyup', function(e) {
                    if (e.key === 'Enter') {
                        filterBudgetTable();
                    }
                });
            }
        });

        // ─── INIT ─────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            fetchBudgetData();
        });
    </script>

</body>
</html>
