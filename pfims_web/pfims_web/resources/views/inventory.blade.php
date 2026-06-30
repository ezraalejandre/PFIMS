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

    <!-- ─── SUCCESS NOTIFICATION ─── -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span>Transaction added successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    @include('partials.header')

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
                tbody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 20px;">No transactions found.</td></tr>';
                return;
            }

            allTransactions.forEach(function(row) {
                var tr = document.createElement('tr');
                var status = row.current_stock > row.reorder_level ? 'in-stock' : 'low-stock';
                var statusText = row.current_stock > row.reorder_level ? 'In Stock' : 'Low Stock';
                var typeLabel = row.transaction_type || '—';
                var dateValue = row.transaction_date ? new Date(row.transaction_date).toLocaleDateString() : '—';

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