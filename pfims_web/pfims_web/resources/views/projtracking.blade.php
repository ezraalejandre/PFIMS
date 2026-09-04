<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Project Tracking - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/projtracking.css') }}">
        <style>
        .error-notification { z-index: 9999 !important; }
        .success-notification { z-index: 9999 !important; }
        #deleteConfirmModal { z-index: 9999 !important; }
        .field-error {
            display: none;
            color: #d32f2f;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        .form-group input.input-error {
            border-color: #d32f2f !important;
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.15) !important;
        }
        .project-filter-panel { display:grid; grid-template-columns:minmax(220px,2fr) repeat(4,minmax(140px,1fr)) auto; gap:12px; align-items:end; margin:18px 0; padding:16px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; }
        .project-filter-field { display:flex; flex-direction:column; gap:6px; }
        .project-filter-field label { font-size:.78rem; font-weight:700; color:#4b5563; }
        .project-filter-field input,.project-filter-field select { width:100%; min-height:42px; padding:9px 11px; border:1px solid #d1d5db; border-radius:8px; background:#fff; }
        .project-analytics { display:grid; grid-template-columns:minmax(0,1fr); gap:14px; margin-bottom:18px; }
                .project-chart-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px 16px; max-width:none; width:100%; }
        .project-chart-card h2 { margin:0 0 8px; font-size:0.9rem; }
        .status-chart { display:grid; gap:5px; }
        .status-chart-row { display:grid; grid-template-columns:80px minmax(0,1fr) 32px; gap:10px; align-items:center; font-size:.78rem; }
        .status-chart-track { height:7px; border-radius:999px; background:#eef2f7; overflow:hidden; }
        .status-chart-bar { height:100%; min-width:0; border-radius:999px; transition:width .25s ease; }
        .status-chart-empty { color:#6b7280; font-size:.85rem; }
        @media (max-width:1100px) { .project-filter-panel { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:640px) { .project-filter-panel { grid-template-columns:1fr; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="projects-page">

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
            <span id="successMessage">Project saved successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- ─── DELETE CONFIRMATION MODAL ─── -->
    <div id="deleteConfirmModal" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage" style="font-size: 1rem; color: #333; margin-bottom: 10px;">
                    Are you sure you want to permanently delete this project?
                </p>
                <p style="font-size: 0.85rem; color: #888; margin-bottom: 20px;">
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 12px; margin-top: 10px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeDeleteModal()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: transparent; color: #888; transition: 0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmDeleteBtn" onclick="confirmDelete()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #d32f2f; color: #fff; transition: 0.3s;">Delete</button>
            </div>
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
                <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
            </a>
            <a href="{{ url('/profile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>{{ auth()->user()->name === 'Administrator' ? 'Admin' : auth()->user()->name }}</span>
            </a>
        </div>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
                <nav>
            <ul>
                <li><a href="{{ url('/dashboard') }}"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
                <li class="active"><a href="{{ url('/projects') }}"><img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon">PROJECTS</a></li>
                <li><a href="{{ url('/finance') }}"><img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon">INVENTORY</a></li>
                <li><a href="{{ url('/suppliers') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/suppliers.png') }}" alt="" class="nav-link-icon">SUPPLIERS</a></li>
                <li><a href="{{ url('/reports') }}"><img src="{{ asset('images/reports.png') }}" alt="" class="nav-link-icon">REPORTS</a></li>
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

        <div class="page-header-with-btn">
            <h1>PROJECT TRACKING</h1>
            <button class="btn-new-project" onclick="openModal()">+ New Project</button>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid-proj">
            <div class="stat-card-proj">
                <div class="stat-info">
                    <div class="stat-label">Active Projects</div>
                    <div class="stat-value" id="activeProjectsCount">0</div>
                    <div class="stat-sub" id="activeProjectsSub">Loading...</div>
                </div>
            </div>
            <div class="stat-card-proj">
                <div class="stat-info">
                    <div class="stat-label">On Schedule</div>
                    <div class="stat-value" id="onScheduleCount">0</div>
                    <div class="stat-sub" id="onScheduleSub">0% of active</div>
                </div>
            </div>
            <div class="stat-card-proj">
                <div class="stat-info">
                    <div class="stat-label">Delayed</div>
                    <div class="stat-value" id="delayedCount">0</div>
                    <div class="stat-sub" id="delayedSub">Needs attention</div>
                </div>
            </div>
            <div class="stat-card-proj">
                <div class="stat-info">
                    <div class="stat-label">Avg Completion</div>
                    <div class="stat-value" id="avgCompletion">0%</div>
                    <div class="stat-sub" id="avgCompletionSub">Across all projects</div>
                </div>
            </div>
        </div>

        <div class="project-filter-panel" aria-label="Project filters">
            <div class="project-filter-field">
                <label for="projectSearch">Search</label>
                <input type="search" id="projectSearch" maxlength="150" placeholder="Project, client, manager..." oninput="filterProjects()">
            </div>
            <div class="project-filter-field">
                <label for="projectStatusFilter">Status</label>
                <select id="projectStatusFilter" onchange="filterProjects()"><option value="">All statuses</option><option>Pending</option><option>On Track</option><option>At Risk</option><option>Delayed</option><option>Completed</option></select>
            </div>
            <div class="project-filter-field">
                <label for="projectPhaseFilter">Phase</label>
                <select id="projectPhaseFilter" onchange="filterProjects()"><option value="">All phases</option><option>Planning</option><option>Foundation</option><option>Structure</option><option>Finishing</option><option>Complete</option></select>
            </div>
            <div class="project-filter-field"><label for="projectDateFrom">Started from</label><input type="date" id="projectDateFrom" min="2000-01-01" max="2100-12-31" onchange="filterProjects()"></div>
            <div class="project-filter-field"><label for="projectDateTo">Started to</label><input type="date" id="projectDateTo" min="2000-01-01" max="2100-12-31" onchange="filterProjects()"></div>
            <button type="button" class="btn-clear-search" onclick="clearProjectSearch()">x</button>
        </div>

        <div class="project-analytics">
            <section class="project-chart-card" aria-labelledby="projectStatusChartTitle">
                <h2 id="projectStatusChartTitle">Project status distribution <small id="projectChartScope" style="font-weight:400;color:#6b7280"></small></h2>
                <div id="projectStatusChart" class="status-chart" role="img" aria-label="Filtered projects grouped by status"></div>
            </section>
        </div>

        <!-- Table with Progress Bar -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Client Name</th>
                        <th>Start Date</th>
                        <th>Est. End Date</th>
                        <th>Actual End Date</th>
                        <th>Duration</th>
                        <th>Phase</th>
                        <th>Progress</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="projectTableBody"></tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper">
                        <div class="rows-info">
                Rows per page
                <select id="projectRowsPerPage" aria-label="Project rows per page" onchange="changeProjectPageSize()">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span id="projectTotalCount" class="pagination-total">Total: 0</span>
            </div>
            <div class="pagination-links" id="projectPaginationLinks">
                <!-- Generated by JavaScript -->
            </div>
        </div>

    </main>

    <!-- ─── OVERLAY / MODAL (Add Project) ─── -->
    <div id="projectModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="projectModalTitle">Add new project</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>

            <div class="step-indicator">
                <span class="step active" id="step1Indicator">
                    <span class="step-number">1</span> Project Info
                </span>
                <span class="step" id="step2Indicator">
                    <span class="step-number">2</span> Team &amp; Schedule
                </span>
                <span class="step" id="step3Indicator">
                    <span class="step-number">3</span> Review
                </span>
            </div>

            <div class="modal-step" id="step1">
                <h3>BASIC INFORMATION</h3>
                <div class="form-group">
                    <label>Project name <span class="required">*</span></label>
                    <input type="text" placeholder="e.g. Skyline Tower Phase 2" id="projectName" maxlength="150">
                    <span id="projectNameError" class="field-error"></span>
                </div>
                <div class="form-group">
                    <label>Client name <span class="required">*</span></label>
                    <input type="text" placeholder="e.g. Mega Realty Corporation" id="clientName" maxlength="150">
                    <span id="clientNameError" class="field-error"></span>
                </div>
                <div class="modal-footer">
                    <div class="footer-left">
                        <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                    </div>
                    <div class="footer-right">
                        <button class="btn-continue" onclick="nextStep(2)">Continue</button>
                    </div>
                </div>
            </div>

            <div class="modal-step" id="step2" style="display: none;">
                <h3>TEAM ASSIGNMENT</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Project manager <span class="required">*</span></label>
                        <select id="projectManager">
                        <option value="">Select Project Manager</option>
                        <option value="A. Santos">A. Santos</option>
                        <option value="B. Reyes">B. Reyes</option>
                        <option value="C. Mendoza">C. Mendoza</option>
                        <option value="D. Cruz">D. Cruz</option>
                        <option value="E. Villanueva">E. Villanueva</option>
            </select>
                        <span id="projectManagerError" class="field-error"></span>
            </div>
                    <div class="form-group">
    <label>No. of workers</label>
    <input type="number" placeholder="e.g. 50" id="workerCount" min="0" max="100000" step="1">
    <span id="workerCountError" class="field-error"></span>
</div>
                </div>

                <h3>TIMELINE</h3>
                <div class="form-row">
                    <div class="form-group">
    <label>Start date <span class="required">*</span></label>
    <input type="date" id="startDate" min="2000-01-01" max="2100-12-31">
    <span id="startDateError" class="field-error"></span>
</div>
                                        <div class="form-group">
    <label>Estimated end date <span class="required">*</span></label>
    <input type="date" id="endDate" min="2000-01-01" max="2100-12-31" oninput="clearEndDateError()">
    <span id="endDateError" class="field-error"></span>
</div>
                </div>

                <div class="modal-footer">
                    <div class="footer-left">
                        <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                        <button class="btn-back" onclick="prevStep(1)">Back</button>
                    </div>
                    <div class="footer-right">
                        <button class="btn-continue" onclick="nextStep(3)">Continue</button>
                    </div>
                </div>
            </div>

            <div class="modal-step" id="step3" style="display: none;">
                <h3>SUMMARY</h3>
                <div class="summary-list">
                    <div class="summary-item"><strong>Project name:</strong> <span class="summary-value" id="summaryName">—</span></div>
                    <div class="summary-item"><strong>Client:</strong> <span class="summary-value" id="summaryClient">—</span></div>
                    <div class="summary-item"><strong>Project manager:</strong> <span class="summary-value" id="summaryManager">—</span></div>
                    <div class="summary-item"><strong>Start date:</strong> <span class="summary-value" id="summaryStart">—</span></div>
                    <div class="summary-item"><strong>Estimated end date:</strong> <span class="summary-value" id="summaryEnd">—</span></div>
                    <div class="summary-item"><strong>No. of workers:</strong> <span class="summary-value" id="summaryWorkers">—</span></div>
                </div>

                <div class="modal-footer">
                    <div class="footer-left">
                        <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                        <button class="btn-back" onclick="prevStep(2)">Back</button>
                    </div>
                    <div class="footer-right">
                        <button class="btn-save" onclick="saveProject()">Save project</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── UPDATE PROJECT OVERVIEW MODAL (with Delete) ─── -->
    <div id="updateModal" class="modal-overlay modal-update">
        <div class="modal-container">
            <div class="modal-header">
                <div>
                    <h2 id="updateProjectName" style="margin-bottom: 2px;">Project Name</h2>
                    <span class="subtitle" id="updateClientName">Client Name</span>
                </div>
                <button class="modal-close" onclick="closeUpdateModal()">×</button>
            </div>

            <div class="project-details-grid">
                <div class="detail-item">
                    <label>Budget</label>
                    <span id="updateBudget">—</span>
                </div>
                <div class="detail-item">
                    <label>Start Date</label>
                    <span id="updateStartDate">—</span>
                </div>
                <div class="detail-item">
                    <label>Est. End Date</label>
                    <span id="updateEstEndDate">—</span>
                </div>
                <div class="detail-item">
                    <label>Actual End Date</label>
                    <span id="updateActualEndDate">—</span>
                </div>
                <div class="detail-item">
                    <label>Duration</label>
                    <span id="updateDuration">—</span>
                </div>
                <div class="detail-item">
                    <label>Phase</label>
                    <span id="updatePhase" class="phase-badge">—</span>
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span id="updateStatus" class="status-badge at-risk">—</span>
                </div>
            </div>

            <div class="modal-footer" style="justify-content: flex-end; gap: 12px;">
                <button class="btn-cancel" onclick="closeUpdateModal()">Close</button>
                <button class="btn-delete" id="deleteProjectBtn" onclick="deleteProject()">Delete</button>
                <button class="btn-edit-project" id="editProjectBtn" onclick="openEditProjectModal()">Edit Project</button>
            </div>
        </div>
    </div>

    <!-- ─── EDIT PROJECT MODAL (standalone) ─── -->
    <div id="editProjectModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Edit Project</h2>
                <button class="modal-close" onclick="closeEditProjectModal()">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editProjectOriginalName">
                <div class="form-row">
                    <div class="form-group">
                        <label>Phase</label>
                        <select id="editPhase">
                            <option value="Planning">Planning</option>
                            <option value="Foundation">Foundation</option>
                            <option value="Structure">Structure</option>
                            <option value="Finishing">Finishing</option>
                            <option value="Complete">Complete</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="editStatus">
                            <option value="On Track">On Track</option>
                            <option value="At Risk">At Risk</option>
                            <option value="Delayed">Delayed</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
    <div class="form-group">
        <label>Start Date</label>
        <input type="date" id="editStartDate" min="2000-01-01" max="2100-12-31">
        <span id="editStartDateError" class="field-error"></span>
    </div>
    <div class="form-group">
        <label>Estimated End Date</label>
        <input type="date" id="editEstEndDate" min="2000-01-01" max="2100-12-31">
        <span id="editEstEndDateError" class="field-error"></span>
    </div>
</div>
                <div class="form-row">
                                        <div class="form-group">
    <label>Actual End Date</label>
    <input type="date" id="editActualEndDate" max="{{ date('Y-m-d') }}">
    <span id="editActualEndDateError" class="field-error"></span>
</div>
                    <div class="form-group">
    <label>Completion Percentage</label>
    <input type="number" id="editCompletionPercentage" min="0" max="100" placeholder="0-100">
    <span id="editCompletionPercentageError" class="field-error"></span>
</div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content: flex-end;">
                <button class="btn-cancel" onclick="closeEditProjectModal()">Cancel</button>
                <button class="btn-save" onclick="saveEditProject()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        // ─── GLOBAL VARIABLES ───
                var currentEditData = null;
        var currentProjectRow = null;
        var deleteCallback = null;
        var allProjects = [];
        var projectSearchTerm = '';
        var isOpeningProjectModal = false;

        // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // ─── ERROR NOTIFICATION (POP-UP) ───
                var suppressErrorAutoClose = false;

        function showError(message) {
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            if (msgSpan) {
                msgSpan.textContent = message || 'An error occurred. Please try again.';
            }
            notif.style.display = 'block';
            suppressErrorAutoClose = true;
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

        // ─── SUCCESS NOTIFICATION (POP-UP) ───
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            if (msgSpan) {
                msgSpan.textContent = message || 'Project saved successfully!';
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

        // ─── DELETE CONFIRMATION MODAL ───
        function openDeleteModal(message, callback) {
            document.getElementById('deleteConfirmMessage').textContent = message || 'Are you sure you want to permanently delete this project?';
            deleteCallback = callback;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            deleteCallback = null;
        }

        function confirmDelete() {
            if (typeof deleteCallback === 'function') {
                deleteCallback();
            }
            closeDeleteModal();
        }

        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // ─── ADD PROJECT MODAL ───
        function openModal() {
            document.getElementById('projectModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            goToStep(1);
            document.getElementById('projectName').value = '';
            document.getElementById('clientName').value = '';
            document.getElementById('projectManager').value = '';
            document.getElementById('workerCount').value = '';
            document.getElementById('startDate').value = '';
            document.getElementById('endDate').value = '';
        }

        function closeModal() {
            document.getElementById('projectModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        let currentStep = 1;

        function goToStep(step) {
            document.querySelectorAll('.modal-step').forEach(function(el) {
                el.style.display = 'none';
            });
            document.getElementById('step' + step).style.display = 'block';

            document.querySelectorAll('.step-indicator .step').forEach(function(el, index) {
                el.classList.toggle('active', index + 1 === step);
                el.classList.toggle('completed', index + 1 < step);
            });
            currentStep = step;
        }

                function clearFieldError(inputId, errorId) {
    var input = document.getElementById(inputId);
    var error = document.getElementById(errorId);

    if (input) {
        input.classList.remove('input-error');
    }

    if (error) {
        error.style.display = 'none';
        error.textContent = '';
    }
}

function showFieldError(inputId, errorId, message) {
    var input = document.getElementById(inputId);
    var error = document.getElementById(errorId);

    if (input) {
        input.classList.add('input-error');
    }

    if (error) {
        error.textContent = message;
        error.style.display = 'block';
    }
}

function clearEndDateError() {
    clearFieldError('endDate', 'endDateError');
}

function showEndDateError(message) {
    showFieldError('endDate', 'endDateError', message);
}

document.addEventListener('DOMContentLoaded', function() {
    var projectName = document.getElementById('projectName');
    var clientName = document.getElementById('clientName');
    var projectManager = document.getElementById('projectManager');
    var workerCount = document.getElementById('workerCount');
    var startDate = document.getElementById('startDate');
    var endDate = document.getElementById('endDate');

    if (projectName) {
        projectName.addEventListener('input', function() {
            if (this.value.trim()) {
                clearFieldError('projectName', 'projectNameError');
            }
        });
    }

    if (clientName) {
        clientName.addEventListener('input', function() {
            if (this.value.trim()) {
                clearFieldError('clientName', 'clientNameError');
            }
        });
    }

    if (projectManager) {
        projectManager.addEventListener('change', function() {
            if (this.value) {
                clearFieldError('projectManager', 'projectManagerError');
            }
        });
    }

    if (workerCount) {
        workerCount.addEventListener('input', function() {
            if (this.value === '' || parseInt(this.value, 10) >= 0) {
                clearFieldError('workerCount', 'workerCountError');
            }
        });
    }

    if (startDate) {
        startDate.addEventListener('change', function() {
            if (this.value) {
                clearFieldError('startDate', 'startDateError');
            }

            var end = document.getElementById('endDate').value;

            if (end && new Date(end) > new Date(this.value)) {
    clearEndDateError();
} else if (end && new Date(end) <= new Date(this.value)) {
    showEndDateError('Estimated end date must be after the start date.');
}
        });
    }

    if (endDate) {
        endDate.addEventListener('change', function() {
            var start = document.getElementById('startDate').value;

            if (!this.value) {
                clearEndDateError();
                return;
            }

            if (start && new Date(this.value) <= new Date(start)) {
    showEndDateError('Estimated end date must be after the start date.');
} else {
    clearEndDateError();
}
        });
    }

    var editStartDate = document.getElementById('editStartDate');
    var editEstEndDate = document.getElementById('editEstEndDate');
    var editActualEndDate = document.getElementById('editActualEndDate');
    var editCompletionPercentage = document.getElementById('editCompletionPercentage');

    if (editStartDate) {
        editStartDate.addEventListener('change', function() {
            if (this.value) {
                clearFieldError('editStartDate', 'editStartDateError');
            }

            var end = document.getElementById('editEstEndDate').value;

            if (end && new Date(end) >= new Date(this.value)) {
                clearFieldError('editEstEndDate', 'editEstEndDateError');
            }
        });
    }

    if (editEstEndDate) {
        editEstEndDate.addEventListener('change', function() {
            var start = document.getElementById('editStartDate').value;

            if (!this.value) {
                clearFieldError('editEstEndDate', 'editEstEndDateError');
                return;
            }

            if (start && new Date(this.value) <= new Date(start)) {
    showFieldError(
        'editEstEndDate',
        'editEstEndDateError',
        'Estimated end date must be after the start date.'
    );
} else {
    clearFieldError('editEstEndDate', 'editEstEndDateError');
}
        });
    }

    if (editActualEndDate) {
        editActualEndDate.addEventListener('change', function() {
            if (!this.value) {
                clearFieldError('editActualEndDate', 'editActualEndDateError');
                return;
            }

            var todayStr = new Date().toISOString().split('T')[0];
            var start = document.getElementById('editStartDate').value;

            if (this.value > todayStr) {
                showFieldError(
                    'editActualEndDate',
                    'editActualEndDateError',
                    'Actual end date cannot be in the future.'
                );
            } else if (start && new Date(this.value) < new Date(start)) {
                showFieldError(
                    'editActualEndDate',
                    'editActualEndDateError',
                    'Actual end date cannot be before the start date.'
                );
            } else {
                clearFieldError('editActualEndDate', 'editActualEndDateError');
            }
        });
    }

    if (editCompletionPercentage) {
        editCompletionPercentage.addEventListener('input', function() {
            var value = this.value;

            if (
                value === '' ||
                (parseFloat(value) >= 0 && parseFloat(value) <= 100)
            ) {
                clearFieldError(
                    'editCompletionPercentage',
                    'editCompletionPercentageError'
                );
            }
        });
    }
});

        function nextStep(step) {
            if (currentStep === 1) {
    var name = document.getElementById('projectName').value.trim();
    var client = document.getElementById('clientName').value.trim();

    clearFieldError('projectName', 'projectNameError');
    clearFieldError('clientName', 'clientNameError');

    if (!name) {
        showFieldError('projectName', 'projectNameError', 'Please enter the project name.');
        return;
    }

    if (!client) {
        showFieldError('clientName', 'clientNameError', 'Please enter the client name.');
        return;
    }
}

if (currentStep === 2) {
    var manager = document.getElementById('projectManager').value;
    var start = document.getElementById('startDate').value;
    var end = document.getElementById('endDate').value;
    var workers = document.getElementById('workerCount').value;

    clearFieldError('projectManager', 'projectManagerError');
    clearFieldError('startDate', 'startDateError');
    clearFieldError('workerCount', 'workerCountError');
    clearEndDateError();

    if (!manager) {
        showFieldError('projectManager', 'projectManagerError', 'Please select a project manager.');
        return;
    }

    if (!start) {
        showFieldError('startDate', 'startDateError', 'Please select a start date.');
        return;
    }

    if (!end) {
        showEndDateError('Please select an estimated end date.');
        return;
    }

    if (workers && (!/^\d+$/.test(workers) || parseInt(workers, 10) > 100000)) {
        showFieldError('workerCount', 'workerCountError', 'Workers must be a whole number from 0 to 100,000.');
        return;
    }

    if (new Date(end) <= new Date(start)) {
    showEndDateError('Estimated end date must be after the start date.');
    return;
}

                document.getElementById('summaryName').textContent = document.getElementById('projectName').value;
                document.getElementById('summaryClient').textContent = document.getElementById('clientName').value;
                document.getElementById('summaryManager').textContent = document.getElementById('projectManager').value;
                document.getElementById('summaryStart').textContent = document.getElementById('startDate').value;
                document.getElementById('summaryEnd').textContent = document.getElementById('endDate').value;
                document.getElementById('summaryWorkers').textContent = document.getElementById('workerCount').value || '—';
            }
            goToStep(step);
        }

        function prevStep(step) {
            goToStep(step);
        }

        function calculateDuration(start, end) {
            if (!start) {
                return '—';
            }
            var startDate = new Date(start);
            var endDate = end ? new Date(end) : new Date();
            if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || endDate < startDate) {
                return '—';
            }
            var diffDays = Math.round((endDate - startDate) / (1000 * 60 * 60 * 24));
            return (diffDays / 30).toFixed(1) + ' mo';
        }

                function getCsrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        }

        // ─── BUTTON LOADING STATE (prevents double-click / double-submit) ───
        function setButtonLoading(button, isLoading, loadingText) {
            if (!button) return;
            if (isLoading) {
                button.dataset.originalText = button.textContent;
                button.textContent = loadingText || 'Saving...';
                button.disabled = true;
                button.style.opacity = '0.7';
                button.style.cursor = 'not-allowed';
            } else {
                button.textContent = button.dataset.originalText || button.textContent;
                button.disabled = false;
                button.style.opacity = '';
                button.style.cursor = '';
            }
        }

        function formatDate(rawDate) {
            if (!rawDate) return '—';
            var date = new Date(rawDate);
            if (isNaN(date.getTime())) return rawDate;
            var options = { year: 'numeric', month: 'short', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        function formatCurrency(value) {
            var amount = parseFloat(value) || 0;
            return '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function mapProjectApiRecord(record) {
            var actualDate = record.actual_end_date || null;
            return {
                id: record.project_id,
                name: record.project_name || 'Untitled Project',
                client: record.client_name || '—',
                budget: record.budget || '',
                manager: record.project_manager || '',
                workers: record.worker_count || '0',
                startDate: record.start_date || '',
                endDate: record.estimated_end_date || '',
                actualEndDate: actualDate || '',
                phase: record.phase || 'Planning',
                progress: record.completion_percentage || 0,
                status: record.status || 'On Track',
                duration: calculateDuration(record.start_date, actualDate),
                startDateDisplay: formatDate(record.start_date),
                estEndDateDisplay: formatDate(record.estimated_end_date),
                actualEndDateDisplay: actualDate ? formatDate(actualDate) : '—'
            };
        }

        // ─── PAGINATION VARIABLES ───
        var projectPageSize = 25;
        var projectCurrentPage = 1;
        var projectFilteredData = [];

        // ─── RENDER PROJECT PAGE ───
        function renderProjectPage(page) {
            var totalPages = Math.max(1, Math.ceil(projectFilteredData.length / projectPageSize));
            projectCurrentPage = Math.min(Math.max(parseInt(page, 10) || 1, 1), totalPages);
            var start = (projectCurrentPage - 1) * projectPageSize;
            var end = Math.min(start + projectPageSize, projectFilteredData.length);
            var pageData = projectFilteredData.slice(start, end);
            
            var tbody = document.getElementById('projectTableBody');
            tbody.innerHTML = '';
            if (pageData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;padding:28px;color:#6b7280">No projects match the selected filters.</td></tr>';
            }
            pageData.forEach(function(project) {
                tbody.appendChild(createProjectRow(project));
            });
            
            renderProjectPagination();
            if (window.refreshTableScrollFade) window.refreshTableScrollFade();
        }

        // ─── RENDER PROJECT PAGINATION ───
                function renderProjectPagination() {
            var container = document.getElementById('projectPaginationLinks');
            if (!container) return;
            
            var total = projectFilteredData.length;
            var totalEl = document.getElementById('projectTotalCount');
            if (totalEl) totalEl.textContent = 'Total: ' + total;
            var totalPages = Math.ceil(total / projectPageSize);
            var current = projectCurrentPage;
            
            if (totalPages <= 1) {
                container.innerHTML = `<span class="pagination-info">Showing all ${total} projects</span>`;
                return;
            }
            
            var html = '';
            
            // Previous
            html += `<a href="#" onclick="renderProjectPage(${current - 1}); return false;" class="${current <= 1 ? 'disabled' : ''}">«</a>`;
            
            // Page numbers - show first 3, then dots, then last 2
            if (totalPages <= 7) {
                for (var i = 1; i <= totalPages; i++) {
                    html += `<a href="#" onclick="renderProjectPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
                }
            } else {
                // First 3 pages
                for (var i = 1; i <= 3; i++) {
                    html += `<a href="#" onclick="renderProjectPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
                }
                
                // Dots if current is beyond page 4
                if (current > 4) {
                    html += `<span class="dots">...</span>`;
                }
                
                // Pages around current
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                for (var i = startPage; i <= endPage; i++) {
                    html += `<a href="#" onclick="renderProjectPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
                }
                
                // Dots if current is before page total-3
                if (current < totalPages - 3) {
                    html += `<span class="dots">...</span>`;
                }
                
                // Last 2 pages
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    if (i > 3) {
                        html += `<a href="#" onclick="renderProjectPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
                    }
                }
            }
            
            // Next
            html += `<a href="#" onclick="renderProjectPage(${current + 1}); return false;" class="${current >= totalPages ? 'disabled' : ''}">»</a>`;
            
            container.innerHTML = html;
        }

        // ─── CHANGE PROJECT PAGE SIZE ───
        function changeProjectPageSize() {
            var select = document.getElementById('projectRowsPerPage');
            projectPageSize = parseInt(select.value) || 25;
            projectCurrentPage = 1;
            renderProjectPage(1);
        }

                function applyColumnVisibility(status) {
            var wrapper = document.querySelector('.table-wrapper');
            if (!wrapper) return;
            wrapper.classList.remove('col-hide-est-end', 'col-hide-actual-end', 'col-hide-progress');
            if (status === 'Completed') {
                wrapper.classList.add('col-hide-est-end', 'col-hide-progress');
            } else if (status === 'On Track' || status === 'Pending' || status === 'At Risk' || status === 'Delayed') {
                wrapper.classList.add('col-hide-actual-end');
            }
        }

        function filterProjects() {
            projectSearchTerm = document.getElementById('projectSearch').value.toLowerCase().trim();
            var status = document.getElementById('projectStatusFilter').value;
            applyColumnVisibility(status);
            var phase = document.getElementById('projectPhaseFilter').value;
            var from = document.getElementById('projectDateFrom').value;
            var to = document.getElementById('projectDateTo').value;
            projectFilteredData = allProjects.filter(function(project) {
                var haystack = [project.name, project.client, project.manager, project.phase, project.status]
                    .join(' ').toLowerCase();
                return (!projectSearchTerm || haystack.includes(projectSearchTerm))
                    && (!status || project.status === status)
                    && (!phase || project.phase === phase)
                    && (!from || project.startDate >= from)
                    && (!to || project.startDate <= to)
                    && (!(from && to) || from <= to);
            });
            refreshProjectAnalytics(projectFilteredData);
            renderProjectPage(1);
        }

        function clearProjectSearch() {
            ['projectSearch', 'projectStatusFilter', 'projectPhaseFilter', 'projectDateFrom', 'projectDateTo']
                .forEach(function(id) { document.getElementById(id).value = ''; });
            projectSearchTerm = '';
            filterProjects();
        }

        function updateStats(projects) {
            allProjects = projects.slice();
            filterProjects();
        }

        function refreshProjectAnalytics(projects) {
        var activeProjects = projects.filter(function(p) {
            return p.status !== 'Completed';
        });
        
        var onSchedule = projects.filter(function(p) {
            return p.status === 'On Track';
        });
        
        var delayed = projects.filter(function(p) {
            return p.status === 'Delayed' || p.status === 'At Risk';
        });
        
        var totalProgress = 0;
        projects.forEach(function(p) {
            totalProgress += parseFloat(p.progress) || 0;
        });
        var avgProgress = projects.length > 0 ? Math.round(totalProgress / projects.length) : 0;
        
        document.getElementById('activeProjectsCount').textContent = activeProjects.length;
        document.getElementById('activeProjectsSub').textContent = activeProjects.length + ' active projects';
        
        document.getElementById('onScheduleCount').textContent = onSchedule.length;
        var onSchedulePercent = activeProjects.length > 0 ? Math.round((onSchedule.length / activeProjects.length) * 100) : 0;
        document.getElementById('onScheduleSub').textContent = onSchedulePercent + '% of active';
        
        document.getElementById('delayedCount').textContent = delayed.length;
        document.getElementById('delayedSub').textContent = delayed.length > 0 ? 'Needs attention' : 'All projects on track';
        
        document.getElementById('avgCompletion').textContent = avgProgress + '%';
        document.getElementById('avgCompletionSub').textContent = 'Across ' + projects.length + ' filtered projects';
        renderStatusChart(projects);
        }

        function renderStatusChart(projects) {
            var chart = document.getElementById('projectStatusChart');
            var scope = document.getElementById('projectChartScope');
            var statuses = [
                { label: 'Pending', color: '#9aa5b1' }, { label: 'On Track', color: '#4f8b68' },
                { label: 'At Risk', color: '#e19a45' }, { label: 'Delayed', color: '#c95c5c' },
                { label: 'Completed', color: '#547896' }
            ];
            chart.innerHTML = '';
            scope.textContent = '(' + projects.length + ' filtered)';
            if (!projects.length) {
                chart.innerHTML = '<div class="status-chart-empty">No chart data for the selected filters.</div>';
                return;
            }
            statuses.forEach(function(item) {
                var count = projects.filter(function(project) { return project.status === item.label; }).length;
                var row = document.createElement('div');
                row.className = 'status-chart-row';
                var label = document.createElement('span'); label.textContent = item.label;
                var track = document.createElement('div'); track.className = 'status-chart-track';
                var bar = document.createElement('div'); bar.className = 'status-chart-bar';
                bar.style.background = item.color; bar.style.width = ((count / projects.length) * 100) + '%';
                track.appendChild(bar);
                var value = document.createElement('strong'); value.textContent = count;
                row.append(label, track, value); chart.appendChild(row);
            });
        }

                // ─── FETCH PROJECTS (modified) ───
        function fetchProjects() {
            fetch('/api/projects', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Unable to load projects');
                }
                return response.json();
            })
            .then(function(data) {
                var projects = data.map(function(item) {
                    return mapProjectApiRecord(item);
                });
                
                // Update stats and render first page
                updateStats(projects);

                // Auto-open a specific project's modal if linked from Dashboard
                var params = new URLSearchParams(window.location.search);
                var targetId = params.get('project');
                if (targetId) {
                    var targetProject = projects.find(function(p) {
                        return String(p.id) === String(targetId);
                    });
                    if (targetProject) {
                        var tempRow = createProjectRow(targetProject);
                        openUpdateModal(
                            tempRow,
                            targetProject.id,
                            targetProject.name,
                            targetProject.client,
                            targetProject.budget,
                            targetProject.startDateDisplay,
                            targetProject.estEndDateDisplay,
                            targetProject.actualEndDateDisplay,
                            targetProject.duration,
                            targetProject.phase,
                            targetProject.status,
                            targetProject.progress,
                            targetProject.manager,
                            targetProject.workers,
                            targetProject.startDate,
                            targetProject.endDate
                        );
                    }
                }
            })
            .catch(function(error) {
                console.error(error);
                showError('Failed to load projects.');
            });
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value).replace(/[&<>'"]/g, function(character) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
            });
        }

        function apiErrorMessage(error, fallback) {
            if (error && error.errors) {
                var fields = Object.keys(error.errors);
                if (fields.length && error.errors[fields[0]].length) return error.errors[fields[0]][0];
            }
            return (error && error.message) || fallback;
        }

        function updateProjectRow(row, project) {
            row.dataset.projectId = project.id || '';
            row.dataset.manager = project.manager || '';
            row.dataset.workers = project.workers || '';
            row.dataset.startDateRaw = project.startDate || '';
            row.dataset.endDateRaw = project.endDate || '';
            row.dataset.actualEndDate = project.actualEndDate || '';
            row.dataset.duration = project.duration || '';
            row.dataset.phase = project.phase || 'Planning';
            row.dataset.progress = project.progress || 0;
            row.dataset.status = project.status || 'On Track';
            row.dataset.budget = project.budget || '';

            row.onclick = function() {
                if (isOpeningProjectModal) return;
                isOpeningProjectModal = true;
                openUpdateModal(
                    this,
                    project.id,
                    project.name,
                    project.client,
                    project.budget,
                    project.startDateDisplay,
                    project.estEndDateDisplay,
                    project.actualEndDateDisplay,
                    project.duration,
                    project.phase,
                    project.status,
                    project.progress,
                    project.manager,
                    project.workers,
                    project.startDate,
                    project.endDate
                );
                setTimeout(function() { isOpeningProjectModal = false; }, 400);
            };

            var progress = Math.min(100, Math.max(0, parseFloat(project.progress) || 0));
            row.innerHTML = '' +
                '<td><strong>' + escapeHtml(project.name) + '</strong></td>' +
                '<td>' + escapeHtml(project.client) + '</td>' +
                '<td>' + escapeHtml(project.startDateDisplay) + '</td>' +
                '<td>' + escapeHtml(project.estEndDateDisplay) + '</td>' +
                '<td>' + escapeHtml(project.actualEndDateDisplay || '—') + '</td>' +
                '<td>' + escapeHtml(project.duration) + '</td>' +
                '<td><span class="phase-badge">' + escapeHtml(project.phase) + '</span></td>' +
                '<td>' +
                    '<div class="progress-cell">' +
                        '<div class="mini-bar"><div class="fill" style="width:' + progress + '%;"></div></div>' +
                    '</div>' +
                '</td>' +
                '<td><span class="status-badge ' + (project.status === 'Completed' ? 'completed' : project.status === 'Delayed' ? 'delayed' : project.status === 'On Track' ? 'on-track' : 'at-risk') + '"><span class="dot"></span> ' + escapeHtml(project.status) + '</span></td>';
        }

        function createProjectRow(project) {
            var tr = document.createElement('tr');
            updateProjectRow(tr, project);
            return tr;
        }

                // ─── SAVE PROJECT ───
        function saveProject() {
            var saveBtn = document.querySelector('#step3 .btn-save');
            var name = document.getElementById('projectName').value.trim();
            var client = document.getElementById('clientName').value.trim();
            var manager = document.getElementById('projectManager').value.trim();
            var workers = document.getElementById('workerCount').value.trim();
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;

                        clearFieldError('projectName', 'projectNameError');
clearFieldError('clientName', 'clientNameError');
clearFieldError('projectManager', 'projectManagerError');
clearFieldError('workerCount', 'workerCountError');
clearFieldError('startDate', 'startDateError');
clearEndDateError();

if (!name) {
    showFieldError('projectName', 'projectNameError', 'Please enter the project name.');
    return;
}

if (!client) {
    showFieldError('clientName', 'clientNameError', 'Please enter the client name.');
    return;
}

if (!manager) {
    showFieldError('projectManager', 'projectManagerError', 'Please select a project manager.');
    return;
}

if (!startDate) {
    showFieldError('startDate', 'startDateError', 'Please select a start date.');
    return;
}

if (!endDate) {
    showEndDateError('Please select an estimated end date.');
    return;
}

if (workers && (!/^\d+$/.test(workers) || parseInt(workers, 10) > 100000)) {
    showFieldError('workerCount', 'workerCountError', 'Workers must be a whole number from 0 to 100,000.');
    return;
}

if (new Date(endDate) <= new Date(startDate)) {
    showEndDateError('Estimated end date must be after the start date.');
    return;
}

                        var payload = {
                project_name: name,
                client_name: client,
                project_manager: manager,
                start_date: startDate,
                estimated_end_date: endDate,
                actual_end_date: '',
                worker_count: workers ? parseInt(workers, 10) : 0,
                phase: 'Planning',
                completion_percentage: 0,
                status: 'On Track'
            };

            setButtonLoading(saveBtn, true, 'Saving...');

            fetch('/api/projects', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(err) {
                        throw new Error(apiErrorMessage(err, 'Failed to save project'));
                    });
                }
                return response.json();
            })
            .then(function(savedProject) {
                var project = mapProjectApiRecord(savedProject);
                var tbody = document.getElementById('projectTableBody');
                if (tbody) {
                    tbody.appendChild(createProjectRow(project));
                }
                // Update stats
                                allProjects.push(project);
                updateStats(allProjects);
                closeModal();
                showSuccess('Project added successfully!');
            })
            .catch(function(error) {
                console.error(error);
                showError(error.message || 'Failed to save project.');
            })
            .finally(function() {
                setButtonLoading(saveBtn, false);
            });
        }

        document.getElementById('projectModal').addEventListener('click', function(e) {
            if (e.target === this) { closeModal(); }
        });

        // ─── UPDATE PROJECT OVERVIEW MODAL ───
        function openUpdateModal(
            row,
            projectId,
            projectName, clientName, budget, startDate, estEndDate, actualEndDate,
            duration, phase, status, progress, manager, workers, startDateRaw, endDateRaw
        ) {
            currentProjectRow = row;

            currentEditData = {
                id: projectId,
                name: projectName,
                client: clientName,
                budget: budget || '',
                manager: manager || '',
                workers: workers || '',
                startDate: startDateRaw || '',
                endDate: endDateRaw || '',
                actualEndDate: actualEndDate || '',
                phase: phase,
                progress: progress || 0,
                status: status || 'On Track',
                startDateDisplay: startDate,
                estEndDateDisplay: estEndDate,
                actualEndDateDisplay: actualEndDate || '—',
                duration: duration
            };

            document.getElementById('updateProjectName').textContent = projectName;
            document.getElementById('updateClientName').textContent = clientName;
            document.getElementById('updateBudget').textContent = budget ? formatCurrency(budget) : '—';
            document.getElementById('updateStartDate').textContent = startDate;
            document.getElementById('updateEstEndDate').textContent = estEndDate;
            document.getElementById('updateActualEndDate').textContent = actualEndDate || '—';
            document.getElementById('updateDuration').textContent = duration;
            document.getElementById('updatePhase').textContent = phase;

            var statusEl = document.getElementById('updateStatus');
            statusEl.textContent = status;
            statusEl.className = 'status-badge';
            if (status === 'On Track') statusEl.classList.add('on-track');
            else if (status === 'Delayed') statusEl.classList.add('delayed');
            else if (status === 'Completed') statusEl.classList.add('completed');
            else statusEl.classList.add('at-risk');

            document.getElementById('updateModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // ─── DELETE PROJECT ───
                function deleteProject() {
            if (!currentProjectRow) {
                showError('No project selected to delete.');
                return;
            }

            var deleteConfirmBtn = document.getElementById('confirmDeleteBtn');
            var projectId = currentProjectRow.dataset.projectId;
            openDeleteModal('Are you sure you want to permanently delete this project?', function() {
                if (!projectId) {
                    currentProjectRow.remove();
                    allProjects = allProjects.filter(function(p) {
                        return p.id !== currentEditData.id;
                    });
                    updateStats(allProjects);
                    closeUpdateModal();
                    showSuccess('Project deleted successfully!');
                    currentProjectRow = null;
                    return;
                }

                                setButtonLoading(deleteConfirmBtn, true, 'Deleting...');

                fetch('/api/projects/' + projectId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) {
                    if (!response.ok) {
                        // Try to parse error message
                        return response.text().then(function(text) {
                            try {
                                var data = JSON.parse(text);
                                throw new Error(data.message || 'Failed to delete project');
                            } catch (e) {
                                throw new Error('Failed to delete project. Server error.');
                            }
                        });
                    }
                    return response.json();
                })
                .then(function() {
                    currentProjectRow.remove();
                    allProjects = allProjects.filter(function(p) {
                        return p.id !== currentEditData.id;
                    });
                    updateStats(allProjects);
                    closeUpdateModal();
                    showSuccess('Project deleted successfully!');
                    currentProjectRow = null;
                })
                                .catch(function(error) {
                    console.error(error);
                    showError(error.message || 'Failed to delete project.');
                })
                .finally(function() {
                    setButtonLoading(deleteConfirmBtn, false);
                });
            });
        }

        // ─── EDIT PROJECT MODAL ───
        function openEditProjectModal() {
            if (!currentEditData) {
                showError('No project data to edit.');
                return;
            }
            document.getElementById('editProjectOriginalName').value = currentEditData.name;
            document.getElementById('editPhase').value = currentEditData.phase || 'Planning';
            document.getElementById('editStatus').value = currentEditData.status || 'On Track';
            document.getElementById('editStartDate').value = currentEditData.startDate || '';
            document.getElementById('editEstEndDate').value = currentEditData.endDate || '';
            document.getElementById('editActualEndDate').value = currentEditData.actualEndDate || '';
            document.getElementById('editCompletionPercentage').value = currentEditData.progress || 0;

            closeUpdateModal();
            document.getElementById('editProjectModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditProjectModal() {
            document.getElementById('editProjectModal').classList.remove('active');
            document.body.style.overflow = '';
        }

                function saveEditProject() {
            if (!currentProjectRow || !currentEditData) {
                showError('No project selected to edit.');
                return;
            }

            var editSaveBtn = document.querySelector('#editProjectModal .btn-save');

            var phase = document.getElementById('editPhase').value;
            var status = document.getElementById('editStatus').value;
            var start = document.getElementById('editStartDate').value;
            var estEnd = document.getElementById('editEstEndDate').value;
            var actualEnd = document.getElementById('editActualEndDate').value;
            var completion = document.getElementById('editCompletionPercentage').value;

                        clearFieldError('editStartDate', 'editStartDateError');
clearFieldError('editEstEndDate', 'editEstEndDateError');
clearFieldError('editActualEndDate', 'editActualEndDateError');
clearFieldError('editCompletionPercentage', 'editCompletionPercentageError');

if (!start) {
    showFieldError('editStartDate', 'editStartDateError', 'Please select a start date.');
    return;
}

if (!estEnd) {
    showFieldError('editEstEndDate', 'editEstEndDateError', 'Please select an estimated end date.');
    return;
}

if (completion !== '' && (parseFloat(completion) < 0 || parseFloat(completion) > 100)) {
    showFieldError(
        'editCompletionPercentage',
        'editCompletionPercentageError',
        'Completion percentage must be between 0 and 100.'
    );
    return;
}

if (new Date(estEnd) <= new Date(start)) {
    showFieldError(
        'editEstEndDate',
        'editEstEndDateError',
        'Estimated end date must be after the start date.'
    );
    return;
}

var todayStr = new Date().toISOString().split('T')[0];

if (actualEnd && actualEnd > todayStr) {
    showFieldError(
        'editActualEndDate',
        'editActualEndDateError',
        'Actual end date cannot be in the future.'
    );
    return;
}

if (actualEnd && new Date(actualEnd) < new Date(start)) {
    showFieldError(
        'editActualEndDate',
        'editActualEndDateError',
        'Actual end date cannot be before the start date.'
    );
    return;
}

                        var payload = {
                phase: phase,
                status: status,
                start_date: start,
                estimated_end_date: estEnd,
                actual_end_date: actualEnd || null,
                completion_percentage: parseFloat(completion) || 0
            };

            setButtonLoading(editSaveBtn, true, 'Saving...');

            fetch('/api/projects/' + currentEditData.id, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(err) {
                        throw new Error(apiErrorMessage(err, 'Failed to save project changes'));
                    });
                }
                return response.json();
            })
            .then(function(updatedProject) {
                currentEditData.phase = phase;
                currentEditData.status = status;
                currentEditData.startDate = start;
                currentEditData.endDate = estEnd;
                currentEditData.actualEndDate = actualEnd || '';
                currentEditData.progress = parseFloat(completion) || 0;
                currentEditData.startDateDisplay = formatDate(start);
                currentEditData.estEndDateDisplay = formatDate(estEnd);
                currentEditData.actualEndDateDisplay = actualEnd ? formatDate(actualEnd) : '—';
                currentEditData.duration = calculateDuration(start, actualEnd);

                updateProjectRow(currentProjectRow, currentEditData);
                
                // Update stats
                var index = allProjects.findIndex(function(p) {
                    return p.id === currentEditData.id;
                });
                if (index !== -1) {
                    allProjects[index] = currentEditData;
                    updateStats(allProjects);
                }
                
                closeEditProjectModal();
                showSuccess('Project "' + currentEditData.name + '" updated successfully!');
                openUpdateModal(
                    currentProjectRow,
                    currentEditData.id,
                    currentEditData.name,
                    currentEditData.client,
                    currentEditData.budget,
                    currentEditData.startDateDisplay,
                    currentEditData.estEndDateDisplay,
                    currentEditData.actualEndDateDisplay,
                    currentEditData.duration,
                    currentEditData.phase,
                    currentEditData.status,
                    currentEditData.progress,
                    currentEditData.manager,
                    currentEditData.workers,
                    currentEditData.startDate,
                    currentEditData.endDate
                );
            })
                        .catch(function(error) {
                console.error(error);
                showError(error.message || 'Failed to save project changes.');
            })
            .finally(function() {
                setButtonLoading(editSaveBtn, false);
            });
        }

        // ─── CLOSE MODALS ON BACKDROP CLICK ───
        document.getElementById('updateModal').addEventListener('click', function(e) {
            if (e.target === this) { closeUpdateModal(); }
        });
        document.getElementById('editProjectModal').addEventListener('click', function(e) {
            if (e.target === this) { closeEditProjectModal(); }
        });

        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });

                function fetchNotifBadge() {
            fetch('/api/notifications/unread-count', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to load unread count.');
                return response.json();
            })
            .then(function(data) {
                var badge = document.getElementById('notifBadge');
                if (!badge) return;
                var count = data.unread_count || 0;
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(function(error) {
                console.error('Error loading notification badge:', error);
            });
        }

        function initializeProjectPage() {
            fetchProjects();
            fetchNotifBadge();
        }

                if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeProjectPage);
        } else {
            initializeProjectPage();
        }
    </script>
    <script src="{{ asset('js/table-scroll-fade.js') }}"></script>

</body>
</html>
