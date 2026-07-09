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
                <li><a href="{{ url('/dashboard') }}">DASHBOARD</a></li>
                <li class="active"><a href="{{ url('/projects') }}">PROJECTS</a></li>
                <li><a href="{{ url('/finance') }}">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}" style="color: inherit; text-decoration: none; display: block;">INVENTORY</a></li>
                <li><a href="{{ url('/suppliers') }}" style="color: inherit; text-decoration: none; display: block;">SUPPLIERS</a></li>
                <li><a href="{{ url('/reports') }}">REPORTS</a></li>
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
                    <div class="stat-value">12</div>
                    <div class="stat-sub">+2 this month</div>
                </div>
            </div>
            <div class="stat-card-proj">
                <div class="stat-info">
                    <div class="stat-label">On Schedule</div>
                    <div class="stat-value">8</div>
                    <div class="stat-sub">67% of active</div>
                </div>
            </div>
            <div class="stat-card-proj">
                <div class="stat-info">
                    <div class="stat-label">Delayed</div>
                    <div class="stat-value">3</div>
                    <div class="stat-sub">Needs attention</div>
                </div>
            </div>
            <div class="stat-card-proj">
                <div class="stat-info">
                    <div class="stat-label">Avg Completion</div>
                    <div class="stat-value">61%</div>
                    <div class="stat-sub">+6% vs last month</div>
                </div>
            </div>
        </div>

        <!-- Table with Progress Bar -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Client Name</th>
                        <th>Budget</th>
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
                    <label>Project name</label>
                    <input type="text" placeholder="e.g. Skyline Tower Phase 2" id="projectName">
                </div>
                <div class="form-group">
                    <label>Client name</label>
                    <input type="text" placeholder="e.g. Mega Realty Corporation" id="clientName">
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
                    </div>
                    <div class="form-group">
                        <label>No. of workers</label>
                        <input type="number" placeholder="e.g. 50" id="workerCount">
                    </div>
                </div>

                <h3>TIMELINE</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Start date <span class="required">*</span></label>
                        <input type="date" id="startDate">
                    </div>
                    <div class="form-group">
                        <label>Estimated end date <span class="required">*</span></label>
                        <input type="date" id="endDate">
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
                    <span id="updateStartDate">Jan 15, 2025</span>
                </div>
                <div class="detail-item">
                    <label>Est. End Date</label>
                    <span id="updateEstEndDate">Dec 30, 2025</span>
                </div>
                <div class="detail-item">
                    <label>Actual End Date</label>
                    <span id="updateActualEndDate">—</span>
                </div>
                <div class="detail-item">
                    <label>Duration</label>
                    <span id="updateDuration">11.5 mo</span>
                </div>
                <div class="detail-item">
                    <label>Phase</label>
                    <span id="updatePhase" class="phase-badge">Structure</span>
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span id="updateStatus" class="status-badge at-risk">At Risk</span>
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
                <div class="form-row">
                    <div class="form-group">
                        <label>Start Date</label>
                        <input type="date" id="editStartDate">
                    </div>
                    <div class="form-group">
                        <label>Estimated End Date</label>
                        <input type="date" id="editEstEndDate">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Actual End Date</label>
                        <input type="date" id="editActualEndDate">
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

        // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // ─── ERROR NOTIFICATION (POP-UP) ───
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

        function nextStep(step) {
            if (currentStep === 1) {
                var name = document.getElementById('projectName').value.trim();
                var client = document.getElementById('clientName').value.trim();
                if (!name) { showError('Please enter the project name.'); return; }
                if (!client) { showError('Please enter the client name.'); return; }
            }
            if (currentStep === 2) {
                var manager = document.getElementById('projectManager').value;
                var start = document.getElementById('startDate').value;
                var end = document.getElementById('endDate').value;
                if (!manager) { showError('Please select a project manager.'); return; }
                if (!start) { showError('Please select a start date.'); return; }
                if (!end) { showError('Please select an estimated end date.'); return; }

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

        function formatDate(rawDate) {
            if (!rawDate) return '—';
            var date = new Date(rawDate);
            if (isNaN(date.getTime())) return rawDate;
            var options = { year: 'numeric', month: 'short', day: 'numeric' };
            return date.toLocaleDateString('en-US', options);
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
                var tbody = document.getElementById('projectTableBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                data.forEach(function(item) {
                    tbody.appendChild(createProjectRow(mapProjectApiRecord(item)));
                });
            })
            .catch(function(error) {
                console.error(error);
                showError('Failed to load projects.');
            });
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

            row.onclick = function() {
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
            };

            row.innerHTML = '' +
                '<td><strong>' + project.name + '</strong></td>' +
                '<td>' + project.client + '</td>' +
                '<td>' + (project.budget || '—') + '</td>' +
                '<td>' + project.startDateDisplay + '</td>' +
                '<td>' + project.estEndDateDisplay + '</td>' +
                '<td>' + (project.actualEndDateDisplay || '—') + '</td>' +
                '<td>' + project.duration + '</td>' +
                '<td><span class="phase-badge">' + project.phase + '</span></td>' +
                '<td>' +
                    '<div class="progress-cell">' +
                        '<div class="mini-bar"><div class="fill" style="width:' + project.progress + '%;"></div></div>' +
                    '</div>' +
                '</td>' +
                '<td><span class="status-badge ' + (project.status === 'Completed' ? 'completed' : project.status === 'Delayed' ? 'delayed' : project.status === 'On Track' ? 'on-track' : 'at-risk') + '"><span class="dot"></span> ' + project.status + '</span></td>';
        }

        function createProjectRow(project) {
            var tr = document.createElement('tr');
            updateProjectRow(tr, project);
            return tr;
        }

        function saveProject() {
            var name = document.getElementById('projectName').value.trim();
            var client = document.getElementById('clientName').value.trim();
            var manager = document.getElementById('projectManager').value.trim();
            var workers = document.getElementById('workerCount').value.trim();
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;

            if (!name) { showError('Please enter the project name.'); return; }
            if (!client) { showError('Please enter the client name.'); return; }
            if (!manager) { showError('Please select a project manager.'); return; }
            if (!startDate) { showError('Please select a start date.'); return; }
            if (!endDate) { showError('Please select an estimated end date.'); return; }

            var payload = {
                project_name: name,
                client_name: client,
                budget: '',
                project_manager: manager,
                start_date: startDate,
                estimated_end_date: endDate,
                actual_end_date: '',
                worker_count: workers ? parseInt(workers, 10) : 0,
                phase: 'Planning',
                completion_percentage: 0,
                status: 'On Track'
            };

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
                        throw new Error(err.message || 'Failed to save project');
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
                closeModal();
                showSuccess('Project added successfully!');
            })
            .catch(function(error) {
                console.error(error);
                showError(error.message || 'Failed to save project.');
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
            document.getElementById('updateBudget').textContent = budget || '—';
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

            var projectId = currentProjectRow.dataset.projectId;
            openDeleteModal('Are you sure you want to permanently delete this project?', function() {
                if (!projectId) {
                    currentProjectRow.remove();
                    closeUpdateModal();
                    showSuccess('Project deleted successfully!');
                    currentProjectRow = null;
                    return;
                }

                fetch('/api/projects/' + projectId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': getCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(response) {
                    if (!response.ok) {
                        return response.json().then(function(err) {
                            throw new Error(err.message || 'Failed to delete project');
                        });
                    }
                    return response.json();
                })
                .then(function() {
                    currentProjectRow.remove();
                    closeUpdateModal();
                    showSuccess('Project deleted successfully!');
                    currentProjectRow = null;
                })
                .catch(function(error) {
                    console.error(error);
                    showError(error.message || 'Failed to delete project.');
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
            document.getElementById('editStartDate').value = currentEditData.startDate || '';
            document.getElementById('editEstEndDate').value = currentEditData.endDate || '';
            document.getElementById('editActualEndDate').value = currentEditData.actualEndDate || '';

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

            var phase = document.getElementById('editPhase').value;
            var start = document.getElementById('editStartDate').value;
            var estEnd = document.getElementById('editEstEndDate').value;
            var actualEnd = document.getElementById('editActualEndDate').value;

            if (!start || !estEnd) {
                showError('Please fill in all required fields (Start Date, Estimated End Date).');
                return;
            }

            var payload = {
                phase: phase,
                start_date: start,
                estimated_end_date: estEnd,
                actual_end_date: actualEnd || null
            };

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
                        throw new Error(err.message || 'Failed to save project changes');
                    });
                }
                return response.json();
            })
            .then(function(updatedProject) {
                currentEditData.phase = phase;
                currentEditData.startDate = start;
                currentEditData.endDate = estEnd;
                currentEditData.actualEndDate = actualEnd || '';
                currentEditData.startDateDisplay = formatDate(start);
                currentEditData.estEndDateDisplay = formatDate(estEnd);
                currentEditData.actualEndDateDisplay = actualEnd ? formatDate(actualEnd) : '—';
                currentEditData.duration = calculateDuration(start, actualEnd);

                updateProjectRow(currentProjectRow, currentEditData);
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

        function initializeProjectPage() {
            fetchProjects();
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeProjectPage);
        } else {
            initializeProjectPage();
        }
    </script>

</body>
</html>