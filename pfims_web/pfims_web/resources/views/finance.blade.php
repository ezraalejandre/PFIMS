<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget & Finance - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/finance.css') }}">
</head>
<body>

    @include('partials.header')

    <!-- ─── MAIN CONTENT ─── -->
    <main class="main-content">

        <!-- Page Header -->
        <div class="page-header">
            <h1>BUDGET &amp; FINANCE</h1>
            <button class="btn-add-expense" onclick="openAddExpenseModal()">+ Add Expense</button>
        </div>

        <!-- Current Budget -->
        <div class="budget-card">
            <span class="budget-label">CURRENT BUDGET</span>
            <span class="budget-amount">₱67,000,000</span>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-mini">
                <div class="stat-label">Total Budget</div>
                <div class="stat-value blue">₱9,240</div>
            </div>
            <div class="stat-mini">
                <div class="stat-label">Total Actual</div>
                <div class="stat-value orange">₱8,800</div>
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

        <!-- ─── EXPENSE TABLE ─── -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Expense Description</th>
                        <th>Category</th>
                        <th>Type</th>
                        <th>Budget</th>
                        <th>Actual</th>
                        <th>Variance</th>
                        <th>Date</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Portland Cement - Top Row (Skyline Tower) -->
                    <tr>
                        <td><strong>Skyline Tower</strong></td>
                        <td>Portland Cement stock-in</td>
                        <td>Materials</td>
                        <td><span class="type-badge material">Material</span></td>
                        <td>₱12,500</td>
                        <td>₱11,800</td>
                        <td class="variance-negative">-₱700</td>
                        <td>2026-06-11</td>
                        <td>From INV-2026-06-11 (150 bags)</td>
                    </tr>

                    <!-- Labor Expenses -->
                    <tr>
                        <td><strong>Skyline Tower</strong></td>
                        <td>Salary</td>
                        <td>Labor</td>
                        <td><span class="type-badge labor">Labor</span></td>
                        <td>₱5,000</td>
                        <td>₱4,800</td>
                        <td class="variance-negative">-₱200</td>
                        <td>2026-06-15</td>
                        <td>Overtime included</td>
                    </tr>
                    <tr>
                        <td><strong>Harbor Bridge Annex</strong></td>
                        <td>Foreman wages</td>
                        <td>Labor</td>
                        <td><span class="type-badge labor">Labor</span></td>
                        <td>₱3,000</td>
                        <td>₱3,200</td>
                        <td class="variance-positive">+₱200</td>
                        <td>2026-06-14</td>
                        <td>—</td>
                    </tr>
                    <tr>
                        <td><strong>Green Hills Residences</strong></td>
                        <td>Safety officer salary</td>
                        <td>Labor</td>
                        <td><span class="type-badge labor">Labor</span></td>
                        <td>₱2,500</td>
                        <td>₱2,500</td>
                        <td class="variance-neutral">₱0</td>
                        <td>2026-06-13</td>
                        <td>—</td>
                    </tr>

                    <!-- Material Expenses (from Inventory) -->
                    <tr>
                        <td><strong>Skyline Tower</strong></td>
                        <td>Ready-mix concrete delivery</td>
                        <td>Materials</td>
                        <td><span class="type-badge material">Material</span></td>
                        <td>₱15,000</td>
                        <td>₱14,500</td>
                        <td class="variance-negative">-₱500</td>
                        <td>2026-06-12</td>
                        <td>From INV-2026-06-12</td>
                    </tr>
                    <tr>
                        <td><strong>BPO Hub Bldg. C</strong></td>
                        <td>Steel rebar purchase</td>
                        <td>Materials</td>
                        <td><span class="type-badge material">Material</span></td>
                        <td>₱8,000</td>
                        <td>₱7,800</td>
                        <td class="variance-negative">-₱200</td>
                        <td>2026-06-10</td>
                        <td>From INV-2026-06-10</td>
                    </tr>
                    <tr>
                        <td><strong>Eastwood Mall</strong></td>
                        <td>Electrical wires</td>
                        <td>Materials</td>
                        <td><span class="type-badge material">Material</span></td>
                        <td>₱4,200</td>
                        <td>₱4,500</td>
                        <td class="variance-positive">+₱300</td>
                        <td>2026-06-09</td>
                        <td>From INV-2026-06-09</td>
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

    <!-- ─── OVERLAY / MODAL (Add Labor Expense) ─── -->
    <div id="addExpenseModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Add Labor Expense</h2>
                <button class="modal-close" onclick="closeAddExpenseModal()">×</button>
            </div>

            <div class="modal-body">
                <!-- Project -->
                <div class="form-group">
                    <label>Project <span class="required">*</span></label>
                    <select id="expenseProject">
                        <option value="">Select Project...</option>
                        <option value="1">Skyline Tower</option>
                        <option value="2">Harbor Bridge Annex</option>
                        <option value="3">Green Hills Residences</option>
                        <option value="4">Eastwood Mall</option>
                        <option value="5">BPO Hub Bldg. C</option>
                        <option value="6">Northgate Tower Phase 2</option>
                    </select>
                </div>

                <!-- Expense Description -->
                <div class="form-group">
                    <label>Expense Description <span class="required">*</span></label>
                    <input type="text" placeholder="e.g. Salary" id="expenseDesc">
                </div>

                <!-- Category -->
                <div class="form-group">
                    <label>Category <span class="required">*</span></label>
                    <select id="expenseCategory">
                        <option value="">Select Category...</option>
                        <option value="1">Labor</option>
                        <option value="2">Equipment</option>
                        <option value="3">Subcontractor</option>
                        <option value="4">Other</option>
                    </select>
                </div>

                <!-- Budget & Actual -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Budget Amount <span class="required">*</span></label>
                        <input type="number" placeholder="0.00" id="expenseBudget">
                    </div>
                    <div class="form-group">
                        <label>Actual Amount <span class="required">*</span></label>
                        <input type="number" placeholder="0.00" id="expenseActual">
                    </div>
                </div>

                <!-- Date -->
                <div class="form-group">
                    <label>Date <span class="required">*</span></label>
                    <input type="date" id="expenseDate" value="{{ date('Y-m-d') }}">
                </div>

                <!-- Remarks -->
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

    <script>
        // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
            // Optionally, you can also update the badge count via AJAX later.
            // The link will still navigate to /notifications.
        }

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
            // Reset form
            document.getElementById('expenseProject').value = '';
            document.getElementById('expenseDesc').value = '';
            document.getElementById('expenseCategory').value = '';
            document.getElementById('expenseBudget').value = '';
            document.getElementById('expenseActual').value = '';
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
            var budget = document.getElementById('expenseBudget').value;
            var actual = document.getElementById('expenseActual').value;
            var date = document.getElementById('expenseDate').value;

            if (!project) { alert('Please select a project.'); return; }
            if (!desc) { alert('Please enter an expense description.'); return; }
            if (!category) { alert('Please select a category.'); return; }
            if (!budget || budget <= 0) { alert('Please enter a valid budget amount.'); return; }
            if (!actual || actual < 0) { alert('Please enter a valid actual amount.'); return; }
            if (!date) { alert('Please select a date.'); return; }

            closeAddExpenseModal();
            showSuccess('Expense added successfully!');
            console.log('Add Expense:', { project, desc, category, budget, actual, date });
        }

        // ─── SUCCESS NOTIFICATION ───
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            if (!notif) {
                // Create notification if it doesn't exist
                var div = document.createElement('div');
                div.id = 'successNotification';
                div.className = 'success-notification';
                div.style.display = 'block';
                div.innerHTML = `
                    <div class="success-content">
                        <span class="success-icon">●</span>
                        <span>${message || 'Expense added successfully!'}</span>
                        <button class="success-close" onclick="closeSuccess()">×</button>
                    </div>
                `;
                document.body.appendChild(div);
            } else {
                var msgSpan = notif.querySelector('.success-content span:not(.success-icon)');
                if (msgSpan) msgSpan.textContent = message || 'Expense added successfully!';
                notif.style.display = 'block';
            }
            setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        function closeSuccess() {
            var notif = document.getElementById('successNotification');
            if (notif) notif.style.display = 'none';
        }

        // ─── CLOSE MODAL ON BACKDROP CLICK ───
        document.getElementById('addExpenseModal').addEventListener('click', function(e) {
            if (e.target === this) { closeAddExpenseModal(); }
        });
    </script>

</body>
</html>