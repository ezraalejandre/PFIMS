<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Inventory Records - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
</head>
<body>

    <!-- Error Notification -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon">⚠</span>
            <span id="errorMessage">An error occurred. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteConfirmModal" class="modal-overlay" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage" style="font-size: 1rem; color: #333; margin-bottom: 10px;">
                    Are you sure you want to permanently delete this transaction?
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

    <!-- ─── SUCCESS NOTIFICATION ─── -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span>Transaction added successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
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
                <li><a href="{{ url('/finance') }}">FINANCE</a></li>
                <li class="active"><a href="{{ url('/inventory') }}">INVENTORY</a></li>
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
                    <a href="{{ url('/') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
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
            <h1>INVENTORY RECORDS</h1>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid-inv">
            <div class="stat-card-inv">
                <div class="stat-label">Total Items</div>
                <div class="stat-value">188</div>
                <div class="stat-sub">Across all transactions</div>
            </div>
            <div class="stat-card-inv">
                <div class="stat-label">Total Value</div>
                <div class="stat-value">$31,963.12</div>
                <div class="stat-sub">Current Inventory Value</div>
            </div>
            <div class="stat-card-inv">
                <div class="stat-label">Low Stock Items</div>
                <div class="stat-value">0</div>
                <div class="stat-sub">Items for restocking</div>
            </div>
            <div class="stat-card-inv">
                <div class="stat-label">Categories</div>
                <div class="stat-value">10</div>
                <div class="stat-sub">Item categories</div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <input type="text" class="search-input" placeholder="Search transactions...">
            <select>
                <option>All Transactions</option>
                <option>IN</option>
                <option>OUT</option>
            </select>
            <input type="date" class="date-input" value="2026-05-01">
            <input type="date" class="date-input" value="2026-05-08">
            <button class="btn-add-transaction" onclick="openModal()">+ Add Transaction</button>
        </div>

        <!-- Table -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Quantity</th>
                        <th>Supplier</th>
                        <th>Type</th>
                        <th>Date</th>
                        <th>Current Stock</th>
                        <th>Status</th>
                        <th style="text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="inventoryTableBody">
                    <tr><td colspan="9" style="text-align: center; padding: 20px;">Loading inventory items...</td></tr>
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
                <a href="#">&laquo;</a>
                <a href="#" class="active">1</a>
                <a href="#">2</a>
                <a href="#">3</a>
                <span class="dots">...</span>
                <a href="#">67</a>
                <a href="#">68</a>
                <a href="#">&raquo;</a>
            </div>
        </div>

    </main>

    </main>

    <!-- View/Edit Modal -->
    <div id="viewModal" class="modal-overlay modal-view">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="viewModalTitle">Transaction Details</h2>
                <button class="modal-close" onclick="closeViewModal()">×</button>
            </div>

            <input type="hidden" id="viewTransactionId" value="">
            <input type="hidden" id="viewItemId" value="">

            <div class="view-details-grid">
                <div class="view-item">
                    <label>Item Name</label>
                    <span id="viewItemNameDisplay" class="view-value">—</span>
                    <input type="text" id="viewItemNameInput" class="view-input" style="display: none;" placeholder="Item Name">
                </div>
                <div class="view-item">
                    <label>Category</label>
                    <span id="viewCategoryDisplay" class="view-value">—</span>
                    <select id="viewCategoryInput" class="view-input" style="display: none;">
                        <option value="Cement">Cement</option>
                        <option value="Steel">Steel</option>
                        <option value="Paint">Paint</option>
                        <option value="Aggregates">Aggregates</option>
                        <option value="Masonry">Masonry</option>
                        <option value="Plumbing">Plumbing</option>
                        <option value="Electrical">Electrical</option>
                        <option value="Finishing">Finishing</option>
                    </select>
                </div>
                <div class="view-item">
                    <label>Unit</label>
                    <span id="viewUnitDisplay" class="view-value">—</span>
                    <select id="viewUnitInput" class="view-input" style="display: none;">
                        <option value="bags">bags</option>
                        <option value="pcs">pcs</option>
                        <option value="gallons">gallons</option>
                        <option value="tons">tons</option>
                        <option value="rolls">rolls</option>
                        <option value="boxes">boxes</option>
                        <option value="m">m</option>
                        <option value="kg">kg</option>
                    </select>
                </div>
                <div class="view-item">
                    <label>Quantity</label>
                    <span id="viewQuantityDisplay" class="view-value">—</span>
                    <input type="number" id="viewQuantityInput" class="view-input" style="display: none;" min="1">
                </div>
                <div class="view-item">
                    <label>Supplier</label>
                    <span id="viewSupplierDisplay" class="view-value">—</span>
                    <select id="viewSupplierInput" class="view-input" style="display: none;">
                        <option value="">Select Supplier...</option>
                    </select>
                </div>
                <div class="view-item">
                    <label>Transaction Type</label>
                    <span id="viewTypeDisplay" class="view-value">—</span>
                    <select id="viewTypeInput" class="view-input" style="display: none;">
                        <option value="IN">IN</option>
                        <option value="OUT">OUT</option>
                    </select>
                </div>
                <div class="view-item">
                    <label>Date</label>
                    <span id="viewDateDisplay" class="view-value">—</span>
                    <input type="date" id="viewDateInput" class="view-input" style="display: none;">
                </div>
                <div class="view-item">
                    <label>Current Stock</label>
                    <span id="viewStockDisplay" class="view-value">—</span>
                </div>
                <div class="view-item">
                    <label>Status</label>
                    <span id="viewStatusDisplay" class="view-value status-badge">—</span>
                </div>
                <div class="view-item" id="viewProjectRow" style="display: none;">
                    <label>Project</label>
                    <span id="viewProjectDisplay" class="view-value">—</span>
                    <input type="text" id="viewProjectInput" class="view-input" style="display: none;">
                </div>
            </div>

            <div class="modal-footer" style="justify-content: flex-end; gap: 12px;">
                <button class="btn-cancel" onclick="closeViewModal()">Close</button>
                <button class="btn-delete" id="viewDeleteBtn" onclick="deleteTransaction()">Delete</button>
                <button class="btn-edit-project" id="viewEditBtn" onclick="enableEditMode()">Edit</button>
                <button class="btn-save" id="viewSaveBtn" style="display: none;" onclick="saveEdit()">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- ─── OVERLAY / MODAL (Add Transaction) ─── -->
    <div id="transactionModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Add new transaction</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>

            <!-- Step Indicator -->
            <div class="step-indicator">
                <span class="step active" id="step1Indicator">
                    <span class="step-number">1</span> Transaction Details
                </span>
                <span class="step" id="step2Indicator">
                    <span class="step-number">2</span> Review
                </span>
            </div>

            <!-- ─── STEP 1: Transaction Details ─── -->
            <div class="modal-step" id="step1">
                <h3>Item Information</h3>

                <!-- Row 1: Item Name + Category (side by side) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Item Name</label>
                        <input type="text" placeholder="Item Name" id="itemName">
                    </div>
                    <div class="form-group">
                        <label>Item Category</label>
                        <select id="itemCategory">
                            <option value="">Loading categories...</option>
                        </select>
                    </div>
                </div>

                <!-- Row 2: Quantity + Unit + Supplier (side by side - 3 columns) -->
                <div class="form-row-three">
                    <div class="form-group">
                        <label>Item Quantity</label>
                        <div class="quantity-control">
                            <button type="button" onclick="changeQuantity(-1)">−</button>
                            <input type="number" id="itemQuantity" value="1" min="1">
                            <button type="button" onclick="changeQuantity(1)">+</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Item Unit</label>
                        <select id="itemUnit">
                            <option value="">Loading units...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Item Supplier</label>
                        <select id="itemSupplier">
                            <option value="">Loading suppliers...</option>
                        </select>
                    </div>
                </div>

                <!-- Separator Line -->
                <hr style="border: none; border-top: 1px solid #e9ecef; margin: 15px 0 20px;">

                <h3>Transaction Details</h3>

                <!-- Row 3: Transaction Type + Date (side by side) -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Transaction Type</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="transactionType" value="IN" checked>
                                IN
                                <span class="radio-sub">Item Stock in</span>
                            </label>
                            <label>
                                <input type="radio" name="transactionType" value="OUT">
                                OUT
                                <span class="radio-sub">Item Stock out</span>
                            </label>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Transaction Date</label>
                        <input type="date" id="transactionDate" value="2026-05-10">
                    </div>
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

            <!-- ─── STEP 2: Review ─── -->
            <div class="modal-step" id="step2" style="display: none;">
                <h3>Review transaction details</h3>

                <div class="summary-list">
                    <div class="summary-item">
                        <strong>Item Name</strong>
                        <span class="summary-value" id="reviewItemName">—</span>
                    </div>
                    <div class="summary-item">
                        <strong>Item Category</strong>
                        <span class="summary-value" id="reviewItemCategory">—</span>
                    </div>
                    <div class="summary-item">
                        <strong>Item Supplier</strong>
                        <span class="summary-value" id="reviewItemSupplier">—</span>
                    </div>
                    <div class="summary-item">
                        <strong>Item Quantity</strong>
                        <span class="summary-value" id="reviewItemQuantity">—</span>
                    </div>
                    <div class="summary-item">
                        <strong>Item Unit</strong>
                        <span class="summary-value" id="reviewItemUnit">—</span>
                    </div>
                </div>

                <!-- Separator Line before Transaction Type -->
                <hr style="border: none; border-top: 1px solid #e9ecef; margin: 5px 0 12px;">

                <div class="summary-list" style="border-left-color: #1a2b3c;">
                    <div class="summary-item">
                        <strong>Transaction Type</strong>
                        <span class="summary-value" id="reviewTransactionType">—</span>
                    </div>
                    <div class="summary-item">
                        <strong>Transaction Date</strong>
                        <span class="summary-value" id="reviewTransactionDate">—</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="footer-left">
                        <button class="btn-cancel" onclick="closeModal()">Cancel</button>
                        <button class="btn-back" onclick="prevStep(1)">Back</button>
                    </div>
                    <div class="footer-right">
                        <button class="btn-save" onclick="saveTransaction()">Add Transaction</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        var csrfToken = '{{ csrf_token() }}';
        var lookupData = { categories: [], suppliers: [], units: [] };
        var inventoryItems = [];
        var allTransactions = [];

        // ─── LOAD LOOKUP DATA ───
        function loadLookupData() {
            fetch('/api/inventory/lookup-data', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success && data.data) {
                    lookupData = data.data;
                    populateDropdowns();
                    loadInventoryItems();
                }
            })
            .catch(function(err) {
                console.error('Error loading lookup data:', err);
            });
        }

        // ─── POPULATE FORM DROPDOWNS ───
        function populateDropdowns() {
            var categorySelect = document.getElementById('itemCategory');
            var unitSelect = document.getElementById('itemUnit');
            var supplierSelect = document.getElementById('itemSupplier');

            // Categories
            categorySelect.innerHTML = '<option value="">Choose Category...</option>';
            lookupData.categories.forEach(function(cat) {
                var opt = document.createElement('option');
                opt.value = cat.inventory_category_id;
                opt.textContent = cat.inventory_category_name;
                categorySelect.appendChild(opt);
            });

            // Units
            unitSelect.innerHTML = '<option value="">Choose Unit...</option>';
            lookupData.units.forEach(function(unit) {
                var opt = document.createElement('option');
                opt.value = unit.unit_id;
                opt.textContent = unit.unit_name;
                unitSelect.appendChild(opt);
            });

            // Suppliers
            supplierSelect.innerHTML = '<option value="">Choose Supplier...</option>';
            lookupData.suppliers.forEach(function(sup) {
                var opt = document.createElement('option');
                opt.value = sup.supplier_id;
                opt.textContent = sup.supplier_name;
                supplierSelect.appendChild(opt);
            });
        }

        // ─── LOAD INVENTORY ITEMS AND TRANSACTIONS ───
        var allTransactions = [];
        function loadInventoryItems() {
            Promise.all([
                fetch('/api/inventory', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(res) { return res.json(); }),
                fetch('/api/inventory/transactions', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(res) { return res.json(); })
            ])
            .then(function(results) {
                var itemsResult = results[0];
                var transactionsResult = results[1];

                inventoryItems = itemsResult.success ? itemsResult.data || [] : [];
                allTransactions = transactionsResult.success ? transactionsResult.data || [] : [];
                renderInventoryTable();
            })
            .catch(function(err) {
                console.error('Error loading inventory data:', err);
            });
        }

        // ─── RENDER INVENTORY TABLE ───
        function renderInventoryTable() {
        var tbody = document.getElementById('inventoryTableBody');
        tbody.innerHTML = '';

        if (!allTransactions.length) {
            tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 20px;">No transactions found.</td></tr>';
            return;
        }

        allTransactions.forEach(function(row) {
            var tr = document.createElement('tr');
            var status = row.current_stock > row.reorder_level ? 'in-stock' : 'low-stock';
            var statusText = row.current_stock > row.reorder_level ? 'In Stock' : 'Low Stock';
            var typeLabel = row.transaction_type || '—';
            var dateValue = row.transaction_date ? new Date(row.transaction_date).toLocaleDateString() : '—';

            tr.setAttribute('data-id', row.inventory_transaction_id || '');
            tr.setAttribute('data-item-id', row.item_id || '');
            tr.setAttribute('data-item', row.item_name || '');
            tr.setAttribute('data-category', row.category || '');
            tr.setAttribute('data-unit', row.unit || '');
            tr.setAttribute('data-quantity', row.quantity || '');
            tr.setAttribute('data-supplier', row.supplier || '');
            tr.setAttribute('data-supplier-id', row.supplier_id || '');
            tr.setAttribute('data-type', typeLabel);
            tr.setAttribute('data-date', row.transaction_date || '');
            tr.setAttribute('data-stock', row.current_stock || '');
            tr.setAttribute('data-status', statusText);
            tr.setAttribute('data-project', row.project || '');

            tr.innerHTML = `
                <td><strong>${row.item_name}</strong></td>
                <td>${row.category}</td>
                <td>${row.unit}</td>
                <td>${row.quantity}</td>
                <td>${row.supplier}</td>
                <td><span class="type-badge ${typeLabel === 'IN' ? 'in' : 'out'}">${typeLabel}</span></td>
                <td>${dateValue}</td>
                <td>${row.current_stock}</td>
                <td><span class="status-badge ${status}"><span class="dot"></span> ${statusText}</span></td>
                <td style="text-align: center;">
                    <span class="action-icon" onclick="event.stopPropagation(); openViewModal(this.closest('tr'));">👁️</span>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

        // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // ─── MODAL CONTROLS ───
        function openModal() {
            document.getElementById('transactionModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            goToStep(1);
            // Reset form
            document.getElementById('itemName').value = '';
            document.getElementById('itemCategory').value = '';
            document.getElementById('itemQuantity').value = 1;
            document.getElementById('itemUnit').value = '';
            document.getElementById('itemSupplier').value = '';
            document.querySelector('input[name="transactionType"][value="IN"]').checked = true;
            var today = new Date().toISOString().split('T')[0];
            document.getElementById('transactionDate').value = today;
            // Clear review
            document.getElementById('reviewItemName').textContent = '—';
            document.getElementById('reviewItemCategory').textContent = '—';
            document.getElementById('reviewItemSupplier').textContent = '—';
            document.getElementById('reviewItemQuantity').textContent = '—';
            document.getElementById('reviewItemUnit').textContent = '—';
            document.getElementById('reviewTransactionType').textContent = '—';
            document.getElementById('reviewTransactionDate').textContent = '—';
        }

        function closeModal() {
            document.getElementById('transactionModal').classList.remove('active');
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
            // Validate Step 1
            var itemName = document.getElementById('itemName').value.trim();
            var categoryId = document.getElementById('itemCategory').value;
            var quantity = document.getElementById('itemQuantity').value;
            var unitId = document.getElementById('itemUnit').value;
            var supplierId = document.getElementById('itemSupplier').value;
            var date = document.getElementById('transactionDate').value;

            if (!itemName) { alert('Please enter the item name.'); return; }
            if (!categoryId) { alert('Please select an item category.'); return; }
            if (!quantity || quantity < 1) { alert('Please enter a valid quantity (minimum 1).'); return; }
            if (!unitId) { alert('Please select an item unit.'); return; }
            if (!supplierId) { alert('Please select a supplier.'); return; }
            if (!date) { alert('Please select a transaction date.'); return; }

            // Find category and unit names
            var categoryName = lookupData.categories.find(function(c) { return c.inventory_category_id == categoryId; })?.inventory_category_name || 'N/A';
            var unitName = lookupData.units.find(function(u) { return u.unit_id == unitId; })?.unit_name || 'N/A';
            var supplierName = lookupData.suppliers.find(function(s) { return s.supplier_id == supplierId; })?.supplier_name || 'N/A';

            // Populate review
            document.getElementById('reviewItemName').textContent = itemName;
            document.getElementById('reviewItemCategory').textContent = categoryName;
            document.getElementById('reviewItemSupplier').textContent = supplierName;
            document.getElementById('reviewItemQuantity').textContent = quantity;
            document.getElementById('reviewItemUnit').textContent = unitName;

            var type = document.querySelector('input[name="transactionType"]:checked');
            var typeLabel = type ? type.value : 'IN';
            var typeDisplay = typeLabel === 'IN' ? 'IN (Item Stock in)' : 'OUT (Item Stock out)';
            document.getElementById('reviewTransactionType').textContent = typeDisplay;
            document.getElementById('reviewTransactionDate').textContent = date;

            goToStep(step);
        }

        function prevStep(step) {
            goToStep(step);
        }

        // ─── QUANTITY CONTROLS ───
        function changeQuantity(delta) {
            var input = document.getElementById('itemQuantity');
            var val = parseInt(input.value) || 1;
            val = Math.max(1, val + delta);
            input.value = val;
        }

        // ─── SAVE TRANSACTION ───
        function saveTransaction() {
            var itemName = document.getElementById('itemName').value.trim();
            var categoryId = document.getElementById('itemCategory').value;
            var quantity = parseFloat(document.getElementById('itemQuantity').value);
            var unitId = document.getElementById('itemUnit').value;
            var supplierId = document.getElementById('itemSupplier').value;
            var date = document.getElementById('transactionDate').value;
            var type = document.querySelector('input[name="transactionType"]:checked').value;
            var categoryName = lookupData.categories.find(function(c) { return c.inventory_category_id == categoryId; })?.inventory_category_name || '';
            var unitName = lookupData.units.find(function(u) { return u.unit_id == unitId; })?.unit_name || '';
            var supplierName = lookupData.suppliers.find(function(s) { return s.supplier_id == supplierId; })?.supplier_name || '';

            function normalize(text) {
                return String(text || '').trim().toLowerCase();
            }

            // First, check if item already exists, if not create it
            var existingItem = inventoryItems.find(function(i) {
                return normalize(i.item_name) === normalize(itemName)
                    && normalize(i.category) === normalize(categoryName)
                    && normalize(i.unit) === normalize(unitName)
                    && normalize(i.supplier) === normalize(supplierName);
            });

            var itemId;
            if (existingItem) {
                itemId = existingItem.item_id;
                addTransaction(itemId, type, quantity, date);
            } else {
                // Create new item first
                var itemPayload = {
                    item_name: itemName,
                    inventory_category_id: categoryId,
                    supplier_id: supplierId,
                    unit_id: unitId,
                    current_stock: 0,
                    reorder_level: 0
                };

                fetch('/api/inventory/item', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(itemPayload)
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        itemId = data.data.item_id;
                        addTransaction(itemId, type, quantity, date);
                    } else {
                        alert('Failed to create item: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(function(err) {
                    console.error('Error creating item:', err);
                    alert('Failed to create item.');
                });
            }
        }

        function addTransaction(itemId, type, quantity, date) {
            var payload = {
                item_id: itemId,
                project_id: null,
                transaction_type: type,
                quantity: quantity,
                transaction_date: date
            };

            fetch('/api/inventory/transaction', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    closeModal();
                    showSuccess(data.message || 'Transaction added successfully!');
                    loadInventoryItems();
                } else {
                    alert('Failed to add transaction: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(function(err) {
                console.error('Error adding transaction:', err);
                alert('Failed to add transaction.');
            });
        }

        // ─── SUCCESS NOTIFICATION ───
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = notif.querySelector('.success-content span:not(.success-icon)');
            if (msgSpan) msgSpan.textContent = message || 'Transaction added successfully!';
            notif.style.display = 'block';
            setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
        }

        var deleteCallback = null;

        function openDeleteModal(message, callback) {
            document.getElementById('deleteConfirmMessage').textContent = message || 'Are you sure you want to permanently delete this transaction?';
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

        function showError(message) {
            document.getElementById('errorMessage').textContent = message || 'An error occurred. Please try again.';
            document.getElementById('errorNotification').style.display = 'block';
        }

        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
        }

        function populateViewDropdowns() {
            var categorySelect = document.getElementById('viewCategoryInput');
            var unitSelect = document.getElementById('viewUnitInput');
            var supplierSelect = document.getElementById('viewSupplierInput');
            if (!categorySelect || !unitSelect || !supplierSelect) return;

            categorySelect.innerHTML = '<option value="">Select Category...</option>';
            lookupData.categories.forEach(function(cat) {
                var opt = document.createElement('option');
                opt.value = cat.inventory_category_name;
                opt.textContent = cat.inventory_category_name;
                categorySelect.appendChild(opt);
            });

            unitSelect.innerHTML = '<option value="">Select Unit...</option>';
            lookupData.units.forEach(function(unit) {
                var opt = document.createElement('option');
                opt.value = unit.unit_name;
                opt.textContent = unit.unit_name;
                unitSelect.appendChild(opt);
            });

            supplierSelect.innerHTML = '<option value="">Select Supplier...</option>';
            lookupData.suppliers.forEach(function(sup) {
                var opt = document.createElement('option');
                opt.value = sup.supplier_id;
                opt.textContent = sup.supplier_name;
                supplierSelect.appendChild(opt);
            });
        }

        // ─── VIEW/EDIT MODAL ───
        var currentRow = null;
        var isEditMode = false;

        function openViewModal(row) {
            currentRow = row;
            isEditMode = false;
            var id = row.dataset.id || '';
            var itemId = row.dataset.itemId || '';
            var item = row.dataset.item || '';
            var category = row.dataset.category || '';
            var unit = row.dataset.unit || '';
            var quantity = row.dataset.quantity || '';
            var supplier = row.dataset.supplier || '';
            var supplierId = row.dataset.supplierId || '';
            var type = row.dataset.type || '';
            var date = row.dataset.date || '';
            var stock = row.dataset.stock || '';
            var status = row.dataset.status || '';
            var project = row.dataset.project || '';

            var selectedItem = inventoryItems.find(function(i) {
                return i.item_id == itemId;
            });

            if (selectedItem) {
                category = category || selectedItem.category || '';
                unit = unit || selectedItem.unit || '';
                supplier = supplier || selectedItem.supplier || '';
                supplierId = supplierId || selectedItem.supplier_id || '';
            }

            if (!supplierId && supplier) {
                var supplierRow = lookupData.suppliers.find(function(s) {
                    return s.supplier_name === supplier;
                });
                supplierId = supplierRow ? supplierRow.supplier_id : '';
            }

            populateViewDropdowns();
            document.getElementById('viewTransactionId').value = id;
            document.getElementById('viewItemId').value = itemId;
            document.getElementById('viewItemNameDisplay').textContent = item;
            document.getElementById('viewItemNameInput').value = item;
            document.getElementById('viewCategoryDisplay').textContent = category;
            document.getElementById('viewCategoryInput').value = category;
            document.getElementById('viewUnitDisplay').textContent = unit;
            document.getElementById('viewUnitInput').value = unit;
            document.getElementById('viewQuantityDisplay').textContent = quantity;
            document.getElementById('viewQuantityInput').value = quantity;
            document.getElementById('viewSupplierDisplay').textContent = supplier;
            document.getElementById('viewSupplierInput').value = supplierId;
            document.getElementById('viewTypeDisplay').textContent = type;
            document.getElementById('viewTypeInput').value = type;
            document.getElementById('viewDateDisplay').textContent = date;
            document.getElementById('viewDateInput').value = date;
            document.getElementById('viewStockDisplay').textContent = stock;
            var statusEl = document.getElementById('viewStatusDisplay');
            statusEl.textContent = status;
            statusEl.className = 'view-value status-badge';
            if (status === 'In Stock') statusEl.classList.add('in-stock');
            else if (status === 'Low Stock') statusEl.classList.add('low-stock');
            else if (status === 'Out of Stock') statusEl.classList.add('out-of-stock');

            var projectRow = document.getElementById('viewProjectRow');
            if (type === 'OUT' && project) {
                projectRow.style.display = 'flex';
                document.getElementById('viewProjectDisplay').textContent = project;
                document.getElementById('viewProjectInput').value = project;
            } else {
                projectRow.style.display = 'none';
            }

            disableEditMode();
            document.getElementById('viewModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
            document.body.style.overflow = '';
            currentRow = null;
        }

        function enableEditMode() {
            isEditMode = true;
            document.querySelectorAll('#viewModal .view-value').forEach(el => {
                if (el.id === 'viewStockDisplay' || el.id === 'viewStatusDisplay') {
                    el.style.display = 'block';
                    return;
                }
                el.style.display = 'none';
            });
            document.querySelectorAll('#viewModal .view-input').forEach(el => el.style.display = 'block');
            document.getElementById('viewEditBtn').style.display = 'none';
            document.getElementById('viewDeleteBtn').style.display = 'none';
            document.getElementById('viewSaveBtn').style.display = 'inline-block';
            var projectRow = document.getElementById('viewProjectRow');
            if (projectRow.style.display !== 'none') {
                document.getElementById('viewProjectDisplay').style.display = 'none';
                document.getElementById('viewProjectInput').style.display = 'block';
            }
            populateViewDropdowns();
        }

        function disableEditMode() {
            isEditMode = false;
            document.querySelectorAll('#viewModal .view-value').forEach(el => el.style.display = 'block');
            document.querySelectorAll('#viewModal .view-input').forEach(el => el.style.display = 'none');
            document.getElementById('viewEditBtn').style.display = 'inline-block';
            document.getElementById('viewDeleteBtn').style.display = 'inline-block';
            document.getElementById('viewSaveBtn').style.display = 'none';
            var projectRow = document.getElementById('viewProjectRow');
            if (projectRow.style.display !== 'none') {
                document.getElementById('viewProjectDisplay').style.display = 'block';
                document.getElementById('viewProjectInput').style.display = 'none';
            }
        }

        function saveEdit() {
            if (!currentRow) return;
            var transactionId = currentRow.dataset.id || '';
            var itemId = document.getElementById('viewItemId').value || currentRow.dataset.itemId || '';
            var itemName = document.getElementById('viewItemNameInput').value.trim();
            var categoryName = document.getElementById('viewCategoryInput').value;
            var unitName = document.getElementById('viewUnitInput').value;
            var supplierId = document.getElementById('viewSupplierInput').value;
            var quantity = parseFloat(document.getElementById('viewQuantityInput').value);
            var type = document.getElementById('viewTypeInput').value;
            var date = document.getElementById('viewDateInput').value;
            var project = document.getElementById('viewProjectInput').value.trim();

            if (!transactionId) { showError('Transaction ID missing.'); return; }
            if (!itemId) { showError('Item ID missing.'); return; }
            if (!itemName) { showError('Please enter an item name.'); return; }
            if (!categoryName) { showError('Please select a category.'); return; }
            if (!unitName) { showError('Please select a unit.'); return; }
            if (!supplierId) { showError('Please select a supplier.'); return; }
            if (!quantity || quantity < 0.01) {
                showError('Please enter a valid quantity.');
                return;
            }
            if (!type || !date) {
                showError('Please fill in all required fields.');
                return;
            }
            if (type === 'OUT' && !project) {
                showError('Project is required for OUT transactions.');
                return;
            }

            var category = lookupData.categories.find(function(c) { return c.inventory_category_name === categoryName; });
            var unit = lookupData.units.find(function(u) { return u.unit_name === unitName; });

            if (!category || !unit) {
                showError('Invalid category or unit.');
                return;
            }

            var payload = {
                item_name: itemName,
                inventory_category_id: category.inventory_category_id,
                unit_id: unit.unit_id,
                supplier_id: parseInt(supplierId, 10),
                quantity: quantity,
                transaction_type: type,
                transaction_date: date,
                project_id: project || null
            };

            fetch('/api/inventory/transaction/' + transactionId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    closeViewModal();
                    showSuccess(data.message || 'Transaction updated successfully!');
                    loadInventoryItems();
                } else {
                    showError(data.message || 'Failed to save changes.');
                }
            })
            .catch(function(err) {
                console.error('Error saving transaction:', err);
                showError('Failed to save changes.');
            });
        }

        function deleteTransaction() {
            if (!currentRow) return;
            var transactionId = currentRow.dataset.id || '';
            if (!transactionId) {
                showError('Transaction ID missing.');
                return;
            }

            openDeleteModal('Are you sure you want to permanently delete this transaction?', function() {
                fetch('/api/inventory/transaction/' + transactionId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (data.success) {
                        closeViewModal();
                        showSuccess(data.message || 'Transaction deleted successfully!');
                        loadInventoryItems();
                    } else {
                        showError(data.message || 'Failed to delete transaction.');
                    }
                })
                .catch(function(err) {
                    console.error('Error deleting transaction:', err);
                    showError('Failed to delete transaction.');
                });
            });
        }

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) { closeViewModal(); }
        });

        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) { closeDeleteModal(); }
        });

        // ─── CLOSE MODAL ON BACKDROP CLICK ───
        document.getElementById('transactionModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // ─── CLOSE SUCCESS ON CLICK OUTSIDE ───
        document.addEventListener('click', function(e) {
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) {
                    closeSuccess();
                }
            }
        });

        // ─── INIT ───
        document.addEventListener('DOMContentLoaded', function() {
            loadLookupData();
        });
    </script>

</body>
</html>