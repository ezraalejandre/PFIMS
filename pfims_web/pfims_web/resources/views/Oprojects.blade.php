<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Projects - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Oprojects.css') }}">
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
                <li class="active"><a href="{{ url('/oprojects') }}">PROJECTS</a></li>
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
                <tbody>
                    <!-- Skyline Tower -->
                    <tr onclick="openUpdateModal(
                        'Skyline Tower',
                        'Mega Reality Corp.',
                        '₱15,000,000',
                        'Jan 15, 2025',
                        'Dec 30, 2025',
                        '—',
                        '11.5 mo',
                        'Structure',
                        'At Risk',
                        72,
                        'A. Santos',
                        50,
                        '2025-01-15',
                        '2025-12-30'
                    )">
                        <td><strong>Skyline Tower</strong></td>
                        <td>Mega Reality Corp.</td>
                        <td>₱15,000,000</td>
                        <td>Jan 15, 2025</td>
                        <td>Dec 30, 2025</td>
                        <td>—</td>
                        <td>11.5 mo</td>
                        <td><span class="phase-badge">Structure</span></td>
                        <td>
                            <div class="progress-cell">
                                <div class="mini-bar"><div class="fill" style="width:72%;"></div></div>
                            </div>
                        </td>
                        <td><span class="status-badge at-risk"><span class="dot"></span> At Risk</span></td>
                    </tr>
                    <!-- Harbor Bridge Annex -->
                    <tr onclick="openUpdateModal(
                        'Harbor Bridge Annex',
                        'City Gov — NCR',
                        '₱8,500,000',
                        'Mar 1, 2025',
                        'Aug 15, 2025',
                        '—',
                        '5.5 mo',
                        'Finishing',
                        'On Track',
                        91,
                        'B. Reyes',
                        30,
                        '2025-03-01',
                        '2025-08-15'
                    )">
                        <td><strong>Harbor Bridge Annex</strong></td>
                        <td>City Gov — NCR</td>
                        <td>₱8,500,000</td>
                        <td>Mar 1, 2025</td>
                        <td>Aug 15, 2025</td>
                        <td>—</td>
                        <td>5.5 mo</td>
                        <td><span class="phase-badge">Finishing</span></td>
                        <td>
                            <div class="progress-cell">
                                <div class="mini-bar"><div class="fill" style="width:91%;"></div></div>
                            </div>
                        </td>
                        <td><span class="status-badge on-track"><span class="dot"></span> On Track</span></td>
                    </tr>
                    <!-- Green Hills Residences -->
                    <tr onclick="openUpdateModal(
                        'Green Hills Residences',
                        'Verde Homes Inc.',
                        '₱12,200,000',
                        'Nov 10, 2024',
                        'May 20, 2025',
                        '—',
                        '6.3 mo',
                        'Complete',
                        'Completed',
                        100,
                        'C. Mendoza',
                        40,
                        '2024-11-10',
                        '2025-05-20'
                    )">
                        <td><strong>Green Hills Residences</strong></td>
                        <td>Verde Homes Inc.</td>
                        <td>₱12,200,000</td>
                        <td>Nov 10, 2024</td>
                        <td>May 20, 2025</td>
                        <td>—</td>
                        <td>6.3 mo</td>
                        <td><span class="phase-badge">Complete</span></td>
                        <td>
                            <div class="progress-cell">
                                <div class="mini-bar"><div class="fill" style="width:100%;"></div></div>
                            </div>
                        </td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <!-- Eastwood Mall -->
                    <tr onclick="openUpdateModal(
                        'Eastwood Mall',
                        'LKP Commercial',
                        '₱20,000,000',
                        'Feb 22, 2025',
                        'Jul 30, 2025',
                        '—',
                        '5.2 mo',
                        'Foundation',
                        'At Risk',
                        55,
                        'D. Cruz',
                        60,
                        '2025-02-22',
                        '2025-07-30'
                    )">
                        <td><strong>Eastwood Mall</strong></td>
                        <td>LKP Commercial</td>
                        <td>₱20,000,000</td>
                        <td>Feb 22, 2025</td>
                        <td>Jul 30, 2025</td>
                        <td>—</td>
                        <td>5.2 mo</td>
                        <td><span class="phase-badge">Foundation</span></td>
                        <td>
                            <div class="progress-cell">
                                <div class="mini-bar"><div class="fill" style="width:55%;"></div></div>
                            </div>
                        </td>
                        <td><span class="status-badge at-risk"><span class="dot"></span> At Risk</span></td>
                    </tr>
                    <!-- BPO Hub Bldg. C -->
                    <tr onclick="openUpdateModal(
                        'BPO Hub Bldg. C',
                        'TechZone Holdings',
                        '₱6,800,000',
                        'Apr 5, 2025',
                        'Jan 5, 2026',
                        '—',
                        '9 mo',
                        'Planning',
                        'At Risk',
                        28,
                        'A. Santos',
                        25,
                        '2025-04-05',
                        '2026-01-05'
                    )">
                        <td><strong>BPO Hub Bldg. C</strong></td>
                        <td>TechZone Holdings</td>
                        <td>₱6,800,000</td>
                        <td>Apr 5, 2025</td>
                        <td>Jan 5, 2026</td>
                        <td>—</td>
                        <td>9 mo</td>
                        <td><span class="phase-badge">Planning</span></td>
                        <td>
                            <div class="progress-cell">
                                <div class="mini-bar"><div class="fill" style="width:28%;"></div></div>
                            </div>
                        </td>
                        <td><span class="status-badge at-risk"><span class="dot"></span> At Risk</span></td>
                    </tr>
                    <!-- North Rail Station -->
                    <tr onclick="openUpdateModal(
                        'North Rail Station',
                        'DOTR — PH',
                        '₱45,000,000',
                        'Sep 1, 2024',
                        'Mar 1, 2025',
                        '—',
                        '6 mo',
                        'Structure',
                        'Delayed',
                        44,
                        'E. Villanueva',
                        80,
                        '2024-09-01',
                        '2025-03-01'
                    )">
                        <td><strong>North Rail Station</strong></td>
                        <td>DOTR — PH</td>
                        <td>₱45,000,000</td>
                        <td>Sep 1, 2024</td>
                        <td>Mar 1, 2025</td>
                        <td>—</td>
                        <td>6 mo</td>
                        <td><span class="phase-badge">Structure</span></td>
                        <td>
                            <div class="progress-cell">
                                <div class="mini-bar"><div class="fill" style="width:44%;"></div></div>
                            </div>
                        </td>
                        <td><span class="status-badge delayed"><span class="dot"></span> Delayed</span></td>
                    </tr>
                    <!-- Pasig River Walk -->
                    <tr onclick="openUpdateModal(
                        'Pasig River Walk',
                        'Pasig City LGU',
                        '₱3,200,000',
                        'Jan 2, 2025',
                        'Jun 30, 2025',
                        '—',
                        '6 mo',
                        'Finishing',
                        'On Track',
                        80,
                        'C. Mendoza',
                        35,
                        '2025-01-02',
                        '2025-06-30'
                    )">
                        <td><strong>Pasig River Walk</strong></td>
                        <td>Pasig City LGU</td>
                        <td>₱3,200,000</td>
                        <td>Jan 2, 2025</td>
                        <td>Jun 30, 2025</td>
                        <td>—</td>
                        <td>6 mo</td>
                        <td><span class="phase-badge">Finishing</span></td>
                        <td>
                            <div class="progress-cell">
                                <div class="mini-bar"><div class="fill" style="width:80%;"></div></div>
                            </div>
                        </td>
                        <td><span class="status-badge on-track"><span class="dot"></span> On Track</span></td>
                    </tr>
                    <!-- Metro Interchange -->
                    <tr onclick="openUpdateModal(
                        'Metro Interchange',
                        'MMDA',
                        '₱28,000,000',
                        'Oct 15, 2024',
                        'Apr 15, 2025',
                        '—',
                        '6 mo',
                        'Foundation',
                        'Delayed',
                        38,
                        'B. Reyes',
                        70,
                        '2024-10-15',
                        '2025-04-15'
                    )">
                        <td><strong>Metro Interchange</strong></td>
                        <td>MMDA</td>
                        <td>₱28,000,000</td>
                        <td>Oct 15, 2024</td>
                        <td>Apr 15, 2025</td>
                        <td>—</td>
                        <td>6 mo</td>
                        <td><span class="phase-badge">Foundation</span></td>
                        <td>
                            <div class="progress-cell">
                                <div class="mini-bar"><div class="fill" style="width:38%;"></div></div>
                            </div>
                        </td>
                        <td><span class="status-badge delayed"><span class="dot"></span> Delayed</span></td>
                    </tr>
                    <!-- Laguna Warehouse Complex -->
                    <tr onclick="openUpdateModal(
                        'Laguna Warehouse Complex',
                        'STAR Logistics',
                        '₱9,500,000',
                        'Mar 18, 2025',
                        'Sep 18, 2025',
                        '—',
                        '6 mo',
                        'Planning',
                        'At Risk',
                        20,
                        'A. Santos',
                        20,
                        '2025-03-18',
                        '2025-09-18'
                    )">
                        <td><strong>Laguna Warehouse Complex</strong></td>
                        <td>STAR Logistics</td>
                        <td>₱9,500,000</td>
                        <td>Mar 18, 2025</td>
                        <td>Sep 18, 2025</td>
                        <td>—</td>
                        <td>6 mo</td>
                        <td><span class="phase-badge">Planning</span></td>
                        <td>
                            <div class="progress-cell">
                                <div class="mini-bar"><div class="fill" style="width:20%;"></div></div>
                            </div>
                        </td>
                        <td><span class="status-badge at-risk"><span class="dot"></span> At Risk</span></td>
                    </tr>
                    <!-- Alabang Medical -->
                    <tr onclick="openUpdateModal(
                        'Alabang Medical',
                        'HealthFirst PH',
                        '₱18,000,000',
                        'Dec 1, 2024',
                        'Nov 30, 2025',
                        '—',
                        '12 mo',
                        'Structure',
                        'On Track',
                        60,
                        'D. Cruz',
                        90,
                        '2024-12-01',
                        '2025-11-30'
                    )">
                        <td><strong>Alabang Medical</strong></td>
                        <td>HealthFirst PH</td>
                        <td>₱18,000,000</td>
                        <td>Dec 1, 2024</td>
                        <td>Nov 30, 2025</td>
                        <td>—</td>
                        <td>12 mo</td>
                        <td><span class="phase-badge">Structure</span></td>
                        <td>
                            <div class="progress-cell">
                                <div class="mini-bar"><div class="fill" style="width:60%;"></div></div>
                            </div>
                        </td>
                        <td><span class="status-badge on-track"><span class="dot"></span> On Track</span></td>
                    </tr>
                </tbody>
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

    <!-- ─── UPDATE PROJECT OVERVIEW MODAL ─── -->
    <div id="updateModal" class="modal-overlay modal-update">
        <div class="modal-container">
            <div class="modal-header">
                <div>
                    <h2 id="updateProjectName" style="margin-bottom: 2px;">Skyline Tower</h2>
                    <span class="subtitle" id="updateClientName">Mega Realty Corp</span>
                </div>
                <button class="modal-close" onclick="closeUpdateModal()">×</button>
            </div>

            <div class="project-details-grid">
                <div class="detail-item">
                    <label>Budget</label>
                    <span id="updateBudget">₱15,000,000</span>
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

    <!-- ─── EDIT PROJECT MODAL ─── -->
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
                    <div class="form-group">
                        <label>Duration</label>
                        <input type="text" id="editDuration" placeholder="e.g. 11.5 mo">
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

        function saveProject() {
            closeModal();
            showSuccess('Project saved successfully!');
        }

        document.getElementById('projectModal').addEventListener('click', function(e) {
            if (e.target === this) { closeModal(); }
        });

        // ─── UPDATE PROJECT OVERVIEW MODAL ───
        function openUpdateModal(
            projectName, clientName, budget, startDate, estEndDate, actualEndDate,
            duration, phase, status, progress, manager, workers, startDateRaw, endDateRaw
        ) {
            currentEditData = {
                name: projectName,
                client: clientName,
                manager: manager || '',
                workers: workers || '',
                startDate: startDateRaw || '',
                endDate: endDateRaw || '',
                phase: phase,
                startDateDisplay: startDate,
                estEndDateDisplay: estEndDate,
                actualEndDateDisplay: actualEndDate || '—',
                duration: duration
            };

            document.getElementById('updateProjectName').textContent = projectName;
            document.getElementById('updateClientName').textContent = clientName;
            document.getElementById('updateBudget').textContent = budget;
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
            if (!currentEditData) {
                showError('No project selected to delete.');
                return;
            }
            openDeleteModal('Are you sure you want to permanently delete "' + currentEditData.name + '"?', function() {
                // Find and remove the row from the table
                var rows = document.querySelectorAll('.table-wrapper tbody tr');
                rows.forEach(function(row) {
                    var nameCell = row.querySelector('td:first-child strong');
                    if (nameCell && nameCell.textContent === currentEditData.name) {
                        row.remove();
                    }
                });
                closeUpdateModal();
                showSuccess('Project "' + currentEditData.name + '" deleted successfully!');
                currentEditData = null;
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
            document.getElementById('editActualEndDate').value = currentEditData.actualEndDateDisplay !== '—' ? currentEditData.actualEndDateDisplay : '';
            document.getElementById('editDuration').value = currentEditData.duration || '';

            closeUpdateModal();
            document.getElementById('editProjectModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditProjectModal() {
            document.getElementById('editProjectModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveEditProject() {
            var name = document.getElementById('editProjectOriginalName').value;
            var phase = document.getElementById('editPhase').value;
            var start = document.getElementById('editStartDate').value;
            var estEnd = document.getElementById('editEstEndDate').value;
            var actualEnd = document.getElementById('editActualEndDate').value;
            var duration = document.getElementById('editDuration').value.trim();

            if (!start || !estEnd || !duration) {
                showError('Please fill in all required fields (Start Date, Estimated End Date, Duration).');
                return;
            }

            closeEditProjectModal();
            showSuccess('Project "' + name + '" updated successfully!');
            console.log('Updated project:', { name, phase, start, estEnd, actualEnd, duration });
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
    </script>

</body>
</html>