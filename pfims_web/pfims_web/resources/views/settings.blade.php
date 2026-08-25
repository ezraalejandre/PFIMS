<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PFIMS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}">
    <script src="{{ asset('js/theme.js') }}"></script>
</head>
<body class="settings-page">

    <!-- ─── SUCCESS NOTIFICATION ─── -->
    <div id="successNotification" class="success-notification" style="display: none; z-index: 4000;">
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
                <span>{{ auth()->user()->name }}</span>
            </a>
        </div>
    </header>

    <!-- ─── SIDEBAR ─── -->
    <aside class="sidebar">
                <nav>
            <ul>
                <li><a href="{{ url('/dashboard') }}"><img src="{{ asset('images/dashboard.png') }}" alt="" class="nav-link-icon">DASHBOARD</a></li>
                <li><a href="{{ url('/projects') }}"><img src="{{ asset('images/projects.png') }}" alt="" class="nav-link-icon">PROJECTS</a></li>
                <li><a href="{{ url('/finance') }}"><img src="{{ asset('images/finance.png') }}" alt="" class="nav-link-icon">FINANCE</a></li>
                <li><a href="{{ url('/inventory') }}"><img src="{{ asset('images/inventory.png') }}" alt="" class="nav-link-icon">INVENTORY</a></li>
                <li><a href="{{ url('/suppliers') }}"><img src="{{ asset('images/suppliers.png') }}" alt="" class="nav-link-icon">SUPPLIERS</a></li>
                <li><a href="{{ url('/reports') }}"><img src="{{ asset('images/reports.png') }}" alt="" class="nav-link-icon">REPORTS</a></li>
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
                        <button class="btn-go-profile" data-url="{{ url('/profile') }}" onclick="window.location.href=this.dataset.url">Go to Profile</button>
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
                    @include('partials.login-history')
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

                <!-- ─── CONFIGURATIONS (Dropdown Management) ─── -->
                <div id="section-configurations" class="settings-section" style="display: none;">
                    <div class="section-title">Configurations</div>
                    <div class="section-desc">Manage the live values used by inventory and finance input forms.</div>

                    <div class="config-tabs">
                        <button class="config-tab active" onclick="switchConfigType(this, 'units')">Units</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'inv_categories')">Inventory Categories</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'exp_categories')">Expense Categories</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'suppliers')">Suppliers</button>
                    </div>

                    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                        <button class="btn-add-user" onclick="openConfigAddModal()">+ Add New</button>
                        <input type="search" id="configTableSearch" oninput="renderConfigTable()" placeholder="Search configurations..." style="min-width:240px;padding:9px 13px;border:1px solid #ddd;border-radius:8px;">
                    </div>

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
                                                <button class="btn-edit-user" data-user-id="{{ $u->id }}" onclick="openUserConfig(Number(this.dataset.userId))">
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
                                <button class="btn-save-config" onclick="saveUserConfig(this)" style="background: #c9a96e; color: #fff; border: none; padding: 10px 24px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Save Changes</button>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </main>

    <!-- ─── CONFIG ITEM MODAL (Add/Edit/Delete) ─── -->
    <div id="deleteUserConfirmModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:3000; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
        <div class="modal-container" style="background:#fff; width:440px; max-width:95%; border-radius:16px; padding:28px 32px; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="text-align:center;">
                <div style="width:58px; height:58px; margin:0 auto 16px; border-radius:50%; display:flex; align-items:center; justify-content:center; background:#ffebee; color:#d32f2f; font-size:28px; font-weight:700;">!</div>
                <h2 style="margin:0 0 10px; color:#1a2b3c; font-size:1.35rem;">Delete User?</h2>
                <p style="margin:0; color:#666; line-height:1.5;">Are you sure you want to permanently delete this user? This action cannot be undone.</p>
            </div>
            <div style="display:flex; justify-content:center; gap:12px; margin-top:26px;">
                <button type="button" class="btn-cancel" onclick="closeDeleteUserModal()" style="padding:10px 22px; border-radius:8px; border:1px solid #ddd; background:#fff; color:#666; font-weight:600; cursor:pointer;">Cancel</button>
                <button type="button" id="confirmDeleteUserBtn" onclick="confirmDeleteUser()" style="padding:10px 22px; border-radius:8px; border:0; background:#d32f2f; color:#fff; font-weight:600; cursor:pointer;">Delete User</button>
            </div>
        </div>
    </div>

    <div id="configItemModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        <div class="modal-container" style="background: #fff; width: 500px; max-width: 95%; border-radius: 16px; padding: 30px 35px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.4rem; font-weight: 600; color: #1a2b3c; margin: 0;" id="configItemModalTitle">Add New Item</h2>
                <button class="modal-close" onclick="closeConfigItemModal()" style="background: none; border: none; font-size: 2rem; cursor: pointer; color: #888; line-height: 1;">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="configItemId">
                <div id="configItemFields"></div>
                <div style="margin-top: 20px; display: flex; justify-content: space-between; align-items: center; gap: 12px; border-top: 1px solid #e9ecef; padding-top: 20px;">
                    <!-- Cancel on the LEFT -->
                    <button class="btn-cancel" onclick="closeConfigItemModal()" style="background: transparent; color: #888; border: 1px solid #ddd; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">Cancel</button>
                    <!-- Delete + Save on the RIGHT -->
                    <div style="display: flex; gap: 12px;">
                        <button class="btn-delete-config" id="deleteConfigBtn" onclick="deleteConfigItem()" style="display: none; background: #d32f2f; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Delete</button>
                        <button class="btn-save" onclick="saveConfigItem(this)" style="background: #c9a96e; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Save</button>
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
                                    <div style="font-size:0.9rem; color:#666;">Use the user's email without the @gmail.com as initial password.</div>
                                </div>
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
                    <button class="btn-save" onclick="saveNewUser(this)" style="background: #c9a96e; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Add User</button>
                </div>
            </div>
        </div>
    </div>

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

    <script>
                // ─── SETTINGS NAVIGATION ───
        var csrfToken = '{{ csrf_token() }}';

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

        function redirectToUserManagement() {
            window.location.href = '/settings?section=usermanagement';
        }

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

                function saveUserConfig(btn) {
            if (!activeConfigUserId) return;
            var payload = {
                role: document.getElementById('configRole').value,
                status: document.getElementById('configStatus').value,
                _token: csrfToken
            };

            setButtonLoading(btn, true, 'Saving...');

            fetch('/users/' + activeConfigUserId, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    showSuccess('User updated successfully!');
                    setTimeout(redirectToUserManagement, 700);
                } else {
                    alert('Failed to update user.');
                }
            })
            .catch(function(err) {
                alert('Failed to update user.');
                console.error(err);
            })
            .finally(function() {
                setButtonLoading(btn, false);
            });
        }

        function deleteUserFromConfig() {
            if (!activeConfigUserId) return;
            document.getElementById('deleteUserConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteUserModal() {
            document.getElementById('deleteUserConfirmModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function confirmDeleteUser() {
            if (!activeConfigUserId) return;
            var confirmButton = document.getElementById('confirmDeleteUserBtn');
            confirmButton.disabled = true;
            confirmButton.textContent = 'Deleting...';

            fetch('/users/' + activeConfigUserId, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    closeDeleteUserModal();
                    showSuccess('User deleted');
                    setTimeout(redirectToUserManagement, 700);
                } else {
                    alert('Failed to delete user.');
                }
            })
            .catch(function(err) {
                alert('Failed to delete user.');
                console.error(err);
            })
            .finally(function() {
                confirmButton.disabled = false;
                confirmButton.textContent = 'Delete User';
            });
        }

        document.getElementById('deleteUserConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteUserModal();
        });

        // ─── CONFIGURATIONS (Dropdown Management) ───
        var configData = {
            'units': [],
            'inv_categories': [],
            'exp_categories': [],
            'suppliers': []
        };

        var configFieldMap = {
            'units': { id: 'unit_id', name: 'unit_name' },
            'inv_categories': { id: 'inventory_category_id', name: 'inventory_category_name' },
            'exp_categories': { id: 'fin_category_id', name: 'category_name' },
            'suppliers': { id: 'supplier_id', name: 'supplier_name' }
        };
        var configMeta = {};

        var currentConfigType = 'units';

        function switchConfigType(el, type) {
            var btns = document.querySelectorAll('.config-tab');
            btns.forEach(function(btn) {
                btn.classList.remove('active');
            });
            el.classList.add('active');
            currentConfigType = type;
            document.getElementById('configTableSearch').value = '';
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
                    configMeta[type] = data.meta || null;
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
            var search = document.getElementById('configTableSearch').value.trim().toLowerCase();
            var tbody = document.getElementById('configTableBody');
            tbody.innerHTML = '';
            var fields = configFieldMap[currentConfigType];
            if (search) {
                items = items.filter(function(item) {
                    return String(item[fields.name] || '').toLowerCase().indexOf(search) !== -1;
                });
            }

            if (!items.length) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 16px;">No items found.</td></tr>';
                return;
            }

            items.forEach(function(item) {
                var tr = document.createElement('tr');
                var idCell = document.createElement('td');
                idCell.textContent = item[fields.id];
                var nameCell = document.createElement('td');
                var strong = document.createElement('strong');
                strong.textContent = item[fields.name];
                nameCell.appendChild(strong);
                var actionCell = document.createElement('td');
                actionCell.style.textAlign = 'center';
                var edit = document.createElement('button');
                edit.className = 'btn-edit-user';
                edit.type = 'button';
                edit.setAttribute('aria-label', 'Edit ' + item[fields.name]);
                edit.innerHTML = `<img src="{{ asset('images/edit.jpg') }}" alt="">`;
                edit.onclick = function() { openConfigEditModal(item[fields.id]); };
                actionCell.appendChild(edit);
                tr.append(idCell, nameCell, actionCell);
                tbody.appendChild(tr);
            });
        }

        function openConfigAddModal() {
            document.getElementById('configItemId').value = '';
            renderConfigFields(null);
            document.getElementById('configItemModalTitle').textContent = 'Add Configuration';
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
            renderConfigFields(item);
            document.getElementById('configItemModalTitle').textContent = 'Edit Configuration';
            document.getElementById('deleteConfigBtn').style.display = 'inline-block';
            document.getElementById('configItemModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeConfigItemModal() {
            document.getElementById('configItemModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function renderConfigFields(item) {
            var container = document.getElementById('configItemFields');
            var meta = configMeta[currentConfigType];
            var definitions = meta && meta.fields ? meta.fields : {};
            container.innerHTML = '';
            Object.keys(definitions).forEach(function(field) {
                var definition = definitions[field];
                var wrapper = document.createElement('div');
                wrapper.className = 'form-group';
                wrapper.style.marginBottom = '18px';
                var label = document.createElement('label');
                label.style.cssText = 'display:block;font-size:.85rem;font-weight:500;color:#333;margin-bottom:4px;';
                label.textContent = definition.label + (definition.required ? ' *' : '');
                var input;
                if (definition.type === 'select') {
                    input = document.createElement('select');
                    Object.keys(definition.options || {}).forEach(function(value) {
                        var option = document.createElement('option');
                        option.value = value;
                        option.textContent = definition.options[value];
                        input.appendChild(option);
                    });
                } else {
                    input = document.createElement('input');
                    input.type = 'text';
                    input.maxLength = definition.max || 255;
                }
                input.id = 'configField_' + field;
                input.dataset.field = field;
                input.required = !!definition.required;
                input.style.cssText = 'width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:.95rem;background:#fafafa;';
                var value = item && item[field] !== null && item[field] !== undefined ? item[field] : '';
                if (field === 'is_active' && value === '') value = '1';
                input.value = String(value === true ? 1 : (value === false ? 0 : value));
                wrapper.append(label, input);
                container.appendChild(wrapper);
            });
        }

        function saveConfigItem(btn) {
            var id = document.getElementById('configItemId').value;
            var payload = {};
            var invalid = false;
            document.querySelectorAll('#configItemFields [data-field]').forEach(function(input) {
                var value = input.value.trim();
                if (input.required && !value) invalid = true;
                payload[input.dataset.field] = value;
            });
            if (invalid) { alert('Complete every required configuration field.'); return; }

            var url = '/api/config/' + currentConfigType;
            var method = 'POST';
            if (id) {
                url += '/' + id;
                method = 'PATCH';
            }

            setButtonLoading(btn, true, 'Saving...');

            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
            .then(function(result) {
                var data = result.data;
                if (result.ok && data.success) {
                    closeConfigItemModal();
                    showSuccess(data.message || 'Saved successfully!');
                    fetchConfigItems(currentConfigType);
                } else {
                    var messages = data.errors ? Object.values(data.errors).flat().join('\n') : data.message;
                    alert(messages || 'Failed to save configuration.');
                }
            })
            .catch(function(err) {
                console.error(err);
                alert('Failed to save item.');
            })
            .finally(function() {
                setButtonLoading(btn, false);
            });
        }

        function deleteConfigItem() {
            var id = document.getElementById('configItemId').value;
            if (!id) return;
            var fields = configFieldMap[currentConfigType];
            var item = (configData[currentConfigType] || []).find(function(row) { return String(row[fields.id]) === String(id); });
            var name = item ? item[fields.name] : 'this configuration';
            if (!confirm('Are you sure you want to permanently delete "' + name + '"?')) return;

            var btn = document.getElementById('deleteConfigBtn');
            setButtonLoading(btn, true, 'Deleting...');

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
            })
            .finally(function() {
                setButtonLoading(btn, false);
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

                function saveNewUser(btn) {
            var name = document.getElementById('addUserName').value.trim();
            var email = document.getElementById('addUserEmail').value.trim();
            var role = document.getElementById('addUserRole').value;
            var status = document.getElementById('addUserStatus').value;
            if (!name || !email) {
                alert('Please fill in all required fields.');
                return;
            }

            var payload = { name: name, email: email, role: role, status: status, _token: csrfToken };

            setButtonLoading(btn, true, 'Adding...');

            fetch('/users', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json(); })
            .then(function(data) {
                if (data.success) {
                    closeAddUserModal();
                    // Show the initial-password instructions only after the user is created.
                    if (data.password) {
                        openPasswordModal(data.password, name);
                    }
                    showSuccess('New user added successfully!');
                } else {
                    alert('Failed to add user.');
                }
            })
            .catch(function(err) {
                alert('Failed to add user.');
                console.error(err);
            })
            .finally(function() {
                setButtonLoading(btn, false);
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
                redirectToUserManagement();
            }
        }

        function copyPassword() {
            var field = document.getElementById('generatedPasswordField');
            if (!field) return;
            try {
                navigator.clipboard.writeText(field.value).then(function(){
                    showSuccess('Password copied to clipboard. Share it securely.');
                }).catch(function(){
                    field.select();
                    document.execCommand('copy');
                    showSuccess('Password copied to clipboard. Share it securely.');
                });
            } catch (e) {
                field.select();
                document.execCommand('copy');
                showSuccess('Password copied to clipboard. Share it securely.');
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

        // ─── CHANGE PASSWORD FUNCTIONS ───

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
            var saveBtn = event.submitter || document.querySelector('#changePasswordForm .btn-save');
            
            // Clear previous errors
            document.getElementById('currentPasswordError').style.display = 'none';
            document.getElementById('newPasswordError').style.display = 'none';
            document.getElementById('confirmPasswordError').style.display = 'none';
            
            var currentPassword = document.getElementById('currentPassword').value;
            var newPassword = document.getElementById('newPassword').value;
            var confirmPassword = document.getElementById('confirmPassword').value;
            
            // Validate
            var hasError = false;
            
            if (newPassword.length < 8) {
                document.getElementById('newPasswordError').textContent = 'Password must be at least 8 characters long';
                document.getElementById('newPasswordError').style.display = 'block';
                hasError = true;
            }
            
            if (newPassword !== confirmPassword) {
                document.getElementById('confirmPasswordError').textContent = 'Passwords do not match';
                document.getElementById('confirmPasswordError').style.display = 'block';
                hasError = true;
            }
            
            if (hasError) {
                return;
            }
            
            // Send request to server
            var payload = {
                current_password: currentPassword,
                new_password: newPassword,
                new_password_confirmation: confirmPassword,
                _token: csrfToken
            };
            
                        setButtonLoading(saveBtn, true, 'Updating...');

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
                    if (data.message && data.message.includes('current password')) {
                        document.getElementById('currentPasswordError').textContent = data.message;
                        document.getElementById('currentPasswordError').style.display = 'block';
                    } else {
                        alert(data.message || 'Failed to update password. Please try again.');
                    }
                }
            })
            .catch(function(err) {
                console.error('Error:', err);
                alert('An error occurred. Please try again.');
            })
            .finally(function() {
                setButtonLoading(saveBtn, false);
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
            var query = new URLSearchParams(window.location.search);
            if (query.get('section') === 'usermanagement') {
                var userManagementTab = document.querySelector(".settings-nav li[onclick*='usermanagement']");
                if (userManagementTab) switchSettings(userManagementTab, 'usermanagement');
            } else if (query.get('change_password') === '1') {
                var securityTab = document.querySelector(".settings-nav li[onclick*='security']");
                if (securityTab) switchSettings(securityTab, 'security');
                openChangePasswordModal();
            }
        });
    </script>

</body>
</html>
