@php
    $portal = $portal ?? 'admin';
    $currentUser = auth()->user();
    $currentUserName = trim((string) ($currentUser?->name ?? 'User')) ?: 'User';
    $nameParts = preg_split('/\s+/u', $currentUserName, -1, PREG_SPLIT_NO_EMPTY);
    $currentUserInitials = mb_strtoupper(
        mb_substr($nameParts[0] ?? 'U', 0, 1).
        (count($nameParts) > 1 ? mb_substr($nameParts[count($nameParts) - 1], 0, 1) : '')
    );
    $currentUserRole = strtolower((string) ($currentUser?->role ?? ''));
    $currentUserRoleLabel = match ($currentUserRole) {
        'admin' => 'Administrator',
        'accounting' => 'Accounting',
        'operations' => 'Operations',
        default => 'User',
    };
    $isAdmin = $currentUserRole === 'admin';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PFIMS</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('css/settings.css') }}">
    <link rel="stylesheet" href="{{ asset('css/'.$portal.'.css') }}">
    <link rel="stylesheet" href="{{ asset('css/ui-refresh.css') }}?v={{ filemtime(public_path('css/ui-refresh.css')) }}">
    <script src="{{ asset('js/theme.js') }}?v={{ filemtime(public_path('js/theme.js')) }}"></script>
</head>
<body class="settings-page" data-portal="{{ $portal }}" data-is-admin="{{ $isAdmin ? '1' : '0' }}" data-config-api-base="{{ request()->getBaseUrl() }}/api/config">

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
                <span>{{ auth()->user()->name === 'Administrator' ? 'Admin' : auth()->user()->name }}</span>
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
                <li><a href="{{ url('/reports') }}"><img src="{{ asset('images/folder.svg') }}" alt="" class="nav-link-icon">REPORTS</a></li>
            </ul>
        </nav>
        <div class="bottom-nav">
            <ul>
                <li class="active">
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
                    @if($isAdmin)
                        <li onclick="switchSettings(this, 'configurations')">Configurations</li>
                    @endif
                    <li onclick="switchSettings(this, 'defaultfilters')">Default Filters</li>
                    <li onclick="switchSettings(this, 'notifications')">Notifications</li>
                    @if($isAdmin)
                        <li onclick="switchSettings(this, 'usermanagement')">User Management</li>
                    @endif
                </ul>
            </div>

            <!-- Settings Content -->
            <div class="settings-content">

                <!-- ─── PROFILE ─── -->
                <div id="section-profile" class="settings-section">
                    <div class="section-title">Profile</div>
                    <div class="section-desc">Manage your personal information and account settings.</div>

                    <div class="profile-preview">
                        <div class="avatar">{{ $currentUserInitials }}</div>
                        <div class="info">
                            <div class="name">{{ $currentUserName }}</div>
                            <div class="role">{{ $currentUserRoleLabel }}</div>
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
                @if($isAdmin)
                <div id="section-configurations" class="settings-section" style="display: none;">
                    <div class="section-title">Configurations</div>
                    <div class="section-desc">Manage operational thresholds and live form values. Project phase stages determine completion automatically.</div>

                    <div class="config-tabs">
                        <button class="config-tab active" onclick="switchConfigType(this, 'units')">Units</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'inv_categories')">Inventory Categories</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'exp_categories')">Expense Categories</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'project_phases')">Project Phases</button>
                        <button class="config-tab" onclick="switchConfigType(this, 'thresholds')">Thresholds</button>
                    </div>

                    <div id="configCrudPanel">
                        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
                            <button class="btn-add-user" onclick="openConfigAddModal()">+ Add New</button>
                            <input type="search" id="configTableSearch" oninput="renderConfigTable()" placeholder="Search configurations..." style="width:100%; max-width:320px; min-width:0; padding:9px 13px;border:1px solid #ddd;border-radius:8px;">
                        </div>

                        <div style="overflow-x: auto; margin-top: 15px;">
                            <table class="user-table" id="configTable">
                                <thead><tr><th>ID</th><th>Name</th><th id="configStageHeader" style="display:none;">Stage</th><th style="text-align: center;">Action</th></tr></thead>
                                <tbody id="configTableBody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div id="thresholdsPanel" class="system-limits-panel" style="display:none;padding:20px;border:1px solid #e4e8ef;border-radius:12px;background:#fff;">
                        <div class="section-title" style="font-size:1.05rem;">Operational thresholds</div>
                        <div class="section-desc" style="margin-bottom:16px;">Set when stock is considered low and when an active project automatically becomes at risk.</div>
                        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;">
                            <label style="display:flex;flex-direction:column;gap:6px;font-weight:600;">Low-stock reorder threshold
                                <input id="inventoryReorderThreshold" type="number" min="0" max="999999999.99" step="0.01" inputmode="decimal" aria-describedby="inventoryReorderThresholdHelp" style="padding:10px;border:1px solid #ddd;border-radius:8px;">
                                <small id="inventoryReorderThresholdHelp" style="font-weight:400;color:#6b7280;">This is the system-wide minimum. Higher item-specific reorder thresholds are still respected.</small>
                            </label>
                            <label style="display:flex;flex-direction:column;gap:6px;font-weight:600;">At-risk lead time (days)
                                <input id="projectAtRiskDays" type="number" min="0" max="365" step="1" inputmode="numeric" aria-describedby="projectAtRiskDaysHelp" style="padding:10px;border:1px solid #ddd;border-radius:8px;">
                                <small id="projectAtRiskDaysHelp" style="font-weight:400;color:#6b7280;">Active, non-completed projects automatically become At Risk this many days before their estimated end date.</small>
                            </label>
                            <label style="display:flex;flex-direction:column;gap:6px;font-weight:600;">Maximum transaction quantity
                                <input id="inventoryMaxTransactionQuantity" type="number" min="0.01" max="999999999999.99" step="0.01" inputmode="decimal" aria-describedby="inventoryMaxTransactionQuantityHelp" style="padding:10px;border:1px solid #ddd;border-radius:8px;">
                                <small id="inventoryMaxTransactionQuantityHelp" style="font-weight:400;color:#6b7280;">Prevents accidental oversized stock movements.</small>
                            </label>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-top:18px;"><button type="button" class="btn-save" id="saveSystemSettings" onclick="saveSystemSettings(this)" style="background:#c9a96e;color:#fff;border:0;padding:10px 20px;border-radius:8px;font-weight:600;cursor:pointer;">Save thresholds</button></div>
                    </div>
                </div>
                @endif

                <div id="section-defaultfilters" class="settings-section" style="display: none;">
                    <div class="section-title">Default Filters</div>
                    <div class="section-desc">Choose the filters that should be applied automatically whenever you open a module. You can temporarily change them on each page without changing these saved preferences.</div>
                    <div class="default-filter-config">
                        <label class="default-filter-module-field"><span>Module</span>
                            <select id="defaultFilterModule" onchange="renderDefaultFilterFields()">
                                <option value="dashboard">Dashboard</option>
                                <option value="projects">Projects</option>
                                <option value="finance.expenses">Finance — Expenses</option>
                                <option value="finance.budgets">Finance — Budgets</option>
                                <option value="finance.bonds">Finance — Construction Bonds</option>
                                <option value="inventory.items">Inventory — Items</option>
                                <option value="inventory.transactions">Inventory — Transactions</option>
                                <option value="suppliers">Suppliers</option>
                                <option value="reports">Reports</option>
                                <option value="analytics.material">Analytics — Material Projection</option>
                                <option value="analytics.budget">Analytics — Budget Comparison</option>
                            </select>
                        </label>
                        <div id="defaultFilterFields" class="default-filter-fields"></div>
                        <div class="default-filter-actions">
                            <button type="button" class="btn-cancel" onclick="removeDefaultFilters()">Remove saved defaults</button>
                            <button type="button" class="btn-save" onclick="saveDefaultFilters()">Save defaults</button>
                        </div>
                        <p id="defaultFilterStatus" class="default-filter-status" role="status" aria-live="polite"></p>
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
                @if($isAdmin)
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
                                                <button type="button" class="btn-config-action btn-view-user" data-user-id="{{ $u->id }}" aria-label="View {{ $u->name }}" onclick="openUserDetails(Number(this.dataset.userId))">View</button>
                                                <button type="button" class="btn-edit-user" data-user-id="{{ $u->id }}" aria-label="Edit {{ $u->name }}" onclick="openUserConfig(Number(this.dataset.userId))">
                                                    <img src="{{ asset('images/edit.jpg') }}" alt="Edit">
                                                </button>
                                                <button type="button" class="btn-delete-user table-delete-user" data-user-id="{{ $u->id }}" aria-label="Delete {{ $u->name }}" onclick="deleteUserById(Number(this.dataset.userId))">Delete</button>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                        </table>
                    </div>

                </div>
                @endif

            </div>
        </div>

    </main>

    <!-- ─── USER DETAILS MODAL ─── -->
    <div id="userDetailsModal" class="modal-overlay settings-user-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="userDetailsModalTitle">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="userDetailsModalTitle">User details</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeUserDetails()">×</button>
            </div>
            <div id="userDetailsFields" class="modal-body"></div>
            <div class="settings-modal-footer">
                <button type="button" class="btn-cancel" onclick="closeUserDetails()">Cancel</button>
                <button type="button" class="btn-save" onclick="editUserFromDetails()">Edit</button>
                <button type="button" class="btn-delete-user" onclick="deleteUserFromDetails()">Delete User</button>
            </div>
        </div>
    </div>

    <!-- ─── USER EDIT MODAL ─── -->
    <div id="userEditModal" class="modal-overlay settings-user-modal" style="display:none;" role="dialog" aria-modal="true" aria-labelledby="userEditModalTitle">
        <div class="modal-container">
            <div class="modal-header">
                <h2 id="userEditModalTitle">Edit user</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeUserEdit()">×</button>
            </div>
            <div class="modal-body">
                <div class="form-group"><label for="configName">Full Name</label><input type="text" id="configName"></div>
                <div class="form-group"><label for="configEmail">Email</label><input type="email" id="configEmail"></div>
                <div class="settings-user-fields">
                    <div class="form-group"><label for="configRole">Role</label><select id="configRole"><option value="admin">Admin</option><option value="accounting">Accounting</option><option value="operations">Operations</option></select></div>
                    <div class="form-group"><label for="configUserStatus">Status</label><select id="configUserStatus"><option value="Active">Active</option><option value="Inactive">Inactive</option></select></div>
                </div>
            </div>
            <div class="settings-modal-footer">
                <button type="button" class="btn-cancel" onclick="closeUserEdit()">Cancel</button>
                <button type="button" class="btn-save" onclick="saveUserConfig(this)">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- ─── CONFIG ITEM MODAL (Add/Edit) ─── -->
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

    <div id="configDeleteConfirmModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:3400; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
        <div class="modal-container" style="background:#fff; width:440px; max-width:95%; border-radius:16px; padding:28px 32px; box-shadow:0 20px 60px rgba(0,0,0,.3);">
            <div style="text-align:center;">
                <div style="width:58px;height:58px;margin:0 auto 16px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#ffebee;color:#d32f2f;font-size:28px;font-weight:700;">!</div>
                <h2 style="margin:0 0 10px;color:#1a2b3c;font-size:1.35rem;">Delete configuration?</h2>
                <p id="configDeleteMessage" style="margin:0;color:#666;line-height:1.5;">This action cannot be undone.</p>
            </div>
            <div style="display:flex;justify-content:center;gap:12px;margin-top:26px;">
                <button type="button" class="btn-cancel" onclick="closeConfigDeleteModal()">Cancel</button>
                <button type="button" id="confirmDeleteConfigBtn" class="btn-delete-config" onclick="confirmDeleteConfig()">Delete</button>
            </div>
        </div>
    </div>

    <div id="configDetailsModal" class="modal-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:3200; justify-content:center; align-items:center; backdrop-filter:blur(4px);">
        <div class="modal-container" style="background:#fff; width:500px; max-width:95%; border-radius:16px; padding:30px 35px; box-shadow:0 20px 60px rgba(0,0,0,.3); max-height:90vh; overflow-y:auto;">
            <div class="modal-header">
                <h2 id="configDetailsModalTitle">Configuration details</h2>
                <button type="button" class="modal-close" aria-label="Close" onclick="closeConfigDetailsModal()">×</button>
            </div>
            <div id="configDetailsFields" class="modal-body"></div>
            <div style="display:flex;justify-content:flex-end;gap:12px;border-top:1px solid #e9ecef;padding-top:20px;margin-top:20px;">
                <button type="button" class="btn-cancel" onclick="closeConfigDetailsModal()">Cancel</button>
                <button type="button" class="btn-delete-config" onclick="deleteConfigFromDetails()">Delete</button>
                <button type="button" class="btn-save" onclick="editConfigFromDetails()">Edit</button>
            </div>
        </div>
    </div>

    <div id="configItemModal" class="modal-overlay" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 3300; justify-content: center; align-items: center; backdrop-filter: blur(4px);">
        <div class="modal-container" style="background: #fff; width: 500px; max-width: 95%; border-radius: 16px; padding: 30px 35px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); max-height: 90vh; overflow-y: auto;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 1.4rem; font-weight: 600; color: #1a2b3c; margin: 0;" id="configItemModalTitle">Add New Item</h2>
                <button class="modal-close" onclick="closeConfigItemModal()" style="background: none; border: none; font-size: 2rem; cursor: pointer; color: #888; line-height: 1;">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="configItemId">
                <div id="configItemFields"></div>
                <div style="margin-top: 20px; display: flex; justify-content: flex-end; align-items: center; gap: 12px; border-top: 1px solid #e9ecef; padding-top: 20px;">
                    <button class="btn-cancel" onclick="closeConfigItemModal()" style="background: transparent; color: #888; border: 1px solid #ddd; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer;">Cancel</button>
                    <button class="btn-save" onclick="saveConfigItem(this)" style="background: #c9a96e; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; font-weight: 600; cursor: pointer; transition: 0.3s;">Save</button>
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
                        <option value="admin">Admin</option>
                        <option value="accounting">Accounting</option>
                        <option value="operations">Operations</option>
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
            closeUserDetails();
            closeConfigItemModal();
            closeConfigDetailsModal();
            closeConfigDeleteModal();
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

        function fetchUserConfig(id) {
            activeConfigUserId = id;
            return fetch('/users/' + id, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function(res) {
                if (!res.ok) throw new Error('Unable to load user');
                return res.json();
            });
        }

        function populateUserConfig(user) {
            document.getElementById('configRole').value = user.role || 'operations';
            document.getElementById('configName').value = user.name || '';
            document.getElementById('configEmail').value = user.email || '';
            document.getElementById('configUserStatus').value = user.status || 'Active';
        }

        function userRoleLabel(role) {
            return role ? role.charAt(0).toUpperCase() + role.slice(1) : 'User';
        }

        function openUserConfig(id) {
            closeUserDetails(false);
            fetchUserConfig(id).then(function(user) {
                populateUserConfig(user);
                document.getElementById('userEditModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }).catch(function(err) {
                alert('Failed to load user data.');
                console.error(err);
                activeConfigUserId = null;
            });
        }

        function openUserDetails(id) {
            closeUserEdit(false);
            fetchUserConfig(id).then(function(user) {
                var fields = document.getElementById('userDetailsFields');
                fields.innerHTML = '';
                [['Name', user.name], ['Email', user.email], ['Role', userRoleLabel(user.role)], ['Status', user.status || 'Active']].forEach(function(entry) {
                    var row = document.createElement('div');
                    row.className = 'settings-detail-row';
                    var label = document.createElement('strong');
                    label.textContent = entry[0];
                    var value = document.createElement('span');
                    value.textContent = entry[1] || '—';
                    row.append(label, value);
                    fields.appendChild(row);
                });
                document.getElementById('userDetailsModalTitle').textContent = 'View ' + (user.name || 'user');
                document.getElementById('userDetailsModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            }).catch(function(err) {
                alert('Failed to load user data.');
                console.error(err);
                activeConfigUserId = null;
            });
        }

        function closeUserDetails(clearActive) {
            document.getElementById('userDetailsModal').style.display = 'none';
            if (clearActive !== false) activeConfigUserId = null;
            restoreSettingsBodyOverflow();
        }

        function closeUserEdit() {
            document.getElementById('userEditModal').style.display = 'none';
            activeConfigUserId = null;
            restoreSettingsBodyOverflow();
        }

        function editUserFromDetails() {
            var id = activeConfigUserId;
            closeUserDetails(false);
            if (id) openUserConfig(id);
        }

        function deleteUserFromDetails() {
            if (!activeConfigUserId) return;
            closeUserDetails(false);
            deleteUserFromConfig();
        }

        function closeUserConfig() {
            closeUserEdit();
        }

                function saveUserConfig(btn) {
            if (!activeConfigUserId) return;
            var payload = {
                name: document.getElementById('configName').value.trim(),
                email: document.getElementById('configEmail').value.trim(),
                role: document.getElementById('configRole').value,
                status: document.getElementById('configUserStatus').value,
                _token: csrfToken
            };

            if (!payload.name || !payload.email) {
                alert('Name and email are required.');
                return;
            }

            setButtonLoading(btn, true, 'Saving...');

            fetch('/users/' + activeConfigUserId, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(payload)
            })
            .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
            .then(function(data) {
                if (data.ok && data.data.success) {
                    showSuccess('User updated successfully!');
                    setTimeout(redirectToUserManagement, 700);
                } else {
                    var message = data.data.errors ? Object.values(data.data.errors).flat().join('\n') : data.data.message;
                    alert(message || 'Failed to update user.');
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

        function deleteUserById(id) {
            if (!id) return;
            activeConfigUserId = id;
            deleteUserFromConfig();
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
            'exp_categories': []
            , 'project_phases': []
        };

        var configFieldMap = {
            'units': { id: 'unit_id', name: 'unit_name' },
            'inv_categories': { id: 'inventory_category_id', name: 'inventory_category_name' },
            'exp_categories': { id: 'fin_category_id', name: 'category_name' }
            , 'project_phases': { id: 'phase_id', name: 'phase_name' }
        };
        var configMeta = {};

        var currentConfigType = 'units';
        var configApiBase = document.body.dataset.configApiBase;
        var isAdmin = document.body.dataset.isAdmin === '1';

        function switchConfigType(el, type) {
            if (!isAdmin) return;
            var btns = document.querySelectorAll('.config-tab');
            btns.forEach(function(btn) {
                btn.classList.remove('active');
            });
            el.classList.add('active');
            currentConfigType = type;
            var isThresholds = type === 'thresholds';
            document.getElementById('configCrudPanel').style.display = isThresholds ? 'none' : 'block';
            document.getElementById('thresholdsPanel').style.display = isThresholds ? 'block' : 'none';
            if (isThresholds) {
                loadSystemSettings();
                return;
            }
            document.getElementById('configTableSearch').value = '';
            fetchConfigItems(type);
        }

        function fetchConfigItems(type) {
            if (!isAdmin) return;
            var tbody = document.getElementById('configTableBody');
            if (!tbody) return;
            tbody.innerHTML = '<tr><td colspan="3" style="text-align:center; padding: 16px;">Loading...</td></tr>';

            fetch(configApiBase + '/' + type, {
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
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
            if (!isAdmin) return;
            var items = configData[currentConfigType] || [];
            var search = document.getElementById('configTableSearch').value.trim().toLowerCase();
            var tbody = document.getElementById('configTableBody');
            if (!tbody) return;
            tbody.innerHTML = '';
            var fields = configFieldMap[currentConfigType];
            var showsStage = currentConfigType === 'project_phases';
            document.getElementById('configStageHeader').style.display = showsStage ? '' : 'none';
            if (search) {
                items = items.filter(function(item) {
                    return String(item[fields.name] || '').toLowerCase().indexOf(search) !== -1;
                });
            }

            if (!items.length) {
                tbody.innerHTML = '<tr><td colspan="' + (showsStage ? '4' : '3') + '" style="text-align:center; padding: 16px;">No items found.</td></tr>';
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
                var stageCell = null;
                if (showsStage) {
                    stageCell = document.createElement('td');
                    stageCell.textContent = item.stage_order;
                }
                var actionCell = document.createElement('td');
                actionCell.style.textAlign = 'center';
                actionCell.style.whiteSpace = 'nowrap';
                var view = document.createElement('button');
                view.className = 'btn-config-action btn-view-config';
                view.type = 'button';
                view.textContent = 'View';
                view.setAttribute('aria-label', 'View ' + item[fields.name]);
                view.onclick = function() { openConfigDetailsModal(item[fields.id]); };
                var edit = document.createElement('button');
                edit.className = 'btn-config-action btn-edit-config';
                edit.type = 'button';
                edit.textContent = 'Edit';
                edit.setAttribute('aria-label', 'Edit ' + item[fields.name]);
                edit.onclick = function() { openConfigEditModal(item[fields.id]); };
                var remove = document.createElement('button');
                remove.className = 'btn-config-action btn-delete-config';
                remove.type = 'button';
                remove.textContent = 'Delete';
                remove.setAttribute('aria-label', 'Delete ' + item[fields.name]);
                remove.onclick = function() { openConfigDeleteModal(item[fields.id]); };
                actionCell.appendChild(view);
                if (currentConfigType === 'project_phases') {
                    actionCell.append(edit, remove);
                }
                tr.append(idCell, nameCell);
                if (stageCell) tr.appendChild(stageCell);
                tr.appendChild(actionCell);
                tbody.appendChild(tr);
            });
        }

        function openConfigAddModal() {
            document.getElementById('configItemId').value = '';
            renderConfigFields(null);
            var stageInput = document.getElementById('configField_stage_order');
            if (stageInput) stageInput.value = String((configData.project_phases || []).length + 1);
            document.getElementById('configItemModalTitle').textContent = 'Add Configuration';
            document.getElementById('configItemModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function openConfigEditModal(id) {
            var item = findConfigItem(id);
            if (!item) return;
            document.getElementById('configItemId').value = id;
            renderConfigFields(item);
            document.getElementById('configItemModalTitle').textContent = 'Edit Configuration';
            document.getElementById('configItemModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeConfigItemModal() {
            document.getElementById('configItemModal').style.display = 'none';
            restoreSettingsBodyOverflow();
        }

        var activeConfigItemId = null;

        function findConfigItem(id) {
            var fields = configFieldMap[currentConfigType];
            return (configData[currentConfigType] || []).find(function(item) {
                return String(item[fields.id]) === String(id);
            });
        }

        function configValueLabel(definition, value) {
            return definition && definition.options && definition.options[String(value)] !== undefined
                ? definition.options[String(value)] : String(value === null || value === undefined ? '' : value);
        }

        function openConfigDetailsModal(id) {
            var item = findConfigItem(id);
            var meta = configMeta[currentConfigType];
            if (!item || !meta) return;
            activeConfigItemId = id;
            document.getElementById('configDetailsModalTitle').textContent = 'View ' + (item[meta.name] || 'configuration');
            var container = document.getElementById('configDetailsFields');
            container.innerHTML = '';
            Object.keys(meta.fields || {}).forEach(function(field) {
                var definition = meta.fields[field];
                var row = document.createElement('div');
                row.style.cssText = 'display:flex;justify-content:space-between;gap:18px;padding:11px 0;border-bottom:1px solid #f0ebe2;';
                var label = document.createElement('strong');
                label.textContent = definition.label;
                var value = document.createElement('span');
                value.textContent = configValueLabel(definition, item[field]);
                value.style.textAlign = 'right';
                row.append(label, value);
                container.appendChild(row);
            });
            document.getElementById('configDetailsModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeConfigDetailsModal() {
            document.getElementById('configDetailsModal').style.display = 'none';
            activeConfigItemId = null;
            restoreSettingsBodyOverflow();
        }

        function editConfigFromDetails() {
            var id = activeConfigItemId;
            closeConfigDetailsModal();
            if (id !== null) openConfigEditModal(id);
        }

        function deleteConfigFromDetails() {
            if (activeConfigItemId === null) return;
            openConfigDeleteModal(activeConfigItemId);
        }

        function openConfigDeleteModal(id) {
            var item = findConfigItem(id);
            if (!item) return;
            activeConfigItemId = id;
            var fields = configFieldMap[currentConfigType];
            document.getElementById('configDeleteMessage').textContent = 'Delete “' + item[fields.name] + '”? This action cannot be undone.';
            document.getElementById('configDetailsModal').style.display = 'none';
            document.getElementById('configDeleteConfirmModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeConfigDeleteModal() {
            document.getElementById('configDeleteConfirmModal').style.display = 'none';
            activeConfigItemId = null;
            restoreSettingsBodyOverflow();
        }

        function confirmDeleteConfig() {
            if (activeConfigItemId === null) return;
            var id = activeConfigItemId;
            var button = document.getElementById('confirmDeleteConfigBtn');
            setButtonLoading(button, true, 'Deleting...');
            fetch(configApiBase + '/' + currentConfigType + '/' + id, {
                credentials: 'same-origin', method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function(res) {
                return res.json().then(function(data) { return { ok: res.ok, data: data }; });
            }).then(function(result) {
                if (result.ok && result.data.success) {
                    closeConfigDeleteModal();
                    showSuccess(result.data.message || 'Configuration deleted successfully.');
                    fetchConfigItems(currentConfigType);
                } else {
                    alert(result.data.message || 'Failed to delete configuration.');
                }
            }).catch(function(error) {
                console.error(error);
                alert('Failed to delete configuration.');
            }).finally(function() { setButtonLoading(button, false); });
        }

        function restoreSettingsBodyOverflow() {
            var openModal = document.querySelector('.settings-page .modal-overlay[style*="display: flex"]');
            if (!openModal) document.body.style.overflow = '';
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
                    input.type = definition.type === 'number' ? 'number' : 'text';
                    if (definition.type === 'number') {
                        input.min = definition.min === undefined ? '0' : String(definition.min);
                        input.step = definition.step === undefined ? '1' : String(definition.step);
                    } else {
                        input.maxLength = definition.max || 255;
                    }
                    if (field === 'category_code') {
                        input.pattern = '[A-Za-z][A-Za-z0-9_ -]*';
                        input.title = 'Start with a letter; use letters, numbers, spaces, hyphens, or underscores.';
                        input.placeholder = 'e.g. CONST_SUPPLY';
                    }
                }
                input.id = 'configField_' + field;
                input.dataset.field = field;
                input.required = !!definition.required;
                input.style.cssText = 'width:100%;padding:10px 14px;border:1px solid #ddd;border-radius:8px;font-size:.95rem;background:#fafafa;';
                var value = item && item[field] !== null && item[field] !== undefined ? item[field] : '';
                if (field === 'is_active' && value === '') value = '1';
                input.value = String(value === true ? 1 : (value === false ? 0 : value));
                wrapper.append(label, input);
                if (field === 'category_code') {
                    var help = document.createElement('small');
                    help.textContent = 'Start with a letter. Use letters, numbers, spaces, hyphens, or underscores. The saved code is normalized to uppercase.';
                    help.style.cssText = 'display:block;margin-top:5px;color:#6b7280;font-size:.78rem;line-height:1.35;';
                    wrapper.appendChild(help);
                }
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
                if (value && input.pattern && !new RegExp('^(?:' + input.pattern + ')$').test(value)) invalid = true;
                payload[input.dataset.field] = value;
            });
            if (invalid) { alert('Complete every required field. Category codes must start with a letter and use only letters, numbers, spaces, hyphens, or underscores.'); return; }

            var url = configApiBase + '/' + currentConfigType;
            var method = 'POST';
            if (id) {
                url += '/' + id;
                method = 'PATCH';
            }

            setButtonLoading(btn, true, 'Saving...');

            fetch(url, {
                credentials: 'same-origin',
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

        function loadSystemSettings() {
            if (!isAdmin) return;
            fetch('/api/settings', { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
                .then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
                .then(function(result) {
                    if (!result.ok || !result.data.success) return;
                    var values = result.data.data || {};
                    var fields = {
                        inventory_reorder_threshold: 'inventoryReorderThreshold',
                        project_at_risk_days_before_end_date: 'projectAtRiskDays',
                        inventory_max_transaction_quantity: 'inventoryMaxTransactionQuantity'
                    };
                    Object.keys(fields).forEach(function(key) {
                        var input = document.getElementById(fields[key]);
                        if (input && values[key] !== undefined) input.value = values[key];
                    });
                }).catch(function(error) { console.error('Unable to load system settings.', error); });
        }

        function saveSystemSettings(button) {
            var payload = {
                inventory_reorder_threshold: document.getElementById('inventoryReorderThreshold').value,
                project_at_risk_days_before_end_date: document.getElementById('projectAtRiskDays').value,
                inventory_max_transaction_quantity: document.getElementById('inventoryMaxTransactionQuantity').value
            };
            setButtonLoading(button, true, 'Saving...');
            fetch('/api/settings', {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function(res) { return res.json().then(function(data) { return { ok: res.ok, data: data }; }); })
                .then(function(result) {
                    if (result.ok && result.data.success) showSuccess(result.data.message || 'System settings saved successfully.');
                    else alert(result.data.message || 'Unable to save system settings.');
                }).catch(function(error) { console.error(error); alert('Unable to save system settings.'); })
                .finally(function() { setButtonLoading(button, false); });
        }

        // ─── ADD USER MODAL ───
        function openAddUserModal() {
            document.getElementById('addUserModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            document.getElementById('addUserName').value = '';
            document.getElementById('addUserEmail').value = '';
            document.getElementById('addUserRole').value = 'admin';
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            document.body.style.overflow = '';
        }

                function saveNewUser(btn) {
            var name = document.getElementById('addUserName').value.trim();
            var email = document.getElementById('addUserEmail').value.trim();
            var role = document.getElementById('addUserRole').value;
            if (!name || !email) {
                alert('Please fill in all required fields.');
                return;
            }

            var payload = { name: name, email: email, role: role, _token: csrfToken };

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
        document.getElementById('configDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) closeConfigDetailsModal();
        });
        document.getElementById('configDeleteConfirmModal').addEventListener('click', function(e) {
            if (e.target === this) closeConfigDeleteModal();
        });
        document.getElementById('userDetailsModal').addEventListener('click', function(e) {
            if (e.target === this) closeUserDetails();
        });
        document.getElementById('userEditModal').addEventListener('click', function(e) {
            if (e.target === this) closeUserEdit();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            if (document.getElementById('configDeleteConfirmModal').style.display === 'flex') return closeConfigDeleteModal();
            if (document.getElementById('configDetailsModal').style.display === 'flex') return closeConfigDetailsModal();
            if (document.getElementById('configItemModal').style.display === 'flex') return closeConfigItemModal();
            if (document.getElementById('userEditModal').style.display === 'flex') return closeUserEdit();
            if (document.getElementById('userDetailsModal').style.display === 'flex') return closeUserDetails();
            if (document.getElementById('addUserModal').style.display === 'flex') return closeAddUserModal();
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
        if (isAdmin) fetchConfigItems(currentConfigType);

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
            loadSystemSettings();
            var query = new URLSearchParams(window.location.search);
            if (query.get('section') === 'defaultfilters') {
                var defaultFiltersTab = document.querySelector(".settings-nav li[onclick*='defaultfilters']");
                if (defaultFiltersTab) switchSettings(defaultFiltersTab, 'defaultfilters');
            } else if (query.get('section') === 'usermanagement') {
                var userManagementTab = document.querySelector(".settings-nav li[onclick*='usermanagement']");
                if (userManagementTab) switchSettings(userManagementTab, 'usermanagement');
            } else if (query.get('change_password') === '1') {
                var securityTab = document.querySelector(".settings-nav li[onclick*='security']");
                if (securityTab) switchSettings(securityTab, 'security');
                openChangePasswordModal();
            }
            loadDefaultFilters();
        });

        var savedDefaultFilters = {};
        var defaultFilterDefinitions = {
            dashboard: [['search','Search','search'],['status','Project status','select',['','Pending','On Track','At Risk','Delayed','Completed']],['stockStatus','Stock status','select',['','in_stock','low_stock','out_of_stock']]],
            projects: [['projectSearch','Search','search'],['projectStatusFilter','Status','select',['','Pending','On Track','At Risk','Delayed','Completed']],['projectPhaseFilter','Phase','select',['','Planning','Foundation','Structure','Finishing','Complete']],['projectDateFrom','From','date'],['projectDateTo','To','date']],
            'finance.expenses': [['projectSearch','Search','search'],['projectFilter','Project ID','number'],['expenseScopeFilter','Expense type','select',['all','direct','admin','overall']],['expenseRecordStatusFilter','Record status','select',['all','missing_amount','no_project','missing_amount_and_project']],['expenseSourceFilter','Expense source','select',['all','inventory','manual']],['expenseCategoryFilter','Category ID','number'],['expenseComponentFilter','Component','select',['all','material','labor','equipment','other']]],
            'finance.budgets': [['budgetSearch','Search','search'],['budgetProjectFilter','Project ID','number'],['budgetStatusFilter','Status','select',['all','On Track','Near Limit','Over Budget','No Budget']]],
            'finance.bonds': [['bondProjectFilter','Project ID','number'],['bondStatusFilter','Status','text']],
            'inventory.items': [['itemsSearchInput','Search','search'],['itemsCategoryFilter','Category ID','number'],['itemsSupplierFilter','Supplier ID','number'],['itemsStockFilter','Stock status','select',['all','in_stock','low_stock','out_of_stock']]],
            'inventory.transactions': [['searchInput','Search','search'],['typeFilter','Transaction type','select',['all','IN','OUT']],['transactionCategoryFilter','Category ID','number'],['transactionProjectFilter','Project ID','number'],['startDate','From','date'],['endDate','To','date']],
            suppliers: [['supplierSearch','Search','search'],['supplierSort','Sort order','select',['name','items','alerts']]],
            reports: [['filterSearch','Search','search'],['filterProject','Project ID','number'],['filterStatus','Status','text'],['filterClassification','Classification','text'],['filterCategory','Category ID','number'],['filterSupplier','Supplier ID','number'],['filterStockStatus','Stock status','text'],['filterStart','From','date'],['filterEnd','To','date']],
            'analytics.material': [['materialForecastSearch','Search','search'],['materialForecastStatus','Status','select',['','Healthy','Low Stock','Reorder Needed']]],
            'analytics.budget': [['budgetVarianceSearch','Search','search'],['budgetVarianceProject','Project ID','number'],['budgetVarianceStatus','Position','select',['','within','over']]]
        };

        function loadDefaultFilters() {
            fetch('/api/default-filters', { headers: { Accept: 'application/json' } })
                .then(function(response) { if (!response.ok) throw new Error('Unable to load defaults.'); return response.json(); })
                .then(function(data) { savedDefaultFilters = data || {}; renderDefaultFilterFields(); })
                .catch(function(error) { document.getElementById('defaultFilterStatus').textContent = error.message; });
        }

        function renderDefaultFilterFields() {
            var module = document.getElementById('defaultFilterModule').value;
            var values = savedDefaultFilters[module] || {};
            var host = document.getElementById('defaultFilterFields');
            host.replaceChildren();
            (defaultFilterDefinitions[module] || []).forEach(function(definition) {
                var label = document.createElement('label');
                var title = document.createElement('span');
                var input = definition[2] === 'select' ? document.createElement('select') : document.createElement('input');
                title.textContent = definition[1];
                if (input.tagName === 'INPUT') input.type = definition[2];
                else (definition[3] || []).forEach(function(value) {
                    var option = document.createElement('option');
                    option.value = value;
                    option.textContent = value || 'No default';
                    input.appendChild(option);
                });
                input.dataset.filterKey = definition[0];
                input.value = values[definition[0]] || '';
                if (input.tagName === 'INPUT') input.placeholder = 'Leave blank for no default';
                label.append(title, input);
                host.appendChild(label);
            });
            document.getElementById('defaultFilterStatus').textContent = Object.keys(values).length ? 'Saved defaults are active for this module.' : 'No defaults saved for this module.';
        }

        function saveDefaultFilters() {
            var module = document.getElementById('defaultFilterModule').value;
            var filters = {};
            document.querySelectorAll('#defaultFilterFields [data-filter-key]').forEach(function(input) {
                if (String(input.value).trim() !== '') filters[input.dataset.filterKey] = String(input.value).trim();
            });
            fetch('/api/default-filters/' + encodeURIComponent(module), {
                method: 'PUT', headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrfToken},
                body: JSON.stringify({filters: filters})
            }).then(function(response) { if (!response.ok) throw new Error('Unable to save defaults.'); return response.json(); })
              .then(function(data) { savedDefaultFilters[module] = data.filters || {}; renderDefaultFilterFields(); showSuccess('Default filters saved.'); })
              .catch(function(error) { document.getElementById('defaultFilterStatus').textContent = error.message; });
        }

        function removeDefaultFilters() {
            var module = document.getElementById('defaultFilterModule').value;
            fetch('/api/default-filters/' + encodeURIComponent(module), {method:'DELETE',headers:{'Accept':'application/json','X-CSRF-TOKEN':csrfToken}})
                .then(function(response) { if (!response.ok) throw new Error('Unable to remove defaults.'); delete savedDefaultFilters[module]; renderDefaultFilterFields(); showSuccess('Saved defaults removed.'); })
                .catch(function(error) { document.getElementById('defaultFilterStatus').textContent = error.message; });
        }
    </script>
    <script src="{{ asset('js/pfims-system-ui.js') }}?v={{ filemtime(public_path('js/pfims-system-ui.js')) }}"></script>
</body>
</html>
