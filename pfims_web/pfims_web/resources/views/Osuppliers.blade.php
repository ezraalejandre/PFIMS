<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Suppliers - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Osuppliers.css') }}">
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

    <!-- ─── SUCCESS NOTIFICATION (POP-UP) ─── -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span id="successMessage">Supplier saved successfully!</span>
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
                <li><a href="{{ url('/oinventory') }}">INVENTORY</a></li>
                <li class="active"><a href="{{ url('/osuppliers') }}">SUPPLIERS</a></li>
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
                <tbody>
                    <tr>
                        <td><strong>Holcim Philippines</strong></td>
                        <td>Manila, Philippines</td>
                        <td>+63 2 8888 1111</td>
                        <td style="text-align: center;">
                            <button class="btn-edit" onclick="openEditModal('Holcim Philippines', 'Manila, Philippines', '+63 2 8888 1111')">
                                <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>SteelAsia</strong></td>
                        <td>Pasig City, Philippines</td>
                        <td>+63 2 8888 2222</td>
                        <td style="text-align: center;">
                            <button class="btn-edit" onclick="openEditModal('SteelAsia', 'Pasig City, Philippines', '+63 2 8888 2222')">
                                <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Republic Cement</strong></td>
                        <td>Makati City, Philippines</td>
                        <td>+63 2 8888 3333</td>
                        <td style="text-align: center;">
                            <button class="btn-edit" onclick="openEditModal('Republic Cement', 'Makati City, Philippines', '+63 2 8888 3333')">
                                <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Boysen</strong></td>
                        <td>Mandaluyong, Philippines</td>
                        <td>+63 2 8888 4444</td>
                        <td style="text-align: center;">
                            <button class="btn-edit" onclick="openEditModal('Boysen', 'Mandaluyong, Philippines', '+63 2 8888 4444')">
                                <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Marlwasa</strong></td>
                        <td>Quezon City, Philippines</td>
                        <td>+63 2 8888 5555</td>
                        <td style="text-align: center;">
                            <button class="btn-edit" onclick="openEditModal('Marlwasa', 'Quezon City, Philippines', '+63 2 8888 5555')">
                                <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Atlanta Industries</strong></td>
                        <td>Muntinlupa, Philippines</td>
                        <td>+63 2 8888 6666</td>
                        <td style="text-align: center;">
                            <button class="btn-edit" onclick="openEditModal('Atlanta Industries', 'Muntinlupa, Philippines', '+63 2 8888 6666')">
                                <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Yelps Dodge Philippines</strong></td>
                        <td>Paranaque, Philippines</td>
                        <td>+63 2 8888 7777</td>
                        <td style="text-align: center;">
                            <button class="btn-edit" onclick="openEditModal('Yelps Dodge Philippines', 'Paranaque, Philippines', '+63 2 8888 7777')">
                                <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Knauf</strong></td>
                        <td>Taguig, Philippines</td>
                        <td>+63 2 8888 8888</td>
                        <td style="text-align: center;">
                            <button class="btn-edit" onclick="openEditModal('Knauf', 'Taguig, Philippines', '+63 2 8888 8888')">
                                <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>DN Steel</strong></td>
                        <td>Valenzuela, Philippines</td>
                        <td>+63 2 8888 9999</td>
                        <td style="text-align: center;">
                            <button class="btn-edit" onclick="openEditModal('DN Steel', 'Valenzuela, Philippines', '+63 2 8888 9999')">
                                <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                            </button>
                        </td>
                    </tr>
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
                <div class="add-row">
                    <div class="add-label">Supplier Name</div>
                    <div class="add-input">
                        <input type="text" placeholder="Item Name" id="addSupplierName">
                    </div>
                </div>

                <hr class="modal-divider">

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
                <input type="hidden" id="editSupplierOriginalName">

                <div class="edit-section">
                    <div class="left-col">
                        <div class="current-label">Current Supplier Name</div>
                        <div class="current-value" id="editCurrentNameDisplay">Description</div>
                    </div>
                    <div class="right-col">
                        <label>Supplier Name</label>
                        <input type="text" placeholder="Item Name" id="editSupplierName">
                    </div>
                </div>

                <hr class="modal-divider">

                <div class="edit-section">
                    <div class="left-col">
                        <div class="current-label">Current Supplier Address</div>
                        <div class="current-value" id="editCurrentAddressDisplay">Description</div>
                    </div>
                    <div class="right-col">
                        <label>Address</label>
                        <input type="text" placeholder="Item Name" id="editSupplierAddress">
                    </div>
                </div>

                <hr class="modal-divider">

                <div class="edit-section">
                    <div class="left-col">
                        <div class="current-label">Current Supplier Contact no.</div>
                        <div class="current-value" id="editCurrentContactDisplay">Description</div>
                    </div>
                    <div class="right-col">
                        <label>Contact no.</label>
                        <input type="text" placeholder="Item Name" id="editSupplierContact">
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <div class="footer-left">
                    <button class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                </div>
                <div class="footer-right">
                    <button class="btn-delete-supplier" onclick="deleteSupplier()">Delete</button>
                    <button class="btn-save" onclick="updateSupplier()">Save</button>
                </div>
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

        // ─── ERROR NOTIFICATION ───
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

        // ─── SUCCESS NOTIFICATION ───
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            if (msgSpan) {
                msgSpan.textContent = message || 'Supplier saved successfully!';
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

        // ─── DELETE CONFIRMATION MODAL ───
        var deleteCallback = null;

        function openDeleteModal(message, callback) {
            document.getElementById('deleteConfirmMessage').textContent = message || 'Are you sure you want to permanently delete this supplier?';
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

        // ─── ADD SUPPLIER MODAL ───
        function openAddModal() {
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
                showError('Please fill in all fields.');
                return;
            }

            closeAddModal();
            showSuccess('Supplier added successfully!');
            console.log('Add Supplier:', { name, address, contact });
        }

        // ─── EDIT SUPPLIER MODAL ───
        function openEditModal(name, address, contact) {
            document.getElementById('editSupplierOriginalName').value = name;

            document.getElementById('editCurrentNameDisplay').textContent = name;
            document.getElementById('editCurrentAddressDisplay').textContent = address || '';
            document.getElementById('editCurrentContactDisplay').textContent = contact || '';

            document.getElementById('editSupplierName').value = name;
            document.getElementById('editSupplierAddress').value = address || '';
            document.getElementById('editSupplierContact').value = contact || '';

            document.getElementById('editSupplierModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editSupplierModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        function updateSupplier() {
            var originalName = document.getElementById('editSupplierOriginalName').value;
            var name = document.getElementById('editSupplierName').value.trim();
            var address = document.getElementById('editSupplierAddress').value.trim();
            var contact = document.getElementById('editSupplierContact').value.trim();

            if (!name || !address || !contact) {
                showError('Please fill in all fields.');
                return;
            }

            closeEditModal();
            showSuccess('Supplier updated successfully!');
            console.log('Update Supplier:', { originalName, name, address, contact });
        }

        function deleteSupplier() {
            var name = document.getElementById('editSupplierOriginalName').value;
            if (!name) {
                showError('No supplier selected.');
                return;
            }
            openDeleteModal('Are you sure you want to permanently delete "' + name + '"?', function() {
                closeEditModal();
                showSuccess('Supplier "' + name + '" has been deleted.');
                console.log('Supplier deleted:', name);
            });
        }

        // ─── CLOSE MODALS ON BACKDROP ───
        document.getElementById('addSupplierModal').addEventListener('click', function(e) {
            if (e.target === this) { closeAddModal(); }
        });
        document.getElementById('editSupplierModal').addEventListener('click', function(e) {
            if (e.target === this) { closeEditModal(); }
        });

        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });
    </script>

</body>
</html>