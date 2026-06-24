<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Tracking - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/projtracking.css') }}">
</head>
<body>

    <!-- ─── SUCCESS NOTIFICATION ─── -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span>Project created successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    @include('partials.header')

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

        <!-- Table -->
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
                        <th>% Complete</th>
                        <th>Phase</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr onclick="openUpdateModal('Skyline Tower', 'Mega Reality Corp.', 72)">
                        <td><strong>Skyline Tower</strong></td>
                        <td>Mega Reality Corp.</td>
                        <td>₱15,000,000</td>
                        <td>Jan 15, 2025</td>
                        <td>Dec 30, 2025</td>
                        <td>—</td>
                        <td>11.5 mo</td>
                        <td>
                            <div class="progress-cell">
                                <span class="percent">72%</span>
                                <div class="mini-bar"><div class="fill" style="width:72%;"></div></div>
                            </div>
                        </td>
                        <td><span class="phase-badge">Structure</span></td>
                        <td><span class="status-badge at-risk"><span class="dot"></span> At Risk</span></td>
                    </tr>
                    <tr onclick="openUpdateModal('Harbor Bridge Annex', 'City Gov — NCR', 91)">
                        <td><strong>Harbor Bridge Annex</strong></td>
                        <td>City Gov — NCR</td>
                        <td>₱8,500,000</td>
                        <td>Mar 1, 2025</td>
                        <td>Aug 15, 2025</td>
                        <td>—</td>
                        <td>5.5 mo</td>
                        <td>
                            <div class="progress-cell">
                                <span class="percent">91%</span>
                                <div class="mini-bar"><div class="fill" style="width:91%;"></div></div>
                            </div>
                        </td>
                        <td><span class="phase-badge">Finishing</span></td>
                        <td><span class="status-badge on-track"><span class="dot"></span> On Track</span></td>
                    </tr>
                    <tr onclick="openUpdateModal('Green Hills Residences', 'Verde Homes Inc.', 100)">
                        <td><strong>Green Hills Residences</strong></td>
                        <td>Verde Homes Inc.</td>
                        <td>₱12,200,000</td>
                        <td>Nov 10, 2024</td>
                        <td>May 20, 2025</td>
                        <td>—</td>
                        <td>6.3 mo</td>
                        <td>
                            <div class="progress-cell">
                                <span class="percent">100%</span>
                                <div class="mini-bar"><div class="fill" style="width:100%;"></div></div>
                            </div>
                        </td>
                        <td><span class="phase-badge">Complete</span></td>
                        <td><span class="status-badge completed"><span class="dot"></span> Completed</span></td>
                    </tr>
                    <tr onclick="openUpdateModal('Eastwood Mall', 'LKP Commercial', 55)">
                        <td><strong>Eastwood Mall</strong></td>
                        <td>LKP Commercial</td>
                        <td>₱20,000,000</td>
                        <td>Feb 22, 2025</td>
                        <td>Jul 30, 2025</td>
                        <td>—</td>
                        <td>5.2 mo</td>
                        <td>
                            <div class="progress-cell">
                                <span class="percent">55%</span>
                                <div class="mini-bar"><div class="fill" style="width:55%;"></div></div>
                            </div>
                        </td>
                        <td><span class="phase-badge">Foundation</span></td>
                        <td><span class="status-badge at-risk"><span class="dot"></span> At Risk</span></td>
                    </tr>
                    <tr onclick="openUpdateModal('BPO Hub Bldg. C', 'TechZone Holdings', 28)">
                        <td><strong>BPO Hub Bldg. C</strong></td>
                        <td>TechZone Holdings</td>
                        <td>₱6,800,000</td>
                        <td>Apr 5, 2025</td>
                        <td>Jan 5, 2026</td>
                        <td>—</td>
                        <td>9 mo</td>
                        <td>
                            <div class="progress-cell">
                                <span class="percent">28%</span>
                                <div class="mini-bar"><div class="fill" style="width:28%;"></div></div>
                            </div>
                        </td>
                        <td><span class="phase-badge">Planning</span></td>
                        <td><span class="status-badge at-risk"><span class="dot"></span> At Risk</span></td>
                    </tr>
                    <tr onclick="openUpdateModal('North Rail Station', 'DOTR — PH', 44)">
                        <td><strong>North Rail Station</strong></td>
                        <td>DOTR — PH</td>
                        <td>₱45,000,000</td>
                        <td>Sep 1, 2024</td>
                        <td>Mar 1, 2025</td>
                        <td>—</td>
                        <td>6 mo</td>
                        <td>
                            <div class="progress-cell">
                                <span class="percent">44%</span>
                                <div class="mini-bar"><div class="fill" style="width:44%;"></div></div>
                            </div>
                        </td>
                        <td><span class="phase-badge">Structure</span></td>
                        <td><span class="status-badge delayed"><span class="dot"></span> Delayed</span></td>
                    </tr>
                    <tr onclick="openUpdateModal('Pasig River Walk', 'Pasig City LGU', 80)">
                        <td><strong>Pasig River Walk</strong></td>
                        <td>Pasig City LGU</td>
                        <td>₱3,200,000</td>
                        <td>Jan 2, 2025</td>
                        <td>Jun 30, 2025</td>
                        <td>—</td>
                        <td>6 mo</td>
                        <td>
                            <div class="progress-cell">
                                <span class="percent">80%</span>
                                <div class="mini-bar"><div class="fill" style="width:80%;"></div></div>
                            </div>
                        </td>
                        <td><span class="phase-badge">Finishing</span></td>
                        <td><span class="status-badge on-track"><span class="dot"></span> On Track</span></td>
                    </tr>
                    <tr onclick="openUpdateModal('Metro Interchange', 'MMDA', 38)">
                        <td><strong>Metro Interchange</strong></td>
                        <td>MMDA</td>
                        <td>₱28,000,000</td>
                        <td>Oct 15, 2024</td>
                        <td>Apr 15, 2025</td>
                        <td>—</td>
                        <td>6 mo</td>
                        <td>
                            <div class="progress-cell">
                                <span class="percent">38%</span>
                                <div class="mini-bar"><div class="fill" style="width:38%;"></div></div>
                            </div>
                        </td>
                        <td><span class="phase-badge">Foundation</span></td>
                        <td><span class="status-badge delayed"><span class="dot"></span> Delayed</span></td>
                    </tr>
                    <tr onclick="openUpdateModal('Laguna Warehouse Complex', 'STAR Logistics', 20)">
                        <td><strong>Laguna Warehouse Complex</strong></td>
                        <td>STAR Logistics</td>
                        <td>₱9,500,000</td>
                        <td>Mar 18, 2025</td>
                        <td>Sep 18, 2025</td>
                        <td>—</td>
                        <td>6 mo</td>
                        <td>
                            <div class="progress-cell">
                                <span class="percent">20%</span>
                                <div class="mini-bar"><div class="fill" style="width:20%;"></div></div>
                            </div>
                        </td>
                        <td><span class="phase-badge">Planning</span></td>
                        <td><span class="status-badge at-risk"><span class="dot"></span> At Risk</span></td>
                    </tr>
                    <tr onclick="openUpdateModal('Alabang Medical', 'HealthFirst PH', 60)">
                        <td><strong>Alabang Medical</strong></td>
                        <td>HealthFirst PH</td>
                        <td>₱18,000,000</td>
                        <td>Dec 1, 2024</td>
                        <td>Nov 30, 2025</td>
                        <td>—</td>
                        <td>12 mo</td>
                        <td>
                            <div class="progress-cell">
                                <span class="percent">60%</span>
                                <div class="mini-bar"><div class="fill" style="width:60%;"></div></div>
                            </div>
                        </td>
                        <td><span class="phase-badge">Structure</span></td>
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
                <h2>Add new project</h2>
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

    <!-- ─── UPDATE PROJECT PROGRESS MODAL ─── -->
    <div id="updateModal" class="modal-overlay modal-update">
        <div class="modal-container">
            <div class="modal-header">
                <div>
                    <h2 id="updateProjectName" style="margin-bottom: 2px;">Skyline Tower</h2>
                    <span class="subtitle" id="updateClientName">Mega Realty Corp</span>
                </div>
                <button class="modal-close" onclick="closeUpdateModal()">×</button>
            </div>

            <!-- Overall Progress -->
            <div style="margin-bottom: 25px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <span style="font-size: 0.8rem; font-weight: 600; color: #888; text-transform: uppercase;">Overall Progress</span>
                    <span style="font-size: 1.4rem; font-weight: 700; color: #1a2b3c;" id="updateOverallProgress">72%</span>
                </div>
                <div class="progress-bar-large">
                    <div class="progress-fill" id="updateOverallFill" style="width: 72%;"></div>
                </div>
                <div style="display: flex; justify-content: space-between; font-size: 0.7rem; color: #aaa;">
                    <span>0%</span>
                    <span>100%</span>
                </div>
            </div>

            <!-- Category 1: Concrete & masonry -->
            <div class="update-category" id="category1">
                <div class="category-header" onclick="toggleCategory(1)">
                    <span style="font-weight: 600; color: #1a2b3c;">Concrete &amp; masonry</span>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-weight: 700; color: #c9a96e;" id="cat1Progress">73%</span>
                        <span class="category-toggle">&#9660;</span>
                    </div>
                </div>
                <div class="category-progress">
                    <div class="progress-bar-large">
                        <div class="progress-fill" style="width: 73%;"></div>
                    </div>
                </div>
                <div class="category-details" id="cat1Details">
                    <table class="material-table" id="materialTable1">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Planned</th>
                                <th>Used</th>
                                <th>Update</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Ready-mix concrete</td>
                                <td>2,400 m³</td>
                                <td>1,750 m³</td>
                                <td><input type="number" class="editable-input" value="1750" id="update1_1" onchange="updateUsed('update1_1', 'Ready-mix concrete', 2400)"></td>
                                <td><span class="progress-percent" id="progress1_1">73%</span></td>
                            </tr>
                            <tr>
                                <td>Hollow blocks</td>
                                <td>85,000 pcs</td>
                                <td>62,000 pcs</td>
                                <td><input type="number" class="editable-input" value="62000" id="update1_2" onchange="updateUsed('update1_2', 'Hollow blocks', 85000)"></td>
                                <td><span class="progress-percent" id="progress1_2">73%</span></td>
                            </tr>
                            <tr>
                                <td>Mortar / grout</td>
                                <td>480 bags</td>
                                <td>340 bags</td>
                                <td><input type="number" class="editable-input" value="340" id="update1_3" onchange="updateUsed('update1_3', 'Mortar / grout', 480)"></td>
                                <td><span class="progress-percent" id="progress1_3">71%</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Category 2: Steel & rebar -->
            <div class="update-category" id="category2">
                <div class="category-header" onclick="toggleCategory(2)">
                    <span style="font-weight: 600; color: #1a2b3c;">Steel &amp; rebar</span>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-weight: 700; color: #c9a96e;">79%</span>
                        <span class="category-toggle">&#9660;</span>
                    </div>
                </div>
                <div class="category-progress">
                    <div class="progress-bar-large">
                        <div class="progress-fill" style="width: 79%;"></div>
                    </div>
                </div>
                <div class="category-details" id="cat2Details" style="display: none;">
                    <table class="material-table" id="materialTable2">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Planned</th>
                                <th>Used</th>
                                <th>Update</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Steel bars</td>
                                <td>1,200 tons</td>
                                <td>950 tons</td>
                                <td><input type="number" class="editable-input" value="950" id="update2_1" onchange="updateUsed('update2_1', 'Steel bars', 1200)"></td>
                                <td><span class="progress-percent" id="progress2_1">79%</span></td>
                            </tr>
                            <tr>
                                <td>Rebar</td>
                                <td>800 tons</td>
                                <td>630 tons</td>
                                <td><input type="number" class="editable-input" value="630" id="update2_2" onchange="updateUsed('update2_2', 'Rebar', 800)"></td>
                                <td><span class="progress-percent" id="progress2_2">79%</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Category 3: Finishing materials -->
            <div class="update-category" id="category3">
                <div class="category-header" onclick="toggleCategory(3)">
                    <span style="font-weight: 600; color: #1a2b3c;">Finishing materials</span>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-weight: 700; color: #c9a96e;">25%</span>
                        <span class="category-toggle">&#9660;</span>
                    </div>
                </div>
                <div class="category-progress">
                    <div class="progress-bar-large">
                        <div class="progress-fill" style="width: 25%;"></div>
                    </div>
                </div>
                <div class="category-details" id="cat3Details" style="display: none;">
                    <table class="material-table" id="materialTable3">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Planned</th>
                                <th>Used</th>
                                <th>Update</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Paint</td>
                                <td>500 gallons</td>
                                <td>125 gallons</td>
                                <td><input type="number" class="editable-input" value="125" id="update3_1" onchange="updateUsed('update3_1', 'Paint', 500)"></td>
                                <td><span class="progress-percent" id="progress3_1">25%</span></td>
                            </tr>
                            <tr>
                                <td>Tiles</td>
                                <td>3,200 pcs</td>
                                <td>800 pcs</td>
                                <td><input type="number" class="editable-input" value="800" id="update3_2" onchange="updateUsed('update3_2', 'Tiles', 3200)"></td>
                                <td><span class="progress-percent" id="progress3_2">25%</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Category 4: Electrical & plumbing -->
            <div class="update-category" id="category4">
                <div class="category-header" onclick="toggleCategory(4)">
                    <span style="font-weight: 600; color: #1a2b3c;">Electrical &amp; plumbing</span>
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <span style="font-weight: 700; color: #c9a96e;">73%</span>
                        <span class="category-toggle">&#9660;</span>
                    </div>
                </div>
                <div class="category-progress">
                    <div class="progress-bar-large">
                        <div class="progress-fill" style="width: 73%;"></div>
                    </div>
                </div>
                <div class="category-details" id="cat4Details" style="display: none;">
                    <table class="material-table" id="materialTable4">
                        <thead>
                            <tr>
                                <th>Material</th>
                                <th>Planned</th>
                                <th>Used</th>
                                <th>Update</th>
                                <th>Progress</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Electrical wires</td>
                                <td>10,000 m</td>
                                <td>7,300 m</td>
                                <td><input type="number" class="editable-input" value="7300" id="update4_1" onchange="updateUsed('update4_1', 'Electrical wires', 10000)"></td>
                                <td><span class="progress-percent" id="progress4_1">73%</span></td>
                            </tr>
                            <tr>
                                <td>PVC pipes</td>
                                <td>800 pcs</td>
                                <td>584 pcs</td>
                                <td><input type="number" class="editable-input" value="584" id="update4_2" onchange="updateUsed('update4_2', 'PVC pipes', 800)"></td>
                                <td><span class="progress-percent" id="progress4_2">73%</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer" style="justify-content: flex-end;">
                <button class="btn-cancel" onclick="closeUpdateModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
        // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
            // The link will still navigate to /notifications.
        }

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
                if (!name) { alert('Please enter the project name.'); return; }
                if (!client) { alert('Please enter the client name.'); return; }
            }
            if (currentStep === 2) {
                var manager = document.getElementById('projectManager').value;
                var start = document.getElementById('startDate').value;
                var end = document.getElementById('endDate').value;
                if (!manager) { alert('Please select a project manager.'); return; }
                if (!start) { alert('Please select a start date.'); return; }
                if (!end) { alert('Please select an estimated end date.'); return; }

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
            showSuccess();
        }

        // ─── SUCCESS NOTIFICATION ───
        function showSuccess() {
            var notif = document.getElementById('successNotification');
            notif.style.display = 'block';
            setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
        }

        document.getElementById('projectModal').addEventListener('click', function(e) {
            if (e.target === this) { closeModal(); }
        });

        document.addEventListener('click', function(e) {
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) {
                    closeSuccess();
                }
            }
        });

        // ─── UPDATE PROJECT MODAL ───
        function openUpdateModal(projectName, clientName, progress) {
            document.getElementById('updateProjectName').textContent = projectName;
            document.getElementById('updateClientName').textContent = clientName;
            document.getElementById('updateOverallProgress').textContent = progress + '%';
            document.getElementById('updateOverallFill').style.width = progress + '%';

            // Reset all category details to closed
            for (var i = 1; i <= 4; i++) {
                var details = document.getElementById('cat' + i + 'Details');
                if (details) {
                    details.style.display = 'none';
                }
                var toggle = document.querySelector('#category' + i + ' .category-toggle');
                if (toggle) {
                    toggle.classList.remove('open');
                }
            }

            document.getElementById('updateModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeUpdateModal() {
            document.getElementById('updateModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // ─── CATEGORY TOGGLE ───
        function toggleCategory(catId) {
            var details = document.getElementById('cat' + catId + 'Details');
            var toggle = document.querySelector('#category' + catId + ' .category-toggle');

            if (details.style.display === 'none') {
                details.style.display = 'block';
                toggle.classList.add('open');
            } else {
                details.style.display = 'none';
                toggle.classList.remove('open');
            }
        }

        // ─── AUTO-UPDATE USED VALUE AND PROGRESS ───
        function updateUsed(inputId, materialName, planned) {
            var input = document.getElementById(inputId);
            var newValue = parseFloat(input.value);
            var oldValue = parseFloat(input.defaultValue);

            if (isNaN(newValue) || newValue < 0) {
                alert('Please enter a valid number for ' + materialName);
                input.value = oldValue || 0;
                return;
            }

            // Calculate progress percentage
            var progress = Math.round((newValue / planned) * 100);
            if (progress > 100) progress = 100;

            // Find the progress span for this row
            var row = input.closest('tr');
            var progressSpan = row.querySelector('.progress-percent');

            // Update the progress display
            if (progressSpan) {
                progressSpan.textContent = progress + '%';
                // Color code the progress
                if (progress < 30) {
                    progressSpan.style.color = '#d32f2f';
                } else if (progress < 70) {
                    progressSpan.style.color = '#e65100';
                } else {
                    progressSpan.style.color = '#2e7d32';
                }
            }

            // Highlight the input to show it's been updated
            input.classList.add('updated');

            // Update the default value
            input.defaultValue = newValue;

            // Log for debugging
            console.log(materialName + ' updated to ' + newValue + ' | Progress: ' + progress + '%');

            // Here you would send an AJAX request to save to the database
        }

        document.getElementById('updateModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeUpdateModal();
            }
        });
    </script>

</body>
</html>