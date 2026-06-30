<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PFIMS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
</head>
<body>

    <!-- ─── SUCCESS NOTIFICATION ─── -->
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
            <a href="{{ url('/notifications') }}" style="opacity: 1; position: relative;">
                <img src="{{ asset('images/notif.jpg') }}" style="height: 22px; width: auto; cursor: pointer;">
                <span style="font-weight: 600;">Notifications</span>
                <span class="notif-badge" id="notifBadge">6</span>
            </a>
            <a href="{{ url('/profile') }}" style="display: flex; align-items: center; gap: 5px; color: inherit; text-decoration: none;">
                <img src="{{ asset('images/user.jpg') }}" alt="User" style="height: 30px; width: 30px; cursor: pointer; border-radius: 50%; object-fit: cover;">
                <span>User</span>
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
                <li><a href="{{ url('/inventory') }}">INVENTORY</a></li>
                <li><a href="{{ url('/suppliers') }}">SUPPLIERS</a></li>
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
                    <a href="{{ url('/') }}" style="display: flex; align-items: center; gap: 12px; color: inherit; text-decoration: none; width: 100%;">
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
            <h1>SETTINGS</h1>
        </div>

        <!-- Settings Wrapper -->
        <div class="settings-wrapper">

            <!-- Settings Sidebar -->
            <div class="settings-sidebar">
                <ul class="settings-nav">
                    <li class="active" onclick="switchSettings(this, 'profile')">Profile</li>
                    <li onclick="switchSettings(this, 'security')">Account &amp; Security</li>
                    <li onclick="switchSettings(this, 'preferences')">System Preferences</li>
                    <li onclick="switchSettings(this, 'configurations')">Configurations</li>
                    <li onclick="switchSettings(this, 'notifications')">Notifications</li>
                    <li onclick="switchSettings(this, 'usermanagement')">User Management</li>
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
                            <div class="role">Project Manager</div>
                        </div>
                        <button class="btn-go-profile" onclick="window.location.href='{{ url('/profile') }}'">Go to Profile</button>
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
                        <button class="btn-change" onclick="alert('Change password functionality coming soon!')">Change Password</button>
                    </div>
                    <div class="security-item">
                        <div class="left">
                            <div class="label">Session Management</div>
                            <div class="desc">You are logged in on 2 devices</div>
                        </div>
                        <button class="btn-change" onclick="alert('Session management coming soon!')">Manage Sessions</button>
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
                        <div class="toggle" onclick="toggleSwitch(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                    <div class="preference-item">
                        <div class="left">
                            <div class="label">Email Notifications</div>
                            <div class="desc">Receive email notifications for system updates</div>
                        </div>
                        <div class="toggle active" onclick="toggleSwitch(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                    <div class="preference-item">
                        <div class="left">
                            <div class="label">Language</div>
                            <div class="desc">Choose your preferred language</div>
                        </div>
                        <select style="padding: 6px 14px; border: 1px solid #ddd; border-radius: 6px; background: #fafafa; font-size: 0.9rem; cursor: pointer;" onchange="alert('Language changed to: ' + this.value)">
                            <option value="en">English</option>
                            <option value="tl">Tagalog</option>
                            <option value="es">Spanish</option>
                        </select>
                    </div>
                    <div class="preference-item">
                        <div class="left">
                            <div class="label">Timezone</div>
                            <div class="desc">Set your system timezone</div>
                        </div>
                        <select style="padding: 6px 14px; border: 1px solid #ddd; border-radius: 6px; background: #fafafa; font-size: 0.9rem; cursor: pointer;" onchange="alert('Timezone changed to: ' + this.value)">
                            <option value="Asia/Manila">Asia/Manila (UTC+8)</option>
                            <option value="UTC">UTC</option>
                            <option value="America/New_York">America/New_York (UTC-4)</option>
                        </select>
                    </div>
                </div>

                <!-- ─── CONFIGURATIONS (Dropdown Management) ─── -->
                <div id="section-configurations" class="settings-section" style="display: none;">
                    <div class="section-title">Configurations</div>
                    <div class="section-desc">Manage system dropdown lists such as units, inventory categories, and expense categories.</div>

                    <div class="config-tabs">
                        <button class="config-tab active" onclick="switchConfigType(this, 'units')">Units</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'inv_categories')">Inventory Categories</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'exp_categories')">Expense Categories</button>
                    </div>

                    <button class="btn-add-user" onclick="openConfigAddModal()">+ Add New</button>

                    <div style="overflow-x: auto; margin-top: 15px;">
                        <table class="user-table" id="configTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="configTableBody">
                            </tbody>
                        </table>
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
                        <div class="toggle active" onclick="toggleSwitch(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                    <div class="preference-item">
                        <div class="left">
                            <div class="label">Budget Alerts</div>
                            <div class="desc">Get notified when projects exceed budget thresholds</div>
                        </div>
                        <div class="toggle active" onclick="toggleSwitch(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                    <div class="preference-item">
                        <div class="left">
                            <div class="label">Email Digests</div>
                            <div class="desc">Receive daily/weekly email summaries of activities</div>
                        </div>
                        <div class="toggle" onclick="toggleSwitch(this)">
                            <div class="toggle-slider"></div>
                        </div>
                    </div>
                </div>

                <!-- ─── USER MANAGEMENT ─── -->
                <div id="section-usermanagement" class="settings-section" style="display: none;">
                    <div class="section-title">User Management</div>
                    <div class="section-desc">Manage all user accounts, their roles, and permissions.</div>

                    <button class="btn-add-user" onclick="openAddUserModal()">+ Add User</button>

                    <div style="overflow-x: auto; margin-top: 15px;">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th style="text-align: center;">Action</th>
                                </tr>
                            </thead>
                                    <tbody>
                                        @foreach($users as $u)
                                        <tr>
                                            <td><strong>{{ $u->name }}</strong></td>
                                            <td>{{ $u->email }}</td>
                                            <td>
                                                @php
                                                    $roleLabel = ucfirst($u->role ?? 'user');
                                                @endphp
                                                <span class="role-badge {{ $u->role === 'admin' ? 'admin' : ($u->role === 'accounting' ? 'manager' : 'staff') }}">{{ $roleLabel }}</span>
                                            </td>
                                            <td>{{ $u->status ?? 'Active' }}</td>
                                            <td style="text-align: center;">
                                                <button class="btn-edit-user" onclick="openUserConfig({{ $u->id }})">
                                                    <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                                                </button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                        </table>
                    </div>

                    <div id="userConfigDetails" style="display: none; background: #faf8f5; border-radius: 12px; padding: 20px; border-left: 4px solid #c9a96e; margin-top: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div>
                                <span style="font-size: 1rem; font-weight: 600; color: #1a2b3c;" id="configUserName">User Name</span>
                                <span class="role-badge" id="configUserRole" style="margin-left: 10px;">Role</span>
                            </div>
                            <button class="btn-close-config" onclick="closeUserConfig()" style="background: transparent; border: none; font-size: 1.5rem; cursor: pointer; color: #888;">×</button>
                        </div>

                        <div style="margin-bottom: 12px;">
                            <label style="display:block; font-weight:600; margin-bottom:6px;">Full Name</label>
                            <input type="text" id="configName" readonly style="width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:6px; background:#f5f5f5;" />
                        </div>
                        <div style="margin-bottom: 12px;">
                            <label style="display:block; font-weight:600; margin-bottom:6px;">Email</label>
                            <input type="email" id="configEmail" readonly style="width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:6px; background:#f5f5f5;" />
                        </div>
                        <div style="margin-bottom: 12px; display:flex; gap:12px;">
                            <div style="flex:1;">
                                <label style="display:block; font-weight:600; margin-bottom:6px;">Role</label>
                                <select id="configRole" style="width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:6px;">
                                    <option value="admin">Admin</option>
                                    <option value="accounting">Accounting</option>
                                    <option value="operations">Operations</option>
                                </select>
                            </div>
                            <div style="width:160px;">
                                <label style="display:block; font-weight:600; margin-bottom:6px;">Status</label>
                                <select id="configStatus" style="width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:6px;">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div style="margin-top: 20px; display: flex; flex-wrap: wrap; gap: 12px;">
                            <button class="btn-delete-user" onclick="deleteUserFromConfig()" style="background: #d32f2f; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Delete User</button>
                            <div style="display: flex; gap: 12px; margin-left: auto;">
                                <button class="btn-cancel-config" onclick="closeUserConfig()" style="background: transparent; color: #888; border: 1px solid #ddd; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Cancel</button>
                                <button class="btn-save-config" onclick="saveUserConfig()" style="background: #c9a96e; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Save Changes</button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- ─── CONFIG ITEM MODAL (Add/Edit/Delete) ─── -->
    <div id="configItemModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        <div class="modal-container" style="background: #fff; width: 500px; max-width: 95%; border-radius: 16px; padding: 30px 35px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.4rem; font-weight: 600; color: #1a2b3c; margin: 0;" id="configItemModalTitle">Add New Item</h2>
                <button class="modal-close" onclick="closeConfigItemModal()" style="background: none; border: none; font-size: 2rem; cursor: pointer; color: #888; line-height: 1;">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="configItemId">
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">Name <span style="color: #d32f2f;">*</span></label>
                    <input type="text" id="configItemName" placeholder="Enter name" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa;">
                </div>
                <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; border-top: 1px solid #e9ecef; padding-top: 20px;">
                    <!-- Cancel on the LEFT -->
                    <button class="btn-cancel" onclick="closeConfigItemModal()" style="background: transparent; color: #888; border: 1px solid #ddd; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">Cancel</button>
                    <!-- Delete + Save on the RIGHT -->
                    <div style="display: flex; gap: 12px;">
                        <button class="btn-delete-config" id="deleteConfigBtn" onclick="deleteConfigItem()" style="display: none; background: #d32f2f; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Delete</button>
                        <button class="btn-save" onclick="saveConfigItem()" style="background: #c9a96e; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Save</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

                    <!-- ─── PASSWORD DISPLAY MODAL ─── -->
                    <div id="passwordModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
                        <div class="modal-container" style="background: #fff; width: 480px; max-width: 95%; border-radius: 12px; padding: 22px 24px; box-shadow: 0 20px 60px rgba(0,0,0,0.25);">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                                <div>
                                    <h3 style="margin:0; font-size:1.1rem;">User Created</h3>
                                    <div style="font-size:0.9rem; color:#666;">Temporary password (copy and share securely)</div>
                                </div>
                                <button onclick="closePasswordModal()" style="background:none; border:none; font-size:1.4rem; cursor:pointer; color:#888;">×</button>
                            </div>
                            <div style="margin-top:10px;">
                                <input id="generatedPasswordField" type="text" readonly style="width:100%; padding:12px 14px; font-size:1rem; border:1px solid #ddd; border-radius:8px; background:#f7f7f7;" />
                                <div style="display:flex; gap:8px; margin-top:12px; justify-content:flex-end;">
                                    <button onclick="copyPassword()" class="btn-save" style="background:#2b6cb0; color:#fff; border:none; padding:8px 14px; border-radius:8px; cursor:pointer;">Copy</button>
                                    <button onclick="closePasswordModal(true)" class="btn-save" style="background:#c9a96e; color:#fff; border:none; padding:8px 14px; border-radius:8px; cursor:pointer;">Done</button>
                                </div>
                            </div>
                        </div>
                    </div>

    <!-- ─── ADD USER MODAL ─── -->
    <div id="addUserModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        <div class="modal-container" style="background: #fff; width: 500px; max-width: 95%; border-radius: 16px; padding: 30px 35px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.4rem; font-weight: 600; color: #1a2b3c; margin: 0;">Add New User</h2>
                <button class="modal-close" onclick="closeAddUserModal()" style="background: none; border: none; font-size: 2rem; cursor: pointer; color: #888; line-height: 1;">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">Full Name <span style="color: #d32f2f;">*</span></label>
                    <input type="text" id="addUserName" placeholder="e.g. Juan Dela Cruz" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa;">
                </div>
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">Email Address <span style="color: #d32f2f;">*</span></label>
                    <input type="email" id="addUserEmail" placeholder="e.g. juan@evc-dcs.com" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa;">
                </div>
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">Role <span style="color: #d32f2f;">*</span></label>
                    <select id="addUserRole" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa;">
                        <option value="Admin">Admin</option>
                        <option value="Accounting">Accounting</option>
                        <option value="Operations">Operations</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 18px;">
                    <label style="display: block; font-size: 0.85rem; font-weight: 500; color: #333; margin-bottom: 4px;">Status <span style="color: #d32f2f;">*</span></label>
                    <select id="addUserStatus" style="width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 8px; font-size: 0.95rem; background: #fafafa;">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div style="margin-top: 20px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #e9ecef; padding-top: 20px;">
                    <button class="btn-cancel" onclick="closeAddUserModal()" style="background: transparent; color: #888; border: 1px solid #ddd; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">Cancel</button>
                    <button class="btn-save" onclick="saveNewUser()" style="background: #c9a96e; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Add User</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ─── SETTINGS NAVIGATION ───
        var csrfToken = '{{ csrf_token() }}';
        function switchSettings(el, section) {
            var navItems = document.querySelectorAll('.settings-nav li');
            navItems.forEach(function(item) {
                item.classList.remove('active');
            });
            el.classList.add('active');

            var sections = document.querySelectorAll('.settings-section');
            sections.forEach(function(sec) {
                sec.style.display = 'none';
            });

            var target = document.getElementById('section-' + section);
            if (target) {
                target.style.display = 'block';
            }

            closeUserConfig();
            closeConfigItemModal();
            closeAddUserModal();

            console.log('Switched to: ' + section);
        }

        // ─── TOGGLE SWITCH ───
        function toggleSwitch(el) {
            el.classList.toggle('active');
            var status = el.classList.contains('active') ? 'Enabled' : 'Disabled';
            console.log('Switch toggled: ' + status);
        }

        // ─── USER CONFIG (CRUD) ───
        var csrfToken = '{{ csrf_token() }}';
        var activeConfigUserId = null;

        function openUserConfig(id) {
            activeConfigUserId = id;
            fetch('/users/' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) { return res.json(); })
            .then(function(user) {
                document.getElementById('configUserName').textContent = user.name;
                document.getElementById('configUserRole').textContent = user.role ? user.role.charAt(0).toUpperCase() + user.role.slice(1) : 'User';
                document.getElementById('configRole').value = user.role || 'operations';
                document.getElementById('configName').value = user.name;
                document.getElementById('configEmail').value = user.email;
                document.getElementById('configStatus').value = (user.status ? user.status : 'Active');

                var roleEl = document.getElementById('configUserRole');
                roleEl.className = 'role-badge';
                if (user.role === 'admin') roleEl.classList.add('admin');
                else if (user.role === 'accounting') roleEl.classList.add('manager');
                else roleEl.classList.add('staff');

                var configDiv = document.getElementById('userConfigDetails');
                configDiv.style.display = 'block';
                configDiv.scrollIntoView({ behavior: 'smooth', block: 'start' });
            })
            .catch(function(err) {
                alert('Failed to load user data.');
                console.error(err);
            });
        }

        function closeUserConfig() {
            document.getElementById('userConfigDetails').style.display = 'none';
            activeConfigUserId = null;
        }

        function saveUserConfig() {
            if (!activeConfigUserId) return;
            var payload = {
                role: document.getElementById('configRole').value,
                status: document.getElementById('configStatus').value,
                _token: csrfToken
            };

            fetch('/users/' + activeConfigUserId, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    showSuccess('User updated successfully!');
                    setTimeout(function() { location.reload(); }, 700);
                } else {
                    alert('Failed to update user.');
                }
            })
            .catch(function(err) {
                alert('Failed to update user.');
                console.error(err);
            });
        }

        function deleteUserFromConfig() {
            if (!activeConfigUserId) return;
            if (!confirm('Are you sure you want to permanently delete this user?')) return;

            fetch('/users/' + activeConfigUserId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    showSuccess('User deleted');
                    setTimeout(function() { location.reload(); }, 700);
                } else {
                    alert('Failed to delete user.');
                }
            })
            .catch(function(err) {
                alert('Failed to delete user.');
                console.error(err);
            });
        }

        // ─── CONFIGURATIONS (Dropdown Management) ───
        var configData = {
            'units': [],
            'inv_categories': [],
            'exp_categories': []
        };

        var configFieldMap = {
            'units': { id: 'unit_id', name: 'unit_name' },
            'inv_categories': { id: 'inventory_category_id', name: 'inventory_category_name' },
            'exp_categories': { id: 'expense_category_id', name: 'category_name' }
        };

        var currentConfigType = 'units';

        function switchConfigType(el, type) {
            var btns = document.querySelectorAll('.config-tab');
            btns.forEach(function(btn) {
                btn.classList.remove('active');
            });
            el.classList.add('active');
            currentConfigType = type;
            fetchConfigItems(type);
        }

        function fetchConfigItems(type) {
            var tbody = document.getElementById('configTableBody');
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 16px;">Loading...</td></tr>';

            fetch('/api/config/' + type, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    configData[type] = data.data || [];
                    renderConfigTable();
                } else {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 16px; color: red;">Failed to load data.</td></tr>';
                }
            })
            .catch(function(err) {
                console.error(err);
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 16px; color: red;">Failed to load data.</td></tr>';
            });
        }

        function renderConfigTable() {
            var items = configData[currentConfigType] || [];
            var tbody = document.getElementById('configTableBody');
            tbody.innerHTML = '';

            if (!items.length) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 16px;">No items found.</td></tr>';
                return;
            }

            var fields = configFieldMap[currentConfigType];
            items.forEach(function(item) {
                var tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${item[fields.id]}</td>
                    <td><strong>${item[fields.name]}</strong></td>
                    <td style="text-align: center;">
                        <button class="btn-edit-user" onclick="openConfigEditModal(${item[fields.id]})">
                            <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function openConfigAddModal() {
            document.getElementById('configItemId').value = '';
            document.getElementById('configItemName').value = '';
            document.getElementById('configItemModalTitle').textContent = 'Add New Item';
            document.getElementById('deleteConfigBtn').style.display = 'none';
            document.getElementById('configItemModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function openConfigEditModal(id) {
            var items = configData[currentConfigType] || [];
            var fields = configFieldMap[currentConfigType];
            var item = items.find(function(i) { return i[fields.id] === id; });
            if (!item) return;
            document.getElementById('configItemId').value = id;
            document.getElementById('configItemName').value = item[fields.name];
            document.getElementById('configItemModalTitle').textContent = 'Edit Item';
            document.getElementById('deleteConfigBtn').style.display = 'inline-block';
            document.getElementById('configItemModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeConfigItemModal() {
            document.getElementById('configItemModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function saveConfigItem() {
            var id = document.getElementById('configItemId').value;
            var name = document.getElementById('configItemName').value.trim();
            if (!name) {
                alert('Please enter a name.');
                return;
            }

            var fields = configFieldMap[currentConfigType];
            var payload = {};
            payload[fields.name] = name;

            var url = '/api/config/' + currentConfigType;
            var method = 'POST';
            if (id) {
                url += '/' + id;
                method = 'PATCH';
            }

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    closeConfigItemModal();
                    showSuccess(data.message || 'Saved successfully!');
                    fetchConfigItems(currentConfigType);
                } else {
                    alert(data.message || 'Failed to save item.');
                }
            })
            .catch(function(err) {
                console.error(err);
                alert('Failed to save item.');
            });
        }

        function deleteConfigItem() {
            var id = document.getElementById('configItemId').value;
            if (!id) return;
            var name = document.getElementById('configItemName').value.trim();
            if (!confirm('Are you sure you want to permanently delete "' + name + '"?')) return;

            fetch('/api/config/' + currentConfigType + '/' + id, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    closeConfigItemModal();
                    showSuccess(data.message || 'Item deleted successfully!');
                    fetchConfigItems(currentConfigType);
                } else {
                    alert(data.message || 'Failed to delete item.');
                }
            })
            .catch(function(err) {
                console.error(err);
                alert('Failed to delete item.');
            });
        }

        // ─── SUCCESS NOTIFICATION ───
        function showSuccess(message) {
            var notif = document.getElementById('successNotification');
            var msgSpan = document.getElementById('successMessage');
            if (msgSpan) {
                msgSpan.textContent = message || 'Saved successfully!';
            }
            notif.style.display = 'block';
            setTimeout(function() {
                closeSuccess();
            }, 5000);
        }

        function closeSuccess() {
            document.getElementById('successNotification').style.display = 'none';
        }

        // ─── ADD USER MODAL ───
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            document.getElementById('addUserName').value = '';
            document.getElementById('addUserEmail').value = '';
            document.getElementById('addUserRole').value = 'Admin';
            document.getElementById('addUserStatus').value = 'Active';
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function saveNewUser() {
            var name = document.getElementById('addUserName').value.trim();
            var email = document.getElementById('addUserEmail').value.trim();
            var role = document.getElementById('addUserRole').value;
            var status = document.getElementById('addUserStatus').value;
            if (!name || !email) {
                alert('Please fill in all required fields.');
                return;
            }

            var payload = { name: name, email: email, role: role, status: status, _token: csrfToken };

            fetch('/users', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    closeAddUserModal();
                    // show generated password to admin in a modal so they can copy it
                    if (data.password) {
                        openPasswordModal(data.password, name);
                    }
                    showSuccess('New user ' + name + ' added successfully!');
                } else {
                    alert('Failed to add user.');
                }
            })
            .catch(function(err) {
                alert('Failed to add user.');
                console.error(err);
            });
        }

        // ─── CLOSE MODALS ON BACKDROP CLICK ───
        document.getElementById('addUserModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddUserModal();
            }
        });
        document.getElementById('configItemModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfigItemModal();
            }
        });
        document.getElementById('passwordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closePasswordModal();
            }
        });

        function openPasswordModal(password, username) {
            var field = document.getElementById('generatedPasswordField');
            if (field) field.value = password || '';
            var modal = document.getElementById('passwordModal');
            if (modal) {
                modal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }
        }

        function closePasswordModal(reload) {
            var modal = document.getElementById('passwordModal');
            if (modal) {
                modal.style.display = 'none';
                document.body.style.overflow = '';
            }
            if (reload) {
                location.reload();
            }
        }

        function copyPassword() {
            var field = document.getElementById('generatedPasswordField');
            if (!field) return;
            try {
                navigator.clipboard.writeText(field.value).then(function(){
                    alert('Password copied to clipboard. Share it securely.');
                }).catch(function(){
                    field.select();
                    document.execCommand('copy');
                    alert('Password copied to clipboard. Share it securely.');
                });
            } catch (e) {
                field.select();
                document.execCommand('copy');
                alert('Password copied to clipboard. Share it securely.');
            }
        }

        // Password modal element will be added to DOM

        // ─── CLOSE SUCCESS ON CLICK OUTSIDE ───
        document.addEventListener('click', function(e) {
            if (document.getElementById('successNotification').style.display === 'block') {
                if (!e.target.closest('.success-notification')) {
                    closeSuccess();
                }
            }
        });

        // ─── INIT ───
        fetchConfigItems(currentConfigType);
    </script>

</body>
</html>