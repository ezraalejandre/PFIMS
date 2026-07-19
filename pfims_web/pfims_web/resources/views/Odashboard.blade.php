<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Operations Dashboard - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Odashboard.css') }}">
    <style>
        .error-notification { z-index: 9999 !important; }
        .success-notification { z-index: 9999 !important; }
        
        /* Material Forecast Table */
        .forecast-table-wrapper {
            background: #fff;
            border-radius: 16px;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            overflow-x: auto;
            margin-top: 15px;
        }
        
        .forecast-table-wrapper table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            min-width: 700px;
        }
        
        .forecast-table-wrapper table thead th {
            text-align: left;
            padding: 12px 16px;
            color: #888;
            font-weight: 600;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f0f0f0;
            white-space: nowrap;
        }
        
        .forecast-table-wrapper table tbody td {
            padding: 10px 16px;
            border-bottom: 1px solid #f5f5f5;
            color: #333;
            white-space: nowrap;
        }
        
        .forecast-table-wrapper table tbody tr:hover {
            background: #faf8f5;
        }
        
        .forecast-table-wrapper table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .forecast-status-badge {
            display: inline-block;
            padding: 3px 12px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }
        
        .forecast-status-badge.reorder {
            background: #ffebee;
            color: #c62828;
        }
        
        .forecast-status-badge.low {
            background: #fff3e0;
            color: #e65100;
        }
        
        .forecast-status-badge.sufficient {
            background: #e8f5e9;
            color: #2e7d32;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .section-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
            color: #1a2b3c;
        }
        
        .btn-refresh {
            background: #c9a96e;
            color: #fff;
            border: none;
            padding: 6px 16px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.8rem;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn-refresh:hover {
            background: #b8975a;
            transform: translateY(-2px);
        }
        
        .loading-text {
            text-align: center;
            padding: 20px;
            color: #888;
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #aaa;
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
                <span>{{ auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
        <nav>
            <ul>
                <li class="active"><a href="{{ url('/odashboard') }}">DASHBOARD</a></li>
                <li><a href="{{ url('/oprojects') }}">PROJECTS</a></li>
                <li><a href="{{ url('/oinventory') }}">INVENTORY</a></li>
                <li><a href="{{ url('/osuppliers') }}">SUPPLIERS</a></li>
                <li><a href="{{ url('/oreports') }}">REPORTS</a></li>
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
            <h1>DASHBOARD <small>operations overview</small></h1>
        </div>

        <!-- Stats Cards (Operational only) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Active Projects</div>
                <div class="stat-value" id="activeProjectsCount">0</div>
                <div class="stat-sub" id="activeProjectsSub">Loading...</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Low Stock Items</div>
                <div class="stat-value" id="lowStockCount">0</div>
                <div class="stat-sub" id="lowStockSub">Loading...</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Inventory Items</div>
                <div class="stat-value" id="totalItemsCount">0</div>
                <div class="stat-sub" id="totalItemsSub">Loading...</div>
            </div>
        </div>

        <!-- ─── PROJECT COMPLETION TREND (BAR CHART) ─── -->
        <div class="charts-row">
            <div class="chart-box" style="grid-column: 1 / -1; max-width: 800px; margin: 0 auto;">
                <h3>PROJECT COMPLETION TREND</h3>
                <div class="bar-chart" id="completionChart">
                    <div class="bar-group"><div class="bar" style="height:35px;"></div><span class="bar-label">Jan</span></div>
                    <div class="bar-group"><div class="bar" style="height:60px;"></div><span class="bar-label">Feb</span></div>
                    <div class="bar-group"><div class="bar" style="height:50px;"></div><span class="bar-label">Mar</span></div>
                    <div class="bar-group"><div class="bar" style="height:80px;"></div><span class="bar-label">Apr</span></div>
                    <div class="bar-group"><div class="bar" style="height:70px;"></div><span class="bar-label">May</span></div>
                    <div class="bar-group"><div class="bar" style="height:90px;"></div><span class="bar-label">Jun</span></div>
                </div>
            </div>
        </div>

        <!-- ─── MATERIAL DEMAND FORECAST ─── -->
        <div class="projects-section" style="margin-top: 30px;">
            <div class="section-header">
                <h2>MATERIAL DEMAND FORECAST</h2>
                <button class="btn-refresh" onclick="loadMaterialForecast()">🔄 Refresh Forecast</button>
            </div>
            <div class="forecast-table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Current Stock</th>
                            <th>Avg Usage</th>
                            <th>Projected Demand</th>
                            <th>Reorder Level</th>
                            <th>Status</th>
                            <th>Recommendation</th>
                        </tr>
                    </thead>
                    <tbody id="forecastTableBody">
                        <tr>
                            <td colspan="7" class="loading-text">Loading forecast data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ─── ACTIVE PROJECTS LIST ─── -->
        <div class="projects-section">
            <div class="section-header">
                <h2>ACTIVE PROJECTS</h2>
            </div>
            <div class="projects-list" id="projectsList">
                <div class="loading-text">Loading projects...</div>
            </div>
        </div>

        <!-- ─── PROJECTS PAGINATION ─── -->
        <div class="pagination-wrapper" id="projectsPagination" style="margin-top: 15px; display: none;">
            <div class="rows-info">
                Showing <span id="projectsShowingStart">0</span>-<span id="projectsShowingEnd">0</span> of <span id="projectsTotalCount">0</span> projects
                <select id="projectsRowsPerPage" onchange="changeProjectsPageSize()">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div class="pagination-links" id="projectsPaginationLinks">
                <!-- Generated by JavaScript -->
            </div>
        </div>

    </main>

    <!-- ─── PROJECT DETAIL MODAL ─── -->
    <div id="projectDetailModal" class="modal-overlay modal-update">
        <div class="modal-container">
            <div class="modal-header">
                <div>
                    <h2 id="detailProjectName" style="margin-bottom: 2px;">Project Name</h2>
                    <span class="subtitle" id="detailClientName">Client</span>
                </div>
                <button class="modal-close" onclick="closeProjectDetail()">×</button>
            </div>
            <div class="project-details-grid">
                <div class="detail-item">
                    <label>Budget</label>
                    <span id="detailBudget">—</span>
                </div>
                <div class="detail-item">
                    <label>Start Date</label>
                    <span id="detailStartDate">—</span>
                </div>
                <div class="detail-item">
                    <label>Est. End Date</label>
                    <span id="detailEstEndDate">—</span>
                </div>
                <div class="detail-item">
                    <label>Actual End Date</label>
                    <span id="detailActualEndDate">—</span>
                </div>
                <div class="detail-item">
                    <label>Duration</label>
                    <span id="detailDuration">—</span>
                </div>
                <div class="detail-item">
                    <label>Phase</label>
                    <span id="detailPhase" class="phase-badge">—</span>
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span id="detailStatus" class="status-badge">—</span>
                </div>
                <div class="detail-item">
                    <label>Completion</label>
                    <span id="detailCompletion">—</span>
                </div>
            </div>
            <div class="modal-footer" style="justify-content: flex-end;">
                <button class="btn-cancel" onclick="closeProjectDetail()">Close</button>
                <button class="btn-view-project" onclick="viewProject()">View Project</button>
            </div>
        </div>
    </div>

    <script>
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        var currentProjectData = null;
        var allProjects = [];
        var projectsPageSize = 10;
        var projectsCurrentPage = 1;
        var filteredProjects = [];

        // ─── NOTIFICATION FUNCTIONS ──────────────────────────────────
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

        // ─── RENDER PROJECTS WITH PAGINATION ────────────────────────
        function renderProjectsWithPagination(projects) {
            allProjects = projects;
            filteredProjects = projects;
            renderProjectsPage(1);
        }

        function renderProjectsPage(page) {
            projectsCurrentPage = page;
            var start = (page - 1) * projectsPageSize;
            var end = Math.min(start + projectsPageSize, filteredProjects.length);
            var pageData = filteredProjects.slice(start, end);
            var container = document.getElementById('projectsList');

            if (!pageData || pageData.length === 0) {
                container.innerHTML = '<div class="no-data">No active projects found.</div>';
                document.getElementById('projectsPagination').style.display = 'none';
                return;
            }

            var html = '';
            pageData.forEach(function(project) {
                var completion = parseFloat(project.completion_percentage) || 0;
                var status = project.status || 'Pending';
                var budgetDisplay = '₱0.00';

                html += `
                    <div class="project-item" 
                        data-name="${project.project_name || 'Unnamed'}"
                        data-client="${project.client_name || '—'}"
                        data-budget="${budgetDisplay}"
                        data-start="${project.start_date || '—'}"
                        data-est-end="${project.estimated_end_date || '—'}"
                        data-actual-end="${project.actual_end_date || '—'}"
                        data-phase="${project.phase || 'Planning'}"
                        data-status="${status}"
                        data-progress="${completion}"
                        data-completion="${completion}%"
                        onclick="openProjectDetail(this)">
                        <div class="info">
                            <h4>${project.project_name || 'Unnamed Project'}</h4>
                            <div class="budget">Client: ${project.client_name || '—'} | Status: ${status}</div>
                        </div>
                        <div class="progress-wrapper">
                            <div class="progress-bar"><div class="fill" style="width:${Math.min(completion, 100)}%;"></div></div>
                            <div class="progress-label"><span>${Math.round(completion)}%</span><span>Complete</span></div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            updateProjectsPagination();
            loadProjectBudgets(pageData);
        }

        function updateProjectsPagination() {
            var container = document.getElementById('projectsPaginationLinks');
            var total = filteredProjects.length;
            var totalPages = Math.ceil(total / projectsPageSize);
            var current = projectsCurrentPage;

            document.getElementById('projectsPagination').style.display = totalPages > 1 ? 'flex' : 'none';
            document.getElementById('projectsShowingStart').textContent = total === 0 ? 0 : (current - 1) * projectsPageSize + 1;
            document.getElementById('projectsShowingEnd').textContent = Math.min(current * projectsPageSize, total);
            document.getElementById('projectsTotalCount').textContent = total;

            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            var html = '';
            html += '<a href="#" onclick="goToProjectsPage(' + (current - 1) + '); return false;" class="' + (current <= 1 ? 'disabled' : '') + '">«</a>';

            if (totalPages <= 7) {
                for (var i = 1; i <= totalPages; i++) {
                    html += '<a href="#" onclick="goToProjectsPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            } else {
                for (var i = 1; i <= 3; i++) {
                    html += '<a href="#" onclick="goToProjectsPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
                if (current > 4) {
                    html += '<span class="dots">...</span>';
                }
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                for (var i = startPage; i <= endPage; i++) {
                    html += '<a href="#" onclick="goToProjectsPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
                if (current < totalPages - 3) {
                    html += '<span class="dots">...</span>';
                }
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    if (i > 3) {
                        html += '<a href="#" onclick="goToProjectsPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                    }
                }
            }

            html += '<a href="#" onclick="goToProjectsPage(' + (current + 1) + '); return false;" class="' + (current >= totalPages ? 'disabled' : '') + '">»</a>';
            container.innerHTML = html;
        }

        function goToProjectsPage(page) {
            var total = filteredProjects.length;
            var totalPages = Math.ceil(total / projectsPageSize);
            if (page < 1 || page > totalPages) return;
            renderProjectsPage(page);
        }

        function changeProjectsPageSize() {
            var select = document.getElementById('projectsRowsPerPage');
            projectsPageSize = parseInt(select.value) || 10;
            projectsCurrentPage = 1;
            renderProjectsPage(1);
        }

        // ─── LOAD ACTIVE PROJECTS ──────────────────────────────────
        function loadActiveProjects() {
            var container = document.getElementById('projectsList');
            container.innerHTML = '<div class="loading-text">Loading projects...</div>';

            fetch('/api/projects', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to load projects');
                return response.json();
            })
            .then(function(projects) {
                // Filter active projects (not completed)
                var activeProjects = projects.filter(function(p) {
                    return p.status && p.status !== 'Completed';
                });

                updateStats(activeProjects, projects);
                renderProjectsWithPagination(activeProjects);
            })
            .catch(function(error) {
                console.error('Error loading projects:', error);
                container.innerHTML = '<div class="no-data">Failed to load projects. Please refresh.</div>';
                showError('Failed to load projects: ' + error.message);
            });
        }

        // ─── UPDATE STATS ──────────────────────────────────────────
        function updateStats(activeProjects, allProjects) {
            // Active projects count
            document.getElementById('activeProjectsCount').textContent = activeProjects.length;
            document.getElementById('activeProjectsSub').textContent = activeProjects.length + ' active projects';

            // Total inventory items (from inventory API)
            fetch('/api/inventory', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to load inventory');
                return response.json();
            })
            .then(function(data) {
                var items = data.success ? data.data || [] : [];
                document.getElementById('totalItemsCount').textContent = items.length;
                document.getElementById('totalItemsSub').textContent = items.length + ' total items';

                // Count low stock items (current_stock <= reorder_level)
                var lowStock = items.filter(function(item) {
                    var stock = parseFloat(item.current_stock) || 0;
                    var reorder = parseFloat(item.reorder_level) || 0;
                    return stock <= reorder && stock > 0;
                });

                document.getElementById('lowStockCount').textContent = lowStock.length;
                document.getElementById('lowStockSub').textContent = lowStock.length + ' items need reorder';
            })
            .catch(function(error) {
                console.error('Error loading inventory:', error);
            });
        }

        // ─── RENDER PROJECTS ──────────────────────────────────────
        function renderProjects(projects) {
            var container = document.getElementById('projectsList');

            if (!projects || projects.length === 0) {
                container.innerHTML = '<div class="no-data">No active projects found.</div>';
                return;
            }

            var html = '';
            projects.forEach(function(project) {
                var completion = parseFloat(project.completion_percentage) || 0;
                var status = project.status || 'Pending';
                var statusClass = status === 'On Track' ? 'on-track' :
                                 status === 'Delayed' ? 'delayed' :
                                 status === 'Completed' ? 'completed' : 'at-risk';

                // Calculate budget from budgets_tbl (we'll fetch this separately)
                var budgetDisplay = '₱0.00';

                html += `
                    <div class="project-item" 
                         data-name="${project.project_name || 'Unnamed'}"
                         data-client="${project.client_name || '—'}"
                         data-budget="${budgetDisplay}"
                         data-start="${project.start_date || '—'}"
                         data-est-end="${project.estimated_end_date || '—'}"
                         data-actual-end="${project.actual_end_date || '—'}"
                         data-phase="${project.phase || 'Planning'}"
                         data-status="${status}"
                         data-progress="${completion}"
                         data-completion="${completion}%"
                         onclick="openProjectDetail(this)">
                        <div class="info">
                            <h4>${project.project_name || 'Unnamed Project'}</h4>
                            <div class="budget">Client: ${project.client_name || '—'} | Status: ${status}</div>
                        </div>
                        <div class="progress-wrapper">
                            <div class="progress-bar"><div class="fill" style="width:${Math.min(completion, 100)}%;"></div></div>
                            <div class="progress-label"><span>${Math.round(completion)}%</span><span>Complete</span></div>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;

            // Load budgets for each project
            loadProjectBudgets(projects);
        }

        // ─── LOAD PROJECT BUDGETS ──────────────────────────────────
        function loadProjectBudgets(projects) {
            projects.forEach(function(project) {
                fetch('/api/budgets/' + project.project_id, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                .then(function(response) {
                    if (!response.ok) return null;
                    return response.json();
                })
                .then(function(data) {
                    if (data && data.budget_amount) {
                        // Update the project item with budget
                        var items = document.querySelectorAll('.project-item');
                        items.forEach(function(item) {
                            var name = item.dataset.name;
                            if (name === project.project_name) {
                                item.dataset.budget = '₱' + parseFloat(data.budget_amount).toLocaleString();
                                var budgetEl = item.querySelector('.budget');
                                if (budgetEl) {
                                    budgetEl.textContent = 'Budget: ₱' + parseFloat(data.budget_amount).toLocaleString() + ' | Status: ' + project.status;
                                }
                            }
                        });
                    }
                })
                .catch(function(error) {
                    console.warn('Failed to load budget for project:', project.project_name);
                });
            });
        }

        // ─── LOAD MATERIAL FORECAST ────────────────────────────────
        function loadMaterialForecast() {
            var tbody = document.getElementById('forecastTableBody');
            tbody.innerHTML = '<tr><td colspan="7" class="loading-text">Loading forecast data...</td></tr>';

            fetch('/api/ml/predict/material-demand', {
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to load forecast');
                return response.json();
            })
            .then(function(data) {
                if (data.success && data.predictions) {
                    // Check if predictions is an object with numeric keys
                    var predictions = data.predictions;
                    if (typeof predictions === 'object' && !Array.isArray(predictions)) {
                        // Convert object to array
                        var items = Object.values(predictions);
                        renderMaterialForecast(items);
                    } else {
                        renderMaterialForecast(predictions);
                    }
                } else {
                    throw new Error(data.message || 'No forecast data available');
                }
            })
            .catch(function(error) {
                console.error('Error loading material forecast:', error);
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">Failed to load forecast data. Please refresh.</td></tr>';
                showError('Failed to load material forecast: ' + error.message);
            });
        }

        // ─── RENDER MATERIAL FORECAST ──────────────────────────────
        function renderMaterialForecast(predictions) {
            var tbody = document.getElementById('forecastTableBody');

            if (!predictions || Object.keys(predictions).length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">No material forecast data available.</td></tr>';
                return;
            }

            var html = '';
            var items = [];

            // Convert object to array
            if (Array.isArray(predictions)) {
                items = predictions;
            } else {
                items = Object.values(predictions);
            }

            // Filter out invalid items and sort by status
            items = items.filter(function(item) {
                return item && item.item_name;
            });

            // Sort by status (Reorder Needed first, then Low Stock, then Sufficient)
            var order = { 'Reorder Needed': 0, 'Low Stock': 1, 'Sufficient': 2 };
            items.sort(function(a, b) {
                return (order[a.status] || 3) - (order[b.status] || 3);
            });

            // Show all items (or limit to 20)
            var displayItems = items.slice(0, 20);

            if (displayItems.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="no-data">No material forecast data available.</td></tr>';
                return;
            }

            displayItems.forEach(function(item) {
                var statusClass = item.status === 'Reorder Needed' ? 'reorder' :
                                item.status === 'Low Stock' ? 'low' : 'sufficient';

                var currentStock = parseFloat(item.current_stock) || 0;
                var avgUsage = parseFloat(item.avg_usage) || 0;
                var projectedDemand = parseFloat(item.projected_demand) || 0;
                var reorderLevel = parseFloat(item.reorder_level) || 0;

                html += `
                    <tr>
                        <td><strong>${item.item_name || 'Unknown'}</strong></td>
                        <td>${currentStock.toFixed(2)}</td>
                        <td>${avgUsage.toFixed(2)}</td>
                        <td>${projectedDemand.toFixed(2)}</td>
                        <td>${reorderLevel.toFixed(2)}</td>
                        <td><span class="forecast-status-badge ${statusClass}">${item.status || 'Unknown'}</span></td>
                        <td>${item.recommendation || '—'}</td>
                    </tr>
                `;
            });

            tbody.innerHTML = html;
        }

        // ─── PROJECT DETAIL MODAL ──────────────────────────────────
        function openProjectDetail(element) {
            var name = element.dataset.name || 'Untitled';
            var client = element.dataset.client || '—';
            var budget = element.dataset.budget || '—';
            var start = element.dataset.start || '—';
            var estEnd = element.dataset.estEnd || '—';
            var actualEnd = element.dataset.actualEnd || '—';
            var phase = element.dataset.phase || '—';
            var status = element.dataset.status || '—';
            var completion = element.dataset.completion || '0%';

            currentProjectData = { name: name };

            document.getElementById('detailProjectName').textContent = name;
            document.getElementById('detailClientName').textContent = client;
            document.getElementById('detailBudget').textContent = budget;
            document.getElementById('detailStartDate').textContent = start;
            document.getElementById('detailEstEndDate').textContent = estEnd;
            document.getElementById('detailActualEndDate').textContent = actualEnd;
            document.getElementById('detailCompletion').textContent = completion;

            // Calculate duration
            if (start && start !== '—' && estEnd && estEnd !== '—') {
                var startDate = new Date(start);
                var endDate = new Date(estEnd);
                var diffMonths = (endDate.getFullYear() - startDate.getFullYear()) * 12 + (endDate.getMonth() - startDate.getMonth());
                document.getElementById('detailDuration').textContent = diffMonths > 0 ? diffMonths + ' months' : '—';
            } else {
                document.getElementById('detailDuration').textContent = '—';
            }

            var phaseEl = document.getElementById('detailPhase');
            phaseEl.textContent = phase;
            phaseEl.className = 'phase-badge';

            var statusEl = document.getElementById('detailStatus');
            statusEl.textContent = status;
            statusEl.className = 'status-badge';
            if (status === 'On Track') statusEl.classList.add('on-track');
            else if (status === 'Delayed') statusEl.classList.add('delayed');
            else if (status === 'Completed') statusEl.classList.add('completed');
            else if (status === 'At Risk') statusEl.classList.add('at-risk');

            document.getElementById('projectDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeProjectDetail() {
            document.getElementById('projectDetailModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function viewProject() {
            window.location.href = "{{ url('/oprojects') }}";
        }

        // ─── CLOSE MODALS ON BACKDROP ──────────────────────────────
        document.getElementById('projectDetailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeProjectDetail();
            }
        });

        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });

        // ─── INIT ─────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            loadActiveProjects();
            loadMaterialForecast();

            // // Auto-refresh every 60 seconds
            // setInterval(function() {
            //     loadActiveProjects();
            //     loadMaterialForecast();
            // }, 60000);
        });
    </script>

</body>
</html>