{{-- Prepare the values used by this page section. --}}
@php $portal = $portal ?? 'admin'; @endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Item Suppliers - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/suppliers.css') }}">
    <link rel="stylesheet" href="{{ asset('css/module-analytics.css') }}">
        {{-- Set the styles used by this page section. --}}
        <style>
        /* Style the delete confirm modal. */
        #deleteConfirmModal { z-index: 9999 !important; }
        /* Style the btn delete supplier. */
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
        /* Style the btn delete supplier on hover. */
        .btn-delete-supplier:hover {
            background: #b71c1c;
            transform: translateY(-2px);
        }
        /* Style the modal footer footer left. */
        .modal-footer .footer-left,
        .modal-footer .footer-right {
            display: flex;
            gap: 12px;
            align-items: center;
        }
        /* Style the modal footer. */
        .modal-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        /* Style the add supplier modal modal container. */
        #addSupplierModal .modal-container {
            width: 460px;
        }
        /* Style the supplier section title. */
        .supplier-section-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0 0 12px;
        }
    </style>
    <link rel="stylesheet" href="{{ asset('css/'.$portal.'.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/modal-policy.js') }}?v={{ filemtime(public_path('js/modal-policy.js')) }}"></script>
    {{-- Set the styles used by this page section. --}}
    <style id="pfims-session-privacy">html { visibility:hidden; }</style>
    {{-- Set up the actions used by this page section. --}}
    <script data-navigation-granted="{{ session('_page_navigation_granted', false) ? '1' : '0' }}" data-session-epoch="{{ session('navigation_epoch', '') }}" src="{{ asset('js/protected-navigation.js') }}?v={{ filemtime(public_path('js/protected-navigation.js')) }}"></script>
</head>
<body class="suppliers-page" data-portal="{{ $portal }}">
    
    <!-- Error notification (pop-up) -->
    <div id="errorNotification" class="error-notification" style="display: none;">
        <div class="error-content">
            <span class="error-icon">!</span>
            <span id="errorMessage">An error occurred. Please try again.</span>
            <button class="error-close" onclick="closeError()">×</button>
        </div>
    </div>

    <!-- Success notification -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span>Supplier saved successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    <!-- Delete confirmation modal -->
    <div id="deleteConfirmModal" class="modal-overlay pfims-delete-confirm" style="display: none; z-index: 9999;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage" style="font-size: 1rem; color: #333; margin-bottom: 10px;">
                    Are you sure you want to move this supplier to Trash Bin?
                </p>
                <p style="font-size: 0.85rem; color: #888; margin-bottom: 20px;">
                    You can restore deleted records from Settings → Trash Bin for 30 days before permanent deletion.
                </p>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: center; gap: 12px; margin-top: 10px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                <button class="btn-cancel" onclick="closeDeleteModal()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: transparent; color: #888; transition: 0.3s;">Cancel</button>
                <button class="btn-delete" id="confirmDeleteBtn" onclick="confirmDelete()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #c95c5c; color: #fff; transition: 0.3s;">Delete</button>
            </div>
        </div>
    </div>

    <!-- Full-width header (fixed) -->
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

    <!-- Sidebar -->
    <aside class="sidebar">
                {{-- Show the page navigation. --}}
                <nav>
            <ul>
                <li><a href="{{ url('/dashboard') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
                <li><a href="{{ url('/projects') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon">PROJECTS</a></li>
                <li><a href="{{ url('/finance') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon">FINANCE</a></li>
                <li class="active"><a href="{{ url('/inventory') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon">INVENTORY</a></li>
                <li><a href="{{ url('/reports') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/folder.svg') }}" alt="" class="nav-link-icon">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li>
                    <a href="{{ url('/settings') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                        <img src="{{ asset('images/settings.png') }}" alt="Settings" class="nav-icon">
                        Settings
                    </a>
                </li>
                <li class="logout">
                    {{-- Collect the details entered in this form. --}}
                    <form method="POST" action="{{ url('/logout') }}" style="width: 100%; margin: 0; padding: 0;">
                        @csrf
                        <button type="submit" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%; background: none; border: none; cursor: pointer; padding: 0; font: inherit; color: inherit;">
                            <img src="{{ asset('images/logout.png') }}" alt="Log Out" class="nav-icon">
                            Log out
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    </aside>

    <!-- Main content -->
    <main class="main-content">

                <div class="page-header">
            <h1>SUPPLIERS</h1>
            <button class="btn-add-supplier" onclick="openAddModal()">+ Add Supplier</button>
        </div>

        <div class="filters-bar">
            <input type="search" id="supplierSearch" class="search-input" maxlength="100" placeholder="Search supplier, address, or contact..." oninput="filterSuppliers()">
            <select id="supplierSort" onchange="filterSuppliers()"><option value="name">Sort by supplier name</option><option value="items">Sort by items supplied</option><option value="alerts">Sort by stock alerts</option></select>
            <button type="button" class="btn-clear-filters" onclick="clearSupplierFilters()">X</button>
        </div>

        {{-- Show the supplier kpis section. --}}
        <div id="supplierKpis" class="stats-grid-supplier"></div>

        <div class="table-wrapper">
            {{-- Show the records in a table. --}}
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

        {{-- Show the suppliers pagination section. --}}
        <div class="pagination-wrapper" id="suppliersPagination">
            <div class="rows-info">
                Rows per page
                <select id="supplierRowsPerPage" aria-label="Supplier rows per page" onchange="changeSupplierPageSize()">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
                <span id="suppliersTotalCount" class="pagination-total">Total: 0</span>
            </div>
            {{-- Show the suppliers pagination links section. --}}
            <div class="pagination-links" id="suppliersPaginationLinks">
                <!-- Generated by JavaScript -->
            </div>
        </div>

    </main>

    <!-- Overlay / modal (add supplier) -->
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

            {{-- Show the add supplier step1 section. --}}
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

            {{-- Show the add supplier step2 section. --}}
            <div class="modal-step" id="addSupplierStep2" style="display: none;">
                <h3 class="supplier-section-title">Review supplier details</h3>
                <div class="summary-list">
                    {{-- Show the review supplier name section. --}}
                    <div class="summary-item"><strong>Supplier Name</strong><span class="summary-value" id="reviewSupplierName">—</span></div>
                    {{-- Show the review supplier address section. --}}
                    <div class="summary-item"><strong>Address</strong><span class="summary-value" id="reviewSupplierAddress">—</span></div>
                    {{-- Show the review supplier contact section. --}}
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

    <!-- Overlay / modal (supplier details) -->
    <div id="viewSupplierModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Supplier Details</h2>
                <button class="modal-close" onclick="closeViewModal()">×</button>
            </div>
            <div class="modal-body view-details-grid">
                {{-- Show the view supplier name section. --}}
                <div class="view-item"><label>Supplier Name</label><span id="viewSupplierName" class="view-value">—</span></div>
                {{-- Show the view supplier address section. --}}
                <div class="view-item"><label>Supplier Address</label><span id="viewSupplierAddress" class="view-value">—</span></div>
                {{-- Show the view supplier contact section. --}}
                <div class="view-item"><label>Supplier Contact no.</label><span id="viewSupplierContact" class="view-value">—</span></div>
            </div>
            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeViewModal()">Close</button>
                <button class="btn-edit-project" type="button" onclick="openEditFromView()">Edit</button>
                <button class="btn-delete btn-delete-supplier" type="button" onclick="openDeleteModal(currentSupplierId)">Delete</button>
            </div>
        </div>
    </div>

    <!-- Overlay / modal (edit supplier) -->
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
<div class="right-col">
                        <label for="editSupplierName">Supplier Name</label>
                        <input type="text" placeholder="Supplier name" id="editSupplierName">
                    </div>
                </div>

                <hr class="modal-divider">

                <!-- Section 2: Address -->
                <div class="edit-section">
<div class="right-col">
                        <label for="editSupplierAddress">Address</label>
                        <input type="text" placeholder="Supplier address" id="editSupplierAddress">
                    </div>
                </div>

                <hr class="modal-divider">

                <!-- Section 3: Contact no. -->
                <div class="edit-section">
<div class="right-col">
                        <label for="editSupplierContact">Contact no.</label>
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

    {{-- Set up the actions used by this page section. --}}
    <script>
        // Global state
        let currentSupplierId = null;
        let suppliersData = [];

        // Button loading state (prevents double-click / double-submit)
        function setButtonLoading(button, isLoading, loadingText) {
            // Return early if the button is missing or false.
            if (!button) return;
            // Run this step if the is loading is set.
            if (isLoading) {
                button.dataset.originalHtml = button.innerHTML;
                button.textContent = loadingText || 'Loading...';
                button.disabled = true;
                button.style.opacity = '0.7';
                button.style.cursor = 'not-allowed';
            } else {
                // Run this step if the call check matches.
                if (Object.prototype.hasOwnProperty.call(button.dataset, 'originalHtml')) {
                    button.innerHTML = button.dataset.originalHtml;
                    delete button.dataset.originalHtml;
                }
                button.disabled = false;
                button.style.opacity = '';
                button.style.cursor = '';
            }
        }

                // Load suppliers on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSuppliers();
            fetchNotifBadge();
        });

        // Fetch unread notification count
        function fetchNotifBadge() {
            // Use the result when this request finishes.
            fetch('/api/notifications/unread-count', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) {
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error('Failed to load unread count.');
                return response.json();
            })
            .then(function(data) {
                var badge = document.getElementById('notifBadge');
                // Return early if the badge is missing or false.
                if (!badge) return;
                var count = data.unread_count || 0;
                // Run this step if the count is above 0.
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

        // Load suppliers from api
        function loadSuppliers() {
            // Use the result when this request finishes.
            Promise.all([fetch('/api/suppliers').then(response => response.json()), fetch('/api/inventory').then(response => response.json()).catch(() => ({success: false, data: []}))])
                .then(([supplierResponse, inventoryResponse]) => {
                    // Run this step if the success is set.
                    if (supplierResponse.success) {
                        const items = inventoryResponse.success ? inventoryResponse.data : [];
                        // Build a value for each supplier.
                        suppliersData = supplierResponse.data.map(supplier => {
                            const supplied = items.filter(item => Number(item.supplier_id) === Number(supplier.supplier_id));
                            return {...supplier, item_count: supplied.length, low_stock_count: supplied.filter(item => Number(item.current_stock) <= Number(item.reorder_level)).length};
                        });
                        filterSuppliers();
                    }
                })
                .catch(error => console.error('Error loading suppliers:', error));
        }

                // Pagination state
        var supplierPageSize = 10;
        var supplierCurrentPage = 1;
        var supplierFilteredData = [];

        // Render suppliers in table
        function renderSuppliers(suppliers) {
            supplierFilteredData = suppliers;
            updateSupplierAnalytics(suppliers);
            renderSupplierPage(1);
        }

        // Show supplier page.
        function renderSupplierPage(page) {
            supplierCurrentPage = page;
            const tbody = document.getElementById('supplierTableBody');
            tbody.innerHTML = '';

            // Run this step if the supplier filtered data length equals 0.
            if (supplierFilteredData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">No suppliers found.</td></tr>';
                renderSupplierPagination();
                return;
            }

            const start = (page - 1) * supplierPageSize;
            const end = Math.min(start + supplierPageSize, supplierFilteredData.length);
            const pageData = supplierFilteredData.slice(start, end);

            // Process each supplier.
            pageData.forEach(supplier => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${escapeSupplierHtml(supplier.supplier_name)}</strong></td>
                    <td>${escapeSupplierHtml(supplier.address)}</td>
                    <td>${escapeSupplierHtml(String(supplier.contact_number || '').trim() || '—')}</td>
                    <td style="text-align: center;">
                    <button class="pfims-row-action" onclick="openViewModal(${supplier.supplier_id}, this)" title="View supplier" aria-label="View supplier"><img src="{{ asset('images/view.jpg') }}" alt=""></button>
                    <button class="pfims-row-action" onclick="openEditModal(${supplier.supplier_id})" title="Edit supplier" aria-label="Edit supplier"><img src="{{ asset('images/edit.jpg') }}" alt=""></button>
                    <button class="pfims-row-action" onclick="openDeleteModal(${supplier.supplier_id})" title="Delete supplier" aria-label="Delete supplier"><img src="{{ asset('images/delete.jpg') }}" alt=""></button>
                `;
                tbody.appendChild(row);
            });

            renderSupplierPagination();
            // Run this step if the refresh table scroll fade is set.
            if (window.refreshTableScrollFade) window.refreshTableScrollFade();
        }

        // Show supplier pagination.
        function renderSupplierPagination() {
            const container = document.getElementById('suppliersPaginationLinks');
            // Return early if the container is missing or false.
            if (!container) return;

            const total = supplierFilteredData.length;
            const totalEl = document.getElementById('suppliersTotalCount');
            // Run this step if the total el is set.
            if (totalEl) totalEl.textContent = 'Total: ' + total;
            const totalPages = Math.ceil(total / supplierPageSize);
            const current = supplierCurrentPage;

                        // Run this step if the total pages is at most 1.
                        if (totalPages <= 1) {
                container.innerHTML = '';
                return;
            }

            var html = '';
            html += `<a href="#" onclick="renderSupplierPage(${current - 1}); return false;" class="${current <= 1 ? 'disabled' : ''}">«</a>`;
            // Repeat for each step in this range.
            for (var i = 1; i <= totalPages; i++) {
                html += `<a href="#" onclick="renderSupplierPage(${i}); return false;" class="${i === current ? 'active' : ''}">${i}</a>`;
            }
            html += `<a href="#" onclick="renderSupplierPage(${current + 1}); return false;" class="${current >= totalPages ? 'disabled' : ''}">»</a>`;
            container.innerHTML = html;
        }

        // Get the change supplier page size.
        function changeSupplierPageSize() {
            const select = document.getElementById('supplierRowsPerPage');
            supplierPageSize = parseInt(select.value) || 10;
            renderSupplierPage(1);
        }

        // Filter suppliers.
        function filterSuppliers() {
            const term = (document.getElementById('supplierSearch')?.value || '').toLowerCase().trim();
            const sort = document.getElementById('supplierSort')?.value || 'name';
            const filtered = suppliersData.filter(supplier => [supplier.supplier_name, supplier.address, supplier.contact_number].some(value => (value || '').toLowerCase().includes(term)));
            filtered.sort((a, b) => sort === 'items' ? b.item_count - a.item_count : (sort === 'alerts' ? b.low_stock_count - a.low_stock_count : (a.supplier_name || '').localeCompare(b.supplier_name || '')));
            renderSuppliers(filtered);
        }

        // Clear supplier filters.
        function clearSupplierFilters() {
            document.getElementById('supplierSearch').value = '';
            document.getElementById('supplierSort').value = 'name';
            filterSuppliers();
        }

        // Update supplier analytics.
        function updateSupplierAnalytics(filtered) {
            const totalItems = filtered.reduce((sum, supplier) => sum + supplier.item_count, 0);
            const alerts = filtered.reduce((sum, supplier) => sum + supplier.low_stock_count, 0);
            document.getElementById('supplierKpis').innerHTML = [
                ['Matching suppliers', filtered.length, 'Suppliers shown by the current filters'],
                ['Items supplied', totalItems, 'Inventory items linked to matching suppliers'],
                ['Low-stock item links', alerts, 'Linked items that need replenishment']
            ].map(([label, value, info]) => `<article class="stat-card-supplier kpi-card"><small>${label}</small><strong>${value.toLocaleString()}</strong><span class="kpi-sub">${info}</span></article>`).join('');
            const top = [...filtered].sort((a,b) => b.item_count - a.item_count).slice(0,8); const max = Math.max(...top.map(item => item.item_count), 1);
            const chart = document.getElementById('supplierCoverageChart');
            // Return early if the chart is missing or false.
            if (!chart) return;
            // Run this step if the top length is missing or false.
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

        // Get the escape supplier html.
        function escapeSupplierHtml(value) {
            return String(value || '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[character]));
        }

                // Open view modal.
                function openViewModal(supplierId, triggerBtn) {
            currentSupplierId = supplierId;
            setButtonLoading(triggerBtn, true, '...');
            // Use the result when this request finishes.
            fetch(`/api/suppliers/${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    // Run this step if the success is missing or false.
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

        // Close view modal.
        function closeViewModal() {
            document.getElementById('viewSupplierModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Open edit from view.
        function openEditFromView() {
            // Return early if the current supplier id is missing or false.
            if (!currentSupplierId) return;
            closeViewModal();
            openEditModal(currentSupplierId);
        }

        // Hide notification badge on click
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            // Run this step if the badge is set.
            if (badge) {
                badge.style.display = 'none';
            }
        }

        // Add supplier modal
                function openAddModal() {
            currentSupplierId = null;
            document.getElementById('addSupplierModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            document.getElementById('addSupplierName').value = '';
            document.getElementById('addSupplierAddress').value = '';
            document.getElementById('addSupplierContact').value = '';
            addSupplierGoToStep(1);
        }

        // Get the add supplier go to step.
        function addSupplierGoToStep(step) {
            // Process each el.
            document.querySelectorAll('#addSupplierModal .modal-step').forEach(function(el) {
                el.style.display = 'none';
            });
            document.getElementById('addSupplierStep' + step).style.display = 'block';
            // Process each el.
            document.querySelectorAll('#addSupplierModal .step-indicator .step').forEach(function(el, index) {
                el.classList.toggle('active', index + 1 === step);
                el.classList.toggle('completed', index + 1 < step);
            });
        }

        // Get the add supplier next step.
        function addSupplierNextStep() {
            var name = document.getElementById('addSupplierName').value.trim();
            var address = document.getElementById('addSupplierAddress').value.trim();
            var contact = document.getElementById('addSupplierContact').value.trim();

            // Run this step if the name is missing or false.
            if (!name) { showError('Please enter a supplier name.'); return; }
            // Run this step if the address is missing or false.
            if (!address) { showError('Please enter a supplier address.'); return; }
            // Run this step if the contact is missing or false.
            if (!contact) { showError('Please enter a supplier contact number.'); return; }
            // Run this step if the test is missing or false.
            if (!/^(?=.*\d)[0-9+().\s-]+$/.test(contact)) {
                showError('Contact number may only contain numbers, spaces, +, -, parentheses, and periods.');
                return;
            }

            document.getElementById('reviewSupplierName').textContent = name;
            document.getElementById('reviewSupplierAddress').textContent = address;
            document.getElementById('reviewSupplierContact').textContent = contact;

            addSupplierGoToStep(2);
        }

        // Get the add supplier prev step.
        function addSupplierPrevStep() {
            addSupplierGoToStep(1);
        }

        // Close add modal.
        function closeAddModal() {
            document.getElementById('addSupplierModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        // Save supplier.
        function saveSupplier() {
            var name = document.getElementById('addSupplierName').value.trim();
            var address = document.getElementById('addSupplierAddress').value.trim();
            var contact = document.getElementById('addSupplierContact').value.trim();

            // Run this step if the name is missing or false or the address is missing or false or the contact is missing or false.
            if (!name || !address || !contact) {
                showError('Please fill in all supplier fields.');
                return;
            }
            // Run this step if the test is missing or false.
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

            // Use the result when this request finishes.
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
                // Run this step if the response ok is missing or false.
                if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat()[0] || 'Error saving supplier.');
                return data;
            })
            .then(data => {
                // Run this step if the success is set.
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

        // Edit supplier modal
        function openEditModal(supplierId) {
            currentSupplierId = supplierId;
            
            // Fetch supplier details
            fetch(`/api/suppliers/${supplierId}`)
                .then(response => response.json())
                .then(data => {
                    // Run this step if the success is set.
                    if (data.success) {
                        const supplier = data.data;
                        document.getElementById('editSupplierName').value = supplier.supplier_name;
                        document.getElementById('editSupplierAddress').value = supplier.address;
                        document.getElementById('editSupplierContact').value = supplier.contact_number;
                    }
                })
                .catch(error => console.error('Error loading supplier:', error));

            document.getElementById('editSupplierModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Close edit modal.
        function closeEditModal() {
            document.getElementById('editSupplierModal').classList.remove('active');
            document.body.style.overflow = '';
            currentSupplierId = null;
        }

        let supplierToDelete = null;

        // Open delete modal.
        function openDeleteModal(supplierId) {
            supplierToDelete = supplierId;
            document.getElementById('deleteConfirmMessage').textContent = 'Are you sure you want to move this supplier to Trash Bin?';
            document.getElementById('deleteConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        // Close delete modal.
        function closeDeleteModal() {
            document.getElementById('deleteConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
            supplierToDelete = null;
        }

        // Get the confirm delete.
        function confirmDelete() {
            // Run this step if the supplier to delete is missing or false.
            if (!supplierToDelete) {
                closeDeleteModal();
                return;
            }

            var deleteBtn = document.getElementById('confirmDeleteBtn');
            setButtonLoading(deleteBtn, true, 'Deleting...');

            // Use the result when this request finishes.
            fetch(`/api/suppliers/${supplierToDelete}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                                closeDeleteModal();
                // Run this step if the success is set.
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

        // Update supplier.
        function updateSupplier() {
            var saveBtn = document.querySelector('#editSupplierModal .btn-save');
            var name = document.getElementById('editSupplierName').value.trim();
            var address = document.getElementById('editSupplierAddress').value.trim();
            var contact = document.getElementById('editSupplierContact').value.trim();

            // Run this step if the name is missing or false or the address is missing or false or the contact is missing or false.
            if (!name || !address || !contact) {
                alert('Please fill in all fields.');
                return;
            }
            // Run this step if the test is missing or false.
            if (!/^(?=.*\d)[0-9+().\s-]+$/.test(contact)) {
                showError('Contact number may only contain numbers, spaces, +, -, parentheses, and periods.');
                return;
            }

            // Run this step if the current supplier id is missing or false.
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
            // Use the result when this request finishes.
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
                // Run this step if the success is set.
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

        // Success notification
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = notif.querySelector('.success-content span:not(.success-icon)');
            // Run this step if the msg span is set.
            if (msgSpan) msgSpan.textContent = message || 'Supplier saved successfully!';
            notif.style.display = 'block';
            // Run this action after the delay.
            setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        // Close success.
        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
        }

        // Show error.
        function showError(message) {
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            // Run this step if the msg span is set.
            if (msgSpan) {
                msgSpan.textContent = message || 'An error occurred. Please try again.';
            }
            notif.style.display = 'block';
            // Run this action after the delay.
            setTimeout(function() {
                closeError();
            }, 5000);
        }

        // Close error.
        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
        }

        // Close modals on backdrop click
        document.getElementById('addSupplierModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) { closeAddModal(); }
        });
        // Handle the click event.
        document.getElementById('editSupplierModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) { closeEditModal(); }
        });
        // Handle the click event.
        document.getElementById('viewSupplierModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) { closeViewModal(); }
        });
        // Handle the click event.
        document.getElementById('deleteConfirmModal').addEventListener('click', function(e) {
            // Run this step if the target equals the value.
            if (e.target === this) { closeDeleteModal(); }
        });

        // Handle the click event.
        document.addEventListener('click', function(e) {
            // Run this step if the style display equals block.
            if (document.getElementById('successNotification').style.display === 'block') {
                // Run this step if the closest is missing or false.
                if (!e.target.closest('.success-notification')) {
                    closeSuccess();
                }
            }
        });
        </script>
    {{-- Set up the actions used by this page section. --}}
    <script src="{{ asset('js/table-scroll-fade.js') }}"></script>
    {{-- Set up the actions used by this page section. --}}
    <script data-login-epoch="{{ session('navigation_epoch', 'initial') }}" src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}"></script>

    @include('partials.validation-alert')
</body>
</html>
