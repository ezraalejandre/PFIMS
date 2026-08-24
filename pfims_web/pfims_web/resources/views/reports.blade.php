<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Reports - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="reports-page">

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
            <a href="{{ url('/notifications') }}" style="opacity: 1; position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span style="font-weight: 600;">Notifications</span>
                <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
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
                <li><a href="{{ url('/dashboard') }}"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
                <li><a href="{{ url('/projects') }}"><img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon">PROJECTS</a></li>
                <li><a href="{{ url('/finance') }}"><img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}"><img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon">INVENTORY</a></li>
                <li><a href="{{ url('/suppliers') }}"><img src="{{ asset('images/suppliers.png') }}" alt="" class="nav-link-icon">SUPPLIERS</a></li>
                <li class="active"><a href="{{ url('/reports') }}"><img src="{{ asset('images/reports.png') }}" alt="" class="nav-link-icon">REPORTS</a></li>
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

    <!-- ─── DELETE CONFIRMATION MODAL ─── -->
    <div id="deleteConfirmModal" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage" style="font-size: 1rem; color: #333; margin-bottom: 10px;">
                    Are you sure you want to permanently delete this report?
                </p>
                <p style="font-size: 0.85rem; color: #888; margin-bottom: 20px;">
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 12px; margin-top: 10px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeDeleteModal()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: transparent; color: #888; transition: 0.3s;">Cancel</button>
                <button class="btn-delete" onclick="confirmDeleteReport()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #d32f2f; color: #fff; transition: 0.3s;">Delete</button>
            </div>
        </div>
    </div>

    <!-- ─── REPORT DETAIL MODAL ─── -->
    <div id="reportDetailModal" class="modal-overlay modal-view" style="display: none; z-index: 9999;">
        <div class="modal-container" style="max-width: 550px;">
            <div class="modal-header">
                <h2>Report Details</h2>
                <button class="modal-close" onclick="closeReportDetailModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="view-details-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px 25px; padding: 20px; background: #faf8f5; border-radius: 12px; margin-bottom: 10px;">
                    <div class="view-item">
                        <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px;">Report ID</label>
                        <span id="detailReportId" class="view-value" style="font-size: 1rem; font-weight: 500; color: #1a2b3c;">—</span>
                    </div>
                    <div class="view-item">
                        <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px;">Title</label>
                        <span id="detailTitle" class="view-value" style="font-size: 1rem; font-weight: 500; color: #1a2b3c;">—</span>
                    </div>
                    <div class="view-item">
                        <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px;">Type</label>
                        <span id="detailType" class="view-value" style="font-size: 1rem; font-weight: 500; color: #1a2b3c;">—</span>
                    </div>
                    <div class="view-item">
                        <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px;">Role</label>
                        <span id="detailRole" class="view-value" style="font-size: 1rem; font-weight: 500; color: #1a2b3c;">—</span>
                    </div>
                    <div class="view-item" style="grid-column: 1 / -1;">
                        <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px;">File Name</label>
                        <span id="detailFileName" class="view-value" style="font-size: 1rem; font-weight: 500; color: #1a2b3c;">—</span>
                    </div>
                    <div class="view-item">
                        <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px;">Date Uploaded</label>
                        <span id="detailDate" class="view-value" style="font-size: 1rem; font-weight: 500; color: #1a2b3c;">—</span>
                    </div>
                    <div class="view-item">
                        <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px;">Uploaded By</label>
                        <span id="detailUploadedBy" class="view-value" style="font-size: 1rem; font-weight: 500; color: #1a2b3c;">—</span>
                    </div>
                    <div class="view-item" style="grid-column: 1 / -1;">
                        <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px;">Status</label>
                        <span id="detailStatus" class="view-value" style="font-size: 1rem; font-weight: 500;">—</span>
                    </div>
                    <div class="view-item" style="grid-column: 1 / -1;">
                        <label style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.5px; color: #888; margin-bottom: 2px;">Description</label>
                        <span id="detailDescription" class="view-value" style="font-size: 0.95rem; font-weight: 400; color: #555; padding: 4px 0;">—</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 10px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeReportDetailModal()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: transparent; color: #888; transition: 0.3s;">Close</button>
                <button class="btn-edit-project" onclick="openReportFile()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #c9a96e; color: #fff; transition: 0.3s;">View File</button>
            </div>
        </div>
    </div>

    <!-- ─── MAIN CONTENT ─── -->
    <main class="main-content">

        <div class="page-header">
            <h1>REPORTS</h1>
            <button class="btn-filter" onclick="openUploadModal()">+ Upload Report</button>
        </div>

        <div class="filters-bar">
            <input type="text" class="search-input" id="reportSearch" placeholder="Search reports by title or type..." oninput="filterReports()">
            <input type="date" class="date-input" id="startDate" value="{{ date('Y-m-d', strtotime('-30 days')) }}">
            <span style="color: #888; font-size: 0.9rem;">to</span>
            <input type="date" class="date-input" id="endDate" value="{{ date('Y-m-d') }}">
                        <select id="reportTypeFilter" onchange="syncTabFromDropdown(); filterReports();">
                <option value="all">All Types</option>
                <option value="project">Project Reports</option>
                <option value="finance">Financial Reports</option>
                <option value="budget">Budget Reports</option>
                <option value="expense">Expense Reports</option>
                <option value="inventory">Inventory Reports</option>
                <option value="workforce">Workforce Reports</option>
                <option value="supplier">Supplier Reports</option>
                <option value="other">Other</option>
            </select>
            <select id="roleFilter" onchange="filterReports()">
                <option value="all">All Roles</option>
                <option value="accounting">Accounting</option>
                <option value="operations">Operations</option>
                <option value="admin">Admin</option>
            </select>
            <button class="btn-filter" onclick="applyFilters()">Apply Filters</button>
        </div>

        <!-- Tab Row with Actions -->
        <div class="tab-row">
                            <div class="report-tabs">
                <span class="tab active" onclick="switchTab(this, 'all')">All Reports</span>
                <span class="tab" onclick="switchTab(this, 'project')">Projects</span>
                <span class="tab" onclick="switchTab(this, 'finance')">Finance</span>
                <span class="tab" onclick="switchTab(this, 'inventory')">Inventory</span>
                <span class="tab" onclick="switchTab(this, 'budget')">Budget</span>
                <span class="tab" onclick="switchTab(this, 'expense')">Expense</span>
                <span class="tab" onclick="switchTab(this, 'workforce')">Workforce</span>
                <span class="tab" onclick="switchTab(this, 'supplier')">Supplier</span>
                <span class="tab" onclick="switchTab(this, 'other')">Other</span>
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
            <div class="section-label">Report Statistics</div>
            <div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Total Reports</div>
                    <div class="kpi-value" id="totalReports">0</div>
                    <div class="kpi-sub">All reports</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Completed</div>
                    <div class="kpi-value" id="completedReports">0</div>
                    <div class="kpi-sub">Completed reports</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">In Progress</div>
                    <div class="kpi-value" id="inProgressReports">0</div>
                    <div class="kpi-sub">In progress</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Pending</div>
                    <div class="kpi-value" id="pendingReports">0</div>
                    <div class="kpi-sub">Pending reports</div>
                </div>
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
                        <th>Role</th>
                        <th>File Name</th>
                        <th>Date Uploaded</th>
                        <th>Uploaded By</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="reportTableBody">
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #888;">
                            <div style="font-size: 2rem; margin-bottom: 10px;">📄</div>
                            No reports uploaded yet.<br>
                            Click "Upload Report" to add your first report.
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper">
                        <div class="rows-info">
                Rows Displayed:
                <select id="rowsPerPage" onchange="changePageSize()">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span id="totalCount" class="pagination-total">Total: 0</span>
            </div>
            <div class="pagination-links" id="paginationLinks">
                <!-- Generated by JavaScript -->
            </div>
        </div>

    </main>

    <!-- ─── UPLOAD MODAL ─── -->
    <div id="uploadModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Upload Report</h2>
                <button class="modal-close" onclick="closeUploadModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Report Title <span class="required">*</span></label>
                    <input type="text" id="reportTitle" placeholder="e.g., Monthly Financial Summary">
                </div>
                <div class="form-group">
                    <label>Report Type <span class="required">*</span></label>
                    <select id="reportType">
                        <option value="project">Project Report</option>
                        <option value="finance">Financial Report</option>
                        <option value="budget">Budget Report</option>
                        <option value="expense">Expense Report</option>
                        <option value="inventory">Inventory Report</option>
                        <option value="workforce">Workforce Report</option>
                        <option value="supplier">Supplier Report</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Role <span class="required">*</span></label>
                    <select id="reportRole">
                        <option value="accounting">Accounting</option>
                        <option value="operations">Operations</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea id="reportDescription" rows="3" placeholder="Brief description of the report..."></textarea>
                </div>
                <div class="upload-area" id="uploadArea" onclick="document.getElementById('fileInput').click()">
                    <div class="upload-icon">📁</div>
                    <div class="upload-text">Click to upload or drag & drop</div>
                    <div class="upload-sub">Supported: PDF, Excel, Word, CSV (Max 10MB)</div>
                    <input type="file" id="fileInput" accept=".pdf,.xlsx,.xls,.doc,.docx,.csv" onchange="handleFileSelect(event)">
                </div>
                <div id="selectedFile" style="display: none; padding: 10px; background: #f5f5f5; border-radius: 8px; margin-top: 10px;">
                    <span id="fileName">file.pdf</span>
                    <span style="color: #888; font-size: 0.85rem;" id="fileSize">(0 KB)</span>
                </div>
                <div class="upload-progress" id="uploadProgress">
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                    <div class="progress-text" id="progressText">Uploading... 0%</div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeUploadModal()">Cancel</button>
                <button class="btn-save" onclick="uploadReport()" id="uploadBtn">Upload</button>
            </div>
        </div>
    </div>

    <script>
        // ─── CSRF TOKEN ───
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        // ─── STATE VARIABLES ───────────────────────────────────────────
        var reports = [];
        var filteredReports = [];
        var currentPage = 1;
        var pageSize = 25;
        var currentTab = 'all';
        var selectedFile = null;
        var reportIdCounter = 0;
        var isInitialLoad = true;

                // ─── NOTIFICATION FUNCTIONS ──────────────────────────────────
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        }

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

        // Click outside to close notifications
        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });

        // ─── FORMAT DATE FUNCTION ────────────────────────────────────
        function formatDate(dateString) {
            if (!dateString) return '—';
            
            // If it's already in YYYY-MM-DD format, return as is
            if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
                return dateString;
            }
            
            try {
                var date = new Date(dateString);
                if (isNaN(date.getTime())) {
                    return dateString;
                }
                var year = date.getFullYear();
                var month = String(date.getMonth() + 1).padStart(2, '0');
                var day = String(date.getDate()).padStart(2, '0');
                return year + '-' + month + '-' + day;
            } catch (e) {
                return dateString;
            }
        }

        // ─── UPLOAD MODAL FUNCTIONS ─────────────────────────────────
        function openUploadModal() {
            document.getElementById('uploadModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('reportTitle').value = '';
            document.getElementById('reportType').value = 'project';
            document.getElementById('reportRole').value = 'accounting';
            document.getElementById('reportDescription').value = '';
            document.getElementById('selectedFile').style.display = 'none';
            document.getElementById('uploadProgress').style.display = 'none';
            document.getElementById('uploadBtn').disabled = false;
            selectedFile = null;
            document.getElementById('fileInput').value = '';
        }

        function closeUploadModal() {
            document.getElementById('uploadModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function handleFileSelect(event) {
            var file = event.target.files[0];
            if (file) {
                var maxSize = 10 * 1024 * 1024;
                if (file.size > maxSize) {
                    showError('File size exceeds 10MB limit.');
                    return;
                }
                selectedFile = file;
                document.getElementById('selectedFile').style.display = 'block';
                document.getElementById('fileName').textContent = file.name;
                document.getElementById('fileSize').textContent = '(' + formatFileSize(file.size) + ')';
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            var k = 1024;
            var sizes = ['Bytes', 'KB', 'MB', 'GB'];
            var i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // ─── DRAG AND DROP ───────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            var uploadArea = document.getElementById('uploadArea');
            
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
                var files = e.dataTransfer.files;
                if (files.length > 0) {
                    var file = files[0];
                    var maxSize = 10 * 1024 * 1024;
                    if (file.size > maxSize) {
                        showError('File size exceeds 10MB limit.');
                        return;
                    }
                    selectedFile = file;
                    document.getElementById('selectedFile').style.display = 'block';
                    document.getElementById('fileName').textContent = file.name;
                    document.getElementById('fileSize').textContent = '(' + formatFileSize(file.size) + ')';
                    document.getElementById('fileInput').files = files;
                }
            });
        });

        // ─── UPLOAD REPORT ──────────────────────────────────────────
        function uploadReport() {
            var title = document.getElementById('reportTitle').value.trim();
            var type = document.getElementById('reportType').value;
            var role = document.getElementById('reportRole').value;
            var description = document.getElementById('reportDescription').value.trim();
            
            if (!title) {
                showError('Please enter a report title.');
                return;
            }
            if (!selectedFile) {
                showError('Please select a file to upload.');
                return;
            }

            var formData = new FormData();
            formData.append('title', title);
            formData.append('type', type);
            formData.append('role', role);
            formData.append('description', description);
            formData.append('file', selectedFile);
            formData.append('_token', csrfToken);

            var uploadBtn = document.getElementById('uploadBtn');
            uploadBtn.disabled = true;
            uploadBtn.textContent = 'Uploading...';

            var progressDiv = document.getElementById('uploadProgress');
            progressDiv.style.display = 'block';
            var progressFill = document.getElementById('progressFill');
            var progressText = document.getElementById('progressText');

            var progress = 0;
            var interval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                progressFill.style.width = progress + '%';
                progressText.textContent = 'Uploading... ' + Math.round(progress) + '%';
            }, 200);

            fetch('/api/reports/upload', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json' // Important: Tell server to return JSON
                },
                body: formData
            })
            .then(function(response) {
                // Check if response is JSON
                var contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(function(text) {
                        throw new Error('Server returned non-JSON response: ' + text.substring(0, 200));
                    });
                }
                return response.json();
            })
            .then(function(data) {
                clearInterval(interval);
                progressFill.style.width = '100%';
                progressText.textContent = 'Upload complete!';
                
                if (data.success) {
                    var newReport = {
                        id: data.report?.report_id || 'RPT-' + String(++reportIdCounter).padStart(3, '0'),
                        title: title,
                        type: type,
                        role: role,
                        description: description,
                        file_name: selectedFile.name,
                        file_size: selectedFile.size,
                        date_uploaded: new Date().toISOString().split('T')[0],
                        uploaded_by: '{{ auth()->user()->name }}',
                        status: 'Completed',
                        file_path: data.report?.file_path || null
                    };
                    
                    reports.unshift(newReport);
                    filterReports();
                    
                    setTimeout(function() {
                        closeUploadModal();
                        showSuccess('Report "' + title + '" uploaded successfully!');
                        uploadBtn.disabled = false;
                        uploadBtn.textContent = 'Upload';
                        progressDiv.style.display = 'none';
                    }, 500);
                } else {
                    throw new Error(data.message || 'Upload failed.');
                }
            })
            .catch(function(error) {
                clearInterval(interval);
                showError('Upload failed: ' + error.message);
                uploadBtn.disabled = false;
                uploadBtn.textContent = 'Upload';
                progressDiv.style.display = 'none';
                console.error('Upload error:', error);
            });
        }

        // ─── REPORT MANAGEMENT ──────────────────────────────────────
        function getStatusBadge(status) {
            var badges = {
                'Completed': '<span class="status-badge completed"><span class="dot"></span> Completed</span>',
                'In Progress': '<span class="status-badge in-progress"><span class="dot"></span> In Progress</span>',
                'Pending': '<span class="status-badge pending"><span class="dot"></span> Pending</span>'
            };
            return badges[status] || badges['Pending'];
        }

        function getTypeLabel(type) {
            var types = {
                'project': 'Project',
                'finance': 'Finance',
                'budget': 'Budget',
                'expense': 'Expense',
                'inventory': 'Inventory',
                'workforce': 'Workforce',
                'supplier': 'Supplier',
                'other': 'Other'
            };
            return types[type] || type;
        }

        function getTypeClass(type) {
            var classes = {
                'project': 'project',
                'finance': 'finance',
                'budget': 'budget',
                'expense': 'expense',
                'inventory': 'inventory',
                'workforce': 'workforce',
                'supplier': 'supplier',
                'other': 'other'
            };
            return classes[type] || 'other';
        }

        function getRoleLabel(role) {
            var roles = {
                'accounting': 'Accounting',
                'operations': 'Operations',
                'admin': 'Admin'
            };
            return roles[role] || role;
        }

        function getRoleClass(role) {
            var classes = {
                'accounting': 'accounting',
                'operations': 'operations',
                'admin': 'admin'
            };
            return classes[role] || '';
        }

        function getFileIcon(filename) {
            var ext = filename.split('.').pop().toLowerCase();
            var icons = {
                'pdf': '📄',
                'xlsx': '📊',
                'xls': '📊',
                'doc': '📝',
                'docx': '📝',
                'csv': '📋'
            };
            return icons[ext] || '📁';
        }

        // ─── RENDER REPORTS ──────────────────────────────────────────
        function renderReports() {
            var tbody = document.getElementById('reportTableBody');
            var start = (currentPage - 1) * pageSize;
            var end = Math.min(start + pageSize, filteredReports.length);
            var pageData = filteredReports.slice(start, end);
            
            if (!pageData.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px; color: #888;">
                            <div style="font-size: 2rem; margin-bottom: 10px;">📄</div>
                            No reports found.<br>
                            Click "Upload Report" to add your first report.
                        </td>
                    </tr>
                `;
                updatePagination();
                updateRowsInfo();
                return;
            }
            
            tbody.innerHTML = '';
            pageData.forEach(function(report) {
                var row = document.createElement('tr');
                var statusBadge = getStatusBadge(report.status);
                var typeLabel = getTypeLabel(report.type);
                var typeClass = getTypeClass(report.type);
                var roleLabel = getRoleLabel(report.role);
                var roleClass = getRoleClass(report.role);
                var fileIcon = getFileIcon(report.file_name);
                var formattedDate = formatDate(report.date_uploaded);
                
                // Use report_id for the ID display
                var reportId = report.report_id || report.id;
                
                row.innerHTML = `
                    <td><strong>#${reportId}</strong></td>
                    <td>${report.title}</td>
                    <td><span class="type-badge ${typeClass}">${typeLabel}</span></td>
                    <td><span class="role-badge ${roleClass}">${roleLabel}</span></td>
                    <td style="display: flex; align-items: center; gap: 6px;">
                        ${fileIcon} ${report.file_name}
                    </td>
                    <td>${formattedDate}</td>
                    <td>${report.uploaded_by}</td>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="action-cell">
                            <button class="download-btn" onclick="downloadReport('${reportId}')" title="Download">
                                <img src="{{ asset('images/download.jpg') }}" alt="Download" style="width: 18px; height: 18px;">
                            </button>
                            <button class="view-btn" onclick="viewReport('${reportId}')" title="View">
                                <img src="{{ asset('images/view.jpg') }}" alt="View" style="width: 18px; height: 18px;">
                            </button>
                            <button class="delete-btn" onclick="deleteReport('${reportId}')" title="Delete">
                                <img src="{{ asset('images/delete.jpg') }}" alt="Delete" style="width: 18px; height: 18px;">
                            </button>
                        </div>
                    </td>
                `;
                tbody.appendChild(row);
            });
            
            updatePagination();
            updateRowsInfo();
            updateKPIs();
            if (window.refreshTableScrollFade) window.refreshTableScrollFade();
        }

        // ─── PAGINATION ──────────────────────────────────────────────
        function updatePagination() {
            var container = document.getElementById('paginationLinks');
            var total = filteredReports.length;
            var totalPages = Math.ceil(total / pageSize);
            var current = currentPage;
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            var html = '';
            html += '<a href="#" onclick="goToPage(' + (current - 1) + '); return false;" class="' + (current <= 1 ? 'disabled' : '') + '">«</a>';
            
            if (totalPages <= 7) {
                for (var i = 1; i <= totalPages; i++) {
                    html += '<a href="#" onclick="goToPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            } else {
                for (var i = 1; i <= 3; i++) {
                    html += '<a href="#" onclick="goToPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
                if (current > 4) {
                    html += '<span class="dots">...</span>';
                }
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                for (var i = startPage; i <= endPage; i++) {
                    html += '<a href="#" onclick="goToPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
                if (current < totalPages - 3) {
                    html += '<span class="dots">...</span>';
                }
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    if (i > 3) {
                        html += '<a href="#" onclick="goToPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                    }
                }
            }
            
            html += '<a href="#" onclick="goToPage(' + (current + 1) + '); return false;" class="' + (current >= totalPages ? 'disabled' : '') + '">»</a>';
            container.innerHTML = html;
        }

        function goToPage(page) {
            var total = filteredReports.length;
            var totalPages = Math.ceil(total / pageSize);
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            renderReports();
        }

        function changePageSize() {
            var select = document.getElementById('rowsPerPage');
            pageSize = parseInt(select.value) || 25;
            currentPage = 1;
            renderReports();
        }

                function updateRowsInfo() {
            var total = filteredReports.length;
            document.getElementById('totalCount').textContent = 'Total: ' + total;
        }

        // ─── FILTER FUNCTIONS ────────────────────────────────────────
        function applyFilters() {
            filterReports();
            showSuccess('Filters applied!');
        }

        function filterReports() {
            var searchTerm = document.getElementById('reportSearch').value.toLowerCase().trim();
            var typeFilter = document.getElementById('reportTypeFilter').value;
            var roleFilter = document.getElementById('roleFilter') ? document.getElementById('roleFilter').value : 'all';
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;
            
            filteredReports = reports.filter(function(report) {
                var matchesSearch = true;
                if (searchTerm) {
                    matchesSearch = report.title.toLowerCase().includes(searchTerm) ||
                                   report.type.toLowerCase().includes(searchTerm) ||
                                   (report.description && report.description.toLowerCase().includes(searchTerm));
                }
                
                var matchesType = true;
                if (typeFilter !== 'all') {
                    matchesType = report.type === typeFilter;
                }
                
                var matchesRole = true;
                if (roleFilter && roleFilter !== 'all') {
                    matchesRole = report.role === roleFilter;
                }
                
                var matchesDate = true;
                if (startDate && endDate) {
                    var reportDate = report.date_uploaded ? report.date_uploaded.split('T')[0] : '';
                    matchesDate = reportDate >= startDate && reportDate <= endDate;
                }
                
                var matchesTab = true;
                if (currentTab !== 'all') {
                    matchesTab = report.type === currentTab;
                }
                
                return matchesSearch && matchesType && matchesRole && matchesDate && matchesTab;
            });
            
            currentPage = 1;
            renderReports();
        }

        // ─── UPDATE KPIs ─────────────────────────────────────────────
        function updateKPIs() {
            var total = reports.length;
            var completed = reports.filter(function(r) { return r.status === 'Completed'; }).length;
            var inProgress = reports.filter(function(r) { return r.status === 'In Progress'; }).length;
            var pending = reports.filter(function(r) { return r.status === 'Pending'; }).length;
            
            document.getElementById('totalReports').textContent = total;
            document.getElementById('completedReports').textContent = completed;
            document.getElementById('inProgressReports').textContent = inProgress;
            document.getElementById('pendingReports').textContent = pending;
        }

                // ─── SYNC TAB FROM DROPDOWN ────────────────────────────────
        function syncTabFromDropdown() {
            var dropdown = document.getElementById('reportTypeFilter');
            var selectedType = dropdown.value;
            var tabs = document.querySelectorAll('.report-tabs .tab');
            tabs.forEach(function(tab) { tab.classList.remove('active'); });
            currentTab = selectedType;
            if (selectedType === 'all') {
                tabs[0].classList.add('active');
                return;
            }
            tabs.forEach(function(tab) {
                var onclickAttr = tab.getAttribute('onclick') || '';
                if (onclickAttr.indexOf("'" + selectedType + "'") !== -1) {
                    tab.classList.add('active');
                }
            });
        }

        // ─── TAB SWITCH ──────────────────────────────────────────────
        function switchTab(el, type) {
            var tabs = document.querySelectorAll('.report-tabs .tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            el.classList.add('active');
            currentTab = type;
            
                        var dropdown = document.getElementById('reportTypeFilter');
            if (type !== 'all') {
                var typeMap = {
                    'project': 'project',
                    'finance': 'finance',
                    'budget': 'budget',
                    'expense': 'expense',
                    'inventory': 'inventory',
                    'workforce': 'workforce',
                    'supplier': 'supplier',
                    'other': 'other'
                };
                dropdown.value = typeMap[type] || 'all';
            } else {
                dropdown.value = 'all';
            }
            
            filterReports();
        }

        // ─── REPORT ACTIONS ──────────────────────────────────────────
                function downloadReport(id) {
            var report = reports.find(function(r) {
                var reportId = r.report_id || r.id;
                return reportId === id;
            });
            if (report && report.file_path) {
                showSuccess('Downloading "' + report.title + '"...');
                window.open('/api/reports/download/' + id, '_blank');
            } else {
                showError('File not available for download.');
            }
        }

                // ─── VIEW REPORT ──────────────────────────────────────────────────
        function viewReport(id) {
            var report = reports.find(function(r) { 
                var reportId = r.report_id || r.id;
                return reportId === id; 
            });
            if (!report) {
                showError('Report not found.');
                return;
            }

            document.getElementById('detailReportId').textContent = '#' + (report.report_id || report.id);
            document.getElementById('detailTitle').textContent = report.title;
            document.getElementById('detailType').textContent = getTypeLabel(report.type);
            document.getElementById('detailRole').textContent = getRoleLabel(report.role);
            document.getElementById('detailFileName').textContent = report.file_name;
            document.getElementById('detailDate').textContent = formatDate(report.date_uploaded);
            document.getElementById('detailUploadedBy').textContent = report.uploaded_by;
            document.getElementById('detailStatus').innerHTML = getStatusBadge(report.status);
            document.getElementById('detailDescription').textContent = report.description || 'No description provided.';

            document.getElementById('reportDetailModal').dataset.reportId = id;

            document.getElementById('reportDetailModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeReportDetailModal() {
            document.getElementById('reportDetailModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function openReportFile() {
            var id = document.getElementById('reportDetailModal').dataset.reportId;
            if (id) {
                var url = '/api/reports/download/' + id + '?inline=true';
                window.open(url, '_blank');
            }
        }

        document.getElementById('reportDetailModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeReportDetailModal();
            }
        });

        // ─── DELETE CONFIRMATION MODAL FUNCTIONS ──────────────────────────
        var deleteReportId = null;

        function openDeleteModal(message, reportId) {
            document.getElementById('deleteConfirmMessage').textContent = message || 'Are you sure you want to permanently delete this report?';
            deleteReportId = reportId;
            document.getElementById('deleteConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            deleteReportId = null;
        }

        function confirmDeleteReport() {
            if (deleteReportId) {
                executeDeleteReport(deleteReportId);
            }
            closeDeleteModal();
        }

        // Close modal on backdrop click
        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // ─── DELETE REPORT ──────────────────────────────────────────────────
        function deleteReport(id) {
            openDeleteModal('Are you sure you want to permanently delete this report? This action cannot be undone.', id);
        }

        function executeDeleteReport(id) {
            fetch('/api/reports/' + id, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.message || 'Delete failed.');
                    });
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    reports = reports.filter(function(r) { 
                        var reportId = r.report_id || r.id;
                        return reportId !== id; 
                    });
                    filterReports();
                    showSuccess(data.message || 'Report deleted successfully!');
                } else {
                    showError(data.message || 'Failed to delete report.');
                }
            })
            .catch(function(error) {
                // If API fails, remove from local data only
                reports = reports.filter(function(r) { 
                    var reportId = r.report_id || r.id;
                    return reportId !== id; 
                });
                filterReports();
                showSuccess('Report removed from view (local only).');
                console.warn('API delete failed:', error.message);
            });
        }

        // ─── REFRESH REPORTS ─────────────────────────────────────────
        function refreshReports() {
            showSuccess('Refreshing reports...');
            fetchReports();
        }

        // ─── EXPORT REPORTS ──────────────────────────────────────────
        function exportReports() {
            if (filteredReports.length === 0) {
                showError('No reports to export.');
                return;
            }
            
            var csv = 'ID,Title,Type,Role,File Name,Date Uploaded,Uploaded By,Status\n';
            filteredReports.forEach(function(r) {
                var formattedDate = formatDate(r.date_uploaded);
                csv += '#' + r.id + ',' + r.title + ',' + getTypeLabel(r.type) + ',' + 
                       getRoleLabel(r.role) + ',' + r.file_name + ',' + 
                       formattedDate + ',' + r.uploaded_by + ',' + r.status + '\n';
            });
            
            var blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'reports_export_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            showSuccess('Export complete!');
        }

                // ─── FETCH REPORTS FROM SERVER ──────────────────────────────
        function fetchReports() {
            var loadingBody = document.getElementById('reportTableBody');
            if (loadingBody) {
                loadingBody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px; color: #888;">Loading reports...</td></tr>';
            }
            fetch('/api/reports', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Failed to fetch reports.');
                }
                return response.json();
            })
            .then(function(data) {
                reports = data || [];
                reportIdCounter = reports.reduce(function(max, r) {
                    var idStr = String(r.id).replace('RPT-', '');
                    var num = parseInt(idStr);
                    return num > max ? num : max;
                }, 0);
                filterReports();
                updateKPIs();
            })
            .catch(function(error) {
                console.warn('Using mock data:', error.message);
                loadMockData();
            });
        }

        // ─── MOCK DATA ──────────────────────────────────────────────
        function loadMockData() {
            reports = [
                {
                    id: 'RPT-001',
                    title: 'Project Completion Report - Q2 2026',
                    type: 'project',
                    role: 'operations',
                    description: 'Project completion summary',
                    file_name: 'project_completion_q2.pdf',
                    file_size: 2457600,
                    date_uploaded: '2026-06-30',
                    uploaded_by: 'Admin User',
                    status: 'Completed',
                    file_path: '/uploads/reports/project_completion_q2.pdf'
                },
                {
                    id: 'RPT-002',
                    title: 'Financial Summary - June 2026',
                    type: 'finance',
                    role: 'accounting',
                    description: 'Monthly financial summary',
                    file_name: 'financial_summary_jun2026.xlsx',
                    file_size: 1024000,
                    date_uploaded: '2026-06-29',
                    uploaded_by: 'Admin User',
                    status: 'Completed',
                    file_path: '/uploads/reports/financial_summary_jun2026.xlsx'
                },
                {
                    id: 'RPT-003',
                    title: 'Inventory Stock Report',
                    type: 'inventory',
                    role: 'operations',
                    description: 'Current inventory levels',
                    file_name: 'inventory_stock_report.csv',
                    file_size: 512000,
                    date_uploaded: '2026-06-28',
                    uploaded_by: 'Operations Team',
                    status: 'In Progress',
                    file_path: '/uploads/reports/inventory_stock_report.csv'
                },
                {
                    id: 'RPT-004',
                    title: 'Annual Budget Projection',
                    type: 'budget',
                    role: 'accounting',
                    description: '2026 annual budget projection',
                    file_name: 'annual_budget_projection.pdf',
                    file_size: 3072000,
                    date_uploaded: '2026-06-27',
                    uploaded_by: 'Admin User',
                    status: 'Pending',
                    file_path: '/uploads/reports/annual_budget_projection.pdf'
                },
                {
                    id: 'RPT-005',
                    title: 'Supplier Performance Review',
                    type: 'supplier',
                    role: 'admin',
                    description: 'Supplier performance analysis',
                    file_name: 'supplier_performance.xlsx',
                    file_size: 819200,
                    date_uploaded: '2026-06-26',
                    uploaded_by: 'Admin User',
                    status: 'Completed',
                    file_path: '/uploads/reports/supplier_performance.xlsx'
                }
            ];
            reportIdCounter = 5;
            filterReports();
            updateKPIs();
        }

        // ─── CLOSE MODAL ON BACKDROP ────────────────────────────────
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUploadModal();
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
            fetchReports();
            fetchNotifBadge();
        });
    </script>
    <script src="{{ asset('js/table-scroll-fade.js') }}"></script>

</body>
</html>
