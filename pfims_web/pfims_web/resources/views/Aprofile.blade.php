<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Profile - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Aprofile.css') }}">
    <style>
        .error-notification { z-index: 9999 !important; }
        .success-notification { z-index: 9999 !important; }
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
            <span id="successMessage">Profile updated successfully!</span>
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
            <a href="{{ url('/anotifications') }}" onclick="hideBadge(event)" style="position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span>Notifications</span>
                <span class="notif-badge" id="notifBadge">6</span>
            </a>
            <a href="{{ url('/aprofile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>User</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
        <nav>
            <ul>
                <li><a href="{{ url('/adashboard') }}">DASHBOARD</a></li>
                <li><a href="{{ url('/afinance') }}">FINANCE</a></li>
                <li><a href="{{ url('/areports') }}">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li>
                    <a href="{{ url('/asettings') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
                        <img src="{{ asset('images/settings.jpg') }}" alt="Settings" class="nav-icon">
                        Settings
                    </a>
                </li>
                <li class="logout">
                    <a href="{{ url('/alanding') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
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
            <h1>PROFILE</h1>
            <div class="subtitle">account &amp; settings management</div>
        </div>

        <!-- Profile Card -->
        <div class="profile-card" id="profileCard">

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">EC</div>
                <div class="profile-info">
                    <h2 id="displayName">Elito V. Catapang</h2>
                    <div class="role" id="displayRole">ACCOUNTING MANAGER</div>
                    <div class="employee-id" id="displayEmployeeId">Employee ID: EVC-ACC-0012</div>
                </div>
                <div class="profile-actions">
                    <button class="btn-cancel-edit" onclick="cancelEdit()">Cancel</button>
                    <button class="btn-save-profile" onclick="saveProfile()">Save Changes</button>
                    <button class="btn-edit-profile" onclick="enableEdit()">Edit Profile</button>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="profile-details">
                <div class="section-title">CONTACT INFORMATION</div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Full Name</label>
                        <div class="value" id="displayFullName">Elito V. Catapang</div>
                        <input type="text" class="edit-input" id="editFullName" value="Elito V. Catapang">
                    </div>
                    <div class="detail-item">
                        <label>Email Address</label>
                        <div class="value" id="displayEmail">e.catapang@evc-dcs.com</div>
                        <input type="email" class="edit-input" id="editEmail" value="e.catapang@evc-dcs.com">
                    </div>
                    <div class="detail-item">
                        <label>Phone Number</label>
                        <div class="value" id="displayPhone">+63 917 555 0123</div>
                        <input type="text" class="edit-input" id="editPhone" value="+63 917 555 0123">
                    </div>
                    <div class="detail-item">
                        <label>Location</label>
                        <div class="value" id="displayLocation">Cebu City, Philippines</div>
                        <input type="text" class="edit-input" id="editLocation" value="Cebu City, Philippines">
                    </div>
                </div>
            </div>

        </div>

    </main>

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
                msgSpan.textContent = message || 'Profile updated successfully!';
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

        function enableEdit() {
            var card = document.getElementById('profileCard');
            card.classList.add('edit-mode');
        }

        function cancelEdit() {
            var card = document.getElementById('profileCard');
            card.classList.remove('edit-mode');

            document.getElementById('editFullName').value = document.getElementById('displayFullName').textContent;
            document.getElementById('editEmail').value = document.getElementById('displayEmail').textContent;
            document.getElementById('editPhone').value = document.getElementById('displayPhone').textContent;
            document.getElementById('editLocation').value = document.getElementById('displayLocation').textContent;
        }

        function saveProfile() {
            var fullName = document.getElementById('editFullName').value.trim();
            var email = document.getElementById('editEmail').value.trim();
            var phone = document.getElementById('editPhone').value.trim();
            var location = document.getElementById('editLocation').value.trim();

            if (!fullName || !email || !phone || !location) {
                showError('Please fill in all fields.');
                return;
            }

            document.getElementById('displayFullName').textContent = fullName;
            document.getElementById('displayEmail').textContent = email;
            document.getElementById('displayPhone').textContent = phone;
            document.getElementById('displayLocation').textContent = location;
            document.getElementById('displayName').textContent = fullName;

            var card = document.getElementById('profileCard');
            card.classList.remove('edit-mode');

            showSuccess('Profile updated successfully!');
            console.log('Profile updated:', { fullName, email, phone, location });
        }

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