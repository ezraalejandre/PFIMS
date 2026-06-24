<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
                <tbody>
                    <tr>
                        <td><strong>Portland Cement</strong></td>
                        <td>Cement</td>
                        <td>bags</td>
                        <td>150</td>
                        <td>Holcim Philippines</td>
                        <td><span class="type-badge in">IN</span></td>
                        <td>2026-05-01</td>
                        <td>450</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                    </tr>
                    <tr>
                        <td><strong>Steel Rebar 12mm</strong></td>
                        <td>Steel</td>
                        <td>pcs</td>
                        <td>80</td>
                        <td>Metro Steel Supply</td>
                        <td><span class="type-badge out">OUT</span></td>
                        <td>02-05-2026</td>
                        <td>220</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                    </tr>
                    <tr>
                        <td><strong>White Latex Paint</strong></td>
                        <td>Paint</td>
                        <td>gallons</td>
                        <td>40</td>
                        <td>ColorPro Paint Center</td>
                        <td><span class="type-badge in">IN</span></td>
                        <td>03-05-2026</td>
                        <td>95</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                    </tr>
                    <tr>
                        <td><strong>Gravel 3/4</strong></td>
                        <td>Aggregates</td>
                        <td>tons</td>
                        <td>25</td>
                        <td>Laguna Aggregate Supplier</td>
                        <td><span class="type-badge out">OUT</span></td>
                        <td>04-05-2026</td>
                        <td>70</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                    </tr>
                    <tr>
                        <td><strong>Concrete Hollow Blocks</strong></td>
                        <td>Masonry</td>
                        <td>pcs</td>
                        <td>500</td>
                        <td>SolidBlocks Manufacturing</td>
                        <td><span class="type-badge in">IN</span></td>
                        <td>05-05-2026</td>
                        <td>1200</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                    </tr>
                    <tr>
                        <td><strong>PVC Pipe 2-inch</strong></td>
                        <td>Plumbing</td>
                        <td>pcs</td>
                        <td>35</td>
                        <td>AquaFlow Industrial Supply</td>
                        <td><span class="type-badge out">OUT</span></td>
                        <td>06-05-2026</td>
                        <td>60</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                    </tr>
                    <tr>
                        <td><strong>Electrical Wire 5.5mm</strong></td>
                        <td>Electrical</td>
                        <td>rolls</td>
                        <td>20</td>
                        <td>PowerLine Electricals</td>
                        <td><span class="type-badge in">IN</span></td>
                        <td>07-05-2026</td>
                        <td>45</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                    </tr>
                    <tr>
                        <td><strong>Ceramic Floor Tiles</strong></td>
                        <td>Finishing</td>
                        <td>boxes</td>
                        <td>60</td>
                        <td>Prime Tiles Depot</td>
                        <td><span class="type-badge out">OUT</span></td>
                        <td>08-05-2026</td>
                        <td>140</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
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
                            <option value="">Choose Category...</option>
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
                            <option value="">Unit...</option>
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
                    <div class="form-group">
                        <label>Item Supplier</label>
                        <select id="itemSupplier">
                            <option value="">Choose Supplier...</option>
                            <option value="Holcim Philippines">Holcim Philippines</option>
                            <option value="Metro Steel Supply">Metro Steel Supply</option>
                            <option value="ColorPro Paint Center">ColorPro Paint Center</option>
                            <option value="Laguna Aggregate Supplier">Laguna Aggregate Supplier</option>
                            <option value="SolidBlocks Manufacturing">SolidBlocks Manufacturing</option>
                            <option value="AquaFlow Industrial Supply">AquaFlow Industrial Supply</option>
                            <option value="PowerLine Electricals">PowerLine Electricals</option>
                            <option value="Prime Tiles Depot">Prime Tiles Depot</option>
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
        // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
            // The link will still navigate to /notifications
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
            document.getElementById('transactionDate').value = '2026-05-10';
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
            var name = document.getElementById('itemName').value.trim();
            var category = document.getElementById('itemCategory').value;
            var quantity = document.getElementById('itemQuantity').value;
            var unit = document.getElementById('itemUnit').value;
            var supplier = document.getElementById('itemSupplier').value;
            var date = document.getElementById('transactionDate').value;

            if (!name) { alert('Please enter the item name.'); return; }
            if (!category) { alert('Please select an item category.'); return; }
            if (!quantity || quantity < 1) { alert('Please enter a valid quantity (minimum 1).'); return; }
            if (!unit) { alert('Please select an item unit.'); return; }
            if (!supplier) { alert('Please select a supplier.'); return; }
            if (!date) { alert('Please select a transaction date.'); return; }

            // Populate review
            document.getElementById('reviewItemName').textContent = name;
            document.getElementById('reviewItemCategory').textContent = category;
            document.getElementById('reviewItemSupplier').textContent = supplier;
            document.getElementById('reviewItemQuantity').textContent = quantity;
            document.getElementById('reviewItemUnit').textContent = unit;

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
    </script>

</body>
</html>