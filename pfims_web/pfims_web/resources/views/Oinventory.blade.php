<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Operations Inventory - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Oinventory.css') }}">
    <style>
        #deleteConfirmModal { z-index: 9999 !important; }
        
        /* Add Item Modal specific styles */
        .modal-add-item .modal-container {
            max-width: 500px;
        }
        
        /* View Modal styles */
        .modal-view .modal-container {
            max-width: 700px;
        }
        
        .view-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px 30px;
            padding: 20px;
            background: #faf8f5;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .view-item {
            display: flex;
            flex-direction: column;
        }
        
        .view-item label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #888;
            margin-bottom: 2px;
        }
        
        .view-item .view-value {
            font-size: 1rem;
            font-weight: 500;
            color: #1a2b3c;
            padding: 4px 0;
        }
        
        .view-item .view-input {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 0.95rem;
            background: #fff;
            transition: 0.3s;
        }
        
        .view-item .view-input:focus {
            outline: none;
            border-color: #c9a96e;
            box-shadow: 0 0 0 3px rgba(201, 169, 110, 0.2);
        }
        
        .view-item .status-badge {
            font-size: 0.9rem;
        }
        
        /* Button group */
        .btn-group {
            display: flex;
            gap: 10px;
        }
        
        .btn-add-item {
            background: #c9a96e;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.3s;
            white-space: nowrap;
        }
        
        .btn-add-item:hover {
            background: #b8975a;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(201, 169, 110, 0.3);
        }
        
        .btn-add-transaction {
            background: #1a2b3c;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: 0.3s;
            white-space: nowrap;
        }
        
        .btn-add-transaction:hover {
            background: #2a3f54;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(26, 43, 60, 0.3);
        }
        
        @media (max-width: 650px) {
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            .btn-group button {
                width: 100%;
                justify-content: center;
                text-align: center;
            }
            .view-details-grid {
                grid-template-columns: 1fr;
                gap: 12px;
                padding: 15px;
            }
            .modal-add-item .modal-container {
                padding: 20px;
            }
        }
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
            <span id="successMessage">Action completed successfully!</span>
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
                <span>{{ auth()->user()->name }}</span>
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
            <h1>INVENTORY RECORDS</h1>
            <div class="btn-group">
                <button class="btn-add-item" onclick="openAddItemModal()">+ Add Item</button>
                <button class="btn-add-transaction" onclick="openTransactionModal()">+ Add Transaction</button>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid-inv">
            <div class="stat-card-inv">
                <div class="stat-label">Total Items</div>
                <div class="stat-value" id="totalItemsCount">0</div>
                <div class="stat-sub" id="totalItemsSub">Across all transactions</div>
            </div>
            <div class="stat-card-inv">
                <div class="stat-label">Low Stock Items</div>
                <div class="stat-value" id="lowStockCount">0</div>
                <div class="stat-sub" id="lowStockSub">Items for restocking</div>
            </div>
            <div class="stat-card-inv">
                <div class="stat-label">Categories</div>
                <div class="stat-value" id="categoriesCount">0</div>
                <div class="stat-sub" id="categoriesSub">Item categories</div>
            </div>
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <input type="text" class="search-input" placeholder="Search transactions..." id="searchInput" oninput="filterTable()">
            <select id="typeFilter" onchange="filterTable()">
                <option value="all">All Transactions</option>
                <option value="IN">IN</option>
                <option value="OUT">OUT</option>
            </select>
            <input type="date" class="date-input" id="startDate" value="{{ date('Y-m-d', strtotime('-30 days')) }}">
            <input type="date" class="date-input" id="endDate" value="{{ date('Y-m-d') }}">
            <button class="btn-add-transaction" onclick="applyFilters()">Apply Filters</button>
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
                    <tr><td colspan="10" style="text-align: center; padding: 20px;">Loading inventory items...</td></tr>
                </tbody>
            </table>
        </div>

        <!-- ─── PAGINATION ─── -->
        <div class="pagination-wrapper">
            <div class="rows-info">
                Rows Displayed:
                <select id="inventoryRowsPerPage" onchange="changeInventoryPageSize()">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>
            <div class="pagination-links" id="inventoryPaginationLinks">
                <!-- Generated by JavaScript -->
            </div>
        </div>

    </main>

    <!-- ─── ADD ITEM MODAL ─── -->
    <div id="addItemModal" class="modal-overlay modal-add-item">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Add New Item</h2>
                <button class="modal-close" onclick="closeAddItemModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>Item Name <span class="required">*</span></label>
                    <input type="text" id="newItemName" placeholder="e.g. Plywood 1/2">
                </div>
                <div class="form-group">
                    <label>Category <span class="required">*</span></label>
                    <select id="newItemCategory">
                        <option value="">Select Category...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Unit <span class="required">*</span></label>
                    <select id="newItemUnit">
                        <option value="">Select Unit...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supplier <span class="required">*</span></label>
                    <select id="newItemSupplier">
                        <option value="">Select Supplier...</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Reorder Level</label>
                    <input type="number" id="newItemReorderLevel" placeholder="e.g. 10" min="0" value="5">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddItemModal()">Cancel</button>
                <button class="btn-save" onclick="saveNewItem()">Add Item</button>
            </div>
        </div>
    </div>

    <!-- ─── VIEW/EDIT MODAL ─── -->
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
                        <option value="">Select Category...</option>
                    </select>
                </div>
                <div class="view-item">
                    <label>Unit</label>
                    <span id="viewUnitDisplay" class="view-value">—</span>
                    <select id="viewUnitInput" class="view-input" style="display: none;">
                        <option value="">Select Unit...</option>
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

    <!-- ─── ADD TRANSACTION MODAL ─── -->
    <div id="transactionModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Add New Transaction</h2>
                <button class="modal-close" onclick="closeTransactionModal()">×</button>
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

                <!-- Item Name - Populates other fields -->
                <div class="form-group">
                    <label>Item Name <span class="required">*</span></label>
                    <select id="transactionItemSelect" onchange="populateItemFields()">
                        <option value="">Select Item...</option>
                    </select>
                    <span style="font-size: 0.75rem; color: #888; margin-top: 4px; display: block;">Select an existing item or use "Add Item" button to create a new one</span>
                </div>

                <!-- Auto-populated fields -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Item Category</label>
                        <input type="text" id="transactionItemCategory" readonly style="background: #f5f5f5; color: #555;">
                    </div>
                    <div class="form-group">
                        <label>Item Unit</label>
                        <input type="text" id="transactionItemUnit" readonly style="background: #f5f5f5; color: #555;">
                    </div>
                </div>

                <div class="form-group">
                    <label>Item Supplier</label>
                    <input type="text" id="transactionItemSupplier" readonly style="background: #f5f5f5; color: #555;">
                </div>

                <!-- Project Selection for OUT transactions -->
                <div class="form-group" id="transactionProjectGroup" style="display: none;">
                    <label>Project Name <span class="required" style="display:none;" id="transactionProjectRequired">*</span></label>
                    <select id="transactionProject">
                        <option value="">Select Project...</option>
                    </select>
                </div>

                <!-- Separator Line -->
                <hr style="border: none; border-top: 1px solid #e9ecef; margin: 15px 0 20px;">

                <h3>Transaction Details</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label>Item Quantity <span class="required">*</span></label>
                        <div class="quantity-control">
                            <button type="button" onclick="changeTransactionQuantity(-1)">−</button>
                            <input type="number" id="transactionQuantity" value="1" min="1">
                            <button type="button" onclick="changeTransactionQuantity(1)">+</button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Transaction Date <span class="required">*</span></label>
                        <input type="date" id="transactionDate" value="{{ date('Y-m-d') }}">
                    </div>
                </div>

                <div class="form-group">
                    <label>Transaction Type <span class="required">*</span></label>
                    <div class="radio-group">
                        <label>
                            <input type="radio" name="transactionType" value="IN" checked onchange="toggleTransactionProjectField()">
                            IN
                            <span class="radio-sub">Item Stock in</span>
                        </label>
                        <label>
                            <input type="radio" name="transactionType" value="OUT" onchange="toggleTransactionProjectField()">
                            OUT
                            <span class="radio-sub">Item Stock out</span>
                        </label>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="footer-left">
                        <button class="btn-cancel" onclick="closeTransactionModal()">Cancel</button>
                    </div>
                    <div class="footer-right">
                        <button class="btn-continue" onclick="transactionNextStep(2)">Continue</button>
                    </div>
                </div>
            </div>

            <!-- ─── STEP 2: Review ─── -->
            <div class="modal-step" id="step2" style="display: none;">
                <h3>Review transaction details</h3>

                <div class="summary-list">
                    <div class="summary-item">
                        <strong>Item Name</strong>
                        <span class="summary-value" id="reviewTransItemName">—</span>
                    </div>
                    <div class="summary-item">
                        <strong>Item Category</strong>
                        <span class="summary-value" id="reviewTransItemCategory">—</span>
                    </div>
                    <div class="summary-item">
                        <strong>Item Supplier</strong>
                        <span class="summary-value" id="reviewTransItemSupplier">—</span>
                    </div>
                    <div class="summary-item">
                        <strong>Item Quantity</strong>
                        <span class="summary-value" id="reviewTransItemQuantity">—</span>
                    </div>
                    <div class="summary-item">
                        <strong>Item Unit</strong>
                        <span class="summary-value" id="reviewTransItemUnit">—</span>
                    </div>
                </div>

                <hr style="border: none; border-top: 1px solid #e9ecef; margin: 5px 0 12px;">

                <div class="summary-list" style="border-left-color: #1a2b3c;">
                    <div class="summary-item">
                        <strong>Transaction Type</strong>
                        <span class="summary-value" id="reviewTransType">—</span>
                    </div>
                    <div class="summary-item">
                        <strong>Transaction Date</strong>
                        <span class="summary-value" id="reviewTransDate">—</span>
                    </div>
                    <div class="summary-item" id="reviewTransProjectRow" style="display: none;">
                        <strong>Project</strong>
                        <span class="summary-value" id="reviewTransProject">—</span>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="footer-left">
                        <button class="btn-cancel" onclick="closeTransactionModal()">Cancel</button>
                        <button class="btn-back" onclick="transactionPrevStep(1)">Back</button>
                    </div>
                    <div class="footer-right">
                        <button class="btn-save" onclick="saveTransaction()">Add Transaction</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        var lookupData = { categories: [], suppliers: [], units: [] };
        var inventoryItems = [];
        var allTransactions = [];
        var filteredData = [];
        var inventoryPageSize = 25;
        var inventoryCurrentPage = 1;

        // ─── NOTIFICATION FUNCTIONS ──────────────────────────────────
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
                msgSpan.textContent = message || 'Action completed successfully!';
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

        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDeleteModal();
            }
        });

        // ─── UPDATE STATS CARDS ───
        function updateStats(transactions, items, categories) {
            var uniqueItems = new Set();
            transactions.forEach(function(t) {
                if (t.item_name) {
                    uniqueItems.add(t.item_name + '|' + t.category);
                }
            });
            var totalItems = uniqueItems.size || items.length || 0;
            document.getElementById('totalItemsCount').textContent = totalItems;
            document.getElementById('totalItemsSub').textContent = totalItems > 0 ? totalItems + ' unique items' : 'No items found';

            var lowStockItems = 0;
            var lowStockList = [];
            items.forEach(function(item) {
                var stock = parseFloat(item.current_stock) || 0;
                if (stock <= 5 && stock > 0) {
                    lowStockItems++;
                    lowStockList.push(item.item_name);
                }
            });
            document.getElementById('lowStockCount').textContent = lowStockItems;
            document.getElementById('lowStockSub').textContent = lowStockItems > 0 ? 
                lowStockList.join(', ').substring(0, 30) + (lowStockList.length > 2 ? '...' : '') : 
                'All items well stocked';

            var uniqueCategories = new Set();
            categories.forEach(function(c) {
                uniqueCategories.add(c.inventory_category_name);
            });
            var categoryCount = uniqueCategories.size || 0;
            document.getElementById('categoriesCount').textContent = categoryCount;
            document.getElementById('categoriesSub').textContent = categoryCount + ' active categories';
        }

        // ─── LOAD LOOKUP DATA ───
        function loadLookupData() {
            fetch('/api/inventory/lookup-data', {
                headers: { 
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success && data.data) {
                    lookupData = data.data;
                    populateAllDropdowns();
                    loadInventoryItems();
                }
            })
            .catch(function(err) {
                console.error('Error loading lookup data:', err);
            });
        }

        // ─── POPULATE ALL DROPDOWNS ───
        function populateAllDropdowns() {
            // Add Item Modal dropdowns
            populateAddItemDropdowns();
            
            // Transaction Modal item select
            populateTransactionItemSelect();
            
            // View Modal dropdowns
            populateViewDropdowns();
            
            // Project dropdown
            populateProjectDropdown();
        }

        // ─── POPULATE ADD ITEM DROPDOWNS ───
        function populateAddItemDropdowns() {
            var categorySelect = document.getElementById('newItemCategory');
            var unitSelect = document.getElementById('newItemUnit');
            var supplierSelect = document.getElementById('newItemSupplier');

            categorySelect.innerHTML = '<option value="">Select Category...</option>';
            if (lookupData.categories) {
                lookupData.categories.forEach(function(cat) {
                    var opt = document.createElement('option');
                    opt.value = cat.inventory_category_id;
                    opt.textContent = cat.inventory_category_name;
                    categorySelect.appendChild(opt);
                });
            }

            unitSelect.innerHTML = '<option value="">Select Unit...</option>';
            if (lookupData.units) {
                lookupData.units.forEach(function(unit) {
                    var opt = document.createElement('option');
                    opt.value = unit.unit_id;
                    opt.textContent = unit.unit_name;
                    unitSelect.appendChild(opt);
                });
            }

            supplierSelect.innerHTML = '<option value="">Select Supplier...</option>';
            if (lookupData.suppliers) {
                lookupData.suppliers.forEach(function(sup) {
                    var opt = document.createElement('option');
                    opt.value = sup.supplier_id;
                    opt.textContent = sup.supplier_name;
                    supplierSelect.appendChild(opt);
                });
            }
        }

        // ─── POPULATE TRANSACTION ITEM SELECT ───
        function populateTransactionItemSelect() {
            var select = document.getElementById('transactionItemSelect');
            select.innerHTML = '<option value="">Select Item...</option>';
            
            // Get unique items from inventory
            var uniqueItems = {};
            inventoryItems.forEach(function(item) {
                var key = item.item_name + '|' + item.category + '|' + item.unit + '|' + item.supplier;
                if (!uniqueItems[key]) {
                    uniqueItems[key] = {
                        item_id: item.item_id,
                        item_name: item.item_name,
                        category: item.category,
                        unit: item.unit,
                        supplier: item.supplier,
                        supplier_id: item.supplier_id
                    };
                }
            });
            
            Object.values(uniqueItems).forEach(function(item) {
                var opt = document.createElement('option');
                opt.value = item.item_id;
                opt.textContent = item.item_name + ' (' + item.category + ', ' + item.unit + ')';
                opt.dataset.category = item.category;
                opt.dataset.unit = item.unit;
                opt.dataset.supplier = item.supplier;
                opt.dataset.supplierId = item.supplier_id;
                select.appendChild(opt);
            });
        }

        // ─── POPULATE ITEM FIELDS FROM SELECTION ───
        function populateItemFields() {
            var select = document.getElementById('transactionItemSelect');
            var selectedOption = select.options[select.selectedIndex];
            
            if (select.value && selectedOption) {
                document.getElementById('transactionItemCategory').value = selectedOption.dataset.category || '';
                document.getElementById('transactionItemUnit').value = selectedOption.dataset.unit || '';
                document.getElementById('transactionItemSupplier').value = selectedOption.dataset.supplier || '';
            } else {
                document.getElementById('transactionItemCategory').value = '';
                document.getElementById('transactionItemUnit').value = '';
                document.getElementById('transactionItemSupplier').value = '';
            }
        }

        // ─── POPULATE VIEW DROPDOWNS ───
        function populateViewDropdowns() {
            var categorySelect = document.getElementById('viewCategoryInput');
            var unitSelect = document.getElementById('viewUnitInput');
            var supplierSelect = document.getElementById('viewSupplierInput');
            
            if (!categorySelect || !unitSelect || !supplierSelect) return;

            categorySelect.innerHTML = '<option value="">Select Category...</option>';
            if (lookupData.categories) {
                lookupData.categories.forEach(function(cat) {
                    var opt = document.createElement('option');
                    opt.value = cat.inventory_category_name;
                    opt.textContent = cat.inventory_category_name;
                    categorySelect.appendChild(opt);
                });
            }

            unitSelect.innerHTML = '<option value="">Select Unit...</option>';
            if (lookupData.units) {
                lookupData.units.forEach(function(unit) {
                    var opt = document.createElement('option');
                    opt.value = unit.unit_name;
                    opt.textContent = unit.unit_name;
                    unitSelect.appendChild(opt);
                });
            }

            supplierSelect.innerHTML = '<option value="">Select Supplier...</option>';
            if (lookupData.suppliers) {
                lookupData.suppliers.forEach(function(sup) {
                    var opt = document.createElement('option');
                    opt.value = sup.supplier_id;
                    opt.textContent = sup.supplier_name;
                    supplierSelect.appendChild(opt);
                });
            }
        }

        // ─── POPULATE PROJECT DROPDOWN ───
        function populateProjectDropdown() {
            fetch('/api/projects/list', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(projects) {
                var select = document.getElementById('transactionProject');
                select.innerHTML = '<option value="">Select Project...</option>';
                if (Array.isArray(projects)) {
                    projects.forEach(function(project) {
                        var opt = document.createElement('option');
                        opt.value = project.project_id;
                        opt.textContent = project.project_name;
                        select.appendChild(opt);
                    });
                }
            })
            .catch(function(err) {
                console.error('Error loading projects:', err);
            });
        }

        // ─── LOAD INVENTORY ITEMS AND TRANSACTIONS ───
        function loadInventoryItems() {
            Promise.all([
                fetch('/api/inventory', {
                    headers: { 
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).then(function(res) { return res.json(); }),
                fetch('/api/inventory/transactions', {
                    headers: { 
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                }).then(function(res) { return res.json(); })
            ])
            .then(function(results) {
                var itemsResult = results[0];
                var transactionsResult = results[1];

                inventoryItems = itemsResult.success ? itemsResult.data || [] : [];
                allTransactions = transactionsResult.success ? transactionsResult.data || [] : [];
                filteredData = allTransactions;
                
                populateTransactionItemSelect();
                renderInventoryPage(1);
                updateStats(allTransactions, inventoryItems, lookupData.categories);
            })
            .catch(function(err) {
                console.error('Error loading inventory data:', err);
                var tbody = document.getElementById('inventoryTableBody');
                tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 20px; color: #d32f2f;">Error loading inventory data. Please refresh the page.</td></tr>';
            });
        }

        // ─── RENDER INVENTORY PAGE ───
        function renderInventoryPage(page) {
            inventoryCurrentPage = page;
            var start = (page - 1) * inventoryPageSize;
            var end = Math.min(start + inventoryPageSize, filteredData.length);
            var pageData = filteredData.slice(start, end);
            
            var tbody = document.getElementById('inventoryTableBody');
            tbody.innerHTML = '';
            
            if (!pageData.length) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align: center; padding: 20px; color: #888;">No transactions found.</td></tr>';
                renderInventoryPagination();
                return;
            }
            
            pageData.forEach(function(row) {
                var tr = document.createElement('tr');
                var stock = parseFloat(row.current_stock) || 0;
                var reorderLevel = parseFloat(row.reorder_level) || 0;
                var status = stock > reorderLevel ? 'in-stock' : (stock <= 0 ? 'out-of-stock' : 'low-stock');
                var statusText = stock > reorderLevel ? 'In Stock' : (stock <= 0 ? 'Out of Stock' : 'Low Stock');
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
                tr.setAttribute('data-stock', stock);
                tr.setAttribute('data-status', statusText);
                tr.setAttribute('data-project', row.project || '');

                var statusClass = status === 'in-stock' ? 'in-stock' : (status === 'out-of-stock' ? 'out-of-stock' : 'low-stock');

                tr.innerHTML = `
                    <td><strong>${row.item_name}</strong></td>
                    <td>${row.category}</td>
                    <td>${row.unit}</td>
                    <td>${row.quantity}</td>
                    <td>${row.supplier}</td>
                    <td><span class="type-badge ${typeLabel === 'IN' ? 'in' : 'out'}">${typeLabel}</span></td>
                    <td>${dateValue}</td>
                    <td>${stock}</td>
                    <td><span class="status-badge ${statusClass}"><span class="dot"></span> ${statusText}</span></td>
                    <td style="text-align: center;">
                        <img src="{{ asset('images/edit.jpg') }}" alt="Edit" style="width: 20px; height: 20px; cursor: pointer; opacity: 0.7; transition: 0.2s;" onclick="event.stopPropagation(); openViewModal(this.closest('tr'));" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'">
                    </td>
                `;
                tbody.appendChild(tr);
            });
            
            renderInventoryPagination();
        }

        // ─── RENDER INVENTORY PAGINATION ───
        function renderInventoryPagination() {
            var container = document.getElementById('inventoryPaginationLinks');
            if (!container) return;
            
            var total = filteredData.length;
            var totalPages = Math.ceil(total / inventoryPageSize);
            var current = inventoryCurrentPage;
            
            if (totalPages <= 1) {
                container.innerHTML = `<span class="pagination-info">Showing all ${total} transactions</span>`;
                return;
            }
            
            var html = '';
            html += `<a href="#" onclick="renderInventoryPage(${current - 1}); return false;" class="${current <= 1 ? 'disabled' : ''}">«</a>`;
            
            if (totalPages <= 7) {
                for (var i = 1; i <= totalPages; i++) {
                    html += `<a href="#" onclick="renderInventoryPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
                }
            } else {
                for (var i = 1; i <= 3; i++) {
                    html += `<a href="#" onclick="renderInventoryPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
                }
                if (current > 4) {
                    html += `<span class="dots">...</span>`;
                }
                var startPage = Math.max(4, current - 1);
                var endPage = Math.min(totalPages - 2, current + 1);
                for (var i = startPage; i <= endPage; i++) {
                    html += `<a href="#" onclick="renderInventoryPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
                }
                if (current < totalPages - 3) {
                    html += `<span class="dots">...</span>`;
                }
                for (var i = totalPages - 1; i <= totalPages; i++) {
                    if (i > 3) {
                        html += `<a href="#" onclick="renderInventoryPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
                    }
                }
            }
            html += `<a href="#" onclick="renderInventoryPage(${current + 1}); return false;" class="${current >= totalPages ? 'disabled' : ''}">»</a>`;
            container.innerHTML = html;
        }

        // ─── CHANGE INVENTORY PAGE SIZE ───
        function changeInventoryPageSize() {
            var select = document.getElementById('inventoryRowsPerPage');
            inventoryPageSize = parseInt(select.value) || 25;
            inventoryCurrentPage = 1;
            renderInventoryPage(1);
        }

        // ─── FILTER TABLE ───
        function filterTable() {
            var searchTerm = document.getElementById('searchInput').value.toLowerCase().trim();
            var typeFilter = document.getElementById('typeFilter').value;
            var startDate = document.getElementById('startDate').value;
            var endDate = document.getElementById('endDate').value;
            
            filteredData = allTransactions.filter(function(row) {
                var matchesSearch = true;
                if (searchTerm) {
                    matchesSearch = (row.item_name || '').toLowerCase().includes(searchTerm) ||
                                   (row.category || '').toLowerCase().includes(searchTerm) ||
                                   (row.supplier || '').toLowerCase().includes(searchTerm);
                }
                
                var matchesType = true;
                if (typeFilter !== 'all') {
                    matchesType = row.transaction_type === typeFilter;
                }
                
                var matchesDate = true;
                if (startDate && endDate) {
                    var rowDate = row.transaction_date || '';
                    matchesDate = rowDate >= startDate && rowDate <= endDate;
                }
                
                return matchesSearch && matchesType && matchesDate;
            });
            
            renderInventoryPage(1);
        }

        function applyFilters() {
            filterTable();
            showSuccess('Filters applied!');
        }

        // ─── ADD ITEM MODAL ───
        function openAddItemModal() {
            document.getElementById('addItemModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('newItemName').value = '';
            document.getElementById('newItemCategory').value = '';
            document.getElementById('newItemUnit').value = '';
            document.getElementById('newItemSupplier').value = '';
            document.getElementById('newItemReorderLevel').value = '5';
            populateAddItemDropdowns();
        }

        function closeAddItemModal() {
            document.getElementById('addItemModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveNewItem() {
            var name = document.getElementById('newItemName').value.trim();
            var categoryId = document.getElementById('newItemCategory').value;
            var unitId = document.getElementById('newItemUnit').value;
            var supplierId = document.getElementById('newItemSupplier').value;
            var reorderLevel = parseFloat(document.getElementById('newItemReorderLevel').value) || 0;

            if (!name) { showError('Please enter an item name.'); return; }
            if (!categoryId) { showError('Please select a category.'); return; }
            if (!unitId) { showError('Please select a unit.'); return; }
            if (!supplierId) { showError('Please select a supplier.'); return; }

            var payload = {
                item_name: name,
                inventory_category_id: parseInt(categoryId),
                supplier_id: parseInt(supplierId),
                unit_id: parseInt(unitId),
                current_stock: 0,
                reorder_level: reorderLevel
            };

            fetch('/api/inventory/item', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    closeAddItemModal();
                    showSuccess('Item "' + name + '" added successfully!');
                    loadInventoryItems();
                } else {
                    showError(data.message || 'Failed to add item.');
                }
            })
            .catch(function(err) {
                console.error('Error adding item:', err);
                showError('Failed to add item.');
            });
        }

        // ─── TRANSACTION MODAL ───
        var transactionCurrentStep = 1;

        function openTransactionModal() {
            document.getElementById('transactionModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            transactionGoToStep(1);
            
            // Reset form
            document.getElementById('transactionItemSelect').value = '';
            document.getElementById('transactionItemCategory').value = '';
            document.getElementById('transactionItemUnit').value = '';
            document.getElementById('transactionItemSupplier').value = '';
            document.getElementById('transactionQuantity').value = 1;
            document.getElementById('transactionDate').value = new Date().toISOString().split('T')[0];
            document.querySelector('input[name="transactionType"][value="IN"]').checked = true;
            document.getElementById('transactionProjectGroup').style.display = 'none';
            document.getElementById('transactionProjectRequired').style.display = 'none';
            document.getElementById('transactionProject').value = '';
            
            // Reset review
            document.getElementById('reviewTransItemName').textContent = '—';
            document.getElementById('reviewTransItemCategory').textContent = '—';
            document.getElementById('reviewTransItemSupplier').textContent = '—';
            document.getElementById('reviewTransItemQuantity').textContent = '—';
            document.getElementById('reviewTransItemUnit').textContent = '—';
            document.getElementById('reviewTransType').textContent = '—';
            document.getElementById('reviewTransDate').textContent = '—';
            document.getElementById('reviewTransProjectRow').style.display = 'none';
            document.getElementById('reviewTransProject').textContent = '—';
            
            populateTransactionItemSelect();
            populateProjectDropdown();
        }

        function closeTransactionModal() {
            document.getElementById('transactionModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function transactionGoToStep(step) {
            document.querySelectorAll('#transactionModal .modal-step').forEach(function(el) {
                el.style.display = 'none';
            });
            document.getElementById('step' + step).style.display = 'block';

            document.querySelectorAll('#transactionModal .step-indicator .step').forEach(function(el, index) {
                el.classList.toggle('active', index + 1 === step);
                el.classList.toggle('completed', index + 1 < step);
            });
            transactionCurrentStep = step;
        }

        function transactionNextStep(step) {
            var itemId = document.getElementById('transactionItemSelect').value;
            var quantity = document.getElementById('transactionQuantity').value;
            var date = document.getElementById('transactionDate').value;
            var type = document.querySelector('input[name="transactionType"]:checked');

            if (!itemId) { showError('Please select an item.'); return; }
            if (!quantity || quantity < 1) { showError('Please enter a valid quantity (minimum 1).'); return; }
            if (!date) { showError('Please select a transaction date.'); return; }

            var typeLabel = type ? type.value : 'IN';
            if (typeLabel === 'OUT') {
                var projectId = document.getElementById('transactionProject').value;
                if (!projectId) {
                    showError('Please select a project for OUT transactions.');
                    return;
                }
                var projectName = document.getElementById('transactionProject').options[document.getElementById('transactionProject').selectedIndex].text;
                document.getElementById('reviewTransProjectRow').style.display = 'flex';
                document.getElementById('reviewTransProject').textContent = projectName;
            } else {
                document.getElementById('reviewTransProjectRow').style.display = 'none';
            }

            var select = document.getElementById('transactionItemSelect');
            var selectedOption = select.options[select.selectedIndex];
            var itemName = selectedOption ? selectedOption.text.split(' (')[0] : '—';
            var category = document.getElementById('transactionItemCategory').value || '—';
            var supplier = document.getElementById('transactionItemSupplier').value || '—';
            var unit = document.getElementById('transactionItemUnit').value || '—';

            document.getElementById('reviewTransItemName').textContent = itemName;
            document.getElementById('reviewTransItemCategory').textContent = category;
            document.getElementById('reviewTransItemSupplier').textContent = supplier;
            document.getElementById('reviewTransItemQuantity').textContent = quantity;
            document.getElementById('reviewTransItemUnit').textContent = unit;
            document.getElementById('reviewTransType').textContent = typeLabel === 'IN' ? 'IN (Item Stock in)' : 'OUT (Item Stock out)';
            document.getElementById('reviewTransDate').textContent = date;

            transactionGoToStep(step);
        }

        function transactionPrevStep(step) {
            transactionGoToStep(step);
        }

        function changeTransactionQuantity(delta) {
            var input = document.getElementById('transactionQuantity');
            var val = parseInt(input.value) || 1;
            val = Math.max(1, val + delta);
            input.value = val;
        }

        function toggleTransactionProjectField() {
            var typeRadios = document.querySelectorAll('input[name="transactionType"]');
            var selected = Array.from(typeRadios).find(r => r.checked);
            var projectGroup = document.getElementById('transactionProjectGroup');
            var projectRequired = document.getElementById('transactionProjectRequired');
            if (selected && selected.value === 'OUT') {
                projectGroup.style.display = 'block';
                projectRequired.style.display = 'inline';
            } else {
                projectGroup.style.display = 'none';
                projectRequired.style.display = 'none';
            }
        }

        // ─── SAVE TRANSACTION ───
        function saveTransaction() {
            var itemId = document.getElementById('transactionItemSelect').value;
            var quantity = parseFloat(document.getElementById('transactionQuantity').value);
            var date = document.getElementById('transactionDate').value;
            var type = document.querySelector('input[name="transactionType"]:checked').value;
            var projectId = null;
            
            if (type === 'OUT') {
                projectId = document.getElementById('transactionProject').value;
                if (!projectId) {
                    showError('Please select a project for OUT transactions.');
                    return;
                }
            }

            var payload = {
                item_id: parseInt(itemId),
                project_id: projectId || null,
                transaction_type: type,
                quantity: quantity,
                transaction_date: date
            };

            fetch('/api/inventory/transaction', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    closeTransactionModal();
                    showSuccess(data.message || 'Transaction added successfully!');
                    loadInventoryItems();
                } else {
                    showError(data.message || 'Failed to add transaction.');
                }
            })
            .catch(function(err) {
                console.error('Error adding transaction:', err);
                showError('Failed to add transaction.');
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
            document.querySelectorAll('#viewModal .view-value').forEach(function(el) {
                if (el.id === 'viewStockDisplay' || el.id === 'viewStatusDisplay') {
                    el.style.display = 'block';
                    return;
                }
                el.style.display = 'none';
            });
            document.querySelectorAll('#viewModal .view-input').forEach(function(el) { el.style.display = 'block'; });
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
            document.querySelectorAll('#viewModal .view-value').forEach(function(el) { el.style.display = 'block'; });
            document.querySelectorAll('#viewModal .view-input').forEach(function(el) { el.style.display = 'none'; });
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
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
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
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
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

        // ─── CLOSE MODALS ON BACKDROP CLICK ───
        document.getElementById('addItemModal').addEventListener('click', function(e) {
            if (e.target === this) { closeAddItemModal(); }
        });

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) { closeViewModal(); }
        });

        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) { closeDeleteModal(); }
        });

        document.getElementById('transactionModal').addEventListener('click', function(e) {
            if (e.target === this) { closeTransactionModal(); }
        });

        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });

        // ─── INIT ───
        document.addEventListener('DOMContentLoaded', function() {
            loadLookupData();
        });
    </script>

</body>
</html>