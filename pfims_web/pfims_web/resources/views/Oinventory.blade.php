<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Inventory - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Oinventory.css') }}">
    <style>
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
            <span id="successMessage">Transaction added successfully!</span>
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
                <li><a href="{{ url('/oprojects') }}">PROJECTS</a></li>
                <li class="active"><a href="{{ url('/oinventory') }}">INVENTORY</a></li>
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

        <!-- Page Header -->
        <div class="page-header">
            <h1>INVENTORY RECORDS</h1>
            <button class="btn-add-transaction" onclick="openModal()">+ Add Transaction</button>
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
                    <tr data-id="1"
                        data-item="Portland Cement"
                        data-category="Cement"
                        data-unit="bags"
                        data-quantity="150"
                        data-supplier="Holcim Philippines"
                        data-type="IN"
                        data-date="2026-05-01"
                        data-stock="450"
                        data-status="In Stock"
                        data-project=""
                        onclick="openViewModal(this)">
                        <td><strong>Portland Cement</strong></td>
                        <td>Cement</td>
                        <td>bags</td>
                        <td>150</td>
                        <td>Holcim Philippines</td>
                        <td><span class="type-badge in">IN</span></td>
                        <td>2026-05-01</td>
                        <td>450</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                        <td style="text-align: center;">
                            <span class="action-icon" onclick="event.stopPropagation(); openViewModal(this.closest('tr'));">👁️</span>
                        </td>
                    </tr>
                    <tr data-id="2"
                        data-item="Steel Rebar 12mm"
                        data-category="Steel"
                        data-unit="pcs"
                        data-quantity="80"
                        data-supplier="Metro Steel Supply"
                        data-type="OUT"
                        data-date="2026-05-02"
                        data-stock="220"
                        data-status="In Stock"
                        data-project="Skyline Tower"
                        onclick="openViewModal(this)">
                        <td><strong>Steel Rebar 12mm</strong></td>
                        <td>Steel</td>
                        <td>pcs</td>
                        <td>80</td>
                        <td>Metro Steel Supply</td>
                        <td><span class="type-badge out">OUT</span></td>
                        <td>2026-05-02</td>
                        <td>220</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                        <td style="text-align: center;">
                            <span class="action-icon" onclick="event.stopPropagation(); openViewModal(this.closest('tr'));">👁️</span>
                        </td>
                    </tr>
                    <tr data-id="3"
                        data-item="White Latex Paint"
                        data-category="Paint"
                        data-unit="gallons"
                        data-quantity="40"
                        data-supplier="ColorPro Paint Center"
                        data-type="IN"
                        data-date="2026-05-03"
                        data-stock="95"
                        data-status="In Stock"
                        data-project=""
                        onclick="openViewModal(this)">
                        <td><strong>White Latex Paint</strong></td>
                        <td>Paint</td>
                        <td>gallons</td>
                        <td>40</td>
                        <td>ColorPro Paint Center</td>
                        <td><span class="type-badge in">IN</span></td>
                        <td>2026-05-03</td>
                        <td>95</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                        <td style="text-align: center;">
                            <span class="action-icon" onclick="event.stopPropagation(); openViewModal(this.closest('tr'));">👁️</span>
                        </td>
                    </tr>
                    <tr data-id="4"
                        data-item="Gravel 3/4"
                        data-category="Aggregates"
                        data-unit="tons"
                        data-quantity="25"
                        data-supplier="Laguna Aggregate Supplier"
                        data-type="OUT"
                        data-date="2026-05-04"
                        data-stock="70"
                        data-status="In Stock"
                        data-project="Harbor Bridge Annex"
                        onclick="openViewModal(this)">
                        <td><strong>Gravel 3/4</strong></td>
                        <td>Aggregates</td>
                        <td>tons</td>
                        <td>25</td>
                        <td>Laguna Aggregate Supplier</td>
                        <td><span class="type-badge out">OUT</span></td>
                        <td>2026-05-04</td>
                        <td>70</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                        <td style="text-align: center;">
                            <span class="action-icon" onclick="event.stopPropagation(); openViewModal(this.closest('tr'));">👁️</span>
                        </td>
                    </tr>
                    <tr data-id="5"
                        data-item="Concrete Hollow Blocks"
                        data-category="Masonry"
                        data-unit="pcs"
                        data-quantity="500"
                        data-supplier="SolidBlocks Manufacturing"
                        data-type="IN"
                        data-date="2026-05-05"
                        data-stock="1200"
                        data-status="In Stock"
                        data-project=""
                        onclick="openViewModal(this)">
                        <td><strong>Concrete Hollow Blocks</strong></td>
                        <td>Masonry</td>
                        <td>pcs</td>
                        <td>500</td>
                        <td>SolidBlocks Manufacturing</td>
                        <td><span class="type-badge in">IN</span></td>
                        <td>2026-05-05</td>
                        <td>1200</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                        <td style="text-align: center;">
                            <span class="action-icon" onclick="event.stopPropagation(); openViewModal(this.closest('tr'));">👁️</span>
                        </td>
                    </tr>
                    <tr data-id="6"
                        data-item="PVC Pipe 2-inch"
                        data-category="Plumbing"
                        data-unit="pcs"
                        data-quantity="35"
                        data-supplier="AquaFlow Industrial Supply"
                        data-type="OUT"
                        data-date="2026-05-06"
                        data-stock="60"
                        data-status="In Stock"
                        data-project="Green Hills Residences"
                        onclick="openViewModal(this)">
                        <td><strong>PVC Pipe 2-inch</strong></td>
                        <td>Plumbing</td>
                        <td>pcs</td>
                        <td>35</td>
                        <td>AquaFlow Industrial Supply</td>
                        <td><span class="type-badge out">OUT</span></td>
                        <td>2026-05-06</td>
                        <td>60</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                        <td style="text-align: center;">
                            <span class="action-icon" onclick="event.stopPropagation(); openViewModal(this.closest('tr'));">👁️</span>
                        </td>
                    </tr>
                    <tr data-id="7"
                        data-item="Electrical Wire 5.5mm"
                        data-category="Electrical"
                        data-unit="rolls"
                        data-quantity="20"
                        data-supplier="PowerLine Electricals"
                        data-type="IN"
                        data-date="2026-05-07"
                        data-stock="45"
                        data-status="In Stock"
                        data-project=""
                        onclick="openViewModal(this)">
                        <td><strong>Electrical Wire 5.5mm</strong></td>
                        <td>Electrical</td>
                        <td>rolls</td>
                        <td>20</td>
                        <td>PowerLine Electricals</td>
                        <td><span class="type-badge in">IN</span></td>
                        <td>2026-05-07</td>
                        <td>45</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                        <td style="text-align: center;">
                            <span class="action-icon" onclick="event.stopPropagation(); openViewModal(this.closest('tr'));">👁️</span>
                        </td>
                    </tr>
                    <tr data-id="8"
                        data-item="Ceramic Floor Tiles"
                        data-category="Finishing"
                        data-unit="boxes"
                        data-quantity="60"
                        data-supplier="Prime Tiles Depot"
                        data-type="OUT"
                        data-date="2026-05-08"
                        data-stock="140"
                        data-status="In Stock"
                        data-project="Eastwood Mall"
                        onclick="openViewModal(this)">
                        <td><strong>Ceramic Floor Tiles</strong></td>
                        <td>Finishing</td>
                        <td>boxes</td>
                        <td>60</td>
                        <td>Prime Tiles Depot</td>
                        <td><span class="type-badge out">OUT</span></td>
                        <td>2026-05-08</td>
                        <td>140</td>
                        <td><span class="status-badge in-stock"><span class="dot"></span> In Stock</span></td>
                        <td style="text-align: center;">
                            <span class="action-icon" onclick="event.stopPropagation(); openViewModal(this.closest('tr'));">👁️</span>
                        </td>
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
                <h2>Add Transaction</h2>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>

            <div class="step-indicator">
                <span class="step active" id="step1Indicator">
                    <span class="step-number">1</span> Transaction Details
                </span>
                <span class="step" id="step2Indicator">
                    <span class="step-number">2</span> Review
                </span>
            </div>

            <div class="modal-step" id="step1">
                <h3>Item Information</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>Item Name</label>
                        <select id="itemNameSelect">
                            <option value="">Select Item...</option>
                        </select>
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

                <div class="form-row" id="projectRow" style="display: none; margin-top: 10px;">
                    <div class="form-group">
                        <label>Project Name</label>
                        <select id="projectName">
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
                </div>

                <hr style="border: none; border-top: 1px solid #e9ecef; margin: 15px 0 20px;">

                <h3>Transaction Details</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>Transaction Type</label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="transactionType" value="IN" checked onchange="toggleProjectField()">
                                IN
                                <span class="radio-sub">Item Stock in</span>
                            </label>
                            <label>
                                <input type="radio" name="transactionType" value="OUT" onchange="toggleProjectField()">
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
                    <div class="summary-item" id="reviewProjectRow" style="display: none;">
                        <strong>Project Name</strong>
                        <span class="summary-value" id="reviewProjectName">—</span>
                    </div>
                </div>

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

    <!-- ─── VIEW MODAL (Read-Only – no Edit/Delete) ─── -->
    <div id="viewModal" class="modal-overlay modal-view">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="viewModalTitle">Transaction Details</h2>
                <button class="modal-close" onclick="closeViewModal()">×</button>
            </div>

            <div class="view-details-grid">
                <div class="view-item">
                    <label>Item Name</label>
                    <span id="viewItemNameDisplay" class="view-value">—</span>
                </div>
                <div class="view-item">
                    <label>Category</label>
                    <span id="viewCategoryDisplay" class="view-value">—</span>
                </div>
                <div class="view-item">
                    <label>Unit</label>
                    <span id="viewUnitDisplay" class="view-value">—</span>
                </div>
                <div class="view-item">
                    <label>Quantity</label>
                    <span id="viewQuantityDisplay" class="view-value">—</span>
                </div>
                <div class="view-item">
                    <label>Supplier</label>
                    <span id="viewSupplierDisplay" class="view-value">—</span>
                </div>
                <div class="view-item">
                    <label>Transaction Type</label>
                    <span id="viewTypeDisplay" class="view-value">—</span>
                </div>
                <div class="view-item">
                    <label>Date</label>
                    <span id="viewDateDisplay" class="view-value">—</span>
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
                </div>
            </div>

            <div class="modal-footer" style="justify-content: flex-end;">
                <button class="btn-cancel" onclick="closeViewModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
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
            document.getElementById('successMessage').textContent = message || 'Transaction added successfully!';
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

        function populateItemDropdowns() {
            var rows = document.querySelectorAll('#inventoryTableBody tr');
            var items = new Set();
            rows.forEach(function(row) {
                var item = row.dataset.item;
                if (item) items.add(item);
            });
            var itemArray = Array.from(items).sort();

            var selectAdd = document.getElementById('itemNameSelect');
            var currentValue = selectAdd.value;
            selectAdd.innerHTML = '<option value="">Select Item...</option>';
            itemArray.forEach(function(item) {
                var opt = document.createElement('option');
                opt.value = item;
                opt.textContent = item;
                selectAdd.appendChild(opt);
            });
            if (currentValue && items.has(currentValue)) {
                selectAdd.value = currentValue;
            }
        }

        function toggleProjectField() {
            var typeRadios = document.querySelectorAll('input[name="transactionType"]');
            var selected = Array.from(typeRadios).find(r => r.checked);
            var projectRow = document.getElementById('projectRow');
            if (selected && selected.value === 'OUT') {
                projectRow.style.display = 'grid';
            } else {
                projectRow.style.display = 'none';
            }
        }

        function openModal() {
            document.getElementById('transactionModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            goToStep(1);
            document.getElementById('itemNameSelect').value = '';
            document.getElementById('itemCategory').value = '';
            document.getElementById('itemQuantity').value = 1;
            document.getElementById('itemUnit').value = '';
            document.getElementById('itemSupplier').value = '';
            document.querySelector('input[name="transactionType"][value="IN"]').checked = true;
            document.getElementById('transactionDate').value = '2026-05-10';
            document.getElementById('projectName').value = '';
            toggleProjectField();
            document.getElementById('reviewItemName').textContent = '—';
            document.getElementById('reviewItemCategory').textContent = '—';
            document.getElementById('reviewItemSupplier').textContent = '—';
            document.getElementById('reviewItemQuantity').textContent = '—';
            document.getElementById('reviewItemUnit').textContent = '—';
            document.getElementById('reviewProjectName').textContent = '—';
            document.getElementById('reviewProjectRow').style.display = 'none';
            document.getElementById('reviewTransactionDate').textContent = '—';
            populateItemDropdowns();
        }

        function closeModal() {
            document.getElementById('transactionModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        var currentStep = 1;

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
            var name = document.getElementById('itemNameSelect').value;
            var category = document.getElementById('itemCategory').value;
            var quantity = document.getElementById('itemQuantity').value;
            var unit = document.getElementById('itemUnit').value;
            var supplier = document.getElementById('itemSupplier').value;
            var date = document.getElementById('transactionDate').value;

            if (!name) { showError('Please select an item.'); return; }
            if (!category) { showError('Please select an item category.'); return; }
            if (!quantity || quantity < 1) { showError('Please enter a valid quantity (minimum 1).'); return; }
            if (!unit) { showError('Please select an item unit.'); return; }
            if (!supplier) { showError('Please select a supplier.'); return; }
            if (!date) { showError('Please select a transaction date.'); return; }

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

            var projectRowReview = document.getElementById('reviewProjectRow');
            var projectName = document.getElementById('projectName').value;
            if (typeLabel === 'OUT') {
                if (!projectName) {
                    showError('Please select a project for OUT transaction.');
                    return;
                }
                document.getElementById('reviewProjectName').textContent = projectName;
                projectRowReview.style.display = 'flex';
            } else {
                projectRowReview.style.display = 'none';
            }

            goToStep(step);
        }

        function prevStep(step) {
            goToStep(step);
        }

        function changeQuantity(delta) {
            var input = document.getElementById('itemQuantity');
            var val = parseInt(input.value) || 1;
            val = Math.max(1, val + delta);
            input.value = val;
        }

        function saveTransaction() {
            closeModal();
            showSuccess('Transaction added successfully!');
            populateItemDropdowns();
        }

        function openViewModal(row) {
            var item = row.dataset.item || '';
            var category = row.dataset.category || '';
            var unit = row.dataset.unit || '';
            var quantity = row.dataset.quantity || '';
            var supplier = row.dataset.supplier || '';
            var type = row.dataset.type || '';
            var date = row.dataset.date || '';
            var stock = row.dataset.stock || '';
            var status = row.dataset.status || '';
            var project = row.dataset.project || '';

            document.getElementById('viewItemNameDisplay').textContent = item;
            document.getElementById('viewCategoryDisplay').textContent = category;
            document.getElementById('viewUnitDisplay').textContent = unit;
            document.getElementById('viewQuantityDisplay').textContent = quantity;
            document.getElementById('viewSupplierDisplay').textContent = supplier;
            document.getElementById('viewTypeDisplay').textContent = type;
            document.getElementById('viewDateDisplay').textContent = date;
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
            } else {
                projectRow.style.display = 'none';
            }

            document.getElementById('viewModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        document.getElementById('transactionModal').addEventListener('click', function(e) {
            if (e.target === this) { closeModal(); }
        });

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) { closeViewModal(); }
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
            populateItemDropdowns();
        });
    </script>

</body>
</html>