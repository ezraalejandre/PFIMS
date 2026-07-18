<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
    <style>
        .error-notification { z-index: 9999 !important; }
        .success-notification { z-index: 9999 !important; }
        
        /* Pagination Styles */
        .pagination-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-top: 1px solid #e5e7eb;
            margin-top: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .rows-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #6b7280;
        }
        
        .rows-info select {
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            font-size: 14px;
            cursor: pointer;
            outline: none;
        }
        
        .rows-info select:focus {
            border-color: #3b82f6;
            ring: 2px solid #93c5fd;
        }
        
        .pagination-links {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .pagination-links button {
            padding: 8px 14px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            background: white;
            color: #374151;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .pagination-links button:hover:not(:disabled) {
            background: #f3f4f6;
            border-color: #9ca3af;
        }
        
        .pagination-links button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .pagination-links button.active {
            background: #3b82f6;
            color: white;
            border-color: #3b82f6;
        }
        
        .pagination-links button.active:hover {
            background: #2563eb;
        }
        
        .pagination-links .ellipsis {
            padding: 8px 10px;
            color: #6b7280;
        }
        
        @media (max-width: 768px) {
            .pagination-wrapper {
                flex-direction: column;
                align-items: stretch;
            }
            
            .rows-info {
                justify-content: center;
            }
            
            .pagination-links {
                justify-content: center;
                flex-wrap: wrap;
            }
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
            <a href="{{ url('/notifications') }}" onclick="hideBadge(event)" style="position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span>Notifications</span>
                <span class="notif-badge" id="notifBadge">6</span>
            </a>
            <a href="{{ url('/profile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>{{ auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
        <nav>
            <ul>
                <li class="active">DASHBOARD</li>
                <li><a href="{{ url('/projects') }}" style="color: inherit; text-decoration: none; display: block;">PROJECTS</a></li>
                <li><a href="{{ url('/finance') }}" style="color: inherit; text-decoration: none; display: block;">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}" style="color: inherit; text-decoration: none; display: block;">INVENTORY</a></li>
                <li><a href="{{ url('/suppliers') }}" style="color: inherit; text-decoration: none; display: block;">SUPPLIERS</a></li>
                <li><a href="{{ url('/reports') }}" style="color: inherit; text-decoration: none; display: block;">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li>
                    <a href="{{ url('/settings') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
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
            <h1>DASHBOARD <small>construction operation overview</small></h1>
        </div>

        <!-- Stats Cards (Removed Equipment Units and Workforce) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Active Projects</div>
                <div class="stat-value">{{ $activeProjects ?? 0 }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Budget</div>
                <div class="stat-value">₱{{ number_format(($totalBudget ?? 0), 0) }}</div>
                <div class="stat-sub">₱{{ number_format(($remainingBudget ?? 0), 0) }} remaining</div>
            </div>
        </div>

        <!-- ─── CHARTS ROW ─── -->
        <div class="charts-row">
            <div class="chart-box">
                <h3>PROJECT COMPLETION TREND</h3>
                <div class="bar-chart">
                    @if(isset($completionData) && count($completionData['months']) > 0)
                        @foreach($completionData['months'] as $index => $month)
                            <div class="bar-group">
                                <div class="bar" style="height:{{ $completionData['percentages'][$index] ?? 35 }}px;"></div>
                                <span class="bar-label">{{ $month }}</span>
                            </div>
                        @endforeach
                    @else
                        <div class="bar-group"><div class="bar" style="height:35px;"></div><span class="bar-label">Jan</span></div>
                        <div class="bar-group"><div class="bar" style="height:60px;"></div><span class="bar-label">Feb</span></div>
                        <div class="bar-group"><div class="bar" style="height:50px;"></div><span class="bar-label">Mar</span></div>
                        <div class="bar-group"><div class="bar" style="height:80px;"></div><span class="bar-label">Apr</span></div>
                        <div class="bar-group"><div class="bar" style="height:70px;"></div><span class="bar-label">May</span></div>
                        <div class="bar-group"><div class="bar" style="height:90px;"></div><span class="bar-label">Jun</span></div>
                    @endif
                </div>
            </div>

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

        <!-- Projects List with Pagination -->
        <div class="projects-section">
            <h2>ACTIVE PROJECTS</h2>
            <div class="projects-list" id="projectsList">
                @if(isset($projects) && count($projects) > 0)
                    @foreach($projects as $project)
                        <div class="project-item" 
                             data-name="{{ $project->project_name }}"
                             data-client="{{ $project->client_name ?? '—' }}"
                             data-budget="₱{{ number_format($project->budget->budget_amount ?? 0, 2) }}"
                             data-start="{{ $project->start_date ? \Carbon\Carbon::parse($project->start_date)->format('M d, Y') : '—' }}"
                             data-est-end="{{ $project->estimated_end_date ? \Carbon\Carbon::parse($project->estimated_end_date)->format('M d, Y') : '—' }}"
                             data-actual-end="{{ $project->actual_end_date ? \Carbon\Carbon::parse($project->actual_end_date)->format('M d, Y') : '—' }}"
                             data-duration="{{ $project->estimated_end_date && $project->start_date ? \Carbon\Carbon::parse($project->start_date)->diffInMonths(\Carbon\Carbon::parse($project->estimated_end_date)) . ' mo' : '—' }}"
                             data-phase="{{ $project->phase ?? '—' }}"
                             data-status="{{ $project->status ?? '—' }}"
                             data-progress="{{ $project->completion_percentage ?? 0 }}"
                             onclick="openProjectDetail(this)">
                            <img src="{{ asset('images/building1.jpg') }}" alt="{{ $project->project_name }}">
                            <div class="info">
                                <h4>{{ $project->project_name }}</h4>
                                <div class="budget">Budget: ₱{{ number_format($project->budget->budget_amount ?? 0, 0) }}</div>
                            </div>
                            <div class="progress-wrapper">
                                <div class="progress-bar">
                                    <div class="fill" style="width:{{ $project->completion_percentage ?? 0 }}%;"></div>
                                </div>
                                <div class="progress-label">
                                    <span>{{ $project->completion_percentage ?? 0 }}%</span>
                                    <span>Complete</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="project-item" style="justify-content: center; padding: 30px;">
                        <div class="info" style="text-align: center; width: 100%;">
                            <h4 style="color: #666;">No active projects</h4>
                            <div class="budget">All projects are completed</div>
                        </div>
                    </div>
                @endif
            </div>

            <!-- ─── PAGINATION ─── -->
            <div class="pagination-wrapper">
                <div class="rows-info">
                    Rows Displayed:
                    <select id="projectsRowsPerPage" onchange="changeProjectPageSize()">
                        <option value="5">5</option>
                        <option value="10" selected>10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="pagination-links" id="projectPaginationLinks">
                    <!-- Generated by JavaScript -->
                </div>
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
            </div>
            <div class="modal-footer" style="justify-content: flex-end;">
                <button class="btn-cancel" onclick="closeProjectDetail()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // Store all projects data
        let allProjects = [];
        let currentPage = 1;
        let pageSize = 10;

        // Initialize pagination
        document.addEventListener('DOMContentLoaded', function() {
            // Get all project items
            const projectItems = document.querySelectorAll('.project-item');
            allProjects = Array.from(projectItems);
            
            // Update total count display
            updateTotalCount();
            
            // Apply pagination
            applyPagination();
        });

        function updateTotalCount() {
            const total = allProjects.length;
            const rowsInfo = document.querySelector('.rows-info');
            if (rowsInfo && total > 0) {
                const span = rowsInfo.querySelector('span');
                if (!span) {
                    const newSpan = document.createElement('span');
                    newSpan.className = 'total-count';
                    newSpan.textContent = `Total: ${total}`;
                    rowsInfo.appendChild(newSpan);
                } else {
                    span.textContent = `Total: ${total}`;
                }
            }
        }

        function applyPagination() {
            const start = (currentPage - 1) * pageSize;
            const end = start + pageSize;
            
            // Hide all projects first
            allProjects.forEach(item => {
                item.style.display = 'none';
            });
            
            // Show only current page items
            const pageItems = allProjects.slice(start, end);
            pageItems.forEach(item => {
                item.style.display = 'flex';
            });
            
            // Update pagination links
            renderPaginationLinks();
        }

        function renderPaginationLinks() {
            const container = document.getElementById('projectPaginationLinks');
            const totalPages = Math.ceil(allProjects.length / pageSize);
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            let html = '';
            
            // Previous button
            html += `<button onclick="goToPage(${currentPage - 1})" ${currentPage <= 1 ? 'disabled' : ''}>‹</button>`;
            
            // Page numbers
            const maxVisible = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
            let endPage = Math.min(totalPages, startPage + maxVisible - 1);
            
            if (endPage - startPage < maxVisible - 1) {
                startPage = Math.max(1, endPage - maxVisible + 1);
            }
            
            if (startPage > 1) {
                html += `<button onclick="goToPage(1)">1</button>`;
                if (startPage > 2) {
                    html += `<span class="ellipsis">…</span>`;
                }
            }
            
            for (let i = startPage; i <= endPage; i++) {
                html += `<button onclick="goToPage(${i})" class="${i === currentPage ? 'active' : ''}">${i}</button>`;
            }
            
            if (endPage < totalPages) {
                if (endPage < totalPages - 1) {
                    html += `<span class="ellipsis">…</span>`;
                }
                html += `<button onclick="goToPage(${totalPages})">${totalPages}</button>`;
            }
            
            // Next button
            html += `<button onclick="goToPage(${currentPage + 1})" ${currentPage >= totalPages ? 'disabled' : ''}>›</button>`;
            
            container.innerHTML = html;
        }

        function goToPage(page) {
            const totalPages = Math.ceil(allProjects.length / pageSize);
            if (page < 1 || page > totalPages) return;
            
            currentPage = page;
            applyPagination();
        }

        function changeProjectPageSize() {
            const select = document.getElementById('projectsRowsPerPage');
            pageSize = parseInt(select.value);
            currentPage = 1;
            applyPagination();
        }

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

        // ─── PROJECT DETAIL MODAL ───
        var currentProjectData = null;

        function openProjectDetail(element) {
            var name = element.dataset.name || 'Untitled';
            var client = element.dataset.client || '—';
            var budget = element.dataset.budget || '—';
            var start = element.dataset.start || '—';
            var estEnd = element.dataset.estEnd || '—';
            var actualEnd = element.dataset.actualEnd || '—';
            var duration = element.dataset.duration || '—';
            var phase = element.dataset.phase || '—';
            var status = element.dataset.status || '—';

            currentProjectData = { name: name };

            document.getElementById('detailProjectName').textContent = name;
            document.getElementById('detailClientName').textContent = client;
            document.getElementById('detailBudget').textContent = budget;
            document.getElementById('detailStartDate').textContent = start;
            document.getElementById('detailEstEndDate').textContent = estEnd;
            document.getElementById('detailActualEndDate').textContent = actualEnd;
            document.getElementById('detailDuration').textContent = duration;

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
            window.location.href = "{{ url('/projects') }}";
        }

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
    </script>

</body>
</html>