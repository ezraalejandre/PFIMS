<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Dashboard - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Odashboard.css') }}">
    <style>
        .error-notification { z-index: 9999 !important; }
        .success-notification { z-index: 9999 !important; }
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

        <!-- Page Title -->
        <div class="page-header">
            <h1>DASHBOARD <small>operations overview</small></h1>
        </div>

        <!-- Stats Cards (Operational only) -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Active Projects</div>
                <div class="stat-value">24</div>
                <div class="stat-sub">6 completed this month</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Equipment Units</div>
                <div class="stat-value">156</div>
                <div class="stat-sub">12 under maintenance</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Workforce</div>
                <div class="stat-value">342</div>
                <div class="stat-sub">28 new hires this month</div>
            </div>
        </div>

        <!-- ─── PROJECT COMPLETION TREND (BAR CHART) ─── -->
        <div class="charts-row">
            <div class="chart-box" style="grid-column: 1 / -1; max-width: 800px; margin: 0 auto;">
                <h3>PROJECT COMPLETION TREND</h3>
                <div class="bar-chart">
                    <div class="bar-group"><div class="bar" style="height:35px;"></div><span class="bar-label">Jan</span></div>
                    <div class="bar-group"><div class="bar" style="height:60px;"></div><span class="bar-label">Feb</span></div>
                    <div class="bar-group"><div class="bar" style="height:50px;"></div><span class="bar-label">Mar</span></div>
                    <div class="bar-group"><div class="bar" style="height:80px;"></div><span class="bar-label">Apr</span></div>
                    <div class="bar-group"><div class="bar" style="height:70px;"></div><span class="bar-label">May</span></div>
                    <div class="bar-group"><div class="bar" style="height:90px;"></div><span class="bar-label">Jun</span></div>
                </div>
            </div>
        </div>

        <!-- ─── ACTIVE PROJECTS LIST ─── -->
        <div class="projects-section">
            <h2>ACTIVE PROJECTS</h2>
            <div class="projects-list">
                <div class="project-item" 
                     data-name="Riverside Commercial Complex"
                     data-client="Riverside Client"
                     data-budget="$2.4M"
                     data-start="Jan 15, 2025"
                     data-est-end="Dec 30, 2025"
                     data-actual-end="—"
                     data-duration="11.5 mo"
                     data-phase="Structure"
                     data-status="At Risk"
                     data-progress="78"
                     onclick="openProjectDetail(this)">
                    <img src="{{ asset('images/building1.jpg') }}" alt="Riverside Commercial Complex">
                    <div class="info">
                        <h4>Riverside Commercial Complex</h4>
                        <div class="budget">Budget: $2.4M</div>
                    </div>
                    <div class="progress-wrapper">
                        <div class="progress-bar"><div class="fill" style="width:78%;"></div></div>
                        <div class="progress-label"><span>78%</span><span>Complete</span></div>
                    </div>
                </div>

                <div class="project-item" 
                     data-name="Downtown Office Tower"
                     data-client="Downtown Client"
                     data-budget="$5.8M"
                     data-start="Mar 1, 2025"
                     data-est-end="Aug 15, 2025"
                     data-actual-end="—"
                     data-duration="5.5 mo"
                     data-phase="Finishing"
                     data-status="On Track"
                     data-progress="45"
                     onclick="openProjectDetail(this)">
                    <img src="{{ asset('images/building2.jpg') }}" alt="Downtown Office Tower">
                    <div class="info">
                        <h4>Downtown Office Tower</h4>
                        <div class="budget">Budget: $5.8M</div>
                    </div>
                    <div class="progress-wrapper">
                        <div class="progress-bar"><div class="fill" style="width:45%;"></div></div>
                        <div class="progress-label"><span>45%</span><span>Complete</span></div>
                    </div>
                </div>

                <div class="project-item" 
                     data-name="Suburban Housing Development"
                     data-client="Suburban Client"
                     data-budget="$3.2M"
                     data-start="Nov 10, 2024"
                     data-est-end="May 20, 2025"
                     data-actual-end="—"
                     data-duration="6.3 mo"
                     data-phase="Complete"
                     data-status="Completed"
                     data-progress="92"
                     onclick="openProjectDetail(this)">
                    <img src="{{ asset('images/building3.jpg') }}" alt="Suburban Housing Development">
                    <div class="info">
                        <h4>Suburban Housing Development</h4>
                        <div class="budget">Budget: $3.2M</div>
                    </div>
                    <div class="progress-wrapper">
                        <div class="progress-bar"><div class="fill" style="width:92%;"></div></div>
                        <div class="progress-label"><span>92%</span><span>Complete</span></div>
                    </div>
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
                <button class="btn-view-project" onclick="viewProject()">View Project</button>
            </div>
        </div>
    </div>

    <script>
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
            window.location.href = "{{ url('/oprojects') }}";
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