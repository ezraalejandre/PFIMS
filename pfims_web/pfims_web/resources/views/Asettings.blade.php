<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Settings - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Asettings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="settings-page">

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
            <a href="{{ url('/anotifications') }}" style="opacity: 1; position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span style="font-weight: 600;">Notifications</span>
                <span class="notif-badge" id="notifBadge">6</span>
            </a>
            <a href="{{ url('/aprofile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>{{ auth()->user()->name }}</span>
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
                    <form method="POST" action="{{ url('/logout') }}" style="width: 100%; margin: 0; padding: 0;">
                        @csrf
                        <button type="submit" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%; background: none; border: none; cursor: pointer; padding: 0; font: inherit;">
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
            <h1>SETTINGS</h1>
        </div>

        <!-- Settings Wrapper -->
        <div class="settings-wrapper">

            <!-- Settings Sidebar (NO User Management, NO Configurations) -->
            <div class="settings-sidebar">
                <ul class="settings-nav">
                    <li class="active" onclick="switchSettings(this, 'profile')">Profile</li>
                    <li onclick="switchSettings(this, 'security')">Account &amp; Security</li>
                    <li onclick="switchSettings(this, 'preferences')">System Preferences</li>
                    <li onclick="switchSettings(this, 'notifications')">Notifications</li>
                </ul>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">

                <!-- ─── PROFILE ─── -->
                <div id="section-profile" class="settings-section">
                    <div class="section-title">Profile</div>
                    <div class="section-desc">Manage your personal information and account settings.</div>
                    <div class="profile-preview">
                        <div class="avatar">EC</div>
                        <div class="info">
                            <div class="name">Elito V. Catapang</div>
                            <div class="role">Accounting Manager</div>
                        </div>
                        <button class="btn-go-profile" data-url="{{ url('/aprofile') }}" onclick="window.location.href=this.dataset.url">Go to Profile</button>
                    </div>
                </div>

                <!-- ─── ACCOUNT & SECURITY ─── -->
                <div id="section-security" class="settings-section" style="display: none;">
                    <div class="section-title">Account &amp; Security</div>
                    <div class="section-desc">Manage your password and security settings.</div>
                    <div class="security-item">
                        <div class="left">
                            <div class="label">Password</div>
                            <div class="desc">Last changed {{ auth()->user()->updated_at ? \Carbon\Carbon::parse(auth()->user()->updated_at)->diffForHumans() : 'Never' }}</div>
                        </div>
                        <button class="btn-change" onclick="openChangePasswordModal()">Change Password</button>
                    </div>
                    <div class="security-item">
                        <div class="left">
                            <div class="label">Two Factor Authentication</div>
                            <div class="desc">Add an extra layer of security to your account</div>
                        </div>
                        <button class="btn-change" onclick="open2FAModal()">Manage Authentication</button>
                    </div>
                </div>

                <!-- ─── SYSTEM PREFERENCES ─── -->
                <div id="section-preferences" class="settings-section" style="display: none;">
                    <div class="section-title">System Preferences</div>
                    <div class="section-desc">Customize your system experience and preferences.</div>
                    <div class="preference-item">
                        <div class="left">
                            <div class="label">Dark Mode</div>
                            <div class="desc">Toggle dark mode for the entire system</div>
                        </div>
                        <div class="toggle" data-theme-toggle role="switch" aria-label="Dark mode" aria-checked="false" onclick="toggleDarkMode()">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                </div>

                <!-- ─── NOTIFICATIONS ─── -->
                <div id="section-notifications" class="settings-section" style="display: none;">
                    <div class="section-title">Notifications</div>
                    <div class="section-desc">Manage your notification preferences.</div>
                    <div class="preference-item">
                        <div class="left">
                            <div class="label">System Notifications</div>
                            <div class="desc">Receive system updates and maintenance alerts</div>
                        </div>
                        <div class="toggle active" onclick="toggleSwitch(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                    <div class="preference-item">
                        <div class="left">
                            <div class="label">Project Updates</div>
                            <div class="desc">Receive notifications for project milestones and changes</div>
                        </div>
                        <div class="toggle active" data-notif-category="project_updates" onclick="toggleNotifCategory(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                    <div class="preference-item">
                        <div class="left">
                            <div class="label">Budget Alerts</div>
                            <div class="desc">Get notified when projects exceed budget thresholds</div>
                        </div>
                        <div class="toggle active" data-notif-category="budget_alerts" onclick="toggleNotifCategory(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- ─── CHANGE PASSWORD MODAL ─── -->
    <div id="changePasswordModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        <div class="modal-container" style="background: #fff; width: 480px; max-width: 95%; border-radius: 16px; padding: 30px 35px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.4rem; font-weight: 600; color: #1a2b3c; margin: 0;">Change Password</h2>
                <button class="modal-close" onclick="closeChangePasswordModal()" style="background: none; border: none; font-size: 2rem; cursor: pointer; color: #888; line-height: 1; transition: 0.2s;">×</button>
            </div>
            <div class="modal-body">
                <form id="changePasswordForm" onsubmit="submitChangePassword(event)">
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">Current Password <span style="color: #d32f2f;">*</span></label>
                        <div class="password-control" style="position: relative;">
                            <input type="password" id="currentPassword" placeholder="Enter your current password" style="width: 100%; padding: 10px 44px 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa; transition: 0.3s;" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" onclick="togglePasswordVisibility('currentPassword', this)"></button>
                        </div>
                        <div id="currentPasswordError" style="color: #d32f2f; font-size: 0.8rem; margin-top: 4px; display: none;"></div>
                    </div>
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">New Password <span style="color: #d32f2f;">*</span></label>
                        <div class="password-control" style="position: relative;">
                            <input type="password" id="newPassword" placeholder="Enter new password (min 8 characters)" style="width: 100%; padding: 10px 44px 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa; transition: 0.3s;" required minlength="8">
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" onclick="togglePasswordVisibility('newPassword', this)"></button>
                        </div>
                        <div style="font-size: 0.75rem; color: #888; margin-top: 4px;">Password must be at least 8 characters long</div>
                        <div id="newPasswordError" style="color: #d32f2f; font-size: 0.8rem; margin-top: 4px; display: none;"></div>
                    </div>
                    <div class="form-group" style="margin-bottom: 18px;">
                        <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">Confirm New Password <span style="color: #d32f2f;">*</span></label>
                        <div class="password-control" style="position: relative;">
                            <input type="password" id="confirmPassword" placeholder="Re-enter new password" style="width: 100%; padding: 10px 44px 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa; transition: 0.3s;" required>
                            <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" onclick="togglePasswordVisibility('confirmPassword', this)"></button>
                        </div>
                        <div id="confirmPasswordError" style="color: #d32f2f; font-size: 0.8rem; margin-top: 4px; display: none;"></div>
                    </div>
                    <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #e9ecef; padding-top: 20px;">
                        <button type="button" class="btn-cancel" onclick="closeChangePasswordModal()" style="background: transparent; color: #888; border: 1px solid #ddd; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Cancel</button>
                        <button type="submit" class="btn-save" style="background: #c9a96e; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ─── TWO FACTOR AUTHENTICATION MODAL ─── -->
    <div id="twofaModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="width: 450px; max-width: 95%;">
            <div class="modal-header">
                <h2>Two Factor Authentication</h2>
                <button class="modal-close" onclick="close2FAModal()">×</button>
            </div>
            <div class="modal-body">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #f0ebe2;">
                    <span style="font-weight: 600; font-size: 1rem; color: #1a2b3c;">Enable 2FA</span>
                    <div class="toggle active" onclick="toggleSwitch(this)">
                        <div class="toggle-slider"></div>
                    </div>
                </div>
                <div style="display: flex; flex-direction: column; gap: 12px; padding-left: 5px;">
                    <label style="display: flex; align-items: center; gap: 10px; font-size: 0.95rem; color: #333; cursor: pointer; padding: 8px 12px; border-radius: 6px; background: #faf8f5; transition: 0.2s;">
                        <input type="radio" name="twofa_method" value="email" checked style="width: 18px; height: 18px; accent-color: #c9a96e; cursor: pointer;">
                        Email
                    </label>
                    <label style="display: flex; align-items: center; gap: 10px; font-size: 0.95rem; color: #333; cursor: pointer; padding: 8px 12px; border-radius: 6px; background: #faf8f5; transition: 0.2s;">
                        <input type="radio" name="twofa_method" value="sms" style="width: 18px; height: 18px; accent-color: #c9a96e; cursor: pointer;">
                        SMS
                    </label>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: center; gap: 12px; margin-top: 25px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <button class="btn-cancel" onclick="close2FAModal()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: transparent; color: #888; transition: 0.3s;">Cancel</button>
                    <button class="btn-save" onclick="save2FA()" style="padding: 10px 24px; border-radius: 8px; font-weight: 600; font-size: 0.9rem; cursor: pointer; border: none; background: #c9a96e; color: #fff; transition: 0.3s;">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ─── DELETE CONFIRMATION MODAL ─── -->
    <div id="deleteConfirmModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="width: 400px; max-width: 95%;">
            <div class="modal-header">
                <h2>Confirm Deletion</h2>
                <button class="modal-close" onclick="closeDeleteModal()">×</button>
            </div>
            <div class="modal-body">
                <p id="deleteConfirmMessage" style="font-size: 1rem; color: #333; margin-bottom: 10px;">
                    Are you sure you want to permanently delete this item?
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

    <script>
        // ─── SETTINGS NAVIGATION ───
        var csrfToken = '{{ csrf_token() }}';
        function switchSettings(el, section) {
            var navItems = document.querySelectorAll('.settings-nav li');
            navItems.forEach(function(item) { item.classList.remove('active'); });
            el.classList.add('active');
            var sections = document.querySelectorAll('.settings-section');
            sections.forEach(function(sec) { sec.style.display = 'none'; });
            var target = document.getElementById('section-' + section);
            if (target) { target.style.display = 'block'; }
            closeChangePasswordModal();
            close2FAModal();
            console.log('Switched to: ' + section);
        }

        // ─── TOGGLE SWITCH ───
        function toggleSwitch(el) {
            el.classList.toggle('active');
            var status = el.classList.contains('active') ? 'Enabled' : 'Disabled';
            console.log('Switch toggled: ' + status);
        }

        function toggleNotifCategory(el) {
            var category = el.getAttribute('data-notif-category');
            el.classList.toggle('active');
            localStorage.setItem('notif_mute_' + category, el.classList.contains('active') ? 'false' : 'true');
        }

        function initNotifCategoryToggles() {
            document.querySelectorAll('[data-notif-category]').forEach(function(el) {
                var muted = localStorage.getItem('notif_mute_' + el.getAttribute('data-notif-category')) === 'true';
                el.classList.toggle('active', !muted);
            });
        }

        document.addEventListener('DOMContentLoaded', initNotifCategoryToggles);

        // ─── ERROR NOTIFICATION (POP-UP) ───
        function showError(message) {
            var notif = document.getElementById('errorNotification');
            var msgSpan = document.getElementById('errorMessage');
            if (msgSpan) { msgSpan.textContent = message || 'An error occurred. Please try again.'; }
            notif.style.display = 'block';
            setTimeout(function() { closeError(); }, 5000);
        }

        function closeError() {
            document.getElementById('errorNotification').style.display = 'none';
        }

        // ─── SUCCESS NOTIFICATION (POP-UP) ───
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            if (msgSpan) { msgSpan.textContent = message || 'Saved successfully!'; }
            notif.style.display = 'block';
            setTimeout(function() { closeSuccess(); }, 5000);
        }

        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
        }

        // ─── DELETE CONFIRMATION MODAL ───
        var deleteCallback = null;

        function openDeleteModal(message, callback) {
            document.getElementById('deleteConfirmMessage').textContent = message || 'Are you sure you want to permanently delete this item?';
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
            if (e.target === this) { closeDeleteModal(); }
        });

        // ─── CHANGE PASSWORD ───
        function openChangePasswordModal() {
            // Reset form
            document.getElementById('changePasswordForm').reset();
            document.getElementById('currentPasswordError').style.display = 'none';
            document.getElementById('newPasswordError').style.display = 'none';
            document.getElementById('confirmPasswordError').style.display = 'none';
            resetPasswordToggleButtons();
            
            // Show modal
            document.getElementById('changePasswordModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function togglePasswordVisibility(inputId, button) {
            var input = document.getElementById(inputId);
            if (input.type === 'password') {
                input.type = 'text';
                button.textContent = '🙈';
            } else {
                input.type = 'password';
                button.textContent = '👁';
            }
        }

        function passwordIcon(isVisible) {
            return isVisible
                ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.94 10.94 0 0 1 12 20c-7 0-11-8-11-8a20.3 20.3 0 0 1 5.06-6.06M9.9 4.24A10.5 10.5 0 0 1 12 4c7 0 11 8 11 8a20.3 20.3 0 0 1-3.22 4.44M14.12 14.12a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'
                : '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8Z"/><circle cx="12" cy="12" r="3"/></svg>';
        }

        function setPasswordToggleIcon(button, isVisible) {
            if (!button) return;
            button.innerHTML = passwordIcon(isVisible);
            button.classList.toggle('is-visible', isVisible);
            button.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        }

        function resetPasswordToggleButtons() {
            document.querySelectorAll('[data-password-toggle]').forEach(function(button) {
                setPasswordToggleIcon(button, false);
            });
            ['currentPassword', 'newPassword', 'confirmPassword'].forEach(function(id) {
                var input = document.getElementById(id);
                if (input) input.type = 'password';
            });
        }

        function togglePasswordVisibility(inputId, button) {
            if (window.event) window.event.preventDefault();
            var input = document.getElementById(inputId);
            if (!input) return;
            var isVisible = input.type === 'password';
            input.type = isVisible ? 'text' : 'password';
            setPasswordToggleIcon(button, isVisible);
            input.focus({ preventScroll: true });
        }

        function submitChangePassword(event) {
            event.preventDefault();
            
            // Clear previous errors
            document.getElementById('currentPasswordError').style.display = 'none';
            document.getElementById('newPasswordError').style.display = 'none';
            document.getElementById('confirmPasswordError').style.display = 'none';
            
            var currentPassword = document.getElementById('currentPassword').value;
            var newPassword = document.getElementById('newPassword').value;
            var confirmPassword = document.getElementById('confirmPassword').value;
            
            // Match the password validation used by the admin settings page.
            var hasError = false;

            if (!currentPassword) {
                document.getElementById('currentPasswordError').textContent = 'Current password is required';
                document.getElementById('currentPasswordError').style.display = 'block';
                hasError = true;
            }

            if (newPassword.length < 8) {
                document.getElementById('newPasswordError').textContent = 'Password must be at least 8 characters long';
                document.getElementById('newPasswordError').style.display = 'block';
                hasError = true;
            } else if (newPassword === currentPassword) {
                document.getElementById('newPasswordError').textContent = 'The new password must be different from your current password.';
                document.getElementById('newPasswordError').style.display = 'block';
                hasError = true;
            }

            if (newPassword !== confirmPassword) {
                document.getElementById('confirmPasswordError').textContent = 'Passwords do not match';
                document.getElementById('confirmPasswordError').style.display = 'block';
                hasError = true;
            }

            if (hasError) return;
            
            // Send request to server
            var payload = {
                current_password: currentPassword,
                new_password: newPassword,
                new_password_confirmation: confirmPassword,
                _token: csrfToken
            };
            
            fetch('/change-password', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    closeChangePasswordModal();
                    showSuccess('Password updated successfully!');
                    // Update the "Last changed" text
                    var descElement = document.querySelector('.security-item .left .desc');
                    if (descElement) {
                        descElement.textContent = 'Last changed just now';
                    }
                } else {
                    // Handle validation errors
                    if (data.errors) {
                        if (data.errors.current_password) {
                            document.getElementById('currentPasswordError').textContent = data.errors.current_password[0];
                            document.getElementById('currentPasswordError').style.display = 'block';
                        }
                        if (data.errors.new_password) {
                            document.getElementById('newPasswordError').textContent = data.errors.new_password[0];
                            document.getElementById('newPasswordError').style.display = 'block';
                        }
                        if (data.errors.new_password_confirmation) {
                            document.getElementById('confirmPasswordError').textContent = data.errors.new_password_confirmation[0];
                            document.getElementById('confirmPasswordError').style.display = 'block';
                        }
                    } else {
                        alert(data.message || 'Failed to update password. Please try again.');
                    }
                }
            })
            .catch(function(err) {
                console.error('Error:', err);
                alert('An error occurred. Please try again.');
            });
        }

        // ─── CLOSE CHANGE PASSWORD MODAL ON BACKDROP CLICK ───
        document.addEventListener('DOMContentLoaded', function() {
            var passwordModal = document.getElementById('changePasswordModal');
            if (passwordModal) {
                passwordModal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeChangePasswordModal();
                    }
                });
            }
        });

        // Add this to your JavaScript
        document.getElementById('newPassword').addEventListener('input', function() {
            var password = this.value;
            var strengthIndicator = document.getElementById('passwordStrength');
            if (!strengthIndicator) {
                var indicator = document.createElement('div');
                indicator.id = 'passwordStrength';
                indicator.style.marginTop = '4px';
                indicator.style.fontSize = '0.75rem';
                this.parentElement.parentElement.appendChild(indicator);
            }
            
            var strength = checkPasswordStrength(password);
            var indicator = document.getElementById('passwordStrength');
            indicator.textContent = 'Strength: ' + strength.text;
            indicator.style.color = strength.color;
        });

        function checkPasswordStrength(password) {
            var strength = {
                text: 'Weak',
                color: '#d32f2f'
            };
            
            if (password.length >= 8) {
                var hasUpperCase = /[A-Z]/.test(password);
                var hasLowerCase = /[a-z]/.test(password);
                var hasNumbers = /\d/.test(password);
                var hasSymbols = /[!@#$%^&*(),.?":{}|<>]/.test(password);
                
                var score = 0;
                if (hasUpperCase) score++;
                if (hasLowerCase) score++;
                if (hasNumbers) score++;
                if (hasSymbols) score++;
                
                if (score >= 4) {
                    strength = { text: 'Strong', color: '#2e7d32' };
                } else if (score >= 3) {
                    strength = { text: 'Good', color: '#f57c00' };
                } else if (score >= 2) {
                    strength = { text: 'Fair', color: '#f57c00' };
                } else {
                    strength = { text: 'Weak', color: '#d32f2f' };
                }
            }
            
            return strength;
        }

        document.addEventListener('DOMContentLoaded', function() {
            resetPasswordToggleButtons();
            if (new URLSearchParams(window.location.search).get('change_password') === '1') {
                var securityTab = document.querySelector(".settings-nav li[onclick*='security']");
                if (securityTab) switchSettings(securityTab, 'security');
                openChangePasswordModal();
            }
        });

        // ─── TWO FACTOR AUTHENTICATION ───
        function open2FAModal() {
            document.getElementById('twofaModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function close2FAModal() {
            document.getElementById('twofaModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function save2FA() {
            var isEnabled = document.querySelector('#twofaModal .toggle').classList.contains('active');
            var selectedMethod = document.querySelector('input[name="twofa_method"]:checked');
            if (!selectedMethod) { showError('Please select a 2FA method.'); return; }
            var method = selectedMethod.value;
            close2FAModal();
            showSuccess('2FA ' + (isEnabled ? 'enabled' : 'disabled') + ' with ' + method + ' successfully!');
            console.log('2FA Settings:', { enabled: isEnabled, method: method });
        }

        // ─── CLOSE MODALS ON BACKDROP ───
        document.getElementById('changePasswordModal').addEventListener('click', function(e) {
            if (e.target === this) { closeChangePasswordModal(); }
        });
        document.getElementById('twofaModal').addEventListener('click', function(e) {
            if (e.target === this) { close2FAModal(); }
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
