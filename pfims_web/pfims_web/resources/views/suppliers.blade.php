<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Item Suppliers - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/suppliers.css') }}">
    <link rel="stylesheet" href="{{ asset('css/module-analytics.css') }}">
        <style>
        #deleteConfirmModal { z-index: 9999 !important; }
        .btn-delete-supplier {
            background: #d32f2f;
            color: #fff;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
        }
        .btn-delete-supplier:hover {
            background: #b71c1c;
            transform: translateY(-2px);
        }
        .modal-footer .footer-left,
        .modal-footer .footer-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        #addSupplierModal .modal-container {
            width: 460px;
        }
        .supplier-section-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 12px;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="suppliers-page">
    
    <!-- ─── ERROR NOTIFICATION (POP-UP) ─── -->
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
            <span>Supplier saved successfully!</span>
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
                    Are you sure you want to permanently delete this supplier?
                </p>
                <p style="font-size: 0.85rem; color: #888; margin-bottom: 20px;">
                    This action cannot be undone.
                </p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 12px; margin-top: 10px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeDeleteModal()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: transparent; color: #888; transition: 0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmDeleteBtn" onclick="confirmDelete()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #c95c5c; color: #fff; transition: 0.3s;">Delete</button>
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
                <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
            </a>
            <a href="{{ url('/profile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>{{ auth()->user()->name === 'Administrator' ? 'Admin' : auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
                <nav>
            <ul>
                <li><a href="{{ url('/dashboard') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
                <li><a href="{{ url('/projects') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon">PROJECTS</a></li>
                <li><a href="{{ url('/finance') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon">INVENTORY</a></li>
                <li class="active"><a href="{{ url('/suppliers') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/suppliers.png') }}" alt="" class="nav-link-icon">SUPPLIERS</a></li>
                <li><a href="{{ url('/reports') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/reports.png') }}" alt="" class="nav-link-icon">REPORTS</a></li>
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

                <div class="page-header">
            <h1>ITEM SUPPLIERS</h1>
            <button class="btn-add-supplier" onclick="openAddModal()">+ Add Supplier</button>
        </div>

        <div class="filters-bar">
            <input type="search" id="supplierSearch" class="search-input" maxlength="100" placeholder="Search supplier, address, or contact..." oninput="filterSuppliers()">
            <select id="supplierSort" onchange="filterSuppliers()"><option value="name">Sort by supplier name</option><option value="items">Sort by items supplied</option><option value="alerts">Sort by stock alerts</option></select>
            <button type="button" class="btn-clear-filters" onclick="clearSupplierFilters()">X</button>
        </div>

        <div id="supplierKpis" class="stats-grid-supplier"></div>

        <div class="module-insights">
            <section class="module-insight-card" aria-labelledby="supplierCoverageChartTitle">
                <h3 id="supplierCoverageChartTitle">Inventory Items by Supplier</h3>
                <p class="insight-caption">Top suppliers by number of items currently linked to them.</p>
                <div id="supplierCoverageChart" class="insight-chart" role="img" aria-label="Inventory items by supplier"></div>
            </section>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Address</th>
                        <th>Contact Number</th>
                        <th style="width: 140px; text-align: center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="supplierTableBody">
                    <!-- Suppliers will be loaded here dynamically -->
                </tbody>
            </table>
        </div>

        <div class="pagination-wrapper" id="suppliersPagination">
            <div class="rows-info">
                Rows per page
                <select id="supplierRowsPerPage" aria-label="Supplier rows per page" onchange="changeSupplierPageSize()">
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span id="suppliersTotalCount" class="pagination-total">Total: 0</span>
            </div>
            <div class="pagination-links" id="suppliersPaginationLinks">
                <!-- Generated by JavaScript -->
            </div>
        </div>

    </main>

    <!-- ─── OVERLAY / MODAL (Add Supplier) ─── -->
    <div id="addSupplierModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Add new supplier</h2>
                <button class="modal-close" onclick="closeAddModal()">×</button>
            </div>

            <div class="step-indicator">
                <span class="step active" id="addSupplierStep1Indicator">
                    <span class="step-number">1</span> Supplier Details
                </span>
                <span class="step" id="addSupplierStep2Indicator">
                    <span class="step-number">2</span> Review
                </span>
            </div>

            <div class="modal-step" id="addSupplierStep1">
                <h3 class="supplier-section-title">Supplier Information</h3>
                <div class="add-row">
                    <div class="add-label">Supplier Name <span class="required">*</span></div>
                    <div class="add-input">
                        <input type="text" placeholder="e.g. Prime Hardware Inc." id="addSupplierName">
                    </div>
                </div>

                <hr class="modal-divider">

                <div class="add-two-col">
                    <div class="col-group">
                        <label>Supplier Address <span class="required">*</span></label>
                        <input type="text" placeholder="e.g. 123 Rizal St, Antipolo City" id="addSupplierAddress">
                    </div>
                    <div class="col-group">
                        <label>Supplier Contact no. <span class="required">*</span></label>
                        <input type="tel" placeholder="e.g. +63 (912) 345-6789" id="addSupplierContact" inputmode="tel" maxlength="20" oninput="this.value = this.value.replace(/[^0-9+().\s-]/g, '')">
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="footer-left">
                        <button class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                    </div>
                    <div class="footer-right">
                        <button class="btn-continue" onclick="addSupplierNextStep()">Continue</button>
                    </div>
                </div>
            </div>

            <div class="modal-step" id="addSupplierStep2" style="display: none;">
                <h3 class="supplier-section-title">Review supplier details</h3>
                <div class="summary-list">
                    <div class="summary-item"><strong>Supplier Name</strong><span class="summary-value" id="reviewSupplierName">—</span></div>
                    <div class="summary-item"><strong>Address</strong><span class="summary-value" id="reviewSupplierAddress">—</span></div>
                    <div class="summary-item"><strong>Contact no.</strong><span class="summary-value" id="reviewSupplierContact">—</span></div>
                </div>
                <div class="modal-footer">
                    <div class="footer-left">
                        <button class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                        <button class="btn-back" onclick="addSupplierPrevStep()">Back</button>
                    </div>
                    <div class="footer-right">
                        <button class="btn-save" id="addSupplierSubmitBtn" onclick="saveSupplier()">Add Supplier</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── OVERLAY / MODAL (Supplier Details) ─── -->
    <div id="viewSupplierModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Supplier Details</h2>
                <button class="modal-close" onclick="closeViewModal()">×</button>
            </div>
            <div class="modal-body view-details-grid">
                <div class="view-item"><label>Supplier Name</label><span id="viewSupplierName" class="view-value">—</span></div>
                <div class="view-item"><label>Supplier Address</label><span id="viewSupplierAddress" class="view-value">—</span></div>
                <div class="view-item"><label>Supplier Contact no.</label><span id="viewSupplierContact" class="view-value">—</span></div>
            </div>
                        <div class="modal-footer">
                <button class="btn-cancel" onclick="closeViewModal()">Close</button>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <button class="btn-delete-supplier" onclick="openDeleteModal(currentSupplierId)" type="button">Delete</button>
                    <button class="btn-save" onclick="openEditFromView()" type="button">Edit</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── OVERLAY / MODAL (Edit Supplier) ─── -->
    <div id="editSupplierModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Edit supplier details</h2>
                <button class="modal-close" onclick="closeEditModal()">×</button>
            </div>

                        <div class="modal-body">
                <h3 class="supplier-section-title">Supplier Information</h3>
                <!-- Section 1: Supplier Name -->
                <div class="edit-section">
                    <div class="left-col">
                        <div class="current-label">Current Supplier Name</div>
                        <div class="current-value" id="currentSupplierName">—</div>
                    </div>
                    <div class="right-col">
                        <label>Supplier Name</label>
                        <input type="text" placeholder="Item Name" id="editSupplierName">
                    </div>
                </div>

                <hr class="modal-divider">

                <!-- Section 2: Address -->
                <div class="edit-section">
                    <div class="left-col">
                        <div class="current-label">Current Supplier Address</div>
                        <div class="current-value" id="currentSupplierAddress">—</div>
                    </div>
                    <div class="right-col">
                        <label>Address</label>
                        <input type="text" placeholder="Item Name" id="editSupplierAddress">
                    </div>
                </div>

                <hr class="modal-divider">

                <!-- Section 3: Contact no. -->
                <div class="edit-section">
                    <div class="left-col">
                        <div class="current-label">Current Supplier Contact no.</div>
                        <div class="current-value" id="currentSupplierContact">—</div>
                    </div>
                    <div class="right-col">
                        <label>Contact no.</label>
                        <input type="tel" placeholder="e.g. +63 (912) 345-6789" id="editSupplierContact" inputmode="tel" maxlength="20" oninput="this.value = this.value.replace(/[^0-9+().\s-]/g, '')">
                    </div>
                </div>
            </div>

                        <div class="modal-footer">
                <button class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button class="btn-save" onclick="updateSupplier()">Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        // Global state
        let currentSupplierId = null;
        let suppliersData = [];

        // ─── BUTTON LOADING STATE (prevents double-click / double-submit) ───
        function setButtonLoading(button, isLoading, loadingText) {
            if (!button) return;
            if (isLoading) {
                button.dataset.originalText = button.textContent;
                button.textContent = loadingText || 'Loading...';
                button.disabled = true;
                button.style.opacity = '0.7';
                button.style.cursor = 'not-allowed';
            } else {
                button.textContent = button.dataset.originalText || button.textContent;
                button.disabled = false;
                button.style.opacity = '';
                button.style.cursor = '';
            }
        }

                // ─── LOAD SUPPLIERS ON PAGE LOAD ───
        document.addEventListener('DOMContentLoaded', function() {
            loadSuppliers();
            fetchNotifBadge();
        });

        // ─── FETCH UNREAD NOTIFICATION COUNT ───
        function fetchNotifBadge() {
            fetch('/api/notifications/unread-count', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                if (!response.ok) throw new Error('Failed to load unread count.');
                return response.json();
            })
            .then(function(data) {
                var badge = document.getElementById('notifBadge');
                if (!badge) return;
                var count = data.unread_count || 0;
                if (count > 0) {
                    badge.textContent = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(function(error) {
                console.error('Error loading notification badge:', error);
            });
        }

        // ─── LOAD SUPPLIERS FROM API ───
        function loadSuppliers() {
            Promise.all([fetch('/api/suppliers').then(response => response.json()), fetch('/api/inventory').then(response => response.json()).catch(() => ({success: false, data: []}))])
                .then(([supplierResponse, inventoryResponse]) => {
                    if (supplierResponse.success) {
                        const items = inventoryResponse.success ? inventoryResponse.data : [];
                        suppliersData = supplierResponse.data.map(supplier => {
                            const supplied = items.filter(item => Number(item.supplier_id) === Number(supplier.supplier_id));
                            return {...supplier, item_count: supplied.length, low_stock_count: supplied.filter(item => Number(item.current_stock) <= Number(item.reorder_level)).length};
                        });
                        filterSuppliers();
                    }
                })
                .catch(error => console.error('Error loading suppliers:', error));
        }

                // ─── PAGINATION STATE ───
        var supplierPageSize = 25;
        var supplierCurrentPage = 1;
        var supplierFilteredData = [];

        // ─── RENDER SUPPLIERS IN TABLE ───
        function renderSuppliers(suppliers) {
            supplierFilteredData = suppliers;
            updateSupplierAnalytics(suppliers);
            renderSupplierPage(1);
        }

        function renderSupplierPage(page) {
            supplierCurrentPage = page;
            const tbody = document.getElementById('supplierTableBody');
            tbody.innerHTML = '';

            if (supplierFilteredData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">No suppliers found.</td></tr>';
                renderSupplierPagination();
                return;
            }

            const start = (page - 1) * supplierPageSize;
            const end = Math.min(start + supplierPageSize, supplierFilteredData.length);
            const pageData = supplierFilteredData.slice(start, end);

            pageData.forEach(supplier => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${supplier.supplier_name}</strong></td>
                    <td>${supplier.address}</td>
                    <td>${supplier.contact_number}</td>
                    <td style="text-align: center;">
                    <button class="btn-edit" onclick="openViewModal(${supplier.supplier_id}, this)" title="View Details" aria-label="View supplier details"><img src="{{ asset('images/edit.jpg') }}" alt="View"></button>
                `;
                tbody.appendChild(row);
            });

            renderSupplierPagination();
            if (window.refreshTableScrollFade) window.refreshTableScrollFade();
        }

        function renderSupplierPagination() {
            const container = document.getElementById('suppliersPaginationLinks');
            if (!container) return;

            const total = supplierFilteredData.length;
            const totalEl = document.getElementById('suppliersTotalCount');
            if (totalEl) totalEl.textContent = 'Total: ' + total;
            const totalPages = Math.ceil(total / supplierPageSize);
            const current = supplierCurrentPage;

                        if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            var html = '';
            html += `<a href="#" onclick="renderSupplierPage(${current - 1}); return false;" class="${current <= 1 ? 'disabled' : ''}">«</a>`;
            for (var i = 1; i <= totalPages; i++) {
                html += `<a href="#" onclick="renderSupplierPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
            }
            html += `<a href="#" onclick="renderSupplierPage(${current + 1}); return false;" class="${current >= totalPages ? 'disabled' : ''}">»</a>`;
            container.innerHTML = html;
        }

        function changeSupplierPageSize() {
            const select = document.getElementById('supplierRowsPerPage');
            supplierPageSize = parseInt(select.value) || 25;
            renderSupplierPage(1);
        }

        function filterSuppliers() {
            const term = (document.getElementById('supplierSearch')?.value || '').toLowerCase().trim();
            const sort = document.getElementById('supplierSort')?.value || 'name';
            const filtered = suppliersData.filter(supplier => [supplier.supplier_name, supplier.address, supplier.contact_number].some(value => (value || '').toLowerCase().includes(term)));
            filtered.sort((a, b) => sort === 'items' ? b.item_count - a.item_count : (sort === 'alerts' ? b.low_stock_count - a.low_stock_count : (a.supplier_name || '').localeCompare(b.supplier_name || '')));
            renderSuppliers(filtered);
        }

        function clearSupplierFilters() {
            document.getElementById('supplierSearch').value = '';
            document.getElementById('supplierSort').value = 'name';
            filterSuppliers();
        }

        function updateSupplierAnalytics(filtered) {
            const totalItems = filtered.reduce((sum, supplier) => sum + supplier.item_count, 0);
            const alerts = filtered.reduce((sum, supplier) => sum + supplier.low_stock_count, 0);
            document.getElementById('supplierKpis').innerHTML = [
                ['Matching suppliers', filtered.length], ['Items supplied', totalItems], ['Low-stock item links', alerts]
            ].map(([label, value]) => `<article class="stat-card-supplier"><small>${label}</small><strong>${value.toLocaleString()}</strong></article>`).join('');
            const top = [...filtered].sort((a,b) => b.item_count - a.item_count).slice(0,8); const max = Math.max(...top.map(item => item.item_count), 1);
            const chart = document.getElementById('supplierCoverageChart');
            if (!top.length) {
                chart.innerHTML = '<div class="insight-empty">No data matches the current filters.</div>';
                return;
            }
            chart.innerHTML = top.map(supplier => `
                <div class="insight-bar-row">
                    <span class="insight-bar-label" title="${escapeSupplierHtml(supplier.supplier_name)}">${escapeSupplierHtml(supplier.supplier_name)}</span>
                    <span class="insight-bar-track"><span class="insight-bar-fill" style="width:${Math.max(2, supplier.item_count / max * 100)}%;background:#e19a45;"></span></span>
                    <span class="insight-bar-value">${supplier.item_count}</span>
                </div>
            `).join('');
        }

        function escapeSupplierHtml(value) {
            return String(value || '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
        }

                function openViewModal(supplierId, triggerBtn) {
            currentSupplierId = supplierId;
            setButtonLoading(triggerBtn, true, '...');
            fetch(`/api/suppliers/${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Unable to load supplier.');
                    const supplier = data.data;
                    document.getElementById('viewSupplierName').textContent = supplier.supplier_name || '—';
                    document.getElementById('viewSupplierAddress').textContent = supplier.address || '—';
                    document.getElementById('viewSupplierContact').textContent = supplier.contact_number || '—';
                    document.getElementById('viewSupplierModal').classList.add('active');
                    document.body.style.overflow = 'hidden';
                })
                .catch(error => showError(error.message || 'Unable to load supplier.'))
                .finally(() => setButtonLoading(triggerBtn, false));
        }

                function closeViewModal() {
            document.getElementById('viewSupplierModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function openEditFromView() {
            if (!currentSupplierId) return;
            closeViewModal();
            openEditModal(currentSupplierId);
        }

        // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // ─── ADD SUPPLIER MODAL ───
                function openAddModal() {
            currentSupplierId = null;
            document.getElementById('addSupplierModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('addSupplierName').value = '';
            document.getElementById('addSupplierAddress').value = '';
            document.getElementById('addSupplierContact').value = '';
            addSupplierGoToStep(1);
        }

        function addSupplierGoToStep(step) {
            document.querySelectorAll('#addSupplierModal .modal-step').forEach(function(el) {
                el.style.display = 'none';
            });
            document.getElementById('addSupplierStep' + step).style.display = 'block';
            document.querySelectorAll('#addSupplierModal .step-indicator .step').forEach(function(el, index) {
                el.classList.toggle('active', index + 1 === step);
                el.classList.toggle('completed', index + 1 < step);
            });
        }

        function addSupplierNextStep() {
            var name = document.getElementById('addSupplierName').value.trim();
            var address = document.getElementById('addSupplierAddress').value.trim();
            var contact = document.getElementById('addSupplierContact').value.trim();

            if (!name) { showError('Please enter a supplier name.'); return; }
            if (!address) { showError('Please enter a supplier address.'); return; }
            if (!contact) { showError('Please enter a supplier contact number.'); return; }
            if (!/^(?=.*\d)[0-9+().\s-]+$/.test(contact)) {
                showError('Contact number may only contain numbers, spaces, +, -, parentheses, and periods.');
                return;
            }

            document.getElementById('reviewSupplierName').textContent = name;
            document.getElementById('reviewSupplierAddress').textContent = address;
            document.getElementById('reviewSupplierContact').textContent = contact;

            addSupplierGoToStep(2);
        }

        function addSupplierPrevStep() {
            addSupplierGoToStep(1);
        }

        function closeAddModal() {
            document.getElementById('addSupplierModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function saveSupplier() {
            var name = document.getElementById('addSupplierName').value.trim();
            var address = document.getElementById('addSupplierAddress').value.trim();
            var contact = document.getElementById('addSupplierContact').value.trim();

            if (!name || !address || !contact) {
                showError('Please fill in all supplier fields.');
                return;
            }
            if (!/^(?=.*\d)[0-9+().\s-]+$/.test(contact)) {
                showError('Contact number may only contain numbers, spaces, +, -, parentheses, and periods.');
                return;
            }

            const payload = {
                supplier_name: name,
                address: address,
                contact_number: contact
            };

            const submitButton = document.getElementById('addSupplierSubmitBtn');
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';

            fetch('/api/suppliers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            })
            .then(async response => {
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Error saving supplier.');
                return data;
            })
            .then(data => {
                if (data.success) {
                    closeAddModal();
                    showSuccess(data.message);
                    loadSuppliers();
                } else {
                    showError(data.message || 'Error saving supplier.');
                }
            })
                        .catch(error => {
                console.error('Error:', error);
                showError(error.message || 'Error saving supplier.');
            })
                        .finally(() => {
                submitButton.disabled = false;
                submitButton.textContent = 'Add Supplier';
            });
        }

        // ─── EDIT SUPPLIER MODAL ───
        function openEditModal(supplierId) {
            currentSupplierId = supplierId;
            
            // Fetch supplier details
            fetch(`/api/suppliers/${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const supplier = data.data;
                        document.getElementById('editSupplierName').value = supplier.supplier_name;
                        document.getElementById('editSupplierAddress').value = supplier.address;
                        document.getElementById('editSupplierContact').value = supplier.contact_number;
                        document.getElementById('currentSupplierName').textContent = supplier.supplier_name || '—';
                        document.getElementById('currentSupplierAddress').textContent = supplier.address || '—';
                        document.getElementById('currentSupplierContact').textContent = supplier.contact_number || '—';
                    }
                })
                .catch(error => console.error('Error loading supplier:', error));

            document.getElementById('editSupplierModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editSupplierModal').classList.remove('active');
            document.body.style.overflow = '';
            currentSupplierId = null;
        }

        let supplierToDelete = null;

        function openDeleteModal(supplierId) {
            supplierToDelete = supplierId;
            document.getElementById('deleteConfirmMessage').textContent = 'Are you sure you want to permanently delete this supplier?';
            document.getElementById('deleteConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            supplierToDelete = null;
        }

        function confirmDelete() {
            if (!supplierToDelete) {
                closeDeleteModal();
                return;
            }

            var deleteBtn = document.getElementById('confirmDeleteBtn');
            setButtonLoading(deleteBtn, true, 'Deleting...');

            fetch(`/api/suppliers/${supplierToDelete}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                                closeDeleteModal();
                if (data.success) {
                    closeViewModal();
                    closeEditModal();
                    showSuccess(data.message || 'Supplier deleted successfully!');
                    loadSuppliers();
                } else {
                    showError(data.message || 'Error deleting supplier');
                }
            })
            .catch(error => {
                closeDeleteModal();
                console.error('Error deleting supplier:', error);
                showError('Error deleting supplier');
            })
            .finally(() => setButtonLoading(deleteBtn, false));
        }

        function updateSupplier() {
            var saveBtn = document.querySelector('#editSupplierModal .btn-save');
            var name = document.getElementById('editSupplierName').value.trim();
            var address = document.getElementById('editSupplierAddress').value.trim();
            var contact = document.getElementById('editSupplierContact').value.trim();

            if (!name || !address || !contact) {
                alert('Please fill in all fields.');
                return;
            }
            if (!/^(?=.*\d)[0-9+().\s-]+$/.test(contact)) {
                showError('Contact number may only contain numbers, spaces, +, -, parentheses, and periods.');
                return;
            }

            if (!currentSupplierId) {
                alert('Supplier ID not found.');
                return;
            }

            const payload = {
                supplier_name: name,
                address: address,
                contact_number: contact
            };

            setButtonLoading(saveBtn, true, 'Saving...');
            fetch(`/api/suppliers/${currentSupplierId}`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeEditModal();
                    showSuccess(data.message);
                    loadSuppliers();
                } else {
                    alert('Error updating supplier');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error updating supplier');
            })
            .finally(() => setButtonLoading(saveBtn, false));
        }

        // ─── SUCCESS NOTIFICATION ───
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = notif.querySelector('.success-content span:not(.success-icon)');
            if (msgSpan) msgSpan.textContent = message || 'Supplier saved successfully!';
            notif.style.display = 'block';
            setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
        }

        function showError(message) {
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            if (msgSpan) {
                msgSpan.textContent = message || 'An error occurred. Please try again.';
            }
            notif.style.display = 'block';
            setTimeout(function() {
                closeError();
            }, 5000);
        }

        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
        }

        // ─── CLOSE MODALS ON BACKDROP CLICK ───
        document.getElementById('addSupplierModal').addEventListener('click', function(e) {
            if (e.target === this) { closeAddModal(); }
        });
        document.getElementById('editSupplierModal').addEventListener('click', function(e) {
            if (e.target === this) { closeEditModal(); }
        });
        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) { closeDeleteModal(); }
        });

        document.addEventListener('click', function(e) {
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) {
                    closeSuccess();
                }
            }
        });
        </script>
    <script src="{{ asset('js/table-scroll-fade.js') }}"></script>

</body>
</html>
