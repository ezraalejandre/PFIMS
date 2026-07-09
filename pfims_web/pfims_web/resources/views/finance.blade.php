<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget & Finance - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/finance.css') }}">
    <style>
        /* Override: Delete modal must be above everything */
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
                </li>>
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
                <div class="stat-value blue" id="totalBudgetValue">₱0.00</div>
            </div>
            <div class="stat-mini">
                <div class="stat-label">Net Variance</div>
                <div class="stat-value red" id="netVarianceValue">₱0.00</div>
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
                <tbody id="expenseTableBody">
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
        var financeProjects = [];
        var financeCategories = [];
        var financeExpenses = [];
        var currentDetailRow = null;
        var isEditMode = false;

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

        function setActiveTab(el) {
            var tabs = document.querySelectorAll('.filter-tabs .tab');
            tabs.forEach(function(tab) {
                tab.classList.remove('active');
            });
            el.classList.add('active');
        }

        function formatCurrency(value) {
            var amount = parseFloat(value) || 0;
            return '₱' + amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

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
                    updateFinanceTotals();
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
                    renderExpenseTable();
                    updateFinanceTotals();
                })
                .catch(function(error) {
                    showError(error.message);
                });
        }

        function populateProjectDropdowns() {
            var projectSelects = [
                document.getElementById('expenseProject'),
                document.getElementById('budgetProject'),
                document.getElementById('detailProjectEdit'),
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

        function getProjectNameById(projectId) {
            var project = financeProjects.find(function(item) {
                return String(item.project_id) === String(projectId);
            });
            return project ? project.project_name : '';
        }

        function getCategoryNameById(categoryId) {
            var category = financeCategories.find(function(item) {
                return String(item.expense_category_id) === String(categoryId);
            });
            return category ? category.category_name : '';
        }

        function renderExpenseTable() {
            var tbody = document.getElementById('expenseTableBody');
            tbody.innerHTML = '';
            financeExpenses.forEach(function(expense) {
                var row = document.createElement('tr');
                row.setAttribute('data-expense-id', expense.expense_id);
                row.setAttribute('data-project-id', expense.project_id || '');
                row.setAttribute('data-project', expense.project_name || '');
                row.setAttribute('data-desc', expense.expense_description || '');
                row.setAttribute('data-category-id', expense.expense_category_id || '');
                row.setAttribute('data-category', expense.expense_category_name || '');
                row.setAttribute('data-amount', expense.actual_amount || '0');
                row.setAttribute('data-date', expense.expense_date || '');
                row.setAttribute('data-remarks', expense.remarks || '');
                row.onclick = function() {
                    openExpenseModal(this);
                };

                var categoryClass = (expense.expense_category_name || '').toLowerCase();
                if (categoryClass === 'materials') categoryClass = 'material';
                if (categoryClass === 'labor') categoryClass = 'labor';
                if (categoryClass === 'other') categoryClass = 'other';

                row.innerHTML = '<td><strong>' + (expense.project_name || '') + '</strong></td>' +
                    '<td>' + (expense.expense_description || '') + '</td>' +
                    '<td><span class="category-badge ' + categoryClass + '">' + (expense.expense_category_name || '') + '</span></td>' +
                    '<td>' + formatCurrency(expense.actual_amount) + '</td>' +
                    '<td>' + (expense.expense_date || '') + '</td>' +
                    '<td>' + (expense.remarks || '—') + '</td>';
                tbody.appendChild(row);
            });
        }

        function updateFinanceTotals() {
            var totalBudget = financeProjects.reduce(function(sum, project) {
                return sum + parseFloat(project.budget || 0);
            }, 0);
            var totalExpenses = financeExpenses.reduce(function(sum, expense) {
                return sum + parseFloat(expense.actual_amount || 0);
            }, 0);
            var netVariance = totalBudget - totalExpenses;

            document.getElementById('totalBudgetValue').textContent = formatCurrency(totalBudget);
            document.getElementById('netVarianceValue').textContent = formatCurrency(netVariance);
            document.getElementById('netVarianceValue').classList.toggle('red', netVariance < 0);
        }

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
            var amount = document.getElementById('expenseAmount').value;
            var date = document.getElementById('expenseDate').value;
            var remarks = document.getElementById('expenseRemarks').value.trim();

            if (!projectId || !desc || !categoryId || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (parseFloat(amount) <= 0) {
                showError('Amount must be greater than 0.');
                return;
            }

            fetch('/api/expenses', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    project_id: projectId,
                    expense_category_id: categoryId,
                    expense_description: desc,
                    actual_amount: amount,
                    expense_date: date,
                    remarks: remarks,
                }),
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
                    renderExpenseTable();
                    updateFinanceTotals();
                    closeAddExpenseModal();
                    showSuccess('Expense added successfully!');
                })
                .catch(function(error) {
                    showError(error.message);
                });
        }

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

            fetch('/api/projects/' + projectId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ budget: amount }),
            })
                .then(function(response) {
                    if (!response.ok) {
                        return response.json().then(function(data) {
                            throw new Error(data.message || 'Unable to save budget.');
                        });
                    }
                    return response.json();
                })
                .then(function(project) {
                    var existing = financeProjects.find(function(item) {
                        return String(item.project_id) === String(project.project_id);
                    });
                    if (existing) {
                        existing.budget = project.budget;
                    }
                    updateFinanceTotals();
                    closeAddBudgetModal();
                    showSuccess('Budget updated for ' + project.project_name + '!');
                })
                .catch(function(error) {
                    showError(error.message);
                });
        }

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
            var amount = document.getElementById('detailAmountEdit').value;
            var date = document.getElementById('detailDateEdit').value;
            var remarks = document.getElementById('detailRemarksEdit').value.trim();

            if (!projectId || !desc || !categoryId || !amount || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (parseFloat(amount) <= 0) {
                showError('Amount must be greater than 0.');
                return;
            }

            var expenseId = currentDetailRow.getAttribute('data-expense-id');
            fetch('/api/expenses/' + expenseId, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    project_id: projectId,
                    expense_category_id: categoryId,
                    expense_description: desc,
                    actual_amount: amount,
                    expense_date: date,
                    remarks: remarks,
                }),
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
                    currentDetailRow.dataset.projectId = expense.project_id || '';
                    currentDetailRow.dataset.project = expense.project_name || '';
                    currentDetailRow.dataset.desc = expense.expense_description || '';
                    currentDetailRow.dataset.categoryId = expense.expense_category_id || '';
                    currentDetailRow.dataset.category = expense.expense_category_name || '';
                    currentDetailRow.dataset.amount = expense.actual_amount || '0';
                    currentDetailRow.dataset.date = expense.expense_date || '';
                    currentDetailRow.dataset.remarks = expense.remarks || '';

                    var cells = currentDetailRow.querySelectorAll('td');
                    var categoryClass = (expense.expense_category_name || '').toLowerCase();
                    if (categoryClass === 'materials') categoryClass = 'material';
                    if (categoryClass === 'labor') categoryClass = 'labor';
                    if (categoryClass === 'other') categoryClass = 'other';

                    cells[0].innerHTML = '<strong>' + (expense.project_name || '') + '</strong>';
                    cells[1].textContent = expense.expense_description || '';
                    cells[2].innerHTML = '<span class="category-badge ' + categoryClass + '">' + (expense.expense_category_name || '') + '</span>';
                    cells[3].textContent = formatCurrency(expense.actual_amount);
                    cells[4].textContent = expense.expense_date || '';
                    cells[5].textContent = expense.remarks || '—';

                    var index = financeExpenses.findIndex(function(item) {
                        return String(item.expense_id) === String(expense.expense_id);
                    });
                    if (index !== -1) {
                        financeExpenses[index] = expense;
                    }
                    updateFinanceTotals();
                    closeExpenseDetailModal();
                    showSuccess('Expense updated successfully!');
                })
                .catch(function(error) {
                    showError(error.message);
                });
        }

        function deleteExpense() {
            if (!currentDetailRow) return;
            openDeleteModal('Are you sure you want to permanently delete this expense?', function() {
                var expenseId = currentDetailRow.getAttribute('data-expense-id');
                fetch('/api/expenses/' + expenseId, {
                    method: 'DELETE',
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
                        currentDetailRow.remove();
                        closeExpenseDetailModal();
                        updateFinanceTotals();
                        showSuccess('Expense deleted successfully!');
                        currentDetailRow = null;
                    })
                    .catch(function(error) {
                        showError(error.message);
                    });
            });
        }

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

        document.addEventListener('DOMContentLoaded', function() {
            fetchProjects();
            fetchExpenseCategories();
            fetchExpenses();
        });
    </script>

</body>
</html>