<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Finance - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Afinance.css') }}">
    <style>
        #deleteConfirmModal { z-index: 9999 !important; }
    </style>
</head>
<body>

    <!-- ─── ERROR NOTIFICATION ─── -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon">⚠</span>
            <span id="errorMessage">An error occurred. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- ─── SUCCESS NOTIFICATION ─── -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span id="successMessage">Saved successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- ─── DELETE CONFIRMATION MODAL (z-index: 9999) ─── -->
    <div id="deleteConfirmModal" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage" style="font-size: 1rem; color: #333; margin-bottom: 10px;">
                    Are you sure you want to permanently delete this expense?
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
            <a href="{{ url('/anotifications') }}" onclick="hideBadge(event)" style="position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span>Notifications</span>
                <span class="notif-badge" id="notifBadge">6</span>
            </a>
            <a href="{{ url('/aprofile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>User</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
        <nav>
            <ul>
                <li><a href="{{ url('/adashboard') }}">DASHBOARD</a></li>
                <li class="active"><a href="{{ url('/afinance') }}">FINANCE</a></li>
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
                    <a href="{{ url('/alanding') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                        <img src="{{ asset('images/logout.jpg') }}" alt="Log Out" class="nav-icon">
                        Log out
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <!-- ─── MAIN CONTENT ─── -->
    <main class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1>BUDGET &amp; FINANCE</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn-add-expense" onclick="openAddExpenseModal()">+ Add Expense</button>
                <button class="btn-add-budget" onclick="openAddBudgetModal()">+ Add Budget</button>
            </div>
        </div>

        <!-- Two Cards: Total Budget & Net Variance -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="stat-label">Total Budget</div>
                <div class="stat-value blue">₱67,000,000</div>
            </div>
            <div class="stat-mini">
                <div class="stat-label">Net Variance</div>
                <div class="stat-value red">-₱440</div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <span class="tab" onclick="setActiveTab(this)">Daily</span>
            <span class="tab" onclick="setActiveTab(this)">Weekly</span>
            <span class="tab active" onclick="setActiveTab(this)">Monthly</span>
            <span class="tab" onclick="setActiveTab(this)">Yearly</span>
        </div>

        <!-- Project Filter -->
        <div class="filter-row">
            <select id="projectFilter" class="project-filter" onchange="filterByProject()">
                <option value="all">All Projects</option>
                <option value="Skyline Tower">Skyline Tower</option>
                <option value="Harbor Bridge Annex">Harbor Bridge Annex</option>
                <option value="Green Hills Residences">Green Hills Residences</option>
                <option value="Eastwood Mall">Eastwood Mall</option>
                <option value="BPO Hub Bldg. C">BPO Hub Bldg. C</option>
                <option value="North Rail Station">North Rail Station</option>
                <option value="Pasig River Walk">Pasig River Walk</option>
                <option value="Metro Interchange">Metro Interchange</option>
                <option value="Laguna Warehouse Complex">Laguna Warehouse Complex</option>
                <option value="Alabang Medical">Alabang Medical</option>
            </select>
        </div>

        <!-- ─── EXPENSE TABLE ─── -->
        <div class="table-wrapper">
            <table id="expenseTable">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Expense Description</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <tr onclick="openExpenseModal(this)" data-project="Skyline Tower" data-desc="Portland Cement stock-in" data-category="Materials" data-amount="11800" data-date="2026-06-11" data-remarks="From INV-2026-06-11">
                        <td><strong>Skyline Tower</strong></td>
                        <td>Portland Cement stock-in</td>
                        <td><span class="category-badge material">Materials</span></td>
                        <td>₱11,800</td>
                        <td>2026-06-11</td>
                        <td>From INV-2026-06-11</td>
                    </tr>
                    <tr onclick="openExpenseModal(this)" data-project="Skyline Tower" data-desc="Salary" data-category="Labor" data-amount="4800" data-date="2026-06-15" data-remarks="Overtime included">
                        <td><strong>Skyline Tower</strong></td>
                        <td>Salary</td>
                        <td><span class="category-badge labor">Labor</span></td>
                        <td>₱4,800</td>
                        <td>2026-06-15</td>
                        <td>Overtime included</td>
                    </tr>
                    <tr onclick="openExpenseModal(this)" data-project="Harbor Bridge Annex" data-desc="Foreman wages" data-category="Labor" data-amount="3200" data-date="2026-06-14" data-remarks="—">
                        <td><strong>Harbor Bridge Annex</strong></td>
                        <td>Foreman wages</td>
                        <td><span class="category-badge labor">Labor</span></td>
                        <td>₱3,200</td>
                        <td>2026-06-14</td>
                        <td>—</td>
                    </tr>
                    <tr onclick="openExpenseModal(this)" data-project="Green Hills Residences" data-desc="Safety officer salary" data-category="Labor" data-amount="2500" data-date="2026-06-13" data-remarks="—">
                        <td><strong>Green Hills Residences</strong></td>
                        <td>Safety officer salary</td>
                        <td><span class="category-badge labor">Labor</span></td>
                        <td>₱2,500</td>
                        <td>2026-06-13</td>
                        <td>—</td>
                    </tr>
                    <tr onclick="openExpenseModal(this)" data-project="Skyline Tower" data-desc="Ready-mix concrete delivery" data-category="Materials" data-amount="14500" data-date="2026-06-12" data-remarks="From INV-2026-06-12">
                        <td><strong>Skyline Tower</strong></td>
                        <td>Ready-mix concrete delivery</td>
                        <td><span class="category-badge material">Materials</span></td>
                        <td>₱14,500</td>
                        <td>2026-06-12</td>
                        <td>From INV-2026-06-12</td>
                    </tr>
                    <tr onclick="openExpenseModal(this)" data-project="BPO Hub Bldg. C" data-desc="Steel rebar purchase" data-category="Materials" data-amount="7800" data-date="2026-06-10" data-remarks="From INV-2026-06-10">
                        <td><strong>BPO Hub Bldg. C</strong></td>
                        <td>Steel rebar purchase</td>
                        <td><span class="category-badge material">Materials</span></td>
                        <td>₱7,800</td>
                        <td>2026-06-10</td>
                        <td>From INV-2026-06-10</td>
                    </tr>
                    <tr onclick="openExpenseModal(this)" data-project="Eastwood Mall" data-desc="Electrical wires" data-category="Materials" data-amount="4500" data-date="2026-06-09" data-remarks="From INV-2026-06-09">
                        <td><strong>Eastwood Mall</strong></td>
                        <td>Electrical wires</td>
                        <td><span class="category-badge material">Materials</span></td>
                        <td>₱4,500</td>
                        <td>2026-06-09</td>
                        <td>From INV-2026-06-09</td>
                    </tr>
                    <tr onclick="openExpenseModal(this)" data-project="Skyline Tower" data-desc="Miscellaneous supplies" data-category="Other" data-amount="1200" data-date="2026-06-16" data-remarks="Office supplies">
                        <td><strong>Skyline Tower</strong></td>
                        <td>Miscellaneous supplies</td>
                        <td><span class="category-badge other">Other</span></td>
                        <td>₱1,200</td>
                        <td>2026-06-16</td>
                        <td>Office supplies</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="pagination-wrapper">
            <div class="rows-info">
                Rows Displayed:
                <select>
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                    <option>100</option>
                </select>
            </div>
            <div class="pagination-links">
                <a href="#">«</a>
                <a href="#" class="active">1</a>
                <a href="#">2</a>
                <a href="#">3</a>
                <span class="dots">...</span>
                <a href="#">12</a>
                <a href="#">13</a>
                <a href="#">»</a>
            </div>
        </div>

    </main>

    <!-- ─── OVERLAY / MODAL (Add Expense) ─── -->
    <div id="addExpenseModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Add Expense</h2>
                <button class="modal-close" onclick="closeAddExpenseModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Project <span class="required">*</span></label>
                    <select id="expenseProject">
                        <option value="">Select Project...</option>
                        <option value="Skyline Tower">Skyline Tower</option>
                        <option value="Harbor Bridge Annex">Harbor Bridge Annex</option>
                        <option value="Green Hills Residences">Green Hills Residences</option>
                        <option value="Eastwood Mall">Eastwood Mall</option>
                        <option value="BPO Hub Bldg. C">BPO Hub Bldg. C</option>
                        <option value="North Rail Station">North Rail Station</option>
                        <option value="Pasig River Walk">Pasig River Walk</option>
                        <option value="Metro Interchange">Metro Interchange</option>
                        <option value="Laguna Warehouse Complex">Laguna Warehouse Complex</option>
                        <option value="Alabang Medical">Alabang Medical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Expense Description <span class="required">*</span></label>
                    <input type="text" placeholder="e.g. Salary" id="expenseDesc">
                </div>
                <div class="form-group">
                    <label>Category <span class="required">*</span></label>
                    <select id="expenseCategory">
                        <option value="">Select Category...</option>
                        <option value="Labor">Labor</option>
                        <option value="Materials">Materials</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount <span class="required">*</span></label>
                    <input type="number" step="0.01" placeholder="0.00" id="expenseAmount">
                </div>
                <div class="form-group">
                    <label>Date <span class="required">*</span></label>
                    <input type="date" id="expenseDate" value="{{ date('Y-m-d') }}">
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <input type="text" placeholder="Additional notes..." id="expenseRemarks">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddExpenseModal()">Cancel</button>
                <button class="btn-save" onclick="saveExpense()">Add Expense</button>
            </div>
        </div>
    </div>

    <!-- ─── OVERLAY / MODAL (Add Budget) ─── -->
    <div id="addBudgetModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Add Budget</h2>
                <button class="modal-close" onclick="closeAddBudgetModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Project <span class="required">*</span></label>
                    <select id="budgetProject">
                        <option value="">Select Project...</option>
                        <option value="Skyline Tower">Skyline Tower</option>
                        <option value="Harbor Bridge Annex">Harbor Bridge Annex</option>
                        <option value="Green Hills Residences">Green Hills Residences</option>
                        <option value="Eastwood Mall">Eastwood Mall</option>
                        <option value="BPO Hub Bldg. C">BPO Hub Bldg. C</option>
                        <option value="North Rail Station">North Rail Station</option>
                        <option value="Pasig River Walk">Pasig River Walk</option>
                        <option value="Metro Interchange">Metro Interchange</option>
                        <option value="Laguna Warehouse Complex">Laguna Warehouse Complex</option>
                        <option value="Alabang Medical">Alabang Medical</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Budget Amount <span class="required">*</span></label>
                    <input type="number" step="0.01" placeholder="0.00" id="budgetAmount">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddBudgetModal()">Cancel</button>
                <button class="btn-save" onclick="saveBudget()">Add Budget</button>
            </div>
        </div>
    </div>

    <!-- ─── OVERLAY / MODAL (Expense Details) ─── -->
    <div id="expenseDetailModal" class="modal-overlay modal-update">
        <div class="modal-container" style="width: 700px; max-width: 95%;">
            <div class="modal-header">
                <div>
                    <h2 id="detailModalTitle">Expense Details</h2>
                </div>
                <button class="modal-close" onclick="closeExpenseDetailModal()">×</button>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <label>Project</label>
                    <span id="detailProjectDisplay" class="detail-value">—</span>
                    <select id="detailProjectEdit" class="detail-edit" style="display:none;">
                        <option value="Skyline Tower">Skyline Tower</option>
                        <option value="Harbor Bridge Annex">Harbor Bridge Annex</option>
                        <option value="Green Hills Residences">Green Hills Residences</option>
                        <option value="Eastwood Mall">Eastwood Mall</option>
                        <option value="BPO Hub Bldg. C">BPO Hub Bldg. C</option>
                        <option value="North Rail Station">North Rail Station</option>
                        <option value="Pasig River Walk">Pasig River Walk</option>
                        <option value="Metro Interchange">Metro Interchange</option>
                        <option value="Laguna Warehouse Complex">Laguna Warehouse Complex</option>
                        <option value="Alabang Medical">Alabang Medical</option>
                    </select>
                </div>
                <div class="detail-item">
                    <label>Expense Description</label>
                    <span id="detailDescDisplay" class="detail-value">—</span>
                    <input type="text" id="detailDescEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>Category</label>
                    <span id="detailCategoryDisplay" class="detail-value">—</span>
                    <select id="detailCategoryEdit" class="detail-edit" style="display:none;">
                        <option value="Labor">Labor</option>
                        <option value="Materials">Materials</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="detail-item">
                    <label>Amount</label>
                    <span id="detailAmountDisplay" class="detail-value">—</span>
                    <input type="number" step="0.01" id="detailAmountEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>Date</label>
                    <span id="detailDateDisplay" class="detail-value">—</span>
                    <input type="date" id="detailDateEdit" class="detail-edit" style="display:none;">
                </div>
                <div class="detail-item">
                    <label>Remarks</label>
                    <span id="detailRemarksDisplay" class="detail-value">—</span>
                    <input type="text" id="detailRemarksEdit" class="detail-edit" style="display:none;">
                </div>
            </div>

            <div class="modal-footer" style="justify-content: flex-end; gap: 12px;">
                <button class="btn-cancel" onclick="closeExpenseDetailModal()">Close</button>
                <button class="btn-delete" id="detailDeleteBtn" onclick="deleteExpense()">Delete</button>
                <button class="btn-edit-project" id="detailEditBtn" onclick="toggleDetailEdit()">Edit</button>
                <button class="btn-save" id="detailSaveBtn" style="display:none;" onclick="saveDetailChanges()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        // ─── HIDE NOTIFICATION BADGE ───
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
                msgSpan.textContent = message || 'Saved successfully!';
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
        var deleteCallback = null;

        function openDeleteModal(message, callback) {
            document.getElementById('deleteConfirmMessage').textContent = message || 'Are you sure you want to permanently delete this expense?';
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

        // ─── FILTER TABS ───
        function setActiveTab(el) {
            var tabs = document.querySelectorAll('.filter-tabs .tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            el.classList.add('active');
            console.log('Filtering by: ' + el.textContent.trim());
        }

        // ─── ADD EXPENSE MODAL ───
        function openAddExpenseModal() {
            document.getElementById('addExpenseModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('expenseProject').value = '';
            document.getElementById('expenseDesc').value = '';
            document.getElementById('expenseCategory').value = '';
            document.getElementById('expenseAmount').value = '';
            document.getElementById('expenseDate').value = '{{ date("Y-m-d") }}';
            document.getElementById('expenseRemarks').value = '';
        }

        function closeAddExpenseModal() {
            document.getElementById('addExpenseModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveExpense() {
            var project = document.getElementById('expenseProject').value;
            var desc = document.getElementById('expenseDesc').value.trim();
            var category = document.getElementById('expenseCategory').value;
            var amount = document.getElementById('expenseAmount').value;
            var date = document.getElementById('expenseDate').value;

            if (!project || !desc || !category || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (parseFloat(amount) <= 0) {
                showError('Amount must be greater than 0.');
                return;
            }

            closeAddExpenseModal();
            showSuccess('Expense added successfully!');
            console.log('Add Expense:', { project, desc, category, amount, date });
        }

        // ─── ADD BUDGET MODAL ───
        function openAddBudgetModal() {
            document.getElementById('addBudgetModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('budgetProject').value = '';
            document.getElementById('budgetAmount').value = '';
        }

        function closeAddBudgetModal() {
            document.getElementById('addBudgetModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveBudget() {
            var project = document.getElementById('budgetProject').value;
            var amount = document.getElementById('budgetAmount').value;

            if (!project || !amount || parseFloat(amount) <= 0) {
                showError('Please select a project and enter a valid budget amount.');
                return;
            }

            closeAddBudgetModal();
            showSuccess('Budget added for ' + project + '!');
            console.log('Add Budget:', { project, amount });
        }

        // ─── PROJECT FILTER ───
        function filterByProject() {
            var filter = document.getElementById('projectFilter').value;
            var rows = document.querySelectorAll('#expenseTable tbody tr');
            rows.forEach(function(row) {
                var project = row.getAttribute('data-project');
                if (filter === 'all' || project === filter) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // ─── EXPENSE DETAIL MODAL ───
        var currentDetailRow = null;
        var isEditMode = false;

        function openExpenseModal(row) {
            currentDetailRow = row;
            document.getElementById('detailProjectDisplay').textContent = row.dataset.project;
            document.getElementById('detailDescDisplay').textContent = row.dataset.desc;
            document.getElementById('detailCategoryDisplay').textContent = row.dataset.category;
            document.getElementById('detailAmountDisplay').textContent = '₱' + parseFloat(row.dataset.amount).toLocaleString();
            document.getElementById('detailDateDisplay').textContent = row.dataset.date;
            document.getElementById('detailRemarksDisplay').textContent = row.dataset.remarks || '—';

            document.getElementById('detailProjectEdit').value = row.dataset.project;
            document.getElementById('detailDescEdit').value = row.dataset.desc;
            document.getElementById('detailCategoryEdit').value = row.dataset.category;
            document.getElementById('detailAmountEdit').value = row.dataset.amount;
            document.getElementById('detailDateEdit').value = row.dataset.date;
            document.getElementById('detailRemarksEdit').value = row.dataset.remarks || '';

            if (isEditMode) toggleDetailEdit();
            isEditMode = false;
            document.getElementById('detailEditBtn').style.display = 'inline-block';
            document.getElementById('detailDeleteBtn').style.display = 'inline-block';
            document.getElementById('detailSaveBtn').style.display = 'none';
            document.querySelectorAll('.detail-edit').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.detail-value').forEach(el => el.style.display = '');

            document.getElementById('expenseDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeExpenseDetailModal() {
            document.getElementById('expenseDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            if (isEditMode) toggleDetailEdit();
        }

        function toggleDetailEdit() {
            isEditMode = !isEditMode;
            var displayEls = document.querySelectorAll('.detail-value');
            var editEls = document.querySelectorAll('.detail-edit');
            var editBtn = document.getElementById('detailEditBtn');
            var deleteBtn = document.getElementById('detailDeleteBtn');
            var saveBtn = document.getElementById('detailSaveBtn');

            if (isEditMode) {
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                displayEls.forEach(el => el.style.display = 'none');
                editEls.forEach(el => el.style.display = '');
            } else {
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                displayEls.forEach(el => el.style.display = '');
                editEls.forEach(el => el.style.display = 'none');
            }
        }

        function saveDetailChanges() {
            if (!currentDetailRow) return;
            var project = document.getElementById('detailProjectEdit').value;
            var desc = document.getElementById('detailDescEdit').value.trim();
            var category = document.getElementById('detailCategoryEdit').value;
            var amount = document.getElementById('detailAmountEdit').value;
            var date = document.getElementById('detailDateEdit').value;
            var remarks = document.getElementById('detailRemarksEdit').value.trim();

            if (!project || !desc || !category || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (parseFloat(amount) <= 0) {
                showError('Amount must be greater than 0.');
                return;
            }

            currentDetailRow.dataset.project = project;
            currentDetailRow.dataset.desc = desc;
            currentDetailRow.dataset.category = category;
            currentDetailRow.dataset.amount = amount;
            currentDetailRow.dataset.date = date;
            currentDetailRow.dataset.remarks = remarks;

            var cells = currentDetailRow.querySelectorAll('td');
            cells[0].innerHTML = '<strong>' + project + '</strong>';
            cells[1].textContent = desc;

            var catClass = category.toLowerCase();
            cells[2].innerHTML = '<span class="category-badge ' + catClass + '">' + category + '</span>';

            cells[3].textContent = '₱' + parseFloat(amount).toLocaleString();
            cells[4].textContent = date;
            cells[5].textContent = remarks || '—';

            closeExpenseDetailModal();
            showSuccess('Expense updated successfully!');
        }

        // ─── DELETE EXPENSE ───
        function deleteExpense() {
            if (!currentDetailRow) return;
            openDeleteModal('Are you sure you want to permanently delete this expense?', function() {
                currentDetailRow.remove();
                closeExpenseDetailModal();
                showSuccess('Expense deleted successfully!');
                currentDetailRow = null;
            });
        }

        // ─── CLOSE MODALS ON BACKDROP ───
        document.getElementById('addExpenseModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddExpenseModal();
        });
        document.getElementById('addBudgetModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddBudgetModal();
        });
        document.getElementById('expenseDetailModal').addEventListener('click', function(e) {
            if (e.target === this) closeExpenseDetailModal();
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