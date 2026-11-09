{{-- Prepare the values used by this page section. --}}
@php $portal = $portal ?? 'admin'; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Project Tracking - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/projtracking.css') }}">
        {{-- Set the styles used by this page section. --}}
        <style>
        /* Style the error notification. */
        .error-notification { z-index: 9999 !important; }
        /* Style the success notification. */
        .success-notification { z-index: 9999 !important; }
        /* Style the delete confirm modal. */
        #deleteConfirmModal { z-index: 9999 !important; }
        /* Style the field error. */
        .field-error {
            display: none;
            color: #d32f2f;
            font-size: 0.8rem;
            margin-top: 5px;
        }
        /* Style the form group input error. */
        .form-group input.input-error,
        .form-group select.input-error {
            border-color: #d32f2f !important;
            box-shadow: 0 0 0 3px rgba(211, 47, 47, 0.15) !important;
        }
        /* Style the project filter panel. */
        .project-filter-panel { display:grid; grid-template-columns:minmax(220px,2fr) repeat(4,minmax(140px,1fr)) auto; gap:12px; align-items:end; margin:18px 0; padding:16px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; }
        /* Style the project filter field. */
        .project-filter-field { display:flex; flex-direction:column; gap:6px; }
        /* Style the project filter field. */
        .project-filter-field label { font-size:.78rem; font-weight:700; color:#4b5563; }
        /* Style the project filter field. */
        .project-filter-field input,.project-filter-field select { width:100%; min-height:42px; padding:9px 11px; border:1px solid #d1d5db; border-radius:8px; background:#fff; }
        /* Style the project analytics. */
        .project-analytics { display:grid; grid-template-columns:minmax(0,1fr); gap:14px; margin-bottom:18px; }
                /* Style the project chart card. */
                .project-chart-card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:12px 16px; max-width:none; width:100%; }
        /* Style the project chart card. */
        .project-chart-card h2 { margin:0 0 8px; font-size:0.9rem; }
        /* Style the status chart. */
        .status-chart { display:grid; gap:5px; }
        /* Style the status chart row. */
        .status-chart-row { display:grid; grid-template-columns:80px minmax(0,1fr) 32px; gap:10px; align-items:center; font-size:.78rem; }
        /* Style the status chart track. */
        .status-chart-track { height:7px; border-radius:999px; background:#eef2f7; overflow:hidden; }
        /* Style the status chart bar. */
        .status-chart-bar { height:100%; min-width:0; border-radius:999px; transition:width .25s ease; }
        /* Style the status chart empty. */
        .status-chart-empty { color:#6b7280; font-size:.85rem; }
        /* Style the project pie wrap. */
        .project-pie-wrap { position:relative; height:260px; max-width:520px; margin:0 auto; }
        /* Adjust these styles for this screen size or display setting. */
        @media (max-width:1024px) { .project-filter-panel { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        /* Adjust these styles for this screen size or display setting. */
        @media (max-width:640px) { .project-filter-panel { grid-template-columns:1fr; } }
    </style>
    <link rel="stylesheet" href="{{ asset('css/'.$portal.'.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/modal-policy.js') }}?v={{ filemtime(public_path('js/modal-policy.js')) }}"></script>
    {{-- Set the styles used by this page section. --}}
    <style id="pfims-session-privacy">html { visibility:hidden; }</style>
    {{-- Set up the actions used by this page section. --}}
    <script data-navigation-granted="{{ session('_page_navigation_granted', false) ? '1' : '0' }}" data-session-epoch="{{ session('navigation_epoch', '') }}" src="{{ asset('js/protected-navigation.js') }}?v={{ filemtime(public_path('js/protected-navigation.js')) }}"></script>
</head>
<body class="projects-page" data-portal="{{ $portal }}" data-project-view-icon="{{ asset('images/view.jpg') }}" data-project-edit-icon="{{ asset('images/edit.jpg') }}">

    <!-- Error notification (pop-up) -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon">!</span>
            <span id="errorMessage">An error occurred. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- Success notification (pop-up) -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span id="successMessage">Project saved successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- Delete confirmation modal -->
    <div id="deleteConfirmModal" class="modal-overlay pfims-delete-confirm" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage" style="font-size: 1rem; color: #333; margin-bottom: 10px;">
                    Are you sure you want to move this project to Trash Bin?
                </p>
                <p style="font-size: 0.85rem; color: #888; margin-bottom: 20px;">
                    You can restore deleted records from Settings → Trash Bin for 30 days before permanent deletion.
                </p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 12px; margin-top: 10px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeDeleteModal()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: transparent; color: #888; transition: 0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmDeleteBtn" onclick="confirmDelete()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #d32f2f; color: #fff; transition: 0.3s;">Delete</button>
            </div>
        </div>
    </div>

    <!-- Full-width header (fixed) -->
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

    <!-- Sidebar -->
    <aside class="sidebar">
                {{-- Show the page navigation. --}}
                <nav>
            <ul>
                <li><a href="{{ url('/dashboard') }}"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
                <li class="active"><a href="{{ url('/projects') }}"><img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon">PROJECTS</a></li>
                <li><a href="{{ url('/finance') }}"><img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon">INVENTORY</a></li>
                <li><a href="{{ url('/reports') }}"><img src="{{ asset('images/folder.svg') }}" alt="" class="nav-link-icon">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li>
                    <a href="{{ url('/settings') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                        <img src="{{ asset('images/settings.png') }}" alt="Settings" class="nav-icon">
                        Settings
                    </a>
                </li>
                <li class="logout">
                    {{-- Collect the details entered in this form. --}}
                    <form method="POST" action="{{ url('/logout') }}" style="width: 100%; margin: 0; padding: 0;">
                        @csrf
                        <button type="submit" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%; background: none; border: none; cursor: pointer; padding: 0; font: inherit; color: inherit;">
                            <img src="{{ asset('images/logout.png') }}" alt="Log Out" class="nav-icon">
                            Log out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">

        <div class="page-header-with-btn">
            <h1>PROJECTS</h1>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button class="btn-new-project" onclick="openPfimsImport()">Import Projects</button>
                <button class="btn-new-project" onclick="openModal()">+ New Project</button>
            </div>
        </div>

        <div class="project-filter-panel" aria-label="Project filters">
            <div class="project-filter-field">
                <label for="projectSearch">Search</label>
                <input type="search" id="projectSearch" maxlength="150" placeholder="Project, client, manager..." oninput="filterProjects()">
            </div>
            <div class="project-filter-field">
                <label for="projectStatusFilter">Project Status</label>
                <select id="projectStatusFilter" onchange="filterProjects()"><option value="">All statuses</option><option>Ongoing</option><option>Pending</option><option>At Risk</option><option>Delayed</option><option>Completed</option></select>
            </div>
            <div class="project-filter-field">
                <label for="projectPhaseFilter">Phase</label>
                <select id="projectPhaseFilter" onchange="filterProjects()"><option value="">All phases</option></select>
            </div>
            {{-- Show the project date from section. --}}
            <div class="project-filter-field"><label for="projectDateFrom">Started from</label><input type="date" id="projectDateFrom" min="2000-01-01" max="2100-12-31" onchange="filterProjects()"></div>
            {{-- Show the project date to section. --}}
            <div class="project-filter-field"><label for="projectDateTo">Started to</label><input type="date" id="projectDateTo" min="2000-01-01" max="2100-12-31" onchange="filterProjects()"></div>
            <button type="button" class="btn-clear-search" onclick="clearProjectSearch()">x</button>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid-proj kpis">
            <article class="stat-card-proj kpi-card"><div class="stat-info"><div class="stat-label">Active Projects</div><div class="stat-value" id="activeProjectsCount">0</div><div class="stat-sub" id="activeProjectsSub">Loading...</div></div></article>
            <article class="stat-card-proj kpi-card"><div class="stat-info"><div class="stat-label">On Schedule</div><div class="stat-value" id="onScheduleCount">0</div><div class="stat-sub" id="onScheduleSub">0% of active</div></div></article>
            <article class="stat-card-proj kpi-card"><div class="stat-info"><div class="stat-label">Delayed</div><div class="stat-value" id="delayedCount">0</div><div class="stat-sub" id="delayedSub">Needs attention</div></div></article>
        </div>

        <!-- Table with Progress Bar -->
        <div class="table-wrapper">
            {{-- Show the records in a table. --}}
            <table data-pfims-standard-actions="off">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Client Name</th>
                        <th>Project Manager</th>
                        <th>Worker Count</th>
                        <th>Start Date</th>
                        <th>Est. End Date</th>
                        <th>Actual End Date</th>
                        <th>Duration</th>
                        <th>Phase</th>
                        <th>Overall Performance</th>
                        <th>Status</th>
                        <th>Actions</th>
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
                    <option value="5" selected>5</option>
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span id="projectTotalCount" class="pagination-total">Total: 0</span>
            </div>
            {{-- Show the project pagination links section. --}}
            <div class="pagination-links" id="projectPaginationLinks">
                <!-- Generated by JavaScript -->
            </div>
        </div>

    </main>

    <!-- Overlay / modal (add project) -->
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

            {{-- Show the step1 section. --}}
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

            {{-- Show the step2 section. --}}
            <div class="modal-step" id="step2" style="display: none;">
                <h3>TEAM ASSIGNMENT</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Project manager <span class="required">*</span></label>
                        <select id="projectManager" required><option value="">Select project manager</option></select>
                        <small>Manage project managers in Settings → Configurations.</small>
                        <span id="projectManagerError" class="field-error"></span>
            </div>
                    <div class="form-group">
    <label>No. of workers</label>
    <input type="number" placeholder="e.g. 50" id="workerCount" min="1" max="100000" step="1">
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
    <input type="date" id="endDate" min="{{ today()->toDateString() }}" max="2100-12-31" oninput="clearEndDateError()">
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

            {{-- Show the step3 section. --}}
            <div class="modal-step" id="step3" style="display: none;">
                <h3>SUMMARY</h3>
                <div class="summary-list">
                    {{-- Show the summary name section. --}}
                    <div class="summary-item"><strong>Project name:</strong> <span class="summary-value" id="summaryName">—</span></div>
                    {{-- Show the summary client section. --}}
                    <div class="summary-item"><strong>Client:</strong> <span class="summary-value" id="summaryClient">—</span></div>
                    {{-- Show the summary manager section. --}}
                    <div class="summary-item"><strong>Project manager:</strong> <span class="summary-value" id="summaryManager">—</span></div>
                    {{-- Show the summary start section. --}}
                    <div class="summary-item"><strong>Start date:</strong> <span class="summary-value" id="summaryStart">—</span></div>
                    {{-- Show the summary end section. --}}
                    <div class="summary-item"><strong>Estimated end date:</strong> <span class="summary-value" id="summaryEnd">—</span></div>
                    {{-- Show the summary workers section. --}}
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

    <!-- Update project overview modal (with delete) -->
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
                    <label>Project Manager</label>
                    <span id="updateProjectManager">—</span>
                </div>
                <div class="detail-item">
                    <label>Worker Count</label>
                    <span id="updateWorkerCount">0</span>
                </div>
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
                <div class="detail-item">
                    <label>Overall Performance</label>
                    <span id="updateProgress">0%</span>
                </div>
            </div>

            <div class="modal-footer" style="justify-content: flex-end; gap: 12px;">
                <button class="btn-cancel" onclick="closeUpdateModal()">Close</button>
                <button type="button" class="btn-edit-project" id="projectDetailsEditBtn">Edit</button>
                <button type="button" class="btn-delete" id="projectDetailsDeleteBtn">Delete</button>
            </div>
        </div>
    </div>

    <!-- Edit project modal (standalone) -->
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
                        <label>Project name <span class="required">*</span></label>
                        <input type="text" id="editProjectName" maxlength="150" required>
                        <span id="editProjectNameError" class="field-error"></span>
                    </div>
                    <div class="form-group">
                        <label>Client name <span class="required">*</span></label>
                        <input type="text" id="editClientName" maxlength="150" required>
                        <span id="editClientNameError" class="field-error"></span>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Project manager <span class="required">*</span></label>
                        <select id="editProjectManager" required><option value="">Select project manager</option></select>
                        <span id="editProjectManagerError" class="field-error"></span>
                    </div>
                    <div class="form-group">
                        <label for="editWorkerCount">No. of workers</label>
                        <input type="number" id="editWorkerCount" min="1" max="100000" step="1" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Phase</label>
                        <select id="editPhase" required></select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select id="editStatus">
                            <option value="Ongoing">Ongoing</option>
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
        <input type="date" id="editEstEndDate" min="{{ today()->toDateString() }}" max="2100-12-31">
        <span id="editEstEndDateError" class="field-error"></span>
    </div>
</div>
                <div class="form-row">
                                        <div class="form-group">
    <label>Actual End Date</label>
    <input type="date" id="editActualEndDate" min="{{ today()->toDateString() }}" max="2100-12-31">
    <span id="editActualEndDateError" class="field-error"></span>
</div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content: flex-end;">
                <button class="btn-cancel" onclick="closeEditProjectModal()">Cancel</button>
                <button class="btn-save" onclick="saveEditProject()">Save Changes</button>
            </div>
        </div>
    </div>

    @include('partials.data-import', ['importModule' => 'projects'])

    {{-- Set up the actions used by this page section. --}}
    <script>
        // Global variables
                var currentEditData = null;
        var currentProjectRow = null;
        var deleteCallback = null;
        var allProjects = [];
        var projectSearchTerm = '';
        var isOpeningProjectModal = false;

        // Hide notification badge on click
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            // Run this step if the badge is set.
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // Error notification (pop-up)
                var suppressErrorAutoClose = false;

        // Show error.
        function showError(message) {
            // Run this step if the show pfims alert equals function.
            if (typeof window.showPfimsAlert === 'function') {
                closeError();
                return window.showPfimsAlert(message || 'An error occurred. Please try again.', 'error');
            }
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            // Run this step if the msg span is set.
            if (msgSpan) {
                msgSpan.textContent = message || 'An error occurred. Please try again.';
            }
            notif.style.display = 'block';
            suppressErrorAutoClose = true;
            // Run this step if the error timeout is set.
            if (window.errorTimeout) clearTimeout(window.errorTimeout);
            // Run this action after the delay.
            window.errorTimeout = setTimeout(function() {
                closeError();
            }, 5000);
        }

        // Close error.
        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
            // Run this step if the error timeout is set.
            if (window.errorTimeout) {
                clearTimeout(window.errorTimeout);
                window.errorTimeout = null;
            }
        }

        // Success notification (pop-up)
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            // Run this step if the msg span is set.
            if (msgSpan) {
                msgSpan.textContent = message || 'Project saved successfully!';
            }
            notif.style.display = 'block';
            // Run this step if the success timeout is set.
            if (window.successTimeout) clearTimeout(window.successTimeout);
            // Run this action after the delay.
            window.successTimeout = setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        // Close success.
        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
            // Run this step if the success timeout is set.
            if (window.successTimeout) {
                clearTimeout(window.successTimeout);
                window.successTimeout = null;
            }
        }

        // Delete confirmation modal
        function openDeleteModal(message, callback) {
            document.getElementById('deleteConfirmMessage').textContent = message || 'Are you sure you want to move this project to Trash Bin?';
            deleteCallback = callback;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close delete modal.
        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            deleteCallback = null;
        }

        // Get the confirm delete.
        function confirmDelete() {
            // Run this step if the delete callback equals function.
            if (typeof deleteCallback === 'function') {
                deleteCallback();
            }
            closeDeleteModal();
        }

        // Handle the click event.
        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // Add project modal
        function projectToday() {
            var parts = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit'
            }).formatToParts(new Date());
            var values = {};
            // Process each part.
            parts.forEach(function(part) { values[part.type] = part.value; });
            return values.year + '-' + values.month + '-' + values.day;
        }

        // Refresh project end date bounds.
        function refreshProjectEndDateBounds() {
            var today = projectToday();
            // Process each id.
            ['endDate', 'editEstEndDate', 'editActualEndDate'].forEach(function(id) {
                document.getElementById(id).min = today;
            });
        }

        // Open the dialog.
        function openModal() {
            refreshProjectEndDateBounds();
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

        // Close the dialog.
        function closeModal() {
            document.getElementById('projectModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        let currentStep = 1;

        // Get the go to step.
        function goToStep(step) {
            // Process each el.
            document.querySelectorAll('.modal-step').forEach(function(el) {
                el.style.display = 'none';
            });
            document.getElementById('step' + step).style.display = 'block';

            // Process each el.
            document.querySelectorAll('.step-indicator .step').forEach(function(el, index) {
                el.classList.toggle('active', index + 1 === step);
                el.classList.toggle('completed', index + 1 < step);
            });
            currentStep = step;
        }

                // Clear field error.
                function clearFieldError(inputId, errorId) {
    var input = document.getElementById(inputId);
    var error = document.getElementById(errorId);

    // Run this step if the input is set.
    if (input) {
        input.classList.remove('input-error');
    }

    // Run this step if the error is set.
    if (error) {
        error.style.display = 'none';
        error.textContent = '';
    }
}

// Show field error.
function showFieldError(inputId, errorId, message) {
    var input = document.getElementById(inputId);
    var error = document.getElementById(errorId);

    // Run this step if the input is set.
    if (input) {
        input.classList.add('input-error');
    }

    // Run this step if the error is set.
    if (error) {
        error.textContent = message;
        error.style.display = 'block';
    }
}

// Clear end date error.
function clearEndDateError() {
    clearFieldError('endDate', 'endDateError');
}

// Show end date error.
function showEndDateError(message) {
    showFieldError('endDate', 'endDateError', message);
}

// Handle the dom content loaded event.
document.addEventListener('DOMContentLoaded', function() {
    var projectName = document.getElementById('projectName');
    var clientName = document.getElementById('clientName');
    var projectManager = document.getElementById('projectManager');
    var workerCount = document.getElementById('workerCount');
    var startDate = document.getElementById('startDate');
    var endDate = document.getElementById('endDate');

    // Run this step if the project name is set.
    if (projectName) {
        // Handle the input event.
        projectName.addEventListener('input', function() {
            // Run this step if the trim check matches.
            if (this.value.trim()) {
                clearFieldError('projectName', 'projectNameError');
            }
        });
    }

    // Run this step if the client name is set.
    if (clientName) {
        // Handle the input event.
        clientName.addEventListener('input', function() {
            // Run this step if the trim check matches.
            if (this.value.trim()) {
                clearFieldError('clientName', 'clientNameError');
            }
        });
    }

    // Run this step if the project manager is set.
    if (projectManager) {
        // Handle the input event.
        projectManager.addEventListener('input', function() {
            // Run this step if the trim check matches.
            if (this.value.trim()) {
                clearFieldError('projectManager', 'projectManagerError');
            }
        });
    }

    // Run this step if the worker count is set.
    if (workerCount) {
        // Handle the input event.
        workerCount.addEventListener('input', function() {
            // Run this step if the value value equals an empty value or the parse int is at least 0.
            if (this.value === '' || parseInt(this.value, 10) >= 0) {
                clearFieldError('workerCount', 'workerCountError');
            }
        });
    }

    // Run this step if the start date is set.
    if (startDate) {
        // Handle the change event.
        startDate.addEventListener('change', function() {
            // Run this step if the value value is set.
            if (this.value) {
                clearFieldError('startDate', 'startDateError');
            }

            var end = document.getElementById('endDate').value;

            // Run this step if the end is set and the value is above the value.
            if (end && new Date(end) > new Date(this.value)) {
    clearEndDateError();
} else if (end && new Date(end) <= new Date(this.value)) {
    showEndDateError('Estimated end date must be after the start date.');
}
        });
    }

    // Run this step if the end date is set.
    if (endDate) {
        // Handle the change event.
        endDate.addEventListener('change', function() {
            var start = document.getElementById('startDate').value;

            // Run this step if the value value is missing or false.
            if (!this.value) {
                clearEndDateError();
                return;
            }

            // Run this step if the value value is below the project today.
            if (this.value < projectToday()) {
    showEndDateError('Estimated end date cannot be in the past.');
} else if (start && new Date(this.value) <= new Date(start)) {
    showEndDateError('Estimated end date must be after the start date.');
} else {
    clearEndDateError();
}
        });
    }

    var editStartDate = document.getElementById('editStartDate');
    var editEstEndDate = document.getElementById('editEstEndDate');
    var editActualEndDate = document.getElementById('editActualEndDate');

    // Run this step if the edit start date is set.
    if (editStartDate) {
        // Handle the change event.
        editStartDate.addEventListener('change', function() {
            // Run this step if the value value is set.
            if (this.value) {
                clearFieldError('editStartDate', 'editStartDateError');
            }

            var end = document.getElementById('editEstEndDate').value;
            var actual = document.getElementById('editActualEndDate').value;

            // Run this step if the end is set and the end is above the value value.
            if (end && end > this.value) {
                clearFieldError('editEstEndDate', 'editEstEndDateError');
            }
            // Run this step if the actual is set and the actual is above the value value.
            if (actual && actual > this.value) {
                clearFieldError('editActualEndDate', 'editActualEndDateError');
            }
        });
    }

    // Run this step if the edit est end date is set.
    if (editEstEndDate) {
        // Handle the change event.
        editEstEndDate.addEventListener('change', function() {
            var start = document.getElementById('editStartDate').value;

            // Run this step if the value value is missing or false.
            if (!this.value) {
                clearFieldError('editEstEndDate', 'editEstEndDateError');
                return;
            }

            // Run this step if the value value is below the project today and the value value does not equal the value.
            if (this.value < projectToday() && this.value !== (currentEditData && currentEditData.endDate)) {
    showFieldError('editEstEndDate', 'editEstEndDateError', 'Estimated end date cannot be in the past.');
} else if (start && new Date(this.value) <= new Date(start)) {
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

    // Run this step if the edit actual end date is set.
    if (editActualEndDate) {
        // Handle the change event.
        editActualEndDate.addEventListener('change', function() {
            // Run this step if the value value is missing or false.
            if (!this.value) {
                clearFieldError('editActualEndDate', 'editActualEndDateError');
                return;
            }

            var todayStr = projectToday();
            var start = document.getElementById('editStartDate').value;

            // Run this step if the value value is below the today str and the value value does not equal the value.
            if (this.value < todayStr && this.value !== (currentEditData && currentEditData.actualEndDate)) {
                showFieldError(
                    'editActualEndDate',
                    'editActualEndDateError',
                    'Actual end date cannot be in the past.'
                );
            } else if (start && this.value <= start) {
                showFieldError(
                    'editActualEndDate',
                    'editActualEndDateError',
                    'Actual end date must be after the start date.'
                );
            } else {
                clearFieldError('editActualEndDate', 'editActualEndDateError');
            }
        });
    }

});

        // Get the next step.
        function nextStep(step) {
            // Run this step if the current step equals 1.
            if (currentStep === 1) {
    var name = document.getElementById('projectName').value.trim();
    var client = document.getElementById('clientName').value.trim();

    clearFieldError('projectName', 'projectNameError');
    clearFieldError('clientName', 'clientNameError');

    // Run this step if the name is missing or false.
    if (!name) {
        showFieldError('projectName', 'projectNameError', 'Please enter the project name.');
        return;
    }

    // Run this step if the client is missing or false.
    if (!client) {
        showFieldError('clientName', 'clientNameError', 'Please enter the client name.');
        return;
    }
}

// Run this step if the current step equals 2.
if (currentStep === 2) {
    var manager = document.getElementById('projectManager').value.trim();
    var start = document.getElementById('startDate').value;
    var end = document.getElementById('endDate').value;
    var workers = document.getElementById('workerCount').value;

    clearFieldError('projectManager', 'projectManagerError');
    clearFieldError('startDate', 'startDateError');
    clearFieldError('workerCount', 'workerCountError');
    clearEndDateError();

    // Run this step if the manager is missing or false.
    if (!manager) {
        showFieldError('projectManager', 'projectManagerError', 'Please select a project manager.');
        return;
    }

    // Run this step if the start is missing or false.
    if (!start) {
        showFieldError('startDate', 'startDateError', 'Please select a start date.');
        return;
    }

    // Run this step if the end is missing or false.
    if (!end) {
        showEndDateError('Please select an estimated end date.');
        return;
    }

    // Run this step if the workers is set and the test is missing or false or the parse int is below 1 or the parse int is above 100000.
    if (workers && (!/^\d+$/.test(workers) || parseInt(workers, 10) < 1 || parseInt(workers, 10) > 100000)) {
        showFieldError('workerCount', 'workerCountError', 'Workers must be a whole number from 1 to 100,000.');
        return;
    }

    // Run this step if the value is at most the value.
    if (new Date(end) <= new Date(start)) {
    showEndDateError('Estimated end date must be after the start date.');
    return;
}

                document.getElementById('summaryName').textContent = document.getElementById('projectName').value;
                document.getElementById('summaryClient').textContent = document.getElementById('clientName').value;
                document.getElementById('summaryManager').textContent = document.getElementById('projectManager').value.trim();
                document.getElementById('summaryStart').textContent = document.getElementById('startDate').value;
                document.getElementById('summaryEnd').textContent = document.getElementById('endDate').value;
                document.getElementById('summaryWorkers').textContent = document.getElementById('workerCount').value || '—';
            }
            goToStep(step);
        }

        // Get the prev step.
        function prevStep(step) {
            goToStep(step);
        }

        // Calculate duration.
        function calculateDuration(start, end) {
            // Return early if the start is missing or false.
            if (!start) {
                return '—';
            }
            var startDate = new Date(start);
            var endDate = end ? new Date(end) : new Date();
            // Return early if the is na n check matches or the is na n check matches or the end date is below the start date.
            if (isNaN(startDate.getTime()) || isNaN(endDate.getTime()) || endDate < startDate) {
                return '—';
            }
            var diffDays = Math.round((endDate - startDate) / (1000 * 60 * 60 * 24));
            return (diffDays / 30).toFixed(1) + ' mo';
        }

                // Get csrf token.
                function getCsrfToken() {
            var meta = document.querySelector('meta[name="csrf-token"]');
            return meta ? meta.content : '';
        }

        // Button loading state (prevents double-click / double-submit)
        function setButtonLoading(button, isLoading, loadingText) {
            // Return early if the button is missing or false.
            if (!button) return;
            // Run this step if the is loading is set.
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

        // Format date.
        function formatDate(rawDate) {
            // Return early if the raw date is missing or false.
            if (!rawDate) return '—';
            var date = new Date(rawDate);
            // Return early if the is na n check matches.
            if (isNaN(date.getTime())) return rawDate;
            var options = { year: 'numeric', month: 'short', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
        }

        // Show the amount as money.
        function formatCurrency(value) {
            var amount = parseFloat(value) || 0;
            return '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // Get the map project api record.
        function mapProjectApiRecord(record) {
            var actualDate = record.actual_end_date || null;
            return {
                id: record.project_id,
                name: record.project_name || 'Untitled Project',
                client: record.client_name || '—',
                budget: typeof record.budget === 'number' ? new Intl.NumberFormat('en-PH', {style:'currency',currency:'PHP'}).format(record.budget) : (record.budget || ''),
                manager: record.project_manager || '',
                workers: record.worker_count || '0',
                startDate: record.start_date || '',
                endDate: record.estimated_end_date || '',
                actualEndDate: actualDate || '',
                phase: record.phase || 'Planning',
                progress: record.completion_percentage || 0,
                status: record.status || 'Ongoing',
                duration: calculateDuration(record.start_date, actualDate),
                startDateDisplay: formatDate(record.start_date),
                estEndDateDisplay: formatDate(record.estimated_end_date),
                actualEndDateDisplay: actualDate ? formatDate(actualDate) : '—'
            };
        }

        // Pagination variables
        var projectPageSize = 5;
        var projectCurrentPage = 1;
        var projectFilteredData = [];

        // Render project page
        function renderProjectPage(page) {
            var totalPages = Math.max(1, Math.ceil(projectFilteredData.length / projectPageSize));
            projectCurrentPage = Math.min(Math.max(parseInt(page, 10) || 1, 1), totalPages);
            var start = (projectCurrentPage - 1) * projectPageSize;
            var end = Math.min(start + projectPageSize, projectFilteredData.length);
            var pageData = projectFilteredData.slice(start, end);
            
            var tbody = document.getElementById('projectTableBody');
            tbody.innerHTML = '';
            // Run this step if the page data length equals 0.
            if (pageData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" style="text-align:center;padding:28px;color:#6b7280">No projects match the selected filters.</td></tr>';
            }
            // Process each project.
            pageData.forEach(function(project) {
                tbody.appendChild(createProjectRow(project));
            });
            
            renderProjectPagination();
            // Run this step if the refresh table scroll fade is set.
            if (window.refreshTableScrollFade) window.refreshTableScrollFade();
        }

        // Render project pagination
                function renderProjectPagination() {
            var container = document.getElementById('projectPaginationLinks');
            // Return early if the container is missing or false.
            if (!container) return;
            
            var total = projectFilteredData.length;
            var totalEl = document.getElementById('projectTotalCount');
            // Run this step if the total el is set.
            if (totalEl) totalEl.textContent = 'Total: ' + total;
            var totalPages = Math.ceil(total / projectPageSize);
            var current = projectCurrentPage;
            
            // Run this step if the total pages is at most 1.
            if (totalPages <= 1) {
                container.innerHTML = `<span class="pagination-info">Showing all ${total} projects</span>`;
                return;
            }
            
            var html = '';
            
            // Previous
            html += `<a href="#" onclick="renderProjectPage(${current - 1}); return false;" class="${current <= 1 ? 'disabled' : ''}">«</a>`;
            
            // Page numbers - show first 3, then dots, then last 2
            if (totalPages <= 7) {
                // Repeat for each step in this range.
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
                // Repeat for each step in this range.
                for (var i = startPage; i <= endPage; i++) {
                    html += `<a href="#" onclick="renderProjectPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
                }
                
                // Dots if current is before page total-3
                if (current < totalPages - 3) {
                    html += `<span class="dots">...</span>`;
                }
                
                // Last 2 pages
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    // Run this step if the i is above 3.
                    if (i > 3) {
                        html += `<a href="#" onclick="renderProjectPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
                    }
                }
            }
            
            // Next
            html += `<a href="#" onclick="renderProjectPage(${current + 1}); return false;" class="${current >= totalPages ? 'disabled' : ''}">»</a>`;
            
            container.innerHTML = html;
        }

        // Change project page size
        function changeProjectPageSize() {
            var select = document.getElementById('projectRowsPerPage');
            projectPageSize = parseInt(select.value) || 10;
            projectCurrentPage = 1;
            renderProjectPage(1);
        }

        // Filter projects.
        function filterProjects() {
            projectSearchTerm = document.getElementById('projectSearch').value.toLowerCase().trim();
            var status = document.getElementById('projectStatusFilter').value;
            var phase = document.getElementById('projectPhaseFilter').value;
            var from = document.getElementById('projectDateFrom').value;
            var to = document.getElementById('projectDateTo').value;
            // Keep the matching project items.
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

        // Clear project search.
        function clearProjectSearch() {
            // Process each id.
            ['projectSearch', 'projectStatusFilter', 'projectPhaseFilter', 'projectDateFrom', 'projectDateTo']
                .forEach(function(id) { document.getElementById(id).value = ''; });
            projectSearchTerm = '';
            filterProjects();
        }

        // Update stats.
        function updateStats(projects) {
            allProjects = projects.slice();
            filterProjects();
        }

        // Refresh project analytics.
        function refreshProjectAnalytics(projects) {
        // Keep the matching p items.
        var activeProjects = projects.filter(function(p) {
            return p.status !== 'Completed';
        });
        
        // Keep the matching p items.
        var onSchedule = projects.filter(function(p) {
            return p.status === 'Ongoing';
        });
        
        // Keep the matching p items.
        var delayed = projects.filter(function(p) {
            return p.status === 'Delayed' || p.status === 'At Risk';
        });
        
        var totalProgress = 0;
        // Process each p.
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
        
        renderStatusChart(projects);
        }

        // Show status chart.
        function renderStatusChart(projects) {
            var chart = document.getElementById('projectStatusChart');
            var scope = document.getElementById('projectChartScope');
            // Return early if the chart is missing or false or the scope is missing or false.
            if (!chart || !scope) return;
            var statuses = [
                { label: 'Ongoing', color: '#4f8b68' }, { label: 'Pending', color: '#64748b' },
                { label: 'At Risk', color: '#e19a45' }, { label: 'Delayed', color: '#c95c5c' }
            ];
            // Keep the matching project items.
            var active = projects.filter(function(project) { return project.status !== 'Completed'; });
            scope.textContent = '(' + active.length + ' active)';
            // Run this step if the project status pie is set.
            if (window.projectStatusPie) window.projectStatusPie.destroy();
            // Build a value for each item.
            window.projectStatusPie = new Chart(chart, { type: 'pie', data: {
                labels: statuses.map(function(item) { return item.label; }),
                datasets: [{ data: statuses.map(function(item) { return active.filter(function(project) { return project.status === item.label; }).length; }), backgroundColor: statuses.map(function(item) { return item.color; }), hoverOffset: 12 }]
            }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' }, tooltip: { enabled: true } } } });
        }

                // Fetch projects (modified)
        function fetchProjects() {
            // Use the result when this request finishes.
            return fetch('/api/projects', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) {
                    throw new Error('Unable to load projects');
                }
                return response.json();
            })
            .then(function(data) {
                // Build a value for each item.
                var projects = data.map(function(item) {
                    return mapProjectApiRecord(item);
                });
                
                // Update stats and render first page
                updateStats(projects);

                // Auto-open a specific project's modal if linked from Dashboard
                var params = new URLSearchParams(window.location.search);
                var targetId = params.get('project');
                // Run this step if the target id is set.
                if (targetId) {
                    // Run the find index action.
                    var targetIndex = projectFilteredData.findIndex(function(project) {
                        return String(project.id) === String(targetId);
                    });
                    // Run this step if the target index equals the value.
                    if (targetIndex === -1) {
                        // Process each id.
                        ['projectSearch', 'projectStatusFilter', 'projectPhaseFilter', 'projectDateFrom', 'projectDateTo']
                            .forEach(function(id) { document.getElementById(id).value = ''; });
                        projectSearchTerm = '';
                        filterProjects();
                        // Run the find index action.
                        targetIndex = projectFilteredData.findIndex(function(project) {
                            return String(project.id) === String(targetId);
                        });
                    }
                    // Run this step if the target index does not equal the value.
                    if (targetIndex !== -1) {
                        renderProjectPage(Math.floor(targetIndex / projectPageSize) + 1);
                        // Run this action after the delay.
                        window.setTimeout(function() {
                            // Run the find action.
                            var targetRow = Array.from(document.querySelectorAll('#projectTableBody tr[data-project-id]')).find(function(row) {
                                return row.dataset.projectId === String(targetId);
                            });
                            var viewButton = targetRow && targetRow.querySelector('.pfims-row-action');
                            // Run this step if the view button is set.
                            if (viewButton) {
                                viewButton.click();
                                // Run this step if the get equals 1.
                                if (params.get('edit') === '1') window.setTimeout(openEditProjectModal, 0);
                            }
                        }, 0);
                    }
                }
            })
            .catch(function(error) {
                console.error(error);
                showError('Failed to load projects.');
            });
        }

        // Load project managers.
        function loadProjectManagers() {
            // Use the result when this request finishes.
            return fetch('/api/project-managers', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Unable to load project managers');
                return response.json();
            }).then(function(managers) {
                // Process each id.
                ['projectManager', 'editProjectManager'].forEach(function(id) {
                    var select = document.getElementById(id);
                    select.innerHTML = '<option value="">' + (managers.length ? 'Select project manager' : 'Add project managers in Settings first') + '</option>';
                    // Process each manager.
                    managers.forEach(function(manager) {
                        select.add(new Option(manager.manager_name, manager.manager_name));
                    });
                });
            });
        }

        // Load project phases.
        function loadProjectPhases() {
            // Use the result when this request finishes.
            return fetch('/api/project-phases', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Unable to load project phases');
                return response.json();
            })
            .then(function(phases) {
                var filter = document.getElementById('projectPhaseFilter');
                var edit = document.getElementById('editPhase');
                filter.innerHTML = '<option value="">All phases</option>';
                edit.innerHTML = '';
                // Process each phase.
                phases.forEach(function(phase) {
                    var filterOption = document.createElement('option');
                    filterOption.value = phase.phase_name;
                    filterOption.textContent = phase.phase_name;
                    filter.appendChild(filterOption);

                    var editOption = document.createElement('option');
                    editOption.value = phase.phase_name;
                    editOption.textContent = phase.stage_order + '. ' + phase.phase_name;
                    edit.appendChild(editOption);
                });
            });
        }

        // Escape text so it can be shown safely in HTML.
        function escapeHtml(value) {
            // Run the replace action.
            return String(value == null ? '' : value).replace(/[&<>'"]/g, function(character) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' })[character];
            });
        }

        // Get the api error message.
        function apiErrorMessage(error, fallback) {
            // Run this step if the error is set and the errors is set.
            if (error && error.errors) {
                var fields = Object.keys(error.errors);
                // Return early if the fields length is set and the 0 length is set.
                if (fields.length && error.errors[fields[0]].length) return error.errors[fields[0]][0];
            }
            return (error && error.message) || fallback;
        }

        // Update project row.
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
            row.dataset.status = project.status || 'Ongoing';
            row.dataset.budget = project.budget || '';

            // Open project from action.
            function openProjectFromAction(editMode) {
                // Return early if the is opening project modal is set.
                if (isOpeningProjectModal) return;
                isOpeningProjectModal = true;
                openUpdateModal(
                    row,
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
                // Run this step if the edit mode is set.
                if (editMode) setTimeout(openEditProjectModal, 0);
                // Run this action after the delay.
                setTimeout(function() { isOpeningProjectModal = false; }, 400);
            }
            row.onclick = null;
            row.style.cursor = 'default';

            var progress = Math.min(100, Math.max(0, parseFloat(project.progress) || 0));
            row.innerHTML = '' +
                '<td><strong>' + escapeHtml(project.name) + '</strong></td>' +
                '<td>' + escapeHtml(project.client) + '</td>' +
                '<td>' + escapeHtml(project.manager || '—') + '</td>' +
                '<td>' + escapeHtml(project.workers ?? 0) + '</td>' +
                '<td>' + escapeHtml(project.startDateDisplay) + '</td>' +
                '<td>' + escapeHtml(project.estEndDateDisplay) + '</td>' +
                '<td>' + escapeHtml(project.actualEndDateDisplay || '—') + '</td>' +
                '<td>' + escapeHtml(project.duration) + '</td>' +
                '<td><span class="phase-badge">' + escapeHtml(project.phase) + '</span></td>' +
                '<td>' +
                    '<div class="progress-cell">' +
                        '<div class="mini-bar"><div class="fill" style="width:' + progress + '%;"></div></div>' +
                        '<span>' + progress + '%</span>' +
                    '</div>' +
                '</td>' +
                '<td><span class="status-badge ' + (project.status === 'Completed' ? 'completed' : project.status === 'Delayed' ? 'delayed' : project.status === 'Ongoing' ? 'on-track' : project.status === 'Pending' ? 'pending' : 'at-risk') + '"><span class="dot"></span> ' + escapeHtml(project.status) + '</span></td>' +
                '<td class="action-cell"><button type="button" class="pfims-row-action" title="View project" aria-label="View project"><img src="' + escapeHtml(document.body.dataset.projectViewIcon) + '" alt=""></button></td>';
            var actionButtons = row.querySelectorAll('.pfims-row-action');
            actionButtons[0].onclick = function(event) { event.stopPropagation(); openProjectFromAction(false); };
        }

        // Create project row.
        function createProjectRow(project) {
            var tr = document.createElement('tr');
            updateProjectRow(tr, project);
            return tr;
        }

                // Save project
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

// Run this step if the name is missing or false.
if (!name) {
    showFieldError('projectName', 'projectNameError', 'Please enter the project name.');
    return;
}

// Run this step if the client is missing or false.
if (!client) {
    showFieldError('clientName', 'clientNameError', 'Please enter the client name.');
    return;
}

// Run this step if the manager is missing or false.
if (!manager) {
    showFieldError('projectManager', 'projectManagerError', 'Please select a project manager.');
    return;
}

// Run this step if the start date is missing or false.
if (!startDate) {
    showFieldError('startDate', 'startDateError', 'Please select a start date.');
    return;
}

// Run this step if the end date is missing or false.
if (!endDate) {
    showEndDateError('Please select an estimated end date.');
    return;
}

// Run this step if the end date is below the project today.
if (endDate < projectToday()) {
    showEndDateError('Estimated end date cannot be in the past.');
    return;
}

// Run this step if the workers is set and the test is missing or false or the parse int is below 1 or the parse int is above 100000.
if (workers && (!/^\d+$/.test(workers) || parseInt(workers, 10) < 1 || parseInt(workers, 10) > 100000)) {
    showFieldError('workerCount', 'workerCountError', 'Workers must be a whole number from 1 to 100,000.');
    return;
}

// Run this step if the value is at most the value.
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
                status: 'Ongoing'
            };

            setButtonLoading(saveBtn, true, 'Saving...');

            // Use the result when this request finishes.
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
                // Return early if the response ok is missing or false.
                if (!response.ok) {
                    // Use the result when this request finishes.
                    return response.json().then(function(err) {
                        throw new Error(apiErrorMessage(err, 'Failed to save project'));
                    });
                }
                return response.json();
            })
            .then(function(savedProject) {
                var project = mapProjectApiRecord(savedProject);
                var tbody = document.getElementById('projectTableBody');
                // Run this step if the tbody is set.
                if (tbody) {
                    tbody.appendChild(createProjectRow(project));
                }
                // Update stats
                                allProjects.unshift(project);
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

        // Handle the click event.
        document.getElementById('projectModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) { closeModal(); }
        });

        // Update project overview modal
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
                // The modal receives a formatted actual end date for display,
                // while the row retains the ISO value needed by date inputs.
                actualEndDate: (row && row.dataset && row.dataset.actualEndDate) || '',
                phase: phase,
                progress: progress || 0,
                status: status || 'Ongoing',
                startDateDisplay: startDate,
                estEndDateDisplay: estEndDate,
                actualEndDateDisplay: actualEndDate || '—',
                duration: duration
            };

            document.getElementById('updateProjectName').textContent = projectName;
            document.getElementById('updateClientName').textContent = clientName;
            document.getElementById('updateProjectManager').textContent = manager || '—';
            document.getElementById('updateWorkerCount').textContent = workers ?? 0;
            document.getElementById('updateProgress').textContent = Math.min(100, Math.max(0, parseFloat(progress) || 0)) + '%';
            document.getElementById('updateBudget').textContent = budget ? formatCurrency(budget) : '—';
            document.getElementById('updateStartDate').textContent = startDate;
            document.getElementById('updateEstEndDate').textContent = estEndDate;
            document.getElementById('updateActualEndDate').textContent = actualEndDate || '—';
            document.getElementById('updateDuration').textContent = duration;
            document.getElementById('updatePhase').textContent = phase;

            var statusEl = document.getElementById('updateStatus');
            statusEl.textContent = status;
            statusEl.className = 'status-badge';
            // Run this step if the status equals Ongoing.
            if (status === 'Ongoing') statusEl.classList.add('on-track');
            else if (status === 'Delayed') statusEl.classList.add('delayed');
            else if (status === 'Completed') statusEl.classList.add('completed');
            else if (status === 'Pending') statusEl.classList.add('pending');
            else statusEl.classList.add('at-risk');

            document.getElementById('updateModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close update modal.
        function closeUpdateModal() {
            document.getElementById('updateModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        document.getElementById('projectDetailsEditBtn').addEventListener('click', openEditProjectModal);
        document.getElementById('projectDetailsDeleteBtn').addEventListener('click', deleteProject);

        // Delete project
                function deleteProject() {
            // Run this step if the current project row is missing or false.
            if (!currentProjectRow) {
                showError('No project selected to delete.');
                return;
            }

            var deleteConfirmBtn = document.getElementById('confirmDeleteBtn');
            var projectId = currentProjectRow.dataset.projectId;
            // Run the open delete modal action.
            openDeleteModal('Are you sure you want to move this project to Trash Bin?', function() {
                // Run this step if the project id is missing or false.
                if (!projectId) {
                    currentProjectRow.remove();
                    // Keep the matching p items.
                    allProjects = allProjects.filter(function(p) {
                        return p.id !== currentEditData.id;
                    });
                    updateStats(allProjects);
                    closeUpdateModal();
                    closeEditProjectModal();
                    showSuccess('Project deleted successfully!');
                    currentProjectRow = null;
                    return;
                }

                                setButtonLoading(deleteConfirmBtn, true, 'Deleting...');

                // Use the result when this request finishes.
                fetch('/api/projects/' + projectId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) {
                    // Return early if the response ok is missing or false.
                    if (!response.ok) {
                        // Try to parse error message
                        return response.text().then(function(text) {
                            var data;
                            // Try this action and handle any errors below.
                            try { data = JSON.parse(text); } catch (e) { data = null; }
                            throw new Error((data && data.message) || 'Failed to delete project. Server error.');
                        });
                    }
                    return response.json();
                })
                .then(function() {
                    currentProjectRow.remove();
                    // Keep the matching p items.
                    allProjects = allProjects.filter(function(p) {
                        return p.id !== currentEditData.id;
                    });
                    updateStats(allProjects);
                    closeUpdateModal();
                    closeEditProjectModal();
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

        // Edit project modal
        function openEditProjectModal() {
            // Run this step if the current edit data is missing or false.
            if (!currentEditData) {
                showError('No project data to edit.');
                return;
            }
            refreshProjectEndDateBounds();
            document.getElementById('editProjectOriginalName').value = currentEditData.name;
            document.getElementById('editProjectName').value = currentEditData.name || '';
            document.getElementById('editWorkerCount').value = currentEditData.workers || '';
            document.getElementById('editClientName').value = currentEditData.client || '';
            var managerSelect = document.getElementById('editProjectManager');
            // Preserve historical imported assignments when editing other fields.
            managerSelect.querySelectorAll('[data-historical]').forEach(function(option) { option.remove(); });
            var managerName = currentEditData.manager || '';
            // Run this step if the manager name is set and the some is missing or false.
            if (managerName && !Array.from(managerSelect.options).some(function(option) { return option.value === managerName; })) {
                var historical = new Option(managerName + ' (current)', managerName);
                historical.dataset.historical = '1';
                managerSelect.add(historical);
            }
            managerSelect.value = managerName;
            document.getElementById('editPhase').value = currentEditData.phase || 'Planning';
            document.getElementById('editStatus').value = currentEditData.status || 'Ongoing';
            document.getElementById('editStartDate').value = currentEditData.startDate || '';
            document.getElementById('editEstEndDate').value = currentEditData.endDate || '';
            document.getElementById('editActualEndDate').value = currentEditData.actualEndDate || '';

            closeUpdateModal();
            document.getElementById('editProjectModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close edit project modal.
        function closeEditProjectModal() {
            document.getElementById('editProjectModal').classList.remove('active');
            document.body.style.overflow = '';
        }

                // Save edit project.
                function saveEditProject() {
            // Run this step if the current project row is missing or false or the current edit data is missing or false.
            if (!currentProjectRow || !currentEditData) {
                showError('No project selected to edit.');
                return;
            }

            var editSaveBtn = document.querySelector('#editProjectModal .btn-save');

            var projectName = document.getElementById('editProjectName').value.trim();
            var clientName = document.getElementById('editClientName').value.trim();
            var manager = document.getElementById('editProjectManager').value.trim();
            var phase = document.getElementById('editPhase').value;
            var status = document.getElementById('editStatus').value;
            var start = document.getElementById('editStartDate').value;
            var estEnd = document.getElementById('editEstEndDate').value;
            var actualEnd = document.getElementById('editActualEndDate').value;

            clearFieldError('editProjectName', 'editProjectNameError');
            clearFieldError('editClientName', 'editClientNameError');
            clearFieldError('editProjectManager', 'editProjectManagerError');
            // Run this step if the project name is missing or false.
            if (!projectName) {
                showFieldError('editProjectName', 'editProjectNameError', 'Please enter the project name.');
                return;
            }
            // Run this step if the client name is missing or false.
            if (!clientName) {
                showFieldError('editClientName', 'editClientNameError', 'Please enter the client name.');
                return;
            }
            // Run this step if the manager is missing or false.
            if (!manager) {
                showFieldError('editProjectManager', 'editProjectManagerError', 'Please select a project manager.');
                return;
            }

                        clearFieldError('editStartDate', 'editStartDateError');
clearFieldError('editEstEndDate', 'editEstEndDateError');
clearFieldError('editActualEndDate', 'editActualEndDateError');

// Run this step if the start is missing or false.
if (!start) {
    showFieldError('editStartDate', 'editStartDateError', 'Please select a start date.');
    return;
}

// Run this step if the est end is missing or false.
if (!estEnd) {
    showFieldError('editEstEndDate', 'editEstEndDateError', 'Please select an estimated end date.');
    return;
}

// Run this step if the est end is below the project today and the est end does not equal the end date.
if (estEnd < projectToday() && estEnd !== currentEditData.endDate) {
    showFieldError('editEstEndDate', 'editEstEndDateError', 'Estimated end date cannot be in the past.');
    return;
}

// Run this step if the value is at most the value.
if (new Date(estEnd) <= new Date(start)) {
    showFieldError(
        'editEstEndDate',
        'editEstEndDateError',
        'Estimated end date must be after the start date.'
    );
    return;
}

var todayStr = projectToday();

// Run this step if the value checks match.
if (actualEnd && actualEnd < todayStr && actualEnd !== currentEditData.actualEndDate) {
    showFieldError(
        'editActualEndDate',
        'editActualEndDateError',
        'Actual end date cannot be in the past.'
    );
    return;
}

// Run this step if the actual end is set and the actual end is at most the start.
if (actualEnd && actualEnd <= start) {
    showFieldError(
        'editActualEndDate',
        'editActualEndDateError',
        'Actual end date must be after the start date.'
    );
    return;
}

            var editedWorkers = Number(document.getElementById('editWorkerCount').value);
            // Run this step if the is integer is missing or false or the edited workers is below 1 or the edited workers is above 100000.
            if (!Number.isInteger(editedWorkers) || editedWorkers < 1 || editedWorkers > 100000) {
                showError('Workers must be a whole number from 1 to 100,000.');
                return;
            }
                        var payload = {
                worker_count: editedWorkers,
                project_name: projectName,
                client_name: clientName,
                project_manager: manager,
                phase: phase,
                status: status,
                start_date: start,
                estimated_end_date: estEnd,
                actual_end_date: actualEnd || null
            };

            setButtonLoading(editSaveBtn, true, 'Saving...');

            // Use the result when this request finishes.
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
                // Return early if the response ok is missing or false.
                if (!response.ok) {
                    // Use the result when this request finishes.
                    return response.json().then(function(err) {
                        throw new Error(apiErrorMessage(err, 'Failed to save project changes'));
                    });
                }
                return response.json();
            })
            .then(function(updatedProject) {
                currentEditData = mapProjectApiRecord(updatedProject);

                updateProjectRow(currentProjectRow, currentEditData);
                
                // Update stats
                var index = allProjects.findIndex(function(p) {
                    return p.id === currentEditData.id;
                });
                // Run this step if the index does not equal the value.
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

        // Close modals on backdrop click
        document.getElementById('updateModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) { closeUpdateModal(); }
        });
        // Handle the click event.
        document.getElementById('editProjectModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) { closeEditProjectModal(); }
        });

        // Handle the click event.
        document.addEventListener('click', function(e) {
            // Run this step if the style display equals block.
            if (document.getElementById('errorNotification').style.display === 'block') {
                // Run this step if the closest is missing or false.
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            // Run this step if the style display equals block.
            if (document.getElementById('successNotification').style.display === 'block') {
                // Run this step if the closest is missing or false.
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });

                // Load notif badge.
                function fetchNotifBadge() {
            // Use the result when this request finishes.
            fetch('/api/notifications/unread-count', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Failed to load unread count.');
                return response.json();
            })
            .then(function(data) {
                var badge = document.getElementById('notifBadge');
                // Return early if the badge is missing or false.
                if (!badge) return;
                var count = data.unread_count || 0;
                // Run this step if the count is above 0.
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

        // Set up project page.
        function initializeProjectPage() {
            // Handle a failed request.
            Promise.all([loadProjectPhases(), loadProjectManagers()])
                .then(fetchProjects)
                .catch(function(error) {
                    console.error(error);
                    showError('Failed to load project options. Please refresh the page.');
                });
            fetchNotifBadge();
        }

                // Run this step if the ready state equals loading.
                if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeProjectPage);
        } else {
            initializeProjectPage();
        }
    </script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/table-scroll-fade.js') }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script data-login-epoch="{{ session('navigation_epoch', 'initial') }}" src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}"></script>

    @include('partials.validation-alert')
</body>
</html>
