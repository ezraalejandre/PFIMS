<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operations Settings - PFIMS</title>
    <link rel="stylesheet" href="{{ asset('css/Osettings.css') }}">
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
            <a href="{{ url('/onotifications') }}" style="opacity: 1; position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span style="font-weight: 600;">Notifications</span>
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
                <li><a href="{{ url('/odashboard') }}"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
                <li><a href="{{ url('/oprojects') }}"><img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon">PROJECTS</a></li>
                <li><a href="{{ url('/oinventory') }}"><img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon">INVENTORY</a></li>
                <li><a href="{{ url('/osuppliers') }}"><img src="{{ asset('images/suppliers.png') }}" alt="" class="nav-link-icon">SUPPLIERS</a></li>
                <li><a href="{{ url('/oreports') }}"><img src="{{ asset('images/reports.png') }}" alt="" class="nav-link-icon">REPORTS</a></li>
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
                            <div class="role">Operations Manager</div>
                        </div>
                        <button class="btn-go-profile" data-url="{{ url('/oprofile') }}" onclick="window.location.href=this.dataset.url">Go to Profile</button>
                    </div>
                </div>

                <!-- ─── ACCOUNT & SECURITY ─── -->
                <div id="section-security" class="settings-section" style="display: none;">
                    <div class="section-title">Account &amp; Security</div>
                    <div class="section-desc">Manage your password and security settings.</div>
                    <div class="security-item">
                        <div class="left">
                            <div class="label">Password</div>
                            <div class="desc">Last changed 3 months ago</div>
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
                            <div class="label">Inventory Alerts</div>
                            <div class="desc">Get notified when stock levels are low</div>
                        </div>
                        <div class="toggle active" data-notif-category="inventory_alerts" onclick="toggleNotifCategory(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- ─── CHANGE PASSWORD MODAL ─── -->
    <div id="changePasswordModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="width: 450px; max-width: 95%;">
            <div class="modal-header">
                <h2>Change Password</h2>
                <button class="modal-close" onclick="closeChangePasswordModal()">×</button>
            </div>
            <div class="modal-body">
                <form id="changePasswordForm" onsubmit="savePassword(event)">
                <div class="form-group">
                    <label>Current Password <span class="required">*</span></label>
                    <div class="password-control">
                        <input type="password" id="currentPassword" placeholder="Enter current password" required>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" onclick="togglePasswordVisibility('currentPassword', this)"></button>
                    </div>
                    <div id="currentPasswordError" class="error-message" style="display: none; color: #d32f2f; font-size: 0.75rem; margin-top: 4px;"></div>
                </div>
                <div class="form-group">
                    <label>New Password <span class="required">*</span></label>
                    <div class="password-control">
                        <input type="password" id="newPassword" placeholder="Enter new password" minlength="8" required>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" onclick="togglePasswordVisibility('newPassword', this)"></button>
                    </div>
                    <div id="newPasswordError" class="error-message" style="display: none; color: #d32f2f; font-size: 0.75rem; margin-top: 4px;"></div>
                    <div style="font-size: 0.75rem; color: #888; margin-top: 4px;">Minimum 8 characters</div>
                </div>
                <div class="form-group">
                    <label>Confirm New Password <span class="required">*</span></label>
                    <div class="password-control">
                        <input type="password" id="confirmPassword" placeholder="Confirm new password" minlength="8" required>
                        <button type="button" class="password-toggle" data-password-toggle aria-label="Show password" onclick="togglePasswordVisibility('confirmPassword', this)"></button>
                    </div>
                    <div id="confirmPasswordError" class="error-message" style="display: none; color: #d32f2f; font-size: 0.75rem; margin-top: 4px;"></div>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e9ecef;">
                    <button type="button" class="btn-cancel" onclick="closeChangePasswordModal()">Cancel</button>
                    <button type="submit" class="btn-save">Save</button>
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
                // ─── BUTTON LOADING STATE ───
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

        // ─── SETTINGS NAVIGATION ───
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
            document.getElementById('changePasswordForm').reset();
            document.getElementById('changePasswordModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            clearPasswordErrors();
            resetPasswordToggleButtons();
        }

        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').style.display = 'none';
            document.body.style.overflow = '';
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

        function clearPasswordErrors() {
            ['currentPasswordError', 'newPasswordError', 'confirmPasswordError'].forEach(function(id) {
                document.getElementById(id).style.display = 'none';
            });
        }

        function showPasswordError(id, message) {
            var error = document.getElementById(id);
            error.textContent = message;
            error.style.display = 'block';
        }

                async function savePassword(event) {
            if (event) event.preventDefault();
            var saveBtn = (event && event.submitter) || document.querySelector('#changePasswordForm .btn-save');
            clearPasswordErrors();
            var current = document.getElementById('currentPassword').value;
            var newPass = document.getElementById('newPassword').value;
            var confirm = document.getElementById('confirmPassword').value;
            var hasError = false;

            if (!current) { showPasswordError('currentPasswordError', 'Current password is required'); hasError = true; }
            if (newPass.length < 8) { showPasswordError('newPasswordError', 'Password must be at least 8 characters long'); hasError = true; }
            else if (newPass === current) { showPasswordError('newPasswordError', 'The new password must be different from your current password.'); hasError = true; }
            if (newPass !== confirm) { showPasswordError('confirmPasswordError', 'Passwords do not match'); hasError = true; }
            if (hasError) return;

            setButtonLoading(saveBtn, true, 'Updating...');

            try {
                var response = await fetch('/change-password', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({
                        current_password: current,
                        new_password: newPass,
                        new_password_confirmation: confirm
                    })
                });
                var data = await response.json();
                if (!response.ok || !data.success) {
                    var errors = data.errors || {};
                    if (errors.current_password) showPasswordError('currentPasswordError', errors.current_password[0]);
                    if (errors.new_password) showPasswordError('newPasswordError', errors.new_password[0]);
                    if (errors.new_password_confirmation) showPasswordError('confirmPasswordError', errors.new_password_confirmation[0]);
                    if (Object.keys(errors).length) return;
                    throw new Error(data.message || 'Failed to update password.');
                }
                                closeChangePasswordModal();
                showSuccess('Password updated successfully!');
                var descElement = document.querySelector('.security-item .left .desc');
                if (descElement) descElement.textContent = 'Last changed just now';
            } catch (error) {
                showError(error.message || 'Failed to update password.');
            } finally {
                setButtonLoading(saveBtn, false);
            }
        }

        document.getElementById('newPassword').addEventListener('input', function() {
            var indicator = document.getElementById('passwordStrength');
            if (!indicator) {
                indicator = document.createElement('div');
                indicator.id = 'passwordStrength';
                indicator.style.marginTop = '4px';
                indicator.style.fontSize = '0.75rem';
                this.closest('.form-group').appendChild(indicator);
            }

            var strength = checkPasswordStrength(this.value);
            indicator.textContent = 'Strength: ' + strength.text;
            indicator.style.color = strength.color;
        });

        function checkPasswordStrength(password) {
            var score = 0;
            if (/[A-Z]/.test(password)) score++;
            if (/[a-z]/.test(password)) score++;
            if (/\d/.test(password)) score++;
            if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) score++;

            if (password.length >= 8 && score >= 4) return { text: 'Strong', color: '#2e7d32' };
            if (password.length >= 8 && score >= 3) return { text: 'Good', color: '#f57c00' };
            if (password.length >= 8 && score >= 2) return { text: 'Fair', color: '#f57c00' };
            return { text: 'Weak', color: '#d32f2f' };
        }

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

        document.addEventListener('DOMContentLoaded', function() {
            resetPasswordToggleButtons();
            if (new URLSearchParams(window.location.search).get('change_password') === '1') {
                var securityTab = document.querySelector(".settings-nav li[onclick*='security']");
                if (securityTab) switchSettings(securityTab, 'security');
                openChangePasswordModal();
            }
        });

    </script>

</body>
</html>
