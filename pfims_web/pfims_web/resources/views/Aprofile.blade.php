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
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
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
                <span class="notif-badge" id="notifBadge" style="display: none;">0</span>
            </a>
            <a href="{{ url('/aprofile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>{{ auth()->user()->name === 'Administrator' ? 'Admin' : auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
                <nav>
            <ul>
                <li><a href="{{ url('/adashboard') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
                <li><a href="{{ url('/afinance') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon">FINANCE</a></li>
                <li><a href="{{ url('/areports') }}" style="color: inherit; text-decoration: none; display: block;"><img src="{{ asset('images/reports.png') }}" alt="" class="nav-link-icon">REPORTS</a></li>
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
            <h1>PROFILE</h1>
            <div class="subtitle">account &amp; settings management</div>
        </div>

        @if(session('status'))
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    showSuccess(@json(session('status')));
                });
            </script>
        @endif

        <!-- Profile Card -->
        <form id="profileCard" class="profile-card" action="{{ url('/profile') }}" method="POST">
            @csrf
            @method('PATCH')

            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                <div class="profile-info">
                    <h2 id="displayName">{{ $user->name }}</h2>
                    <div class="role" id="displayRole">{{ strtoupper($user->role ?? 'USER') }}</div>
                </div>
                <div class="profile-actions">
                    <button type="button" class="btn-cancel-edit" onclick="cancelEdit()">Cancel</button>
                    <button type="button" class="btn-save-profile" onclick="saveProfile()">Save Changes</button>
                    <button type="button" class="btn-edit-profile" onclick="enableEdit()">Edit Profile</button>
                </div>
            </div>

            <!-- Profile Details -->
            <div class="profile-details">
                <div class="section-title">CONTACT INFORMATION</div>
                <div class="detail-grid">
                    <!-- Full Name -->
                    <div class="detail-item">
                        <label>Full Name</label>
                        <div class="value" id="displayFullName">{{ old('name', $user->name) }}</div>
                        <input type="text" name="name" class="edit-input" id="editFullName" value="{{ old('name', $user->name) }}">
                    </div>
                    <!-- Email Address -->
                    <div class="detail-item">
                        <label>Email Address</label>
                        <div class="value" id="displayEmail">{{ old('email', $user->email) }}</div>
                        <input type="email" name="email" class="edit-input" id="editEmail" value="{{ old('email', $user->email) }}">
                    </div>
                    <!-- Phone Number -->
                    <div class="detail-item">
                        <label>Phone Number</label>
                        <div class="value" id="displayPhone">{{ old('phone', $user->phone) }}</div>
                        <input type="text" name="phone" class="edit-input" id="editPhone" value="{{ old('phone', $user->phone) }}">
                    </div>
                    <!-- Location -->
                    <div class="detail-item">
                        <label>Location</label>
                        <div class="value" id="displayLocation">{{ old('location', $user->location) }}</div>
                        <input type="text" name="location" class="edit-input" id="editLocation" value="{{ old('location', $user->location) }}">
                    </div>
                </div>
            </div>

        </form>

    </main>

    <script>
                // ─── HIDE NOTIFICATION BADGE ON CLICK ───
        function hideBadge(event) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                badge.style.display = 'none';
            }
            // The link will still navigate to /notifications.
        }

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

        document.addEventListener('DOMContentLoaded', fetchNotifBadge);

        // ─── ERROR NOTIFICATION (POP-UP) ───
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

        // ─── SUCCESS NOTIFICATION (POP-UP) ───
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

        document.addEventListener('click', function(e) {
            if (document.getElementById('errorNotification').style.display === 'block') {
                if (!e.target.closest('.error-notification')) { closeError(); }
            }
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) { closeSuccess(); }
            }
        });

        // ─── BUTTON LOADING STATE (prevents double-click / double-submit) ───
        function setButtonLoading(button, isLoading, loadingText) {
            if (!button) return;
            if (isLoading) {
                button.dataset.originalText = button.textContent;
                button.textContent = loadingText || 'Saving...';
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

        // ─── ENABLE EDIT MODE ───
        function enableEdit() {
            var card = document.getElementById('profileCard');
            card.classList.add('edit-mode');
        }

        // ─── CANCEL EDIT ───
        function cancelEdit() {
            var card = document.getElementById('profileCard');
            card.classList.remove('edit-mode');

            // Reset input values back to display values
            document.getElementById('editFullName').value = document.getElementById('displayFullName').textContent;
            document.getElementById('editEmail').value = document.getElementById('displayEmail').textContent;
            document.getElementById('editPhone').value = document.getElementById('displayPhone').textContent;
            document.getElementById('editLocation').value = document.getElementById('displayLocation').textContent;
        }

        // ─── SAVE PROFILE ───
        function saveProfile() {
            var saveBtn = document.querySelector('.profile-actions .btn-save-profile');
            if (saveBtn && saveBtn.disabled) return;

            var fullName = document.getElementById('editFullName').value.trim();
            var email = document.getElementById('editEmail').value.trim();
            var phone = document.getElementById('editPhone').value.trim();
            var location = document.getElementById('editLocation').value.trim();

            if (!fullName || !email || !phone || !location) {
                showError('Please fill in all fields.');
                return;
            }

            // Update display values locally before submit
            document.getElementById('displayFullName').textContent = fullName;
            document.getElementById('displayEmail').textContent = email;
            document.getElementById('displayPhone').textContent = phone;
            document.getElementById('displayLocation').textContent = location;
            document.getElementById('displayName').textContent = fullName;

            var card = document.getElementById('profileCard');
            card.classList.remove('edit-mode');

            setButtonLoading(saveBtn, true, 'Saving...');
            document.getElementById('profileCard').submit();
        }
    </script>

</body>
</html>
