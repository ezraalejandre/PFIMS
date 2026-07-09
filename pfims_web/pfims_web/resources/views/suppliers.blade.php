<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Item Suppliers - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/suppliers.css') }}">
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
                <li><a href="{{ url('/finance') }}">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}" style="color: inherit; text-decoration: none; display: block;">INVENTORY</a></li>
                <li class="active"><a href="{{ url('/suppliers') }}" style="color: inherit; text-decoration: none; display: block;">SUPPLIERS</a></li>
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

        <div class="page-header">
            <h1>ITEM SUPPLIERS</h1>
            <button class="btn-add-supplier" onclick="openAddModal()">+ Add Supplier</button>
        </div>

        <div class="filters-bar">
            <input type="text" class="search-input" placeholder="Search Category...">
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Address</th>
                        <th>Contact Number</th>
                        <th style="width: 60px; text-align: center;">Action</th>
                    </tr>
                </thead>
                <tbody id="supplierTableBody">
                    <!-- Suppliers will be loaded here dynamically -->
                </tbody>
            </table>
        </div>

    </main>

    <!-- ─── OVERLAY / MODAL (Add Supplier) ─── -->
    <div id="addSupplierModal" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h2>Add new supplier</h2>
                <button class="modal-close" onclick="closeAddModal()">×</button>
            </div>

            <div class="modal-body">
                <!-- Supplier Name: label + input on same row -->
                <div class="add-row">
                    <div class="add-label">Supplier Name</div>
                    <div class="add-input">
                        <input type="text" placeholder="Item Name" id="addSupplierName">
                    </div>
                </div>

                <hr class="modal-divider">

                <!-- Supplier Address & Contact side by side -->
                <div class="add-two-col">
                    <div class="col-group">
                        <label>Supplier Address</label>
                        <input type="text" placeholder="Item Name" id="addSupplierAddress">
                    </div>
                    <div class="col-group">
                        <label>Supplier Contact no.</label>
                        <input type="text" placeholder="Item Name" id="addSupplierContact">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button class="btn-save" onclick="saveSupplier()">Add Supplier</button>
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
                <!-- Section 1: Supplier Name -->
                <div class="edit-section">
                    <div class="left-col">
                        <div class="current-label">Current Supplier Name</div>
                        <div class="current-value">Description</div>
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
                        <div class="current-value">Description</div>
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
                        <div class="current-value">Description</div>
                    </div>
                    <div class="right-col">
                        <label>Contact no.</label>
                        <input type="text" placeholder="Item Name" id="editSupplierContact">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <button class="btn-delete-supplier" onclick="openDeleteModal(currentSupplierId)" type="button">Delete</button>
                    <button class="btn-save" onclick="updateSupplier()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Global state
        let currentSupplierId = null;

        // ─── LOAD SUPPLIERS ON PAGE LOAD ───
        document.addEventListener('DOMContentLoaded', function() {
            loadSuppliers();
        });

        // ─── LOAD SUPPLIERS FROM API ───
        function loadSuppliers() {
            fetch('/api/suppliers')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderSuppliers(data.data);
                    }
                })
                .catch(error => console.error('Error loading suppliers:', error));
        }

        // ─── RENDER SUPPLIERS IN TABLE ───
        function renderSuppliers(suppliers) {
            const tbody = document.getElementById('supplierTableBody');
            tbody.innerHTML = '';

            if (suppliers.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px;">No suppliers found.</td></tr>';
                return;
            }

            suppliers.forEach(supplier => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><strong>${supplier.supplier_name}</strong></td>
                    <td>${supplier.address}</td>
                    <td>${supplier.contact_number}</td>
                    <td style="text-align: center;">
                        <button class="btn-edit" onclick="openEditModal(${supplier.supplier_id})">
                            <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                        </button>
                    </td>
                `;
                tbody.appendChild(row);
            });
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
                alert('Please fill in all fields.');
                return;
            }

            const payload = {
                supplier_name: name,
                address: address,
                contact_number: contact
            };

            fetch('/api/suppliers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(payload)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeAddModal();
                    showSuccess(data.message);
                    loadSuppliers();
                } else {
                    alert('Error saving supplier');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving supplier');
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
            });
        }

        function updateSupplier() {
            var name = document.getElementById('editSupplierName').value.trim();
            var address = document.getElementById('editSupplierAddress').value.trim();
            var contact = document.getElementById('editSupplierContact').value.trim();

            if (!name || !address || !contact) {
                alert('Please fill in all fields.');
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
            });
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

</body>
</html>