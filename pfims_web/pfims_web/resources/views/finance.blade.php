<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Budget & Finance - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/finance.css') }}">
    <style>
        #deleteConfirmModal { z-index: 9999 !important; }
        #deleteBudgetConfirmModal { z-index: 9999 !important; }
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

    <!-- ─── DELETE CONFIRMATION MODAL (Expense) ─── -->
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

    <!-- ─── DELETE CONFIRMATION MODAL (Budget) ─── -->
    <div id="deleteBudgetConfirmModal" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeBudgetDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteBudgetConfirmMessage" style="font-size: 1rem; color: #333; margin-bottom: 10px;">
                    Are you sure you want to permanently delete this budget?
                </p>
                <p style="font-size: 0.85rem; color: #888; margin-bottom: 20px;">
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 12px; margin-top: 10px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeBudgetDeleteModal()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: transparent; color: #888; transition: 0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmBudgetDeleteBtn" onclick="confirmBudgetDelete()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #d32f2f; color: #fff; transition: 0.3s;">Delete</button>
            </div>
        </div>
    </div>

    <!-- ─── FULL-WIDTH HEADER ─── -->
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
                <li><a href="{{ url('/projects') }}">PROJECTS</a></li>
                <li class="active"><a href="{{ url('/finance') }}">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}">INVENTORY</a></li>
                <li><a href="{{ url('/suppliers') }}">SUPPLIERS</a></li>
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

        <!-- Page Header -->
        <div class="page-header">
            <h1>BUDGET &amp; FINANCE</h1>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <button class="btn-add-expense" onclick="openAddExpenseModal()">+ Add Expense</button>
                <button class="btn-add-budget" onclick="openAddBudgetModal()">+ Add Budget</button>
            </div>
        </div>

        <!-- ─── VIEW TOGGLE ─── -->
        <div class="view-toggle">
            <button class="toggle-btn active" id="btnExpenseView" onclick="switchView('expense')">
                Expenses
            </button>
            <button class="toggle-btn" id="btnBudgetView" onclick="switchView('budget')">
                Budgets
            </button>
        </div>

        <!-- ─── EXPENSE VIEW ─── -->
        <div id="expenseView">
            <!-- Three Cards: Total Budget, Expenses, Net Variance -->
            <div class="stats-row expense-stats" id="expenseStats">
                <div class="stat-mini">
                    <div class="stat-label">Total Budget</div>
                    <div class="stat-value blue" id="totalBudgetValue">₱0.00</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-label">Total Expenses</div>
                    <div class="stat-value" id="totalExpensesValue" style="color: #1a2b3c;">₱0.00</div>
                </div>
                <div class="stat-mini">
                    <div class="stat-label">Net Variance</div>
                    <div class="stat-value red" id="netVarianceValue">₱0.00</div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="filter-tabs">
                <span class="tab" data-period="daily" onclick="setActiveTab(this, 'daily')">Daily</span>
                <span class="tab" data-period="weekly" onclick="setActiveTab(this, 'weekly')">Weekly</span>
                <span class="tab active" data-period="monthly" onclick="setActiveTab(this, 'monthly')">Monthly</span>
                <span class="tab" data-period="yearly" onclick="setActiveTab(this, 'yearly')">Yearly</span>
            </div>

            <!-- Project Filter & Search -->
            <div class="filter-row">
                <select id="projectFilter" class="project-filter" onchange="filterByProject()">
                    <option value="all">All Projects</option>
                </select>
                
                <input type="text" 
                       id="projectSearch" 
                       class="project-filter" 
                       placeholder="Search project name..." 
                       oninput="applyFilters()">
                
                <button class="btn-clear-search" onclick="clearSearch()">✕ Clear</button>
            </div>

            <!-- ─── EXPENSE TABLE ─── -->
            <div class="table-wrapper expense-table-wrapper" id="expenseTableWrapper">
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
                    <tbody id="expenseTableBody">
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="pagination-wrapper" id="expensePagination">
                <div class="rows-info">
                    <span id="rowsInfoText">Showing 0 of 0 expenses</span>
                    <select id="financeRowsPerPage" onchange="changeFinancePageSize()">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="pagination-links" id="financePaginationLinks">
                    <!-- Generated by JavaScript -->
                </div>
            </div>
        </div>

        <!-- ─── BUDGET VIEW ─── -->
        <div id="budgetView" style="display: none;">
            <!-- Budget Stats: Total Budget only -->
            <div class="stats-row-budget budget-stats visible" id="budgetStats">
                <div class="stat-mini">
                    <div class="stat-label">Total Budget</div>
                    <div class="stat-value blue" id="budgetTotalValue">₱0.00</div>
                </div>
            </div>

            <!-- Budget Filters: Project Dropdown & Search -->
            <div class="budget-filter-row">
                <select id="budgetProjectFilter" class="project-filter" onchange="filterBudgetTable()">
                    <option value="all">All Projects</option>
                </select>
                
                <input type="text" 
                       id="budgetSearch" 
                       class="project-filter" 
                       placeholder="Search project name..." 
                       oninput="filterBudgetTable()">
                
                <button class="btn-clear-search" onclick="clearBudgetSearch()">✕ Clear</button>
            </div>

            <!-- ─── BUDGET TABLE ─── -->
            <div class="budget-table-wrapper visible" id="budgetTableWrapper">
                <table id="budgetTable">
                    <thead>
                        <tr>
                            <th>Project Name</th>
                            <th>Budget Amount</th>
                        </tr>
                    </thead>
                    <tbody id="budgetTableBody">
                        <tr>
                            <td colspan="2" style="text-align: center; padding: 20px;">Loading budget data...</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Budget Pagination -->
            <div class="pagination-wrapper" id="budgetPagination">
                <div class="rows-info">
                    <span id="budgetRowsInfo">Showing 0 of 0 projects</span>
                    <select id="budgetRowsPerPage" onchange="changeBudgetPageSize()">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="pagination-links" id="budgetPaginationLinks">
                    <!-- Generated by JavaScript -->
                </div>
            </div>
        </div>

    </main>

    <!-- ─── ADD EXPENSE MODAL ─── -->
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

    <!-- ─── ADD BUDGET MODAL ─── -->
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

    <!-- ─── EXPENSE DETAIL MODAL ─── -->
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

    <!-- ─── BUDGET DETAIL MODAL ─── -->
    <div id="budgetDetailModal" class="modal-overlay modal-update">
        <div class="modal-container" style="width: 600px; max-width: 95%;">
            <div class="modal-header">
                <div>
                    <h2 id="budgetDetailModalTitle">Budget Details</h2>
                </div>
                <button class="modal-close" onclick="closeBudgetDetailModal()">×</button>
            </div>

            <div class="budget-detail-grid">
                <div class="budget-detail-item">
                    <label>Project</label>
                    <span id="budgetDetailProjectDisplay" class="budget-detail-value">—</span>
                    <!-- REMOVE THIS: <select id="budgetDetailProjectEdit" class="budget-detail-edit" style="display:none;"></select> -->
                </div>
                <div class="budget-detail-item">
                    <label>Budget Amount</label>
                    <span id="budgetDetailAmountDisplay" class="budget-detail-value">—</span>
                    <input type="number" step="0.01" id="budgetDetailAmountEdit" class="budget-detail-edit" style="display:none;">
                </div>
            </div>

            <div class="modal-footer" style="justify-content: flex-end; gap: 12px;">
                <button class="btn-cancel" onclick="closeBudgetDetailModal()">Close</button>
                <button class="btn-delete" id="budgetDetailDeleteBtn" onclick="deleteBudget()">Delete</button>
                <button class="btn-edit-project" id="budgetDetailEditBtn" onclick="toggleBudgetDetailEdit()">Edit</button>
                <button class="btn-save" id="budgetDetailSaveBtn" style="display:none;" onclick="saveBudgetDetailChanges()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        // ─── STATE VARIABLES ───────────────────────────────────────────
        var financeProjects = [];
        var financeCategories = [];
        var financeExpenses = [];
        var financeFilteredData = [];
        var financePageSize = 25;
        var financeCurrentPage = 1;
        var currentDetailRow = null;
        var isEditMode = false;
        var currentView = 'expense';
        
        // Budget view state
        var budgetData = [];
        var budgetFilteredData = [];
        var budgetPageSize = 25;
        var budgetCurrentPage = 1;
        var budgetProjectFilter = 'all';
        var budgetSearchTerm = '';
        var currentBudgetRow = null;
        var isBudgetEditMode = false;
        
        // Filter state
        var currentPeriod = 'monthly';
        var currentSearchTerm = '';
        var currentProjectFilter = 'all';
        
        var deleteCallback = null;
        var budgetDeleteCallback = null;
        var errorTimeout = null;
        var successTimeout = null;

        // ─── UTILITY FUNCTIONS ─────────────────────────────────────────
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
            if (errorTimeout) clearTimeout(errorTimeout);
            errorTimeout = setTimeout(function() {
                closeError();
            }, 5000);
        }

        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
            if (errorTimeout) {
                clearTimeout(errorTimeout);
                errorTimeout = null;
            }
        }

        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            if (msgSpan) {
                msgSpan.textContent = message || 'Saved successfully!';
            }
            notif.style.display = 'block';
            if (successTimeout) clearTimeout(successTimeout);
            successTimeout = setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
            if (successTimeout) {
                clearTimeout(successTimeout);
                successTimeout = null;
            }
        }

        function formatCurrency(value) {
            var amount = parseFloat(value) || 0;
            return '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        // ─── VIEW TOGGLE ──────────────────────────────────────────────
        function switchView(view) {
            currentView = view;
            
            var btnExpense = document.getElementById('btnExpenseView');
            var btnBudget = document.getElementById('btnBudgetView');
            var expenseView = document.getElementById('expenseView');
            var budgetView = document.getElementById('budgetView');
            
            if (view === 'expense') {
                btnExpense.classList.add('active');
                btnBudget.classList.remove('active');
                expenseView.style.display = 'block';
                budgetView.style.display = 'none';
                applyFilters();
            } else {
                btnExpense.classList.remove('active');
                btnBudget.classList.add('active');
                expenseView.style.display = 'none';
                budgetView.style.display = 'block';
                populateBudgetProjectFilter();
                fetchBudgetData();
            }
        }

        // ─── DELETE MODAL (Expense) ────────────────────────────────────
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

        // ─── DELETE MODAL (Budget) ────────────────────────────────────
        function openBudgetDeleteModal(message, callback) {
            document.getElementById('deleteBudgetConfirmMessage').textContent = message || 'Are you sure you want to permanently delete this budget?';
            budgetDeleteCallback = callback;
            document.getElementById('deleteBudgetConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeBudgetDeleteModal() {
            document.getElementById('deleteBudgetConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            budgetDeleteCallback = null;
        }

        function confirmBudgetDelete() {
            if (typeof budgetDeleteCallback === 'function') {
                budgetDeleteCallback();
            }
            closeBudgetDeleteModal();
        }

        document.getElementById('deleteBudgetConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeBudgetDeleteModal();
            }
        });

        // ─── SET ACTIVE TAB ────────────────────────────────────────────
        function setActiveTab(el, period) {
            var tabs = document.querySelectorAll('.filter-tabs .tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            el.classList.add('active');
            currentPeriod = period;
            applyFilters();
        }

        // ─── FILTER BY PROJECT ─────────────────────────────────────────
        function filterByProject() {
            var filter = document.getElementById('projectFilter').value;
            currentProjectFilter = filter;
            applyFilters();
        }

        // ─── CLEAR SEARCH ──────────────────────────────────────────────
        function clearSearch() {
            document.getElementById('projectSearch').value = '';
            currentSearchTerm = '';
            applyFilters();
        }

        // ─── APPLY ALL FILTERS ─────────────────────────────────────────
        function applyFilters() {
            if (currentView !== 'expense') return;
            
            var searchTerm = document.getElementById('projectSearch').value.toLowerCase().trim();
            currentSearchTerm = searchTerm;
            
            var projectFiltered = [];
            if (currentProjectFilter === 'all') {
                projectFiltered = financeExpenses;
            } else {
                projectFiltered = financeExpenses.filter(function(expense) {
                    return expense.project_name === currentProjectFilter;
                });
            }
            
            var searchFiltered = projectFiltered;
            if (searchTerm) {
                searchFiltered = projectFiltered.filter(function(expense) {
                    var projectName = (expense.project_name || '').toLowerCase();
                    var description = (expense.expense_description || '').toLowerCase();
                    var category = (expense.category_name || expense.expense_category_name || '').toLowerCase();
                    var remarks = (expense.remarks || '').toLowerCase();
                    
                    return projectName.includes(searchTerm) || 
                           description.includes(searchTerm) || 
                           category.includes(searchTerm) || 
                           remarks.includes(searchTerm);
                });
            }
            
            var periodFiltered = filterByPeriod(searchFiltered);
            
            financeFilteredData = periodFiltered;
            renderFinancePage(1);
            updateFinanceTotalsForFilter(currentProjectFilter, periodFiltered);
        }

        // ─── FILTER BY PERIOD ──────────────────────────────────────────
        function filterByPeriod(expenses) {
            var now = new Date();
            var filtered = [];
            
            expenses.forEach(function(expense) {
                if (!expense.expense_date) return;
                
                var expenseDate = new Date(expense.expense_date);
                
                switch(currentPeriod) {
                    case 'daily':
                        if (expenseDate.toDateString() === now.toDateString()) {
                            filtered.push(expense);
                        }
                        break;
                        
                    case 'weekly':
                        var weekStart = new Date(now);
                        weekStart.setDate(now.getDate() - now.getDay());
                        weekStart.setHours(0, 0, 0, 0);
                        
                        var weekEnd = new Date(weekStart);
                        weekEnd.setDate(weekStart.getDate() + 6);
                        weekEnd.setHours(23, 59, 59, 999);
                        
                        if (expenseDate >= weekStart && expenseDate <= weekEnd) {
                            filtered.push(expense);
                        }
                        break;
                        
                    case 'monthly':
                        if (expenseDate.getMonth() === now.getMonth() && 
                            expenseDate.getFullYear() === now.getFullYear()) {
                            filtered.push(expense);
                        }
                        break;
                        
                    case 'yearly':
                        if (expenseDate.getFullYear() === now.getFullYear()) {
                            filtered.push(expense);
                        }
                        break;
                        
                    default:
                        filtered.push(expense);
                }
            });
            
            return filtered;
        }

        // ─── UPDATE FINANCE TOTALS FOR FILTER ─────────────────────────
        function updateFinanceTotalsForFilter(projectName, filteredExpenses) {
            var expenses = filteredExpenses || financeExpenses;
            
            var totalBudget = 0;
            var totalExpenses = 0;
            
            if (projectName === 'all' || !projectName) {
                totalBudget = financeProjects.reduce(function(sum, project) {
                    return sum + parseFloat(project.budget || 0);
                }, 0);
                
                totalExpenses = expenses.reduce(function(sum, expense) {
                    return sum + parseFloat(expense.actual_amount || 0);
                }, 0);
            } else {
                var project = financeProjects.find(function(p) {
                    return p.project_name === projectName;
                });
                
                if (project) {
                    totalBudget = parseFloat(project.budget) || 0;
                }
                
                totalExpenses = expenses.reduce(function(sum, expense) {
                    if (expense.project_name === projectName) {
                        return sum + parseFloat(expense.actual_amount || 0);
                    }
                    return sum;
                }, 0);
            }
            
            var netVariance = totalBudget - totalExpenses;
            
            document.getElementById('totalBudgetValue').textContent = formatCurrency(totalBudget);
            
            var expensesEl = document.getElementById('totalExpensesValue');
            if (expensesEl) {
                expensesEl.textContent = formatCurrency(totalExpenses);
            }
            
            document.getElementById('netVarianceValue').textContent = formatCurrency(netVariance);
            
            var varianceEl = document.getElementById('netVarianceValue');
            if (netVariance < 0) {
                varianceEl.className = 'stat-value red';
            } else {
                varianceEl.className = 'stat-value green';
            }
        }

        // ─── UPDATE ROWS INFO ──────────────────────────────────────────
        function updateRowsInfo(totalCount) {
            var rowsInfo = document.getElementById('rowsInfoText');
            if (!rowsInfo) return;
            
            var currentPage = financeCurrentPage || 1;
            var pageSize = financePageSize || 25;
            var start = (currentPage - 1) * pageSize + 1;
            var end = Math.min(start + pageSize - 1, totalCount);
            
            if (totalCount === 0) {
                rowsInfo.textContent = 'Showing 0 of 0 expenses';
            } else {
                rowsInfo.textContent = 'Showing ' + start + '-' + end + ' of ' + totalCount + ' expenses';
            }
        }

        // ─── API FETCH FUNCTIONS ──────────────────────────────────────
        function fetchProjects() {
            return fetch('/api/projects')
                .then(function(response) {
                    if (!response.ok) throw new Error('Failed to load projects.');
                    return response.json();
                })
                .then(function(data) {
                    financeProjects = data || [];
                    populateProjectDropdowns();
                    populateProjectFilter();
                    if (currentView === 'expense') {
                        updateFinanceTotalsForFilter(currentProjectFilter, financeExpenses);
                    }
                })
                .catch(function(error) {
                    showError(error.message);
                });
        }

        function fetchExpenseCategories() {
            return fetch('/api/expense-categories')
                .then(function(response) {
                    if (!response.ok) throw new Error('Failed to load expense categories.');
                    return response.json();
                })
                .then(function(data) {
                    financeCategories = data || [];
                    populateCategoryDropdown();
                })
                .catch(function(error) {
                    showError(error.message);
                });
        }

        function fetchExpenses() {
            return fetch('/api/expenses')
                .then(function(response) {
                    if (!response.ok) throw new Error('Failed to load expenses.');
                    return response.json();
                })
                .then(function(data) {
                    financeExpenses = data || [];
                    financeFilteredData = financeExpenses;
                    if (currentView === 'expense') {
                        renderFinancePage(1);
                        updateFinanceTotalsForFilter(currentProjectFilter, financeExpenses);
                    }
                })
                .catch(function(error) {
                    showError(error.message);
                });
        }

        function fetchBudgetData() {
            return fetch('/api/budgets')
                .then(function(response) {
                    if (!response.ok) throw new Error('Failed to load budget data.');
                    return response.json();
                })
                .then(function(data) {
                    budgetData = data || [];
                    budgetFilteredData = budgetData;
                    renderBudgetPage(1);
                    updateBudgetStats();
                })
                .catch(function(error) {
                    showError(error.message);
                    var tbody = document.getElementById('budgetTableBody');
                    tbody.innerHTML = '<tr><td colspan="2" style="text-align: center; padding: 20px; color: #d32f2f;">Error loading budget data</td></tr>';
                });
        }

        // ─── POPULATE DROPDOWNS ───────────────────────────────────────
        function populateProjectDropdowns() {
            var projectSelects = [
                document.getElementById('expenseProject'),
                document.getElementById('budgetProject'),
                document.getElementById('detailProjectEdit'),
                document.getElementById('budgetDetailProjectEdit'),
            ];

            projectSelects.forEach(function(select) {
                if (!select) return;
                select.innerHTML = '<option value="">Select Project...</option>';
                financeProjects.forEach(function(project) {
                    var option = document.createElement('option');
                    option.value = project.project_id;
                    option.textContent = project.project_name;
                    select.appendChild(option);
                });
            });
        }

        function populateCategoryDropdown() {
            var expenseCategory = document.getElementById('expenseCategory');
            var detailCategoryEdit = document.getElementById('detailCategoryEdit');
            [expenseCategory, detailCategoryEdit].forEach(function(select) {
                if (!select) return;
                select.innerHTML = '<option value="">Select Category...</option>';
                financeCategories.forEach(function(category) {
                    var option = document.createElement('option');
                    option.value = category.expense_category_id;
                    option.textContent = category.category_name;
                    select.appendChild(option);
                });
            });
        }

        function populateProjectFilter() {
            var filter = document.getElementById('projectFilter');
            filter.innerHTML = '<option value="all">All Projects</option>';
            financeProjects.forEach(function(project) {
                var option = document.createElement('option');
                option.value = project.project_name;
                option.textContent = project.project_name;
                filter.appendChild(option);
            });
        }

        function populateBudgetProjectFilter() {
            var filter = document.getElementById('budgetProjectFilter');
            filter.innerHTML = '<option value="all">All Projects</option>';
            financeProjects.forEach(function(project) {
                var option = document.createElement('option');
                option.value = project.project_name;
                option.textContent = project.project_name;
                filter.appendChild(option);
            });
        }

        // ─── RENDER EXPENSE FUNCTIONS ─────────────────────────────────
        function renderFinancePage(page) {
            financeCurrentPage = page;
            var start = (page - 1) * financePageSize;
            var end = Math.min(start + financePageSize, financeFilteredData.length);
            var pageData = financeFilteredData.slice(start, end);
            
            var tbody = document.getElementById('expenseTableBody');
            tbody.innerHTML = '';
            
            if (!pageData.length) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No expenses found.</td></tr>';
                renderFinancePagination();
                updateRowsInfo(0);
                return;
            }
            
            pageData.forEach(function(expense) {
                var row = document.createElement('tr');
                row.setAttribute('data-expense-id', expense.expense_id);
                row.setAttribute('data-project-id', expense.project_id || '');
                row.setAttribute('data-project', expense.project_name || '');
                row.setAttribute('data-desc', expense.expense_description || '');
                row.setAttribute('data-category-id', expense.expense_category_id || '');
                row.setAttribute('data-category', expense.category_name || expense.expense_category_name || '');
                row.setAttribute('data-amount', expense.actual_amount || '0');
                row.setAttribute('data-date', expense.expense_date || '');
                row.setAttribute('data-remarks', expense.remarks || '');
                row.onclick = function() {
                    openExpenseModal(this);
                };

                var categoryName = expense.category_name || expense.expense_category_name || '';
                var categoryClass = categoryName.toLowerCase();
                if (categoryClass === 'materials' || categoryClass === 'material') categoryClass = 'material';
                if (categoryClass === 'labor') categoryClass = 'labor';
                if (categoryClass === 'equipment') categoryClass = 'equipment';
                if (categoryClass === 'other') categoryClass = 'other';

                row.innerHTML = '<td><strong>' + (expense.project_name || '') + '</strong></td>' +
                    '<td>' + (expense.expense_description || '') + '</td>' +
                    '<td><span class="category-badge ' + categoryClass + '">' + categoryName + '</span></td>' +
                    '<td>' + formatCurrency(expense.actual_amount || 0) + '</td>' +
                    '<td>' + (expense.expense_date || '') + '</td>' +
                    '<td>' + (expense.remarks || '—') + '</td>';
                tbody.appendChild(row);
            });
            
            renderFinancePagination();
            updateRowsInfo(financeFilteredData.length);
        }

        function renderFinancePagination() {
            var container = document.getElementById('financePaginationLinks');
            if (!container) return;
            
            var total = financeFilteredData.length;
            var totalPages = Math.ceil(total / financePageSize);
            var current = financeCurrentPage;
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            var html = '';
            
            html += '<a href="#" onclick="renderFinancePage(' + (current - 1) + '); return false;" class="' + (current <= 1 ? 'disabled' : '') + '">«</a>';
            
            if (totalPages <= 7) {
                for (var i = 1; i <= totalPages; i++) {
                    html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            } else {
                for (var i = 1; i <= 3; i++) {
                    html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
                
                if (current > 4) {
                    html += '<span class="dots">...</span>';
                }
                
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                for (var i = startPage; i <= endPage; i++) {
                    html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
                
                if (current < totalPages - 3) {
                    html += '<span class="dots">...</span>';
                }
                
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    if (i > 3) {
                        html += '<a href="#" onclick="renderFinancePage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                    }
                }
            }
            
            html += '<a href="#" onclick="renderFinancePage(' + (current + 1) + '); return false;" class="' + (current >= totalPages ? 'disabled' : '') + '">»</a>';
            
            container.innerHTML = html;
        }

        function changeFinancePageSize() {
            var select = document.getElementById('financeRowsPerPage');
            financePageSize = parseInt(select.value) || 25;
            financeCurrentPage = 1;
            renderFinancePage(1);
        }

        // ─── RENDER BUDGET FUNCTIONS ──────────────────────────────────
        function renderBudgetPage(page) {
            budgetCurrentPage = page;
            var start = (page - 1) * budgetPageSize;
            var end = Math.min(start + budgetPageSize, budgetFilteredData.length);
            var pageData = budgetFilteredData.slice(start, end);
            
            var tbody = document.getElementById('budgetTableBody');
            tbody.innerHTML = '';
            
            if (!pageData.length) {
                tbody.innerHTML = '<tr><td colspan="2" style="text-align: center; padding: 20px;">No budget data found.</td></tr>';
                renderBudgetPagination();
                updateBudgetRowsInfo(0);
                return;
            }
            
            pageData.forEach(function(budget) {
                var row = document.createElement('tr');
                row.setAttribute('data-budget-id', budget.budget_id);
                row.setAttribute('data-project-id', budget.project_id);
                row.setAttribute('data-project-name', budget.project_name || 'Unnamed Project');
                row.setAttribute('data-budget-amount', budget.budget_amount || '0');
                row.onclick = function() {
                    openBudgetModal(this);
                };

                var budgetAmount = parseFloat(budget.budget_amount) || 0;
                
                row.innerHTML = '<td><strong>' + (budget.project_name || 'Unnamed Project') + '</strong></td>' +
                    '<td>' + formatCurrency(budgetAmount) + '</td>';
                tbody.appendChild(row);
            });
            
            renderBudgetPagination();
            updateBudgetRowsInfo(budgetFilteredData.length);
        }

        function renderBudgetPagination() {
            var container = document.getElementById('budgetPaginationLinks');
            if (!container) return;
            
            var total = budgetFilteredData.length;
            var totalPages = Math.ceil(total / budgetPageSize);
            var current = budgetCurrentPage;
            
            if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }
            
            var html = '';
            
            html += '<a href="#" onclick="renderBudgetPage(' + (current - 1) + '); return false;" class="' + (current <= 1 ? 'disabled' : '') + '">«</a>';
            
            if (totalPages <= 7) {
                for (var i = 1; i <= totalPages; i++) {
                    html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
            } else {
                for (var i = 1; i <= 3; i++) {
                    html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
                
                if (current > 4) {
                    html += '<span class="dots">...</span>';
                }
                
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                for (var i = startPage; i <= endPage; i++) {
                    html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                }
                
                if (current < totalPages - 3) {
                    html += '<span class="dots">...</span>';
                }
                
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    if (i > 3) {
                        html += '<a href="#" onclick="renderBudgetPage(' + i + '); return false;" class="' + (i === current ? 'active' : '') + '">' + i + '</a>';
                    }
                }
            }
            
            html += '<a href="#" onclick="renderBudgetPage(' + (current + 1) + '); return false;" class="' + (current >= totalPages ? 'disabled' : '') + '">»</a>';
            
            container.innerHTML = html;
        }

        function changeBudgetPageSize() {
            var select = document.getElementById('budgetRowsPerPage');
            budgetPageSize = parseInt(select.value) || 25;
            budgetCurrentPage = 1;
            renderBudgetPage(1);
        }

        function updateBudgetRowsInfo(totalCount) {
            var rowsInfo = document.getElementById('budgetRowsInfo');
            if (!rowsInfo) return;
            
            var currentPage = budgetCurrentPage || 1;
            var pageSize = budgetPageSize || 25;
            var start = (currentPage - 1) * pageSize + 1;
            var end = Math.min(start + pageSize - 1, totalCount);
            
            if (totalCount === 0) {
                rowsInfo.textContent = 'Showing 0 of 0 projects';
            } else {
                rowsInfo.textContent = 'Showing ' + start + '-' + end + ' of ' + totalCount + ' projects';
            }
        }

        function updateBudgetStats() {
            var totalBudget = budgetFilteredData.reduce(function(sum, item) {
                return sum + (parseFloat(item.budget_amount) || 0);
            }, 0);
            
            document.getElementById('budgetTotalValue').textContent = formatCurrency(totalBudget);
        }

        function filterBudgetTable() {
            var searchTerm = document.getElementById('budgetSearch').value.toLowerCase().trim();
            var projectFilter = document.getElementById('budgetProjectFilter').value;
            
            budgetSearchTerm = searchTerm;
            budgetProjectFilter = projectFilter;
            
            var projectFiltered = [];
            if (projectFilter === 'all') {
                projectFiltered = budgetData;
            } else {
                projectFiltered = budgetData.filter(function(item) {
                    return item.project_name === projectFilter;
                });
            }
            
            if (searchTerm) {
                budgetFilteredData = projectFiltered.filter(function(item) {
                    var projectName = (item.project_name || '').toLowerCase();
                    return projectName.includes(searchTerm);
                });
            } else {
                budgetFilteredData = projectFiltered;
            }
            
            renderBudgetPage(1);
            updateBudgetStats();
        }

        function clearBudgetSearch() {
            document.getElementById('budgetSearch').value = '';
            document.getElementById('budgetProjectFilter').value = 'all';
            budgetSearchTerm = '';
            budgetProjectFilter = 'all';
            filterBudgetTable();
        }

        // ─── BUDGET DETAIL MODAL ──────────────────────────────────────
        function openBudgetModal(row) {
            currentBudgetRow = row;
            
            var projectId = row.getAttribute('data-project-id');
            var projectName = row.getAttribute('data-project-name');
            var budgetAmount = row.getAttribute('data-budget-amount');
            
            document.getElementById('budgetDetailProjectDisplay').textContent = projectName;
            document.getElementById('budgetDetailAmountDisplay').textContent = formatCurrency(budgetAmount);
            
            // Store projectId for saving
            document.getElementById('budgetDetailProjectDisplay').setAttribute('data-project-id', projectId);
            document.getElementById('budgetDetailAmountEdit').value = budgetAmount;
            
            if (isBudgetEditMode) toggleBudgetDetailEdit();
            isBudgetEditMode = false;
            document.getElementById('budgetDetailEditBtn').style.display = 'inline-block';
            document.getElementById('budgetDetailDeleteBtn').style.display = 'inline-block';
            document.getElementById('budgetDetailSaveBtn').style.display = 'none';
            document.querySelectorAll('.budget-detail-edit').forEach(function(el) { el.style.display = 'none'; });
            document.querySelectorAll('.budget-detail-value').forEach(function(el) { el.style.display = ''; });
            
            document.getElementById('budgetDetailModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeBudgetDetailModal() {
            document.getElementById('budgetDetailModal').classList.remove('active');
            document.body.style.overflow = '';
            if (isBudgetEditMode) toggleBudgetDetailEdit();
        }

        function toggleBudgetDetailEdit() {
            isBudgetEditMode = !isBudgetEditMode;
            var displayEls = document.querySelectorAll('.budget-detail-value');
            var editEls = document.querySelectorAll('.budget-detail-edit');
            var editBtn = document.getElementById('budgetDetailEditBtn');
            var deleteBtn = document.getElementById('budgetDetailDeleteBtn');
            var saveBtn = document.getElementById('budgetDetailSaveBtn');
            
            if (isBudgetEditMode) {
                editBtn.style.display = 'none';
                deleteBtn.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                displayEls.forEach(function(el) { el.style.display = 'none'; });
                editEls.forEach(function(el) { el.style.display = ''; });
            } else {
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                displayEls.forEach(function(el) { el.style.display = ''; });
                editEls.forEach(function(el) { el.style.display = 'none'; });
            }
        }

        function saveBudgetDetailChanges() {
            if (!currentBudgetRow) return;
            
            // Get project ID from the display element
            var projectId = document.getElementById('budgetDetailProjectDisplay').getAttribute('data-project-id');
            var budgetAmount = parseFloat(document.getElementById('budgetDetailAmountEdit').value) || 0;
            
            if (!projectId) {
                showError('Project information is missing.');
                return;
            }
            if (budgetAmount <= 0) {
                showError('Budget amount must be greater than 0.');
                return;
            }
            
            var budgetId = currentBudgetRow.getAttribute('data-budget-id');
            
            fetch('/api/budgets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    project_id: parseInt(projectId),
                    budget_amount: budgetAmount
                }),
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.message || 'Unable to update budget.');
                    });
                }
                return response.json();
            })
            .then(function(budget) {
                // Update the budget in the projects list
                var existing = financeProjects.find(function(item) {
                    return String(item.project_id) === String(budget.project_id);
                });
                if (existing) {
                    existing.budget = budget.budget_amount;
                }
                
                // Update the row
                var projectName = financeProjects.find(function(p) {
                    return String(p.project_id) === String(projectId);
                });
                currentBudgetRow.setAttribute('data-project-id', budget.project_id);
                currentBudgetRow.setAttribute('data-project-name', projectName ? projectName.project_name : 'Unnamed Project');
                currentBudgetRow.setAttribute('data-budget-amount', budget.budget_amount);
                
                var cells = currentBudgetRow.querySelectorAll('td');
                cells[0].innerHTML = '<strong>' + (projectName ? projectName.project_name : 'Unnamed Project') + '</strong>';
                cells[1].textContent = formatCurrency(budget.budget_amount);
                
                closeBudgetDetailModal();
                showSuccess('Budget updated successfully!');
                
                if (currentView === 'budget') {
                    fetchBudgetData();
                } else {
                    updateFinanceTotalsForFilter(currentProjectFilter, financeExpenses);
                }
            })
            .catch(function(error) {
                showError(error.message);
            });
        }

        function deleteBudget() {
            if (!currentBudgetRow) return;
            
            openBudgetDeleteModal('Are you sure you want to permanently delete this budget?', function() {
                var budgetId = currentBudgetRow.getAttribute('data-budget-id');
                
                fetch('/api/budgets/' + budgetId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(function(response) {
                    if (!response.ok) {
                        // Try to parse error message
                        return response.text().then(function(text) {
                            try {
                                var data = JSON.parse(text);
                                throw new Error(data.message || 'Unable to delete budget.');
                            } catch (e) {
                                throw new Error('Unable to delete budget. Server error.');
                            }
                        });
                    }
                    return response.json();
                })
                .then(function() {
                    budgetData = budgetData.filter(function(item) {
                        return String(item.budget_id) !== String(budgetId);
                    });
                    currentBudgetRow.remove();
                    closeBudgetDetailModal();
                    updateBudgetStats();
                    showSuccess('Budget deleted successfully!');
                    currentBudgetRow = null;
                    
                    // Refresh budget data
                    if (currentView === 'budget') {
                        fetchBudgetData();
                    }
                })
                .catch(function(error) {
                    showError(error.message);
                });
            });
        }

        document.getElementById('budgetDetailModal').addEventListener('click', function(e) {
            if (e.target === this) closeBudgetDetailModal();
        });

        // ─── EXPENSE DETAIL MODAL ─────────────────────────────────────
        function openExpenseModal(row) {
            currentDetailRow = row;
            document.getElementById('detailProjectDisplay').textContent = row.dataset.project;
            document.getElementById('detailDescDisplay').textContent = row.dataset.desc;
            document.getElementById('detailCategoryDisplay').textContent = row.dataset.category;
            document.getElementById('detailAmountDisplay').textContent = formatCurrency(row.dataset.amount);
            document.getElementById('detailDateDisplay').textContent = row.dataset.date;
            document.getElementById('detailRemarksDisplay').textContent = row.dataset.remarks || '—';

            document.getElementById('detailProjectEdit').value = row.dataset.projectId || '';
            document.getElementById('detailDescEdit').value = row.dataset.desc;
            document.getElementById('detailCategoryEdit').value = row.dataset.categoryId || '';
            document.getElementById('detailAmountEdit').value = row.dataset.amount;
            document.getElementById('detailDateEdit').value = row.dataset.date;
            document.getElementById('detailRemarksEdit').value = row.dataset.remarks || '';

            if (isEditMode) toggleDetailEdit();
            isEditMode = false;
            document.getElementById('detailEditBtn').style.display = 'inline-block';
            document.getElementById('detailDeleteBtn').style.display = 'inline-block';
            document.getElementById('detailSaveBtn').style.display = 'none';
            document.querySelectorAll('.detail-edit').forEach(function(el) { el.style.display = 'none'; });
            document.querySelectorAll('.detail-value').forEach(function(el) { el.style.display = ''; });

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
                displayEls.forEach(function(el) { el.style.display = 'none'; });
                editEls.forEach(function(el) { el.style.display = ''; });
            } else {
                editBtn.style.display = 'inline-block';
                deleteBtn.style.display = 'inline-block';
                saveBtn.style.display = 'none';
                displayEls.forEach(function(el) { el.style.display = ''; });
                editEls.forEach(function(el) { el.style.display = 'none'; });
            }
        }

        function saveDetailChanges() {
            if (!currentDetailRow) return;
            var projectId = document.getElementById('detailProjectEdit').value;
            var desc = document.getElementById('detailDescEdit').value.trim();
            var categoryId = document.getElementById('detailCategoryEdit').value;
            var amount = parseFloat(document.getElementById('detailAmountEdit').value) || 0;
            var date = document.getElementById('detailDateEdit').value;
            var remarks = document.getElementById('detailRemarksEdit').value.trim();

            if (!projectId || !desc || !categoryId || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (amount <= 0) {
                showError('Amount must be greater than 0.');
                return;
            }

            var expenseId = currentDetailRow.getAttribute('data-expense-id');
            
            var payload = {
                project_id: parseInt(projectId),
                expense_category_id: parseInt(categoryId),
                expense_description: desc,
                amount: amount,
                expense_date: date,
                remarks: remarks || null
            };

            fetch('/api/expenses/' + expenseId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(payload),
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.message || 'Unable to update expense.');
                    });
                }
                return response.json();
            })
            .then(function(expense) {
                var index = financeExpenses.findIndex(function(item) {
                    return String(item.expense_id) === String(expense.expense_id);
                });
                if (index !== -1) {
                    financeExpenses[index] = expense;
                }
                
                applyFilters();
                closeExpenseDetailModal();
                showSuccess('Expense updated successfully!');
            })
            .catch(function(error) {
                showError('Error updating expense: ' + error.message);
            });
        }

        function deleteExpense() {
            if (!currentDetailRow) return;
            openDeleteModal('Are you sure you want to permanently delete this expense?', function() {
                var expenseId = currentDetailRow.getAttribute('data-expense-id');
                fetch('/api/expenses/' + expenseId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                })
                .then(function(response) {
                    if (!response.ok) {
                        return response.json().then(function(data) {
                            throw new Error(data.message || 'Unable to delete expense.');
                        });
                    }
                    return response.json();
                })
                .then(function() {
                    financeExpenses = financeExpenses.filter(function(item) {
                        return String(item.expense_id) !== String(expenseId);
                    });
                    closeExpenseDetailModal();
                    applyFilters();
                    showSuccess('Expense deleted successfully!');
                    currentDetailRow = null;
                })
                .catch(function(error) {
                    showError(error.message);
                });
            });
        }

        // ─── ADD EXPENSE MODAL ────────────────────────────────────────
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
            var projectId = document.getElementById('expenseProject').value;
            var desc = document.getElementById('expenseDesc').value.trim();
            var categoryId = document.getElementById('expenseCategory').value;
            var amount = parseFloat(document.getElementById('expenseAmount').value) || 0;
            var date = document.getElementById('expenseDate').value;
            var remarks = document.getElementById('expenseRemarks').value.trim();

            if (!projectId || !desc || !categoryId || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (amount <= 0) {
                showError('Amount must be greater than 0.');
                return;
            }

            var payload = {
                project_id: parseInt(projectId),
                expense_category_id: parseInt(categoryId),
                expense_description: desc,
                amount: amount,
                expense_date: date,
                remarks: remarks || null
            };

            fetch('/api/expenses', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify(payload),
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.message || 'Unable to save expense.');
                    });
                }
                return response.json();
            })
            .then(function(expense) {
                financeExpenses.push(expense);
                applyFilters();
                closeAddExpenseModal();
                showSuccess('Expense added successfully!');
            })
            .catch(function(error) {
                showError(error.message);
            });
        }

        // ─── ADD BUDGET MODAL ─────────────────────────────────────────
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
            var projectId = document.getElementById('budgetProject').value;
            var amount = document.getElementById('budgetAmount').value;

            if (!projectId || !amount || parseFloat(amount) <= 0) {
                showError('Please select a project and enter a valid budget amount.');
                return;
            }

            fetch('/api/budgets', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({
                    project_id: parseInt(projectId),
                    budget_amount: parseFloat(amount)
                }),
            })
            .then(function(response) {
                if (!response.ok) {
                    return response.json().then(function(data) {
                        throw new Error(data.message || 'Unable to save budget.');
                    });
                }
                return response.json();
            })
            .then(function(budget) {
                var existing = financeProjects.find(function(item) {
                    return String(item.project_id) === String(budget.project_id);
                });
                if (existing) {
                    existing.budget = budget.budget_amount;
                }
                
                closeAddBudgetModal();
                showSuccess('Budget added successfully!');
                
                if (currentView === 'budget') {
                    fetchBudgetData();
                } else {
                    updateFinanceTotalsForFilter(currentProjectFilter, financeExpenses);
                }
            })
            .catch(function(error) {
                showError(error.message);
            });
        }

        // ─── EVENT LISTENERS ──────────────────────────────────────────
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

        // ─── INIT ─────────────────────────────────────────────────────
        document.addEventListener('DOMContentLoaded', function() {
            fetchProjects();
            fetchExpenseCategories();
            fetchExpenses();
        });
    </script>

</body>
</html>