<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Item Suppliers - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/suppliers.css') }}">
</head>
<body>

    <!-- ─── SUCCESS NOTIFICATION ─── -->
    <div id="successNotification" class="success-notification" style="display: none;">
        <div class="success-content">
            <span class="success-icon">●</span>
            <span>Supplier saved successfully!</span>
            <button class="success-close" onclick="closeSuccess()">×</button>
        </div>
    </div>

    @include('partials.header')

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
                <button class="btn-save" onclick="updateSupplier()">Add Supplier</button>
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

        // ─── CLOSE MODALS ON BACKDROP CLICK ───
        document.getElementById('addSupplierModal').addEventListener('click', function(e) {
            if (e.target === this) { closeAddModal(); }
        });
        document.getElementById('editSupplierModal').addEventListener('click', function(e) {
            if (e.target === this) { closeEditModal(); }
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