<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Suppliers - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Osuppliers.css') }}">
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
            <span id="successMessage">Saved successfully!</span>
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

        <!-- Page Header -->
        <div class="page-header">
            <h1>SUPPLIERS</h1>
            <!-- "Add Supplier" button REMOVED for Operations -->
        </div>

        <!-- Filters Bar -->
        <div class="filters-bar">
            <input type="text" class="search-input" placeholder="Search suppliers...">
            <select>
                <option>All Suppliers</option>
                <option>Active</option>
                <option>Inactive</option>
            </select>
        </div>

        <!-- Table (Read-Only – no Actions column) -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Supplier Name</th>
                        <th>Contact Person</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr onclick="openViewModal(this)"
                        data-name="Holcim Philippines"
                        data-contact="Maria Santos"
                        data-email="m.santos@holcim.com"
                        data-phone="+63 2 888 1234"
                        data-address="Pasig City, Metro Manila"
                        data-status="Active">
                        <td><strong>Holcim Philippines</strong></td>
                        <td>Maria Santos</td>
                        <td>m.santos@holcim.com</td>
                        <td>+63 2 888 1234</td>
                        <td>Pasig City, Metro Manila</td>
                        <td><span class="status-badge active"><span class="dot"></span> Active</span></td>
                    </tr>
                    <tr onclick="openViewModal(this)"
                        data-name="Metro Steel Supply"
                        data-contact="John Reyes"
                        data-email="j.reyes@metrosteel.com"
                        data-phone="+63 2 888 5678"
                        data-address="Quezon City, Metro Manila"
                        data-status="Active">
                        <td><strong>Metro Steel Supply</strong></td>
                        <td>John Reyes</td>
                        <td>j.reyes@metrosteel.com</td>
                        <td>+63 2 888 5678</td>
                        <td>Quezon City, Metro Manila</td>
                        <td><span class="status-badge active"><span class="dot"></span> Active</span></td>
                    </tr>
                    <tr onclick="openViewModal(this)"
                        data-name="ColorPro Paint Center"
                        data-contact="Anna Cruz"
                        data-email="a.cruz@colorpro.com"
                        data-phone="+63 2 888 9012"
                        data-address="Makati City, Metro Manila"
                        data-status="Active">
                        <td><strong>ColorPro Paint Center</strong></td>
                        <td>Anna Cruz</td>
                        <td>a.cruz@colorpro.com</td>
                        <td>+63 2 888 9012</td>
                        <td>Makati City, Metro Manila</td>
                        <td><span class="status-badge active"><span class="dot"></span> Active</span></td>
                    </tr>
                    <tr onclick="openViewModal(this)"
                        data-name="Laguna Aggregate Supplier"
                        data-contact="David Tan"
                        data-email="d.tan@lagunaagg.com"
                        data-phone="+63 49 555 3456"
                        data-address="Sta. Rosa, Laguna"
                        data-status="Active">
                        <td><strong>Laguna Aggregate Supplier</strong></td>
                        <td>David Tan</td>
                        <td>d.tan@lagunaagg.com</td>
                        <td>+63 49 555 3456</td>
                        <td>Sta. Rosa, Laguna</td>
                        <td><span class="status-badge active"><span class="dot"></span> Active</span></td>
                    </tr>
                    <tr onclick="openViewModal(this)"
                        data-name="SolidBlocks Manufacturing"
                        data-contact="Ringo Santos"
                        data-email="r.santos@solidblocks.com"
                        data-phone="+63 2 888 7890"
                        data-address="Valenzuela City, Metro Manila"
                        data-status="Inactive">
                        <td><strong>SolidBlocks Manufacturing</strong></td>
                        <td>Ringo Santos</td>
                        <td>r.santos@solidblocks.com</td>
                        <td>+63 2 888 7890</td>
                        <td>Valenzuela City, Metro Manila</td>
                        <td><span class="status-badge inactive"><span class="dot"></span> Inactive</span></td>
                    </tr>
                    <tr onclick="openViewModal(this)"
                        data-name="AquaFlow Industrial Supply"
                        data-contact="Maria Lopez"
                        data-email="m.lopez@aquaflow.com"
                        data-phone="+63 2 888 3456"
                        data-address="Mandaluyong City, Metro Manila"
                        data-status="Active">
                        <td><strong>AquaFlow Industrial Supply</strong></td>
                        <td>Maria Lopez</td>
                        <td>m.lopez@aquaflow.com</td>
                        <td>+63 2 888 3456</td>
                        <td>Mandaluyong City, Metro Manila</td>
                        <td><span class="status-badge active"><span class="dot"></span> Active</span></td>
                    </tr>
                    <tr onclick="openViewModal(this)"
                        data-name="PowerLine Electricals"
                        data-contact="Jose Garcia"
                        data-email="j.garcia@powerline.com"
                        data-phone="+63 2 888 6789"
                        data-address="Paranaque City, Metro Manila"
                        data-status="Active">
                        <td><strong>PowerLine Electricals</strong></td>
                        <td>Jose Garcia</td>
                        <td>j.garcia@powerline.com</td>
                        <td>+63 2 888 6789</td>
                        <td>Paranaque City, Metro Manila</td>
                        <td><span class="status-badge active"><span class="dot"></span> Active</span></td>
                    </tr>
                    <tr onclick="openViewModal(this)"
                        data-name="Prime Tiles Depot"
                        data-contact="Carla Mendoza"
                        data-email="c.mendoza@primetiles.com"
                        data-phone="+63 2 888 0123"
                        data-address="Marikina City, Metro Manila"
                        data-status="Inactive">
                        <td><strong>Prime Tiles Depot</strong></td>
                        <td>Carla Mendoza</td>
                        <td>c.mendoza@primetiles.com</td>
                        <td>+63 2 888 0123</td>
                        <td>Marikina City, Metro Manila</td>
                        <td><span class="status-badge inactive"><span class="dot"></span> Inactive</span></td>
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
                <a href="#">«</a>
                <a href="#" class="active">1</a>
                <a href="#">2</a>
                <a href="#">3</a>
                <span class="dots">...</span>
                <a href="#">12</a>
                <a href="#">13</a>
                <a href="#">»</a>
            </div>
        </div>

    </main>

    <!-- ─── VIEW MODAL (Read-Only) ─── -->
    <div id="viewModal" class="modal-overlay">
        <div class="modal-container" style="width: 700px; max-width: 95%;">
            <div class="modal-header">
                <div>
                    <h2 id="viewModalTitle">Supplier Details</h2>
                </div>
                <button class="modal-close" onclick="closeViewModal()">×</button>
            </div>

            <div class="detail-grid">
                <div class="detail-item">
                    <label>Supplier Name</label>
                    <span id="viewNameDisplay" class="detail-value">—</span>
                </div>
                <div class="detail-item">
                    <label>Contact Person</label>
                    <span id="viewContactDisplay" class="detail-value">—</span>
                </div>
                <div class="detail-item">
                    <label>Email Address</label>
                    <span id="viewEmailDisplay" class="detail-value">—</span>
                </div>
                <div class="detail-item">
                    <label>Phone Number</label>
                    <span id="viewPhoneDisplay" class="detail-value">—</span>
                </div>
                <div class="detail-item" style="grid-column: 1 / -1;">
                    <label>Address</label>
                    <span id="viewAddressDisplay" class="detail-value">—</span>
                </div>
                <div class="detail-item">
                    <label>Status</label>
                    <span id="viewStatusDisplay" class="detail-value status-badge">—</span>
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
                msgSpan.textContent = message || 'Saved successfully!';
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

        function openViewModal(row) {
            var name = row.dataset.name || '';
            var contact = row.dataset.contact || '';
            var email = row.dataset.email || '';
            var phone = row.dataset.phone || '';
            var address = row.dataset.address || '';
            var status = row.dataset.status || '';

            document.getElementById('viewNameDisplay').textContent = name;
            document.getElementById('viewContactDisplay').textContent = contact;
            document.getElementById('viewEmailDisplay').textContent = email;
            document.getElementById('viewPhoneDisplay').textContent = phone;
            document.getElementById('viewAddressDisplay').textContent = address;

            var statusEl = document.getElementById('viewStatusDisplay');
            statusEl.textContent = status;
            statusEl.className = 'detail-value status-badge';
            if (status === 'Active') {
                statusEl.classList.add('active');
            } else if (status === 'Inactive') {
                statusEl.classList.add('inactive');
            }

            document.getElementById('viewModal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeViewModal() {
            document.getElementById('viewModal').classList.remove('active');
            document.body.style.overflow = '';
        }

        document.getElementById('viewModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeViewModal();
            }
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